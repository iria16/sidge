<?php
declare(strict_types=1);

/**
 * app/Views/empleados/modals/edit-modal.php
 * Modal "Editar Empleado". Se puebla vía AJAX (empleados/show) antes de abrirse y
 * envía los cambios a empleados/update. Usa components/modal.php.
 *
 * Mismo lenguaje visual, estructura de encabezado y pestañas que el modal de
 * edición del estudiante (modalEditarEstudiante):
 *   - Encabezado con foto circular editable arriba (modal-identity-header.php)
 *   - Pestañas organizadas: Personales, Laborales, Contacto y Domicilio, Formación
 *   - Sin foto duplicada en Datos Personales
 *
 * Variables en scope (definidas en index.php o expediente.php):
 * @var array $cargos
 * @var array $turnos
 * @var array $gradosAcademicos
 * @var array $titulos
 * @var array $instituciones
 * @var array $estados
 */
ob_start();
?>
<form id="formEditarEmpleado" novalidate>
  <!-- ID oculto del empleado que se está editando -->
  <input type="hidden" id="editEmpleado__id" name="id" value="">

  <!-- Encabezado de identidad compartido con el modal de estudiante -->
  <?php
  $identityPreviewId  = 'editFotoPreview';
  $identityInputId    = 'editFotoInput';
  $identityInputName  = 'foto';
  $identityNameId     = 'editEncabezadoNombre';
  $identityDocId      = 'editEncabezadoDocumento';
  $identityQuitarId   = 'btnQuitarFotoEdicion';
  $identityEliminarId = 'editEliminarFoto';
  include __DIR__ . '/../../components/modal-identity-header.php';
  ?>

  <ul class="nav nav-tabs modal-tabs px-3 pt-3 border-bottom-0" id="editEmpleadoTabs" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" id="tab-emp-personales" data-bs-toggle="tab" data-bs-target="#pane-emp-personales" type="button" role="tab">Datos Personales</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="tab-emp-laborales" data-bs-toggle="tab" data-bs-target="#pane-emp-laborales" type="button" role="tab">Datos Laborales</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="tab-emp-contacto" data-bs-toggle="tab" data-bs-target="#pane-emp-contacto" type="button" role="tab">Contacto y Domicilio</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="tab-emp-formacion" data-bs-toggle="tab" data-bs-target="#pane-emp-formacion" type="button" role="tab">Formación Académica</button>
    </li>
  </ul>

  <div class="tab-content bg-white p-3 border-top" id="editEmpleadoTabsContent">
    <!-- Pestaña 1: Datos Personales -->
    <div class="tab-pane fade show active" id="pane-emp-personales" role="tabpanel" aria-labelledby="tab-emp-personales">
      <?php
      $prefixId = 'editEmpleado__';
      $prefixName = '';
      $showPhoto = false;
      $minDate = date('Y-m-d', strtotime('-100 years'));
      $maxDate = date('Y-m-d', strtotime('-18 years'));
      $dateTitle = 'El empleado debe tener entre 18 y 100 años.';
      $docMinLength = '7';
      $docMaxLength = '8';
      $tipoDocOpciones = ['V', 'E'];
      $pideMunicipioParroquiaNacimiento = false;

      include __DIR__ . '/../../components/persona-form.php';
      ?>
    </div>

    <!-- Pestaña 2: Datos Laborales -->
    <div class="tab-pane fade" id="pane-emp-laborales" role="tabpanel" aria-labelledby="tab-emp-laborales">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="label-sigde" for="editEmpleado__cargo">Cargo <span class="text-danger">*</span></label>
          <select class="form-select" id="editEmpleado__cargo" name="id_cargo" required>
            <option value="">Seleccione...</option>
            <?php foreach ($cargos as $option): ?>
              <option value="<?= (int) $option['id_cargo'] ?>"><?= htmlspecialchars($option['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="invalid-feedback">El cargo es obligatorio.</div>
        </div>

        <div class="col-md-6">
          <label class="label-sigde" for="editEmpleado__fechaIngreso">Fecha de ingreso <span class="text-danger">*</span></label>
          <input
            type="date"
            class="form-control"
            id="editEmpleado__fechaIngreso"
            name="fecha_ingreso"
            min="1980-01-01"
            max="<?= date('Y-m-d') ?>"
            required>
          <div class="invalid-feedback">La fecha de ingreso es obligatoria.</div>
        </div>

        <div class="col-md-6">
          <label class="label-sigde" for="editEmpleado__turno">Turno de trabajo</label>
          <select class="form-select" id="editEmpleado__turno" name="id_turno">
            <option value="">Sin turno asignado</option>
            <?php foreach (($turnos ?? []) as $turno): ?>
              <option value="<?= (int) $turno['id_turno'] ?>"><?= htmlspecialchars($turno['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>

    <!-- Pestaña 3: Contacto y Domicilio -->
    <div class="tab-pane fade" id="pane-emp-contacto" role="tabpanel" aria-labelledby="tab-emp-contacto">
      <?php
      $prefixId = 'editEmpleado__';
      $prefixName = '';
      $supportText = 'Actualice los datos de comunicación del trabajador para asegurar una vía de contacto efectiva.';
      $phoneRequired = true;
      include __DIR__ . '/../../components/contacto-form.php';
      ?>
      <hr class="my-4">
      <?php
      $prefixId = 'editEmpleado__';
      $prefixName = '';
      $direccionRequired = true;
      include __DIR__ . '/../../components/direccion-form.php';
      ?>
    </div>

    <!-- Pestaña 4: Formación Académica -->
    <div class="tab-pane fade" id="pane-emp-formacion" role="tabpanel" aria-labelledby="tab-emp-formacion">
      <input type="hidden" name="id_formacion_academica" id="editFormacionId" value="">
      <input type="hidden" name="eliminar_formacion" id="editFormacionEliminar" value="">

      <div id="editFormacionActual" class="d-none mb-3">
        <div class="d-flex align-items-center gap-2 border rounded px-3 py-2">
          <i class="bi bi-mortarboard text-primary" aria-hidden="true"></i>
          <div class="flex-grow-1">
            <div class="fw-semibold" id="editFormacionTitulo">—</div>
            <div class="text-support small" id="editFormacionDetalle">—</div>
          </div>
          <button type="button" class="btn btn-sm btn-outline-danger" id="editFormacionQuitar">
            <i class="bi bi-trash3 me-1" aria-hidden="true"></i>Quitar
          </button>
        </div>
      </div>

      <div class="row g-2">
        <div class="col-md-6">
          <label class="label-sigde" for="editEmpleado__gradoAcademico">Grado académico</label>
          <select class="form-select" id="editEmpleado__gradoAcademico" name="id_grado_academico">
            <option value="">Seleccione...</option>
            <?php foreach ($gradosAcademicos as $option): ?>
              <option value="<?= (int) $option['id_grado_academico'] ?>"><?= htmlspecialchars($option['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-6">
          <label class="label-sigde" for="editEmpleado__titulo">Título</label>
          <select class="form-select" id="editEmpleado__titulo" name="id_titulo">
            <option value="">Seleccione...</option>
            <?php foreach ($titulos as $titulo): ?>
              <option value="<?= (int) $titulo['id_titulo'] ?>" data-id-grado="<?= (int) ($titulo['id_grado_academico'] ?? 0) ?>"><?= htmlspecialchars($titulo['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-8">
          <label class="label-sigde" for="editEmpleado__institucion">Institución</label>
          <select class="form-select" id="editEmpleado__institucion" name="id_institucion">
            <option value="">Seleccione...</option>
            <?php foreach ($instituciones as $option): ?>
              <option value="<?= (int) $option['id_institucion'] ?>"><?= htmlspecialchars($option['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-4">
          <label class="label-sigde" for="editEmpleado__fechaObtencion">Fecha de obtención</label>
          <input type="date" class="form-control" id="editEmpleado__fechaObtencion" name="fecha_obtencion">
        </div>
      </div>
    </div>
  </div>
</form>
<?php
$modalBody     = ob_get_clean();
$modalId       = 'editarEmpleadoModal';
$modalTitle    = 'Editar Empleado';
$modalSubtitle = '';
$modalSize     = 'xl';
$modalStatic   = true;

ob_start();
?>
<button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancelar</button>
<button type="submit" form="formEditarEmpleado" id="btnGuardarEdicionEmpleado" class="btn btn-primary">
  <i class="bi bi-floppy me-1"></i> Guardar cambios
</button>
<?php
$modalFooter = ob_get_clean();

include __DIR__ . '/../../components/modal.php';
