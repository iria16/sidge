/* SIGDE - modules/empleados/empleado-editar.js
 * Modal Editar Empleado: carga el registro, rellena el formulario (foto, tipo de documento,
 * cascada de dirección y formación académica) y lo guarda con empleado/update.
 *
 * Depende de core/sigde-ui.js, core/wizard-ui.js y de este mismo directorio, empleado-comun.js.*/

(function () {
  'use strict';

// --- Modal Editar Empleado ---
function initEditarEmpleadoModal() {
  var modalEditarEl = document.getElementById('editarEmpleadoModal');
  if (!modalEditarEl) return;

  var formEditar = document.getElementById('formEditarEmpleado');
  if (!formEditar) return;

  var btnGuardar = document.querySelector('button[form="formEditarEmpleado"]');
  var htmlBtnOriginal = btnGuardar ? btnGuardar.innerHTML : '';

  // --- Foto de perfil: cambiar o quitar (reutiliza .wizard-photo-upload) ---
  var editFotoPreview    = formEditar.querySelector('#editFotoPreview');
  var btnQuitarFoto      = formEditar.querySelector('#btnQuitarFotoEdicion');
  var fotoEdicion = SIGDEWizardUI.initFoto({
    inputId: 'editFotoInput',
    previewId: 'editFotoPreview',
    quitarId: 'btnQuitarFotoEdicion',
    flagId: 'editEliminarFoto'
  });

  // Pinta la foto actual (o el icono por defecto) en el círculo del
  // componente y muestra/oculta el botón "Quitar foto". ruta es relativa
  // tipo 'uploads/empleados/xxx.jpg'.
  function pintarFotoEdicion(ruta) {
    if (ruta) {
      if (editFotoPreview) {
        editFotoPreview.innerHTML = '<img src="' + SIGDEEmpleados.baseUrl + ruta.replace(/^\//, '') + '" alt="Foto de perfil">';
      }
      if (btnQuitarFoto) btnQuitarFoto.hidden = false;
    } else {
      if (editFotoPreview) {
        editFotoPreview.innerHTML = '<i class="bi bi-person"></i>';
      }
      if (btnQuitarFoto) btnQuitarFoto.hidden = true;
    }
  }

  SIGDEUI.alCerrarModal(modalEditarEl, function liberarFotoTemporal() {
    if (fotoEdicion) fotoEdicion.vaciar(false);
  });

  // --- Dirección: cascada estado->municipio->parroquia del modal de edición ---
  // La misma de SIGDEWizardUI.initCascadaUbicacion() que usa el wizard de alta
  // y el de inscripciones, pero sobre los selects del formulario de edición.
  var cascadaEdicion = SIGDEWizardUI.initCascadaUbicacion({
    url: SIGDEEmpleados.baseUrl + SIGDEEmpleados.rutaControlador + '/getUbicacion',
    estadoId: 'editEmpleado__Estado',
    municipioId: 'editEmpleado__Municipio',
    parroquiaId: 'editEmpleado__Parroquia'
  });

  /* Puebla la cascada con la dirección que ya tiene el empleado. Los tres
 select vienen habilitados y con sus listas completas (ver
 initCascadaUbicacion), así que solo hace falta elegir los tres valores; si
 alguno ya no está en el catálogo, elegirParroquia devuelve false y quien
 guarda lo ve en el resumen.*/
  function poblarUbicacionEdicion(d) {
    if (!d || !cascadaEdicion || typeof cascadaEdicion.elegirParroquia !== 'function') return;
    cascadaEdicion.elegirParroquia(
      Number(d.id_estado || 0),
      Number(d.id_municipio || 0),
      Number(d.id_parroquia || 0)
    );
  }

  // --- Tipo de documento: solo la letra cuando el select está cerrado ---
  // El comportamiento vive en wizard-ui.js, compartido con el wizard de
  // empleado y el de inscripción.
  SIGDEWizardUI.initTipoDocumentoLetra({ selectId: 'editEmpleado__TipoDocumento' });
  if (SIGDEWizardUI.initTarjetasSeleccionables) {
    SIGDEWizardUI.initTarjetasSeleccionables('[data-sexo-card]');
  }

  // --- Formación académica: el grado académico filtra los títulos ---
  // El grado académico filtra los títulos (data-id-grado del servidor), igual
  // que en el wizard; el filtrado vive en SIGDEUI.filtrarOpciones().
  var selGradoEd  = formEditar.elements.id_grado_academico;
  var selTituloEd = formEditar.elements.id_titulo;

  /* Pinta la formación que ya tiene el empleado dentro del modal. Antes los <select> de título,
 grado e institución abrían siempre en "Seleccione...": el modal se guardaba sin tocar nada de
 formación y, en apariencia, el título se perdía.*/
  function pintarFormacionEdicion(formacion) {
    var filas = Array.isArray(formacion) ? formacion : [];
    var caja = document.getElementById('editFormacionActual');
    var inputId = document.getElementById('editFormacionId');
    var quitar = document.getElementById('editFormacionEliminar');
    var selGrado = document.getElementById('editEmpleado__gradoAcademico');
    var selTitulo = document.getElementById('editEmpleado__titulo');
    var selInst = document.getElementById('editEmpleado__institucion');
    var fecha = document.getElementById('editEmpleado__fechaObtencion');

    if (inputId) inputId.value = '';
    if (quitar) quitar.value = '';
    if (caja) caja.classList.add('d-none');

    if (!filas.length) return;

    var primera = filas[0];
    if (inputId) inputId.value = String(primera.id_formacion_academica || '');
    if (selGrado && primera.id_grado_academico) selGrado.value = String(primera.id_grado_academico);
    if (selTitulo && primera.id_titulo) selTitulo.value = String(primera.id_titulo);
    if (selInst && primera.id_institucion) selInst.value = String(primera.id_institucion);
    if (fecha && primera.fecha_obtencion) {
      fecha.value = String(primera.fecha_obtencion).slice(0, 10);
    }

    if (!caja) return;
    var t = document.getElementById('editFormacionTitulo');
    var det = document.getElementById('editFormacionDetalle');
    var opcionTitulo = selTitulo && selTitulo.selectedIndex >= 0 ? selTitulo.options[selTitulo.selectedIndex] : null;
    var opcionInst = selInst && selInst.selectedIndex >= 0 ? selInst.options[selInst.selectedIndex] : null;

    if (t) t.textContent = opcionTitulo ? opcionTitulo.text : ('Título ' + primera.id_titulo);
    if (det) {
      var partes = [];
      if (opcionInst) partes.push(opcionInst.text);
      if (primera.fecha_obtencion) {
        var fechaStr = (SIGDEUI && typeof SIGDEUI.formatearFechaCorta === 'function')
          ? SIGDEUI.formatearFechaCorta(primera.fecha_obtencion)
          : String(primera.fecha_obtencion).slice(0, 10);
        partes.push('obtenido el ' + fechaStr);
      }
      if (filas.length > 1) {
        partes.push('y ' + (filas.length - 1) + ' título(s) más que este modal no muestra; '
          + 'no se borran al guardar');
      }
      det.textContent = partes.join(' · ');
    }
    caja.classList.remove('d-none');
  }

  var btnQuitarFormacion = document.getElementById('editFormacionQuitar');
  if (btnQuitarFormacion) {
    btnQuitarFormacion.addEventListener('click', async function () {
      var selGradoQ = document.getElementById('editEmpleado__gradoAcademico');
      var selTituloQ = document.getElementById('editEmpleado__titulo');
      var selInstQ = document.getElementById('editEmpleado__institucion');
      var fechaQ = document.getElementById('editEmpleado__fechaObtencion');
      var tituloQ = selTituloQ && selTituloQ.selectedIndex >= 0 ? selTituloQ.options[selTituloQ.selectedIndex].text : '';

      var ok = await SIGDEUI.confirmar({
        icono: 'warning',
        titulo: '¿Quitar la formación?',
        texto: 'Se eliminará'
          + (tituloQ ? ' el título "' + tituloQ + '"' : ' el registro de formación')
          + ' de este empleado. Para dejarlo sin formación, debes registrar otro título desde'
          + ' el alta de empleado.',
        confirmar: 'Sí, quitar'
      });
      if (!ok) return;

      /* Se vacían los campos para que quede claro que ya no hay nada que guardar, pero la
 orden la lleva el campo eliminar_formacion: si el guardado fallara, los <select>
 vacíos NO pueden acabar borrando la fila, porque eso solo pasa con ese campo.*/
      if (selGradoQ) selGradoQ.value = '';
      if (selTituloQ) selTituloQ.value = '';
      if (selInstQ) selInstQ.value = '';
      if (fechaQ) fechaQ.value = '';
      var eliminar = document.getElementById('editFormacionEliminar');
      if (eliminar) eliminar.value = '1';
      var caja = document.getElementById('editFormacionActual');
      if (caja) caja.classList.add('d-none');
      SIGDEUI.mostrarAviso('info', 'Formación marcada para quitar',
        'Pulsa Guardar para aplicar el cambio.');
    });
  }

  function filtrarTitulosEdicion() {
    if (!selGradoEd || !selTituloEd) return;
    SIGDEUI.filtrarOpciones(selTituloEd, selGradoEd.value, 'data-id-grado');
  }

  if (selGradoEd && selTituloEd) {
    selGradoEd.addEventListener('change', filtrarTitulosEdicion);
  }

  document.addEventListener('click', function (e) {
    var btnEditar = e.target.closest('.editar-empleado');
    if (!btnEditar || btnEditar.disabled || btnEditar.classList.contains('disabled')) return;

    var idEmpleado = btnEditar.dataset.empleadoId;
    if (!idEmpleado) return;

    SIGDEEmpleados.cargarEmpleado(idEmpleado).then(function (d) {
      if (!d) return;

      try {
        formEditar.reset();
        formEditar.classList.remove('was-validated');

        // Cada input se llena por su name; lo que la BD devuelve como null se deja vacío.
        Object.keys(d).forEach(function (campo) {
          if (d[campo] === null || typeof d[campo] === 'object') return;
          var input = formEditar.elements[campo];
          if (!input || typeof input.value === 'undefined') return;
          if (input instanceof RadioNodeList || (input.length && input[0] && input[0].type === 'radio')) {
            return;
          }
          if (input.type === 'file' || input.type === 'submit' || input.type === 'button') return;
          input.value = d[campo];
        });

        var inputId = formEditar.querySelector('#editEmpleado__id') || formEditar.querySelector('input[name="id"]');
        if (inputId) inputId.value = d.id_empleado;

        // Radio buttons de sexo y sus tarjetas seleccionables
        if (d.sexo) {
          var radiosSexo = formEditar.querySelectorAll('input[name="sexo"]');
          radiosSexo.forEach(function (r) {
            r.checked = (r.value === String(d.sexo));
            var card = r.closest('[data-sexo-card], .selectable-card');
            if (card) {
              card.classList.toggle('selected', r.checked);
            }
          });
        }

        pintarFotoEdicion(d.avatar_foto || d.foto || '');
        poblarUbicacionEdicion(d);

        // Encabezado de perfil: nombre completo + documento del empleado.
        var encNombre = document.getElementById('editEncabezadoNombre');
        var encDoc    = document.getElementById('editEncabezadoDocumento');
        if (encNombre) encNombre.textContent = d.nombre_completo || '—';
        if (encDoc) {
          encDoc.textContent = d.numero_documento
            ? [d.tipo_documento, d.numero_documento].filter(Boolean).join('-')
            : '—';
        }

        pintarFormacionEdicion(d.formacion);
        filtrarTitulosEdicion();

        var primerTab = modalEditarEl.querySelector('.modal-tabs .nav-link');
        if (primerTab) bootstrap.Tab.getOrCreateInstance(primerTab).show();

        var modal = modalEditarEl || document.getElementById('editarEmpleadoModal');
        SIGDEUI.mostrarModal(modal);
      } catch (err) {
        console.error('Error al preparar modal de edición de empleado:', err);
        SIGDEUI.mostrarError('No se pudo preparar el formulario de edición.');
      }
    });
  });

  formEditar.addEventListener('submit', async function (e) {
    e.preventDefault();

    if (!formEditar.checkValidity()) {
      e.stopPropagation();
      formEditar.classList.add('was-validated');
      var primerInvalido = formEditar.querySelector(':invalid');
      if (primerInvalido) {
        var paneInvalido = primerInvalido.closest('.tab-pane');
        if (paneInvalido && paneInvalido.id) {
          var tabBtn = formEditar.querySelector('[data-bs-target="#' + paneInvalido.id + '"]');
          if (tabBtn) bootstrap.Tab.getOrCreateInstance(tabBtn).show();
        }
        primerInvalido.focus();
      }
      return;
    }
    formEditar.classList.remove('was-validated');

    if (btnGuardar) {
      btnGuardar.disabled = true;
      btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Guardando...';
    }

    try {
      var formData = new FormData(formEditar);
      var res = await SIGDEUI.enviar(SIGDEEmpleados.baseUrl + SIGDEEmpleados.rutaControlador + '/update', { metodo: 'POST', cuerpo: formData });
      var data = res.data;

      if (!res.ok || !data.ok) {
        var msgError = data.mensaje || 'No se pudo actualizar el empleado.';
        if (data.data && typeof data.data === 'object') {
          var detalles = Object.values(data.data).join('\n');
          if (detalles) msgError += '\n\n' + detalles;
        }
        SIGDEUI.mostrarError(msgError, 'Error al editar empleado');
        return;
      }

      // Esperamos a hidden.bs.modal antes de disparar el Swal, para evitar
      // el conflicto de foco/aria-hidden entre Bootstrap y SweetAlert2.
      SIGDEUI.alCerrarModal(modalEditarEl, function mostrarSwalActualizado() {
        SIGDEUI.exito(data.mensaje || 'Empleado actualizado correctamente.', 'Actualizado')
          .then(function () { window.location.reload(); });
      });

      SIGDEUI.ocultarModal(modalEditarEl);

    } catch (err) {
      console.error('Error al procesar petición AJAX (update):', err);
      SIGDEUI.mostrarError('Ocurrió un fallo de conexión al editar el empleado.');
    } finally {
      if (btnGuardar) {
        btnGuardar.disabled = false;
        btnGuardar.innerHTML = htmlBtnOriginal;
      }
    }
  });
}

  // --- Inicialización Módulo ---
  function init() {
    initEditarEmpleadoModal();
  }

  // Inicialización segura del DOM que evita problemas cuando los scripts
  // se cargan con 'defer', 'async' o al final de la plantilla layout.
  if (document.readyState === 'interactive' || document.readyState === 'complete') {
    init();
  } else {
    document.addEventListener('DOMContentLoaded', init);
  }
})();
