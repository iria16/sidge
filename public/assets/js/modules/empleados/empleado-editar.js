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
  var editEliminarFoto   = formEditar.querySelector('#editEliminarFoto');
  var btnQuitarFoto      = formEditar.querySelector('#btnQuitarFotoEdicion');
  var editFotoInput      = formEditar.querySelector('#editFotoInput');
  var editFotoUrl        = null;

  // Pinta la foto actual (o el icono por defecto) en el círculo del
  // componente y muestra/oculta el botón "Quitar foto". ruta es relativa
  // tipo 'uploads/empleados/xxx.jpg'.
  function pintarFotoEdicion(ruta) {
    if (editFotoUrl) { URL.revokeObjectURL(editFotoUrl); editFotoUrl = null; }
    if (ruta) {
      editFotoPreview.innerHTML = '<img src="' + SIGDEEmpleados.baseUrl + ruta.replace(/^\//, '') + '" alt="Foto de perfil">';
      btnQuitarFoto.hidden = false;
    } else {
      editFotoPreview.innerHTML = '<i class="bi bi-person"></i>';
      btnQuitarFoto.hidden = true;
    }
  }

  if (editFotoInput) {
    editFotoInput.addEventListener('change', function () {
      var archivo = editFotoInput.files[0];
      if (!archivo) return;
      // Elegir un archivo nuevo anula un "Quitar foto" previo.
      editEliminarFoto.value = '';
      if (editFotoUrl) { URL.revokeObjectURL(editFotoUrl); editFotoUrl = null; }
      editFotoUrl = URL.createObjectURL(archivo);
      editFotoPreview.innerHTML = '<img src="' + editFotoUrl + '" alt="Nueva foto de perfil">';
      btnQuitarFoto.hidden = false;
    });
  }

  if (btnQuitarFoto) {
    btnQuitarFoto.addEventListener('click', function () {
      editFotoInput.value = '';
      editEliminarFoto.value = '1';
      pintarFotoEdicion('');
    });
  }

  SIGDEUI.alCerrarModal(modalEditarEl, function liberarFotoTemporal() {
    if (editFotoUrl) { URL.revokeObjectURL(editFotoUrl); editFotoUrl = null; }
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
    if (!d) return;
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
      if (primera.fecha_obtencion) partes.push('obtenido el ' + SIGDEUI.formatearFechaCorta(primera.fecha_obtencion));
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

    SIGDEEmpleados.cargarEmpleado(btnEditar.dataset.empleadoId).then(function (d) {
      if (!d) return;

      formEditar.reset();
      formEditar.classList.remove('was-validated');

      // Cada input se llena por su name; lo que la BD devuelve como null se
      // deja vacío. Se salta explícitamente los input[type=file]: el
      // navegador nunca permite asignarles un valor no vacío por JS (por
      // seguridad) y hacerlo lanza InvalidStateError, cortando el resto del
      // llenado. La foto actual se muestra aparte (avatar de solo lectura),
      // no se precarga en el <input type="file">.
      Object.keys(d).forEach(function (campo) {
        var input = formEditar.elements[campo];
        if (!input || d[campo] === null) return;
        if (input.type === 'file') return;
        input.value = d[campo];
      });
      if (formEditar.elements.id) formEditar.elements.id.value = d.id_empleado;

      // Foto de perfil: se refleja la foto actual en el círculo; el
      // <input type="file"> se deja vacío (el navegador no permite
      // asignarle valor por JS) y el hidden eliminar_foto vuelve a su
      // valor inicial gracias a formEditar.reset().
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

      // Primero la formación guardada y DESPUÉS el filtrado, en ese orden: el filtrado usa
      // el grado que hay seleccionado, y al abrir el modal ese grado todavía está en
      // "Seleccione...". Al revés, todos los títulos quedaban ocultos y el que se precargaba
      // caía dentro de una opción en hidden, así que el select se veía vacío.
      pintarFormacionEdicion(d.formacion);
      filtrarTitulosEdicion();

      SIGDEUI.mostrarModal(modalEditarEl);
    });
  });

  formEditar.addEventListener('submit', async function (e) {
    e.preventDefault();

    if (!formEditar.checkValidity()) {
      e.stopPropagation();
      formEditar.classList.add('was-validated');
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
