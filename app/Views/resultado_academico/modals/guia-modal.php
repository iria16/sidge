<?php
/**
 * Modal: criterios de promoción anual.
 * Compartido entre resultado_academico/index.php y resultado_academico/seccion.php.
 */

ob_start();
?>
<div class="info-alert mb-3">
  <div class="info-alert__icon"><i class="bi bi-info-circle-fill" aria-hidden="true"></i></div>
  <div>
    <div class="info-alert__title">Marco Institucional y Pedagógico (Resolución N° 266 MPPE)</div>
    <div class="info-alert__text">
      El docente registra el literal de fin de ciclo correspondiente al grado cursado. El estado de promoción se deriva automáticamente del literal asignado; la Dirección consulta el avance de las secciones.
    </div>
  </div>
</div>

<h6 class="fw-bold mb-2 text-primary small text-uppercase">
  <i class="bi bi-card-checklist me-1 text-primary" aria-hidden="true"></i>Escala cualitativa de literales
</h6>

<div class="d-flex flex-column gap-2 mb-3">
  <div class="p-2 px-3 rounded border bg-light d-flex align-items-center justify-content-between gap-3">
    <div class="d-flex align-items-center gap-3 min-w-0">
      <span class="badge text-bg-success px-2 py-1 fw-bold flex-shrink-0">Literal A</span>
      <div class="min-w-0">
        <strong class="text-dark d-block small">Alcanzó todas las competencias de manera sobresaliente.</strong>
        <span class="text-secondary small">Avanza al grado inmediato superior</span>
      </div>
    </div>
    <span class="status-badge status-badge--active flex-shrink-0">Promovido</span>
  </div>

  <div class="p-2 px-3 rounded border bg-light d-flex align-items-center justify-content-between gap-3">
    <div class="d-flex align-items-center gap-3 min-w-0">
      <span class="badge text-bg-primary px-2 py-1 fw-bold flex-shrink-0">Literal B</span>
      <div class="min-w-0">
        <strong class="text-dark d-block small">Alcanzó todas las competencias con desempeño muy satisfactorio.</strong>
        <span class="text-secondary small">Avanza al grado inmediato superior</span>
      </div>
    </div>
    <span class="status-badge status-badge--active flex-shrink-0">Promovido</span>
  </div>

  <div class="p-2 px-3 rounded border bg-light d-flex align-items-center justify-content-between gap-3">
    <div class="d-flex align-items-center gap-3 min-w-0">
      <span class="badge text-bg-info px-2 py-1 fw-bold flex-shrink-0">Literal C</span>
      <div class="min-w-0">
        <strong class="text-dark d-block small">Alcanzó la mayoría de las competencias esenciales.</strong>
        <span class="text-secondary small">Avanza al grado inmediato superior</span>
      </div>
    </div>
    <span class="status-badge status-badge--active flex-shrink-0">Promovido</span>
  </div>

  <div class="p-2 px-3 rounded border bg-light d-flex align-items-center justify-content-between gap-3">
    <div class="d-flex align-items-center gap-3 min-w-0">
      <span class="badge text-bg-warning px-2 py-1 fw-bold flex-shrink-0">Literal D</span>
      <div class="min-w-0">
        <strong class="text-dark d-block small">Alcanzó algunas competencias mínimas; requiere acompañamiento.</strong>
        <span class="text-secondary small">Avanza al grado inmediato superior</span>
      </div>
    </div>
    <span class="status-badge status-badge--active flex-shrink-0">Promovido</span>
  </div>

  <div class="p-2 px-3 rounded border bg-light d-flex align-items-center justify-content-between gap-3">
    <div class="d-flex align-items-center gap-3 min-w-0">
      <span class="badge text-bg-danger px-2 py-1 fw-bold flex-shrink-0">Literal E</span>
      <div class="min-w-0">
        <strong class="text-danger d-block small">No alcanzó las competencias esenciales para avanzar.</strong>
        <span class="text-danger small">Permanece en el mismo grado cursado</span>
      </div>
    </div>
    <span class="status-badge status-badge--inactive flex-shrink-0">No promovido</span>
  </div>
</div>

<h6 class="fw-bold mb-2 mt-3 text-primary small text-uppercase">
  <i class="bi bi-shield-check me-1 text-primary" aria-hidden="true"></i>Reglas de cierre escolar
</h6>
<ul class="text-secondary small mb-0 ps-3">
  <li class="mb-1"><strong>Sin literal:</strong> el estudiante queda pendiente y bloquea el cierre del año escolar.</li>
  <li class="mb-1"><strong>Retiro:</strong> el retiro es independiente y no inventa un resultado académico.</li>
  <li><strong>Egreso de 6.º grado:</strong> solo procede con estado promovido.</li>
</ul>
<?php
$modalBody = ob_get_clean();

$modalFooter = '<button type="button" class="btn btn-primary btn-sm" data-bs-dismiss="modal">Entendido</button>';

$modalId       = 'modalGuiaNormativa';
$modalTitle    = 'Criterios de evaluación y promoción anual';
$modalSubtitle = 'Escala cualitativa y efectos en el cierre del año escolar';
$modalSize     = 'lg';

include __DIR__ . '/../../components/modal.php';
