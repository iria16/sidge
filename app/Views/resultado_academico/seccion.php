<?php
/**
 * Vista: resultado_academico/seccion.php
 * Carga de propuesta pedagógica y confirmación oficial de resultados de la sección.
 *
 * Utiliza los componentes y variables del sistema de diseño SIGDE:
 * - components/page-header.php
 * - components/stat-card.php
 * - components/status-badge.php
 * - components/avatar.php
 * - Clases SCSS: .data-panel, .stat-card, .period-chip, .modal
 *
 * @var array  $detalle
 * @var string $rolUsuario
 * @var bool   $esDirectivo
 * @var bool   $esDocente   solo el docente escribe; el resto mira en lectura
 * @var bool   $esConsulta
 * @var array  $literales
 * @var string|null $flash_error
 * @var string|null $flash_success
 * @var string|null $flash_info
 */

$esDocente     = ($rolUsuario === 'docente');
$gradoNombre   = htmlspecialchars($detalle['grado'] ?? '');
$seccionNombre = htmlspecialchars($detalle['seccion'] ?? '');
$turnoNombre   = htmlspecialchars($detalle['turno'] ?? '');
$anioNombre    = htmlspecialchars($detalle['anio_escolar'] ?? '');
$docenteNombre = htmlspecialchars($detalle['docente_nombre'] ?? 'Sin asignar');
$idEstructura  = (int) $detalle['id'];
$idGradoActual = (int) $detalle['id_grado'];

$pageTitle       = "Literales Finales - {$gradoNombre} \"{$seccionNombre}\"";
$pageDescription = "Turno {$turnoNombre} · Carga oficial de calificaciones cualitativas · Docente: {$docenteNombre}";
$currentNav      = $esDocente ? 'mi-seccion-promocion' : 'promocion';

$breadcrumbs = $esDocente
    ? [
        ['label' => 'Mi sección', 'href' => BASE_URL . 'resultadoAcademico/index'],
        ['label' => "{$gradoNombre} \"{$seccionNombre}\"", 'href' => null],
    ]
    : [
        ['label' => 'Gestión de estudiantes', 'href' => null],
        ['label' => 'Promoción Académica', 'href' => BASE_URL . 'resultadoAcademico/index'],
        ['label' => "{$gradoNombre} \"{$seccionNombre}\"", 'href' => null],
    ];

$estudiantes    = $detalle['estudiantes'] ?? [];
$gradosCatalogo = $detalle['grados_catalogo'] ?? [];

// Calcular id y nombre del siguiente grado en catálogo
$idGradoSiguiente     = null;
$nombreGradoSiguiente = null;
foreach ($gradosCatalogo as $idx => $gCat) {
    if ((int) $gCat['id'] === $idGradoActual) {
        if (isset($gradosCatalogo[$idx + 1])) {
            $idGradoSiguiente     = (int) $gradosCatalogo[$idx + 1]['id'];
            $nombreGradoSiguiente = (string) $gradosCatalogo[$idx + 1]['nombre'];
        }
        break;
    }
}
$esUltimoGrado    = ($idGradoSiguiente === null);
$textoGradoAvanza = $esUltimoGrado ? 'Egreso de Primaria' : ($nombreGradoSiguiente ?? 'Grado Superior');

// Métricas de la nómina
$totalEst        = count($estudiantes);
$totalConLiteral = 0;
$totalSinLiteral = 0;
$promovidos      = 0;
$noPromovidos    = 0;
$confirmados     = 0;
$pendientes      = 0;

foreach ($estudiantes as $est) {
    $lit    = strtoupper(trim($est['literal'] ?? ''));
    $st     = $est['estado_resultado'] ?? 'PENDIENTE';
    $esConf = !empty($est['fecha_confirmacion']) || in_array($st, ['PROMOVIDO', 'NO_PROMOVIDO'], true);

    if ($lit !== '') {
        $totalConLiteral++;
    } else {
        $totalSinLiteral++;
    }

    if ($esConf) {
        $confirmados++;
    }

    if ($st === 'PROMOVIDO') {
        $promovidos++;
    } elseif ($st === 'NO_PROMOVIDO') {
        $noPromovidos++;
    } else {
        $pendientes++;
    }
}

$porcentajeCarga = $totalEst > 0 ? (int) round(($totalConLiteral / $totalEst) * 100) : 0;

ob_start();

// ---------- Page Header Actions (Botones de Acción y Badges) ----------
ob_start();
?>
<div class="d-flex align-items-center gap-2 flex-wrap">
  <a href="<?= BASE_URL ?>resultadoAcademico/index" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
    <i class="bi bi-arrow-left" aria-hidden="true"></i>
    <span>Volver</span>
  </a>
</div>
<?php
$pageHeaderActions = ob_get_clean();
$pageHeading       = $pageTitle;
$pageSubheading    = "Turno {$turnoNombre} · Carga oficial de calificaciones cualitativas";
?>
<div class="d-flex align-items-center gap-2 mb-2">
  <span class="badge" style="background-color: #0f172a; color: #ffffff; font-size: 0.72rem; letter-spacing: 0.05em; font-weight: 700; padding: 4px 8px; border-radius: 4px;">
    ACTA FINAL
  </span>
  <span class="text-secondary small fw-medium">
    Período Escolar <?= $anioNombre ?>
  </span>
</div>
<?php
include __DIR__ . '/../components/page-header.php';
?>

