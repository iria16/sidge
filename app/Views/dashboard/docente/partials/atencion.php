<?php
declare(strict_types=1);

/**
 * Partial: dashboard/docente/partials/atencion.php
 * Centro único de alertas operativas y pendientes del Docente de Aula.
 *
 * Variables esperadas:
 * @var bool  $mostrarLiterales
 * @var array $literales
 */

$pendientes     = (int) ($literales['pendientes'] ?? 0);
$totalLiterales = (int) ($literales['total'] ?? 0);
$hayPendientes  = !empty($mostrarLiterales) && $pendientes > 0;
?>
<section class="content-card mb-4" aria-labelledby="atencionHeading">
  <div class="content-card__header d-flex justify-content-between align-items-center">
    <h2 class="content-card__title mb-0" id="atencionHeading">
      <i class="bi bi-bell text-warning me-2" aria-hidden="true"></i>Atención inmediata
    </h2>
    <?php if ($hayPendientes): ?>
      <span class="badge text-bg-warning">Acción requerida</span>
    <?php endif; ?>
  </div>

  <div class="content-card__body p-3">
    <?php if (!$hayPendientes): ?>
      <!-- Estado vacío: Todo al día -->
      <div class="d-flex align-items-center gap-3 p-3 text-success bg-success-subtle rounded border border-success-subtle" role="status">
        <i class="bi bi-check-circle-fill fs-3 flex-shrink-0" aria-hidden="true"></i>
        <div>
          <div class="fw-semibold">Sin pendientes hoy</div>
          <?php if (!empty($mostrarLiterales)): ?>
            <div class="small">Calificaciones de promoción completadas para todos los estudiantes de la sección.</div>
          <?php else: ?>
            <div class="small">Aula activa en período regular de clases. No se requieren acciones administrativas en este momento.</div>
          <?php endif; ?>
        </div>
      </div>
    <?php else: ?>
      <div class="d-flex flex-column gap-3">
        <!-- Alerta de Literales Finales Pendientes (Solo activa al acercarse el cierre escolar o con carga iniciada) -->
        <div class="alert alert-warning py-2 px-3 mb-0 d-flex align-items-center justify-content-between flex-wrap gap-2" role="alert">
          <div class="d-flex align-items-center gap-2">
            <i class="bi bi-award fs-5 flex-shrink-0 text-warning" aria-hidden="true"></i>
            <div>
              <strong>Carga de literales de promoción pendiente:</strong>
              Faltan <?= htmlspecialchars((string) $pendientes) ?> de <?= htmlspecialchars((string) $totalLiterales) ?> estudiantes por registrar su calificación final de promoción.
            </div>
          </div>
          <a href="<?= BASE_URL ?>resultadoAcademico/seccion?estado=pendiente" class="btn btn-sm btn-warning">
            Cargar literales &rsaquo;
          </a>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>