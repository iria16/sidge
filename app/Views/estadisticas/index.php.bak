<?php
declare(strict_types=1);

/**
 * Vista: estadisticas/index.php
 * Interfaz única de Estadísticas Institucionales para Dirección y Coordinación.
 * Estructurada bajo el patrón estándar de Gestión de Grados y Secciones.
 *
 * Variables esperadas desde EstadisticaController::index():
 * @var array  $anios
 * @var int    $idAnio
 * @var string $nombrePeriodo
 * @var string $vista
 * @var string $q
 * @var array  $metricasMatricula
 * @var array  $metricasCapacidad
 * @var array  $metricasPersonal
 * @var array  $resumenRendimiento
 * @var array  $filas
 */

$pageTitle       = 'Estadísticas Institucionales';
$pageDescription = 'Indicadores demográficos, rendimiento escolar, capacidad y talento humano del plantel.';
$currentNav      = 'estadisticas';

$breadcrumbs = [
    ['label' => 'Seguimiento institucional', 'href' => null],
    ['label' => 'Estadísticas',              'href' => null],
];

ob_start();
?>
<div class="container-fluid px-0">
  <?php
  // ---------- Page Header ----------
  $pageHeaderActions = '';
  $pageHeading       = $pageTitle;
  $pageSubheading    = $pageDescription;
  include __DIR__ . '/../components/page-header.php';
  ?>

  <!-- ---------- Stat Cards: 4 KPIs Principales ---------- -->
  <div class="row g-3 mb-4">
    <!-- Card 1: Matrícula -->
    <div class="col-sm-6 col-xl-3">
      <?php
      $statLabel     = 'Matrícula cursando';
      $statValue     = number_format((int) ($metricasMatricula['total_estudiantes'] ?? 0), 0, ',', '.') . ' estud.';
      $statIcon      = 'bi-people';
      $statTone      = 'primary';
      $statVariant   = 'default';
      $statTrend     = ($metricasMatricula['total_estudiantes'] ?? 0) > 0 ? 'En curso' : 'Sin matrícula';
      $statTrendType = 'neutral';
      $statMeta      = 'Varones: ' . ($metricasMatricula['varones'] ?? 0) . ' (' . ($metricasMatricula['porcentaje_varones'] ?? 0) . '%) · Hembras: ' . ($metricasMatricula['hembras'] ?? 0) . ' (' . ($metricasMatricula['porcentaje_hembras'] ?? 0) . '%)';
      $statProgress  = null;
      $statHref      = '';
      include __DIR__ . '/../components/stat-card.php';
      ?>
    </div>

    <!-- Card 2: Capacidad -->
    <div class="col-sm-6 col-xl-3">
      <?php
      $porcOcup      = (int) ($metricasCapacidad['porcentaje_ocupacion'] ?? 0);
      $statLabel     = 'Capacidad institucional';
      $statValue     = $porcOcup . '% ocupación';
      $statIcon      = 'bi-pie-chart';
      $statTone      = 'info';
      $statVariant   = 'default';
      $statTrend     = ($metricasCapacidad['cupos_disponibles'] ?? 0) . ' cupos libres';
      $statTrendType = 'neutral';
      $statMeta      = ($metricasCapacidad['cupos_ocupados'] ?? 0) . ' de ' . ($metricasCapacidad['cupos_totales'] ?? 0) . ' cupos configurados';
      $statProgress  = $porcOcup;
      $statHref      = '';
      include __DIR__ . '/../components/stat-card.php';
      ?>
    </div>

    <!-- Card 3: Rendimiento -->
    <div class="col-sm-6 col-xl-3">
      <?php
      $porcCarga     = (int) ($resumenRendimiento['porcentaje_carga'] ?? 0);
      $statLabel     = 'Carga de literales';
      $statValue     = $porcCarga . '% completado';
      $statIcon      = 'bi-mortarboard';
      $statTone      = $porcCarga === 100 ? 'success' : ($porcCarga > 0 ? 'info' : 'warning');
      $statVariant   = 'default';
      $statTrend     = ($resumenRendimiento['total_promovidos'] ?? 0) . ' promovidos';
      $statTrendType = 'up';
      $statMeta      = ($resumenRendimiento['total_cargados'] ?? 0) . ' evaluados · ' . ($resumenRendimiento['total_pendientes'] ?? 0) . ' sin literal';
      $statProgress  = $porcCarga;
      $statHref      = '';
      include __DIR__ . '/../components/stat-card.php';
      ?>
    </div>

    <!-- Card 4: Personal -->
    <div class="col-sm-6 col-xl-3">
      <?php
      $reposos       = (int) ($metricasPersonal['en_reposo_hoy'] ?? 0);
      $statLabel     = 'Personal escolar';
      $statValue     = number_format((int) ($metricasPersonal['empleados_activos'] ?? 0), 0, ',', '.') . ' activos';
      $statIcon      = 'bi-person-badge';
      $statTone      = $reposos > 0 ? 'warning' : 'success';
      $statVariant   = $reposos > 0 ? 'alert' : 'default';
      $statTrend     = $reposos > 0 ? ($reposos . ' en reposo hoy') : 'Nómina operativa';
      $statTrendType = $reposos > 0 ? 'down' : 'up';
      $statMeta      = ($metricasPersonal['docentes_activos'] ?? 0) . ' docentes · ' . ($metricasPersonal['administrativos'] ?? 0) . ' admin · ' . ($metricasPersonal['obreros'] ?? 0) . ' obreros';
      $statProgress  = null;
      $statHref      = '';
      include __DIR__ . '/../components/stat-card.php';
      ?>
    </div>
  </div>

  <?php
  // ---------- Data Panel: Filtros de Período y Vista ----------
  $panelFormAction = BASE_URL . 'estadistica/index';

  ob_start();
  ?>
  <div class="data-panel__active-year" role="status" aria-label="Año escolar consultado: <?= htmlspecialchars($nombrePeriodo) ?>">
    <i class="bi bi-calendar3" aria-hidden="true"></i>
    <strong><?= htmlspecialchars($nombrePeriodo !== '' ? $nombrePeriodo : 'Todos los años') ?></strong>
  </div>

  <label for="filtroAnio" class="visually-hidden">Año Escolar</label>
  <select id="filtroAnio" name="anio" class="form-select" onchange="this.form.submit()">
    <?php foreach ($anios as $a): ?>
      <?php
      $idA      = (int) $a['id_anio_escolar'];
      $nomA     = (string) $a['nombre_periodo'];
      $estA     = (string) $a['estado'];
      $esActivo = $estA === 'ACTIVO';
      ?>
      <option value="<?= $idA ?>" <?= $idA === $idAnio ? 'selected' : '' ?>>
        Período: <?= htmlspecialchars($nomA) ?><?= $esActivo ? ' (Activo)' : ' (' . htmlspecialchars($estA) . ')' ?>
      </option>
    <?php endforeach; ?>
  </select>

  <label for="filtroVista" class="visually-hidden">Categoría Estadística</label>
  <select id="filtroVista" name="vista" class="form-select" onchange="this.form.submit()">
    <option value="matricula" <?= $vista === 'matricula' ? 'selected' : '' ?>>Vista: Matrícula por Grado y Género</option>
    <option value="rendimiento" <?= $vista === 'rendimiento' ? 'selected' : '' ?>>Vista: Rendimiento y Literales por Aula</option>
    <option value="edades" <?= $vista === 'edades' ? 'selected' : '' ?>>Vista: Distribución por Edades y Rezago</option>
    <option value="cobertura" <?= $vista === 'cobertura' ? 'selected' : '' ?>>Vista: Infraestructura y Asignación Docente</option>
    <option value="personal" <?= $vista === 'personal' ? 'selected' : '' ?>>Vista: Talento Humano y Personal Escolar</option>
  </select>
  <?php
  $panelFilters           = ob_get_clean();
  $panelSearchName        = 'q';
  $panelSearchValue       = $q;
  $panelSearchPlaceholder = 'Buscar en la tabla...';

  // ---------- Tabla y Cabeceras según la vista ----------
  ob_start();
  if ($vista === 'rendimiento'):
  ?>
    <colgroup>
      <col style="width: 22%;">
      <col style="width: 24%;">
      <col style="width: 14%;">
      <col style="width: 14%;">
      <col style="width: 14%;">
      <col style="width: 12%;">
    </colgroup>
  <?php elseif ($vista === 'edades'): ?>
    <colgroup>
      <col style="width: 25%;">
      <col style="width: 25%;">
      <col style="width: 25%;">
      <col style="width: 25%;">
    </colgroup>
  <?php elseif ($vista === 'cobertura'): ?>
    <colgroup>
      <col style="width: 20%;">
      <col style="width: 15%;">
      <col style="width: 15%;">
      <col style="width: 15%;">
      <col style="width: 23%;">
      <col style="width: 12%;">
    </colgroup>
  <?php elseif ($vista === 'personal'): ?>
    <colgroup>
      <col style="width: 25%;">
      <col style="width: 13%;">
      <col style="width: 14%;">
      <col style="width: 12%;">
      <col style="width: 12%;">
      <col style="width: 12%;">
      <col style="width: 12%;">
    </colgroup>
  <?php else: // matricula ?>
    <colgroup>
      <col style="width: 22%;">
      <col style="width: 13%;">
      <col style="width: 15%;">
      <col style="width: 15%;">
      <col style="width: 15%;">
      <col style="width: 10%;">
      <col style="width: 10%;">
    </colgroup>
  <?php
  endif;
  $panelColgroup = ob_get_clean();

  // Thead
  ob_start();
  if ($vista === 'rendimiento'):
  ?>
    <tr>
      <th scope="col">Aula</th>
      <th scope="col">Docente Titular</th>
      <th scope="col" class="text-center">Matrícula</th>
      <th scope="col" class="text-center text-success">Promovidos</th>
      <th scope="col" class="text-center text-danger">No promovidos</th>
      <th scope="col" class="text-end">Avance</th>
    </tr>
  <?php elseif ($vista === 'edades'): ?>
    <tr>
      <th scope="col">Edad Cumplida</th>
      <th scope="col" class="text-center text-primary">Varones (M)</th>
      <th scope="col" class="text-center text-danger">Hembras (F)</th>
      <th scope="col" class="text-end fw-semibold">Total Estudiantes</th>
    </tr>
  <?php elseif ($vista === 'cobertura'): ?>
    <tr>
      <th scope="col">Aula</th>
      <th scope="col">Turno</th>
      <th scope="col" class="text-center">Capacidad</th>
      <th scope="col" class="text-center">Matriculados</th>
      <th scope="col">Docente Asignado</th>
      <th scope="col" class="text-end">Estado</th>
    </tr>
  <?php elseif ($vista === 'personal'): ?>
    <tr>
      <th scope="col">Cargo Institucional</th>
      <th scope="col">Turno</th>
      <th scope="col" class="text-center">Total Nómina</th>
      <th scope="col" class="text-center text-success">Activos</th>
      <th scope="col" class="text-center text-secondary">Inactivos</th>
      <th scope="col" class="text-center text-primary">Género (V / H)</th>
      <th scope="col" class="text-end">En Reposo</th>
    </tr>
  <?php else: // matricula ?>
    <tr>
      <th scope="col">Grado</th>
      <th scope="col" class="text-center">Aulas</th>
      <th scope="col" class="text-center">Capacidad</th>
      <th scope="col" class="text-center text-primary">Varones (M)</th>
      <th scope="col" class="text-center text-danger">Hembras (F)</th>
      <th scope="col" class="text-center fw-semibold">Total Cursando</th>
      <th scope="col" class="text-end">% Ocupación</th>
    </tr>
  <?php
  endif;
  $panelTableHead = ob_get_clean();

  // Tbody
  ob_start();
  if ($filas === []):
  ?>
    <tr>
      <td colspan="7" class="text-center text-support py-4">
        No se encontraron registros para los filtros seleccionados en este período escolar.
      </td>
    </tr>
  <?php
  else:
    if ($vista === 'rendimiento'):
      foreach ($filas as $f):
        $porc = (int) ($f['porcentaje_carga'] ?? 0);
        $badgeClass = $porc === 100 ? 'success' : ($porc > 0 ? 'info' : 'secondary');
      ?>
        <tr>
          <td class="fw-semibold"><?= htmlspecialchars((string) ($f['grado'] . ' "' . $f['seccion'] . '"')) ?></td>
          <td>
            <?php if (!empty($f['docente_nombre'])): ?>
              <?= htmlspecialchars((string) $f['docente_nombre']) ?>
            <?php else: ?>
              <span class="badge text-bg-warning">Sin docente asignado</span>
            <?php endif; ?>
          </td>
          <td class="text-center"><?= (int) $f['total_estudiantes'] ?></td>
          <td class="text-center fw-medium text-success"><?= (int) $f['total_promovidos'] ?></td>
          <td class="text-center fw-medium text-danger"><?= (int) $f['total_no_promovidos'] ?></td>
          <td class="text-end">
            <span class="badge text-bg-<?= $badgeClass ?>"><?= $porc ?>%</span>
          </td>
        </tr>
      <?php
      endforeach;

    elseif ($vista === 'edades'):
      $totVar = 0; $totHem = 0; $totGeneral = 0;
      foreach ($filas as $f):
        $totVar += (int) $f['varones'];
        $totHem += (int) $f['hembras'];
        $totGeneral += (int) $f['total'];
      ?>
        <tr>
          <td class="fw-semibold"><?= (int) $f['edad'] ?> años</td>
          <td class="text-center fw-medium text-primary"><?= (int) $f['varones'] ?></td>
          <td class="text-center fw-medium text-danger"><?= (int) $f['hembras'] ?></td>
          <td class="text-end fw-bold"><?= (int) $f['total'] ?></td>
        </tr>
      <?php
      endforeach;

    elseif ($vista === 'cobertura'):
      foreach ($filas as $f):
        $cap   = (int) ($f['capacidad_maxima'] ?? 0);
        $mat   = (int) ($f['total_matricula'] ?? 0);
        $tiene = !empty($f['docente_titular']);
      ?>
        <tr>
          <td class="fw-semibold"><?= htmlspecialchars((string) ($f['grado'] . ' "' . $f['seccion'] . '"')) ?></td>
          <td><?= htmlspecialchars((string) ($f['turno'] ?? '—')) ?></td>
          <td class="text-center text-support"><?= $cap ?></td>
          <td class="text-center fw-medium <?= $mat > $cap ? 'text-danger' : '' ?>"><?= $mat ?></td>
          <td>
            <?php if ($tiene): ?>
              <?= htmlspecialchars((string) $f['docente_titular']) ?>
            <?php else: ?>
              <span class="badge text-bg-warning">Sin titular</span>
            <?php endif; ?>
          </td>
          <td class="text-end">
            <span class="badge text-bg-<?= $tiene ? 'success' : 'danger' ?>">
              <?= $tiene ? 'Asignada' : 'Por asignar' ?>
            </span>
          </td>
        </tr>
      <?php
      endforeach;

    elseif ($vista === 'personal'):
      $totPersonal = 0; $totAct = 0; $totInact = 0; $totVar = 0; $totHem = 0; $totRep = 0;
      foreach ($filas as $f):
        $totPersonal += (int) $f['total_personal'];
        $totAct      += (int) $f['activos'];
        $totInact    += (int) $f['inactivos'];
        $totVar      += (int) $f['varones'];
        $totHem      += (int) $f['hembras'];
        $totRep      += (int) $f['en_reposo'];
      ?>
        <tr>
          <td class="fw-semibold">
            <i class="bi bi-briefcase me-1 text-primary" aria-hidden="true"></i>
            <?= htmlspecialchars((string) $f['cargo']) ?>
          </td>
          <td><span class="badge bg-light text-dark border"><?= htmlspecialchars((string) $f['turno']) ?></span></td>
          <td class="text-center fw-bold"><?= (int) $f['total_personal'] ?> <span class="text-support small">(<?= $f['porcentaje'] ?>%)</span></td>
          <td class="text-center text-success fw-medium"><?= (int) $f['activos'] ?></td>
          <td class="text-center <?= (int) $f['inactivos'] > 0 ? 'text-danger fw-medium' : 'text-support' ?>"><?= (int) $f['inactivos'] ?></td>
          <td class="text-center text-support small"><?= (int) $f['varones'] ?> var. / <?= (int) $f['hembras'] ?> hem.</td>
          <td class="text-end">
            <?php if ((int) $f['en_reposo'] > 0): ?>
              <span class="badge bg-warning text-dark"><i class="bi bi-heart-pulse me-1"></i><?= (int) $f['en_reposo'] ?> reposo</span>
            <?php else: ?>
              <span class="text-support">—</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php
      endforeach;

    else: // matricula
      $sumSec = 0; $sumCap = 0; $sumVar = 0; $sumHem = 0; $sumTot = 0;
      foreach ($filas as $f):
        $cap  = (int) $f['capacidad'];
        $tot  = (int) $f['total_estudiantes'];
        $porc = $cap > 0 ? (int) min(100, round(($tot / $cap) * 100)) : 0;
        $sumSec += (int) $f['total_secciones'];
        $sumCap += $cap;
        $sumVar += (int) $f['varones'];
        $sumHem += (int) $f['hembras'];
        $sumTot += $tot;
      ?>
        <tr>
          <td class="fw-semibold"><?= htmlspecialchars((string) $f['grado']) ?></td>
          <td class="text-center"><?= (int) $f['total_secciones'] ?></td>
          <td class="text-center text-support"><?= $cap ?></td>
          <td class="text-center fw-medium text-primary"><?= (int) $f['varones'] ?></td>
          <td class="text-center fw-medium text-danger"><?= (int) $f['hembras'] ?></td>
          <td class="text-center fw-bold"><?= $tot ?></td>
          <td class="text-end">
            <span class="badge text-bg-<?= $porc >= 95 ? 'warning' : 'light border' ?>"><?= $porc ?>%</span>
          </td>
        </tr>
      <?php
      endforeach;
    endif;
  endif;
  $panelTableBody = ob_get_clean();

  $totalFilas   = count($filas);
  $panelSummary = 'Mostrando ' . $totalFilas . ' registros estadísticos';

  include __DIR__ . '/../components/data-panel.php';
  ?>
</div>
<?php
$pageContent = ob_get_clean();

include __DIR__ . '/../layouts/app.php';
