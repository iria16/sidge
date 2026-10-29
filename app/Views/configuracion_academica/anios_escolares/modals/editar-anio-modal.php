<?php
declare(strict_types=1);

/**
 * Modal: anios_escolares/modals/editar-anio-modal.php
 * Edición de un período académico existente.
 */
$modalId = 'editarAnioModal';
$modalTitle = 'Editar Año Escolar';
$modalSubtitle = 'Actualice la información del período seleccionado.';
ob_start();
?>
<form id="formEditarAnio" novalidate>
  <input type="hidden" name="id" id="editarAnioId">
  <div class="mb-3">
    <label for="editarAnioNombre" class="label-sigde">Nombre del período</label>
    <input type="text" name="nombre" id="editarAnioNombre" class="form-control" maxlength="20" required>
  </div>
  <div class="row g-3">
    <div class="col-md-6">
      <label for="editarAnioInicio" class="label-sigde">Fecha de inicio</label>
      <input type="date" name="fecha_inicio" id="editarAnioInicio" class="form-control" required>
    </div>
    <div class="col-md-6">
      <label for="editarAnioCierre" class="label-sigde">Fecha de cierre</label>
      <input type="date" name="fecha_cierre" id="editarAnioCierre" class="form-control" required>
    </div>
  </div>
</form>
<?php
$modalBody = ob_get_clean();
$modalFooter = '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" form="formEditarAnio" class="btn btn-primary">Guardar cambios</button>';
include __DIR__ . '/../../../components/modal.php';
