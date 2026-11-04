<?php

declare(strict_types=1);

namespace App\Support;

use App\Config\Database;
use PDO;
use Throwable;

/**
 * Helper global de configuración institucional y directiva.
 *
 * Centraliza la lectura de parámetros oficiales (logos, nombres del ministerio y plantel,
 * datos del director(a), firma y sello) con las siguientes características:
 *
 * 1. Caché en memoria por petición (request-scoped): se consultan todas las claves
 *    en un único SELECT y se reutilizan durante toda la vida de la solicitud.
 * 2. Invalidación de caché en el navegador: agrega ?v={filemtime} a los assets públicos.
 * 3. Fallback automático: si el archivo de logo configurado en BD no existe físicamente en disco,
 *    asset() y dataUriPublico() caen automáticamente al logo por defecto del sistema.
 * 4. Almacenamiento seguro: la firma digitalizada y el sello se almacenan FUERA de public/
 *    (storage/institucion/) y nunca se exponen vía HTTP; se leen exclusivamente en el servidor
 *    como Data URIs base64 para Dompdf.
 * 5. Cálculo de proporciones con getimagesize(): entrega anchos y altos explícitos
 *    para evitar que Dompdf deforme las imágenes en las constancias.
 * 6. Validación de integridad: verifica si el director está debidamente configurado
 *    antes de permitir la emisión de documentos legales.
 */
class Configuracion
{
    /** @var array<string, string>|null Caché de claves en memoria para la petición actual */
    private static ?array $cache = null;

    /** @var PDO|null Conexión PDO inyectada opcionalmente */
    private static ?PDO $pdo = null;

    /**
     * Inyecta la conexión PDO de la aplicación (llamado centralmente en public/index.php).
     */
    public static function setPdo(?PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    /**
     * Obtiene la conexión PDO activa.
     */
    private static function getPdo(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        self::$pdo = (new Database())->getConnection();
        return self::$pdo;
    }

    /**
     * Invalida la caché en memoria para forzar una recarga en la siguiente lectura.
     * Se debe invocar tras actualizar cualquier clave en la base de datos.
     */
    public static function invalidateCache(): void
    {
        self::$cache = null;
    }

    /**
     * Carga todas las claves de configuración en memoria en una sola consulta.
     */
    private static function loadCache(): void
    {
        if (self::$cache !== null) {
            return;
        }

        self::$cache = [];
        try {
            $stmt = self::getPdo()->query('SELECT clave, valor FROM configuracion_institucional');
            while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
                self::$cache[(string) $fila['clave']] = (string) ($fila['valor'] ?? '');
            }
        } catch (Throwable $e) {
            error_log('Configuracion::loadCache() error: ' . $e->getMessage());
        }
    }

    /**
     * Retorna el valor de una clave de configuración.
     *
     * @param string $clave Nombre de la clave.
     * @param string $default Valor por defecto si no existe o está vacía.
     */
    public static function get(string $clave, string $default = ''): string
    {
        self::loadCache();

        $valor = trim(self::$cache[$clave] ?? '');
        return $valor !== '' ? $valor : $default;
    }

    /**
     * Retorna el mapa completo de configuraciones cargadas.
     *
     * @return array<string, string>
     */
    public static function getAll(): array
    {
        self::loadCache();
        return self::$cache ?? [];
    }

    /**
     * Valida si los datos obligatorios del director están configurados.
     * Regla estricta: NO se pueden emitir constancias si falta nombre, cédula o cargo.
     */
    public static function estaConfiguradoDirector(): bool
    {
        $nombre = trim(self::get('director_nombre'));
        $cedula = trim(self::get('director_cedula'));
        $cargo  = trim(self::get('director_cargo'));

        return $nombre !== '' && $cedula !== '' && $cargo !== '';
    }

