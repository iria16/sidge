<?php

declare(strict_types=1);

/**
 * Vista: configuracion_academica/anios_escolares/index.php
 * Gestión de Años Escolares — períodos académicos, fechas y transiciones.
 *
 * Espera del controlador:
 * @var array  $stats        ['total','activos','planificados','cerrados','actual']
 * @var array  $anios        filas del listado
 * @var array  $filters      ['estado' => ..., 'q' => ...] (valores actuales del GET)
 * @var array  $paginacion   ['desde','hasta','total','pagina_actual','total_paginas']
 * @var bool   $puedeReabrir el rol directivo puede reabrir un período cerrado
 */

$pageTitle       = 'Gestión de Años Escolares';
$pageDescription = 'Administre los períodos académicos, fechas de inicio y cierres institucionales.';
$currentNav      = 'anios-escolares';
$extraScripts    = ['/js/academic-years.js'];

$breadcrumbs = [
    ['label' => 'Configuración académica', 'href' => null],
    ['label' => 'Años escolares', 'href' => null],
];

$stats          = $stats ?? [
    'total' => 0,
    'activos' => 0,
    'planificados' => 0,
    'cerrados' => 0,
    'actual' => 'Sin período activo',
];
$anios          = $anios ?? [];
$filters        = $filters ?? ['estado' => '', 'q' => ''];
$puedeReabrir   = $puedeReabrir ?? false;
$paginacion     = $paginacion ?? [
    'desde' => 0, 'hasta' => 0, 'total' => 0, 'pagina_actual' => 1, 'total_paginas' => 1,
];

ob_start();

  // ---------- Page header ----------
  ob_start();
  ?>
  <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#nuevoAnioModal">
    <i class="bi bi-plus-lg"></i> Nuevo Año Escolar
  </button>
  <?php
  $pageHeaderActions = ob_get_clean();
  $pageHeading       = 'Gestión de Años Escolares';
  $pageSubheading    = $pageDescription;
  include __DIR__ . '/../../components/page-header.php';
?>

<?php include __DIR__ . '/partials/stat-cards.php'; ?>

<?php
  // ---------- Data panel: filtros ----------
  $panelFormAction = BASE_URL . 'academicYear/index';

  ob_start();
  include __DIR__ . '/partials/panel-filtros.php';
  $panelFilters           = ob_get_clean();
  $panelSearchName        = 'q';
  $panelSearchValue       = (string) ($filters['q'] ?? '');
  $panelSearchPlaceholder = 'Buscar por año o estado...';

  // ---------- Data panel: colgroup / thead / tbody ----------
  // Anchos fijos por columna: Período / Inicio / Cierre / Estado / Acciones.
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
                . ' de ' . (int) $paginacion['total'] . ' períodos registrados';
  $panelPagination = [
      'pagina_actual' => $paginacion['pagina_actual'],
      'total_paginas' => $paginacion['total_paginas'],
  ];

  include __DIR__ . '/../../components/data-panel.php';
?>

<?php
$pageContent = ob_get_clean();

// ---------- Modales (uno por archivo, fuera del contenido) ----------
ob_start();
include __DIR__ . '/modals/nuevo-anio-modal.php';
include __DIR__ . '/modals/editar-anio-modal.php';
include __DIR__ . '/modals/ver-anio-modal.php';
include __DIR__ . '/modals/reabrir-anio-modal.php';
$pageModals = ob_get_clean();

require __DIR__ . '/../../layouts/app.php';
