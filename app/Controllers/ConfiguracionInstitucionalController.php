<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\ConfiguracionInstitucionalModel;
use App\Support\Configuracion;
use PDO;
use Throwable;

/**
 * Controlador de Configuración Institucional y Directiva.
 *
 * Acceso estrictamente restringido al rol 'admin' (segunda barrera de seguridad).
 *
 * Responsabilidades:
 * 1. index: Renderiza la vista con los parámetros oficiales, logos, datos directivos y estado de firma/sello.
 * 2. guardar: Procesa y valida los campos de texto del plantel y la directiva.
 * 3. subirImagen: Recibe y re-codifica imágenes públicas (logos) y privadas (firma y sello PNG).
 * 4. quitarImagen: Restablece logos a sus versiones predeterminadas o vacía firma/sello.
 * 5. verPrivado: Transmite en streaming seguro la vista previa de firma y sello solo al administrador.
 */
class ConfiguracionInstitucionalController extends BaseController
{
    protected string $viewPath  = 'configuracion_institucional';
    protected string $routeName = 'configuracionInstitucional';

    /**
     * Segunda barrera de seguridad: Comprueba estrictamente el rol 'admin'.
     */
    protected function afterConstruct(): void
    {
        $this->exigirAdmin();
    }

    /**
     * Valida que el usuario en sesión sea exclusivamente 'admin'.
     */
    private function exigirAdmin(): void
    {
        $rolRaw = (string) ($_SESSION['rol'] ?? '');
        $rol = function_exists('\\App\\Traits\\sigde_rol_clave')
            ? \App\Traits\sigde_rol_clave($rolRaw)
            : strtolower(trim($rolRaw));

        if ($rol !== 'admin') {
            if (esPeticionAjax()) {
                $this->jsonResponse(false, null, 'No tienes permiso para realizar esta acción.', 403);
            }
            http_response_code(403);
            die('<h1>403 - Acceso denegado</h1>');
        }
    }

    protected function createModel(PDO $pdo): ConfiguracionInstitucionalModel
    {
        // Compartir la misma conexión PDO con el helper global
        Configuracion::setPdo($pdo);
        return new ConfiguracionInstitucionalModel($pdo);
    }

    private function modelo(): ConfiguracionInstitucionalModel
    {
        /** @var ConfiguracionInstitucionalModel $model */
        $model = $this->getModel();
        return $model;
    }

