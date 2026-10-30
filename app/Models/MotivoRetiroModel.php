<?php

declare(strict_types=1);

namespace App\Models;

class MotivoRetiroModel extends CatalogoModel
{
    protected string $table = 'motivo_retiro';
    protected string $primaryKey = 'id_motivo_retiro';

    protected array $columns = ['id_motivo_retiro', 'nombre', 'estado'];
    protected array $allowedColumns = ['id_motivo_retiro', 'nombre', 'estado'];
    protected string $campoNombre = 'nombre';
}