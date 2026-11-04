<?php
declare(strict_types=1);

/**
 * Partial: estudiantes/partials/panel-colgroup.php
 * Anchos de columna del listado. Los juegos suman 100%.
 *
 * - Docente:   Nombre / Cédula / Estado / Acción.
 * - Directivo: Nombre / Cédula / Grado y sección / Turno / Estado / Acción.
 * - Resto:     Nombre / Cédula / Grado y sección / Estado / Acción.
 *
 * @var bool $esDocente
 * @var bool $esDirectivo
 */
?>
  <colgroup>
    <?php if ($esDocente): ?>
      <col class="ancho-38">
      <col class="ancho-29">
      <col class="ancho-16">
      <col class="ancho-17">
    <?php elseif (!empty($esDirectivo)): ?>
      <col class="ancho-28">
      <col class="ancho-14">
      <col class="ancho-22">
      <col class="ancho-12">
      <col class="ancho-10">
      <col class="ancho-14">
    <?php else: ?>
      <col class="ancho-30">
      <col class="ancho-16">
      <col class="ancho-24">
      <col class="ancho-14">
      <col class="ancho-16">
    <?php endif; ?>
  </colgroup>
