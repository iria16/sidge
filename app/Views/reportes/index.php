<?php
declare(strict_types=1);

/**
 * Vista: reportes/index.php
 * Interfaz única del Centro de Reportes Institucionales para Dirección y Coordinación.
 * Estructurada bajo el patrón estándar de Gestión de Grados y Secciones.
 *
 * Variables esperadas desde ReporteController::index():
 * @var array  $anios
 * @var int    $idAnio
 * @var string $nombrePeriodo
 * @var string $tipo
 * @var string $q
 * @var int|null $idGrado
 * @var int|null $idSeccion
 * @var array  $grados
 * @var array  $secciones
 * @var array  $datosReporte
 * @var array<string,string> $nombresReporte
 * @var array<string,mixed>|null $resumenRepresentantes
 */

$pageTitle       = 'Centro de Reportes Institucionales';
$pageDescription = 'Emisión oficial, consulta y exportación de nóminas, estadísticas y balances del plantel.';
$currentNav      = 'reportes';

$breadcrumbs = [
    ['label' => 'Seguimiento institucional', 'href' => null],
    ['label' => 'Reportes',                  'href' => null],
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

  <?php
  // ---------- Data Panel: Filtros de Reporte y Contexto ----------
  $panelFormAction = BASE_URL . 'reporte/index';

  ob_start();
  ?>
  <div class="data-panel__active-year" role="status" aria-label="Año escolar consultado: <?= htmlspecialchars($nombrePeriodo) ?>">
    <i class="bi bi-calendar3" aria-hidden="true"></i>
    <strong><?= htmlspecialchars($nombrePeriodo !== '' ? $nombrePeriodo : 'Todos') ?></strong>
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
        Año: <?= htmlspecialchars($nomA) ?><?= $esActivo ? ' (Activo)' : ' (' . htmlspecialchars($estA) . ')' ?>
      </option>
    <?php endforeach; ?>
  </select>

  <label for="filtroTipo" class="visually-hidden">Tipo de Reporte</label>
  <select id="filtroTipo" name="tipo" class="form-select" onchange="this.form.submit()">
    <?php foreach ($nombresReporte as $clave => $etiqueta): ?>
      <option value="<?= $clave ?>" <?= $tipo === $clave ? 'selected' : '' ?>><?= $etiqueta ?></option>
    <?php endforeach; ?>
  </select>

  <?php if ($tipo === 'nomina_estudiantes'): ?>
    <label for="filtroGrado" class="visually-hidden">Grado</label>
    <select id="filtroGrado" name="grado" class="form-select" onchange="this.form.submit()">
      <option value="">Grado (Todos)</option>
      <?php foreach ($grados as $g): ?>
        <option value="<?= (int) $g['id_grado'] ?>" <?= $idGrado === (int) $g['id_grado'] ? 'selected' : '' ?>>
          <?= htmlspecialchars((string) $g['nombre']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <label for="filtroSeccion" class="visually-hidden">Sección</label>
    <select id="filtroSeccion" name="seccion" class="form-select" onchange="this.form.submit()">
      <option value="">Sección (Todas)</option>
      <?php foreach ($secciones as $s): ?>
        <option value="<?= (int) $s['id_seccion'] ?>" <?= $idSeccion === (int) $s['id_seccion'] ? 'selected' : '' ?>>
          <?= htmlspecialchars((string) $s['nombre']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  <?php endif; ?>

  <?php
  $parametrosPreview = array_filter([
      'anio'    => $idAnio,
      'tipo'    => $tipo,
      'grado'   => $idGrado,
      'seccion' => $idSeccion,
      'q'       => $q,
  ], static fn ($valor): bool => $valor !== null && $valor !== '');

  $urlPreview  = BASE_URL . 'reporte/preview?' . http_build_query($parametrosPreview);
  $urlDownload = $urlPreview . '&descargar=1';
  ?>
  <button type="button"
          class="btn btn-primary d-inline-flex align-items-center gap-1 shadow-sm btn-previsualizar-reporte"
          data-url-preview="<?= htmlspecialchars($urlPreview) ?>"
          data-url-download="<?= htmlspecialchars($urlDownload) ?>"
          data-titulo="<?= htmlspecialchars($nombresReporte[$tipo] ?? 'Reporte oficial') ?>"
          data-periodo="<?= htmlspecialchars($nombrePeriodo) ?>"
          title="Visualizar el reporte antes de imprimir o descargar">
    <i class="bi bi-eye" aria-hidden="true"></i>
    <span>Visualizar</span>
  </button>
  <?php
  $panelFilters           = ob_get_clean();
  $panelSearchName        = 'q';
  $panelSearchValue       = $q;
  $panelSearchPlaceholder = 'Buscar en el reporte...';

  // ---------- Tabla según tipo de reporte ----------
  ob_start();
  if ($tipo === 'nomina_estudiantes'):
  ?>
    <colgroup>
      <col style="width: 5%;">
      <col style="width: 13%;">
      <col style="width: 27%;">
      <col style="width: 6%;">
      <col style="width: 7%;">
      <col style="width: 14%;">
      <col style="width: 16%;">
      <col style="width: 12%;">
    </colgroup>
    <tr>
      <th scope="col" class="text-center">#</th>
      <th scope="col">Documento</th>
      <th scope="col">Estudiante</th>
      <th scope="col" class="text-center">Sexo</th>
      <th scope="col" class="text-center">Edad</th>
      <th scope="col">Grado / Sección</th>
      <th scope="col">Representante Legal</th>
      <th scope="col">Teléfono</th>
    </tr>
  <?php
  elseif ($tipo === 'distribucion_aulas'):
  ?>
    <colgroup>
      <col style="width: 20%;">
      <col style="width: 15%;">
      <col style="width: 15%;">
      <col style="width: 15%;">
      <col style="width: 23%;">
      <col style="width: 12%;">
    </colgroup>
    <tr>
      <th scope="col">Aula</th>
      <th scope="col">Turno</th>
      <th scope="col" class="text-center">Capacidad</th>
      <th scope="col" class="text-center">Matriculados</th>
      <th scope="col">Docente Asignado</th>
      <th scope="col" class="text-end">Estado</th>
    </tr>
  <?php
  elseif ($tipo === 'personal_institucional'):
  ?>
    <colgroup>
      <col style="width: 13%;">
      <col style="width: 27%;">
      <col style="width: 20%;">
      <col style="width: 12%;">
      <col style="width: 14%;">
      <col style="width: 14%;">
    </colgroup>
    <tr>
      <th scope="col">Cédula</th>
      <th scope="col">Nombre Completo</th>
      <th scope="col">Cargo</th>
      <th scope="col">Turno</th>
      <th scope="col">Teléfono</th>
      <th scope="col" class="text-end">Condición</th>
    </tr>
  <?php
  elseif ($tipo === 'retiros_periodo'):
  ?>
    <colgroup>
      <col style="width: 13%;">
      <col style="width: 27%;">
      <col style="width: 15%;">
      <col style="width: 20%;">
      <col style="width: 13%;">
      <col style="width: 12%;">
    </colgroup>
    <tr>
      <th scope="col">Documento</th>
      <th scope="col">Estudiante</th>
      <th scope="col">Aula</th>
      <th scope="col">Motivo de Retiro</th>
      <th scope="col">Fecha</th>
      <th scope="col" class="text-end">Estado</th>
    </tr>
  <?php
  else: // estadistico_general (Matriz MPPE)
    $edades = $datosReporte['edades'] ?? [];
  ?>
    <tr>
      <th scope="col">Grado y Sección</th>
      <th scope="col">Turno</th>
      <th scope="col" class="text-center">Capacidad</th>
      <th scope="col" class="text-center">Varones</th>
      <th scope="col" class="text-center">Hembras</th>
      <th scope="col" class="text-center fw-semibold">Total</th>
      <?php foreach ($edades as $edad): ?>
        <th scope="col" class="text-center text-muted small"><?= $edad ?> a.</th>
      <?php endforeach; ?>
    </tr>
  <?php
  endif;
  $panelTableHead = ob_get_clean();

  // Tbody
  ob_start();
  if ($tipo === 'nomina_estudiantes'):
    $rows = is_array($datosReporte) ? $datosReporte : [];
    if ($rows === []):
  ?>
      <tr><td colspan="8" class="text-center text-support py-4">No se encontraron estudiantes para los filtros seleccionados.</td></tr>
    <?php
    else:
      $i = 1;
      foreach ($rows as $e):
        $doc = trim(($e['tipo_documento'] ?? '') . '-' . ($e['numero_documento'] ?? ''), '-');
        $repNombre     = trim((string) ($e['rep_nombre'] ?? ''));
        $repVigentes   = (int) ($e['rep_vigentes'] ?? 0);
        $repFallecidos = (int) ($e['rep_fallecidos'] ?? 0);
        if ($repNombre !== '' && $repVigentes > 0) {
            $repTexto = $repNombre;
            $repClase = '';
        } elseif ($repFallecidos > 0) {
            $repTexto = 'Representante fallecido, requiere actualizar';
            $repClase = 'text-warning fw-semibold';
        } else {
            $repTexto = 'Sin representante';
            $repClase = 'text-support';
        }
    ?>
        <tr>
          <td class="text-center text-support small"><?= $i++ ?></td>
          <td class="fw-medium"><?= htmlspecialchars($doc !== '' ? $doc : 'Sin doc.') ?></td>
          <td class="fw-semibold"><?= htmlspecialchars((string) ($e['nombre_completo'] ?? '')) ?></td>
          <td class="text-center"><?= htmlspecialchars((string) ($e['sexo'] ?? '—')) ?></td>
          <td class="text-center"><?= (int) ($e['edad'] ?? 0) ?></td>
          <td><?= htmlspecialchars((string) ($e['grado'] . ' "' . $e['seccion'] . '"')) ?></td>
          <td class="<?= htmlspecialchars($repClase) ?>"><?= htmlspecialchars($repTexto) ?></td>
          <td><?= htmlspecialchars((string) ($e['rep_telefono'] ?: '—')) ?></td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>

  <?php elseif ($tipo === 'distribucion_aulas'):
    $rows = is_array($datosReporte) ? $datosReporte : [];
    if ($rows === []):
  ?>
      <tr><td colspan="6" class="text-center text-support py-4">No hay secciones registradas para este año escolar.</td></tr>
    <?php
    else:
      foreach ($rows as $d):
        $cap   = (int) ($d['capacidad_maxima'] ?? 0);
        $mat   = (int) ($d['total_matricula'] ?? 0);
        $tiene = !empty($d['docente_titular']);
    ?>
        <tr>
          <td class="fw-semibold"><?= htmlspecialchars((string) ($d['grado'] . ' "' . $d['seccion'] . '"')) ?></td>
          <td><?= htmlspecialchars((string) ($d['turno'] ?? '—')) ?></td>
          <td class="text-center text-support"><?= $cap ?></td>
          <td class="text-center fw-medium <?= $mat > $cap ? 'text-danger' : '' ?>"><?= $mat ?></td>
          <td><?= htmlspecialchars((string) ($d['docente_titular'] ?: 'Sin docente titular')) ?></td>
          <td class="text-end">
            <span class="badge text-bg-<?= $tiene ? 'success' : 'danger' ?>">
              <?= $tiene ? 'Asignada' : 'Por asignar' ?>
            </span>
          </td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>

  <?php elseif ($tipo === 'personal_institucional'):
    $rows = is_array($datosReporte) ? $datosReporte : [];
    if ($rows === []):
  ?>
      <tr><td colspan="6" class="text-center text-support py-4">No se encontró personal registrado.</td></tr>
    <?php
    else:
      foreach ($rows as $p):
        $doc     = trim(($p['tipo_documento'] ?? '') . '-' . ($p['numero_documento'] ?? ''), '-');
        $activo  = strtoupper((string) ($p['estado_persona'] ?? '')) === 'ACTIVO';
        $reposo  = !empty($p['en_reposo']);
    ?>
        <tr>
          <td class="fw-medium"><?= htmlspecialchars($doc) ?></td>
          <td class="fw-semibold"><?= htmlspecialchars((string) ($p['nombre_completo'] ?? '')) ?></td>
          <td><?= htmlspecialchars((string) ($p['cargo'] ?? 'Sin cargo')) ?></td>
          <td><?= htmlspecialchars((string) ($p['turno'] ?? '—')) ?></td>
          <td><?= htmlspecialchars((string) ($p['telefono'] ?: '—')) ?></td>
          <td class="text-end">
            <?php if ($reposo): ?>
              <span class="badge text-bg-warning">En reposo</span>
            <?php elseif ($activo): ?>
              <span class="badge text-bg-success">Activo</span>
            <?php else: ?>
              <span class="badge text-bg-secondary">Inactivo</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>

  <?php elseif ($tipo === 'retiros_periodo'):
    $rows = is_array($datosReporte) ? $datosReporte : [];
    if ($rows === []):
  ?>
      <tr><td colspan="6" class="text-center text-support py-4">No hay retiros registrados en este período escolar.</td></tr>
    <?php
    else:
      foreach ($rows as $r):
        $doc = trim(($r['tipo_documento'] ?? '') . '-' . ($r['numero_documento'] ?? ''), '-');
        $fec = !empty($r['fecha_solicitud']) ? date('d/m/Y', strtotime((string) $r['fecha_solicitud'])) : '—';
    ?>
        <tr>
          <td class="fw-medium"><?= htmlspecialchars($doc) ?></td>
          <td class="fw-semibold"><?= htmlspecialchars((string) ($r['nombre_completo'] ?? '')) ?></td>
          <td><?= htmlspecialchars((string) ($r['grado'] . ' "' . $r['seccion'] . '"')) ?></td>
          <td><?= htmlspecialchars((string) ($r['motivo'] ?? '')) ?></td>
          <td><?= htmlspecialchars($fec) ?></td>
          <td class="text-end">
            <span class="badge text-bg-info"><?= htmlspecialchars((string) ($r['estado_solicitud'] ?? '')) ?></span>
          </td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>

  <?php else: // estadistico_general (Matriz MPPE)
    $filas   = $datosReporte['filas'] ?? [];
    $edades  = $datosReporte['edades'] ?? [];
    $totales = $datosReporte['totales'] ?? [];

    if ($filas === []):
  ?>
      <tr><td colspan="20" class="text-center text-support py-4">No hay datos de matrícula registrados para este período escolar.</td></tr>
    <?php
    else:
      foreach ($filas as $f):
    ?>
        <tr>
          <td class="fw-semibold"><?= htmlspecialchars((string) ($f['grado'] . ' "' . $f['seccion'] . '"')) ?></td>
          <td><?= htmlspecialchars((string) ($f['turno'] ?? '')) ?></td>
          <td class="text-center text-support"><?= (int) $f['capacidad'] ?></td>
          <td class="text-center fw-medium text-primary"><?= (int) $f['varones'] ?></td>
          <td class="text-center fw-medium text-danger"><?= (int) $f['hembras'] ?></td>
          <td class="text-center fw-bold"><?= (int) $f['total'] ?></td>
          <?php foreach ($edades as $edad): ?>
            <?php $cant = (int) ($f['edades'][$edad]['total'] ?? 0); ?>
            <td class="text-center text-muted small"><?= $cant > 0 ? $cant : '—' ?></td>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
      <!-- Fila de totales generales -->
      <tr class="table-light fw-bold border-top-2">
        <td colspan="2">Totales Generales</td>
        <td class="text-center"><?= array_sum(array_column($filas, 'capacidad')) ?></td>
        <td class="text-center text-primary"><?= (int) ($totales['varones'] ?? 0) ?></td>
        <td class="text-center text-danger"><?= (int) ($totales['hembras'] ?? 0) ?></td>
        <td class="text-center fs-6"><?= (int) ($totales['total'] ?? 0) ?></td>
        <?php foreach ($edades as $edad): ?>
          <?php
          $sumEdad = 0;
          foreach ($filas as $f) {
              $sumEdad += (int) ($f['edades'][$edad]['total'] ?? 0);
          }
          ?>
          <td class="text-center small"><?= $sumEdad > 0 ? $sumEdad : '—' ?></td>
        <?php endforeach; ?>
      </tr>
    <?php endif; ?>
  <?php endif; ?>
  <?php
  $panelTableBody = ob_get_clean();

  $totalFilas   = is_array($datosReporte) ? (isset($datosReporte['filas']) ? count($datosReporte['filas']) : count($datosReporte)) : 0;
  $panelSummary = 'Reporte activo: ' . ($nombresReporte[$tipo] ?? 'Oficial') . ' · ' . $totalFilas . ' registros';

  if ($tipo === 'nomina_estudiantes' && $resumenRepresentantes !== null) {
      $panelSummary .= ' · Más de un representante activo: ' . (int) $resumenRepresentantes['mas_de_un']
          . ' · Solo representante fallecido: ' . (int) $resumenRepresentantes['solo_fallecido']
          . ' · Sin representante: ' . (int) $resumenRepresentantes['sin_rep'];
  }
  ?>
  <?php if ($tipo === 'nomina_estudiantes' && $resumenRepresentantes !== null): ?>
    <div class="info-alert mb-3">
      <div class="info-alert__icon"><i class="bi bi-people" aria-hidden="true"></i></div>
      <p class="info-alert__text mb-0">
        <strong>Control de representantes:</strong>
        <?= (int) $resumenRepresentantes['mas_de_un'] ?> con más de un representante activo,
        <?= (int) $resumenRepresentantes['solo_fallecido'] ?> solo con representante fallecido y
        <?= (int) $resumenRepresentantes['sin_rep'] ?> sin representante.
      </p>
    </div>
  <?php endif; ?>
  <?php include __DIR__ . '/../components/data-panel.php'; ?>
</div>
<?php
$pageContent = ob_get_clean();

ob_start();
include __DIR__ . '/modals/preview-modal.php';
$pageModals = ob_get_clean();

$extraScripts = ['/js/modules/reportes/reportes.js'];

include __DIR__ . '/../layouts/app.php';
