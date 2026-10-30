<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\NotificationModel;
use PDO;

class NotificationController extends BaseController
{
    protected function createModel(PDO $pdo): object
    {
        return new NotificationModel($pdo);
    }

    private function modelo(): NotificationModel
    {
        /** @var NotificationModel $model */
        $model = $this->getModel();
        return $model;
    }

    private function usuarioActual(): int
    {
        $id = $this->idUsuarioActual();
        if ($id === null || $id <= 0) {
            $this->jsonResponse(false, null, 'Sesión no válida.', 401);
        }

        return $id;
    }

    public function contador(): void
    {
        $idUsuario = $this->usuarioActual();
        $total = $this->modelo()->contarNoLeidas($idUsuario);

        $this->jsonResponse(true, ['total' => $total]);
    }

    public function listar(): void
    {
        $idUsuario = $this->usuarioActual();
        $items = $this->modelo()->listarUltimas($idUsuario, 10);

        $this->jsonResponse(true, ['items' => $items]);
    }

    public function leer(): void
    {
        $idUsuario = $this->usuarioActual();
        $id = (int) ($_POST['id'] ?? 0);

        if ($id <= 0) {
            $this->jsonResponse(false, null, 'Notificación no válida.', 422);
        }

        $this->modelo()->marcarLeida($id, $idUsuario);

        $this->jsonResponse(true, ['total' => $this->modelo()->contarNoLeidas($idUsuario)]);
    }

    public function leerTodas(): void
    {
        $idUsuario = $this->usuarioActual();
        $this->modelo()->marcarTodas($idUsuario);

        $this->jsonResponse(true, ['total' => 0]);
    }
}
