<?php
declare(strict_types=1);

/**
 * Vista: estadisticas/index.php
 * Interfaz única de Estadísticas Institucionales para Dirección y Coordinación.
 *
 * Variables esperadas desde StatisticsController::index():
 * @var array<int, array<string, mixed>> $anios
 * @var int                              $idAnio
 * @var string                           $nombrePeriodo
 * @var array<int, array<string, mixed>> $grados
 * @var int|null                         $idGrado
 * @var array<int, array<string, mixed>> $secciones
 * @var int|null                         $idSeccion
 * @var string                           $pestana
 * @var string                           $q
 * @var array<string, mixed>            $resumenHermanos
 * @var array<int, array<string, mixed>> $detalleHermanos
 * @var array<string, int>              $paginacionHermanos
 * @var array<string, mixed>            $demografiaMatricula
 * @var array<int, array<string, mixed>> $matriculaAulas
 * @var array<string, mixed>            $estadisticasFamilias
 * @var array<int, array<string, mixed>> $distribucionParroquias
 * @var array<string, mixed>            $metricasSalud
 * @var array<int, array<string, mixed>> $tiposSangre
 * @var array<int, array<string, mixed>> $filasSalud
 * @var array<int, array<string, mixed>> $filasAntropometricas
 * @var array<string, mixed>            $distribucionTallas
 * @var array<int, array<string, mixed>> $filasTallas
 * @var array<string, mixed>            $documentosPendientes
 * @var array<int, array<string, mixed>> $coberturaAulas
 * @var array<int, array<string, mixed>> $estadisticasPersonal
 * @var array<string, mixed>            $metricasPersonal
 * @var array<int, array<string, mixed>> $filasRendimiento
 * @var array<string, mixed>            $resumenRendimiento
 */

$pageTitle       = 'Estadísticas Institucionales';
$pageDescription = 'Control de familias y hermanos, matrícula escolar, calificaciones y rendimiento, capacidad física y talento humano del plantel.';
$currentNav      = 'estadisticas';

$breadcrumbs = [
    ['label' => 'Seguimiento institucional', 'href' => null],
    ['label' => 'Estadísticas',              'href' => null],
];

