<?php

declare(strict_types=1);

/**
 * Vista: gestion_estudiante/reingreso/index.php
 * Gestión y formalización de reincorporaciones de estudiantes retirados al año escolar activo.
 *
 * @var array{reingresos_activos: int, retirados_elegibles: int} $stats
 * @var array<int, array<string, mixed>> $solicitudes
 * @var array{q: string} $filters
 * @var array{desde: int, hasta: int, total: int, pagina_actual: int, total_paginas: int} $paginacion
 * @var array<int, array{id_estructura_academica: int, nombre: string, cupos_disponibles: int}> $estructuras
 * @var array<int, array{id_institucion: int, nombre: string}> $instituciones
 */

$pageTitle       = 'Reingresos';
$pageDescription = 'Gestión y formalización de reincorporaciones de estudiantes retirados.';
$currentNav      = 'reingreso';
$extraScripts    = [
    '/js/modules/estudiantes/familiares.js',
    '/js/modules/ratificacion/ratificacion.js',
    '/js/modules/estudiantes/recaudos.js',
    '/js/modules/reingresos/reingreso.js',
];
$breadcrumbs = [
    ['label' => 'Gestión de estudiantes', 'href' => null],
    ['label' => 'Reingreso', 'href' => null],
];
$stats          = $stats ?? ['reingresos_activos' => 0, 'retirados_elegibles' => 0];
$solicitudes    = $solicitudes ?? [];
$filters        = $filters ?? ['q' => ''];
$paginacion     = $paginacion ?? [
    'desde' => 0, 'hasta' => 0, 'total' => 0, 'pagina_actual' => 1, 'total_paginas' => 1,
];
$estructuras    = $estructuras ?? [];
$instituciones  = $instituciones ?? [];
$catFamiliares  = $catFamiliares ?? [];
$estados        = $estados ?? ($catFamiliares['estados'] ?? []);
$nacionalidades = $nacionalidades ?? [];
$minDateEstudiante = $minDateEstudiante ?? '';
$maxDateEstudiante = $maxDateEstudiante ?? '';

ob_start();

  // ---------- Page header ----------
  ob_start();
  ?>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#registrarReingresoModal">
      <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i> Registrar Reingreso
    </button>
  <?php
  $pageHeaderActions = ob_get_clean();
  $pageHeading    = 'Reingresos Escolares';
  $pageSubheading = $pageDescription;
  include __DIR__ . '/../../components/page-header.php';
?>

<?php include __DIR__ . '/partials/stat-cards.php'; ?>

<?php
  // ---------- Data panel: filtros ----------
  $panelFormAction = BASE_URL . 'reingreso/index';

  $panelSearchName        = 'q';
  $panelSearchValue       = (string) ($filters['q'] ?? '');
  $panelSearchPlaceholder = 'Buscar por nombre o cédula...';

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
                . ' de ' . (int) $paginacion['total'] . ' reingresos';
  $panelPagination = [
      'pagina_actual' => $paginacion['pagina_actual'],
      'total_paginas' => $paginacion['total_paginas'],
  ];

  include __DIR__ . '/../../components/data-panel.php';
?>

<?php
$pageContent = ob_get_clean();

// ---------- Modales ----------
ob_start();
include __DIR__ . '/modals/registrar-reingreso-modal.php';
require_once __DIR__ . '/../ratificacion/modals/editar-estudiante-modal.php';
$pageModals = ob_get_clean();

require __DIR__ . '/../../layouts/app.php';