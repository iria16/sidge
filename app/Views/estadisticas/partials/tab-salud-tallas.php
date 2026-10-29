<?php
declare(strict_types=1);

/**
 * Partial: estadisticas/partials/tab-salud-tallas.php
 * Pestaña 3: Dotación de Uniformes, Calzado y Salud Antropométrica.
 *
 * @var array<string, mixed>            $metricasSalud
 * @var array<string, int>|array<int, array<string, mixed>> $tiposSangre
 * @var array<int, array<string, mixed>> $filasSalud
 * @var array<int, array<string, mixed>> $filasAntropometricas
 * @var array<string, mixed>            $distribucionTallas
 * @var array<int, array<string, mixed>> $filasTallas
 */
?>
<div class="d-flex flex-column gap-4">

  <!-- 1. Tallas de Uniforme y Calzado -->
  <div class="stats-table-wrapper">
    <div class="p-3 bg-white border-bottom">
      <h2 class="h6 fw-bold mb-0 text-dark d-flex align-items-center gap-2">
        <i class="bi bi-tag text-primary" aria-hidden="true"></i>Dotación: Tallas de Camisa, Pantalón y Calzado por Grado
      </h2>
      <span class="text-secondary small">Cobertura de registro de tallas escolares para dotación</span>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 tabla-tallas">
        <thead class="table-light text-uppercase small text-secondary">
          <tr>
            <th scope="col" class="col-grado">Grado</th>
            <th scope="col" class="col-tot text-center">Total Estudiantes</th>
            <th scope="col" class="col-item text-center">Camisa</th>
            <th scope="col" class="col-item text-center">Pantalón</th>
            <th scope="col" class="col-item text-center">Calzado</th>
            <th scope="col" class="col-all text-center">Con Todas</th>
            <th scope="col" class="col-cob text-end">Cobertura</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($filasTallas === []): ?>
            <tr><td colspan="7" class="text-center text-secondary py-3">Sin registros de tallas escolares.</td></tr>
          <?php else: ?>
            <?php foreach ($filasTallas as $ft): ?>
              <?php
              $tot = (int) ($ft['total_estudiantes'] ?? 0);
              $cob = (int) ($ft['cobertura'] ?? 0);
              ?>
              <tr>
                <td class="col-grado fw-semibold text-dark"><?= htmlspecialchars((string) ($ft['grado'] ?? '')) ?></td>
                <td class="col-tot text-center"><?= $tot ?></td>
                <td class="col-item text-center text-secondary"><?= (int) ($ft['con_camisa'] ?? 0) ?></td>
                <td class="col-item text-center text-secondary"><?= (int) ($ft['con_pantalon'] ?? 0) ?></td>
                <td class="col-item text-center text-secondary"><?= (int) ($ft['con_zapato'] ?? 0) ?></td>
                <td class="col-all text-center fw-bold"><?= (int) ($ft['con_todas'] ?? 0) ?></td>
                <td class="col-cob text-end">
                  <?php if ($tot === 0): ?>
                    <span class="text-secondary">—</span>
                  <?php else: ?>
                    <span class="badge text-bg-<?= $cob === 100 ? 'success' : ($cob >= 50 ? 'info' : 'secondary') ?>"><?= $cob ?>%</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- 2. Medidas Antropométricas y Salud -->
  <div class="stats-table-wrapper">
    <div class="p-3 bg-white border-bottom">
      <h2 class="h6 fw-bold mb-0 text-dark d-flex align-items-center gap-2">
        <i class="bi bi-heart-pulse text-primary" aria-hidden="true"></i>Salud: Medidas Antropométricas por Grado
      </h2>
      <span class="text-secondary small">Monitoreo de peso, estatura y completitud de fichas médicas</span>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 tabla-antropometria">
        <thead class="table-light text-uppercase small text-secondary">
          <tr>
            <th scope="col" class="col-grado">Grado</th>
            <th scope="col" class="col-stat text-center text-warning">En Proceso</th>
            <th scope="col" class="col-stat text-center text-success">Completas</th>
            <th scope="col" class="col-stat text-center text-danger">Sin Medidas</th>
            <th scope="col" class="col-metric text-center">Promedio Peso (kg)</th>
            <th scope="col" class="col-metric text-center">Promedio Estatura (cm)</th>
            <th scope="col" class="col-pct text-end">% Completado</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($filasSalud === []): ?>
            <tr><td colspan="7" class="text-center text-secondary py-3">Sin registros de medidas antropométricas.</td></tr>
          <?php else: ?>
            <?php foreach ($filasSalud as $fs): ?>
              <?php
              $proc = (int) ($fs['en_proceso'] ?? 0);
              $comp = (int) ($fs['completas'] ?? 0);
              $sinM = (int) ($fs['sin_medidas'] ?? 0);
              $cbar = (int) ($fs['porcentaje_completo'] ?? 0);
              ?>
              <tr>
                <td class="col-grado fw-semibold text-dark"><?= htmlspecialchars((string) ($fs['grado'] ?? '')) ?></td>
                <td class="col-stat text-center text-warning"><?= $proc ?></td>
                <td class="col-stat text-center text-success fw-medium"><?= $comp ?></td>
                <td class="col-stat text-center text-danger"><?= $sinM ?></td>
                <td class="col-metric text-center text-secondary"><?= number_format((float) ($fs['promedio_peso'] ?? 0), 1, ',', '.') ?></td>
                <td class="col-metric text-center text-secondary"><?= number_format((float) ($fs['promedio_estatura'] ?? 0), 1, ',', '.') ?></td>
                <td class="col-pct text-end">
                  <span class="badge text-bg-<?= $cbar === 100 ? 'success' : ($cbar >= 50 ? 'info' : 'secondary') ?>"><?= $cbar ?>%</span>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- 3. Tipos de Sangre -->
  <div class="stats-table-wrapper p-3">
    <div class="small fw-semibold text-secondary text-uppercase mb-2">
      <i class="bi bi-droplet-fill text-danger me-1"></i>Tipos de Sangre Registrados en la Población Estudiantil:
    </div>
    <div class="d-flex flex-wrap gap-2">
      <?php if ($tiposSangre === []): ?>
        <span class="text-secondary small">Sin tipificaciones sanguíneas registradas en fichas médicas.</span>
      <?php else: ?>
        <?php foreach ($tiposSangre as $k => $val): ?>
          <?php
          if (is_array($val)) {
              $nombreTipo = (string) ($val['tipo_sangre'] ?? $k);
              $cantEstud  = (int) ($val['total'] ?? 0);
          } else {
              $nombreTipo = (string) $k;
              $cantEstud  = (int) $val;
          }
          $etiqueta = ($nombreTipo === 'sin_registrar' || $nombreTipo === '' || $nombreTipo === null)
              ? 'Sin registrar'
              : $nombreTipo;
          ?>
          <span class="badge bg-light text-dark border p-2">
            Tipo <strong><?= htmlspecialchars($etiqueta) ?></strong>:
            <span class="text-danger fw-bold ms-1"><?= $cantEstud ?></span>
          </span>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

</div>
