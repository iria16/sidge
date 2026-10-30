<?php
// public/index.php
// Front Controller: unico punto de entrada del sistema.
// Gestiona sesiones, rutas, metodos HTTP, permisos, CSRF y ejecuta el controlador correspondiente.

// 1. CONFIGURACION Y SEGURIDAD DE LA SESION
// Segundos de inactividad permitidos antes de cerrar la sesion (1 hora).
$limiteInactividad = 3600;

// Tiempo tras el cual PHP puede borrar sesiones inactivas (es orientativo;
// el cierre real lo hace la comprobacion de mas abajo).
ini_set('session.gc_maxlifetime', (string) $limiteInactividad);

// Modo estricto: PHP rechaza IDs de sesion que el servidor no creo,
// asi nadie puede imponer un ID propio (fijacion de sesion).
ini_set('session.use_strict_mode', '1');

// Detecta HTTPS tambien detras de un proxy (hostings como InfinityFree),
// donde PHP no siempre recibe HTTPS = 'on'. Se usa en la marca Secure.
$https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
    || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

// lifetime 0: la cookie se borra al cerrar el navegador. httponly: JS no la lee.
// samesite Strict: no se envia desde otros sitios (frena CSRF). secure: solo por HTTPS.
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $https,
    'httponly' => true,
    'samesite' => 'Strict'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// DENY: ningun otro sitio puede mostrar el sistema en un iframe (clickjacking).
// nosniff: el navegador respeta el tipo de archivo declarado y no lo adivina.
// Referrer-Policy: las URLs internas no se filtran a otros sitios.
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

// HSTS: una vez visto por HTTPS, el navegador se niega a volver a HTTP.
// Solo se envia si la conexion actual es HTTPS.
if ($https) {
    header('Strict-Transport-Security: max-age=31536000');
}

// Si paso el limite sin actividad, se vacian los datos de la sesion y se cambia el ID.
// session_regenerate_id(true) tambien borra el archivo de la sesion vieja del servidor.
if (isset($_SESSION['ultima_actividad']) && (time() - $_SESSION['ultima_actividad']) > $limiteInactividad) {
    $_SESSION = [];
    session_regenerate_id(true);
}

// Solo quien inicio sesion renueva el reloj de actividad: asi los visitantes
// del login y los bots no dejan marcas ni sesiones innecesarias.
if (isset($_SESSION['usuario_id'])) {
    $_SESSION['ultima_actividad'] = time();
}

// Token CSRF de la sesion. Se crea aqui (despues del posible vaciado por
// inactividad) para que siempre exista al pintar un formulario.
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}


// 1.1 AYUDAS PARA PETICIONES AJAX Y CSRF
// SIGDEUI.enviar() (sigde-ui.js) manda X-Requested-With: XMLHttpRequest. Con esa cabecera
// se sabe que pide el JS y no el navegador, asi que se responde JSON y no un redirect o HTML.
function esPeticionAjax(): bool
{
    return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
}

// Token CSRF para las vistas (escapar siempre con htmlspecialchars al imprimirlo).
// En formularios: un input hidden con name="csrf" y como value el resultado de csrfToken().
// Para el JS: un meta name="csrf-token" en el head con el mismo valor, que sigde-ui.js
// lee y envia en la cabecera X-CSRF-Token.
function csrfToken(): string
{
    return (string) ($_SESSION['csrf'] ?? '');
}

