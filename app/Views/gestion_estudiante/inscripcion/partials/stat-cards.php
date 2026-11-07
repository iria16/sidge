<?php
declare(strict_types=1);

/**
 * Partial: inscripcion/partials/stat-cards.php
 * Indicadores de ingresos, cupos e inscripciones del mes.
 *
 * @var array{inscritosAnio: int, trendInscritos: int, cuposDisponibles: int, inscripcionesMes: int} $stats
 * @var int $trendInscritos
 */
?>
<!-- ---------- Stat cards ---------- -->
<div class="row g-3 mb-4">
  <?php
    $statCards = [
        [
            /* Dice "ingresos" y no "inscritos" a propósito: la inscripción es un
 registro único por alumno, así que este número cuenta a quién le
 tocó pasar por aquí este año, no cuántos alumnos hay en el
 plantel. Con "inscritos" se leía como el total de alumnos, que es
 un número distinto y mucho más grande.*/
            'label'     => 'Ingresos de este año escolar',
            'value'     => number_format((int) $stats['inscritosAnio'], 0, ',', '.'),
            'icon'      => 'bi-people-fill',
            'tone'      => 'info',
            'trend'     => $trendInscritos > 0 ? '+' . $trendInscritos . '%' : '',
            'trendType' => 'up',
        ],
        [
            'label'     => 'Cupos disponibles',
            'value'     => number_format((int) $stats['cuposDisponibles'], 0, ',', '.'),
            'icon'      => 'bi-check-circle-fill',
            'tone'      => 'success',
            'trend'     => 'Disponibles',
            'trendType' => 'neutral',
        ],
        [
            'label'     => 'Inscripciones este mes',
            'value'     => number_format((int) $stats['inscripcionesMes'], 0, ',', '.'),
            'icon'      => 'bi-calendar-check-fill',
            'tone'      => 'primary',
            'trend'     => 'Del mes en curso',
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
        include __DIR__ . '/../../../components/stat-card.php';
      ?>
    </div>
  <?php endforeach; ?>
</div>
