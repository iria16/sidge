<?php
/**
 * app/Views/empleados/modals/reposo-modal.php
 * Modal para Registrar o Editar Reposo Médico de un Empleado.
 *
 * Utiliza components/modal.php para mantener consistencia con el diseño del sistema.
 */

ob_start();
?>
<form id="formReposoEmpleado" novalidate autocomplete="off">
  <input type="hidden" name="id_empleado" id="reposoEmpleadoId" value="">
  <input type="hidden" name="id_reposo" id="reposoId" value="">

  <div class="mb-3">
    <label class="form-label fw-semibold" for="reposoEmpleadoNombre">Empleado</label>
    <input type="text" class="form-control bg-light" id="reposoEmpleadoNombre" readonly value="">
  </div>

  <div class="row g-2 mb-3">
    <div class="col-md-6">
      <label class="form-label fw-semibold" for="reposoFechaInicio">Fecha de inicio <span class="text-danger">*</span></label>
      <input type="date" class="form-control" name="fecha_inicio" id="reposoFechaInicio" required>
      <div class="invalid-feedback" id="errorReposoFechaInicio">Indica la fecha de inicio.</div>
    </div>
    <div class="col-md-6">
      <label class="form-label fw-semibold" for="reposoFechaFin">Fecha de fin</label>
      <input type="date" class="form-control" name="fecha_fin" id="reposoFechaFin">
      <div class="form-text text-muted small">Opcional. Dejar vacío si el reposo es abierto.</div>
      <div class="invalid-feedback" id="errorReposoFechaFin"></div>
    </div>
  </div>

  <div class="mb-3">
    <label class="form-label fw-semibold" for="reposoMotivo">Motivo o descripción</label>
    <textarea class="form-control" name="motivo" id="reposoMotivo" rows="2" maxlength="200" placeholder="Ej. Licencia médica, descanso postnatal, etc."></textarea>
    <div class="form-text text-muted small">Máximo 200 caracteres. No incluir diagnósticos médicos confidenciales.</div>
    <div class="invalid-feedback" id="errorReposoMotivo"></div>
  </div>
</form>
<?php
$modalBody     = ob_get_clean();
$modalId       = 'reposoEmpleadoModal';
$modalTitle    = 'Registrar Reposo';
$modalSubtitle = 'Gestión de licencias y permisos médicos del personal';
$modalSize     = 'md';
$modalFooter   = '
  <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
  <button type="button" class="btn btn-primary" id="btnGuardarReposo">
    <i class="bi bi-check-lg me-1"></i> Guardar Reposo
  </button>
';

include __DIR__ . '/../../components/modal.php';
