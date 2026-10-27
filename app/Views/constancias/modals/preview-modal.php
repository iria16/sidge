<?php
declare(strict_types=1);

/**
 * Modal: constancias/modals/preview-modal.php
 * Vista previa interactiva de la constancia oficial antes de descargar.
 * Permite visualizar el documento en un iframe y ofrece opciones de impresión y descarga en PDF.
 */

ob_start();
?>
<div class="student-record__pdf-preview" id="contenedorPreviewConstancia">
  <iframe id="iframeVistaPreviaConstancia" src="about:blank" title="Vista previa de la constancia"></iframe>
</div>
<?php
$modalBody = ob_get_clean();

ob_start();
?>
<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
<button type="button" class="btn btn-outline-primary" id="btnImprimirConstancia">
  <i class="bi bi-printer me-1" aria-hidden="true"></i>Imprimir
</button>
<a href="#" id="btnDescargarConstanciaPdf" class="btn btn-primary" download target="_blank" rel="noopener">
  <i class="bi bi-download me-1" aria-hidden="true"></i>Descargar PDF
</a>
<?php
$modalFooter   = ob_get_clean();
$modalId       = 'modalVistaPreviaConstancia';
$modalTitle    = 'Vista previa de Constancia';
$modalSubtitle = 'Verifique el documento oficial antes de proceder con la descarga';
$modalSize     = 'xl';
$modalCentered = false;

require __DIR__ . '/../../components/modal.php';
