<?php
/**
 * app/Views/empleados/modals/estado-modal.php
 * Cambio de estado del empleado con motivo, fecha efectiva e historial.
 */
ob_start();
?>
<form id="formEstadoEmpleado" novalidate autocomplete="off">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars((string) ($_SESSION['csrf'] ?? '')) ?>">
  <input type="hidden" name="id" id="estadoEmpleadoId" value="">
  <input type="hidden" name="nuevo_estado" id="estadoEmpleadoNuevo" value="">
  <input type="hidden" name="confirmar" id="estadoEmpleadoConfirmar" value="1">

  <div class="info-alert mb-3">
    <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
      <span>Empleado</span>
      <strong id="estadoEmpleadoNombre">—</strong>
    </div>
    <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mt-1">
      <span>Estado actual</span>
      <strong id="estadoEmpleadoActual">—</strong>
    </div>
    <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mt-1">
      <span>Nuevo estado</span>
      <strong id="estadoEmpleadoDestino">—</strong>
    </div>
  </div>

  <div id="estadoAdvertencia" class="security-alert mb-3" hidden>
    <div class="d-flex align-items-start gap-2">
      <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
      <div>
        <strong id="estadoAdvertenciaTexto">Este cambio requiere confirmación.</strong>
        <ul class="mb-0 mt-1 ps-3" id="estadoAsignacionesLista"></ul>
        <p class="mb-0 mt-1" id="estadoReposoAviso" hidden></p>
      </div>
    </div>
  </div>

  <div class="row g-2 mb-3">
    <div class="col-md-5">
      <label class="form-label fw-semibold" for="estadoFechaEfectiva">Fecha efectiva <span class="text-danger">*</span></label>
      <input type="date" class="form-control" name="fecha_efectiva" id="estadoFechaEfectiva" required>
      <div class="invalid-feedback" id="errorEstadoFechaEfectiva">Indica la fecha efectiva.</div>
    </div>
    <div class="col-md-7">
      <label class="form-label fw-semibold" for="estadoMotivo">Motivo <span class="text-danger">*</span></label>
      <input type="text" class="form-control" name="motivo" id="estadoMotivo" maxlength="200" required placeholder="Ej. Jubilación, renuncia, cambio de cargo.">
      <div class="invalid-feedback" id="errorEstadoMotivo">Indica el motivo del cambio.</div>
    </div>
  </div>

  <p class="text-support small mb-0" id="estadoNotaLiberacion">
    Si el empleado tiene asignaciones activas, se cerrarán en la fecha efectiva. No se borran.
  </p>
</form>
<?php
$modalBody     = ob_get_clean();
$modalId       = 'estadoEmpleadoModal';
$modalTitle    = 'Cambiar estado del empleado';
$modalSubtitle = 'El cambio queda registrado en el historial del expediente.';
$modalSize     = 'md';
$modalFooter   = '
  <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
  <button type="button" class="btn btn-primary" id="btnGuardarEstadoEmpleado">
    <i class="bi bi-check-lg me-1"></i> Guardar cambio
  </button>
';

include __DIR__ . '/../../components/modal.php';
