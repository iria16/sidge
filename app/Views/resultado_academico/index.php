<?php
/**
 * Vista: resultado_academico/index.php
 * Bandeja principal de resultados académicos y promoción escolar.
 *
 * Utiliza los componentes y variables del sistema de diseño SIGDE:
 * - components/page-header.php
 * - components/stat-card.php
 * - components/status-badge.php
 * - components/avatar.php
 * - Estilos SCSS: _variables.scss, _content-card.scss, _data-panel.scss, _modal.scss
 *
 * @var array  $secciones
 * @var array  $anios
 * @var int    $idAnio
 * @var string $nombreAnioActivo
 * @var string $nombreUsuarioActual
 * @var string $rolUsuario
 * @var string|null $flash_error
 * @var string|null $flash_success
 */

$esDocente = ($rolUsuario === 'docente');
$pageTitle = $esDocente ? 'Mi Sección · Resultados y Promoción' : 'Promoción Académica Anual';
$pageDescription = $esDocente
    ? 'Carga de propuesta pedagógica cualitativa de fin de curso para su aula asignada.'
    : 'Gestión y confirmación oficial de resultados académicos de fin de año escolar (Promoción y No Promoción).';
$currentNav = $esDocente ? 'mi-seccion-promocion' : 'promocion';

$breadcrumbs = $esDocente
    ? [
        ['label' => 'Mi sección', 'href' => null],
        ['label' => 'Resultados y Promoción', 'href' => null],
    ]
    : [
        ['label' => 'Gestión de estudiantes', 'href' => null],
        ['label' => 'Promoción Académica', 'href' => null],
    ];

/* Totales estadísticos.
   El docente y Dirección miran cosas distintas y por eso no comparten cifras:
   para el docente cuenta lo que ÉL evaluó (literales asignados) y cuánto de eso
   Dirección ya validó; para Dirección cuenta el resultado oficial. Antes las
   tarjetas del docente se armaban con los mismos números confirmados, así que
   un docente con el aula enteramente evaluada veía «0 promovidos» y «4
   pendientes»: sus resultados no aparecían por ninguna parte. */
$totalSecciones   = count($secciones);
$totalEstudiantes = 0;
$totalPromovidos  = 0;
$totalNoPromovidos= 0;
$totalPendientes  = 0;
$totalRegistrados = 0;
$totalConfirmados = 0;

foreach ($secciones as $sec) {
    $totalEstudiantes  += (int) ($sec['total_estudiantes'] ?? 0);
    $totalPromovidos   += (int) ($sec['total_promovidos'] ?? 0);
    $totalNoPromovidos += (int) ($sec['total_no_promovidos'] ?? 0);
    $totalPendientes   += (int) ($sec['total_pendientes'] ?? 0);
    $totalRegistrados  += (int) ($sec['total_registrados'] ?? 0);
    $totalConfirmados  += (int) ($sec['total_confirmados'] ?? 0);
}

$totalRegistrados = min($totalRegistrados, $totalEstudiantes);
$totalConfirmados = min($totalConfirmados, $totalRegistrados);
$totalSinEvaluar  = max(0, $totalEstudiantes - $totalRegistrados);
/* Pendiente de confirmar es solo lo que YA está evaluado y Dirección aún no
   validó. Antes se usaba `total_pendientes`, que además de estos incluye a quien
   no tiene literal: a un alumno sin evaluar no se le puede pedir confirmación. */
$totalPendientesConfirmacion = max(0, $totalRegistrados - $totalConfirmados);

$porcentajeEvaluado = ($totalEstudiantes > 0)
    ? (int) round(($totalRegistrados / $totalEstudiantes) * 100)
    : 0;

/* El porcentaje de la tarjeta «Pendientes de Confirmación» mantiene su
   significado original para Dirección —resultado oficial sobre matrícula—, que
   es lo que sus otras tres tarjetas miden. Para el docente se usa en su lugar
   $porcentajeEvaluado, más abajo en su bloque de tarjetas. */
$porcentajeConfirmado = ($totalEstudiantes > 0)
    ? (int) round((($totalPromovidos + $totalNoPromovidos) / $totalEstudiantes) * 100)
    : 0;

$nombrePeriodo = !empty($nombreAnioActivo) ? $nombreAnioActivo : (!empty($anios[0]['nombre_periodo']) ? $anios[0]['nombre_periodo'] : 'En curso');
$nombreDocente = !empty($nombreUsuarioActual) ? $nombreUsuarioActual : ($_SESSION['nombre'] ?? 'Docente');

