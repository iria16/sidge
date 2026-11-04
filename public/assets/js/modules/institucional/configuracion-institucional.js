/**
 * SIGDE - configuracion-institucional.js
 * Gestión de Parámetros Oficiales del Plantel, Membretes y Recursos Gráficos.
 *
 * Utiliza SIGDEUI.enviar() para la comunicación AJAX con el backend.
 * El sobre devuelto es siempre `respuesta.data`, sin llamar a .json().
 * Manipulación de DOM mediante createElement para evitar inyecciones por innerHTML.
 */

document.addEventListener('DOMContentLoaded', function () {
  initFormTextos();
  initCascadaUbicacion();
  initNivelEducativo();
  initValidacionesEnTiempoReal();
  initSubidaImagenes();
  initQuitarImagenes();
  initRestablecerCintilloOficial();
});

/**
 * Gestión de los formularios independientes por pestaña (Plantel, Directiva, Membrete).
 */
function initFormTextos() {
  var formularios = [
    { id: 'formPlantel', btnId: 'btnGuardarPlantel', textoBtn: 'Guardar Datos del Plantel', modalId: 'modalEditarPlantel' },
    { id: 'formDirectiva', btnId: 'btnGuardarDirectiva', textoBtn: 'Guardar Directiva', modalId: 'modalEditarDirectiva' },
    { id: 'formMembrete', btnId: 'btnGuardarMembrete', textoBtn: 'Guardar Membrete', modalId: 'modalEditarMembrete' },
    { id: 'formTextosInstitucion', btnId: 'btnGuardarTextos', textoBtn: 'Guardar Cambios' }
  ];

  formularios.forEach(function (conf) {
    var form = document.getElementById(conf.id);
    if (!form) return;

    var btnGuardar = document.getElementById(conf.btnId) || form.querySelector('button[type="submit"]');

    form.addEventListener('submit', function (e) {
      e.preventDefault();

      // Limpiar errores previos en este formulario
      form.querySelectorAll('.is-invalid').forEach(function (el) {
        el.classList.remove('is-invalid');
      });
      form.querySelectorAll('.invalid-feedback').forEach(function (el) {
        el.textContent = '';
      });

      // Validar únicamente los campos dentro de este formulario
      var inputs = form.querySelectorAll('input:not([type="hidden"]), select');
      var formValido = true;
      var primerCampoInvalido = null;

      inputs.forEach(function (input) {
        var esValido = validarCampo(input);
        if (!esValido) {
          formValido = false;
          if (!primerCampoInvalido) {
            primerCampoInvalido = input;
          }
        }
      });

      if (!formValido) {
        SIGDEUI.mostrarError(
          'Por favor verifique los campos marcados en rojo. Existen errores de formato o campos obligatorios vacíos.',
          'Campos Inválidos'
        );
        if (primerCampoInvalido) {
          primerCampoInvalido.scrollIntoView({ behavior: 'smooth', block: 'center' });
          primerCampoInvalido.focus();
        }
        return;
      }

      var fd = new FormData(form);

      if (btnGuardar) {
        btnGuardar.disabled = true;
        btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Guardando...';
      }

      var url = (window.BASE_URL || '/') + 'configuracionInstitucional/guardar';

      SIGDEUI.enviar(url, {
        metodo: 'POST',
        cuerpo: fd
      })
        .then(function (respuesta) {
          var payload = respuesta.data;

          if (respuesta.ok && payload && payload.ok) {
            SIGDEUI.exito(payload.mensaje || 'Datos guardados correctamente.', '¡Actualizado!');

            // Cerrar el modal correspondiente si está abierto
            if (conf.modalId) {
              var modalEl = document.getElementById(conf.modalId);
              if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                var modalInst = bootstrap.Modal.getInstance(modalEl);
                if (modalInst) {
                  modalInst.hide();
                }
              }
            }

            // Actualizar los inputs con los valores formateados
            if (payload.data && payload.data.campos) {
              var c = payload.data.campos;
              Object.keys(c).forEach(function (campo) {
                var input = form.querySelector('[name="' + campo + '"]');
                if (input) {
                  input.value = c[campo];
                }
              });

              // Actualizar elementos de sólo lectura en la ficha de consulta, banner de cabecera y maqueta en vivo
              var setTexto = function (id, valor) {
                var el = document.getElementById(id);
                if (el) el.textContent = valor || '';
              };

              if (c.plantel_nombre) {
                setTexto('view-plantel_nombre', c.plantel_nombre);
                setTexto('header-plantel_nombre', c.plantel_nombre);
              }
              if (c.plantel_nombre_corto) {
                setTexto('view-plantel_nombre_corto', c.plantel_nombre_corto);
                setTexto('header-plantel_nombre_corto', c.plantel_nombre_corto);
              }
              if (c.plantel_codigo_dea !== undefined) {
                var deaVal = c.plantel_codigo_dea || 'No asignado';
                setTexto('view-plantel_codigo_dea', deaVal);
                setTexto('header-plantel_codigo_dea', deaVal);
              }
              if (c.cdce_estado) {
                setTexto('view-cdce_estado', c.cdce_estado);
                setTexto('header-cdce_estado', c.cdce_estado);
              }
              if (c.plantel_municipio) {
                setTexto('view-plantel_municipio', c.plantel_municipio);
                setTexto('header-plantel_municipio', c.plantel_municipio);
              }
              if (c.plantel_parroquia) {
                setTexto('view-plantel_parroquia', c.plantel_parroquia);
              }
              if (c.lugar_expedicion) {
                setTexto('view-lugar_expedicion', c.lugar_expedicion);
              }
              if (c.plantel_codigo_dependencia) {
                setTexto('view-plantel_codigo_dependencia', c.plantel_codigo_dependencia);
                setTexto('header-plantel_codigo_dependencia', c.plantel_codigo_dependencia);
              }
              if (c.nivel_educativo) {
                setTexto('view-nivel_educativo', c.nivel_educativo);
                setTexto('header-nivel_educativo', c.nivel_educativo);
              }
              if (c.nivel_codigo) {
                setTexto('view-nivel_codigo', c.nivel_codigo);
                setTexto('header-nivel_codigo', c.nivel_codigo);
              }

              // Directiva
              var dirNombreFinal = '';
              if (c.director_nombre) {
                dirNombreFinal = c.director_nombre;
              } else if (c.director_primer_nombre || c.director_primer_apellido) {
                dirNombreFinal = [c.director_primer_nombre, c.director_segundo_nombre, c.director_primer_apellido, c.director_segundo_apellido].filter(Boolean).join(' ');
              }
              if (dirNombreFinal) {
                setTexto('view-director_nombre', dirNombreFinal);
              }
              if (c.director_cedula !== undefined) {
                var cedVal = c.director_cedula || 'No registrada';
                setTexto('view-director_cedula', cedVal);
              }
              if (c.director_cargo) {
                setTexto('view-director_cargo', c.director_cargo);
              }
              if (c.cdce_director_nombre !== undefined) {
                setTexto('view-cdce_director_nombre', c.cdce_director_nombre || 'En blanco (para llenado manual)');
              }
              if (c.cdce_director_cedula !== undefined) {
                setTexto('view-cdce_director_cedula', c.cdce_director_cedula || 'En blanco');
              }

              // Membrete
              if (c.ministerio_linea_1) {
                setTexto('view-ministerio_linea_1', c.ministerio_linea_1);
              }
              if (c.ministerio_linea_2) {
                setTexto('view-ministerio_linea_2', c.ministerio_linea_2);
              }
              if (c.certificado_epigrafe !== undefined) {
                setTexto('view-certificado_epigrafe', c.certificado_epigrafe || 'Sin epígrafe configurado');
              }
            }

            // Si el director ya quedó completamente configurado, retirar el aviso específico y actualizar badge
            if (payload.data && payload.data.director_configurado) {
              var alertaIncompleta = document.getElementById('alertaDirectorIncompleto');
              if (alertaIncompleta) {
                alertaIncompleta.remove();
              }
              var badgeDir = document.getElementById('badge-estado-director');
              if (badgeDir) {
                badgeDir.className = 'badge bg-success';
                badgeDir.textContent = 'Configurado';
              }
            }
            return;
          }

          // Manejo de errores de validación del backend (HTTP 422)
          var mensajeError = (payload && (payload.mensaje || payload.message)) ? (payload.mensaje || payload.message) : 'Revise los campos señalados en el formulario.';
          var primerErrEl = null;
          var erroresMap = (payload && payload.errors && typeof payload.errors === 'object')
            ? payload.errors
            : ((payload && payload.data && typeof payload.data === 'object') ? payload.data : null);

          if (erroresMap) {
            Object.keys(erroresMap).forEach(function (campo) {
              var input = form.querySelector('[name="' + campo + '"]');
              var errorDiv = form.querySelector('#error-' + campo) || document.getElementById('error-' + campo);
              if (input) {
                input.classList.add('is-invalid');
                if (!primerErrEl) primerErrEl = input;
              }
              if (errorDiv) {
                errorDiv.textContent = erroresMap[campo];
              }
            });
          }

          if (primerErrEl) {
            primerErrEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
            primerErrEl.focus();
          }

          SIGDEUI.mostrarError(mensajeError, 'Error de Validación');
        })
        .catch(function () {
          SIGDEUI.mostrarError('Ocurrió un problema de red al intentar guardar los datos.', 'Error de Conexión');
        })
        .finally(function () {
          if (btnGuardar) {
            btnGuardar.disabled = false;
            btnGuardar.innerHTML = '<i class="bi bi-save" aria-hidden="true"></i> <span>' + conf.textoBtn + '</span>';
          }
        });
    });
  });

  // Limpiar validaciones al cerrar los modales
  ['modalEditarPlantel', 'modalEditarDirectiva', 'modalEditarMembrete'].forEach(function(modalId) {
    var modalEl = document.getElementById(modalId);
    if (!modalEl) return;
    modalEl.addEventListener('hidden.bs.modal', function () {
      modalEl.querySelectorAll('.is-invalid').forEach(function (el) {
        el.classList.remove('is-invalid');
      });
      modalEl.querySelectorAll('.invalid-feedback').forEach(function (el) {
        el.textContent = '';
      });
    });
  });
}

