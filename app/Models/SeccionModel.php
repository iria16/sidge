<?php

declare(strict_types=1);

namespace App\Models;

class SeccionModel extends CatalogoModel
{
    protected string $table = 'seccion';
    protected string $primaryKey = 'id_seccion';

    protected array $columns = ['id_seccion', 'nombre', 'estado'];
    protected array $allowedColumns = ['id_seccion', 'nombre', 'estado'];
    protected string $campoNombre = 'nombre';
}