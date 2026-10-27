/* ==========================================================================
   SIGDE - catalogo.js
   Comportamiento específico de la vista de Gestión de Catálogos:
   selector de tipo de catálogo, toggle de estado, alta y edición de
   elementos vía AJAX.
   Depende de: Bootstrap 5 (modales), SweetAlert2 (opcional),
   window.BASE_URL (definido globalmente en layouts/app.php).
   ========================================================================== */

// --- Inicialización Módulo ---
function initCatalogoModule() {
  initSelectorTipoCatalogo();
  initToggleEstadoElemento();
  initCrearElementoForm();
  initEditarElementoModal();
}

// Inicialización segura del DOM que evita problemas cuando los scripts
// se cargan con 'defer', 'async' o al final de la plantilla layout.
if (document.readyState === 'interactive' || document.readyState === 'complete') {
  initCatalogoModule();
} else {
  document.addEventListener('DOMContentLoaded', initCatalogoModule);
}

// --- Configuración Global de Rutas ---
var rawBaseUrl = window.BASE_URL || '';
var baseUrl = rawBaseUrl.endsWith('/') ? rawBaseUrl : rawBaseUrl + '/';
var rutaControlador = 'catalogo';

// --- Funciones Auxiliares ---
function getTipoCatalogoActivo() {
  var select = document.getElementById('tipoCatalogo');
  return select ? select.value : '';
}

function aplicarValidacionNombreElemento(input) {
  if (!input) return;

  // El limite lo declara el formulario con data-max-length. No es una
  // constante fija porque cada catalogo tiene el suyo: los de siempre 50,
  // institucion 100, que es lo que admite su columna.
  var form = input.form;
  var maximo = form ? parseInt(form.getAttribute('data-max-length'), 10) : 50;
  if (isNaN(maximo)) maximo = 50;

  var actualizarMensaje = function () {
    if (input.validity.valueMissing) {
      input.setCustomValidity('El nombre es obligatorio.');
    } else if (input.validity.tooLong) {
      input.setCustomValidity('El nombre no puede superar los ' + maximo + ' caracteres.');
    } else {
      input.setCustomValidity('');
    }
  };

  input.addEventListener('input', actualizarMensaje);
  input.addEventListener('blur', actualizarMensaje);
  actualizarMensaje();
}

// --- Selector de Catálogo (Filtro por Tipo) ---
function initSelectorTipoCatalogo() {
  var select = document.getElementById('tipoCatalogo');
  if (!select) return;

  select.addEventListener('change', function () {
    var url = new URL(window.location.href);
    url.searchParams.set('tipo', select.value);
    url.searchParams.delete('page');
    window.location.href = url.toString();
  });
}

// --- Cambiar Estado (Activo / Inactivo) ---
function initToggleEstadoElemento() {
  document.addEventListener('change', async function (e) {
    var checkbox = e.target.closest('.toggle-estado');
    if (!checkbox) return;
    var id = checkbox.dataset.elementoId;
    var tipoActivo = getTipoCatalogoActivo();
    var nuevoEstadoActivo = checkbox.checked;
    var accion = nuevoEstadoActivo ? 'activar' : 'desactivar';
    var checkboxAnterior = !checkbox.checked;
    var tr = checkbox.closest('tr');
    var badge = tr ? tr.querySelector('.status-badge') : null;

    var confirmacion = false;
    confirmacion = await SIGDEUI.confirmar({
      titulo: '¿' + accion.charAt(0).toUpperCase() + accion.slice(1) + ' elemento?',
      texto: '¿Está seguro que desea ' + accion + ' este elemento del catálogo?',
      confirmar: 'Sí, ' + accion
    });

    if (!confirmacion) {
      checkbox.checked = checkboxAnterior;
      return;
    }

    try {
      // 'toggleStatus' es el nombre real en la whitelist de router.php para 'catalogo'
      var res = await SIGDEUI.enviar(baseUrl + rutaControlador + '/toggleStatus', { metodo: 'POST', cuerpo: { id: id, tipo: tipoActivo } });
      var data = res.data;

      if (!res.ok || !data.ok) {
        checkbox.checked = checkboxAnterior;
        SIGDEUI.mostrarError(data.mensaje || 'No se pudo actualizar el estado del elemento.');
        return;
      }

      if (tr) {
        tr.classList.toggle('is-muted', !nuevoEstadoActivo);
        var btnEditar = tr.querySelector('.editar-elemento');
        if (btnEditar) {
          btnEditar.disabled = !nuevoEstadoActivo;
          btnEditar.title = nuevoEstadoActivo ? 'Editar elemento' : 'No se puede editar un elemento inactivo';
        }
        var switchWrapper = tr.querySelector('.table-switch');
        if (switchWrapper && !checkbox.disabled) {
          switchWrapper.title = (nuevoEstadoActivo ? 'Desactivar' : 'Activar') + ' elemento';
        }
      }
      if (badge) {
        badge.className = 'status-badge status-badge--' + (nuevoEstadoActivo ? 'active' : 'inactive');
        badge.textContent = nuevoEstadoActivo ? 'Activo' : 'Inactivo';
      }

      SIGDEUI.exito('Elemento ' + accion + 'do correctamente.', '¡Listo!');
    } catch (err) {
      checkbox.checked = checkboxAnterior;
      console.error('Error al cambiar estado:', err);
      SIGDEUI.mostrarError('Error de conexión al actualizar el estado.');
    }
  });
}

