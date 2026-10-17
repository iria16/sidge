<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\BitacoraModel;
use App\Models\EstudianteModel;
use App\Models\TeacherAssignmentModel;
use App\Support\Configuracion;
use App\Support\NominaPdf;
use App\Traits\TextoTrait;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Throwable;

/* `sigde_rol_clave()` vive en App\Traits (ver SecurityTrait) y el import de
   funciones es de alcance de archivo: sin esta línea, la llamada caería al
   espacio global, donde esa función no existe, y reventaría con
   "Call to undefined function". BaseController hace lo mismo. */
use function App\Traits\sigde_rol_clave;

/**
 * Módulo del docente: inicio, nómina de su sección y ficha del estudiante.
 *
 * Regla que atraviesa toda la clase: la sección NO viaja desde el cliente. Se
 * resuelve en el servidor con la sesión del usuario, una vez por acción, en
 * `seccionActiva()`. Ninguna acción acepta un id de sección por GET o POST, así
 * que no hay forma de ver la sección de otro docente escribiendo la URL.
 *
 * El rol se valida en cada acción, no solo en las rutas: `public/index.php` ya
 * restringe `/docente/*` al rol docente, pero el Controller no confía en esa
 * tabla y lo comprueba otra vez. Si alguien invocara la clase por otro camino,
 * esta comprobación es la que lo detiene.
 *
 * Lo que este módulo NO consulta nunca: `ficha_medica`, `retiro` y
 * `motivo_retiro`. No hay ninguna acción que las nombre, de modo que esa
 * información no puede aparecer por accidente en una pantalla del docente.
 */
class DocenteController extends BaseController
{
    use TextoTrait;

    protected string $routeName = 'docente';
    protected string $viewPath = 'docente';

    /* Caché de la sección resuelta: las acciones la piden más de una vez y la
       consulta es la misma. Es por petición, no se comparte entre peticiones. */
    private ?array $seccionCache = null;
    private bool $seccionResuelta = false;

    protected function createModel(PDO $pdo): ?object
    {
        return new EstudianteModel($pdo);
    }

    /**
     * Sección del docente en el año en curso, o null si no tiene ninguna.
     *
     * Parte de `$_SESSION['usuario_id']`, nunca de un parámetro de la petición.
     */
    private function seccionActiva(): ?array
    {
        if ($this->seccionResuelta) {
            return $this->seccionCache;
        }
        $this->seccionResuelta = true;

        $idUsuario = $this->idUsuarioActual() ?? 0;
        if ($idUsuario <= 0) {
            return $this->seccionCache = null;
        }

        try {
            $this->seccionCache = (new TeacherAssignmentModel($this->conexion()))
                ->getSeccionActivaDeDocente($idUsuario);
        } catch (Throwable $e) {
            error_log('DocenteController::seccionActiva(): ' . $e->getMessage());
            $this->seccionCache = null;
        }

        return $this->seccionCache;
    }

    /** Corta el acceso si el rol de la sesión no es docente. */
    private function exigirDocente(): void
    {
        if (sigde_rol_clave($_SESSION['rol'] ?? '') !== 'docente') {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }
    }

    /** Datos que todas las vistas necesitan para el encabezado de contexto. */
    private function datosComunes(): array
    {
        $seccion = $this->seccionActiva();

        return [
            'seccion'     => $seccion,
            'tieneSeccion' => $seccion !== null,
            'idEstructura' => (int) ($seccion['id_estructura_academica'] ?? 0),
            'seccionTexto' => $seccion
                ? $seccion['grado'] . ' · ' . $seccion['seccion'] . ' · ' . $seccion['turno']
                : '',
        ];
    }

    /* ======================================================================
       /docente/nomina  ·  Listados de solo lectura (pantalla, impresión y PDF)
       ====================================================================== */