<!-- Estilos específicos para la pantalla de Literales según mockup -->
<style>
/* Selector de Literal en forma de pastilla sólida (Pill) */
.select-literal-pill {
  /* -webkit-appearance: none va explícito porque esta pastilla pinta su propia
     flecha: sin el prefijo, Safari deja la nativa del navegador encima y se
     ven dos superpuestas. El CSS global (base/selects) ya lo resuelve para
     todos los selects, pero aquí se repite para que la pastilla no dependa de
     que ese archivo esté cargado. */
  -webkit-appearance: none;
  appearance: none;
  width: 72px;
  height: 38px;
  border-radius: 8px;
  font-size: 0.95rem;
  font-weight: 700;
  text-align: center;
  text-align-last: center;
  border: 1px solid transparent;
  cursor: pointer;
  background-repeat: no-repeat;
  background-position: right 6px center;
  background-size: 12px 12px;
  padding-left: 6px;
  padding-right: 20px;
  transition: all 0.15s ease-in-out;
}
.select-literal-pill option {
  background-color: #ffffff;
  color: #1e293b;
  font-weight: 600;
}
.select-literal-pill[data-literal="A"] {
  background-color: #16a34a;
  color: #ffffff;
  border-color: #15803d;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23ffffff'%3E%3Cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E");
}
.select-literal-pill[data-literal="B"] {
  background-color: #2563eb;
  color: #ffffff;
  border-color: #1d4ed8;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23ffffff'%3E%3Cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E");
}
.select-literal-pill[data-literal="C"] {
  background-color: #0284c7;
  color: #ffffff;
  border-color: #0369a1;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23ffffff'%3E%3Cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E");
}
.select-literal-pill[data-literal="D"] {
  background-color: #d97706;
  color: #ffffff;
  border-color: #b45309;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23ffffff'%3E%3Cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E");
}
.select-literal-pill[data-literal="E"] {
  background-color: #dc2626;
  color: #ffffff;
  border-color: #b91c1c;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23ffffff'%3E%3Cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E");
}
.select-literal-pill[data-literal=""], .select-literal-pill:not([data-literal]) {
  background-color: #f1f5f9;
  color: #64748b;
  border-color: #cbd5e1;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%2364748b'%3E%3Cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E");
}
.select-literal-pill:disabled {
  opacity: 0.88;
  cursor: not-allowed;
  filter: saturate(0.9);
}

/* Badges de Decisión Oficial estilo mockup */
.decision-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.35rem 0.75rem;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.03em;
  text-transform: uppercase;
}
.decision-badge--promovido {
  background-color: #e0f2fe;
  color: #0369a1;
  border: 1px solid #bae6fd;
}
.decision-badge--no-promovido {
  background-color: #ffe4e6;
  color: #be123c;
  border: 1px solid #fecdd3;
}
.decision-badge--pendiente {
  background-color: #fef3c7;
  color: #b45309;
  border: 1px solid #fde68a;
}

/* Input de observación pedagógica */
.input-obs-pedagogica {
  background-color: #f8fafc;
  border-color: #e2e8f0;
  font-size: 0.85rem;
  border-radius: 6px;
  transition: all 0.15s ease;
}
.input-obs-pedagogica:focus {
  background-color: #ffffff;
  border-color: #3b82f6;
  box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.15);
}
.input-obs-pedagogica:disabled {
  background-color: #f1f5f9;
  color: #64748b;
  cursor: not-allowed;
}

/* Barra de filtros de pestañas */
.filter-pills-nav {
  display: inline-flex;
  gap: 4px;
  background: #f1f5f9;
  padding: 4px;
  border-radius: 8px;
}
.btn-filter-tab {
  border: none;
  background: transparent;
  color: #64748b;
  font-size: 0.82rem;
  font-weight: 600;
  padding: 5px 12px;
  border-radius: 6px;
  transition: all 0.15s ease;
}
.btn-filter-tab:hover {
  color: #0f172a;
  background: rgba(255, 255, 255, 0.6);
}
.btn-filter-tab.active {
  background: #0f172a;
  color: #ffffff;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.15);
}

/* Banner de Normativa */
.normativa-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  transition: box-shadow 0.2s ease;
}
.normativa-card:hover {
  box-shadow: 0 4px 12px rgba(15, 23, 42, 0.04);
}
.normativa-card__icon {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  background-color: #eff6ff;
  color: #1d4ed8;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.15rem;
}

/* Fila de estudiante confirmado */
.fila-confirmada {
  background-color: rgba(248, 250, 252, 0.65);
}

@media print {
  .app-shell__sidebar, .app-header, .app-footer, .data-panel__toolbar, .data-panel__footer, .btn, .normativa-card button {
    display: none !important;
  }
  .data-panel {
    border: none !important;
    box-shadow: none !important;
  }
}
</style>

<!-- ---------- Resumen Estadístico (components/stat-card.php) ----------
     Las cuatro tarjetas (Progreso de Carga, Por Asignar, Confirmados Oficial y
     Promovidos) no se le pintan al docente. Son un resumen para quien administra
     el resultado del turno —dirección y coordinación—, y a un docente de aula
     única le repiten lo que ya tiene delante en la tabla: cuántos faltan, cuántos
     van y cuántos hay. Se le queda el trabajo (la tabla y sus decisiones) y se le
     quitan los contadores. Para los demás roles siguen igual. -->
<?php if (!$esDocente): ?>
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <?php
      $statLabel     = 'Progreso de Carga';
      $statValue     = "{$totalConLiteral} de {$totalEst} ({$porcentajeCarga}%)";
      $statIcon      = 'bi-check2-circle';
      $statTone      = 'primary';
      $statProgress  = $porcentajeCarga;
      $statMeta      = 'Literales asignados';
      include __DIR__ . '/../components/stat-card.php';
    ?>
  </div>
  <div class="col-6 col-lg-3">
    <?php
      $statLabel     = 'Por Asignar';
      $statValue     = "{$totalSinLiteral} estudiantes";
      $statIcon      = 'bi-pencil-square';
      $statTone      = 'warning';
      $statProgress  = null;
      $statMeta      = 'Pendientes de calificación';
      include __DIR__ . '/../components/stat-card.php';
    ?>
  </div>
  <div class="col-6 col-lg-3">
    <?php
      $statLabel     = 'Confirmados Oficial';
      $statValue     = "{$confirmados} de {$totalEst}";
      $statIcon      = 'bi-shield-check';
      $statTone      = 'success';
      $statProgress  = null;
      $statMeta      = 'Validados por Dirección';
      include __DIR__ . '/../components/stat-card.php';
    ?>
  </div>
  <div class="col-6 col-lg-3">
    <?php
      $statLabel     = 'Promovidos';
      $statValue     = "{$promovidos} estudiantes";
      $statIcon      = 'bi-mortarboard-fill';
      $statTone      = ($noPromovidos > 0) ? 'danger' : 'info';
      $statProgress  = null;
      $statMeta      = ($noPromovidos > 0) ? "{$noPromovidos} no promovido(s)" : 'Prosecución regular';
      include __DIR__ . '/../components/stat-card.php';
    ?>
  </div>
