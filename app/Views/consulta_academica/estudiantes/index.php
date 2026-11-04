<?php

declare(strict_types=1);

/**
 * Vista: consulta_academica/estudiantes/index.php
 * Consulta de Expedientes — listado y búsqueda avanzada de estudiantes.
 *
 * Espera del controlador:
 * @var array $stats           ['total' => int, 'activos' => int, 'retirados' => int, 'egresados' => int]
 * @var array $estudiantes     filas del listado
 * @var array $aniosEscolares  años escolares disponibles
 * @var array $grados          grados disponibles para el filtro
 * @var array $secciones       secciones disponibles para el filtro
 * @var array $filters         ['q', 'anio', 'grado', 'seccion', 'estado']
 * @var array $paginacion      ['desde', 'hasta', 'total', 'pagina_actual', 'total_paginas']
 * @var bool  $esDocente       rol docente (restringe filtros y columnas)
 * @var bool  $esSecretaria    rol secretaría
 * @var bool  $sinAsignaciones docente sin estructuras asignadas en el año activo
 */

$pageTitle       = 'Consulta de Expedientes';
$pageDescription = 'Gestión y búsqueda avanzada del alumnado de la institución.';
$currentNav      = 'estudiantes';

$breadcrumbs = [
    ['label' => 'Gestión de estudiantes', 'href' => null],
    ['label' => 'Estudiantes', 'href' => null],
];

$estudiantes     = $estudiantes ?? [];
$aniosEscolares  = $aniosEscolares ?? [];
$turnos          = $turnos ?? [];
$esDocente       = !empty($esDocente);
$esSecretaria    = !empty($esSecretaria);
$esDirectivo     = !empty($esDirectivo) || (!$esDocente && !$esSecretaria);
$sinAsignaciones = !empty($sinAsignaciones);
$grados          = $grados ?? [];
$secciones       = $secciones ?? [];
$filters         = $filters ?? ['q' => '', 'anio' => '', 'id_turno' => '', 'grado' => '', 'seccion' => '', 'estado' => ''];
$paginacion      = $paginacion ?? [
    'desde' => 0, 'hasta' => 0, 'total' => 0, 'pagina_actual' => 1, 'total_paginas' => 1,
];

ob_start();

  // ---------- Page header ----------
  $pageHeading    = 'Consulta de Expedientes';
  $pageSubheading = $pageDescription;
  include __DIR__ . '/../../components/page-header.php';

  // ---------- Data panel: filtros ----------
  $panelFormAction = BASE_URL . 'estudiantes/index';

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
                . ' de ' . number_format((int) $paginacion['total'], 0, ',', '.') . ' expedientes';
  $panelPagination = [
      'pagina_actual' => $paginacion['pagina_actual'],
      'total_paginas' => $paginacion['total_paginas'],
  ];

  include __DIR__ . '/../../components/data-panel.php';

$pageContent = ob_get_clean();

require __DIR__ . '/../../layouts/app.php';
