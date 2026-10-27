<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\RecuperacionAccesoModel;
use App\Support\Validator;
use App\Traits\DetalleJsonTrait;
use App\Traits\ListableIndexTrait;
use PDO;
use Throwable;

/**
 * Lado administrador de la recuperación de acceso: aprueba o rechaza solicitudes (creadas por AuthController::requestRecovery).
 * Al aprobar genera una clave provisional; también permite restablecer la clave de cualquier usuario sin solicitud.
 * Vistas en Views/usuarios/recuperacion; updateEstado, getDetalle y resetPassword deben estar en el whitelist de public/index.php.
 */
class RecuperacionAccesoController extends BaseController
{
    use DetalleJsonTrait, ListableIndexTrait;

    /** Carpeta de vistas del módulo (Views/usuarios/recuperacion). */
    protected string $viewPath    = 'usuarios/recuperacion';

    /** Segmento de ruta del controlador; lo usa redirect() del BaseController. */
    protected string $routeName   = 'recuperacionAcceso';

    /**
     * Devuelve el modelo con su tipo real, porque getModel() retorna ?object y el editor no resuelve sus métodos.
     * Este envoltorio fija el tipo de createModel() y evita los subrayados rojos.
     */
    private function modelo(): RecuperacionAccesoModel
    {
        /** @var RecuperacionAccesoModel $model */
        $model = $this->getModel();

        return $model;
    }

    protected function createModel(PDO $pdo): object
    {
        return new RecuperacionAccesoModel($pdo);
    }

    /**
     * Etiqueta y clase visual (status-badge) de cada estado, con la clave en minúsculas como la devuelve el modelo.
     * Se pasa a la vista como $estadosMeta para que no repita este mapa.
     */
    private const ESTADOS_META = [
        'pendiente' => ['label' => 'Pendiente', 'clase' => 'pending'],
        'aprobado'  => ['label' => 'Aprobado',  'clase' => 'active'],
        'rechazado' => ['label' => 'Rechazado', 'clase' => 'inactive'],
    ];

    /**
     * Lee los filtros del listado desde la URL (gancho de ListableIndexTrait).
     *
     * @return array{estado: string, q: string}
     */
    protected function getFiltersFromRequest(): array
    {
        return [
            'estado' => trim($_GET['estado'] ?? ''),
            'q'      => trim($_GET['q'] ?? ''),
        ];
    }

    /**
     * Variables extra del listado: solicitudes, stats, estadosMeta (ESTADOS_META)
     * y usuariosDisponibles (para el restablecimiento manual de contraseña).
     */
    protected function getExtraIndexData(array $items, array $filters): array
    {
        $model = $this->modelo();

        return [
            'solicitudes'         => $items,
            'stats'               => $model->getStats(),
            'estadosMeta'         => self::ESTADOS_META,
            'usuariosDisponibles' => $model->getUsuariosDisponibles(),
        ];
    }

    /**
     * Aprueba o rechaza una solicitud pendiente (recuperacionAcceso/updateEstado, POST, JSON).
     * Recibe `id` y `accion` ('aprobar' o 'rechazar'); aprobar devuelve la clave provisional en `data`.
     * Si el modelo devuelve null/false la solicitud ya fue atendida o no existe (409), evitando doble proceso.
     */
    public function updateEstado(): void
    {
        $validator = Validator::make($_POST, [
            'id'     => 'required|integer|min_value:1',
            'accion' => 'required|string|in:aprobar,rechazar',
        ], [
            'id.required'     => 'ID de solicitud requerido.',
            'id.min_value'    => 'ID de solicitud inválido.',
            'accion.required' => 'Acción requerida.',
            'accion.in'       => 'Acción inválida.',
        ]);

        if (!$validator->passes()) {
            $this->jsonResponse(false, $validator->errors(), implode(' ', $validator->errors()), 422);
        }

        $id     = (int) $_POST['id'];
        $accion = (string) $_POST['accion'];

        $model = $this->modelo();

        try {
            if ($accion === 'aprobar') {
                $resultado = $model->aprobarSolicitud($id, $this->idUsuarioActual());

                if ($resultado === null) {
                    $this->jsonResponse(false, null, 'La solicitud ya fue atendida o no existe.', 409);
                }

                $this->jsonResponse(true, $resultado, 'Solicitud aprobada. Contraseña provisional generada.');
            }

            $rechazada = $model->rechazarSolicitud($id, $this->idUsuarioActual());

            if (!$rechazada) {
                $this->jsonResponse(false, null, 'La solicitud ya fue atendida o no existe.', 409);
            }

            $this->jsonResponse(true, null, 'Solicitud rechazada.');
        } catch (Throwable $e) {
            $this->jsonError($e, 'Recuperación de Acceso');
        }
    }

    /**
     * Devuelve el detalle de una solicitud en JSON para el modal (recuperacionAcceso/getDetalle?id=..., GET).
     * Delega el flujo estándar 400/404/500/200 en responderDetalle() de DetalleJsonTrait.
     */
    public function getDetalle(): void
    {
        $this->responderDetalle(
            'getDetalleSolicitud',
            'ID inválido.',
            'La solicitud no existe.',
            'Recuperación de Acceso'
        );
    }

    /**
     * Restablece la contraseña de un usuario sin solicitud previa (recuperacionAcceso/resetPassword, POST, JSON).
     * Devuelve en `data` la clave provisional, el usuario y el nombre para mostrarlos en el modal.
     * Ojo: la clave viaja en el JSON, así que no debe escribirse en logs ni guardarse en el front.
     */
    public function resetPassword(): void
    {
        $validator = Validator::make($_POST, [
            'usuario_id' => 'required|integer|min_value:1',
        ], [
            'usuario_id.required'  => 'Debes seleccionar un usuario.',
            'usuario_id.min_value' => 'Debes seleccionar un usuario.',
        ]);

        if (!$validator->passes()) {
            $this->jsonResponse(false, $validator->errors(), implode(' ', $validator->errors()), 422);
        }

        $usuarioId = (int) $_POST['usuario_id'];

        $model = $this->modelo();

        try {
            $claveProvisional = $model->resetPassword($usuarioId, $this->idUsuarioActual());
            $usuario           = $model->getUsuarioBasico($usuarioId);

            $this->jsonResponse(true, [
                'clave'   => $claveProvisional,
                'usuario' => $usuario['usuario'] ?? '',
                'nombre'  => $usuario['nombre'] ?? '',
            ], 'Contraseña restablecida correctamente.');
        } catch (Throwable $e) {
            $this->jsonError($e, 'Recuperación de Acceso');
        }
    }
}