    /**
     * Pantalla principal de Configuración Institucional.
     */
    public function index(): void
    {
        $this->exigirAdmin();

        try {
            $model = $this->modelo();
            $config = $model->getAll();
            $flash  = $this->getFlashMessages();

            $rutaFirma = Configuracion::rutaPrivada('director_firma');
            $tieneFirma = $rutaFirma !== null && is_file($rutaFirma);

            $rutaSello = Configuracion::rutaPrivada('institucion_sello');
            $tieneSello = $rutaSello !== null && is_file($rutaSello);

            $tieneCintillo = Configuracion::tieneCintillo();
            $cintilloUrl   = $tieneCintillo ? Configuracion::asset('plantel_cintillo') : '';
            $valorCintillo = trim(Configuracion::get('plantel_cintillo'));
            $esCintilloOficial = $tieneCintillo && str_contains($valorCintillo, 'franja_tricolor');

            $dirPrimerNombre    = (string) ($config['director_primer_nombre'] ?? '');
            $dirSegundoNombre   = (string) ($config['director_segundo_nombre'] ?? '');
            $dirPrimerApellido  = (string) ($config['director_primer_apellido'] ?? '');
            $dirSegundoApellido = (string) ($config['director_segundo_apellido'] ?? '');

            if ($dirPrimerNombre === '' && $dirPrimerApellido === '' && !empty($config['director_nombre'])) {
                $partes = preg_split('/\s+/u', trim($config['director_nombre']));
                if (count($partes) === 1) {
                    $dirPrimerNombre = $partes[0];
                } elseif (count($partes) === 2) {
                    $dirPrimerNombre = $partes[0];
                    $dirPrimerApellido = $partes[1];
                } elseif (count($partes) === 3) {
                    $dirPrimerNombre = $partes[0];
                    $dirPrimerApellido = $partes[1];
                    $dirSegundoApellido = $partes[2];
                } elseif (count($partes) >= 4) {
                    $dirPrimerNombre = $partes[0];
                    $dirSegundoNombre = $partes[1];
                    $dirPrimerApellido = $partes[2];
                    $dirSegundoApellido = implode(' ', array_slice($partes, 3));
                }
            }

            $datos = [
                'config'               => $config,
                'flash'                => $flash,
                'csrfToken'            => csrfToken(),
                'directorConfigurado'  => Configuracion::estaConfiguradoDirector(),
                'dirPrimerNombre'      => $dirPrimerNombre,
                'dirSegundoNombre'     => $dirSegundoNombre,
                'dirPrimerApellido'    => $dirPrimerApellido,
                'dirSegundoApellido'   => $dirSegundoApellido,
                'ministerioLogoUrl'    => Configuracion::asset('ministerio_logo'),
                'plantelLogoUrl'       => Configuracion::asset('plantel_logo'),
                'tieneCintillo'        => $tieneCintillo,
                'plantelCintilloUrl'   => $cintilloUrl,
                'esCintilloOficial'    => $esCintilloOficial,
                'tieneFirma'           => $tieneFirma,
                'firmaUrl'             => $tieneFirma ? (BASE_URL . 'configuracionInstitucional/verPrivado/director_firma?v=' . filemtime($rutaFirma)) : '',
                'tieneSello'           => $tieneSello,
                'selloUrl'             => $tieneSello ? (BASE_URL . 'configuracionInstitucional/verPrivado/institucion_sello?v=' . filemtime($rutaSello)) : '',
            ];

            extract($datos);
            require __DIR__ . '/../Views/configuracion_institucional/index.php';
        } catch (Throwable $e) {
            $this->handleError($e, 'Carga de configuración institucional');
        }
    }

