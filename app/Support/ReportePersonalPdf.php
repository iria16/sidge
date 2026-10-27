<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;
use DateTimeZone;
use Dompdf\Dompdf;
use RuntimeException;
use Throwable;

/**
 * Genera la planilla oficial de personal en PDF.
 *
 * Es el mismo listado que muestra el Centro de Reportes, con el membrete
 * institucional y la hoja de listados compartida. No incluye datos médicos ni
 * información distinta a la que ya ve Dirección y Coordinación en pantalla.
 */
final class ReportePersonalPdf
{
    private const TIMEZONE = 'America/Caracas';

    /**
     * @param array<string,string> $config
     * @param array<int,array<string,mixed>> $personal
     * @param array{q?:string} $filtros
     */
    public static function generar(array $config, array $personal, array $filtros = []): string
    {
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

        $activos = 0;
        $enReposo = 0;
        foreach ($personal as $persona) {
            if (!empty($persona['en_reposo'])) {
                $enReposo++;
            }
            if (strtoupper((string) ($persona['estado_persona'] ?? '')) === 'ACTIVO') {
                $activos++;
            }
        }

        $variables = [
            'config'             => $config,
            'personal'           => $personal,
            'filtros'            => $filtros,
            'fechaEmision'       => $fechaHoy->format('d/m/Y'),
            'horaEmision'        => $fechaHoy->format('h:i A'),
            'membreteLogoMin'    => $logoMin,
            'membreteLogoPla'    => $logoPla,
            'membreteLineas'     => $lineas,
            'membreteTextoClase' => 'nomina-membrete',
            'membreteAltEscuela' => (string) ($config['plantel_nombre'] ?? 'Escuela'),
            'totalPersonal'      => count($personal),
            'personalActivo'     => $activos,
            'personalEnReposo'   => $enReposo,
            'estilosPdf'         => PdfRecursos::estilos('reporte-personal'),
        ];

        return self::renderizar(
            dirname(__DIR__) . '/Views/reportes/pdf/personal.php',
            $variables
        );
    }

    /**
     * Renderiza la plantilla HTML y la compila con Dompdf.
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

        $dompdf = new Dompdf(PdfRecursos::opciones('Helvetica'));
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();

        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('Helvetica', 'normal');
        $canvas->page_text(
            270,
            750,
            'Página {PAGE_NUM} de {PAGE_COUNT}',
            $font,
            8,
            [0.35, 0.35, 0.35]
        );

        return $dompdf->output();
    }
}
