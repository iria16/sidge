<?php
declare(strict_types=1);

/**
 * Partial: reingreso/partials/panel-rows.php
 * Filas de la tabla de reingresos formalizados.
 *
 * @var array<int, array<string, mixed>> $solicitudes
 */
  foreach ($solicitudes as $item):
    $estudiante   = (string) ($item['estudiante'] ?? '');
    $cedula       = (string) ($item['cedula'] ?? '');
    $grado        = (string) ($item['grado'] ?? '');
    $seccion      = (string) ($item['seccion'] ?? '');
    $turno        = (string) ($item['turno'] ?? '');
    $anio         = (string) ($item['anio'] ?? '');
    $fechaReinc   = !empty($item['fecha_reincorporacion']) ? date('d/m/Y', strtotime((string) $item['fecha_reincorporacion'])) : '—';
    $motivo       = (string) ($item['motivo_reingreso'] ?? '');
    $iniciales    = (string) ($item['iniciales'] ?? '');
    $gradoRetiro  = (string) ($item['grado_retiro'] ?? '—');
    $anioRetiro   = (string) ($item['anio_retiro'] ?? '—');
    $motivoRetiro = (string) ($item['motivo_retiro'] ?? '—');
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
        <strong><?= htmlspecialchars($grado) ?></strong><br>
        <span class="text-support">Sección <?= htmlspecialchars($seccion) ?> · <?= htmlspecialchars($turno) ?> (<?= htmlspecialchars($anio) ?>)</span>
      </td>
      <td>
        <span class="text-dark fw-medium"><?= htmlspecialchars($gradoRetiro) ?> (<?= htmlspecialchars($anioRetiro) ?>)</span><br>
        <span class="text-support small">Motivo retiro: <?= htmlspecialchars($motivoRetiro) ?></span>
      </td>
      <td>
        <span class="fw-medium text-dark"><?= htmlspecialchars($fechaReinc) ?></span>
      </td>
      <td>
        <span class="text-dark"><?= htmlspecialchars($motivo) ?></span>
        <?php if (!empty($item['observaciones'])): ?>
          <br><span class="text-support small" title="Observaciones"><?= htmlspecialchars((string) $item['observaciones']) ?></span>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$solicitudes): ?>
    <tr>
      <td colspan="5" class="text-center text-support py-5">
        <i class="bi bi-inbox fs-1 d-block mb-2 text-muted"></i>
        <div class="fw-medium text-dark">No hay reingresos registrados en este período.</div>
        <div class="small text-secondary mt-1">Presione el botón superior <strong>"Registrar Reingreso"</strong> para reincorporar a un estudiante retirado.</div>
      </td>
    </tr>
  <?php endif; ?>