/**
 * Gestión de la cascada Estado -> Municipio -> Parroquia con <select>.
 */
function initCascadaUbicacion() {
  var selEstado = document.getElementById('cdce_estado');
  var selMunicipio = document.getElementById('plantel_municipio');
  var selParroquia = document.getElementById('plantel_parroquia');

  if (!selEstado || !selMunicipio || !selParroquia) return;

  function filtrarMunicipios(mantenerValorActual) {
    var optEstado = selEstado.options[selEstado.selectedIndex];
    var idEstado = optEstado ? optEstado.dataset.idEstado : null;

    var previoMunicipioVal = selMunicipio.value;
    var previoValido = false;

    for (var i = 0; i < selMunicipio.options.length; i++) {
      var opt = selMunicipio.options[i];
      if (!opt.value) continue;

      var coincide = !idEstado || (opt.dataset.idEstado === idEstado);
      opt.hidden = !coincide;
      opt.disabled = !coincide;

      if (coincide && opt.value === previoMunicipioVal) {
        previoValido = true;
      }
    }

    if (!previoValido && !mantenerValorActual && idEstado) {
      selMunicipio.value = '';
    }

    filtrarParroquias(mantenerValorActual);
  }

  function filtrarParroquias(mantenerValorActual) {
    var optMunicipio = selMunicipio.options[selMunicipio.selectedIndex];
    var idMunicipio = optMunicipio ? optMunicipio.dataset.idMunicipio : null;

    var previoParroquiaVal = selParroquia.value;
    var previoValido = false;

    for (var i = 0; i < selParroquia.options.length; i++) {
      var opt = selParroquia.options[i];
      if (!opt.value) continue;

      var coincide = !idMunicipio || (opt.dataset.idMunicipio === idMunicipio);
      opt.hidden = !coincide;
      opt.disabled = !coincide;

      if (coincide && opt.value === previoParroquiaVal) {
        previoValido = true;
      }
    }

    if (!previoValido && !mantenerValorActual && idMunicipio) {
      selParroquia.value = '';
    }
  }

  selEstado.addEventListener('change', function () {
    filtrarMunicipios(false);
    validarCampo(selEstado);
    validarCampo(selMunicipio);
    validarCampo(selParroquia);
  });

  selMunicipio.addEventListener('change', function () {
    filtrarParroquias(false);
    validarCampo(selMunicipio);
    validarCampo(selParroquia);
  });

  selParroquia.addEventListener('change', function () {
    validarCampo(selParroquia);
  });

  // Filtrado inicial preservando valores cargados desde el servidor
  filtrarMunicipios(true);
}

