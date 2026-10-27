<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Dompdf\Dompdf;
use RuntimeException;
use Throwable;

/**
 * FichaEmpleadoPdf
 * ----------------
 * Generación oficial del PDF "Ficha Integral del Personal / Expediente del Empleado" mediante Dompdf.
 *
 * Aplica el patrón institucional ministerial:
 * - Hoja tamaño Carta vertical con márgenes de 2.5 cm (71 pt).
 * - Tipografía Times New Roman (#000000).
 * - Membrete ministerial e institucional de 3 columnas (20% / 60% / 20%).
 * - Cuadro de identificación con borde fino (0.75 pt), foto proporcional fija.
 * - Tablas con bordes finos (0.75 pt), cabeceras en gris claro (#f2f2f2) y texto negro.
 * - Cierre formal de fecha automática y bloque de firma/sello oficial de la Dirección.
 * - Numeración de páginas "Página X de Y" en 9 pt centrada al pie vía canvas.
 */
class FichaEmpleadoPdf
{
    private const TIMEZONE = 'America/Caracas';

    private const MESES_ESPANOL = [
        1  => 'enero',
        2  => 'febrero',
        3  => 'marzo',
        4  => 'abril',
        5  => 'mayo',
        6  => 'junio',
        7  => 'julio',
        8  => 'agosto',
        9  => 'septiembre',
        10 => 'octubre',
        11 => 'noviembre',
        12 => 'diciembre',
    ];

    /**
     * Genera el binario PDF de la Ficha Integral del Empleado.
     */
    public static function generar(
        array $config,
        array $empleado,
        array $formacion = [],
        array $asignaciones = [],
        array $reposos = [],
        array $historial = [],
        ?DateTimeInterface $fechaExpedicion = null,
        ?string $fotoDataUri = null
    ): string {
        $tz = new DateTimeZone(self::TIMEZONE);
        if ($fechaExpedicion === null) {
            $fechaExpedicion = new DateTimeImmutable('now', $tz);
        }

        // Logos oficiales idénticos a ConstanciaPdf
        $logoMin = Configuracion::logoEstrictoConstancia('ministerio_logo', 140, 42);
        $ministerioLogo = $logoMin['uri'] ?? null;
        $logoPla = Configuracion::logoEstrictoConstancia('plantel_logo', 50, 58);
        $plantelLogo = $logoPla['uri'] ?? null;

        // Firma y sello institucional
        $firmaUri = $config['firmaUri'] ?? Configuracion::dataUriPrivado('director_firma');
        $selloUri = $config['selloUri'] ?? Configuracion::dataUriPrivado('institucion_sello');

        // Autoridad directiva
        $dirNombre    = mb_strtoupper(trim((string) ($config['director_nombre'] ?? '')), 'UTF-8');
        $dirCargo     = mb_strtoupper(trim((string) ($config['director_cargo'] ?? 'DIRECTOR(A)')), 'UTF-8');
        $dirCedulaRaw = (string) ($config['director_cedula'] ?? '');
        $dirLetra     = preg_match('/^[VE]/i', $dirCedulaRaw, $m) ? strtoupper($m[0]) : 'V';
        $dirNumLimpio = preg_replace('/\D/', '', $dirCedulaRaw);
        $dirNumFmt    = ($dirNumLimpio !== '') ? number_format((int) $dirNumLimpio, 0, '', '.') : '';
        $dirDocTexto  = ($dirNumFmt !== '') ? ($dirLetra . '-' . $dirNumFmt) : '';

        // Datos del plantel
        $nombrePlantel     = trim((string) ($config['plantel_nombre'] ?? 'ESCUELA BÁSICA "ISIDRO RAMÍREZ"'));
        $plantelDea        = trim((string) ($config['plantel_codigo_dea'] ?? ''));
        $codigoDependencia = trim((string) ($config['plantel_codigo_dependencia'] ?? ''));

        // Cierre de fecha automática
        $diaExp      = (int) $fechaExpedicion->format('j');
        $diaTexto    = ($diaExp === 1) ? 'al primer (1) día' : "a los {$diaExp} días";
        $mesExpTexto = self::MESES_ESPANOL[(int) $fechaExpedicion->format('n')] ?? 'octubre';
        $anioExpTexto = $fechaExpedicion->format('Y');
        $ciudadExp   = trim((string) ($config['lugar_expedicion'] ?? $config['plantel_municipio'] ?? 'Valencia'));
        if ($ciudadExp === '') {
            $ciudadExp = 'Valencia';
        }
        $fechaCierreTexto = "Emitido en {$ciudadExp}, {$diaTexto} del mes de {$mesExpTexto} de {$anioExpTexto}.";

        $estilosPdf = PdfRecursos::estilos('ficha');

        $variables = [
            'config'            => $config,
            'empleado'          => $empleado,
            'formacion'         => $formacion,
            'asignaciones'      => $asignaciones,
            'reposos'           => $reposos,
            'historial'         => $historial,
            'fotoDataUri'       => $fotoDataUri,
            'ministerioLogo'    => $ministerioLogo,
            'plantelLogo'       => $plantelLogo,
            'firmaUri'          => $firmaUri,
            'selloUri'          => $selloUri,
            'dirNombre'         => $dirNombre,
            'dirCargo'          => $dirCargo,
            'dirDocTexto'       => $dirDocTexto,
            'nombrePlantel'     => $nombrePlantel,
            'plantelDea'        => $plantelDea,
            'codigoDependencia' => $codigoDependencia,
            'fechaCierreTexto'  => $fechaCierreTexto,
            'estilosPdf'        => $estilosPdf,
        ];

        return self::renderizar(
            dirname(__DIR__, 2) . '/app/Views/empleados/export-pdf.php',
            $variables
        );
    }