</div>

<?php endif; ?>

<!-- ---------- Tarjeta de Normativa Oficial (Resolución N° 266) ---------- -->
<div class="normativa-card shadow-sm mb-4">
  <div class="p-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div class="d-flex align-items-center gap-3">
      <div class="normativa-card__icon">
        <i class="bi bi-book" aria-hidden="true"></i>
      </div>
      <div>
        <h6 class="mb-0 fw-bold text-dark" style="font-size: 0.92rem;">Normativa de Escala Cualitativa Oficial</h6>
        <p class="mb-0 text-secondary small">Resolución N° 266 del Ministerio del Poder Popular para la Educación</p>
      </div>
    </div>
    <div>
      <button type="button" class="btn btn-link text-decoration-none text-primary fw-semibold btn-sm d-inline-flex align-items-center gap-1"
              data-bs-toggle="collapse" data-bs-target="#collapseCriterios" aria-expanded="false" aria-controls="collapseCriterios" id="btnToggleCriterios">
        <span>Ver criterios</span>
        <i class="bi bi-chevron-down" id="chevronCriterios"></i>
      </button>
    </div>
  </div>

  <div class="collapse border-top" id="collapseCriterios" style="border-color: #f1f5f9 !important;">
    <div class="p-3 bg-light bg-opacity-50">
      <div class="row g-2 mb-2">
        <div class="col-12 col-md-4">
          <div class="p-2 rounded bg-white border small">
            <span class="badge text-white me-1" style="background-color: #16a34a;">Literal A</span>
            <strong>Sobresaliente:</strong> Alcanzó todas las competencias del grado.
            <div class="text-success fw-semibold mt-1">Promovido al grado superior</div>
          </div>
        </div>
        <div class="col-12 col-md-4">
          <div class="p-2 rounded bg-white border small">
            <span class="badge text-white me-1" style="background-color: #2563eb;">Literal B</span>
            <strong>Muy Bueno:</strong> Alcanzó las competencias con buen desempeño.
            <div class="text-success fw-semibold mt-1">Promovido al grado superior</div>
          </div>
        </div>
        <div class="col-12 col-md-4">
          <div class="p-2 rounded bg-white border small">
            <span class="badge text-white me-1" style="background-color: #0284c7;">Literal C</span>
            <strong>Bueno:</strong> Alcanzó la mayoría de las competencias esenciales.
            <div class="text-success fw-semibold mt-1">Promovido al grado superior</div>
          </div>
        </div>
        <div class="col-12 col-md-6">
          <div class="p-2 rounded bg-white border small">
            <span class="badge text-white me-1" style="background-color: #d97706;">Literal D</span>
            <strong>Regular:</strong> Competencias mínimas; requiere acompañamiento.
            <div class="text-success fw-semibold mt-1">Promovido al grado superior</div>
          </div>
        </div>
        <div class="col-12 col-md-6">
          <div class="p-2 rounded bg-white border small">
            <span class="badge text-white me-1" style="background-color: #dc2626;">Literal E</span>
            <strong>No Promovido:</strong> No alcanzó competencias esenciales.
            <div class="text-danger fw-semibold mt-1">Permanece en el mismo grado</div>
          </div>
        </div>
      </div>
      <div class="text-end">
        <button type="button" class="btn btn-outline-primary btn-sm py-1 px-3" data-bs-toggle="modal" data-bs-target="#modalGuiaNormativa">
          <i class="bi bi-info-circle me-1"></i> Ver marco legal y reglas completas
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ---------- Formulario y Nómina de Evaluación (data-panel) ---------- -->
<form id="formResultados" method="POST" action="<?= BASE_URL ?>resultadoAcademico/guardarPropuesta">
  <?php /* Token de seguridad. Este formulario es un POST nativo, no pasa por fetch(),
           así que el token no se inyecta solo: el front controller rechaza con 403
           cualquier POST privado que llegue sin él. El mismo form sirve para la
           confirmación (el JS solo le cambia el action), de modo que este campo
           cubre las dos acciones. */ ?>
  <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
  <input type="hidden" name="id_estructura_academica" value="<?= $idEstructura ?>">

  <div class="data-panel mb-5">
    
    <!-- Barra de Herramientas de la Nómina (data-panel__toolbar) -->
    <div class="data-panel__toolbar d-flex align-items-center justify-content-between flex-wrap gap-3 p-3">
      
      <!-- Filtros de Pestañas estilo mockup -->
      <?php /* Si se pidió por la URL un filtro que no tiene nada que mostrar —por ejemplo
         ?estado=confirmados en una sección donde aún no se confirmó nada—, se cae a
         «todos». Una tabla vacía sin ningún botón activo parece una pantalla rota,
         y el enlace del panel solo usa «pendiente», así que esto es por si alguien
         guarda la URL a mano. */
