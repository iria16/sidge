<?php

declare(strict_types=1);

/**
 * Vista: docente/nomina.php  ·  /docente/nomina
 * Listados de solo lectura de la sección del docente.
 *
 * Una sola tabla: los chips cambian de vista (?vista=) y la barra de filtros
 * actualiza la tabla automáticamente (form.auto-filters + .data-panel). Las
 * columnas las decide el Controller según la vista; aquí solo se pintan.
 *
 * @var array|null $seccion        Sección resuelta en el servidor (sesión).
 * @var bool       $tieneSeccion
 * @var string     $vista          nomina | contacto | cumpleaneros | boletas
 * @var array      $vistas         Definición de las vistas permitidas.
 * @var array      $columnas       clave => encabezado, en orden.
 * @var array      $filas          Resultado de EstudianteModel::nominaSeccion().
 * @var array      $filtros        q, sexo, mes (ya validados).
 * @var array      $meses          1..12 => nombre.
 * @var int        $mesActual      Mes en curso (1..12) en America/Caracas.
 * @var string     $nombreEscuela
 */

require_once __DIR__ . '/../helpers/ui.php';

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

/* Query string con SOLO los filtros permitidos de la vista destino: los enlaces
   de chips y de PDF nunca reenvían parámetros ajenos a esa vista.

   `orden` y `sentido` sí viajan siempre, y esa es la razón de que el orden se
   mantenga al cambiar de pestaña: el chip apunta a la otra vista llevándose el
   criterio elegido. En las vistas de orden fijo (cumpleañeros, acta) se quitan
   antes de construir la URL, para no dejar en la barra un control que ahí no
   hace nada. */
$urlNomina = static function (array $extra = []) use ($filtros, $vista, $vistas): string {
    $vistaDestino    = (string) ($extra['vista'] ?? $vista);
    $filtrosDestino  = $vistas[$vistaDestino]['filtros'] ?? ['q'];
    $valoresActuales = [
        'q'    => $filtros['q'],
        'sexo' => $filtros['sexo'],
        'mes'  => $filtros['mes'] !== null && isset($_GET['mes']) ? (string) $filtros['mes'] : '',
    ];

    $params = ['vista' => $vistaDestino];
    foreach ($filtrosDestino as $claveFiltro) {
        if (($valoresActuales[$claveFiltro] ?? '') !== '') {
            $params[$claveFiltro] = $valoresActuales[$claveFiltro];
        }
    }

    foreach ($extra as $k => $v) {
        if ($v === null || $v === '') {
            unset($params[$k]);
        } else {
            $params[$k] = $v;
        }
    }

    return BASE_URL . 'docente/nomina' . ($params ? '?' . http_build_query($params) : '');
};

$tituloVista        = $vistas[$vista]['titulo'];
$filtrosVista       = $vistas[$vista]['filtros'] ?? ['q'];
/* Un solo buscador para todas las pestañas, y el mismo texto de ayuda en todas:
   la búsqueda cubre nombre, apellido y representante legal en cualquier vista,
   así que no puede cambiar la pista según la pestaña. */
$placeholderBuscar  = 'Nombre, apellido o representante';
$tieneSelectFiltro  = in_array('sexo', $filtrosVista, true) || in_array('mes', $filtrosVista, true);
$textoSeccion       = $seccion ? trim($seccion['grado'] . ' "' . $seccion['seccion'] . '"') : '';
$fmtFecha           = static fn(?string $f): string => $f ? date('d/m/Y', strtotime($f)) : '';

/** Teléfono en lista sencilla sin WhatsApp */
$telefonoConWa = static function (string $tel, bool $incluirInvitacionGrupo = false) use ($e): string {
    return '<span class="nomina-tel">' . $e($tel) . '</span>';
};

/** Teléfono de persona autorizada en lista sencilla */
$telefonoAutConWa = static function (string $tel) use ($e): string {
    return '<div class="nomina-tel-linea"><span class="nomina-tel-num">' . $e($tel) . '</span></div>';
};

$pageTitle        = 'Listados de la sección';
$currentNav       = 'mi-seccion-listados';
$breadcrumbs      = [
    ['label' => 'Mi sección', 'href' => null],
    ['label' => 'Listados', 'href' => null],
];
$extraScripts     = ['/js/modules/docente/docente.js'];

$pageHeading      = 'Listados de la sección';
$pageSubheading   = $seccion
    ? $seccion['grado'] . ' · Sección ' . $seccion['seccion'] . ' · ' . $seccion['turno'] . ' · ' . $seccion['anio_escolar']
    : 'Sin sección asignada';
