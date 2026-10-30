<?php
declare(strict_types=1);

/**
 * Vista: dashboard/docente/index.php
 * Panel del Docente: visión de aula, métricas pedagógicas, cumpleaños y alertas de evaluación.
 *
 * Variables esperadas desde TeacherDashboardController::index():
 * @var string     $nombreDocente
 * @var string     $saludo
 * @var array|null $seccion
 * @var bool       $tieneSeccion
 * @var array      $resumen
 * @var array      $distribucion
 * @var array      $cumpleanos
 * @var array      $literales
 * @var array      $ingresos
 * @var bool       $mostrarLiterales
 */

$pageTitle    = 'Dashboard Docente';
$currentNav   = 'dashboard';
$extraScripts = [
    '/js/vendor/chart.js',
    '/js/modules/dashboard/charts.js',
];

$breadcrumbs = [
    ['label' => 'Panel', 'href' => BASE_URL . 'dashboard/docente'],
    ['label' => 'Dashboard', 'href' => null],
];

ob_start();
?>
<div class="container-fluid px-0">
  <!-- 1. ENCABEZADO DE PÁGINA -->
  <div class="page-header mb-4">
    <div>
      <span class="docente-badge-rol">DOCENTE DE AULA TITULAR</span>
      <h1 class="page-header__title mb-1">¡<?= htmlspecialchars($saludo ?? 'Buenos días') ?>, Prof. <?= htmlspecialchars($nombreDocente) ?>!</h1>
      <?php if ($tieneSeccion): ?>
        <p class="page-header__subtitle d-flex align-items-center gap-2 flex-wrap mb-0">
          <span><?= htmlspecialchars($seccion['grado'] ?? '') ?> "<?= htmlspecialchars($seccion['seccion'] ?? '') ?>" · Turno <?= htmlspecialchars($seccion['turno'] ?? '') ?></span>
          <?php if (!empty($seccion['anio_escolar'])): ?>
            <span class="text-muted">|</span>
            <?php
            $periodText     = 'Período ' . (string) $seccion['anio_escolar'];
            $periodStatus   = 'Activo';
            $periodIsActive = true;
            include __DIR__ . '/../../components/period-chip.php';
            ?>
          <?php endif; ?>
        </p>
      <?php else: ?>
        <p class="page-header__subtitle text-warning mb-0">Sin sección asignada en el período escolar activo</p>
      <?php endif; ?>
    </div>
    <div class="page-header__actions d-flex align-items-center gap-2 flex-wrap">
      <span class="badge text-bg-light border">
        <i class="bi bi-calendar3 me-1" aria-hidden="true"></i> <?= htmlspecialchars(date('d/m/Y')) ?>
      </span>
    </div>
  </div>

  <?php if (!$tieneSeccion): ?>
    <!-- Estado sin asignación de aula -->
    <div class="alert alert-warning d-flex align-items-start gap-3 shadow-sm border-0 p-4 mb-4" role="status">
      <i class="bi bi-exclamation-triangle fs-3 flex-shrink-0 text-warning" aria-hidden="true"></i>
      <div>
        <h2 class="h5 fw-bold mb-1 text-dark">No tienes una sección asignada actualmente</h2>
        <p class="text-dark mb-2">
          Para consultar las métricas de tu aula, la nómina de estudiantes y registrar literales finales,
          la Dirección debe asignarte una sección en el período escolar activo.
        </p>
        <span class="small text-muted">Si consideras que se trata de un error, por favor contacta al departamento de coordinación o dirección.</span>
      </div>
    </div>
  <?php else: ?>
    <!-- 2. TRES INDICADORES CLAVE (SOLO TOTALES) -->
    <?php include __DIR__ . '/partials/indicadores.php'; ?>

    <!-- 3. ATENCIÓN INMEDIATA (ALERTAS Y BOTONES DE ACCIÓN) -->
    <?php include __DIR__ . '/partials/atencion.php'; ?>

    <!-- 4. GRÁFICAS ANALÍTICAS (DEMOGRAFÍA Y EDADES) -->
    <?php include __DIR__ . '/partials/graficas.php'; ?>

    <!-- 5. CLIMA ESCOLAR Y CUMPLEAÑOS -->
    <?php include __DIR__ . '/partials/cumpleanos.php'; ?>
  <?php endif; ?>
</div>
<?php
$pageContent = ob_get_clean();

include __DIR__ . '/../../layouts/app.php';