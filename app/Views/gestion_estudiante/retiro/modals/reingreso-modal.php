<?php
declare(strict_types=1);

/**
 * Modal: retiro/modals/reingreso-modal.php
 * Formalización de reincorporación / reingreso de estudiante retirado.
 */
?>
<div class="modal fade" id="reingresoModal" tabindex="-1" aria-labelledby="reingresoModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title d-flex align-items-center gap-2" id="reingresoModalLabel">
          <i class="bi bi-arrow-counterclockwise text-primary"></i>
          <span>Reingreso / Reincorporación Escolar</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <form id="formReingreso" novalidate>
        <div class="modal-body p-4">
          <input type="hidden" id="reingresoIdEstudiante" name="id_estudiante" value="">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">

          <!-- Cargando -->
          <div id="reingresoCargando" class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
              <span class="visually-hidden">Cargando...</span>
            </div>
            <div class="text-support mt-2">Consultando antecedentes del estudiante...</div>
          </div>

          <!-- Contenido del formulario -->
          <div id="reingresoContenido" class="d-none">
            <!-- Tarjeta resumen del retiro previo -->
            <div class="card bg-light border-0 mb-4">
              <div class="card-body">
                <div class="row g-3">
                  <div class="col-md-6">
                    <span class="text-support small d-block">Estudiante</span>
                    <strong class="fs-6" id="reingresoNombre">—</strong>
                    <div class="text-muted small" id="reingresoDocumento">—</div>
                  </div>
                  <div class="col-md-6">
                    <span class="text-support small d-block">Antecedente de Retiro</span>
                    <div class="small">
                      <strong>Grado previo:</strong> <span id="reingresoGradoPrevio">—</span><br>
                      <strong>Fecha de retiro:</strong> <span id="reingresoFechaRetiro">—</span><br>
                      <strong>Motivo:</strong> <span id="reingresoMotivoRetiro">—</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="row g-3">
              <!-- Grado y sección a cursar -->
              <div class="col-12">
                <label for="reingresoEstructura" class="form-label label-sigde fw-semibold">
                  Grado y Sección a Cursar (Año Escolar Activo) <span class="text-danger">*</span>
                </label>
                <select class="form-select" id="reingresoEstructura" name="id_estructura_academica" required>
                  <option value="">Seleccione el grado y sección...</option>
                </select>
                <div class="invalid-feedback">Debe seleccionar la sección y grado a asignar.</div>
              </div>

              <!-- Fecha de reincorporación -->
              <div class="col-md-6">
                <label for="reingresoFecha" class="form-label label-sigde fw-semibold">
                  Fecha de Reincorporación <span class="text-danger">*</span>
                </label>
                <input type="date" class="form-control" id="reingresoFecha" name="fecha_reincorporacion"
                       value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
                <div class="invalid-feedback">Indique la fecha de reincorporación.</div>
              </div>

              <!-- Institución de procedencia opcional -->
              <div class="col-md-6">
                <label for="reingresoInstitucion" class="form-label label-sigde fw-semibold">
                  Plantel de Procedencia (Opcional)
                </label>
                <select class="form-select" id="reingresoInstitucion" name="id_institucion_procedencia">
                  <option value="">Ninguno / Retorno directo</option>
                </select>
                <div class="form-text">Si cursó en otro plantel durante el período retirado.</div>
              </div>

              <!-- Motivo de reingreso -->
              <div class="col-12">
                <label for="reingresoMotivo" class="form-label label-sigde fw-semibold">
                  Motivo de Reingreso <span class="text-danger">*</span>
                </label>
                <input type="text" class="form-control" id="reingresoMotivo" name="motivo_reingreso"
                       placeholder="Ej: Retorno a la comunidad / Solicitud formal de reincorporación" required maxlength="255">
                <div class="invalid-feedback">Especifique el motivo del reingreso.</div>
              </div>

              <!-- Observaciones -->
              <div class="col-12">
                <label for="reingresoObservaciones" class="form-label label-sigde fw-semibold">
                  Observaciones adicionales
                </label>
                <textarea class="form-control" id="reingresoObservaciones" name="observaciones" rows="2"
                          placeholder="Resolución directiva, recaudos actualizados o notas pedagógicas..."></textarea>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary d-flex align-items-center gap-2" id="btnGuardarReingreso">
            <i class="bi bi-check-lg"></i>
            <span>Formalizar Reingreso</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>