ob_start();

// ---------- Page Header Actions (Lado derecho del encabezado estándar) ----------
ob_start();
?>
<div class="d-flex align-items-center gap-2 flex-wrap">
  <?php if ($esDocente): ?>
    <span class="status-badge status-badge--active py-2 px-3">
      <i class="bi bi-calendar-check me-1"></i> Período Activo: <strong><?= htmlspecialchars($nombrePeriodo) ?></strong>
    </span>
  <?php else: ?>
    <form method="GET" action="<?= BASE_URL ?>resultadoAcademico/index" class="d-flex align-items-center gap-2 m-0">
      <div class="data-panel__year-filter">
        <i class="bi bi-calendar3" aria-hidden="true"></i>
        <select id="filtroAnio" name="anio" class="form-select form-select-sm" aria-label="Filtrar período" onchange="this.form.submit()">
          <?php foreach ($anios as $a): ?>
            <?php
              $idA = (int) ($a['id_anio_escolar'] ?? $a['id'] ?? 0);
              $nombreA = $a['nombre_periodo'] ?? $a['nombre'] ?? '';
              $estadoA = $a['estado'] ?? '';
              $esActivo = ($estadoA === 'en_curso' || strtoupper((string)$estadoA) === 'ACTIVO');
            ?>
            <option value="<?= $idA ?>" <?= ($idA === $idAnio) ? 'selected' : '' ?>>
              <?= htmlspecialchars($nombreA) ?> <?= $esActivo ? '· (Activo)' : '(' . htmlspecialchars($estadoA) . ')' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>
  <?php endif; ?>

  <button type="button" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1"
          data-bs-toggle="modal" data-bs-target="#modalGuiaNormativa">
    <i class="bi bi-info-circle" aria-hidden="true"></i>
    <span>Criterios de Promoción</span>
  </button>
</div>
<?php
$pageHeaderActions = ob_get_clean();
$pageHeading       = $pageTitle;
$pageSubheading    = $pageDescription;
include __DIR__ . '/../components/page-header.php';
?>

