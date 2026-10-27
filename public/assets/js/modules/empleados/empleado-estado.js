/* SIGDE - modules/empleados/empleado-estado.js
 * Cambio de estado del empleado con motivo, fecha efectiva e historial.
 * Reemplaza el toggle directo: el switch y el botón del expediente abren el
 * mismo modal, que primero consulta el impacto (asignaciones y reposo vigente). */

(function () {
  'use strict';

  var modalEl = null;
  var formEl = null;
  var btnGuardar = null;
  var switchPendiente = null;
  var guardado = false;

  function hoyISO() {
    return (window.SIGDEEmpleados && SIGDEEmpleados.fechaHoyISO)
      ? SIGDEEmpleados.fechaHoyISO()
      : new Date().toISOString().slice(0, 10);
  }

  function nombreEstado(estado) {
    return estado === 'ACTIVO' ? 'Activo' : 'Inactivo';
  }

  function limpiarErrores() {
    if (!formEl) return;
    formEl.querySelectorAll('.is-invalid').forEach(function (el) {
      el.classList.remove('is-invalid');
    });
  }

  function mostrarError(campo, mensaje) {
    var input = document.getElementById('estado' + campo);
    var error = document.getElementById('errorEstado' + campo);
    if (input) input.classList.add('is-invalid');
    if (error) error.textContent = mensaje;
  }

  function pintarImpacto(data) {
    var advertencia = document.getElementById('estadoAdvertencia');
    var texto = document.getElementById('estadoAdvertenciaTexto');
    var lista = document.getElementById('estadoAsignacionesLista');
    var avisoReposo = document.getElementById('estadoReposoAviso');
    if (!advertencia || !texto || !lista || !avisoReposo) return;

    lista.innerHTML = '';
    avisoReposo.hidden = true;

    var asignaciones = (data && data.asignaciones) || [];
    var reposo = data && data.reposo_vigente;

    if (!asignaciones.length && !reposo) {
      advertencia.hidden = true;
      return;
    }

    advertencia.hidden = false;
    texto.textContent = asignaciones.length
      ? 'Este empleado tiene asignaciones activas que se cerrarán en la fecha efectiva:'
      : 'Este empleado está en reposo hoy.';

    asignaciones.forEach(function (a) {
      var li = document.createElement('li');
      li.textContent = [a.periodo, a.grado, a.seccion, a.turno, a.tipo_asignacion]
        .filter(Boolean)
        .join(' · ');
      lista.appendChild(li);
    });

    if (reposo) {
      avisoReposo.hidden = false;
      avisoReposo.textContent = 'Reposo vigente: ' + (reposo.fecha_inicio || '') +
        ' al ' + (reposo.fecha_fin || '') + '.';
    }
  }

  function consultarImpacto(id, destino, fecha) {
    var url = SIGDEEmpleados.baseUrl + SIGDEEmpleados.rutaControlador + '/estadoInfo'
      + '?id=' + encodeURIComponent(id)
      + '&nuevo_estado=' + encodeURIComponent(destino)
      + '&fecha_efectiva=' + encodeURIComponent(fecha);

    SIGDEUI.enviar(url)
      .then(function (res) {
        if (!res.ok || !res.data || !res.data.ok) {
          return;
        }
        pintarImpacto(res.data.data);
      })
      .catch(function () {
        /* La advertencia es informativa: si falla, el servidor vuelve a comprobar
           el impacto al guardar y no se permite un cambio accidental. */
      });
  }

  function abrirModal(datos) {
    if (!modalEl || !formEl) return;

    limpiarErrores();

    var id = String(datos.id || '');
    var estados = {
      actual: datos.estado === 'ACTIVO' ? 'ACTIVO' : 'INACTIVO',
      destino: datos.destino === 'ACTIVO' ? 'ACTIVO' : 'INACTIVO'
    };

    document.getElementById('estadoEmpleadoId').value = id;
    document.getElementById('estadoEmpleadoNombre').textContent = datos.nombre || '—';
    document.getElementById('estadoEmpleadoActual').textContent = nombreEstado(estados.actual);
    document.getElementById('estadoEmpleadoDestino').textContent = nombreEstado(estados.destino);
    document.getElementById('estadoEmpleadoNuevo').value = estados.destino;
    document.getElementById('estadoEmpleadoConfirmar').value = '1';

    var fecha = document.getElementById('estadoFechaEfectiva');
    fecha.max = hoyISO();
    fecha.value = hoyISO();
    document.getElementById('estadoMotivo').value = '';

    var advertencia = document.getElementById('estadoAdvertencia');
    if (advertencia) advertencia.hidden = true;

    var nota = document.getElementById('estadoNotaLiberacion');
    if (nota) {
      nota.textContent = estados.destino === 'INACTIVO'
        ? 'Si el empleado tiene asignaciones activas, se cerrarán en la fecha efectiva. No se borran.'
        : 'Al activar no se cierran asignaciones.';
    }

    if (btnGuardar) {
      btnGuardar.disabled = false;
      btnGuardar.innerHTML = '<i class="bi bi-check-lg me-1"></i> Guardar cambio';
    }

    SIGDEUI.mostrarModal(modalEl);
    consultarImpacto(id, estados.destino, fecha.value);
  }

  function revertirSwitch() {
    if (!switchPendiente) return;
    switchPendiente.checked = switchPendiente.dataset.empleadoEstado === 'ACTIVO';
    switchPendiente = null;
  }

  function enviarCambio() {
    if (!formEl || !btnGuardar) return;

    limpiarErrores();

    var id = document.getElementById('estadoEmpleadoId').value;
    var motivo = (document.getElementById('estadoMotivo').value || '').trim();
    var fecha = document.getElementById('estadoFechaEfectiva').value || '';

    var hayError = false;

    if (!motivo) {
      mostrarError('Motivo', 'Indica el motivo del cambio.');
      hayError = true;
    }

    if (!fecha) {
      mostrarError('FechaEfectiva', 'Indica la fecha efectiva.');
      hayError = true;
    } else if (fecha > hoyISO()) {
      mostrarError('FechaEfectiva', 'La fecha efectiva no puede ser futura.');
      hayError = true;
    }

    if (hayError) return;

    var htmlOriginal = btnGuardar.innerHTML;
    btnGuardar.disabled = true;
    btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Guardando...';

    SIGDEUI.enviar(SIGDEEmpleados.baseUrl + SIGDEEmpleados.rutaControlador + '/cambiarEstado', {
      metodo: 'POST',
      cuerpo: new FormData(formEl)
    })
      .then(function (res) {
        var data = res.data;

        if (!res.ok || !data || !data.ok) {
          if (data && data.data && typeof data.data === 'object') {
            if (data.data.motivo) mostrarError('Motivo', data.data.motivo);
            if (data.data.fecha_efectiva) mostrarError('FechaEfectiva', data.data.fecha_efectiva);
          }
          SIGDEUI.mostrarError((data && data.mensaje) || 'No se pudo cambiar el estado.', 'Error');
          return;
        }

        guardado = true;
        SIGDEUI.ocultarModal(modalEl);
        SIGDEUI.exito(data.mensaje || 'Estado actualizado correctamente.', '¡Listo!');

        window.setTimeout(function () {
          window.location.reload();
        }, 450);
      })
      .catch(function () {
        SIGDEUI.mostrarError('Fallo de conexión al cambiar el estado.');
      })
      .finally(function () {
        btnGuardar.disabled = false;
        btnGuardar.innerHTML = htmlOriginal;
      });
  }

  function init() {
    modalEl = document.getElementById('estadoEmpleadoModal');
    formEl = document.getElementById('formEstadoEmpleado');
    btnGuardar = document.getElementById('btnGuardarEstadoEmpleado');

    if (!modalEl || !formEl || !btnGuardar) return;

    document.addEventListener('change', function (e) {
      var checkbox = e.target.closest('.toggle-estado');
      if (!checkbox) return;

      var estadoActual = checkbox.dataset.empleadoEstado === 'ACTIVO' ? 'ACTIVO' : 'INACTIVO';
      var destino = checkbox.checked ? 'ACTIVO' : 'INACTIVO';

      if (destino === estadoActual) return;

      switchPendiente = checkbox;
      guardado = false;

      abrirModal({
        id: checkbox.dataset.empleadoId,
        nombre: checkbox.dataset.empleadoNombre || '',
        estado: estadoActual,
        destino: destino
      });
    });

    document.addEventListener('click', function (e) {
      var btn = e.target.closest('#btnCambiarEstadoEmpleado');
      if (!btn) return;

      var actual = btn.dataset.empleadoEstado === 'ACTIVO' ? 'ACTIVO' : 'INACTIVO';
      switchPendiente = null;
      guardado = false;

      abrirModal({
        id: btn.dataset.empleadoId,
        nombre: btn.dataset.empleadoNombre || '',
        estado: actual,
        destino: actual === 'ACTIVO' ? 'INACTIVO' : 'ACTIVO'
      });
    });

    modalEl.addEventListener('hidden.bs.modal', function () {
      if (!guardado) revertirSwitch();
      switchPendiente = null;
    });

    formEl.addEventListener('submit', function (e) {
      e.preventDefault();
      enviarCambio();
    });

    btnGuardar.addEventListener('click', enviarCambio);
  }

  if (document.readyState === 'interactive' || document.readyState === 'complete') {
    init();
  } else {
    document.addEventListener('DOMContentLoaded', init);
  }
})();