    /**
     * Mensaje estándar que indica que se solicite al administrador configurar al directivo.
     */
    public static function mensajeDirectorFaltante(): string
    {
        return 'No se puede emitir la constancia: solicite al administrador del sistema que complete el nombre, cédula y cargo del director(a) en Datos Institucionales.';
    }

    /**
     * Retorna un arreglo asociativo con los datos del director.
     *
     * @return array{nombre: string, cedula: string, cargo: string, configurado: bool, tiene_firma: bool, tiene_sello: bool}
     */
    public static function getDirector(): array
    {
        return [
            'nombre'       => self::get('director_nombre'),
            'cedula'       => self::get('director_cedula'),
            'cargo'        => self::get('director_cargo'),
            'configurado'  => self::estaConfiguradoDirector(),
            'tiene_firma'  => self::tieneFirma(),
            'tiene_sello'  => self::tieneSello(),
        ];
    }

    /**
     * Indica si existe una firma digitalizada cargada y accesible en disco.
     */
    public static function tieneFirma(): bool
    {
        $archivo = self::get('director_firma');
        if ($archivo === '') {
            return false;
        }

        $ruta = self::getRutaPrivada('director_firma');
        return $ruta !== null && is_file($ruta);
    }

    /**
     * Indica si existe un sello húmedo institucional cargado y accesible en disco.
     */
    public static function tieneSello(): bool
    {
        $archivo = self::get('institucion_sello');
        if ($archivo === '') {
            return false;
        }

        $ruta = self::getRutaPrivada('institucion_sello');
        return $ruta !== null && is_file($ruta);
    }

    /**
     * Resuelve la ruta raíz del proyecto en el sistema de archivos.
     */
    public static function getProjectRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * Resuelve la ruta absoluta del directorio público (DocumentRoot).
     */
    public static function getPublicRoot(): string
    {
        $projectPublic = self::getProjectRoot() . DIRECTORY_SEPARATOR . 'public';
        if (is_dir($projectPublic)) {
            return $projectPublic;
        }

        // Entornos de hosting compartido (ej. InfinityFree / htdocs) donde el contenido de public/ reside en la raíz pública
        if (isset($_SERVER['DOCUMENT_ROOT']) && is_dir((string) $_SERVER['DOCUMENT_ROOT'])) {
            return rtrim((string) $_SERVER['DOCUMENT_ROOT'], '/\\');
        }

        return self::getProjectRoot();
    }

    /**
     * Resuelve la ruta física absoluta de un archivo privado de firma o sello.
     * Estos archivos viven estrictamente fuera de public/ (storage/institucion/).
     */
    public static function getRutaPrivada(string $clave): ?string
    {
        $nombreArchivo = basename(self::get($clave));
        if ($nombreArchivo === '') {
            return null;
        }

        $subcarpeta = match ($clave) {
            'director_firma'         => 'firma',
            'institucion_sello'      => 'sello',
            'cdce_director_firma'    => 'firma_cdce',
            'cdce_institucion_sello' => 'sello_cdce',
            default                  => null,
        };

        if ($subcarpeta === null) {
            return null;
        }

        $rutaCompleta = self::getProjectRoot() . DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . 'institucion' . DIRECTORY_SEPARATOR . $subcarpeta
            . DIRECTORY_SEPARATOR . $nombreArchivo;

        return is_file($rutaCompleta) ? $rutaCompleta : null;
    }

    /**
     * Alias de conveniencia para getRutaPrivada().
     */
    public static function rutaPrivada(string $clave): ?string
    {
        return self::getRutaPrivada($clave);
    }

    /**
     * Retorna la ruta por defecto para logos y cintillo en caso de que falte o no exista el archivo personalizado.
     */
    private static function getLogoPorDefecto(string $clave): string
    {
        return match ($clave) {
            'ministerio_logo'  => '/assets/img/ministerio_logo_bandera.png',
            'plantel_logo'     => '/assets/img/logo.png',
            'plantel_cintillo' => is_file(self::getPublicRoot() . '/assets/img/franjatricolor.png')
                ? '/assets/img/franjatricolor.png'
                : '/assets/img/franja_tricolor.png',
            default            => '',
        };
    }

