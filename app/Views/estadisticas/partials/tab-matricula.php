<?php
declare(strict_types=1);

/**
 * Partial: estadisticas/partials/tab-matricula.php
 * Pestaña 2: Matrícula, Capacidad, Demografía y Familias.
 *
 * @var array<string, mixed>            $demografiaMatricula
 * @var array<int, array<string, mixed>> $matriculaAulas
 * @var array<string, mixed>            $estadisticasFamilias
 * @var array<int, array<string, mixed>> $distribucionParroquias
 */

$encodeConfig = static function (array $config): string {
    $flags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
    return htmlspecialchars((string) json_encode($config, $flags), ENT_QUOTES, 'UTF-8');
};

// Gráfica de Género
$donutGeneroConfig = [
    'labels'       => ['Masculino', 'Femenino'],
    'values'       => [(int) ($demografiaMatricula['varones'] ?? 0), (int) ($demografiaMatricula['hembras'] ?? 0)],
    'colors'       => ['c1', 'c2'],
    'emptyMessage' => 'Sin matrícula activa registrada',
];

// Gráfica de Ingresos, Regulares y Reingresos
$donutIngresosConfig = [
    'labels'       => ['Nuevos Ingresos', 'Regulares / Continuidad', 'Reingresos'],
    'values'       => [
        (int) ($demografiaMatricula['nuevos_ingresos'] ?? 0),
        (int) ($demografiaMatricula['regulares'] ?? 0),
        (int) ($demografiaMatricula['reingresos'] ?? 0),
    ],
    'colors'       => ['c4', 'c5', 'c2'],
    'emptyMessage' => 'Sin datos de inscripción para el período',
];
?>
<div class="d-flex flex-column gap-4">

  <!-- 1. Tarjetas KPI de Matrícula y Demografía (4 métricas clave) -->
  <div class="row g-3">
    <!-- Card 1: Matrícula Activa -->
    <div class="col-12 col-sm-6 col-xl-3">
      <?php
      $totCur = (int) ($demografiaMatricula['total_cursando'] ?? 0);
      $var = (int) ($demografiaMatricula['varones'] ?? 0);
      $hem = (int) ($demografiaMatricula['hembras'] ?? 0);
      $statLabel     = 'Matrícula activa';
      $statValue     = number_format($totCur, 0, ',', '.') . ' estud.';
      $statIcon      = 'bi-backpack';
      $statTone      = 'primary';
      $statVariant   = 'default';
      $statTrend     = 'Cursando';
      $statTrendType = 'neutral';
      $statMeta      = 'M: ' . $var . ' · F: ' . $hem;
      $statProgress  = null;
      $statHref      = null;
      include __DIR__ . '/../../components/stat-card.php';
      ?>
    </div>

    <!-- Card 2: Nuevos Ingresos -->
    <div class="col-12 col-sm-6 col-xl-3">
      <?php
      $nuevos = (int) ($demografiaMatricula['nuevos_ingresos'] ?? 0);
      $statLabel     = 'Nuevos ingresos';
      $statValue     = number_format($nuevos, 0, ',', '.') . ' estud.';
      $statIcon      = 'bi-person-plus';
      $statTone      = 'info';
      $statVariant   = 'default';
      $statTrend     = 'Primer ingreso';
      $statTrendType = 'neutral';
      $statMeta      = 'Inscripción inicial en plantel';
      $statProgress  = null;
      $statHref      = null;
      include __DIR__ . '/../../components/stat-card.php';
      ?>
    </div>

    <!-- Card 3: Regulares y Reingresos -->
    <div class="col-12 col-sm-6 col-xl-3">
      <?php
      $reg = (int) ($demografiaMatricula['regulares'] ?? 0);
      $reing = (int) ($demografiaMatricula['reingresos'] ?? 0);
      $statLabel     = 'Estud. regulares';
      $statValue     = number_format($reg, 0, ',', '.') . ' estud.';
      $statIcon      = 'bi-arrow-repeat';
      $statTone      = 'secondary';
      $statVariant   = 'default';
      $statTrend     = 'Continuidad';
      $statTrendType = 'neutral';
      $statMeta      = 'Ratificados: ' . $reg . ' · Reingresos: ' . $reing;
      $statProgress  = null;
      $statHref      = null;
      include __DIR__ . '/../../components/stat-card.php';
      ?>
    </div>

    <!-- Card 4: Alerta Extraedad -->
    <div class="col-12 col-sm-6 col-xl-3">
      <?php
      $ext = (int) ($demografiaMatricula['estudiantes_extraedad'] ?? 0);
      $statLabel     = 'Edad fuera de rango';
      $statValue     = number_format($ext, 0, ',', '.') . ' estud.';
      $statIcon      = 'bi-clock-history';
      $statTone      = $ext > 0 ? 'warning' : 'success';
      $statVariant   = 'default';
      $statTrend     = 'Rezago';
      $statTrendType = 'neutral';
      $statMeta      = 'Edad > esperada al grado';
      $statProgress  = null;
      $statHref      = null;
      include __DIR__ . '/../../components/stat-card.php';
      ?>
    </div>
  </div>

  <!-- 2. Gráficas de Género e Ingresos -->
  <div class="row g-4">
    <!-- Gráfica 1: Composición por Género -->
    <div class="col-12 col-md-6">
      <div class="stats-chart-wrapper">
        <div class="stats-card-header">
          <h2 class="stats-card-header__title">
            <i class="bi bi-gender-ambiguous text-primary" aria-hidden="true"></i>Distribución por Género
          </h2>
        </div>
        <div class="chart-container chart-container--donut"
             data-sigde-chart="donut"
             data-chart-config="<?= $encodeConfig($donutGeneroConfig) ?>">
          <canvas aria-label="Distribución de matrícula por género" role="img"></canvas>
        </div>
      </div>
    </div>

    <!-- Gráfica 2: Nuevos Ingresos, Regulares y Reingresos -->
    <div class="col-12 col-md-6">
      <div class="stats-chart-wrapper">
        <div class="stats-card-header">
          <h2 class="stats-card-header__title">
            <i class="bi bi-pie-chart text-primary" aria-hidden="true"></i>Tipo de Ingreso y Reincorporación
          </h2>
        </div>
        <div class="chart-container chart-container--donut"
             data-sigde-chart="donut"
             data-chart-config="<?= $encodeConfig($donutIngresosConfig) ?>">
          <canvas aria-label="Nuevos ingresos frente a regulares" role="img"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- 3. Tabla: Capacidad y Cupos por Grado y Sección -->
  <div class="stats-table-wrapper">
    <div class="p-3 bg-white border-bottom">
      <h2 class="h6 fw-bold mb-0 text-dark d-flex align-items-center gap-2">
        <i class="bi bi-bar-chart-steps text-primary" aria-hidden="true"></i>Estudiantes por Grado y Sección (Capacidad y Cupos Disponibles)
      </h2>
      <span class="text-secondary small">Balance de ocupación física de aulas y vacantes disponibles</span>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 tabla-estadisticas tabla-matricula-aulas">
        <thead class="table-light text-uppercase small text-secondary">
          <tr>
            <th scope="col" class="col-grado">Grado</th>
            <th scope="col" class="col-sec text-center">Sección</th>
            <th scope="col" class="col-turno">Turno</th>
            <th scope="col" class="col-cap text-center">Capacidad Máxima</th>
            <th scope="col" class="col-m text-center text-primary">Masculino (M)</th>
            <th scope="col" class="col-f text-center text-danger">Femenino (F)</th>
            <th scope="col" class="col-tot text-center fw-bold">Matrícula Total</th>
            <th scope="col" class="col-cupos text-center text-success">Cupos Libres</th>
            <th scope="col" class="col-ocup text-end">% Ocupación</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($matriculaAulas === []): ?>
            <tr>
              <td colspan="9" class="text-center text-secondary py-4">No hay aulas registradas en el período.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($matriculaAulas as $a): ?>
              <?php
              $cap = (int) ($a['capacidad_maxima'] ?? 0);
              $tot = (int) ($a['total_matricula'] ?? 0);
              $lib = max(0, $cap - $tot);
              $pct = $cap > 0 ? (int) min(100, round(($tot / $cap) * 100)) : 0;
              ?>
              <tr>
                <td class="col-grado fw-semibold text-dark"><?= htmlspecialchars((string) $a['grado']) ?></td>
                <td class="col-sec text-center"><span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars((string) $a['seccion']) ?></span></td>
                <td class="col-turno"><?= htmlspecialchars((string) $a['turno']) ?></td>
                <td class="col-cap text-center text-secondary"><?= $cap ?></td>
                <td class="col-m text-center text-primary fw-medium"><?= (int) $a['varones'] ?></td>
                <td class="col-f text-center text-danger fw-medium"><?= (int) $a['hembras'] ?></td>
                <td class="col-tot text-center fw-bold fs-6"><?= $tot ?></td>
                <td class="col-cupos text-center text-success fw-bold"><?= $lib ?></td>
                <td class="col-ocup text-end">
                  <span class="badge text-bg-<?= $pct >= 95 ? 'warning' : ($pct > 0 ? 'info' : 'secondary') ?>"><?= $pct ?>%</span>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- 4. Fila Doble: Edades y Rezago + Análisis de Familias -->
  <div class="row g-4">
    <!-- Tabla de Edades -->
    <div class="col-12 col-lg-6">
      <div class="stats-table-wrapper h-100">
        <div class="p-3 bg-white border-bottom">
          <h2 class="h6 fw-bold mb-0 text-dark d-flex align-items-center gap-2">
            <i class="bi bi-calendar-event text-primary" aria-hidden="true"></i>Estudiantes por Rango de Edad
          </h2>
          <span class="text-secondary small">Pirámide de edades y detección de rezago escolar</span>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-uppercase small text-secondary">
              <tr>
                <th scope="col">Edad Cumplida</th>
                <th scope="col" class="text-center text-primary">Masculino (M)</th>
                <th scope="col" class="text-danger text-center">Femenino (F)</th>
                <th scope="col" class="text-end">Total</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $edades = $demografiaMatricula['desglose_edades'] ?? [];
              if ($edades === []):
              ?>
                <tr><td colspan="4" class="text-center text-secondary py-3">Sin registros de fecha de nacimiento.</td></tr>
              <?php else: ?>
                <?php foreach ($edades as $e): ?>
                  <tr>
                    <td class="fw-semibold"><?= (int) $e['edad'] ?> años</td>
                    <td class="text-center text-primary"><?= (int) $e['varones'] ?></td>
                    <td class="text-center text-danger"><?= (int) $e['hembras'] ?></td>
                    <td class="text-end fw-bold"><?= (int) $e['total'] ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Análisis de Familias, Parentesco y Personal -->
    <div class="col-12 col-lg-6">
      <div class="stats-table-wrapper h-100">
        <div class="p-3 bg-white border-bottom">
          <h2 class="h6 fw-bold mb-0 text-dark d-flex align-items-center gap-2">
            <i class="bi bi-people text-primary" aria-hidden="true"></i>Representantes por Parentesco y Enlace Institucional
          </h2>
          <span class="text-secondary small">Relación familiar legal y representantes que forman parte del personal</span>
        </div>

        <div class="p-3">
          <!-- Parentesco -->
          <div class="mb-3">
            <div class="small fw-semibold text-secondary text-uppercase mb-2">Vínculo de Parentesco:</div>
            <div class="d-flex flex-wrap gap-2">
              <?php foreach (($estadisticasFamilias['parentescos'] ?? []) as $par): ?>
                <span class="badge bg-light text-dark border p-2">
                  <?= htmlspecialchars((string) $par['parentesco']) ?>:
                  <strong class="text-primary ms-1"><?= (int) $par['total'] ?></strong>
                </span>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Representantes que son Personal de la Institución -->
          <div class="mb-0">
            <div class="small fw-semibold text-secondary text-uppercase mb-2">
              Representantes que son Personal del Plantel (<?= (int) ($estadisticasFamilias['total_reps_empleados'] ?? 0) ?>):
            </div>
            <?php
            $repsEmp = $estadisticasFamilias['representantes_empleados'] ?? [];
            if ($repsEmp === []):
            ?>
              <div class="text-secondary small">Ningún representante registrado en la nómina activa de empleados.</div>
            <?php else: ?>
              <div class="d-flex flex-column gap-1">
                <?php foreach ($repsEmp as $re): ?>
                  <div class="p-2 bg-light rounded border d-flex justify-content-between align-items-center">
                    <div>
                      <strong class="text-dark small"><?= htmlspecialchars((string) $re['nombre']) ?></strong>
                      <span class="text-secondary small font-monospace">(<?= htmlspecialchars((string) $re['cedula']) ?>)</span>
                    </div>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle small"><?= htmlspecialchars((string) $re['cargo']) ?></span>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- 5. Distribución Territorial: Parroquia y Sector -->
  <div class="stats-table-wrapper">
    <div class="p-3 bg-white border-bottom">
      <h2 class="h6 fw-bold mb-0 text-dark d-flex align-items-center gap-2">
        <i class="bi bi-geo-alt-fill text-primary" aria-hidden="true"></i>Estudiantes por Parroquia de Residencia
      </h2>
      <span class="text-secondary small">Localización geográfica de la comunidad estudiantil</span>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light text-uppercase small text-secondary">
          <tr>
            <th scope="col">Parroquia</th>
            <th scope="col">Municipio</th>
            <th scope="col" class="text-end">Total Estudiantes</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($distribucionParroquias === []): ?>
            <tr><td colspan="3" class="text-center text-secondary py-3">Sin direcciones domiciliarias registradas.</td></tr>
          <?php else: ?>
            <?php foreach ($distribucionParroquias as $dp): ?>
              <tr>
                <td class="fw-semibold text-dark"><?= htmlspecialchars((string) $dp['parroquia']) ?></td>
                <td><?= htmlspecialchars((string) $dp['municipio']) ?></td>
                <td class="text-end fw-bold"><?= (int) $dp['total'] ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
