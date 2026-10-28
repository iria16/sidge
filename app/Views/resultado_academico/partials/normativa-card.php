<?php
/**
 * Partial: tarjeta de normativa de la escala cualitativa.
 */
?>
<div class="normativa-card shadow-sm mb-4">
  <div class="p-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div class="d-flex align-items-center gap-3">
      <div class="normativa-card__icon">
        <i class="bi bi-book" aria-hidden="true"></i>
      </div>
      <div>
        <h6 class="mb-0 fw-bold text-dark resultado-normativa__title">Normativa de Escala Cualitativa Oficial</h6>
        <p class="mb-0 text-secondary small">Resolución N° 266 del Ministerio del Poder Popular para la Educación</p>
      </div>
    </div>
    <div>
      <button type="button" class="btn btn-link text-decoration-none text-primary fw-semibold btn-sm d-inline-flex align-items-center gap-1"
              data-bs-toggle="collapse" data-bs-target="#collapseCriterios" aria-expanded="false" aria-controls="collapseCriterios" id="btnToggleCriterios">
        <span>Ver criterios</span>
        <i class="bi bi-chevron-down" id="chevronCriterios"></i>
      </button>
    </div>
  </div>

  <div class="collapse border-top resultado-normativa__collapse" id="collapseCriterios">
    <div class="p-3 bg-light bg-opacity-50">
      <div class="row g-2 mb-2">
        <div class="col-12 col-md-4">
          <div class="p-2 rounded bg-white border small">
            <span class="badge resultado-literal-badge resultado-literal-badge--a me-1">Literal A</span>
            <strong>Sobresaliente:</strong> Alcanzó todas las competencias del grado.
            <div class="text-success fw-semibold mt-1">Promovido al grado superior</div>
          </div>
        </div>
        <div class="col-12 col-md-4">
          <div class="p-2 rounded bg-white border small">
            <span class="badge resultado-literal-badge resultado-literal-badge--b me-1">Literal B</span>
            <strong>Muy Bueno:</strong> Alcanzó las competencias con buen desempeño.
            <div class="text-success fw-semibold mt-1">Promovido al grado superior</div>
          </div>
        </div>
        <div class="col-12 col-md-4">
          <div class="p-2 rounded bg-white border small">
            <span class="badge resultado-literal-badge resultado-literal-badge--c me-1">Literal C</span>
            <strong>Bueno:</strong> Alcanzó la mayoría de las competencias esenciales.
            <div class="text-success fw-semibold mt-1">Promovido al grado superior</div>
          </div>
        </div>
        <div class="col-12 col-md-6">
          <div class="p-2 rounded bg-white border small">
            <span class="badge resultado-literal-badge resultado-literal-badge--d me-1">Literal D</span>
            <strong>Regular:</strong> Competencias mínimas; requiere acompañamiento.
            <div class="text-success fw-semibold mt-1">Promovido al grado superior</div>
          </div>
        </div>
        <div class="col-12 col-md-6">
          <div class="p-2 rounded bg-white border small">
            <span class="badge resultado-literal-badge resultado-literal-badge--e me-1">Literal E</span>
            <strong>No Promovido:</strong> No alcanzó competencias esenciales.
            <div class="text-danger fw-semibold mt-1">Permanece en el mismo grado</div>
          </div>
        </div>
      </div>
      <div class="text-end">
        <button type="button" class="btn btn-outline-primary btn-sm py-1 px-3" data-bs-toggle="modal" data-bs-target="#modalGuiaNormativa">
          <i class="bi bi-info-circle me-1"></i> Ver marco legal y reglas completas
        </button>
      </div>
    </div>
  </div>
</div>
