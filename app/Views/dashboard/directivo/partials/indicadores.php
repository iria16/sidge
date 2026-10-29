<?php
declare(strict_types=1);

/**
 * Partial: dashboard/directivo/partials/indicadores.php
 * Tres indicadores clave consolidados para la Dirección escolar (solo totales).
 *
 * Variables esperadas:
 * @var array $metricasMatricula
 * @var array $metricasCapacidad
 * @var array $metricasPersonal
 */

$totalMatricula = (int) ($metricasMatricula['total_estudiantes'] ?? 0);
$ocupacionPct   = (int) ($metricasCapacidad['porcentaje_ocupacion'] ?? 0);
$cuposLibres    = (int) ($metricasCapacidad['cupos_disponibles'] ?? 0);
$cuposTotales   = (int) ($metricasCapacidad['cupos_totales'] ?? 0);
$totalPersonal  = (int) ($metricasPersonal['empleados_activos'] ?? 0);
?>
<div class="row g-3 mb-4">
  <!-- Card 1: Matrícula Cursando (Solo total) -->
  <div class="col-sm-6 col-lg-4">
    <?php
    $statLabel     = 'Matrícula cursando';
    $statValue     = number_format($totalMatricula, 0, ',', '.') . ' estud.';
    $statIcon      = 'bi-people';
    $statTone      = 'primary';
    $statVariant   = 'default';
    $statTrend     = $totalMatricula > 0 ? 'Activa' : '';
    $statTrendType = 'neutral';
    $statMeta      = 'Población estudiantil del período';
    $statProgress  = null;
    $statHref      = BASE_URL . 'estadistica/index?vista=matricula';
    include __DIR__ . '/../../../components/stat-card.php';
    ?>
  </div>

  <!-- Card 2: Capacidad y Ocupación (Solo % y cupos libres, sin total de alumnos) -->
  <div class="col-sm-6 col-lg-4">
    <?php
    $statLabel     = 'Capacidad escolar';
    $statValue     = $ocupacionPct . '% ocupación';
    $statIcon      = 'bi-pie-chart';
    $statTone      = 'info';
    $statVariant   = 'default';
    $statTrend     = $cuposLibres . ' libres';
    $statTrendType = 'neutral';
    $statMeta      = $cuposTotales . ' cupos totales configurados';
    $statProgress  = $ocupacionPct;
    $statHref      = BASE_URL . 'estadistica/index?vista=cobertura';
    include __DIR__ . '/../../../components/stat-card.php';
    ?>
  </div>

  <!-- Card 3: Personal Activo (Solo total en nómina) -->
  <div class="col-sm-6 col-lg-4">
    <?php
    $statLabel     = 'Personal activo';
    $statValue     = number_format($totalPersonal, 0, ',', '.') . ' empleados';
    $statIcon      = 'bi-person-badge';
    $statTone      = 'success';
    $statVariant   = 'default';
    $statTrend     = 'En plantilla';
    $statTrendType = 'neutral';
    $statMeta      = 'Cuerpo docente, administrativo y auxiliar';
    $statProgress  = null;
    $statHref      = BASE_URL . 'empleado/index';
    include __DIR__ . '/../../../components/stat-card.php';
    ?>
  </div>
</div>
