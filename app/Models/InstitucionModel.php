<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Instituciones;
use PDO;

/**
 * InstitucionModel
 *
 * Catálogo `institucion`: es el único catálogo del sistema con un campo extra
 * obligatorio (el tipo), así que sobrescribe create() de CatalogoModel para
 * incluirlo. El resto (Listable, Toggleable, existeNombre, getPrimaryKey,
 * búsqueda por nombre) lo hereda igual que los demás catálogos.
 *
 * El tipo es el código del enum de App\Support\Instituciones
 * (titulo_academico, preescolar, escuela), guardado en `institucion.tipo`.
 *
 * DESPUÉS DE 20261021_institucion_tipo_fk_1_datos.sql existe además
 * `institucion.id_tipo_institucion`, una FK a `tipo_institucion` con los ids
 * 1 (Preescolar), 6 (Universidad) y 8 (Escuela). El enum NO cambia: sigue
 * siendo el que decide, y `Instituciones::idCatalogo()` traduce su código al
 * id. La tabla deja de ser un histórico y pasa a ser la que manda.
 */
class InstitucionModel extends CatalogoModel
{
    protected string $table      = 'institucion';
    protected string $primaryKey = 'id_institucion';

    /**
     * Opciones de tipo para el <select> de alta rápida, en el formato que
     * esperan los modales: el código del enum (lo que viaja en el POST) y su
     * texto.
     *
     * No consulta la base: la lista sale del enum, que es quien sabe qué tipos
     * existen y qué regla tiene cada uno.
     *
     * @param array<int, string> $tipos Códigos del enum a ofrecer.
     * @return array<int, array{tipo: string, nombre: string}>
     */
    public function tiposFormulario(array $tipos): array
    {
        return array_map(
            static fn (string $tipo): array => [
                'tipo'   => $tipo,
                'nombre' => Instituciones::etiqueta($tipo),
            ],
            array_values($tipos)
        );
    }

    /**
     * Tipo (código del enum) de una institución ya registrada. Lo necesita el
     * wizard de inscripciones para aplicar la regla de grado, que depende del
     * tipo: solo la escuela exige un grado anterior dentro del rango 1-6.
     */
    public function tipoDeInstitucion(int $idInstitucion): ?string
    {
        $stmt = $this->pdo->prepare(
            "SELECT tipo
             FROM institucion
             WHERE id_institucion = :id
               AND estado = 'ACTIVO'
             LIMIT 1"
        );
        $stmt->execute(['id' => $idInstitucion]);
        $tipo = $stmt->fetchColumn();

        return $tipo === false ? null : (string) $tipo;
    }

    /**
     * Alta de institución.
     *
     * Escribe LAS DOS columnas de tipo a propósito: `tipo` (texto) e
     * `id_tipo_institucion` (FK). Están las dos porque la migración se
     * despliega en pasos y hay una ventana en la que el código nuevo ya corre
     * y `tipo` todavía existe. Cuando el tercer script borre `tipo`, aquí se
     * quita su línea y el resto no cambia.
     *
     * El id no se toma del formulario: sale del enum, que es quien sabe qué
     * categoría es cada tipo.
     *
     * @param array $data Se espera 'nombre' y 'tipo' (código del enum).
     */
    public function create(array $data): bool
    {
        $tipo = trim((string) ($data['tipo'] ?? ''));

        return $this->pdo->prepare(
            'INSERT INTO institucion (nombre, tipo, id_tipo_institucion)
             VALUES (:nombre, :tipo, :id_tipo_institucion)'
        )->execute([
            'nombre'             => trim((string) ($data['nombre'] ?? '')),
            'tipo'               => $tipo,
            'id_tipo_institucion' => Instituciones::idCatalogo($tipo),
        ]);
    }
}
