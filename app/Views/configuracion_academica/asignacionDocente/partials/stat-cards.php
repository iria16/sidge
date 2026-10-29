<?php
declare(strict_types=1);

/**
 * Partial: asignacionDocente/partials/stat-cards.php
 * Indicadores de aulas asignadas, pendientes y docentes activos.
 *
 * @var array{total: int, asignadas: int, pendientes: int, docentes: int} $stats
 */
?>
<!-- ---------- Stat cards ---------- -->
<div class="row g-3 mb-4">
  <?php
    $statCards = [
        [
            'label'     => 'Aulas con docente',
            'value'     => (int) $stats['asignadas'] . ' / ' . (int) $stats['total'],
            'icon'      => 'bi-mortarboard',
            'tone'      => 'primary',
            'trend'     => 'Asignadas',
            'trendType' => 'up',
        ],
        [
            'label'     => 'Aulas por asignar',
            'value'     => (int) $stats['pendientes'],
            'icon'      => 'bi-person-exclamation',
            'tone'      => 'danger',
            'trend'     => 'Pendientes',
            'trendType' => 'down',
        ],
        [
            'label'     => 'Docentes activos',
            'value'     => (int) $stats['docentes'],
            'icon'      => 'bi-people',
            'tone'      => 'light',
            'variant'   => 'highlighted',
            'trend'     => 'En plantilla',
            'trendType' => 'neutral',
        ],
    ];
    foreach ($statCards as $card):
  ?>
    <div class="col-sm-6 col-lg-4">
      <?php
        $statLabel     = $card['label'];
        $statValue     = $card['value'];
        $statIcon      = $card['icon'];
        $statTone      = $card['tone'];
        $statTrend     = $card['trend'];
        $statTrendType = $card['trendType'];
        $statVariant   = $card['variant'] ?? null;
        include __DIR__ . '/../../components/stat-card.php';
      ?>
    </div>
  <?php endforeach; ?>
</div>
