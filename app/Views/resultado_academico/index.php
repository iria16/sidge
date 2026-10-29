<?php
declare(strict_types=1);

/**
 * Vista: resultado_academico/index.php
 * Bandeja de secciones del docente y avance de literales.
 *
 * @var array  $secciones
 * @var array  $anios
 * @var int    $idAnio
 * @var string $nombreAnioActivo
 * @var string $nombreUsuarioActual
 * @var string $rolUsuario
 * @var string|null $flash_error
 * @var string|null $flash_success
 */

$pageTitle  = 'Mi sección · Resultados y promoción';
$currentNav = 'mi-seccion-promocion';

$breadcrumbs = [
    ['label' => 'Mi sección', 'href' => null],
    ['label' => 'Resultados y promoción', 'href' => null],
];

$resumen        = \App\Support\ResultadoAcademicoVista::resumenSecciones($secciones);
$seccionesFilas = $resumen['filas'];
$totalSecciones = $resumen['total_secciones'];
$nombrePeriodo  = $nombreAnioActivo !== '' ? $nombreAnioActivo : 'En curso';

ob_start();
?>
<div class="container-fluid px-0">
  <?php
  ob_start();
  ?>
  <div class="d-flex align-items-center gap-2 flex-wrap">
      <span class="status-badge status-badge--active py-2 px-3">
        <i class="bi bi-calendar-check me-1" aria-hidden="true"></i>
        Período activo: <strong><?= htmlspecialchars($nombrePeriodo) ?></strong>
      </span>

    <button type="button" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1"
            data-bs-toggle="modal" data-bs-target="#modalGuiaNormativa">
      <i class="bi bi-info-circle" aria-hidden="true"></i>
      <span>Criterios de promoción</span>
    </button>
  </div>
  <?php
  $pageHeaderActions = ob_get_clean();
  $pageHeading       = $pageTitle;
  $pageSubheading    = 'Registre el literal de fin de ciclo de su sección. El estado de promoción se calcula al guardar.';
  include __DIR__ . '/../components/page-header.php';
  ?>

  <?php if ($flash_error !== null): ?>
    <div class="alert alert-danger" role="alert"><?= htmlspecialchars($flash_error) ?></div>
  <?php endif; ?>
  <?php if ($flash_success !== null): ?>
    <div class="alert alert-success" role="alert"><?= htmlspecialchars($flash_success) ?></div>
  <?php endif; ?>

  <?php if ($totalSecciones > 0): ?>
    <?php include __DIR__ . '/partials/resumen-cards.php'; ?>

    <div class="content-card">
      <div class="content-card__header">
        <h2 class="content-card__title">
          <i class="bi bi-mortarboard me-2 text-primary" aria-hidden="true"></i>
          Mis secciones asignadas
        </h2>
        <span class="text-secondary small"><?= $totalSecciones ?> sección<?= $totalSecciones === 1 ? '' : 'es' ?></span>
      </div>

      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 resultado-secciones-table">
          <thead>
            <tr>
              <th scope="col">Sección</th>
              <th scope="col">Turno</th>
              <th scope="col">Progreso de evaluación</th>
              <th scope="col" class="text-center">Estado</th>
              <th scope="col" class="text-end">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php include __DIR__ . '/partials/secciones-rows.php'; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php else: ?>
    <div class="content-card">
      <div class="content-card__empty">
        <i class="bi bi-mortarboard" aria-hidden="true"></i>
        <h3 class="content-card__title mb-2 fs-5">No tiene una sección asignada</h3>
        <p class="text-secondary mb-3">
          Su usuario tiene el rol de docente, pero no figura como titular de una sección
          en el período <strong><?= htmlspecialchars($nombrePeriodo) ?></strong>.
        </p>
        <p class="text-secondary mb-0">
          Solicite a la Dirección o Coordinación que registre su asignación docente.
        </p>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php
$pageContent = ob_get_clean();

ob_start();
include __DIR__ . '/modals/guia-modal.php';
include __DIR__ . '/modals/preview-modal.php';
$pageModals = ob_get_clean();

$extraScripts = ['/js/modules/resultado_academico/index.js'];

include __DIR__ . '/../layouts/app.php';
