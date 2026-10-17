<?php

declare(strict_types=1);

/**
 * Vista: constancias/inscripcion.php
 * Listado de inscripciones para emisión de Constancia de Inscripción y visor de formato de prueba.
 *
 * @var array<int, array<string, mixed>> $inscripciones
 * @var int $total
 * @var array<int, array{id_anio_escolar: int, nombre_periodo: string}> $aniosEscolares
 * @var array<int, string> $grados
 * @var array<int, string> $secciones
 * @var array<string, string> $filters
 * @var array<string, int> $paginacion
 * @var bool $directorConfigurado
 * @var array{error: ?string, success: ?string} $flash
 */

$pageTitle       = 'Constancias de Inscripción';
$pageDescription = 'Emisión oficial de constancias de inscripción por período escolar.';
$currentNav      = 'constancia-inscripcion';

$breadcrumbs = [
    ['label' => 'Constancias', 'href' => null],
    ['label' => 'Constancia de Inscripción', 'href' => null],
];

ob_start();
?>

<div class="container-fluid px-0">

  <!-- Encabezado de página -->
  <?php
  ob_start();
  ?>
  <div class="d-flex align-items-center gap-2 flex-wrap">
    <a href="<?= BASE_URL ?>constancias/verPrueba?tipo=inscripcion"
       target="_blank"
       class="btn btn-primary d-inline-flex align-items-center gap-2 shadow-sm"
       id="btnVerPruebaInscripcion">
      <i class="bi bi-file-earmark-pdf fs-5" aria-hidden="true"></i>
      <span>Ver Formato de Prueba (PDF)</span>
    </a>
  </div>
  <?php
  $pageHeaderActions = ob_get_clean();
  $pageHeading       = 'Constancias de Inscripción';
  $pageSubheading    = 'Consulte los registros de inscripción por período escolar y emita la constancia oficial en formato Carta/Dompdf.';
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

  <!-- Panel de Datos: Tabla con filtros -->
  <?php
  // Filtros del Toolbar
  ob_start();
  ?>
  <label for="filtroAnio" class="visually-hidden">Período Escolar</label>
  <select id="filtroAnio" name="anio" class="form-select" aria-label="Filtrar por período">
    <option value="">Período Escolar (Todos)</option>
    <?php foreach ($aniosEscolares as $a): ?>
      <option value="<?= htmlspecialchars((string) $a['nombre_periodo']) ?>" <?= ($filters['anio'] ?? '') === $a['nombre_periodo'] ? 'selected' : '' ?>>
        <?= htmlspecialchars((string) $a['nombre_periodo']) ?>
      </option>
    <?php endforeach; ?>
  </select>

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
  $panelFormAction        = BASE_URL . 'constancias/inscripcion';
  $panelSearchName        = 'q';
  $panelSearchValue       = (string) ($filters['q'] ?? '');
  $panelSearchPlaceholder = 'Buscar por nombre o cédula...';

  // Thead
  ob_start();
  ?>
  <tr>
    <th scope="col" style="width: 28%;">Estudiante</th>
    <th scope="col" style="width: 15%;">Documento</th>
    <th scope="col" style="width: 15%;">Grado y Sección</th>
    <th scope="col" style="width: 14%;">Período Escolar</th>
    <th scope="col" style="width: 13%;">Tipo Matrícula</th>
    <th scope="col" class="text-center" style="width: 15%;">Acción</th>
  </tr>
  <?php
  $panelTableHead = ob_get_clean();

  // Tbody
  ob_start();
  if (empty($inscripciones)):
  ?>
    <tr>
      <td colspan="6" class="text-center py-4 text-muted">
        <i class="bi bi-inbox fs-2 d-block mb-2"></i>
        No se encontraron inscripciones que coincidan con los criterios de búsqueda.
      </td>
    </tr>
  <?php
  else:
    foreach ($inscripciones as $i):
      $tipoMat = (string) ($i['tipo_matricula'] ?? 'INSCRIPCION');
  ?>
    <tr>
      <td>
        <div class="fw-semibold text-dark">
          <?= htmlspecialchars((string) ($i['nombre_completo'] ?? '')) ?>
        </div>
        <div class="text-muted small">
          Nac: <?= htmlspecialchars((string) ($i['fecha_nacimiento'] ?? 'Sin fecha')) ?>
          <?php if (!empty($i['municipio_nacimiento'])): ?>
            &bull; <?= htmlspecialchars((string) $i['municipio_nacimiento']) ?>, <?= htmlspecialchars((string) $i['estado_nacimiento']) ?>
          <?php else: ?>
            <span class="text-warning fw-semibold">&bull; Falta lugar nacimiento</span>
          <?php endif; ?>
        </div>
      </td>
      <td class="text-support">
        <span class="badge bg-light text-dark border">
          <?= htmlspecialchars((string) ($i['tipo_documento'] ?? 'V')) ?>-<?= htmlspecialchars((string) ($i['numero_documento'] ?? '')) ?>
        </span>
      </td>
      <td class="text-support">
        <?= htmlspecialchars((string) ($i['grado'] ?? 'Sin grado')) ?>
        <div class="text-muted small">Sección <?= htmlspecialchars((string) ($i['seccion'] ?? '—')) ?></div>
      </td>
      <td class="text-support">
        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
          <?= htmlspecialchars((string) ($i['anio_escolar'] ?? '—')) ?>
        </span>
      </td>
      <td class="text-support small">
        <span class="badge bg-light text-dark border">
          <?= ($tipoMat === 'INSCRIPCION') ? 'Primer Ingreso' : 'Continuidad' ?>
        </span>
      </td>
        <?php /* Formulario y no enlace. `constancias/emitir` genera un documento,
               así que public/index.php la exige por POST: un enlace se puede
               poner en cualquier href y disparar la generación sin que nadie lo
               haya pedido. La pantalla de estudios ya lo hacía así; esta se
               iguala. El target="_blank" conserva el comportamiento anterior:
               el PDF se abre en otra pestaña. */ ?>
        <form method="POST" action="<?= BASE_URL ?>constancias/emitir" target="_blank" class="d-inline m-0 p-0">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars((string) ($_SESSION['csrf'] ?? '')) ?>">
          <input type="hidden" name="tipo" value="inscripcion">
          <input type="hidden" name="id" value="<?= (int) $i['id_estudiante'] ?>">
          <input type="hidden" name="id_matricula" value="<?= (int) $i['id_matricula'] ?>">
          <button type="submit"
                  class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1 shadow-sm"
                  title="Emitir Constancia de Inscripción en PDF">
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
      'Mostrando %d a %d de %d inscripciones',
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
