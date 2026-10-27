<?php
/**
 * app/Views/empleados/modals/cerrar-reposo-modal.php
 * Cierre anticipado de un reposo: deja el reposo en estado CERRADO.
 */
ob_start();
?>
<form id="formCerrarReposoEmpleado" novalidate autocomplete="off">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars((string) ($_SESSION['csrf'] ?? '')) ?>">
  <input type="hidden" name="id_reposo" id="cerrarReposoId" value="">

  <p class="text-support mb-3">
    Empleado: <strong id="cerrarReposoEmpleadoNombre">—</strong>
  </p>

  <div class="mb-3">
    <label class="form-label fw-semibold" for="cerrarReposoFechaFin">Fecha de cierre <span class="text-danger">*</span></label>
    <input type="date" class="form-control" name="fecha_fin" id="cerrarReposoFechaFin" required>
    <div class="invalid-feedback" id="errorCerrarReposoFechaFin">Indica la fecha de cierre.</div>
  </div>

  <div class="mb-0">
    <label class="form-label fw-semibold" for="cerrarReposoObservacion">Observación <span class="text-danger">*</span></label>
    <textarea class="form-control" name="observacion" id="cerrarReposoObservacion" rows="3" maxlength="1000" required placeholder="Explica por qué se cierra antes de la fecha prevista."></textarea>
    <div class="invalid-feedback" id="errorCerrarReposoObservacion">Indica por qué se cierra el reposo.</div>
  </div>
</form>
<?php
$modalBody     = ob_get_clean();
$modalId       = 'cerrarReposoEmpleadoModal';
$modalTitle    = 'Cerrar reposo';
$modalSubtitle = 'El reposo quedará como CERRADO y no se podrá editar.';
$modalSize     = 'md';
$modalFooter   = '
  <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
  <button type="button" class="btn btn-danger" id="btnConfirmarCerrarReposo">
    <i class="bi bi-check2-circle me-1"></i> Cerrar reposo
  </button>
';

include __DIR__ . '/../../components/modal.php';
