<?php
declare(strict_types=1);

/**
 * Partial: estadisticas/partials/tab-institucional.php
 * Pestaña 4: Control Administrativo (Recaudos Pendientes, Cobertura Docente y Personal Escolar).
 *
 * @var array<string, mixed>            $documentosPendientes
 * @var array<int, array<string, mixed>> $coberturaAulas
 * @var array<int, array<string, mixed>> $estadisticasPersonal
 * @var array<string, mixed>            $metricasPersonal
 */
?>
<div class="d-flex flex-column gap-4">

  <!-- 1. Documentos y Recaudos Pendientes -->
  <div class="stats-table-wrapper">
    <div class="p-3 bg-white border-bottom d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
      <div>
        <h2 class="h6 fw-bold mb-0 text-dark d-flex align-items-center gap-2">
          <i class="bi bi-file-earmark-text text-danger" aria-hidden="true"></i>Expedientes y Documentos Físicos Pendientes
        </h2>
        <span class="text-secondary small">Incluye recaudos obligatorios y opcionales pendientes de inscripción</span>
      </div>
      <div>
        <span class="badge bg-danger-subtle text-danger border border-danger-subtle fs-6 px-3 py-1">
          <i class="bi bi-exclamation-circle me-1"></i><?= (int) ($documentosPendientes['estudiantes_con_deuda'] ?? 0) ?> estudiantes adeudan recaudos
        </span>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light text-uppercase small text-secondary">
          <tr>
            <th scope="col">Tipo de Documento / Recaudo</th>
            <th scope="col" class="text-center">Estado de Consignación</th>
            <th scope="col" class="text-end text-danger">Total Pendientes</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $docs = $documentosPendientes['documentos'] ?? [];
          if ($docs === []):
          ?>
            <tr><td colspan="3" class="text-center text-success py-3"><i class="bi bi-check-circle me-1"></i>No hay recaudos pendientes de consignación.</td></tr>
          <?php else: ?>
            <?php foreach ($docs as $d): ?>
              <tr>
                <td class="fw-semibold text-dark">
                  <i class="bi bi-file-earmark me-2 text-secondary"></i><?= htmlspecialchars((string) $d['documento']) ?>
                </td>
                <td class="text-center">
                  <span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i>Pendiente por entregar</span>
                </td>
                <td class="text-end fw-bold text-danger fs-6"><?= (int) $d['pendientes'] ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- 2. Cobertura y Asignación Docente por Aula -->
  <div class="stats-table-wrapper">
    <div class="p-3 bg-white border-bottom">
      <h2 class="h6 fw-bold mb-0 text-dark d-flex align-items-center gap-2">
        <i class="bi bi-person-workspace text-primary" aria-hidden="true"></i>Infraestructura y Asignación Docente por Aula
      </h2>
      <span class="text-secondary small">Cobertura de titulares en secciones y relación con capacidad instalada</span>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light text-uppercase small text-secondary">
          <tr>
            <th scope="col">Aula</th>
            <th scope="col">Turno</th>
            <th scope="col" class="text-center">Capacidad</th>
            <th scope="col" class="text-center">Matriculados</th>
            <th scope="col">Docente Titular</th>
            <th scope="col" class="text-end">Estado</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($coberturaAulas === []): ?>
            <tr><td colspan="6" class="text-center text-secondary py-3">Sin aulas configuradas.</td></tr>
          <?php else: ?>
            <?php foreach ($coberturaAulas as $ca): ?>
              <?php
              $cap = (int) ($ca['capacidad_maxima'] ?? 0);
              $mat = (int) ($ca['total_matricula'] ?? 0);
              $tieneDoc = !empty($ca['docente_titular']);
              ?>
              <tr>
                <td class="fw-semibold text-dark"><?= htmlspecialchars((string) ($ca['grado'] . ' "' . $ca['seccion'] . '"')) ?></td>
                <td><?= htmlspecialchars((string) ($ca['turno'] ?? '—')) ?></td>
                <td class="text-center text-secondary"><?= $cap ?></td>
                <td class="text-center fw-medium <?= $mat > $cap ? 'text-danger fw-bold' : '' ?>"><?= $mat ?></td>
                <td>
                  <?php if ($tieneDoc): ?>
                    <strong class="text-dark"><?= htmlspecialchars((string) $ca['docente_titular']) ?></strong>
                  <?php else: ?>
                    <span class="badge text-bg-warning">Sin titular asignado</span>
                  <?php endif; ?>
                </td>
                <td class="text-end">
                  <span class="badge text-bg-<?= $tieneDoc ? 'success' : 'danger' ?>">
                    <?= $tieneDoc ? 'Asignada' : 'Por asignar' ?>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- 3. Talento Humano y Personal Escolar -->
  <div class="stats-table-wrapper">
    <div class="p-3 bg-white border-bottom">
      <h2 class="h6 fw-bold mb-0 text-dark d-flex align-items-center gap-2">
        <i class="bi bi-briefcase text-primary" aria-hidden="true"></i>Talento Humano y Personal Escolar
      </h2>
      <span class="text-secondary small">Distribución laboral por cargo, turno, género y ausencias médicas</span>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light text-uppercase small text-secondary">
          <tr>
            <th scope="col">Cargo Institucional</th>
            <th scope="col">Turno</th>
            <th scope="col" class="text-center">Total Nómina</th>
            <th scope="col" class="text-center text-success">Activos</th>
            <th scope="col" class="text-center text-secondary">Inactivos</th>
            <th scope="col" class="text-center text-primary">Género (M / F)</th>
            <th scope="col" class="text-end">En Reposo</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($estadisticasPersonal === []): ?>
            <tr><td colspan="7" class="text-center text-secondary py-3">Sin personal registrado.</td></tr>
          <?php else: ?>
            <?php foreach ($estadisticasPersonal as $ep): ?>
              <tr>
                <td class="fw-semibold text-dark">
                  <i class="bi bi-briefcase me-1 text-primary" aria-hidden="true"></i>
                  <?= htmlspecialchars((string) $ep['cargo']) ?>
                </td>
                <td>
                  <?php if (!empty($ep['turno'])): ?>
                    <span class="badge bg-light text-dark border"><?= htmlspecialchars((string) $ep['turno']) ?></span>
                  <?php endif; ?>
                </td>
                <td class="text-center fw-bold"><?= (int) $ep['total_personal'] ?></td>
                <td class="text-center text-success fw-medium"><?= (int) $ep['activos'] ?></td>
                <td class="text-center <?= (int) $ep['inactivos'] > 0 ? 'text-danger fw-medium' : 'text-secondary' ?>"><?= (int) $ep['inactivos'] ?></td>
                <td class="text-center text-secondary small"><?= (int) $ep['varones'] ?> masc. / <?= (int) $ep['hembras'] ?> fem.</td>
                <td class="text-end">
                  <?php if ((int) $ep['en_reposo'] > 0): ?>
                    <span class="badge bg-warning text-dark"><i class="bi bi-heart-pulse me-1"></i><?= (int) $ep['en_reposo'] ?> reposo</span>
                  <?php else: ?>
                    <span class="text-secondary">—</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
