<?php
declare(strict_types=1);

/**
 * Partial: estructuraAcademica/partials/stat-cards.php
 * Indicadores de registros, secciones y cupos configurados.
 *
 * @var array{total: int, grados: int, secciones: int, capacidad: int} $stats
 */
?>
<!-- ---------- Stat cards ---------- -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-lg-4">
    <?php
      $statLabel     = 'Grados y secciones';
      $statValue     = (int) $stats['total'];
      $statIcon      = 'bi-diagram-3';
      $statTone      = 'primary';
      $statTrend     = 'Configurados';
      $statTrendType = 'neutral';
      include __DIR__ . '/../../../components/stat-card.php';
    ?>
  </div>
  <div class="col-sm-6 col-lg-4">
    <?php
      $statLabel     = 'Secciones disponibles';
      $statValue     = (int) $stats['secciones'];
      $statIcon      = 'bi-collection';
      $statTone      = 'success';
      $statTrend     = 'Activos';
      $statTrendType = 'neutral';
      include __DIR__ . '/../../../components/stat-card.php';
    ?>
  </div>
  <div class="col-sm-6 col-lg-4">
    <?php
      $statLabel     = 'Cupos configurados';
      $statValue     = (int) $stats['capacidad'];
      $statIcon      = 'bi-people';
      $statTone      = 'warning';
      $statTrend     = 'Capacidad';
      $statTrendType = 'neutral';
      include __DIR__ . '/../../../components/stat-card.php';
    ?>
  </div>
</div>
