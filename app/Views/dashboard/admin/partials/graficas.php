<?php
declare(strict_types=1);

/**
 * Partial: dashboard/admin/partials/graficas.php
 * Gráficas analíticas de cuentas y seguridad institucional para Administración.
 * Reutiliza el módulo compartido public/assets/js/modules/dashboard/charts.js y la paleta --chart-*.
 *
 * Variables esperadas:
 * @var array $actividad
 * @var array $usuariosPorRol
 * @var array $indicadores
 */

$encodeConfig = static function (array $config): string {
    $flags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
    return htmlspecialchars((string) json_encode($config, $flags), ENT_QUOTES, 'UTF-8');
};

// 1. Configuración: Usuarios por Rol (Dona)
$rolesLabels = [];
$rolesValues = [];
$rolesColors = ['c1', 'c2', 'c3', 'c4', 'c5', 'c6'];

foreach ($usuariosPorRol as $r) {
    $rolesLabels[] = (string) ($r['rol'] ?? '');
    $rolesValues[] = (int) ($r['total'] ?? 0);
}

$donutRolesConfig = [
    'labels'       => $rolesLabels,
    'values'       => $rolesValues,
    'colors'       => array_slice($rolesColors, 0, count($rolesLabels)),
    'emptyMessage' => 'Sin cuentas de usuario registradas en el sistema',
];

// 2. Configuración: Actividad de Bitácora (30 días - Barras)
$diasLabels = [];
$opsData    = [];

foreach (($actividad['serie'] ?? []) as $punto) {
    $diasLabels[] = (string) ($punto['dia_mes'] ?? '');
    $opsData[]    = (int) ($punto['total'] ?? 0);
}

$barrasActividadConfig = [
    'labels'   => $diasLabels,
    'datasets' => [
        [
            'label'    => 'Operaciones diarias',
            'data'     => $opsData,
            'colorKey' => 'c2',
        ],
    ],
    'emptyMessage' => 'Sin actividad registrada en los últimos 30 días',
];

$totalUsuarios = (int) ($indicadores['total_usuarios'] ?? 0);
$totalOps      = (int) ($actividad['total'] ?? 0);
?>
<div class="row g-3 mb-4">
  <!-- Gráfica 1: Composición de Usuarios por Rol (Dona) -->
  <div class="col-lg-5">
    <section class="content-card h-100" aria-labelledby="chartRolesHeading">
      <div class="content-card__header d-flex justify-content-between align-items-center">
        <h2 class="content-card__title mb-0" id="chartRolesHeading">
          <i class="bi bi-pie-chart text-primary me-2" aria-hidden="true"></i>Usuarios por Rol
        </h2>
        <span class="text-support small"><?= number_format($totalUsuarios, 0, ',', '.') ?> cuentas</span>
      </div>
      <div class="content-card__body p-3">
        <div class="chart-container chart-container--donut"
             data-sigde-chart="donut"
             data-chart-config="<?= $encodeConfig($donutRolesConfig) ?>">
          <canvas aria-label="Gráfica de dona que representa la distribución porcentual de usuarios por rol"></canvas>
        </div>
      </div>
    </section>
  </div>

  <!-- Gráfica 2: Frecuencia de Operaciones en Bitácora (Barras) -->
  <div class="col-lg-7">
    <section class="content-card h-100" aria-labelledby="chartActividadHeading">
      <div class="content-card__header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2 class="content-card__title mb-0" id="chartActividadHeading">
          <i class="bi bi-activity text-info me-2" aria-hidden="true"></i>Actividad del Sistema (30 días)
        </h2>
        <span class="text-support small"><?= number_format($totalOps, 0, ',', '.') ?> ops registradas</span>
      </div>
      <div class="content-card__body p-3">
        <div class="chart-container"
             data-sigde-chart="grouped-bar"
             data-chart-config="<?= $encodeConfig($barrasActividadConfig) ?>">
          <canvas aria-label="Gráfica de barras que representa la frecuencia diaria de operaciones en bitácora durante los últimos 30 días"></canvas>
        </div>
      </div>
    </section>
  </div>
</div>
