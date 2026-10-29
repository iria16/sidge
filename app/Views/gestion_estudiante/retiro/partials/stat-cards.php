<?php
declare(strict_types=1);

/**
 * Partial: retiro/partials/stat-cards.php
 * Indicadores de solicitudes de retiro.
 *
 * @var array{pendientes: int, aprobados: int, rechazados: int} $stats
 */
?>
<!-- ---------- Stat cards ---------- -->
<div class="row g-3 mb-4">
  <?php
    $statCards = [
        ['label' => 'Pendientes', 'value' => (int) $stats['pendientes'], 'icon' => 'bi-hourglass-split',    'tone' => 'warning'],
        ['label' => 'Aprobados',  'value' => (int) $stats['aprobados'],  'icon' => 'bi-check-circle-fill',  'tone' => 'success'],
        ['label' => 'Rechazados', 'value' => (int) $stats['rechazados'], 'icon' => 'bi-x-circle-fill',      'tone' => 'danger'],
    ];
    foreach ($statCards as $card):
  ?>
    <div class="col-sm-6 col-lg-4">
      <?php
        $statLabel = $card['label'];
        $statValue = $card['value'];
        $statIcon  = $card['icon'];
        $statTone  = $card['tone'];
        include __DIR__ . '/../../../components/stat-card.php';
      ?>
    </div>
  <?php endforeach; ?>
</div>
