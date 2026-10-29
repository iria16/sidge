<?php
declare(strict_types=1);

/**
 * Partial: dashboard/docente/partials/graficas.php
 * Gráficas analíticas de demografía y distribución etaria del aula docente.
 * Reutiliza el módulo compartido public/assets/js/modules/dashboard/charts.js y la paleta --chart-*.
 *
 * Variables esperadas:
 * @var array $distribucion
 * @var array $resumen
 */

$encodeConfig = static function (array $config): string {
    $flags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
    return htmlspecialchars((string) json_encode($config, $flags), ENT_QUOTES, 'UTF-8');
};

// 1. Configuración: Proporción por Sexo del Aula (Dona)
$varones = (int) ($distribucion['sexo']['varones'] ?? 0);
$hembras = (int) ($distribucion['sexo']['hembras'] ?? 0);
$totalAlumnos = (int) ($distribucion['total'] ?? 0);

$donutSexoConfig = [
    'labels'       => ['Varones', 'Hembras'],
    'values'       => [$varones, $hembras],
    'colors'       => ['c1', 'c2'],
    'emptyMessage' => 'Sin estudiantes activos matriculados en esta sección',
];

// 2. Configuración: Distribución por Edad Cumplida (Barras)
$edadesLabels = [];
$edadesData   = [];

foreach (($distribucion['edades'] ?? []) as $item) {
    $edadesLabels[] = (string) ($item['edad'] ?? '') . ' años';
    $edadesData[]   = (int) ($item['cantidad'] ?? 0);
}

$barrasEdadesConfig = [
    'labels'   => $edadesLabels,
    'datasets' => [
        [
            'label'    => 'Alumnos por edad',
            'data'     => $edadesData,
            'colorKey' => 'c2',
        ],
    ],
    'emptyMessage' => 'Sin registros de fecha de nacimiento disponibles en la sección',
];
?>
<div class="row g-3 mb-4">
  <!-- Gráfica 1: Proporción por Sexo (Dona) -->
  <div class="col-lg-5">
    <section class="content-card h-100" aria-labelledby="chartSexoDocenteHeading">
      <div class="content-card__header d-flex justify-content-between align-items-center">
        <h2 class="content-card__title mb-0" id="chartSexoDocenteHeading">
          <i class="bi bi-gender-ambiguous text-primary me-2" aria-hidden="true"></i>Proporción por Sexo
        </h2>
        <span class="text-support small"><?= number_format($totalAlumnos, 0, ',', '.') ?> en aula</span>
      </div>
      <div class="content-card__body p-3">
        <div class="chart-container chart-container--donut"
             data-sigde-chart="donut"
             data-chart-config="<?= $encodeConfig($donutSexoConfig) ?>">
          <canvas aria-label="Gráfica de dona que representa la distribución por sexo de los estudiantes del aula"></canvas>
        </div>
      </div>
    </section>
  </div>

  <!-- Gráfica 2: Distribución por Edad Cumplida (Barras) -->
  <div class="col-lg-7">
    <section class="content-card h-100" aria-labelledby="chartEdadesDocenteHeading">
      <div class="content-card__header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2 class="content-card__title mb-0" id="chartEdadesDocenteHeading">
          <i class="bi bi-bar-chart text-info me-2" aria-hidden="true"></i>Distribución por Edad Cumplida
        </h2>
        <span class="text-support small">Frecuencia de matrícula por edad en el salón</span>
      </div>
      <div class="content-card__body p-3">
        <div class="chart-container"
             data-sigde-chart="grouped-bar"
             data-chart-config="<?= $encodeConfig($barrasEdadesConfig) ?>">
          <canvas aria-label="Gráfica de barras que representa la distribución de estudiantes por edad cumplida en el salón"></canvas>
        </div>
      </div>
    </section>
  </div>
</div>
