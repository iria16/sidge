<?php
/**
 * app/Views/gestion_estudiante/inscripcion/modal-nueva-institucion-procedencia.php
 * Alta rápida de la institución de procedencia desde el paso 2 del wizard.
 *
 * Soporta instituciones nacionales (con Código DEA y cascada Estado -> Municipio -> Parroquia)
 * e instituciones extranjeras (es_extranjera = true, con selector de país obligatorio).
 *
 * El guardado lo maneja enrollments.js (btnGuardarNuevaInstitucionProcedencia)
 * contra inscripciones/guardarInstitucion.
 *
 * Variables en scope:
 *  - $tiposInstitucionEstudiante (App\Support\Instituciones)
 *  - $estados (lista de estados para la cascada de ubicación nacional)
 */
$tipoProcedencia = $tiposInstitucionEstudiante ?? [];
$listaEstados    = $estados ?? [];

ob_start();
?>
<div class="row g-3">
  <!-- Tipo de institución -->
  <div class="col-md-6">
    <label class="label-sigde" for="nuevaInstitucionProcedenciaTipo">Tipo de Institución *</label>
    <select class="form-select" id="nuevaInstitucionProcedenciaTipo" required>
      <option value="">Seleccione el tipo...</option>
      <?php foreach ($tipoProcedencia as $tipo): ?>
        <option value="<?= htmlspecialchars($tipo['tipo']) ?>"><?= htmlspecialchars($tipo['nombre']) ?></option>
      <?php endforeach; ?>
    </select>
    <div class="text-support mt-1" style="font-size: 0.75rem">
      Solo preescolar o escuela: es la institución de donde viene el estudiante.
    </div>
    <div class="invalid-feedback">Seleccione el tipo de institución.</div>
  </div>

  <!-- Switch: ¿Es institución extranjera? -->
  <div class="col-md-6 d-flex align-items-center">
    <div class="wizard-toggle-card w-100 p-2 border rounded mt-3 mt-md-0">
      <div class="wizard-toggle-card__text">
        <strong>Institución Extranjera</strong>
        <span class="text-muted d-block" style="font-size: 0.75rem">Plantel fuera de Venezuela.</span>
      </div>
      <label class="wizard-switch mb-0">
        <input type="checkbox" id="nuevaInstEsExtranjera" name="es_extranjera" value="1">
        <span class="wizard-switch__slider"></span>
      </label>
    </div>
  </div>

  <!-- Nombre de la institución (obligatorio en todos los casos) -->
  <div class="col-12">
    <label class="label-sigde" for="nuevaInstitucionProcedenciaNombre">Nombre de la Institución *</label>
    <input
      type="text"
      class="form-control"
      id="nuevaInstitucionProcedenciaNombre"
      placeholder="Ej: Escuela Juan Manuel Aristeguieta"
      maxlength="100"
      required
    >
    <div class="invalid-feedback">Escribe el nombre de la institución.</div>
  </div>



  <!-- Bloque Nacional: Código DEA y Cascada Estado -> Municipio -> Parroquia -->
  <div class="col-12" id="bloqueInstNacional">
    <div class="mb-3">
      <label class="label-sigde" for="nuevaInstCodigoDea">Código DEA</label>
      <input
        type="text"
        class="form-control text-uppercase"
        id="nuevaInstCodigoDea"
        name="codigo_dea"
        placeholder="Ej: OD05750814"
        maxlength="20"
        autocomplete="off"
      >
      <div class="text-support mt-1" style="font-size: 0.75rem">
        Código oficial asignado por el MPPE (opcional).
      </div>
      <div class="invalid-feedback" id="error-nuevaInstCodigoDea"></div>
    </div>

    <!-- Cascada con el componente de ubicación existente -->
    <div class="row g-2">
      <div class="col-md-4">
        <label class="label-sigde" for="nuevaInstEstado">Estado</label>
        <select class="form-select" id="nuevaInstEstado" name="id_estado_geo">
          <option value="">Seleccione estado...</option>
          <?php foreach ($listaEstados as $est): ?>
            <option value="<?= (int) $est['id_estado'] ?>"><?= htmlspecialchars($est['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="label-sigde" for="nuevaInstMunicipio">Municipio</label>
        <select class="form-select" id="nuevaInstMunicipio" name="id_municipio" data-depende-de="nuevaInstEstado">
          <option value="">Seleccione municipio...</option>
        </select>
      </div>
      <div class="col-md-4">
        <label class="label-sigde" for="nuevaInstParroquia">Parroquia</label>
        <select class="form-select" id="nuevaInstParroquia" name="id_parroquia" data-depende-de="nuevaInstMunicipio">
          <option value="">Seleccione parroquia...</option>
        </select>
      </div>
    </div>
  </div>
</div>
<?php
$modalBody = ob_get_clean();

ob_start();
?>
<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
<button type="button" class="btn btn-primary" id="btnGuardarNuevaInstitucionProcedencia">
  <i class="bi bi-check-lg" aria-hidden="true"></i> Guardar Registro
</button>
<?php
$modalFooter = ob_get_clean();

$modalId       = 'modalNuevaInstitucionProcedencia';
$modalTitle    = 'Institución';
$modalSubtitle = 'Registre la institución de procedencia del estudiante.';
$modalSize     = 'lg';

include __DIR__ . '/../../components/modal.php';