    /**
     * Guarda masivamente los campos de texto del formulario institucional.
     */
    public function guardar(): void
    {
        $this->exigirAdmin();

        try {
            $model = $this->modelo();
            $idUsuario = $this->idUsuarioActual() ?? 0;

            $pNombre   = $this->capitalizarTitulo((string) ($_POST['director_primer_nombre'] ?? ''));
            $sNombre   = $this->capitalizarTitulo((string) ($_POST['director_segundo_nombre'] ?? ''));
            $pApellido = $this->capitalizarTitulo((string) ($_POST['director_primer_apellido'] ?? ''));
            $sApellido = $this->capitalizarTitulo((string) ($_POST['director_segundo_apellido'] ?? ''));

            if ($pNombre !== '' || $pApellido !== '') {
                $partesDirector = array_filter([$pNombre, $sNombre, $pApellido, $sApellido], fn($v) => $v !== '');
                $nombreDirector = trim(implode(' ', $partesDirector));
            } else {
                $nombreDirector = $this->capitalizarTitulo((string) ($_POST['director_nombre'] ?? ''));
            }

            $cargoDirector  = $this->capitalizarTitulo((string) ($_POST['director_cargo'] ?? ''));

            $cedulaRaw = $this->normalizarEspacios((string) ($_POST['director_cedula'] ?? ''));
            if ($cedulaRaw !== '') {
                $digitos = preg_replace('/\D/', '', $cedulaRaw);
                $letra = strtoupper(substr(trim($cedulaRaw), 0, 1));
                if (!in_array($letra, ['V', 'E'], true) || strlen($digitos) < 6 || strlen($digitos) > 9) {
                    $this->jsonResponse(false, [
                        'director_cedula' => 'La cédula del director debe ser válida (V o E seguida de 6 a 9 dígitos, ej. V-12.345.678).',
                    ], 'La cédula del director no tiene un formato válido.', 422);
                }
                $cedulaDirector = $letra . '-' . $digitos;
            } else {
                $cedulaDirector = '';
            }

            $campos = [
                'ministerio_linea_1'         => $this->normalizarEspacios((string) ($_POST['ministerio_linea_1'] ?? '')),
                'ministerio_linea_2'         => $this->normalizarEspacios((string) ($_POST['ministerio_linea_2'] ?? '')),
                'plantel_nombre'             => $this->normalizarEspacios((string) ($_POST['plantel_nombre'] ?? '')),
                'plantel_nombre_corto'       => $this->normalizarEspacios((string) ($_POST['plantel_nombre_corto'] ?? '')),
                'plantel_codigo_dea'         => mb_strtoupper($this->normalizarEspacios((string) ($_POST['plantel_codigo_dea'] ?? '')), 'UTF-8'),
                'director_nombre'            => $nombreDirector,
                'director_primer_nombre'     => $pNombre,
                'director_segundo_nombre'    => $sNombre,
                'director_primer_apellido'   => $pApellido,
                'director_segundo_apellido'  => $sApellido,
                'director_cedula'            => $cedulaDirector,
                'director_cargo'             => $cargoDirector,
                'plantel_municipio'          => $this->capitalizarTitulo((string) ($_POST['plantel_municipio'] ?? '')),
                'plantel_parroquia'          => $this->capitalizarTitulo((string) ($_POST['plantel_parroquia'] ?? '')),
                'cdce_estado'                => $this->capitalizarTitulo((string) ($_POST['cdce_estado'] ?? '')),
                'plantel_codigo_dependencia' => $this->normalizarEspacios((string) ($_POST['plantel_codigo_dependencia'] ?? '')),
                'nivel_educativo'            => $this->capitalizarTitulo((string) ($_POST['nivel_educativo'] ?? '')),
                'nivel_codigo'               => $this->normalizarEspacios((string) ($_POST['nivel_codigo'] ?? '')),
                'lugar_expedicion'           => $this->capitalizarTitulo((string) ($_POST['lugar_expedicion'] ?? '')),
            ];

            $resultado = $model->guardarTextos($campos, $idUsuario);

            if (!$resultado['ok']) {
                $this->jsonResponse(false, $resultado['errores'] ?? null, $resultado['mensaje'], 422);
            }

            $this->jsonResponse(true, [
                'director_configurado' => Configuracion::estaConfiguradoDirector(),
                'campos'               => $campos,
            ], $resultado['mensaje']);
        } catch (Throwable $e) {
            $this->jsonError($e, 'Guardado de textos institucionales');
        }
    }

    /**
     * Sube, valida y re-codifica una imagen institucional o directiva.
     */
    public function subirImagen(): void
    {
        $this->exigirAdmin();

        try {
            $clave = $this->normalizarEspacios((string) ($_POST['clave'] ?? ''));
            $archivo = $_FILES['imagen'] ?? null;

            if ($archivo === null || !is_array($archivo)) {
                $this->jsonResponse(false, null, 'No se recibió ningún archivo de imagen para procesar.', 422);
            }

            $model = $this->modelo();
            $idUsuario = $this->idUsuarioActual() ?? 0;

            $resultado = $model->procesarYGuardarImagen($clave, $archivo, $idUsuario);

            if (!$resultado['ok']) {
                $this->jsonResponse(false, null, $resultado['mensaje'], 422);
            }

            $esPrivado = in_array($clave, ['director_firma', 'institucion_sello'], true);
            $rutaPrivada = $esPrivado ? Configuracion::rutaPrivada($clave) : null;
            $previewUrl = ($esPrivado && $rutaPrivada !== null && is_file($rutaPrivada))
                ? (BASE_URL . 'configuracionInstitucional/verPrivado/' . $clave . '?v=' . filemtime($rutaPrivada))
                : ($resultado['asset_url'] ?? '');

            $this->jsonResponse(true, [
                'clave'       => $clave,
                'preview_url' => $previewUrl,
                'es_privado'  => $esPrivado,
            ], $resultado['mensaje']);
        } catch (Throwable $e) {
            $this->jsonError($e, 'Subida de imagen institucional');
        }
    }

