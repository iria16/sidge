<?php
/**
 * Modal: Nuevo Elemento
 * Espera del index.php: $catalogoActivo, $catalogoInfo, $catalogoMaxLength,
 * $tiposInstitucion
 */

// ---------- Cuerpo del Modal ----------
ob_start();
?>
<form id="formNuevoElemento" novalidate data-max-length="<?= (int) ($catalogoMaxLength ?? 50) ?>"<?= !empty($tiposInstitucion) ? ' data-pide-tipo="1"' : '' ?>>
  <div class="alert alert-danger d-none" data-form-error></div>

  <!-- Campo oculto con el catálogo activo -->
  <input type="hidden" name="tipo" value="<?= htmlspecialchars($catalogoActivo) ?>">

  <section class="wizard-summary__section">
    <div class="wizard-summary__card">
      <div class="mb-3">
        <label for="nuevoElementoNombre" class="form-label">
          Nombre <span class="text-danger">*</span>
        </label>
        <input
          type="text"
          id="nuevoElementoNombre"
          name="nombre"
          class="form-control"
          required
          maxlength="<?= (int) ($catalogoMaxLength ?? 50) ?>"
          autocomplete="off"
        >
        <div class="invalid-feedback">El nombre es obligatorio (máximo <?= (int) ($catalogoMaxLength ?? 50) ?> caracteres).</div>
      </div>

      <?php if (!empty($tiposInstitucion)): ?>
        <!-- Solo institución. El campo no es opcional: la columna
             id_tipo_institucion es NOT NULL y no tiene valor por defecto, así
             que sin tipo el registro no entra. -->
        <div class="mb-3">
          <label for="nuevoElementoTipo" class="form-label">
            Tipo de institución <span class="text-danger">*</span>
          </label>
          <select id="nuevoElementoTipo" name="tipo" class="form-select" required>
            <option value="">Seleccione el tipo...</option>
            <?php foreach ($tiposInstitucion as $tipoOpcion): ?>
              <option value="<?= htmlspecialchars($tipoOpcion['tipo']) ?>"><?= htmlspecialchars($tipoOpcion['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="invalid-feedback">Selecciona el tipo de institución.</div>
          <div class="text-support mt-1" style="font-size: 0.75rem">
            Universidad para el título académico de un empleado; Preescolar o Escuela
            para la procedencia de un estudiante.
          </div>
        </div>
      <?php endif; ?>
    </div>
  </section>
</form>
<?php
$modalBody = ob_get_clean();

// ---------- Pie del Modal ----------
ob_start();
?>
<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
<button type="submit" form="formNuevoElemento" class="btn btn-primary">
  <span class="spinner-border spinner-border-sm d-none me-1" data-form-spinner aria-hidden="true"></span>
  Guardar
</button>
<?php
$modalFooter = ob_get_clean();

// ---------- Configuración del componente Modal ----------
$modalId       = 'modalNuevoElemento';
$modalTitle    = 'Nuevo elemento — ' . htmlspecialchars($catalogoInfo['nombre'] ?? '');
$modalSubtitle = 'Complete el nombre del nuevo elemento del catálogo.';

include __DIR__ . '/../../../components/modal.php';

unset($modalBody, $modalFooter, $modalId, $modalTitle, $modalSubtitle);