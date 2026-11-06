<?php

declare(strict_types=1);

namespace App\Traits;

    /* Estado "Retirado" de un alumno. POR QUÉ UN TRAIT Y NO UNA COLUMNA: no hay ningún campo
 "retirado" en `estudiante` ni en `matricula`, y con el propósito. El retiro es una fila MÁS de
 `matricula` con tipo = 'RETIRO', al lado de la de inscripción, que sigue apuntando al
 alumno y deja ver su historia completa.*/
trait RetiradoTrait
{
    /**
 * Fragmento SQL reutilizable: "este alumno tiene fila de retiro".
 *
 * Se usa dentro del SELECT (para traer el dato ya resuelto y no una
 * subconsulta por fila) y dentro del WHERE (para excluir). Se apoya en un
 * EXISTS y no en un JOIN para no duplicar filas: un alumno puede tener más de
 * una fila en `matricula`, y un JOIN las multiplicaría en el listado.
 *
 * @param string $aliasMatricula Alias de la tabla en la consulta que lo usa
 * (p. ej. 'm' en el listado de inscripciones).
 * @return string Fragmento que evalúa a 1 si el alumno está retirado.
*/
    private static function retiradoSql(string $aliasMatricula = 'm'): string
    {
        return "EXISTS (SELECT 1 FROM matricula mr
                        INNER JOIN anio_escolar anr ON anr.id_anio_escolar = mr.id_anio_escolar
                        WHERE mr.id_estudiante = {$aliasMatricula}.id_estudiante
                          AND mr.tipo = 'RETIRO'
                          AND NOT EXISTS (
                              SELECT 1 FROM matricula mp
                              INNER JOIN anio_escolar anp ON anp.id_anio_escolar = mp.id_anio_escolar
                              WHERE mp.id_estudiante = mr.id_estudiante
                                AND (anp.anio_inicio > anr.anio_inicio
                                     OR (anp.anio_inicio = anr.anio_inicio AND mp.id_matricula > mr.id_matricula))
                          ))";
    }

}
