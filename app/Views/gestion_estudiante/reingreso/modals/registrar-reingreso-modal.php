<?php
declare(strict_types=1);

/**
 * Modal: reingreso/modals/registrar-reingreso-modal.php
 * Formalización del reingreso de un estudiante retirado (solo secretaria).
 *
 * @var array<int, array{id_estructura_academica: int, nombre: string, cupos_disponibles: int}> $estructuras
 * @var array<int, array{id_institucion: int, nombre: string}> $instituciones
 */
$estructuras   = $estructuras ?? [];
$instituciones = $instituciones ?? [];

ob_start();
?>
<form id="formRegistrarReingreso" novalidate>
  <input type="hidden" id="reingresoIdEstudiante" name="id_estudiante" value="">
  <input type="hidden" name="csrf" value="<?= csrfToken() ?>">

  <!-- Buscador por cédula -->
  <div class="mb-3">
    <label for="reingresoBuscarEstudiante" class="label-sigde fw-semibold">Cédula o Cédula Escolar del estudiante <span class="text-danger">*</span></label>
    <div class="input-icon-group">
      <i class="bi bi-person-vcard" aria-hidden="true"></i>
      <input type="search" class="form-control" id="reingresoBuscarEstudiante"
             name="q" placeholder="Ej: 11625863 o V11625863136"
             autocomplete="off">
    </div>
    <div class="invalid-feedback" id="reingresoBuscarFeedback">Busque y seleccione un estudiante con retiro aprobado.</div>
  </div>

  <!-- Resultados de búsqueda dinámicos -->
  <div id="reingresoResultados" class="mb-3 d-none"></div>

  <!-- Ficha de datos del estudiante seleccionado -->
  <div id="reingresoEstudianteDatos" class="card bg-light border-0 mb-3 d-none">
    <div class="card-body p-3">
      <div class="d-flex align-items-center gap-2 mb-2 text-primary fw-semibold small">
        <i class="bi bi-person-check-fill fs-6"></i>
        <span>Estudiante seleccionado para reincorporación</span>
      </div>
      <div class="row g-2">
        <div class="col-md-6">
          <span class="text-secondary small d-block">Nombre completo</span>
          <strong class="text-dark" id="reingresoDatosNombre">—</strong>
          <div class="text-muted small" id="reingresoDatosCedula">—</div>
        </div>
        <div class="col-md-6">
          <span class="text-secondary small d-block">Antecedente de retiro</span>
          <div class="small">
            <span class="text-dark fw-medium" id="reingresoDatosGrado">—</span><br>
            <span class="text-secondary" id="reingresoDatosRetiro">—</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Campos de formalización (se activan al elegir estudiante) -->
  <div id="reingresoFormCampos" class="d-none">
    <div class="row g-3">
      <!-- Grado y sección a cursar -->
      <div class="col-12">
        <label for="reingresoEstructura" class="label-sigde fw-semibold">Grado y Sección a Cursar (Año Escolar Activo) <span class="text-danger">*</span></label>
        <div class="input-icon-group">
          <i class="bi bi-diagram-3" aria-hidden="true"></i>
          <select class="form-select" id="reingresoEstructura" name="id_estructura_academica" required>
            <option value="" selected disabled>Seleccione grado y sección...</option>
            <?php foreach ($estructuras as $est): 
              $ord = \App\Support\Instituciones::ordinalGrado((string) ($est['grado'] ?? ''));
            ?>
              <option value="<?= (int) $est['id_estructura_academica'] ?>"
                      data-ordinal="<?= (int) ($ord ?? 0) ?>"
                      data-disponibles="<?= (int) $est['disponibles'] ?>"
                      <?= ($est['disponibles'] <= 0) ? 'disabled' : '' ?>>
                <?= htmlspecialchars($est['nombre']) ?> (<?= (int) $est['disponibles'] ?> cupos disponibles)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="invalid-feedback">Debe seleccionar una sección con cupo disponible.</div>
        <div id="reingresoAvisoCupos" class="alert alert-warning py-2 px-3 small d-none mt-2 d-flex align-items-center gap-2">
          <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
          <span id="reingresoAvisoCuposTexto"></span>
        </div>
      </div>

      <!-- Fecha de reincorporación -->
      <div class="col-md-6">
        <label for="reingresoFecha" class="label-sigde fw-semibold">Fecha de reincorporación <span class="text-danger">*</span></label>
        <div class="input-icon-group">
          <i class="bi bi-calendar-event" aria-hidden="true"></i>
          <input type="date" class="form-control" id="reingresoFecha" name="fecha_reincorporacion"
                 value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="invalid-feedback">Indique la fecha de reincorporación (no futura).</div>
      </div>

      <!-- Plantel de procedencia (sin la palabra opcional) -->
      <div class="col-md-6">
        <label for="reingresoInstitucion" class="label-sigde fw-semibold">Plantel de procedencia</label>
        <div class="input-icon-group">
          <i class="bi bi-building" aria-hidden="true"></i>
          <select class="form-select" id="reingresoInstitucion" name="id_institucion_procedencia">
            <option value="">Retorno directo / Sin plantel intermedio</option>
            <?php foreach ($instituciones as $inst): ?>
              <option value="<?= (int) $inst['id_institucion'] ?>">
                <?= htmlspecialchars($inst['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- Motivo del reingreso -->
      <div class="col-12">
        <label for="reingresoMotivo" class="label-sigde fw-semibold">Motivo del reingreso <span class="text-danger">*</span></label>
        <div class="input-icon-group">
          <i class="bi bi-file-earmark-text" aria-hidden="true"></i>
          <input type="text" class="form-control" id="reingresoMotivo" name="motivo_reingreso"
                 placeholder="Ej: Retorno a la comunidad / Solicitud formal de reincorporación" required maxlength="255">
        </div>
        <div class="invalid-feedback">Especifique el motivo de reincorporación (mínimo 5 caracteres).</div>
      </div>

      <!-- Observaciones -->
      <div class="col-12">
        <label for="reingresoObservaciones" class="label-sigde fw-semibold">Observaciones</label>
        <textarea class="form-control" id="reingresoObservaciones" name="observaciones"
                  rows="2" maxlength="500" placeholder="Detalle pedagógico, recaudos actualizados o notas de la resolución"></textarea>
      </div>
    </div>
  </div>
</form>
<?php
$modalBody = ob_get_clean();

ob_start();
?>
<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
<button type="submit" form="formRegistrarReingreso" id="btnGuardarReingreso" class="btn btn-primary" disabled>
  <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i> Formalizar Reingreso
</button>
<?php
$modalFooter = ob_get_clean();

$modalId       = 'registrarReingresoModal';
$modalTitle    = 'Registrar Reingreso Escolar';
$modalSubtitle = 'Busque al estudiante por cédula o cédula escolar para verificar su retiro y formalizar el retorno.';
$modalSize     = 'lg'; // Modal amplio con excelente espaciado y sin scroll vertical estrecho
include __DIR__ . '/../../../components/modal.php';
unset($modalBody, $modalFooter, $modalId, $modalTitle, $modalSubtitle, $modalSize);
?>