if ($filtroEstadoInicial === 'CONFIRMADOS' && (int) ($confirmados ?? 0) <= 0) {
    $filtroEstadoInicial = 'todos';
}
if ($filtroEstadoInicial === 'PENDIENTE' && (int) ($totalSinLiteral ?? 0) <= 0) {
    $filtroEstadoInicial = 'todos';
}
if ($filtroEstadoInicial === 'CARGADOS' && (int) ($totalConLiteral ?? 0) <= 0) {
    $filtroEstadoInicial = 'todos';
}
?>
<div class="filter-pills-nav" role="group" aria-label="Filtro de estado">
        <?php /* El botón activo lo marca el servidor: si se entró con ?estado=pendiente,
                 la tabla tiene que verse filtrada desde el primer momento y no solo
                 con el botón pintado. */ ?>
        <button type="button" class="btn-filter-tab<?= ($filtroEstadoInicial ?? 'todos') === 'todos' ? ' active' : '' ?>" data-filter="todos">
          Todos (<?= $totalEst ?>)
        </button>
        <button type="button" class="btn-filter-tab<?= ($filtroEstadoInicial ?? 'todos') === 'PENDIENTE' ? ' active' : '' ?>" data-filter="PENDIENTE">
          Pendientes (<span id="filterCountPendientes"><?= $totalSinLiteral ?></span>)
        </button>
        <button type="button" class="btn-filter-tab<?= ($filtroEstadoInicial ?? 'todos') === 'CARGADOS' ? ' active' : '' ?>" data-filter="CARGADOS">
          Cargados (<span id="filterCountCargados"><?= $totalConLiteral ?></span>)
        </button>
        <?php if ($confirmados > 0): ?>
          <button type="button" class="btn-filter-tab<?= ($filtroEstadoInicial ?? 'todos') === 'CONFIRMADOS' ? ' active' : '' ?>" data-filter="CONFIRMADOS">
            Confirmados (<span id="filterCountConfirmados"><?= $confirmados ?></span>)
          </button>
        <?php endif; ?>
      </div>

      <!-- Buscador y Acciones -->
      <div class="d-flex align-items-center gap-2 flex-grow-1 flex-md-grow-0 justify-content-end" style="min-width: 280px;">
        
        <!-- Buscador en tiempo real -->
        <div class="position-relative flex-grow-1" style="max-width: 300px;">
          <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary" style="font-size: 0.85rem;" aria-hidden="true"></i>
          <input type="search" id="filtroBuscador" class="form-control form-control-sm ps-5 rounded-pill border-light-subtle shadow-none" 
                 placeholder="Buscar por Cédula o Nombre..." autocomplete="off">
        </div>

        <?php if ($esDocente): ?>
          <button type="submit" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1 shadow-sm" id="btnGuardarPropuesta">
            <i class="bi bi-floppy-fill" aria-hidden="true"></i>
            <span>Guardar Cambios</span>
          </button>
        <?php endif; ?>

        <?php if ($esDirectivo): ?>
          <button type="button" class="btn btn-success btn-sm d-inline-flex align-items-center gap-1 shadow-sm btn-abrir-confirmar">
            <i class="bi bi-check2-circle" aria-hidden="true"></i>
            <span>Confirmar Oficial</span>
          </button>
        <?php endif; ?>

      </div>

    </div>

    <!-- Tabla de Nómina (data-panel__body) -->
    <div class="data-panel__body table-responsive">
      <table class="table data-panel__table align-middle mb-0" id="tablaResultados">
        <thead>
          <tr class="text-secondary small">
            <th scope="col" style="width: 4%;" class="text-center">N°</th>
            <th scope="col" style="width: 30%;">ESTUDIANTE</th>
            <th scope="col" style="width: 14%;" class="text-center">LITERAL</th>
            <th scope="col" style="width: 22%;">DECISIÓN OFICIAL</th>
            <th scope="col" style="width: 30%;">OBSERVACIÓN PEDAGÓGICA</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($estudiantes)): ?>
            <tr>
              <td colspan="5" class="text-center py-5 text-muted">
                <i class="bi bi-people fs-2 d-block mb-2 text-secondary opacity-50"></i>
                No hay estudiantes cursando en esta sección.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($estudiantes as $idx => $est): ?>
              <?php
                $idEst       = (int) $est['id_estudiante'];
                $idMat       = (int) $est['id_matricula'];
                $literal     = strtoupper(trim($est['literal'] ?? ''));
                $estado      = $est['estado_resultado'] ?? 'PENDIENTE';
                $idGradoDest = (int) ($est['id_grado_destino'] ?? 0);
                $obs         = htmlspecialchars($est['observaciones'] ?? '');
                $nombreEst   = htmlspecialchars($est['nombre_completo'] ?? '');
                $docTipo     = htmlspecialchars($est['tipo_documento'] ?? '');
                $docNum      = htmlspecialchars($est['numero_documento'] ?? '');

                $partesNombre   = explode(' ', trim($nombreEst));
                $avatarInitials = strtoupper(substr($partesNombre[0] ?? 'E', 0, 1) . substr($partesNombre[1] ?? 'S', 0, 1));

                $propuestoPor  = htmlspecialchars($est['propuesto_por_nombre'] ?? '');
                $fechaProp     = !empty($est['fecha_propuesta']) ? date('d/m/Y', strtotime($est['fecha_propuesta'])) : '';
                $confirmadoPor = htmlspecialchars($est['confirmado_por_nombre'] ?? '');
                $fechaConf     = !empty($est['fecha_confirmacion']) ? date('d/m/Y', strtotime($est['fecha_confirmacion'])) : '';

                $estaConfirmado = !empty($est['fecha_confirmacion']) || in_array($estado, ['PROMOVIDO', 'NO_PROMOVIDO'], true);
                /* Una fila se bloquea si ya la confirmó la dirección o si quien mira
                   no es el docente de la sección: solo el docente propone, así que
                   el resto de roles —incluido el admin— la ven en lectura. */
                $bloquearFila   = $estaConfirmado || !$esDocente;
              ?>
              <tr data-student-row="<?= $idEst ?>" 
                  data-estado-actual="<?= $estado ?>" 
                  data-tiene-literal="<?= ($literal !== '') ? '1' : '0' ?>"
                  data-esta-confirmado="<?= $estaConfirmado ? '1' : '0' ?>"
                  class="fila-estudiante <?= $estaConfirmado ? 'fila-confirmada' : '' ?>">
                
                <td class="text-center text-muted fw-semibold small">
                  <?= sprintf('%02d', $idx + 1) ?>
                  <!-- id_matricula se envía para auditoría y conteo exacto de omisiones en el backend -->
                  <input type="hidden" name="estudiantes[<?= $idEst ?>][id_matricula]" value="<?= $idMat ?>">
                </td>

                <td>
                  <div class="d-flex align-items-center gap-2">
                    <?php
                      $avatarSize = 'sm';
                      $avatarTone = 'primary';
                      include __DIR__ . '/../components/avatar.php';
                    ?>
                    <div>
                      <strong class="nombre-estudiante d-block text-dark" style="font-size: 0.88rem;"><?= $nombreEst ?></strong>
                      <span class="documento-estudiante text-secondary small">
                        <?= !empty($docTipo) ? "{$docTipo}: {$docNum}" : ($docNum ?: 'Sin documento') ?>
                      </span>
                    </div>
                  </div>
                </td>

                <td class="text-center">
                  <div class="d-inline-flex align-items-center gap-1 position-relative">
                    <select name="estudiantes[<?= $idEst ?>][literal]"
                            class="select-literal-pill"
                            data-id="<?= $idEst ?>"
                            data-literal="<?= $literal ?>"
                            <?= $bloquearFila ? 'disabled' : '' ?>
                            aria-label="Literal para <?= $nombreEst ?>">
                      <option value="" <?= ($literal === '') ? 'selected' : '' ?>>--</option>
                      <option value="A" <?= ($literal === 'A') ? 'selected' : '' ?>>A</option>
                      <option value="B" <?= ($literal === 'B') ? 'selected' : '' ?>>B</option>
                      <option value="C" <?= ($literal === 'C') ? 'selected' : '' ?>>C</option>
                      <option value="D" <?= ($literal === 'D') ? 'selected' : '' ?>>D</option>
                      <option value="E" <?= ($literal === 'E') ? 'selected' : '' ?>>E</option>
                    </select>
                    <?php if ($estaConfirmado): ?>
                      <span class="badge bg-light text-secondary border px-1 py-1" title="Confirmado por <?= $confirmadoPor ?> el <?= $fechaConf ?>">
                        <i class="bi bi-lock-fill text-muted" aria-hidden="true"></i>
                      </span>
                    <?php endif; ?>
                  </div>
                </td>

                <td>
                  <input type="hidden" name="estudiantes[<?= $idEst ?>][estado_resultado]"
                         id="input_estado_<?= $idEst ?>" value="<?= $estado ?>">
                  <input type="hidden" name="estudiantes[<?= $idEst ?>][id_grado_destino]"
                         id="input_grado_<?= $idEst ?>" value="<?= $idGradoDest ?>">
                  
                  <div id="badge_estado_<?= $idEst ?>" class="mb-1">
                    <?php if ($estado === 'PROMOVIDO'): ?>
                      <span class="decision-badge decision-badge--promovido">
                        <i class="bi bi-check-circle-fill" aria-hidden="true"></i> PROMOVIDO
                      </span>
                    <?php elseif ($estado === 'NO_PROMOVIDO'): ?>
                      <span class="decision-badge decision-badge--no-promovido">
                        <i class="bi bi-x-circle-fill" aria-hidden="true"></i> NO PROMOVIDO
                      </span>
                    <?php else: ?>
                      <span class="decision-badge decision-badge--pendiente">
                        <i class="bi bi-clock-fill" aria-hidden="true"></i> PENDIENTE
                      </span>
                    <?php endif; ?>
                  </div>

                  <div id="proyeccion_grado_<?= $idEst ?>" class="text-secondary small" style="font-size: 0.76rem;">
                    <?php if ($estado === 'PROMOVIDO'): ?>
                      <span class="text-primary"><i class="bi bi-arrow-right me-1"></i>Avanza: <strong><?= $textoGradoAvanza ?></strong></span>
                    <?php elseif ($estado === 'NO_PROMOVIDO'): ?>
                      <span class="text-danger"><i class="bi bi-arrow-repeat me-1"></i>Permanece: <strong><?= $gradoNombre ?></strong></span>
                    <?php else: ?>
                      <span class="text-muted"><i class="bi bi-dash-circle me-1"></i>Por evaluar</span>
                    <?php endif; ?>
                    
                    <?php if ($estaConfirmado && !empty($fechaConf)): ?>
                      <span class="text-success ms-1" title="Confirmado oficialmente por <?= $confirmadoPor ?>">
                        · <i class="bi bi-shield-check"></i> <?= $fechaConf ?>
                      </span>
                    <?php endif; ?>
                  </div>
                </td>

                <td>
                  <input type="text" name="estudiantes[<?= $idEst ?>][observaciones]"
                         class="form-control form-control-sm input-obs-pedagogica"
                         value="<?= $obs ?>"
                         placeholder="Ingresar observación pedagógica..."
                         maxlength="255"
                         <?= $bloquearFila ? 'readonly disabled' : '' ?>>
                </td>

              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Footer de la Tabla (data-panel__footer) -->
    <div class="data-panel__footer d-flex justify-content-between align-items-center flex-wrap gap-2 p-3">
      <span class="text-secondary small">
        <i class="bi bi-shield-lock me-1"></i> Seguridad institucional: los registros confirmados por Dirección quedan blindados contra modificaciones.
      </span>
      <div class="d-flex gap-2">
        <?php /* El guardar del docente vive arriba, en la barra de la tabla, junto a
               los contadores que van cambiando según carga. Antes había un segundo
               «Guardar Cambios» aquí abajo que hacía la misma cosa: dos botones para
               la misma acción, y el de abajo solo se alcanzaba al final de una tabla
               larga. Este pie queda para la confirmación, que es otro acto y es del
               directivo. */ ?>
        <?php if ($esDirectivo): ?>
          <button type="button" class="btn btn-success btn-sm d-inline-flex align-items-center gap-1 shadow-sm btn-abrir-confirmar">
            <i class="bi bi-check2-circle" aria-hidden="true"></i>
            <span>Confirmar Resultados Oficiales</span>
          </button>
        <?php endif; ?>
      </div>
    </div>

  </div>
