/* SIGDE - Comportamiento del módulo de Reingresos Escolares (solo rol secretaria) */
document.addEventListener('DOMContentLoaded', function () {
  var route = (window.BASE_URL || '') + 'reingreso/';
  var formRegistrar = document.getElementById('formRegistrarReingreso');
  var modalRegistrar = document.getElementById('registrarReingresoModal');

  if (!formRegistrar) return;

  var inputBuscar    = document.getElementById('reingresoBuscarEstudiante');
  var inputIdEst     = document.getElementById('reingresoIdEstudiante');
  var feedbackBuscar = document.getElementById('reingresoBuscarFeedback');
  var cajaResultados = document.getElementById('reingresoResultados');
  var panelDatos     = document.getElementById('reingresoEstudianteDatos');
  var contenedorCampos = document.getElementById('reingresoFormCampos');
  var btnGuardar     = document.getElementById('btnGuardarReingreso');

  var selEstructura  = document.getElementById('reingresoEstructura');
  var inputFecha     = document.getElementById('reingresoFecha');
  var inputMotivo    = document.getElementById('reingresoMotivo');

  var resultados = [];
  var elegido = null;

  var ponerTexto = function (id, valor) {
    var elem = document.getElementById(id);
    if (elem) elem.textContent = valor || '—';
  };

  var marcarFaltaEstudiante = function (falta) {
    if (!inputBuscar) return;
    inputBuscar.classList.toggle('is-invalid', falta);
    if (feedbackBuscar) feedbackBuscar.classList.toggle('d-block', falta);
  };

  var limpiarSeleccion = function () {
    resultados = [];
    elegido = null;
    if (inputIdEst) inputIdEst.value = '';
    if (panelDatos) panelDatos.classList.add('d-none');
    if (contenedorCampos) contenedorCampos.classList.add('d-none');
    if (btnGuardar) btnGuardar.disabled = true;
    marcarFaltaEstudiante(false);

    var avisoCupos = document.getElementById('reingresoAvisoCupos');
    if (avisoCupos) avisoCupos.classList.add('d-none');

    // Restaurar opciones del select
    if (selEstructura) {
      selEstructura.value = '';
      Array.from(selEstructura.options).forEach(function (opt) {
        opt.hidden = false;
        var cupos = parseInt(opt.dataset.disponibles || '0', 10);
        opt.disabled = !opt.value || cupos <= 0;
      });
    }
  };

  var avisar = function (html) {
    if (!cajaResultados) return;
    cajaResultados.innerHTML = html;
    cajaResultados.classList.remove('d-none');
  };

  var avisoError = function (titulo, texto) {
    return '<div class="info-alert"><span class="info-alert__icon">'
      + '<i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i></span>'
      + '<div><p class="info-alert__title">' + titulo + '</p>'
      + '<p class="info-alert__text">' + SIGDEUI.escapeHtml(texto) + '</p></div></div>';
  };

  var filaResultado = function (item, indice) {
    var gradoInfo = item.ultimo_grado + ' "' + item.ultima_seccion + '" (' + item.anio_retiro + ')';
    if (item.elegible) {
      return '<button type="button" class="list-group-item list-group-item-action reingreso-resultado p-3 text-start" data-indice="' + indice + '">'
        + '<div class="d-flex justify-content-between align-items-center">'
        + '  <span class="fw-bold text-dark">' + SIGDEUI.escapeHtml(item.nombre) + '</span>'
        + '  <span class="badge bg-success-subtle text-success border border-success-subtle">Retiro Aprobado</span>'
        + '</div>'
        + '<div class="small text-secondary mt-1">' + SIGDEUI.escapeHtml(item.cedula) + ' · ' + SIGDEUI.escapeHtml(gradoInfo) + '</div>'
        + '<div class="small text-muted mt-1"><i class="bi bi-info-circle me-1"></i>Motivo de retiro previo: ' + SIGDEUI.escapeHtml(item.motivo_retiro || 'No especificado') + '</div>'
        + '</button>';
    }
    return '<div class="list-group-item reingreso-resultado p-3 text-start bg-light opacity-75">'
      + '<div class="d-flex justify-content-between align-items-center">'
      + '  <span class="fw-semibold text-muted">' + SIGDEUI.escapeHtml(item.nombre) + '</span>'
      + '  <span class="badge bg-danger-subtle text-danger border border-danger-subtle">No elegible</span>'
      + '</div>'
      + '<div class="small text-secondary mt-1">' + SIGDEUI.escapeHtml(item.cedula) + ' · ' + SIGDEUI.escapeHtml(gradoInfo) + '</div>'
      + '<div class="small text-danger mt-1"><i class="bi bi-exclamation-circle me-1"></i>' + SIGDEUI.escapeHtml(item.motivo_bloqueo) + '</div>'
      + '</div>';
  };

  var mostrarSeleccion = function (item) {
    elegido = item;
    if (inputIdEst) inputIdEst.value = item.id_estudiante;
    marcarFaltaEstudiante(false);

    var btnEditar = document.getElementById('btnEditarEstudianteReingreso');
    if (btnEditar) {
      btnEditar.dataset.estudianteId = item.id_estudiante;
    }

    ponerTexto('reingresoDatosNombre', item.nombre);
    ponerTexto('reingresoDatosCedula', item.cedula);
    ponerTexto('reingresoDatosGrado', item.ultimo_grado + ' — Sección "' + item.ultima_seccion + '" (' + item.ultimo_turno + ')');
    ponerTexto('reingresoDatosRetiro', item.anio_retiro + ' · Fecha: ' + item.fecha_retiro + ' (Motivo: ' + item.motivo_retiro + ')');

    // Regla de negocio: No permitir reingreso en grados inferiores al que cursaba antes de retirarse
    var ordinalRetiro = parseInt(item.ordinal_grado || (function () {
      var m = (item.ultimo_grado || '').match(/\d+/);
      return m ? m[0] : 0;
    })(), 10);

    if (selEstructura) {
      selEstructura.value = '';
      var opcionesValidas = 0;

      Array.from(selEstructura.options).forEach(function (opt) {
        if (!opt.value) return; // Opción por defecto
        var optOrdinal = parseInt(opt.dataset.ordinal || (function () {
          var m = (opt.textContent || '').match(/\d+/);
          return m ? m[0] : 0;
        })(), 10);
        var cupos = parseInt(opt.dataset.disponibles || '0', 10);

        // Si el grado de la sección es inferior al grado previo retirado, se oculta y deshabilita
        if (optOrdinal < ordinalRetiro) {
          opt.hidden = true;
          opt.disabled = true;
        } else {
          opt.hidden = false;
          opt.disabled = cupos <= 0;
          if (cupos > 0) opcionesValidas++;
        }
      });

      var avisoCupos = document.getElementById('reingresoAvisoCupos');
      var avisoTexto = document.getElementById('reingresoAvisoCuposTexto');
      if (avisoCupos && avisoTexto) {
        if (opcionesValidas === 0) {
          avisoTexto.textContent = 'No hay cupos disponibles en ' + (item.ultimo_grado || 'su grado') + ' ni en grados superiores.';
          avisoCupos.classList.remove('d-none');
          if (btnGuardar) btnGuardar.disabled = true;
        } else {
          avisoCupos.classList.add('d-none');
          if (btnGuardar) btnGuardar.disabled = false;
        }
      }
    }

    if (panelDatos) panelDatos.classList.remove('d-none');
    if (contenedorCampos) contenedorCampos.classList.remove('d-none');

    if (cajaResultados) {
      cajaResultados.classList.add('d-none');
      cajaResultados.innerHTML = '';
    }
  };

  var buscar = SIGDEUI.esDebounced(400, function () {
    var texto = inputBuscar.value.trim();

    if (texto.length < 3) {
      cajaResultados.classList.add('d-none');
      cajaResultados.innerHTML = '';
      return;
    }

    avisar('<div class="text-center text-support py-2"><span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Consultando registros...</div>');

    SIGDEUI.getJson(route + 'buscarEstudiante?q=' + encodeURIComponent(texto))
      .then(function (respuesta) {
        if (inputBuscar.value.trim() !== texto) return;

        if (!respuesta.ok || !respuesta.data) {
          avisar(avisoError('No se pudo buscar', (respuesta.data && respuesta.data.mensaje) || 'Intente nuevamente.'));
          return;
        }

        var datos = respuesta.data.data;
        if (datos.esperando) {
          cajaResultados.classList.add('d-none');
          cajaResultados.innerHTML = '';
          return;
        }

        resultados = datos.resultados || [];
        if (resultados.length === 0) {
          avisar('<div class="info-alert"><span class="info-alert__icon"><i class="bi bi-person-x" aria-hidden="true"></i></span>'
            + '<div><p class="info-alert__title">Sin coincidencias</p>'
            + '<p class="info-alert__text">No se encontró ningún estudiante con la cédula o nombre indicado.</p></div></div>');
          return;
        }

        var html = '<div class="list-group list-group-flush border rounded-3">';
        resultados.forEach(function (r, idx) {
          html += filaResultado(r, idx);
        });
        html += '</div>';
        avisar(html);
      })
      .catch(function () {
        avisar(avisoError('Error de conexión', 'No se pudo consultar el servidor.'));
      });
  });

  inputBuscar.addEventListener('input', function () {
    limpiarSeleccion();
    buscar();
  });

  cajaResultados.addEventListener('click', function (e) {
    var btn = e.target.closest('.reingreso-resultado[data-indice]');
    if (!btn) return;
    var idx = parseInt(btn.dataset.indice, 10);
    var item = resultados[idx];
    if (item && item.elegible) {
      mostrarSeleccion(item);
    }
  });

  // Limpiar modal al cerrarse (solo si el usuario realmente cerró Reingreso, no si fue pausa para editar)
  if (modalRegistrar) {
    modalRegistrar.addEventListener('hidden.bs.modal', function () {
      if (modalRegistrar.dataset.enEdicion === '1') {
        // Pausa temporal para editar familiares o recaudos: conservar estudiante y campos intactos
        return;
      }
      formRegistrar.reset();
      limpiarSeleccion();
      if (inputBuscar) inputBuscar.value = '';
      if (cajaResultados) {
        cajaResultados.classList.add('d-none');
        cajaResultados.innerHTML = '';
      }
    });
  }

  // Envío y formalización
  formRegistrar.addEventListener('submit', function (e) {
    e.preventDefault();

    if (!elegido || !inputIdEst.value) {
      marcarFaltaEstudiante(true);
      if (inputBuscar) inputBuscar.focus();
      return;
    }

    if (!selEstructura || !selEstructura.value) {
      selEstructura.classList.add('is-invalid');
      selEstructura.focus();
      return;
    }
    selEstructura.classList.remove('is-invalid');

    var fecha = inputFecha.value.trim();
    if (!fecha) {
      inputFecha.classList.add('is-invalid');
      inputFecha.focus();
      return;
    }
    inputFecha.classList.remove('is-invalid');

    var motivo = inputMotivo.value.trim();
    if (motivo.length < 5) {
      inputMotivo.classList.add('is-invalid');
      inputMotivo.focus();
      return;
    }
    inputMotivo.classList.remove('is-invalid');

    var seccionTexto = selEstructura.options[selEstructura.selectedIndex].text;

    SIGDEUI.confirmar({
      titulo: '¿Formalizar reincorporación escolar?',
      texto: 'Se formalizará el reingreso de ' + elegido.nombre + ' en ' + seccionTexto + '.',
      icono: 'question',
      confirmar: 'Sí, formalizar reingreso'
    }).then(function (confirmado) {
      if (!confirmado) return;

      var body = new FormData(formRegistrar);
      btnGuardar.disabled = true;
      btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Procesando...';

      SIGDEUI.enviar(route + 'guardar', { metodo: 'POST', cuerpo: body })
        .then(function (respuesta) {
          if (!respuesta.ok || !respuesta.data || !respuesta.data.ok) {
            btnGuardar.disabled = false;
            btnGuardar.innerHTML = '<i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i> Formalizar Reingreso';
            SIGDEUI.mostrarError((respuesta.data && (respuesta.data.mensaje || respuesta.data.message)) || 'No se pudo registrar la reincorporación.');
            return;
          }

          SIGDEUI.exito(respuesta.data.mensaje || 'Estudiante reincorporado exitosamente.', '¡Reingreso Formalizado!').then(function () {
            window.location.reload();
          });
        })
        .catch(function () {
          btnGuardar.disabled = false;
          btnGuardar.innerHTML = '<i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i> Formalizar Reingreso';
          SIGDEUI.mostrarError('Error de conexión con el servidor al procesar el reingreso.');
        });
    });
  });
});
