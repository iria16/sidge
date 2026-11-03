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

    /**
     * Comprueba las opciones que también se permiten en las columnas ENUM.
     * La talla es opcional, por eso null y la cadena vacía son válidos.
     */
    public static function esValida(string $prenda, ?string $talla): bool
    {
        $opciones = match ($prenda) {
            'camisa' => self::CAMISA,
            'pantalon' => self::PANTALON,
            'zapato' => self::zapato(),
            default => [],
        };

        if ($opciones === []) {
            return false;
        }
        if ($talla === null || trim($talla) === '') {
            return true;
        }

        return in_array(trim($talla), $opciones, true);
    }

    /**
     * Valida los campos enviados por inscripción o ratificación.
     *
     * @param array<string, mixed> $tallas
     * @return array<string, string>
     */
    public static function errores(array $tallas): array
    {
        $errores = [];
        $nombres = [
            'camisa' => 'camisa',
            'pantalon' => 'pantalón',
            'zapato' => 'calzado',
        ];

        foreach ($nombres as $prenda => $nombre) {
            $valor = $tallas[$prenda] ?? null;
            $valorValido = $valor === null
                || ((is_string($valor) || is_int($valor))
                    && self::esValida($prenda, (string) $valor));
            if (!$valorValido) {
                $errores['talla_' . $prenda] = 'Seleccione una talla válida de ' . $nombre . '.';
            }
        }

        return $errores;
    }
}
