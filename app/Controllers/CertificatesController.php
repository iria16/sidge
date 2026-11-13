<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\BinaryResponse;
use App\Models\CertificatesModel;
use App\Support\ConstanciaPdf;
use App\Support\PdfCache;
use DomainException;
use InvalidArgumentException;
use PDO;
use Throwable;

use function App\Traits\sigde_rol_clave;

/**
 * CertificatesController
 * ---------------------
 * Controlador unificado para la emisión oficial de Constancias:
 * - Estudio
 * - Inscripción / Ratificación
 * - Prosecución
 * - Retiro
 *
 * Cumple con los lineamientos de arquitectura y seguridad:
 * 1. Todos los tipos de constancia se gestionan desde una única interfaz con selector de tipo.
 * 2. Validación estricta de elegibilidad en el servidor por tipo de constancia.
 * 3. Restricción de permisos y turnos según el rol de la sesión.
 * 4. Registro formal de auditoría en la bitácora del sistema.
 */
class CertificatesController extends BaseController
{
    protected string $viewPath  = 'constancias';
    protected string $routeName = 'constancias';

    private function modelo(): CertificatesModel
    {
        /** @var CertificatesModel $model */
        $model = $this->getModel();
        return $model;
    }

    protected function createModel(PDO $pdo): object
    {
        return new CertificatesModel($pdo);
    }

    /**
     * Retorna los tipos de constancia a los que tiene permiso el usuario en sesión.
     * Retiro, Prosecución y Egreso son exclusivos del rol secretaria.
     */
    private function tiposPermitidosPorRol(): array
    {
        return CertificatesModel::tiposPermitidosPorRol(sigde_rol_clave($_SESSION['rol'] ?? ''));
    }

    /**
     * Pantalla unificada de Constancias.
     * Carga el listado de estudiantes elegibles según el tipo seleccionado y los filtros aplicados.
     */
    public function index(): void
    {
        try {
            $tiposDisponibles = $this->tiposPermitidosPorRol();
            if (empty($tiposDisponibles)) {
                $this->prohibido('No tiene permisos para acceder al módulo de constancias.');
            }

            // Tipo solicitado por GET o primer tipo disponible por defecto
            $tipo = strtolower(trim((string) ($_GET['tipo'] ?? 'estudio')));
            if (!array_key_exists($tipo, $tiposDisponibles)) {
                $tipo = (string) array_key_first($tiposDisponibles);
            }

            $model = $this->modelo();
            $page  = max(1, (int) ($_GET['page'] ?? 1));
            $perPage = 10;

            $filters = [
                'q'       => trim((string) ($_GET['q'] ?? '')),
                'grado'   => trim((string) ($_GET['grado'] ?? '')),
                'seccion' => trim((string) ($_GET['seccion'] ?? '')),
                'turno'   => $this->turnoSecretariaActual(),
            ];

            $estudiantes    = $model->getListado($tipo, $filters, $page, $perPage);
            $total          = $model->countListado($tipo, $filters);
            $grados         = $model->getGrados();
            $secciones      = $model->getSecciones();

            $paginacion = [
                'desde'         => $total > 0 ? (($page - 1) * $perPage) + 1 : 0,
                'hasta'         => min($page * $perPage, $total),
                'total'         => $total,
                'pagina_actual' => $page,
                'total_paginas' => max(1, (int) ceil($total / $perPage)),
            ];

            $directorConfigurado = $model->estaConfiguradoDirector();
            $flash = $this->getFlashMessages();

            require __DIR__ . '/../Views/constancias/index.php';
        } catch (Throwable $e) {
            $this->handleError($e, static::class . '@index');
        }
    }

    /**
     * Redirecciones de compatibilidad hacia la pantalla unificada index.
     */
    public function estudio(): void
    {
        $this->redirect('index?tipo=estudio');
    }

    public function inscripcion(): void
    {
        $this->redirect('index?tipo=inscripcion');
    }

    public function retiro(): void
    {
        $this->redirect('index?tipo=retiro');
    }

    public function prosecucion(): void
    {
        $this->redirect('index?tipo=prosecucion');
    }

