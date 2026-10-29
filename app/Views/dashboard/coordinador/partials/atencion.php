<?php
declare(strict_types=1);

/**
 * Partial: dashboard/coordinador/partials/atencion.php
 * Centro único de alertas pedagógicas y operativas para Coordinación.
 *
 * Variables esperadas:
 * @var array $alertas
 * @var array $metricasPersonal
 */

$enReposoHoy = (int) ($metricasPersonal['en_reposo_hoy'] ?? 0);
$hayAlertas  = count($alertas) > 0 || $enReposoHoy > 0;
?>
<section class="content-card mb-4" aria-labelledby="atencionCoordinadorHeading">
  <div class="content-card__header d-flex justify-content-between align-items-center">
    <h2 class="content-card__title mb-0" id="atencionCoordinadorHeading">
      <i class="bi bi-bell text-warning me-2" aria-hidden="true"></i>Atención inmediata
    </h2>
    <?php if ($hayAlertas): ?>
      <span class="badge text-bg-warning">Acción requerida</span>
    <?php endif; ?>
  </div>

  <div class="content-card__body p-3">
    <?php if (!$hayAlertas): ?>
      <!-- Estado vacío: Todo en orden -->
      <div class="d-flex align-items-center gap-3 p-3 text-success bg-success-subtle rounded border border-success-subtle" role="status">
        <i class="bi bi-check-circle-fill fs-3 flex-shrink-0" aria-hidden="true"></i>
        <div>
          <div class="fw-semibold">Sin pendientes hoy</div>
          <div class="small">No hay ausencias médicas ni contingencias vigentes en el plantel.</div>
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
                <div class="text-support small">Supervise la cobertura pedagógica y la continuidad de actividades en aula.</div>
              </div>
            </div>
            <a href="<?= BASE_URL ?>empleado/index" class="btn btn-sm btn-outline-primary">
              <i class="bi bi-person-lines-fill me-1" aria-hidden="true"></i>Ver personal en reposo
            </a>
          </div>
        <?php endif; ?>

        <!-- 2. Alertas dinámicas del modelo (Aulas sin titular, Literales de fin de ciclo) -->
        <?php foreach ($alertas as $alerta): ?>
          <?php
          $tipo    = (string) ($alerta['tipo'] ?? '');
          $nivel   = (string) ($alerta['nivel'] ?? 'warning');
          $detalle = (string) ($alerta['detalle'] ?? '');
          $seccion = (string) ($alerta['seccion'] ?? '');

          // Configuración de estilo y acción directa según el tipo de alerta
          $accionTexto = '';
          $accionUrl   = '';
          $icono       = 'bi-exclamation-triangle';

          if ($tipo === 'sin_docente') {
              $accionTexto = 'Asignar titular';
              $accionUrl   = BASE_URL . 'teacherAssignment/index';
              $icono       = 'bi-person-x';
          } elseif ($tipo === 'sin_literal') {
              $accionTexto = 'Ver calificaciones';
              $accionUrl   = BASE_URL . 'estadistica/index?vista=rendimiento';
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
