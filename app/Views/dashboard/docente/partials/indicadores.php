<?php
declare(strict_types=1);

/**
 * Partial: dashboard/docente/partials/indicadores.php
 * Tres indicadores clave consolidados para el docente de aula (solo totales agregados).
 *
 * Variables esperadas:
 * @var array $resumen
 * @var array $cumpleanos
 */

$totalMatricula  = (int) ($resumen['total'] ?? 0);
$edadPromedio    = (float) ($resumen['edad_promedio'] ?? 0.0);
$cumpleanosMes   = count($cumpleanos['cumpleanos_mes'] ?? []);
$nombreMesActual = (string) ($cumpleanos['mes_nombre'] ?? 'este mes');
?>
<div class="row g-3 mb-4">
  <!-- Card 1: Matrícula del Aula (Solo total) -->
  <div class="col-sm-6 col-lg-4">
    <?php
    $statLabel     = 'Matrícula del aula';
    $statValue     = number_format($totalMatricula, 0, ',', '.') . ' estud.';
    $statIcon      = 'bi-people';
    $statTone      = 'primary';
    $statVariant   = 'default';
    $statTrend     = $totalMatricula > 0 ? 'Activa' : '';
    $statTrendType = 'neutral';
    $statMeta      = 'Estudiantes cursando en tu sección';
    $statProgress  = null;
    $statHref      = '';
    include __DIR__ . '/../../../components/stat-card.php';
    ?>
  </div>

  <!-- Card 2: Edad Promedio (Solo total en años) -->
  <div class="col-sm-6 col-lg-4">
    <?php
    $statLabel     = 'Edad promedio';
    $statValue     = number_format($edadPromedio, 1, ',', '.') . ' años';
    $statIcon      = 'bi-calendar3';
    $statTone      = 'info';
    $statVariant   = 'default';
    $statTrend     = 'Grupo';
    $statTrendType = 'neutral';
    $statMeta      = 'Promedio de edad del grupo escolar';
    $statProgress  = null;
    $statHref      = '';
    include __DIR__ . '/../../../components/stat-card.php';
    ?>
  </div>

  <!-- Card 3: Cumpleaños del Mes -->
  <div class="col-sm-6 col-lg-4">
    <?php
    $statLabel     = 'Cumpleaños en ' . $nombreMesActual;
    $statValue     = $cumpleanosMes . ($cumpleanosMes === 1 ? ' alumno' : ' alumnos');
    $statIcon      = 'bi-cake2';
    $statTone      = 'success';
    $statVariant   = 'default';
    $statTrend     = $cumpleanosMes > 0 ? 'Celebración' : 'Al día';
    $statTrendType = 'neutral';
    $statMeta      = 'Clima escolar y fechas especiales del aula';
    $statProgress  = null;
    $statHref      = '';
    include __DIR__ . '/../../../components/stat-card.php';
    ?>
  </div>
</div>
