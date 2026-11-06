<?php

declare(strict_types=1);

/**
 * Vista: gestion_estudiante/reingreso/index.php
 * Listado de estudiantes con retiro aprobado candidatos a reincorporación escolar.
 *
 * @var array $retirados
 * @var string $busqueda
 */

$pageTitle       = 'Reingreso Escolar';
$pageDescription = 'Estudiantes desincorporados que pueden ser reincorporados a la matrícula activa en el período en curso.';
$currentNav      = 'reingreso';
$extraScripts    = ['/js/modules/retiros/withdrawals.js'];

$breadcrumbs = [
    ['label' => 'Gestión de estudiantes', 'href' => null],
    ['label' => 'Reingreso Escolar', 'href' => null],
];

$retirados = $retirados ?? [];
$busqueda  = $busqueda ?? '';

ob_start();
?>
<div class="d-flex flex-column gap-4">

  <!-- Header con buscador -->
  <div class="card border-0 shadow-sm">
    <div class="card-body p-3">
      <form method="GET" action="<?= BASE_URL ?>reingreso/index" class="row g-2 align-items-center">
        <div class="col-md-6 col-lg-5">
          <div class="input-icon-group">
            <i class="bi bi-search" aria-hidden="true"></i>
            <input type="search" name="q" class="form-control"
                   placeholder="Buscar por nombre, apellido o cédula..."
                   value="<?= htmlspecialchars($busqueda) ?>">
          </div>
        </div>
        <div class="col-auto">
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-filter me-1"></i> Filtrar
          </button>
          <?php if ($busqueda !== ''): ?>
            <a href="<?= BASE_URL ?>reingreso/index" class="btn btn-outline-secondary">Limpiar</a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <!-- Panel de Datos -->
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
      <h6 class="mb-0 fw-bold d-flex align-items-center gap-2">
        <i class="bi bi-arrow-counterclockwise text-primary"></i>
        <span>Estudiantes Retirados Disponibles para Reincorporación (<?= count($retirados) ?>)</span>
      </h6>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light text-uppercase small text-secondary">
          <tr>
            <th scope="col" style="min-width: 220px;">Estudiante</th>
            <th scope="col">Antecedente Escolar</th>
            <th scope="col">Período Retiro</th>
            <th scope="col">Fecha Retiro</th>
            <th scope="col">Motivo</th>
            <th scope="col" class="text-center" style="width: 140px;">Acción</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($retirados)): ?>
            <tr>
              <td colspan="6" class="text-center text-secondary py-5">
                <i class="bi bi-info-circle fs-3 d-block mb-2 text-muted"></i>
                No se encontraron estudiantes retirados pendientes de reincorporación.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($retirados as $item): ?>
              <tr>
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <div class="avatar avatar--sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; font-size: 0.85rem; font-weight: 600;">
                      <?= htmlspecialchars(mb_strtoupper(mb_substr($item['nombre_completo'] ?? 'E', 0, 1))) ?>
                    </div>
                    <div>
                      <div class="fw-semibold text-dark"><?= htmlspecialchars($item['nombre_completo'] ?? '—') ?></div>
                      <span class="text-secondary small"><?= htmlspecialchars($item['documento'] ?? '—') ?></span>
                    </div>
                  </div>
                </td>
                <td>
                  <span class="fw-medium text-dark"><?= htmlspecialchars($item['ultimo_grado'] ?? '—') ?></span><br>
                  <span class="text-secondary small">Sección <?= htmlspecialchars($item['ultima_seccion'] ?? '—') ?> (<?= htmlspecialchars($item['ultimo_turno'] ?? '—') ?>)</span>
                </td>
                <td>
                  <span class="badge bg-light text-dark border"><?= htmlspecialchars($item['anio_retiro'] ?? '—') ?></span>
                </td>
                <td>
                  <span class="small text-secondary"><?= !empty($item['fecha_retiro']) ? date('d/m/Y', strtotime($item['fecha_retiro'])) : '—' ?></span>
                </td>
                <td>
                  <span class="text-dark small"><?= htmlspecialchars($item['motivo_retiro'] ?? '—') ?></span>
                </td>
                <td class="text-center">
                  <button type="button"
                          class="btn btn-sm btn-outline-success btn-abrir-reingreso d-inline-flex align-items-center gap-1"
                          data-id-estudiante="<?= (int) $item['id_estudiante'] ?>"
                          title="Reincorporar estudiante">
                    <i class="bi bi-arrow-counterclockwise"></i>
                    <span>Reincorporar</span>
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
<?php
$pageContent = ob_get_clean();

// Modales requeridos
ob_start();
include __DIR__ . '/../retiro/modals/reingreso-modal.php';
$pageModals = ob_get_clean();

require __DIR__ . '/../../layouts/app.php';