    /**
     * Comprueba si la institución tiene configurada y existente en disco una franja tricolor / cintillo de pie de página.
     */
    public static function tieneCintillo(): bool
    {
        $valor = trim(self::get('plantel_cintillo'));
        $rutaElegida = $valor !== '' ? $valor : self::getLogoPorDefecto('plantel_cintillo');
        if ($rutaElegida === '') {
            return false;
        }

        $rutaLimpia = '/' . ltrim(str_replace('\\', '/', $rutaElegida), '/');
        $rutaFisica = self::getPublicRoot() . str_replace('/', DIRECTORY_SEPARATOR, $rutaLimpia);

        if (!is_file($rutaFisica)) {
            $default = self::getLogoPorDefecto('plantel_cintillo');
            if ($default !== '') {
                $rutaLimpia = '/' . ltrim(str_replace('\\', '/', $default), '/');
                $rutaFisica = self::getPublicRoot() . str_replace('/', DIRECTORY_SEPARATOR, $rutaLimpia);
            }
        }

        return is_file($rutaFisica);
    }

    /**
     * Resuelve la URL pública con control de caché (?v=filemtime) para un asset de imagen.
     * Si el archivo configurado no existe en disco, usa automáticamente el logo por defecto.
     *
     * @param string $clave Clave de configuración (ej. 'plantel_logo', 'ministerio_logo').
     * @param string $fallback Ruta pública por defecto si la clave no tiene valor.
     */
    public static function asset(string $clave, string $fallback = ''): string
    {
        $valor = self::get($clave);
        $defaultLogo = self::getLogoPorDefecto($clave);
        $rutaElegida = $valor !== '' ? $valor : ($fallback !== '' ? $fallback : $defaultLogo);

        if ($rutaElegida === '') {
            return '';
        }

        $rutaLimpia = '/' . ltrim(str_replace('\\', '/', $rutaElegida), '/');
        $rutaFisica = self::getPublicRoot() . str_replace('/', DIRECTORY_SEPARATOR, $rutaLimpia);

        // Si el archivo en disco no existe (ej. archivo borrado), recurrir al logo por defecto
        if (!is_file($rutaFisica) && $defaultLogo !== '') {
            $rutaLimpia = '/' . ltrim(str_replace('\\', '/', $defaultLogo), '/');
            $rutaFisica = self::getPublicRoot() . str_replace('/', DIRECTORY_SEPARATOR, $rutaLimpia);
        }

        $version = is_file($rutaFisica) ? (string) filemtime($rutaFisica) : '1';
        $baseUrl = defined('BASE_URL') ? rtrim((string) constant('BASE_URL'), '/') : '';

        return $baseUrl . $rutaLimpia . '?v=' . $version;
    }

    /**
     * Convierte una imagen pública a un Data URI base64 seguro para Dompdf.
     * Si el archivo físico configurado no existe en disco, recurre automáticamente al logo por defecto.
     *
     * @param string $clave Clave de configuración (ej. 'plantel_logo', 'ministerio_logo').
     * @param string $fallback Ruta pública por defecto si la clave no existe.
     */
    public static function dataUriPublico(string $clave, string $fallback = ''): ?string
    {
        $valor = self::get($clave);
        $defaultLogo = self::getLogoPorDefecto($clave);
        $rutaElegida = $valor !== '' ? $valor : ($fallback !== '' ? $fallback : $defaultLogo);

        if ($rutaElegida === '') {
            return null;
        }

        $rutaLimpia = '/' . ltrim(str_replace('\\', '/', $rutaElegida), '/');
        $rutaFisica = self::getPublicRoot() . str_replace('/', DIRECTORY_SEPARATOR, $rutaLimpia);

        // Si el archivo físico no existe, probar con el logo por defecto
        if (!is_file($rutaFisica) && $defaultLogo !== '') {
            $rutaLimpia = '/' . ltrim(str_replace('\\', '/', $defaultLogo), '/');
            $rutaFisica = self::getPublicRoot() . str_replace('/', DIRECTORY_SEPARATOR, $rutaLimpia);
        }

        if (!is_file($rutaFisica)) {
            return null;
        }

        return self::archivoADataUri($rutaFisica);
    }

