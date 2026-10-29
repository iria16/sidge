<?php
declare(strict_types=1);

/**
 * Partial: usuarios/recuperacion/partials/panel-rows.php
 * Filas del listado. Se incluye dentro de un ob_start() para armar
 * $panelTableBody.
 *
 * @var array $solicitudes
 * @var array $estadosMeta
 */
  foreach ($solicitudes as $s):
    $meta   = $estadosMeta[$s['estado']] ?? $estadosMeta['pendiente'];
    $origen = $s['origen'] ?? 'solicitud';
  ?>
    <tr>
      <td>
        <div class="fw-semibold"><?= htmlspecialchars($s['fecha']) ?></div>
        <div class="text-support small"><?= htmlspecialchars($s['hora']) ?></div>
      </td>
      <td>
        <div class="avatar-group d-flex align-items-center gap-2">
          <div class="flex-shrink-0">
            <?php
              // Si avatar_tipo es 'foto', se pinta la imagen real del empleado
              // (BASE_URL + ruta relativa ya normalizada por AvatarTrait::resolverAvatar).
              // Si no, cae a iniciales+color por rol.
              $avatarSize     = 'md';
              $avatarSrc      = ($s['avatar_tipo'] ?? 'iniciales') === 'foto'
                  ? htmlspecialchars(BASE_URL . $s['avatar_foto'])
                  : '';
              $avatarAlt      = $s['nombre'];
              $avatarTone     = $s['avatar_color'] ?? 'primary';
              $avatarInitials = $s['iniciales'] ?? '';
              include __DIR__ . '/../../../components/avatar.php';
            ?>
          </div>
          <div class="text-truncate">
            <div class="avatar-group__name text-truncate"><?= htmlspecialchars($s['nombre']) ?></div>
          </div>
        </div>
      </td>
      <td class="text-support ps-4"><?= htmlspecialchars($s['usuario']) ?></td>
      <td class="text-center">
        <?php
          $badgeLabel  = $meta['label'];
          $badgeStatus = $meta['clase'];
          include __DIR__ . '/../../../components/status-badge.php';
        ?>
        <?php if ($origen === 'admin'): ?>
          <div class="text-support small mt-1">
            <i class="bi bi-person-gear" aria-hidden="true"></i> Restablecido por admin
          </div>
        <?php endif; ?>
      </td>
      <td class="text-center">
        <div class="data-panel__actions">
          <?php if ($s['estado'] === 'pendiente'): ?>
            <button type="button"
              class="action-btn action-btn--toggle aprobar-solicitud"
              data-solicitud-id="<?= (int) $s['id'] ?>"
              data-usuario-id="<?= (int) $s['usuario_id'] ?>"
              data-usuario-nombre="<?= htmlspecialchars($s['nombre'] . ' · ' . $s['usuario']) ?>"
              title="Aprobar solicitud">
              <i class="bi bi-check-lg" aria-hidden="true"></i>
            </button>
            <button type="button"
              class="action-btn action-btn--danger rechazar-solicitud"
              data-solicitud-id="<?= (int) $s['id'] ?>"
              title="Rechazar solicitud">
              <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
          <?php else: ?>
            <button type="button" class="action-btn action-btn--view ver-solicitud"
              data-bs-toggle="modal"
              data-bs-target="#modalDetalleSolicitud"
              data-solicitud-id="<?= (int) $s['id'] ?>"
              title="Ver detalle">
              <i class="bi bi-eye" aria-hidden="true"></i>
            </button>
          <?php endif; ?>
        </div>
      </td>
    </tr>
<?php endforeach; ?>
<?php if (empty($solicitudes)): ?>
    <tr>
      <td colspan="5" class="text-center text-support py-4">
        No se encontraron solicitudes con los filtros seleccionados.
      </td>
    </tr>
<?php endif; ?>
