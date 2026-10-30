<?php
declare(strict_types=1);

/**
 * Partial: anios_escolares/partials/panel-rows.php
 * Filas del listado. Se incluye dentro de un ob_start() para armar
 * $panelTableBody.
 *
 * @var array<int, array<string, mixed>> $anios
 * @var bool $puedeReabrir
 */
  foreach ($anios as $anio):
    $estado = $anio['estado'];
    $activo = $estado === 'activo';
    $planificado = $estado === 'planificado';
    $cerrado = $estado === 'cerrado';
    $estadoLabel = $planificado ? 'Planificado' : ($activo ? 'Activo' : 'Cerrado');
    $badgeStatus = $planificado ? 'pending' : ($activo ? 'active' : 'inactive');
  ?>
    <tr class="<?= $cerrado ? 'is-muted' : '' ?>">
      <td><strong><?= htmlspecialchars($anio['nombre']) ?></strong></td>
      <td><?= htmlspecialchars(date('d/m/Y', strtotime($anio['fecha_inicio']))) ?></td>
      <td><?= htmlspecialchars(date('d/m/Y', strtotime($anio['fecha_cierre']))) ?></td>
      <td>
        <?php
          $badgeLabel = $estadoLabel;
          include __DIR__ . '/../../../components/status-badge.php';
        ?>
      </td>
      <td class="text-center">
        <div class="data-panel__actions">
          <button type="button" class="action-btn ver-anio"
            data-id="<?= (int) $anio['id'] ?>"
            data-nombre="<?= htmlspecialchars($anio['nombre']) ?>"
            data-inicio="<?= htmlspecialchars($anio['fecha_inicio']) ?>"
            data-cierre="<?= htmlspecialchars($anio['fecha_cierre']) ?>"
            data-estado="<?= htmlspecialchars($estadoLabel) ?>"
            data-iniciado-en="<?= htmlspecialchars((string) ($anio['iniciado_en'] ?? '')) ?>"
            data-iniciado-por="<?= htmlspecialchars((string) ($anio['iniciado_por'] ?? '')) ?>"
            data-cerrado-en="<?= htmlspecialchars((string) ($anio['cerrado_en'] ?? '')) ?>"
            data-cerrado-por="<?= htmlspecialchars((string) ($anio['cerrado_por'] ?? '')) ?>"
            data-reabierto-por="<?= htmlspecialchars((string) ($anio['reabierto_por'] ?? '')) ?>"
            data-reapertura="<?= htmlspecialchars((string) ($anio['motivo_reapertura'] ?? '')) ?>"
            title="Ver año escolar" aria-label="Ver <?= htmlspecialchars($anio['nombre']) ?>">
            <i class="bi bi-eye"></i>
          </button>
          <?php if (!$cerrado): ?>
            <button type="button" class="action-btn action-btn--edit editar-anio"
              data-id="<?= (int) $anio['id'] ?>"
              data-nombre="<?= htmlspecialchars($anio['nombre']) ?>"
              data-inicio="<?= htmlspecialchars($anio['fecha_inicio']) ?>"
              data-cierre="<?= htmlspecialchars($anio['fecha_cierre']) ?>"
              title="Editar año escolar" aria-label="Editar <?= htmlspecialchars($anio['nombre']) ?>">
              <i class="bi bi-pencil"></i>
            </button>
          <?php endif; ?>
          <?php if ($planificado): ?>
            <button type="button" class="action-btn iniciar-anio" data-id="<?= (int) $anio['id'] ?>"
              data-nombre="<?= htmlspecialchars($anio['nombre']) ?>"
              title="Iniciar año escolar" aria-label="Iniciar <?= htmlspecialchars($anio['nombre']) ?>">
              <i class="bi bi-play-circle"></i>
            </button>
          <?php elseif ($activo): ?>
            <button type="button" class="action-btn action-btn--danger cerrar-anio" data-id="<?= (int) $anio['id'] ?>"
              data-nombre="<?= htmlspecialchars($anio['nombre']) ?>"
              title="Cerrar año escolar" aria-label="Cerrar <?= htmlspecialchars($anio['nombre']) ?>">
              <i class="bi bi-lock"></i>
            </button>
          <?php elseif ($cerrado && $puedeReabrir): ?>
            <?php /* Botón de reapertura: solo la directiva, igual que la ruta. Se comprueba
 también en el servidor, esto solo evita mostrar una acción que va a fallar.*/ ?>
            <button type="button" class="action-btn reabrir-anio" data-id="<?= (int) $anio['id'] ?>"
              data-nombre="<?= htmlspecialchars($anio['nombre']) ?>"
              title="Reabrir año escolar (requiere motivo)" aria-label="Reabrir <?= htmlspecialchars($anio['nombre']) ?>">
              <i class="bi bi-arrow-counterclockwise"></i>
            </button>
          <?php endif; ?>
        </div>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$anios): ?>
    <tr>
      <td colspan="5" class="text-center text-support py-4">
        No se encontraron años escolares.
      </td>
    </tr>
  <?php endif; ?>
