<?php
declare(strict_types=1);

/**
 * Partial: dashboard/admin/partials/indicadores.php
 * Tres indicadores clave consolidados para Administración (solo totales agregados).
 *
 * Variables esperadas:
 * @var array $indicadores
 * @var array $actividad
 * @var array $coberturaPersonal
 */

$totalUsuarios    = (int) ($indicadores['total_usuarios'] ?? 0);
$totalOps         = (int) ($actividad['total'] ?? 0);
$picoOps          = (int) ($actividad['pico'] ?? 0);
$porcentajeCob    = (int) ($coberturaPersonal['porcentaje'] ?? 0);
$empleadosConCta  = (int) ($coberturaPersonal['con_cuenta'] ?? 0);
$totalEmpleados   = (int) ($coberturaPersonal['total_empleados'] ?? 0);
?>
<div class="row g-3 mb-4">
  <!-- Card 1: Cuentas registradas (Solo total) -->
  <div class="col-sm-6 col-lg-4">
    <?php
    $statLabel     = 'Cuentas de usuario';
    $statValue     = number_format($totalUsuarios, 0, ',', '.') . ' cuentas';
    $statIcon      = 'bi-people';
    $statTone      = 'primary';
    $statVariant   = 'default';
    $statTrend     = $totalUsuarios > 0 ? 'Registradas' : '';
    $statTrendType = 'neutral';
    $statMeta      = 'Total de usuarios habilitados en plataforma';
    $statProgress  = null;
    $statHref      = '';
    include __DIR__ . '/../../../components/stat-card.php';
    ?>
  </div>

  <!-- Card 2: Actividad mensual de auditoría -->
  <div class="col-sm-6 col-lg-4">
    <?php
    $statLabel     = 'Operaciones en bitácora';
    $statValue     = number_format($totalOps, 0, ',', '.') . ' ops';
    $statIcon      = 'bi-activity';
    $statTone      = 'info';
    $statVariant   = 'default';
    $statTrend     = '30 días';
    $statTrendType = 'neutral';
    $statMeta      = 'Pico máximo: ' . number_format($picoOps, 0, ',', '.') . ' operaciones/día';
    $statProgress  = null;
    $statHref      = '';
    include __DIR__ . '/../../../components/stat-card.php';
    ?>
  </div>

  <!-- Card 3: Cobertura de cuentas en el personal institucional -->
  <div class="col-sm-6 col-lg-4">
    <?php
    $statLabel     = 'Personal con cuenta';
    $statValue     = $porcentajeCob . '% cubierto';
    $statIcon      = 'bi-person-check';
    $statTone      = 'success';
    $statVariant   = 'default';
    $statTrend     = $empleadosConCta . ' de ' . $totalEmpleados;
    $statTrendType = 'neutral';
    $statMeta      = 'Personal activo con acceso al sistema';
    $statProgress  = $porcentajeCob;
    $statHref      = '';
    include __DIR__ . '/../../../components/stat-card.php';
    ?>
  </div>
</div>
