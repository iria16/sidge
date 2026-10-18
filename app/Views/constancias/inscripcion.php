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
  /* Sin acciones en el encabezado: el visor de formato de prueba se retiró.
     No se abre búfer porque no hay nada que capturar aquí: si se abriera sin
     cerrarlo, el ob_get_clean() final se llevaría el búfer equivocado, toda la
     página se imprimiría antes del <!DOCTYPE> y el layout se quedaría sin
     contenido. Ese fallo ya pasó una vez. */
  $pageHeaderActions = '';
  $pageHeading       = 'Constancias de Inscripción';
  $pageSubheading    = 'Consulte los registros de inscripción por período escolar y emita la constancia oficial en formato Carta/Dompdf.';
  include __DIR__ . '/../components/page-header.php';
  ?>

<?php include __DIR__ . '/partials/alertas.php'; ?>

  <!-- Panel de Datos: Tabla con filtros -->
  <?php
  // Filtros del Toolbar
  ob_start();
  ?>
  <?php /* El año no se elige: es el activo, siempre. La constancia se emite sobre el
         matrícula en curso, así que ofrecer otros años hacía creer que se podía
         sacar de un período que ya no está en marcha. Como texto y no como un
         <select> deshabilitado, que parece un control y no lo es. */ ?>
  <span class="data-panel__year-fixed" id="filtroAnio">
    <?php foreach ($aniosEscolares as $a): ?>
      <?= htmlspecialchars((string) $a['nombre_periodo']) ?>
    <?php endforeach; ?>
  </span>

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
    <th scope="col" class="ancho-30">Estudiante</th>
    <th scope="col" class="ancho-17">Documento</th>
    <th scope="col" class="ancho-19">Grado y Sección</th>
    <th scope="col" class="ancho-17">Tipo Matrícula</th>
    <th scope="col" class="text-center ancho-17">Acción</th>
  </tr>
  <?php
  $panelTableHead = ob_get_clean();

  // Tbody
  ob_start();
  if (empty($inscripciones)):
  ?>
    <tr>
      <td colspan="5" class="text-center py-4 text-muted">
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
        <span class="badge bg-light text-dark border">
          <?= ($tipoMat === 'INSCRIPCION') ? 'Primer Ingreso' : 'Continuidad' ?>
        </span>
      </td>
        <td class="text-center">
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
