<?php
declare(strict_types=1);

/**
 * Modal: reportes/modals/preview-modal.php
 * Vista previa del reporte oficial antes de imprimir o descargar.
 * Reutiliza el mismo esquema y contenedor que el preview de constancias.
 */

ob_start();
?>
<div class="student-record__pdf-preview" id="contenedorPreviewReporte">
  <iframe id="iframeVistaPreviaReporte" src="about:blank" title="Vista previa del reporte"></iframe>
</div>
<?php
$modalBody = ob_get_clean();

ob_start();
?>
<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
<button type="button" class="btn btn-outline-primary" id="btnImprimirReporte">
  <i class="bi bi-printer me-1" aria-hidden="true"></i>Imprimir
</button>
<a href="#" id="btnDescargarReportePdf" class="btn btn-primary" download target="_blank" rel="noopener">
  <i class="bi bi-download me-1" aria-hidden="true"></i>Descargar PDF
</a>
<?php
$modalFooter   = ob_get_clean();
$modalId       = 'modalVistaPreviaReporte';
$modalTitle    = 'Vista previa del reporte';
$modalSubtitle = 'Revise el documento oficial antes de imprimir o descargar';
$modalSize     = 'xl';
$modalCentered = false;

require __DIR__ . '/../../components/modal.php';
