<?php
/**
 * Modal: Nuevo Elemento
 * Espera del index.php: $catalogoActivo, $catalogoInfo, $catalogoMaxLength,
 * $tiposInstitucion, $estadosGeo, $gradosAcademicos, $pideGrado
 */

// ---------- Cuerpo del Modal ----------
ob_start();
?>
<form id="formNuevoElemento" novalidate data-max-length="<?= (int) ($catalogoMaxLength ?? 50) ?>"<?= !empty($tiposInstitucion) ? ' data-pide-tipo="1"' : '' ?><?= !empty($pideGrado) ? ' data-pide-grado="1"' : '' ?>>
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
          <!-- El name NO puede ser "tipo": ese nombre ya lo usa el campo
             oculto del catálogo activo, y si los dos viajan en el mismo
             POST el segundo pisa al primero y resolveTipoActivo() cae
             al primer catálogo (grado), guardando la institución donde no
             es. -->
          <select id="nuevoElementoTipo" name="tipo_institucion" class="form-select" required>
            <option value="">Seleccione el tipo...</option>
            <?php foreach ($tiposInstitucion as $tipoOpcion): ?>
              <option value="<?= htmlspecialchars($tipoOpcion['tipo']) ?>"><?= htmlspecialchars($tipoOpcion['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="invalid-feedback">Selecciona el tipo de institución.</div>
        </div>
      <?php endif; ?>

      <?php if (!empty($pideGrado)): ?>
        <div class="mb-3">
          <label for="nuevoElementoGradoAcademico" class="form-label">
            Grado académico <span class="text-danger">*</span>
          </label>
          <select id="nuevoElementoGradoAcademico" name="id_grado_academico" class="form-select" required>
            <option value="">Seleccione el grado académico...</option>
            <?php foreach ($gradosAcademicos as $gradoAcademico): ?>
              <option value="<?= (int) $gradoAcademico['id_grado_academico'] ?>"><?= htmlspecialchars($gradoAcademico['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="invalid-feedback">Selecciona el grado académico del título.</div>
        </div>
      <?php endif; ?>

      <?php if (!empty($estadosGeo)): ?>
        <?php
        $prefijo    = 'nuevoElemento';
        $urlCascada = BASE_URL . 'catalogo/getUbicacion';
        include __DIR__ . '/../../../components/institucion-procedencia-form.php';
        ?>
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