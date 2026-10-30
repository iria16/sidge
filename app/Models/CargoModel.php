<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Modelo para la gestión del catálogo de Cargos institucionales/laborales.
 * Esquema: id_cargo (PK), nombre, estado ('ACTIVO'|'INACTIVO').
 */
class CargoModel extends CatalogModel
{
    protected string $table = 'cargo';
    protected string $primaryKey = 'id_cargo';

    protected array $columns = ['id_cargo', 'nombre', 'estado'];
    protected array $allowedColumns = ['id_cargo', 'nombre', 'estado'];
    protected string $campoNombre = 'nombre';
}
