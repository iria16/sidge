<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;

/**
 * Saludo según la hora de Caracas.
 *
 * Centraliza la zona horaria y los cortes para que los dashboards no repitan
 * el mismo `match` ni fijen la zona por su cuenta.
 */
final class Saludo
{
    public static function actual(): string
    {
        date_default_timezone_set('America/Caracas');

        $hora = (int) (new DateTimeImmutable('now'))->format('G');

        return match (true) {
            $hora < 12 => 'Buenos días',
            $hora < 19 => 'Buenas tardes',
            default    => 'Buenas noches',
        };
    }
}
