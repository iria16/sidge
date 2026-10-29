<?php
declare(strict_types=1);

/**
 * Modal: anios_escolares/modals/reabrir-anio-modal.php
 * Reapertura de un período cerrado (solo directivo).
 *
 * @var bool $puedeReabrir
 */
// ---------- Modal: reabrir un año escolar cerrado ----------
// El motivo es obligatorio y se muestra antes de enviar nada, porque la reapertura queda
// registrada con él: es la única explicación que quedará de por qué se deshizo un cierre.
if ($puedeReabrir):
$modalId = 'reabrirAnioModal';
$modalTitle = 'Reabrir Año Escolar';
$modalSubtitle = 'Solo es posible deshacer un cierre por error. La reapertura queda registrada con su nombre, la fecha y este motivo.';
ob_start();
?>
<form id="formReabrirAnio" class="reabrir-anio-form" novalidate>
  <input type="hidden" name="id" id="reabrirAnioId" value="">
  <div class="alert alert-warning mb-4">
    <i class="bi bi-exclamation-triangle me-2" aria-hidden="true"></i>
    Al reabrir, el período vuelve a ser el vigente y se podrán registrar inscripciones, ratificaciones,
    retiros y egresos. Las operaciones que se hicieron con el año cerrado no se deshacen.
  </div>
  <div class="mb-3">
    <label for="reabrirAnioNombre" class="form-label">Año escolar</label>
    <input type="text" id="reabrirAnioNombre" class="form-control" disabled value="">
  </div>
  <div class="mb-0">
    <label for="reabrirAnioMotivo" class="form-label">Motivo de la reapertura <span class="text-danger">*</span></label>
    <textarea class="form-control" id="reabrirAnioMotivo" name="motivo" rows="4" required
              maxlength="200" placeholder="Ej.: Se cerró por error antes de resolver las solicitudes de retiro del período."></textarea>
    <div class="form-text">Mínimo 10 caracteres. Quedará en la bitácora de auditoría.</div>
  </div>
</form>
<?php
$modalBody = ob_get_clean();
$modalFooter = '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" form="formReabrirAnio" class="btn btn-warning">Reabrir año escolar</button>';
include __DIR__ . '/../../../components/modal.php';
endif;
