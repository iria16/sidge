<?php
declare(strict_types=1);

/**
 * Partial: dashboard/secretaria/partials/graficas.php
 * Gráficas analíticas de composición de matrícula y cupos por grado del turno.
 * Reutiliza el módulo compartido public/assets/js/modules/dashboard/charts.js y la paleta --chart-*.
 *
 * Variables esperadas:
 * @var array|null $estadisticasInscripciones
 * @var array|null $estadisticasRatificacion
 * @var array|null $cuposPorEstructura
 */

$encodeConfig = static function (array $config): string {
    $flags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
    return htmlspecialchars((string) json_encode($config, $flags), ENT_QUOTES, 'UTF-8');
};

// 1. Configuración: Composición de la Matrícula del Turno (Dona)
$nuevosIngresos = (int) ($estadisticasInscripciones['inscritosAnio'] ?? 0);
$yaRatificados  = (int) ($estadisticasRatificacion['yaRatificados'] ?? 0);
$totalMatricula = $nuevosIngresos + $yaRatificados;

$donutComposicionConfig = [
    'labels'       => ['Ratificados (Continuidad)', 'Nuevos Ingresos'],
    'values'       => [$yaRatificados, $nuevosIngresos],
    'colors'       => ['c1', 'c2'],
    'emptyMessage' => 'Sin estudiantes formalizados en el período actual',
];

// 2. Configuración: Inscritos vs Capacidad por Grado (Barras Agrupadas)
$porGrado = [];
foreach (($cuposPorEstructura['filas'] ?? []) as $fila) {
    $grado = (string) ($fila['grado'] ?? '');
    if (!isset($porGrado[$grado])) {
        $porGrado[$grado] = ['inscritos' => 0, 'capacidad' => 0];
    }
    $porGrado[$grado]['inscritos'] += (int) ($fila['inscritos'] ?? 0);
    $porGrado[$grado]['capacidad'] += (int) ($fila['capacidad'] ?? 0);
}

$barrasGradosConfig = [
    'labels'   => array_keys($porGrado),
    'datasets' => [
        [
            'label'    => 'Inscritos',
            'data'     => array_column($porGrado, 'inscritos'),
            'colorKey' => 'c1',
        ],
        [
            'label'    => 'Capacidad',
            'data'     => array_column($porGrado, 'capacidad'),
            'colorKey' => 'c3',
        ],
    ],
    'emptyMessage' => 'Sin secciones ni capacidad configurada en el turno',
];
?>
<div class="row g-3 mb-4">
  <!-- Gráfica 1: Composición de la Matrícula (Dona) -->
  <div class="col-lg-5">
    <section class="content-card h-100" aria-labelledby="chartComposicionHeading">
      <div class="content-card__header d-flex justify-content-between align-items-center">
        <h2 class="content-card__title mb-0" id="chartComposicionHeading">
          <i class="bi bi-pie-chart text-primary me-2" aria-hidden="true"></i>Composición de Matrícula
        </h2>
        <span class="text-support small"><?= number_format($totalMatricula, 0, ',', '.') ?> formalizados</span>
      </div>
      <div class="content-card__body p-3">
        <div class="chart-container chart-container--donut"
             data-sigde-chart="donut"
             data-chart-config="<?= $encodeConfig($donutComposicionConfig) ?>">
          <canvas aria-label="Gráfica de dona que representa la proporción de ratificados frente a nuevos ingresos en el turno"></canvas>
        </div>
      </div>
    </section>
  </div>

  <!-- Gráfica 2: Inscritos vs Capacidad por Grado (Barras Agrupadas) -->
  <div class="col-lg-7">
    <section class="content-card h-100" aria-labelledby="chartCuposHeading">
      <div class="content-card__header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2 class="content-card__title mb-0" id="chartCuposHeading">
          <i class="bi bi-bar-chart text-info me-2" aria-hidden="true"></i>Inscritos vs. Capacidad por Grado
        </h2>
        <span class="text-support small">Disponibilidad física por grado en el turno</span>
      </div>
      <div class="content-card__body p-3">
        <div class="chart-container"
             data-sigde-chart="grouped-bar"
             data-chart-config="<?= $encodeConfig($barrasGradosConfig) ?>">
          <canvas aria-label="Gráfica de barras que compara estudiantes inscritos frente a la capacidad por grado escolar"></canvas>
        </div>
      </div>
    </section>
  </div>
</div>