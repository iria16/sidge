<?php
declare(strict_types=1);

/**
 * Modal: constancias/modals/preview-modal.php
 * Vista previa interactiva de la constancia oficial antes de descargar.
 * Permite visualizar el documento en un iframe y ofrece opciones de impresión y descarga en PDF.
 */

ob_start();
?>
<div class="student-record__pdf-preview position-relative" id="contenedorPreviewConstancia">
  <!-- Spinner de carga mientras se prepara el documento -->
  <div id="loaderPreviewConstancia" class="position-absolute top-50 start-50 translate-middle text-center p-3" style="display: none; z-index: 10;">
    <div class="spinner-border text-primary mb-2" role="status" style="width: 3rem; height: 3rem;">
      <span class="visually-hidden">Cargando...</span>
    </div>
    <div class="text-dark fw-medium small">Cargando vista previa oficial...</div>
  </div>

  <!-- Tarjeta de contingencia en caso de bloqueo por políticas del navegador/servidor -->
  <div id="fallbackPreviewConstancia" class="p-4 text-center my-auto" style="display: none;">
    <i class="bi bi-file-earmark-pdf text-primary fs-1 mb-2 d-block"></i>
    <h6 class="fw-bold mb-1">Vista previa no disponible en este visor</h6>
    <p class="text-muted small mb-3">La política de seguridad de su navegador o servidor restringe la previsualización directa embebida. Puede visualizar el documento oficial abriéndolo directamente o descargándolo.</p>
    <div class="d-flex justify-content-center gap-2">
      <a href="#" id="btnFallbackAbrir" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-box-arrow-up-right me-1"></i> Abrir en nueva pestaña
      </a>
      <a href="#" id="btnFallbackDescargar" download target="_blank" rel="noopener" class="btn btn-primary btn-sm">
        <i class="bi bi-download me-1"></i> Descargar PDF
      </a>
    </div>
  </div>

  <iframe id="iframeVistaPreviaConstancia" src="about:blank" title="Vista previa de la constancia"></iframe>
</div>
<?php
$modalBody = ob_get_clean();

ob_start();
?>
<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
<a href="#" id="btnAbrirNuevaPestanaConstancia" class="btn btn-outline-primary" target="_blank" rel="noopener">
  <i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>Abrir en nueva pestaña
</a>
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
