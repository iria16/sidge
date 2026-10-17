<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;
use DateTimeZone;
use Dompdf\Dompdf;
use RuntimeException;
use Throwable;

/**
 * NominaPdf
 * ---------
 * Generación de documentos PDF oficiales para las 4 vistas de nómina
 * del rol Docente (nómina, acta, contactos y cumpleañeros).
 *
 * Utiliza Dompdf con hoja de estilos externa compilada desde SCSS
 * (PdfRecursos::estilos('nomina')), papel carta vertical, isRemoteEnabled = false
 * y la misma consulta de datos de EstudianteModel::nominaSeccion().
 */
final class NominaPdf
{
    private const TIMEZONE = 'America/Caracas';

    /**
     * Genera el binario PDF para la vista solicitada.
     *
     * @param array<string,mixed> $config
     * @param array<string,mixed> $seccion
     * @param string              $vista
     * @param string              $tituloVista
     * @param array<string,string> $columnas
     * @param array<int,array<string,mixed>> $filas
     * @param array<string,mixed> $filtros
     */
    public static function generar(
        array $config,
        array $seccion,
        string $vista,
        string $tituloVista,
        array $columnas,
        array $filas,
        array $filtros
    ): string {
        $tz = new DateTimeZone(self::TIMEZONE);
        $fechaHoy = new DateTimeImmutable('now', $tz);

        // Logos oficiales como Data URIs seguros (sin resolución remota)
        $logoMin = Configuracion::logoEstrictoConstancia('ministerio_logo', 128, 40);
        $logoPla = Configuracion::logoEstrictoConstancia('plantel_logo', 46, 52);

        // Líneas institucionales para el membrete compartido
        $lineas = array_values(array_filter([
            trim((string) ($config['ministerio_linea_1'] ?? 'REPÚBLICA BOLIVARIANA DE VENEZUELA')),
            trim((string) ($config['ministerio_linea_2'] ?? 'MINISTERIO DEL PODER POPULAR PARA LA EDUCACIÓN')),
            trim((string) ($config['plantel_nombre'] ?? 'E.B. ISIDRO RAMÍREZ')),
        ]));

        $dea = trim((string) ($config['plantel_codigo_dea'] ?? ''));
        $dep = trim((string) ($config['plantel_codigo_dependencia'] ?? ''));
        $codigos = [];
        if ($dea !== '') {
            $codigos[] = "CÓDIGO DEA: {$dea}";
        }
        if ($dep !== '') {
            $codigos[] = "DEP: {$dep}";
        }
        if (!empty($codigos)) {
            $lineas[] = implode('   |   ', $codigos);
        }

        $variables = [
            'config'             => $config,
            'seccion'            => $seccion,
            'vista'              => $vista,
            'tituloVista'        => $tituloVista,
            'columnas'           => $columnas,
            'filas'              => $filas,
            'filtros'            => $filtros,
            'fechaEmision'       => $fechaHoy->format('d/m/Y'),
            'horaEmision'        => $fechaHoy->format('h:i A'),
            'membreteLogoMin'    => $logoMin,
            'membreteLogoPla'    => $logoPla,
            'membreteLineas'     => $lineas,
            'membreteTextoClase' => 'nomina-membrete',
            'membreteAltEscuela' => (string) ($config['plantel_nombre'] ?? 'Escuela'),
            'estilosPdf'         => PdfRecursos::estilos('nomina'),
        ];

        return self::renderizar(
            dirname(__DIR__) . '/Views/docente/pdf/nomina_seccion.php',
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

        // Numeración institucional de páginas al pie del documento
        $canvas = $dompdf->getCanvas();
        $font   = $dompdf->getFontMetrics()->getFont('Helvetica', 'normal');
        $canvas->page_text(
            270,
            750,
            "Página {PAGE_NUM} de {PAGE_COUNT}",
            $font,
            8,
            [0.35, 0.35, 0.35]
        );

        return $dompdf->output();
    }
}
