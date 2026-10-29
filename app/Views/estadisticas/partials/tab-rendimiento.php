<?php
declare(strict_types=1);

/**
 * Partial: estadisticas/partials/tab-rendimiento.php
 * Pestaña 5: Calificaciones, Avance de Literales y Rendimiento Académico.
 *
 * @var array<int, array<string, mixed>> $filasRendimiento
 * @var array<string, mixed>            $resumenRendimiento
 */

$encodeConfig = static function (array $config): string {
    $flags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
    return htmlspecialchars((string) json_encode($config, $flags), ENT_QUOTES, 'UTF-8');
};

$totEst       = (int) ($resumenRendimiento['total_estudiantes'] ?? 0);
$totCargados  = (int) ($resumenRendimiento['total_cargados'] ?? 0);
$totPend      = (int) ($resumenRendimiento['total_pendientes'] ?? 0);
$totProm      = (int) ($resumenRendimiento['total_promovidos'] ?? 0);
$totNoProm    = (int) ($resumenRendimiento['total_no_promovidos'] ?? 0);
$totSec       = (int) ($resumenRendimiento['total_secciones'] ?? count($filasRendimiento));
$pctCarga     = (int) ($resumenRendimiento['porcentaje_carga'] ?? 0);
$tasaProm     = (int) ($resumenRendimiento['tasa_promocion'] ?? 0);

$totA         = (int) ($resumenRendimiento['total_a'] ?? 0);
$totB         = (int) ($resumenRendimiento['total_b'] ?? 0);
$totC         = (int) ($resumenRendimiento['total_c'] ?? 0);
$totD         = (int) ($resumenRendimiento['total_d'] ?? 0);
$totE         = (int) ($resumenRendimiento['total_e'] ?? 0);

