<?php
declare(strict_types=1);

/**
 * Partial: reingreso/partials/stat-cards.php
 * Indicadores del módulo de reingreso escolar.
 *
 * @var array{reingresos_activos: int, retirados_elegibles: int} $stats
 */
$stats = $stats ?? ['reingresos_activos' => 0, 'retirados_elegibles' => 0];
?>
<!-- ---------- Stat cards ---------- -->
<div class="row g-3 mb-4">
  <?php
    $statCards = [
        ['label' => 'Reingresos en Período Activo',   'value' => (int) ($stats['reingresos_activos'] ?? 0),  'icon' => 'bi-arrow-counterclockwise', 'tone' => 'primary'],
        ['label' => 'Estudiantes Retirados Elegibles', 'value' => (int) ($stats['retirados_elegibles'] ?? 0), 'icon' => 'bi-person-check',             'tone' => 'success'],
    ];
    foreach ($statCards as $card):
  ?>
    <div class="col-sm-6 col-lg-6">
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
