<?php

declare(strict_types=1);

namespace App\Models;

class RolModel extends CatalogoModel
{
    protected string $table = 'rol';
    protected string $primaryKey = 'id_rol';

    protected array $columns = ['id_rol', 'nombre', 'estado'];
    protected array $allowedColumns = ['id_rol', 'nombre', 'estado'];
    protected string $campoNombre = 'nombre';
}