// --- Formulario Crear Elemento ---
function initCrearElementoForm() {
  var formCrear = document.getElementById('formNuevoElemento');
  if (!formCrear) return;

  var modalCrearEl = document.getElementById('modalNuevoElemento');
  var btnGuardar = document.querySelector('button[form="formNuevoElemento"]');

  aplicarValidacionNombreElemento(document.getElementById('nuevoElementoNombre'));

  // El bloque del codigo DEA aparece o desaparece con el tipo elegido.
  var selectTipoNuevo = document.getElementById('nuevoElementoTipo');
  var bloqueDeaNuevo = formCrear.querySelector('[data-solo-tipo-estudiante]');
  if (selectTipoNuevo) {
    selectTipoNuevo.addEventListener('change', function () {
      alternarCampoDea(selectTipoNuevo, bloqueDeaNuevo);
    });
    alternarCampoDea(selectTipoNuevo, bloqueDeaNuevo);
  }

  // Nacional / extranjera, y la cascada de estado, municipio y parroquia.
  var interruptorNuevo = formCrear.querySelector('[name="es_extranjera"]');
  if (interruptorNuevo) {
    interruptorNuevo.addEventListener('change', function () {
      alternarBloquesNacionalExtranjera(formCrear);
    });
    alternarBloquesNacionalExtranjera(formCrear);
  }
  iniciarCascadaInstitucion('nuevoElemento');

  formCrear.addEventListener('submit', async function (e) {
    e.preventDefault();

    if (!formCrear.checkValidity()) {
      e.stopPropagation();
      formCrear.classList.add('was-validated');
      return;
    }

    if (btnGuardar) {
      btnGuardar.disabled = true;
      btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Guardando...';
    }

    try {
      var formData = new FormData(formCrear);
      // 'store' es el nombre real en la whitelist de router.php para 'catalogo'
      var res = await SIGDEUI.enviar(baseUrl + rutaControlador + '/store', { metodo: 'POST', cuerpo: formData });
      var data = res.data;

      if (!res.ok || !data.ok) {
        var msgError = data.mensaje || 'No se pudo crear el elemento. Verifique los datos.';
        if (data.data && typeof data.data === 'object') {
          var detalles = Object.values(data.data).join('\n');
          if (detalles) msgError += '\n\n' + detalles;
        }
        SIGDEUI.mostrarError(msgError, 'Error al crear elemento');
        return;
      }

      // Esperamos a que el modal termine de cerrarse (hidden.bs.modal, no
      // solo el hide() disparado) antes de mostrar el Swal de éxito. Si el
      // Swal se dispara mientras Bootstrap todavía está procesando el
      // cierre, el foco puede quedar en un descendiente del modal justo
      // cuando Bootstrap le pone aria-hidden, lo que generaba el warning
      // "Blocked aria-hidden on an element because its descendant
      // retained focus" en consola.
      if (modalCrearEl) {
        var bsModal = bootstrap.Modal.getInstance(modalCrearEl) || new bootstrap.Modal(modalCrearEl);
        SIGDEUI.alCerrarModal(modalCrearEl, function mostrarSwalCreado() {
          formCrear.reset();
          formCrear.classList.remove('was-validated');

          SIGDEUI.exito(data.mensaje || 'Elemento creado correctamente.', 'Creado')
            .then(function () { window.location.reload(); });
        });
        bsModal.hide();
      } else {
        formCrear.reset();
        formCrear.classList.remove('was-validated');
        window.location.reload();
      }

    } catch (err) {
      console.error('Error al procesar petición AJAX (store):', err);
      SIGDEUI.mostrarError('Ocurrió un fallo de conexión al crear el elemento.');
    } finally {
      if (btnGuardar) {
        btnGuardar.disabled = false;
        btnGuardar.innerHTML = 'Crear elemento';
      }
    }
  });
}

