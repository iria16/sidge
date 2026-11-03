<?php

declare(strict_types=1);

namespace App\Models;

class OcupacionModel extends CatalogModel
{
    protected string $table = 'ocupacion';
    protected string $primaryKey = 'id_ocupacion';

    protected array $columns = ['id_ocupacion', 'nombre', 'estado'];
    protected array $allowedColumns = ['id_ocupacion', 'nombre', 'estado'];
    protected string $campoNombre = 'nombre';
}
