<?php
declare(strict_types=1);

/**
 * Partial: dashboard/directivo/partials/atencion.php
 * Centro único de alertas operativas y pendientes del Directivo.
 *
 * Variables esperadas:
 * @var array $alertas
 * @var array $metricasPersonal
 */

$enReposoHoy = (int) ($metricasPersonal['en_reposo_hoy'] ?? 0);
$hayAlertas  = count($alertas) > 0 || $enReposoHoy > 0;
?>
<section class="content-card mb-4" aria-labelledby="atencionHeading">
  <div class="content-card__header d-flex justify-content-between align-items-center">
    <h2 class="content-card__title mb-0" id="atencionHeading">
      <i class="bi bi-bell text-warning me-2" aria-hidden="true"></i>Atención inmediata
    </h2>
    <?php if ($hayAlertas): ?>
      <span class="badge text-bg-warning">Acción requerida</span>
    <?php endif; ?>
  </div>

  <div class="content-card__body p-3">
    <?php if (!$hayAlertas): ?>
      <!-- Estado vacío: Todo al día -->
      <div class="d-flex align-items-center gap-3 p-3 text-success bg-success-subtle rounded border border-success-subtle" role="status">
        <i class="bi bi-check-circle-fill fs-3 flex-shrink-0" aria-hidden="true"></i>
        <div>
          <div class="fw-semibold">Sin pendientes hoy</div>
          <div class="small">No hay solicitudes de retiro, ausencias médicas ni aulas sin docente titular que requieran atención.</div>
        </div>
      </div>
    <?php else: ?>
      <div class="d-flex flex-column gap-3">
        <!-- 1. Alerta de Reposos Médicos del Día -->
        <?php if ($enReposoHoy > 0): ?>
          <div class="alert alert-info py-2 px-3 mb-0 d-flex align-items-center justify-content-between flex-wrap gap-2" role="alert">
            <div class="d-flex align-items-center gap-2">
              <i class="bi bi-heart-pulse text-danger fs-5 flex-shrink-0" aria-hidden="true"></i>
              <div>
                <strong><?= $enReposoHoy ?> empleado<?= $enReposoHoy === 1 ? '' : 's' ?></strong> en reposo médico vigente hoy.
                <div class="text-support small">Supervise la continuidad de actividades en aula y dependencias.</div>
              </div>
            </div>
            <a href="<?= BASE_URL ?>empleado/index" class="btn btn-sm btn-outline-primary">
              <i class="bi bi-person-lines-fill me-1" aria-hidden="true"></i>Ver personal en reposo
            </a>
          </div>
        <?php endif; ?>

        <!-- 2. Alertas dinámicas provenientes del modelo (Retiros, Aulas vacías, Literales) -->
        <?php foreach ($alertas as $alerta): ?>
          <?php
          $tipo    = (string) ($alerta['tipo'] ?? '');
          $nivel   = (string) ($alerta['nivel'] ?? 'warning');
          $detalle = (string) ($alerta['detalle'] ?? '');
          $seccion = (string) ($alerta['seccion'] ?? '');

          // Asignación de acción y estilo según el tipo de alerta (Responsabilidad de la Vista)
          $accionTexto = '';
          $accionUrl   = '';
          $icono       = 'bi-exclamation-triangle';

          if ($tipo === 'retiros_pendientes') {
              $accionTexto = 'Resolver retiros';
              $accionUrl   = BASE_URL . 'withdrawal/index?estado=PENDIENTE';
              $icono       = 'bi-box-arrow-right';
          } elseif ($tipo === 'sin_docente') {
              $accionTexto = 'Asignar titular';
              $accionUrl   = BASE_URL . 'teacherAssignment/index';
              $icono       = 'bi-person-x';
          } elseif ($tipo === 'sin_literal') {
              $accionTexto = 'Ver calificaciones';
              $accionUrl   = BASE_URL . 'estadistica/index?pestana=rendimiento';
              $icono       = 'bi-award';
          }
          ?>
          <div class="alert alert-<?= htmlspecialchars($nivel) ?> py-2 px-3 mb-0 d-flex align-items-center justify-content-between flex-wrap gap-2" role="alert">
            <div class="d-flex align-items-center gap-2">
              <i class="bi <?= htmlspecialchars($icono) ?> fs-5 flex-shrink-0" aria-hidden="true"></i>
              <div>
                <strong><?= htmlspecialchars($seccion !== '' ? $seccion . ': ' : '') ?></strong><?= htmlspecialchars($detalle) ?>
              </div>
            </div>
            <?php if ($accionTexto !== '' && $accionUrl !== ''): ?>
              <a href="<?= htmlspecialchars($accionUrl) ?>" class="btn btn-sm btn-<?= $nivel === 'warning' ? 'warning' : 'outline-primary' ?>">
                <?= htmlspecialchars($accionTexto) ?> &rsaquo;
              </a>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