/**
 * Alterna los bloques de procedencia segun el interruptor de institution
 * extranjera.
 *
 * Un <select> con required que queda escondido sigue bloqueando el envio del
 * formulario, asi que el `required` se mueve con el bloque y ademas se limpian
 * los campos del camino que no aplica: si alguien marca "extranjera" y guarda,
 * el estado, el municipio y la parroquia tienen que irse a NULL en la base, no
 * quedar pegados sin que nadie los use.
 */
function alternarBloquesNacionalExtranjera(form) {
  if (!form) return;

  var interruptor = form.querySelector('[name="es_extranjera"]');
  if (!interruptor) return;

  var esExtranjera = interruptor.checked;

  ['nacional', 'extranjera'].forEach(function (nombre) {
    var bloque = form.querySelector('[data-bloque="' + nombre + '"]');
    if (!bloque) return;

    bloque.classList.toggle('d-none', (nombre === 'extranjera') !== esExtranjera);

    Array.prototype.forEach.call(bloque.querySelectorAll('input, select'), function (campo) {
      var enBloqueOculto = campo.closest('.d-none');
      if (enBloqueOculto) {
        campo.required = false;
        return;
      }
      campo.required = !bloque.classList.contains('d-none');
      if (campo.required === false && campo.tagName === 'SELECT') campo.value = '';
      if (campo.required === false && campo.tagName === 'INPUT') campo.value = '';
    });
  });
}

/**
 * Levanta la cascada estado -> municipio -> parroquia del formulario.
 * La funcion es la misma que usa el domicilio del empleado y el wizard de
 * inscripciones (ver SIGDEWizardUI.initCascadaUbicacion): pide los tres
 * niveles de una vez y filtra en el navegador, para no ir a la base en cada
 * cambio.
 */
function iniciarCascadaInstitucion(prefijo) {
  if (typeof SIGDEWizardUI === 'undefined' || !SIGDEWizardUI.initCascadaUbicacion) return null;

  return SIGDEWizardUI.initCascadaUbicacion({
    url: baseUrl + rutaControlador + '/getUbicacion',
    estadoId: prefijo + 'Estado',
    municipioId: prefijo + 'Municipio',
    parroquiaId: prefijo + 'Parroquia'
  });
}

/**
 * Muestra u oculta el bloque del codigo DEA segun el tipo elegido.
 *
 * El DEA solo tiene sentido en preescolar y escuela, que son las etapas del
 * sistema escolar. En universidad el campo se oculta y se limpia, para que no
 * quede un valor viejo pegado a un tipo que ya no lo usa.
 */
function alternarCampoDea(selectTipo, bloque) {
  if (!selectTipo || !bloque) return;

  var esDeEstudiante = selectTipo.value !== 'titulo_academico';
  var campo = bloque.querySelector('input[name="codigo_dea"]');

  bloque.classList.toggle('d-none', !esDeEstudiante);

  if (!campo) return;

  // El `required` va pegado al bloque. Un campo obligatorio escondido haria que
  // el navegador bloqueara el envio con el formulario lleno y sin DEA visible.
  campo.required = esDeEstudiante;

  if (!esDeEstudiante) campo.value = '';
}

