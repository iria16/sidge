/* SIGDE - modules/empleados/empleado-expediente.js
 * Acciones de reposo dentro del expediente: alta, edición, cierre y anulación.
 * Los formularios y la validación viven en empleado-reposo.js; este archivo solo
 * conecta los botones renderizados por el servidor con esa API. */

(function () {
  'use strict';

  function empleadoId() {
    return new URLSearchParams(window.location.search).get('id') || '';
  }

  function nombreEmpleado() {
    var btn = document.getElementById('btnNuevoReposoExpediente');
    return btn ? (btn.dataset.empleadoNombre || '') : '';
  }

  function init() {
    if (!window.SIGDEEmpleados || !SIGDEEmpleados.reposos) return;

    document.addEventListener('click', function (e) {
      var id = empleadoId();
      var nombre = nombreEmpleado();

      var btnNuevo = e.target.closest('#btnNuevoReposoExpediente');
      if (btnNuevo) {
        var empId = btnNuevo.dataset.empleadoId || id;
        var empNombre = btnNuevo.dataset.empleadoNombre || nombre;
        var activo = btnNuevo.dataset.empleadoActivo === '1';
        if (window.SIGDEEmpleados && SIGDEEmpleados.reposos && SIGDEEmpleados.reposos.abrirNuevo) {
          SIGDEEmpleados.reposos.abrirNuevo(empId, empNombre, activo);
        }
        return;
      }

      var btnEditar = e.target.closest('.btn-editar-reposo');
      if (btnEditar) {
        SIGDEEmpleados.reposos.abrirEditar({
          id_reposo: btnEditar.dataset.reposoId,
          fecha_inicio: btnEditar.dataset.fechaInicio || '',
          fecha_fin: btnEditar.dataset.fechaFin || '',
          motivo: btnEditar.dataset.motivo || '',
          observacion: btnEditar.dataset.observacion || ''
        }, id, nombre, true);
        return;
      }

      var btnCerrar = e.target.closest('.btn-cerrar-reposo');
      if (btnCerrar) {
        SIGDEEmpleados.reposos.abrirCerrar({
          id_reposo: btnCerrar.dataset.reposoId,
          id_empleado: id,
          fecha_inicio: btnCerrar.dataset.fechaInicio || '',
          fecha_fin: btnCerrar.dataset.fechaFin || ''
        }, btnCerrar.dataset.empleadoNombre || nombre, id);
        return;
      }

      var btnAnular = e.target.closest('.btn-anular-reposo');
      if (btnAnular) {
        SIGDEEmpleados.reposos.abrirAnular({
          id_reposo: btnAnular.dataset.reposoId,
          id_empleado: id
        }, btnAnular.dataset.empleadoNombre || nombre, id);
      }
    });
  }

  if (document.readyState === 'interactive' || document.readyState === 'complete') {
    init();
  } else {
    document.addEventListener('DOMContentLoaded', init);
  }
})();
