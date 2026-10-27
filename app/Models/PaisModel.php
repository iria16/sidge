<?php

declare(strict_types=1);

namespace App\Models;

/**
 * País de una institución extranjera.
 *
 * Catálogo como los demás de Configuración → Catálogos: la tabla se llena y
 * se administra desde ahí.
 *
 * El id 1 es Venezuela y está fijado a propósito. Es el valor por defecto que
 * usa el formulario cuando la institución es nacional, y atarlo al
 * AUTO_INCREMENT sería frágil: una base estrenada o restaurada desde un
 * respaldo puede arrancar el contador en otro lado. Mismo criterio que
 * NacionalidadModel::ID_POR_DEFECTO.
 */
class PaisModel extends CatalogoModel
{
    protected string $table      = 'pais';
    protected string $primaryKey = 'id_pais';
    protected string $campoNombre = 'nombre';

    /** Id del valor por defecto. Lo busca por nombre en vez de hardcodear el 1. */
    public const ID_POR_DEFECTO = 1;

    /**
     * Id de Venezuela, el país por defecto de toda institución nacional.
     * Se busca por nombre: si alguien renombra o borra el país, el valor por
     * defecto sigue siendo el que exista y no un número que ya no apunta a nada.
     */
    public function idVenezuela(): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT id_pais FROM pais WHERE LOWER(nombre) = 'venezuela' LIMIT 1"
        );
        $stmt->execute();
        $id = $stmt->fetchColumn();

        return $id === false ? self::ID_POR_DEFECTO : (int) $id;
    }
}
