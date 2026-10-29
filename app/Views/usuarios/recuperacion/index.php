<?php

declare(strict_types=1);

/**
 * Vista: usuarios/recuperacion/index.php
 * Gestión de Recuperación de Acceso — listado de solicitudes de
 * restablecimiento de contraseña, con filtro por estado y acciones
 * rápidas (aprobar / rechazar / ver detalle).
 *
 * Espera del controlador:
 * @var array  $stats               ['total','pendientes','aprobadas','rechazadas']
 * @var array  $solicitudes         cada uno: ['id','usuario_id','fecha','hora','nombre','cargo','usuario','avatar_tipo','avatar_foto','avatar_color','iniciales','estado','origen']
 * @var array  $estadosMeta         mapa estado => ['label','clase'] (status-badge.php)
 * @var array  $usuariosDisponibles cada uno: ['id','nombre','usuario'] — para el modal de restablecer clave manual
 * @var array  $filters             ['estado' => ..., 'q' => ...] (valores actuales del GET)
 * @var array  $paginacion          ['desde','hasta','total','pagina_actual','total_paginas']
 */

$pageTitle  = 'Recuperación de Acceso';
$currentNav = 'recuperacion';
$extraScripts = ['/js/modules/usuarios/recuperacion.js'];

$breadcrumbs = [
    ['label' => 'Usuarios', 'href' => null],
    ['label' => 'Recuperación de acceso', 'href' => null],
];

$stats = $stats ?? ['total' => 0, 'pendientes' => 0, 'aprobadas' => 0, 'rechazadas' => 0];
$solicitudes = $solicitudes ?? [];
$estadosMeta = $estadosMeta ?? [
    'pendiente' => ['label' => 'Pendiente', 'clase' => 'pending'],
    'aprobado'  => ['label' => 'Aprobado',  'clase' => 'active'],
    'rechazado' => ['label' => 'Rechazado', 'clase' => 'inactive'],
];
$usuariosDisponibles = $usuariosDisponibles ?? [];
$filters = $filters ?? ['estado' => '', 'q' => ''];
$paginacion = $paginacion ?? [
    'desde' => 0, 'hasta' => 0, 'total' => 0, 'pagina_actual' => 1, 'total_paginas' => 1,
];

ob_start();

  // ---------- Page header ----------
  ob_start();
  ?>
  <button type="button" class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#restablecerClaveModal">
    <i class="bi bi-key-fill me-1" aria-hidden="true"></i>Restablecer Contraseña
  </button>
  <?php
  $pageHeaderActions = ob_get_clean();
  $pageHeading       = 'Gestión de Recuperación de Acceso';
  $pageSubheading    = 'Gestione las solicitudes de restablecimiento de contraseña.';
  include __DIR__ . '/../../components/page-header.php';
?>

<?php include __DIR__ . '/partials/stat-cards.php'; ?>

<?php
  // ---------- Data panel: filtros ----------
  $panelFormAction = BASE_URL . 'recuperacionAcceso/index';

  ob_start();
  include __DIR__ . '/partials/panel-filtros.php';
  $panelFilters           = ob_get_clean();
  $panelSearchName        = 'q';
  $panelSearchValue       = (string) ($filters['q'] ?? '');
  $panelSearchPlaceholder = 'Buscar por nombre o usuario...';

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
                . ' de ' . (int) $paginacion['total'] . ' resultados';
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
include __DIR__ . '/modals/reset-password-modal.php';
$credModalTitulo = '¡Contraseña restablecida con éxito!';
$credModalTexto  = 'Se generó una nueva clave provisional para <strong id="credUsuarioNombre" aria-live="polite"></strong>. Copia la contraseña a continuación.';
include __DIR__ . '/../../components/credentials-modal.php';
include __DIR__ . '/modals/detalle-modal.php';
$pageModals = ob_get_clean();

require __DIR__ . '/../../layouts/app.php';