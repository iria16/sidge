<?php
declare(strict_types=1);

/**
 * Vista: resultado_academico/index.php
 * Bandeja de secciones y avance de literales del año escolar.
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

$esDocente  = ($rolUsuario === 'docente');
$pageTitle  = $esDocente ? 'Mi sección · Resultados y promoción' : 'Promoción académica anual';
$currentNav = $esDocente ? 'mi-seccion-promocion' : 'promocion';

$breadcrumbs = $esDocente
    ? [
        ['label' => 'Mi sección', 'href' => null],
        ['label' => 'Resultados y promoción', 'href' => null],
    ]
    : [
        ['label' => 'Gestión de estudiantes', 'href' => null],
        ['label' => 'Promoción académica', 'href' => null],
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
    <?php if ($esDocente): ?>
      <span class="status-badge status-badge--active py-2 px-3">
        <i class="bi bi-calendar-check me-1" aria-hidden="true"></i>
        Período activo: <strong><?= htmlspecialchars($nombrePeriodo) ?></strong>
      </span>
    <?php else: ?>
      <form method="GET" action="<?= BASE_URL ?>resultadoAcademico/index" class="d-flex align-items-center gap-2 m-0">
        <label for="filtroAnio" class="visually-hidden">Período escolar</label>
        <select id="filtroAnio" name="anio" class="form-select form-select-sm">
          <?php foreach ($anios as $a): ?>
            <?php
            $idA      = (int) ($a['id_anio_escolar'] ?? $a['id'] ?? 0);
            $nombreA  = (string) ($a['nombre_periodo'] ?? $a['nombre'] ?? '');
            $estadoA  = (string) ($a['estado'] ?? '');
            $esActivo = $estadoA === 'en_curso' || strtoupper($estadoA) === 'ACTIVO';
            ?>
            <option value="<?= $idA ?>" <?= $idA === $idAnio ? 'selected' : '' ?>>
              <?= htmlspecialchars($nombreA) ?><?= $esActivo ? ' · Activo' : ' (' . htmlspecialchars($estadoA) . ')' ?>
            </option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-outline-primary btn-sm">Ver</button>
      </form>
    <?php endif; ?>

    <button type="button" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1"
            data-bs-toggle="modal" data-bs-target="#modalGuiaNormativa">
      <i class="bi bi-info-circle" aria-hidden="true"></i>
      <span>Criterios de promoción</span>
    </button>
  </div>
  <?php
  $pageHeaderActions = ob_get_clean();
  $pageHeading       = $pageTitle;
  $pageSubheading    = $esDocente
      ? 'Registre el literal de fin de ciclo de su sección. El estado de promoción se calcula al guardar.'
      : 'Seguimiento de literales cargados, pendientes y resultados por sección.';
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
          <?= $esDocente ? 'Mis secciones asignadas' : 'Aulas y secciones del plantel' ?>
        </h2>
        <span class="text-secondary small"><?= $totalSecciones ?> sección<?= $totalSecciones === 1 ? '' : 'es' ?></span>
      </div>

      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 resultado-secciones-table">
          <thead>
            <tr>
              <th scope="col"><?= $esDocente ? 'Sección' : 'Grado y sección' ?></th>
              <th scope="col">Turno</th>
              <?php if (!$esDocente): ?>
                <th scope="col">Docente</th>
              <?php endif; ?>
              <th scope="col" class="text-center text-nowrap">Matrícula</th>
              <th scope="col" class="text-center text-nowrap">Cargados</th>
              <th scope="col" class="text-center text-nowrap">Pendientes</th>
              <th scope="col" class="text-center text-nowrap">Promovidos</th>
              <th scope="col" class="text-center text-nowrap">No prom.</th>
              <th scope="col">Estado</th>
              <th scope="col" class="text-end">Acción</th>
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
        <?php if ($esDocente): ?>
          <h3 class="content-card__title mb-2 fs-5">No tiene una sección asignada</h3>
          <p class="text-secondary mb-3">
            Su usuario tiene el rol de docente, pero no figura como titular de una sección
            en el período <strong><?= htmlspecialchars($nombrePeriodo) ?></strong>.
          </p>
          <p class="text-secondary mb-0">
            Solicite a la Dirección o Coordinación que registre su asignación docente.
          </p>
        <?php else: ?>
          <h3 class="content-card__title mb-2 fs-5">No hay secciones para este período</h3>
          <p class="text-secondary mb-3">
            No se encontraron estructuras académicas en el período seleccionado.
          </p>
          <a href="<?= BASE_URL ?>academicStructure/index" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle me-1" aria-hidden="true"></i> Configurar estructura académica
          </a>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php
$pageContent = ob_get_clean();

ob_start();
include __DIR__ . '/modals/guia-modal.php';
$pageModals = ob_get_clean();

$extraScripts = ['/js/modules/resultado_academico/index.js'];

include __DIR__ . '/../layouts/app.php';
