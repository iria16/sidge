<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Deriva «con quién vive» desde el núcleo familiar.
 *
 * No existe una columna para este dato: se calcula con las relaciones
 * ACTIVAS y VIVAS de `persona_estudiante` que tienen `vive_con_estudiante`
 * marcado. La misma expresión SQL se usa en ficha, directorio y estadísticas
 * para que las tres pantallas no inventen criterios distintos.
 *
 * Jerarquía:
 * - madre y padre marcados → ambos padres
 * - solo madre (aunque haya otros familiares) → solo con la madre
 * - solo padre (aunque haya otros familiares) → solo con el padre
 * - ninguna madre/padre, pero otra persona marcada → otro familiar o tutor
 * - nadie marcado → no registrado
 */
final class ConQuienVive
{
    public const AMBOS_PADRES  = 'ambos_padres';
    public const SOLO_MADRE    = 'solo_madre';
    public const SOLO_PADRE    = 'solo_padre';
    public const OTRO_FAMILIAR = 'otro_familiar';
    public const NO_REGISTRADO = 'no_registrado';

    /** Clave derivada => etiqueta visible. */
    private const ETIQUETAS = [
        self::AMBOS_PADRES  => 'Con ambos padres',
        self::SOLO_MADRE    => 'Solo con la madre',
        self::SOLO_PADRE    => 'Solo con el padre',
        self::OTRO_FAMILIAR => 'Con otro familiar o tutor',
        self::NO_REGISTRADO => 'No registrado',
    ];

    public static function etiqueta(?string $clave): string
    {
        return self::ETIQUETAS[$clave ?? ''] ?? self::ETIQUETAS[self::NO_REGISTRADO];
    }

    /**
     * Expresión SQL escalar con la categoría derivada del estudiante.
     *
     * `$idEstudiante` es una expresión interna (por ejemplo `e.id_estudiante`),
     * nunca un valor de formulario. Se valida para que no pueda convertirse en
     * un punto de inyección si alguien la reutiliza con datos externos.
     */
    public static function expresionSql(string $idEstudiante): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $idEstudiante)) {
            throw new \InvalidArgumentException('Expresión de estudiante no válida.');
        }

        return "COALESCE((
            SELECT CASE
                       WHEN SUM(LOWER(TRIM(pa.nombre)) = 'madre') > 0
                        AND SUM(LOWER(TRIM(pa.nombre)) = 'padre') > 0 THEN '" . self::AMBOS_PADRES . "'
                       WHEN SUM(LOWER(TRIM(pa.nombre)) = 'madre') > 0 THEN '" . self::SOLO_MADRE . "'
                       WHEN SUM(LOWER(TRIM(pa.nombre)) = 'padre') > 0 THEN '" . self::SOLO_PADRE . "'
                       ELSE '" . self::OTRO_FAMILIAR . "'
                   END
              FROM persona_estudiante pe
              INNER JOIN persona pv ON pv.id_persona = pe.id_persona
              INNER JOIN parentesco pa ON pa.id_parentesco = pe.id_parentesco
             WHERE pe.id_estudiante = {$idEstudiante}
               AND pe.estado_relacion = 'ACTIVO'
               AND pv.es_fallecido = 0
               AND pe.vive_con_estudiante = 1
            HAVING COUNT(*) > 0
        ), '" . self::NO_REGISTRADO . "')";
    }
}
