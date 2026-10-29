<?php
declare(strict_types=1);

/**
 * Modal: resultado_academico/modals/preview-modal.php
 * Vista previa interactiva de Certificados de Calificaciones antes de imprimir o descargar.
 * Permite visualizar el documento oficial en un iframe y ofrece opciones de impresión y descarga en PDF.
 */

ob_start();
?>
<div class="student-record__pdf-preview" id="contenedorPreviewCertificado">
  <iframe id="iframeVistaPreviaCertificado" src="about:blank" title="Vista previa del certificado de calificaciones"></iframe>
</div>
<?php
$modalBody = ob_get_clean();

ob_start();
?>
<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
<button type="button" class="btn btn-outline-primary" id="btnImprimirCertificado">
  <i class="bi bi-printer me-1" aria-hidden="true"></i>Imprimir
</button>
<a href="#" id="btnDescargarCertificadoPdf" class="btn btn-primary" download target="_blank" rel="noopener">
  <i class="bi bi-download me-1" aria-hidden="true"></i>Descargar PDF
</a>
<?php
$modalFooter   = ob_get_clean();
$modalId       = 'modalVistaPreviaCertificado';
$modalTitle    = 'Certificado de Calificaciones';
$modalSubtitle = 'Verifique el documento oficial antes de proceder con la impresión o descarga';
$modalSize     = 'xl';
$modalCentered = false;

require __DIR__ . '/../../components/modal.php';