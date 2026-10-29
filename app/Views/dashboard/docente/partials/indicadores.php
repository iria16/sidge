<?php
declare(strict_types=1);

/**
 * Partial: dashboard/docente/partials/indicadores.php
 * Tres indicadores clave consolidados para el docente de aula (totales pedagógicos y operativos).
 *
 * Variables esperadas:
 * @var array $resumen
 * @var array $literales
 * @var array $cumpleanos
 */

$totalMatricula  = (int) ($resumen['total'] ?? 0);
$conTelefono     = (int) ($resumen['con_telefono'] ?? 0);
$sinTelefono     = (int) ($resumen['sin_telefono'] ?? max(0, $totalMatricula - $conTelefono));
$pctTelefono     = (int) ($resumen['pct_telefono'] ?? ($totalMatricula > 0 ? round(($conTelefono / $totalMatricula) * 100) : 0));
$cumpleanosMes   = count($cumpleanos['cumpleanos_mes'] ?? []);
$nombreMesActual = (string) ($cumpleanos['mes_nombre'] ?? 'este mes');
?>
<div class="row g-3 mb-4">
  <!-- Card 1: Matrícula del Aula -->
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
    $statHref      = BASE_URL . 'docente/nomina';
    include __DIR__ . '/../../../components/stat-card.php';
    ?>
  </div>

  <!-- Card 2: Directorio de Contactos (Sustituye a calificaciones/edad promedio) -->
  <div class="col-sm-6 col-lg-4">
    <?php
    $statLabel     = 'Directorio de contactos';
    $statValue     = $conTelefono . ' de ' . $totalMatricula . ' contactos';
    $statIcon      = 'bi-person-lines-fill';
    $statTone      = $totalMatricula > 0 && $sinTelefono === 0 ? 'success' : ($conTelefono > 0 ? 'info' : 'warning');
    $statVariant   = 'default';
    $statTrend     = $pctTelefono . '% ubicables';
    $statTrendType = $pctTelefono >= 100 ? 'up' : 'neutral';
    $statMeta      = $sinTelefono > 0
        ? $sinTelefono . ' estudiante' . ($sinTelefono === 1 ? '' : 's') . ' sin teléfono de contacto'
        : ($totalMatricula > 0 ? 'Directorio de representantes al día' : 'Sin matrícula');
    $statProgress  = $totalMatricula > 0 ? $pctTelefono : null;
    $statHref      = BASE_URL . 'docente/nomina?vista=contacto';
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
    $statHref      = BASE_URL . 'docente/nomina?vista=cumpleaneros';
    include __DIR__ . '/../../../components/stat-card.php';
    ?>
  </div>
</div>