/**
 * Autocompletado del código MPPE según el Nivel Educativo seleccionado.
 */
function initNivelEducativo() {
  var selNivel = document.getElementById('nivel_educativo');
  var inputCodigo = document.getElementById('nivel_codigo');

  if (!selNivel || !inputCodigo) return;

  selNivel.addEventListener('change', function () {
    var opt = selNivel.options[selNivel.selectedIndex];
    if (opt && opt.dataset.codigo) {
      inputCodigo.value = opt.dataset.codigo;
    }
    validarCampo(selNivel);
    validarCampo(inputCodigo);
  });

  inputCodigo.addEventListener('input', function () {
    validarCampo(inputCodigo);
  });
}

/**
 * Inicialización de validaciones en tiempo real (input y blur).
 */
function initValidacionesEnTiempoReal() {
  var camposMonitoreados = [
    'director_cedula',
    'cdce_director_cedula',
    'plantel_codigo_dea',
    'plantel_codigo_dependencia',
    'nivel_codigo',
    'plantel_nombre',
    'plantel_nombre_corto',
    'ministerio_linea_1',
    'ministerio_linea_2',
    'lugar_expedicion',
    'director_primer_nombre',
    'director_primer_apellido'
  ];

  camposMonitoreados.forEach(function (nombre) {
    var input = document.querySelector('[name="' + nombre + '"]');
    if (!input) return;

    input.addEventListener('input', function () {
      if (nombre === 'plantel_codigo_dea') {
        input.value = input.value.toUpperCase();
      }
      validarCampo(input);
    });

    input.addEventListener('blur', function () {
      if (nombre === 'director_cedula' || nombre === 'cdce_director_cedula') {
        input.value = formatearCedula(input.value);
      }
      validarCampo(input);
    });
  });
}

/**
 * Valida un campo individual mostrando el mensaje de error junto al campo en `#error-[campo]`.
 *
 * @param {HTMLElement} input Elemento input o select a validar.
 * @returns {boolean} True si el campo es válido, False de lo contrario.
 */
