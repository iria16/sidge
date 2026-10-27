<?php

declare(strict_types=1);

/**
 * Vista: dashboard/secretaria/index.php
 * Panel operativo de Secretaría.
 *
 * Variables desde DashboardController::secretaria():
 * - string $saludo                 Saludo según la hora de Caracas.
 * - string $nombreUsuario          Nombre de la secretaria en sesión.
 * - array|null $anioActivo        Año escolar en curso: ['id' => int, 'nombre' => string].
 * - string|null $turnoNombre      Nombre visible del turno; null si no existe.
 * - array|null $estructura        AcademicStructureModel::pendientesDeConfiguracion().
 * - array|null $estadisticasEstudiantes
 * - array|null $estadisticasInscripciones
 * - array|null $estadisticasRatificacion
 * - array|null $estadisticasRetiros
 * - array|null $cuposPorEstructura    InscripcionesModel::getCuposPorEstructura().
 * Cada bloque de estadísticas es null si su consulta falló o si no hay año/turno.
 */
$pageTitle       = 'Dashboard';
$currentNav      = 'dashboard';
$breadcrumbs     = [
    ['label' => 'Panel', 'href' => BASE_URL . 'dashboard/secretaria'],
    ['label' => 'Dashboard'],
];

$saludo = trim((string) ($saludo ?? 'Buenos días'));
$nombreUsuario = trim((string) ($nombreUsuario ?? ($_SESSION['nombre'] ?? 'Secretaría')));
$anioActivo = $anioActivo ?? null;
$turnoNombre = $turnoNombre ?? null;
$estructura = $estructura ?? null;
$estadisticasEstudiantes = $estadisticasEstudiantes ?? null;
$estadisticasInscripciones = $estadisticasInscripciones ?? null;
$estadisticasRatificacion = $estadisticasRatificacion ?? null;
$estadisticasRetiros = $estadisticasRetiros ?? null;
$cuposPorEstructura = $cuposPorEstructura ?? null;

$formatear = static function (?array $fila, string $clave): string {
    if ($fila === null) {
        return '—';
    }

    return number_format((int) ($fila[$clave] ?? 0), 0, ',', '.');
};

$hayAnioActivo = $anioActivo !== null;
$pendientesEstructura = $hayAnioActivo && $estructura !== null ? (int) $estructura['pendientes'] : 0;
$mostrarAviso = !$hayAnioActivo || $pendientesEstructura > 0;

ob_start();
?>
<div class="d-flex align-items-center justify-content-end gap-2 flex-wrap">
  <?php if ($anioActivo !== null): ?>
    <?php
    $periodText = (string) $anioActivo['nombre'];
    $periodStatus = 'Activo';
    $periodIsActive = true;
    include __DIR__ . '/../../components/period-chip.php';
    ?>
  <?php else: ?>
    <span class="badge text-bg-danger">
      <i class="bi bi-calendar-x me-1" aria-hidden="true"></i> Sin año escolar activo
    </span>
  <?php endif; ?>
  <span class="badge text-bg-light border">
    <i class="bi bi-clock me-1" aria-hidden="true"></i>
    <?= $turnoNombre !== null && $turnoNombre !== '' ? htmlspecialchars('Turno ' . $turnoNombre) : 'Sin turno asignado' ?>
  </span>
  <span class="text-support small"><?= htmlspecialchars(date('d/m/Y')) ?></span>
</div>
<?php
$pageHeaderActions = ob_get_clean();

ob_start();
?>
<div class="page-header mb-4">
  <div>
    <span class="docente-badge-rol">SECRETARÍA</span>
    <h1 class="page-header__title mb-1">¡<?= htmlspecialchars($saludo) ?>, <?= htmlspecialchars($nombreUsuario) ?>!</h1>
    <p class="page-header__subtitle mb-0">Resumen operativo del año escolar y del turno asignado.</p>
  </div>
  <?php if ($pageHeaderActions !== ''): ?>
    <div class="page-header__actions">
      <?= $pageHeaderActions ?>
    </div>
  <?php endif; ?>
</div>

<?php if ($mostrarAviso): ?>
  <?php $esBloqueante = !$hayAnioActivo; ?>
  <div class="alert alert-<?= $esBloqueante ? 'danger' : 'warning' ?> d-flex align-items-start gap-3 mb-4"
       role="alert">
    <i class="bi bi-<?= $esBloqueante ? 'x-octagon' : 'exclamation-triangle' ?> fs-5 flex-shrink-0"
       aria-hidden="true"></i>
    <div class="flex-grow-1">
      <?php if (!$hayAnioActivo): ?>
        <div class="fw-semibold mb-1">No hay un año escolar abierto</div>
        <p class="mb-0">
          Mientras ningún período esté en curso no se pueden registrar inscripciones ni
          ratificaciones. Avise al directivo para que abra el período en
          Configuración → Años escolares.
        </p>
      <?php else: ?>
        <?php $s = $pendientesEstructura === 1 ? '' : 's'; ?>
        <div class="fw-semibold mb-1">
          Falta configurar la estructura académica: <?= $pendientesEstructura ?>
          combinación<?= $s ?> pendiente<?= $s ?> en <?= htmlspecialchars((string) $estructura['anio']) ?>
        </div>
        <p class="mb-0">
          Están registradas <?= (int) $estructura['registradas'] ?> de las
          <?= (int) $estructura['esperadas'] ?> que se esperarían
          (<?= (int) $estructura['grados'] ?> grados × <?= (int) $estructura['turnos'] ?> turnos).
          La estructura la configura el directivo; usted no puede crearla ni editarla.
        </p>
        <?php if (!empty($estructura['grados_sin_seccion'])): ?>
          <p class="mb-0 mt-1">
            <strong>Sin ninguna sección en <?= htmlspecialchars((string) $estructura['anio']) ?>:</strong>
            <?= htmlspecialchars(implode(', ', $estructura['grados_sin_seccion'])) ?>.
            Un alumno que venga de un grado anterior no podrá ratificarse a uno de esos,
            porque la combinación no existe.
          </p>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<?php
$porRatificar = $estadisticasRatificacion['porRatificar'] ?? null;
$cuposDisponibles = $estadisticasInscripciones['cuposDisponibles'] ?? null;
$metaContexto = $anioActivo !== null
    ? ($turnoNombre !== null && $turnoNombre !== '' ? 'Turno ' . $turnoNombre : 'Turno asignado')
    : 'Sin año escolar activo';

include __DIR__ . '/partials/indicadores.php';
?>

<div class="row g-4 mb-4">
  <?php if ($anioActivo !== null): ?>
    <div class="col-12 col-xl-7">
      <?php include __DIR__ . '/partials/cupos-tabla.php'; ?>
    </div>
  <?php endif; ?>

  <div class="col-12 <?= $anioActivo !== null ? 'col-xl-5' : '' ?>">
    <?php include __DIR__ . '/partials/atencion.php'; ?>
  </div>
</div>

<?php include __DIR__ . '/partials/accesos-rapidos.php'; ?>
<?php
$pageContent = ob_get_clean();
include __DIR__ . '/../../layouts/app.php';