    /**
     * Resuelve estrictamente un logo para constancias oficiales:
     * - Devuelve Data URI y dimensiones únicamente si hay un archivo configurado y existe físicamente en disco.
     * - NO realiza fallback a archivos por defecto. Si no hay archivo o no existe en disco, retorna NULL (sin imagen rota).
     * - Preserva intactos los métodos asset() y dataUriPublico().
     * - Calcula width y height proporcionales con getimagesize() dentro de una caja máxima ($maxW x $maxH).
     *
     * @param string $clave Clave de configuración (ej. 'ministerio_logo', 'plantel_logo').
     * @param int $maxW Ancho máximo permitido en puntos.
     * @param int $maxH Alto máximo permitido en puntos.
     * @return array{uri: string, width: int, height: int}|null Datos del logo o null.
     */
    public static function logoEstrictoConstancia(string $clave, int $maxW, int $maxH): ?array
    {
        $valor = trim(self::get($clave));
        if ($valor === '') {
            return null;
        }

        $rutaLimpia = '/' . ltrim(str_replace('\\', '/', $valor), '/');
        $rutaFisica = self::getPublicRoot() . str_replace('/', DIRECTORY_SEPARATOR, $rutaLimpia);

        if (!is_file($rutaFisica)) {
            return null;
        }

        $info = @getimagesize($rutaFisica);
        if ($info === false || (int) $info[0] <= 0 || (int) $info[1] <= 0) {
            return null;
        }

        $origW = (int) $info[0];
        $origH = (int) $info[1];

        // Escalar manteniendo la relación de aspecto dentro de la caja ($maxW x $maxH)
        $scale = min($maxW / $origW, $maxH / $origH);
        $width = max(1, (int) round($origW * $scale));
        $height = max(1, (int) round($origH * $scale));

        $dataUri = self::archivoADataUri($rutaFisica);
        if ($dataUri === null) {
            return null;
        }

        return [
            'uri'    => $dataUri,
            'width'  => $width,
            'height' => $height,
        ];
    }

    /**
     * Igual que logoEstrictoConstancia(), pero recibe el tamaño final deseado en
     * puntos y devuelve width/height ya en las unidades que entiende Dompdf.
     *
     * Existe porque Dompdf interpreta los atributos width/height de un <img> como
     * píxeles y los convierte a puntos con un factor de 0.75. Pedir 138x36 pt a
     * logoEstrictoConstancia() daría un logo de 103x27 pt en el papel: el
     * defecto se propaga a cada documento que maqueten y es fácil de reintroducir.
     * Aquí se declara una sola vez.
     *
     * El tamaño en CSS (max-width/max-height) sigue siendo la última red de
     * seguridad y lo fija cada documento, porque cada uno mide en unidades
     * distintas (pt en la constancia, mm en la ficha).
     *
     * @param string $clave Clave de configuración ('ministerio_logo', 'plantel_logo').
     * @param float  $anchoPt Ancho final deseado en puntos.
     * @param float  $altoPt  Alto final deseado en puntos.
     * @return array{uri: string, width: int, height: int}|null
     */
    public static function logoPdf(string $clave, float $anchoPt, float $altoPt): ?array
    {
        return self::logoEstrictoConstancia(
            $clave,
            (int) round($anchoPt * 4 / 3),
            (int) round($altoPt * 4 / 3)
        );
    }