<?php if ($totalSecciones > 0): ?>

  <!-- ---------- Tarjetas Estadísticas (components/stat-card.php) ---------- -->
  <?php /* El primer contador, «Estudiantes en mi Aula», no se le pinta al docente:
           es el número de su clase, que ya tiene delante en la tabla y en el
           título de la pantalla. Las otras tres —promovidos, no promovidos y
           pendientes de confirmación— sí se quedan, porque son el estado de la
           corrección y de lo que falta por validar. Para los demás roles quedan
           las cuatro.

           Las tarjetas usan además una columna distinta según cuántas haya: con
           las cuatro, una cuarta parte; al docente, que ve tres, un tercio para
           que ocupen la fila entera y no quede un hueco vacío a la derecha. */ ?>
  <div class="row g-3 mb-4">
    <?php $colTarjeta = $esDocente ? 'col-12 col-sm-4 col-xl-4' : 'col-12 col-sm-6 col-xl-3'; ?>
    <?php if (!$esDocente): ?>
      <div class="<?= $colTarjeta ?>">
        <?php
          $statLabel     = 'Estudiantes en Nómina';
          $statValue     = number_format($totalEstudiantes, 0, ',', '.');
          $statIcon      = 'bi-people-fill';
          $statTone      = 'primary';
          $statTrend     = "{$totalSecciones} sección" . ($totalSecciones === 1 ? '' : 'es');
          $statTrendType = 'neutral';
          include __DIR__ . '/../components/stat-card.php';
        ?>
      </div>
    <?php endif; ?>
    <?php /* Al docente no le sirven «Promovidos» ni «No promovidos» mientras Dirección
             no ha confirmado nada: salían en cero con el aula ya evaluada. Lo que
             necesita es cuánto evaluó, cuánto le falta y cuánto de lo suyo ya está
             validado. Las tres cifras salen de `total_registrados` y
             `total_confirmados`, que el modelo ya traía. */ ?>
    <?php if ($esDocente): ?>
      <div class="<?= $colTarjeta ?>">
        <?php
          $statLabel     = 'Evaluados';
          $statValue     = number_format($totalRegistrados, 0, ',', '.');
          $statIcon      = 'bi-check2-circle';
          $statTone      = 'success';
          $statTrend     = "{$porcentajeEvaluado}% del aula";
          $statTrendType = ($porcentajeEvaluado === 100) ? 'up' : 'neutral';
          $statProgress  = $porcentajeEvaluado;
          include __DIR__ . '/../components/stat-card.php';
        ?>
      </div>
      <div class="<?= $colTarjeta ?>">
        <?php
          $statLabel     = 'Sin evaluar';
          $statValue     = number_format($totalSinEvaluar, 0, ',', '.');
          $statIcon      = 'bi-pencil-square';
          $statTone      = ($totalSinEvaluar === 0) ? 'success' : 'warning';
          $statTrend     = ($totalSinEvaluar === 0) ? 'Aula completa' : 'Sin literal asignado';
          $statTrendType = ($totalSinEvaluar === 0) ? 'up' : 'neutral';
          include __DIR__ . '/../components/stat-card.php';
        ?>
      </div>
      <div class="<?= $colTarjeta ?>">
        <?php
          $statLabel     = 'Pendientes de Confirmación';
          $statValue     = number_format($totalPendientesConfirmacion, 0, ',', '.');
          $statIcon      = 'bi-clock-history';
          $statTone      = ($totalPendientesConfirmacion === 0) ? 'success' : 'warning';
          $statTrend     = "{$totalConfirmados} ya validados";
          $statTrendType = 'neutral';
          $statProgress  = ($totalRegistrados > 0)
              ? (int) round(($totalConfirmados / $totalRegistrados) * 100)
              : 0;
          include __DIR__ . '/../components/stat-card.php';
        ?>
      </div>
    <?php else: ?>
      <div class="<?= $colTarjeta ?>">
        <?php
          $statLabel     = 'Promovidos';
          $statValue     = number_format($totalPromovidos, 0, ',', '.');
          $statIcon      = 'bi-award-fill';
          $statTone      = 'success';
          $statTrend     = 'Literales A, B, C, D';
          $statTrendType = 'up';
          include __DIR__ . '/../components/stat-card.php';
        ?>
      </div>
      <div class="<?= $colTarjeta ?>">
        <?php
          $statLabel     = 'No promovidos';
          $statValue     = number_format($totalNoPromovidos, 0, ',', '.');
          $statIcon      = 'bi-arrow-repeat';
          $statTone      = 'danger';
          $statTrend     = 'Literal E';
          $statTrendType = 'down';
          include __DIR__ . '/../components/stat-card.php';
        ?>
      </div>
      <div class="<?= $colTarjeta ?>">
        <?php
          $statLabel     = 'Pendientes de Confirmación';
          $statValue     = number_format($totalPendientes, 0, ',', '.');
          $statIcon      = 'bi-clock-history';
          $statTone      = 'warning';
          $statTrend     = "{$porcentajeConfirmado}% avance";
          $statTrendType = 'neutral';
          $statProgress  = $porcentajeConfirmado;
          include __DIR__ . '/../components/stat-card.php';
        ?>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($esDocente && $totalSecciones === 1): ?>
    <?php
      // Vista destacada y directa para docente de aula única
      $sec           = $secciones[0];
      $idSec         = (int) $sec['id'];
      $nombreGrado   = htmlspecialchars($sec['grado'] ?? '');
      $nombreSeccion = htmlspecialchars($sec['seccion'] ?? '');
      $nombreTurno   = htmlspecialchars($sec['turno'] ?? '');
      /* El avance mide lo que el DOCENTE ha evaluado, no lo que Dirección ya
         confirmó. Son dos pasos distintos: una propuesta con literal se queda en
         estado PENDIENTE hasta que Dirección la valida, así que contar solo los
         confirmados dejaba la barra en 0% para siempre, aunque el docente ya
         hubiera evaluado el aula entera. Por eso aquí va `total_registrados` —
         a quién se le asignó literal — y no `total_promovidos`.
         Lo que sí se cuenta aparte, en `total_confirmados`, es cuántas de esas
         propuestas ya pasaron por Dirección. */
      $nEstudiantes = (int) ($sec['total_estudiantes'] ?? 0);
      $nRegistrados = (int) ($sec['total_registrados'] ?? 0);
      $nConfirmados = (int) ($sec['total_confirmados'] ?? 0);
      $nSinEvaluar  = max(0, $nEstudiantes - $nRegistrados);

      $pctEvaluado = ($nEstudiantes > 0)
          ? (int) round(($nRegistrados / $nEstudiantes) * 100)
          : 0;

      if ($nEstudiantes === 0) {
          $badgeStatus = 'inactive';
          $badgeTexto  = 'Sin alumnos';
      } elseif ($nRegistrados === 0) {
          $badgeStatus = 'pending';
          $badgeTexto  = 'Sin evaluar';
      } elseif ($nSinEvaluar === 0 && $nConfirmados === $nEstudiantes) {
          $badgeStatus = 'active';
          $badgeTexto  = 'Confirmada';
      } elseif ($nSinEvaluar === 0) {
          $badgeStatus = 'active';
          $badgeTexto  = 'Evaluada';
      } else {
          $badgeStatus = 'info';
          $badgeTexto  = 'En progreso';
      }
    ?>
    <div class="content-card mb-4">
      <div class="content-card__header pb-3 mb-3">
        <div>
          <h2 class="content-card__title fs-5 mb-1">
            <i class="bi bi-mortarboard me-2 text-primary"></i><?= $nombreGrado ?> "<?= $nombreSeccion ?>"
          </h2>
          <span class="text-secondary small">
            Turno <?= $nombreTurno ?> · Período escolar activo <strong><?= htmlspecialchars($nombrePeriodo) ?></strong>
          </span>
        </div>
        <div>
          <?php
            $badgeLabel = $badgeTexto;
            include __DIR__ . '/../components/status-badge.php';
          ?>
        </div>
      </div>

      <div class="row g-3 align-items-center">
        <div class="col-12 col-lg-7">
          <div class="d-flex flex-column gap-2">
            <div class="d-flex justify-content-between align-items-center">
              <span class="small fw-semibold text-secondary">Progreso de evaluación del aula:</span>
              <span class="small fw-bold text-primary"><?= $nRegistrados ?> de <?= $nEstudiantes ?> estudiantes (<?= $pctEvaluado ?>%)</span>
            </div>
            <div class="progress" style="height: 10px; background-color: #e3e7f0; border-radius: 6px;">
              <div class="progress-bar <?= ($pctEvaluado === 100) ? 'bg-success' : 'bg-primary' ?>" 
                   role="progressbar" style="width: <?= $pctEvaluado ?>%; border-radius: 6px;" 
                   aria-valuenow="<?= $pctEvaluado ?>" aria-valuemin="0" aria-valuemax="100"></div>
            </div>

            <div class="d-flex flex-wrap gap-2 mt-2">
              <span class="badge rounded-pill bg-light text-dark border px-3 py-2">
                <i class="bi bi-people me-1 text-primary"></i> <strong><?= $nEstudiantes ?></strong> en nómina
              </span>
              <span class="badge rounded-pill bg-light text-success border px-3 py-2">
                <i class="bi bi-check2-circle me-1"></i> <strong><?= $nRegistrados ?></strong> evaluados
              </span>
              <span class="badge rounded-pill bg-light text-warning border px-3 py-2">
                <i class="bi bi-pencil-square me-1"></i> <strong><?= $nSinEvaluar ?></strong> sin evaluar
              </span>
              <span class="badge rounded-pill bg-light text-primary border px-3 py-2">
                <i class="bi bi-shield-check me-1"></i> <strong><?= $nConfirmados ?></strong> confirmados
              </span>
            </div>
          </div>
        </div>

        <div class="col-12 col-lg-5 text-lg-end mt-3 mt-lg-0">
          <a href="<?= BASE_URL ?>resultadoAcademico/seccion?id=<?= $idSec ?>" class="btn btn-primary d-inline-flex align-items-center gap-2 px-4 py-2">
            <i class="bi bi-pencil-square fs-6"></i>
            <span class="fw-semibold">Evaluar Aula y Cargar Literales</span>
          </a>
        </div>
      </div>
    </div>

  <?php else: ?>

    <!-- ---------- Contenedor de Secciones (Grid de Tarjetas + Vista Tabla) ---------- -->
    <div class="content-card mb-4">
      <div class="content-card__header">
        <h2 class="content-card__title">
          <i class="bi bi-grid-3x3-gap me-2 text-primary"></i>
          <?= $esDocente ? 'Mis Secciones Asignadas' : 'Aulas y Secciones del Plantel' ?>
        </h2>
        <div class="d-flex align-items-center gap-2">
          <span class="text-secondary small me-2">
            <?= $totalSecciones ?> <?= ($totalSecciones === 1) ? 'sección' : 'secciones' ?>
          </span>
          <ul class="nav nav-pills nav-pills-sm" id="viewTabs" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link active py-1 px-3 small d-flex align-items-center gap-1" id="tab-cards-btn" 
                      data-bs-toggle="pill" data-bs-target="#tab-cards" type="button" role="tab" aria-selected="true">
                <i class="bi bi-grid" aria-hidden="true"></i> <span>Tarjetas</span>
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link py-1 px-3 small d-flex align-items-center gap-1" id="tab-table-btn" 
                      data-bs-toggle="pill" data-bs-target="#tab-table" type="button" role="tab" aria-selected="false">
                <i class="bi bi-table" aria-hidden="true"></i> <span>Tabla</span>
              </button>
            </li>
          </ul>
        </div>
      </div>

      <div class="tab-content" id="viewTabsContent">

        <!-- VISTA 1: TARJETAS (CARDS) -->
        <div class="tab-pane fade show active" id="tab-cards" role="tabpanel">
          <div class="row g-3">
            <?php foreach ($secciones as $sec): ?>
              <?php
                $idSec         = (int) $sec['id'];
                $nombreGrado   = htmlspecialchars($sec['grado'] ?? '');
                $nombreSeccion = htmlspecialchars($sec['seccion'] ?? '');
                $nombreTurno   = htmlspecialchars($sec['turno'] ?? '');
                $docente       = htmlspecialchars($sec['docente_nombre'] ?? 'Sin asignar');
                $nEstudiantes = (int) ($sec['total_estudiantes'] ?? 0);
                $nPromovidos  = (int) ($sec['total_promovidos'] ?? 0);
                $nNoPromovidos= (int) ($sec['total_no_promovidos'] ?? 0);
                $nPendientes  = (int) ($sec['total_pendientes'] ?? 0);
                /* Registrados = con literal asignado, confirmados = validados por
                   Dirección. El avance va sobre los registrados: promoted y
                   no promovido son estados que recién existen al confirmar, así
                   que usarlos hacía que la barra quedara en 0% con el trabajo ya
                   hecho. La misma razón que en la tarjeta destacada de arriba. */
                $nRegistrados = (int) ($sec['total_registrados'] ?? 0);
                $nConfirmados = (int) ($sec['total_confirmados'] ?? 0);
                $nSinEvaluar  = max(0, $nEstudiantes - $nRegistrados);

                $pctEvaluado = ($nEstudiantes > 0)
                    ? (int) round(($nRegistrados / $nEstudiantes) * 100)
                    : 0;

                if ($nEstudiantes === 0) {
                    $badgeStatus = 'inactive';
                    $badgeTexto  = 'Sin alumnos';
                } elseif ($nRegistrados === 0) {
                    $badgeStatus = 'pending';
                    $badgeTexto  = 'Sin evaluar';
                } elseif ($nSinEvaluar === 0 && $nConfirmados === $nEstudiantes) {
                    $badgeStatus = 'active';
                    $badgeTexto  = 'Confirmada';
                } elseif ($nSinEvaluar === 0) {
                    $badgeStatus = 'active';
                    $badgeTexto  = 'Evaluada';
                } else {
                    $badgeStatus = 'info';
                    $badgeTexto  = 'En progreso';
                }
              ?>
              <div class="col-12 col-md-6 col-xl-4">
                <div class="card h-100 p-3 border" style="background-color: #f8f9fb; border-color: #e3e7f0 !important; border-radius: 1rem;">
                  
                  <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                      <h3 class="fs-6 fw-bold mb-0 text-primary"><?= $nombreGrado ?> "<?= $nombreSeccion ?>"</h3>
                      <small class="text-secondary">Turno <?= $nombreTurno ?></small>
                    </div>
                    <?php
                      $badgeLabel = $badgeTexto;
                      include __DIR__ . '/../components/status-badge.php';
                    ?>
                  </div>

                  <?php if (!$esDocente): ?>
                    <div class="small text-secondary mb-3">
                      <i class="bi bi-person text-secondary me-1"></i> Docente: <strong class="text-primary"><?= $docente ?></strong>
                    </div>
                  <?php endif; ?>

                  <!-- Métricas: al docente le interesan su propio avance y lo que
                       falta por evaluar; a Dirección le interesa el resultado
                       oficial. Mostrarle al docente los mismos tres números lo
                       dejaba con «0 promovidos» al lado de una barra al 75%. -->
                  <div class="row g-2 text-center my-auto py-2">
                    <?php if ($esDocente): ?>
                      <div class="col-4">
                        <div class="p-2 rounded bg-white border">
                          <span class="d-block fw-bold text-success fs-5"><?= $nRegistrados ?></span>
                          <small class="text-secondary" style="font-size: 0.72rem;">Evaluados</small>
                        </div>
                      </div>
                      <div class="col-4">
                        <div class="p-2 rounded bg-white border">
                          <span class="d-block fw-bold text-warning fs-5"><?= $nSinEvaluar ?></span>
                          <small class="text-secondary" style="font-size: 0.72rem;">Sin evaluar</small>
                        </div>
                      </div>
                      <div class="col-4">
                        <div class="p-2 rounded bg-white border">
                          <span class="d-block fw-bold text-primary fs-5"><?= $nConfirmados ?></span>
                          <small class="text-secondary" style="font-size: 0.72rem;">Confirmados</small>
                        </div>
                      </div>
                    <?php else: ?>
                      <div class="col-4">
                        <div class="p-2 rounded bg-white border">
                          <span class="d-block fw-bold text-success fs-5"><?= $nPromovidos ?></span>
                          <small class="text-secondary" style="font-size: 0.72rem;">Promovidos</small>
                        </div>
                      </div>
                      <div class="col-4">
                        <div class="p-2 rounded bg-white border">
                          <span class="d-block fw-bold text-danger fs-5"><?= $nNoPromovidos ?></span>
                          <small class="text-secondary" style="font-size: 0.72rem;">No promov.</small>
                        </div>
                      </div>
                      <div class="col-4">
                        <div class="p-2 rounded bg-white border">
                          <span class="d-block fw-bold text-warning fs-5"><?= $nPendientes ?></span>
                          <small class="text-secondary" style="font-size: 0.72rem;">Pendientes</small>
                        </div>
                      </div>
                    <?php endif; ?>
                  </div>

                  <!-- Barra de avance -->
                  <div class="mt-3 pt-2 border-top">
                    <div class="d-flex justify-content-between small text-secondary mb-1">
                      <span>Avance: <?= $pctEvaluado ?>%</span>
                      <span><?= $nEstudiantes ?> estudiantes</span>
                    </div>
                    <div class="progress" style="height: 6px; background-color: #e3e7f0;">
                      <div class="progress-bar <?= ($pctEvaluado === 100) ? 'bg-success' : 'bg-primary' ?>" 
                           role="progressbar" style="width: <?= $pctEvaluado ?>%" 
                           aria-valuenow="<?= $pctEvaluado ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                  </div>

                  <!-- Acción -->
                  <div class="mt-3 pt-1">
                    <a href="<?= BASE_URL ?>resultadoAcademico/seccion?id=<?= $idSec ?>" class="btn btn-outline-primary btn-sm w-100 d-flex align-items-center justify-content-center gap-1">
                      <i class="bi bi-pencil-square" aria-hidden="true"></i>
                      <span><?= $esDocente ? 'Evaluar Aula' : 'Gestionar Sección' ?></span>
                    </a>
                  </div>

                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- VISTA 2: TABLA DE SECCIONES (TABLE) -->
        <div class="tab-pane fade" id="tab-table" role="tabpanel">
          <div class="data-panel__body">
            <table class="table data-panel__table">
              <colgroup>
                <col style="width: 22%;">
                <col style="width: 10%;">
                <?php if (!$esDocente): ?>
                  <col style="width: 22%;">
                <?php endif; ?>
                <col style="width: 9%;">
                <col style="width: 9%;">
                <col style="width: 9%;">
                <col style="width: 9%;">
                <col style="width: 10%;">
              </colgroup>
              <thead>
                <tr>
                  <th scope="col">Grado y Sección</th>
                  <th scope="col">Turno</th>
                  <?php if (!$esDocente): ?>
                    <th scope="col">Docente Asignado</th>
                  <?php endif; ?>
                  <th scope="col" class="text-center">Matrícula</th>
                  <th scope="col" class="text-center">Promovidos</th>
                  <th scope="col" class="text-center">No Promovidos</th>
                  <th scope="col" class="text-center">Pendientes</th>
                  <th scope="col" class="text-end">Acción</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($secciones as $sec): ?>
                  <?php
                    $idSec         = (int) $sec['id'];
                    $nombreGrado   = htmlspecialchars($sec['grado'] ?? '');
                    $nombreSeccion = htmlspecialchars($sec['seccion'] ?? '');
                    $nombreTurno   = htmlspecialchars($sec['turno'] ?? '');
                    $docente       = htmlspecialchars($sec['docente_nombre'] ?? 'Sin asignar');
                    $nEstudiantes  = (int) ($sec['total_estudiantes'] ?? 0);
                    $nPromovidos   = (int) ($sec['total_promovidos'] ?? 0);
                    $nNoPromovidos = (int) ($sec['total_no_promovidos'] ?? 0);
                    $nPendientes   = (int) ($sec['total_pendientes'] ?? 0);
                  ?>
                  <tr>
                    <td>
                      <strong class="text-primary"><?= $nombreGrado ?> "<?= $nombreSeccion ?>"</strong>
                      <span class="text-secondary small d-block">Período <?= htmlspecialchars($sec['anio_escolar'] ?? '') ?></span>
                    </td>
                    <td><?= $nombreTurno ?></td>
                    <?php if (!$esDocente): ?>
                      <td><?= $docente ?></td>
                    <?php endif; ?>
                    <td class="text-center fw-bold"><?= $nEstudiantes ?></td>
                    <td class="text-center text-success fw-bold"><?= $nPromovidos ?></td>
                    <td class="text-center text-danger fw-bold"><?= $nNoPromovidos ?></td>
                    <td class="text-center text-warning fw-bold"><?= $nPendientes ?></td>
                    <td class="text-end">
                      <a href="<?= BASE_URL ?>resultadoAcademico/seccion?id=<?= $idSec ?>" class="btn btn-sm btn-outline-primary py-1 px-2">
                        <?= $esDocente ? 'Evaluar' : 'Gestionar' ?>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

      </div>
    </div>

  <?php endif; ?>