if (!function_exists('e')) {
    /**
     * Helper de presentación para escapar salida HTML segura.
     */
    function e(mixed $valor): string
    {
        return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

// Responde un error o estado en JSON con la forma unificada del sistema (ok, success, mensaje, message, data, errors).
// $codigo es opcional: sigde-ui.js reconoce 'sesion_expirada' para enviar al login o 'csrf_invalido'.
function responderJson(int $status, string $mensaje, ?string $codigo = null, mixed $errors = null, mixed $data = null): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');

    $ok = ($status >= 200 && $status < 300);
    $respuesta = [
        'ok'      => $ok,
        'success' => $ok,
        'mensaje' => $mensaje,
        'message' => $mensaje,
        'data'    => $data,
        'errors'  => $errors,
    ];
    if ($codigo !== null) {
        $respuesta['codigo'] = $codigo;
    }

    echo json_encode($respuesta, JSON_UNESCAPED_UNICODE);
    exit;
}


// 2. DETECCION AUTOMATICA DE BASE_URL
// Deduce la carpeta desde donde se sirve el sistema (ej. /sidgev1/public/),
// para que funcione igual en local y en el hosting.
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
$scriptDir = rtrim(dirname($scriptName), '/');
$baseUrl = ($scriptDir === '/' || $scriptDir === '') ? '/' : $scriptDir . '/';
define('BASE_URL', $baseUrl);


// 3. OBTENCION Y LIMPIEZA DE LA URI
$uriSolicitada = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uriDecodificada = urldecode($uriSolicitada);

// Se quita BASE_URL solo si esta al inicio de la ruta (no en medio).
// Tambien se acepta la ruta sin barra final (/sidgev1/public).
$baseSinBarra = rtrim(BASE_URL, '/');

if ($baseSinBarra !== '' && ($uriDecodificada === $baseSinBarra || strpos($uriDecodificada, BASE_URL) === 0)) {
    $rutaRelativa = substr($uriDecodificada, strlen($baseSinBarra));
} else {
    $rutaRelativa = $uriDecodificada;
}

$rutaRelativa = '/' . ltrim($rutaRelativa, '/');


// 4. PREVENCION DE PATH TRAVERSAL
// Rechaza rutas con ../ o ..\ que intenten salir de las carpetas del sistema.
if (strpos($rutaRelativa, '../') !== false || strpos($rutaRelativa, '..\\') !== false) {
    http_response_code(400);
    die("<h1>400 - Solicitud invalida</h1>");
}


// 5. LIMPIEZA Y SEPARACION DE SEGMENTOS
// La ruta es /controlador/accion/parametros. Si esta vacia, se usa auth/login.
$uriLimpia = trim($rutaRelativa, '/');
$segmentos = $uriLimpia !== '' ? explode('/', $uriLimpia) : [];
$controllerR = $segmentos[0] ?? 'auth';
$actionR = $segmentos[1] ?? ($controllerR === 'auth' ? 'login' : 'index');
$parametrosRuta = array_slice($segmentos, 2);


// 6. SANEAMIENTO DE CONTROLADOR Y ACCION
// Solo se aceptan letras y numeros; cualquier otro caracter se rechaza.
$controller = ctype_alnum($controllerR) ? $controllerR : null;
$action = ctype_alnum($actionR) ? $actionR : null;

if ($controller === null || $action === null) {
    http_response_code(400);
    die("<h1>400 - Solicitud invalida</h1>");
}


// 7. MATRIZ DE RUTAS Y ROLES PERMITIDOS
// Lista blanca: controlador => accion => roles con permiso. 'publico' = sin login.
// Lo que no este aqui responde 404, aunque la clase y el metodo existan.
$rutasPermitidas = [
    'auth' => [
        'login' => ['publico'],
        'authenticate' => ['publico'],
        'logout' => ['admin', 'docente', 'coordinador', 'directivo', 'secretaria'],
        'cambiarPasswordProvisional' => ['admin', 'docente', 'coordinador', 'directivo', 'secretaria'],
        'updatePassword' => ['admin', 'docente', 'coordinador', 'directivo', 'secretaria'],
        'recoverAccess' => ['publico'],
        'requestRecovery' => ['publico']
    ],
    'dashboard' => [
        'index' => ['admin', 'docente', 'coordinador', 'directivo', 'secretaria'],
        'admin' => ['admin'],
        'docente' => ['docente'],
        'coordinador' => ['coordinador'],
        'directivo' => ['directivo'],
        'secretaria' => ['secretaria']
    ],
    'cuentaUsuario' => [
        'index' => ['admin'],
        'create' => ['admin'],
        'store' => ['admin'],
        'edit' => ['admin'],
        'update' => ['admin'],
        'toggleStatus' => ['admin'],
        'getDetalle' => ['admin']
    ],
    'bitacora' => [
        'index' => ['admin'],
        'exportar' => ['admin']
    ],
    'recuperacionAcceso' => [
        'index' => ['admin'],
        'getDetalle' => ['admin'],
        'updateEstado' => ['admin'],
        'resetPassword' => ['admin']
    ],
    'notificacion' => [
        'contador' => ['admin', 'secretaria', 'directivo'],
        'listar' => ['admin', 'secretaria', 'directivo'],
        'leer' => ['admin', 'secretaria', 'directivo'],
        'leerTodas' => ['admin', 'secretaria', 'directivo']
    ],
    'catalogo' => [
        'index' => ['admin'],
        'store' => ['admin'],
        'update' => ['admin'],
        'toggleStatus' => ['admin']
    ],
    'empleado' => [
        'index' => ['directivo', 'coordinador'],
        'expediente' => ['directivo', 'coordinador'],
        'exportar' => ['directivo', 'coordinador'],
        'estadoInfo' => ['directivo'],
        'cambiarEstado' => ['directivo'],
        'cerrarReposo' => ['directivo'],
        'anularReposo' => ['directivo'],
        'create' => ['directivo'],
        'show' => ['directivo', 'coordinador'],
        'edit' => ['directivo'],
        'store' => ['directivo'],
        'update' => ['directivo'],
        'toggleStatus' => ['directivo'],
        'getUbicacion' => ['directivo', 'coordinador'],
        // Reposos del personal (empleados y docentes). Registrarlos es una decisión de
        // la dirección, así que van como el resto del módulo: solo `directivo`.
        'registrarReposo' => ['directivo'],
        'updateReposo' => ['directivo'],
        'getReposos' => ['directivo', 'coordinador'],
        'guardarTitulo' => ['directivo'],
        'guardarInstitucion' => ['directivo']
    ],
    'academicYear' => [
        'index' => ['directivo'],
        'store' => ['directivo'],
        'update' => ['directivo'],
        'iniciar' => ['directivo'],
        'cerrar' => ['directivo'],
        // Solo la directiva: quien decide el ciclo del año es quien puede revertir un
        // cierre por error, y queda con su nombre y su motivo en la bitácora.
        'reabrir' => ['directivo']
    ],
    'academicStructure' => [
        'index' => ['coordinador', 'directivo'],
        'store' => ['coordinador', 'directivo'],
        'update' => ['coordinador', 'directivo'],
        // A cuántos alumnos movería la edición de una estructura (GET).
        // El frontend lo pregunta antes de enviar para poder pedir confirmación
        // con el número real, en vez de un texto genérico. Solo lectura.
        'impacto' => ['coordinador', 'directivo']
    ],
    'teacherAssignment' => [
        'index' => ['coordinador', 'directivo'],
        'asignar' => ['coordinador', 'directivo'],
        'actualizar' => ['coordinador', 'directivo'],
        'retirar' => ['coordinador', 'directivo'],
        'getDocentesPorEstructura' => ['coordinador', 'directivo']
    ],
    'withdrawal' => [
        'index' => ['directivo', 'secretaria'],
        // Detalle de una solicitud (GET withdrawal/ver/{id}). Lo consume el botón
        // "ver" del listado: devuelve SOLO el cuerpo del modal cuando viene por AJAX.
        'ver' => ['directivo', 'secretaria'],
        'buscarEstudiante' => ['secretaria'],
        'store' => ['secretaria'],
        'updateEstado' => ['directivo']
    ],
    'estudiantes' => [
        'index' => ['directivo', 'coordinador', 'secretaria', 'docente'],
        'show' => ['directivo', 'coordinador', 'secretaria', 'docente'],
        'exportar' => ['directivo', 'coordinador', 'secretaria', 'docente'],
        // Solo secretaria: marcar un recaudo como entregado es un asiento sobre un requisito
        // de matrícula, no una consulta. La ruta sola no basta, el turno se comprueba en el
        // controlador (secretariaTieneAccesoAEstudiante).
        'guardarRecaudos' => ['secretaria']
    ],
    'inscripciones' => [
        'index' => ['secretaria'],
        'store' => ['secretaria'],
        'ver' => ['secretaria'],
        'continuar' => ['secretaria'],
        'buscarPersona' => ['secretaria'],
        'guardarInstitucion' => ['secretaria'],
        'getUbicacion' => ['secretaria']
    ],
    'ratificacion' => [
        'index' => ['secretaria'],
        'ver' => ['secretaria'],
        'datosEstudiante' => ['secretaria'],
        'getUbicacion' => ['secretaria'],
        'buscarPersona' => ['secretaria'],
        // Secciones del grado en el año destino (JSON). La recarga el selector de
        // sección del modal cuando la secretaria cambia el grado.
        'secciones' => ['secretaria'],
        'ratificar' => ['secretaria'],
        'updateEstudiante' => ['secretaria', 'directivo', 'admin']
    ],
    'configuracionInstitucional' => [
        'index'              => ['admin'],
        'guardar'            => ['admin'],
        'subirImagen'        => ['admin'],
        'quitarImagen'       => ['admin'],
        'verPrivado'         => ['admin'],
        'restablecerOficial' => ['admin'],
    ],
    'constancias' => [
        'index'       => ['secretaria'],
        'estudio'     => ['secretaria'],
        'inscripcion' => ['secretaria'],
        'retiro'      => ['secretaria'],
        'prosecucion' => ['secretaria'],
        'verPrueba'   => ['secretaria'],
        'emitir'      => ['secretaria'],
        'emitirLote'  => ['secretaria'],
    ],
    'resultadoAcademico' => [
        'index'            => ['docente'],
        'seccion'          => ['docente'],
        'guardarPropuesta' => ['docente'],
    ],
    'certificadoCalificaciones' => [
        'generarIndividual' => ['docente'],
        'generarPorSeccion' => ['docente'],
    ],
    // Listados del docente (GET) y enlace de WhatsApp de su sección (POST). La sección sale de la sesión.
    'docente' => [
        'index'                 => ['docente'],
        'nomina'                => ['docente'],
        'seccion'               => ['docente'],
        'guardarEnlaceWhatsapp' => ['docente'],
    ],
    // Módulo de estadísticas oficiales (Directivo y Coordinador).
    'estadistica' => [
        'index' => ['directivo', 'coordinador'],
        'exportarHermanosExcel' => ['directivo', 'coordinador'],
        'exportarHermanosPdf' => ['directivo', 'coordinador'],
        'exportarMatriculaExcel' => ['directivo', 'coordinador'],
    ],
    // Perfil institucional de usuario (todos los roles autenticados).
    'perfil' => [
        'index'          => ['admin', 'docente', 'coordinador', 'directivo', 'secretaria'],
        'actualizarFoto' => ['admin', 'docente', 'coordinador', 'directivo', 'secretaria'],
    ],
];

// 7.1 ACCIONES QUE SOLO ACEPTAN POST
// Toda accion que crea, modifica, cierra o elimina datos va aqui. Con esto no se
// puede disparar escribiendo la URL ni desde un <img>/<a> en otro sitio (405).
// Si una accion nueva escribe en la BD y no se agrega aqui, queda abierta por GET.
$accionesSoloPost = [
    'auth' => ['authenticate', 'updatePassword', 'requestRecovery'],
    'cuentaUsuario' => ['store', 'update', 'toggleStatus'],
    'recuperacionAcceso' => ['updateEstado', 'resetPassword'],
    'catalogo' => ['store', 'update', 'toggleStatus'],
    'empleado' => ['store', 'update', 'toggleStatus', 'guardarTitulo', 'guardarInstitucion', 'registrarReposo', 'updateReposo', 'cambiarEstado', 'cerrarReposo', 'anularReposo'],
    'academicYear' => ['store', 'update', 'iniciar', 'cerrar', 'reabrir'],
    'academicStructure' => ['store', 'update'],
    'teacherAssignment' => ['asignar', 'actualizar', 'retirar'],
    'withdrawal' => ['store', 'updateEstado'],
    'notificacion' => ['leer', 'leerTodas'],
    'estudiantes' => ['guardarRecaudos'],
    'inscripciones' => ['store', 'guardarInstitucion'],
    'egresos' => ['graduar', 'graduarMasivo'],
    'ratificacion' => ['ratificar', 'updateEstudiante'],
    'configuracionInstitucional' => ['guardar', 'subirImagen', 'quitarImagen', 'restablecerOficial'],
    'resultadoAcademico' => ['guardarPropuesta', 'confirmar'],
    'docente' => ['guardarEnlaceWhatsapp'],
    'perfil' => ['actualizarFoto'],
];

if (!isset($rutasPermitidas[$controller][$action])) {
    http_response_code(404);
    die("<h1>404 - Ruta no encontrada</h1>");
}

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$exigePost = in_array($action, $accionesSoloPost[$controller] ?? [], true);

if ($exigePost && $metodo !== 'POST') {
    header('Allow: POST');

    if (esPeticionAjax()) {
        responderJson(405, 'Método no permitido.');
    }

    http_response_code(405);
    die("<h1>405 - Método no permitido</h1>");
}


// 8. MIDDLEWARE DE AUTENTICACION Y AUTORIZACION
$rolesPermitidos = $rutasPermitidas[$controller][$action];
$esRutaPublica = in_array('publico', $rolesPermitidos, true);

// Ruta privada sin sesion: el JS recibe 401 en JSON (y va al login);
// el navegador normal se redirige al login.
if (!$esRutaPublica && !isset($_SESSION['usuario_id'])) {
    if (esPeticionAjax()) {
        responderJson(401, 'Tu sesión expiró. Inicia sesión de nuevo.', 'sesion_expirada');
    }

    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

// Ruta privada con sesion: se verifica que el rol tenga permiso (403 si no).
if (!$esRutaPublica) {
    $rolUsuario = $_SESSION['rol'] ?? null;
    if (!in_array($rolUsuario, $rolesPermitidos, true)) {
        if (esPeticionAjax()) {
            responderJson(403, 'No tienes permiso para realizar esta acción.');
        }

        http_response_code(403);
        die("<h1>403 - Acceso denegado</h1>");
    }

    // Con clave provisional solo se puede cambiarla o cerrar sesion; cualquier otra
    // ruta redirige al cambio. Asi no se salta escribiendo la URL a mano.
    $accionesPermitidasProvisional = ['cambiarPasswordProvisional', 'updatePassword', 'logout'];

    if (!empty($_SESSION['password_provisional'])
        && !($controller === 'auth' && in_array($action, $accionesPermitidasProvisional, true))) {
        if (esPeticionAjax()) {
            responderJson(403, 'Debes cambiar tu contraseña provisional.', 'password_provisional');
        }

        header('Location: ' . BASE_URL . 'auth/cambiarPasswordProvisional');
        exit;
    }

    // Las paginas privadas no se guardan en cache: sin esto, el boton "atras"
    // podria mostrar contenido privado despues de cerrar sesion.
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
}

// Ruta publica con sesion iniciada: no tiene sentido ver el login, va al dashboard.
// Si la peticion es auth/authenticate (POST), se permite para que procese el nuevo login.
if ($esRutaPublica && isset($_SESSION['usuario_id']) && !($controller === 'auth' && $action === 'authenticate')) {
    $destino = 'dashboard/index';
    header('Location: ' . BASE_URL . $destino);
    exit;
}


// 8.1 VERIFICACION CSRF
// Todo POST en rutas privadas debe traer el token de la sesion, en el campo 'csrf' (formularios)
// o en la cabecera X-CSRF-Token (AJAX). Va despues de la autenticacion para que
// una sesion expirada responda 'sesion_expirada' y no un error de token.
// hash_equals compara en tiempo constante (evita ataques por temporizacion).
// En rutas publicas de autenticacion no se bloquea si el usuario aun no ha iniciado sesion.
if ($metodo === 'POST' && !$esRutaPublica) {
    $esperado = (string) ($_SESSION['csrf'] ?? '');
    $recibido = (string) ($_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));

    if ($esperado === '' || $recibido === '' || !hash_equals($esperado, $recibido)) {
        if (esPeticionAjax()) {
            responderJson(403, 'Tu sesión de formulario venció. Recarga la página e inténtalo de nuevo.', 'csrf_invalido');
        }

        http_response_code(403);
        die("<h1>403 - Token de seguridad inválido</h1>");
    }
}


// 9. INYECCION DE PARAMETROS EN $_GET
// El primer parametro de la ruta queda en $_GET['id'], los siguientes en param2, param3...
// Se guardan sin escapar: htmlspecialchars se aplica al imprimir en la vista.
if (!empty($parametrosRuta)) {
    foreach ($parametrosRuta as $indice => $valor) {
        $nombreVariable = ($indice === 0) ? 'id' : 'param' . ($indice + 1);
        $_GET[$nombreVariable] = $valor;
    }
}


// 10. AUTOLOADER CON NAMESPACES Y COMPOSER VENDOR
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// Convierte App\Controllers\XController en app/Controllers/XController.php
// y lo carga solo cuando se necesita, sin require manuales.
spl_autoload_register(function ($clase) {
    $prefijo = 'App\\';
    $dir_base = __DIR__ . '/../app/';
    $longitud = strlen($prefijo);

    if (strncmp($prefijo, $clase, $longitud) !== 0) {
        return;
    }

    $clase_relativa = substr($clase, $longitud);
    $archivo = $dir_base . str_replace('\\', '/', $clase_relativa) . '.php';

    if (file_exists($archivo)) {
        require $archivo;
    }
});


// 11. CARGA Y EJECUCION DEL CONTROLADOR
$mapaControladores = [
    'bitacora' => 'AuditLog',
    'catalogo' => 'Catalog',
    'certificadoCalificaciones' => 'GradeCertificate',
    'configuracionInstitucional' => 'InstitutionalConfig',
    'constancias' => 'Certificates',
    'cuentaUsuario' => 'UserAccount',
    'docente' => 'Teacher',
    'empleado' => 'Employee',
    'estadistica' => 'Statistics',
    'estudiantes' => 'Students',
    'inscripciones' => 'Enrollment',
    'notificacion' => 'Notification',
];
$nombreControladorClase = $mapaControladores[$controller] ?? ucfirst($controller);
$nombreClaseControlador = 'App\\Controllers\\' . $nombreControladorClase . 'Controller';

if (!class_exists($nombreClaseControlador)) {
    // El detalle va al log del servidor, no a la pantalla: mostrar el nombre
    // de la clase revelaria como esta organizado el codigo.
    error_log("[front controller] Falta el controlador: {$nombreClaseControlador}");

    if (esPeticionAjax()) {
        responderJson(500, 'Error interno del servidor.');
    }

    http_response_code(500);
    die("<h1>500 - Error interno</h1><p>Ocurrió un problema. Intenta de nuevo más tarde.</p>");
}

// Rate limiting basico por IP para endpoints sensibles (sin BD).
$limitesSensibles = [
    'auth/requestRecovery' => [5, 900],
    'withdrawal/store' => [20, 60],
    'inscripciones/store' => [20, 60],
    'empleado/store' => [20, 60],
    'catalogo/store' => [30, 60],
    'catalogo/update' => [30, 60],
    'configuracionInstitucional/subirImagen' => [20, 60],
    'configuracionInstitucional/quitarImagen' => [20, 60],
];
$claveLimite = $controller . '/' . $action;
if (isset($limitesSensibles[$claveLimite])) {
    [$maxIntentos, $ventana] = $limitesSensibles[$claveLimite];
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'desconocida');
    $archivoLimite = __DIR__ . '/../storage/rate_limit/' . md5($ip . '|' . $claveLimite) . '.json';
    $ahora = time();
    $datos = ['inicio' => $ahora, 'intentos' => 0];
    if (is_file($archivoLimite)) {
        $leido = json_decode((string) file_get_contents($archivoLimite), true);
        if (is_array($leido) && isset($leido['inicio'], $leido['intentos'])) {
            $datos = $leido;
            if ($ahora - (int) $datos['inicio'] > $ventana) {
                $datos = ['inicio' => $ahora, 'intentos' => 0];
            }
        }
    }
    $datos['intentos'] = (int) $datos['intentos'] + 1;
    @file_put_contents($archivoLimite, json_encode($datos));
    if ((int) $datos['intentos'] > $maxIntentos) {
        http_response_code(429);
        if (esPeticionAjax()) {
            responderJson(false, null, 'Demasiadas solicitudes. Intente de nuevo en unos minutos.', 429);
        }
        die('<h1>429 - Demasiadas solicitudes</h1>');
    }
}

$instanciaControlador = new $nombreClaseControlador();

// is_callable y no method_exists: este ultimo tambien da true para metodos
// protected o private, y llamarlos desde aqui seria un error fatal.
if (!is_callable([$instanciaControlador, $action])) {
    http_response_code(404);
    die("<h1>404 - Recurso no encontrado</h1>");
}

$instanciaControlador->$action();
