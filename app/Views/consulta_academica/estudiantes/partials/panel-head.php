<?php
declare(strict_types=1);

 /**
 * Partial: estudiantes/partials/panel-head.php
 * Cabecera de la tabla del listado.
 *
 * @var bool $esDocente
 * @var bool $esDirectivo
 */
 ?>
  <tr>
      <th scope="col">Nombre completo</th>
      <th scope="col">Cédula escolar</th>
      <?php /* «Grado y sección» no se le pinta al docente: su lista ya es de una sola
                 sección, la que da, así que la columna repite en cada fila lo mismo que
                 ya dice el encabezado de la página. Es la misma razón por la que sus
                 filtros de grado y sección no existen. */ ?>
      <?php if (!$esDocente): ?>
        <th scope="col">Grado y sección</th>
        <?php if ($esDirectivo): ?>
          <th scope="col" class="text-center">Turno</th>
        <?php endif; ?>
      <?php endif; ?>
      <th scope="col" class="text-center">Estado</th>
      <th scope="col" class="text-center data-panel__col-acciones">Acciones</th>
  </tr>