function validarCampo(input) {
  if (!input) return true;

  var nombre = input.name || input.id;
  var valor = input.value.trim();
  var errorDiv = document.getElementById('error-' + nombre);
  var mensajeError = '';

  if (nombre === 'director_primer_nombre') {
    if (valor === '') {
      mensajeError = 'El primer nombre del director es obligatorio.';
    }
  } else if (nombre === 'director_primer_apellido') {
    if (valor === '') {
      mensajeError = 'El primer apellido del director es obligatorio.';
    }
  } else if (nombre === 'director_cedula' || nombre === 'cdce_director_cedula') {
    if (valor !== '') {
      var soloDigitos = valor.replace(/\D/g, '');
      var letraMatch = valor.match(/^[VE]/i);
      var letra = letraMatch ? letraMatch[0].toUpperCase() : '';

      if (!letra || soloDigitos.length < 6 || soloDigitos.length > 9) {
        mensajeError = 'La cédula debe comenzar con V o E seguida de 6 a 9 dígitos (ej. V-12.345.678).';
      }
    }
  } else if (nombre === 'plantel_codigo_dea') {
    if (valor !== '') {
      if (!/^[A-Za-z0-9]{6,20}$/.test(valor)) {
        mensajeError = 'El Código DEA debe tener entre 6 y 20 caracteres alfanuméricos sin espacios ni símbolos (ej. OD05281402).';
      }
    }
  } else if (nombre === 'plantel_codigo_dependencia') {
    if (valor === '') {
      mensajeError = 'El Código de Dependencia es obligatorio para emitir constancias.';
    } else if (!/^\d{6,15}$/.test(valor)) {
      mensajeError = 'El Código de Dependencia debe contener únicamente entre 6 y 15 dígitos numéricos (ej. 006562012).';
    }
  } else if (nombre === 'nivel_codigo') {
    if (valor === '') {
      mensajeError = 'El Código de Nivel MPPE es obligatorio.';
    } else if (!/^\d{5}$/.test(valor)) {
      mensajeError = 'El Código de Nivel MPPE debe contener exactamente 5 dígitos numéricos (ej. 21000).';
    }
  } else if (nombre === 'plantel_nombre') {
    if (valor === '') {
      mensajeError = 'El nombre oficial completo del plantel es obligatorio.';
    }
  } else if (nombre === 'plantel_nombre_corto') {
    if (valor === '') {
      mensajeError = 'El nombre corto / siglas del plantel es obligatorio.';
    }
  } else if (nombre === 'ministerio_linea_1') {
    if (valor === '') {
      mensajeError = 'La línea 1 del membrete ministerial es obligatoria.';
    }
  } else if (nombre === 'ministerio_linea_2') {
    if (valor === '') {
      mensajeError = 'La línea 2 del membrete ministerial es obligatoria.';
    }
  } else if (nombre === 'cdce_estado') {
    if (valor === '') {
      mensajeError = 'Debe seleccionar el Estado CDCE.';
    }
  } else if (nombre === 'plantel_municipio') {
    if (valor === '') {
      mensajeError = 'Debe seleccionar el Municipio del plantel.';
    }
  } else if (nombre === 'plantel_parroquia') {
    if (valor === '') {
      mensajeError = 'Debe seleccionar la Parroquia del plantel.';
    }
  } else if (nombre === 'nivel_educativo') {
    if (valor === '') {
      mensajeError = 'Debe seleccionar el Nivel Educativo.';
    }
  } else if (nombre === 'lugar_expedicion') {
    if (valor === '') {
      mensajeError = 'El lugar de expedición en constancias es obligatorio.';
    }
  }

  if (mensajeError) {
    input.classList.add('is-invalid');
    if (errorDiv) {
      errorDiv.textContent = mensajeError;
    }
    return false;
  } else {
    input.classList.remove('is-invalid');
    if (errorDiv) {
      errorDiv.textContent = '';
    }
    return true;
  }
}

/**
 * Normaliza y formatea la cédula de identidad venezolana (ej. V-12.345.678).
 */
function formatearCedula(valor) {
  if (!valor) return '';
  var letraMatch = valor.match(/^[VE]/i);
  var letra = letraMatch ? letraMatch[0].toUpperCase() : 'V';
  var digitos = valor.replace(/\D/g, '');
  if (digitos.length >= 6 && digitos.length <= 9) {
    var formateado = digitos.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return letra + '-' + formateado;
  }
  return valor.trim();
}

/**
 * Muestra el distintivo visual «Imagen actualizada» junto a la previsualización.
 */
function mostrarAvisoImagenActualizada(clave) {
  var badge = document.getElementById('status-img-' + clave);
  if (!badge) return;

  badge.classList.remove('d-none');
  badge.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i>Imagen actualizada';

  if (badge._timeoutId) {
    clearTimeout(badge._timeoutId);
  }
  badge._timeoutId = setTimeout(function () {
    badge.classList.add('d-none');
  }, 8000);
}

/**
 * Modal y carga de imágenes (logos públicos y firma/sello privados).
 */
