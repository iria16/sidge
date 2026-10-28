<?php
/**
 * Partial: tarjetas de resumen de la bandeja de secciones.
 *
 * @var array $resumen Totales calculados por ResultadoAcademicoVista.
 */
?>
<div class="row g-3 mb-4">
  <div class="col-12 col-sm-6 col-xl-3">
    <?php
    $statLabel     = 'Estudiantes en nómina';
    $statValue     = number_format($resumen['total_estudiantes'], 0, ',', '.');
    $statIcon      = 'bi-people-fill';
    $statTone      = 'primary';
    $statTrend     = $resumen['total_secciones'] . ' sección' . ($resumen['total_secciones'] === 1 ? '' : 'es');
    $statTrendType = 'neutral';
    include __DIR__ . '/../../components/stat-card.php';
    ?>
  </div>
  <div class="col-12 col-sm-6 col-xl-3">
    <?php
    $statLabel     = 'Literales cargados';
    $statValue     = number_format($resumen['total_cargados'], 0, ',', '.');
    $statIcon      = 'bi-check2-circle';
    $statTone      = 'success';
    $statTrend     = $resumen['porcentaje_cargado'] . '% de la nómina';
    $statTrendType = $resumen['porcentaje_cargado'] === 100 ? 'up' : 'neutral';
    $statProgress  = $resumen['porcentaje_cargado'];
    include __DIR__ . '/../../components/stat-card.php';
    ?>
  </div>
  <div class="col-12 col-sm-6 col-xl-3">
    <?php
    $statLabel     = 'Sin literal';
    $statValue     = number_format($resumen['total_pendientes'], 0, ',', '.');
    $statIcon      = 'bi-pencil-square';
    $statTone      = $resumen['total_pendientes'] === 0 ? 'success' : 'warning';
    $statTrend     = $resumen['total_pendientes'] === 0 ? 'Carga completa' : 'Bloquean el cierre del año';
    $statTrendType = $resumen['total_pendientes'] === 0 ? 'up' : 'neutral';
    include __DIR__ . '/../../components/stat-card.php';
    ?>
  </div>
  <div class="col-12 col-sm-6 col-xl-3">
    <?php
    $statLabel     = 'Promovidos';
    $statValue     = number_format($resumen['total_promovidos'], 0, ',', '.');
    $statIcon      = 'bi-award-fill';
    $statTone      = 'success';
    $statTrend     = $resumen['total_no_promovidos'] . ' no promovido' . ($resumen['total_no_promovidos'] === 1 ? '' : 's');
    $statTrendType = 'neutral';
    include __DIR__ . '/../../components/stat-card.php';
    ?>
  </div>
</div>