    /**
     * Genera la vista previa del documento sin registrar emisión.
     * La emisión formal se realiza por POST en emitir() o emitirLote().
     */
    public function verPrueba(): void
    {
        $esLote = (int) ($_GET['lote'] ?? 0) === 1;
        $tipoRedireccion = $esLote ? 'egreso' : strtolower(trim((string) ($_GET['tipo'] ?? 'estudio')));

        try {
            if ($esLote) {
                $filters = [
                    'q'       => trim((string) ($_GET['q'] ?? '')),
                    'seccion' => trim((string) ($_GET['seccion'] ?? '')),
                    'turno'   => $this->turnoSecretariaActual(),
                ];
                $lote = $this->prepararLote($filters);
                $pdf = $this->obtenerPdfLote($lote);
                BinaryResponse::pdf($pdf, $lote['filename'], true)->send();
            }

            $tipo = strtolower(trim((string) ($_GET['tipo'] ?? 'estudio')));
            $id = (int) ($_GET['id'] ?? 0);
            $idMatricula = isset($_GET['id_matricula']) ? (int) $_GET['id_matricula'] : null;

            $datos = $this->prepararConstancia($tipo, $id, $idMatricula);
            $pdf = $this->generarPdfConstancia($tipo, $datos['config'], $datos['estudiante']);
            $filename = $this->nombreArchivoConstancia($tipo, $datos['estudiante']);

            BinaryResponse::pdf($pdf, $filename, true)->send();
        } catch (DomainException | InvalidArgumentException $e) {
            $this->setFlash('error', $e->getMessage());
            $this->redirect('index?tipo=' . urlencode($tipoRedireccion));
        } catch (Throwable $e) {
            $this->handleError($e, static::class . '@verPrueba');
        }
    }

    /**
     * Emite la constancia oficial en PDF.
     * Valida la elegibilidad estricta en el servidor y registra en bitácora.
     */
    public function emitir(): void
    {
        $this->exigirPost();

        $tipo = strtolower(trim((string) ($_POST['tipo'] ?? ($_GET['tipo'] ?? 'estudio'))));
        $id   = (int) ($_POST['id'] ?? ($_GET['id'] ?? 0));
        $idMatricula = isset($_POST['id_matricula']) ? (int) $_POST['id_matricula'] : (isset($_GET['id_matricula']) ? (int) $_GET['id_matricula'] : null);

        try {
            $datos = $this->prepararConstancia($tipo, $id, $idMatricula);
            $pdf = $this->generarPdfConstancia($tipo, $datos['config'], $datos['estudiante']);
            $this->registrarEmisionConstancia($datos, $tipo);
            $filename = $this->nombreArchivoConstancia($tipo, $datos['estudiante']);

            $esDescarga = (isset($_GET['descargar']) && $_GET['descargar'] === '1')
                       || (isset($_POST['descargar']) && $_POST['descargar'] === '1');

            BinaryResponse::pdf($pdf, $filename, !$esDescarga)->send();
        } catch (DomainException | InvalidArgumentException $e) {
            $this->setFlash('error', $e->getMessage());
            $this->redirect('index?tipo=' . urlencode($tipo));
        } catch (Throwable $e) {
            $this->handleError($e, static::class . '@emitir');
        }
    }

    /**
     * Emite por lote el cuadernillo de Certificados de Educación Primaria (6.° grado)
     * para todos los estudiantes promovidos bajo los filtros activos (sección, búsqueda y turno).
     */
    public function emitirLote(): void
    {
        $this->exigirPost();

        $filters = [
            'q'       => trim((string) ($_POST['q'] ?? ($_GET['q'] ?? ''))),
            'seccion' => trim((string) ($_POST['seccion'] ?? ($_GET['seccion'] ?? ''))),
            'turno'   => $this->turnoSecretariaActual(),
        ];

        try {
            $lote = $this->prepararLote($filters);
            $pdf = $this->obtenerPdfLote($lote);
            $this->registrarEmisionLote($lote);

            $esDescarga = (isset($_GET['descargar']) && $_GET['descargar'] === '1')
                       || (isset($_POST['descargar']) && $_POST['descargar'] === '1');

            BinaryResponse::pdf($pdf, $lote['filename'], !$esDescarga)->send();
        } catch (DomainException | InvalidArgumentException $e) {
            $this->setFlash('error', $e->getMessage());
            $this->redirect('index?tipo=egreso');
        } catch (Throwable $e) {
            $this->handleError($e, static::class . '@emitirLote');
        }
    }

