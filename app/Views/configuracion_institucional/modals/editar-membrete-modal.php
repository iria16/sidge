<?php

declare(strict_types=1);

/**
 * Modal: modals/editar-membrete-modal.php
 * Formulario para editar las líneas oficiales del membrete de constancias y documentos.
 *
 * Variables esperadas:
 * @var array<string, string> $config
 * @var string $csrfToken
 */

ob_start();
?>
<form id="formMembrete" novalidate>
  <input type="hidden" name="csrf" value="<?php echo e($csrfToken); ?>">
  <input type="hidden" name="seccion" value="membrete">

  <div class="mb-3">
    <label for="ministerio_linea_1" class="form-label fw-semibold">
      Línea 1 del Ministerio <span class="text-danger">*</span>
    </label>
    <input type="text"
           class="form-control"
           id="ministerio_linea_1"
           name="ministerio_linea_1"
           value="<?php echo e($config['ministerio_linea_1'] ?? 'REPÚBLICA BOLIVARIANA DE VENEZUELA'); ?>"
           maxlength="200"
           required>
    <div class="invalid-feedback" id="error-ministerio_linea_1"></div>
  </div>

  <div class="mb-3">
    <label for="ministerio_linea_2" class="form-label fw-semibold">
      Línea 2 del Ministerio <span class="text-danger">*</span>
    </label>
    <input type="text"
           class="form-control"
           id="ministerio_linea_2"
           name="ministerio_linea_2"
           value="<?php echo e($config['ministerio_linea_2'] ?? 'MINISTERIO DEL PODER POPULAR PARA LA EDUCACIÓN'); ?>"
           maxlength="200"
           required>
    <div class="invalid-feedback" id="error-ministerio_linea_2"></div>
  </div>

  <div class="mb-3">
    <label for="certificado_epigrafe" class="form-label fw-semibold">
      Epígrafe Anual del Certificado de Educación Primaria
    </label>
    <input type="text"
           class="form-control"
           id="certificado_epigrafe"
           name="certificado_epigrafe"
           value="<?php echo e($config['certificado_epigrafe'] ?? 'BICENTENARIO DE LA UNIDAD AMERICANA: CONGRESO ANFICTIÓNICO DE PANAMÁ'); ?>"
           maxlength="200">
    <div class="invalid-feedback" id="error-certificado_epigrafe"></div>
    <div class="form-text">Lema o efeméride oficial anual que aparece al pie del Certificado de Educación Primaria (6.° Grado). Las comillas se agregan automáticamente.</div>
  </div>
</form>
<?php
$modalBody = ob_get_clean();

ob_start();
?>
<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
<button type="submit" form="formMembrete" class="btn btn-primary d-inline-flex align-items-center gap-2" id="btnGuardarMembrete">
  <i class="bi bi-save" aria-hidden="true"></i>
  <span>Guardar Membrete</span>
</button>
<?php
$modalFooter = ob_get_clean();

$modalId       = 'modalEditarMembrete';
$modalTitle    = 'Editar Membrete Oficial';
$modalSubtitle = 'Encabezado oficial de la República y Ministerio.';

include __DIR__ . '/../../components/modal.php';

unset($modalBody, $modalFooter, $modalId, $modalTitle, $modalSubtitle);