ob_start();
?>
<div class="container-fluid px-0">

  <!-- Encabezado de página -->
  <?php
  $pageHeaderActions = '';
  $pageHeading       = $pageTitle;
  $pageSubheading    = $pageDescription;
  include __DIR__ . '/../components/page-header.php';
  ?>

  <!-- Barra de Filtros Globales (Año, Grado, Sección y Exportación) -->
  <?php include __DIR__ . '/partials/filtros-globales.php'; ?>

  <!-- Navegación por Pestañas Institucionales -->
  <ul class="nav nav-tabs estadisticas-tabs mb-4" id="estadisticasTabs" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link <?= $pestana === 'hermanos' ? 'active' : '' ?>"
              id="tab-hermanos-btn"
              data-bs-toggle="tab"
              data-bs-target="#tab-hermanos"
              type="button"
              role="tab"
              aria-controls="tab-hermanos"
              aria-selected="<?= $pestana === 'hermanos' ? 'true' : 'false' ?>">
        <i class="bi bi-people-fill" aria-hidden="true"></i>
        <span>Representantes y Hermanos</span>
      </button>
    </li>

    <li class="nav-item" role="presentation">
      <button class="nav-link <?= $pestana === 'matricula' ? 'active' : '' ?>"
              id="tab-matricula-btn"
              data-bs-toggle="tab"
              data-bs-target="#tab-matricula"
              type="button"
              role="tab"
              aria-controls="tab-matricula"
              aria-selected="<?= $pestana === 'matricula' ? 'true' : 'false' ?>">
        <i class="bi bi-backpack-fill" aria-hidden="true"></i>
        <span>Matrícula y Familias</span>
      </button>
    </li>

    <li class="nav-item" role="presentation">
      <button class="nav-link <?= $pestana === 'salud' ? 'active' : '' ?>"
              id="tab-salud-btn"
              data-bs-toggle="tab"
              data-bs-target="#tab-salud"
              type="button"
              role="tab"
              aria-controls="tab-salud"
              aria-selected="<?= $pestana === 'salud' ? 'true' : 'false' ?>">
        <i class="bi bi-heart-pulse-fill" aria-hidden="true"></i>
        <span>Dotación y Salud</span>
      </button>
    </li>

    <li class="nav-item" role="presentation">
      <button class="nav-link <?= $pestana === 'institucional' ? 'active' : '' ?>"
              id="tab-institucional-btn"
              data-bs-toggle="tab"
              data-bs-target="#tab-institucional"
              type="button"
              role="tab"
              aria-controls="tab-institucional"
              aria-selected="<?= $pestana === 'institucional' ? 'true' : 'false' ?>">
        <i class="bi bi-shield-check" aria-hidden="true"></i>
        <span>Control Administrativo</span>
      </button>
    </li>

    <li class="nav-item" role="presentation">
      <button class="nav-link <?= $pestana === 'rendimiento' ? 'active' : '' ?>"
              id="tab-rendimiento-btn"
              data-bs-toggle="tab"
              data-bs-target="#tab-rendimiento"
              type="button"
              role="tab"
              aria-controls="tab-rendimiento"
              aria-selected="<?= $pestana === 'rendimiento' ? 'true' : 'false' ?>">
        <i class="bi bi-award-fill" aria-hidden="true"></i>
        <span>Calificaciones y Rendimiento</span>
      </button>
    </li>
  </ul>

  <!-- Contenido de las Pestañas -->
  <div class="tab-content" id="estadisticasTabContent">
    <!-- Pestaña 1: Representantes y Hermanos -->
    <div class="tab-pane fade <?= $pestana === 'hermanos' ? 'show active' : '' ?>"
         id="tab-hermanos"
         role="tabpanel"
         aria-labelledby="tab-hermanos-btn"
         tabindex="0">
      <?php include __DIR__ . '/partials/tab-hermanos.php'; ?>
    </div>

    <!-- Pestaña 2: Matrícula y Familias -->
    <div class="tab-pane fade <?= $pestana === 'matricula' ? 'show active' : '' ?>"
         id="tab-matricula"
         role="tabpanel"
         aria-labelledby="tab-matricula-btn"
         tabindex="0">
      <?php include __DIR__ . '/partials/tab-matricula.php'; ?>
    </div>

    <!-- Pestaña 3: Dotación y Salud -->
    <div class="tab-pane fade <?= $pestana === 'salud' ? 'show active' : '' ?>"
         id="tab-salud"
         role="tabpanel"
         aria-labelledby="tab-salud-btn"
         tabindex="0">
      <?php include __DIR__ . '/partials/tab-salud-tallas.php'; ?>
    </div>

    <!-- Pestaña 4: Control Administrativo -->
    <div class="tab-pane fade <?= $pestana === 'institucional' ? 'show active' : '' ?>"
         id="tab-institucional"
         role="tabpanel"
         aria-labelledby="tab-institucional-btn"
         tabindex="0">
      <?php include __DIR__ . '/partials/tab-institucional.php'; ?>
    </div>

    <!-- Pestaña 5: Calificaciones y Rendimiento -->
    <div class="tab-pane fade <?= $pestana === 'rendimiento' ? 'show active' : '' ?>"
         id="tab-rendimiento"
         role="tabpanel"
         aria-labelledby="tab-rendimiento-btn"
         tabindex="0">
      <?php include __DIR__ . '/partials/tab-rendimiento.php'; ?>
    </div>
  </div>

</div>
<?php
$pageContent = ob_get_clean();

// Inclusión de módulos JS de gráficas y filtros de estadísticas
$extraScripts = [
    '/js/vendor/chart.js',
    '/js/modules/dashboard/charts.js',
    '/js/modules/estadisticas/estadisticas.js',
];

include __DIR__ . '/../layouts/app.php';
