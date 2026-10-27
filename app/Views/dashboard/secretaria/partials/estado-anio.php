<?php

declare(strict_types=1);

/**
 * Partial: dashboard/secretaria/partials/estado-anio.php
 *
 * @var callable $formatear
 * @var array|null $anioActivo
 * @var array|null $estadisticasEstudiantes
 * @var array|null $estadisticasInscripciones
 * @var array|null $estadisticasRatificacion
 * @var array|null $estadisticasEgresos
 */
$totalEgreso = $estadisticasEgresos !== null ? (int) ($estadisticasEgresos['total'] ?? 0) : null;
$avanceEgreso = $totalEgreso !== null && $totalEgreso > 0
    ? (int) round(((int) ($estadisticasEgresos['graduados'] ?? 0) / $totalEgreso) * 100)
    : 0;
?>
<section class="content-card h-100" aria-labelledby="headingEstadoAnio">
  <div class="content-card__header">
    <h2 id="headingEstadoAnio" class="content-card__title">
      <i class="bi bi-calendar3 me-1 text-secondary" aria-hidden="true"></i>
      Estado del año
    </h2>
    <?php if ($anioActivo !== null): ?>
      <span class="text-support small"><?= htmlspecialchars((string) $anioActivo['nombre']) ?></span>
    <?php endif; ?>
  </div>

  <?php if ($anioActivo === null): ?>
    <div class="content-card__empty">
      <i class="bi bi-calendar-x" aria-hidden="true"></i>
      <p>Sin año escolar activo no hay avance que mostrar.</p>
    </div>
  <?php else: ?>
    <div class="content-card__body">
      <div>
        <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
          <h3 class="h6 fw-bold text-primary mb-0">Inscripción</h3>
          <a href="<?= BASE_URL ?>inscripciones/index" class="link-sigde small">Ver módulo</a>
        </div>
        <?php if ($estadisticasInscripciones === null): ?>
          <p class="text-support small mb-0">No disponible.</p>
        <?php else: ?>
          <?php
          $trendInscripciones = (int) ($estadisticasInscripciones['trendInscritos'] ?? 0);
          $trendClase = $trendInscripciones > 0
              ? 'text-bg-success'
              : ($trendInscripciones < 0 ? 'text-bg-danger' : 'text-bg-secondary');
          $trendTexto = ($trendInscripciones > 0 ? '+' : '') . $trendInscripciones . '%';
          ?>
          <div class="row g-3">
            <div class="col-6 col-md-3">
              <p class="h4 fw-bold text-primary mb-0"><?= $formatear($estadisticasInscripciones, 'inscritosAnio') ?></p>
              <p class="text-support small mb-0">Inscripciones del año</p>
            </div>
            <div class="col-6 col-md-3">
              <p class="h4 fw-bold text-primary mb-0"><?= $formatear($estadisticasInscripciones, 'inscripcionesMes') ?></p>
              <p class="text-support small mb-0">
                Este mes
                <span class="badge <?= $trendClase ?>"><?= htmlspecialchars($trendTexto) ?></span>
              </p>
            </div>
            <div class="col-6 col-md-3">
              <p class="h4 fw-bold text-primary mb-0"><?= $formatear($estadisticasInscripciones, 'cuposDisponibles') ?></p>
              <p class="text-support small mb-0">Cupos disponibles</p>
            </div>
            <div class="col-6 col-md-3">
              <p class="h4 fw-bold text-primary mb-0"><?= $formatear($estadisticasEstudiantes, 'activos') ?></p>
              <p class="text-support small mb-0">Estudiantes activos</p>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <hr class="my-1">

      <div>
        <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
          <h3 class="h6 fw-bold text-primary mb-0">Ratificación</h3>
          <?php if ($estadisticasRatificacion === null): ?>
            <span class="text-support small">—</span>
          <?php else: ?>
            <span class="text-support small"><?= (int) ($estadisticasRatificacion['avance'] ?? 0) ?>%</span>
          <?php endif; ?>
        </div>
        <?php if ($estadisticasRatificacion === null): ?>
          <p class="text-support small mb-0">No disponible.</p>
        <?php else: ?>
          <progress class="secretaria-progress"
                    value="<?= (int) ($estadisticasRatificacion['avance'] ?? 0) ?>"
                    max="100"
                    aria-label="Avance de ratificación">
            <?= (int) ($estadisticasRatificacion['avance'] ?? 0) ?>%
          </progress>
          <p class="text-support small mt-1 mb-0">
            <?= $formatear($estadisticasRatificacion, 'yaRatificados') ?> ratificados ·
            <?= $formatear($estadisticasRatificacion, 'porRatificar') ?> por ratificar
          </p>
        <?php endif; ?>
      </div>

      <hr class="my-1">

      <div>
        <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
          <h3 class="h6 fw-bold text-primary mb-0">Egreso</h3>
          <?php if ($estadisticasEgresos === null): ?>
            <span class="text-support small">—</span>
          <?php else: ?>
            <span class="text-support small"><?= $avanceEgreso ?>%</span>
          <?php endif; ?>
        </div>
        <?php if ($estadisticasEgresos === null): ?>
          <p class="text-support small mb-0">No disponible.</p>
        <?php elseif ($totalEgreso === 0): ?>
          <progress class="secretaria-progress" value="0" max="100" aria-label="Avance de egreso">0%</progress>
          <p class="text-support small mt-1 mb-0">Sin alumnos de 6.º grado en el año activo.</p>
        <?php else: ?>
          <progress class="secretaria-progress"
                    value="<?= $avanceEgreso ?>"
                    max="100"
                    aria-label="Avance de egreso">
            <?= $avanceEgreso ?>%
          </progress>
          <p class="text-support small mt-1 mb-0">
            <?= $formatear($estadisticasEgresos, 'graduados') ?> graduados ·
            <?= $formatear($estadisticasEgresos, 'pendientes') ?> pendientes
          </p>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</section>
