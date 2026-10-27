<?php

declare(strict_types=1);

/**
 * Partial: dashboard/secretaria/partials/indicadores.php
 *
 * @var callable $formatear
 * @var array|null $anioActivo
 * @var array|null $estadisticasEstudiantes
 * @var array|null $estadisticasInscripciones
 * @var array|null $estadisticasRatificacion
 * @var int|null $porRatificar
 * @var int|null $cuposDisponibles
 * @var string $metaContexto
 */
?>
<section aria-labelledby="headingIndicadores" class="mb-4">
  <h2 id="headingIndicadores" class="visually-hidden">Indicadores de Secretaría</h2>
  <div class="row g-3">
    <div class="col-12 col-sm-6 col-xl-3">
      <?php
      $statLabel = 'Estudiantes activos';
      $statValue = $formatear($estadisticasEstudiantes, 'activos');
      $statIcon = 'bi-people';
      $statTone = 'primary';
      $statVariant = '';
      $statMeta = $metaContexto;
      $statHref = BASE_URL . 'estudiantes/index';
      include __DIR__ . '/../../../components/stat-card.php';
      ?>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <?php
      $statLabel = 'Nuevos ingresos';
      $statValue = $formatear($estadisticasInscripciones, 'inscritosAnio');
      $statIcon = 'bi-person-plus';
      $statTone = 'info';
      $statVariant = '';
      $statMeta = $anioActivo === null
          ? 'Sin año escolar activo'
          : 'Inscritos en el período actual';
      $statHref = BASE_URL . 'inscripciones/index';
      include __DIR__ . '/../../../components/stat-card.php';
      ?>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <?php
      $statLabel = 'Por ratificar';
      $statValue = $formatear($estadisticasRatificacion, 'porRatificar');
      $statIcon = 'bi-arrow-repeat';
      $statTone = $porRatificar === null ? 'info' : ((int) $porRatificar > 0 ? 'warning' : 'success');
      $statVariant = $porRatificar !== null && (int) $porRatificar > 0 ? 'alert' : '';
      $statMeta = $anioActivo === null
          ? 'Sin año escolar activo'
          : ($estadisticasRatificacion === null
              ? 'No disponible'
              : $formatear($estadisticasRatificacion, 'yaRatificados') . ' ratificados (' . (int) ($estadisticasRatificacion['avance'] ?? 0) . '%)');
      $statHref = BASE_URL . 'ratificacion/index';
      include __DIR__ . '/../../../components/stat-card.php';
      ?>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <?php
      $statLabel = 'Cupos disponibles';
      $statValue = $formatear($estadisticasInscripciones, 'cuposDisponibles');
      $statIcon = 'bi-check-circle';
      $statTone = $cuposDisponibles === null ? 'info' : ((int) $cuposDisponibles > 0 ? 'success' : 'warning');
      $statVariant = '';
      $statMeta = $anioActivo === null
          ? 'Sin año escolar activo'
          : ($cuposDisponibles === null ? 'No disponible' : 'Disponibilidad en su turno');
      $statHref = BASE_URL . 'inscripciones/index';
      include __DIR__ . '/../../../components/stat-card.php';
      ?>
    </div>
  </div>
</section>