function initSubidaImagenes() {
  var modalEl = document.getElementById('modalSubirImagen');
  if (!modalEl) return;

  var modalTitulo       = modalEl.querySelector('.modal-title');
  var inputClave        = document.getElementById('subidaClave');
  var inputFile         = document.getElementById('inputArchivoImagen');
  var textoRequisitos   = document.getElementById('subidaRequisitosTexto');
  var contenedorPreview = document.getElementById('contenedorModalPreview');
  var imgPreview        = document.getElementById('imgModalPreview');
  var formSubida        = document.getElementById('formSubirImagen');
  var btnSubir          = document.getElementById('btnConfirmarSubida');

  // Abrir modal con la clave seleccionada
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.btn-abrir-subida');
    if (!btn) return;

    var clave = btn.dataset.clave;
    var titulo = btn.dataset.titulo || 'Imagen';
    var esPrivado = btn.dataset.privado === '1';

    inputClave.value = clave;
    modalTitulo.textContent = 'Actualizar ' + titulo;
    inputFile.value = '';
    contenedorPreview.classList.add('d-none');
    imgPreview.src = '';

    if (esPrivado) {
      inputFile.setAttribute('accept', '.png,image/png');
      textoRequisitos.textContent = 'Estrictamente formato PNG con fondo transparente. Tamaño máx: 2 MB. Ancho mínimo: 300 px.';
    } else if (clave === 'plantel_cintillo') {
      inputFile.setAttribute('accept', '.png,.jpg,.jpeg,image/png,image/jpeg');
      textoRequisitos.textContent = 'Formatos permitidos: PNG o JPG. Franja horizontal para pie de página de constancias. Tamaño máx: 2 MB. Ancho mínimo: 300 px.';
    } else {
      inputFile.setAttribute('accept', '.png,.jpg,.jpeg,image/png,image/jpeg');
      textoRequisitos.textContent = 'Formatos permitidos: PNG o JPG. Tamaño máx: 2 MB. Ancho mínimo: 300 px.';
    }

    var bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
    bsModal.show();
  });

  // Previsualización en el modal al seleccionar archivo
  inputFile.addEventListener('change', function () {
    var archivo = inputFile.files[0];
    if (!archivo) {
      contenedorPreview.classList.add('d-none');
      imgPreview.src = '';
      return;
    }

    // Validación previa de tamaño (2 MB)
    if (archivo.size > 2097152) {
      SIGDEUI.mostrarError('El archivo seleccionado supera los 2 MB de tamaño máximo permitido.', 'Archivo demasiado pesado');
      inputFile.value = '';
      contenedorPreview.classList.add('d-none');
      return;
    }

    var reader = new FileReader();
    reader.onload = function (evt) {
      imgPreview.src = evt.target.result;
      contenedorPreview.classList.remove('d-none');
    };
    reader.readAsDataURL(archivo);
  });

  // Envío del formulario de subida
  formSubida.addEventListener('submit', function (e) {
    e.preventDefault();

    if (!inputFile.files[0]) {
      SIGDEUI.mostrarError('Por favor seleccione una imagen para subir.');
      return;
    }

    var claveSeleccionada = inputClave.value;
    var csrfToken = document.querySelector('input[name="csrf"]')?.value || '';
    var fd = new FormData();
    fd.append('clave', claveSeleccionada);
    fd.append('imagen', inputFile.files[0]);
    fd.append('csrf', csrfToken);

    btnSubir.disabled = true;
    btnSubir.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Guardando en servidor...';

    var url = (window.BASE_URL || '/') + 'configuracionInstitucional/subirImagen';

    SIGDEUI.enviar(url, {
      metodo: 'POST',
      cuerpo: fd
    })
      .then(function (respuesta) {
        var payload = respuesta.data;

        if (respuesta.ok && payload && payload.ok) {
          var bsModal = bootstrap.Modal.getInstance(modalEl);
          if (bsModal) bsModal.hide();

          // Mensaje claro que aclara que se guardó inmediatamente y no requiere pulsar "Guardar Cambios"
          SIGDEUI.exito(
            payload.mensaje || 'Imagen actualizada y guardada con éxito en el servidor. No requiere presionar «Guardar Cambios».',
            '¡Imagen Actualizada!'
          );

          var clave = payload.data?.clave || claveSeleccionada;
          var previewUrl = payload.data?.preview_url;
          var esPrivado = payload.data?.es_privado;

          if (clave && previewUrl) {
            actualizarPreviewEnVista(clave, previewUrl, esPrivado);
          }
          mostrarAvisoImagenActualizada(clave);
          return;
        }

        var errorMsg = (payload && payload.mensaje) ? payload.mensaje : 'No se pudo procesar la imagen.';
        SIGDEUI.mostrarError(errorMsg, 'Error al Subir Imagen');
      })
      .catch(function () {
        SIGDEUI.mostrarError('Ocurrió un problema de comunicación con el servidor al subir la imagen.', 'Error de Conexión');
      })
      .finally(function () {
        btnSubir.disabled = false;
        btnSubir.innerHTML = '<i class="bi bi-cloud-arrow-up" aria-hidden="true"></i> <span>Guardar Imagen Ahora</span>';
      });
  });
}

