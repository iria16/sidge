<?php
declare(strict_types=1);

/**
 * Partial: retiro/partials/panel-rows.php
 * Filas del listado. Se incluye dentro de un ob_start() para armar
 * $panelTableBody.
 *
 * @var array<int, array<string, mixed>> $solicitudes
 * @var bool $puedeResolver
 */
  foreach ($solicitudes as $item):
    $estudiante     = $item['estudiante'] ?? '';
    $cedula         = $item['cedula'] ?? '';
    $grado          = $item['grado'] ?? '';
    $seccion        = $item['seccion'] ?? '';
    $fechaSolicitud = $item['fecha_solicitud'] ?? '';
    $observaciones  = $item['observaciones'] ?? '';
    $estado         = $item['estado'] ?? 'pendiente';
    $iniciales      = $item['iniciales'] ?? '';

    $estadoClase = $estado === 'aprobado'
        ? 'active'
        : ($estado === 'rechazado' ? 'inactive' : 'pending');
  ?>
    <tr>
      <td>
        <div class="avatar-group d-flex align-items-center gap-2">
          <?php
            $avatarInitials = $iniciales;
            $avatarSize     = 'sm';
            $avatarTone     = 'primary';
            $avatarSrc      = '';
            $avatarAlt      = $estudiante;
            include __DIR__ . '/../../../components/avatar.php';
          ?>
          <div>
            <div class="avatar-group__name"><?= htmlspecialchars($estudiante) ?></div>
            <div class="avatar-group__meta"><?= htmlspecialchars($cedula) ?></div>
          </div>
        </div>
      </td>
      <td>
        <?= htmlspecialchars($grado) ?><br>
        <span class="text-support">Sección <?= htmlspecialchars($seccion) ?></span>
      </td>
      <td><?= htmlspecialchars($fechaSolicitud) ?></td>
      <td>
        <?php
          $badgeLabel  = ucfirst($estado);
          $badgeStatus = $estadoClase;
          include __DIR__ . '/../../../components/status-badge.php';
        ?>
      </td>
      <td class="text-center">
        <div class="data-panel__actions">
          <button type="button"
            class="action-btn action-btn--view ver-retiro"
            data-id="<?= (int) $item['id'] ?>"
            title="Ver solicitud">
            <i class="bi bi-eye"></i>
          </button>
          <?php if ($puedeResolver && $estado === 'pendiente'): ?>
            <button type="button" class="action-btn aprobar-retiro" data-id="<?= (int) $item['id'] ?>" title="Aprobar retiro">
              <i class="bi bi-check-circle"></i>
            </button>
            <button type="button" class="action-btn action-btn--danger rechazar-retiro" data-id="<?= (int) $item['id'] ?>" title="Rechazar retiro">
              <i class="bi bi-x-circle"></i>
            </button>
          <?php endif; ?>
        </div>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$solicitudes): ?>
    <tr>
      <td colspan="5" class="text-center text-support py-4">
        No se encontraron solicitudes de retiro.
      </td>
    </tr>
  <?php endif; ?>
