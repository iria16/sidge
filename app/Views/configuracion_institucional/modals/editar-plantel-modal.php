<?php

declare(strict_types=1);

/**
 * Modal: modals/editar-plantel-modal.php
 * Formulario para editar datos oficiales de identificación y ubicación territorial del plantel.
 *
 * Variables esperadas:
 * @var array<string, string> $config
 * @var string $csrfToken
 * @var string $valEstado
 * @var string $valMunicipio
 * @var string $valParroquia
 * @var string $valNivel
 * @var string $valCodigoNivel
 * @var array<int, array<string, mixed>> $estados
 * @var array<int, array<string, mixed>> $municipios
 * @var array<int, array<string, mixed>> $parroquias
 * @var array<int, array{nombre: string, codigo: string, descripcion: string}> $nivelesEducativos
 */

ob_start();
?>
<form id="formPlantel" novalidate>
  <input type="hidden" name="csrf" value="<?php echo e($csrfToken); ?>">
  <input type="hidden" name="seccion" value="plantel">

  <div class="row g-3">
    <div class="col-md-8">
      <label for="plantel_nombre" class="form-label fw-semibold">
        Nombre Oficial Completo del Plantel <span class="text-danger">*</span>
      </label>
      <input type="text"
             class="form-control"
             id="plantel_nombre"
             name="plantel_nombre"
             value="<?php echo e($config['plantel_nombre'] ?? 'ESCUELA BÁSICA "ISIDRO RAMÍREZ"'); ?>"
             maxlength="200"
             required>
      <div class="invalid-feedback" id="error-plantel_nombre"></div>
    </div>
    <div class="col-md-4">
      <label for="plantel_nombre_corto" class="form-label fw-semibold">
        Siglas / Nombre Corto <span class="text-danger">*</span>
      </label>
      <input type="text"
             class="form-control"
             id="plantel_nombre_corto"
             name="plantel_nombre_corto"
             value="<?php echo e($config['plantel_nombre_corto'] ?? 'E.B. Isidro Ramírez'); ?>"
             maxlength="100"
             required>
      <div class="invalid-feedback" id="error-plantel_nombre_corto"></div>
    </div>

    <div class="col-md-6">
      <label for="plantel_codigo_dea" class="form-label fw-semibold">
        Código DEA <span class="text-danger">*</span>
      </label>
      <input type="text"
             class="form-control font-monospace"
             id="plantel_codigo_dea"
             name="plantel_codigo_dea"
             value="<?php echo e($config['plantel_codigo_dea'] ?? 'OD05750814'); ?>"
             placeholder="Ej: OD05750814"
             maxlength="20"
             required>
      <div class="invalid-feedback" id="error-plantel_codigo_dea"></div>
    </div>

    <div class="col-md-6">
      <label for="plantel_codigo_dependencia" class="form-label fw-semibold">
        Código de Dependencia <span class="text-danger">*</span>
      </label>
      <input type="text"
             class="form-control font-monospace"
             id="plantel_codigo_dependencia"
             name="plantel_codigo_dependencia"
             value="<?php echo e($config['plantel_codigo_dependencia'] ?? '006562012'); ?>"
             placeholder="Ej: 006562012"
             maxlength="50"
             required>
      <div class="invalid-feedback" id="error-plantel_codigo_dependencia"></div>
    </div>

    <div class="col-md-4">
      <label for="cdce_estado" class="form-label fw-semibold">
        Estado CDCE <span class="text-danger">*</span>
      </label>
      <select class="form-select" id="cdce_estado" name="cdce_estado" required>
        <option value="">Seleccione Estado...</option>
        <?php foreach ($estados as $est): ?>
          <?php
            $estNombre = $est['nombre'] ?? '';
            $estId     = $est['id_estado'] ?? '';
            $sel       = (mb_strtoupper(trim($valEstado), 'UTF-8') === mb_strtoupper(trim((string)$estNombre), 'UTF-8')) ? 'selected' : '';
          ?>
          <option value="<?php echo e((string)$estNombre); ?>" data-id-estado="<?php echo e((string)$estId); ?>" <?php echo $sel; ?>>
            <?php echo e((string)$estNombre); ?>
          </option>
        <?php endforeach; ?>
      </select>
      <div class="invalid-feedback" id="error-cdce_estado"></div>
    </div>

    <div class="col-md-4">
      <label for="plantel_municipio" class="form-label fw-semibold">
        Municipio del Plantel <span class="text-danger">*</span>
      </label>
      <select class="form-select" id="plantel_municipio" name="plantel_municipio" required>
        <option value="">Seleccione Municipio...</option>
        <?php foreach ($municipios as $mun): ?>
          <?php
            $munNombre   = $mun['nombre'] ?? '';
            $munId       = $mun['id_municipio'] ?? '';
            $munIdEstado = $mun['id_estado'] ?? '';
            $sel         = (mb_strtoupper(trim($valMunicipio), 'UTF-8') === mb_strtoupper(trim((string)$munNombre), 'UTF-8')) ? 'selected' : '';
          ?>
          <option value="<?php echo e((string)$munNombre); ?>" data-id-municipio="<?php echo e((string)$munId); ?>" data-id-estado="<?php echo e((string)$munIdEstado); ?>" <?php echo $sel; ?>>
            <?php echo e((string)$munNombre); ?>
          </option>
        <?php endforeach; ?>
      </select>
      <div class="invalid-feedback" id="error-plantel_municipio"></div>
    </div>

    <div class="col-md-4">
      <label for="plantel_parroquia" class="form-label fw-semibold">
        Parroquia del Plantel <span class="text-danger">*</span>
      </label>
      <select class="form-select" id="plantel_parroquia" name="plantel_parroquia" required>
        <option value="">Seleccione Parroquia...</option>
        <?php foreach ($parroquias as $par): ?>
          <?php
            $parNombre = $par['nombre'] ?? '';
            $parId     = $par['id_parroquia'] ?? '';
            $parIdMun  = $par['id_municipio'] ?? '';
            $sel       = (mb_strtoupper(trim($valParroquia), 'UTF-8') === mb_strtoupper(trim((string)$parNombre), 'UTF-8')) ? 'selected' : '';
          ?>
          <option value="<?php echo e((string)$parNombre); ?>" data-id-parroquia="<?php echo e((string)$parId); ?>" data-id-municipio="<?php echo e((string)$parIdMun); ?>" <?php echo $sel; ?>>
            <?php echo e((string)$parNombre); ?>
          </option>
        <?php endforeach; ?>
      </select>
      <div class="invalid-feedback" id="error-plantel_parroquia"></div>
    </div>

    <div class="col-md-6">
      <label for="lugar_expedicion" class="form-label fw-semibold">
        Lugar de Expedición en Constancias <span class="text-danger">*</span>
      </label>
      <input type="text"
             class="form-control"
             id="lugar_expedicion"
             name="lugar_expedicion"
             value="<?php echo e($config['lugar_expedicion'] ?? 'Carabobo'); ?>"
             placeholder="Ej: Carabobo"
             maxlength="200"
             required>
      <div class="invalid-feedback" id="error-lugar_expedicion"></div>
      <div class="form-text">Texto para «Constancia que se expide en [Lugar]...».</div>
    </div>

    <div class="col-md-4">
      <label for="nivel_educativo" class="form-label fw-semibold">
        Nivel Educativo <span class="text-danger">*</span>
      </label>
      <select class="form-select" id="nivel_educativo" name="nivel_educativo" required>
        <option value="">Seleccione Nivel...</option>
        <?php foreach ($nivelesEducativos as $niv): ?>
          <?php
            $nivNombre = $niv['nombre'] ?? '';
            $nivCodigo = $niv['codigo'] ?? '';
            $sel       = (mb_strtoupper(trim($valNivel), 'UTF-8') === mb_strtoupper(trim((string)$nivNombre), 'UTF-8')) ? 'selected' : '';
          ?>
          <option value="<?php echo e((string)$nivNombre); ?>" data-codigo="<?php echo e((string)$nivCodigo); ?>" <?php echo $sel; ?>>
            <?php echo e((string)$nivNombre); ?>
          </option>
        <?php endforeach; ?>
      </select>
      <div class="invalid-feedback" id="error-nivel_educativo"></div>
    </div>

    <div class="col-md-2">
      <label for="nivel_codigo" class="form-label fw-semibold">
        Cód. MPPE <span class="text-danger">*</span>
      </label>
      <input type="text"
             class="form-control font-monospace"
             id="nivel_codigo"
             name="nivel_codigo"
             value="<?php echo e($valCodigoNivel); ?>"
             maxlength="5"
             required>
      <div class="invalid-feedback" id="error-nivel_codigo"></div>
    </div>
  </div>
</form>
<?php
$modalBody = ob_get_clean();

ob_start();
?>
<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
<button type="submit" form="formPlantel" class="btn btn-primary d-inline-flex align-items-center gap-2" id="btnGuardarPlantel">
  <i class="bi bi-save" aria-hidden="true"></i>
  <span>Guardar Datos del Plantel</span>
</button>
<?php
$modalFooter = ob_get_clean();

$modalId       = 'modalEditarPlantel';
$modalTitle    = 'Editar Datos Oficiales del Plantel';
$modalSubtitle = 'Denominación oficial, código DEA y ubicación territorial de la institución.';
$modalSize     = 'lg';

include __DIR__ . '/../../components/modal.php';

unset($modalBody, $modalFooter, $modalId, $modalTitle, $modalSubtitle, $modalSize);
