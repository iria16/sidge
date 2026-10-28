<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Resuelve la IP del cliente una sola vez para toda la aplicación.
 *
 * Existe para que los modelos no lean `$_SERVER` directamente: si un modelo
 * necesita auditar, pide la IP a este soporte o la recibe como parámetro.
 */
final class ClientIp
{
    public static function get(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        return is_string($ip) && $ip !== '' ? $ip : '0.0.0.0';
    }
}
