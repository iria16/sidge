<?php
/**
 * Partial: filas de la nómna de literales.
 *
 * @var int       $idEstructura
 * @var array     $filas
 * @var array     $literales Literales válidos del modelo.
 * @var string    $gradoNombre
 * @var string    $textoGradoAvanza
 * @var bool      $anioCerrado
 */
?>
<?php foreach ($filas as $idx => $est): ?>
  <?php
  $idEst         = $est['id_estudiante'];
  $idMat         = $est['id_matricula'];
  $literal       = $est['literal'];
  $estado        = $est['estado'];
  $idGradoDest   = $est['id_grado_destino'];
  $observaciones = htmlspecialchars($est['observaciones']);
  $nombreEst     = htmlspecialchars($est['nombre_completo']);
  $documento     = htmlspecialchars($est['documento']);
  $cargadoPor    = htmlspecialchars($est['cargado_por']);
  $fechaCarga    = $est['fecha_carga'];
  // El bloqueo es global por año cerrado (el docente siempre tiene acceso a sus secciones)
  $bloquear      = $anioCerrado ?? false;
  ?>
  <tr data-student-row="<?= $idEst ?>"
      data-estado-actual="<?= $estado ?>"
      data-tiene-literal="<?= ($literal !== '') ? '1' : '0' ?>"
      class="fila-estudiante">

    <td class="resultado-col-num text-center text-muted fw-semibold small text-nowrap">
      <?= sprintf('%02d', $idx + 1) ?>
      <input type="hidden" name="estudiantes[<?= $idEst ?>][id_matricula]" value="<?= $idMat ?>">
    </td>

    <td class="resultado-col-estudiante">
      <div class="resultado-estudiante-celda">
        <span class="nombre-estudiante d-block fw-semibold text-dark resultado-estudiante-nombre"><?= $nombreEst ?></span>
        <span class="documento-estudiante text-secondary small font-monospace"><span class="badge bg-light text-secondary border me-1 font-monospace" style="font-size: 0.70rem;">C.E.</span><?= $documento ?></span>
      </div>
    </td>

    <td class="resultado-col-literal text-center text-nowrap">
      <input type="hidden" name="estudiantes[<?= $idEst ?>][literal]" id="input_literal_<?= $idEst ?>" value="<?= $literal ?>">
      <div class="d-inline-flex align-items-center justify-content-center position-relative">
        <select class="form-select form-select-sm select-literal-pill"
                data-id="<?= $idEst ?>"
                data-literal="<?= $literal ?>"
                <?= $bloquear ? 'disabled' : '' ?>
                aria-label="Literal para <?= $nombreEst ?>">
          <option value="" <?= ($literal === '') ? 'selected' : '' ?>>--</option>
          <?php foreach ($literales as $lit): ?>
            <option value="<?= htmlspecialchars($lit) ?>" <?= ($literal === $lit) ? 'selected' : '' ?>><?= htmlspecialchars($lit) ?></option>
          <?php endforeach; ?>
        </select>
        <span class="indicador-guardado ms-1 text-<?= $literal !== '' ? 'success' : 'muted' ?>"
              data-guardado-indicador
              data-estado-guardado="<?= $literal !== '' ? 'guardado' : '' ?>"
              title="<?= $literal !== '' ? 'Guardado' : 'Sin literal' ?>">
          <i class="bi <?= $literal !== '' ? 'bi-check2' : 'bi-dash' ?>" aria-hidden="true"></i>
        </span>
      </div>
      <?php if ($literal !== '' && $fechaCarga): ?>
        <div class="text-secondary small mt-1 text-nowrap" style="font-size: 0.70rem;" title="Cargado por <?= $cargadoPor ?> el <?= $fechaCarga ?>">
          <i class="bi bi-calendar3 me-1" aria-hidden="true"></i><?= $fechaCarga ?>
        </div>
      <?php endif; ?>
    </td>

    <td class="resultado-col-decision">
      <input type="hidden" name="estudiantes[<?= $idEst ?>][estado_resultado]"
             id="input_estado_<?= $idEst ?>" value="<?= $estado ?>">
      <input type="hidden" name="estudiantes[<?= $idEst ?>][id_grado_destino]"
             id="input_grado_<?= $idEst ?>" value="<?= $idGradoDest ?>">

      <div id="badge_estado_<?= $idEst ?>" class="mb-1">
        <?php if ($estado === 'PROMOVIDO'): ?>
          <span class="decision-badge decision-badge--promovido">
            <i class="bi bi-check-circle-fill" aria-hidden="true"></i> Promovido
          </span>
        <?php elseif ($estado === 'NO_PROMOVIDO'): ?>
          <span class="decision-badge decision-badge--no-promovido">
            <i class="bi bi-x-circle-fill" aria-hidden="true"></i> No promovido
          </span>
        <?php else: ?>
          <span class="decision-badge decision-badge--pendiente">
            <i class="bi bi-clock-fill" aria-hidden="true"></i> Pendiente
          </span>
        <?php endif; ?>
      </div>

      <div id="proyeccion_grado_<?= $idEst ?>" class="resultado-proyeccion text-secondary small">
        <?php if ($estado === 'PROMOVIDO'): ?>
          <div class="text-secondary"><i class="bi bi-arrow-right-short text-success"></i> Avanza: <strong><?= htmlspecialchars($textoGradoAvanza) ?></strong></div>
        <?php elseif ($estado === 'NO_PROMOVIDO'): ?>
          <div class="text-secondary"><i class="bi bi-arrow-repeat text-danger"></i> Permanece: <strong><?= htmlspecialchars($gradoNombre) ?></strong></div>
        <?php else: ?>
          <div class="text-muted"><i class="bi bi-dash text-secondary"></i> Sin evaluar</div>
        <?php endif; ?>
      </div>
    </td>

    <td class="resultado-col-obs">
      <input type="text" name="estudiantes[<?= $idEst ?>][observaciones]"
             class="form-control form-control-sm input-obs-pedagogica"
             value="<?= $observaciones ?>"
             placeholder="Observación pedagógica..."
             maxlength="255"
             data-observacion
             <?= $bloquear ? 'readonly' : '' ?>>
      <span class="text-danger small mt-1 d-block fw-medium" data-obs-requerida <?= ($literal === 'E' && !$bloquear) ? '' : 'hidden' ?> style="font-size: 0.72rem;">
        * Obligatoria para literal E
      </span>
      <div class="invalid-feedback d-block" data-error-fila hidden></div>
    </td>

    <td class="resultado-col-boleta text-center text-nowrap" id="boleta_accion_<?= $idEst ?>">
      <?php
      $esSextoPromovido = \App\Models\GradeCertificateModel::esGradoSexto($gradoNombre ?? '')
          && in_array(strtoupper(trim($literal)), ['A', 'B', 'C', 'D'], true);
      ?>
      <?php if ($esSextoPromovido): ?>
        <span class="badge bg-light text-muted border py-1 px-2 d-inline-flex align-items-center gap-1"
              title="El Certificado de Culminación de Primaria (Egreso) es emitido por Secretaría">
          <i class="bi bi-mortarboard text-primary" aria-hidden="true"></i>
          <span>Emisión en Secretaría</span>
        </span>
      <?php elseif ($literal !== ''): ?>
        <button type="button"
                class="btn btn-sm btn-outline-primary py-1 px-2 d-inline-flex align-items-center gap-1 btn-preview-certificado"
                data-url-preview="<?= BASE_URL ?>certificadoCalificaciones/generarIndividual?id_estudiante=<?= $idEst ?>&id_estructura=<?= $idEstructura ?>"
                data-titulo="Certificado de Calificaciones"
                data-subtitulo="<?= htmlspecialchars($nombreEst . ' · ' . $documento . ' (' . $literal . ' - ' . $estado . ')') ?>"
                title="Visualizar e imprimir Certificado de Calificaciones">
          <i class="bi bi-file-earmark-text" aria-hidden="true"></i>
          <span>Visualizar</span>
        </button>
      <?php else: ?>
        <span class="text-muted small" title="Requiere asignar literal">—</span>
      <?php endif; ?>
    </td>
  </tr>
<?php endforeach; ?>
