<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;
use DateTimeZone;
use Dompdf\Dompdf;
use Dompdf\Options;
use RuntimeException;
use Throwable;

if (!class_exists(\Dompdf\Dompdf::class)) {
    $vendorAutoload = __DIR__ . '/../../vendor/autoload.php';
    if (file_exists($vendorAutoload)) {
        require_once $vendorAutoload;
    }
}

/**
 * ListadoPdf
 * ----------
 * Servicio especializado para la generación y renderización de reportes
 * tabulares oficiales de sección (Nómina Matriz, Directorio, Asistencia)
 * mediante Dompdf a formato carta vertical.
 */
class ListadoPdf
{
    private const TIMEZONE = 'America/Caracas';

    /**
     * Genera el binario PDF para el tipo de listado solicitado.
     *
     * @param array  $config           Configuración institucional de Configuracion::getAll()
     * @param array  $seccion          Detalles de la sección y docente
     * @param array  $estudiantes      Lista de estudiantes con representantes y contactos
     * @param string $tipoDocumento    'nomina' | 'contactos'
     * @param array  $opcionesColumnas Array asociativo con toggles ('mostrar_cedula_escolar', 'mostrar_telefono', 'mostrar_retiro')
     * @return string Binario PDF generado
     */
    public static function generar(
        array $config,
        array $seccion,
        array $estudiantes,
        string $tipoDocumento = 'nomina',
        array $opcionesColumnas = []
    ): string {
        $tz = new DateTimeZone(self::TIMEZONE);
        $fechaHoy = new DateTimeImmutable('now', $tz);

        // Assets gráficos institucionales como Data URIs base64
        $logoMin = Configuracion::logoEstrictoConstancia('ministerio_logo', 130, 40);
        $ministerioLogo = $logoMin['uri'] ?? null;
        $dimMin = $logoMin ? ['width' => $logoMin['width'], 'height' => $logoMin['height']] : ['width' => 0, 'height' => 0];

        $logoPla = Configuracion::logoEstrictoConstancia('plantel_logo', 48, 54);
        $plantelLogo = $logoPla['uri'] ?? null;
        $dimPla = $logoPla ? ['width' => $logoPla['width'], 'height' => $logoPla['height']] : ['width' => 0, 'height' => 0];

        $firmaUri = Configuracion::dataUriPrivado('director_firma');
        $selloUri = Configuracion::dataUriPrivado('institucion_sello');

        // Métricas de género
        $totalEst = count($estudiantes);
        $totalFem = 0;
        $totalMasc = 0;
        foreach ($estudiantes as $e) {
            $gen = strtoupper((string) ($e['genero'] ?? ''));
            if ($gen === 'F') {
                $totalFem++;
            } elseif ($gen === 'M') {
                $totalMasc++;
            }
        }

        // Datos del director formateados
        $dirNombre = trim((string) ($config['director_nombre'] ?? ''));
        $dirCargo  = trim((string) ($config['director_cargo'] ?? 'Director(a) del Plantel'));
        $dirCedulaRaw = (string) ($config['director_cedula'] ?? '');
        $dirNumLimpio = preg_replace('/\D/', '', $dirCedulaRaw);
        $dirCedulaFmt = ($dirNumLimpio !== '') ? 'C.I. ' . number_format((int) $dirNumLimpio, 0, '', '.') : '';

        // Datos del docente formateados
        $docNombre = trim((string) ($seccion['docente_nombre'] ?? ''));
        if ($docNombre === '') {
            $docNombre = 'Docente no asignado';
        }
        $docCargo = trim((string) ($seccion['docente_cargo'] ?? 'Docente de Aula Titular'));
        $docCedulaRaw = (string) ($seccion['docente_cedula'] ?? '');
        $docNumLimpio = preg_replace('/\D/', '', $docCedulaRaw);
        $docCedulaFmt = ($docNumLimpio !== '') ? 'C.I. ' . number_format((int) $docNumLimpio, 0, '', '.') : '';

        // Título y subtítulo del documento según el tipo
        switch ($tipoDocumento) {
            case 'contactos':
                $tituloDocumento = 'DIRECTORIO DE CONTACTOS Y DATOS FAMILIARES';
                $subtituloDoc    = 'Ficha de Emergencias y Localización Inmediata';
                $badgeDoc        = 'CON DATOS FAMILIARES';
                break;
            case 'nomina':
            default:
                $tipoDocumento   = 'nomina';
                $tituloDocumento = 'NÓMINA MATRIZ DE ESTUDIANTES Y REPRESENTANTES';
                $subtituloDoc    = 'Control de Aula Regular';
                $badgeDoc        = 'FORMATO OFICIAL';
                break;
        }

        $variables = [
            'config'            => $config,
            'seccion'           => $seccion,
            'estudiantes'       => $estudiantes,
            'tipoDocumento'     => $tipoDocumento,
            'opcionesColumnas'  => $opcionesColumnas,
            'tituloDocumento'   => $tituloDocumento,
            'subtituloDoc'      => $subtituloDoc,
            'badgeDoc'          => $badgeDoc,
            'fechaEmision'      => $fechaHoy->format('d/m/Y'),
            'horaEmision'       => $fechaHoy->format('h:i A'),
            'totalEst'          => $totalEst,
            'totalFem'          => $totalFem,
            'totalMasc'         => $totalMasc,
            'ministerioLogo'    => $ministerioLogo,
            'plantelLogo'       => $plantelLogo,
            'dimMin'            => $dimMin,
            'dimPla'            => $dimPla,
            'firmaUri'          => $firmaUri,
            'selloUri'          => $selloUri,
            'dirNombre'         => $dirNombre,
            'dirCargo'          => $dirCargo,
            'dirCedulaFmt'      => $dirCedulaFmt,
            'docNombre'         => $docNombre,
            'docCargo'          => $docCargo,
            'docCedulaFmt'      => $docCedulaFmt,
        ];

        return self::renderizarPdf(__DIR__ . '/../Views/docente/pdf/plantilla_listado.php', $variables);
    }

    /**
     * Renderiza la plantilla HTML y la compila con Dompdf.
     */
    private static function renderizarPdf(string $templatePath, array $variables): string
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

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'Helvetica');
        $options->set('chroot', [
            Configuracion::getProjectRoot(),
            Configuracion::getPublicRoot(),
        ]);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();

        // Numeración de páginas en el pie con el canvas de Dompdf
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('Helvetica', 'normal');
        $canvas->page_text(
            270, 
            762, 
            "Página {PAGE_NUM} de {PAGE_COUNT}  ·  SIGDE", 
            $font, 
            7.5, 
            [0.4, 0.45, 0.5]
        );

        return $dompdf->output();
    }
}
