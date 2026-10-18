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
  initSubidaImagenes();
  initQuitarImagenes();
  initRestablecerOficial();
});

/**
 * Gestión del formulario principal de textos oficiales y directivos.
 */
function initFormTextos() {
  var form = document.getElementById('formTextosInstitucion');
  if (!form) return;

  var btnGuardar = document.getElementById('btnGuardarTextos');

  form.addEventListener('submit', function (e) {
    e.preventDefault();

    // Limpiar errores previos
    form.querySelectorAll('.is-invalid').forEach(function (el) {
      el.classList.remove('is-invalid');
    });
    form.querySelectorAll('.invalid-feedback').forEach(function (el) {
      el.textContent = '';
    });

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
          SIGDEUI.exito(payload.mensaje || 'Configuración guardada correctamente.', '¡Actualizado!');

          // Actualizar los inputs con los valores formateados (capitalización y normalización)
          if (payload.data && payload.data.campos) {
            Object.keys(payload.data.campos).forEach(function (campo) {
              var input = form.querySelector('[name="' + campo + '"]');
              if (input) {
                input.value = payload.data.campos[campo];
              }
            });
          }

          // Si el director ya quedó completamente configurado, retirar el aviso específico
          if (payload.data && payload.data.director_configurado) {
            var alertaIncompleta = document.getElementById('alertaDirectorIncompleto');
            if (alertaIncompleta) {
              alertaIncompleta.remove();
            }
          }
          return;
        }

        // Manejo de errores de validación (HTTP 422)
        var mensajeError = (payload && payload.mensaje) ? payload.mensaje : 'Revise los campos señalados en el formulario.';

        if (payload && payload.data && typeof payload.data === 'object') {
          Object.keys(payload.data).forEach(function (campo) {
            var input = form.querySelector('[name="' + campo + '"]');
            var errorDiv = document.getElementById('error-' + campo);
            if (input) {
              input.classList.add('is-invalid');
            }
            if (errorDiv) {
              errorDiv.textContent = payload.data[campo];
            }
          });
        }

        SIGDEUI.mostrarError(mensajeError, 'Error de Validación');
      })
      .catch(function () {
        SIGDEUI.mostrarError('Ocurrió un problema de red al intentar guardar los datos.', 'Error de Conexión');
      })
      .finally(function () {
        if (btnGuardar) {
          btnGuardar.disabled = false;
          btnGuardar.innerHTML = '<i class="bi bi-save" aria-hidden="true"></i> <span>Guardar Cambios</span>';
        }
      });
  });
}

/**
 * Modal y carga de imágenes (logos públicos y firma/sello privados).
 */
function initSubidaImagenes() {
  var modalEl = document.getElementById('modalSubirImagen');
  if (!modalEl) return;

  var modalTitulo       = document.getElementById('modalSubirImagenTitulo');
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

    var csrfToken = document.querySelector('input[name="csrf"]')?.value || '';
    var fd = new FormData();
    fd.append('clave', inputClave.value);
    fd.append('imagen', inputFile.files[0]);
    fd.append('csrf', csrfToken);

    btnSubir.disabled = true;
    btnSubir.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Procesando...';

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

          SIGDEUI.exito(payload.mensaje || 'Imagen procesada y guardada con éxito.');

          var clave = payload.data?.clave;
          var previewUrl = payload.data?.preview_url;
          var esPrivado = payload.data?.es_privado;

          if (clave && previewUrl) {
            actualizarPreviewEnVista(clave, previewUrl, esPrivado);
          }
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
        btnSubir.innerHTML = '<i class="bi bi-cloud-arrow-up" aria-hidden="true"></i> <span>Subir y Guardar</span>';
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
    var esPrivado = btn.dataset.privado === '1' || ['director_firma', 'institucion_sello'].includes(clave);
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
          SIGDEUI.exito(payload.mensaje || 'Operación completada correctamente.');

          var esPrivadoResp = payload.data?.es_privado;
          var fallbackUrl = payload.data?.fallback_url;
          var esCintilloResp = payload.data?.es_cintillo || (clave === 'plantel_cintillo');

          if (esCintilloResp) {
            restablecerPlaceholderCintillo();
          } else if (esPrivadoResp) {
            restablecerPlaceholderPrivado(clave);
          } else if (fallbackUrl) {
            var img = document.getElementById('preview-' + clave);
            if (img) {
              img.src = fallbackUrl + (fallbackUrl.includes('?') ? '&' : '?') + 't=' + Date.now();
            }
          }
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
    return;
  }

  // Elemento privado (firma o sello)
  var box = document.getElementById('box-' + clave);
  var badge = document.getElementById('badge-' + clave);

  if (badge) {
    badge.className = 'badge bg-success';
    badge.textContent = (clave === 'director_firma') ? 'Configurada' : 'Configurado';
  }

  if (box) {
    while (box.firstChild) {
      box.removeChild(box.firstChild);
    }
    var imgPrivada = document.createElement('img');
    imgPrivada.id = 'preview-' + clave;
    imgPrivada.src = urlConBusting;
    imgPrivada.alt = (clave === 'director_firma') ? 'Firma del Director' : 'Sello Institucional';
    box.appendChild(imgPrivada);
  }

  // Asegurar que exista el botón de retirar si no existía antes
  var btnQuitar = document.getElementById('btn-quitar-' + clave);
  if (!btnQuitar && box) {
    var contenedorBotones = box.parentElement.querySelector('.d-flex.gap-2');
    if (contenedorBotones) {
      var nuevoBtn = document.createElement('button');
      nuevoBtn.type = 'button';
      nuevoBtn.id = 'btn-quitar-' + clave;
      nuevoBtn.className = 'btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1 btn-quitar-imagen';
      nuevoBtn.dataset.clave = clave;
      nuevoBtn.dataset.titulo = (clave === 'director_firma') ? 'Firma del Director' : 'Sello Institucional';
      nuevoBtn.dataset.privado = '1';

      var icono = document.createElement('i');
      icono.className = 'bi bi-trash';
      nuevoBtn.appendChild(icono);
      nuevoBtn.appendChild(document.createTextNode(' Retirar'));

      contenedorBotones.appendChild(nuevoBtn);
    }
  }
}

