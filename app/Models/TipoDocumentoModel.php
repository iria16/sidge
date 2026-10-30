<?php

declare(strict_types=1);

namespace App\Models;

class TipoDocumentoModel extends CatalogModel
{
    protected string $table = 'tipo_documento';
    protected string $primaryKey = 'id_tipo_documento';

    protected array $columns = ['id_tipo_documento', 'nombre', 'codigo', 'estado'];
    protected array $allowedColumns = ['id_tipo_documento', 'nombre', 'codigo', 'estado'];
    protected string $campoNombre = 'nombre';
}