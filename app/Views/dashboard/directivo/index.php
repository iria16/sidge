<?php
declare(strict_types=1);

/**
 * Vista: dashboard/directivo/index.php
 * Panel de Dirección: visión estratégica, estadísticas de matrícula, personal y cobertura.
 *
 * Variables esperadas desde DashboardController::directivo():
 * @var string $saludo
 * @var string $nombreUsuario
 * @var array|null $anioActivo
 * @var array $metricasMatricula
 * @var array $metricasCapacidad
 * @var array $metricasPersonal
 * @var array $distribucionGrados
 * @var array $avanceResultados
 * @var array $alertas
 * @var bool $esCoordinador
 */

$pageTitle   = 'Dashboard';
$currentNav  = 'dashboard';

$breadcrumbs = [
    ['label' => 'Panel', 'href' => BASE_URL . 'dashboard/directivo'],
    ['label' => 'Dashboard', 'href' => null],
];

ob_start();
?>
<div class="container-fluid px-0">
  <?php include __DIR__ . '/../partials/directivo-dashboard.php'; ?>
</div>
<?php
$pageContent = ob_get_clean();

include __DIR__ . '/../../layouts/app.php';
