<?php
/**
 * Modal: Editar Elemento
 * Se llena desde JS (catalogo.js) con los data-elemento-id /
 * data-elemento-nombre del botón "editar" presionado.
 * Espera del index.php: $catalogoActivo, $catalogoInfo, $catalogoMaxLength,
 * $tiposInstitucion
 */

// ---------- Cuerpo del Modal ----------
ob_start();
?>
<form id="formEditarElemento" novalidate data-max-length="<?= (int) ($catalogoMaxLength ?? 50) ?>"<?= !empty($tiposInstitucion) ? ' data-pide-tipo="1"' : '' ?>>
  <div class="alert alert-danger d-none" data-form-error></div>

  <!-- Campos ocultos requeridos para la actualización -->
  <input type="hidden" name="tipo" value="<?= htmlspecialchars($catalogoActivo) ?>">
  <input type="hidden" name="id" id="editarElementoId">

  <section class="wizard-summary__section">
    <div class="wizard-summary__card">
      <div class="mb-3">
        <label for="editarElementoNombre" class="form-label">
          Nombre <span class="text-danger">*</span>
        </label>
        <input
          type="text"
          id="editarElementoNombre"
          name="nombre"
          class="form-control"
          required
          maxlength="<?= (int) ($catalogoMaxLength ?? 50) ?>"
          autocomplete="off"
        >
        <div class="invalid-feedback">El nombre es obligatorio (máximo <?= (int) ($catalogoMaxLength ?? 50) ?> caracteres).</div>
      </div>

      <?php if (!empty($tiposInstitucion)): ?>
        <!-- El tipo sí se puede corregir: si al crear se eligió la categoría
             equivocada, se arregla aquí en vez de tener que dar de baja la
             institución y crearla de nuevo. -->
        <div class="mb-3">
          <label for="editarElementoTipo" class="form-label">
            Tipo de institución <span class="text-danger">*</span>
          </label>
          <select id="editarElementoTipo" name="tipo" class="form-select" required>
            <option value="">Seleccione el tipo...</option>
            <?php foreach ($tiposInstitucion as $tipoOpcion): ?>
              <option value="<?= htmlspecialchars($tipoOpcion['tipo']) ?>"><?= htmlspecialchars($tipoOpcion['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="invalid-feedback">Selecciona el tipo de institución.</div>
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
<button type="submit" form="formEditarElemento" class="btn btn-primary">
  <span class="spinner-border spinner-border-sm d-none me-1" data-form-spinner aria-hidden="true"></span>
  Guardar cambios
</button>
<?php
$modalFooter = ob_get_clean();

// ---------- Configuración del componente Modal ----------
$modalId       = 'modalEditarElemento';
$modalTitle    = 'Editar elemento — ' . htmlspecialchars($catalogoInfo['nombre'] ?? '');
$modalSubtitle = 'Modifique la información del registro seleccionado.';

include __DIR__ . '/../../../components/modal.php';

unset($modalBody, $modalFooter, $modalId, $modalTitle, $modalSubtitle);