$idEstructura     = (int) ($seccion['id_estructura_academica'] ?? 0);

// Módulo de solo vista: sin botón de exportación
$pageHeaderActions = '';

ob_start();

include __DIR__ . '/../components/page-header.php';

if (!$tieneSeccion): ?>
  <div class="alert alert-warning d-flex align-items-start gap-3 shadow-sm" role="status">
    <i class="bi bi-exclamation-triangle fs-4 flex-shrink-0" aria-hidden="true"></i>
    <div>
      <div class="fw-semibold mb-1">No tienes una sección asignada</div>
      <div class="text-dark">La dirección debe asignarte una sección en el período escolar en curso.</div>
    </div>
  </div>
<?php else: ?>

  <?php include __DIR__ . '/partials/chips.php'; ?>

  <?php include __DIR__ . '/partials/filters.php'; ?>

  <?php /* ── Tabla única ─────────────────────────────────────────────────── */ ?>
  <?php /* Los autorizados NO se aplanan: el modelo ya devuelve una lista de
           estudiantes, cada uno con su arreglo de autorizados, que es la forma en
           que la pantalla los muestra y en la que el PDF los muestra. Aplanar
           aquí era lo que obligaba a repetir el nombre del alumno y del
           representante en cada fila. */ ?>
  <section class="card border-0 shadow-sm nomina-hoja nomina-hoja--<?= $e($vista) ?> data-panel">
    <div class="data-panel__body">
      <div class="card-header bg-white d-flex justify-content-between align-items-center py-3 px-4 border-bottom">
        <h2 class="h6 mb-0 fw-bold">
          <?= $e($tituloVista) ?>
          <?php if ($vista === 'cumpleaneros' && $filtros['mes'] !== null): ?>
            · <?= $e($meses[$filtros['mes']]) ?>
          <?php endif; ?>
        </h2>
        <span class="small text-muted"><?= count($filas) ?> estudiante<?= count($filas) === 1 ? '' : 's' ?></span>
      </div>
      <div class="table-responsive">

        <?php include __DIR__ . '/partials/table-head.php'; ?>
        <?php include __DIR__ . '/partials/table-body.php'; ?>

      </div>
    </div>
  </section>

<?php endif; ?>

<?php
$pageContent = ob_get_clean();

$pageModals = '';
if ($tieneSeccion && !in_array($vista, ['nomina', 'contacto', 'cumpleaneros'], true)) {
    /* Vista previa del documento en modal, para todas las pestañas por igual:
       «Visualizar documento» no baja nada, arma el PDF de la vista en curso y
       lo enseña aquí. Recién entonces aparece el enlace de descarga, que es el
       momento en que el docente decide. El acta pide además motivo y fecha, que
       van en su propia tarjeta y los valida el mismo JS. */
    ob_start();
    ?>
    <div class="student-record__pdf-preview d-none" id="contenedorPreviewDocumento">
      <iframe id="iframeVistaPreviaDocumento" title="Vista previa del documento"></iframe>
    </div>
    <?php
    $modalBody = ob_get_clean();

    ob_start();
    ?>
    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
    <?php /* Imprimir va junto a Descargar y se destapa con él, por el mismo motivo:
             la vista previa es el momento en que el docente decide, y antes de
             revisarla no hay nada que imprimir. Imprime el iframe de la propia
             vista previa, no una descarga aparte: el mismo documento que acaba
             de ver. */ ?>
    <button type="button" class="btn btn-outline-secondary d-none" id="btnImprimirDocumentoPdf">
      <i class="bi bi-printer me-1" aria-hidden="true"></i>Imprimir
    </button>
    <a href="#" id="btnDescargarDocumentoPdf" class="btn btn-primary d-none" target="_blank" rel="noopener">
      <i class="bi bi-download me-1" aria-hidden="true"></i>Descargar PDF
    </a>
    <?php
    $modalFooter   = ob_get_clean();
    $modalId       = 'modalVistaPreviaDocumento';
    $modalTitle    = $tituloVista;
    $modalSubtitle = $textoSeccion . ' · ' . ($seccion['anio_escolar'] ?? '');
    $modalSize     = 'xl';
    $modalCentered = false;

    ob_start();
    require __DIR__ . '/../components/modal.php';
    $pageModals = ob_get_clean();
}

/* docente.js: búsqueda de la sección y botón del acta. */
$extraScripts = array_merge($extraScripts ?? [], ['/js/modules/docente/docente.js']);
include __DIR__ . '/../layouts/app.php';