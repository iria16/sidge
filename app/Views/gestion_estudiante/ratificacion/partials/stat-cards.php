<?php
declare(strict_types=1);

/**
 * Partial: ratificacion/partials/stat-cards.php
 * Indicadores del proceso de ratificación.
 *
 * @var array{porRatificar: int, yaRatificados: int, avance: int} $stats
 */
?>
<!-- ---------- Stat cards ---------- -->
<div class="row g-3 mb-4">
  <?php
    /* Esta pantalla es de ratificación, y el número que importa es cuántos de los
 que ya estaban en el plantel todavía no tienen matrícula para el año que
 viene. Los que hay que INSCRIBIR no se cuentan aquí: no son un pendiente de
 esta pantalla, se gestionan en el módulo de Inscripción, y mezclarlos hacía
 que el número de "por ratificar" fuera más grande de lo que realmente era y
 que en el cierre de año no se supiera con qué lista trabajar.*/
    $statCards = [
        [
            'label'     => 'Por ratificar',
            'value'     => number_format((int) $stats['porRatificar'], 0, ',', '.'),
            'icon'      => 'bi-clipboard-check',
            'tone'      => 'warning',
            'trend'     => 'Ya inscritos',
            'trendType' => 'neutral',
        ],
        [
            'label'     => 'Ya ratificados',
            'value'     => (int) $stats['yaRatificados'],
            'icon'      => 'bi-check-circle-fill',
            'tone'      => 'success',
            'trend'     => 'Completados',
            'trendType' => 'up',
        ],
        [
            'label'     => 'Avance del proceso',
            'value'     => (int) $stats['avance'] . '%',
            'icon'      => 'bi-graph-up-arrow',
            'tone'      => 'primary',
            'trend'     => 'De los ratificables',
            'trendType' => 'neutral',
            'progress'  => (int) $stats['avance'],
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
        $statProgress  = $card['progress'] ?? null;
        include __DIR__ . '/../../../components/stat-card.php';
      ?>
    </div>
  <?php endforeach; ?>
</div>