// --- Modal y Formulario Editar Elemento ---
function initEditarElementoModal() {
  var modalEditarEl = document.getElementById('modalEditarElemento');
  if (!modalEditarEl) return;

  /* Cascada compartida por todas las ediciones de la página. */
  var cascadaEdicion = null;

  aplicarValidacionNombreElemento(modalEditarEl.querySelector('#editarElementoNombre'));

  // Handler delegatorio para abrir el modal con datos de la fila
  document.addEventListener('click', function (e) {
    var btnEditar = e.target.closest('.editar-elemento');
    if (!btnEditar || btnEditar.disabled || btnEditar.classList.contains('disabled')) return;
    e.preventDefault();

    var d = btnEditar.dataset;
    var inputId = modalEditarEl.querySelector('#editarElementoId');
    var inputNombre = modalEditarEl.querySelector('#editarElementoNombre');
    var selectTipo = modalEditarEl.querySelector('#editarElementoTipo');

    if (inputId) inputId.value = d.elementoId || '';
    if (inputNombre) inputNombre.value = d.elementoNombre || '';

    // Solo existe en el catalogo institucion. El boton de la fila manda el ID
    // de tipo_institucion (la columna de texto ya no existe), pero el value de
    // cada opcion es el codigo de texto. Si se asignara el id al value no
    // coincidiria con ninguna opcion, el select quedaria vacio y, como es
    // required, el formulario no se enviaria.
    if (selectTipo) {
      var idCatalogo = d.elementoTipo;
      var opcionTipo = idCatalogo
        ? selectTipo.querySelector('option[data-id="' + idCatalogo + '"]')
        : null;
      selectTipo.value = opcionTipo ? opcionTipo.value : '';
    }

    // Ubicacion y codigo DEA. Sin esto, editar una institucion los pondria en
    // blanco y se perderian.
    var selectEstado = modalEditarEl.querySelector('#editarElementoEstado');
    var inputDea = modalEditarEl.querySelector('#editarElementoDea');
    var selectPais = modalEditarEl.querySelector('#editarElementoPais');
    if (selectEstado) selectEstado.value = d.elementoEstado || '';
    if (inputDea) inputDea.value = d.elementoDea || '';
    if (selectPais) selectPais.value = d.elementoPais || '';

    var interruptorEditar = modalEditarEl.querySelector('[name="es_extranjera"]');
    if (interruptorEditar) {
      interruptorEditar.checked = d.elementoExtranjera === '1';
      alternarBloquesNacionalExtranjera(modalEditarEl.querySelector('form'));
    }

    alternarCampoDea(selectTipo, modalEditarEl.querySelector('[data-solo-tipo-estudiante]'));

    /* La cascada se crea una vez y se reutiliza: pedir los municipios de nuevo
       en cada edición los descargaría otra vez, y ya seenan 335 filas. */
    if (!cascadaEdicion) cascadaEdicion = iniciarCascadaInstitucion('editarElemento');
    if (cascadaEdicion && selectEstado && selectEstado.value) {
      /* elegirParroquia(estado, municipio, parroquia) deja los tres selects en
         su valor. Si alguno ya no está en el catálogo devuelve false, y quien
         guarda lo ve como selección inválida. */
      cascadaEdicion.elegirParroquia(
        selectEstado.value,
        d.elementoMunicipio || 0,
        d.elementoParroquia || 0
      );
    }

    if (inputNombre) inputNombre.dispatchEvent(new Event('input'));

    var formEditInner = modalEditarEl.querySelector('form');
    if (formEditInner) formEditInner.classList.remove('was-validated');

    SIGDEUI.mostrarModal(modalEditarEl);
  });

  var selectTipoEditar = modalEditarEl.querySelector('#editarElementoTipo');
  if (selectTipoEditar) {
    selectTipoEditar.addEventListener('change', function () {
      alternarCampoDea(selectTipoEditar, modalEditarEl.querySelector('[data-solo-tipo-estudiante]'));
    });
  }

  var formEditar = modalEditarEl.querySelector('#formEditarElemento');
  if (!formEditar) return;

  formEditar.addEventListener('submit', async function (e) {
    e.preventDefault();

    if (!formEditar.checkValidity()) {
      e.stopPropagation();
      formEditar.classList.add('was-validated');
      return;
    }
    formEditar.classList.remove('was-validated');

    var btnGuardar = document.querySelector('button[form="formEditarElemento"]');
    if (btnGuardar) {
      btnGuardar.disabled = true;
      btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Guardando...';
    }

    try {
      var formData = new FormData(formEditar);
      // 'update' es el nombre real en la whitelist de router.php para 'catalogo'
      var res = await SIGDEUI.enviar(baseUrl + rutaControlador + '/update', { metodo: 'POST', cuerpo: formData });
      var data = res.data;

      if (!res.ok || !data.ok) {
        var msgError = data.mensaje || 'No se pudo actualizar el elemento.';
        if (data.data && typeof data.data === 'object') {
          var detalles = Object.values(data.data).join('\n');
          if (detalles) msgError += '\n\n' + detalles;
        }
        SIGDEUI.mostrarError(msgError, 'Error al editar elemento');
        return;
      }

      // Mismo motivo que en initCrearElementoForm(): esperamos a
      // hidden.bs.modal antes de disparar el Swal, para evitar el
      // conflicto de foco/aria-hidden entre Bootstrap y SweetAlert2.
      SIGDEUI.alCerrarModal(modalEditarEl, function mostrarSwalActualizado() {
        var id = formData.get('id');
        var selectTipoEditar = modalEditarEl.querySelector('#editarElementoTipo');
        var selectEstadoEditar = modalEditarEl.querySelector('#editarElementoEstado');
        var inputDeaEditar = modalEditarEl.querySelector('#editarElementoDea');
        // 'nuevoNombre' se toma de data.data.nombre (lo que el backend
        // realmente guardó, ya pasado por capitalizarTitulo()) y no de
        // formData.get('nombre') (el texto crudo tipeado por el
        // usuario). Si se usara el valor crudo, la fila mostraría
        // "licenciado en informatica" en pantalla mientras la BD
        // tiene "Licenciado en Informática", hasta el próximo reload.
        var nuevoNombre = (data.data && data.data.nombre) || formData.get('nombre');
        var btnEditarEnFila = document.querySelector('.editar-elemento[data-elemento-id="' + id + '"]');
        var tr = btnEditarEnFila ? btnEditarEnFila.closest('tr') : null;

        if (btnEditarEnFila) {
          btnEditarEnFila.dataset.elementoNombre = nuevoNombre || '';
        }

        if (tr) {
          var nombreCell = tr.querySelector('td');
          if (nombreCell) nombreCell.textContent = nuevoNombre || '';

          // El tipo también se actualiza en la fila. Antes solo se refrescaba
          // el nombre, así que cambiar el tipo se notaba al recargar la página.
          // La columna Tipo es la segunda y solo existe en el catálogo
          // institución: que haya <select> de tipo es la señal de que
          // también está esa columna.
          if (selectTipoEditar && selectTipoEditar.value) {
            var opcionElegida = selectTipoEditar.options[selectTipoEditar.selectedIndex];
            var celdaTipo = tr.querySelector('td:nth-child(2)');
            if (celdaTipo && opcionElegida) celdaTipo.textContent = opcionElegida.text;
            if (btnEditarEnFila && opcionElegida && opcionElegida.dataset.id) {
              btnEditarEnFila.dataset.elementoTipo = opcionElegida.dataset.id;
            }
          }

          // La linea de ubicacion y DEA bajo el nombre se reconstruye con lo
          // que se acaba de guardar.
          if (selectEstadoEditar && inputDeaEditar) {
            var lineaUbicacion = tr.querySelector('td .text-support');
            if (lineaUbicacion) {
              var opcionEstadoElegida = selectEstadoEditar.options[selectEstadoEditar.selectedIndex];
              var textoUbicacion = opcionEstadoElegida && opcionEstadoElegida.value
                ? opcionEstadoElegida.text
                : 'Sin ubicacion';
              if (inputDeaEditar.value) textoUbicacion += ' \u00b7 DEA ' + inputDeaEditar.value.toUpperCase();
              lineaUbicacion.textContent = textoUbicacion;
            }
            if (btnEditarEnFila) {
              btnEditarEnFila.dataset.elementoEstado = selectEstadoEditar.value;
              btnEditarEnFila.dataset.elementoDea = inputDeaEditar.value;
            }
          }

          var toggleInput = tr.querySelector('.toggle-estado');
          if (toggleInput) toggleInput.setAttribute('aria-label', 'Activar o desactivar ' + (nuevoNombre || ''));
        }

        SIGDEUI.exito(data.mensaje || 'Elemento actualizado correctamente.', 'Actualizado');
      });

      SIGDEUI.ocultarModal(modalEditarEl);

    } catch (err) {
      console.error('Error al procesar petición AJAX (update):', err);
      SIGDEUI.mostrarError('Ocurrió un fallo de conexión al editar el elemento.');
    } finally {
      if (btnGuardar) {
        btnGuardar.disabled = false;
        btnGuardar.innerHTML = '<i class="bi bi-floppy me-1"></i>Guardar cambios';
      }
    }
  });
}