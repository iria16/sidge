<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * CedulaEscolar
 * -----------------------------------------------------------------
 * Genera y valida la cédula escolar de un estudiante que todavía no tiene cédula
 * de identidad propia.
 *
 * Formato (12 caracteres, todos obligatorios):
 *
 *     V1|15|05801819
 *     │ │ │  └──── 8 dígitos completos de la cédula del representante legal
 *     │ │ └─────── 2 últimos dígitos del año de nacimiento del estudiante
 *     │ └───────── orden del estudiante entre los hijos del representante (1-9)
 *     └─────────── letra del representante: V (venezolano) o E (extranjero)
 *
 * Reglas de negocio:
 * 1. El representante aporta 8 dígitos completos. Si su cédula tiene 7 dígitos,
 *    se agrega un cero a la izquierda de forma obligatoria para completar los 8.
 *    Si tiene 8 dígitos, no se toca.
 * 2. Formato: Letra (V o E) + 11 dígitos = 12 caracteres exactos.
 * 3. Los dígitos 2 y 3 (índices 2-3) deben coincidir con los últimos 2 dígitos
 *    del año de nacimiento del estudiante.
 */
final class CedulaEscolar
{
    /** Letras admitidas como inicio de la cédula escolar. */
    public const LETRAS = ['V', 'E'];

    /** Orden del hijo: una sola cifra, del 1 al 9. */
    public const ORDEN_MINIMO = 1;
    public const ORDEN_MAXIMO = 9;

    /** Dígitos completos que aporta la cédula del representante (exactamente 8). */
    public const LARGO_CEDULA_REPRESENTANTE = 8;

    /** Letra que se usa cuando el representante no informa su tipo. */
    public const LETRA_POR_DEFECTO = 'V';

    /**
     * Formato que debe cumplir la cédula escolar (12 caracteres):
     * Letra (V/E) + orden (1-9) + año (2 dígitos) + representante (8 dígitos).
     */
    public const PATRON = '/^[VE][1-9]\d{10}$/';

    /**
     * Devuelve los 8 dígitos con los que se arma la cédula escolar, a partir de
     * la cédula de identidad del representante.
     *
     * Regla:
     * - Si la cédula tiene 7 dígitos: se agrega un cero a la izquierda para completar 8.
     * - Si la cédula tiene 8 dígitos: se conserva tal cual.
     *
     * @throws InvalidArgumentException si la cédula normalizada no tiene entre 7 y 8 dígitos.
     */
    public static function digitosDeRepresentante(string $cedulaRepresentante): string
    {
        $digitos = self::soloDigitos($cedulaRepresentante);
        // Quitar ceros a la izquierda que haya escrito el usuario para evaluar el número real
        $digitos = ltrim($digitos, '0');

        $longitud = strlen($digitos);
        if ($longitud < 7 || $longitud > 8) {
            throw new InvalidArgumentException(
                'La cédula del representante debe tener 7 u 8 dígitos.'
            );
        }

        // Si tiene 7 dígitos, completa con un cero a la izquierda para llegar a 8
        return str_pad($digitos, self::LARGO_CEDULA_REPRESENTANTE, '0', STR_PAD_LEFT);
    }

    /**
     * Arma la cédula escolar en formato oficial (12 caracteres).
     *
     * @param int         $orden               Qué hijo es, de 1 a 9.
     * @param string      $fechaNacimiento     Fecha de nacimiento del estudiante (Y-m-d).
     * @param string      $cedulaRepresentante Cédula del representante legal.
     * @param string|null $letra               V o E. Si se omite, se deduce del prefijo o default 'V'.
     *
     * @throws InvalidArgumentException si algún parámetro no cumple las reglas.
     */
    public static function generar(
        int $orden,
        string $fechaNacimiento,
        string $cedulaRepresentante,
        ?string $letra = null
    ): string {
        if ($orden < self::ORDEN_MINIMO || $orden > self::ORDEN_MAXIMO) {
            throw new InvalidArgumentException(
                'El orden del hijo debe ser un número del ' . self::ORDEN_MINIMO . ' al ' . self::ORDEN_MAXIMO . '.'
            );
        }

        $anio = self::anioDeNacimiento($fechaNacimiento);
        $digitosRep = self::digitosDeRepresentante($cedulaRepresentante);
        $letraDoc = self::resolverLetra($letra, $cedulaRepresentante);

        return $letraDoc . $orden . $anio . $digitosRep;
    }

