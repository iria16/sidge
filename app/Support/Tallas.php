<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Catálogo único de tallas de uniforme.
 *
 * La inscripción y la ratificación usan las mismas opciones, y los reportes
 * que agrupen tallas deben leerlas de aquí para no inventar otra lista.
 */
final class Tallas
{
    public const PLACEHOLDER = 'Seleccione talla';

    /** @var list<string> */
    public const CAMISA = ['2', '4', '6', '8', '10', '12', '14', '16', 'S', 'M', 'L', 'XL'];

    /** @var list<string> */
    public const PANTALON = ['2', '4', '6', '8', '10', '12', '14', '16', '26', '28', '30', '32', '34', '36'];

    /** @return list<string> */
    public static function zapato(): array
    {
        return array_map('strval', range(24, 40));
    }
}
