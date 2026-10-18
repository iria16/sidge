<?php

declare(strict_types=1);

namespace App\Config;

use RuntimeException;

/**
 * Lector de variables de entorno desde el archivo .env de la raíz del proyecto.
 *
 * Existe para que ninguna credencial quede escrita dentro del código. El archivo .env
 * está en .gitignore, así que las claves nunca entran al repositorio.
 *
 * Prioridad de resolución, de mayor a menor:
 *   1. Variable de entorno real del servidor (getenv).
 *   2. Archivo .env de la raíz del proyecto.
 *
 * El orden importa en hosting: en un servidor compartido (InfinityFree y similares)
 * no se puede crear un .env, pero sí definir variables de entorno desde el panel.
 * Con esa prioridad el mismo código funciona en ambos casos sin cambios.
 */
final class Env
{
    /** @var array<string, string>|null Valores leídos del archivo; null mientras no se haya leído. */
    private static ?array $valores = null;

    /**
     * Devuelve el valor de una variable, o $porDefecto si no está definida.
     *
     * Úsalo para valores opcionales. Para las credenciales de conexión, mejor
     * requerido(): si falta una, quiero un error claro y no una conexión fallida.
     */
    public static function obtener(string $clave, ?string $porDefecto = null): ?string
    {
        $delServidor = getenv($clave);
        if ($delServidor !== false && $delServidor !== '') {
            return $delServidor;
        }

        self::cargar();

        return self::$valores[$clave] ?? $porDefecto;
    }

    /**
     * Devuelve el valor de una variable o lanza una excepción si no está definida.
     *
     * El mensaje dice qué hacer, porque si falta el .env el error real ("Access
     * denied for user...") no dice nada sobre la causa y cuesta horas de depuración.
     */
    public static function requerido(string $clave): string
    {
        $valor = self::obtener($clave);

        if ($valor === null || $valor === '') {
            throw new RuntimeException(
                "Falta la variable de entorno {$clave}. "
                . "Copia .env.example a .env y define {$clave} con su valor. "
                . "Revisa README.md, sección Base de datos."
            );
        }

        return $valor;
    }

    /**
     * Lee el archivo .env una sola vez por petición y guarda los pares clave=>valor.
     *
     * Formato aceptado por línea, ignorando vacías y comentarios (#):
     *   CLAVE=valor
     *   export CLAVE=valor      (convención habitual de shell)
     *   CLAVE="valor con espacios"
     *   CLAVE='otro valor'
     *
     * Las comillas solo se quitan si envuelven toda la línea; si aparecen dentro,
     * se conservan porque son parte del valor.
     */
    private static function cargar(): void
    {
        if (self::$valores !== null) {
            return;
        }

        self::$valores = [];

        $ruta = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';
        if (!is_readable($ruta)) {
            return;
        }

        $lineas = file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        foreach ($lineas as $linea) {
            $linea = trim($linea);

            if ($linea === '' || str_starts_with($linea, '#')) {
                continue;
            }

            /* `export ` es ruido de shell que algunos copian de un ejemplo de .env. */
            if (str_starts_with($linea, 'export ')) {
                $linea = trim(substr($linea, 7));
            }

            $separador = strpos($linea, '=');
            if ($separador === false) {
                continue;
            }

            $clave = trim(substr($linea, 0, $separador));
            $valor = trim(substr($linea, $separador + 1));

            if ($clave === '') {
                continue;
            }

            self::$valores[$clave] = self::sinComillas($valor);
        }
    }

    /**
     * Quita las comillas que envuelven todo el valor, y solo en ese caso.
     */
    private static function sinComillas(string $valor): string
    {
        $longitud = strlen($valor);

        if ($longitud >= 2) {
            $primera = $valor[0];
            $ultima  = $valor[$longitud - 1];

            if (($primera === '"' && $ultima === '"') || ($primera === "'" && $ultima === "'")) {
                return substr($valor, 1, -1);
            }
        }

        return $valor;
    }
}