    /**
     * Valida si la cédula escolar cumple el formato oficial y opcionalmente
     * comprueba la consistencia de los dígitos de año contra la fecha de nacimiento.
     *
     * @param string      $cedula          Cédula escolar a validar (12 caracteres).
     * @param string|null $fechaNacimiento Fecha de nacimiento opcional para verificar año.
     */
    public static function esValida(string $cedula, ?string $fechaNacimiento = null): bool
    {
        $cedula = strtoupper(trim($cedula));

        if (!preg_match(self::PATRON, $cedula)) {
            return false;
        }

        if ($fechaNacimiento !== null && $fechaNacimiento !== '') {
            try {
                $anioEsperado = self::anioDeNacimiento($fechaNacimiento);
                $anioCedula = substr($cedula, 2, 2);
                if ($anioCedula !== $anioEsperado) {
                    return false;
                }
            } catch (InvalidArgumentException) {
                return false;
            }
        }

        return true;
    }

    /**
     * Desglosa una cédula escolar válida en sus partes componentes.
     *
     * @return array{tipo: string, orden: int, anio: string, cedula_representante: string}
     * @throws InvalidArgumentException si la cédula no cumple el patrón.
     */
    public static function desglosar(string $cedula): array
    {
        $cedula = strtoupper(trim($cedula));

        if (!self::esValida($cedula)) {
            throw new InvalidArgumentException('El formato de la cédula escolar no es válido.');
        }

        return [
            'tipo'                 => substr($cedula, 0, 1),
            'orden'                => (int) substr($cedula, 1, 1),
            'anio'                 => substr($cedula, 2, 2),
            'cedula_representante' => substr($cedula, 4, 8),
        ];
    }

    /**
     * Quita cualquier caracter que no sea dígito.
     */
    public static function soloDigitos(string $cadena): string
    {
        return preg_replace('/\D/', '', $cadena) ?? '';
    }

    /**
     * Resuelve la letra (V o E) a partir del parámetro o de la cédula del representante.
     */
    private static function resolverLetra(?string $letra, string $cedulaRepresentante): string
    {
        if ($letra !== null && $letra !== '') {
            $letra = strtoupper(trim($letra));
            if (in_array($letra, self::LETRAS, true)) {
                return $letra;
            }
        }

        $cedulaLimpia = strtoupper(trim($cedulaRepresentante));
        if (preg_match('/^([VE])/i', $cedulaLimpia, $m)) {
            return strtoupper($m[1]);
        }

        return self::LETRA_POR_DEFECTO;
    }

    /**
     * Extrae los últimos 2 dígitos del año de nacimiento con validación estricta YYYY-MM-DD.
     *
     * @throws InvalidArgumentException si la fecha no es válida o no cumple formato estricto.
     */
    private static function anioDeNacimiento(string $fechaNacimiento): string
    {
        $fechaLimpia = trim($fechaNacimiento);
        $fecha = DateTimeImmutable::createFromFormat('Y-m-d', $fechaLimpia);

        if ($fecha === false || $fecha->format('Y-m-d') !== $fechaLimpia) {
            throw new InvalidArgumentException('La fecha de nacimiento del estudiante no es válida (debe ser YYYY-MM-DD).');
        }

        $anio = (int) $fecha->format('Y');
        if ($anio < 1900 || $anio > 2100) {
            throw new InvalidArgumentException('El año de nacimiento del estudiante está fuera de rango.');
        }

        return substr($fecha->format('Y'), -2);
    }
}
