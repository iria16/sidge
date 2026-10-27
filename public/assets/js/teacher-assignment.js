/* SIGDE - comportamiento de Asignación Docente */
document.addEventListener('DOMContentLoaded', function () {
  var route = (window.BASE_URL || '') + 'teacherAssignment/';
  var newForm = document.getElementById('formNuevaAsignacion');
  var editForm = document.getElementById('formEditarAsignacion');
  var newStructure = document.getElementById('nuevoAsignacionSeccion');
  var newTeacher = document.getElementById('nuevoAsignacionDocente');

  function cargarDocentes(select, estructuraId) {
    if (!select) return Promise.resolve();
    select.replaceChildren(new Option('Cargando docentes...', ''));
    select.disabled = true;
    if (!estructuraId) {
      select.replaceChildren(new Option('Seleccione primero una sección...', ''));
      select.disabled = false;
      return Promise.resolve();
    }
    return SIGDEUI.enviar(route + 'getDocentesPorEstructura?id=' + encodeURIComponent(estructuraId))
      .then(function (result) {
        if (!result.ok || !result.data.ok) {
          SIGDEUI.mostrarError(result.data.mensaje || 'No se pudieron cargar los docentes.');
          select.replaceChildren(new Option('No se pudieron cargar los docentes', ''));
          return;
        }
        select.replaceChildren(new Option('Seleccione un docente...', ''));
        var list = Array.isArray(result.data.data) ? result.data.data : [];
        list.forEach(function (docente) {
          var label = docente.nombre + (docente.cargo ? ' · ' + docente.cargo : '');
          select.add(new Option(label, docente.id));
        });
        if (list.length === 0) {
          select.replaceChildren(new Option('No hay docentes asignados a este turno', ''));
        }
      })
      .catch(function () {
        SIGDEUI.mostrarError('No se pudieron cargar los docentes de este turno.');
        select.replaceChildren(new Option('Error al cargar docentes', ''));
      })
      .finally(function () {
        select.disabled = false;
      });
  }

  if (newStructure) {
    newStructure.addEventListener('change', function () {
      cargarDocentes(newTeacher, newStructure.value);
    });
  }

  function submit(form, isEdit) {
    if (!form.checkValidity()) {
      form.classList.add('was-validated');
      return;
    }
    var asignacionInput = form.querySelector('[name="id_asignacion"]');
    var asignacionId = asignacionInput ? parseInt(asignacionInput.value, 10) : 0;
    var action = isEdit && asignacionId > 0 ? 'actualizar' : 'asignar';
    SIGDEUI.enviar(route + action, { metodo: 'POST', cuerpo: new FormData(form) })
      .then(function (result) {
        if (!result.ok || !result.data.ok) {
          SIGDEUI.mostrarError(result.data.mensaje || 'No se pudo guardar la asignación.');
          return;
        }
        window.location.reload();
      })
      .catch(function () {
        SIGDEUI.mostrarError('No se pudo conectar con el servidor.');
      });
  }

  if (newForm) {
    newForm.addEventListener('submit', function (event) {
      event.preventDefault();
      submit(newForm, false);
    });
  }

  if (editForm) {
    editForm.addEventListener('submit', function (event) {
      event.preventDefault();
      submit(editForm, true);
    });
  }

  /* Abrir el modal de edición por DELEGACIÓN en document: main.js
     reemplaza el <tbody> por AJAX en cada filtro, búsqueda o paginación,
     por lo que los botones cambian dinámicamente. */
  document.addEventListener('click', function (e) {
    var button = e.target.closest('.editar-asignacion');
    if (!button) return;

    var modal = document.getElementById('editarAsignacionModal');
    var editStructure = document.getElementById('editarAsignacionEstructura');
    var editAssignmentId = document.getElementById('editarAsignacionId');
    var editTeacher = document.getElementById('editarAsignacionDocente');
    var editType = document.getElementById('editarAsignacionTipo');
    var editDate = document.getElementById('editarAsignacionFecha');

    if (editStructure) editStructure.value = button.dataset.id || '';
    if (editAssignmentId) editAssignmentId.value = button.dataset.asignacionId || '';
    if (editType) editType.value = button.dataset.tipoId || '';
    if (editDate) editDate.value = button.dataset.fechaInicio || (editDate ? editDate.defaultValue : '');

    SIGDEUI.mostrarModal(modal);

    cargarDocentes(editTeacher, button.dataset.id).then(function () {
      if (editTeacher) editTeacher.value = button.dataset.docenteId || '';
    });
  });

  /* Retirar asignación por delegación en document */
  document.addEventListener('click', function (e) {
    var button = e.target.closest('.quitar-asignacion');
    if (!button) return;

    SIGDEUI.confirmar({
      icono: 'warning',
      titulo: '¿Retirar asignación?',
      confirmar: 'Sí, retirar'
    }).then(function (confirmado) {
      if (!confirmado) return;
      SIGDEUI.enviar(route + 'retirar', { metodo: 'POST', cuerpo: { id: button.dataset.id } })
        .then(function (answer) {
          if (!answer.ok || !answer.data.ok) {
            SIGDEUI.mostrarError(answer.data.mensaje || 'No se pudo retirar.');
            return;
          }
          window.location.reload();
        });
    });
  });
});