</form>

<?php
$pageContent = ob_get_clean();

// ---------------------------------------------------------
// Modales de la vista (Normativa y Confirmación Directiva)
// ---------------------------------------------------------
ob_start();
?>

<!-- Modal: Criterios Normativos -->
<div class="modal fade app-modal" id="modalGuiaNormativa" tabindex="-1" aria-labelledby="modalGuiaNormativaLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="modalGuiaNormativaLabel">
            <i class="bi bi-book-half me-2 text-primary"></i>Criterios de Evaluación y Promoción Anual
          </h5>
          <p class="modal-header__subtitle">Escala cualitativa de literales y efectos de promoción en Educación Primaria</p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <div class="modal-body">
        
        <div class="info-alert mb-3">
          <div class="info-alert__icon"><i class="bi bi-info-circle-fill" aria-hidden="true"></i></div>
          <div>
            <div class="info-alert__title">Marco Institucional y Pedagógico (Normativa MPPE)</div>
            <div class="info-alert__text">
              La promoción al grado inmediato superior es el resultado pedagógico oficial correspondiente al grado cursado. Los resultados deben registrarse con estricta veracidad y trazabilidad antes del cierre del año escolar.
            </div>
          </div>
        </div>

        <h6 class="fw-bold mb-2 text-primary" style="font-size: 0.9rem;">
          <i class="bi bi-card-checklist me-1 text-primary"></i> Escala Cualitativa de Literales
        </h6>

        <div class="d-flex flex-column gap-2 mb-3">
          
          <!-- Literal A -->
          <div class="p-2 px-3 rounded border d-flex flex-wrap align-items-center justify-content-between gap-2" style="background-color: #f8f9fb; border-color: #e3e7f0 !important;">
            <div class="d-flex align-items-center gap-3">
              <span class="badge text-white px-2 py-1" style="background-color: #16a34a; font-weight: 700;">Literal A</span>
              <div>
                <strong class="text-primary" style="font-size: 0.85rem;">Alcanzó todas las competencias del grado de manera sobresaliente</strong>
                <span class="text-secondary small d-block">Avanza al grado inmediato superior</span>
              </div>
            </div>
            <span class="status-badge status-badge--active">PROMOVIDO</span>
          </div>

          <!-- Literal B -->
          <div class="p-2 px-3 rounded border d-flex flex-wrap align-items-center justify-content-between gap-2" style="background-color: #f8f9fb; border-color: #e3e7f0 !important;">
            <div class="d-flex align-items-center gap-3">
              <span class="badge text-white px-2 py-1" style="background-color: #2563eb; font-weight: 700;">Literal B</span>
              <div>
                <strong class="text-primary" style="font-size: 0.85rem;">Alcanzó todas las competencias con un desempeño muy satisfactorio</strong>
                <span class="text-secondary small d-block">Avanza al grado inmediato superior</span>
              </div>
            </div>
            <span class="status-badge status-badge--active">PROMOVIDO</span>
          </div>

          <!-- Literal C -->
          <div class="p-2 px-3 rounded border d-flex flex-wrap align-items-center justify-content-between gap-2" style="background-color: #f8f9fb; border-color: #e3e7f0 !important;">
            <div class="d-flex align-items-center gap-3">
              <span class="badge text-white px-2 py-1" style="background-color: #0284c7; font-weight: 700;">Literal C</span>
              <div>
                <strong class="text-primary" style="font-size: 0.85rem;">Alcanzó la mayoría de las competencias esenciales del grado</strong>
                <span class="text-secondary small d-block">Avanza al grado inmediato superior</span>
              </div>
            </div>
            <span class="status-badge status-badge--active">PROMOVIDO</span>
          </div>

          <!-- Literal D -->
          <div class="p-2 px-3 rounded border d-flex flex-wrap align-items-center justify-content-between gap-2" style="background-color: #f8f9fb; border-color: #e3e7f0 !important;">
            <div class="d-flex align-items-center gap-3">
              <span class="badge text-white px-2 py-1" style="background-color: #d97706; font-weight: 700;">Literal D</span>
              <div>
                <strong class="text-primary" style="font-size: 0.85rem;">Alcanzó algunas competencias mínimas; requiere acompañamiento</strong>
                <span class="text-secondary small d-block">Avanza al grado inmediato superior</span>
              </div>
            </div>
            <span class="status-badge status-badge--active">PROMOVIDO</span>
          </div>

          <!-- Literal E -->
          <div class="p-2 px-3 rounded border d-flex flex-wrap align-items-center justify-content-between gap-2" style="background-color: rgba(220, 38, 38, 0.04); border-color: rgba(220, 38, 38, 0.2) !important;">
            <div class="d-flex align-items-center gap-3">
              <span class="badge text-white px-2 py-1" style="background-color: #dc2626; font-weight: 700;">Literal E</span>
              <div>
                <strong class="text-danger" style="font-size: 0.85rem;">No alcanzó las competencias esenciales requeridas para avanzar</strong>
                <span class="text-danger small d-block">Permanece en el mismo grado cursado</span>
              </div>
            </div>
            <span class="status-badge status-badge--inactive">NO PROMOVIDO</span>
          </div>

        </div>

        <h6 class="fw-bold mb-2 mt-3 text-primary" style="font-size: 0.9rem;">
          <i class="bi bi-shield-check me-1 text-primary"></i> Reglas de Integridad y Trazabilidad
        </h6>
        <ul class="text-secondary small mb-0 ps-3">
          <li class="mb-1"><strong>Independencia del retiro:</strong> El retiro de un estudiante mantiene su resultado como pendiente; no se presume promovido ni no promovido.</li>
          <li class="mb-1"><strong>Egreso de 6to Grado:</strong> Solo los estudiantes con resultado <em>Promovido</em> formalmente confirmado por Dirección pueden ser graduados.</li>
          <li><strong>Cierre de Año Escolar:</strong> La Dirección no podrá cerrar el año escolar si existen estudiantes cursando con resultado pendiente de confirmar.</li>
        </ul>

      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Entendido</button>
      </div>

    </div>
  </div>
