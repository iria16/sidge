<?php
declare(strict_types=1);

/**
 * Fragmento: dashboard/partials/directivo-dashboard.php
 * Tablero consolidado para los roles Directivo y Coordinador.
 *
 * @var string $saludo
 * @var string $nombreUsuario
 * @var array|null $anioActivo
 * @var array $metricasMatricula
 * @var array $metricasCapacidad
 * @var array $metricasPersonal
 * @var array $distribucionGrados
 * @var array $avanceResultados
 * @var array $alertas
 * @var bool $esCoordinador
 */

$hayAnioActivo = $anioActivo !== null;
$rolClave      = $esCoordinador ? 'coordinador' : 'directivo';
$rolEtiqueta   = $esCoordinador ? 'COORDINACIÓN' : 'DIRECCIÓN';
?>

<!-- 1. ENCABEZADO DE PÁGINA -->
<div class="page-header mb-4">
  <div>
    <span class="docente-badge-rol"><?= $rolEtiqueta ?></span>
    <h1 class="page-header__title mb-1">¡<?= htmlspecialchars($saludo) ?>, <?= htmlspecialchars($nombreUsuario) ?>!</h1>
    <p class="page-header__subtitle mb-0">
      <?= $esCoordinador
        ? 'Seguimiento pedagógico, distribución escolar y estadísticas institucionales (Modo Consulta).'
        : 'Visión estratégica de la institución: matrícula escolar, capacidad, personal y rendimiento.' ?>
    </p>
  </div>
  <div class="page-header__actions d-flex align-items-center gap-2 flex-wrap">
    <?php if ($hayAnioActivo): ?>
      <?php
      $periodText = (string) $anioActivo['nombre'];
      $periodStatus = 'Activo';
      $periodIsActive = true;
      include __DIR__ . '/../../components/period-chip.php';
      ?>
    <?php else: ?>
      <span class="badge text-bg-danger">
        <i class="bi bi-calendar-x me-1" aria-hidden="true"></i> Sin año escolar activo
      </span>
    <?php endif; ?>
    <span class="badge text-bg-light border">
      <i class="bi bi-calendar3 me-1" aria-hidden="true"></i> <?= htmlspecialchars(date('d/m/Y')) ?>
    </span>
  </div>
</div>

<?php if (!$hayAnioActivo): ?>
  <div class="alert alert-warning d-flex align-items-center gap-3 mb-4" role="alert">
    <i class="bi bi-exclamation-triangle fs-4 flex-shrink-0" aria-hidden="true"></i>
    <div>
      <div class="fw-semibold">No hay un año escolar activo en el sistema</div>
      <p class="mb-0 small">
        <?= $esCoordinador
          ? 'Contacte a la Dirección para activar el período académico correspondiente.'
          : 'Configure y active el período escolar en Configuración &rsaquo; Años Escolares para habilitar la matrícula y el registro académico.' ?>
      </p>
    </div>
  </div>
<?php endif; ?>

