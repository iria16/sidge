/* SIGDE - modules/resultado_academico/seccion.js
 * Nómina de literales: filtros, contador, estado de guardado por fila,
 * confirmación de promovido -> no promovido y cierre de sección.
 *
 * La regla de qué literales promueven llega desde el servidor en data-*;
 * este archivo no la duplica. El servidor vuelve a validar todo.
 */

(function () {
  'use strict';

  var form = document.getElementById('formResultados');
  if (!form) return;

  function jsonLista(valor) {
    try {
      var lista = JSON.parse(valor || '[]');
      return Array.isArray(lista) ? lista.map(function (item) { return String(item).toUpperCase(); }) : [];
    } catch (e) {
      return [];
    }
  }

  var literalesPromueven = jsonLista(form.getAttribute('data-literales-promueven'));
  var literalesExigenObs = jsonLista(form.getAttribute('data-literales-exigen-observacion'));
  var idGradoActual = parseInt(form.getAttribute('data-id-grado-actual') || '0', 10);
  var idGradoSiguiente = parseInt(form.getAttribute('data-id-grado-siguiente') || '0', 10);
  var textoGradoAvanza = form.getAttribute('data-texto-grado-avanza') || 'Grado superior';
  var gradoNombre = form.getAttribute('data-grado-nombre') || 'Mismo grado';
  var soloLectura = form.getAttribute('data-solo-lectura') === '1';

  var filas = Array.prototype.slice.call(document.querySelectorAll('.fila-estudiante'));
  var dirty = {};
  var filtroInicial = String(form.getAttribute('data-filtro-inicial') || 'todos').toUpperCase();
  var filtroActual = (filtroInicial === 'PENDIENTE' || filtroInicial === 'CARGADOS')
    ? filtroInicial
    : 'todos';

  var inputBuscador = document.getElementById('filtroBuscador');
  var btnGuardar = document.getElementById('btnGuardarPropuesta');

  function normalizar(valor) {
    return String(valor || '').toUpperCase().trim();
  }

  function escapeHtml(valor) {
    return String(valor || '').replace(/[&<>"']/g, function (caracter) {
      return {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
      }[caracter];
    });
  }

  function promueve(literal) {
    return literalesPromueven.indexOf(literal) !== -1;
  }

  function exigeObservacion(literal) {
    return literalesExigenObs.indexOf(literal) !== -1;
  }

  function filaDe(id) {
    return document.querySelector('tr[data-student-row="' + id + '"]');
  }

  function marcarEstado(fila, estado, mensaje) {
    if (!fila) return;

    var indicador = fila.querySelector('[data-guardado-indicador]');
    if (indicador) {
      var clases = 'indicador-guardado ms-1 ';
      var icono = '';
      var titulo = 'Sin guardar';

      if (estado === 'guardado') {
        clases += 'text-success';
        icono = '<i class="bi bi-check2" aria-hidden="true"></i>';
        titulo = 'Guardado';
      } else if (estado === 'guardando') {
        clases += 'text-primary';
        icono = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true" style="width: 0.8rem; height: 0.8rem;"></span>';
        titulo = 'Guardando';
      } else if (estado === 'error') {
        clases += 'text-danger';
        icono = '<i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>';
        titulo = 'Error al guardar';
      } else if (estado === 'pendiente') {
        clases += 'text-warning';
        icono = '<i class="bi bi-pencil-fill" aria-hidden="true"></i>';
        titulo = 'Cambio sin guardar';
      } else {
        clases += 'text-muted';
        icono = '<i class="bi bi-dash" aria-hidden="true"></i>';
        titulo = 'Sin literal';
      }

      indicador.className = clases;
      indicador.setAttribute('data-estado-guardado', estado);
      indicador.title = titulo;
      indicador.innerHTML = icono;
    }

    var errorFila = fila.querySelector('[data-error-fila]');
    if (errorFila) {
      errorFila.textContent = mensaje || '';
      errorFila.hidden = !mensaje;
    }
  }

  function actualizarContadores() {
    var conLiteral = 0;
    var sinLiteral = 0;

    filas.forEach(function (fila) {
      if (fila.getAttribute('data-tiene-literal') === '1') {
        conLiteral++;
      } else {
        sinLiteral++;
      }
    });

    var fPend = document.getElementById('filterCountPendientes');
    var fCarg = document.getElementById('filterCountCargados');
    var contador = document.querySelector('[data-contador-texto]');

    if (fPend) fPend.textContent = String(sinLiteral);
    if (fCarg) fCarg.textContent = String(conLiteral);
    if (contador) contador.textContent = conLiteral + ' de ' + filas.length + ' evaluados';
  }

  function aplicarFiltro() {
    var texto = inputBuscador ? inputBuscador.value.toLowerCase().trim() : '';

    filas.forEach(function (fila) {
      var nombre = fila.querySelector('.nombre-estudiante');
      var documento = fila.querySelector('.documento-estudiante');
      var busqueda = ((nombre ? nombre.textContent : '') + ' ' + (documento ? documento.textContent : '')).toLowerCase();
      var tieneLiteral = fila.getAttribute('data-tiene-literal') === '1';

      var coincideFiltro = filtroActual === 'todos'
        || (filtroActual === 'PENDIENTE' && !tieneLiteral)
        || (filtroActual === 'CARGADOS' && tieneLiteral);
      var coincideTexto = texto === '' || busqueda.indexOf(texto) !== -1;

      fila.hidden = !(coincideFiltro && coincideTexto);
    });
  }

  function actualizarFila(select) {
    var studentId = select.getAttribute('data-id');
    var literal = normalizar(select.value);
    var fila = select.closest('tr');
    var inputEstado = document.getElementById('input_estado_' + studentId);
    var inputGrado = document.getElementById('input_grado_' + studentId);
    var inputLiteral = document.getElementById('input_literal_' + studentId);
    var badgeEstado = document.getElementById('badge_estado_' + studentId);
    var proyeccion = document.getElementById('proyeccion_grado_' + studentId);
    var celdaBoleta = document.getElementById('boleta_accion_' + studentId);

    select.setAttribute('data-literal', literal);
    // El select es solo interfaz: el literal que viaja en el POST va en el
    // hidden, porque para el directivo el select está deshabilitado.
    if (inputLiteral) inputLiteral.value = literal;
    if (fila) {
      fila.setAttribute('data-tiene-literal', literal === '' ? '0' : '1');
    }

    if (literal === '') {
      if (inputEstado) inputEstado.value = 'PENDIENTE';
      if (inputGrado) inputGrado.value = '';
      if (fila) fila.setAttribute('data-estado-actual', 'PENDIENTE');
      if (badgeEstado) {
        badgeEstado.innerHTML = '<span class="decision-badge decision-badge--pendiente"><i class="bi bi-clock-fill" aria-hidden="true"></i> Pendiente</span>';
      }
      if (proyeccion) {
        proyeccion.innerHTML = '<div class="text-muted"><i class="bi bi-dash text-secondary"></i> Sin evaluar</div>';
      }
      if (celdaBoleta) {
        celdaBoleta.innerHTML = '<span class="text-muted small" title="Requiere asignar literal">—</span>';
      }
    } else if (promueve(literal)) {
      if (inputEstado) inputEstado.value = 'PROMOVIDO';
      if (inputGrado) inputGrado.value = String(idGradoSiguiente || idGradoActual);
      if (fila) fila.setAttribute('data-estado-actual', 'PROMOVIDO');
      if (badgeEstado) {
        badgeEstado.innerHTML = '<span class="decision-badge decision-badge--promovido"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Promovido</span>';
      }
      if (proyeccion) {
        proyeccion.innerHTML = '<div class="text-secondary"><i class="bi bi-arrow-right-short text-success"></i> Avanza: <strong>' + escapeHtml(textoGradoAvanza) + '</strong></div>';
      }
      if (celdaBoleta) {
        var nombreEst = fila ? (fila.querySelector('.nombre-estudiante') ? fila.querySelector('.nombre-estudiante').textContent : '') : '';
        var documento = fila ? (fila.querySelector('.documento-estudiante') ? fila.querySelector('.documento-estudiante').textContent.trim() : '') : '';
        celdaBoleta.innerHTML = '<button type="button" class="btn btn-sm btn-outline-primary py-1 px-2 d-inline-flex align-items-center gap-1 btn-preview-certificado" ' +
          'data-url-preview="<?= BASE_URL ?>certificadoCalificaciones/generarIndividual?id_estudiante=' + studentId + '&id_estructura=<?= $idEstructura ?>" ' +
          'data-titulo="Certificado de Calificaciones" ' +
          'data-subtitulo="' + escapeHtml(nombreEst + ' · ' + documento + ' (' + literal + ' - PROMOVIDO)') + '" ' +
          'title="Visualizar e imprimir Certificado de Calificaciones">' +
          '<i class="bi bi-file-earmark-text" aria-hidden="true"></i><span>Visualizar</span></button>';
      }
    } else {
      if (inputEstado) inputEstado.value = 'NO_PROMOVIDO';
      if (inputGrado) inputGrado.value = String(idGradoActual);
      if (fila) fila.setAttribute('data-estado-actual', 'NO_PROMOVIDO');
      if (badgeEstado) {
        badgeEstado.innerHTML = '<span class="decision-badge decision-badge--no-promovido"><i class="bi bi-x-circle-fill" aria-hidden="true"></i> No promovido</span>';
      }
      if (proyeccion) {
        proyeccion.innerHTML = '<div class="text-secondary"><i class="bi bi-arrow-repeat text-danger"></i> Permanece: <strong>' + escapeHtml(gradoNombre) + '</strong></div>';
      }
      if (celdaBoleta) {
        var nombreEst = fila ? (fila.querySelector('.nombre-estudiante') ? fila.querySelector('.nombre-estudiante').textContent : '') : '';
        var documento = fila ? (fila.querySelector('.documento-estudiante') ? fila.querySelector('.documento-estudiante').textContent.trim() : '') : '';
        celdaBoleta.innerHTML = '<button type="button" class="btn btn-sm btn-outline-primary py-1 px-2 d-inline-flex align-items-center gap-1 btn-preview-certificado" ' +
          'data-url-preview="<?= BASE_URL ?>certificadoCalificaciones/generarIndividual?id_estudiante=' + studentId + '&id_estructura=<?= $idEstructura ?>" ' +
          'data-titulo="Certificado de Calificaciones" ' +
          'data-subtitulo="' + escapeHtml(nombreEst + ' · ' + documento + ' (' + literal + ' - NO PROMOVIDO)') + '" ' +
          'title="Visualizar e imprimir Certificado de Calificaciones">' +
          '<i class="bi bi-file-earmark-text" aria-hidden="true"></i><span>Visualizar</span></button>';
      }
    }

    var observacion = fila ? fila.querySelector('[data-observacion]') : null;
    var marcaObs = fila ? fila.querySelector('[data-obs-requerida]') : null;
    var requerida = literal !== '' && exigeObservacion(literal);

    if (observacion) {
      observacion.required = requerida && !soloLectura;
    }
    if (marcaObs) {
      marcaObs.hidden = !requerida;
    }

    actualizarContadores();
    aplicarFiltro();
  }

  function marcarCambio(fila) {
    if (!fila) return;
    dirty[fila.getAttribute('data-student-row')] = true;
    marcarEstado(fila, 'pendiente', '');
  }

  document.querySelectorAll('.select-literal-pill').forEach(function (select) {
    select.addEventListener('change', function () {
      actualizarFila(select);
      marcarCambio(select.closest('tr'));
    });
  });

  document.querySelectorAll('[data-observacion]').forEach(function (input) {
    input.addEventListener('input', function () {
      var fila = input.closest('tr');
      marcarCambio(fila);
      var errorFila = fila ? fila.querySelector('[data-error-fila]') : null;
      if (errorFila) {
        errorFila.textContent = '';
        errorFila.hidden = true;
      }
    });
  });

  document.querySelectorAll('.btn-filter-tab').forEach(function (boton) {
    boton.addEventListener('click', function () {
      document.querySelectorAll('.btn-filter-tab').forEach(function (b) { b.classList.remove('active'); });
      boton.classList.add('active');
      filtroActual = boton.getAttribute('data-filter') || 'todos';
      aplicarFiltro();
    });
  });

  if (inputBuscador) {
    inputBuscador.addEventListener('input', aplicarFiltro);
  }

  var collapseCriterios = document.getElementById('collapseCriterios');
  var chevronCriterios = document.getElementById('chevronCriterios');
  if (collapseCriterios && chevronCriterios) {
    collapseCriterios.addEventListener('show.bs.collapse', function () {
      chevronCriterios.classList.remove('bi-chevron-down');
      chevronCriterios.classList.add('bi-chevron-up');
    });
    collapseCriterios.addEventListener('hide.bs.collapse', function () {
      chevronCriterios.classList.remove('bi-chevron-up');
      chevronCriterios.classList.add('bi-chevron-down');
    });
  }

  if (form && btnGuardar && window.SIGDEUI) {
    form.addEventListener('submit', function (evento) {
      if (!form.action || form.action.indexOf('guardarPropuesta') === -1) {
        return;
      }

      evento.preventDefault();
      if (soloLectura) return;

      var primerError = null;

      filas.forEach(function (fila) {
        var select = fila.querySelector('.select-literal-pill');
        var literal = select ? normalizar(select.value) : '';
        var observacion = fila.querySelector('[data-observacion]');
        var errorFila = fila.querySelector('[data-error-fila]');

        if (errorFila) {
          errorFila.textContent = '';
          errorFila.hidden = true;
        }

        if (literal !== '' && exigeObservacion(literal) && observacion && observacion.value.trim() === '') {
          marcarEstado(fila, 'error', 'La observación es obligatoria para el literal ' + literal + '.');
          if (!primerError) primerError = observacion;
        }
      });

      if (primerError) {
        SIGDEUI.mostrarError('Completa la observación de los literales que la exigen.', 'Faltan datos');
        primerError.focus();
        return;
      }

      var ids = Object.keys(dirty);
      if (ids.length === 0) {
        SIGDEUI.mostrarAviso('info', 'Sin cambios', 'No hay literales ni observaciones por guardar.');
        return;
      }

      ids.forEach(function (id) {
        marcarEstado(filaDe(id), 'guardando');
      });

      var textoOriginal = btnGuardar.innerHTML;
      btnGuardar.disabled = true;
      btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span><span>Guardando...</span>';

      SIGDEUI.enviar(form.action, {
        metodo: 'POST',
        cuerpo: new FormData(form)
      }).then(function (respuesta) {
        var sobre = respuesta.data || {};
        var datos = sobre.data || {};
        var errores = datos.errores || {};
        var guardados = (datos.guardados_ids || []).map(String);
        var huboErrorGeneral = false;
        var pendientes = {};

        ids.forEach(function (id) {
          var fila = filaDe(id);
          if (!fila) return;

          if (errores[id]) {
            marcarEstado(fila, 'error', String(errores[id]));
            pendientes[id] = true;
          } else if (guardados.indexOf(String(id)) !== -1) {
            marcarEstado(fila, 'guardado', '');
          } else {
            huboErrorGeneral = true;
            marcarEstado(fila, 'error', 'No se pudo guardar esta fila.');
            pendientes[id] = true;
          }
        });

        /* Solo las filas que el servidor confirmó dejan de estar pendientes.
           Así se puede reintentar el guardado sin tocar de nuevo el formulario. */
        dirty = pendientes;

        if (!respuesta.ok || !sobre.ok) {
          var mensaje = sobre.mensaje || 'No se pudieron guardar los literales.';
          SIGDEUI.mostrarError(mensaje);
          if (!Object.keys(errores).length && huboErrorGeneral) {
            ids.forEach(function (id) {
              marcarEstado(filaDe(id), 'error', mensaje);
            });
          }
          return;
        }

        if (Object.keys(errores).length) {
          SIGDEUI.mostrarAviso('warning', 'Revisa los literales', sobre.mensaje || 'Hay filas con error.');
        } else {
          SIGDEUI.exito(sobre.mensaje || 'Literales guardados correctamente.', '¡Listo!').then(function () { window.location.reload(); });
        }

        actualizarContadores();
        aplicarFiltro();
      }).catch(function () {
        ids.forEach(function (id) {
          marcarEstado(filaDe(id), 'error', 'No se pudo conectar con el servidor.');
        });
        SIGDEUI.mostrarError('No se pudo conectar con el servidor.');
      }).then(function () {
        btnGuardar.disabled = false;
        btnGuardar.innerHTML = textoOriginal;
      });
    });
  }

  actualizarContadores();
  aplicarFiltro();

  /* Vista previa interactiva de Certificados de Calificaciones */
  var modalCertificado = document.getElementById('modalVistaPreviaCertificado');
  var iframeCertificado = document.getElementById('iframeVistaPreviaCertificado');
  var btnDescargarCertificado = document.getElementById('btnDescargarCertificadoPdf');
  var btnImprimirCertificado = document.getElementById('btnImprimirCertificado');

  if (modalCertificado && iframeCertificado) {
    var modalTitleEl = modalCertificado.querySelector('.modal-title');
    var modalSubtitleEl = modalCertificado.querySelector('.modal-header__subtitle, .modal-subtitle, .app-modal__subtitle, small.text-muted');

    document.addEventListener('click', function (e) {
      var btn = e.target.closest('.btn-preview-certificado');
      if (!btn) return;

      var urlPreview = btn.getAttribute('data-url-preview') || '';
      var urlDownload = btn.getAttribute('data-url-download') || urlPreview;
      var titulo = btn.getAttribute('data-titulo') || 'Certificado de Calificaciones';
      var subtitulo = btn.getAttribute('data-subtitulo') || '';

      if (!urlPreview) return;

      if (modalTitleEl) {
        modalTitleEl.textContent = titulo;
      }
      if (modalSubtitleEl) {
        modalSubtitleEl.textContent = subtitulo;
      }

      iframeCertificado.src = urlPreview;

      if (btnDescargarCertificado) {
        btnDescargarCertificado.setAttribute('href', urlDownload);
      }

      if (window.bootstrap && bootstrap.Modal) {
        var modal = bootstrap.Modal.getInstance(modalCertificado) || new bootstrap.Modal(modalCertificado);
        modal.show();
      } else if (window.SIGDEUI && SIGDEUI.mostrarModal) {
        SIGDEUI.mostrarModal(modalCertificado);
      }
    });

    if (btnImprimirCertificado) {
      btnImprimirCertificado.addEventListener('click', function () {
        try {
          iframeCertificado.contentWindow.focus();
          iframeCertificado.contentWindow.print();
        } catch (err) {
          var url = btnDescargarCertificado ? btnDescargarCertificado.getAttribute('href') : null;
          if (url && url !== '#') {
            window.open(url, '_blank');
          }
        }
      });
    }

    modalCertificado.addEventListener('hidden.bs.modal', function () {
      iframeCertificado.src = 'about:blank';
    });
  }
})();
