<?php

namespace App\Support;

use PDO;

/**
 * AniosEscolares
 * Centraliza la consulta y verificación del año escolar en curso.
 * Garantiza consistencia en toda la aplicación ordenando siempre por anio_inicio DESC.
 */
final class AniosEscolares
{
    private function __construct()
    {
    }

    /**
     * Devuelve el ID del año escolar en curso, o 0 si no existe período activo.
     */
    public static function idEnCurso(PDO $pdo): int
    {
        return (int) self::columna($pdo, 'id_anio_escolar');
    }

    /**
     * Fecha de inicio del año en curso, o '9999-12-31' como valor por defecto.
     */
    public static function inicioEnCurso(PDO $pdo): string
    {
        return (string) (self::columna($pdo, 'anio_inicio') ?: '9999-12-31');
    }

    /**
     * Nombre formal del período lectivo activo (ej. '2025-2026') o cadena vacía.
     */
    public static function nombreEnCurso(PDO $pdo): string
    {
        return (string) self::columna($pdo, 'nombre_periodo');
    }

    private static function columna(PDO $pdo, string $columna): string|false
    {
        // Valida que la columna pertenezca a la lista blanca permitida.
        if (!in_array($columna, ['id_anio_escolar', 'anio_inicio', 'nombre_periodo'], true)) {
            throw new \InvalidArgumentException("Columna no permitida: {$columna}");
        }

        return $pdo->query(
            "SELECT {$columna} FROM anio_escolar
              WHERE estado = 'ACTIVO'
              ORDER BY anio_inicio DESC
              LIMIT 1"
        )->fetchColumn();
    }
}
