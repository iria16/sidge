<?php
declare(strict_types=1);

/**
 * Partial: catalogo/partials/panel-colgroup.php
 * Anchos de columna del listado: Nombre / Tipo o grado (opcional) / Estado / Acciones.
 *
 * @var array $tiposInstitucion
 * @var bool $pideGrado
 */
?>
  <colgroup>
    <?php if (empty($tiposInstitucion) && empty($pideGrado)): ?>
      <col class="ancho-55 min-ancho-280px">
    <?php else: ?>
      <col class="ancho-45 min-ancho-280px">
      <col class="ancho-25 min-ancho-140px">
    <?php endif; ?>
    <col class="ancho-15 min-ancho-120px">
    <col class="ancho-15 min-ancho-120px">
  </colgroup>
