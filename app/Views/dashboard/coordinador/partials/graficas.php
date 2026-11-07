<?php
declare(strict_types=1);

/**
 * Partial: dashboard/coordinador/partials/graficas.php
 * Gráficas analíticas de supervisión pedagógica y balance escolar para Coordinación.
 * Reutiliza el módulo compartido public/assets/js/modules/dashboard/charts.js y la paleta --chart-*.
 *
 * Variables esperadas:
 * @var array $metricasMatricula
 * @var array $metricasPersonal
 * @var array $distribucionGrados
 */

// Helper para codificar configuraciones JSON de forma segura en atributos data-*
$encodeConfig = static function (array $config): string {
    $flags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
    return htmlspecialchars((string) json_encode($config, $flags), ENT_QUOTES, 'UTF-8');
};

// 1. Configuración: Matrícula por Sexo (Dona)
$totalEstudiantes = (int) ($metricasMatricula['total_estudiantes'] ?? 0);
$varones = (int) ($metricasMatricula['varones'] ?? 0);
$hembras = (int) ($metricasMatricula['hembras'] ?? 0);

$donutSexoConfig = [
    'labels'       => ['Varones', 'Hembras'],
    'values'       => [$varones, $hembras],
    'colors'       => ['c1', 'c2'],
    'emptyMessage' => 'Sin estudiantes cursando en el período activo',
];

// 2. Configuración: Composición del Personal Activo (Dona dinámica por cargo registrado)
$cargosPersonal = $metricasPersonal['por_cargo'] ?? [];
$personalLabels = [];
$personalValues = [];

foreach ($cargosPersonal as $cp) {
    $personalLabels[] = (string) ($cp['cargo'] ?? '');
    $personalValues[] = (int) ($cp['total'] ?? 0);
}

// Paleta cíclica c1 a c6 para los cargos
$personalColors = [];
$paletaKeys = ['c1', 'c2', 'c3', 'c4', 'c5', 'c6'];
foreach ($personalLabels as $idx => $lbl) {
    $personalColors[] = $paletaKeys[$idx % count($paletaKeys)];
}

$donutPersonalConfig = [
    'labels'       => $personalLabels,
    'values'       => $personalValues,
    'colors'       => $personalColors,
    'emptyMessage' => 'Sin personal activo registrado en nómina',
];

// 3. Configuración: Matrícula vs. Capacidad por Grado (Barras Agrupadas)
$gradosLabels    = [];
$gradosMatricula = [];
$gradosCapacidad = [];

foreach ($distribucionGrados as $g) {
    $gradosLabels[]    = (string) ($g['grado'] ?? '');
    $gradosMatricula[] = (int) ($g['total_estudiantes'] ?? 0);
    $gradosCapacidad[] = (int) ($g['capacidad'] ?? 0);
}

$barrasGradosConfig = [
    'labels'   => $gradosLabels,
    'datasets' => [
        [
            'label'    => 'Matrícula actual',
            'data'     => $gradosMatricula,
            'colorKey' => 'c1',
        ],
        [
            'label'    => 'Capacidad instalada',
            'data'     => $gradosCapacidad,
            'colorKey' => 'c3',
        ],
    ],
    'emptyMessage' => 'Sin secciones ni matrícula configurada para el período activo',
];
?>

<!-- Fila 1: Donas de Composición (Demografía y Personal) -->
<div class="row g-3 mb-4">
  <!-- Gráfica 1: Matrícula por Sexo -->
  <div class="col-lg-6">
    <section class="content-card h-100" aria-labelledby="chartSexoHeading">
      <div class="content-card__header d-flex justify-content-between align-items-center">
        <h2 class="content-card__title mb-0" id="chartSexoHeading">
          <i class="bi bi-gender-ambiguous text-primary me-2" aria-hidden="true"></i>Matrícula por Sexo
        </h2>
        <span class="text-support small"><?= number_format($totalEstudiantes, 0, ',', '.') ?> total</span>
      </div>
      <div class="content-card__body p-3">
        <div class="chart-container chart-container--donut"
             data-sigde-chart="donut"
             data-chart-config="<?= $encodeConfig($donutSexoConfig) ?>">
          <canvas aria-label="Gráfica de dona que representa la distribución de matrícula escolar por sexo"></canvas>
        </div>
      </div>
    </section>
  </div>

  <!-- Gráfica 2: Composición del Personal -->
  <div class="col-lg-6">
    <section class="content-card h-100" aria-labelledby="chartPersonalHeading">
      <div class="content-card__header d-flex justify-content-between align-items-center">
        <h2 class="content-card__title mb-0" id="chartPersonalHeading">
          <i class="bi bi-people text-info me-2" aria-hidden="true"></i>Composición del Personal
        </h2>
        <span class="text-support small"><?= (int) ($metricasPersonal['empleados_activos'] ?? 0) ?> activos</span>
      </div>
      <div class="content-card__body p-3">
        <div class="chart-container chart-container--donut"
             data-sigde-chart="donut"
             data-chart-config="<?= $encodeConfig($donutPersonalConfig) ?>">
          <canvas aria-label="Gráfica de dona que representa la distribución del personal activo por tipo de cargo"></canvas>
        </div>
      </div>
    </section>
  </div>
</div>

<!-- Fila 2: Barras Agrupadas (Matrícula vs Capacidad por Grado) -->
<div class="row g-3 mb-4">
  <div class="col-12">
    <section class="content-card" aria-labelledby="chartGradosHeading">
      <div class="content-card__header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2 class="content-card__title mb-0" id="chartGradosHeading">
          <i class="bi bi-bar-chart text-primary me-2" aria-hidden="true"></i>Matrícula vs. Capacidad por Grado
        </h2>
        <span class="text-support small">Balance de ocupación física de aulas por grado</span>
      </div>
      <div class="content-card__body p-3">
        <div class="chart-container"
             data-sigde-chart="grouped-bar"
             data-chart-config="<?= $encodeConfig($barrasGradosConfig) ?>">
          <canvas aria-label="Gráfica de barras agrupadas comparando estudiantes cursando contra capacidad física instalada por grado"></canvas>
        </div>
      </div>
    </section>
  </div>
</div>
