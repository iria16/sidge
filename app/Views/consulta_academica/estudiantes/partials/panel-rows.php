<?php
declare(strict_types=1);

/**
 * Partial: estudiantes/partials/panel-rows.php
 * Filas del listado. Se incluye dentro de un ob_start() para armar
 * $panelTableBody.
 *
 * @var array $estudiantes
 * @var array $filters
 * @var bool  $esDocente
 * @var bool  $sinAsignaciones
 */
  foreach ($estudiantes as $estudiante):
    // El estado viene del ciclo del estudiante (activo, retirado o egresado), no de la
    // persona: retirado y egresado son desenlaces distintos y se distinguen en la tabla.
    $estadoCiclo    = (string) ($estudiante['estado'] ?? 'activo');
    $estadoEtiqueta = ['activo' => 'Activo', 'retirado' => 'Retirado', 'egresado' => 'Egresado'][$estadoCiclo] ?? 'Activo';
    // El color sale de los modificadores que ya existen en la hoja de estilos: retirado es
    // una salida antes de culminar (rojo, como el retiro) y egresado una culminación (informativo).
    $estadoColor    = ['activo' => 'active', 'retirado' => 'inactive', 'egresado' => 'info'][$estadoCiclo] ?? 'active';
  ?>
    <tr>
      <td data-label="">
        <div class="avatar-group">
          <?php
            $avatarSize     = 'md';
            $avatarTone     = $estudiante['avatar_color'] ?? 'primary';
            $avatarSrc      = $estudiante['avatar_img'] ?? '';
            $avatarAlt      = $estudiante['nombre'] ?? '';
            $avatarInitials = $estudiante['iniciales'] ?? 'ES';
            include __DIR__ . '/../../../components/avatar.php';
          ?>
          <span class="avatar-group__name"><?= htmlspecialchars($estudiante['nombre'] ?? '') ?></span>
        </div>
      </td>
      <td class="text-support" data-label="Cédula escolar"><?= htmlspecialchars($estudiante['cedula_escolar'] ?? '') ?></td>
      <?php if (!$esDocente): ?>
        <td class="text-support text-wrap" data-label="Grado y sección">
          <div class="fw-medium text-dark text-break"><?= htmlspecialchars($estudiante['grado'] ?? 'Sin grado') ?></div>
          <div class="text-muted small">
            Sección <?= htmlspecialchars($estudiante['seccion'] ?? '—') ?>
            <?php if (!empty($estudiante['anio_escolar'])): ?>
              · <?= htmlspecialchars($estudiante['anio_escolar']) ?>
            <?php endif; ?>
          </div>
        </td>
        <?php if (!empty($esDirectivo)): ?>
          <td class="text-center" data-label="Turno">
            <span class="badge bg-light text-dark border"><?= htmlspecialchars($estudiante['turno'] ?? '—') ?></span>
          </td>
        <?php endif; ?>
      <?php endif; ?>
      <td class="text-center" data-label="Estado">
        <?php
          $badgeLabel  = $estadoEtiqueta;
          $badgeStatus = $estadoColor;
          include __DIR__ . '/../../../components/status-badge.php';
        ?>
      </td>
      <td class="text-center data-panel__col-acciones" data-label="Acciones">
        <div class="data-panel__actions">
          <a href="<?= BASE_URL ?>estudiantes/show/<?= (int) ($estudiante['id'] ?? 0) ?>?anio=<?= (int) ($filters['anio'] ?? 0) ?>"
             class="action-btn action-btn--view"
             title="Abrir expediente">
            <i class="bi bi-folder2-open" aria-hidden="true"></i>
          </a>
        </div>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$estudiantes): ?>
    <tr>
      <td colspan="<?= $esDocente ? 4 : (!empty($esDirectivo) ? 6 : 5) ?>" class="text-center text-support py-4">
        <?php /* Sin asignaciones la tabla vacía es la respuesta correcta, no un fallo: el
 docente solo ve lo que tiene asignado. Se dice explícitamente para que no
 parezca que el listado no funciona.*/ ?>
        <?php if (!empty($sinAsignaciones)): ?>
          <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
          No tiene estructuras asignadas en el año escolar en curso, así que todavía no le
          corresponden expedientes. Pida al coordinador o a la dirección que registren su
          asignación en Configuración → Asignación Docente.
        <?php else: ?>
          No se encontraron estudiantes con los filtros seleccionados.
        <?php endif; ?>
      </td>
    </tr>
  <?php endif; ?>
