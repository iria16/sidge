<?php

declare(strict_types=1);

/**
 * Vista: gestion_estudiante/ratificacion/index.php
 * Ratificación de Matrícula — gestión de continuidad escolar y proyección
 * del próximo periodo académico.
 *
 * Armada con los mismos componentes que el resto de módulos:
 * components/page-header.php, components/stat-card.php,
 * components/avatar.php, components/status-badge.php y
 * components/data-panel.php. La ratificación es individual (el botón de
 * cada fila abre el modal y confirma contra ratificacion/ratificar), así
 * que la interacción vive en ratificacion.js.
 *
 * Espera del controlador:
 * - array $stats ['porRatificar' => int, 'yaRatificados' => int, 'avance' => int]
 * - array $ratificaciones cada uno: ['id','nombre','cedula','iniciales','avatar_color',
 * 'avatar_img' (opcional),'grado_actual','seccion','turno',
 * 'proximo_grado','estado' => 'ratificado'|'pendiente']
 * - array $grados lista de grados para el filtro
 * - array $secciones lista de secciones para el filtro
 * - array $turnos lista de turnos para el filtro
 * - array $filters valores actuales de los filtros aplicados
 * - array $paginacion ['desde','hasta','total','pagina_actual','total_paginas']
 * - array $combinaciones combinaciones del año activo para los selects del modal
 *
 * @var array $stats
 * @var array $ratificaciones
 * @var array $grados
 * @var array $secciones
 * @var array $turnos
 * @var array $filters
 * @var array $paginacion
 * @var array $combinaciones
 */

$pageTitle       = 'Ratificación de Matrícula';
$pageDescription = 'Al cierre del año escolar, coloque en el periodo en curso a los alumnos que continúan. '
    . 'Los que ya están matriculados en el año en curso no se listan: todavía están cursando.';
$currentNav      = 'ratificacion';

$breadcrumbs = [
    ['label' => 'Gestión de estudiantes', 'href' => null],
    ['label' => 'Ratificación de Matrícula', 'href' => null],
];

$stats          = $stats ?? ['porRatificar' => 0, 'yaRatificados' => 0, 'avance' => 0];
$ratificaciones = $ratificaciones ?? [];
$grados         = $grados ?? [];
$secciones      = $secciones ?? [];
$turnos         = $turnos ?? [];
$filters        = $filters ?? ['grado' => '', 'seccion' => '', 'turno' => '', 'q' => ''];
$paginacion     = $paginacion ?? [
    'desde' => 0, 'hasta' => 0, 'total' => 0, 'pagina_actual' => 1, 'total_paginas' => 1,
];

ob_start();

  // ---------- Page header ----------
  // Sin acciones en el header: la ratificación es siempre individual, desde
  // el botón de cada fila.
  $pageHeading    = 'Ratificación de Matrícula';
  $pageSubheading = $pageDescription;
  include __DIR__ . '/../../components/page-header.php';
?>

<?php include __DIR__ . '/partials/stat-cards.php'; ?>

<?php
  // ---------- Data panel: filtros ----------
  // El filtrado es automático (main.js, form.auto-filters): no hay botón
  // de buscar, los <select> y el input disparan el panel al cambiar.
  $panelFormAction = BASE_URL . 'ratificacion/index';

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
                . ' de ' . number_format((int) $paginacion['total'], 0, ',', '.') . ' estudiantes';
  $panelPagination = [
      'pagina_actual' => $paginacion['pagina_actual'],
      'total_paginas' => $paginacion['total_paginas'],
  ];

  include __DIR__ . '/../../components/data-panel.php';
?>

<?php
$pageContent = ob_get_clean();

ob_start();

// Las combinaciones del año activo van una sola vez en la página, en JSON, y el
// navegador arma los <select> del modal a partir de aquí. No se piden por
// alumno: la lista depende del año, que es el mismo para todos los de la
// pantalla, así que traerla en cada fila sería una petición por fila sin ganar
// nada.
include __DIR__ . '/partials/combinaciones-json.php';

require_once __DIR__ . '/modals/ratificar-modal.php';
require_once __DIR__ . '/modals/editar-estudiante-modal.php';

include __DIR__ . '/modals/ver-ratificacion-modal.php';

$pageModals = ob_get_clean();

/* familiares.js va primero: ratificacion.js lo arranca con FamiliaresUI.init().
 Los dos archivos se cargan en las dos pantallas porque el sub-modal de
 familiares es el MISMO (inscripcion/modal-agregar-familiar.php) y sus reglas son
 las mismas; lo que cambia es la configuración que le pasa cada uno.*/
$extraScripts = [
    '/js/modules/estudiantes/familiares.js',
    '/js/modules/ratificacion/ratificacion.js',
    '/js/modules/estudiantes/recaudos.js',
];
require __DIR__ . '/../../layouts/app.php';
