<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\BinaryResponse;
use App\Models\BitacoraModel;
use App\Models\EstudianteModel;
use App\Models\TeacherAssignmentModel;
use App\Support\Configuracion;
use App\Support\ConQuienVive;
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
       /docente  ·  Inicio
       ====================================================================== */

    public function index(): void
    {
        $this->exigirDocente();
        (new DocenteDashboardController())->index();
    }

    /* ======================================================================
       /docente/seccion  ·  Nómina
       ====================================================================== */

    public function seccion(): void
    {
        $this->exigirDocente();
        $base              = $this->datosComunes();
        $seccion           = $base['seccion'];
        $tieneSeccion      = $base['tieneSeccion'];
        $idEstructura      = $base['idEstructura'];

        $filtros = [
            'q'           => trim((string) ($_GET['q'] ?? '')),
            'sexo'        => trim((string) ($_GET['sexo'] ?? '')),
            'vive_con'    => trim((string) ($_GET['vive_con'] ?? '')),
            'contacto'    => trim((string) ($_GET['contacto'] ?? '')),
            'autorizados' => trim((string) ($_GET['autorizados'] ?? '')),
            'literal'     => trim((string) ($_GET['literal'] ?? '')),
        ];

        $nomina   = [];
        $agrupado = [];

        if ($tieneSeccion) {
            try {
$nomina = $this->getModel()->getNominaDocente($idEstructura, $filtros);

                /* Agrupar por representante es solo una forma de presentar las
                   MISMAS filas ya filtradas: no cambia el conjunto de datos, así
                   que el contador de arriba sigue siendo el de la tabla. */
                $agrupado = $this->agruparPorRepresentante($nomina);
            } catch (Throwable $e) {
                error_log('DocenteController::seccion(): ' . $e->getMessage());
            }
        }

        $tieneFiltros = (bool) array_filter($filtros, static fn($v) => $v !== '');

        require_once __DIR__ . '/../Views/docente/seccion.php';
    }

    /**
     * Reordena la nómina bajo cada representante.
     *
     * @param  array<int,array<string,mixed>> $nomina
     * @return array<int,array<string,mixed>>
     */
    private function agruparPorRepresentante(array $nomina): array
    {
        $grupos = [];
        foreach ($nomina as $f) {
            /* Clave: el id del representante. Si el estudiante no tiene
               representante se usa su propio id para que no se junte con nadie. */
            $clave = $f['id_representante'] ? ('rep:' . $f['id_representante'])
                                            : ('est:' . $f['id_estudiante']);

            if (!isset($grupos[$clave])) {
                $grupos[$clave] = [
                    'id_representante' => $f['id_representante'],
                    'representante'    => $f['representante'] ?: 'Sin representante',
                    'parentesco'       => $f['parentesco'],
                    'telefono'         => $f['numero_telefono_principal'] ?: '',
                    'codigo_telefono'  => $f['codigo_telefono_principal'] ?: '',
                    'estudiantes'      => [],
                ];
            }
            $grupos[$clave]['estudiantes'][] = $f;
        }

        uasort($grupos, static function (array $a, array $b): int {
            return strcasecmp($a['representante'], $b['representante']);
        });

        return $grupos;
    }

    /* ======================================================================
       /docente/estudiante  ·  Ficha
       ====================================================================== */

    public function estudiante(): void
    {
        $this->exigirDocente();
        $base              = $this->datosComunes();
        $seccion           = $base['seccion'];
        $tieneSeccion      = $base['tieneSeccion'];
        $idEstructura      = $base['idEstructura'];

        $idEstudiante = (int) ($_GET['id'] ?? 0);
        if ($idEstudiante <= 0) {
            header('Location: ' . BASE_URL . 'docente/seccion');
            exit;
        }

        $ficha      = null;
        $familiares = [];

        /* El acotado está en getFichaDocente(): si el estudiante no está en la
           sección del docente no devuelve fila, y aquí se responde 404. Cambiar
           el id en la URL no da acceso a ninguna ficha ajena. */
        if ($tieneSeccion) {
            try {
                $ficha = $this->getModel()->getFichaDocente($idEstructura, $idEstudiante);
                if ($ficha !== null) {
                    $familiares = $this->getModel()->getFamiliaresDocente($idEstudiante);
                }
            } catch (Throwable $e) {
                error_log('DocenteController::estudiante(): ' . $e->getMessage());
            }
        }

        if ($ficha === null) {
            $this->renderHttpError(404, '404 · No encontrado', 'No encontramos ese expediente.');
        }

        /* El representante no se repite en la lista de autorizados: la vista
           lo recibe una vez y lo marca como tal. */
        $representante = null;
        $autorizados   = [];
        foreach ($familiares as $f) {
            if ((int) $f['es_representante_legal'] === 1) {
                $representante = $f;
            } else {
                $autorizados[] = $f;
            }
        }

        $ficha['con_quien_vive_texto'] =
            ConQuienVive::etiqueta($ficha['con_quien_vive'] ?? null);

        require_once __DIR__ . '/../Views/docente/estudiante.php';
    }

    /* ======================================================================
       /docente/buscar  ·  Búsqueda por AJAX de la nómina
       ====================================================================== */

    public function buscar(): void
    {
        $this->exigirDocente();
        $base = $this->datosComunes();

        if (!$base['tieneSeccion']) {
            $this->jsonResponse(true, ['nomina' => [], 'total' => 0]);
        }

        $filtros = [
            'q'           => trim((string) ($_GET['q'] ?? '')),
            'sexo'        => trim((string) ($_GET['sexo'] ?? '')),
            'vive_con'    => trim((string) ($_GET['vive_con'] ?? '')),
            'contacto'    => trim((string) ($_GET['contacto'] ?? '')),
            'autorizados' => trim((string) ($_GET['autorizados'] ?? '')),
            'literal'     => trim((string) ($_GET['literal'] ?? '')),
        ];

        try {
            $nomina = $this->getModel()->getNominaDocente($base['idEstructura'], $filtros);
        } catch (Throwable $e) {
            error_log('DocenteController::buscar(): ' . $e->getMessage());
            $this->jsonResponse(false, null, 'No se pudo consultar la nómina.', 500);
        }

        $this->jsonResponse(true, ['nomina' => $nomina, 'total' => count($nomina)]);
    }

    /* ======================================================================
       /docente/nomina  ·  Listados de solo lectura (pantalla, impresión y PDF)
       ====================================================================== */

    /**
     * Vistas permitidas al docente y las columnas de cada una. Es la lista
     * blanca: cualquier otro valor de ?vista= se rechaza con 422. La vista de
     * hermanos es del rol Directivo y por eso no está aquí.
     */
    private const VISTAS_NOMINA = [
        'nomina' => [
            'etiqueta' => 'Nómina',
            'titulo'   => 'Nómina de estudiantes',
            'columnas' => ['n', 'cedula', 'estudiante', 'sexo', 'fecha_nac', 'edad'],
            'filtros'  => ['sexo', 'orden', 'q'],
        ],
        'contacto' => [
            'etiqueta' => 'Contactos',
            'titulo'   => 'Directorio de contactos',
            'columnas' => ['estudiante', 'representante', 'telefonos_rep'],
            'filtros'  => ['orden', 'q'],
        ],
        'cumpleaneros' => [
            'etiqueta' => 'Cumpleañeros',
            'titulo'   => 'Cumpleañeros del mes',
            'columnas' => ['dia', 'estudiante', 'edad_cumple'],
            'filtros'  => ['mes', 'orden', 'q'],
        ],

    ];

    /** Opciones de ordenamiento permitidas por cada vista (pantalla y PDF). */
    public const ORDENES_NOMINA = [
        'nomina' => [
            'apellidos_asc'  => 'Apellidos (A–Z)',
            'apellidos_desc' => 'Apellidos (Z–A)',
            'edad_asc'       => 'Edad (de menor a mayor)',
            'edad_desc'      => 'Edad (de mayor a menor)',
        ],
        'contacto' => [
            'apellidos_asc'  => 'Apellidos (A–Z)',
            'apellidos_desc' => 'Apellidos (Z–A)',
        ],
        'cumpleaneros' => [
            'dia_asc'        => 'Día del mes (1–31)',
            'dia_desc'       => 'Día del mes (31–1)',
            'edad_asc'       => 'Edad que cumple (menor a mayor)',
            'edad_desc'      => 'Edad que cumple (mayor a menor)',
            'apellidos_asc'  => 'Apellidos (A–Z)',
        ],

    ];

    /** Encabezado visible de cada columna (pantalla y PDF). */
    private const COLUMNAS_NOMINA = [
        'n'             => 'N°',
        'cedula'        => 'Cédula escolar',
        'estudiante'    => 'Apellidos y nombres',
        'sexo'          => 'Sexo',
        'fecha_nac'     => 'Fecha de nacimiento',
        'edad'          => 'Edad',
        'representante' => 'Representante legal',
        'cedula_rep'    => 'C.I. representante',
        'firma'         => 'Firma',
        'telefonos_rep' => 'Teléfonos del representante',
        'direccion'     => 'Dirección de habitación',
        'autorizados'   => 'Autorizados para retiro',
        'dia'           => 'Día',
        'edad_cumple'   => 'Cumple',
        'literal'       => 'Literal',
        'promocion'     => 'Decisión / Estado',
        'boleta'        => 'Certificado / Boleta',
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

        $filas    = [];
        $sectores = [];
        if ($tieneSeccion) {
            try {
                $filas = $this->getModel()->nominaSeccion(
                    $base['idEstructura'],
                    $filtros + ['vista' => $vista]
                );
                if ($vista === 'contacto') {
                    $sectores = $this->getModel()->sectoresSeccion($base['idEstructura']);
                }
            } catch (Throwable $e) {
                error_log('DocenteController::nomina(): ' . $e->getMessage());
                $filas    = [];
                $sectores = [];
            }
        }

        $config      = Configuracion::getAll($this->conexion());
        $ahoraCaracas = new DateTimeImmutable('now', new DateTimeZone('America/Caracas'));
        $fechaHoyIso = $ahoraCaracas->format('Y-m-d');
        $horaActual  = (int) $ahoraCaracas->format('G');
        $saludoWa    = match (true) {
            $horaActual < 12 => 'Buen día',
            $horaActual < 19 => 'Buenas tardes',
            default          => 'Buenas noches',
        };

        $ordenesVista = self::ORDENES_NOMINA[$vista] ?? [];
        if (!empty($filtros['orden']) && isset($ordenesVista[$filtros['orden']])) {
            $filtros['orden_etiqueta'] = $ordenesVista[$filtros['orden']];
        }

        if ($formato === 'pdf') {
            if (!$tieneSeccion) {
                $this->renderHttpError(404, '404 · Sin sección', 'No tienes una sección asignada en el período escolar en curso.');
            }



            try {
                $pdf = NominaPdf::generar($config, $seccion, $vista, $definicion['titulo'], $columnas, $filas, $filtros);
            } catch (Throwable $e) {
                $this->handleError($e, 'docente.nomina.pdf');
            }

            $nombre = $this->nombreArchivoNomina($vista, $seccion);
            $disposicion = (($_GET['descargar'] ?? '') !== '1')
                ? 'inline'
                : 'attachment';

            BinaryResponse::pdf($pdf, $nombre, $disposicion === 'inline')->send();
        }

        $vistas          = self::VISTAS_NOMINA;
        $meses           = self::MESES;
        $nombreEscuela   = trim((string) ($config['plantel_nombre'] ?? '')) ?: 'E.B. Isidro Ramírez';
        $nombreEscuelaWa = $this->capitalizarTitulo($nombreEscuela);

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
     * @return array{q:string,sexo:string,mes:int|null,sector:string,orden:string}
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
                $mes = (int) (new DateTimeImmutable('now', new DateTimeZone('America/Caracas')))->format('n');
            }
        }

        $sector = '';
        if (in_array('sector', $permitidosVista, true)) {
            $sector = $this->normalizarEspacios((string) ($_GET['sector'] ?? ''));
            if (mb_strlen($sector, 'UTF-8') > 100) {
                $this->rechazarSolicitud('El filtro de sector admite hasta 100 caracteres.');
            }
        }

        $orden = '';
        if (in_array('orden', $permitidosVista, true)) {
            $orden = trim((string) ($_GET['orden'] ?? ''));
            $ordenesPermitidos = array_keys(self::ORDENES_NOMINA[$vista] ?? []);
            if ($orden !== '' && !in_array($orden, $ordenesPermitidos, true)) {
                $this->rechazarSolicitud('El criterio de ordenamiento no es válido.');
            }
        }

        return [
            'q'      => $q,
            'sexo'   => $sexo,
            'mes'    => $mes,
            'sector' => $sector,
            'orden'  => $orden,
        ];
    }

    /** Responde 422 y corta la ejecución. */
    private function rechazarSolicitud(string $mensaje): never
    {
        $this->renderHttpError(422, '422 · Solicitud no válida', $mensaje);
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