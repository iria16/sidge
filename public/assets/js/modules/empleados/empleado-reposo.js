/* SIGDE - modules/empleados/empleado-reposo.js
 * Reposos y permisos médicos del personal: historial, alta, edición y cierre rápido, más el
 * badge que refleja el estado en la tabla. Lo usan el modal de Ver Detalle (que pinta el
 * historial en su propia pestaña) y los botones de la tabla.
 *
 * Depende de core/sigde-ui.js y de este mismo directorio, empleado-comun.js.*/

(function () {
  'use strict';

// --- Reposos y Permisos Médicos del Personal ---

function cargarRepososEmpleado(idEmpleado, nombreCompleto, activo) {
  var contenedorVigente = document.getElementById('reposoVigenteCard');
  var periodoVigente = document.getElementById('reposoVigentePeriodo');
  var motivoVigente = document.getElementById('reposoVigenteMotivo');
  var btnCerrarVigente = document.getElementById('btnCerrarReposoVigente');
  var btnEditarVigente = document.getElementById('btnEditarReposoVigente');
  var historialTbody = document.getElementById('historialRepososBody');
  var detalleBadgeReposo = document.getElementById('detalleBadgeReposo');

  if (!historialTbody) return;

  historialTbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-2"><span class="spinner-border spinner-border-sm me-1" role="status"></span> Cargando reposos...</td></tr>';
  if (contenedorVigente) contenedorVigente.hidden = true;

  SIGDEUI.enviar(SIGDEEmpleados.baseUrl + SIGDEEmpleados.rutaControlador + '/getReposos?id_empleado=' + encodeURIComponent(idEmpleado))
    .then(function (res) {
      if (!res.ok || !res.data || !res.data.ok) {
        var msg = (res.data && res.data.mensaje) || 'No se pudo cargar el historial de reposos.';
        historialTbody.innerHTML = '<tr><td colspan="4" class="text-danger text-center py-2">' + SIGDEUI.escapeHtml(msg) + '</td></tr>';
        return;
      }

      var respuestaServidor = res.data.data || {};
      var reposos = respuestaServidor.reposos || [];
      var vigente = respuestaServidor.vigente || null;

      if (detalleBadgeReposo) {
        detalleBadgeReposo.hidden = !vigente;
      }
      SIGDEEmpleados.actualizarBadgeReposoEnTabla(idEmpleado, !!vigente);

      if (vigente && contenedorVigente) {
        var textoPeriodo = '';
        if (vigente.fecha_fin) {
          textoPeriodo = SIGDEUI.formatearFechaCorta(vigente.fecha_inicio) + ' al ' + SIGDEUI.formatearFechaCorta(vigente.fecha_fin);
        } else {
          textoPeriodo = 'Abierto desde ' + SIGDEUI.formatearFechaCorta(vigente.fecha_inicio);
        }
        if (periodoVigente) periodoVigente.textContent = textoPeriodo;
        if (motivoVigente) motivoVigente.textContent = vigente.motivo || 'Sin motivo especificado';

        if (btnCerrarVigente) {
          btnCerrarVigente.onclick = function () {
            cerrarReposoRapido(vigente, idEmpleado, nombreCompleto, activo);
          };
        }
        if (btnEditarVigente) {
          btnEditarVigente.onclick = function () {
            abrirModalEditarReposo(vigente, idEmpleado, nombreCompleto, activo);
          };
        }
        contenedorVigente.hidden = false;
      } else if (contenedorVigente) {
        contenedorVigente.hidden = true;
      }

      if (reposos.length === 0) {
        historialTbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">No hay reposos ni licencias registradas para este empleado.</td></tr>';
        return;
      }

      var html = '';
      reposos.forEach(function (r) {
        var periodoStr = '';
        if (r.fecha_fin) {
          periodoStr = SIGDEUI.formatearFechaCorta(r.fecha_inicio) + ' — ' + SIGDEUI.formatearFechaCorta(r.fecha_fin);
        } else {
          periodoStr = '<span class="text-primary fw-medium"><i class="bi bi-clock-history me-1"></i>Abierto desde ' + SIGDEUI.formatearFechaCorta(r.fecha_inicio) + '</span>';
        }

        var badgeEstado = '';
        if (r.vigente) {
          badgeEstado = '<span class="status-badge status-badge--pending">Vigente</span>';
        } else {
          badgeEstado = '<span class="status-badge status-badge--inactive">Finalizado</span>';
        }

        var motivoStr = r.motivo ? SIGDEUI.escapeHtml(r.motivo) : '<span class="text-muted fst-italic">Sin motivo</span>';

        var botonesAccion = '<div class="d-flex justify-content-end gap-1">';
        if (!r.fecha_fin || r.vigente) {
          botonesAccion += '<button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 btn-cerrar-fila" title="Cerrar reposo" data-id="' + r.id_reposo + '"><i class="bi bi-check2-circle"></i> Cerrar</button>';
        }
        botonesAccion += '<button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 btn-editar-fila" title="Editar reposo" data-id="' + r.id_reposo + '"><i class="bi bi-pencil"></i></button>';
        botonesAccion += '</div>';

        html += '<tr>' +
          '<td>' + periodoStr + '</td>' +
          '<td>' + motivoStr + '</td>' +
          '<td>' + badgeEstado + '</td>' +
          '<td class="text-end">' + botonesAccion + '</td>' +
          '</tr>';
      });

      historialTbody.innerHTML = html;

      historialTbody.querySelectorAll('.btn-cerrar-fila').forEach(function (btn) {
        var rId = parseInt(btn.dataset.id, 10);
        var reposoObj = reposos.find(function (item) { return item.id_reposo === rId; });
        if (reposoObj) {
          btn.addEventListener('click', function () {
            cerrarReposoRapido(reposoObj, idEmpleado, nombreCompleto, activo);
          });
        }
      });

      historialTbody.querySelectorAll('.btn-editar-fila').forEach(function (btn) {
        var rId = parseInt(btn.dataset.id, 10);
        var reposoObj = reposos.find(function (item) { return item.id_reposo === rId; });
        if (reposoObj) {
          btn.addEventListener('click', function () {
            abrirModalEditarReposo(reposoObj, idEmpleado, nombreCompleto, activo);
          });
        }
      });
    })
    .catch(function (err) {
      console.error('Error al cargar reposos:', err);
      historialTbody.innerHTML = '<tr><td colspan="4" class="text-center text-danger py-2">Fallo de conexión al cargar los reposos.</td></tr>';
    });
}

async function cerrarReposoRapido(reposo, idEmpleado, nombreCompleto, activo) {
  var fechaFinCierre = SIGDEEmpleados.fechaHoyISO();
  if (reposo.fecha_inicio && fechaFinCierre < reposo.fecha_inicio) {
    fechaFinCierre = reposo.fecha_inicio;
  }

  var confirmacion = await SIGDEUI.confirmar({
    titulo: '¿Cerrar reposo médico?',
    texto: 'Se registrará como fecha de culminación el ' + SIGDEUI.formatearFechaCorta(fechaFinCierre) + '. ¿Desea continuar?',
    confirmar: 'Sí, cerrar reposo',
    cancelar: 'Cancelar',
    icono: 'question'
  });

  if (!confirmacion) return;

  var formData = new FormData();
  formData.append('id_reposo', reposo.id_reposo);
  formData.append('id_empleado', idEmpleado);
  formData.append('fecha_inicio', reposo.fecha_inicio);
  formData.append('fecha_fin', fechaFinCierre);
  formData.append('motivo', reposo.motivo || '');

  try {
    var res = await SIGDEUI.enviar(SIGDEEmpleados.baseUrl + SIGDEEmpleados.rutaControlador + '/updateReposo', {
      metodo: 'POST',
      cuerpo: formData
    });
    var data = res.data;

    if (!res.ok || !data.ok) {
      SIGDEUI.mostrarError((data && data.mensaje) || 'No se pudo cerrar el reposo.', 'Error al cerrar reposo');
      return;
    }

    SIGDEUI.exito(data.mensaje || 'Reposo cerrado correctamente.', '¡Listo!');
    if (document.getElementById('historialRepososBody')) {
      cargarRepososEmpleado(idEmpleado, nombreCompleto, activo);
    } else {
      setTimeout(function () { window.location.reload(); }, 600);
    }
  } catch (err) {
    console.error('Error al cerrar reposo:', err);
    SIGDEUI.mostrarError('Fallo de conexión al cerrar el reposo.');
  }
}

async function anularReposoRapido(reposo, nombreCompleto, idEmpleado) {
  var confirmacion = await SIGDEUI.confirmar({
    titulo: '¿Anular reposo médico?',
    texto: 'El reposo quedará en estado ANULADO y no se considerará en los cálculos de vigencia ni traslapes. ¿Desea continuar?',
    confirmar: 'Sí, anular reposo',
    cancelar: 'Cancelar',
    icono: 'warning'
  });

  if (!confirmacion) return;

  var formData = new FormData();
  formData.append('id_reposo', reposo.id_reposo);
  formData.append('observacion', 'Anulado desde el expediente.');

  try {
    var res = await SIGDEUI.enviar(SIGDEEmpleados.baseUrl + SIGDEEmpleados.rutaControlador + '/anularReposo', {
      metodo: 'POST',
      cuerpo: formData
    });
    var data = res.data;

    if (!res.ok || !data.ok) {
      SIGDEUI.mostrarError((data && data.mensaje) || 'No se pudo anular el reposo.', 'Error al anular reposo');
      return;
    }

    SIGDEUI.exito(data.mensaje || 'Reposo anulado correctamente.', '¡Listo!');
    if (document.getElementById('historialRepososBody')) {
      cargarRepososEmpleado(idEmpleado, nombreCompleto, true);
    } else {
      setTimeout(function () { window.location.reload(); }, 600);
    }
  } catch (err) {
    console.error('Error al anular reposo:', err);
    SIGDEUI.mostrarError('Fallo de conexión al anular el reposo.');
  }
}

function abrirModalEditarReposo(reposo, idEmpleado, nombreCompleto, activo) {
  var modalEl = document.getElementById('reposoEmpleadoModal');
  if (!modalEl) return;

  limpiarErroresFormReposo();

  var inputId = document.getElementById('reposoId');
  var inputEmpId = document.getElementById('reposoEmpleadoId');
  var inputEmpNombre = document.getElementById('reposoEmpleadoNombre');
  var inputInicio = document.getElementById('reposoFechaInicio');
  var inputFin = document.getElementById('reposoFechaFin');
  var inputMotivo = document.getElementById('reposoMotivo');
  var labelModal = document.getElementById('reposoEmpleadoModalLabel');

  if (inputId) inputId.value = reposo.id_reposo;
  if (inputEmpId) inputEmpId.value = idEmpleado;
  if (inputEmpNombre) inputEmpNombre.value = nombreCompleto;
  if (inputInicio) inputInicio.value = reposo.fecha_inicio || '';
  if (inputFin) inputFin.value = reposo.fecha_fin || '';
  if (inputMotivo) inputMotivo.value = reposo.motivo || '';
  if (labelModal) labelModal.textContent = 'Editar Reposo Médico';

  modalEl.dataset.empleadoActivo = activo ? '1' : '0';

  SIGDEUI.mostrarModal(modalEl);
}

function abrirModalNuevoReposo(idEmpleado, nombreCompleto, activo) {
  if (!activo) {
    SIGDEUI.mostrarAviso('warning', 'Empleado inactivo', 'Solo se pueden registrar reposos para empleados activos.');
    return;
  }

  var modalEl = document.getElementById('reposoEmpleadoModal');
  if (!modalEl) return;

  limpiarErroresFormReposo();

  var inputId = document.getElementById('reposoId');
  var inputEmpId = document.getElementById('reposoEmpleadoId');
  var inputEmpNombre = document.getElementById('reposoEmpleadoNombre');
  var inputInicio = document.getElementById('reposoFechaInicio');
  var inputFin = document.getElementById('reposoFechaFin');
  var inputMotivo = document.getElementById('reposoMotivo');
  var labelModal = document.getElementById('reposoEmpleadoModalLabel');

  if (inputId) inputId.value = '';
  if (inputEmpId) inputEmpId.value = idEmpleado;
  if (inputEmpNombre) inputEmpNombre.value = nombreCompleto;
  if (inputInicio) inputInicio.value = SIGDEEmpleados.fechaHoyISO();
  if (inputFin) inputFin.value = '';
  if (inputMotivo) inputMotivo.value = '';
  if (labelModal) labelModal.textContent = 'Registrar Reposo Médico';

  modalEl.dataset.empleadoActivo = '1';

  SIGDEUI.mostrarModal(modalEl);
}

function limpiarErroresFormReposo() {
  var form = document.getElementById('formReposoEmpleado');
  if (!form) return;
  form.querySelectorAll('.is-invalid').forEach(function (el) {
    el.classList.remove('is-invalid');
  });
  var errInicio = document.getElementById('errorReposoFechaInicio');
  if (errInicio) errInicio.textContent = 'Indica la fecha de inicio.';
  var errFin = document.getElementById('errorReposoFechaFin');
  if (errFin) errFin.textContent = '';
  var errMotivo = document.getElementById('errorReposoMotivo');
  if (errMotivo) errMotivo.textContent = '';
}

function initRepososEmpleado() {
  var btnNuevo = document.getElementById('btnNuevoReposoDesdeDetalle');
  var modalReposoEl = document.getElementById('reposoEmpleadoModal');
  var formReposo = document.getElementById('formReposoEmpleado');
  var btnGuardar = document.getElementById('btnGuardarReposo');

  if (btnNuevo) {
    btnNuevo.addEventListener('click', function () {
      var idEmp = btnNuevo.dataset.empleadoId;
      var nombreEmp = btnNuevo.dataset.empleadoNombre;
      var activo = btnNuevo.dataset.empleadoActivo === '1';
      abrirModalNuevoReposo(idEmp, nombreEmp, activo);
    });
  }

  // Al ocultar el modal de reposo, si el modal de ver detalle sigue abierto, restaurar modal-open en el body
  if (modalReposoEl) {
    modalReposoEl.addEventListener('hidden.bs.modal', function () {
      var modalVerEl = document.getElementById('verEmpleadoModal');
      if (modalVerEl && modalVerEl.classList.contains('show')) {
        document.body.classList.add('modal-open');
      }
    });
  }

  if (btnGuardar && formReposo) {
    btnGuardar.addEventListener('click', async function () {
      limpiarErroresFormReposo();

      var inputId = document.getElementById('reposoId');
      var inputEmpId = document.getElementById('reposoEmpleadoId');
      var inputInicio = document.getElementById('reposoFechaInicio');
      var inputFin = document.getElementById('reposoFechaFin');
      var inputMotivo = document.getElementById('reposoMotivo');

      var idReposo = (inputId && inputId.value || '').trim();
      var idEmpleado = (inputEmpId && inputEmpId.value || '').trim();
      var fechaInicio = (inputInicio && inputInicio.value || '').trim();
      var fechaFin = (inputFin && inputFin.value || '').trim();
      var motivo = (inputMotivo && inputMotivo.value || '').trim();
      var nombreEmpleado = (document.getElementById('reposoEmpleadoNombre') && document.getElementById('reposoEmpleadoNombre').value) || '';
      var activo = modalReposoEl && modalReposoEl.dataset.empleadoActivo === '1';

      var hayError = false;

      if (!fechaInicio) {
        if (inputInicio) inputInicio.classList.add('is-invalid');
        var errIni = document.getElementById('errorReposoFechaInicio');
        if (errIni) errIni.textContent = 'Indica la fecha de inicio del reposo.';
        hayError = true;
      }

      if (fechaFin && fechaInicio && fechaFin < fechaInicio) {
        if (inputFin) inputFin.classList.add('is-invalid');
        var errF = document.getElementById('errorReposoFechaFin');
        if (errF) errF.textContent = 'La fecha de fin no puede ser anterior a la de inicio.';
        hayError = true;
      }

      if (motivo.length > 200) {
        if (inputMotivo) inputMotivo.classList.add('is-invalid');
        var errM = document.getElementById('errorReposoMotivo');
        if (errM) errM.textContent = 'El motivo no puede pasar de 200 caracteres.';
        hayError = true;
      }

      if (hayError) return;

      var esEdicion = idReposo !== '' && parseInt(idReposo, 10) > 0;
      var endpoint = esEdicion
        ? SIGDEEmpleados.baseUrl + SIGDEEmpleados.rutaControlador + '/updateReposo'
        : SIGDEEmpleados.baseUrl + SIGDEEmpleados.rutaControlador + '/registrarReposo';

      var formData = new FormData(formReposo);

      var htmlOriginal = btnGuardar.innerHTML;
      btnGuardar.disabled = true;
      btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Guardando...';

      try {
        var res = await SIGDEUI.enviar(endpoint, {
          metodo: 'POST',
          cuerpo: formData
        });
        var data = res.data;

        if (!res.ok || !data.ok) {
          if (data && data.data && typeof data.data === 'object') {
            if (data.data.fecha_inicio && inputInicio) {
              inputInicio.classList.add('is-invalid');
              var errIni2 = document.getElementById('errorReposoFechaInicio');
              if (errIni2) errIni2.textContent = data.data.fecha_inicio;
            }
            if (data.data.fecha_fin && inputFin) {
              inputFin.classList.add('is-invalid');
              var errFin2 = document.getElementById('errorReposoFechaFin');
              if (errFin2) errFin2.textContent = data.data.fecha_fin;
            }
            if (data.data.motivo && inputMotivo) {
              inputMotivo.classList.add('is-invalid');
              var errMot2 = document.getElementById('errorReposoMotivo');
              if (errMot2) errMot2.textContent = data.data.motivo;
            }
          }
          SIGDEUI.mostrarError((data && data.mensaje) || 'No se pudo guardar el reposo.', 'Error');
          return;
        }

        SIGDEUI.ocultarModal(modalReposoEl);
        SIGDEUI.exito(data.mensaje || 'Reposo guardado correctamente.', '¡Listo!');

        if (document.getElementById('historialRepososBody')) {
          cargarRepososEmpleado(idEmpleado, nombreEmpleado, activo);
        } else {
          setTimeout(function () { window.location.reload(); }, 600);
        }
      } catch (err) {
        console.error('Error al guardar reposo:', err);
        SIGDEUI.mostrarError('Fallo de conexión al guardar el reposo.');
      } finally {
        btnGuardar.disabled = false;
        btnGuardar.innerHTML = htmlOriginal;
      }
    });
  }
}

  /* Publica la API de reposos en el espacio de nombres SIGDEEmpleados.
   * La usan el modal de Ver Detalle y la pantalla del expediente. */
  SIGDEEmpleados.reposos = {
    cargar: cargarRepososEmpleado,
    abrirNuevo: abrirModalNuevoReposo,
    abrirEditar: abrirModalEditarReposo,
    abrirCerrar: cerrarReposoRapido,
    abrirAnular: anularReposoRapido
  };

  // --- Inicialización Módulo ---
  function init() {
    initRepososEmpleado();
  }

  // Inicialización segura del DOM que evita problemas cuando los scripts
  // se cargan con 'defer', 'async' o al final de la plantilla layout.
  if (document.readyState === 'interactive' || document.readyState === 'complete') {
    init();
  } else {
    document.addEventListener('DOMContentLoaded', init);
  }
})();