/**
 * Retiro o restablecimiento de imágenes.
 */
function initQuitarImagenes() {
  document.addEventListener('click', async function (e) {
    var btn = e.target.closest('.btn-quitar-imagen');
    if (!btn) return;

    var clave = btn.dataset.clave;
    var titulo = btn.dataset.titulo || 'imagen';
    var esPrivado = btn.dataset.privado === '1' || ['director_firma', 'institucion_sello', 'cdce_director_firma', 'cdce_institucion_sello'].includes(clave);
    var esCintillo = clave === 'plantel_cintillo';

    var tituloModal = (esPrivado || esCintillo) ? ('¿Retirar ' + titulo + '?') : ('¿Restablecer ' + titulo + '?');
    var textoModal = esPrivado
      ? 'Esta acción eliminará el archivo configurado (los documentos se emitirán con el espacio en blanco).'
      : (esCintillo
        ? 'Esta acción eliminará la franja de pie de página (las constancias se emitirán sin cintillo inferior).'
        : 'Esta acción restablecerá la imagen predeterminada oficial del sistema.');
    var textoBoton = (esPrivado || esCintillo) ? 'Sí, retirar' : 'Sí, restablecer';

    var confirmado = await SIGDEUI.confirmar({
      titulo: tituloModal,
      texto: textoModal,
      confirmar: textoBoton
    });

    if (!confirmado) return;

    var csrfToken = document.querySelector('input[name="csrf"]')?.value || '';
    var fd = new FormData();
    fd.append('clave', clave);
    fd.append('csrf', csrfToken);

    var url = (window.BASE_URL || '/') + 'configuracionInstitucional/quitarImagen';

    SIGDEUI.enviar(url, {
      metodo: 'POST',
      cuerpo: fd
    })
      .then(function (respuesta) {
        var payload = respuesta.data;

        if (respuesta.ok && payload && payload.ok) {
          SIGDEUI.exito(
            payload.mensaje || 'Imagen actualizada en el sistema.',
            '¡Actualizado!'
          );

          var esPrivadoResp = payload.data?.es_privado;
          var fallbackUrl = payload.data?.fallback_url;
          var esCintilloResp = payload.data?.es_cintillo || (clave === 'plantel_cintillo');

          if (esCintilloResp) {
            restablecerPlaceholderCintillo();
          } else if (esPrivadoResp) {
            restablecerPlaceholderPrivado(clave);
          } else if (fallbackUrl) {
            var fbUrlConBusting = fallbackUrl + (fallbackUrl.includes('?') ? '&' : '?') + 't=' + Date.now();
            var img = document.getElementById('preview-' + clave);
            if (img) {
              img.src = fbUrlConBusting;
            }
            if (clave === 'plantel_logo') {
              var hLogo = document.getElementById('header-plantel_logo');
              if (hLogo) hLogo.src = fbUrlConBusting;
            }
          }
          mostrarAvisoImagenActualizada(clave);
          return;
        }

        SIGDEUI.mostrarError((payload && payload.mensaje) ? payload.mensaje : 'No se pudo retirar la imagen.');
      })
      .catch(function () {
        SIGDEUI.mostrarError('Error de conexión al intentar retirar la imagen.');
      });
  });
}

/**
 * Actualiza dinámicamente el contenedor visual tras una subida exitosa usando manipulación de DOM.
 */
