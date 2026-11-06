<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\ReingresoModel;
use PDO;
use Throwable;

class ReingresoController extends BaseController
{
    protected string $viewPath = 'reingreso';
    protected string $routeName = 'reingreso';

    protected function createModel(): ReingresoModel
    {
        return new ReingresoModel($this->pdo);
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

            // Instituciones activas para procedencia opcional
            $stmtInst = $this->pdo->query(
                "SELECT id_institucion, nombre FROM institucion WHERE estado = 'ACTIVO' ORDER BY nombre"
            );
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