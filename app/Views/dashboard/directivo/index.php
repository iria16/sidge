<?php
declare(strict_types=1);

/**
 * Vista: dashboard/directivo/index.php
 * Panel de Dirección: visión ejecutiva, indicadores clave, gráficas y alertas operativas.
 *
 * Variables esperadas desde DashboardController::directivo():
 * @var string $saludo
 * @var string $nombreUsuario
 * @var array|null $anioActivo
 * @var array $metricasMatricula
 * @var array $metricasCapacidad
 * @var array $metricasPersonal
 * @var array $distribucionGrados
 * @var array $avanceResultados
 * @var array $alertas
 * @var int $retirosPendientes
 */

$pageTitle    = 'Dashboard';
$currentNav   = 'dashboard';
$extraScripts = [
    '/js/vendor/chart.js',
    '/js/modules/dashboard/charts.js',
];

$breadcrumbs = [
    ['label' => 'Panel', 'href' => BASE_URL . 'dashboard/directivo'],
    ['label' => 'Dashboard', 'href' => null],
];

$hayAnioActivo = $anioActivo !== null;

ob_start();
?>
<div class="container-fluid px-0">
  <!-- 1. ENCABEZADO DE PÁGINA -->
  <div class="page-header mb-4">
    <div>
      <span class="docente-badge-rol">DIRECCIÓN</span>
      <h1 class="page-header__title mb-1">¡<?= htmlspecialchars($saludo) ?>, <?= htmlspecialchars($nombreUsuario) ?>!</h1>
      <p class="page-header__subtitle mb-0">Visión estratégica de la institución: matrícula escolar, capacidad, personal y alertas operativas.</p>
    </div>
    <div class="page-header__actions d-flex align-items-center gap-2 flex-wrap">
      <?php if ($hayAnioActivo): ?>
        <?php
        $periodText = (string) $anioActivo['nombre'];
        $periodStatus = 'Activo';
        $periodIsActive = true;
        include __DIR__ . '/../../components/period-chip.php';
        ?>
      <?php else: ?>
        <span class="badge text-bg-danger">
          <i class="bi bi-calendar-x me-1" aria-hidden="true"></i> Sin año escolar activo
        </span>
      <?php endif; ?>
      <span class="badge text-bg-light border">
        <i class="bi bi-calendar3 me-1" aria-hidden="true"></i> <?= htmlspecialchars(date('d/m/Y')) ?>
      </span>
    </div>
  </div>

  <?php if (!$hayAnioActivo): ?>
    <div class="alert alert-warning d-flex align-items-center gap-3 mb-4" role="alert">
      <i class="bi bi-exclamation-triangle fs-4 flex-shrink-0" aria-hidden="true"></i>
      <div>
        <div class="fw-semibold">No hay un año escolar activo en el sistema</div>
        <p class="mb-0 small">
          Configure y active el período escolar en Configuración &rsaquo; Años Escolares para habilitar la matrícula y el registro académico.
        </p>
      </div>
    </div>
  <?php endif; ?>

  <!-- 2. TRES INDICADORES CLAVE (SOLO TOTALES) -->
  <?php include __DIR__ . '/partials/indicadores.php'; ?>

  <!-- 3. ATENCIÓN INMEDIATA (ALERTAS Y BOTONES DE ACCIÓN) -->
  <?php include __DIR__ . '/partials/atencion.php'; ?>

  <!-- 4. GRÁFICAS ANALÍTICAS (DESGLOSES VISUALES SIN REDUNDANCIA) -->
  <?php include __DIR__ . '/partials/graficas.php'; ?>
</div>
<?php
$pageContent = ob_get_clean();

include __DIR__ . '/../../layouts/app.php';