/**
 * Restablece visualmente el contenedor privado a su estado sin archivo cargado usando nodos DOM.
 */
function restablecerPlaceholderPrivado(clave) {
  var box = document.getElementById('box-' + clave);
  var badge = document.getElementById('badge-' + clave);
  var btnQuitar = document.getElementById('btn-quitar-' + clave);

  if (badge) {
    badge.className = 'badge bg-secondary';
    badge.textContent = (clave === 'director_firma') ? 'Sin Firma' : 'Sin Sello';
  }

  if (btnQuitar) {
    btnQuitar.remove();
  }

  if (box) {
    while (box.firstChild) {
      box.removeChild(box.firstChild);
    }

    var contenedor = document.createElement('div');
    contenedor.className = 'text-center text-secondary small py-3';
    contenedor.id = 'placeholder-' + clave;

    var icono = document.createElement('i');
    icono.className = 'bi ' + (clave === 'director_firma' ? 'bi-pen' : 'bi-award') + ' fs-4 d-block mb-1 text-muted';

    var span = document.createElement('span');
    span.textContent = (clave === 'director_firma')
      ? 'Sin firma digitalizada (se emitirá en blanco)'
      : 'Sin sello digitalizado (se emitirá en blanco)';

    contenedor.appendChild(icono);
    contenedor.appendChild(span);
    box.appendChild(contenedor);
  }
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
    icon.className = 'bi bi-slash-circle fs-5 d-block mb-1 text-muted';
    div.appendChild(icon);

    var span = document.createElement('span');
    span.textContent = 'Sin franja de pie de página (las constancias se emitirán sin cintillo inferior)';
    div.appendChild(span);

    box.appendChild(div);
  }

  if (btnSubir) {
    btnSubir.innerHTML = '<i class="bi bi-upload"></i> Cargar Imagen (PNG/JPG)';
  }
  if (btnQuitar) btnQuitar.classList.add('d-none');
  if (btnOficial) btnOficial.classList.remove('d-none');
}

/**
 * Muestra dinámicamente la franja tricolor en el contenedor tras subida o restablecimiento oficial.
 */
function mostrarCintilloEnVista(previewUrl, esOficial) {
  var box = document.getElementById('box-plantel_cintillo');
  var badge = document.getElementById('badge-plantel_cintillo');
  var btnSubir = document.getElementById('btn-subir-plantel_cintillo');
  var btnQuitar = document.getElementById('btn-quitar-plantel_cintillo');
  var btnOficial = document.getElementById('btn-oficial-plantel_cintillo');

  if (badge) {
    badge.className = esOficial ? 'badge bg-info text-dark border' : 'badge bg-success';
    badge.textContent = esOficial ? 'Oficial' : 'Personalizada';
  }

  if (box) {
    while (box.firstChild) {
      box.removeChild(box.firstChild);
    }
    var urlConBusting = previewUrl + (previewUrl.includes('?') ? '&' : '?') + 't=' + Date.now();
    var img = document.createElement('img');
    img.id = 'preview-plantel_cintillo';
    img.src = urlConBusting;
    img.alt = 'Franja Tricolor de Pie de Página';
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

/**
 * Gestiona el botón para restablecer la franja tricolor oficial del sistema.
 */
function initRestablecerOficial() {
  document.addEventListener('click', async function (e) {
    var btn = e.target.closest('.btn-restablecer-oficial');
    if (!btn) return;

    var confirmado = await SIGDEUI.confirmar({
      titulo: '¿Restablecer Franja Tricolor Oficial?',
      texto: 'Esta acción volverá a activar la franja tricolor oficial por defecto en el pie de página de las constancias.',
      confirmar: 'Sí, restablecer'
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
          SIGDEUI.exito(payload.mensaje || 'Franja tricolor oficial restablecida.');
          var previewUrl = payload.data?.preview_url;
          if (previewUrl) {
            mostrarCintilloEnVista(previewUrl, true);
          }
          return;
        }
        SIGDEUI.mostrarError((payload && payload.mensaje) ? payload.mensaje : 'No se pudo restablecer la franja oficial.');
      })
      .catch(function () {
        SIGDEUI.mostrarError('Error de conexión al restablecer la franja oficial.');
      });
  });
}

