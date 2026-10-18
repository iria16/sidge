<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Direccion
 * -----------------------------------------------------------------
 * Única forma de redactar un domicilio en una línea para mostrar.
 *
 * El orden y los prefijos son los mismos en toda la aplicación: calle, casa,
 * sector, parroquia, municipio, estado y, al final, el punto de referencia.
 * Cada tramo entra solo si trae valor, para que no queden comas sueltas: una
 * dirección sin sector se lee "Av. Las Flores, Casa nro 12, Chuao" y no
 * "Av. Las Flores, Casa nro 12, , Chuao".
 *
 * Antes cada módulo armaba la suya y ya había tres redacciones distintas: la
 * ficha del empleado (EmpleadoModel), el JS del modal de detalle
 * (pintarDetalleEmpleado, que volvía a componerla en el navegador) y la del
 * representante en Listados y Actas. Con una persona registrada con un solo
 * tramo faltante, cada una mostraba algo distinto.
 */
final class Direccion
{
    /**
     * Redacta el domicilio en una línea.
     *
     * Los parámetros son los campos tal como los devuelve la consulta (pueden
     * venir null de un LEFT JOIN sin fila), por eso todos son opcionales: el
     * orden de los argumentos ES el orden en que se muestran.
     *
     * La calle va como tramo propio, sin prefijo, y el número aparte como
     * "Casa nro 12": es como se lee una dirección, no "Av. Las Flores 12".
     * Un número sin calle sí se muestra, porque el número sigue diciendo algo.
     *
     * @return string Cadena vacía si no hay ningún tramo con valor.
     */
    public static function linea(
        ?string $calle = null,
        ?string $numero = null,
        ?string $sector = null,
        ?string $parroquia = null,
        ?string $municipio = null,
        ?string $estado = null,
        ?string $referencia = null
    ): string {
        $tramos = array_filter([
            trim((string) $calle),
            self::prefijo($numero, 'Casa nro'),
            self::prefijo($sector, 'Sector'),
            self::prefijo($parroquia, 'Parroquia'),
            self::prefijo($municipio, 'Municipio'),
            self::prefijo($estado, 'Estado'),
            self::prefijo($referencia, 'Punto de referencia'),
        ], static fn(string $t): bool => $t !== '');

        return implode(', ', $tramos);
    }

    /** Prefija el tramo, o devuelve cadena vacía si no trae valor. */
    private static function prefijo(?string $valor, string $etiqueta): string
    {
        $v = trim((string) $valor);

        return $v === '' ? '' : $etiqueta . ' ' . $v;
    }
}
