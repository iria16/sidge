<?php
/**
 * Partial: filas de la tabla de secciones.
 *
 * @var array $seccionesFilas Filas calculadas por ResultadoAcademicoVista.
 */
?>
<?php foreach ($seccionesFilas as $sec): ?>
  <?php
  $total    = (int) ($sec['estudiantes'] ?? 0);
  $cargados = (int) ($sec['cargados'] ?? 0);
  $pct      = $total > 0 ? (int) round(($cargados / $total) * 100) : 0;
  ?>
  <tr>
    <td>
      <strong class="text-primary fs-6">
        <?= htmlspecialchars($sec['grado']) ?> "<?= htmlspecialchars($sec['seccion']) ?>"
      </strong>
    </td>
    <td><?= htmlspecialchars($sec['turno']) ?></td>
    <td>
      <div class="d-flex align-items-center gap-3" style="max-width: 260px;">
        <div class="progress flex-grow-1" style="height: 8px;" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100">
          <div class="progress-bar <?= $pct === 100 ? 'bg-success' : 'bg-primary' ?>" style="width: <?= $pct ?>%;"></div>
        </div>
        <span class="small fw-semibold text-secondary text-nowrap"><?= $cargados ?>/<?= $total ?> (<?= $pct ?>%)</span>
      </div>
    </td>
    <td class="text-center">
      <?php
      $badgeLabel  = $sec['badge_texto'];
      $badgeStatus = $sec['badge_status'];
      include __DIR__ . '/../../components/status-badge.php';
      ?>
    </td>
    <td class="text-end">
      <a href="<?= BASE_URL ?>resultadoAcademico/seccion?id=<?= $sec['id'] ?>" class="btn btn-sm btn-primary text-nowrap">
        <i class="bi bi-pencil-square me-1" aria-hidden="true"></i>Cargar literales
      </a>
    </td>
  </tr>
<?php endforeach; ?>