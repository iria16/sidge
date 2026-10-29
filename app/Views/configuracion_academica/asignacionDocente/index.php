<?php

declare(strict_types=1);

/**
 * Vista: configuracion_academica/asignacionDocente/index.php
 * Asignación docente — docente titular por sección académica.
 *
 * Espera del controlador:
 * @var array  $stats            ['total','asignadas','pendientes','docentes']
 * @var array  $asignaciones     filas del listado
 * @var string $anioActivo       nombre del año escolar activo
 * @var array  $grados           opciones de grado
 * @var array  $secciones        opciones de sección
 * @var array  $turnos           opciones de turno
 * @var array  $filters          ['grado','seccion','turno','q']
 * @var array  $estructuras      secciones disponibles para asignar
 * @var array  $docentes         docentes disponibles
 * @var array  $tiposAsignacion  tipos de asignación
 * @var array  $paginacion       ['desde','hasta','total','pagina_actual','total_paginas']
 * @var bool   $esConsulta       el rol solo consulta (sin botones de escritura)
 */

$pageTitle       = 'Asignación Docente';
$pageDescription = 'Asigne un docente titular a las secciones configuradas en la Estructura Académica.';
$currentNav      = 'asignacion-docente';
$extraScripts    = ['/js/modules/academica/teacher-assignment.js'];

$breadcrumbs = [
    ['label' => 'Configuración académica', 'href' => null],
    ['label' => 'Asignación docente', 'href' => null],
];

$stats           = $stats ?? ['total' => 0, 'asignadas' => 0, 'pendientes' => 0, 'docentes' => 0];
$asignaciones    = $asignaciones ?? [];
$anioActivo      = $anioActivo ?? 'No hay un año escolar activo';
$grados          = $grados ?? [];
$secciones       = $secciones ?? [];
$turnos          = $turnos ?? [];
$filters         = $filters ?? [];
$estructuras     = $estructuras ?? [];
$docentes        = $docentes ?? [];
$tiposAsignacion = $tiposAsignacion ?? [];
$esConsulta      = $esConsulta ?? false;
$paginacion      = $paginacion ?? [
    'desde' => 0, 'hasta' => 0, 'total' => 0, 'pagina_actual' => 1, 'total_paginas' => 1,
];

ob_start();

  // ---------- Page header ----------
  ob_start();
  ?>
  <?php if (empty($esConsulta)): ?>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#nuevaAsignacionModal">
      <i class="bi bi-plus-lg"></i> Nueva Asignación
    </button>
  <?php endif; ?>
  <?php
  $pageHeaderActions = ob_get_clean();
  $pageHeading       = 'Asignación Docente';
  $pageSubheading    = $pageDescription;
  include __DIR__ . '/../../components/page-header.php';
?>

<?php include __DIR__ . '/partials/stat-cards.php'; ?>

<?php
  // ---------- Data panel: filtros ----------
  $panelFormAction = BASE_URL . 'teacherAssignment/index';

  ob_start();
  include __DIR__ . '/partials/panel-filtros.php';
  $panelFilters           = ob_get_clean();
  $panelSearchName        = 'q';
  $panelSearchValue       = (string) ($filters['q'] ?? '');
  $panelSearchPlaceholder = 'Buscar por docente o grado...';

  // ---------- Data panel: colgroup / thead / tbody ----------
  ob_start();
  include __DIR__ . '/partials/panel-colgroup.php';
  $panelColgroup = ob_get_clean();

  ob_start();
  include __DIR__ . '/partials/panel-head.php';
  $panelTableHead = ob_get_clean();

  ob_start();
  include __DIR__ . '/partials/panel-rows.php';
  $panelTableBody = ob_get_clean();

  $panelSummary = 'Mostrando ' . (int) $paginacion['desde'] . ' a ' . (int) $paginacion['hasta']
                . ' de ' . (int) $paginacion['total'] . ' secciones registradas';
  $panelPagination = [
      'pagina_actual' => $paginacion['pagina_actual'],
      'total_paginas' => $paginacion['total_paginas'],
  ];

  include __DIR__ . '/../../components/data-panel.php';
?>

<?php
$pageContent = ob_get_clean();

// ---------- Modales ----------
// Un solo archivo reutilizable se incluye dos veces: alta y edición.
ob_start();
if (empty($esConsulta)) {
    $editMode = false;
    $modalId = 'nuevaAsignacionModal';
    $modalTitle = 'Nueva Asignación';
    $modalSubtitle = 'Asigne un docente a una sección académica.';
    $formId = 'formNuevaAsignacion';
    include __DIR__ . '/assignment-modal.php';

    $editMode = true;
    $modalId = 'editarAsignacionModal';
    $modalTitle = 'Editar Asignación';
    $modalSubtitle = 'Actualice el docente responsable de la sección.';
    $formId = 'formEditarAsignacion';
    include __DIR__ . '/assignment-modal.php';
}
$pageModals = ob_get_clean();

require __DIR__ . '/../../layouts/app.php';
