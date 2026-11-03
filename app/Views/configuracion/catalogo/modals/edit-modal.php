<?php
/**
 * Modal: Editar Elemento
 * Se llena desde JS (catalogo.js) con los data-elemento-* del botón "editar"
 * presionado.
 * Espera del index.php: $catalogoActivo, $catalogoInfo, $catalogoMaxLength,
 * $tiposInstitucion, $estadosGeo, $gradosAcademicos, $pideGrado
 */

// ---------- Cuerpo del Modal ----------
ob_start();
?>
<form id="formEditarElemento" novalidate data-max-length="<?= (int) ($catalogoMaxLength ?? 50) ?>"<?= !empty($tiposInstitucion) ? ' data-pide-tipo="1"' : '' ?><?= !empty($pideGrado) ? ' data-pide-grado="1"' : '' ?>>
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
          <!-- Mismo nombre que en el alta: "tipo" está ocupado por el campo oculto
             del catálogo activo. -->
          <select id="editarElementoTipo" name="tipo_institucion" class="form-select" required>
            <option value="">Seleccione el tipo...</option>
            <?php foreach ($tiposInstitucion as $tipoOpcion): ?>
              <!-- data-id es el id de tipo_institucion. El value sigue siendo el
                   codigo de texto, que es lo que viaja al servidor, pero el boton
                   de la fila solo conoce el id (la columna de texto ya no existe),
                   asi que sin esto el select no se podria preseleccionar. -->
              <option value="<?= htmlspecialchars($tipoOpcion['tipo']) ?>"
                      data-id="<?= (int) \App\Support\Instituciones::idCatalogo($tipoOpcion['tipo']) ?>"><?= htmlspecialchars($tipoOpcion['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="invalid-feedback">Selecciona el tipo de institución.</div>
        </div>
      <?php endif; ?>

      <?php if (!empty($pideGrado)): ?>
        <div class="mb-3">
          <label for="editarElementoGradoAcademico" class="form-label">
            Grado académico <span class="text-danger">*</span>
          </label>
          <select id="editarElementoGradoAcademico" name="id_grado_academico" class="form-select" required>
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
        $prefijo         = 'editarElemento';
        $urlCascada      = BASE_URL . 'catalogo/getUbicacion';
        $elExtranjera    = !empty($el['es_extranjera']);
        $selEstado       = (int) ($el['id_estado_geo'] ?? 0);
        $selMunicipio    = (int) ($el['id_municipio'] ?? 0);
        $selParroquia    = (int) ($el['id_parroquia'] ?? 0);
        $selDea          = (string) ($el['codigo_dea'] ?? '');
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