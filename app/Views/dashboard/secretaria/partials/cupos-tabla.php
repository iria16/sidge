<?php

declare(strict_types=1);

/**
 * Partial: dashboard/secretaria/partials/cupos-tabla.php
 *
 * @var array $anioActivo
 * @var string|null $turnoNombre
 * @var array{filas: array<int, array{grado:string, seccion:string, capacidad:int, inscritos:int, disponibles:int, ocupacion:int}>, totales: array{capacidad:int, inscritos:int, disponibles:int, ocupacion:int}}|null $cuposPorEstructura
 */
$tituloContexto = (string) $anioActivo['nombre'];
if ($turnoNombre !== null && $turnoNombre !== '') {
    $tituloContexto .= ' · Turno ' . $turnoNombre;
}
?>
<section class="content-card h-100" aria-labelledby="headingCupos">
  <div class="content-card__header">
    <h2 id="headingCupos" class="content-card__title">
      <i class="bi bi-table me-1 text-secondary" aria-hidden="true"></i>
      Cupos por grado y sección
    </h2>
    <span class="text-support small"><?= htmlspecialchars($tituloContexto) ?></span>
  </div>

  <?php if ($cuposPorEstructura === null): ?>
    <div class="content-card__empty">
      <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
      <p>No se pudieron cargar los cupos. El resto del panel sigue disponible.</p>
    </div>
  <?php elseif ($cuposPorEstructura['filas'] === []): ?>
    <div class="content-card__empty">
      <i class="bi bi-diagram-3" aria-hidden="true"></i>
      <p>No hay combinaciones de grado y sección para este turno. La estructura académica la configura el Directivo.</p>
    </div>
  <?php else: ?>
    <?php $totales = $cuposPorEstructura['totales']; ?>
    <div class="table-responsive">
      <table class="table align-middle mb-0 cupos-tabla">
        <colgroup>
          <col class="ancho-22">
          <col class="ancho-14">
          <col class="ancho-14">
          <col class="ancho-14">
          <col class="ancho-14">
          <col class="ancho-22">
        </colgroup>
        <thead>
          <tr>
            <th scope="col">Grado</th>
            <th scope="col" class="text-center">Sección</th>
            <th scope="col" class="text-center">Capacidad</th>
            <th scope="col" class="text-center">Inscritos</th>
            <th scope="col" class="text-center">Disponibles</th>
            <th scope="col">Ocupación</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($cuposPorEstructura['filas'] as $fila): ?>
            <?php
            $llena = (int) $fila['disponibles'] === 0;
            $sinCapacidad = (int) $fila['capacidad'] === 0;
            $ocupacion = max(0, (int) $fila['ocupacion']);
            $progreso = min(100, $ocupacion);
            ?>
            <tr<?= $llena ? ' class="cupos-tabla__fila-llena"' : '' ?>>
              <td class="fw-medium"><?= htmlspecialchars((string) $fila['grado']) ?></td>
              <td class="text-center"><?= htmlspecialchars((string) $fila['seccion']) ?></td>
              <td class="text-center"><?= number_format((int) $fila['capacidad'], 0, ',', '.') ?></td>
              <td class="text-center"><?= number_format((int) $fila['inscritos'], 0, ',', '.') ?></td>
              <td class="text-center">
                <?= number_format((int) $fila['disponibles'], 0, ',', '.') ?>
                <?php if ($llena): ?>
                  <span class="badge text-bg-warning ms-1">
                    <i class="bi bi-lock-fill me-1" aria-hidden="true"></i><?= $sinCapacidad ? 'Sin capacidad' : 'Llena' ?>
                  </span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($sinCapacidad): ?>
                  <span class="text-support small">—</span>
                <?php else: ?>
                  <div class="d-flex align-items-center gap-3 cupos-tabla__ocupacion">
                    <progress class="secretaria-progress cupos-tabla__barra"
                              value="<?= $progreso ?>"
                              max="100"
                              aria-label="Ocupación de <?= htmlspecialchars((string) $fila['grado'] . ' ' . (string) $fila['seccion']) ?>">
                      <?= $progreso ?>%
                    </progress>
                    <span class="small text-nowrap cupos-tabla__porcentaje"><?= $ocupacion ?>%</span>
                  </div>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr class="fw-semibold">
            <td colspan="2">Total</td>
            <td class="text-center"><?= number_format((int) $totales['capacidad'], 0, ',', '.') ?></td>
            <td class="text-center"><?= number_format((int) $totales['inscritos'], 0, ',', '.') ?></td>
            <td class="text-center"><?= number_format((int) $totales['disponibles'], 0, ',', '.') ?></td>
            <td>
              <?php if ((int) $totales['capacidad'] === 0): ?>
                <span class="text-support small">—</span>
              <?php else: ?>
                <?php $progresoTotal = min(100, max(0, (int) $totales['ocupacion'])); ?>
                <div class="d-flex align-items-center gap-3 cupos-tabla__ocupacion">
                  <progress class="secretaria-progress cupos-tabla__barra"
                            value="<?= $progresoTotal ?>"
                            max="100"
                            aria-label="Ocupación total">
                    <?= $progresoTotal ?>%
                  </progress>
                  <span class="small text-nowrap cupos-tabla__porcentaje"><?= (int) $totales['ocupacion'] ?>%</span>
                </div>
              <?php endif; ?>
            </td>
          </tr>
        </tfoot>
      </table>
    </div>
  <?php endif; ?>
</section>
