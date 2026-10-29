<?php

declare(strict_types=1);

namespace App\Support;

use App\Support\Configuracion;
use DateTimeImmutable;
use DateTimeZone;
use Dompdf\Dompdf;
use Dompdf\Options;

if (!class_exists(\Dompdf\Dompdf::class)) {
    $vendorAutoload = __DIR__ . '/../../vendor/autoload.php';
    if (file_exists($vendorAutoload)) {
        require_once $vendorAutoload;
    }
}

/**
 * EstadisticaExport
 * -----------------
 * Servicio especializado para la exportación de reportes estadísticos
 * a formato CSV compatible con Microsoft Excel (UTF-8 con BOM) y PDF oficial ministerial (Dompdf).
 */
class EstadisticaExport
{
    private const TIMEZONE = 'America/Caracas';

    /**
     * Genera el contenido CSV para el reporte de Control de Hermanos.
     *
     * @param array<int, array<string, mixed>> $familias
     * @return string
     */
    public static function generarHermanosCsv(array $familias): string
    {
        $delimitador = ';';
        $salida = fopen('php://memory', 'w');
        if ($salida === false) {
            return '';
        }

        // BOM UTF-8 para apertura correcta en Excel
        fprintf($salida, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($salida, [
            'N°',
            'Representante Legal',
            'Cédula',
            'Teléfono Principal',
            'Total Estudiantes a Cargo',
            'Hermanos en Distintos Grados',
            'Detalle de Estudiantes (Nombre - Grado y Sección)'
        ], $delimitador);

        foreach ($familias as $idx => $fam) {
            $estudiantesLista = [];
            foreach (($fam['estudiantes'] ?? []) as $est) {
                $estudiantesLista[] = ($est['nombre_completo'] ?? '') . ' (' . ($est['grado_seccion'] ?? '') . ')';
            }

            fputcsv($salida, [
                $idx + 1,
                $fam['nombre_representante'] ?? '',
                $fam['cedula_representante'] ?? '',
                $fam['telefono_representante'] ?? 'Sin teléfono',
                (int) ($fam['total_estudiantes'] ?? 0),
                !empty($fam['hermanos_distintos_grados']) ? 'SÍ (Distintos Grados)' : 'NO (Mismo Grado)',
                implode(' | ', $estudiantesLista),
            ], $delimitador);
        }

        rewind($salida);
        $csv = stream_get_contents($salida);
        fclose($salida);

        return $csv !== false ? $csv : '';
    }

    /**
     * Genera el contenido CSV para la Matrícula y Capacidad Escolar.
     *
     * @param array<int, array<string, mixed>> $aulas
     * @return string
     */
    public static function generarMatriculaCsv(array $aulas): string
    {
        $delimitador = ';';
        $salida = fopen('php://memory', 'w');
        if ($salida === false) {
            return '';
        }

        fprintf($salida, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($salida, [
            'N°',
            'Grado',
            'Sección',
            'Turno',
            'Capacidad Máxima',
            'Total Matriculados',
            'Varones (M)',
            'Hembras (F)',
            'Cupos Disponibles',
            '% Ocupación'
        ], $delimitador);

        foreach ($aulas as $idx => $a) {
            $cap = (int) ($a['capacidad_maxima'] ?? 0);
            $tot = (int) ($a['total_matricula'] ?? 0);
            $dis = max(0, $cap - $tot);
            $pct = $cap > 0 ? (int) min(100, round(($tot / $cap) * 100)) : 0;

            fputcsv($salida, [
                $idx + 1,
                $a['grado'] ?? '',
                $a['seccion'] ?? '',
                $a['turno'] ?? 'General',
                $cap,
                $tot,
                (int) ($a['varones'] ?? 0),
                (int) ($a['hembras'] ?? 0),
                $dis,
                $pct . '%'
            ], $delimitador);
        }

        rewind($salida);
        $csv = stream_get_contents($salida);
        fclose($salida);

        return $csv !== false ? $csv : '';
    }

    /**
     * Genera el documento PDF ministerial de Control de Hermanos.
     *
     * @param array<string, mixed> $config
     * @param array<string, mixed> $periodo
     * @param array<int, array<string, mixed>> $familias
     * @param array<string, mixed> $resumen
     * @return string
     */
    public static function generarHermanosPdf(
        array $config,
        array $periodo,
        array $familias,
        array $resumen
    ): string {
        $tz = new DateTimeZone(self::TIMEZONE);
        $fechaHoy = new DateTimeImmutable('now', $tz);

        $logoMin = Configuracion::logoEstrictoConstancia('ministerio_logo', 130, 40);
        $ministerioLogo = $logoMin['uri'] ?? null;
        $logoPla = Configuracion::logoEstrictoConstancia('plantel_logo', 48, 54);
        $plantelLogo = $logoPla['uri'] ?? null;

        $cintilloUri = Configuracion::dataUriPublico('plantel_cintillo');

        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
          <meta charset="UTF-8">
          <title>Control Institucional de Hermanos</title>
          <style>
            @page { margin: 18mm 12mm 20mm 12mm; }
            body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 8pt; color: #1e293b; line-height: 1.3; }
            .header-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
            .header-table td { vertical-align: middle; }
            .school-name { font-size: 9pt; font-weight: bold; text-align: center; text-transform: uppercase; color: #0f172a; }
            .doc-title { font-size: 11pt; font-weight: bold; text-align: center; text-transform: uppercase; color: #1e3a8a; margin: 8px 0 3px 0; }
            .doc-subtitle { font-size: 8pt; text-align: center; color: #64748b; margin-bottom: 12px; }
            
            .summary-box { width: 100%; border: 1px solid #cbd5e1; background-color: #f8fafc; border-radius: 4px; padding: 6px 10px; margin-bottom: 12px; }
            .summary-table { width: 100%; border-collapse: collapse; }
            .summary-table td { padding: 3px 6px; font-size: 7.5pt; }
            .summary-val { font-weight: bold; color: #0f172a; font-size: 8.5pt; }

            .data-table { width: 100%; border-collapse: collapse; margin-top: 6px; }
            .data-table th { background-color: #1e3a8a; color: #ffffff; font-weight: bold; text-align: left; padding: 5px 6px; font-size: 7.5pt; border: 1px solid #1e3a8a; }
            .data-table td { padding: 4px 6px; border: 1px solid #e2e8f0; font-size: 7.5pt; vertical-align: top; }
            .data-table tr:nth-child(even) { background-color: #f8fafc; }
            .text-center { text-align: center; }
            .text-end { text-align: right; }
            .badge-diff { color: #92400e; font-weight: bold; background-color: #fef3c7; padding: 1px 4px; border-radius: 3px; font-size: 6.5pt; }
            .badge-same { color: #3730a3; font-weight: bold; background-color: #e0e7ff; padding: 1px 4px; border-radius: 3px; font-size: 6.5pt; }
            
            .footer-cintillo { position: fixed; bottom: -12mm; left: 0; right: 0; height: 10mm; text-align: center; }
            .footer-cintillo img { max-width: 100%; height: auto; }
            .page-num:after { content: counter(page); }
          </style>
        </head>
        <body>
          <table class="header-table">
            <tr>
              <td style="width: 20%; text-align: left;">
                <?php if ($ministerioLogo): ?>
                  <img src="<?= $ministerioLogo ?>" alt="Ministerio" style="max-height: 38px;">
                <?php endif; ?>
              </td>
              <td style="width: 60%;" class="school-name">
                <?= htmlspecialchars((string) ($config['ministerio_linea_1'] ?? 'REPÚBLICA BOLIVARIANA DE VENEZUELA')) ?><br>
                <?= htmlspecialchars((string) ($config['plantel_nombre'] ?? 'PLANTEL EDUCATIVO')) ?><br>
                <span style="font-size: 7.5pt; font-weight: normal; color: #475569;">
                  CÓDIGO DEA: <?= htmlspecialchars((string) ($config['plantel_codigo_dea'] ?? '')) ?> | DEPENDENCIA: <?= htmlspecialchars((string) ($config['plantel_codigo_dependencia'] ?? '')) ?>
                </span>
              </td>
              <td style="width: 20%; text-align: right;">
                <?php if ($plantelLogo): ?>
                  <img src="<?= $plantelLogo ?>" alt="Plantel" style="max-height: 44px;">
                <?php endif; ?>
              </td>
            </tr>
          </table>

          <div class="doc-title">Reporte de Representantes y Control de Hermanos</div>
          <div class="doc-subtitle">
            Período Escolar: <strong><?= htmlspecialchars((string) ($periodo['nombre'] ?? 'Activo')) ?></strong> |
            Fecha de Emisión: <?= $fechaHoy->format('d/m/Y h:i A') ?>
          </div>

          <div class="summary-box">
            <table class="summary-table">
              <tr>
                <td>Total Estudiantes: <span class="summary-val"><?= (int) ($resumen['total_estudiantes'] ?? 0) ?></span></td>
                <td>Total Representantes: <span class="summary-val"><?= (int) ($resumen['total_representantes'] ?? 0) ?></span></td>
                <td>Promedio Alumnos/Rep: <span class="summary-val"><?= htmlspecialchars((string) ($resumen['promedio_estudiantes'] ?? '1.0')) ?></span></td>
                <td>Familias con $\ge 2$ hijos: <span class="summary-val"><?= (int) ($resumen['familias_con_hermanos'] ?? 0) ?></span></td>
                <td>Alumnos con Hermanos: <span class="summary-val"><?= (int) ($resumen['estudiantes_con_hermanos'] ?? 0) ?></span></td>
              </tr>
            </table>
          </div>

          <table class="data-table">
            <thead>
              <tr>
                <th style="width: 4%;" class="text-center">N°</th>
                <th style="width: 26%;">Representante Legal</th>
                <th style="width: 12%;">Cédula</th>
                <th style="width: 14%;">Teléfono</th>
                <th style="width: 8%;" class="text-center">Hijos</th>
                <th style="width: 36%;">Estudiantes a Cargo (Grado y Sección)</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($familias === []): ?>
                <tr>
                  <td colspan="6" class="text-center" style="padding: 16px;">No se registraron familias para los filtros seleccionados.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($familias as $i => $fam): ?>
                  <tr>
                    <td class="text-center"><?= $i + 1 ?></td>
                    <td>
                      <strong><?= htmlspecialchars((string) ($fam['nombre_representante'] ?? '')) ?></strong>
                      <?php if (!empty($fam['hermanos_distintos_grados'])): ?>
                        <br><span class="badge-diff">Hermanos en distintos grados</span>
                      <?php elseif ((int) ($fam['total_estudiantes'] ?? 0) > 1): ?>
                        <br><span class="badge-same">Mismo grado</span>
                      <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars((string) ($fam['cedula_representante'] ?? '')) ?></td>
                    <td><?= htmlspecialchars((string) ($fam['telefono_representante'] ?? 'Sin contacto')) ?></td>
                    <td class="text-center"><strong><?= (int) ($fam['total_estudiantes'] ?? 0) ?></strong></td>
                    <td>
                      <?php foreach (($fam['estudiantes'] ?? []) as $est): ?>
                        <div>• <?= htmlspecialchars((string) ($est['nombre_completo'] ?? '')) ?> <span style="color: #64748b;">(<?= htmlspecialchars((string) ($est['grado_seccion'] ?? '')) ?>)</span></div>
                      <?php endforeach; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>

          <?php if ($cintilloUri): ?>
            <div class="footer-cintillo">
              <img src="<?= $cintilloUri ?>" alt="Franja">
            </div>
          <?php endif; ?>
        </body>
        </html>
        <?php
        $html = ob_get_clean();

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();

        return (string) $dompdf->output();
    }
}
