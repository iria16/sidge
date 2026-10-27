<?php

declare(strict_types=1);

/**
 * Partial: dashboard/secretaria/partials/accesos-rapidos.php
 *
 * @var array|null $anioActivo
 */
?>
<section class="content-card" aria-labelledby="headingAccesos">
  <div class="content-card__header">
    <h2 id="headingAccesos" class="content-card__title">
      <i class="bi bi-lightning-charge me-1 text-secondary" aria-hidden="true"></i>
      Accesos rápidos
    </h2>
  </div>
  <div class="content-card__body">
    <div class="d-flex flex-wrap gap-2">
      <?php if ($anioActivo !== null): ?>
        <a href="<?= BASE_URL ?>inscripciones/index" class="btn btn-primary">
          <i class="bi bi-person-plus me-1" aria-hidden="true"></i> Nueva inscripción
        </a>
        <a href="<?= BASE_URL ?>ratificacion/index" class="btn btn-outline-primary">
          <i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i> Ratificar estudiante
        </a>
        <a href="<?= BASE_URL ?>withdrawal/index" class="btn btn-outline-primary">
          <i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i> Registrar retiro
        </a>
      <?php else: ?>
        <p class="text-support small mb-0">
          Las acciones de matrícula se habilitan cuando el Directivo abre el año escolar.
        </p>
      <?php endif; ?>
      <a href="<?= BASE_URL ?>estudiantes/index" class="btn btn-outline-secondary">
        <i class="bi bi-search me-1" aria-hidden="true"></i> Consultar estudiante
      </a>
      <a href="<?= BASE_URL ?>constancias/index" class="btn btn-outline-secondary">
        <i class="bi bi-file-earmark-text me-1" aria-hidden="true"></i> Emitir constancia
      </a>
    </div>
  </div>
</section>