    private function prepararConstancia(string $tipo, int $id, ?int $idMatricula): array
    {
        $tiposDisponibles = $this->tiposPermitidosPorRol();
        if (!array_key_exists($tipo, $tiposDisponibles)) {
            $this->prohibido('No tiene permisos para emitir este tipo de constancia.');
        }

        if ($id <= 0) {
            throw new InvalidArgumentException('Identificador inválido para la emisión.');
        }

        $model = $this->modelo();
        $estudiante = null;
        $idEstudianteAuditoria = 0;

        if ($tipo === 'retiro') {
            if (!$model->esElegibleParaRetiro($id)) {
                throw new DomainException('El retiro solicitado no existe, no está APROBADO o ya no es el retiro actual del estudiante.');
            }
            $estudiante = $model->getDatosConstanciaRetiro($id);
            if (!$estudiante) {
                throw new DomainException('No se pudieron recuperar los datos del retiro.');
            }
            $idEstudianteAuditoria = (int) $estudiante['id_estudiante'];
        } elseif ($tipo === 'prosecucion') {
            if (!$model->esElegibleParaProsecucion($id)) {
                throw new DomainException('El resultado académico solicitado no existe o no corresponde a una prosecución aprobada (1º a 5º grado) con retiro aprobado.');
            }
            $estudiante = $model->getDatosConstanciaProsecucion($id);
            if (!$estudiante) {
                throw new DomainException('No se pudieron recuperar los datos de prosecución.');
            }
            $idEstudianteAuditoria = (int) $estudiante['id_estudiante'];
        } elseif ($tipo === 'egreso') {
            if (!$model->esElegibleParaEgreso($id)) {
                throw new DomainException('El estudiante seleccionado no posee literal aprobatorio (A–D) ni registro de egreso en 6to grado en el período escolar activo.');
            }
            $estudiante = $model->getDatosConstanciaEgreso($id);
            if (!$estudiante) {
                throw new DomainException('No se pudieron recuperar los datos de egreso.');
            }
            $idEstudianteAuditoria = (int) $estudiante['id_estudiante'];
        } elseif ($tipo === 'inscripcion') {
            if (!$model->esElegibleParaInscripcion($id, $idMatricula)) {
                throw new DomainException('El estudiante no posee un registro de inscripción válido.');
            }
            $idEstudianteAuditoria = $id;
            $estudiante = $model->getEstudianteParaConstancia($id, $idMatricula);
            if (!$estudiante) {
                throw new DomainException('El estudiante solicitado no existe en el sistema o no posee registros de matrícula.');
            }
        } else {
            if (!$model->esElegibleParaEstudio($id)) {
                $this->prohibido('Solo se puede emitir constancia de estudio a estudiantes activos.');
            }
            $idEstudianteAuditoria = $id;
            $estudiante = $model->getEstudianteParaConstancia($id, null);
            if (!$estudiante) {
                throw new DomainException('El estudiante solicitado no existe en el sistema o no posee registros de matrícula activos.');
            }
        }

        if ($idEstudianteAuditoria > 0 && !$this->secretariaTieneAccesoAEstudiante($idEstudianteAuditoria)) {
            throw new DomainException('Ese estudiante no pertenece a su turno asignado.');
        }

        $config = $model->getConfiguracion();
        if (!$model->estaConfiguradoDirector()) {
            throw new DomainException('No se puede emitir la constancia: solicite al administrador que complete los datos del director(a) en Datos Institucionales.');
        }

        return [
            'model'      => $model,
            'config'     => $config,
            'estudiante' => $estudiante,
        ];
    }

    private function prepararLote(array $filters): array
    {
        $tiposDisponibles = $this->tiposPermitidosPorRol();
        if (!array_key_exists('egreso', $tiposDisponibles)) {
            $this->prohibido('No tiene permisos para emitir Certificados de Educación Primaria por lote.');
        }

        if (sigde_rol_clave($_SESSION['rol'] ?? '') === 'secretaria' && (int) ($filters['turno'] ?? 0) <= 0) {
            $this->prohibido('Su usuario no tiene un turno asignado.');
        }

        $model = $this->modelo();
        $candidatos = $model->getEstudiantesEgresoParaLote($filters);
        if ($candidatos === []) {
            throw new DomainException('No se encontraron estudiantes promovidos de 6.° grado para emitir certificados con los filtros seleccionados.');
        }

        $config = $model->getConfiguracion();
        if (!$model->estaConfiguradoDirector()) {
            throw new DomainException('No se pueden emitir los certificados: solicite al administrador que complete los datos del director(a) en Datos Institucionales.');
        }

        $estudiantesData = [];
        foreach ($candidatos as $cand) {
            $idEst = (int) ($cand['id_estudiante'] ?? 0);
            if ($idEst <= 0) {
                continue;
            }
            $datos = $model->getDatosConstanciaEgreso(0, $idEst);
            if ($datos) {
                $estudiantesData[] = $datos;
            }
        }

        if ($estudiantesData === []) {
            throw new DomainException('No se pudieron recuperar los datos de egreso de los estudiantes seleccionados.');
        }

        $sufijoTurno = !empty($filters['turno']) ? '_turno_' . (int) $filters['turno'] : '';
        $sufijoSeccion = $filters['seccion'] !== ''
            ? '_seccion_' . preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $filters['seccion'])
            : '';
        $filename = "certificados_primaria_lote{$sufijoTurno}{$sufijoSeccion}.pdf";

