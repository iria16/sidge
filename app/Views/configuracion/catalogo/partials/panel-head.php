<?php
declare(strict_types=1);

/**
 * Partial: catalogo/partials/panel-head.php
 * Cabecera de la tabla del listado.
 *
 * @var array $tiposInstitucion
 */
?>
  <tr>
    <th scope="col">Nombre</th>
    <?php if (!empty($tiposInstitucion)): ?><th scope="col">Tipo</th><?php endif; ?>
    <th scope="col" class="text-center">Estado</th>
    <th scope="col" class="text-center">Acciones</th>
  </tr>
