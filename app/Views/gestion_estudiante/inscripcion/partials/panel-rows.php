<?php
declare(strict_types=1);

/**
 * Partial: inscripcion/partials/panel-rows.php
 * Filas del listado. Se incluye dentro de un ob_start() para armar
 * $panelTableBody.
 *
 * @var array<int, array<string, mixed>> $inscripciones
 */
  foreach ($inscripciones as $inscripcion):
    $inscripcionId = (int) ($inscripcion['id'] ?? 0);
  ?>
    <tr>
      <td>
        <div class="avatar-group">
          <?php
            $avatarSize     = 'md';
            $avatarTone     = $inscripcion['avatar_color'] ?? 'primary';
            $avatarSrc      = $inscripcion['avatar_img'] ?? '';
            $avatarAlt      = $inscripcion['nombre'] ?? '';
            $avatarInitials = $inscripcion['iniciales'] ?? 'ES';
            include __DIR__ . '/../../../components/avatar.php';
          ?>
          <div>
            <div class="avatar-group__name"><?= htmlspecialchars($inscripcion['nombre'] ?? '') ?></div>
            <div class="avatar-group__meta"><?= htmlspecialchars($inscripcion['cedula'] ?? '') ?></div>
          </div>
        </div>
      </td>
      <td class="text-support"><?= htmlspecialchars($inscripcion['grado'] ?? '') ?></td>
      <td class="text-support"><?= htmlspecialchars($inscripcion['seccion'] ?? '') ?></td>
      <td class="text-support"><?= htmlspecialchars($inscripcion['fecha_inscripcion'] ?? '') ?></td>
      <td class="text-center">
        <div class="data-panel__actions">
          <button type="button" class="action-btn action-btn--view btn-ver-inscripcion"
                  title="Ver inscripción"
                  data-id="<?= $inscripcionId ?>">
            <i class="bi bi-eye" aria-hidden="true"></i>
          </button>
        </div>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$inscripciones): ?>
    <tr>
      <td colspan="5" class="text-center text-support py-4">
        No se encontraron inscripciones con los filtros seleccionados.
      </td>
    </tr>
  <?php endif; ?>