<?php else: ?>

  <!-- ---------- Estado Vacío (Content Card Empty) ---------- -->
  <div class="content-card">
    <div class="content-card__empty">
      <i class="bi bi-mortarboard" aria-hidden="true"></i>

      <?php if ($esDocente): ?>
        <h3 class="content-card__title mb-2 fs-5">No tiene una sección asignada para el período activo</h3>
        <p class="text-secondary mb-3">
          Su usuario figura con el rol de <strong>Docente</strong>, pero actualmente no tiene un grado y sección titular asignados en el período escolar activo (<strong><?= htmlspecialchars($nombrePeriodo) ?></strong>).
        </p>

        <div class="info-alert text-start mx-auto mb-4" style="max-width: 520px;">
          <div class="info-alert__icon"><i class="bi bi-info-circle-fill" aria-hidden="true"></i></div>
          <div>
            <div class="info-alert__title">¿Qué debe hacer para cargar notas?</div>
            <div class="info-alert__text">
              Solicite a la <strong>Dirección o Coordinación Académica</strong> que formalice su designación de aula en el módulo de Asignación Docente. Una vez registrada su aula, aparecerá de inmediato en este panel.
            </div>
          </div>
        </div>

        <div class="d-flex justify-content-center gap-2">
          <a href="<?= BASE_URL ?>dashboard/docente" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-house me-1"></i> Ir al Panel
          </a>
          <a href="<?= BASE_URL ?>estudiantes/index" class="btn btn-primary btn-sm">
            <i class="bi bi-people me-1"></i> Consultar Expedientes
          </a>
        </div>

      <?php else: ?>
        <h3 class="content-card__title mb-2 fs-5">No hay secciones registradas para este período</h3>
        <p class="text-secondary mb-4">
          No se encontraron estructuras académicas registradas en el período seleccionado (<strong><?= htmlspecialchars($nombrePeriodo) ?></strong>).
        </p>
        <a href="<?= BASE_URL ?>academicStructure/index" class="btn btn-primary btn-sm">
          <i class="bi bi-plus-circle me-1"></i> Configurar Estructura Académica
        </a>
      <?php endif; ?>

    </div>
  </div>

