<?php
declare(strict_types=1);

/**
 * Partial: usuarios/recuperacion/partials/stat-cards.php
 * Indicadores de solicitudes pendientes, aprobadas y rechazadas.
 *
 * @var array{total: int, pendientes: int, aprobadas: int, rechazadas: int} $stats
 */
?>
<!-- ---------- Stat cards ---------- -->
<?php
  // Los porcentajes son proporciones del total, no tendencias en el tiempo,
  // así que las tres tarjetas usan 'neutral' (ver misma discusión en
  // usuarios/cuentas/index.php sobre statTrendType).
  $statsConfig = [
      [
          'label' => 'Solicitudes Pendientes',
          'value' => (int) $stats['pendientes'],
          'icon'  => 'bi-clock-history',
          'tone'  => 'warning',
          'trend' => ($stats['total'] > 0 ? round(($stats['pendientes'] / $stats['total']) * 100) : 0) . '% del total',
          'trendType' => 'neutral',
      ],
      [
          'label' => 'Solicitudes Aprobadas',
          'value' => (int) $stats['aprobadas'],
          'icon'  => 'bi-shield-check',
          'tone'  => 'success',
          'trend' => ($stats['total'] > 0 ? round(($stats['aprobadas'] / $stats['total']) * 100) : 0) . '% del total',
          'trendType' => 'neutral',
      ],
      [
          'label' => 'Solicitudes Rechazadas',
          'value' => (int) $stats['rechazadas'],
          'icon'  => 'bi-slash-circle',
          'tone'  => 'danger',
          'trend' => ($stats['total'] > 0 ? round(($stats['rechazadas'] / $stats['total']) * 100) : 0) . '% del total',
          'trendType' => 'neutral',
      ],
  ];
?>
<div class="row g-3 mb-3">
  <?php foreach ($statsConfig as $cfg): ?>
    <div class="col-sm-6 col-lg-4">
      <?php
        $statLabel     = $cfg['label'];
        $statValue     = $cfg['value'];
        $statIcon      = $cfg['icon'];
        $statTone      = $cfg['tone'];
        $statTrend     = $cfg['trend'];
        $statTrendType = $cfg['trendType'];
        include __DIR__ . '/../../../components/stat-card.php';
      ?>
    </div>
  <?php endforeach; ?>
</div>