function actualizarPreviewEnVista(clave, previewUrl, esPrivado) {
  var urlConBusting = previewUrl + (previewUrl.includes('?') ? '&' : '?') + 't=' + Date.now();

  if (clave === 'plantel_cintillo') {
    mostrarCintilloEnVista(previewUrl, false);
    return;
  }

  if (!esPrivado) {
    var imgPublica = document.getElementById('preview-' + clave);
    if (imgPublica) {
      imgPublica.src = urlConBusting;
    }
    if (clave === 'plantel_logo') {
      var headerLogo = document.getElementById('header-plantel_logo');
      if (headerLogo) headerLogo.src = urlConBusting;
    }
    return;
  }

  // Elemento privado (firma o sello)
  var esFirmaKey = (clave === 'director_firma' || clave === 'cdce_director_firma');
  var wrapper = document.getElementById('wrapper-' + clave);

  var titulo = 'Firma Digitalizada';
  var altText = 'Firma Digitalizada';
  if (clave === 'director_firma') {
    titulo = 'Firma Digitalizada';
    altText = 'Firma del Director';
  } else if (clave === 'institucion_sello') {
    titulo = 'Sello del Plantel';
    altText = 'Sello de la Institución';
  } else if (clave === 'cdce_director_firma') {
    titulo = 'Firma Director(a) CDCE';
    altText = 'Firma del Director CDCE';
  } else if (clave === 'cdce_institucion_sello') {
    titulo = 'Sello CDCE Estadal';
    altText = 'Sello CDCE Estadal';
  }

  if (wrapper) {
    wrapper.innerHTML =
      '<div class="border rounded p-3 bg-white h-100 d-flex flex-column justify-content-between">' +
        '<div>' +
          '<div class="d-flex justify-content-between align-items-center mb-2">' +
            '<span class="fw-semibold text-dark small">' + titulo + '</span>' +
            '<span id="badge-' + clave + '" class="badge bg-success">Configurada</span>' +
          '</div>' +
          '<div class="private-preview-box transparency-pattern mb-2" id="box-' + clave + '">' +
            '<img id="preview-' + clave + '" src="' + urlConBusting + '" alt="' + altText + '">' +
          '</div>' +
        '</div>' +
        '<div class="d-flex gap-1 mt-2">' +
          '<button type="button" class="btn btn-sm btn-outline-primary flex-fill d-inline-flex align-items-center justify-content-center gap-1 btn-abrir-subida" data-clave="' + clave + '" data-titulo="' + altText + '" data-privado="1">' +
            '<i class="bi bi-upload"></i> Cambiar' +
          '</button>' +
          '<button type="button" class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1 btn-quitar-imagen" id="btn-quitar-' + clave + '" data-clave="' + clave + '" data-titulo="' + altText + '" data-privado="1">' +
            '<i class="bi bi-trash"></i>' +
          '</button>' +
        '</div>' +
      '</div>';
    return;
  }

  var box = document.getElementById('box-' + clave);
  var badge = document.getElementById('badge-' + clave);

  if (badge) {
    badge.className = 'badge bg-success';
    badge.textContent = esFirmaKey ? 'Configurada' : 'Configurado';
  }

  if (box) {
    while (box.firstChild) {
      box.removeChild(box.firstChild);
    }
    var imgPrivada = document.createElement('img');
    imgPrivada.id = 'preview-' + clave;
    imgPrivada.src = urlConBusting;
    imgPrivada.alt = esFirmaKey ? 'Firma Digitalizada' : 'Sello Oficial';
    box.appendChild(imgPrivada);
  }
}

/**
 * Restablece visualmente el contenedor privado a su estado sin archivo cargado usando nodos DOM.
 */
function restablecerPlaceholderPrivado(clave) {
  var esFirmaKey = (clave === 'director_firma' || clave === 'cdce_director_firma');
  var wrapper = document.getElementById('wrapper-' + clave);

  var titulo = 'Firma Digitalizada';
  var iconClass = 'bi-pen';
  var uploadTitle = 'Firma';
  var badgeText = 'Sin Firma';

  if (clave === 'director_firma') {
    titulo = 'Firma Digitalizada';
    iconClass = 'bi-pen';
    uploadTitle = 'Firma del Director';
    badgeText = 'Sin Firma';
  } else if (clave === 'institucion_sello') {
    titulo = 'Sello del Plantel';
    iconClass = 'bi-award';
    uploadTitle = 'Sello del Plantel';
    badgeText = 'Sin Sello';
  } else if (clave === 'cdce_director_firma') {
    titulo = 'Firma Director(a) CDCE';
    iconClass = 'bi-pen';
    uploadTitle = 'Firma del Director CDCE';
    badgeText = 'Sin Firma';
  } else if (clave === 'cdce_institucion_sello') {
    titulo = 'Sello CDCE Estadal';
    iconClass = 'bi-award';
    uploadTitle = 'Sello CDCE Estadal';
    badgeText = 'Sin Sello';
  }

  if (wrapper) {
    wrapper.innerHTML =
      '<div class="compact-signature-slot">' +
        '<div class="compact-signature-slot__info">' +
          '<i class="bi ' + iconClass + ' compact-signature-slot__icon" aria-hidden="true"></i>' +
          '<div>' +
            '<div class="compact-signature-slot__title">' + titulo + '</div>' +
            '<span id="badge-' + clave + '" class="badge bg-secondary">' + badgeText + '</span>' +
          '</div>' +
        '</div>' +
        '<div id="box-' + clave + '" class="d-none"></div>' +
        '<button type="button" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1 btn-abrir-subida" data-clave="' + clave + '" data-titulo="' + uploadTitle + '" data-privado="1">' +
          '<i class="bi bi-upload"></i> Cargar' +
        '</button>' +
      '</div>';
    return;
  }

  var box = document.getElementById('box-' + clave);
  var badge = document.getElementById('badge-' + clave);
  var btnQuitar = document.getElementById('btn-quitar-' + clave);

  if (badge) {
    badge.className = 'badge bg-secondary';
    badge.textContent = esFirmaKey ? 'Sin Firma' : 'Sin Sello';
  }

  if (btnQuitar) {
    btnQuitar.remove();
  }

  if (box) {
    while (box.firstChild) {
      box.removeChild(box.firstChild);
    }

    var contenedor = document.createElement('div');
    contenedor.className = 'text-center text-secondary small py-2';
    contenedor.id = 'placeholder-' + clave;

    var icono = document.createElement('i');
    icono.className = 'bi ' + (esFirmaKey ? 'bi-pen' : 'bi-award') + ' fs-5 d-block mb-1 text-muted';

    var span = document.createElement('span');
    span.className = 'small';
    span.textContent = esFirmaKey
      ? 'Sin firma cargada'
      : 'Sin sello cargado';

    contenedor.appendChild(icono);
    contenedor.appendChild(span);
    box.appendChild(contenedor);
  }
}

