<?php
/**
 * Partial: filas de la nómina de literales.
 *
 * @var array  $filas
 * @var array  $literales Literales válidos del modelo.
 * @var string $gradoNombre
 * @var string $textoGradoAvanza
 */
?>
<?php foreach ($filas as $idx => $est): ?>
  <?php
  $idEst          = $est['id_estudiante'];
  $idMat          = $est['id_matricula'];
  $literal        = $est['literal'];
  $estado         = $est['estado'];
  $idGradoDest    = $est['id_grado_destino'];
  $observaciones  = htmlspecialchars($est['observaciones']);
  $nombreEst      = htmlspecialchars($est['nombre_completo']);
  $documento      = htmlspecialchars($est['documento']);
  $cargadoPor     = htmlspecialchars($est['cargado_por']);
  $fechaCarga     = $est['fecha_carga'];
  $estaConfirmado = $est['esta_confirmado'];
  $bloquear       = $est['bloquear'];
  ?>
  <tr data-student-row="<?= $idEst ?>"
      data-estado-actual="<?= $estado ?>"
      data-tiene-literal="<?= ($literal !== '') ? '1' : '0' ?>"
      data-esta-confirmado="<?= $estaConfirmado ? '1' : '0' ?>"
      class="fila-estudiante <?= $estaConfirmado ? 'fila-confirmada' : '' ?>">

    <td class="resultado-col-num text-center text-muted fw-semibold small text-nowrap">
      <?= sprintf('%02d', $idx + 1) ?>
      <input type="hidden" name="estudiantes[<?= $idEst ?>][id_matricula]" value="<?= $idMat ?>">
    </td>

    <td class="celda-wrap">
      <div class="d-flex align-items-center gap-2">
        <?php
        $avatarInitials = $est['avatar_iniciales'];
        $avatarSize     = 'sm';
        $avatarTone     = 'primary';
        include __DIR__ . '/../../components/avatar.php';
        ?>
        <div class="resultado-estudiante-celda">
          <strong class="nombre-estudiante d-block text-dark resultado-estudiante-nombre"><?= $nombreEst ?></strong>
          <span class="documento-estudiante text-secondary small"><?= $documento ?></span>
        </div>
      </div>
    </td>

    <td class="text-center text-nowrap">
      <input type="hidden" name="estudiantes[<?= $idEst ?>][literal]" id="input_literal_<?= $idEst ?>" value="<?= $literal ?>">
      <div class="d-inline-flex align-items-center gap-1 position-relative">
        <select class="select-literal-pill"
                data-id="<?= $idEst ?>"
                data-literal="<?= $literal ?>"
                <?= $bloquear ? 'disabled' : '' ?>
                aria-label="Literal para <?= $nombreEst ?>">
          <option value="" <?= ($literal === '') ? 'selected' : '' ?>>--</option>
          <?php foreach ($literales as $lit): ?>
            <option value="<?= htmlspecialchars($lit) ?>" <?= ($literal === $lit) ? 'selected' : '' ?>><?= htmlspecialchars($lit) ?></option>
          <?php endforeach; ?>
        </select>
        <span class="badge bg-light text-secondary border px-1 py-1 indicador-guardado"
              data-guardado-indicador
              data-estado-guardado="<?= $literal !== '' ? 'guardado' : '' ?>"
              title="<?= $literal !== '' ? 'Guardado' : 'Sin literal' ?>">
          <i class="bi <?= $literal !== '' ? 'bi-check2' : 'bi-dash' ?>" aria-hidden="true"></i>
        </span>
      </div>
      <?php if ($literal !== ''): ?>
        <span class="d-block text-secondary small mt-1" title="Cargado por <?= $cargadoPor ?> el <?= $fechaCarga ?>">
          <i class="bi bi-calendar-check me-1" aria-hidden="true"></i><?= $fechaCarga ?>
        </span>
      <?php endif; ?>
    </td>

    <td class="celda-wrap">
      <input type="hidden" name="estudiantes[<?= $idEst ?>][estado_resultado]"
             id="input_estado_<?= $idEst ?>" value="<?= $estado ?>">
      <input type="hidden" name="estudiantes[<?= $idEst ?>][id_grado_destino]"
             id="input_grado_<?= $idEst ?>" value="<?= $idGradoDest ?>">

      <div id="badge_estado_<?= $idEst ?>" class="mb-1">
        <?php if ($estado === 'PROMOVIDO'): ?>
          <span class="decision-badge decision-badge--promovido">
            <i class="bi bi-check-circle-fill" aria-hidden="true"></i> PROMOVIDO
          </span>
        <?php elseif ($estado === 'NO_PROMOVIDO'): ?>
          <span class="decision-badge decision-badge--no-promovido">
            <i class="bi bi-x-circle-fill" aria-hidden="true"></i> NO PROMOVIDO
          </span>
        <?php else: ?>
          <span class="decision-badge decision-badge--pendiente">
            <i class="bi bi-clock-fill" aria-hidden="true"></i> PENDIENTE
          </span>
        <?php endif; ?>
      </div>

      <div id="proyeccion_grado_<?= $idEst ?>" class="text-secondary small resultado-proyeccion resultado-estudiante-celda">
        <?php if ($estado === 'PROMOVIDO'): ?>
          <div class="text-primary"><i class="bi bi-arrow-right me-1"></i>Avanza: <strong><?= htmlspecialchars($textoGradoAvanza) ?></strong></div>
        <?php elseif ($estado === 'NO_PROMOVIDO'): ?>
          <div class="text-danger"><i class="bi bi-arrow-repeat me-1"></i>Permanece: <strong><?= htmlspecialchars($gradoNombre) ?></strong></div>
        <?php else: ?>
          <div class="text-muted"><i class="bi bi-dash-circle me-1"></i>Por evaluar</div>
        <?php endif; ?>
      </div>
    </td>

    <td class="celda-wrap">
      <input type="text" name="estudiantes[<?= $idEst ?>][observaciones]"
             class="form-control form-control-sm input-obs-pedagogica"
             value="<?= $observaciones ?>"
             placeholder="Ingresar observación pedagógica..."
             maxlength="255"
             data-observacion
             <?= $bloquear ? 'readonly' : '' ?>>
      <span class="text-danger small" data-obs-requerida <?= ($literal === 'E' && !$bloquear) ? '' : 'hidden' ?>>
        Obligatoria para literal E
      </span>
      <div class="invalid-feedback d-block" data-error-fila hidden></div>
    </td>
  </tr>
<?php endforeach; ?>
