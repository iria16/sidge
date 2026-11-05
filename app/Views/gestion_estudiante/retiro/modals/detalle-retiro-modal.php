<?php
declare(strict_types=1);

/**
 * Modal: retiro/modals/detalle-retiro-modal.php
 * Detalle de la solicitud. El cuerpo lo carga por AJAX retiro/ver.php.
 */
  $modalId       = 'detalleRetiroModal';
  $modalTitle    = 'Detalle de Retiro';
  $modalSubtitle = 'Información de la solicitud seleccionada.';
  ob_start();
  ?>
<?php /* El cuerpo del modal se carga desde retiro/ver.php y el JS lo inserta en
 #retiroDetalleCuerpo. */ ?>
<div id="retiroDetalleCuerpo">
  <div class="text-center text-support py-4">
    <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
    Cargando...
  </div>
</div>
<?php
$modalBody = ob_get_clean();
$modalFooter = '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>';
include __DIR__ . '/../../../components/modal.php';
