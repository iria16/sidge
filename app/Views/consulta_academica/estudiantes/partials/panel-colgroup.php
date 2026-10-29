<?php
declare(strict_types=1);

/**
 * Partial: estudiantes/partials/panel-colgroup.php
 * Anchos de columna del listado. Los dos juegos suman 100%.
 *
 * - Docente: Nombre / Cédula / Estado / Acción.
 * - Resto:   Nombre / Cédula / Grado y sección / Estado / Acción.
 *
 * @var bool $esDocente
 */
?>
  <colgroup>
    <?php if ($esDocente): ?>
      <col class="ancho-45">
      <col class="ancho-22">
      <col class="ancho-16">
      <col class="ancho-17">
    <?php else: ?>
      <col class="ancho-30">
      <col class="ancho-16">
      <col class="ancho-24">
      <col class="ancho-14">
      <col class="ancho-16">
    <?php endif; ?>
  </colgroup>