</div>

<?php if ($esDirectivo): ?>
<!-- Modal: Confirmación Oficial Directiva -->
<div class="modal fade app-modal" id="modalConfirmarResultados" tabindex="-1" aria-labelledby="modalConfirmarResultadosLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="modalConfirmarResultadosLabel">
            <i class="bi bi-shield-check me-2 text-primary"></i>Confirmación Oficial de Promoción
          </h5>
          <p class="modal-header__subtitle">Sección <?= $gradoNombre ?> "<?= $seccionNombre ?>" · Período <?= $anioNombre ?></p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <div class="modal-body">
        <p class="text-secondary small mb-3">
          Está a punto de <strong>confirmar oficialmente</strong> los resultados de fin de año. Los estudiantes con literal confirmado quedarán sellados y listos para prosecución escolar.
        </p>

        <div class="p-3 rounded border mb-3 small" style="background-color: #f8f9fb; border-color: #e3e7f0 !important;">
          <div class="d-flex justify-content-between mb-1">
            <span class="text-secondary">Estudiantes en nómina:</span>
            <strong id="modalResumenTotal" class="text-primary">0</strong>
          </div>
          <div class="d-flex justify-content-between mb-1 text-success">
            <span>A confirmar como Promovidos:</span>
            <strong id="modalResumenPromovidos">0</strong>
          </div>
          <div class="d-flex justify-content-between mb-1 text-danger">
            <span>A confirmar como No Promovidos:</span>
            <strong id="modalResumenNoPromovidos">0</strong>
          </div>
          <div class="d-flex justify-content-between mb-1 text-warning">
            <span>Sin literal (permanecerán pendientes):</span>
            <strong id="modalResumenSinLiteral">0</strong>
          </div>
          <div class="d-flex justify-content-between text-muted border-top pt-1 mt-1">
            <span>Ya confirmados previamente (protegidos):</span>
            <strong id="modalResumenYaConfirmados">0</strong>
          </div>
        </div>

        <div class="info-alert">
          <div class="info-alert__icon"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i></div>
          <div>
            <div class="info-alert__title">Aviso de Cierre</div>
            <div class="info-alert__text">
              Una vez confirmados, los resultados quedan blindados y no podrán ser modificados.
            </div>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-success btn-sm" id="btnEjecutarConfirmacion">
          <i class="bi bi-check2-circle me-1"></i> Confirmar y Oficializar
        </button>
      </div>

    </div>
  </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const idGradoActual    = <?= (int) $idGradoActual ?>;
  const idGradoSiguiente = <?= json_encode($idGradoSiguiente) ?>;
  const textoGradoAvanza = <?= json_encode($textoGradoAvanza) ?>;
  const gradoNombre      = <?= json_encode($gradoNombre) ?>;
  const totalEstudiantes = <?= (int) $totalEst ?>;
  const formResultados   = document.getElementById('formResultados');



  // Rotar chevron de la tarjeta de normativa
  const collapseCriterios = document.getElementById('collapseCriterios');
  const chevronCriterios  = document.getElementById('chevronCriterios');
  if (collapseCriterios && chevronCriterios) {
    collapseCriterios.addEventListener('show.bs.collapse', function() {
      chevronCriterios.classList.remove('bi-chevron-down');
      chevronCriterios.classList.add('bi-chevron-up');
    });
    collapseCriterios.addEventListener('hide.bs.collapse', function() {
      chevronCriterios.classList.remove('bi-chevron-up');
      chevronCriterios.classList.add('bi-chevron-down');
    });
  }

  function actualizarFila(selectLiteral) {
    const studentId   = selectLiteral.dataset.id;
    const literal     = selectLiteral.value.toUpperCase().trim();
    const inputEstado = document.getElementById('input_estado_' + studentId);
    const badgeEstado = document.getElementById('badge_estado_' + studentId);
    const proyGrado   = document.getElementById('proyeccion_grado_' + studentId);
    const inputGrado  = document.getElementById('input_grado_' + studentId);
    const fila        = selectLiteral.closest('tr');

    // Actualizar estilo visual del select pill
    selectLiteral.setAttribute('data-literal', literal);

    if (['A', 'B', 'C', 'D'].includes(literal)) {
      if (inputEstado) inputEstado.value = 'PROMOVIDO';
      if (fila) {
        fila.setAttribute('data-estado-actual', 'PROMOVIDO');
        fila.setAttribute('data-tiene-literal', '1');
      }
      if (badgeEstado) {
        badgeEstado.innerHTML = '<span class="decision-badge decision-badge--promovido"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> PROMOVIDO</span>';
      }
      if (proyGrado) {
        proyGrado.innerHTML = '<span class="text-primary"><i class="bi bi-arrow-right me-1"></i>Avanza: <strong>' + textoGradoAvanza + '</strong></span>';
      }
      if (inputGrado && idGradoSiguiente) {
        inputGrado.value = idGradoSiguiente;
      }
    } else if (literal === 'E') {
      if (inputEstado) inputEstado.value = 'NO_PROMOVIDO';
      if (fila) {
        fila.setAttribute('data-estado-actual', 'NO_PROMOVIDO');
        fila.setAttribute('data-tiene-literal', '1');
      }
      if (badgeEstado) {
        badgeEstado.innerHTML = '<span class="decision-badge decision-badge--no-promovido"><i class="bi bi-x-circle-fill" aria-hidden="true"></i> NO PROMOVIDO</span>';
      }
      if (proyGrado) {
        proyGrado.innerHTML = '<span class="text-danger"><i class="bi bi-arrow-repeat me-1"></i>Permanece: <strong>' + gradoNombre + '</strong></span>';
      }
      if (inputGrado) {
        inputGrado.value = idGradoActual;
      }
    } else {
      if (inputEstado) inputEstado.value = 'PENDIENTE';
      if (fila) {
        fila.setAttribute('data-estado-actual', 'PENDIENTE');
        fila.setAttribute('data-tiene-literal', '0');
      }
      if (badgeEstado) {
        badgeEstado.innerHTML = '<span class="decision-badge decision-badge--pendiente"><i class="bi bi-clock-fill" aria-hidden="true"></i> PENDIENTE</span>';
      }
      if (proyGrado) {
        proyGrado.innerHTML = '<span class="text-muted"><i class="bi bi-dash-circle me-1"></i>Por evaluar</span>';
      }
      if (inputGrado) {
        inputGrado.value = '';
      }
    }
    actualizarContadores();
  }

  function actualizarContadores() {
    let conLiteral = 0, sinLiteral = 0, prom = 0, noProm = 0;
    
    document.querySelectorAll('.fila-estudiante').forEach(function(fila) {
      const select = fila.querySelector('.select-literal-pill');
      const val = select ? select.value.toUpperCase().trim() : '';
      if (val !== '') {
        conLiteral++;
        if (['A', 'B', 'C', 'D'].includes(val)) {
          prom++;
        } else if (val === 'E') {
          noProm++;
        }
      } else {
        sinLiteral++;
      }
    });

    const pct = totalEstudiantes > 0 ? Math.round((conLiteral / totalEstudiantes) * 100) : 0;

    // Actualizar Stat Card de Progreso
    const cardProgreso = document.querySelector('.stat-card__icon--primary')?.closest('.stat-card');
    if (cardProgreso) {
      const valEl = cardProgreso.querySelector('.stat-card__value');
      if (valEl) valEl.textContent = conLiteral + ' de ' + totalEstudiantes + ' (' + pct + '%)';
      const barEl = cardProgreso.querySelector('.stat-card__bar span');
      if (barEl) barEl.style.width = pct + '%';
    }

    // Actualizar Stat Card Por Asignar
    const cardPorAsignar = document.querySelector('.stat-card__icon--warning')?.closest('.stat-card');
    if (cardPorAsignar) {
      const valEl = cardPorAsignar.querySelector('.stat-card__value');
      if (valEl) valEl.textContent = sinLiteral + ' estudiantes';
    }

    // Actualizar Stat Card Promovidos
    const cardProm = document.querySelector('.stat-card__icon--info, .stat-card__icon--danger')?.closest('.stat-card');
    if (cardProm) {
      const valEl = cardProm.querySelector('.stat-card__value');
      if (valEl) valEl.textContent = prom + ' estudiantes';
      const metaEl = cardProm.querySelector('.stat-card__meta');
      if (metaEl) metaEl.textContent = noProm > 0 ? (noProm + ' no promovido(s)') : 'Prosecución regular';
    }

    // Actualizar contadores en las pestañas de filtros
    const fPend = document.getElementById('filterCountPendientes');
    const fCarg = document.getElementById('filterCountCargados');
    if (fPend) fPend.textContent = sinLiteral;
    if (fCarg) fCarg.textContent = conLiteral;
  }

  // Escuchar cambios en los selectores de literales
  document.querySelectorAll('.select-literal-pill').forEach(function(sel) {
    sel.addEventListener('change', function() {
      actualizarFila(this);
    });
  });

  // -------------------------------------------------------------
  // Buscador y Filtros por Pestaña
  // -------------------------------------------------------------
  const inputBuscador = document.getElementById('filtroBuscador');
  /* Nace con el filtro que pidió la URL (?estado=pendiente desde el panel del
     docente), no siempre en «todos». Abajo se llama a filtrarTabla() para que las
     filas queden ocultas desde el primer momento y no solo con el botón pintado. */
  let filtroTabActual = <?= json_encode($filtroEstadoInicial ?? 'todos', JSON_UNESCAPED_UNICODE) ?>;

  function filtrarTabla() {
    const texto = inputBuscador ? inputBuscador.value.toLowerCase().trim() : '';

    document.querySelectorAll('.fila-estudiante').forEach(function(fila) {
      const nombre         = fila.querySelector('.nombre-estudiante')?.textContent.toLowerCase() || '';
      const doc            = fila.querySelector('.documento-estudiante')?.textContent.toLowerCase() || '';
      const tieneLiteral   = fila.getAttribute('data-tiene-literal') === '1';
      const estaConfirmado = fila.getAttribute('data-esta-confirmado') === '1';

      const coincideTexto = (texto === '' || nombre.includes(texto) || doc.includes(texto));

      let coincideTab = false;
      if (filtroTabActual === 'todos') {
        coincideTab = true;
      } else if (filtroTabActual === 'PENDIENTE') {
        coincideTab = !tieneLiteral;
      } else if (filtroTabActual === 'CARGADOS') {
        coincideTab = tieneLiteral;
      } else if (filtroTabActual === 'CONFIRMADOS') {
        coincideTab = estaConfirmado;
      }

      if (coincideTexto && coincideTab) {
        fila.style.display = '';
      } else {
        fila.style.display = 'none';
      }
    });
  }

  if (inputBuscador) {
    inputBuscador.addEventListener('input', filtrarTabla);
  }

  document.querySelectorAll('.btn-filter-tab').forEach(function(btn) {
    btn.addEventListener('click', function() {
      document.querySelectorAll('.btn-filter-tab').forEach(b => b.classList.remove('active'));
      this.classList.add('active');
      filtroTabActual = this.dataset.filter;
      filtrarTabla();
    });
  });

  /* Si se entró con ?estado=..., se aplica el filtro desde el primer momento:
     el botón llega activo desde el servidor, pero sin esto las filas seguirían
     todas visibles hasta que el usuario tocara algo. */
  filtrarTabla();

  // -------------------------------------------------------------
  // Modal de confirmación para directivos
  // -------------------------------------------------------------
  const modalEl = document.getElementById('modalConfirmarResultados');
  if (modalEl) {
    const modalBs = new bootstrap.Modal(modalEl);

    function abrirModal() {
      let total = 0, aConfirmarProm = 0, aConfirmarNoProm = 0, sinLit = 0, yaConf = 0;
      
      document.querySelectorAll('.fila-estudiante').forEach(function(fila) {
        total++;
        const estaConf = fila.getAttribute('data-esta-confirmado') === '1';
        const select   = fila.querySelector('.select-literal-pill');
        const val      = select ? select.value.toUpperCase().trim() : '';

        if (estaConf) {
          yaConf++;
        } else if (['A', 'B', 'C', 'D'].includes(val)) {
          aConfirmarProm++;
        } else if (val === 'E') {
          aConfirmarNoProm++;
        } else {
          sinLit++;
        }
      });

      document.getElementById('modalResumenTotal').textContent          = total;
      document.getElementById('modalResumenPromovidos').textContent      = aConfirmarProm;
      document.getElementById('modalResumenNoPromovidos').textContent    = aConfirmarNoProm;
      document.getElementById('modalResumenSinLiteral').textContent      = sinLit;
      document.getElementById('modalResumenYaConfirmados').textContent   = yaConf;

      modalBs.show();
    }

    document.querySelectorAll('.btn-abrir-confirmar').forEach(function(btn) {
      btn.addEventListener('click', abrirModal);
    });

    const btnEjecutar = document.getElementById('btnEjecutarConfirmacion');
    if (btnEjecutar && formResultados) {
      btnEjecutar.addEventListener('click', function() {
        formResultados.action = '<?= BASE_URL ?>resultadoAcademico/confirmar';
        modalBs.hide();
        formResultados.submit();
      });
    }
  }
});
</script>

<?php
$pageModals = ob_get_clean();
require __DIR__ . '/../layouts/app.php';
