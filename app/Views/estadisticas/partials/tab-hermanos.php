<?php
declare(strict_types=1);

/**
 * Partial: estadisticas/partials/tab-hermanos.php
 * Pestaña 1: Representantes y Control de Hermanos.
 *
 * @var array<string, mixed>            $resumenHermanos
 * @var array<int, array<string, mixed>> $detalleHermanos
 * @var string                          $q
 */

// Helper para codificar configuraciones JSON de gráficas
$encodeConfig = static function (array $config): string {
    $flags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
    return htmlspecialchars((string) json_encode($config, $flags), ENT_QUOTES, 'UTF-8');
};

$distFam = $resumenHermanos['distribucion_familias'] ?? ['1' => 0, '2' => 0, '3_mas' => 0];
$donutFamiliasConfig = [
    'labels'       => ['1 Estudiante', '2 Estudiantes', '3 o más Estudiantes'],
    'values'       => [(int) ($distFam['1'] ?? 0), (int) ($distFam['2'] ?? 0), (int) ($distFam['3_mas'] ?? 0)],
    'colors'       => ['c2', 'c1', 'c4'],
    'emptyMessage' => 'Sin familias registradas en el período',
];
?>
<div class="d-flex flex-column gap-4">

  <!-- 1. Tarjetas KPI de Representantes y Hermanos (4 métricas clave) -->
  <div class="row g-3">
    <!-- Card 1: Total Estudiantes -->
    <div class="col-12 col-sm-6 col-xl-3">
      <?php
      $statLabel     = 'Total estudiantes';
      $statValue     = number_format((int) ($resumenHermanos['total_estudiantes'] ?? 0), 0, ',', '.') . ' alumnos';
      $statIcon      = 'bi-backpack';
      $statTone      = 'primary';
      $statVariant   = 'default';
      $statTrend     = 'Cursando';
      $statTrendType = 'neutral';
      $statMeta      = 'Matrícula activa en el período';
      $statProgress  = null;
      $statHref      = null;
      include __DIR__ . '/../../components/stat-card.php';
      ?>
    </div>

    <!-- Card 2: Total Representantes -->
    <div class="col-12 col-sm-6 col-xl-3">
      <?php
      $statLabel     = 'Representantes legales';
      $statValue     = number_format((int) ($resumenHermanos['total_representantes'] ?? 0), 0, ',', '.') . ' personas';
      $statIcon      = 'bi-person-vcard';
      $statTone      = 'info';
      $statVariant   = 'default';
      $statTrend     = 'Familias';
      $statTrendType = 'neutral';
      $statMeta      = 'Responsables legales únicos';
      $statProgress  = null;
      $statHref      = null;
      include __DIR__ . '/../../components/stat-card.php';
      ?>
    </div>

    <!-- Card 3: Promedio Hijos / Rep -->
    <div class="col-12 col-sm-6 col-xl-3">
      <?php
      $statLabel     = 'Promedio por familia';
      $statValue     = (string) ($resumenHermanos['promedio_estudiantes'] ?? '0') . ' est./rep.';
      $statIcon      = 'bi-calculator';
      $statTone      = 'secondary';
      $statVariant   = 'default';
      $statTrend     = 'Ratio';
      $statTrendType = 'neutral';
      $statMeta      = 'Hijos por representante legal';
      $statProgress  = null;
      $statHref      = null;
      include __DIR__ . '/../../components/stat-card.php';
      ?>
    </div>

    <!-- Card 4: Familias con Hermanos -->
    <div class="col-12 col-sm-6 col-xl-3">
      <?php
      $famHerm = (int) ($resumenHermanos['familias_con_hermanos'] ?? 0);
      $estHerm = (int) ($resumenHermanos['estudiantes_con_hermanos'] ?? 0);
      $statLabel     = 'Familias con hermanos';
      $statValue     = number_format($famHerm, 0, ',', '.') . ' familias';
      $statIcon      = 'bi-people';
      $statTone      = 'warning';
      $statVariant   = 'default';
      $statTrend     = '2+ estudiantes';
      $statTrendType = 'neutral';
      $statMeta      = $estHerm . ' estudiantes vinculados';
      $statProgress  = null;
      $statHref      = null;
      include __DIR__ . '/../../components/stat-card.php';
      ?>
    </div>
  </div>

  <!-- 2. Gráfica y Desglose de Familias -->
  <div class="row g-4">
    <!-- Gráfica de Dona: Distribución de Familias -->
    <div class="col-12 col-lg-5">
      <div class="stats-chart-wrapper">
        <div class="stats-card-header">
          <h2 class="stats-card-header__title">
            <i class="bi bi-pie-chart text-primary" aria-hidden="true"></i>Distribución de Familias por Hijos
          </h2>
        </div>
        <div class="chart-container chart-container--donut"
             data-sigde-chart="donut"
             data-chart-config="<?= $encodeConfig($donutFamiliasConfig) ?>">
          <canvas aria-label="Distribución de familias por cantidad de estudiantes a cargo" role="img"></canvas>
        </div>
      </div>
    </div>

    <!-- Fichas de Control Familiar -->
    <div class="col-12 col-lg-7">
      <div class="row g-3 h-100">
        <div class="col-sm-6">
          <div class="stats-metric-tile">
            <div class="stats-metric-tile__label">Familias de 1 Estudiante</div>
            <div class="stats-metric-tile__value text-primary"><?= (int) ($distFam['1'] ?? 0) ?></div>
            <div class="stats-metric-tile__meta">Representantes con un único alumno en la institución.</div>
          </div>
        </div>

        <div class="col-sm-6">
          <div class="stats-metric-tile">
            <div class="stats-metric-tile__label">Familias de 2 Estudiantes</div>
            <div class="stats-metric-tile__value text-info"><?= (int) ($distFam['2'] ?? 0) ?></div>
            <div class="stats-metric-tile__meta">Parejas de hermanos inscritos en el período escolar.</div>
          </div>
        </div>

        <div class="col-sm-6">
          <div class="stats-metric-tile">
            <div class="stats-metric-tile__label">Familias de 3 o Más Estudiantes</div>
            <div class="stats-metric-tile__value text-warning"><?= (int) ($distFam['3_mas'] ?? 0) ?></div>
            <div class="stats-metric-tile__meta">Grupos familiares numerosos bajo un mismo representante.</div>
          </div>
        </div>

        <div class="col-sm-6">
          <div class="stats-metric-tile">
            <div class="stats-metric-tile__label">Hermanos en Distintos Grados</div>
            <div class="stats-metric-tile__value text-success"><?= (int) ($resumenHermanos['reps_distintos_grados'] ?? 0) ?></div>
            <div class="stats-metric-tile__meta">Representantes que atienden a estudiantes en niveles diferentes.</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- 3. Tabla Desplegable de Control de Hermanos -->
  <div class="stats-table-wrapper">
    <div class="p-3 bg-white border-bottom d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
      <div>
        <h2 class="h6 fw-bold mb-0 text-dark d-flex align-items-center gap-2">
          <i class="bi bi-people-fill text-primary" aria-hidden="true"></i>Control de Hermanos y Familias
        </h2>
        <span class="text-secondary small">Total registrados: <?= count($detalleHermanos) ?> familias</span>
      </div>

      <!-- Buscador en tiempo real -->
      <div class="d-flex align-items-center gap-2">
        <div class="input-group input-group-sm">
          <span class="input-group-text bg-light border-end-0">
            <i class="bi bi-search text-secondary" aria-hidden="true"></i>
          </span>
          <input type="text"
                 id="buscarHermanos"
                 class="form-control border-start-0"
                 placeholder="Buscar por representante o alumno..."
                 aria-label="Buscar representante o alumno">
        </div>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 tabla-estadisticas tabla-hermanos" id="tablaControlHermanos">
        <thead class="table-light text-uppercase small text-secondary">
          <tr>
            <th scope="col" class="col-num text-center">N°</th>
            <th scope="col" class="col-rep">Representante Legal</th>
            <th scope="col" class="col-cedula">Cédula</th>
            <th scope="col" class="col-telefono">Teléfono Principal</th>
            <th scope="col" class="col-hijos text-center">Estudiantes a Cargo</th>
            <th scope="col" class="col-situacion text-center">Situación Familiar</th>
            <th scope="col" class="col-accion text-center">Detalle</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($detalleHermanos === []): ?>
            <tr>
              <td colspan="7" class="text-center text-secondary py-4">
                No se encontraron representantes ni hermanos registrados para los filtros seleccionados.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($detalleHermanos as $i => $fam): ?>
              <?php
              $idRep       = (int) $fam['id_representante'];
              $cantHijos   = (int) $fam['total_estudiantes'];
              $diffGrados  = !empty($fam['hermanos_distintos_grados']);
              $targetId    = 'collapseHermanos' . $idRep;
              ?>
              <tr class="fila-familia" data-bs-target="#<?= $targetId ?>">
                <td class="col-num text-center text-secondary fw-semibold"><?= $i + 1 ?></td>
                <td class="col-rep">
                  <strong class="text-dark d-block"><?= htmlspecialchars((string) $fam['nombre_representante']) ?></strong>
                </td>
                <td class="col-cedula font-monospace text-primary"><?= htmlspecialchars((string) $fam['cedula_representante']) ?></td>
                <td class="col-telefono text-secondary"><?= htmlspecialchars((string) $fam['telefono_representante']) ?></td>
                <td class="col-hijos text-center">
                  <span class="badge <?= $cantHijos > 1 ? 'bg-primary' : 'bg-light text-dark border' ?> px-2 py-1 fs-6">
                    <?= $cantHijos ?> <?= $cantHijos === 1 ? 'hijo' : 'hijos' ?>
                  </span>
                </td>
                <td class="col-situacion text-center">
                  <?php if ($diffGrados): ?>
                    <span class="sibling-badge sibling-badge--diff-grades">
                      <i class="bi bi-diagram-3-fill" aria-hidden="true"></i> Distintos Grados
                    </span>
                  <?php elseif ($cantHijos > 1): ?>
                    <span class="sibling-badge sibling-badge--same-grade">
                      <i class="bi bi-people" aria-hidden="true"></i> Mismo Grado
                    </span>
                  <?php else: ?>
                    <span class="sibling-badge sibling-badge--single">
                      Hijo Único
                    </span>
                  <?php endif; ?>
                </td>
                <td class="col-accion text-center">
                  <button type="button"
                          class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1 shadow-sm"
                          data-bs-toggle="collapse"
                          data-bs-target="#<?= $targetId ?>"
                          aria-expanded="false"
                          aria-controls="<?= $targetId ?>">
                    <i class="bi bi-chevron-down" aria-hidden="true"></i>
                    <span>Ver Estudiantes</span>
                  </button>
                </td>
              </tr>

              <!-- Fila Desplegable con Ficha de Hermanos -->
              <tr class="collapse bg-light" id="<?= $targetId ?>">
                <td colspan="7" class="p-3">
                  <div class="sibling-detail-box">
                    <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom">
                      <strong class="small text-secondary text-uppercase">
                        <i class="bi bi-backpack2 me-1 text-primary"></i>Estudiantes a cargo de <?= htmlspecialchars((string) $fam['nombre_representante']) ?>:
                      </strong>
                      <span class="badge bg-white text-secondary border font-monospace"><?= $cantHijos ?> registrado(s)</span>
                    </div>

                    <div class="row g-2">
                      <?php foreach (($fam['estudiantes'] ?? []) as $est): ?>
                        <div class="col-12 col-md-6 col-lg-4">
                          <div class="p-2 bg-white rounded border d-flex justify-content-between align-items-center shadow-sm">
                            <div>
                              <div class="fw-bold text-dark small"><?= htmlspecialchars((string) $est['nombre_completo']) ?></div>
                              <div class="text-secondary small font-monospace"><?= htmlspecialchars((string) $est['documento']) ?></div>
                            </div>
                            <div class="text-end">
                              <span class="badge bg-light text-primary border font-monospace"><?= htmlspecialchars((string) $est['grado_seccion']) ?></span>
                              <div class="text-secondary small"><?= htmlspecialchars((string) $est['turno']) ?></div>
                            </div>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>

          <tr id="sinResultadosHermanos" class="d-none">
            <td colspan="7" class="text-center text-secondary py-4">
              No se encontraron coincidencias para la búsqueda.
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

</div>
