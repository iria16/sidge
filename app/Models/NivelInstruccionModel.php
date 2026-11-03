<?php

declare(strict_types=1);

namespace App\Models;

class NivelInstruccionModel extends CatalogModel
{
    protected string $table = 'nivel_instruccion';
    protected string $primaryKey = 'id_nivel_instruccion';
    protected string $campoNombre = 'nombre';
}
