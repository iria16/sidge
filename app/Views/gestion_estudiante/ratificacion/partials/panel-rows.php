<?php
declare(strict_types=1);

/**
 * Partial: ratificacion/partials/panel-rows.php
 * Filas del listado. Se incluye dentro de un ob_start() para armar
 * $panelTableBody.
 *
 * @var array<int, array<string, mixed>> $ratificaciones
 */
  foreach ($ratificaciones as $item):
    $esRatificado = ($item['estado'] ?? '') === 'ratificado';
    $estudianteId = (int) ($item['id'] ?? 0);

    /* El estado refleja la matrícula del año en curso: pendiente, ratificado,
       retirado o egresado. */
    $estadoItem = (string) ($item['estado'] ?? 'pendiente');
    $cerrado    = in_array($estadoItem, ['retirado', 'egresado'], true);
?>

    <tr>
      <td>
        <div class="avatar-group">
          <?php
            $avatarSize     = 'md';
            $avatarTone     = $item['avatar_color'] ?? 'primary';
            $avatarSrc      = $item['avatar_img'] ?? '';
            $avatarAlt      = $item['nombre'] ?? '';
            $avatarInitials = $item['iniciales'] ?? 'ES';
            include __DIR__ . '/../../../components/avatar.php';
          ?>
          <div>
            <div class="avatar-group__name"><?= htmlspecialchars($item['nombre'] ?? '') ?></div>
            <div class="avatar-group__meta"><?= htmlspecialchars($item['cedula'] ?? '') ?></div>
          </div>
        </div>
      </td>
      <td class="text-support"><?= htmlspecialchars($item['grado_actual'] ?? '') ?></td>
      <td class="text-support"><?= htmlspecialchars($item['seccion'] ?? '') ?></td>
      <td class="fw-semibold">
        <div><?= htmlspecialchars($item['proximo_grado'] ?? '—') ?></div>
        <?php if (!empty($item['promocion_badge'])): ?>
          <span class="badge <?= ($item['promocion_tipo'] ?? '') === 'promovido' ? 'bg-success-subtle text-success border border-success-subtle' : (($item['promocion_tipo'] ?? '') === 'no_promovido' ? 'bg-danger-subtle text-danger border border-danger-subtle' : 'bg-warning-subtle text-warning-emphasis border border-warning-subtle') ?> py-0 px-2 mt-1 badge-promocion">
            <?= htmlspecialchars($item['promocion_badge']) ?>
          </span>
        <?php endif; ?>
      </td>
      <td>
        <?php
          /* Se muestra el proceso que corresponde a ESTE estudiante, ya evaluado
 por el modelo con la misma pregunta que usa el guardado. La secretaria
 no tiene que deducirlo: si dice "Inscribir", el botón de ratificar no
 está porque ratificar no es su proceso.*/
          $etiquetas = [
              'ratificado'  => ['Ratificado', 'active',  'Ya tiene su continuación registrada'],
              'retirado'    => ['Retirado',   'inactive', 'Se retiró antes de culminar el año'],
              'egresado'    => ['Egresado',   'info',    'Culminó el ciclo completo: su año está cerrado'],
              'pendiente'   => ['Pendiente',  'pending', 'Ya estaba inscripto: le corresponde ratificación'],
          ];
          $etiqueta = $etiquetas[$estadoItem] ?? $etiquetas['pendiente'];
          $badgeLabel  = $etiqueta[0];
          $badgeStatus = $etiqueta[1];
          if ($estadoItem === 'pendiente' && ($item['promocion_tipo'] ?? '') === 'sin_literal') {
              $badgeLabel  = 'Sin literal';
              $badgeStatus = 'pending';
          }
          include __DIR__ . '/../../../components/status-badge.php';
        ?>
      </td>
      <td class="text-center">
        <div class="data-panel__actions">
          <?php if ($cerrado): ?>
            <button type="button" class="action-btn" disabled
                    title="<?= $estadoItem === 'egresado'
                        ? 'Ya egresó: su año está cerrado y no corresponde ratificarlo'
                        : 'Se retiró antes de culminar el año: su registro ya tiene ese estado' ?>">
              <i class="bi bi-<?= $estadoItem === 'egresado' ? 'award' : 'box-arrow-right' ?>"></i>
            </button>
          <?php elseif ($esRatificado): ?>
            <?php /* El botón de VER solo existe cuando ya se ratificó, porque antes
 no hay una ratificación que ver: se vería el detalle de una fila que
 todavía no existe. También cuando el año se cerró, porque entonces el
 botón sale deshabilitado y con la explicación en el title.*/ ?>
            <button type="button" class="action-btn action-btn--view btn-ver-ratificacion"
                    data-id="<?= $estudianteId ?>" title="Ver detalle de la ratificación">
              <i class="bi bi-eye"></i>
            </button>
          <?php elseif (($item['promocion_tipo'] ?? '') === 'sin_literal'): ?>
            <button type="button"
                    class="btn btn-secondary rounded-pill px-4 py-1 fw-semibold disabled"
                    disabled
                    title="El estudiante no tiene literal cargado por la docente. No se puede ratificar sin resultado.">
              Ratificar
            </button>
          <?php else: ?>
            <button type="button"
                    class="btn btn-primary rounded-pill px-4 py-1 fw-semibold btn-ratificar-modal"
                    title="Ratificar estudiante"
                    data-id="<?= $estudianteId ?>"
                    data-anio-origen="<?= htmlspecialchars($item['anio_actual'] ?? '—') ?>"
                    data-grado-origen-id="<?= (int) ($item['id_grado'] ?? 0) ?>"
                    data-grado-origen="<?= htmlspecialchars($item['grado_actual'] ?? '—') ?>"
                    data-grado-destino-id="<?= (int) ($item['proximo_grado_id'] ?? 0) ?>"
                    data-grado-destino="<?= htmlspecialchars($item['proximo_grado'] ?? '—') ?>"
                    data-seccion-destino-id="<?= (int) ($item['id_seccion'] ?? 0) ?>"
                    data-turno-destino-id="<?= (int) ($item['id_turno'] ?? 0) ?>"
                    data-promocion-tipo="<?= htmlspecialchars($item['promocion_tipo'] ?? 'pendiente') ?>"
                    data-promocion-badge="<?= htmlspecialchars($item['promocion_badge'] ?? '') ?>">
              Ratificar
            </button>
          <?php endif; ?>

          <?php /* Solo queda la acción de esta pantalla. El enlace al expediente
 se quitó porque el botón de ver ya lleva al detalle del alumno, y dos
 botones con el mismo icono a un click de distancia hacen dudar de
 cuál ver. El expediente sigue estando, desde el listado de la sección
 Consulta académica → Estudiantes.*/ ?>
        </div>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$ratificaciones): ?>
    <tr>
      <td colspan="6" class="text-center text-support py-4">
        No se encontraron estudiantes con los filtros seleccionados.
      </td>
    </tr>
  <?php endif; ?>
