<?php
declare(strict_types=1);

/**
 * Modal: anios_escolares/modals/nuevo-anio-modal.php
 * Alta de un período académico.
 */
$modalId = 'nuevoAnioModal';
$modalTitle = 'Nuevo Año Escolar';
$modalSubtitle = 'Registre un nuevo período académico institucional.';
ob_start();
?>
<form id="formAnioEscolar" novalidate>
  <section class="wizard-summary__section">
    <div class="wizard-summary__section-header">
      <span class="wizard-summary__icon"><i class="bi bi-calendar-check" aria-hidden="true"></i></span>
      <span class="wizard-summary__section-title">Información del período</span>
    </div>
    <div class="wizard-summary__card">
      <div class="mb-3">
        <label for="anioNombre" class="label-sigde">Nombre del período</label>
        <input type="text" id="anioNombre" name="nombre" class="form-control" placeholder="Ej. 2024-2025" maxlength="9" pattern="\d{4}-\d{4}" required>
        <div class="invalid-feedback">Indique el nombre del período.</div>
      </div>
      <div class="row g-2">
        <div class="col-md-6">
          <label for="anioInicio" class="label-sigde">Fecha de inicio</label>
          <input type="date" id="anioInicio" name="fecha_inicio" class="form-control" required>
          <div class="invalid-feedback">Indique la fecha de inicio.</div>
        </div>
        <div class="col-md-6">
          <label for="anioCierre" class="label-sigde">Fecha de cierre</label>
          <input type="date" id="anioCierre" name="fecha_cierre" class="form-control" required>
          <div class="invalid-feedback">Indique la fecha de cierre.</div>
        </div>
      </div>
    </div>
  </section>
</form>
<?php
$modalBody = ob_get_clean();
$modalFooter = '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" form="formAnioEscolar" class="btn btn-primary"><i class="bi bi-check-lg"></i> Guardar período</button>';
include __DIR__ . '/../../../components/modal.php';
