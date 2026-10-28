<?php

declare(strict_types=1);

namespace App\Support;

/**
 * ListadoCsv
 *
 * Convierte las filas de una sección (nómina, contactos o asistencia) al CSV
 * que se descarga desde el módulo de Listados.
 *
 * Vive aquí y no en el controlador porque armar el archivo no es una tarea
 * HTTP: es una transformación de datos. El controlador solo resuelve permisos,
 * pide las filas al modelo, llama a esta clase y entrega el resultado con
 * BinaryResponse.
 *
 * El BOM UTF-8 va al inicio para que Excel abra tildes y ñ sin distorsión.
 */
final class ListadoCsv
{
    /**
     * @param array<int, array<string, mixed>> $estudiantes Filas del modelo.
     * @param string                           $formato     nomina | contactos | asistencia.
     * @return string CSV completo, con BOM y saltos de línea.
     */
    public static function generar(array $estudiantes, string $formato): string
    {
        $delimitador = ';';
        $salida = fopen('php://memory', 'w');

        if ($salida === false) {
            return '';
        }

        fprintf($salida, chr(0xEF) . chr(0xBB) . chr(0xBF));

        if ($formato === 'asistencia') {
            fputcsv($salida, ['N°', 'Estudiante (Apellidos y Nombres)', 'Documento Identidad', 'Representante Legal', 'C.I. Representante', 'Firma', 'Observaciones'], $delimitador);
            foreach ($estudiantes as $idx => $e) {
                fputcsv($salida, [
                    $idx + 1,
                    $e['apellidos_nombres'] ?? '',
                    $e['documento_etiqueta'] ?? '',
                    ($e['rep_apellidos_nombres'] ?? '') ?: 'Sin asignar',
                    $e['rep_documento_etiqueta'] ?? '',
                    '',
                    '',
                ], $delimitador);
            }
        } elseif ($formato === 'contactos') {
            fputcsv($salida, ['N°', 'Estudiante', 'Documento', 'Representante Legal', 'Parentesco', 'Teléfono Celular', 'Teléfono Alternativo', 'Correo Electrónico'], $delimitador);
            foreach ($estudiantes as $idx => $e) {
                $telAlt = trim((string) ($e['numero_telefono_alternativo'] ?? ''));
                if ($telAlt !== '' && !empty($e['codigo_telefono_alternativo'])) {
                    $telAlt = "{$e['codigo_telefono_alternativo']}-{$telAlt}";
                }

                fputcsv($salida, [
                    $idx + 1,
                    $e['apellidos_nombres'] ?? '',
                    $e['documento_etiqueta'] ?? '',
                    ($e['rep_apellidos_nombres'] ?? '') ?: 'Sin asignar',
                    $e['parentesco'] ?? '',
                    $e['telefono_completo'] ?? '',
                    $telAlt ?: 'No registrado',
                    ($e['correo_electronico'] ?? '') ?: 'No registrado',
                ], $delimitador);
            }
        } else {
            fputcsv($salida, ['N°', 'Cédula / Documento', 'Apellidos y Nombres', 'Edad', 'Género', 'Representante Legal', 'C.I. Representante', 'Teléfono Celular', 'Autorizado Retiro'], $delimitador);
            foreach ($estudiantes as $idx => $e) {
                fputcsv($salida, [
                    $idx + 1,
                    $e['documento_etiqueta'] ?? '',
                    $e['apellidos_nombres'] ?? '',
                    $e['edad'] ?? '',
                    $e['genero'] ?? '',
                    ($e['rep_apellidos_nombres'] ?? '') ?: 'Sin asignar',
                    $e['rep_documento_etiqueta'] ?? '',
                    $e['telefono_completo'] ?? '',
                    !empty($e['es_autorizado_retiro']) ? 'SÍ' : 'NO',
                ], $delimitador);
            }
        }

        rewind($salida);
        $contenido = stream_get_contents($salida);
        fclose($salida);

        return $contenido === false ? '' : $contenido;
    }
}
