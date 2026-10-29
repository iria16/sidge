<?php
declare(strict_types=1);

/**
 * Vista: dashboard/secretaria/index.php
 * Panel de Secretaría: resumen operativo del año escolar y turno asignado.
 *
 * Variables esperadas desde DashboardController::secretaria():
 * @var string      $saludo
 * @var string      $nombreUsuario
 * @var array|null  $anioActivo
 * @var string|null $turnoNombre
 * @var array|null  $estructura
 * @var array|null  $estadisticasEstudiantes
 * @var array|null  $estadisticasInscripciones
 * @var array|null  $estadisticasRatificacion
 * @var array|null  $estadisticasRetiros
 * @var array|null  $cuposPorEstructura
 */

$pageTitle    = 'Dashboard';
$currentNav   = 'dashboard';
$extraScripts = [
    '/js/vendor/chart.js',
    '/js/modules/dashboard/charts.js',
];

$breadcrumbs = [
    ['label' => 'Panel', 'href' => BASE_URL . 'dashboard/secretaria'],
    ['label' => 'Dashboard', 'href' => null],
];

$hayAnioActivo        = $anioActivo !== null;
$pendientesEstructura = $hayAnioActivo && $estructura !== null ? (int) ($estructura['pendientes'] ?? 0) : 0;

ob_start();
?>
<div class="container-fluid px-0">
  <!-- 1. ENCABEZADO DE PÁGINA -->
  <div class="page-header mb-4">
    <div>
      <span class="docente-badge-rol">SECRETARÍA</span>
      <h1 class="page-header__title mb-1">¡<?= htmlspecialchars($saludo) ?>, <?= htmlspecialchars($nombreUsuario) ?>!</h1>
      <p class="page-header__subtitle mb-0">Gestión operativa del año escolar: matrícula por turno, nuevos ingresos, ratificaciones y retiros.</p>
    </div>
    <div class="page-header__actions d-flex align-items-center gap-2 flex-wrap">
      <?php if ($hayAnioActivo): ?>
        <?php
        $periodText     = (string) $anioActivo['nombre'];
        $periodStatus   = 'Activo';
        $periodIsActive = true;
        include __DIR__ . '/../../components/period-chip.php';
        ?>
      <?php else: ?>
        <span class="badge text-bg-danger">
          <i class="bi bi-calendar-x me-1" aria-hidden="true"></i> Sin año escolar activo
        </span>
      <?php endif; ?>

      <span class="badge text-bg-light border">
        <i class="bi bi-clock me-1" aria-hidden="true"></i>
        <?= $turnoNombre !== null && $turnoNombre !== '' ? htmlspecialchars('Turno ' . $turnoNombre) : 'Sin turno asignado' ?>
      </span>

      <span class="badge text-bg-light border">
        <i class="bi bi-calendar3 me-1" aria-hidden="true"></i> <?= htmlspecialchars(date('d/m/Y')) ?>
      </span>
    </div>
  </div>

  <?php if (!$hayAnioActivo): ?>
    <div class="alert alert-danger d-flex align-items-start gap-3 mb-4" role="alert">
      <i class="bi bi-x-octagon fs-5 flex-shrink-0" aria-hidden="true"></i>
      <div>
        <div class="fw-semibold mb-1">No hay un año escolar abierto</div>
        <p class="mb-0 small">
          Mientras ningún período esté en curso no se pueden registrar inscripciones ni ratificaciones. Avise al directivo para que abra el período en Configuración &rsaquo; Años escolares.
        </p>
      </div>
    </div>
  <?php elseif ($pendientesEstructura > 0): ?>
    <div class="alert alert-warning d-flex align-items-start gap-3 mb-4" role="alert">
      <i class="bi bi-exclamation-triangle fs-5 flex-shrink-0" aria-hidden="true"></i>
      <div>
        <div class="fw-semibold mb-1">Estructura académica en configuración</div>
        <p class="mb-0 small">
          Faltan <?= $pendientesEstructura ?> combinaciones en <?= htmlspecialchars((string) ($estructura['anio'] ?? '')) ?>. La estructura la configura el Directivo.
        </p>
      </div>
    </div>
  <?php endif; ?>

  <!-- 2. TRES INDICADORES CLAVE (SOLO TOTALES) -->
  <?php include __DIR__ . '/partials/indicadores.php'; ?>

  <!-- 3. ATENCIÓN INMEDIATA (ALERTAS Y BOTONES DE ACCIÓN) -->
  <?php include __DIR__ . '/partials/atencion.php'; ?>

  <!-- 4. GRÁFICAS ANALÍTICAS (COMPOSICIÓN Y CUPOS POR GRADO) -->
  <?php include __DIR__ . '/partials/graficas.php'; ?>
</div>
<?php
$pageContent = ob_get_clean();

include __DIR__ . '/../../layouts/app.php';