<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AcademicYearModel;
use App\Models\AuditLogModel;
use App\Models\AcademicResultModel;
use PDO;
use Throwable;
use function App\Traits\sigde_rol_clave;

/**
 * AcademicResultController
 * Controlador para la gestión de resultados académicos de fin de año.
 *
 * El docente registra el literal (A-E) y el estado se deriva automáticamente:
 * - A-D = PROMOVIDO
 * - E   = NO_PROMOVIDO
 * - Sin literal = PENDIENTE
 *
 * Acceso exclusivo para docentes. Directivo y coordinador ven estadísticas
 * en el módulo de Estadísticas > Rendimiento y Literales por Aula.
 */
class AcademicResultController extends BaseController
{
    protected string $viewPath  = 'resultado_academico';
    protected string $routeName = 'resultadoAcademico';

    private function modelo(): AcademicResultModel
    {
        /** @var AcademicResultModel $model */
        $model = $this->getModel();

        return $model;
    }

    protected function createModel(PDO $pdo): object
    {
        return new AcademicResultModel($pdo);
    }

    /**
     * Bandeja principal de resultados académicos:
     * Lista de secciones agrupadas por año escolar.
     */
    public function index(): void
    {
        $idUsuario = (int) ($this->idUsuarioActual() ?? 0);
        $rol = sigde_rol_clave($_SESSION['rol'] ?? '');

        // Solo docente tiene acceso a este módulo
        if ($rol !== 'docente') {
            $this->setFlash('error', 'Solo los docentes pueden acceder a la carga de literales.');
            $this->redirect('index');
        }

        $academicYearModel = new AcademicYearModel($this->conexion());
        $idAnioActivo = $academicYearModel->getActiveId();

        // Docente solo ve el período activo
        $idAnio = $idAnioActivo;
        $todosAnios = $academicYearModel->getAll();
        $anios = array_values(array_filter(
            $todosAnios,
            static fn ($a): bool => (int) ($a['id_anio_escolar'] ?? $a['id'] ?? 0) === (int) $idAnioActivo
        ));

        $secciones = $this->modelo()->getSeccionesPorAnio($idAnio, $idUsuario, $rol);

        $nombreAnioActivo = $academicYearModel->getActiveName() ?: 'En curso';
        $nombreUsuarioActual = $_SESSION['nombre'] ?? '';

        $flash = $this->getFlashMessages();
        $data = [
            'secciones'           => $secciones,
            'anios'               => $anios,
            'idAnio'              => $idAnio,
            'nombreAnioActivo'    => $nombreAnioActivo,
            'nombreUsuarioActual' => $nombreUsuarioActual,
            'rolUsuario'          => $rol,
            'flash_error'         => $flash['error'],
            'flash_success'       => $flash['success'],
        ];

        extract($data);
        require __DIR__ . '/../Views/' . $this->viewPath . '/index.php';
    }

    /**
     * Vista de nómina de estudiantes de una sección específica
     * para carga o confirmación de resultados.
     */
    public function seccion(): void
    {
        $idUsuario = (int) ($this->idUsuarioActual() ?? 0);
        $rol = sigde_rol_clave($_SESSION['rol'] ?? '');

        // Solo docente tiene acceso
        if ($rol !== 'docente') {
            $this->setFlash('error', 'Solo los docentes pueden acceder a la carga de literales.');
            $this->redirect('index');
        }

        $idEstructura = (int) ($_GET['id'] ?? 0);
        if ($idEstructura <= 0) {
            $seccionActiva = (new \App\Models\TeacherAssignmentModel($this->conexion()))
                ->getSeccionActivaDeDocente($idUsuario);
            $idEstructura = (int) ($seccionActiva['id_estructura_academica'] ?? 0);
        }

        if ($idEstructura <= 0) {
            $this->setFlash('error', 'Sección no válida.');
            $this->redirect('index');
        }

        $detalle = $this->modelo()->getDetalleSeccion($idEstructura, $idUsuario, $rol);
        if (!$detalle) {
            $this->setFlash('error', 'No tiene permisos para acceder a esta sección o la sección no pertenece al período escolar activo.');
            $this->redirect('index');
        }

        $filtrosIniciales = ['pendiente' => 'PENDIENTE', 'cargados' => 'CARGADOS'];
        $estadoSolicitado = strtolower(trim((string) ($_GET['estado'] ?? '')));
        $filtroEstadoInicial = $filtrosIniciales[$estadoSolicitado] ?? 'todos';

        $flash = $this->getFlashMessages();
        $data = [
            'detalle'             => $detalle,
            'rolUsuario'          => $rol,
            'esDocenteAsignada'   => true,
            'anioCerrado'         => strtoupper((string) ($detalle['estado_anio'] ?? '')) === 'CERRADO',
            'literales'           => AcademicResultModel::LITERALES_VALIDOS,
            'literalesPromueven'  => AcademicResultModel::LITERALES_PROMUEVEN,
            'literalesExigenObservacion' => AcademicResultModel::LITERALES_EXIGEN_OBSERVACION,
            'filtroEstadoInicial' => $filtroEstadoInicial,
            'flash_error'         => $flash['error'],
            'flash_success'       => $flash['success'],
            'flash_info'          => $flash['info'],
        ];

        extract($data);
        require __DIR__ . '/../Views/' . $this->viewPath . '/seccion.php';
    }

