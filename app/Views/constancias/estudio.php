<?php

declare(strict_types=1);

/**
 * Vista: constancias/estudio.php
 * Listado de estudiantes para emisión de Constancia de Estudio y visor de formatos de prueba.
 *
 * @var array<int, array<string, mixed>> $estudiantes
 * @var int $total
 * @var array<string, int> $stats
 * @var array<int, string> $grados
 * @var array<int, string> $secciones
 * @var array<string, string> $filters
 * @var array<string, int> $paginacion
 * @var bool $directorConfigurado
 * @var array{error: ?string, success: ?string} $flash
 */

$pageTitle       = 'Constancias de Estudio';
$pageDescription = 'Emisión oficial de constancias de estudio y previsualización de plantillas aprobadas.';
$currentNav      = 'constancia-estudio';

$breadcrumbs = [
    ['label' => 'Constancias', 'href' => null],
    ['label' => 'Constancia de Estudio', 'href' => null],
];

ob_start();
?>

<div class="container-fluid px-0">

  <!-- Encabezado de página -->
  <?php
  ob_start();
  ?>
  <?php /* Sin acciones en el encabezado: el visor de formato de prueba y su
         desplegable de variantes se retiraron. */
  $pageHeaderActions = '';
  $pageHeading       = 'Constancias de Estudio';
  $pageSubheading    = 'Consulte los expedientes del alumnado y emita la constancia oficial de estudio en formato Carta/Dompdf.';
  include __DIR__ . '/../components/page-header.php';
  ?>

  <!-- Notificaciones Flash -->
  <?php if (!empty($flash['error'])): ?>
    <div class="alert alert-danger d-flex align-items-center gap-3 shadow-sm mb-4" role="alert">
      <i class="bi bi-exclamation-octagon-fill fs-4 flex-shrink-0" aria-hidden="true"></i>
      <div>
        <h6 class="alert-heading fw-bold mb-1">No se pudo emitir la constancia</h6>
        <p class="mb-0 small"><?= htmlspecialchars($flash['error']) ?></p>
      </div>
    </div>
  <?php endif; ?>

  <?php if (!empty($flash['success'])): ?>
    <div class="alert alert-success d-flex align-items-center gap-3 shadow-sm mb-4" role="alert">
      <i class="bi bi-check-circle-fill fs-4 flex-shrink-0" aria-hidden="true"></i>
      <div><?= htmlspecialchars($flash['success']) ?></div>
    </div>
  <?php endif; ?>

  <!-- Alerta si falta configurar directivo -->
  <?php if (!$directorConfigurado): ?>
    <div class="alert alert-warning d-flex align-items-center gap-3 shadow-sm mb-4" role="alert">
      <i class="bi bi-exclamation-triangle-fill fs-3 text-warning flex-shrink-0" aria-hidden="true"></i>
      <div>
        <h6 class="alert-heading fw-bold mb-1">Autoridad Directiva Incompleta</h6>
        <p class="mb-0 small text-dark">
          Falta el nombre, cédula o cargo del director(a) en <a href="<?= BASE_URL ?>configuracionInstitucional/index" class="fw-bold text-dark text-decoration-underline">Datos Institucionales</a>.
          La emisión de documentos oficiales requiere obligatoriamente estos datos antes de su descarga.
        </p>
      </div>
    </div>
  <?php endif; ?>

  <!-- Tarjetas de Estadísticas -->
  <div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
      <?php
      $statLabel   = 'Total Estudiantes';
      $statValue   = (string) ($stats['total'] ?? 0);
      $statIcon    = 'bi-people';
      $statTone    = 'primary';
      include __DIR__ . '/../components/stat-card.php';
      ?>
    </div>
  </div>

  <!-- Panel de Datos: Tabla con filtros -->
  <?php
  // Filtros del Toolbar
  ob_start();
  ?>
  <label for="filtroGrado" class="visually-hidden">Grado</label>
  <select id="filtroGrado" name="grado" class="form-select" aria-label="Filtrar por grado">
    <option value="">Grado (Todos)</option>
    <?php foreach ($grados as $g): ?>
      <option value="<?= htmlspecialchars($g) ?>" <?= ($filters['grado'] ?? '') === $g ? 'selected' : '' ?>>
        <?= htmlspecialchars($g) ?>
      </option>
    <?php endforeach; ?>
  </select>

  <label for="filtroSeccion" class="visually-hidden">Sección</label>
  <select id="filtroSeccion" name="seccion" class="form-select" aria-label="Filtrar por sección">
    <option value="">Sección (Todas)</option>
    <?php foreach ($secciones as $s): ?>
      <option value="<?= htmlspecialchars($s) ?>" <?= ($filters['seccion'] ?? '') === $s ? 'selected' : '' ?>>
        <?= htmlspecialchars($s) ?>
      </option>
    <?php endforeach; ?>
  </select>
  <?php
  $panelFilters           = ob_get_clean();
  $panelFormAction        = BASE_URL . 'constancias/estudio';
  $panelSearchName        = 'q';
  $panelSearchValue       = (string) ($filters['q'] ?? '');
  $panelSearchPlaceholder = 'Buscar por nombre o cédula...';

  // Thead
  ob_start();
  ?>
  <tr>
    <th scope="col" style="width: 30%;">Estudiante</th>
    <th scope="col" style="width: 15%;">Documento</th>
    <th scope="col" style="width: 15%;">Grado y Sección</th>
    <th scope="col" style="width: 12%;">Período</th>
    <th scope="col" class="text-center" style="width: 13%;">Estado</th>
    <th scope="col" class="text-center" style="width: 15%;">Acción</th>
  </tr>
  <?php
  $panelTableHead = ob_get_clean();

  // Tbody
  ob_start();
  if (empty($estudiantes)):
  ?>
    <tr>
      <td colspan="6" class="text-center py-4 text-muted">
        <i class="bi bi-inbox fs-2 d-block mb-2"></i>
        No se encontraron estudiantes que coincidan con los criterios de búsqueda.
      </td>
    </tr>
  <?php
  else:
    foreach ($estudiantes as $e):
      $esActivo = ($e['estado_academico'] ?? 'activo') === 'activo';
      $estadoAcad = (string) ($e['estado_academico'] ?? 'activo');
  ?>
    <tr class="<?= !$esActivo ? 'is-muted' : '' ?>">
      <td>
        <div class="fw-semibold text-dark">
          <?= htmlspecialchars((string) ($e['nombre_completo'] ?? '')) ?>
        </div>
        <div class="text-muted small">
          Nac: <?= htmlspecialchars((string) ($e['fecha_nacimiento'] ?? 'Sin fecha')) ?>
          <?php if (!empty($e['municipio_nacimiento'])): ?>
            &bull; <?= htmlspecialchars((string) $e['municipio_nacimiento']) ?>, <?= htmlspecialchars((string) $e['estado_nacimiento']) ?>
          <?php else: ?>
            <span class="text-warning fw-semibold">&bull; Falta lugar nacimiento</span>
          <?php endif; ?>
        </div>
      </td>
      <td class="text-support">
        <span class="badge bg-light text-dark border">
          <?= htmlspecialchars((string) ($e['tipo_documento'] ?? 'V')) ?>-<?= htmlspecialchars((string) ($e['numero_documento'] ?? '')) ?>
        </span>
      </td>
      <td class="text-support">
        <?= htmlspecialchars((string) ($e['grado'] ?? 'Sin grado')) ?>
        <div class="text-muted small">Sección <?= htmlspecialchars((string) ($e['seccion'] ?? '—')) ?></div>
      </td>
      <td class="text-support small">
        <?= htmlspecialchars((string) ($e['anio_escolar'] ?? '—')) ?>
      </td>
      <td class="text-center">
        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">Activo</span>
      </td>
      <td class="text-center">
        <form method="POST" action="<?= BASE_URL ?>constancias/emitir" target="_blank" class="d-inline m-0 p-0">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars((string) ($_SESSION['csrf'] ?? '')) ?>">
          <input type="hidden" name="tipo" value="estudio">
          <input type="hidden" name="id" value="<?= (int) $e['id_estudiante'] ?>">
          <button type="submit"
                  class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1 shadow-sm"
                  title="Emitir Constancia de Estudio en PDF">
            <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i>
            <span>Emitir</span>
          </button>
        </form>
      </td>
    </tr>
  <?php
    endforeach;
  endif;
  $panelTableBody = ob_get_clean();

  $panelSummary = sprintf(
      'Mostrando %d a %d de %d estudiantes',
      $paginacion['desde'],
      $paginacion['hasta'],
      $paginacion['total']
  );
  $panelPagination = [
      'pagina_actual' => $paginacion['pagina_actual'],
      'total_paginas' => $paginacion['total_paginas'],
  ];

  include __DIR__ . '/../components/data-panel.php';
  ?>

</div>

<?php
$pageContent = ob_get_clean();
include __DIR__ . '/../layouts/app.php';
