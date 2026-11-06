<?php

declare(strict_types=1);

/**
 * Vista: gestion_estudiante/retiro/index.php
 * Gestión y procesamiento de solicitudes de retiro escolar.
 *
 * Espera del controlador:
 * @var array  $stats           ['pendientes' => int, 'aprobados' => int, 'rechazados' => int]
 * @var array  $solicitudes     filas del listado
 * @var array  $motivos         motivos de retiro para el modal de alta
 * @var array  $filters         ['estado' => ..., 'q' => ...] (valores actuales del GET)
 * @var array  $paginacion      ['desde','hasta','total','pagina_actual','total_paginas']
 * @var bool   $puedeRegistrar  la secretaria registra los retiros
 * @var bool   $puedeResolver   el directivo los aprueba o rechaza
 */

$pageTitle       = 'Retiro';
$pageDescription = 'Gestión y procesamiento de solicitudes de retiro escolar.';
$currentNav      = 'retiro';
$extraScripts    = ['/js/modules/retiros/withdrawals.js'];
$breadcrumbs = [
    ['label' => 'Gestión de estudiantes', 'href' => null],
    ['label' => 'Retiro', 'href' => null],
];
$stats          = $stats ?? ['pendientes' => 0, 'aprobados' => 0, 'rechazados' => 0];
$solicitudes    = $solicitudes ?? [];
$motivos        = $motivos ?? [];
// El controlador decide qué puede hacer cada rol: la secretaria registra los
// retiros y el directivo los aprueba/rechaza.
$puedeRegistrar = $puedeRegistrar ?? false;
$puedeResolver  = $puedeResolver ?? false;
$filters        = $filters ?? ['estado' => '', 'q' => ''];
$paginacion     = $paginacion ?? [
    'desde' => 0, 'hasta' => 0, 'total' => 0, 'pagina_actual' => 1, 'total_paginas' => 1,
];

ob_start();

  // ---------- Page header ----------
  // El botón de "Registrar retiro" solo lo ve quien puede crearlo (secretaria);
  // el resto de la vista es de consulta para los dos roles.
  ob_start();
  if ($puedeRegistrar):
  ?>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#registrarRetiroModal">
      <i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i> Registrar Retiro
    </button>
  <?php
  endif;
  $pageHeaderActions = ob_get_clean();
  $pageHeading    = 'Retiros Escolares';
  $pageSubheading = $pageDescription;
  include __DIR__ . '/../../components/page-header.php';
?>

<?php include __DIR__ . '/partials/stat-cards.php'; ?>

<?php
  // ---------- Data panel: filtros ----------
  $panelFormAction = BASE_URL . 'withdrawal/index';

  ob_start();
  include __DIR__ . '/partials/panel-filtros.php';
  $panelFilters           = ob_get_clean();
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
                . ' de ' . (int) $paginacion['total'] . ' solicitudes';
  $panelPagination = [
      'pagina_actual' => $paginacion['pagina_actual'],
      'total_paginas' => $paginacion['total_paginas'],
  ];

  include __DIR__ . '/../../components/data-panel.php';
?>

<?php
$pageContent = ob_get_clean();

// ---------- Modales ----------
// Se capturan aparte de $pageContent: layouts/app.php los imprime bajo <body>,
// fuera de .app-shell, para que no hereden overflow ni apilamiento del cascarón.
ob_start();
include __DIR__ . '/modals/registrar-retiro-modal.php';
include __DIR__ . '/modals/detalle-retiro-modal.php';
include __DIR__ . '/modals/reingreso-modal.php';
$pageModals = ob_get_clean();

require __DIR__ . '/../../layouts/app.php';