    /**
     * Vistas permitidas al docente y las columnas de cada una. Es la lista
     * blanca: cualquier otro valor de ?vista= se rechaza con 422.
     */
    /**
     * Pestañas de "Listados y Actas". El ORDEN del arreglo es el ORDEN de las
     * pestañas en pantalla, así que va en el orden en que se leen:
     * Nómina, Contacto, Direcciones, Cumpleañeros, Autorizados, Acta.
     */
    private const VISTAS_NOMINA = [
        'nomina' => [
            'etiqueta' => 'Nómina',
            'titulo'   => 'Nómina de estudiantes',
            'columnas' => ['n', 'cedula', 'estudiante', 'sexo', 'fecha_nac', 'edad'],
            'filtros'  => ['sexo', 'q'],
        ],
        'contacto' => [
            'etiqueta' => 'Contacto',
            'titulo'   => 'Directorio de contactos',
            'columnas' => ['estudiante', 'representante', 'telefonos_rep'],
            'filtros'  => ['q'],
        ],
        /* El domicilio del representante tiene su propia vista y no una columna fija
           en el directorio. Es un dato que se consulta pocas veces y que ocupa
           bastante ancho: dejarlo siempre visible empujaba las columnas útiles
           fuera de la pantalla. Quien lo necesita entra por el chip. */
        'direcciones' => [
            'etiqueta' => 'Direcciones',
            'titulo'   => 'Direcciones de los representantes',
            'columnas' => ['estudiante', 'representante', 'direccion_rep'],
            'filtros'  => ['q'],
        ],
        'cumpleaneros' => [
            'etiqueta' => 'Cumpleañeros',
            'titulo'   => 'Cumpleañeros del mes',
            'columnas' => ['dia', 'estudiante', 'edad_cumple'],
            'filtros'  => ['mes', 'q'],
        ],
        /* Quién, además del representante legal, está autorizado a retirar al
           alumno. Tiene su propia vista y no cuelga del directorio de contactos:
           la mayoría de los alumnos no tiene a nadie, y una columna fija con eso
           en casi todas las filas solo servía para empujar las demás.
           No repite el representante legal: ya está en la pestaña Contacto, y
           aquí solo es ruido al lado del nombre del alumno. */
        'autorizados' => [
            'etiqueta' => 'Autorizados para retiro',
            'titulo'   => 'Personas autorizadas para retirar a cada alumno',
            'columnas' => ['estudiante', 'autorizado', 'parentesco_aut', 'telefono_aut'],
            'filtros'  => ['q'],
        ],
        'acta' => [
            /* Sale como pestaña, igual que las demás. Antes vivía en un modal que
               se abría desde el botón del encabezado; ahora es una vista más de
               la lista: se abre en su propia pestaña y su contenido se ve de
               entrada, sin un clic de más. El motivo y la fecha que pide el
               documento se escriben al pie de esa misma pestaña.

               No tiene filtros a propósito: es un documento, no una lista que se
               pueda filtrar, así que la barra se queda solo con el buscador
               apagado y sin selectores. */
            'etiqueta' => 'Acta de firma',
            'titulo'   => 'Acta de asistencia de representantes',
            'columnas' => ['n', 'estudiante', 'representante', 'firma'],
            'filtros'  => [],
        ],
    ];

    /** Encabezado visible de cada columna (pantalla y PDF). */
    private const COLUMNAS_NOMINA = [
        'n'               => 'N°',
        'cedula'          => 'Cédula escolar',
        'estudiante'      => 'Apellidos y nombres',
        'sexo'            => 'Sexo',
        'fecha_nac'       => 'Fecha de nacimiento',
        'edad'            => 'Edad',
        'representante'   => 'Representante legal',
        'cedula_rep'      => 'C.I. representante',
        'firma'           => 'Firma',
        'telefonos_rep'   => 'Teléfonos',
        'direccion_rep'   => 'Dirección',
        'autorizado'      => 'Persona autorizada',
        'parentesco_aut'  => 'Parentesco',
        'telefono_aut'    => 'Teléfono',
        'dia'             => 'Día',
        'edad_cumple'     => 'Cumple',
    ];

    private const FORMATOS_NOMINA = ['html', 'pdf'];

