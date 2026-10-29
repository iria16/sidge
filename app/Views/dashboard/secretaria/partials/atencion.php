<?php
declare(strict_types=1);

/**
 * Partial: dashboard/secretaria/partials/atencion.php
 * Centro único de alertas operativas y pendientes de Secretaría.
 *
 * Variables esperadas:
 * @var string|null $turnoNombre
 * @var array|null  $estadisticasRatificacion
 * @var array|null  $estadisticasRetiros
 */

$porRatificar      = (int) ($estadisticasRatificacion['porRatificar'] ?? 0);
$retirosPendientes = (int) ($estadisticasRetiros['pendientes'] ?? 0);
$turnoTexto        = $turnoNombre !== null && $turnoNombre !== '' ? 'Turno ' . $turnoNombre : 'turno actual';
$hayAlertas        = $porRatificar > 0 || $retirosPendientes > 0;
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
          <div class="small">Procesos de inscripción, ratificación y retiros al día. No hay solicitudes pendientes de tramitación en este momento.</div>
        </div>
      </div>
    <?php else: ?>
      <div class="d-flex flex-column gap-3">
        <!-- 1. Alerta de Ratificaciones Pendientes -->
        <?php if ($porRatificar > 0): ?>
          <div class="alert alert-warning py-2 px-3 mb-0 d-flex align-items-center justify-content-between flex-wrap gap-2" role="alert">
            <div class="d-flex align-items-center gap-2">
              <i class="bi bi-arrow-repeat fs-5 flex-shrink-0 text-warning" aria-hidden="true"></i>
              <div>
                <strong><?= $porRatificar ?> estudiante<?= $porRatificar === 1 ? '' : 's' ?> por ratificar:</strong>
                Alumnos con matrícula previa en <?= htmlspecialchars($turnoTexto) ?> que aún no han formalizado su inscripción.
              </div>
            </div>
            <a href="<?= BASE_URL ?>ratificacion/index" class="btn btn-sm btn-warning">
              Ratificar estudiantes &rsaquo;
            </a>
          </div>
        <?php endif; ?>

        <!-- 2. Alerta de Solicitudes de Retiro en Trámite -->
        <?php if ($retirosPendientes > 0): ?>
          <div class="alert alert-info py-2 px-3 mb-0 d-flex align-items-center justify-content-between flex-wrap gap-2" role="alert">
            <div class="d-flex align-items-center gap-2">
              <i class="bi bi-box-arrow-right fs-5 flex-shrink-0 text-info" aria-hidden="true"></i>
              <div>
                <strong><?= $retirosPendientes ?> solicitud<?= $retirosPendientes === 1 ? '' : 'es' ?> de retiro en trámite:</strong>
                Retiros registrados en <?= htmlspecialchars($turnoTexto) ?> que esperan resolución por parte de la Dirección.
              </div>
            </div>
            <a href="<?= BASE_URL ?>withdrawal/index" class="btn btn-sm btn-outline-primary">
              Ver retiros &rsaquo;
            </a>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</section>