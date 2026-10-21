<?php

declare(strict_types=1);

/**
 * Partial: dashboard/secretaria/partials/indicadores.php
 *
 * @var callable $formatear
 * @var array|null $anioActivo
 * @var array|null $estadisticasEstudiantes
 * @var array|null $estadisticasRatificacion
 * @var array|null $estadisticasRetiros
 * @var array|null $estadisticasEgresos
 * @var int|null $porRatificar
 * @var int|null $retirosPendientes
 * @var int|null $egresosPendientes
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
      $statLabel = 'Por ratificar';
      $statValue = $formatear($estadisticasRatificacion, 'porRatificar');
      $statIcon = 'bi-arrow-repeat';
      $statTone = $porRatificar === null ? 'info' : ((int) $porRatificar > 0 ? 'warning' : 'success');
      $statVariant = $porRatificar !== null && (int) $porRatificar > 0 ? 'alert' : '';
      $statMeta = $anioActivo === null
          ? 'Sin año escolar activo'
          : ($porRatificar === null ? 'No disponible' : 'Alumnos con matrícula previa');
      $statHref = BASE_URL . 'ratificacion/index';
      include __DIR__ . '/../../../components/stat-card.php';
      ?>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <?php
      $statLabel = 'Retiros pendientes';
      $statValue = $formatear($estadisticasRetiros, 'pendientes');
      $statIcon = 'bi-box-arrow-right';
      $statTone = $retirosPendientes === null ? 'info' : ((int) $retirosPendientes > 0 ? 'warning' : 'success');
      $statVariant = $retirosPendientes !== null && (int) $retirosPendientes > 0 ? 'alert' : '';
      $statMeta = $anioActivo === null
          ? 'Sin año escolar activo'
          : ($retirosPendientes === null ? 'No disponible' : 'Pendientes de decisión del Directivo');
      $statHref = BASE_URL . 'withdrawal/index';
      include __DIR__ . '/../../../components/stat-card.php';
      ?>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <?php
      $statLabel = 'Egresos pendientes';
      $statValue = $formatear($estadisticasEgresos, 'pendientes');
      $statIcon = 'bi-mortarboard';
      $statTone = $egresosPendientes === null ? 'info' : ((int) $egresosPendientes > 0 ? 'warning' : 'success');
      $statVariant = $egresosPendientes !== null && (int) $egresosPendientes > 0 ? 'alert' : '';
      $statMeta = $anioActivo === null
          ? 'Sin año escolar activo'
          : ($egresosPendientes === null ? 'No disponible' : 'Alumnos de 6.º sin graduar');
      $statHref = BASE_URL . 'egresos/index';
      include __DIR__ . '/../../../components/stat-card.php';
      ?>
    </div>
  </div>
</section>
