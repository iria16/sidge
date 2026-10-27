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
 * FichaEstudiantePdf
 * ------------------
 * Generación oficial del PDF "Ficha Integral del Estudiante" mediante Dompdf.
 *
 * Aplica el patrón institucional unificado (Certificado y Constancia):
 * - Hoja tamaño Carta vertical con márgenes de 2.5 cm (71 pt).
 * - Tipografía Times New Roman (#000000) y eliminación total de colores azules.
 * - Membrete ministerial e institucional de 3 columnas (20% / 60% / 20%).
 * - Cuadro de datos del estudiante con borde fino (0.75 pt), esquinas rectas y sin sombras.
 * - Foto proporcional fija con fallback neutro que nunca rompe la maqueta.
 * - Cédula Escolar o Cédula de Identidad excluyentes con concordancia de género.
 * - Cierre formal de fecha automática y bloque de firma/sello oficial idéntico.
 * - Numeración de páginas "Página X de Y" en 9 pt centrada al pie vía canvas.
 */
class FichaEstudiantePdf
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
     * Genera el binario PDF de la Ficha Integral del Estudiante.
     */
    public static function generar(
        array $config,
        array $estudiante,
        array $datosEditables = [],
        array $familiaresNucleo = [],
        array $autorizadosParaRetiro = [],
        array $historialAcademico = [],
        bool $esDocente = false,
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
        $dirNombre = mb_strtoupper(trim((string) ($config['director_nombre'] ?? '')), 'UTF-8');
        $dirCargo  = mb_strtoupper(trim((string) ($config['director_cargo'] ?? 'DIRECTOR(A)')), 'UTF-8');
        $dirCedulaRaw = (string) ($config['director_cedula'] ?? '');
        $dirLetra = preg_match('/^[VE]/i', $dirCedulaRaw, $m) ? strtoupper($m[0]) : 'V';
        $dirNumLimpio = preg_replace('/\D/', '', $dirCedulaRaw);
        $dirNumFormatted = ($dirNumLimpio !== '') ? number_format((int) $dirNumLimpio, 0, '', '.') : '';
        $dirDocTexto = ($dirNumFormatted !== '') ? ($dirLetra . '-' . $dirNumFormatted) : '';

        // Datos del plantel
        $nombrePlantel = trim((string) ($config['plantel_nombre'] ?? 'ESCUELA BÁSICA "ISIDRO RAMÍREZ"'));
        $plantelDea = trim((string) ($config['plantel_codigo_dea'] ?? ''));
        $codigoDependencia = trim((string) ($config['plantel_codigo_dependencia'] ?? ''));

        // Cierre de fecha automática
        $diaExp = (int) $fechaExpedicion->format('j');
        $diaTexto = ($diaExp === 1) ? 'al primer (1) día' : "a los {$diaExp} días";
        $mesExpTexto = self::MESES_ESPANOL[(int) $fechaExpedicion->format('n')] ?? 'octubre';
        $anioExpTexto = $fechaExpedicion->format('Y');
        $ciudadExp = trim((string) ($config['lugar_expedicion'] ?? $config['plantel_municipio'] ?? 'Valencia'));
        if ($ciudadExp === '') {
            $ciudadExp = 'Valencia';
        }
        $fechaCierreTexto = "Emitido en {$ciudadExp}, {$diaTexto} del mes de {$mesExpTexto} de {$anioExpTexto}.";

        $estilosPdf = PdfRecursos::estilos('ficha');

        $variables = [
            'config'                => $config,
            'estudiante'            => $estudiante,
            'datosEditables'        => $datosEditables,
            'familiaresNucleo'      => $familiaresNucleo,
            'autorizadosParaRetiro' => $autorizadosParaRetiro,
            'historialAcademico'    => $historialAcademico,
            'esDocente'             => $esDocente,
            'fotoDataUri'           => $fotoDataUri,
            'ministerioLogo'        => $ministerioLogo,
            'plantelLogo'           => $plantelLogo,
            'firmaUri'              => $firmaUri,
            'selloUri'              => $selloUri,
            'dirNombre'             => $dirNombre,
            'dirCargo'              => $dirCargo,
            'dirDocTexto'           => $dirDocTexto,
            'nombrePlantel'         => $nombrePlantel,
            'plantelDea'            => $plantelDea,
            'codigoDependencia'     => $codigoDependencia,
            'fechaCierreTexto'      => $fechaCierreTexto,
            'estilosPdf'            => $estilosPdf,
        ];

        return self::renderizar(
            dirname(__DIR__, 2) . '/app/Views/consulta_academica/estudiantes/export-pdf.php',
            $variables
        );
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
