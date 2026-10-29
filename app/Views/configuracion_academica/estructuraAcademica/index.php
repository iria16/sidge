<?php

declare(strict_types=1);

/**
 * Vista: configuracion_academica/estructuraAcademica/index.php
 * Gestión de Grados y Secciones — estructura académica por año.
 *
 * Espera del controlador:
 * @var array  $stats          ['total','grados','secciones','capacidad']
 * @var array  $estructuras    filas del listado
 * @var array  $anios          años disponibles para el filtro
 * @var array  $aniosEditables años donde se puede escribir (solo el activo)
 * @var string $anioActivo     nombre del año escolar activo
 * @var bool   $anioEsLectura  el año consultado no admite escritura
 * @var array  $filters        ['anio','grado','seccion','turno','q']
 * @var array  $grados         opciones de grado
 * @var array  $secciones      opciones de sección
 * @var array  $turnos         opciones de turno
 * @var array  $paginacion     ['desde','hasta','total','pagina_actual','total_paginas']
 * @var bool   $esConsulta     el rol solo consulta
 */

$pageTitle       = 'Gestión de Grados y Secciones';
$pageDescription = 'Administre la distribución de grados, secciones y turnos académicos.';
$currentNav      = 'estructura-academica';
$extraScripts    = ['/js/modules/academica/academic-structure.js'];

$breadcrumbs = [
    ['label' => 'Configuración académica', 'href' => null],
    ['label' => 'Estructura académica',     'href' => null],
];

$stats          = $stats ?? ['total' => 0, 'grados' => 0, 'secciones' => 0, 'capacidad' => 0];
$estructuras    = $estructuras ?? [];
$anios          = $anios ?? [];
$anioActivo     = $anioActivo ?? 'No hay un año escolar activo';
$anioEsLectura  = $anioEsLectura ?? true;
$filters        = $filters ?? [];
$anioConsultado = isset($filters['anio']) ? (int) $filters['anio'] : 0;
$grados         = $grados ?? [];
$secciones      = $secciones ?? [];
$turnos         = $turnos ?? [];
$esConsulta     = $esConsulta ?? false;
$paginacion     = $paginacion ?? [
    'desde' => 0, 'hasta' => 0, 'total' => 0, 'pagina_actual' => 1, 'total_paginas' => 1,
];

/* El coordinador consulta la estructura pero no la modifica: crearla o cambiarla
   es decisión de la dirección. Entra en la misma condición que un año cerrado,
   que ya tenía resuelto el "no mostrar un botón que el servidor va a rechazar",
   en vez de abrir un segundo camino para lo mismo. */
$soloLectura = $anioEsLectura || $esConsulta;

ob_start();

  // ---------- Page header ----------
  ob_start();
  ?>
  <?php /* Un año CERRADO es historial: se consulta, no se escribe. El botón se esconde en
   vez de deshabilitarse para que no quede a la vista una acción que nunca va a
   funcionar. La misma condición la repite el servidor en store().*/ ?>
  <?php if (!$soloLectura): ?>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#nuevaEstructuraModal">
      <i class="bi bi-plus-lg"></i> Nuevo Registro
    </button>
  <?php endif; ?>
  <?php
  $pageHeaderActions = ob_get_clean();
  $pageHeading       = 'Gestión de Grados y Secciones';
  $pageSubheading    = $pageDescription;
  include __DIR__ . '/../../components/page-header.php';
?>

<?php include __DIR__ . '/partials/stat-cards.php'; ?>

<?php
  // ---------- Data panel: filtros ----------
  $panelFormAction = BASE_URL . 'academicStructure/index';

  ob_start();
  include __DIR__ . '/partials/panel-filtros.php';
  $panelFilters           = ob_get_clean();
  $panelSearchName        = 'q';
  $panelSearchValue       = (string) ($filters['q'] ?? '');
  $panelSearchPlaceholder = 'Buscar por grado o sección...';

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
                . ' de ' . (int) $paginacion['total'] . ' registros';
  $panelPagination = [
      'pagina_actual' => $paginacion['pagina_actual'],
      'total_paginas' => $paginacion['total_paginas'],
  ];

  include __DIR__ . '/../../components/data-panel.php';
?>

<?php
$pageContent = ob_get_clean();

// ---------- Modales ----------
/* Los modales solo sirven para escribir. En consulta no se incluyen: si
   estuvieran, el JavaScript los abriría y mandaría un POST que la ruta
   rechaza con 403. */
ob_start();
if (!$soloLectura) {
    $modalId       = 'nuevaEstructuraModal';
    $modalTitle    = 'Nuevo Registro';
    $modalSubtitle = 'Configure una nueva sección académica.';
    $formId        = 'formNuevaEstructura';
    $editMode      = false;
    include __DIR__ . '/structure-modal.php';

    $modalId       = 'editarEstructuraModal';
    $modalTitle    = 'Editar Registro';
    $modalSubtitle = 'Actualice la configuración académica.';
    $formId        = 'formEditarEstructura';
    $editMode      = true;
    include __DIR__ . '/structure-modal.php';
}
$pageModals = ob_get_clean();

require __DIR__ . '/../../layouts/app.php';
