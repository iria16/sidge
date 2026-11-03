<?php

declare(strict_types=1);

namespace App\Models;

class NacionalidadModel extends CatalogModel
{
    protected string $table = 'nacionalidad';
    protected string $primaryKey = 'id_nacionalidad';

    protected array $columns = ['id_nacionalidad', 'nombre', 'estado'];
    protected array $allowedColumns = ['id_nacionalidad', 'nombre', 'estado'];
    protected string $campoNombre = 'nombre';

    /** Id del valor por defecto. Lo busca por nombre en vez de hardcodear el 1. */
    public const ID_POR_DEFECTO = 1;

    /**
     * ¿La nacionalidad existe y está ACTIVA?
     *
     * Se consulta el catálogo y no se confía en el id del formulario: un POST
     * manipulado con un id viejo o desactivado no debe guardarse.
     */
    public function estaActiva(int $idNacionalidad): bool
    {
        if ($idNacionalidad <= 0) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM {$this->table}
              WHERE {$this->primaryKey} = :id AND estado = 'ACTIVO'
              LIMIT 1"
        );
        $stmt->execute(['id' => $idNacionalidad]);

        return (bool) $stmt->fetchColumn();
    }
}
