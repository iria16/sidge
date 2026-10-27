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
 * @var array  $metricasSalud
 * @var array  $tiposSangre
 * @var array  $filasAntropometricas
 * @var array  $distribucionTallas
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
  ob_start();
  ?>
  <a href="<?= BASE_URL ?>reporte/index<?= $idAnio > 0 ? '?anio=' . $idAnio : '' ?>" class="btn btn-primary">
    <i class="bi bi-file-earmark-bar-graph me-1" aria-hidden="true"></i> Ir a Reportes
  </a>
  <?php
  $pageHeaderActions = ob_get_clean();
  $pageHeading       = $pageTitle;
  $pageSubheading    = $pageDescription;
  include __DIR__ . '/../components/page-header.php';
  ?>

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
    <option value="salud" <?= $vista === 'salud' ? 'selected' : '' ?>>Vista: Salud, Medidas y Alertas</option>
    <option value="tallas" <?= $vista === 'tallas' ? 'selected' : '' ?>>Vista: Tallas de Uniforme</option>
  </select>
  <?php
  $panelFilters      = ob_get_clean();
  $panelHideSearch   = true;

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
  <?php elseif ($vista === 'salud'): ?>
    <colgroup>
      <col style="width: 20%;">
      <col style="width: 10%;">
      <col style="width: 10%;">
      <col style="width: 12%;">
      <col style="width: 12%;">
      <col style="width: 12%;">
      <col style="width: 12%;">
      <col style="width: 12%;">
    </colgroup>
  <?php elseif ($vista === 'tallas'): ?>
    <colgroup>
      <col style="width: 22%;">
      <col style="width: 10%;">
      <col style="width: 12%;">
      <col style="width: 12%;">
      <col style="width: 12%;">
      <col style="width: 16%;">
      <col style="width: 16%;">
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
  <?php elseif ($vista === 'salud'): ?>
    <tr>
      <th scope="col">Grado</th>
      <th scope="col" class="text-center">Matrícula</th>
      <th scope="col" class="text-center">Fichas</th>
      <th scope="col" class="text-center">Cobertura</th>
      <th scope="col" class="text-center text-info">Tipo de sangre</th>
      <th scope="col" class="text-center text-warning">Alergias</th>
      <th scope="col" class="text-center text-primary">Medicamentos</th>
      <th scope="col" class="text-center text-danger">Condiciones</th>
    </tr>
  <?php elseif ($vista === 'tallas'): ?>
    <tr>
      <th scope="col">Grado</th>
      <th scope="col" class="text-center">Matrícula</th>
      <th scope="col" class="text-center text-info">Con camisa</th>
      <th scope="col" class="text-center text-primary">Con pantalón</th>
      <th scope="col" class="text-center text-success">Con calzado</th>
      <th scope="col" class="text-center">Tallas completas</th>
      <th scope="col" class="text-end">Cobertura</th>
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
  $panelColspan = $vista === 'salud' ? 8 : 7;
  ob_start();
  if ($filas === []):
  ?>
    <tr>
      <td colspan="<?= $panelColspan ?>" class="text-center text-support py-4">
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

    elseif ($vista === 'salud'):
      $totSaludAlumnos = 0;
      $totSaludFichas = 0;
      $totSaludTipo = 0;
      $totSaludAlergias = 0;
      $totSaludMedicamentos = 0;
      $totSaludCondiciones = 0;
      foreach ($filas as $f):
        $alumnos     = (int) ($f['total_estudiantes'] ?? 0);
        $fichas      = (int) ($f['fichas'] ?? 0);
        $cobertura   = (int) ($f['cobertura'] ?? 0);
        $tipo        = (int) ($f['con_tipo_sangre'] ?? 0);
        $alergias    = (int) ($f['con_alergias'] ?? 0);
        $medicamentos = (int) ($f['con_medicamentos'] ?? 0);
        $condiciones = (int) ($f['con_condiciones'] ?? 0);

        $totSaludAlumnos += $alumnos;
        $totSaludFichas += $fichas;
        $totSaludTipo += $tipo;
        $totSaludAlergias += $alergias;
        $totSaludMedicamentos += $medicamentos;
        $totSaludCondiciones += $condiciones;

        $badgeCobertura = $cobertura >= 80 ? 'success' : ($cobertura > 0 ? 'warning' : 'danger');
      ?>
        <tr>
          <td class="fw-semibold"><?= htmlspecialchars((string) $f['grado']) ?></td>
          <td class="text-center"><?= $alumnos ?></td>
          <td class="text-center"><?= $fichas ?></td>
          <td class="text-center">
            <span class="badge text-bg-<?= $badgeCobertura ?>"><?= $cobertura ?>%</span>
          </td>
          <td class="text-center <?= $tipo > 0 ? 'text-info fw-semibold' : 'text-support' ?>"><?= $tipo ?></td>
          <td class="text-center <?= $alergias > 0 ? 'text-warning fw-semibold' : 'text-support' ?>"><?= $alergias ?></td>
          <td class="text-center <?= $medicamentos > 0 ? 'text-primary fw-semibold' : 'text-support' ?>"><?= $medicamentos ?></td>
          <td class="text-center <?= $condiciones > 0 ? 'text-danger fw-semibold' : 'text-support' ?>"><?= $condiciones ?></td>
        </tr>
      <?php
      endforeach;
      $totSaludCobertura = $totSaludAlumnos > 0
          ? (int) round(($totSaludFichas / $totSaludAlumnos) * 100)
          : 0;
      $badgeCoberturaTotal = $totSaludCobertura >= 80 ? 'success' : ($totSaludCobertura > 0 ? 'warning' : 'danger');
      ?>
        <tr class="table-light fw-semibold">
          <td>Total</td>
          <td class="text-center"><?= $totSaludAlumnos ?></td>
          <td class="text-center"><?= $totSaludFichas ?></td>
          <td class="text-center">
            <span class="badge text-bg-<?= $badgeCoberturaTotal ?>"><?= $totSaludCobertura ?>%</span>
          </td>
          <td class="text-center"><?= $totSaludTipo ?></td>
          <td class="text-center"><?= $totSaludAlergias ?></td>
          <td class="text-center"><?= $totSaludMedicamentos ?></td>
          <td class="text-center"><?= $totSaludCondiciones ?></td>
        </tr>
      <?php
    elseif ($vista === 'tallas'):
      $totTallasAlumnos = 0;
      $totTallasCamisa = 0;
      $totTallasPantalon = 0;
      $totTallasZapato = 0;
      $totTallasCompletas = 0;
      foreach ($filas as $f):
        $alumnosTallas = (int) ($f['total_estudiantes'] ?? 0);
        $camisa        = (int) ($f['con_camisa'] ?? 0);
        $pantalon      = (int) ($f['con_pantalon'] ?? 0);
        $zapato        = (int) ($f['con_zapato'] ?? 0);
        $completas     = (int) ($f['con_todas'] ?? 0);
        $cobertura     = (int) ($f['cobertura'] ?? 0);

        $totTallasAlumnos += $alumnosTallas;
        $totTallasCamisa += $camisa;
        $totTallasPantalon += $pantalon;
        $totTallasZapato += $zapato;
        $totTallasCompletas += $completas;

        $badgeTallas = $cobertura >= 80 ? 'success' : ($cobertura > 0 ? 'warning' : 'danger');
      ?>
        <tr>
          <td class="fw-semibold"><?= htmlspecialchars((string) $f['grado']) ?></td>
          <td class="text-center"><?= $alumnosTallas ?></td>
          <td class="text-center <?= $camisa > 0 ? 'text-info fw-semibold' : 'text-support' ?>"><?= $camisa ?></td>
          <td class="text-center <?= $pantalon > 0 ? 'text-primary fw-semibold' : 'text-support' ?>"><?= $pantalon ?></td>
          <td class="text-center <?= $zapato > 0 ? 'text-success fw-semibold' : 'text-support' ?>"><?= $zapato ?></td>
          <td class="text-center <?= $completas > 0 ? 'fw-semibold' : 'text-support' ?>"><?= $completas ?></td>
          <td class="text-end">
            <span class="badge text-bg-<?= $badgeTallas ?>"><?= $cobertura ?>%</span>
          </td>
        </tr>
      <?php
      endforeach;
      $totTallasCobertura = $totTallasAlumnos > 0
          ? (int) round(($totTallasCompletas / $totTallasAlumnos) * 100)
          : 0;
      $badgeTallasTotal = $totTallasCobertura >= 80 ? 'success' : ($totTallasCobertura > 0 ? 'warning' : 'danger');
      ?>
        <tr class="table-light fw-semibold">
          <td>Total</td>
          <td class="text-center"><?= $totTallasAlumnos ?></td>
          <td class="text-center"><?= $totTallasCamisa ?></td>
          <td class="text-center"><?= $totTallasPantalon ?></td>
          <td class="text-center"><?= $totTallasZapato ?></td>
          <td class="text-center"><?= $totTallasCompletas ?></td>
          <td class="text-end">
            <span class="badge text-bg-<?= $badgeTallasTotal ?>"><?= $totTallasCobertura ?>%</span>
          </td>
        </tr>
      <?php
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
  $panelSummary = match ($vista) {
      'salud'  => 'Mostrando ' . $totalFilas . ' grados · ' . ($totSaludAlumnos ?? 0) . ' estudiantes · ' . ($totSaludFichas ?? 0) . ' fichas',
      'tallas' => 'Mostrando ' . $totalFilas . ' grados · ' . ($totTallasAlumnos ?? 0) . ' estudiantes · ' . ($totTallasCompletas ?? 0) . ' con tallas completas',
      default  => 'Mostrando ' . $totalFilas . ' registros estadísticos',
  };

  include __DIR__ . '/../components/data-panel.php';
  ?>

  <?php if ($vista === 'tallas'): ?>
  <div class="row g-3 mt-1">
    <?php foreach ($distribucionTallas as $prenda => $datos): ?>
      <?php
      $tallasPrenda = $datos['tallas'] ?? [];
      $totalPrenda = array_sum($tallasPrenda);
      $tallasConDatos = array_filter($tallasPrenda, static fn ($cantidad): bool => (int) $cantidad > 0);
      $iconoPrenda = match ($prenda) {
          'camisa'   => 'bi-person-standing',
          'pantalon' => 'bi-tags',
          default    => 'bi-rulers',
      };
      ?>
      <div class="col-lg-4">
        <div class="content-card h-100">
          <div class="content-card__header">
            <h2 class="content-card__title">
              <i class="bi <?= $iconoPrenda ?> me-2" aria-hidden="true"></i><?= htmlspecialchars((string) ($datos['titulo'] ?? $prenda)) ?>
            </h2>
          </div>
          <div class="content-card__body">
            <p class="text-support small mb-3">
              <?= (int) ($datos['total'] ?? 0) ?> estudiantes con talla registrada.
            </p>
            <table class="table mb-0">
              <thead>
                <tr>
                  <th scope="col">Talla</th>
                  <th scope="col" class="text-center">Estudiantes</th>
                  <th scope="col" class="text-end">Porcentaje</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($tallasConDatos === []): ?>
                  <tr>
                    <td colspan="3" class="text-center text-support py-4">Sin tallas registradas.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($tallasConDatos as $talla => $cantidad): ?>
                    <?php
                    $esSinRegistrar = $talla === 'sin_registrar';
                    $porcentajeTalla = $totalPrenda > 0 ? round(($cantidad / $totalPrenda) * 100, 1) : 0;
                    ?>
                    <tr>
                      <td class="fw-semibold <?= $esSinRegistrar ? 'text-support' : '' ?>">
                        <?= htmlspecialchars($esSinRegistrar ? 'Sin registrar' : (string) $talla) ?>
                      </td>
                      <td class="text-center"><?= (int) $cantidad ?></td>
                      <td class="text-end"><?= $porcentajeTalla ?>%</td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($vista === 'salud'): ?>
  <div class="content-card mt-4">
    <div class="content-card__header">
      <h2 class="content-card__title">
        <i class="bi bi-rulers me-2" aria-hidden="true"></i>Medidas antropométricas por grado
      </h2>
    </div>
    <div class="content-card__body">
      <p class="text-support small mb-3">
        Peso y estatura registrados en la ficha del estudiante. La cobertura cuenta a quienes
        tienen ambas medidas; los promedios solo usan los valores registrados.
      </p>
      <table class="table mb-0">
        <thead>
          <tr>
            <th scope="col">Grado</th>
            <th scope="col" class="text-center">Matrícula</th>
            <th scope="col" class="text-center">Con peso</th>
            <th scope="col" class="text-end">Peso prom.</th>
            <th scope="col" class="text-center">Con estatura</th>
            <th scope="col" class="text-end">Estatura prom.</th>
            <th scope="col" class="text-end">Cobertura</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($filasAntropometricas === []): ?>
            <tr>
              <td colspan="7" class="text-center text-support py-4">
                No se encontraron registros para los filtros seleccionados en este período escolar.
              </td>
            </tr>
          <?php else: ?>
            <?php
            $totAntroAlumnos = 0;
            $totAntroPeso    = 0;
            $totAntroEsta    = 0;
            $totAntroAmbas   = 0;
            $sumaAntroPeso   = 0.0;
            $sumaAntroEsta   = 0.0;

            foreach ($filasAntropometricas as $f):
              $alumnosAntro  = (int) ($f['total_estudiantes'] ?? 0);
              $conPeso       = (int) ($f['con_peso'] ?? 0);
              $conEsta       = (int) ($f['con_estatura'] ?? 0);
              $conAmbas      = (int) ($f['con_ambas'] ?? 0);
              $coberturaAntro = (int) ($f['cobertura'] ?? 0);

              $totAntroAlumnos += $alumnosAntro;
              $totAntroPeso    += $conPeso;
              $totAntroEsta    += $conEsta;
              $totAntroAmbas   += $conAmbas;
              $sumaAntroPeso   += (float) ($f['suma_peso'] ?? 0);
              $sumaAntroEsta   += (float) ($f['suma_estatura'] ?? 0);

              $promPesoFila = $f['peso_promedio'] !== null
                  ? number_format((float) $f['peso_promedio'], 1, ',', '.') . ' kg'
                  : '—';
              $promEstaFila = $f['estatura_promedio'] !== null
                  ? number_format((float) $f['estatura_promedio'], 0, ',', '.') . ' cm'
                  : '—';
              $badgeAntro = $coberturaAntro >= 80 ? 'success' : ($coberturaAntro > 0 ? 'warning' : 'danger');
            ?>
              <tr>
                <td class="fw-semibold"><?= htmlspecialchars((string) $f['grado']) ?></td>
                <td class="text-center"><?= $alumnosAntro ?></td>
                <td class="text-center <?= $conPeso > 0 ? 'text-info fw-semibold' : 'text-support' ?>"><?= $conPeso ?></td>
                <td class="text-end <?= $conPeso > 0 ? '' : 'text-support' ?>"><?= $promPesoFila ?></td>
                <td class="text-center <?= $conEsta > 0 ? 'text-primary fw-semibold' : 'text-support' ?>"><?= $conEsta ?></td>
                <td class="text-end <?= $conEsta > 0 ? '' : 'text-support' ?>"><?= $promEstaFila ?></td>
                <td class="text-end">
                  <span class="badge text-bg-<?= $badgeAntro ?>"><?= $coberturaAntro ?>%</span>
                </td>
              </tr>
            <?php endforeach; ?>

            <?php
            $promPesoTotal = $totAntroPeso > 0
                ? number_format($sumaAntroPeso / $totAntroPeso, 1, ',', '.') . ' kg'
                : '—';
            $promEstaTotal = $totAntroEsta > 0
                ? number_format($sumaAntroEsta / $totAntroEsta, 0, ',', '.') . ' cm'
                : '—';
            $coberturaTotalAntro = $totAntroAlumnos > 0
                ? (int) round(($totAntroAmbas / $totAntroAlumnos) * 100)
                : 0;
            $badgeTotalAntro = $coberturaTotalAntro >= 80
                ? 'success'
                : ($coberturaTotalAntro > 0 ? 'warning' : 'danger');
            ?>
            <tr class="table-light fw-semibold">
              <td>Total</td>
              <td class="text-center"><?= $totAntroAlumnos ?></td>
              <td class="text-center"><?= $totAntroPeso ?></td>
              <td class="text-end"><?= $promPesoTotal ?></td>
              <td class="text-center"><?= $totAntroEsta ?></td>
              <td class="text-end"><?= $promEstaTotal ?></td>
              <td class="text-end">
                <span class="badge text-bg-<?= $badgeTotalAntro ?>"><?= $coberturaTotalAntro ?>%</span>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($vista === 'salud'): ?>
  <?php $totalSangre = (int) ($metricasSalud['total_estudiantes'] ?? 0); ?>
  <div class="content-card mt-4">
    <div class="content-card__header">
      <h2 class="content-card__title">
        <i class="bi bi-droplet-half me-2" aria-hidden="true"></i>Distribución por tipo de sangre
      </h2>
    </div>
    <div class="content-card__body">
      <p class="text-support small mb-3">
        Incluye a los estudiantes sin ficha médica y a las fichas sin tipo de sangre registrado.
      </p>
      <table class="table mb-0">
        <thead>
          <tr>
            <th scope="col">Tipo de sangre</th>
            <th scope="col" class="text-center">Estudiantes</th>
            <th scope="col" class="text-end">Porcentaje</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($tiposSangre as $tipoSangre => $cantidad): ?>
            <?php
            $esSinRegistrar = $tipoSangre === 'sin_registrar';
            $porcentajeTipo = $totalSangre > 0 ? round(($cantidad / $totalSangre) * 100, 1) : 0;
            ?>
            <tr>
              <td class="fw-semibold <?= $esSinRegistrar ? 'text-support' : '' ?>">
                <?= htmlspecialchars($esSinRegistrar ? 'Sin registrar' : $tipoSangre) ?>
              </td>
              <td class="text-center"><?= (int) $cantidad ?></td>
              <td class="text-end"><?= $porcentajeTipo ?>%</td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php
$pageContent = ob_get_clean();

include __DIR__ . '/../layouts/app.php';