    /**
     * Convierte una ruta relativa pública a Data URI para Dompdf.
     */
    public static function imagenADataUri(?string $rutaRelativa): ?string
    {
        if ($rutaRelativa === null || trim($rutaRelativa) === '') {
            return null;
        }

        $limpia = '/' . ltrim(str_replace('\\', '/', $rutaRelativa), '/');
        $basePublic = realpath(dirname(__DIR__, 2) . '/public');
        $archivo = realpath(dirname(__DIR__, 2) . '/public' . $limpia);

        if ($basePublic === false || $archivo === false || !str_starts_with($archivo, $basePublic . DIRECTORY_SEPARATOR) || !is_file($archivo)) {
            return null;
        }

        $info = @getimagesize($archivo);
        if (!$info) {
            return null;
        }

        $mime = $info['mime'] ?? 'image/jpeg';
        $bytes = @file_get_contents($archivo);
        if ($bytes === false) {
            return null;
        }

        return 'data:' . $mime . ';base64,' . base64_encode($bytes);
    }

    /**
     * Renderiza la plantilla PHP y compila con Dompdf en hoja Carta con numeración de página centrada.
     */
    private static function renderizar(string $templatePath, array $variables): string
    {
        if (!is_file($templatePath)) {
            throw new RuntimeException("Plantilla PDF no encontrada: {$templatePath}");
        }

        extract($variables);

        ob_start();
        try {
            require $templatePath;
            $html = ob_get_clean();
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        $dompdf = new Dompdf(PdfRecursos::opciones('Times-Roman'));
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();

        // Numeración centrada al pie: "Página X de Y" en 9 pt
        $canvas = $dompdf->getCanvas();
        $font   = $dompdf->getFontMetrics()->getFont('Times-Roman', 'normal');
        $canvas->page_text(
            272,
            745,
            'Página {PAGE_NUM} de {PAGE_COUNT}',
            $font,
            9,
            [0, 0, 0]
        );

        return $dompdf->output();
    }
}
