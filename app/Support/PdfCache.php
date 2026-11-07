<?php

declare(strict_types=1);

namespace App\Support;

final class PdfCache
{
    private const TTL_SECONDS = 1800;
    private const MAX_AGE_SECONDS = 3600;

    public static function dir(): string
    {
        $ruta = __DIR__ . '/../../storage/temp/pdf';
        if (!is_dir($ruta)) {
            @mkdir($ruta, 0770, true);
        }
        return $ruta;
    }

    public static function file(string $key): string
    {
        return self::dir() . '/' . hash('sha256', $key) . '.pdf';
    }

    public static function get(string $key, int $ttl = self::TTL_SECONDS): ?string
    {
        $ruta = self::file($key);
        if (!is_file($ruta)) {
            return null;
        }
        if ((time() - filemtime($ruta)) > $ttl) {
            @unlink($ruta);
            return null;
        }
        return $ruta;
    }

    public static function put(string $key, string $binary): void
    {
        $ruta = self::file($key);
        file_put_contents($ruta, $binary);
        @chmod($ruta, 0660);
    }

    public static function delete(string $key): void
    {
        $ruta = self::file($key);
        if (is_file($ruta)) {
            @unlink($ruta);
        }
    }

    public static function clean(int $maxAge = self::MAX_AGE_SECONDS): int
    {
        $dir = self::dir();
        if (!is_dir($dir)) {
            return 0;
        }
        $borrados = 0;
        foreach (new \FilesystemIterator($dir) as $archivo) {
            if (!$archivo->isFile()) {
                continue;
            }
            if ((time() - $archivo->getMTime()) > $maxAge) {
                @unlink($archivo->getPathname());
                $borrados++;
            }
        }
        return $borrados;
    }
}
