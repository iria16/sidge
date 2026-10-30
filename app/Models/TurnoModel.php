<?php

declare(strict_types=1);

namespace App\Models;

class TurnoModel extends CatalogModel
{
    protected string $table = 'turno';
    protected string $primaryKey = 'id_turno';

    protected array $columns = ['id_turno', 'nombre', 'estado'];
    protected array $allowedColumns = ['id_turno', 'nombre', 'estado'];
    protected string $campoNombre = 'nombre';

    /** Nombre visible del turno; null si el id no existe o no es válido. */
    public function getNombreById(int $id): ?string
    {
        if ($id <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT nombre FROM turno WHERE id_turno = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $nombre = $stmt->fetchColumn();

        return $nombre === false ? null : (string) $nombre;
    }
}