    /**
     * Convierte un archivo privado (firma o sello fuera de public/) a Data URI base64 para Dompdf.
     * Si no está cargado o no existe el archivo, retorna NULL para que el PDF deje el espacio en blanco.
     */
    public static function dataUriPrivado(string $clave): ?string
    {
        $rutaFisica = self::getRutaPrivada($clave);
        if ($rutaFisica === null || !is_file($rutaFisica)) {
            return null;
        }

        return self::archivoADataUri($rutaFisica);
    }

    /**
     * Lee un archivo físico y lo transforma en base64 con su mime type real.
     */
    private static function archivoADataUri(string $rutaFisica): ?string
    {
        $info = @getimagesize($rutaFisica);
        if ($info === false) {
            return null;
        }

        $mime = $info['mime'] ?? 'image/png';
        $bytes = @file_get_contents($rutaFisica);
        if ($bytes === false || $bytes === '') {
            return null;
        }

        return 'data:' . $mime . ';base64,' . base64_encode($bytes);
    }

    /**
     * Calcula dimensiones proporcionales escaladas en píxeles para Dompdf usando getimagesize().
     * Garantiza que la imagen quepa dentro de un marco de ancho y alto máximo sin deformarse.
     *
     * @param string $rutaFisica Ruta absoluta al archivo en disco.
     * @param int $maxW Ancho máximo permitido en píxeles.
     * @param int $maxH Alto máximo permitido en píxeles.
     * @return array{width: int, height: int, mime: string}|null Dimensiones calculadas o null si falla.
     */
    public static function calcularDimensionesPdf(string $rutaFisica, int $maxW, int $maxH): ?array
    {
        if (!is_file($rutaFisica)) {
            return null;
        }

        $info = @getimagesize($rutaFisica);
        if ($info === false) {
            return null;
        }

        $origW = (int) $info[0];
        $origH = (int) $info[1];
        $mime  = (string) ($info['mime'] ?? 'image/png');

        if ($origW <= 0 || $origH <= 0) {
            return null;
        }

        // Factor de escala limitante entre ancho y alto
        $scale = min($maxW / $origW, $maxH / $origH, 1.0);

        return [
            'width'  => max(1, (int) round($origW * $scale)),
            'height' => max(1, (int) round($origH * $scale)),
            'mime'   => $mime,
        ];
    }

    /**
     * Helper de conveniencia para obtener dimensiones escaladas de un asset público.
     */
    public static function dimensionesAssetPublico(string $clave, string $fallback, int $maxW, int $maxH): ?array
    {
        $valor = self::get($clave);
        $defaultLogo = self::getLogoPorDefecto($clave);
        $rutaElegida = $valor !== '' ? $valor : ($fallback !== '' ? $fallback : $defaultLogo);

        if ($rutaElegida === '') {
            return null;
        }

        $rutaLimpia = '/' . ltrim(str_replace('\\', '/', $rutaElegida), '/');
        $rutaFisica = self::getPublicRoot() . str_replace('/', DIRECTORY_SEPARATOR, $rutaLimpia);

        if (!is_file($rutaFisica) && $defaultLogo !== '') {
            $rutaLimpia = '/' . ltrim(str_replace('\\', '/', $defaultLogo), '/');
            $rutaFisica = self::getPublicRoot() . str_replace('/', DIRECTORY_SEPARATOR, $rutaLimpia);
        }

        return self::calcularDimensionesPdf($rutaFisica, $maxW, $maxH);
    }

    /**
     * Helper de conveniencia para obtener dimensiones escaladas de un asset privado.
     */
    public static function dimensionesAssetPrivado(string $clave, int $maxW, int $maxH): ?array
    {
        $rutaFisica = self::getRutaPrivada($clave);
        if ($rutaFisica === null) {
            return null;
        }

        return self::calcularDimensionesPdf($rutaFisica, $maxW, $maxH);
    }
}
