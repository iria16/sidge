<?php
declare(strict_types=1);

/**
 * Partial: estructuraAcademica/partials/panel-rows.php
 * Filas del listado. Se incluye dentro de un ob_start() para armar
 * $panelTableBody.
 *
 * @var array $estructuras
 * @var bool  $soloLectura  año cerrado o rol de consulta
 * @var bool  $esConsulta   el rol solo consulta
 */
?>
<?php foreach ($estructuras as $estructura): ?>
  <tr>
    <td class="fw-semibold"><?= htmlspecialchars($estructura['grado']) ?></td>
    <td><?= htmlspecialchars($estructura['seccion']) ?></td>
    <td><?= htmlspecialchars($estructura['turno']) ?></td>
    <?php
    /* Matriculados y capacidad van juntos porque la comparación es el dato: una sección
 con más alumnos que cupos es el error que hay que ver, y antes solo se mostraba la
 capacidad, así que el desborde no se veía en ningún lado.*/
    $matriculados = (int) $estructura['estudiantes'];
    $capacidad    = (int) $estructura['capacidad_maxima'];
    $sobrecupo    = $matriculados > $capacidad;
    ?>
    <td class="text-center fw-semibold <?= $sobrecupo ? 'text-danger' : '' ?>"
        title="<?= $sobrecupo ? 'La sección tiene más estudiantes que cupos' : 'Estudiantes matriculados' ?>">
      <?= $matriculados ?>
    </td>
    <td class="text-center"><?= $capacidad ?></td>
    <td class="text-center">
      <div class="data-panel__actions">
        <?php /* En un año cerrado no se muestra el botón de edición: el servidor
 rechaza la operación con 409. */ ?>
        <?php if ($soloLectura): ?>
          <span class="action-btn" aria-hidden="true"
                title="<?= !empty($esConsulta) ? 'Solo consulta: su rol no modifica la estructura' : 'Año cerrado: solo consulta' ?>">
            <i class="bi bi-lock-fill"></i>
          </span>
        <?php else: ?>
        <button type="button"
          class="action-btn action-btn--edit editar-estructura"
          data-id="<?= (int) $estructura['id'] ?>"
          data-anio="<?= (int) $estructura['id_anio_escolar'] ?>"
          data-grado="<?= (int) $estructura['id_grado'] ?>"
          data-seccion="<?= (int) $estructura['id_seccion'] ?>"
          data-turno="<?= (int) $estructura['id_turno'] ?>"
          data-capacidad="<?= (int) $estructura['capacidad_maxima'] ?>"
          /* El número de ocupados también viaja en el botón: es el dato que el modal
 necesita para poder avisar "esta acción afectará a N alumnos" sin tener
 que pedirlo por AJAX en el momento en que se abre. El backend lo vuelve a
 calcular al confirmar, porque entre abrir y guardar alguien puede
 haberse matriculado.*/
          data-ocupados="<?= (int) $estructura['estudiantes'] ?>"
          title="Editar estructura">
          <i class="bi bi-pencil"></i>
        </button>
        <?php endif; ?>
      </div>
    </td>
  </tr>
<?php endforeach; ?>
<?php if (!$estructuras): ?>
  <tr>
    <td colspan="6" class="text-center text-support py-4">
      No se encontraron estructuras académicas.
    </td>
  </tr>
<?php endif; ?>
