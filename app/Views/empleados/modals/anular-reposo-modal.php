<?php
/**
 * app/Views/empleados/modals/anular-reposo-modal.php
 * Anulación de un reposo registrado por error: queda en estado ANULADO.
 */
ob_start();
?>
<form id="formAnularReposoEmpleado" novalidate autocomplete="off">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars((string) ($_SESSION['csrf'] ?? '')) ?>">
  <input type="hidden" name="id_reposo" id="anularReposoId" value="">

  <p class="text-support mb-3">
    Empleado: <strong id="anularReposoEmpleadoNombre">—</strong>
  </p>

  <div class="mb-0">
    <label class="form-label fw-semibold" for="anularReposoObservacion">Observación <span class="text-danger">*</span></label>
    <textarea class="form-control" name="observacion" id="anularReposoObservacion" rows="3" maxlength="1000" required placeholder="Explica por qué se anula este reposo."></textarea>
    <div class="invalid-feedback" id="errorAnularReposoObservacion">Indica por qué se anula el reposo.</div>
  </div>
</form>
<?php
$modalBody     = ob_get_clean();
$modalId       = 'anularReposoEmpleadoModal';
$modalTitle    = 'Anular reposo';
$modalSubtitle = 'El reposo quedará como ANULADO y no contará para vigencia ni traslapes.';
$modalSize     = 'md';
$modalFooter   = '
  <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
  <button type="button" class="btn btn-danger" id="btnConfirmarAnularReposo">
    <i class="bi bi-x-circle me-1"></i> Anular reposo
  </button>
';

include __DIR__ . '/../../components/modal.php';
