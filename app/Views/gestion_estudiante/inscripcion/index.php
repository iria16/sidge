<?php

declare(strict_types=1);

/**
 * Vista: gestion_estudiante/inscripcion/index.php
 * Gestión de Inscripciones — ingreso de nuevos estudiantes al plantel.
 *
 * Armada con los mismos componentes que el resto de módulos:
 * components/page-header.php, components/stat-card.php,
 * components/avatar.php, components/status-badge.php y
 * components/data-panel.php. El wizard vive en enrollment-modal.php y su
 * JS en public/assets/js/modules/estudiantes/enrollments.js ($extraScripts).
 *
 * El filtrado y la paginación los aplica automáticamente main.js sobre el
 * form del panel (initAutoFilters), igual que en el resto de módulos: por
 * eso la tabla y el resumen se renderizan siempre desde PHP.
 *
 * Espera del controlador:
 * - array $stats ['inscritosAnio' => int, 'trendInscritos' => int,
 * 'cuposDisponibles' => int, 'inscripcionesMes' => int]
 * - array $inscripciones cada uno: ['id','nombre','cedula','iniciales','avatar_color',
 * 'avatar_img' (opcional),'grado','seccion','fecha_inscripcion',
 * 'estado' => 'completa'|'incompleta']
 * - string $anioEnCurso periodo en curso (referencia; el selector usa $anios)
 * - array $anios años consultables: [['id','nombre','estado','es_lectura']]
 * - array $anioSeleccionado el año que está viendo la lista
 * - bool $anioEsLectura true si ese año está cerrado y no admite altas
 * - array $grados lista de grados para el filtro
 * - array $secciones lista de secciones para el filtro
 * - array $filters valores actuales de los filtros aplicados
 * - array $paginacion ['desde','hasta','total','pagina_actual','total_paginas']
 *
 * @var array $stats
 * @var array $inscripciones
 * @var string $anioEnCurso
 * @var array $anios
 * @var array $anioSeleccionado
 * @var bool $anioEsLectura
 * @var array $grados
 * @var array $secciones
 * @var array $filters
 * @var array $paginacion
 */

$pageTitle       = 'Gestión de Inscripciones';
/* Esta pantalla es SOLO del primer ingreso. La inscripción ocurre una sola vez en
 la vida del alumno en el plantel, así que lo que se lista son los ingresos.
 Antes la descripción decía "y ratificaciones anuales", y ya no corresponde: las
 ratificaciones se procesan en su propio módulo y llegan por otra vía. Con las
 dos cosas juntas en la misma pantalla, en el cierre de año la secretaria no
 sabía cuál usar para un alumno que ya estaba del año anterior.*/
$pageDescription = 'Registra el ingreso de nuevos estudiantes al plantel. Un alumno se inscribe una sola vez; '
    . 'los que ya estaban en años anteriores se ratifican en el módulo de Ratificación.';
$currentNav      = 'inscripcion';
/* familiares.js va PRIMERO: enrollments.js arranca el módulo con
 FamiliaresUI.init(), así que tiene que existir ya. El orden importa.*/
$extraScripts    = ['/js/modules/estudiantes/familiares.js', '/js/modules/estudiantes/enrollments.js'];

$breadcrumbs = [
    ['label' => 'Gestión de estudiantes', 'href' => null],
    ['label' => 'Inscripciones', 'href' => null],
];

$stats          = $stats ?? ['inscritosAnio' => 0, 'trendInscritos' => 0, 'cuposDisponibles' => 0, 'inscripcionesMes' => 0];
$inscripciones  = $inscripciones ?? [];
$anioEnCurso = $anioEnCurso ?? '';
/* El año elegido y su estado. Por defecto se toma el que está en curso, así la vista
 nunca queda sin él aunque se renderice desde otro punto del proyecto.*/
$anios            = $anios ?? [];
$anioSeleccionado = $anioSeleccionado
    ?? ['id' => 0, 'nombre' => $anioEnCurso ?: 'sin año en curso', 'estado' => 'CERRADO', 'es_lectura' => true];
$anioEsLectura    = $anioEsLectura ?? true;
$grados         = $grados ?? [];
$secciones      = $secciones ?? [];
$filters        = $filters ?? ['q' => '', 'anio' => '', 'grado' => '', 'seccion' => ''];
$paginacion     = $paginacion ?? [
    'desde' => 0, 'hasta' => 0, 'total' => 0, 'pagina_actual' => 1, 'total_paginas' => 1,
];

$trendInscritos = (int) ($stats['trendInscritos'] ?? 0);

ob_start();

  // ---------- Page header ----------
  ob_start();
  ?>
  <?php /* Un año CERRADO es historial: se consulta, no se escribe. El botón se esconde
 en vez de deshabilitarse para que no quede a la vista una acción que nunca
 va a funcionar. La misma condición la repite el servidor en store().*/ ?>
  <?php if (!$anioEsLectura): ?>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#nuevaInscripcionModal">
      <i class="bi bi-person-plus me-1" aria-hidden="true"></i> Nueva Inscripción
    </button>
  <?php endif; ?>
  <?php
  $pageHeaderActions = ob_get_clean();
  $pageHeading       = 'Gestión de Inscripciones';
  $pageSubheading    = $pageDescription;
  include __DIR__ . '/../../components/page-header.php';
?>

<?php include __DIR__ . '/partials/stat-cards.php'; ?>

<?php
  /* ---------- Panel de datos: filtros ----------
 El filtrado y la paginación los aplica main.js sobre este formulario, con su
 función initAutoFilters: no hay botón de buscar, los <select> y el campo de
 texto disparan el panel al cambiar.*/
  $panelFormAction = BASE_URL . 'inscripciones/index';

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
                . ' de ' . number_format((int) $paginacion['total'], 0, ',', '.') . ' inscripciones';
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
include __DIR__ . '/enrollment-modal.php';
include __DIR__ . '/modals/ver-inscripcion-modal.php';
$pageModals = ob_get_clean();

require __DIR__ . '/../../layouts/app.php';
