<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;
use DateTimeZone;
use Dompdf\Dompdf;
use RuntimeException;
use Throwable;

/**
 * Genera la vista previa HTML y el PDF de los reportes institucionales.
 *
 * La misma plantilla alimenta el iframe de vista previa y el archivo PDF, para
 * que lo que se revisa en pantalla sea exactamente lo que se descarga.
 */
final class ReporteOficialPdf
{
    private const TIMEZONE = 'America/Caracas';

    /**
     * @param array<string,string> $config
     * @param array<string,mixed> $datosReporte
     * @param array<string,mixed>|null $resumenRepresentantes
     * @param array{anio?:int,q?:string,grado?:int|null,seccion?:int|null} $filtros
     * @return array<string,mixed>
     */
    public static function variables(
        array $config,
        string $tipo,
        string $nombrePeriodo,
        array $datosReporte,
        ?array $resumenRepresentantes,
        array $filtros,
        string $tituloReporte
    ): array {
        $tz = new DateTimeZone(self::TIMEZONE);
        $fechaHoy = new DateTimeImmutable('now', $tz);

        $logoMin = Configuracion::logoEstrictoConstancia('ministerio_logo', 128, 40);
        $logoPla = Configuracion::logoEstrictoConstancia('plantel_logo', 46, 52);

        $lineas = array_values(array_filter([
            trim((string) ($config['ministerio_linea_1'] ?? 'REPÚBLICA BOLIVARIANA DE VENEZUELA')),
            trim((string) ($config['ministerio_linea_2'] ?? 'MINISTERIO DEL PODER POPULAR PARA LA EDUCACIÓN')),
            trim((string) ($config['plantel_nombre'] ?? 'E.B. ISIDRO RAMÍREZ')),
        ]));

        $codigos = [];
        $dea = trim((string) ($config['plantel_codigo_dea'] ?? ''));
        $dep = trim((string) ($config['plantel_codigo_dependencia'] ?? ''));
        if ($dea !== '') {
            $codigos[] = "CÓDIGO DEA: {$dea}";
        }
        if ($dep !== '') {
            $codigos[] = "DEP: {$dep}";
        }
        if ($codigos !== []) {
            $lineas[] = implode('   |   ', $codigos);
        }

        $totalRegistros = isset($datosReporte['filas']) && is_array($datosReporte['filas'])
            ? count($datosReporte['filas'])
            : count($datosReporte);

        return [
            'config'                  => $config,
            'tipo'                    => $tipo,
            'tituloReporte'           => $tituloReporte,
            'nombrePeriodo'           => $nombrePeriodo,
            'datosReporte'            => $datosReporte,
            'resumenRepresentantes'   => $resumenRepresentantes,
            'filtros'                 => $filtros,
            'totalRegistros'          => $totalRegistros,
            'fechaEmision'            => $fechaHoy->format('d/m/Y'),
            'horaEmision'             => $fechaHoy->format('h:i A'),
            'membreteLogoMin'         => $logoMin,
            'membreteLogoPla'         => $logoPla,
            'membreteLineas'          => $lineas,
            'membreteTextoClase'      => 'nomina-membrete',
            'membreteAltEscuela'      => (string) ($config['plantel_nombre'] ?? 'Escuela'),
            'estilosPdf'              => PdfRecursos::estilos('reporte-oficial'),
        ];
    }

    /**
     * Devuelve el HTML que se muestra en el iframe de vista previa.
     *
     * @param array<string,mixed> $variables
     */
    public static function html(array $variables): string
    {
        return self::renderizar(
            dirname(__DIR__) . '/Views/reportes/pdf/oficial.php',
            $variables
        );
    }

    /**
     * Compila la misma plantilla con Dompdf.
     *
     * @param array<string,mixed> $variables
     */
    public static function generar(array $variables): string
    {
        $html = self::html($variables);

        $dompdf = new Dompdf(PdfRecursos::opciones('Helvetica'));
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('letter', 'landscape');
        $dompdf->render();

        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('Helvetica', 'normal');
        $canvas->page_text(
            360,
            565,
            'Página {PAGE_NUM} de {PAGE_COUNT}',
            $font,
            8,
            [0.35, 0.35, 0.35]
        );

        return $dompdf->output();
    }

    /**
     * @param array<string,mixed> $variables
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
            return (string) ob_get_clean();
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }
}