<!-- 2. CUATRO INDICADORES CLAVE (STAT-CARDS) -->
<div class="row g-3 mb-4">
  <!-- Card 1: Matrícula Activa -->
  <div class="col-sm-6 col-xl-3">
    <?php
    $statLabel     = 'Matrícula cursando';
    $statValue     = number_format((int) $metricasMatricula['total_estudiantes'], 0, ',', '.') . ' estud.';
    $statIcon      = 'bi-people';
    $statTone      = 'primary';
    $statVariant   = 'default';
    $statTrend     = $metricasMatricula['total_estudiantes'] > 0 ? 'Activa' : '';
    $statTrendType = 'neutral';
    $statMeta      = 'Varones: ' . $metricasMatricula['varones'] . ' (' . $metricasMatricula['porcentaje_varones'] . '%) · Hembras: ' . $metricasMatricula['hembras'] . ' (' . $metricasMatricula['porcentaje_hembras'] . '%)';
    $statProgress  = null;
    $statHref      = BASE_URL . 'estadistica/index?vista=matricula';
    include __DIR__ . '/../../components/stat-card.php';
    ?>
  </div>

  <!-- Card 2: Capacidad y Ocupación -->
  <div class="col-sm-6 col-xl-3">
    <?php
    $statLabel     = 'Capacidad escolar';
    $statValue     = $metricasCapacidad['porcentaje_ocupacion'] . '% ocupación';
    $statIcon      = 'bi-pie-chart';
    $statTone      = 'info';
    $statVariant   = 'default';
    $statTrend     = $metricasCapacidad['cupos_disponibles'] . ' libres';
    $statTrendType = 'neutral';
    $statMeta      = $metricasCapacidad['cupos_ocupados'] . ' de ' . $metricasCapacidad['cupos_totales'] . ' cupos configurados';
    $statProgress  = (int) $metricasCapacidad['porcentaje_ocupacion'];
    $statHref      = BASE_URL . 'estadistica/index?vista=cobertura';
    include __DIR__ . '/../../components/stat-card.php';
    ?>
  </div>

  <!-- Card 3: Personal Institucional -->
  <div class="col-sm-6 col-xl-3">
    <?php
    $statLabel     = 'Personal activo';
    $statValue     = number_format((int) $metricasPersonal['empleados_activos'], 0, ',', '.') . ' empleados';
    $statIcon      = 'bi-person-badge';
    $statTone      = 'success';
    $statVariant   = 'default';
    $statTrend     = $metricasPersonal['en_reposo_hoy'] > 0 ? ($metricasPersonal['en_reposo_hoy'] . ' en reposo') : 'Al día';
    $statTrendType = $metricasPersonal['en_reposo_hoy'] > 0 ? 'down' : 'up';
    $statMeta      = $metricasPersonal['docentes_activos'] . ' docentes · ' . $metricasPersonal['administrativos'] . ' admin · ' . $metricasPersonal['obreros'] . ' obreros';
    $statProgress  = null;
    $statHref      = BASE_URL . 'empleado/index';
    include __DIR__ . '/../../components/stat-card.php';
    ?>
  </div>

  <!-- Card 4: Cobertura Docente -->
  <div class="col-sm-6 col-xl-3">
    <?php
    $sinDocente    = (int) $metricasCapacidad['secciones_sin_docente'];
    $statLabel     = 'Aulas con docente';
    $statValue     = $metricasCapacidad['secciones_con_docente'] . ' de ' . $metricasCapacidad['total_secciones'] . ' aulas';
    $statIcon      = 'bi-door-open';
    $statTone      = $sinDocente > 0 ? 'warning' : 'success';
    $statVariant   = $sinDocente > 0 ? 'alert' : 'default';
    $statTrend     = $sinDocente > 0 ? ($sinDocente . ' por asignar') : '100% cubierto';
    $statTrendType = $sinDocente > 0 ? 'down' : 'up';
    $statMeta      = $sinDocente > 0 ? 'Atención: Salones sin titular asignado' : 'Todas las secciones tienen docente titular';
    $statProgress  = $metricasCapacidad['total_secciones'] > 0
        ? (int) round(($metricasCapacidad['secciones_con_docente'] / $metricasCapacidad['total_secciones']) * 100)
        : null;
    $statHref      = BASE_URL . 'teacherAssignment/index';
    include __DIR__ . '/../../components/stat-card.php';
    ?>
  </div>
</div>

