<?php
declare(strict_types=1);

/**
 * Vista: dashboard/coordinador/index.php
 * Orquestador principal del Dashboard de Coordinación.
 * Estructura fija: Encabezado -> Stat-Cards -> Gráficas -> Atención Inmediata.
 *
 * Variables esperadas:
 * @var string $saludo
 * @var string $nombreUsuario
 * @var array|null $anioActivo
 * @var array $metricasMatricula
 * @var array $metricasCapacidad
 * @var array $metricasPersonal
 * @var array $distribucionGrados
 * @var array $avanceResultados
 * @var array $alertas
 */

$pageTitle   = 'Dashboard';
$currentNav  = 'dashboard';

$breadcrumbs = [
    ['label' => 'Panel', 'href' => BASE_URL . 'dashboard/coordinador'],
    ['label' => 'Dashboard', 'href' => null],
];

// Scripts requeridos: Chart.js 4.4.8 vendor y módulo compartido de inicialización
$extraScripts = [
    '/js/vendor/chart.js',
    '/js/modules/dashboard/charts.js',
];

$hayAnioActivo = $anioActivo !== null;

ob_start();
?>
<div class="container-fluid px-0">
  <!-- 1. ENCABEZADO -->
  <div class="page-header mb-4">
    <div>
      <span class="docente-badge-rol">COORDINACIÓN</span>
      <h1 class="page-header__title mb-1">¡<?= htmlspecialchars($saludo) ?>, <?= htmlspecialchars($nombreUsuario) ?>!</h1>
      <p class="page-header__subtitle mb-0">
        Supervisión pedagógica, cobertura docente y balance escolar del plantel.
      </p>
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
          Contacte a la Dirección para activar el período académico correspondiente y habilitar la estructura escolar.
        </p>
      </div>
    </div>
  <?php endif; ?>

  <!-- 2. STAT-CARDS (Solo totales agregados) -->
  <?php include __DIR__ . '/partials/indicadores.php'; ?>

  <!-- 3. GRÁFICAS ANALÍTICAS (Desgloses de composición y balance) -->
  <?php include __DIR__ . '/partials/graficas.php'; ?>

  <!-- 4. ATENCIÓN INMEDIATA (Alertas procesables por Coordinación) -->
  <?php include __DIR__ . '/partials/atencion.php'; ?>
</div>
<?php
$pageContent = ob_get_clean();

include __DIR__ . '/../../layouts/app.php';