        $cacheKey = 'egresolote_' . md5((string) json_encode([
            'turno'     => (int) ($filters['turno'] ?? 0),
            'seccion'   => (string) ($filters['seccion'] ?? ''),
            'q'         => (string) ($filters['q'] ?? ''),
            'ids'       => array_map(static fn(array $e): int => (int) ($e['id_estudiante'] ?? 0), $estudiantesData),
            'literales' => array_map(static fn(array $e): array => [
                (int) ($e['id_estudiante'] ?? 0),
                (string) ($e['literal'] ?? ''),
                (string) ($e['periodo_escolar'] ?? ''),
            ], $estudiantesData),
            'director'  => [
                (string) ($config['director_nombre'] ?? ''),
                (string) ($config['director_cedula'] ?? ''),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));

        return [
            'model'       => $model,
            'config'      => $config,
            'estudiantes' => $estudiantesData,
            'filename'    => $filename,
            'cacheKey'    => $cacheKey,
            'seccion'     => (string) ($filters['seccion'] ?? ''),
        ];
    }

    private function generarPdfConstancia(string $tipo, array $config, array $estudiante): string
    {
        if ($tipo === 'retiro') {
            return ConstanciaPdf::generarRetiro($config, $estudiante);
        }
        if ($tipo === 'prosecucion') {
            return ConstanciaPdf::generarProsecucion($config, $estudiante);
        }
        if ($tipo === 'egreso') {
            return ConstanciaPdf::generarEgreso($config, $estudiante);
        }
        if ($tipo === 'inscripcion') {
            return ConstanciaPdf::generarInscripcion($config, $estudiante);
        }

        $modalidad = (string) ($estudiante['modalidad'] ?? 'activo');
        return ConstanciaPdf::generarEstudio($config, $estudiante, $modalidad);
    }

    private function obtenerPdfLote(array $lote): string
    {
        $cached = PdfCache::get($lote['cacheKey']);
        if ($cached !== null) {
            $contenido = @file_get_contents($cached);
            if ($contenido !== false) {
                return $contenido;
            }
        }

        $pdf = ConstanciaPdf::generarEgresoPorLote($lote['config'], $lote['estudiantes']);
        PdfCache::put($lote['cacheKey'], $pdf);

        return $pdf;
    }

    private function nombreArchivoConstancia(string $tipo, array $estudiante): string
    {
        $nombreLimpio = preg_replace('/[^A-Za-z0-9_-]/', '_', (string) ($estudiante['nombre_completo'] ?? 'estudiante'));
        return ($tipo === 'egreso')
            ? "certificado_primaria_{$nombreLimpio}.pdf"
            : "constancia_{$tipo}_{$nombreLimpio}.pdf";
    }

    private function registrarEmisionConstancia(array $datos, string $tipo): void
    {
        $idUsuario = (int) ($this->idUsuarioActual() ?? 0);
        if ($idUsuario <= 0) {
            return;
        }

        $estudiante = $datos['estudiante'];
        $accion = ($tipo === 'egreso')
            ? sprintf('EMISIÓN DE CERTIFICADO DE EDUCACIÓN PRIMARIA: %s (%s)', $estudiante['nombre_completo'] ?? 'Estudiante', $estudiante['numero_documento'] ?? 'Sin doc')
            : sprintf('EMISIÓN DE CONSTANCIA (%s): %s (%s)', mb_strtoupper($tipo), $estudiante['nombre_completo'] ?? 'Estudiante', $estudiante['numero_documento'] ?? 'Sin doc');

        $datos['model']->registrarEmision($idUsuario, $accion);
    }

    private function registrarEmisionLote(array $lote): void
    {
        $idUsuario = (int) ($this->idUsuarioActual() ?? 0);
        if ($idUsuario <= 0) {
            return;
        }

        $seccionTexto = $lote['seccion'] !== '' ? " (Sección {$lote['seccion']})" : '';
        $lote['model']->registrarEmision(
            $idUsuario,
            sprintf('EMISIÓN POR LOTE DE CERTIFICADOS DE EDUCACIÓN PRIMARIA: %d estudiantes%s', count($lote['estudiantes']), $seccionTexto)
        );
    }

    private function exigirPost(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            return;
        }

        header('Allow: POST');
        http_response_code(405);

        if (function_exists('esPeticionAjax') && esPeticionAjax() && function_exists('responderJson')) {
            responderJson(405, 'Método no permitido.');
        }

        exit('Método no permitido.');
    }
}