    public const MESES = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
        7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];

    public function nomina(): void
    {
        $this->exigirDocente();

        $vista   = $this->parametroLista('vista', array_keys(self::VISTAS_NOMINA), 'nomina');
        $formato = $this->parametroLista('formato', self::FORMATOS_NOMINA, 'html');

        /* El acta es una vista más de la lista: llega por ?vista=acta y se pinta
           en su propia pestaña, con el motivo y la fecha al pie. Antes la
           devolvíamos a la Nómina y la abríamos en un modal; ahora se ve de
           entrada. El PDF sigue saliendo por la misma ruta, con ?formato=pdf. */
        $filtros = $this->filtrosNomina($vista);

        /* La sección sale de la sesión (asignación vigente del año activo).
           Ningún ?id=, ?seccion= ni campo de formulario participa aquí. */
        $base         = $this->datosComunes();
        $seccion      = $base['seccion'];
        $tieneSeccion = $base['tieneSeccion'];

        $definicion = self::VISTAS_NOMINA[$vista];
        $columnas   = [];
        foreach ($definicion['columnas'] as $clave) {
            $columnas[$clave] = self::COLUMNAS_NOMINA[$clave];
        }

        $filas = [];
        if ($tieneSeccion) {
            try {
                $filas = $this->getModel()->nominaSeccion(
                    $base['idEstructura'],
                    $filtros + ['vista' => $vista]
                );
            } catch (Throwable $e) {
                error_log('DocenteController::nomina(): ' . $e->getMessage());
                $filas = [];
            }
        }

        $config      = Configuracion::getAll($this->conexion());
        $ahoraCaracas = new DateTimeImmutable('now', new DateTimeZone('America/Caracas'));
        $fechaHoyIso = $ahoraCaracas->format('Y-m-d');
        $horaActual  = (int) $ahoraCaracas->format('G');

        /* Motivo y fecha con los que se pinta el formulario de la pestaña del
           acta. Se leen de la URL solo para que al recargar con ?motivo= y
           ?fecha= no se pierda lo que el docente ya escribió; si no vienen o
           no tienen forma de fecha, la fecha cae en el día de hoy. El motivo
           NO se sanea aquí a propósito: la validación fuerte vive en el PDF
           (datosActaPdf) y docente.js avisa antes de generar. Prefijarlo solo
           cuando el motivo se ve entero evita que un texto a medias acabe
           guardado. */
        $motivoActa = '';
        if (isset($_GET['motivo']) && is_string($_GET['motivo'])) {
            $motivoCrudo = trim($_GET['motivo']);
            if (mb_strlen($motivoCrudo, 'UTF-8') <= 120 && !preg_match('/[<>{}\[\]]/', $motivoCrudo)) {
                $motivoActa = $motivoCrudo;
            }
        }
        $fechaActa = $fechaHoyIso;
        if (isset($_GET['fecha']) && is_string($_GET['fecha']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['fecha'])) {
            $fechaActa = $_GET['fecha'];
        }
        $saludoWa    = match (true) {
            $horaActual < 12 => 'Buen día',
            $horaActual < 19 => 'Buenas tardes',
            default          => 'Buenas noches',
        };

        if ($formato === 'pdf') {
            if (!$tieneSeccion) {
                http_response_code(404);
                header('Content-Type: text/html; charset=UTF-8');
                echo '<!DOCTYPE html><meta charset="utf-8"><title>Sin sección</title>'
                   . '<p>No tienes una sección asignada en el período escolar en curso.</p>';
                exit;
            }

            if ($vista === 'acta') {
                $filtros += $this->datosActaPdf($fechaHoyIso);
            }

            try {
                $pdf = NominaPdf::generar($config, $seccion, $vista, $definicion['titulo'], $columnas, $filas, $filtros);
            } catch (Throwable $e) {
                $this->handleError($e, 'docente.nomina.pdf');
            }

            $nombre = $this->nombreArchivoNomina($vista, $seccion);
            /* Lo que decide si el navegador lo muestra o lo baja es la petición,
               no la vista. El botón «Visualizar documento» pide este mismo PDF
               sin `descargar` para meterlo en el iframe del modal, y ese caso
               tiene que llegar en línea: con `attachment` el navegador se lo
               descarga por su cuenta y el docente nunca alcanza a verlo. Solo
               el enlace «Descargar PDF», que es la misma ruta con descargar=1,
               baja el archivo. Antes era al revés —solo el acta viajaba en línea
               y las otras cinco se bajaban solas—, que era el fallo. */
            $disposicion = (($_GET['descargar'] ?? '') === '1') ? 'attachment' : 'inline';

            header_remove('X-Frame-Options');
            header('X-Frame-Options: SAMEORIGIN');
            header('Content-Type: application/pdf');
            header('Content-Disposition: ' . $disposicion . '; filename="' . $nombre . '"');
            header('Content-Length: ' . strlen($pdf));
            header('X-Content-Type-Options: nosniff');
            echo $pdf;
            exit;
        }

        /* Todas las vistas, el acta incluida, van a la barra de pestañas. Se
           pasan tal cual porque el chip del acta ya no es un caso especial:
           usa el mismo bucle que Nómina, Contacto, Direcciones, Cumpleañeros y
           Autorizados. */
        $vistas           = self::VISTAS_NOMINA;
        $meses            = self::MESES;
        $nombreEscuela    = trim((string) ($config['plantel_nombre'] ?? '')) ?: 'E.B. Isidro Ramírez';
        $nombreEscuelaWa  = $this->capitalizarTitulo($nombreEscuela);
        $nombreDocente    = trim((string) ($seccion['docente_nombre'] ?? ($_SESSION['nombre'] ?? '')));

        require __DIR__ . '/../Views/docente/nomina.php';
    }

    /**
     * Guarda, actualiza o elimina el enlace de invitación al grupo de WhatsApp
     * de la sección asignada al docente en sesión (POST /docente/guardarEnlaceWhatsapp).
     */
    public function guardarEnlaceWhatsapp(): void
    {
        $this->exigirDocente();

        $base    = $this->datosComunes();
        $seccion = $base['seccion'];
        if (!$base['tieneSeccion'] || $seccion === null) {
            $this->jsonResponse(false, null, 'No tienes una sección activa asignada.', 403);
        }

        if (isset($_POST['enlace_whatsapp']) && !is_string($_POST['enlace_whatsapp'])) {
            $this->jsonResponse(false, ['enlace_whatsapp' => 'El enlace no es válido.'], 'El enlace no es válido.', 422);
        }

        $enlaceRaw = trim((string) ($_POST['enlace_whatsapp'] ?? ''));
        $enlaceNuevo = null;

        if ($enlaceRaw !== '') {
            if (preg_match('/\s/u', $enlaceRaw)) {
                $msg = 'El enlace no debe contener espacios.';
                $this->jsonResponse(false, ['enlace_whatsapp' => $msg], $msg, 422);
            }
            if (mb_strlen($enlaceRaw, 'UTF-8') > 120) {
                $msg = 'El enlace admite hasta 120 caracteres.';
                $this->jsonResponse(false, ['enlace_whatsapp' => $msg], $msg, 422);
            }
            if (!preg_match('#^https://chat\.whatsapp\.com/(?:invite/)?[A-Za-z0-9_-]{10,64}$#', $enlaceRaw)) {
                $msg = 'El enlace debe comenzar por https://chat.whatsapp.com/ seguido del código de invitación válido.';
                $this->jsonResponse(false, ['enlace_whatsapp' => $msg], $msg, 422);
            }
            $enlaceNuevo = $enlaceRaw;
        }

        $idEstructura   = (int) $base['idEstructura'];
        $enlaceAnterior = $seccion['enlace_whatsapp'] ?? null;

        try {
            $modelo = new TeacherAssignmentModel($this->conexion());
            $modelo->actualizarEnlaceWhatsapp($idEstructura, $enlaceNuevo);
        } catch (Throwable $e) {
            $this->jsonError($e, 'Enlace de WhatsApp');
        }

        // Registrar en bitácora sin guardar nunca la URL del grupo (da acceso directo).
        $idUsuario = (int) ($this->idUsuarioActual() ?? 0);
        if ($idUsuario > 0 && $enlaceAnterior !== $enlaceNuevo) {
            $etiquetaSeccion = trim(($seccion['grado'] ?? '') . ' ' . ($seccion['seccion'] ?? ''));
            if ($enlaceNuevo === null) {
                $accionBitacora = 'QUITÓ ENLACE DE WHATSAPP · ' . $etiquetaSeccion;
            } elseif ($enlaceAnterior === null || $enlaceAnterior === '') {
                $accionBitacora = 'AGREGÓ ENLACE DE WHATSAPP · ' . $etiquetaSeccion;
            } else {
                $accionBitacora = 'CAMBIÓ ENLACE DE WHATSAPP · ' . $etiquetaSeccion;
            }
            (new BitacoraModel($this->conexion()))->registrar($idUsuario, $accionBitacora, 'Docente');
        }

        $mensaje = $enlaceNuevo === null
            ? 'Se quitó el enlace del grupo de WhatsApp.'
            : 'Enlace del grupo de WhatsApp guardado correctamente.';

        $this->jsonResponse(true, ['tiene_enlace' => $enlaceNuevo !== null], $mensaje);
    }

    /**
     * Valida y sanitiza los datos exclusivos del Acta de firmas (motivo
     * obligatorio de 3 a 120 caracteres y fecha válida YYYY-MM-DD, por defecto hoy).
     *
     * @return array{motivo:string,fecha_acta:string}
     */
    private function datosActaPdf(string $fechaHoyIso): array
    {
        if (isset($_GET['motivo']) && !is_string($_GET['motivo'])) {
            $this->rechazarSolicitud('El motivo del acta no es válido.');
        }

        $motivo = $this->formatearMotivoActa((string) ($_GET['motivo'] ?? ''));
        if ($motivo === '') {
            $this->rechazarSolicitud('El motivo del acta es obligatorio.');
        }

        $longitud = mb_strlen($motivo, 'UTF-8');
        if ($longitud < 3) {
            $this->rechazarSolicitud('El motivo del acta debe tener al menos 3 caracteres.');
        }
        if ($longitud > 120) {
            $this->rechazarSolicitud('El motivo del acta admite hasta 120 caracteres.');
        }
        if (!preg_match('/^[\p{L}\p{M}\p{N}\s.,;:()°ºª\-\/\'"]+$/u', $motivo)) {
            $this->rechazarSolicitud('El motivo del acta contiene caracteres no permitidos.');
        }

        if (isset($_GET['fecha']) && !is_string($_GET['fecha'])) {
            $this->rechazarSolicitud('La fecha del acta no es válida.');
        }
        $fechaTexto = trim((string) ($_GET['fecha'] ?? ''));
        if ($fechaTexto === '') {
            $fechaTexto = $fechaHoyIso;
        }
        $fechaObj = DateTimeImmutable::createFromFormat('!Y-m-d', $fechaTexto);
        if (!$fechaObj || $fechaObj->format('Y-m-d') !== $fechaTexto) {
            $this->rechazarSolicitud('La fecha del acta no es válida.');
        }

        return [
            'motivo'     => $motivo,
            'fecha_acta' => $fechaObj->format('d/m/Y'),
        ];
    }

    /**
     * Limpia etiquetas HTML y espacios del motivo del acta, poniendo en mayúscula
     * solo la primera letra y respetando siglas o nombres propios ("Consejo Educativo",
     * "UNEFA"). Si TODO el texto viene en mayúsculas, lo convierte a formato oración.
     */
    private function formatearMotivoActa(string $texto): string
    {
        $limpio = $this->normalizarEspacios($texto);
        if ($limpio === '') {
            return '';
        }

        $tieneLetras    = (bool) preg_match('/\p{L}/u', $limpio);
        $tieneMinuscula = (bool) preg_match('/\p{Ll}/u', $limpio);

        if ($tieneLetras && !$tieneMinuscula) {
            return $this->capitalizarOracion($limpio);
        }

        return mb_strtoupper(mb_substr($limpio, 0, 1, 'UTF-8'), 'UTF-8')
             . mb_substr($limpio, 1, null, 'UTF-8');
    }

    /**
     * Lee un parámetro GET de lista cerrada. Ausente o vacío: valor por defecto.
     * Cualquier otro valor (o un arreglo) se rechaza con 422.
     *
     * @param array<int,string> $permitidos
     */
    private function parametroLista(string $clave, array $permitidos, string $defecto): string
    {
        if (!array_key_exists($clave, $_GET) || $_GET[$clave] === '') {
            return $defecto;
        }
        $valor = $_GET[$clave];
        if (!is_string($valor) || !in_array($valor, $permitidos, true)) {
            $this->rechazarSolicitud("El parámetro «{$clave}» no es válido.");
        }

        return $valor;
    }

    /**
     * Lista blanca de filtros de /docente/nomina según los filtros propios de
     * cada vista (`VISTAS_NOMINA[$vista]['filtros']`). Cualquier parámetro que
     * no pertenezca a la vista activa se ignora.
     *
     * `orden` y `sentido` no son filtros de la vista: se aceptan siempre, porque
     * la barra de orden acompaña a cualquier vista y el orden elegido tiene que
     * sobrevivir al cambio de pestaña. Se ignoran (vuelven a su valor por
     * defecto) en las vistas de orden fijo, y no llegan al modelo porque
     * nominaSeccion() tiene su propio orden para esas dos.
     *
     * @return array{q:string,sexo:string,mes:int|null,orden:string,sentido:string}
     */
    private function filtrosNomina(string $vista): array
    {
        $permitidosVista = self::VISTAS_NOMINA[$vista]['filtros'];

        foreach ($permitidosVista as $clave) {
            if (isset($_GET[$clave]) && !is_string($_GET[$clave])) {
                $this->rechazarSolicitud("El filtro «{$clave}» no es válido.");
            }
        }

        $q = '';
        if (in_array('q', $permitidosVista, true)) {
            $q = $this->normalizarEspacios((string) ($_GET['q'] ?? ''));
            if (mb_strlen($q, 'UTF-8') > 60) {
                $this->rechazarSolicitud('La búsqueda admite hasta 60 caracteres.');
            }
        }

        $sexo = '';
        if (in_array('sexo', $permitidosVista, true)) {
            $sexo = strtoupper(trim((string) ($_GET['sexo'] ?? '')));
            if ($sexo !== '' && !in_array($sexo, ['F', 'M'], true)) {
                $this->rechazarSolicitud('El filtro «sexo» no es válido.');
            }
        }

        $mes = null;
        if (in_array('mes', $permitidosVista, true)) {
            $mesTexto = trim((string) ($_GET['mes'] ?? ''));
            if ($mesTexto !== '') {
                if (!ctype_digit($mesTexto) || (int) $mesTexto < 1 || (int) $mesTexto > 12) {
                    $this->rechazarSolicitud('El mes debe estar entre 1 y 12.');
                }
                $mes = (int) $mesTexto;
            } else {
                /* Sin ?mes= se muestra el mes en curso: es el que se consulta casi
                   siempre, y preguntar por los doce sería un paso extra. */
                $mes = (int) (new DateTimeImmutable('now', new DateTimeZone('America/Caracas')))->format('n');
            }
        }

        /* El orden no viaja en los filtros: es fijo en todos los listados y lo
           aplica el modelo (alfabético por apellido, y por día en cumpleañeros).
           `orden` y `sentido` siguen siendo filtros que el modelo entiende, con
           ese mismo valor por defecto, por si otra pantalla los usa. */
        return ['q' => $q, 'sexo' => $sexo, 'mes' => $mes];
    }

    /** Responde 422 y corta la ejecución. */
    private function rechazarSolicitud(string $mensaje): never
    {
        http_response_code(422);
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!DOCTYPE html><meta charset="utf-8"><title>Solicitud no válida</title><p>'
           . htmlspecialchars($mensaje, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
        exit;
    }

    /** acta-3A-2025-2026.pdf: vista, grado (número) + sección, año escolar. */
    private function nombreArchivoNomina(string $vista, array $seccion): string
    {
        $grado = (string) ($seccion['grado'] ?? '');
        $grado = preg_match('/\d+/', $grado, $m) ? $m[0] : $grado;
        $slug  = static fn(string $v): string => preg_replace('/[^A-Za-z0-9-]/', '', $v) ?? '';

        return $vista . '-'
             . $slug($grado . (string) ($seccion['seccion'] ?? '')) . '-'
             . $slug((string) ($seccion['anio_escolar'] ?? '')) . '.pdf';
    }
}