// Configuración de la gráfica donut de literales
$donutLiteralesConfig = [
    'labels'       => ['A (Excelente)', 'B (Muy Bueno)', 'C (Bueno)', 'D (Regular)', 'E (No Aprobó)'],
    'values'       => [$totA, $totB, $totC, $totD, $totE],
    'colors'       => ['c1', 'c2', 'c3', 'c4', 'c5'],
    'emptyMessage' => 'Sin calificaciones registradas para el período',
];
?>
<div class="d-flex flex-column gap-4">

  <!-- 1. Tarjetas KPI de Calificaciones y Rendimiento -->
  <div class="row g-3">
    <!-- Card 1: Matrícula a Evaluar -->
    <div class="col-12 col-sm-6 col-xl-3">
      <?php
      $statLabel     = 'Matrícula a evaluar';
      $statValue     = number_format($totEst, 0, ',', '.') . ' estud.';
      $statIcon      = 'bi-mortarboard';
      $statTone      = 'primary';
      $statVariant   = 'default';
      $statTrend     = 'Matrícula activa';
      $statTrendType = 'neutral';
      $statMeta      = number_format($totSec, 0) . ' aula' . ($totSec === 1 ? '' : 's') . ' en seguimiento';
      $statProgress  = null;
      $statHref      = null;
      include __DIR__ . '/../../components/stat-card.php';
      ?>
    </div>

    <!-- Card 2: Literales Cargados -->
    <div class="col-12 col-sm-6 col-xl-3">
      <?php
      $statLabel     = 'Literales cargados';
      $statValue     = number_format($totCargados, 0, ',', '.') . ' estud.';
      $statIcon      = 'bi-check2-circle';
      $statTone      = 'success';
      $statVariant   = 'default';
      $statTrend     = $pctCarga . '% avance';
      $statTrendType = $pctCarga >= 100 ? 'up' : 'neutral';
      $statMeta      = 'Avance global de evaluación';
      $statProgress  = $pctCarga;
      $statHref      = null;
      include __DIR__ . '/../../components/stat-card.php';
      ?>
    </div>

    <!-- Card 3: Pendientes por Calificar -->
    <div class="col-12 col-sm-6 col-xl-3">
      <?php
      $statLabel     = 'Pendientes por calificar';
      $statValue     = number_format($totPend, 0, ',', '.') . ' estud.';
      $statIcon      = 'bi-clock-history';
      $statTone      = $totPend > 0 ? 'warning' : 'secondary';
      $statVariant   = 'default';
      $statTrend     = $totPend > 0 ? 'Por registrar' : 'Al día';
      $statTrendType = $totPend > 0 ? 'down' : 'neutral';
      $statMeta      = $totPend > 0 ? 'Pendiente por el docente' : 'Todas las notas cargadas';
      $statProgress  = null;
      $statHref      = null;
      include __DIR__ . '/../../components/stat-card.php';
      ?>
    </div>

    <!-- Card 4: Estudiantes Promovidos -->
    <div class="col-12 col-sm-6 col-xl-3">
      <?php
      $statLabel     = 'Estudiantes promovidos';
      $statValue     = number_format($totProm, 0, ',', '.') . ' estud.';
      $statIcon      = 'bi-award';
      $statTone      = 'info';
      $statVariant   = 'default';
      $statTrend     = $tasaProm . '% aprobación';
      $statTrendType = $tasaProm >= 75 ? 'up' : 'neutral';
      $statMeta      = 'No promovidos (E): ' . number_format($totNoProm, 0);
      $statProgress  = null;
      $statHref      = null;
      include __DIR__ . '/../../components/stat-card.php';
      ?>
    </div>
  </div>

  <!-- 2. Distribución Ministerial de Literales (Escala A-E) -->
  <div class="row g-3">
    <!-- Gráfica Donut de Literales -->
    <div class="col-12 col-lg-5">
      <div class="stats-chart-wrapper">
        <div class="stats-card-header">
          <h2 class="stats-card-header__title">
            <i class="bi bi-pie-chart text-primary" aria-hidden="true"></i>Distribución de Literales (A - E)
          </h2>
        </div>
        <div class="chart-container chart-container--donut"
             data-sigde-chart="donut"
             data-chart-config="<?= $encodeConfig($donutLiteralesConfig) ?>">
          <canvas aria-label="Distribución de literales académicos" role="img"></canvas>
        </div>
      </div>
    </div>

    <!-- Desglose Cualitativo de la Escala Ministerial -->
    <div class="col-12 col-lg-7">
      <div class="content-card h-100">
        <div class="content-card__header border-bottom pb-2">
          <h2 class="content-card__title">
            <i class="bi bi-award text-primary me-2" aria-hidden="true"></i>Desglose Cualitativo Institucional
          </h2>
          <span class="text-secondary small">Normativa MPPE para Educación Primaria</span>
        </div>

        <div class="p-3 d-flex flex-column gap-2">
          <!-- Literal A -->
          <div class="d-flex align-items-center justify-content-between p-2 rounded bg-light border">
            <div class="d-flex align-items-center gap-2">
              <span class="literal-badge literal-badge--a">A</span>
              <div>
                <span class="fw-semibold text-dark d-block">Excelente</span>
                <span class="text-secondary small">Alcanzó todas las competencias con rendimiento sobresaliente.</span>
              </div>
            </div>
            <div class="text-end">
              <span class="badge text-bg-success fs-6"><?= $totA ?></span>
              <span class="d-block text-secondary small">Promueve</span>
            </div>
          </div>

          <!-- Literal B -->
          <div class="d-flex align-items-center justify-content-between p-2 rounded bg-light border">
            <div class="d-flex align-items-center gap-2">
              <span class="literal-badge literal-badge--b">B</span>
              <div>
                <span class="fw-semibold text-dark d-block">Muy Bueno</span>
                <span class="text-secondary small">Alcanzó la mayoría de las competencias satisfactoriamente.</span>
              </div>
            </div>
            <div class="text-end">
              <span class="badge text-bg-info fs-6"><?= $totB ?></span>
              <span class="d-block text-secondary small">Promueve</span>
            </div>
          </div>

          <!-- Literal C -->
          <div class="d-flex align-items-center justify-content-between p-2 rounded bg-light border">
            <div class="d-flex align-items-center gap-2">
              <span class="literal-badge literal-badge--c">C</span>
              <div>
                <span class="fw-semibold text-dark d-block">Bueno</span>
                <span class="text-secondary small">Alcanzó las competencias esenciales del grado.</span>
              </div>
            </div>
            <div class="text-end">
              <span class="badge text-bg-primary fs-6"><?= $totC ?></span>
              <span class="d-block text-secondary small">Promueve</span>
            </div>
          </div>

          <!-- Literal D -->
          <div class="d-flex align-items-center justify-content-between p-2 rounded bg-light border">
            <div class="d-flex align-items-center gap-2">
              <span class="literal-badge literal-badge--d">D</span>
              <div>
                <span class="fw-semibold text-dark d-block">Regular</span>
                <span class="text-secondary small">Alcanzó mínimamente las competencias requeridas.</span>
              </div>
            </div>
            <div class="text-end">
              <span class="badge text-bg-warning fs-6"><?= $totD ?></span>
              <span class="d-block text-secondary small">Promueve</span>
            </div>
          </div>

          <!-- Literal E -->
          <div class="d-flex align-items-center justify-content-between p-2 rounded bg-light border">
            <div class="d-flex align-items-center gap-2">
              <span class="literal-badge literal-badge--e">E</span>
              <div>
                <span class="fw-semibold text-dark d-block">No Aprobó</span>
                <span class="text-secondary small">No alcanzó las competencias mínimas requeridas del grado.</span>
              </div>
            </div>
            <div class="text-end">
              <span class="badge text-bg-danger fs-6"><?= $totE ?></span>
              <span class="d-block text-secondary small">No promueve</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- 3. Tabla: Calificaciones y Avance por Grado y Sección -->
  <div class="stats-table-wrapper">
    <div class="p-3 bg-white border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
      <div>
        <h2 class="h6 fw-bold mb-0 text-dark d-flex align-items-center gap-2">
          <i class="bi bi-bar-chart-steps text-primary" aria-hidden="true"></i>Avance de Calificaciones y Rendimiento por Aula
        </h2>
        <span class="text-secondary small">Supervisión de literales registrados, pendientes y resultados de promoción</span>
      </div>
      <span class="text-secondary small">
        <?= count($filasRendimiento) ?> aula<?= count($filasRendimiento) === 1 ? '' : 's' ?>
      </span>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 tabla-estadisticas tabla-rendimiento-aulas">
        <thead class="table-light text-uppercase small text-secondary">
          <tr>
            <th scope="col" class="col-aula">Aula</th>
            <th scope="col" class="col-turno">Turno</th>
            <th scope="col" class="col-docente">Docente Titular</th>
            <th scope="col" class="col-mat text-center">Matrícula</th>
            <th scope="col" class="col-cargados text-center text-primary">Cargados</th>
            <th scope="col" class="col-pend text-center text-warning">Pendientes</th>
            <th scope="col" class="col-prom text-center text-success">Promovidos</th>
            <th scope="col" class="col-noprom text-center text-danger">No Prom.</th>
            <th scope="col" class="col-avance text-end">Estado y Avance</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($filasRendimiento === []): ?>
            <tr>
              <td colspan="9" class="text-center text-secondary py-4">
                No se encontraron registros de calificaciones para los filtros seleccionados en este período escolar.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($filasRendimiento as $fila): ?>
              <?php
              $mat        = (int) $fila['total_estudiantes'];
              $carg       = (int) $fila['total_cargados'];
              $pend       = (int) $fila['total_pendientes'];
              $prom       = (int) $fila['total_promovidos'];
              $noprom     = (int) $fila['total_no_promovidos'];
              $porc       = (int) $fila['porcentaje_carga'];
              $docente    = trim((string) ($fila['docente_nombre'] ?? ''));
              $nombreAula = (string) ($fila['grado'] . ' "' . $fila['seccion'] . '"');

              if ($mat === 0) {
                  $badgeClase = 'status-badge--inactive';
                  $badgeTexto = 'Sin estudiantes';
                  $badgeIcono = 'bi-dash-circle';
              } elseif ($pend === 0) {
                  $badgeClase = 'status-badge--active';
                  $badgeTexto = '100% Completo';
                  $badgeIcono = 'bi-check-circle-fill';
              } elseif ($carg === 0) {
                  $badgeClase = 'status-badge--pending';
                  $badgeTexto = '0% Sin iniciar';
                  $badgeIcono = 'bi-clock';
              } else {
                  $badgeClase = 'status-badge--info';
                  $badgeTexto = $porc . '% En progreso';
                  $badgeIcono = 'bi-arrow-repeat';
              }
              ?>
              <tr>
                <td class="col-aula fw-semibold text-dark">
                  <?= htmlspecialchars($nombreAula) ?>
                </td>
                <td class="col-turno text-secondary">
                  <?= htmlspecialchars((string) ($fila['turno'] ?? '—')) ?>
                </td>
                <td class="col-docente">
                  <?php if ($docente !== ''): ?>
                    <span class="text-dark"><?= htmlspecialchars($docente) ?></span>
                  <?php else: ?>
                    <span class="badge text-bg-warning">
                      <i class="bi bi-person-x me-1" aria-hidden="true"></i>Sin titular
                    </span>
                  <?php endif; ?>
                </td>
                <td class="col-mat text-center fw-bold">
                  <?= $mat ?>
                </td>
                <td class="col-cargados text-center fw-medium text-primary">
                  <?= $carg ?>
                </td>
                <td class="col-pend text-center">
                  <?php if ($pend > 0): ?>
                    <span class="badge text-bg-warning"><?= $pend ?></span>
                  <?php else: ?>
                    <span class="text-secondary">0</span>
                  <?php endif; ?>
                </td>
                <td class="col-prom text-center fw-medium text-success">
                  <?= $prom ?>
                </td>
                <td class="col-noprom text-center fw-medium text-danger">
                  <?= $noprom ?>
                </td>
                <td class="col-avance text-end">
                  <span class="status-badge <?= $badgeClase ?>">
                    <i class="bi <?= $badgeIcono ?> me-1" aria-hidden="true"></i><?= $badgeTexto ?>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
        <?php if ($filasRendimiento !== []): ?>
          <tfoot class="table-light fw-bold">
            <tr>
              <td colspan="3" class="text-uppercase small text-secondary">
                Totales Institucionales (<?= count($filasRendimiento) ?> aulas)
              </td>
              <td class="text-center"><?= $totEst ?></td>
              <td class="text-center text-primary"><?= $totCargados ?></td>
              <td class="text-center text-warning"><?= $totPend ?></td>
              <td class="text-center text-success"><?= $totProm ?></td>
              <td class="text-center text-danger"><?= $totNoProm ?></td>
              <td class="text-end">
                <span class="status-badge <?= $pctCarga >= 100 ? 'status-badge--active' : 'status-badge--info' ?>">
                  <?= $pctCarga ?>% Global
                </span>
              </td>
            </tr>
          </tfoot>
        <?php endif; ?>
      </table>
    </div>
  </div>

  <!-- 4. Nota Informativa y Criterios Ministeriales -->
  <div class="content-card bg-light border">
    <div class="p-3 d-flex align-items-start gap-3">
      <i class="bi bi-info-circle text-primary fs-4 flex-shrink-0" aria-hidden="true"></i>
      <div>
        <h3 class="h6 fw-bold mb-1 text-dark">Orientación de Evaluación y Promoción Ministerial</h3>
        <p class="text-secondary small mb-1">
          En Educación Primaria, la evaluación de los aprendizajes es cualitativa y continua. Los literales <strong>A, B, C y D</strong>
          determinan la aprobación y prosecución al grado inmediato superior. El literal <strong>E</strong> determina que el estudiante
          no ha alcanzado las competencias mínimas y requiere permanencia escolar o plan de nivelación pedagógica.
        </p>
        <span class="text-secondary small">
          <strong>Nota institucional:</strong> La carga y modificación de los literales finales es responsabilidad exclusiva del docente titular
          asignado a cada sección en el módulo de <em>Resultados y Promoción</em>.
        </span>
      </div>
    </div>
  </div>

</div>
