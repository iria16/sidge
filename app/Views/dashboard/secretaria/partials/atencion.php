<?php

declare(strict_types=1);

/**
 * Partial: dashboard/secretaria/partials/atencion.php
 *
 * La estructura incompleta es solo informativa: Secretaría no puede crearla.
 *
 * @var bool $hayAnioActivo
 * @var array|null $anioActivo
 * @var array|null $estructura
 * @var int $pendientesEstructura
 * @var array|null $estadisticasRatificacion
 * @var array|null $estadisticasRetiros
 */
$atencion = [];

if (!$hayAnioActivo) {
    $atencion[] = [
        'icono' => 'bi-calendar-x',
        'tono' => 'danger',
        'titulo' => 'No hay año escolar abierto',
        'descripcion' => 'El Directivo debe abrir un período para habilitar inscripciones y ratificaciones.',
        'conteo' => null,
        'href' => null,
        'enlace' => null,
    ];
} elseif ($estructura !== null && $pendientesEstructura > 0) {
    $atencion[] = [
        'icono' => 'bi-diagram-3',
        'tono' => 'warning',
        'titulo' => 'Estructura académica incompleta',
        'descripcion' => 'Faltan ' . $pendientesEstructura . ' combinaciones en '
            . (string) $estructura['anio'] . '. El Directivo debe completar la estructura.',
        'conteo' => $pendientesEstructura,
        'href' => null,
        'enlace' => null,
    ];
}

if ($anioActivo !== null && $estadisticasRatificacion !== null && (int) ($estadisticasRatificacion['porRatificar'] ?? 0) > 0) {
    $atencion[] = [
        'icono' => 'bi-arrow-repeat',
        'tono' => 'warning',
        'titulo' => 'Ratificaciones pendientes',
        'descripcion' => 'Alumnos con matrícula previa que aún no han sido ratificados.',
        'conteo' => (int) $estadisticasRatificacion['porRatificar'],
        'href' => BASE_URL . 'ratificacion/index',
        'enlace' => 'Ver ratificaciones',
    ];
}

if ($anioActivo !== null && $estadisticasRetiros !== null && (int) ($estadisticasRetiros['pendientes'] ?? 0) > 0) {
    $atencion[] = [
        'icono' => 'bi-box-arrow-right',
        'tono' => 'info',
        'titulo' => 'Solicitudes de retiro en revisión',
        'descripcion' => 'Solicitudes registradas que esperan la decisión del Directivo.',
        'conteo' => (int) $estadisticasRetiros['pendientes'],
        'href' => BASE_URL . 'withdrawal/index',
        'enlace' => 'Ver solicitudes',
    ];
}
?>
<section class="content-card h-100" aria-labelledby="headingAtencion">
  <div class="content-card__header">
    <h2 id="headingAtencion" class="content-card__title">
      <i class="bi bi-bell me-1 text-secondary" aria-hidden="true"></i>
      Requiere atención
    </h2>
  </div>

  <?php if (!$atencion): ?>
    <div class="content-card__empty">
      <i class="bi bi-inbox" aria-hidden="true"></i>
      <p>No hay pendientes operativos para este turno.</p>
    </div>
  <?php else: ?>
    <div class="list-group list-group-flush">
      <?php foreach ($atencion as $item): ?>
        <div class="list-group-item d-flex align-items-start gap-3 px-0">
          <span class="stat-card__icon stat-card__icon--<?= htmlspecialchars((string) $item['tono']) ?>">
            <i class="bi <?= htmlspecialchars((string) $item['icono']) ?>" aria-hidden="true"></i>
          </span>
          <div class="flex-grow-1">
            <div class="d-flex align-items-center gap-2 flex-wrap">
              <p class="fw-semibold mb-0"><?= htmlspecialchars((string) $item['titulo']) ?></p>
              <?php if ($item['conteo'] !== null): ?>
                <span class="badge text-bg-light border"><?= (int) $item['conteo'] ?></span>
              <?php endif; ?>
            </div>
            <p class="text-support small mb-0"><?= htmlspecialchars((string) $item['descripcion']) ?></p>
          </div>
          <?php if ($item['href'] !== null && $item['enlace'] !== null): ?>
            <a href="<?= htmlspecialchars((string) $item['href']) ?>" class="btn btn-sm btn-outline-secondary flex-shrink-0">
              <?= htmlspecialchars((string) $item['enlace']) ?>
            </a>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