<?php endif; ?>

<!-- ---------- Modal: Criterios Normativos (components/modal.php - app-modal) ---------- -->
<div class="modal fade app-modal" id="modalGuiaNormativa" tabindex="-1" aria-labelledby="modalGuiaNormativaLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="modalGuiaNormativaLabel">
            <i class="bi bi-book-half me-2 text-primary"></i>Criterios de Evaluación y Promoción Anual
          </h5>
          <p class="modal-header__subtitle">Flujo de proceso, escala cualitativa y efectos normativos en Educación Primaria</p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <div class="modal-body">
        
        <!-- Flujo del proceso pedagógico -->
        <h6 class="fw-bold mb-2 text-primary" style="font-size: 0.9rem;">
          <i class="bi bi-diagram-3 me-1 text-primary"></i> Flujo del Proceso de Promoción
        </h6>
        <div class="row g-2 mb-3">
          <div class="col-12 col-md-4">
            <div class="p-2 px-3 rounded border h-100" style="background-color: #f8f9fb; border-color: #e3e7f0 !important;">
              <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge rounded-circle p-2 d-inline-flex align-items-center justify-content-center text-white" 
                      style="background-color: #0a1440; width: 22px; height: 22px; font-size: 0.72rem;">1</span>
                <strong class="text-primary small">Asignar Literales</strong>
              </div>
              <p class="small text-secondary mb-0" style="font-size: 0.8rem;">
                El docente titular registra los literales cualitativos (A, B, C, D o E) de fin de ciclo.
              </p>
            </div>
          </div>
          <div class="col-12 col-md-4">
            <div class="p-2 px-3 rounded border h-100" style="background-color: #f8f9fb; border-color: #e3e7f0 !important;">
              <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge rounded-circle p-2 d-inline-flex align-items-center justify-content-center text-dark" 
                      style="background-color: #e0a13b; width: 22px; height: 22px; font-size: 0.72rem;">2</span>
                <strong class="text-primary small">Guardar Propuesta</strong>
              </div>
              <p class="small text-secondary mb-0" style="font-size: 0.8rem;">
                Los resultados quedan en estado <strong>Pendiente</strong> en espera de validación directiva.
              </p>
            </div>
          </div>
          <div class="col-12 col-md-4">
            <div class="p-2 px-3 rounded border h-100" style="background-color: #f8f9fb; border-color: #e3e7f0 !important;">
              <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge rounded-circle p-2 d-inline-flex align-items-center justify-content-center text-white" 
                      style="background-color: #1fa971; width: 22px; height: 22px; font-size: 0.72rem;">3</span>
                <strong class="text-primary small">Confirmación</strong>
              </div>
              <p class="small text-secondary mb-0" style="font-size: 0.8rem;">
                La Dirección oficializa los resultados, habilitando ratificaciones y egresos.
              </p>
            </div>
          </div>
        </div>

        <h6 class="fw-bold mb-2 text-primary" style="font-size: 0.9rem;">
          <i class="bi bi-card-checklist me-1 text-primary"></i> Escala Cualitativa de Literales
        </h6>

        <!-- Lista Responsiva de Literales -->
        <div class="d-flex flex-column gap-2 mb-3">
          
          <!-- Literal A -->
          <div class="p-2 px-3 rounded border d-flex flex-wrap align-items-center justify-content-between gap-2" style="background-color: #f8f9fb; border-color: #e3e7f0 !important;">
            <div class="d-flex align-items-center gap-3">
              <span class="badge text-white px-2 py-1" style="background-color: #1fa971; font-weight: 700;">Literal A</span>
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
              <span class="badge text-white px-2 py-1" style="background-color: #1fa971; font-weight: 700;">Literal B</span>
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
              <span class="badge text-white px-2 py-1" style="background-color: #3b6fe0; font-weight: 700;">Literal C</span>
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
              <span class="badge text-dark px-2 py-1" style="background-color: #e0a13b; font-weight: 700;">Literal D</span>
              <div>
                <strong class="text-primary" style="font-size: 0.85rem;">Alcanzó algunas competencias mínimas; requiere acompañamiento</strong>
                <span class="text-secondary small d-block">Avanza al grado inmediato superior</span>
              </div>
            </div>
            <span class="status-badge status-badge--active">PROMOVIDO</span>
          </div>

          <!-- Literal E -->
          <div class="p-2 px-3 rounded border d-flex flex-wrap align-items-center justify-content-between gap-2" style="background-color: rgba(224, 69, 95, 0.04); border-color: rgba(224, 69, 95, 0.2) !important;">
            <div class="d-flex align-items-center gap-3">
              <span class="badge text-white px-2 py-1" style="background-color: #e0455f; font-weight: 700;">Literal E</span>
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

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../layouts/app.php';
