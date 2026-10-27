<?php

declare(strict_types=1);

/**
 * Modal: modals/editar-directiva-modal.php
 * Formulario para editar la autoridad directiva firmante de constancias y documentos.
 *
 * Variables esperadas:
 * @var array<string, string> $config
 * @var string $csrfToken
 * @var string $dirPrimerNombre
 * @var string $dirSegundoNombre
 * @var string $dirPrimerApellido
 * @var string $dirSegundoApellido
 */

ob_start();
?>
<form id="formDirectiva" novalidate>
  <input type="hidden" name="csrf" value="<?php echo e($csrfToken); ?>">
  <input type="hidden" name="seccion" value="directiva">

  <div class="row g-3">
    <div class="col-md-6">
      <label for="director_primer_nombre" class="form-label fw-semibold">
        Primer Nombre <span class="text-danger">*</span>
      </label>
      <input type="text"
             class="form-control"
             id="director_primer_nombre"
             name="director_primer_nombre"
             value="<?php echo e($dirPrimerNombre); ?>"
             maxlength="100"
             required>
      <div class="invalid-feedback" id="error-director_primer_nombre"></div>
    </div>

    <div class="col-md-6">
      <label for="director_segundo_nombre" class="form-label fw-semibold">Segundo Nombre</label>
      <input type="text"
             class="form-control"
             id="director_segundo_nombre"
             name="director_segundo_nombre"
             value="<?php echo e($dirSegundoNombre); ?>"
             maxlength="100">
      <div class="invalid-feedback" id="error-director_segundo_nombre"></div>
    </div>

    <div class="col-md-6">
      <label for="director_primer_apellido" class="form-label fw-semibold">
        Primer Apellido <span class="text-danger">*</span>
      </label>
      <input type="text"
             class="form-control"
             id="director_primer_apellido"
             name="director_primer_apellido"
             value="<?php echo e($dirPrimerApellido); ?>"
             maxlength="100"
             required>
      <div class="invalid-feedback" id="error-director_primer_apellido"></div>
    </div>

    <div class="col-md-6">
      <label for="director_segundo_apellido" class="form-label fw-semibold">Segundo Apellido</label>
      <input type="text"
             class="form-control"
             id="director_segundo_apellido"
             name="director_segundo_apellido"
             value="<?php echo e($dirSegundoApellido); ?>"
             maxlength="100">
      <div class="invalid-feedback" id="error-director_segundo_apellido"></div>
    </div>

    <div class="col-md-6">
      <label for="director_cedula" class="form-label fw-semibold">
        Cédula de Identidad <span class="text-danger">*</span>
      </label>
      <input type="text"
             class="form-control font-monospace"
             id="director_cedula"
             name="director_cedula"
             value="<?php echo e($config['director_cedula'] ?? ''); ?>"
             placeholder="Ej: V-12.345.678"
             maxlength="20"
             required>
      <div class="invalid-feedback" id="error-director_cedula"></div>
      <div class="form-text">Formato: V- o E- seguido de los dígitos.</div>
    </div>

    <div class="col-md-6">
      <label for="director_cargo" class="form-label fw-semibold">
        Cargo Oficial <span class="text-danger">*</span>
      </label>
      <input type="text"
             class="form-control"
             id="director_cargo"
             name="director_cargo"
             value="<?php echo e($config['director_cargo'] ?? 'Director (E)'); ?>"
             placeholder="Ej: Director (E)"
             maxlength="100"
             required>
      <div class="invalid-feedback" id="error-director_cargo"></div>
    </div>
  </div>

  <hr class="my-4">
  <h6 class="fw-bold text-primary mb-1">
    <i class="bi bi-building-check me-1" aria-hidden="true"></i>
    Centro de Desarrollo de la Calidad Educativa Estadal (CDCE) — Opcional
  </h6>
  <p class="text-muted small mb-3">
    Si completa estos datos, aparecerán impresos en la segunda columna del cuadro de firmas de la <strong>Constancia de Prosecución</strong> y del <strong>Certificado de Educación Primaria</strong>. Si los deja vacíos, el espacio saldrá en blanco.
  </p>

  <div class="row g-3">
    <div class="col-md-7">
      <label for="cdce_director_nombre" class="form-label fw-semibold">
        Nombre y Apellido del Director(a) CDCE
      </label>
      <input type="text"
             class="form-control"
             id="cdce_director_nombre"
             name="cdce_director_nombre"
             value="<?php echo e($config['cdce_director_nombre'] ?? ''); ?>"
             placeholder="Dejar en blanco si se llenará manualmente"
             maxlength="150">
      <div class="invalid-feedback" id="error-cdce_director_nombre"></div>
    </div>

    <div class="col-md-5">
      <label for="cdce_director_cedula" class="form-label fw-semibold">
        Número de C.I. del Director(a) CDCE
      </label>
      <input type="text"
             class="form-control font-monospace"
             id="cdce_director_cedula"
             name="cdce_director_cedula"
             value="<?php echo e($config['cdce_director_cedula'] ?? ''); ?>"
             placeholder="Ej: V-12.345.678"
             maxlength="20">
      <div class="invalid-feedback" id="error-cdce_director_cedula"></div>
    </div>
  </div>
</form>
<?php
$modalBody = ob_get_clean();

ob_start();
?>
<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
<button type="submit" form="formDirectiva" class="btn btn-primary d-inline-flex align-items-center gap-2" id="btnGuardarDirectiva">
  <i class="bi bi-save" aria-hidden="true"></i>
  <span>Guardar Directiva</span>
</button>
<?php
$modalFooter = ob_get_clean();

$modalId       = 'modalEditarDirectiva';
$modalTitle    = 'Editar Autoridad Directiva';
$modalSubtitle = 'Nombres, cédula y cargo de la autoridad firmante.';
$modalSize     = 'lg';

include __DIR__ . '/../../components/modal.php';

unset($modalBody, $modalFooter, $modalId, $modalTitle, $modalSubtitle, $modalSize);
