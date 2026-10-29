<?php
declare(strict_types=1);

/**
 * Partial: docente/partials/table-head.php
 * Cabecera de la tabla de nómina.
 *
 * @var array  $columnas
 * @var callable $e
 */
?>
        <table class="table align-middle mb-0 data-panel__table nomina-tabla" id="nominaTabla">
        <thead>
          <tr>
<?php /* Encabezados de texto. El orden de los listados es fijo y lo
                    aplica el servidor: alfabético por apellido, que es como se
                    lee un listado y como se entrega en el PDF. */ ?>
<?php foreach ($columnas as $clave => $titulo): ?>
              <th scope="col" class="nomina-col--<?= $e($clave) ?>"><?= $e($titulo) ?></th>
<?php endforeach; ?>
          </tr>
        </thead>
