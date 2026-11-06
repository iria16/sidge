<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\ReingresoModel;
use App\Support\Instituciones;
use PDO;
use Throwable;

class ReingresoController extends BaseController
{
    protected string $viewPath = 'reingreso';
    protected string $routeName = 'reingreso';

    protected function createModel(PDO $pdo): ?object
    {
        return new ReingresoModel($pdo);
    }

    /**
     * Muestra la vista principal de Reingreso con el historial de reincorporaciones y estadísticas.
     */
    public function index(): void
    {
        try {
            $filters = [
                'q' => trim((string) ($_GET['q'] ?? '')),
            ];
            $pagina = max(1, (int) ($_GET['pagina'] ?? 1));

            /** @var ReingresoModel $model */
            $model = $this->getModel();
            $turno = $this->turnoSecretariaActual();

            $stats = $model->getStats($turno);
            $resultado = $model->listarReingresos($filters, $pagina, 10, $turno);
            $solicitudes = $resultado['solicitudes'];
            $paginacion = $resultado['paginacion'];

            // Catálogos y año escolar activo reutilizando RatificationModel
            $ratModel = new \App\Models\RatificationModel($this->pdo);
            $anioDestino = $ratModel->getAnioDestino();
            $idAnioActivo = (int) ($anioDestino['id'] ?? 0);

            // Estructuras académicas del año activo con cupos disponibles para el modal
            $estructuras = $model->estructurasConCupo($idAnioActivo > 0 ? $idAnioActivo : null, $turno);

            // Instituciones de procedencia activas (solo escuelas y etapas de estudiante, como en inscripción)
            $idsEscuelas = Instituciones::idsCatalogoDeEstudiante();
            $marcadores = implode(', ', array_fill(0, count($idsEscuelas), '?'));
            $stmtInst = $this->pdo->prepare(
                "SELECT id_institucion, nombre
                   FROM institucion
                  WHERE estado = 'ACTIVO'
                    AND id_tipo_institucion IN ({$marcadores})
                  ORDER BY nombre"
            );
            $stmtInst->execute($idsEscuelas);
            $instituciones = $stmtInst->fetchAll(PDO::FETCH_ASSOC);

            // Catálogos para el modal de edición de datos del estudiante, familiares y recaudos (reutilizados de Ratificación/Inscripción)
            $catFamiliares = $ratModel->getCatalogosFamiliar();
            $estados = $catFamiliares['estados'] ?? [];
            $nacionalidades = $ratModel->getNacionalidades();

            $minDateEstudiante = (((int) ($anioDestino['anio_inicio'] ?? date('Y')) ?: (int) date('Y')) - 14) . '-01-01';
            $maxDateEstudiante = (((int) ($anioDestino['anio_inicio'] ?? date('Y')) ?: (int) date('Y')) - 6) . '-12-31';

            require __DIR__ . '/../Views/gestion_estudiante/reingreso/index.php';
        } catch (Throwable $e) {
            $this->handleError($e, static::class);
        }
    }

    /**
     * Busca estudiantes por cédula o nombre para el modal de reingreso (AJAX GET).
     */
    public function buscarEstudiante(): void
    {
        $termino = trim((string) ($_GET['q'] ?? ''));
        if (mb_strlen($termino, 'UTF-8') < 3) {
            $this->jsonResponse(true, ['resultados' => [], 'aviso' => null, 'esperando' => true], null);
            return;
        }

        try {
            /** @var ReingresoModel $model */
            $model = $this->getModel();
            $this->jsonResponse(true, [
                'resultados' => $model->buscarPorDocumento(
                    $termino,
                    10,
                    $this->turnoSecretariaActual()
                ),
                'aviso'      => null,
                'esperando'  => false,
            ]);
        } catch (Throwable $e) {
            $this->jsonError($e, static::class);
        }
    }

    /**
     * Obtiene los datos del estudiante retirado y las estructuras disponibles
     * para precargar el modal de reingreso (AJAX GET).
     */
    public function datos(): void
    {
        $idEstudiante = (int) ($_GET['id'] ?? 0);
        if ($idEstudiante <= 0) {
            $this->jsonResponse(false, null, 'Estudiante no especificado.', 400);
            return;
        }

        try {
            /** @var ReingresoModel $model */
            $model = $this->getModel();
            $estudiante = $model->getDatosEstudiante($idEstudiante);

            if (!$estudiante) {
                $this->jsonResponse(false, null, 'No se encontró un retiro vigente para este estudiante.', 404);
                return;
            }

            // Estructuras académicas con cupos disponibles
            $estructuras = $model->estructurasConCupo();

            // Instituciones activas para procedencia (solo escuelas, como en inscripción)
            $idsEscuelas = Instituciones::idsCatalogoDeEstudiante();
            $marcadores = implode(', ', array_fill(0, count($idsEscuelas), '?'));
            $stmtInst = $this->pdo->prepare(
                "SELECT id_institucion, nombre
                   FROM institucion
                  WHERE estado = 'ACTIVO'
                    AND id_tipo_institucion IN ({$marcadores})
                  ORDER BY nombre"
            );
            $stmtInst->execute($idsEscuelas);
            $instituciones = $stmtInst->fetchAll(PDO::FETCH_ASSOC);

            $this->jsonResponse(true, [
                'estudiante'    => $estudiante,
                'estructuras'   => $estructuras,
                'instituciones' => $instituciones,
            ], 'Datos cargados exitosamente.');
        } catch (Throwable $e) {
            $this->jsonError($e, static::class);
        }
    }

    /**
     * Procesa la solicitud de reincorporación formal (AJAX POST).
     */
    public function guardar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(false, null, 'Método no permitido.', 405);
            return;
        }

        try {
            $datos = [
                'id_estudiante'              => (int) ($_POST['id_estudiante'] ?? 0),
                'id_estructura_academica'    => (int) ($_POST['id_estructura_academica'] ?? 0),
                'motivo_reingreso'           => trim((string) ($_POST['motivo_reingreso'] ?? '')),
                'fecha_reincorporacion'      => trim((string) ($_POST['fecha_reincorporacion'] ?? '')) ?: date('Y-m-d'),
                'id_institucion_procedencia' => !empty($_POST['id_institucion_procedencia']) ? (int) $_POST['id_institucion_procedencia'] : null,
                'observaciones'              => !empty($_POST['observaciones']) ? trim((string) $_POST['observaciones']) : null,
            ];

            $idUsuario = (int) ($_SESSION['usuario_id'] ?? 0);

            /** @var ReingresoModel $model */
            $model = $this->getModel();
            $resultado = $model->procesarReingreso($datos, $idUsuario);

            $this->jsonResponse(true, $resultado, 'Estudiante reincorporado correctamente.');
        } catch (Throwable $e) {
            $this->jsonResponse(false, null, $e->getMessage(), 422);
        }
    }
}