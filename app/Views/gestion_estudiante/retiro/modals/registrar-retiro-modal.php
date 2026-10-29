<?php
declare(strict_types=1);

/**
 * Modal: retiro/modals/registrar-retiro-modal.php
 * Alta de una solicitud de retiro (solo secretaria).
 *
 * @var bool  $puedeRegistrar
 * @var array<int, array{id: int|string, nombre: string}> $motivos
 */
  if ($puedeRegistrar):
      $hayMotivos = !empty($motivos);
  ob_start();
  ?>
  <form id="formRegistrarRetiro" novalidate>
    <!-- La matrícula viaja oculta: la secretaria no escribe un id, elige de la lista. -->
    <input type="hidden" id="retiroIdMatricula" name="id_matricula" value="">

    <div class="mb-3">
      <label for="retiroBuscarEstudiante" class="label-sigde">Cédula del estudiante</label>
      <div class="input-icon-group">
        <i class="bi bi-person-vcard" aria-hidden="true"></i>
        <input type="search" class="form-control" id="retiroBuscarEstudiante"
               name="q" placeholder="Ej: 11625863"
               autocomplete="off">
      </div>
      <div class="invalid-feedback" id="retiroBuscarFeedback">Busque y seleccione un estudiante.</div>
    </div>

    <!-- Lista de coincidencias: la arma el JS con lo que devuelve buscarEstudiante. -->
    <div id="retiroResultados" class="mb-3 d-none"></div>

    <!-- Datos del estudiante ya localizado: son los que se revisan antes de confirmar. -->
    <div id="retiroEstudianteDatos" class="mb-3 d-none">
      <div class="detail-section__label"><i class="bi bi-person-vcard"></i>Estudiante seleccionado</div>
      <div class="detail-list__item">
        <div class="label-sigde">Nombre</div>
        <div class="detail-list__value" id="retiroDatosNombre">—</div>
      </div>
      <div class="detail-list__item">
        <div class="label-sigde">Documento</div>
        <div class="detail-list__value" id="retiroDatosCedula">—</div>
      </div>
      <div class="detail-list__item">
        <div class="label-sigde">Grado y sección</div>
        <div class="detail-list__value" id="retiroDatosGrado">—</div>
      </div>
      <div class="detail-list__item">
        <div class="label-sigde">Año escolar</div>
        <div class="detail-list__value" id="retiroDatosAnio">—</div>
      </div>
    </div>

    <div class="mb-3">
      <label for="retiroFecha" class="label-sigde">Fecha del retiro</label>
      <div class="input-icon-group">
        <i class="bi bi-calendar-event" aria-hidden="true"></i>
        <input type="date" class="form-control" id="retiroFecha" name="fecha_retiro"
               value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
      </div>
      <div class="invalid-feedback" id="retiroFechaFeedback">Indique la fecha del retiro.</div>
    </div>

    <div class="mb-3">
      <label for="retiroRepresentante" class="label-sigde">Representante legal o persona autorizada que retira</label>
      <div class="input-icon-group">
        <i class="bi bi-person-check" aria-hidden="true"></i>
        <select class="form-select" id="retiroRepresentante" name="id_persona_representante" required disabled>
          <option value="" selected disabled>Busque un estudiante primero</option>
        </select>
      </div>
      <div class="invalid-feedback" id="retiroRepresentanteFeedback">Seleccione quién retira al estudiante.</div>
    </div>

    <div class="mb-3">
      <label for="retiroMotivo" class="label-sigde">Motivo del retiro</label>
      <div class="input-icon-group">
        <i class="bi bi-file-earmark-text" aria-hidden="true"></i>
        <select class="form-select" id="retiroMotivo" name="id_motivo_retiro" required <?= $hayMotivos ? '' : 'disabled' ?>>
          <option value="" selected disabled>Seleccionar motivo</option>
          <?php foreach ($motivos as $motivo): ?>
            <option value="<?= (int) $motivo['id'] ?>">
              <?= htmlspecialchars($motivo['nombre']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if (!$hayMotivos): ?>
        <div class="form-text text-danger">No hay motivos de retiro configurados.</div>
      <?php else: ?>
        <div class="invalid-feedback">Seleccione el motivo.</div>
      <?php endif; ?>
    </div>

    <div class="mb-0">
      <label for="retiroObservaciones" class="label-sigde">Observaciones</label>
      <textarea class="form-control" id="retiroObservaciones" name="observaciones"
                rows="3" maxlength="500" placeholder="Detalle opcional de la solicitud"></textarea>
    </div>

    <div class="info-alert mt-3">
      <span class="info-alert__icon"><i class="bi bi-info-circle-fill" aria-hidden="true"></i></span>
      <div>
        <p class="info-alert__title">Queda pendiente de aprobación</p>
        <p class="info-alert__text">El retiro se registra como pendiente; el directivo lo aprueba o rechaza desde el listado.</p>
      </div>
    </div>
  </form>
  <?php
      $modalBody = ob_get_clean();

      ob_start();
  ?>
  <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
  <button type="submit" form="formRegistrarRetiro" id="btnRegistrarRetiro" class="btn btn-primary"
          <?= $hayMotivos ? '' : 'disabled' ?>>
    <i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i> Registrar retiro
  </button>
  <?php
      $modalFooter = ob_get_clean();

      $modalId       = 'registrarRetiroModal';
      $modalTitle    = 'Registrar Retiro';
      $modalSubtitle = 'Busque al estudiante, revise sus datos y luego indique el motivo.';
      include __DIR__ . '/../../../components/modal.php';
      unset($modalBody, $modalFooter, $modalId, $modalTitle, $modalSubtitle, $hayMotivos);
  endif;
