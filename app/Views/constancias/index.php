<?php

declare(strict_types=1);

/**
 * Vista: constancias/index.php
 * Interfaz unificada de emisión oficial de constancias con selector de tipo:
 * - Estudio
 * - Inscripción / Ratificación
 * - Prosecución
 * - Retiro
 *
 * @var string $tipo
 * @var array<string, string> $tiposDisponibles
 * @var array<int, array<string, mixed>> $estudiantes
 * @var int $total
 * @var array<int, string> $grados
 * @var array<int, string> $secciones
 * @var array<string, string> $filters
 * @var array<string, int> $paginacion
 * @var bool $directorConfigurado
 * @var array{error: ?string, success: ?string} $flash
 */

$pageTitle       = 'Constancias';
$pageDescription = 'Emisión oficial de constancias y certificaciones escolares.';
$currentNav      = 'constancias';

$breadcrumbs = [
    ['label' => 'Constancias', 'href' => null],
];

ob_start();
?>

<div class="container-fluid px-0">

  <!-- Encabezado de página -->
  <?php
  $pageHeaderActions = '';
  if ($tipo === 'egreso' && $total > 0) {
      $paramsLote = array_filter([
          'seccion' => $filters['seccion'] ?? '',
          'q'       => $filters['q'] ?? '',
      ], static fn ($v): bool => (string) $v !== '');
      $qsLote          = $paramsLote !== [] ? ('?' . http_build_query($paramsLote)) : '';
      $qsLotePreview   = '?' . http_build_query(array_merge(['lote' => 1, 'tipo' => 'egreso'], $paramsLote));
      $urlLotePreview  = BASE_URL . 'constancias/verPrueba' . $qsLotePreview;
      $urlLoteEmitir   = BASE_URL . 'constancias/emitirLote' . $qsLote;
      $subtituloLote   = !empty($filters['seccion'])
          ? '6.° Grado · Sección ' . $filters['seccion'] . ' (' . $total . ' estudiante' . ($total === 1 ? '' : 's') . ')'
          : '6.° Grado · Todas las secciones (' . $total . ' estudiante' . ($total === 1 ? '' : 's') . ')';

      ob_start();
      ?>
      <button type="button"
              id="btnCertificadosLote"
              class="btn btn-primary d-inline-flex align-items-center gap-1 shadow-sm btn-previsualizar-constancia"
              data-url-preview="<?= htmlspecialchars($urlLotePreview) ?>"
              data-url-emitir="<?= htmlspecialchars($urlLoteEmitir) ?>"
              data-estudiante="<?= htmlspecialchars($subtituloLote) ?>"
              data-tipo="Certificados de Educación Primaria (Lote)">
        <i class="bi bi-printer-fill" aria-hidden="true"></i>
        <span>Emitir por Lote</span>
      </button>
      <?php
      $pageHeaderActions = ob_get_clean();
  }
  $pageHeading       = 'Emisión de Constancias';
  $pageSubheading    = 'Seleccione el tipo de constancia, consulte los estudiantes elegibles y emita el documento oficial en formato PDF.';
  include __DIR__ . '/../components/page-header.php';
  ?>

  <?php include __DIR__ . '/partials/alertas.php'; ?>

  <!-- Chips de tipo de constancia (mismo componente y diseño de docente/nomina) -->
  <nav class="nomina-chips mb-3" aria-label="Tipo de constancia">
    <?php foreach ($tiposDisponibles as $key => $label): 
      $esActivo = ($tipo === $key);
      $icono = match($key) {
        'estudio'     => 'bi-file-earmark-text',
        'inscripcion' => 'bi-person-check',
        'prosecucion' => 'bi-award',
        'retiro'      => 'bi-box-arrow-right',
        'egreso'      => 'bi-mortarboard-fill',
        default       => 'bi-file-earmark-pdf',
      };
      $textoChip = ($key === 'egreso') ? 'Certificado de ' . $label : 'Constancia de ' . $label;
    ?>
      <a class="nomina-chip <?= $esActivo ? 'is-active' : '' ?>"
         href="<?= BASE_URL ?>constancias/index?tipo=<?= urlencode($key) ?>"
         <?= $esActivo ? 'aria-current="page"' : '' ?>>
        <i class="bi <?= $icono ?> me-1" aria-hidden="true"></i><?= htmlspecialchars($textoChip) ?>
      </a>
    <?php endforeach; ?>
  </nav>

  <!-- Panel de Datos: Tabla con filtros -->
  <?php
  // Filtros del Toolbar
  ob_start();
  ?>
  <!-- Preservar el tipo de constancia seleccionado al filtrar o buscar -->
  <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo) ?>">

  <?php if ($tipo !== 'egreso'): ?>
    <label for="filtroGrado" class="visually-hidden">Grado</label>
    <select id="filtroGrado" name="grado" class="form-select" aria-label="Filtrar por grado" onchange="this.form.submit()">
      <option value="">Todos los grados</option>
      <?php foreach ($grados as $g): ?>
        <option value="<?= htmlspecialchars($g) ?>" <?= ($filters['grado'] ?? '') === $g ? 'selected' : '' ?>>
          <?= htmlspecialchars($g) ?>
        </option>
      <?php endforeach; ?>
    </select>
  <?php endif; ?>

  <label for="filtroSeccion" class="visually-hidden">Sección</label>
  <select id="filtroSeccion" name="seccion" class="form-select" aria-label="Filtrar por sección" onchange="this.form.submit()">
    <option value="">Todas las secciones</option>
    <?php foreach ($secciones as $s): ?>
      <option value="<?= htmlspecialchars($s) ?>" <?= ($filters['seccion'] ?? '') === $s ? 'selected' : '' ?>>
        <?= htmlspecialchars($s) ?>
      </option>
    <?php endforeach; ?>
  </select>
  <?php
  $panelFilters           = ob_get_clean();
  $panelFormAction        = BASE_URL . 'constancias/index';
  $panelSearchName        = 'q';
  $panelSearchValue       = (string) ($filters['q'] ?? '');
  $panelSearchPlaceholder = 'Buscar por nombre o cédula...';

  // Colgroup para fijar la proporción exacta de columnas y evitar colisiones
  ob_start();
  ?>
  <colgroup>
    <col class="ancho-30">
    <col class="ancho-16">
    <col class="ancho-22">
    <col class="ancho-18">
    <col class="ancho-14">
  </colgroup>
  <?php
  $panelColgroup = ob_get_clean();

  // Thead adaptativo según el tipo de constancia
  ob_start();
  ?>
  <tr>
    <th scope="col" class="ancho-30">Estudiante</th>
    <th scope="col" class="ancho-16">Documento</th>
    <th scope="col" class="ancho-22">
      <?= $tipo === 'prosecucion' ? 'Grado Cursado' : 'Grado y Sección' ?>
    </th>
    <th scope="col" class="ancho-18">
      <?php if ($tipo === 'inscripcion'): ?>
        Tipo Matrícula
      <?php elseif ($tipo === 'prosecucion'): ?>
        Promoción
      <?php elseif ($tipo === 'egreso'): ?>
        Culminación
      <?php elseif ($tipo === 'retiro'): ?>
        Representante
      <?php else: ?>
        Condición
      <?php endif; ?>
    </th>
    <th scope="col" class="text-center ancho-14">Acción</th>
  </tr>
  <?php
  $panelTableHead = ob_get_clean();

  // Tbody
  ob_start();
  if (empty($estudiantes)):
  ?>
    <tr>
      <td colspan="5" class="text-center py-4 text-muted">
        <i class="bi bi-inbox fs-2 d-block mb-2"></i>
        No se encontraron estudiantes elegibles para <?= ($tipo === 'egreso') ? 'Certificado de ' : 'Constancia de ' ?><?= htmlspecialchars($tiposDisponibles[$tipo] ?? 'Estudio') ?> con los filtros seleccionados.
      </td>
    </tr>
  <?php
  else:
    foreach ($estudiantes as $e):
      $idEmision = (int) ($e['id_registro'] ?? $e['id_estudiante']);
      $docTipo   = (string) ($e['tipo_documento'] ?? 'V');
      $docNum    = (string) ($e['numero_documento'] ?? '');
      $urlPreview = BASE_URL . 'constancias/verPrueba?tipo=' . urlencode($tipo) . '&id=' . $idEmision;
      if ($tipo === 'inscripcion' && !empty($e['id_matricula'])) {
          $urlPreview .= '&id_matricula=' . (int) $e['id_matricula'];
      }
      $urlEmitir = BASE_URL . 'constancias/emitir?tipo=' . urlencode($tipo) . '&id=' . $idEmision;
      if ($tipo === 'inscripcion' && !empty($e['id_matricula'])) {
          $urlEmitir .= '&id_matricula=' . (int) $e['id_matricula'];
      }
      $tipoLabel   = ($tipo === 'egreso') ? 'Educación Primaria' : ($tiposDisponibles[$tipo] ?? 'Estudio');
      $tipoTitulo  = ($tipo === 'egreso') ? 'Certificado de ' . $tipoLabel : 'Constancia de ' . $tipoLabel;
  ?>
    <tr>
      <!-- Columna 1: Estudiante -->
      <td>
        <div class="fw-semibold text-dark text-break">
          <?= htmlspecialchars((string) ($e['nombre_completo'] ?? '')) ?>
        </div>
      </td>

      <!-- Columna 2: Documento -->
      <td class="text-support">
        <span class="badge bg-light text-dark border">
          <?= htmlspecialchars($docTipo) ?>-<?= htmlspecialchars($docNum) ?>
        </span>
      </td>

      <!-- Columna 3: Grado y Sección -->
      <td class="text-support">
        <?php if ($tipo === 'prosecucion'): ?>
          <div class="fw-medium text-dark text-break"><?= htmlspecialchars((string) ($e['grado_origen'] ?? $e['grado'] ?? 'Sin grado')) ?></div>
        <?php else: ?>
          <div class="fw-medium text-dark text-break"><?= htmlspecialchars((string) ($e['grado'] ?? 'Sin grado')) ?></div>
        <?php endif; ?>
        <div class="text-muted small">Sección <?= htmlspecialchars((string) ($e['seccion'] ?? '—')) ?></div>
      </td>

      <!-- Columna 4: Estado / Metadato según tipo -->
      <td class="text-support">
        <?php if ($tipo === 'inscripcion'): ?>
          <?php
          $tipoMat = (string) ($e['tipo_matricula'] ?? 'INSCRIPCION');
          $etiquetaMatricula = match (strtoupper($tipoMat)) {
              'INSCRIPCION' => 'Primer Ingreso',
              'REINGRESO'   => 'Reingreso',
              default       => 'Continuidad',
          };
          ?>
          <span class="badge bg-light text-dark border">
            <?= htmlspecialchars($etiquetaMatricula) ?>
          </span>
        <?php elseif ($tipo === 'prosecucion'): ?>
          <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">Literal <?= htmlspecialchars((string) ($e['literal'] ?? 'A')) ?></span>
          <div class="text-muted small mt-1 text-break">
            Promovido a: <?= htmlspecialchars((string) ($e['grado_destino'] ?: 'Grado siguiente')) ?>
          </div>
        <?php elseif ($tipo === 'egreso'): ?>
          <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">Literal <?= htmlspecialchars((string) ($e['literal'] ?? 'A')) ?></span>
          <div class="text-muted small mt-1 text-break">
            1er Año Educación Media
          </div>
        <?php elseif ($tipo === 'retiro'): ?>
          <div class="text-dark small fw-medium text-break">
            <?= htmlspecialchars((string) ($e['representante_nombre'] ?: 'Representante')) ?>
          </div>
          <?php if (!empty($e['representante_cedula'])): ?>
            <div class="text-muted small"><?= htmlspecialchars((string) $e['representante_cedula']) ?></div>
          <?php endif; ?>
        <?php else: ?>
          <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">Activo</span>
        <?php endif; ?>
      </td>

      <!-- Columna 5: Acción (Visualizar primero antes de descargar) -->
      <td class="text-center">
        <button type="button"
                class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1 shadow-sm btn-previsualizar-constancia"
                data-url-preview="<?= htmlspecialchars($urlPreview) ?>"
                data-url-emitir="<?= htmlspecialchars($urlEmitir) ?>"
                data-estudiante="<?= htmlspecialchars((string) ($e['nombre_completo'] ?? '')) ?>"
                data-tipo="<?= htmlspecialchars($tipoTitulo) ?>"
                title="Visualizar <?= htmlspecialchars($tipoTitulo) ?>">
          <i class="bi bi-eye" aria-hidden="true"></i>
          <span>Visualizar</span>
        </button>
      </td>
    </tr>
  <?php
    endforeach;
  endif;
  $panelTableBody = ob_get_clean();

  $panelSummary = 'Mostrando ' . (int) $paginacion['desde'] . ' a ' . (int) $paginacion['hasta']
                . ' de ' . (int) $paginacion['total'] . ' registros';
  $panelPagination = [
      'pagina_actual' => $paginacion['pagina_actual'],
      'total_paginas' => $paginacion['total_paginas'],
  ];

  include __DIR__ . '/../components/data-panel.php';
  ?>

</div>

<?php
$pageContent = ob_get_clean();

// ---------- Modales (uno por archivo, fuera del contenido) ----------
ob_start();
include __DIR__ . '/modals/preview-modal.php';
$pageModals = ob_get_clean();

// ---------- Scripts ----------
$extraScripts = ['/js/modules/constancias/constancias.js'];

include __DIR__ . '/../layouts/app.php';
