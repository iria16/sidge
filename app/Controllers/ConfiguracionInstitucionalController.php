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
    private function esPeticionAjax(): bool
    {
        return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
    }

    protected function exigirAdmin(): void
    {
        $rolRaw = (string) ($_SESSION['rol'] ?? '');
        $rol = function_exists('\\App\\Traits\\sigde_rol_clave')
            ? \App\Traits\sigde_rol_clave($rolRaw)
            : strtolower(trim($rolRaw));

        if ($rol !== 'admin') {
            if ($this->esPeticionAjax()) {
                $this->responderJsonEstandar(false, null, 'No tienes permiso para realizar esta acción.', 403, [
                    'permisos' => 'Acceso denegado. Se requiere rol de administrador.',
                ]);
            }
            http_response_code(403);
            die('<h1>403 - Acceso denegado</h1>');
        }
    }

    /**
     * Verifica estrictamente el token CSRF mediante hash_equals().
     */
    protected function verificarCsrf(): void
    {
        $esperado = (string) ($_SESSION['csrf'] ?? '');
        $recibido = (string) ($_POST['csrf'] ?? ($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')));

        if ($esperado === '' || $recibido === '' || !hash_equals($esperado, $recibido)) {
            $this->responderJsonEstandar(false, null, 'Token de seguridad inválido o expirado. Por favor, recargue la página.', 403, [
                'csrf' => 'Token CSRF inválido o ausente.',
            ]);
        }
    }

    /**
     * Emite una respuesta JSON consistente {ok, success, mensaje, message, data, errors}.
     * En caso de error o validación, los errores por campo se sitúan en 'errors' y 'data' queda en null.
     */
    protected function responderJsonEstandar(bool $ok, mixed $data = null, ?string $mensaje = null, int $code = 200, ?array $errores = null): never
    {
        if (!$ok) {
            if ($errores === null && is_array($data)) {
                $errores = $data;
                $data = null;
            } elseif ($errores !== null && $data === $errores) {
                $data = null;
            }
        }

        $payload = [
            'ok'      => $ok,
            'success' => $ok,
            'mensaje' => $mensaje,
            'message' => $mensaje,
            'data'    => $data,
            'errors'  => $errores,
        ];

        $this->jsonRaw($payload, $code);
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

            $rutaFirmaCdce = Configuracion::rutaPrivada('cdce_director_firma');
            $tieneFirmaCdce = $rutaFirmaCdce !== null && is_file($rutaFirmaCdce);

            $rutaSelloCdce = Configuracion::rutaPrivada('cdce_institucion_sello');
            $tieneSelloCdce = $rutaSelloCdce !== null && is_file($rutaSelloCdce);

            $tieneCintillo = Configuracion::tieneCintillo();
            $cintilloUrl   = $tieneCintillo ? Configuracion::asset('plantel_cintillo') : '';
            $valorCintillo = trim(Configuracion::get('plantel_cintillo'));
            $esCintilloOficial = $tieneCintillo && str_contains($valorCintillo, 'franja_tricolor');

            $dirPrimerNombre    = (string) ($config['director_primer_nombre'] ?? '');
            $dirSegundoNombre   = (string) ($config['director_segundo_nombre'] ?? '');
            $dirPrimerApellido  = (string) ($config['director_primer_apellido'] ?? '');
            $dirSegundoApellido = (string) ($config['director_segundo_apellido'] ?? '');

            // Cálculo centralizado del nombre completo del director
            $nombreCompletoDir = trim((string) ($config['director_nombre'] ?? ''));
            if ($nombreCompletoDir === '') {
                $partes = array_filter([$dirPrimerNombre, $dirSegundoNombre, $dirPrimerApellido, $dirSegundoApellido], fn($v) => trim($v) !== '');
                $nombreCompletoDir = trim(implode(' ', $partes));
            }

            // Validación del estado de la autoridad directiva y armado del mensaje explicativo
            $dirCedula = trim((string) ($config['director_cedula'] ?? ''));
            $dirCargo  = trim((string) ($config['director_cargo'] ?? ''));
            $directorConfigurado = ($nombreCompletoDir !== '' && $dirCedula !== '' && $dirCargo !== '');

            if ($directorConfigurado) {
                $mensajeDirector = 'Autoridad directiva configurada correctamente para la emisión de documentos.';
            } else {
                $faltantes = [];
                if ($nombreCompletoDir === '') $faltantes[] = 'nombre';
                if ($dirCedula === '') $faltantes[] = 'cédula';
                if ($dirCargo === '') $faltantes[] = 'cargo';
                $mensajeDirector = 'Complete los campos de ' . implode(', ', $faltantes) . ' del director(a) para habilitar la emisión de constancias de estudio e inscripción oficiales.';
            }

            // Valores territoriales y de nivel con fallback centralizado
            $valEstado       = !empty($config['cdce_estado']) ? $config['cdce_estado'] : 'Carabobo';
            $valMunicipio    = !empty($config['plantel_municipio']) ? $config['plantel_municipio'] : 'Valencia';
            $valParroquia    = !empty($config['plantel_parroquia']) ? $config['plantel_parroquia'] : 'Miguel Peña';
            $valNivel        = !empty($config['nivel_educativo']) ? $config['nivel_educativo'] : 'Educación Primaria';
            $valCodigoNivel  = !empty($config['nivel_codigo']) ? $config['nivel_codigo'] : '21000';

            $catalogoUbicacion = $model->getCatalogoUbicacion();
            $nivelesEducativos = $model->getNivelesEducativos();

            $datos = [
                'config'               => $config,
                'flash'                => $flash,
                'csrfToken'            => csrfToken(),
                'directorConfigurado'  => $directorConfigurado,
                'mensajeDirector'      => $mensajeDirector,
                'nombreCompletoDir'    => $nombreCompletoDir,
                'dirPrimerNombre'      => $dirPrimerNombre,
                'dirSegundoNombre'     => $dirSegundoNombre,
                'dirPrimerApellido'    => $dirPrimerApellido,
                'dirSegundoApellido'   => $dirSegundoApellido,
                'valEstado'            => $valEstado,
                'valMunicipio'         => $valMunicipio,
                'valParroquia'         => $valParroquia,
                'valNivel'             => $valNivel,
                'valCodigoNivel'       => $valCodigoNivel,
                'ministerioLogoUrl'    => Configuracion::asset('ministerio_logo'),
                'plantelLogoUrl'       => Configuracion::asset('plantel_logo'),
                'tieneCintillo'        => $tieneCintillo,
                'plantelCintilloUrl'   => $cintilloUrl,
                'esCintilloOficial'    => $esCintilloOficial,
                'tieneFirma'           => $tieneFirma,
                'firmaUrl'             => $tieneFirma ? (BASE_URL . 'configuracionInstitucional/verPrivado/director_firma?v=' . filemtime($rutaFirma)) : '',
                'tieneSello'           => $tieneSello,
                'selloUrl'             => $tieneSello ? (BASE_URL . 'configuracionInstitucional/verPrivado/institucion_sello?v=' . filemtime($rutaSello)) : '',
                'tieneFirmaCdce'       => $tieneFirmaCdce,
                'firmaCdceUrl'         => $tieneFirmaCdce ? (BASE_URL . 'configuracionInstitucional/verPrivado/cdce_director_firma?v=' . filemtime($rutaFirmaCdce)) : '',
                'tieneSelloCdce'       => $tieneSelloCdce,
                'selloCdceUrl'         => $tieneSelloCdce ? (BASE_URL . 'configuracionInstitucional/verPrivado/cdce_institucion_sello?v=' . filemtime($rutaSelloCdce)) : '',
                'estados'              => $catalogoUbicacion['estados'] ?? [],
                'municipios'           => $catalogoUbicacion['municipios'] ?? [],
                'parroquias'           => $catalogoUbicacion['parroquias'] ?? [],
                'nivelesEducativos'    => $nivelesEducativos,
            ];

            extract($datos);
            require __DIR__ . '/../Views/configuracion_institucional/index.php';
        } catch (Throwable $e) {
            $this->handleError($e, 'Carga de configuración institucional');
        }
    }

    /**
     * Guarda campos de texto de forma modular por sección independiente
     * o masivamente si no se especifica sección.
     */
    public function guardar(): void
    {
        $this->exigirAdmin();
        $this->verificarCsrf();

        try {
            $model = $this->modelo();
            $idUsuario = $this->idUsuarioActual() ?? 0;
            $seccion = $this->normalizarEspacios((string) ($_POST['seccion'] ?? ''));

            if ($seccion === 'plantel') {
                $deaRaw = mb_strtoupper($this->normalizarEspacios((string) ($_POST['plantel_codigo_dea'] ?? '')), 'UTF-8');
                if ($deaRaw !== '' && !preg_match('/^[A-Z0-9]{6,20}$/', $deaRaw)) {
                    $this->responderJsonEstandar(false, [
                        'plantel_codigo_dea' => 'El Código DEA debe contener entre 6 y 20 caracteres alfanuméricos sin espacios ni símbolos (ej. OD05281402).',
                    ], 'El Código DEA no tiene un formato válido.', 422);
                }

                $depRaw = $this->normalizarEspacios((string) ($_POST['plantel_codigo_dependencia'] ?? ''));
                if ($depRaw !== '' && !preg_match('/^\d{6,15}$/', $depRaw)) {
                    $this->responderJsonEstandar(false, [
                        'plantel_codigo_dependencia' => 'El Código de Dependencia debe contener entre 6 y 15 dígitos numéricos (ej. 006562012).',
                    ], 'El Código de Dependencia no tiene un formato válido.', 422);
                }

                $nivelCodRaw = $this->normalizarEspacios((string) ($_POST['nivel_codigo'] ?? ''));
                if ($nivelCodRaw !== '' && !preg_match('/^\d{5}$/', $nivelCodRaw)) {
                    $this->responderJsonEstandar(false, [
                        'nivel_codigo' => 'El Código de Nivel MPPE debe contener exactamente 5 dígitos numéricos (ej. 21000).',
                    ], 'El Código de Nivel MPPE no tiene un formato válido.', 422);
                }

                $campos = [
                    'plantel_nombre'             => $this->normalizarEspacios((string) ($_POST['plantel_nombre'] ?? '')),
                    'plantel_nombre_corto'       => $this->normalizarEspacios((string) ($_POST['plantel_nombre_corto'] ?? '')),
                    'plantel_codigo_dea'         => $deaRaw,
                    'plantel_municipio'          => $this->capitalizarTitulo((string) ($_POST['plantel_municipio'] ?? '')),
                    'plantel_parroquia'          => $this->capitalizarTitulo((string) ($_POST['plantel_parroquia'] ?? '')),
                    'cdce_estado'                => $this->capitalizarTitulo((string) ($_POST['cdce_estado'] ?? '')),
                    'plantel_codigo_dependencia' => $depRaw,
                    'nivel_educativo'            => $this->capitalizarTitulo((string) ($_POST['nivel_educativo'] ?? '')),
                    'nivel_codigo'               => $nivelCodRaw,
                    'lugar_expedicion'           => $this->capitalizarTitulo((string) ($_POST['lugar_expedicion'] ?? '')),
                ];

                $resultado = $model->guardarTextos($campos, $idUsuario, 'plantel');
            } elseif ($seccion === 'directiva') {
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

                $cargoDirector = $this->capitalizarTitulo((string) ($_POST['director_cargo'] ?? ''));

                $cedulaRaw = mb_strtoupper($this->normalizarEspacios((string) ($_POST['director_cedula'] ?? '')), 'UTF-8');
                if ($cedulaRaw !== '') {
                    $digitos = preg_replace('/\D/', '', $cedulaRaw);
                    $letra = substr($cedulaRaw, 0, 1);
                    if (!in_array($letra, ['V', 'E'], true) || strlen($digitos) < 6 || strlen($digitos) > 9) {
                        $this->responderJsonEstandar(false, [
                            'director_cedula' => 'La cédula del director debe ser válida (V o E seguida de 6 a 9 dígitos, ej. V-12.345.678).',
                        ], 'La cédula del director no tiene un formato válido.', 422);
                    }
                    $cedulaDirector = $letra . '-' . $digitos;
                } else {
                    $cedulaDirector = '';
                }

                $cdceNombre = $this->capitalizarTitulo((string) ($_POST['cdce_director_nombre'] ?? ''));
                $cdceCedulaRaw = mb_strtoupper($this->normalizarEspacios((string) ($_POST['cdce_director_cedula'] ?? '')), 'UTF-8');
                if ($cdceCedulaRaw !== '') {
                    $digitosCdce = preg_replace('/\D/', '', $cdceCedulaRaw);
                    $letraCdce = substr($cdceCedulaRaw, 0, 1);
                    if (!in_array($letraCdce, ['V', 'E'], true) || strlen($digitosCdce) < 6 || strlen($digitosCdce) > 9) {
                        $this->responderJsonEstandar(false, [
                            'cdce_director_cedula' => 'La cédula del director(a) CDCE debe ser válida (V o E seguida de 6 a 9 dígitos, ej. V-12.345.678).',
                        ], 'La cédula del director(a) CDCE no tiene un formato válido.', 422);
                    }
                    $cdceCedula = $letraCdce . '-' . $digitosCdce;
                } else {
                    $cdceCedula = '';
                }

                $campos = [
                    'director_nombre'            => $nombreDirector,
                    'director_primer_nombre'     => $pNombre,
                    'director_segundo_nombre'    => $sNombre,
                    'director_primer_apellido'   => $pApellido,
                    'director_segundo_apellido'  => $sApellido,
                    'director_cedula'            => $cedulaDirector,
                    'director_cargo'             => $cargoDirector,
                    'cdce_director_nombre'       => $cdceNombre,
                    'cdce_director_cedula'       => $cdceCedula,
                ];

                $resultado = $model->guardarTextos($campos, $idUsuario, 'directiva');
            } elseif ($seccion === 'membrete') {
                $epigrafeRaw = trim(strip_tags((string) ($_POST['certificado_epigrafe'] ?? '')), " \"'«»");
                $campos = [
                    'ministerio_linea_1'   => $this->normalizarEspacios((string) ($_POST['ministerio_linea_1'] ?? '')),
                    'ministerio_linea_2'   => $this->normalizarEspacios((string) ($_POST['ministerio_linea_2'] ?? '')),
                    'certificado_epigrafe' => mb_strtoupper($this->normalizarEspacios($epigrafeRaw), 'UTF-8'),
                ];

                $resultado = $model->guardarTextos($campos, $idUsuario, 'membrete');
            } else {
                // Guardado masivo completo (fallback)
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

                $cargoDirector = $this->capitalizarTitulo((string) ($_POST['director_cargo'] ?? ''));

                $cedulaRaw = mb_strtoupper($this->normalizarEspacios((string) ($_POST['director_cedula'] ?? '')), 'UTF-8');
                if ($cedulaRaw !== '') {
                    $digitos = preg_replace('/\D/', '', $cedulaRaw);
                    $letra = substr($cedulaRaw, 0, 1);
                    if (!in_array($letra, ['V', 'E'], true) || strlen($digitos) < 6 || strlen($digitos) > 9) {
                        $this->responderJsonEstandar(false, [
                            'director_cedula' => 'La cédula del director debe ser válida (V o E seguida de 6 a 9 dígitos, ej. V-12.345.678).',
                        ], 'La cédula del director no tiene un formato válido.', 422);
                    }
                    $cedulaDirector = $letra . '-' . $digitos;
                } else {
                    $cedulaDirector = '';
                }

                $deaRaw = mb_strtoupper($this->normalizarEspacios((string) ($_POST['plantel_codigo_dea'] ?? '')), 'UTF-8');
                if ($deaRaw !== '' && !preg_match('/^[A-Z0-9]{6,20}$/', $deaRaw)) {
                    $this->responderJsonEstandar(false, [
                        'plantel_codigo_dea' => 'El Código DEA debe contener entre 6 y 20 caracteres alfanuméricos sin espacios ni símbolos (ej. OD05281402).',
                    ], 'El Código DEA no tiene un formato válido.', 422);
                }

                $depRaw = $this->normalizarEspacios((string) ($_POST['plantel_codigo_dependencia'] ?? ''));
                if ($depRaw !== '' && !preg_match('/^\d{6,15}$/', $depRaw)) {
                    $this->responderJsonEstandar(false, [
                        'plantel_codigo_dependencia' => 'El Código de Dependencia debe contener entre 6 y 15 dígitos numéricos (ej. 006562012).',
                    ], 'El Código de Dependencia no tiene un formato válido.', 422);
                }

                $nivelCodRaw = $this->normalizarEspacios((string) ($_POST['nivel_codigo'] ?? ''));
                if ($nivelCodRaw !== '' && !preg_match('/^\d{5}$/', $nivelCodRaw)) {
                    $this->responderJsonEstandar(false, [
                        'nivel_codigo' => 'El Código de Nivel MPPE debe contener exactamente 5 dígitos numéricos (ej. 21000).',
                    ], 'El Código de Nivel MPPE no tiene un formato válido.', 422);
                }

                $epigrafeRaw = trim(strip_tags((string) ($_POST['certificado_epigrafe'] ?? '')), " \"'«»");
                $campos = [
                    'ministerio_linea_1'         => $this->normalizarEspacios((string) ($_POST['ministerio_linea_1'] ?? '')),
                    'ministerio_linea_2'         => $this->normalizarEspacios((string) ($_POST['ministerio_linea_2'] ?? '')),
                    'certificado_epigrafe'       => mb_strtoupper($this->normalizarEspacios($epigrafeRaw), 'UTF-8'),
                    'plantel_nombre'             => $this->normalizarEspacios((string) ($_POST['plantel_nombre'] ?? '')),
                    'plantel_nombre_corto'       => $this->normalizarEspacios((string) ($_POST['plantel_nombre_corto'] ?? '')),
                    'plantel_codigo_dea'         => $deaRaw,
                    'director_nombre'            => $nombreDirector,
                    'director_primer_nombre'     => $pNombre,
                    'director_segundo_nombre'    => $sNombre,
                    'director_primer_apellido'   => $pApellido,
                    'director_segundo_apellido'  => $sApellido,
                    'director_cedula'            => $cedulaDirector,
                    'director_cargo'             => $cargoDirector,
                    'cdce_director_nombre'       => $this->capitalizarTitulo((string) ($_POST['cdce_director_nombre'] ?? '')),
                    'cdce_director_cedula'       => mb_strtoupper($this->normalizarEspacios((string) ($_POST['cdce_director_cedula'] ?? '')), 'UTF-8'),
                    'plantel_municipio'          => $this->capitalizarTitulo((string) ($_POST['plantel_municipio'] ?? '')),
                    'plantel_parroquia'          => $this->capitalizarTitulo((string) ($_POST['plantel_parroquia'] ?? '')),
                    'cdce_estado'                => $this->capitalizarTitulo((string) ($_POST['cdce_estado'] ?? '')),
                    'plantel_codigo_dependencia' => $depRaw,
                    'nivel_educativo'            => $this->capitalizarTitulo((string) ($_POST['nivel_educativo'] ?? '')),
                    'nivel_codigo'               => $nivelCodRaw,
                    'lugar_expedicion'           => $this->capitalizarTitulo((string) ($_POST['lugar_expedicion'] ?? '')),
                ];

                $resultado = $model->guardarTextos($campos, $idUsuario);
            }

            if (!$resultado['ok']) {
                $this->responderJsonEstandar(false, $resultado['errores'] ?? null, $resultado['mensaje'], 422, $resultado['errores'] ?? null);
            }

            $this->responderJsonEstandar(true, [
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
        $this->verificarCsrf();

        try {
            $clave = $this->normalizarEspacios((string) ($_POST['clave'] ?? ''));
            $archivo = $_FILES['imagen'] ?? null;

            if ($archivo === null || !is_array($archivo)) {
                $this->responderJsonEstandar(false, null, 'No se recibió ningún archivo de imagen para procesar.', 422);
            }

            $model = $this->modelo();
            $idUsuario = $this->idUsuarioActual() ?? 0;

            $resultado = $model->procesarYGuardarImagen($clave, $archivo, $idUsuario);

            if (!$resultado['ok']) {
                $this->responderJsonEstandar(false, null, $resultado['mensaje'], 422);
            }

            $esPrivado = in_array($clave, ['director_firma', 'institucion_sello', 'cdce_director_firma', 'cdce_institucion_sello'], true);
            $rutaPrivada = $esPrivado ? Configuracion::rutaPrivada($clave) : null;
            $previewUrl = ($esPrivado && $rutaPrivada !== null && is_file($rutaPrivada))
                ? (BASE_URL . 'configuracionInstitucional/verPrivado/' . $clave . '?v=' . filemtime($rutaPrivada))
                : ($resultado['asset_url'] ?? '');

            $this->responderJsonEstandar(true, [
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
        $this->verificarCsrf();

        try {
            $clave = $this->normalizarEspacios((string) ($_POST['clave'] ?? ''));
            $model = $this->modelo();
            $idUsuario = $this->idUsuarioActual() ?? 0;

            $resultado = $model->eliminarImagen($clave, $idUsuario);

            if (!$resultado['ok']) {
                $this->responderJsonEstandar(false, null, $resultado['mensaje'], 422);
            }

            $esPrivado  = in_array($clave, ['director_firma', 'institucion_sello', 'cdce_director_firma', 'cdce_institucion_sello'], true);
            $esCintillo = ($clave === 'plantel_cintillo');
            $fallbackUrl = ($esPrivado || $esCintillo) ? '' : Configuracion::asset($clave);

            $this->responderJsonEstandar(true, [
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
        $this->verificarCsrf();

        try {
            $clave = $this->normalizarEspacios((string) ($_POST['clave'] ?? ''));
            if ($clave !== 'plantel_cintillo') {
                $this->responderJsonEstandar(false, null, 'Acción no permitida para esta clave.', 422);
            }

            $idUsuario = $this->idUsuarioActual() ?? 0;
            $resultado = $this->modelo()->restablecerFranjaOficial($idUsuario);

            if (!$resultado['ok']) {
                $this->responderJsonEstandar(false, null, $resultado['mensaje'], 422);
            }

            $this->responderJsonEstandar(true, [
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
        if (!in_array($clave, ['director_firma', 'institucion_sello', 'cdce_director_firma', 'cdce_institucion_sello'], true)) {
            http_response_code(404);
            die('<h1>404 - Recurso no encontrado</h1>');
        }

        $nombreArchivo = $this->modelo()->get($clave);
        if ($nombreArchivo === '') {
            http_response_code(404);
            die('<h1>404 - Imagen no configurada</h1>');
        }

        $subcarpeta = match ($clave) {
            'director_firma'         => 'firma',
            'institucion_sello'      => 'sello',
            'cdce_director_firma'    => 'firma_cdce',
            'cdce_institucion_sello' => 'sello_cdce',
            default                  => 'firma',
        };
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

