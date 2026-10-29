<?php
declare(strict_types=1);

/**
 * Partial: dashboard/secretaria/partials/indicadores.php
 * Tres indicadores clave consolidados para Secretaría (solo totales agregados).
 *
 * Variables esperadas:
 * @var array|null  $anioActivo
 * @var string|null $turnoNombre
 * @var array|null  $estadisticasEstudiantes
 * @var array|null  $estadisticasInscripciones
 * @var array|null  $estadisticasRatificacion
 */

$activos            = (int) ($estadisticasEstudiantes['activos'] ?? 0);
$nuevosIngresos     = (int) ($estadisticasInscripciones['inscritosAnio'] ?? 0);
$porRatificar       = (int) ($estadisticasRatificacion['porRatificar'] ?? 0);
$yaRatificados      = (int) ($estadisticasRatificacion['yaRatificados'] ?? 0);
$avanceRatificacion = (int) ($estadisticasRatificacion['avance'] ?? 0);
$textoTurno         = $turnoNombre !== null && $turnoNombre !== '' ? 'Turno ' . $turnoNombre : 'turno asignado';
?>
<div class="row g-3 mb-4">
  <!-- Card 1: Matrícula Activa en Turno (Solo total) -->
  <div class="col-sm-6 col-lg-4">
    <?php
    $statLabel     = 'Matrícula activa en turno';
    $statValue     = number_format($activos, 0, ',', '.') . ' estud.';
    $statIcon      = 'bi-people';
    $statTone      = 'primary';
    $statVariant   = 'default';
    $statTrend     = $activos > 0 ? 'Activa' : '';
    $statTrendType = 'neutral';
    $statMeta      = 'Estudiantes cursando en ' . $textoTurno;
    $statProgress  = null;
    $statHref      = '';
    include __DIR__ . '/../../../components/stat-card.php';
    ?>
  </div>

  <!-- Card 2: Nuevos Ingresos Formalizados (Solo total) -->
  <div class="col-sm-6 col-lg-4">
    <?php
    $statLabel     = 'Nuevos ingresos formalizados';
    $statValue     = number_format($nuevosIngresos, 0, ',', '.') . ' estud.';
    $statIcon      = 'bi-person-plus';
    $statTone      = 'info';
    $statVariant   = 'default';
    $statTrend     = $nuevosIngresos > 0 ? 'Inscritos' : '';
    $statTrendType = 'neutral';
    $statMeta      = 'Inscripciones de nuevo ingreso registradas en el período';
    $statProgress  = null;
    $statHref      = '';
    include __DIR__ . '/../../../components/stat-card.php';
    ?>
  </div>

  <!-- Card 3: Por Ratificar (Continuidad escolar) -->
  <div class="col-sm-6 col-lg-4">
    <?php
    $statLabel     = 'Por ratificar';
    $statValue     = number_format($porRatificar, 0, ',', '.') . ' estud.';
    $statIcon      = 'bi-arrow-repeat';
    $statTone      = $porRatificar > 0 ? 'warning' : 'success';
    $statVariant   = $porRatificar > 0 ? 'alert' : 'default';
    $statTrend     = $porRatificar > 0 ? 'Pendiente' : 'Al día';
    $statTrendType = $porRatificar > 0 ? 'warning' : 'positive';
    $statMeta      = $porRatificar > 0
        ? number_format($yaRatificados, 0, ',', '.') . ' ratificados (' . $avanceRatificacion . '% avance)'
        : 'Continuidad escolar al día';
    $statProgress  = null;
    $statHref      = '';
    include __DIR__ . '/../../../components/stat-card.php';
    ?>
  </div>
</div>