    /**
     * Retira una imagen personalizada o la restablece por defecto.
     */
    public function quitarImagen(): void
    {
        $this->exigirAdmin();

        try {
            $clave = $this->normalizarEspacios((string) ($_POST['clave'] ?? ''));
            $model = $this->modelo();
            $idUsuario = $this->idUsuarioActual() ?? 0;

            $resultado = $model->eliminarImagen($clave, $idUsuario);

            if (!$resultado['ok']) {
                $this->jsonResponse(false, null, $resultado['mensaje'], 422);
            }

            $esPrivado  = in_array($clave, ['director_firma', 'institucion_sello'], true);
            $esCintillo = ($clave === 'plantel_cintillo');
            $fallbackUrl = ($esPrivado || $esCintillo) ? '' : Configuracion::asset($clave);

            $this->jsonResponse(true, [
                'clave'        => $clave,
                'fallback_url' => $fallbackUrl,
                'es_privado'   => $esPrivado,
                'es_cintillo'  => $esCintillo,
            ], $resultado['mensaje']);
        } catch (Throwable $e) {
            $this->jsonError($e, 'Eliminación de imagen institucional');
        }
    }

    /**
     * Restablece la franja tricolor oficial del sistema.
     */
    public function restablecerOficial(): void
    {
        $this->exigirAdmin();

        try {
            $clave = $this->normalizarEspacios((string) ($_POST['clave'] ?? ''));
            if ($clave !== 'plantel_cintillo') {
                $this->jsonResponse(false, null, 'Acción no permitida para esta clave.', 422);
            }

            $idUsuario = $this->idUsuarioActual() ?? 0;
            $resultado = $this->modelo()->restablecerFranjaOficial($idUsuario);

            if (!$resultado['ok']) {
                $this->jsonResponse(false, null, $resultado['mensaje'], 422);
            }

            $this->jsonResponse(true, [
                'clave'       => 'plantel_cintillo',
                'preview_url' => $resultado['url'],
            ], $resultado['mensaje']);
        } catch (Throwable $e) {
            $this->jsonError($e, 'Restablecimiento de franja oficial');
        }
    }

    /**
     * Transmite en streaming seguro la firma o el sello institucional.
     * Solo accesible por el administrador (HTTP 403 para cualquier otro rol).
     */
    public function verPrivado(): void
    {
        $this->exigirAdmin();

        $clave = $this->normalizarEspacios((string) ($_GET['id'] ?? ''));
        if (!in_array($clave, ['director_firma', 'institucion_sello'], true)) {
            http_response_code(404);
            die('<h1>404 - Recurso no encontrado</h1>');
        }

        $nombreArchivo = $this->modelo()->get($clave);
        if ($nombreArchivo === '') {
            http_response_code(404);
            die('<h1>404 - Imagen no configurada</h1>');
        }

        $subcarpeta = ($clave === 'director_firma') ? 'firma' : 'sello';
        $nombreLimpio = basename($nombreArchivo);
        $rutaCompleta = Configuracion::getProjectRoot() . DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . 'institucion' . DIRECTORY_SEPARATOR . $subcarpeta
            . DIRECTORY_SEPARATOR . $nombreLimpio;

        if (!is_file($rutaCompleta)) {
            http_response_code(404);
            die('<h1>404 - Archivo no encontrado en el servidor</h1>');
        }

        // Transmisión con cabeceras estrictas de seguridad y no-caché
        header('Content-Type: image/png');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Cache-Control: private, no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Content-Length: ' . (string) filesize($rutaCompleta));

        readfile($rutaCompleta);
        exit;
    }
}
