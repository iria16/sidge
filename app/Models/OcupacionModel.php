<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Ocupación de la persona (46 activas: Médico, Comerciante, Docente...).
 *
 * Se administra desde Configuración → Catálogos, igual que el parentesco. Antes
 * no se podía: la tabla existía y el modelo la usaba, pero escribía siempre la
 * primera fila activa (`firstCatalogId`) porque el formulario no la pedía. Es
 * decir, todos los familiares del sistema tenían la misma ocupación, la que
 *iculate por casualidad alfabéticamente. Por eso se agrega al catálogo: para que
 * se pueda mantener y para que el dato que se registra sea el que se eligió.
 */
class OcupacionModel extends CatalogModel
{
    protected string $table = 'ocupacion';
    protected string $primaryKey = 'id_ocupacion';

    protected array $columns = ['id_ocupacion', 'nombre', 'estado'];
    protected array $allowedColumns = ['id_ocupacion', 'nombre', 'estado'];
    protected string $campoNombre = 'nombre';
}
