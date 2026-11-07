<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;
use RuntimeException;

final class ImagenPdf
{
    public static function publicaDataUri(
        string $rutaPublica,
        bool $recortarBlanco = false,
        ?float $relacionAspecto = null
    ): ?string {
        $rutaPublica = '/' . ltrim(str_replace('\\', '/', trim($rutaPublica)), '/');
        if ($rutaPublica === '/' || str_contains($rutaPublica, '..')) {
            return null;
        }

        $directorioPublico = realpath(dirname(__DIR__, 2) . '/public');
        $archivo = realpath(dirname(__DIR__, 2) . '/public' . $rutaPublica);
        if ($directorioPublico === false
            || $archivo === false
            || !str_starts_with($archivo, $directorioPublico . DIRECTORY_SEPARATOR)
            || !is_file($archivo)) {
            return null;
        }

        $imageInfo = getimagesize($archivo);
        $mime = $imageInfo['mime'] ?? '';
        if (!in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
            return null;
        }

        if ($mime === 'image/webp') {
            if (!function_exists('imagecreatefromwebp')) {
                throw new RuntimeException('El servidor no puede procesar la foto WEBP para el PDF.');
            }
            $imagen = imagecreatefromwebp($archivo);
            if ($imagen === false) {
                throw new RuntimeException('No se pudo procesar la foto para el PDF.');
            }
            ob_start();
            if (!imagepng($imagen)) {
                ob_end_clean();
                throw new RuntimeException('No se pudo preparar la foto para el PDF.');
            }
            $contenido = ob_get_clean();
            $mime = 'image/png';
        } elseif ($recortarBlanco && $mime === 'image/png') {
            $imagen = imagecreatefrompng($archivo);
            if ($imagen === false) {
                throw new RuntimeException('No se pudo procesar el logo institucional.');
            }

            $ancho = imagesx($imagen);
            $alto = imagesy($imagen);
            $minX = $ancho;
            $minY = $alto;
            $maxX = -1;
            $maxY = -1;

            for ($y = 0; $y < $alto; $y++) {
                for ($x = 0; $x < $ancho; $x++) {
                    $color = imagecolorsforindex($imagen, imagecolorat($imagen, $x, $y));
                    if (($color['alpha'] ?? 0) >= 120
                        || ($color['red'] > 247 && $color['green'] > 247 && $color['blue'] > 247)) {
                        continue;
                    }
                    $minX = min($minX, $x);
                    $minY = min($minY, $y);
                    $maxX = max($maxX, $x);
                    $maxY = max($maxY, $y);
                }
            }

            if ($maxX < $minX || $maxY < $minY) {
                throw new RuntimeException('El logo del Ministerio está vacío.');
            }

            $recorte = imagecrop($imagen, [
                'x' => $minX,
                'y' => $minY,
                'width' => $maxX - $minX + 1,
                'height' => $maxY - $minY + 1,
            ]);
            if ($recorte === false) {
                throw new RuntimeException('No se pudo ajustar el logo del Ministerio.');
            }

            ob_start();
            if (!imagepng($recorte)) {
                ob_end_clean();
                throw new RuntimeException('No se pudo convertir el logo del Ministerio.');
            }
            $contenido = ob_get_clean();
        } else {
            $contenido = file_get_contents($archivo);
        }

        if ($contenido !== false && $relacionAspecto !== null) {
            if ($relacionAspecto <= 0) {
                throw new InvalidArgumentException('La proporción de recorte de la foto no es válida.');
            }

            $imagen = @imagecreatefromstring($contenido);
            if ($imagen === false) {
                throw new RuntimeException('No se pudo preparar la foto para el PDF.');
            }

            $ancho = imagesx($imagen);
            $alto = imagesy($imagen);
            $relacionActual = $ancho / $alto;
            if ($relacionActual > $relacionAspecto) {
                $anchoRecorte = max(1, (int) round($alto * $relacionAspecto));
                $caja = [
                    'x' => (int) floor(($ancho - $anchoRecorte) / 2),
                    'y' => 0,
                    'width' => $anchoRecorte,
                    'height' => $alto,
                ];
            } else {
                $altoRecorte = max(1, (int) round($ancho / $relacionAspecto));
                $caja = [
                    'x' => 0,
                    'y' => (int) floor(($alto - $altoRecorte) / 2),
                    'width' => $ancho,
                    'height' => $altoRecorte,
                ];
            }

            $recorte = imagecrop($imagen, $caja);
            if ($recorte === false) {
                throw new RuntimeException('No se pudo recortar la foto para el PDF.');
            }

            ob_start();
            if (!imagepng($recorte)) {
                ob_end_clean();
                throw new RuntimeException('No se pudo convertir la foto para el PDF.');
            }
            $contenido = ob_get_clean();
            $mime = 'image/png';
        }

        return $contenido === false
            ? null
            : 'data:' . $mime . ';base64,' . base64_encode($contenido);
    }
}
