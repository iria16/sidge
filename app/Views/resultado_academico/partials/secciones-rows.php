<?php
/**
 * Partial: filas de la tabla de secciones.
 *
 * @var array $seccionesFilas Filas calculadas por ResultadoAcademicoVista.
 * @var bool  $esDocente
 */
?>
<?php foreach ($seccionesFilas as $sec): ?>
  <tr>
    <td>
      <strong class="text-primary">
        <?= htmlspecialchars($sec['grado']) ?>
        <?= htmlspecialchars($sec['seccion']) ?>
      </strong>
      <span class="text-secondary small d-block">
        Período <?= htmlspecialchars($sec['anio_escolar']) ?>
      </span>
    </td>
    <td><?= htmlspecialchars($sec['turno']) ?></td>
    <?php if (!$esDocente): ?>
      <td><?= htmlspecialchars($sec['docente']) ?></td>
    <?php endif; ?>
    <td class="text-center fw-semibold"><?= $sec['estudiantes'] ?></td>
    <td class="text-center text-success fw-semibold"><?= $sec['cargados'] ?></td>
    <td class="text-center <?= $sec['pendientes'] > 0 ? 'text-warning' : 'text-secondary' ?> fw-semibold"><?= $sec['pendientes'] ?></td>
    <td class="text-center text-success fw-semibold"><?= $sec['promovidos'] ?></td>
    <td class="text-center text-danger fw-semibold"><?= $sec['no_promovidos'] ?></td>
    <td>
      <?php
      $badgeLabel  = $sec['badge_texto'];
      $badgeStatus = $sec['badge_status'];
      include __DIR__ . '/../../components/status-badge.php';
      ?>
    </td>
    <td class="text-end">
      <a href="<?= BASE_URL ?>resultadoAcademico/seccion?id=<?= $sec['id'] ?>" class="btn btn-sm btn-outline-primary text-nowrap">
        <?= $esDocente ? 'Registrar literales' : 'Ver sección' ?>
      </a>
    </td>
  </tr>
<?php endforeach; ?>
