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

    /** Turnos exclusivos de personal/empleados que no aplican para matrícula estudiantil. */
    public const TURNO_COMPLETO = 'Tiempo Completo';
    public const TURNOS_EXCLUSIVOS_PERSONAL = [self::TURNO_COMPLETO];

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

    /**
     * Comprueba si un turno es exclusivo de personal y no puede usarse en secciones estudiantiles.
     */
    public function esTurnoExclusivoPersonal(int $id): bool
    {
        $nombre = $this->getNombreById($id);
        if ($nombre === null) {
            return false;
        }
        return in_array($nombre, self::TURNOS_EXCLUSIVOS_PERSONAL, true);
    }
}
