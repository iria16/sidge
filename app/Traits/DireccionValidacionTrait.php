<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\UbicacionModel;

trait DireccionValidacionTrait
{
    /**
     * Valida los campos de la dirección de residencia.
     */
    protected function validarDireccion(array $data, array &$errores): void
    {
        $direccion = $data['direccion'] ?? [];

        $idEstado = (int) ($direccion['id_estado'] ?? 0);
        $idMunicipio = (int) ($direccion['id_municipio'] ?? 0);
        $idParroquia = (int) ($direccion['id_parroquia'] ?? 0);

        // 1. Estado (Obligatorio)
        if ($idEstado <= 0) {
            $errores['id_estado'] = 'Selecciona el estado de residencia.';
        }

        // 2. Municipio (Obligatorio)
        if ($idMunicipio <= 0) {
            $errores['id_municipio'] = 'Selecciona el municipio de residencia.';
        }

        // 3. Parroquia (Obligatorio) y coherencia de la jerarquía
        if ($idParroquia <= 0) {
            $errores['id_parroquia'] = 'Selecciona la parroquia de residencia.';
        } else {
            $ubicacion = (new UbicacionModel($this->conexion()))->ubicacionDeParroquia($idParroquia);
            if ($ubicacion === null) {
                $errores['id_parroquia'] = 'La parroquia seleccionada no existe.';
            } elseif (($idEstado > 0 && $idEstado !== (int) $ubicacion['estado'])
                || ($idMunicipio > 0 && $idMunicipio !== (int) $ubicacion['municipio'])) {
                $errores['id_parroquia'] = 'La parroquia no corresponde al estado y municipio seleccionados.';
            }
        }

        // 4. Sector / Urbanización (Obligatorio: min 3, max 100)
        $sector = trim((string) ($direccion['sector_urbanizacion'] ?? ''));
        if ($sector === '') {
            $errores['sector_urbanizacion'] = 'El sector o urbanización es obligatorio.';
        } elseif (mb_strlen($sector, 'UTF-8') < 3 || mb_strlen($sector, 'UTF-8') > 100) {
            $errores['sector_urbanizacion'] = 'El sector debe tener entre 3 y 100 caracteres.';
        }

        // 3. Calle / Avenida (Obligatorio: min 2, max 100)
        $calle = trim((string) ($direccion['calle_avenida'] ?? ''));
        if ($calle === '') {
            $errores['calle_avenida'] = 'La calle o avenida es obligatoria.';
        } elseif (mb_strlen($calle, 'UTF-8') < 2 || mb_strlen($calle, 'UTF-8') > 100) {
            $errores['calle_avenida'] = 'La calle o avenida debe tener entre 2 y 100 caracteres.';
        }

        // 4. Número de Casa / Apto (Obligatorio: min 1, max 20)
        $nroCasa = trim((string) ($direccion['nro_casa_apto'] ?? ''));
        if ($nroCasa === '') {
            $errores['nro_casa_apto'] = 'Indica el número de casa o apartamento.';
        } elseif (mb_strlen($nroCasa, 'UTF-8') > 20) {
            $errores['nro_casa_apto'] = 'El número de casa/apto no puede superar los 20 caracteres.';
        }

        // 5. Punto de Referencia (Opcional: max 150)
        $puntoRef = trim((string) ($direccion['punto_referencia'] ?? ''));
        if ($puntoRef !== '' && mb_strlen($puntoRef, 'UTF-8') > 150) {
            $errores['punto_referencia'] = 'El punto de referencia no puede superar los 150 caracteres.';
        }
    }
}