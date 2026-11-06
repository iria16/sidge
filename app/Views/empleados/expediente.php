<?php
declare(strict_types=1);

/**
 * Vista: empleados/expediente.php
 * Expediente único del empleado.
 *
 * @var array $empleado
 * @var array $formacion
 * @var array $asignaciones
 * @var array $reposos
 * @var array $historial
 * @var bool  $puedeGestionar
 */
$pageTitle  = 'Expediente del Empleado';
$currentNav = 'empleados';

$breadcrumbs = [
    ['label' => 'Personal', 'href' => null],
    ['label' => 'Empleados', 'href' => BASE_URL . 'empleado/index'],
    ['label' => 'Expediente', 'href' => null],
];

ob_start();
?>
<div class="container-fluid px-0">
  <?php include __DIR__ . '/partials/expediente-header.php'; ?>
  <?php include __DIR__ . '/partials/expediente-tabs.php'; ?>
</div>
<?php
$pageContent = ob_get_clean();

ob_start();
include __DIR__ . '/modals/edit-modal.php';
include __DIR__ . '/modals/reposo-modal.php';
include __DIR__ . '/modals/cerrar-reposo-modal.php';
include __DIR__ . '/modals/anular-reposo-modal.php';

// Modal Vista Previa de la Ficha Integral del Empleado (PDF)
$urlPdf = BASE_URL . 'empleado/exportar?id=' . (int) ($empleado['id_empleado'] ?? 0);
ob_start();
?>
<div class="student-record__pdf-preview position-relative" id="contenedorPreviewExpediente">
  <!-- Spinner de carga -->
  <div id="loaderPreviewExpediente" class="position-absolute top-50 start-50 translate-middle text-center p-3" style="display: none; z-index: 10;">
    <div class="spinner-border text-primary mb-2" role="status" style="width: 3rem; height: 3rem;">
      <span class="visually-hidden">Cargando...</span>
    </div>
    <div class="text-dark fw-medium small">Cargando vista previa...</div>
  </div>
  <!-- Fallback de contingencia -->
  <div id="fallbackPreviewExpediente" class="p-4 text-center my-auto" style="display: none;">
    <i class="bi bi-file-earmark-pdf text-primary fs-1 mb-2 d-block"></i>
    <h6 class="fw-bold mb-1">Vista previa no disponible</h6>
    <p class="text-muted small mb-3">Use los botones para abrir o descargar el documento.</p>
    <div class="d-flex justify-content-center gap-2">
      <a href="#" id="btnFallbackAbrirExp" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-box-arrow-up-right me-1"></i> Abrir en nueva pestaña
      </a>
      <a href="#" id="btnFallbackDescargarExp" download target="_blank" rel="noopener" class="btn btn-primary btn-sm">
        <i class="bi bi-download me-1"></i> Descargar
      </a>
    </div>
  </div>
  <iframe id="iframeVistaPreviaExpediente" data-src="<?= htmlspecialchars($urlPdf) ?>" title="Vista previa del expediente del personal"></iframe>
</div>
<?php
$modalBody = ob_get_clean();

ob_start();
?>
<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
<a href="<?= htmlspecialchars($urlPdf) ?>" target="_blank" rel="noopener" class="btn btn-outline-primary">
  <i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>Abrir en nueva pestaña
</a>
<button type="button" class="btn btn-outline-primary" id="btnImprimirExpedientePdf">
  <i class="bi bi-printer me-1" aria-hidden="true"></i>Imprimir
</button>
<a href="<?= htmlspecialchars($urlPdf . '&descargar=1') ?>"
   class="btn btn-primary" target="_blank" rel="noopener">
  <i class="bi bi-download me-1" aria-hidden="true"></i>Descargar PDF
</a>
<?php
$modalFooter   = ob_get_clean();
$modalId       = 'modalVistaPreviaExpediente';
$modalTitle    = 'Ficha integral del empleado';
$modalSubtitle = trim((string) ($empleado['nombre_completo'] ?? 'Personal') . ' · ' . (string) ($empleado['tipo_documento'] ?? 'V') . '-' . (string) ($empleado['numero_documento'] ?? ''));
$modalSize     = 'xl';
$modalCentered = false;
include __DIR__ . '/../components/modal.php';

$pageModals = ob_get_clean();

$extraScripts = [
    '/js/modules/empleados/empleado-comun.js',
    '/js/modules/empleados/empleado-editar.js',
    '/js/modules/empleados/empleado-reposo.js',
    '/js/modules/empleados/empleado-expediente.js',
    '/js/modules/estudiantes/student-record.js',
];

require __DIR__ . '/../layouts/app.php';
