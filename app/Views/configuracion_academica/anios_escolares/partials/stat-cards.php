<?php
declare(strict_types=1);

/**
 * Partial: anios_escolares/partials/stat-cards.php
 * Indicadores de períodos activos, planificados y cerrados.
 *
 * @var array{actual: string, planificados: int, cerrados: int} $stats
 */
?>
<!-- ---------- Stat cards ---------- -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-lg-4">
    <?php
      $statLabel     = 'Período activo';
      $statValue     = $stats['actual'];
      $statIcon      = 'bi-check-circle';
      $statTone      = 'success';
      $statTrend     = 'Activo';
      $statTrendType = 'neutral';
      include __DIR__ . '/../../../components/stat-card.php';
    ?>
  </div>
  <div class="col-sm-6 col-lg-4">
    <?php
      $statLabel     = 'Planificados';
      $statValue     = (int) $stats['planificados'];
      $statIcon      = 'bi-calendar2-week';
      $statTone      = 'warning';
      $statTrend     = 'Por iniciar';
      $statTrendType = 'neutral';
      include __DIR__ . '/../../../components/stat-card.php';
    ?>
  </div>
  <div class="col-sm-6 col-lg-4">
    <?php
      $statLabel     = 'Cerrados';
      $statValue     = (int) $stats['cerrados'];
      $statIcon      = 'bi-archive';
      $statTone      = 'light';
      $statTrend     = 'Históricos';
      $statTrendType = 'neutral';
      include __DIR__ . '/../../../components/stat-card.php';
    ?>
  </div>
</div>
