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
    /**
     * @param string|string[] $roles
     * @return int[]
     */
    public function destinatariosPorRol(string|array $roles): array
    {
        $listaRoles = is_array($roles) ? array_values(array_filter($roles)) : [$roles];
        if ($listaRoles === []) {
            return [];
        }

        $rolesLower = [];
        foreach ($listaRoles as $r) {
            $rLower = mb_strtolower(trim((string) $r), 'UTF-8');
            if ($rLower === '') {
                continue;
            }
            $rolesLower[] = $rLower;
            if (in_array($rLower, ['directivo', 'directiva', 'director', 'directora', 'direccion', 'dirección'], true)) {
                $rolesLower[] = 'directivo';
                $rolesLower[] = 'directiva';
                $rolesLower[] = 'director';
                $rolesLower[] = 'directora';
                $rolesLower[] = 'direccion';
                $rolesLower[] = 'dirección';
            }
            if (in_array($rLower, ['admin', 'administrador', 'administradora'], true)) {
                $rolesLower[] = 'admin';
                $rolesLower[] = 'administrador';
                $rolesLower[] = 'administradora';
            }
            if (in_array($rLower, ['secretaria', 'secretario', 'secretaría'], true)) {
                $rolesLower[] = 'secretaria';
                $rolesLower[] = 'secretario';
                $rolesLower[] = 'secretaría';
            }
            if (in_array($rLower, ['coordinador', 'coordinadora', 'coordinacion', 'coordinación'], true)) {
                $rolesLower[] = 'coordinador';
                $rolesLower[] = 'coordinadora';
                $rolesLower[] = 'coordinacion';
                $rolesLower[] = 'coordinación';
            }
            if (in_array($rLower, ['docente', 'profesor', 'profesora'], true)) {
                $rolesLower[] = 'docente';
                $rolesLower[] = 'profesor';
                $rolesLower[] = 'profesora';
            }
        }
        $rolesLower = array_values(array_unique($rolesLower));
        if ($rolesLower === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($rolesLower), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT u.id_usuario
               FROM usuario u
               INNER JOIN rol r ON r.id_rol = u.id_rol
              WHERE UPPER(u.estado) = 'ACTIVO'
                AND LOWER(TRIM(r.nombre)) IN ({$placeholders})"
        );
        $stmt->execute($rolesLower);

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
        string $urlDestino
    ): void {
        $ids = is_array($destinatarios) ? $destinatarios : [$destinatarios];
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));

        if ($ids === []) {
            return;
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO notificacion
                (id_usuario_destino, tipo, titulo, mensaje, url_destino)
             VALUES
                (:usuario, :tipo, :titulo, :mensaje, :url)"
        );

        foreach ($ids as $id) {
            $stmt->execute([
                'usuario'     => $id,
                'tipo'        => $tipo,
                'titulo'      => $titulo,
                'mensaje'     => $mensaje,
                'url'         => $urlDestino,
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
