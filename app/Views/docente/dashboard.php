<?php
/**
 * Vista: docente/dashboard.php  ·  /dashboard/docente
 * Tablero principal del rol Docente.
 *
 * Solo lectura: sin botones de edición ni formularios de escritura.
 * La sección del docente se resuelve en el servidor a partir de la sesión activa.
 *
 * @var string     $nombreDocente Nombre completo del docente en sesión.
 * @var string     $saludo        Saludo según la hora de Caracas.
 * @var array|null $seccion       Datos de la estructura asignada o null.
 * @var bool       $tieneSeccion  Indica si tiene sección activa.
 * @var array      $resumen       Cifras generales para stat-cards.
 * @var array      $distribucion  Distribución por sexo y por edad.
 * @var array      $cumpleanos    Cumpleañero de hoy y lista del mes.
 * @var array      $literales     Avance de literales finales y escala.
 */

$e = static fn($v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$pageTitle   = 'Dashboard Docente';
$currentNav  = 'dashboard';
$breadcrumbs = [
    ['label' => 'Panel', 'href' => BASE_URL . 'dashboard/docente'],
    ['label' => 'Dashboard'],
];

ob_start();

// ---------- Chip de período activo (components/period-chip.php) ----------
$periodChipHtml = '';
if ($tieneSeccion && !empty($seccion['anio_escolar'])) {
    ob_start();
    $periodText     = 'Período ' . $seccion['anio_escolar'];
    $periodStatus   = 'Activo';
    $periodIsActive = true;
    include __DIR__ . '/../components/period-chip.php';
    $periodChipHtml = ob_get_clean();
}

// ---------- Encabezado de página ----------
?>
<div class="page-header mb-4">
  <div>
    <span class="docente-badge-rol">DOCENTE DE AULA TITULAR</span>
    <h1 class="page-header__title mb-1">¡<?= $e($saludo ?? 'Buenos días') ?>, Prof. <?= $e($nombreDocente) ?>!</h1>
    <?php if ($tieneSeccion): ?>
      <p class="page-header__subtitle d-flex align-items-center gap-2 flex-wrap mb-0">
        <span><?= $e($seccion['grado']) ?> "<?= $e($seccion['seccion']) ?>" · Turno <?= $e($seccion['turno']) ?></span>
        <?php if ($periodChipHtml !== ''): ?>
          <span class="text-muted">|</span>
          <?= $periodChipHtml ?>
        <?php endif; ?>
      </p>
    <?php else: ?>
      <p class="page-header__subtitle text-warning mb-0">Sin sección asignada en el período escolar activo</p>
    <?php endif; ?>
  </div>
</div>

<?php if (!$tieneSeccion): ?>
  <!-- Estado sin asignación vigente -->
  <div class="alert alert-warning d-flex align-items-start gap-3 shadow-sm border-0 p-4" role="status">
    <i class="bi bi-exclamation-triangle fs-3 flex-shrink-0 text-warning" aria-hidden="true"></i>
    <div>
      <h2 class="h5 fw-bold mb-1 text-dark">No tienes una sección asignada actualmente</h2>
      <p class="text-dark mb-2">
        Para consultar las métricas de tu aula, la nómina de estudiantes y registrar literales finales,
        la Dirección debe asignarte una sección en el período escolar activo.
      </p>
      <span class="small text-muted">Si consideras que se trata de un error, por favor contacta al departamento de coordinación o dirección.</span>
    </div>
  </div>
<?php else: ?>

  <!-- ---------- 1. Stat Cards Principales ---------- -->
  <?php $colCardClass = !empty($mostrarLiterales) ? 'col-12 col-md-4' : 'col-12 col-md-6'; ?>
  <div class="row g-3 mb-4">
    
    <!-- Card 1: Matrícula Total -->
    <div class="<?= $colCardClass ?>">
      <article class="stat-card">
        <div class="stat-card__top">
          <span class="stat-card__icon stat-card__icon--primary">
            <i class="bi bi-people-fill" aria-hidden="true"></i>
          </span>
        </div>
        <p class="stat-card__label">Matrícula Total</p>
        <p class="stat-card__value mb-0"><?= $e($resumen['total']) ?></p>
        
        <div class="stat-card__split-meta">
          <span class="stat-card__split-item stat-card__split-item--varones">
            <?= $e($resumen['varones']) ?> Varones (<?= $e($resumen['pct_varones']) ?>%)
          </span>
          <span class="stat-card__split-item stat-card__split-item--hembras">
            <?= $e($resumen['hembras']) ?> Hembras (<?= $e($resumen['pct_hembras']) ?>%)
          </span>
        </div>

        <div class="stat-card__split-bar" aria-hidden="true">
          <span class="stat-card__split-seg--varones" style="width: <?= (float) $resumen['pct_varones'] ?>%"></span>
          <span class="stat-card__split-seg--hembras" style="width: <?= (float) $resumen['pct_hembras'] ?>%"></span>
        </div>
      </article>
    </div>

    <!-- Card 2: Edad Promedio -->
    <div class="<?= $colCardClass ?>">
      <article class="stat-card">
        <div class="stat-card__top">
          <span class="stat-card__icon stat-card__icon--info">
            <i class="bi bi-calendar3" aria-hidden="true"></i>
          </span>
        </div>
        <p class="stat-card__label">Edad Promedio</p>
        <p class="stat-card__value mb-0">
          <?= $e($resumen['edad_promedio']) ?> <span class="fs-6 fw-normal text-muted">años</span>
        </p>
        <p class="stat-card__meta mb-0">
          <?php if ($resumen['edad_min'] !== null): ?>
            Rango: Min <?= $e($resumen['edad_min']) ?> años &nbsp;&nbsp; Max <?= $e($resumen['edad_max']) ?> años
          <?php else: ?>
            Sin datos de edad registrados
          <?php endif; ?>
        </p>
      </article>
    </div>

    <?php if (!empty($mostrarLiterales)): ?>
    <!-- Card 3: Literales Cargados -->
    <div class="<?= $colCardClass ?>">
      <article class="stat-card">
        <div class="stat-card__top">
          <span class="stat-card__icon stat-card__icon--primary">
            <i class="bi bi-sliders" aria-hidden="true"></i>
          </span>
          <?php if ((int) $literales['pendientes'] > 0): ?>
            <span class="stat-card__trend stat-card__trend--warning">
              <?= $e($literales['pendientes']) ?> PENDIENTES
            </span>
          <?php else: ?>
            <span class="stat-card__trend stat-card__trend--positive">
              <i class="bi bi-check-circle-fill me-1" aria-hidden="true"></i>COMPLETO
            </span>
          <?php endif; ?>
        </div>
        <p class="stat-card__label">Literales Cargados</p>
        <div class="d-flex align-items-baseline justify-content-between">
          <p class="stat-card__value mb-0">
            <?= $e($literales['registrados']) ?> <span class="fs-6 fw-normal text-muted">/ <?= $e($literales['total']) ?></span>
          </p>
          <span class="small fw-bold text-muted"><?= $e($literales['porcentaje']) ?>%</span>
        </div>
        <div class="stat-card__bar" role="progressbar" aria-valuenow="<?= (int) $literales['porcentaje'] ?>" aria-valuemin="0" aria-valuemax="100">
          <span style="width: <?= (float) $literales['porcentaje'] ?>%"></span>
        </div>

        <div class="mt-2 pt-1 border-top d-flex align-items-center justify-content-between">
          <?php if ((int) $literales['pendientes'] > 0): ?>
            <a href="<?= BASE_URL ?>resultadoAcademico/seccion?estado=pendiente" 
               class="small fw-semibold text-primary text-decoration-none d-inline-flex align-items-center gap-1">
              <span>Continuar registro pendiente (<?= $e($literales['pendientes']) ?> <?= $literales['pendientes'] === 1 ? 'estudiante' : 'estudiantes' ?>)</span>
              <i class="bi bi-arrow-right" aria-hidden="true"></i>
            </a>
          <?php else: ?>
            <span class="small text-muted d-inline-flex align-items-center gap-1">
              <i class="bi bi-check-circle text-success" aria-hidden="true"></i>
              Todos los literales cargados
            </span>
          <?php endif; ?>
        </div>
      </article>
    </div>
    <?php endif; ?>

  </div>

  <!-- ---------- 2. Contenido Central (2 Columnas) ---------- -->
  <div class="row g-4">
    
    <!-- Columna Izquierda (Distribución + Nuevo Ingreso y Ratificados) -->
    <div class="col-12 col-lg-7 col-xl-8">
      
      <!-- Panel 1: Distribución de la Sección -->
      <section class="card border-0 shadow-sm mb-4 section-distribution">
        <div class="card-body p-4">
          <div class="mb-4 pb-2 border-bottom">
            <h2 class="h6 fw-bold text-dark d-flex align-items-center gap-2 mb-0">
              <i class="bi bi-grid-1x2 text-primary" aria-hidden="true"></i>
              Distribución de la Sección
            </h2>
          </div>

          <!-- Proporción por Sexo -->
          <div class="mb-4">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <span class="section-distribution__subtitle mb-0">Proporción por Sexo</span>
              <span class="section-distribution__header-info">Total: <?= $e($distribucion['total']) ?> alumnos</span>
            </div>
            
            <div class="section-distribution__sex-bar-wrapper">
              <div class="section-distribution__sex-seg section-distribution__sex-seg--varones" 
                   style="width: <?= (float) ($distribucion['sexo']['pct_varones'] ?: 50) ?>%">
                <i class="bi bi-gender-male" aria-hidden="true"></i>
                <span>Varones: <?= $e($distribucion['sexo']['varones']) ?> (<?= $e($distribucion['sexo']['pct_varones']) ?>%)</span>
              </div>
              <div class="section-distribution__sex-seg section-distribution__sex-seg--hembras" 
                   style="width: <?= (float) ($distribucion['sexo']['pct_hembras'] ?: 50) ?>%">
                <i class="bi bi-gender-female" aria-hidden="true"></i>
                <span>Hembras: <?= $e($distribucion['sexo']['hembras']) ?> (<?= $e($distribucion['sexo']['pct_hembras']) ?>%)</span>
              </div>
            </div>
          </div>

          <!-- Distribución por Edad Cumplida -->
          <div>
            <div class="d-flex align-items-center justify-content-between mb-3">
              <span class="section-distribution__subtitle mb-0">Distribución por Edad Cumplida</span>
              <span class="section-distribution__header-info">Frecuencia de Matrícula</span>
            </div>

            <?php if (empty($distribucion['edades'])): ?>
              <p class="text-muted small mb-0">No hay registros de edad disponibles en este grupo.</p>
            <?php else: ?>
              <div class="section-distribution__age-list">
                <?php foreach ($distribucion['edades'] as $item): ?>
                  <div class="section-distribution__age-row">
                    <span class="section-distribution__age-label"><?= $e($item['edad']) ?> años</span>
                    <div class="section-distribution__age-track">
                      <div class="section-distribution__age-fill <?= $item['es_mas_comun'] ? 'section-distribution__age-fill--highlight' : '' ?>"
                           style="width: <?= max(12, (float) $item['porcentaje']) ?>%">
                        <span><?= $e($item['cantidad']) ?> <?= $item['cantidad'] === 1 ? 'alumno' : 'alumnos' ?></span>
                        <?php if ($item['es_mas_comun']): ?>
                          <span class="section-distribution__tag-comun">Más común</span>
                        <?php endif; ?>
                      </div>
                    </div>
                    <span class="section-distribution__age-pct"><?= $e($item['porcentaje']) ?>%</span>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

        </div>
      </section>

      <!-- Panel 2: Nuevo Ingreso y Ratificados -->
      <section class="card border-0 shadow-sm">
        <div class="card-body p-4">
          <div class="d-flex align-items-center justify-content-between mb-4 pb-2 border-bottom">
            <h2 class="h6 fw-bold text-dark d-flex align-items-center gap-2 mb-0">
              <i class="bi bi-arrow-left-right text-primary" aria-hidden="true"></i>
              Nuevo Ingreso y Ratificados
            </h2>
            <span class="badge bg-light text-primary border fw-bold px-3 py-2">
              <?= $e($ingresos['total']) ?> <?= $ingresos['total'] === 1 ? 'ALUMNO' : 'ALUMNOS' ?>
            </span>
          </div>

          <?php if ($ingresos['total'] === 0): ?>
            <p class="text-muted small mb-0">No hay estudiantes activos registrados en esta sección.</p>
          <?php else: ?>

            <!-- Continuidad y Nuevo Ingreso en el Plantel -->
            <div>
              <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="section-distribution__subtitle mb-0">Continuidad en el Plantel</span>
                <span class="section-distribution__header-info">Matrícula Activa</span>
              </div>

              <div class="d-flex flex-column gap-3">
                <!-- Barra Ratificados -->
                <div>
                  <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="small fw-semibold text-secondary">
                      <i class="bi bi-arrow-repeat text-primary me-1" aria-hidden="true"></i>Ratificados (Continuidad)
                    </span>
                    <span class="small fw-bold text-dark"><?= $e($ingresos['ratificados']) ?> (<?= $e($ingresos['pct_ratificados']) ?>%)</span>
                  </div>
                  <div class="section-distribution__age-track">
                    <?php if ($ingresos['ratificados'] > 0): ?>
                      <div class="section-distribution__age-fill section-distribution__age-fill--highlight"
                           style="width: <?= max(12, (float) $ingresos['pct_ratificados']) ?>%">
                        <span><?= $e($ingresos['ratificados']) ?> <?= $ingresos['ratificados'] === 1 ? 'alumno' : 'alumnos' ?></span>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>

                <!-- Barra Nuevo Ingreso -->
                <div>
                  <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="small fw-semibold text-secondary">
                      <i class="bi bi-person-plus text-info me-1" aria-hidden="true"></i>Nuevos ingresos al plantel
                    </span>
                    <span class="small fw-bold text-dark"><?= $e($ingresos['nuevos']) ?> (<?= $e($ingresos['pct_nuevos']) ?>%)</span>
                  </div>
                  <div class="section-distribution__age-track">
                    <?php if ($ingresos['nuevos'] > 0): ?>
                      <div class="section-distribution__age-fill"
                           style="width: <?= max(12, (float) $ingresos['pct_nuevos']) ?>%">
                        <span><?= $e($ingresos['nuevos']) ?> <?= $ingresos['nuevos'] === 1 ? 'alumno' : 'alumnos' ?></span>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>

          <?php endif; ?>

        </div>
      </section>

    </div>

    <!-- Columna Derecha (Cumpleañero de Hoy + Cumpleaños del Mes) -->
    <div class="col-12 col-lg-5 col-xl-4">
      
      <!-- 1. Tarjeta destacada Cumpleañera/o de Hoy (components/featured-birthday-card.php) -->
      <div class="mb-4">
        <?php
          $birthdayData = $cumpleanos['cumpleanero_hoy'];
          $mesNombre    = $cumpleanos['mes_nombre'];
          include __DIR__ . '/../components/featured-birthday-card.php';
        ?>
      </div>

      <!-- 2. Lista de Cumpleaños del Mes -->
      <section class="card border-0 shadow-sm">
        <div class="card-body p-4">
          <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
            <h2 class="h6 fw-bold text-dark d-flex align-items-center gap-2 mb-0">
              <i class="bi bi-cake2 text-primary" aria-hidden="true"></i>
              Cumpleañeros de <?= $e($cumpleanos['mes_nombre']) ?>
            </h2>
          </div>

          <?php if (empty($cumpleanos['cumpleanos_mes'])): ?>
            <div class="text-center py-4 text-muted">
              <i class="bi bi-balloon fs-2 d-block mb-2 text-secondary" aria-hidden="true"></i>
              <p class="small mb-0">No hay cumpleaños registrados este mes en tu sección.</p>
            </div>
          <?php else: ?>
            <div class="birthday-list">
              <?php foreach ($cumpleanos['cumpleanos_mes'] as $item): ?>
                <div class="birthday-list__item <?= $item['es_hoy'] ? 'birthday-list__item--today' : '' ?>">
                  <div class="birthday-list__main">
                    <div class="birthday-list__date-badge <?= $item['es_hoy'] ? 'birthday-list__date-badge--today' : '' ?>">
                      <span class="birthday-list__month"><?= $e($item['mes_abrev']) ?></span>
                      <span class="birthday-list__day"><?= $e($item['dia']) ?></span>
                    </div>
                    <div class="birthday-list__info">
                      <div class="birthday-list__name"><?= $e($item['nombre']) ?></div>
                      <p class="birthday-list__age"><?= $e($item['texto_edad']) ?></p>
                    </div>
                  </div>

                  <span class="birthday-list__pill birthday-list__pill--<?= $e($item['estado_tipo']) ?>">
                    <?= $e($item['estado']) ?>
                  </span>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

        </div>
      </section>

    </div>

  </div>

<?php endif; ?>

<?php
$pageContent = ob_get_clean();
include __DIR__ . '/../layouts/app.php';