    /**
     * Carga de propuesta académica por el docente (o directivo).
     * El estado de los resultados se guarda como PENDIENTE de confirmación.
     */
    public function guardarPropuesta(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->setFlash('error', 'Método no permitido.');
            $this->redirect('index');
        }

        $idEstructura = (int) ($_POST['id_estructura_academica'] ?? 0);
        if ($idEstructura <= 0) {
            $this->setFlash('error', 'Estructura académica inválida.');
            $this->redirect('index');
        }

        $estudiantesData = $_POST['estudiantes'] ?? [];
        if (!is_array($estudiantesData)) {
            $estudiantesData = [];
        }

        $idUsuario = (int) ($this->idUsuarioActual() ?? 0);
        $rol = sigde_rol_clave($_SESSION['rol'] ?? '');

        // Si es petición AJAX
        $esAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        try {
            $res = $this->modelo()->guardarPropuesta($idEstructura, $estudiantesData, $idUsuario, $rol);

            $r = $res['resumen'] ?? [
                'guardados'           => 0,
                'promovidos'          => 0,
                'no_promovidos'       => 0,
                'sin_literal'         => 0,
                'omitidos'            => 0,
            ];

            /* La nómina se guarda por AJAX: el front necesita saber qué filas
               quedaron bien y cuáles no para pintar el estado de cada una. */
            $datosAjax = [
                'guardados_ids' => $r['guardados_ids'] ?? [],
                'errores'       => $r['errores'] ?? [],
            ];

            /* Cada guardado de literales queda en la bitácora: quién lo hizo,
               sobre qué sección, y cuántas filas de cada tipo. */
            if ($res['ok'] && $idUsuario > 0) {
                (new AuditLogModel($this->conexion()))->registrar(
                    $idUsuario,
                    sprintf(
                        'LITERALES: %d guardados (%d prom, %d no prom, %d sin lit) · %d omitidos · estruct %d',
                        (int) ($r['guardados'] ?? 0),
                        (int) ($r['promovidos'] ?? 0),
                        (int) ($r['no_promovidos'] ?? 0),
                        (int) ($r['sin_literal'] ?? 0),
                        (int) ($r['omitidos'] ?? 0),
                        (int) $idEstructura
                    ),
                    'Resultado académico'
                );
            }

            if (!$res['ok']) {
                if ($esAjax) {
                    $this->jsonResponse(false, $datosAjax, $res['mensaje'], 422);
                }
                $this->setFlash('error', $res['mensaje']);
                $this->redirect('seccion', ['id' => $idEstructura]);
            }

            if ($esAjax) {
                $this->jsonResponse(true, $datosAjax, $res['mensaje'], 200);
            }

            $tipoFlash = ($r['guardados'] ?? 0) > 0 ? 'success' : 'info';
            $this->setFlash($tipoFlash, $res['mensaje']);
            $this->redirect('seccion', ['id' => $idEstructura]);
        } catch (Throwable $e) {
            if ($esAjax) {
                $this->jsonError($e, 'Guardar propuesta académica');
            }
            $this->handleError($e, static::class . '@guardarPropuesta');
        }
    }
}
