<?php

declare(strict_types=1);

/**
 * Vista: configuracion/catalogo/index.php
 * Gestión de Catálogos del Sistema — selector de catálogo, listado, filtros y alta de elementos.
 *
 * Espera del controlador:
 * @var array  $tiposCatalogo   cada uno: ['clave','nombre','icono','total']
 * @var string $catalogoActivo  clave del catálogo seleccionado actualmente (ej. 'turno')
 * @var array  $elementos       cada uno: ['id','nombre','estado' => 'ACTIVO'|'INACTIVO']
 * @var array  $filters         ['estado' => ..., 'q' => ...] (valores actuales del GET)
 * @var array  $paginacion      ['desde','hasta','total','pagina_actual','total_paginas']
 * @var array  $estadosGeo      estados disponibles para los catálogos de instituciones
 * @var array  $tiposInstitucion tipos de institución disponibles (vacío en catálogos simples)
 * @var bool   $pideGrado       indica si el catálogo requiere un grado académico
 * @var array  $gradosAcademicos grados académicos activos
 * @var array<int, string> $gradosAcademicosPorId nombres de grados por ID
 */

$pageTitle  = 'Gestión de Catálogos del Sistema';
$currentNav = 'catalogos';

$breadcrumbs = [
    ['label' => 'Administración', 'href' => null],
    ['label' => 'Catálogos', 'href' => null],
];

$catalogoInfo = null;
foreach ($tiposCatalogo as $tipo) {
    if ($tipo['clave'] === $catalogoActivo) {
        $catalogoInfo = $tipo;
        break;
    }
}
$catalogoInfo = $catalogoInfo ?? $tiposCatalogo[0];

ob_start();

  // ---------- Page header ----------
  ob_start();
  ?>
  <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-2"
          data-bs-toggle="modal" data-bs-target="#modalNuevoElemento">
    <i class="bi bi-plus-lg" aria-hidden="true"></i>
    Nuevo Elemento
  </button>
  <?php
  $pageHeaderActions = ob_get_clean();
  $pageHeading       = 'Gestión de Catálogos del Sistema';
  $pageSubheading    = 'Configure los elementos que parametrizan el funcionamiento escolar.';
  include __DIR__ . '/../../components/page-header.php';
?>

<?php include __DIR__ . '/partials/selector-catalogo.php'; ?>

<?php
  // ---------- Data panel: filtros ----------
  // name="tipo" viaja oculto para que el GET conserve el catálogo activo
  // al filtrar/buscar/paginar.
  $panelFormAction = BASE_URL . 'catalogo/index';

  ob_start();
  include __DIR__ . '/partials/panel-filtros.php';
  $panelFilters           = ob_get_clean();
  $panelSearchName        = 'q';
  $panelSearchValue       = $filters['q'] ?? '';
  $panelSearchPlaceholder = 'Buscar por nombre...';

  // ---------- Data panel: colgroup / thead / tbody ----------
  // Sin <colgroup>, table-layout: fixed reparte el ancho en partes iguales
  // (720px / 3 = 240px) y Nombre, la columna con más texto, quedaría igual de
  // angosta que Estado o Acciones.
  ob_start();
  include __DIR__ . '/partials/panel-colgroup.php';
  $panelColgroup = ob_get_clean();

  ob_start();
  include __DIR__ . '/partials/panel-head.php';
  $panelTableHead = ob_get_clean();

  // id="catalogoTablaBody": permite que catalogo.js ubique el <tbody> con
  // certeza (por ejemplo para insertar la fila de "sin resultados" cuando,
  // tras un toggle de estado con un filtro aplicado, la última fila visible
  // se remueve dinámicamente sin recargar la página).
  ob_start();
  include __DIR__ . '/partials/panel-rows.php';
  $panelTableBody = ob_get_clean();
  $panelTableBodyId = 'catalogoTablaBody';
  $catalogoColumnas = 3 + (!empty($tiposInstitucion) ? 1 : 0) + (!empty($pideGrado) ? 1 : 0);

  $panelSummary = 'Mostrando ' . (int) $paginacion['desde'] . ' a ' . (int) $paginacion['hasta']
                . ' de ' . (int) $paginacion['total'] . ' elementos';
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
require_once __DIR__ . '/modals/create-modal.php';
require_once __DIR__ . '/modals/edit-modal.php';
$pageModals = ob_get_clean();

$extraScripts = ['/js/modules/catalogos/catalogo.js']; // la version la agrega partials/scripts.php
include __DIR__ . '/../../layouts/app.php';
