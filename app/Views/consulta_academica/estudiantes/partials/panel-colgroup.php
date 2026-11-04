<?php
declare(strict_types=1);

/**
 * Partial: estudiantes/partials/panel-colgroup.php
 * Anchos de columna del listado. Los dos juegos suman 100%.
 *
 * - Docente: Nombre / Cédula / Estado / Acción.
 * - Resto:   Nombre / Cédula / Grado y sección / [Turno (directivo)] / Estado / Acción.
 *
 * @var bool $esDocente
 * @var bool $esDirectivo
 */
?>
  <colgroup>
    <?php if ($esDocente): ?>
      <col class="ancho-45">
      <col class="ancho-22">
      <col class="ancho-16">
      <col class="ancho-17">
    <?php else: ?>
      <col class="ancho-28">
      <col class="ancho-14">
      <col class="ancho-22">
      <?php if ($esDirectivo): ?>
        <col class="ancho-12">
      <?php endif; ?>
      <col class="ancho-10">
      <col class="ancho-14">
    <?php endif; ?>
  </colgroup>