<!-- 3. CUERPO PRINCIPAL: RESUMEN ANALÍTICO, ACCESOS DIRECTOS Y ALERTAS -->
<div class="row g-3">
  <!-- Columna Izquierda (8 cols): Tarjetas destacadas a Estadísticas y Reportes -->
  <div class="col-lg-8">
    <!-- Tarjeta 1: Estadísticas Institucionales -->
    <section class="content-card mb-4" aria-labelledby="estadisticasHubHeading">
      <div class="content-card__header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2 class="content-card__title mb-0" id="estadisticasHubHeading">
          <i class="bi bi-bar-chart-line text-primary me-2" aria-hidden="true"></i>Pulso y Estadísticas Institucionales
        </h2>
        <a href="<?= BASE_URL ?>estadistica/index" class="btn btn-sm btn-primary">
          <i class="bi bi-graph-up me-1" aria-hidden="true"></i>Abrir Estadísticas
        </a>
      </div>
      <div class="content-card__body p-3">
        <div class="row g-3 mb-3">
          <div class="col-md-4">
            <div class="p-3 bg-light rounded border text-center">
              <span class="text-support small d-block mb-1">Matrícula Activa</span>
              <strong class="fs-4 text-primary"><?= number_format((int) ($metricasMatricula['total_estudiantes'] ?? 0), 0, ',', '.') ?></strong>
              <div class="small text-muted mt-1"><?= (int) ($metricasMatricula['varones'] ?? 0) ?> V · <?= (int) ($metricasMatricula['hembras'] ?? 0) ?> H</div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="p-3 bg-light rounded border text-center">
              <span class="text-support small d-block mb-1">Ocupación de Cupos</span>
              <strong class="fs-4 text-info"><?= (int) ($metricasCapacidad['porcentaje_ocupacion'] ?? 0) ?>%</strong>
              <div class="small text-muted mt-1"><?= (int) ($metricasCapacidad['cupos_disponibles'] ?? 0) ?> cupos libres</div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="p-3 bg-light rounded border text-center">
              <span class="text-support small d-block mb-1">Personal en Plantel</span>
              <strong class="fs-4 text-success"><?= (int) ($metricasPersonal['empleados_activos'] ?? 0) ?></strong>
              <div class="small text-muted mt-1"><?= (int) ($metricasPersonal['docentes_activos'] ?? 0) ?> docentes en aula</div>
            </div>
          </div>
        </div>
        <p class="text-support small mb-0">
          La interfaz única de <strong>Estadísticas</strong> consolida la matriz por grados y género, pirámide de edades y rezago escolar, balance de capacidad e infraestructura, y resultados académicos de fin de ciclo.
        </p>
      </div>
    </section>

    <!-- Tarjeta 2: Centro de Reportes Oficiales -->
    <section class="content-card" aria-labelledby="reportesHubHeading">
      <div class="content-card__header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2 class="content-card__title mb-0" id="reportesHubHeading">
          <i class="bi bi-file-earmark-bar-graph text-info me-2" aria-hidden="true"></i>Centro de Reportes Institucionales
        </h2>
        <a href="<?= BASE_URL ?>reporte/index" class="btn btn-sm btn-outline-primary">
          <i class="bi bi-arrow-right me-1" aria-hidden="true"></i>Ver todos los reportes
        </a>
      </div>
      <div class="list-group list-group-flush small">
        <a href="<?= BASE_URL ?>reporte/index?tipo=estadistico_general" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-2">
          <div>
            <i class="bi bi-bar-chart text-primary me-2" aria-hidden="true"></i>
            <strong>Estadístico General de Matrícula (Matriz MPPE)</strong>
            <div class="text-support">Distribución ministerial de secciones por edades cumplidas y sexo</div>
          </div>
          <span class="badge text-bg-light border">Ver formato &rsaquo;</span>
        </a>
        <a href="<?= BASE_URL ?>reporte/index?tipo=nomina_estudiantes" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-2">
          <div>
            <i class="bi bi-journal-text text-info me-2" aria-hidden="true"></i>
            <strong>Nómina General de Estudiantes</strong>
            <div class="text-support">Listados por grado y sección con datos de contacto del representante</div>
          </div>
          <span class="badge text-bg-light border">Ver nómina &rsaquo;</span>
        </a>
        <a href="<?= BASE_URL ?>reporte/index?tipo=distribucion_aulas" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-2">
          <div>
            <i class="bi bi-door-open text-warning me-2" aria-hidden="true"></i>
            <strong>Distribución y Capacidad de Aulas</strong>
            <div class="text-support">Relación de cupos configurados, matriculados y titular asignado</div>
          </div>
          <span class="badge text-bg-light border">Ver balance &rsaquo;</span>
        </a>
        <a href="<?= BASE_URL ?>reporte/index?tipo=personal_institucional" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-2">
          <div>
            <i class="bi bi-person-lines-fill text-success me-2" aria-hidden="true"></i>
            <strong>Directorio y Plantilla Oficial de Personal</strong>
            <div class="text-support">Cuerpo docente, administrativo y auxiliar con estado laboral</div>
          </div>
          <span class="badge text-bg-light border">Ver plantilla &rsaquo;</span>
        </a>
        <a href="<?= BASE_URL ?>reporte/index?tipo=retiros_periodo" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-2">
          <div>
            <i class="bi bi-box-arrow-right text-danger me-2" aria-hidden="true"></i>
            <strong>Relación Oficial de Retiros y Deserciones</strong>
            <div class="text-support">Registro de desvinculaciones con fecha y motivo formal</div>
          </div>
          <span class="badge text-bg-light border">Ver relación &rsaquo;</span>
        </a>
      </div>
    </section>
  </div>

  <!-- Columna Derecha (4 cols): Alertas y Acciones Rápidas -->
  <div class="col-lg-4">
    <!-- Bloque 1: Alertas Operativas -->
    <section class="content-card mb-4" aria-labelledby="alertasHeading">
      <div class="content-card__header">
        <h2 class="content-card__title mb-0" id="alertasHeading">
          <i class="bi bi-bell me-2" aria-hidden="true"></i>Atención inmediata
        </h2>
      </div>
      <div class="content-card__body p-3">
        <?php if ($alertas === [] && ($metricasPersonal['en_reposo_hoy'] ?? 0) === 0): ?>
          <div class="d-flex align-items-center gap-2 text-success py-2">
            <i class="bi bi-check-circle-fill fs-5" aria-hidden="true"></i>
            <div>
              <div class="fw-semibold">Operaciones al día</div>
              <div class="text-support small">No hay aulas sin docente ni ausencias médicas pendientes.</div>
            </div>
          </div>
        <?php else: ?>
          <div class="d-flex flex-column gap-2">
            <?php if (($metricasPersonal['en_reposo_hoy'] ?? 0) > 0): ?>
              <div class="alert alert-info py-2 px-3 mb-0 d-flex align-items-center gap-2">
                <i class="bi bi-heart-pulse text-danger flex-shrink-0" aria-hidden="true"></i>
                <div class="small">
                  <strong><?= (int) $metricasPersonal['en_reposo_hoy'] ?> empleado(s)</strong> en reposo médico hoy.
                  <a href="<?= BASE_URL ?>empleado/index" class="d-block text-primary fw-medium">Ver personal &rsaquo;</a>
                </div>
              </div>
            <?php endif; ?>

            <?php foreach ($alertas as $alerta): ?>
              <div class="alert alert-<?= htmlspecialchars($alerta['nivel']) ?> py-2 px-3 mb-0 d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-circle flex-shrink-0" aria-hidden="true"></i>
                <div class="small">
                  <strong><?= htmlspecialchars($alerta['seccion']) ?>:</strong> <?= htmlspecialchars($alerta['detalle']) ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <!-- Bloque 2: Accesos Directos Institucionales -->
    <section class="content-card" aria-labelledby="enlacesRapidosHeading">
      <div class="content-card__header">
        <h2 class="content-card__title mb-0" id="enlacesRapidosHeading">
          <i class="bi bi-link-45deg me-2" aria-hidden="true"></i>Gestión directa
        </h2>
      </div>
      <div class="list-group list-group-flush small">
        <a href="<?= BASE_URL ?>empleado/index" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-2">
          <div>
            <i class="bi bi-folder2-open text-success me-2" aria-hidden="true"></i>
            <strong>Expedientes del Personal</strong>
            <div class="text-support"><?= $esCoordinador ? 'Consulta del personal escolar' : 'Gestión y expedientes de trabajadores' ?></div>
          </div>
          <i class="bi bi-chevron-right text-muted" aria-hidden="true"></i>
        </a>
        <a href="<?= BASE_URL ?>teacherAssignment/index" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-2">
          <div>
            <i class="bi bi-person-video3 text-primary me-2" aria-hidden="true"></i>
            <strong>Asignación Docente</strong>
            <div class="text-support">Titularidad de aulas por grado y sección</div>
          </div>
          <i class="bi bi-chevron-right text-muted" aria-hidden="true"></i>
        </a>
        <a href="<?= BASE_URL ?>estudiantes/index" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-2">
          <div>
            <i class="bi bi-backpack text-info me-2" aria-hidden="true"></i>
            <strong>Expedientes de Estudiantes</strong>
            <div class="text-support">Búsqueda y consulta de fichas académicas</div>
          </div>
          <i class="bi bi-chevron-right text-muted" aria-hidden="true"></i>
        </a>
      </div>
    </section>
  </div>
</div>
