<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class NotificationModel
{
    public function __construct(private PDO $pdo) {}

    /**
     * IDs de usuarios activos con un rol dado (por nombre de rol).
     *
     * @return int[]
     */
    public function destinatariosPorRol(string $rol): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT u.id_usuario
               FROM usuario u
               INNER JOIN rol r ON r.id_rol = u.id_rol
              WHERE u.estado = 'ACTIVO'
                AND r.nombre = :rol"
        );
        $stmt->execute(['rol' => $rol]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Crea una notificación para uno o varios destinatarios.
     *
     * @param int|int[] $destinatarios
     */
    public function crear(
        int|array $destinatarios,
        string $tipo,
        string $titulo,
        string $mensaje,
        string $urlDestino,
        ?int $idReferencia = null
    ): void {
        $ids = is_array($destinatarios) ? $destinatarios : [$destinatarios];
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));

        if ($ids === []) {
            return;
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO notificacion
                (id_usuario_destino, tipo, titulo, mensaje, url_destino, id_referencia)
             VALUES
                (:usuario, :tipo, :titulo, :mensaje, :url, :referencia)"
        );

        foreach ($ids as $id) {
            $stmt->execute([
                'usuario'     => $id,
                'tipo'        => $tipo,
                'titulo'      => $titulo,
                'mensaje'     => $mensaje,
                'url'         => $urlDestino,
                'referencia'  => $idReferencia,
            ]);
        }
    }

    public function contarNoLeidas(int $idUsuario): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM notificacion WHERE id_usuario_destino = :usuario AND leida = 0"
        );
        $stmt->execute(['usuario' => $idUsuario]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listarUltimas(int $idUsuario, int $limite = 10): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id_notificacion, tipo, titulo, mensaje, url_destino, leida, fecha_creacion
               FROM notificacion
              WHERE id_usuario_destino = :usuario
              ORDER BY fecha_creacion DESC, id_notificacion DESC
              LIMIT :limite"
        );
        $stmt->bindValue('usuario', $idUsuario, PDO::PARAM_INT);
        $stmt->bindValue('limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function marcarLeida(int $idNotificacion, int $idUsuario): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE notificacion
                SET leida = 1, fecha_lectura = NOW()
              WHERE id_notificacion = :id
                AND id_usuario_destino = :usuario
                AND leida = 0"
        );
        $stmt->execute(['id' => $idNotificacion, 'usuario' => $idUsuario]);

        return $stmt->rowCount() > 0;
    }

    public function marcarTodas(int $idUsuario): int
    {
        $stmt = $this->pdo->prepare(
            "UPDATE notificacion
                SET leida = 1, fecha_lectura = NOW()
              WHERE id_usuario_destino = :usuario
                AND leida = 0"
        );
        $stmt->execute(['usuario' => $idUsuario]);

        return $stmt->rowCount();
    }
}
