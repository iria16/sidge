<?php
declare(strict_types=1);

/**
 * Partial: asignacionDocente/partials/panel-rows.php
 * Filas del listado. Se incluye dentro de un ob_start() para armar
 * $panelTableBody.
 *
 * @var array<int, array<string, mixed>> $asignaciones
 * @var bool $esConsulta
 */
  foreach ($asignaciones as $item):
    $grado         = $item['grado'] ?? '';
    $seccion       = $item['seccion'] ?? '';
    $turno         = $item['turno'] ?? '';
    $docente       = $item['docente'] ?? '';
    $estado        = $item['estado'] ?? 'pendiente';
    $estructuraId  = (int) ($item['estructura_id'] ?? 0);
    $asignacionId  = (int) ($item['id_asignacion'] ?? 0);
    $asignado      = $estado === 'asignado';
  ?>
    <tr>
      <td class="fw-semibold"><?= htmlspecialchars($grado) ?></td>
      <td>
        <?= htmlspecialchars($seccion) ?>
        <div class="text-support small"><?= htmlspecialchars($turno) ?></div>
      </td>
      <td>
        <?php if ($docente !== ''): ?>
          <div class="avatar-group d-flex align-items-center gap-2">
            <?php
              $avatarSize     = 'sm';
              $avatarTone     = 'primary';
              $avatarSrc      = '';
              $avatarAlt      = $docente;
              $avatarInitials = mb_strtoupper(mb_substr($docente, 0, 1));
              include __DIR__ . '/../../../components/avatar.php';
            ?>
            <span><?= htmlspecialchars($docente) ?></span>
          </div>
        <?php else: ?>
          <span class="text-support">Por asignar</span>
        <?php endif; ?>
      </td>
      <td>
        <?php
          $badgeLabel  = $asignado ? 'Asignado' : 'Pendiente';
          $badgeStatus = $asignado ? 'active' : 'pending';
          include __DIR__ . '/../../../components/status-badge.php';
        ?>
      </td>
      <td class="text-center">
        <?php if (empty($esConsulta)): ?>
          <div class="data-panel__actions">
            <button type="button"
              class="action-btn action-btn--edit editar-asignacion"
              data-id="<?= $estructuraId ?>"
              data-asignacion-id="<?= $asignacionId > 0 ? $asignacionId : '' ?>"
              data-docente-id="<?= (int) ($item['id_docente'] ?? 0) ?>"
              data-tipo-id="<?= (int) ($item['id_tipo_asignacion'] ?? 0) ?>"
              data-fecha-inicio="<?= htmlspecialchars($item['fecha_inicio'] ?? '') ?>"
              title="Asignar o editar docente">
              <i class="bi bi-pencil"></i>
            </button>
            <?php if ($asignacionId): ?>
              <button type="button"
                class="action-btn action-btn--danger quitar-asignacion"
                data-id="<?= $asignacionId ?>"
                title="Retirar asignación">
                <i class="bi bi-trash"></i>
              </button>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$asignaciones): ?>
    <tr>
      <td colspan="5" class="text-center text-support py-4">
        No se encontraron secciones configuradas.
      </td>
    </tr>
  <?php endif; ?>
