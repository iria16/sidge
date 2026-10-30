<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class ParentescoModel extends CatalogoModel
{
    protected string $table = 'parentesco';
    protected string $primaryKey = 'id_parentesco';

    protected array $columns = ['id_parentesco', 'nombre', 'codigo', 'estado'];
    protected array $allowedColumns = ['id_parentesco', 'nombre', 'codigo', 'estado'];
    protected string $campoNombre = 'nombre';

    /**
     * Nombres de los parentescos ACTIVOS.
     *
     * Los módulos de inscripción y ratificación validan el `familiares_json`
     * contra esta lista: si el catálogo cambia, ambos dejan de aceptar el mismo
     * parentesco sin tocar el controlador.
     *
     * @return string[]
     */
    public function nombresActivos(): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT nombre FROM {$this->table} WHERE estado = 'ACTIVO' ORDER BY nombre"
        );
        $stmt->execute();

        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
}