/**
 * Restablecimiento de la franja tricolor oficial predeterminada.
 */
function initRestablecerCintilloOficial() {
  document.addEventListener('click', async function (e) {
    var btn = e.target.closest('.btn-restablecer-cintillo-oficial');
    if (!btn) return;

    var confirmado = await SIGDEUI.confirmar({
      titulo: '¿Restablecer Franja Tricolor Oficial?',
      texto: 'Se aplicará el cintillo tricolor oficial predeterminado del sistema para el pie de página de las constancias.',
      confirmar: 'Sí, usar franja oficial'
    });

    if (!confirmado) return;

    var csrfToken = document.querySelector('input[name="csrf"]')?.value || '';
    var fd = new FormData();
    fd.append('clave', 'plantel_cintillo');
    fd.append('csrf', csrfToken);

    var url = (window.BASE_URL || '/') + 'configuracionInstitucional/restablecerOficial';

    SIGDEUI.enviar(url, {
      metodo: 'POST',
      cuerpo: fd
    })
      .then(function (respuesta) {
        var payload = respuesta.data;
        if (respuesta.ok && payload && payload.ok) {
          SIGDEUI.exito(
            payload.mensaje || 'Se ha restablecido la franja tricolor oficial.',
            '¡Franja Restablecida!'
          );

          var previewUrl = payload.data?.preview_url || '/assets/img/franjatricolor.png';
          mostrarCintilloEnVista(previewUrl, true);
          mostrarAvisoImagenActualizada('plantel_cintillo');
          return;
        }

        SIGDEUI.mostrarError((payload && payload.mensaje) ? payload.mensaje : 'No se pudo restablecer la franja oficial.');
      })
      .catch(function () {
        SIGDEUI.mostrarError('Error de conexión al intentar restablecer la franja oficial.');
      });
  });
}

/**
 * Restablece visualmente el contenedor de la franja tricolor cuando es retirada (sin cintillo).
 */
function restablecerPlaceholderCintillo() {
  var box = document.getElementById('box-plantel_cintillo');
  var badge = document.getElementById('badge-plantel_cintillo');
  var btnSubir = document.getElementById('btn-subir-plantel_cintillo');
  var btnQuitar = document.getElementById('btn-quitar-plantel_cintillo');
  var btnOficial = document.getElementById('btn-oficial-plantel_cintillo');

  if (badge) {
    badge.className = 'badge bg-secondary';
    badge.textContent = 'Sin Franja';
  }

  if (box) {
    while (box.firstChild) {
      box.removeChild(box.firstChild);
    }
    var div = document.createElement('div');
    div.className = 'text-center text-secondary small py-2';
    div.id = 'placeholder-plantel_cintillo';

    var icon = document.createElement('i');
    icon.className = 'bi bi-slash-circle me-1 text-muted';
    div.appendChild(icon);

    var span = document.createElement('span');
    span.textContent = 'Sin franja inferior activa';
    div.appendChild(span);

    box.appendChild(div);
  }

  if (btnSubir) {
    btnSubir.innerHTML = '<i class="bi bi-upload"></i> Cargar Franja';
  }
  if (btnQuitar) btnQuitar.classList.add('d-none');
  if (btnOficial) btnOficial.classList.remove('d-none');
}

/**
 * Muestra dinámicamente la franja tricolor en el contenedor tras subida o restablecimiento.
 */
function mostrarCintilloEnVista(previewUrl, esOficial) {
  var box = document.getElementById('box-plantel_cintillo');
  var badge = document.getElementById('badge-plantel_cintillo');
  var btnSubir = document.getElementById('btn-subir-plantel_cintillo');
  var btnQuitar = document.getElementById('btn-quitar-plantel_cintillo');
  var btnOficial = document.getElementById('btn-oficial-plantel_cintillo');

  if (badge) {
    if (esOficial) {
      badge.className = 'badge bg-info';
      badge.textContent = 'Predeterminada Oficial';
    } else {
      badge.className = 'badge bg-success';
      badge.textContent = 'Personalizada';
    }
  }

  var urlConBusting = previewUrl + (previewUrl.includes('?') ? '&' : '?') + 't=' + Date.now();

  if (box) {
    while (box.firstChild) {
      box.removeChild(box.firstChild);
    }
    var img = document.createElement('img');
    img.id = 'preview-plantel_cintillo';
    img.src = urlConBusting;
    img.alt = 'Franja Tricolor';
    box.appendChild(img);
  }

  if (btnSubir) {
    btnSubir.innerHTML = '<i class="bi bi-upload"></i> Cambiar Imagen';
  }
  if (btnQuitar) btnQuitar.classList.remove('d-none');
  if (btnOficial) {
    if (esOficial) {
      btnOficial.classList.add('d-none');
    } else {
      btnOficial.classList.remove('d-none');
    }
  }
}

