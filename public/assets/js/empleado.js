/* Comportamiento específico de la vista de Gestión de Empleados: toggle de estado, alta vía AJAX
 * (wizard de registro) y los modales Ver Detalle y Editar. Depende de Bootstrap 5, SIGDEUI y
 * wizard-ui.*/

// --- Inicialización Módulo ---
function initEmpleadoModule() {
  initToggleEstadoEmpleado();
  initVerEmpleadoModal();
  initEditarEmpleadoModal();
  initWizardRegistro();
  initFiltrosDeEscritura();
  initRepososEmpleado();
}

// Inicialización segura del DOM que evita problemas cuando los scripts
// se cargan con 'defer', 'async' o al final de la plantilla layout.
if (document.readyState === 'interactive' || document.readyState === 'complete') {
  initEmpleadoModule();
} else {
  document.addEventListener('DOMContentLoaded', initEmpleadoModule);
}

// --- Configuración Global de Rutas ---
var rawBaseUrl = window.BASE_URL || '';
var baseUrl = rawBaseUrl.endsWith('/') ? rawBaseUrl : rawBaseUrl + '/';
// Debe coincidir con la URL real del módulo (127.0.0.1:8000/empleado/...)
// y con la clave 'empleado' de $rutasPermitidas en el front controller.
// OJO: EmployeeController::$routeName está en plural ('empleados'); si en
// algún momento se unifica todo a plural, cambiar esto también.
var rutaControlador = 'empleado';

// --- Funciones Auxiliares ---
// "2024-03-15" -> "15/03/2024" (sin pasar por Date, para evitar líos de zona horaria)
function formatearFecha(iso) {
  if (!iso) return '—';
  var partes = String(iso).split('-');
  return partes[2] && partes[1] && partes[0] ? partes[2] + '/' + partes[1] + '/' + partes[0] : String(iso);
}

function escapeHtml(texto) {
  if (texto === null || texto === undefined) return '';
  var div = document.createElement('div');
  div.textContent = String(texto);
  return div.innerHTML;
}

function fechaHoyISO() {
  var hoy = new Date();
  var anio = hoy.getFullYear();
  var mes = ('0' + (hoy.getMonth() + 1)).slice(-2);
  var dia = ('0' + hoy.getDate()).slice(-2);
  return anio + '-' + mes + '-' + dia;
}

function actualizarBadgeReposoEnTabla(idEmpleado, enReposo) {
  var tr = document.querySelector('tr[data-id="' + idEmpleado + '"]') ||
           (document.querySelector('.ver-empleado[data-empleado-id="' + idEmpleado + '"]') &&
            document.querySelector('.ver-empleado[data-empleado-id="' + idEmpleado + '"]').closest('tr'));
  if (tr) {
    var b = tr.querySelector('[data-badge-reposo]');
    if (b) {
      b.hidden = !enReposo;
    }
  }
}

// Pide un empleado a empleado/show. Devuelve data.data o null si falló
// (ya mostró el error con la misma alerta que usan los demás módulos).
function cargarEmpleado(id) {
  return SIGDEUI.enviar(baseUrl + rutaControlador + '/show?id=' + encodeURIComponent(id))
    .then(function (r) {
      if (!r.ok || !r.data.ok) {
        SIGDEUI.mostrarError((r.data && r.data.mensaje) || 'No se pudo cargar el empleado.');
        return null;
      }
      return r.data.data;
    })
    .catch(function () {
      SIGDEUI.mostrarError('Fallo de conexión al cargar el empleado.');
      return null;
    });
}

/* ---------- Cascada estado -> municipio -> parroquia ----------
 La lógica vive en SIGDEWizardUI.initCascadaUbicacion(), compartida con el
 wizard de inscripciones: mismo endpoint, mismos textos y mismo
 comportamiento. Acá solo se le pasa la URL de este módulo y se conserva el
 handle, que usan el modal de edición y el wizard de alta.*/
function iniciarCascadaUbicacion() {
  return SIGDEWizardUI.initCascadaUbicacion({
    url: baseUrl + rutaControlador + '/getUbicacion',
    estadoId: 'direccionEstado',
    municipioId: 'direccionMunicipio',
    parroquiaId: 'direccionParroquia'
  });
}



// --- Filtros de escritura (componente phone-input.php y campos de nombre) ---
// La implementación vive en public/assets/js/wizard-ui.js y la comparten el
// wizard de empleado, el de estudiante y el sub-modal de familiar:
// pattern="[0-9]{7}" y maxlength (de phone-input.php) ya invalidan el campo al
// enviar; estos filtros además evitan que la letra llegue a escribirse.
function initFiltrosDeEscritura() {
  SIGDEWizardUI.initSoloDigitos();
  SIGDEWizardUI.initSoloLetras();
}

// --- Cambiar Estado (Activo / Inactivo) con Modal y Motivo ---
function initToggleEstadoEmpleado() {
  var modalEl = document.getElementById('estadoEmpleadoModal');
  var formEl = document.getElementById('formEstadoEmpleado');
  var btnGuardar = document.getElementById('btnGuardarEstadoEmpleado');
  if (!modalEl || !formEl || !btnGuardar) return;

  var switchPendiente = null;
  var guardado = false;

  function hoyISO() {
    var hoy = new Date();
    var anio = hoy.getFullYear();
    var mes = ('0' + (hoy.getMonth() + 1)).slice(-2);
    var dia = ('0' + hoy.getDate()).slice(-2);
    return anio + '-' + mes + '-' + dia;
  }

  function nombreEstado(estado) {
    return (estado || '').toUpperCase() === 'ACTIVO' ? 'Activo' : 'Inactivo';
  }

  function limpiarErrores() {
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
    var url = baseUrl + rutaControlador + '/estadoInfo'
      + '?id=' + encodeURIComponent(id)
      + '&nuevo_estado=' + encodeURIComponent(destino)
      + '&fecha_efectiva=' + encodeURIComponent(fecha);

    SIGDEUI.enviar(url)
      .then(function (res) {
        if (!res.ok || !res.data || !res.data.ok) return;
        pintarImpacto(res.data.data);
      })
      .catch(function () {});
  }

  function abrirModal(datos) {
    limpiarErrores();

    var id = String(datos.id || '');
    var estados = {
      actual: datos.estado === 'ACTIVO' ? 'ACTIVO' : 'INACTIVO',
      destino: datos.destino === 'ACTIVO' ? 'ACTIVO' : 'INACTIVO'
    };

    var inpId = document.getElementById('estadoEmpleadoId');
    if (inpId) inpId.value = id;
    var elNom = document.getElementById('estadoEmpleadoNombre');
    if (elNom) elNom.textContent = datos.nombre || '—';
    var elAct = document.getElementById('estadoEmpleadoActual');
    if (elAct) elAct.textContent = nombreEstado(estados.actual);
    var elDes = document.getElementById('estadoEmpleadoDestino');
    if (elDes) elDes.textContent = nombreEstado(estados.destino);
    var elNue = document.getElementById('estadoEmpleadoNuevo');
    if (elNue) elNue.value = estados.destino;
    var elConf = document.getElementById('estadoEmpleadoConfirmar');
    if (elConf) elConf.value = '1';

    var fecha = document.getElementById('estadoFechaEfectiva');
    if (fecha) {
      fecha.max = hoyISO();
      fecha.value = hoyISO();
    }
    var mot = document.getElementById('estadoMotivo');
    if (mot) mot.value = '';

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
    if (fecha) {
      consultarImpacto(id, estados.destino, fecha.value);
    }
  }

  function revertirSwitch() {
    if (!switchPendiente) return;
    switchPendiente.checked = (switchPendiente.dataset.empleadoEstado || '').toUpperCase() === 'ACTIVO';
    switchPendiente = null;
  }

  async function enviarCambio() {
    limpiarErrores();

    var id = document.getElementById('estadoEmpleadoId') ? document.getElementById('estadoEmpleadoId').value : '';
    var motivo = document.getElementById('estadoMotivo') ? (document.getElementById('estadoMotivo').value || '').trim() : '';
    var fecha = document.getElementById('estadoFechaEfectiva') ? (document.getElementById('estadoFechaEfectiva').value || '') : '';

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

    try {
      var res = await SIGDEUI.enviar(baseUrl + rutaControlador + '/cambiarEstado', {
        metodo: 'POST',
        cuerpo: new FormData(formEl)
      });
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
    } catch (err) {
      SIGDEUI.mostrarError('Fallo de conexión al cambiar el estado.');
    } finally {
      btnGuardar.disabled = false;
      btnGuardar.innerHTML = htmlOriginal;
    }
  }

  document.addEventListener('change', function (e) {
    var checkbox = e.target.closest('.toggle-estado');
    if (!checkbox) return;

    var estadoActual = (checkbox.dataset.empleadoEstado || '').toUpperCase() === 'ACTIVO' ? 'ACTIVO' : 'INACTIVO';
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

  modalEl.addEventListener('hidden.bs.modal', function () {
    if (!guardado) revertirSwitch();
    switchPendiente = null;
  });

  formEl.addEventListener('submit', function (e) {
    e.preventDefault();
    enviarCambio();
  });

  btnGuardar.addEventListener('click', function (e) {
    e.preventDefault();
    enviarCambio();
  });
}

// --- Modal Ver Detalle ---
function initVerEmpleadoModal() {
  var modalVerEl = document.getElementById('verEmpleadoModal');
  if (!modalVerEl) return;

  // Delegación en document: los botones de la tabla pueden ser
  // reemplazados por main.js al filtrar o paginar, y así siguen funcionando.
  document.addEventListener('click', function (e) {
    var btnVer = e.target.closest('.ver-empleado');
    if (!btnVer) return;

    cargarEmpleado(btnVer.dataset.empleadoId).then(function (d) {
      if (!d) return;
      pintarDetalleEmpleado(modalVerEl, d);
      SIGDEUI.mostrarModal(modalVerEl);
    });
  });
}

function pintarDetalleEmpleado(modalEl, d) {
  var activo = d.estado === 'ACTIVO';
  var textoTelefono = function (codigo, numero) {
    return [codigo, numero].filter(Boolean).join('-');
  };
  // Dirección como texto continuo y ordenado: calle/avenida, casa nro,
  // sector, parroquia, municipio, estado y, si existe, punto de referencia.
  // Cada segmento solo entra si tiene valor, para que no queden huecos.
  var t = [];
  if (d.calle_avenida)        t.push(d.calle_avenida);
  if (d.nro_casa_apto)        t.push('Casa nro ' + d.nro_casa_apto);
  if (d.sector_urbanizacion)  t.push('Sector ' + d.sector_urbanizacion);
  if (d.parroquia_nombre)     t.push('Parroquia ' + d.parroquia_nombre);
  if (d.municipio_nombre)     t.push('Municipio ' + d.municipio_nombre);
  if (d.estado_nombre)        t.push('Estado ' + d.estado_nombre);
  var direccionCompleta = t.join(', ');
  if (d.punto_referencia) {
    direccionCompleta += (direccionCompleta ? ', ' : '') + 'Punto de referencia ' + d.punto_referencia;
  }

  var valores = {
    nombre_completo:  d.nombre_completo,
    cedula:           d.tipo_documento + '-' + d.numero_documento,
    /* Vacía y no un texto de relleno: la ficha ya pone "—" cuando no hay valor, y
       escribir "Sin nacionalidad" en cada ficha vieja sería ruido. */
    nacionalidad:     d.nacionalidad || '',
    cargo:            d.cargo || 'Sin cargo',
    turno:            d.turno || 'Sin turno',
    estado:           activo ? 'Activo' : 'Inactivo',
    sexo:             { F: 'Femenino', M: 'Masculino' }[d.sexo] || d.sexo,
    fecha_nacimiento: formatearFecha(d.fecha_nacimiento),
    fecha_ingreso:    formatearFecha(d.fecha_ingreso),
    fecha_egreso:     formatearFecha(d.fecha_egreso),
    telefono:         d.telefono || '',
    telefono_alternativo: textoTelefono(d.codigo_telefono_alternativo, d.numero_telefono_alternativo),
    correo:           d.correo_electronico || '',
    formacion:        d.formacion || '',
    direccion_completa: direccionCompleta,
  };

  modalEl.querySelectorAll('[data-campo]').forEach(function (el) {
    el.textContent = valores[el.dataset.campo] || '—';
  });

  // El badge de estado y la barra inferior comparten data-campo="estado";
  // solo el badge lleva el modificador de color según el valor.
  modalEl.querySelectorAll('[data-campo="estado"]').forEach(function (el) {
    if (!el.classList.contains('status-badge')) return;
    el.classList.remove('status-badge--active', 'status-badge--inactive');
    el.classList.add(activo ? 'status-badge--active' : 'status-badge--inactive');
  });

  var iniciales = modalEl.querySelector('[data-campo-iniciales]');
  var contenedorAvatar = modalEl.querySelector('.user-detail__header .avatar');
  // El avatar ya viene decidido por AvatarTrait en el backend: foto o
  // iniciales + color. Acá solo se refleja.
  var avatarTipo = d.avatar_tipo || (d.foto ? 'foto' : 'iniciales');

  if (iniciales) {
    iniciales.textContent = d.iniciales || 'EE';
    iniciales.hidden = avatarTipo === 'foto';
  }
  if (contenedorAvatar && d.avatar_color) {
    contenedorAvatar.classList.remove('avatar--primary', 'avatar--success', 'avatar--dark');
    contenedorAvatar.classList.add('avatar--' + d.avatar_color);
  }

  var foto = modalEl.querySelector('[data-campo-foto]');
  if (foto) {
    if (avatarTipo === 'foto' && d.avatar_foto) {
      // La foto se guarda como ruta absoluta del sitio (/uploads/...).
      foto.src = baseUrl + d.avatar_foto.replace(/^\//, '');
      foto.hidden = false;
    } else {
      foto.hidden = true;
    }
  }

  // Reposo médico (badge en header, botón de registrar y carga de historial)
  var badgeReposo = modalEl.querySelector('#detalleBadgeReposo');
  if (badgeReposo) {
    badgeReposo.hidden = !d.en_reposo;
  }

  var btnNuevoReposo = modalEl.querySelector('#btnNuevoReposoDesdeDetalle');
  if (btnNuevoReposo) {
    btnNuevoReposo.disabled = !activo;
    btnNuevoReposo.title = activo ? 'Registrar reposo' : 'Solo se pueden registrar reposos para empleados activos';
    btnNuevoReposo.dataset.empleadoId = d.id_empleado;
    btnNuevoReposo.dataset.empleadoNombre = d.nombre_completo;
    btnNuevoReposo.dataset.empleadoActivo = activo ? '1' : '0';
  }

  cargarRepososEmpleado(d.id_empleado, d.nombre_completo, activo);
}

// --- Reposos y Permisos Médicos del Personal ---

function cargarRepososEmpleado(idEmpleado, nombreCompleto, activo) {
  var contenedorVigente = document.getElementById('reposoVigenteCard');
  var periodoVigente = document.getElementById('reposoVigentePeriodo');
  var motivoVigente = document.getElementById('reposoVigenteMotivo');
  var btnCerrarVigente = document.getElementById('btnCerrarReposoVigente');
  var btnEditarVigente = document.getElementById('btnEditarReposoVigente');
  var historialTbody = document.getElementById('historialRepososBody');
  var detalleBadgeReposo = document.getElementById('detalleBadgeReposo');

  if (!historialTbody) return;

  historialTbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-2"><span class="spinner-border spinner-border-sm me-1" role="status"></span> Cargando reposos...</td></tr>';
  if (contenedorVigente) contenedorVigente.hidden = true;

  SIGDEUI.enviar(baseUrl + rutaControlador + '/getReposos?id_empleado=' + encodeURIComponent(idEmpleado))
    .then(function (res) {
      if (!res.ok || !res.data || !res.data.ok) {
        var msg = (res.data && res.data.mensaje) || 'No se pudo cargar el historial de reposos.';
        historialTbody.innerHTML = '<tr><td colspan="4" class="text-danger text-center py-2">' + escapeHtml(msg) + '</td></tr>';
        return;
      }

      var respuestaServidor = res.data.data || {};
      var reposos = respuestaServidor.reposos || [];
      var vigente = respuestaServidor.vigente || null;

      if (detalleBadgeReposo) {
        detalleBadgeReposo.hidden = !vigente;
      }
      actualizarBadgeReposoEnTabla(idEmpleado, !!vigente);

      if (vigente && contenedorVigente) {
        var textoPeriodo = '';
        if (vigente.fecha_fin) {
          textoPeriodo = formatearFecha(vigente.fecha_inicio) + ' al ' + formatearFecha(vigente.fecha_fin);
        } else {
          textoPeriodo = 'Abierto desde ' + formatearFecha(vigente.fecha_inicio);
        }
        if (periodoVigente) periodoVigente.textContent = textoPeriodo;
        if (motivoVigente) motivoVigente.textContent = vigente.motivo || 'Sin motivo especificado';

        if (btnCerrarVigente) {
          btnCerrarVigente.onclick = function () {
            cerrarReposoRapido(vigente, idEmpleado, nombreCompleto, activo);
          };
        }
        if (btnEditarVigente) {
          btnEditarVigente.onclick = function () {
            abrirModalEditarReposo(vigente, idEmpleado, nombreCompleto, activo);
          };
        }
        contenedorVigente.hidden = false;
      } else if (contenedorVigente) {
        contenedorVigente.hidden = true;
      }

      if (reposos.length === 0) {
        historialTbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">No hay reposos ni licencias registradas para este empleado.</td></tr>';
        return;
      }

      var html = '';
      reposos.forEach(function (r) {
        var periodoStr = '';
        if (r.fecha_fin) {
          periodoStr = formatearFecha(r.fecha_inicio) + ' — ' + formatearFecha(r.fecha_fin);
        } else {
          periodoStr = '<span class="text-primary fw-medium"><i class="bi bi-clock-history me-1"></i>Abierto desde ' + formatearFecha(r.fecha_inicio) + '</span>';
        }

        var badgeEstado = '';
        if (r.vigente) {
          badgeEstado = '<span class="status-badge status-badge--pending">Vigente</span>';
        } else {
          badgeEstado = '<span class="status-badge status-badge--inactive">Finalizado</span>';
        }

        var motivoStr = r.motivo ? escapeHtml(r.motivo) : '<span class="text-muted fst-italic">Sin motivo</span>';

        var botonesAccion = '<div class="d-flex justify-content-end gap-1">';
        if (!r.fecha_fin || r.vigente) {
          botonesAccion += '<button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 btn-cerrar-fila" title="Cerrar reposo" data-id="' + r.id_reposo + '"><i class="bi bi-check2-circle"></i> Cerrar</button>';
        }
        botonesAccion += '<button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 btn-editar-fila" title="Editar reposo" data-id="' + r.id_reposo + '"><i class="bi bi-pencil"></i></button>';
        botonesAccion += '</div>';

        html += '<tr>' +
          '<td>' + periodoStr + '</td>' +
          '<td>' + motivoStr + '</td>' +
          '<td>' + badgeEstado + '</td>' +
          '<td class="text-end">' + botonesAccion + '</td>' +
          '</tr>';
      });

      historialTbody.innerHTML = html;

      historialTbody.querySelectorAll('.btn-cerrar-fila').forEach(function (btn) {
        var rId = parseInt(btn.dataset.id, 10);
        var reposoObj = reposos.find(function (item) { return item.id_reposo === rId; });
        if (reposoObj) {
          btn.addEventListener('click', function () {
            cerrarReposoRapido(reposoObj, idEmpleado, nombreCompleto, activo);
          });
        }
      });

      historialTbody.querySelectorAll('.btn-editar-fila').forEach(function (btn) {
        var rId = parseInt(btn.dataset.id, 10);
        var reposoObj = reposos.find(function (item) { return item.id_reposo === rId; });
        if (reposoObj) {
          btn.addEventListener('click', function () {
            abrirModalEditarReposo(reposoObj, idEmpleado, nombreCompleto, activo);
          });
        }
      });
    })
    .catch(function (err) {
      console.error('Error al cargar reposos:', err);
      historialTbody.innerHTML = '<tr><td colspan="4" class="text-center text-danger py-2">Fallo de conexión al cargar los reposos.</td></tr>';
    });
}

async function cerrarReposoRapido(reposo, idEmpleado, nombreCompleto, activo) {
  var fechaFinCierre = fechaHoyISO();
  if (reposo.fecha_inicio && fechaFinCierre < reposo.fecha_inicio) {
    fechaFinCierre = reposo.fecha_inicio;
  }

  var confirmacion = await SIGDEUI.confirmar({
    titulo: '¿Cerrar reposo médico?',
    texto: 'Se registrará como fecha de culminación el ' + formatearFecha(fechaFinCierre) + '. ¿Desea continuar?',
    confirmar: 'Sí, cerrar reposo',
    cancelar: 'Cancelar',
    icono: 'question'
  });

  if (!confirmacion) return;

  var formData = new FormData();
  formData.append('id_reposo', reposo.id_reposo);
  formData.append('id_empleado', idEmpleado);
  formData.append('fecha_inicio', reposo.fecha_inicio);
  formData.append('fecha_fin', fechaFinCierre);
  formData.append('motivo', reposo.motivo || '');

  try {
    var res = await SIGDEUI.enviar(baseUrl + rutaControlador + '/updateReposo', {
      metodo: 'POST',
      cuerpo: formData
    });
    var data = res.data;

    if (!res.ok || !data.ok) {
      SIGDEUI.mostrarError((data && data.mensaje) || 'No se pudo cerrar el reposo.', 'Error al cerrar reposo');
      return;
    }

    SIGDEUI.exito(data.mensaje || 'Reposo cerrado correctamente.', '¡Listo!');
    cargarRepososEmpleado(idEmpleado, nombreCompleto, activo);
  } catch (err) {
    console.error('Error al cerrar reposo:', err);
    SIGDEUI.mostrarError('Fallo de conexión al cerrar el reposo.');
  }
}

function abrirModalEditarReposo(reposo, idEmpleado, nombreCompleto, activo) {
  var modalEl = document.getElementById('reposoEmpleadoModal');
  if (!modalEl) return;

  limpiarErroresFormReposo();

  var inputId = document.getElementById('reposoId');
  var inputEmpId = document.getElementById('reposoEmpleadoId');
  var inputEmpNombre = document.getElementById('reposoEmpleadoNombre');
  var inputInicio = document.getElementById('reposoFechaInicio');
  var inputFin = document.getElementById('reposoFechaFin');
  var inputMotivo = document.getElementById('reposoMotivo');
  var labelModal = document.getElementById('reposoEmpleadoModalLabel');

  if (inputId) inputId.value = reposo.id_reposo;
  if (inputEmpId) inputEmpId.value = idEmpleado;
  if (inputEmpNombre) inputEmpNombre.value = nombreCompleto;
  if (inputInicio) inputInicio.value = reposo.fecha_inicio || '';
  if (inputFin) inputFin.value = reposo.fecha_fin || '';
  if (inputMotivo) inputMotivo.value = reposo.motivo || '';
  if (labelModal) labelModal.textContent = 'Editar Reposo Médico';

  modalEl.dataset.empleadoActivo = activo ? '1' : '0';

  SIGDEUI.mostrarModal(modalEl);
}

function abrirModalNuevoReposo(idEmpleado, nombreCompleto, activo) {
  if (!activo) {
    SIGDEUI.mostrarAviso('warning', 'Empleado inactivo', 'Solo se pueden registrar reposos para empleados activos.');
    return;
  }

  var modalEl = document.getElementById('reposoEmpleadoModal');
  if (!modalEl) return;

  limpiarErroresFormReposo();

  var inputId = document.getElementById('reposoId');
  var inputEmpId = document.getElementById('reposoEmpleadoId');
  var inputEmpNombre = document.getElementById('reposoEmpleadoNombre');
  var inputInicio = document.getElementById('reposoFechaInicio');
  var inputFin = document.getElementById('reposoFechaFin');
  var inputMotivo = document.getElementById('reposoMotivo');
  var labelModal = document.getElementById('reposoEmpleadoModalLabel');

  if (inputId) inputId.value = '';
  if (inputEmpId) inputEmpId.value = idEmpleado;
  if (inputEmpNombre) inputEmpNombre.value = nombreCompleto;
  if (inputInicio) inputInicio.value = fechaHoyISO();
  if (inputFin) inputFin.value = '';
  if (inputMotivo) inputMotivo.value = '';
  if (labelModal) labelModal.textContent = 'Registrar Reposo Médico';

  modalEl.dataset.empleadoActivo = '1';

  SIGDEUI.mostrarModal(modalEl);
}

function limpiarErroresFormReposo() {
  var form = document.getElementById('formReposoEmpleado');
  if (!form) return;
  form.querySelectorAll('.is-invalid').forEach(function (el) {
    el.classList.remove('is-invalid');
  });
  var errInicio = document.getElementById('errorReposoFechaInicio');
  if (errInicio) errInicio.textContent = 'Indica la fecha de inicio.';
  var errFin = document.getElementById('errorReposoFechaFin');
  if (errFin) errFin.textContent = '';
  var errMotivo = document.getElementById('errorReposoMotivo');
  if (errMotivo) errMotivo.textContent = '';
}

function initRepososEmpleado() {
  var btnNuevo = document.getElementById('btnNuevoReposoDesdeDetalle');
  var modalReposoEl = document.getElementById('reposoEmpleadoModal');
  var formReposo = document.getElementById('formReposoEmpleado');
  var btnGuardar = document.getElementById('btnGuardarReposo');

  if (btnNuevo) {
    btnNuevo.addEventListener('click', function () {
      var idEmp = btnNuevo.dataset.empleadoId;
      var nombreEmp = btnNuevo.dataset.empleadoNombre;
      var activo = btnNuevo.dataset.empleadoActivo === '1';
      abrirModalNuevoReposo(idEmp, nombreEmp, activo);
    });
  }

  // Al ocultar el modal de reposo, si el modal de ver detalle sigue abierto, restaurar modal-open en el body
  if (modalReposoEl) {
    modalReposoEl.addEventListener('hidden.bs.modal', function () {
      var modalVerEl = document.getElementById('verEmpleadoModal');
      if (modalVerEl && modalVerEl.classList.contains('show')) {
        document.body.classList.add('modal-open');
      }
    });
  }

  if (btnGuardar && formReposo) {
    btnGuardar.addEventListener('click', async function () {
      limpiarErroresFormReposo();

      var inputId = document.getElementById('reposoId');
      var inputEmpId = document.getElementById('reposoEmpleadoId');
      var inputInicio = document.getElementById('reposoFechaInicio');
      var inputFin = document.getElementById('reposoFechaFin');
      var inputMotivo = document.getElementById('reposoMotivo');

      var idReposo = (inputId && inputId.value || '').trim();
      var idEmpleado = (inputEmpId && inputEmpId.value || '').trim();
      var fechaInicio = (inputInicio && inputInicio.value || '').trim();
      var fechaFin = (inputFin && inputFin.value || '').trim();
      var motivo = (inputMotivo && inputMotivo.value || '').trim();
      var nombreEmpleado = (document.getElementById('reposoEmpleadoNombre') && document.getElementById('reposoEmpleadoNombre').value) || '';
      var activo = modalReposoEl && modalReposoEl.dataset.empleadoActivo === '1';

      var hayError = false;

      if (!fechaInicio) {
        if (inputInicio) inputInicio.classList.add('is-invalid');
        var errIni = document.getElementById('errorReposoFechaInicio');
        if (errIni) errIni.textContent = 'Indica la fecha de inicio del reposo.';
        hayError = true;
      }

      if (fechaFin && fechaInicio && fechaFin < fechaInicio) {
        if (inputFin) inputFin.classList.add('is-invalid');
        var errF = document.getElementById('errorReposoFechaFin');
        if (errF) errF.textContent = 'La fecha de fin no puede ser anterior a la de inicio.';
        hayError = true;
      }

      if (motivo.length > 200) {
        if (inputMotivo) inputMotivo.classList.add('is-invalid');
        var errM = document.getElementById('errorReposoMotivo');
        if (errM) errM.textContent = 'El motivo no puede pasar de 200 caracteres.';
        hayError = true;
      }

      if (hayError) return;

      var esEdicion = idReposo !== '' && parseInt(idReposo, 10) > 0;
      var endpoint = esEdicion
        ? baseUrl + rutaControlador + '/updateReposo'
        : baseUrl + rutaControlador + '/registrarReposo';

      var formData = new FormData(formReposo);

      var htmlOriginal = btnGuardar.innerHTML;
      btnGuardar.disabled = true;
      btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Guardando...';

      try {
        var res = await SIGDEUI.enviar(endpoint, {
          metodo: 'POST',
          cuerpo: formData
        });
        var data = res.data;

        if (!res.ok || !data.ok) {
          if (data && data.data && typeof data.data === 'object') {
            if (data.data.fecha_inicio && inputInicio) {
              inputInicio.classList.add('is-invalid');
              var errIni2 = document.getElementById('errorReposoFechaInicio');
              if (errIni2) errIni2.textContent = data.data.fecha_inicio;
            }
            if (data.data.fecha_fin && inputFin) {
              inputFin.classList.add('is-invalid');
              var errFin2 = document.getElementById('errorReposoFechaFin');
              if (errFin2) errFin2.textContent = data.data.fecha_fin;
            }
            if (data.data.motivo && inputMotivo) {
              inputMotivo.classList.add('is-invalid');
              var errMot2 = document.getElementById('errorReposoMotivo');
              if (errMot2) errMot2.textContent = data.data.motivo;
            }
          }
          SIGDEUI.mostrarError((data && data.mensaje) || 'No se pudo guardar el reposo.', 'Error');
          return;
        }

        SIGDEUI.ocultarModal(modalReposoEl);
        SIGDEUI.exito(data.mensaje || 'Reposo guardado correctamente.', '¡Listo!');

        cargarRepososEmpleado(idEmpleado, nombreEmpleado, activo);
      } catch (err) {
        console.error('Error al guardar reposo:', err);
        SIGDEUI.mostrarError('Fallo de conexión al guardar el reposo.');
      } finally {
        btnGuardar.disabled = false;
        btnGuardar.innerHTML = htmlOriginal;
      }
    });
  }
}

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
      editFotoPreview.innerHTML = '<img src="' + baseUrl + ruta.replace(/^\//, '') + '" alt="Foto de perfil">';
      btnQuitarFoto.hidden = false;
    } else {
      editFotoPreview.innerHTML = '<i class="bi bi-person"></i>';
      btnQuitarFoto.hidden = true;
    }
  }

  SIGDEUI.alCerrarModal(modalEditarEl, function liberarFotoTemporal() {
    if (fotoEdicion) fotoEdicion.vaciar(false);
  });

  // --- Dirección: cascada estado->municipio->parroquia del modal de edición ---
  // La misma de SIGDEWizardUI.initCascadaUbicacion() que usa el wizard de alta
  // y el de inscripciones, pero sobre los selects del formulario de edición.
  var cascadaEdicion = SIGDEWizardUI.initCascadaUbicacion({
    url: baseUrl + rutaControlador + '/getUbicacion',
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
      if (primera.fecha_obtencion) partes.push('obtenido el ' + formatearFecha(primera.fecha_obtencion));
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

    cargarEmpleado(btnEditar.dataset.empleadoId).then(function (d) {
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
      var res = await SIGDEUI.enviar(baseUrl + rutaControlador + '/update', { metodo: 'POST', cuerpo: formData });
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

// --- Wizard "Registrar Nuevo Empleado" ---
// Navegación de pasos, cascada de ubicación, foto, títulos académicos y
// resumen. El <form> finalmente se envía a empleado/store con el mismo
// patrón de submit (spinner + fetch + error con detalles) que los demás.
function initWizardRegistro() {
  var formNuevo = document.getElementById('formNuevoEmpleado');
  if (!formNuevo) return;

  var modalNuevoEl = document.getElementById('nuevoEmpleadoModal');
  var paneles  = Array.from(formNuevo.querySelectorAll('.wizard-panel[data-step]'));
  var pasosNav = Array.from(document.querySelectorAll('#wizardSteps [data-step-indicator]'));
  var btnAtras     = document.getElementById('btnWizardAtras');
  var btnSiguiente = document.getElementById('btnWizardSiguiente');
  var btnFinalizar = document.getElementById('btnWizardFinalizar');
  var htmlFinalizarOriginal = btnFinalizar ? btnFinalizar.innerHTML : '';
  var fotoInput   = document.getElementById('fotoInput');
  var fotoPreview = document.getElementById('fotoPreview');
  var totalPasos  = paneles.length;

  var TITULOS   = []; // títulos agregados en el paso 5 (serializados en #titulosJson)
  var pasoActual = 1;
  var edicionDesdeResumen = false; // "Editar" en el paso 6: al pulsar Siguiente se vuelve directo al resumen

  if (totalPasos <= 0) return;

  /* Normaliza los nombres al salir del campo. El servidor igual los normaliza al guardar, pero si
 solo lo hiciera al guardar, la persona vería en el campo lo que escribió y en la confirmación
 tampoco notaría el cambio.*/
  ['primer_nombre', 'segundo_nombre', 'primer_apellido', 'segundo_apellido']
    .forEach(function (nombreCampo) {
      var campo = formNuevo.querySelector('[name="' + nombreCampo + '"]');
      if (!campo) return;
      campo.addEventListener('blur', function () {
        var limpio = SIGDEUI.normalizarNombre(campo.value);
        if (campo.value !== limpio) campo.value = limpio;
      });
    });

  function panelDe(n) {
    return formNuevo.querySelector('.wizard-panel[data-step="' + n + '"]');
  }

  // .wizard-panel es un <div> y los divs no exponen checkValidity():
  // valido manualmente sus controles. Los radios se evalúan por grupo
  // (basta uno marcado) y los controles disabled se ignoran solos.
  function panelCompleto(panel) {
    if (!panel) return true;

    var gruposRadio = new Set();
    var valido = true;

    panel.querySelectorAll('input, select, textarea').forEach(function (el) {
      if (el.type === 'radio') {
        gruposRadio.add(el.name);
        return;
      }
      if (!el.checkValidity()) valido = false;
    });

    gruposRadio.forEach(function (nombre) {
      if (!panel.querySelector('input[name="' + CSS.escape(nombre) + '"]:checked')) {
        valido = false;
      }
    });

    return valido;
  }

  function validarPaso(n) {
    return panelCompleto(panelDe(n));
  }

  // ---------- Validación extra: teléfono alternativo requiere prefijo ----------
  // codigo/numero_telefono_alternativo son opcionales en el <select>/<input>
  // (sin atributo required), así que checkValidity() y el ícono nativo
  // :valid/:invalid del componente phone-input.php (ver su comentario: no
  // usa .is-valid de Bootstrap, se pinta con CSS atado al pseudo-estado
  // HTML5) dan por bueno el select aunque quede en "--". Por eso hay que
  // forzar ese estado con setCustomValidity(): si el usuario escribió el
  // número pero dejó el prefijo en "--", el select pasa a :invalid de
  // verdad (checkValidity() lo detecta y el ícono también cambia). Mismo
  // criterio que aplica ContactoValidacionTrait::validarContacto() en el
  // backend.
  function validarTelefonoAlternativo(panel) {
    if (!panel) return true;

    var codAlt = panel.querySelector('#codigo_telefono_alternativo');
    var tlfAlt = panel.querySelector('#numero_telefono_alternativo');
    if (!codAlt || !tlfAlt) return true;

    var numero  = tlfAlt.value.trim();
    var prefijo = codAlt.value.trim();

    if (numero !== '' && prefijo === '') {
      codAlt.setCustomValidity('Selecciona un prefijo para el teléfono alternativo.');
    } else {
      codAlt.setCustomValidity('');
    }

    // pattern="[0-9]{7}" ya invalida tlfAlt nativamente si el número está
    // incompleto o trae letras; no hace falta duplicar esa comprobación,
    // basta con leerla junto al estado forzado del select.
    return codAlt.checkValidity() && tlfAlt.checkValidity();
  }

  // Pasos que necesitan una validación extra además de panelCompleto().
  // La clave es el número de paso tal como aparece en data-step
  // (2 = Contacto, según #wizardSteps: Personales, Contacto, Dirección...).
  var VALIDACIONES_EXTRA_PASO = {
    2: validarTelefonoAlternativo
  };

  // Las funciones de este mapa solo actualizan el customValidity de sus
  // campos (setCustomValidity no se revierte solo cuando el usuario corrige
  // el campo); panelCompleto() es quien realmente decide el resultado leyendo
  // checkValidity() después. Por eso 'extra' se ejecuta SIEMPRE primero, para
  // refrescar ese estado, y nunca queda condicionado al resultado de
  // validarPaso() como pasaba antes con el && (si el campo ya estaba
  // marcado inválido por un intento previo, el customValidity viejo nunca
  // se limpiaba y el paso quedaba atascado aunque el usuario ya hubiera
  // corregido el prefijo).
  function validarPasoCompleto(n) {
    var panel = panelDe(n);
    var extra = VALIDACIONES_EXTRA_PASO[n];
    if (extra) extra(panel);
    return validarPaso(n);
  }

  function pintarIndicadores(n) {
    pasosNav.forEach(function (paso) {
      var numero = Number(paso.dataset.stepIndicator);
      paso.classList.toggle('is-active', numero === n);
      paso.classList.toggle('is-completed', numero < n);
      var circulo = paso.querySelector('.wizard-steps__circle');
      if (circulo) {
        circulo.innerHTML = numero < n
          ? '<i class="bi bi-check" aria-hidden="true"></i>'
          : String(numero);
      }
    });
  }

  function pintarBotones(n) {
    if (btnAtras)     btnAtras.classList.toggle('d-none', n === 1);
    if (btnSiguiente) btnSiguiente.classList.toggle('d-none', n === totalPasos);
    if (btnFinalizar) btnFinalizar.classList.toggle('d-none', n !== totalPasos);
  }

  function activarPaso() {
    paneles.forEach(function (p) {
      p.classList.toggle('is-active', Number(p.dataset.step) === pasoActual);
    });
    pintarIndicadores(pasoActual);
    pintarBotones(pasoActual);
  }

  function avanzar() {
    if (!validarPasoCompleto(pasoActual)) {
      panelDe(pasoActual).classList.add('was-validated');
      SIGDEUI.mostrarAviso('warning', 'Campos incompletos', 'Completa los campos obligatorios del paso antes de continuar.');
      return;
    }
    if (pasoActual < totalPasos) {
      // Si venimos de un "Editar" del resumen, "Siguiente" regresa siempre
      // al último paso en vez de avanzar de a uno.
      if (edicionDesdeResumen) {
        pasoActual = totalPasos;
        edicionDesdeResumen = false;
        pintarResumen();
        activarPaso();
        return;
      }
      pasoActual += 1;
      if (pasoActual === totalPasos) pintarResumen();
      activarPaso();
    }
  }

  function retroceder() {
    edicionDesdeResumen = false; // ir hacia atrás cancela el retorno directo al resumen
    if (pasoActual > 1) {
      pasoActual -= 1;
      activarPaso();
    }
  }

  // Botones de navegación del footer del wizard.
  if (btnSiguiente) btnSiguiente.addEventListener('click', avanzar);
  if (btnAtras)     btnAtras.addEventListener('click', retroceder);

  // Los botones "Editar" del resumen saltan al paso correspondiente.
  document.addEventListener('click', function (evento) {
    var boton = evento.target.closest('[data-editar-paso]');
    if (!boton) return;
    pasoActual = Number(boton.dataset.editarPaso) || 1;
    edicionDesdeResumen = true; // al volver con "Siguiente" se regresa al resumen (paso final)
    activarPaso();
  });

  // ---------- Foto de perfil (paso 1) ----------
  // Previsualización y "Quitar foto" vía wizard-ui.js (misma implementación
  // que el wizard de estudiante). En registro no hay foto previa en BD, así
  // que vaciar el input basta: el backend solo sube si llega un archivo.
  var fotoRegistro = SIGDEWizardUI.initFoto({
    inputId: 'fotoInput',
    previewId: 'fotoPreview',
    quitarId: 'btnQuitarFotoRegistro',
    onChange: function () {
      // Si ya estás en el resumen, refresca la foto del paso 6.
      if (pasoActual === totalPasos) pintarResumen();
    }
  });

  // ---------- Tarjetas seleccionables del sexo (paso 1) ----------
  SIGDEWizardUI.initTarjetasSeleccionables('[data-sexo-card]');

  // ---------- Tipo de documento: solo la letra cuando el select está cerrado (paso 1) ----------
  // Está en wizard-ui.js porque el wizard de inscripción lo usa igual.
  SIGDEWizardUI.initTipoDocumentoLetra({ selectId: 'tipoDocumento' });

  // ---------- Cascada estado->municipio->parroquia (paso 3) ----------
  // El mismo comportamiento del modal de edición: al cambiar el padre, el hijo
  // queda deshabilitado mostrando qué falta elegir. Está en SIGDEWizardUI.
  var cascadaRegistro = iniciarCascadaUbicacion();

  // ---------- Persona ya registrada (paso 1) ----------
  /* Si la cédula ya está en el sistema (por ejemplo, la persona se registró antes
     como familiar de un estudiante), se traen sus datos para darla de alta como
     empleado sin volver a escribirlos. Nombres, fecha y sexo quedan bloqueados:
     son su identidad en el sistema. Contacto y dirección siguen editables.
     El servidor ya reutiliza esa persona al guardar (PersonaModel::buscarOCrear). */
  var busquedaPersona = (function iniciarBusquedaPersona() {
    var inputDoc = formNuevo.querySelector('#numeroDocumento');
    if (!inputDoc) return { limpiar: function () {} };

    var IDENTIDAD = ['primerNombre', 'segundoNombre', 'primerApellido', 'segundoApellido', 'fechaNacimiento'];
    var cedulaCargada = null;

    var aviso = document.createElement('div');
    aviso.className = 'alert py-2 mt-2 d-none';
    aviso.setAttribute('role', 'status');
    aviso.setAttribute('aria-live', 'polite');
    var contenedorDoc = inputDoc.closest('.col-md-6, .col-md-8, .col-12, .mb-3') || inputDoc.parentNode;
    contenedorDoc.appendChild(aviso);

    function soloDigitos(v) { return String(v || '').replace(/\D/g, ''); }

    function poner(id, valor) {
      var el = formNuevo.querySelector('#' + id);
      if (el && valor !== undefined && valor !== null && valor !== '' && valor !== 0) el.value = valor;
    }

    function ponerPorNombre(nombre, valor) {
      var el = formNuevo.elements[nombre];
      if (el && valor) el.value = valor;
    }

    function mostrarAviso(tipo, html) {
      aviso.className = 'alert alert-' + tipo + ' py-2 mt-2';
      aviso.innerHTML = html;
    }

    function bloquear(bloqueado) {
      IDENTIDAD.forEach(function (id) {
        var el = formNuevo.querySelector('#' + id);
        if (!el) return;
        if (bloqueado) el.setAttribute('readonly', '');
        else el.removeAttribute('readonly');
      });
      /* Los radios del sexo NO se deshabilitan: un control disabled no viaja en el
         FormData y el alta fallaría por "Selecciona el sexo". Se bloquea la tarjeta. */
      formNuevo.querySelectorAll('[data-sexo-card]').forEach(function (card) {
        card.classList.toggle('is-locked', bloqueado);
        card.style.pointerEvents = bloqueado ? 'none' : '';
      });
    }

    function limpiar(vaciarCampos) {
      if (vaciarCampos && cedulaCargada !== null) {
        IDENTIDAD.forEach(function (id) {
          var el = formNuevo.querySelector('#' + id);
          if (el) el.value = '';
        });
        formNuevo.querySelectorAll('input[name="sexo"]').forEach(function (r) {
          r.checked = false;
          r.dispatchEvent(new Event('change', { bubbles: true }));
        });
      }
      cedulaCargada = null;
      bloquear(false);
      aviso.className = 'alert py-2 mt-2 d-none';
      aviso.innerHTML = '';
    }

    function cargar(encontrado, documento) {
      var p = encontrado.persona || {};
      var nombre = [p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido]
        .filter(Boolean).join(' ');

      if (encontrado.es_empleado) {
        mostrarAviso('warning', '<i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>'
          + '<strong>' + SIGDEUI.escapeHtml(nombre) + '</strong> ya está registrado(a) como empleado.');
        return;
      }

      poner('primerNombre', p.primer_nombre);
      poner('segundoNombre', p.segundo_nombre);
      poner('primerApellido', p.primer_apellido);
      poner('segundoApellido', p.segundo_apellido);
      poner('fechaNacimiento', p.fecha_nacimiento);
      if (Number(p.id_estado_nacimiento) > 0) poner('nacimientoEstado', p.id_estado_nacimiento);
      if (Number(p.id_nacionalidad) > 0) poner('nacionalidad', p.id_nacionalidad);
      if (p.tipo_documento) poner('tipoDocumento', p.tipo_documento);

      var radio = formNuevo.querySelector('input[name="sexo"][value="' + p.sexo + '"]');
      if (radio) {
        radio.checked = true;
        radio.dispatchEvent(new Event('change', { bubbles: true }));
      }

      var c = encontrado.contacto;
      if (c) {
        ponerPorNombre('codigo_telefono_principal', c.codigo);
        ponerPorNombre('numero_telefono_principal', c.numero);
        ponerPorNombre('codigo_telefono_alternativo', c.codigo_alt);
        ponerPorNombre('numero_telefono_alternativo', c.numero_alt);
        ponerPorNombre('correo_electronico', c.correo);
      }

      var d = encontrado.direccion;
      if (d) {
        poner('sectorUrbanizacion', d.sector);
        poner('calleAvenida', d.calle);
        poner('nroCasaApto', d.casa);
        poner('punto_referencia', d.referencia);
        if (cascadaRegistro && Number(d.parroquia) > 0) {
          cascadaRegistro.elegirParroquia(Number(d.estado || 0), Number(d.municipio || 0), Number(d.parroquia));
        }
      }

      cedulaCargada = documento;
      bloquear(true);

      var html = '<i class="bi bi-info-circle me-1" aria-hidden="true"></i>'
        + '<strong>Ya está registrada: ' + SIGDEUI.escapeHtml(nombre) + '.</strong> '
        + 'Se cargaron sus datos; revise el contacto y la dirección y complete los datos laborales.';
      if (Number(encontrado.representante_de) > 0) {
        html += '<br>Es representante legal de ' + Number(encontrado.representante_de) + ' estudiante(s).';
      }
      mostrarAviso('info', html);
    }

    var buscar = SIGDEUI.esDebounced(500, function () {
      var documento = soloDigitos(inputDoc.value);
      if (documento.length < 7 || documento.length > 8) return;

      SIGDEUI.enviar(baseUrl + rutaControlador + '/buscarPersona?documento=' + encodeURIComponent(documento), { metodo: 'GET' })
        .then(function (r) {
          // Si la cédula cambió mientras se consultaba, la respuesta ya no aplica.
          if (soloDigitos(inputDoc.value) !== documento) return;
          if (!r.ok || !r.data || r.data.ok !== true) return;
          if (r.data.data) cargar(r.data.data, documento);
          else limpiar(false);
        })
        .catch(function () { /* la búsqueda es una ayuda: si falla, se escribe a mano */ });
    });

    function alCambiarCedula() {
      var documento = soloDigitos(inputDoc.value);
      // Si se corrige la cédula de una persona cargada, se quitan sus datos.
      if (cedulaCargada !== null && documento !== cedulaCargada) limpiar(true);
      buscar();
    }

    inputDoc.addEventListener('input', alCambiarCedula);
    inputDoc.addEventListener('change', alCambiarCedula);

    return { limpiar: limpiar };
  })();

  // ---------- Títulos académicos (paso 5) ----------
  function pintarTablaTitulos() {
    var tbody = document.getElementById('tablaTitulosBody');
    if (!tbody) return;
    tbody.innerHTML = '';

    if (TITULOS.length === 0) {
      var filaVacia = document.createElement('tr');
      filaVacia.innerHTML = '<td colspan="4" class="text-center text-support py-4">Aún no has agregado ningún título.</td>';
      tbody.appendChild(filaVacia);
      return;
    }

    TITULOS.forEach(function (t, indice) {
      var fila = document.createElement('tr');

      var celdaTitulo = document.createElement('td');
      celdaTitulo.textContent = t.titulo;

      var celdaInstitucion = document.createElement('td');
      celdaInstitucion.textContent = t.institucion || '—';

      var celdaFecha = document.createElement('td');
      celdaFecha.textContent = t.fecha_obtencion ? formatearFecha(t.fecha_obtencion) : '—';

      var celdaAcciones = document.createElement('td');
      celdaAcciones.className = 'text-center';
      var botonQuitar = document.createElement('button');
      botonQuitar.type = 'button';
      botonQuitar.className = 'btn btn-link btn-sm text-danger p-1';
      botonQuitar.dataset.tituloEliminar = String(indice);
      botonQuitar.title = 'Quitar título';
      botonQuitar.innerHTML = '<i class="bi bi-trash" aria-hidden="true"></i>';
      celdaAcciones.appendChild(botonQuitar);

      fila.appendChild(celdaTitulo);
      fila.appendChild(celdaInstitucion);
      fila.appendChild(celdaFecha);
      fila.appendChild(celdaAcciones);
      tbody.appendChild(fila);
    });
  }

  function sincronizarTitulos() {
    var oculto = document.getElementById('titulosJson');
    if (oculto) oculto.value = JSON.stringify(TITULOS);
  }

  function textoOpcion(sel) {
    return (sel && sel.selectedIndex >= 0) ? sel.options[sel.selectedIndex].text : '';
  }

  // Paso 5: los títulos pertenecen a un grado académico. Cada <option> de
  // #tituloAgregar lleva data-id-grado (servidor) y solo se muestran las del
  // grado elegido en #gradoAgregar.
  function filtrarTitulosPorGrado() {
    var selGrado  = document.getElementById('gradoAgregar');
    var selTitulo = document.getElementById('tituloAgregar');
    if (!selGrado || !selTitulo) return;
    SIGDEUI.filtrarOpciones(selTitulo, selGrado.value, 'data-id-grado');
  }

  function agregarTitulo() {
    var selTitulo = document.getElementById('tituloAgregar');

    // id_grado_academico, id_institucion y fecha_obtencion son NOT NULL en
    // formacion_academica, así que el título solo se agrega con los tres.
    // El grado va primero: filtra qué títulos se pueden elegir.
    var selGrado    = document.getElementById('gradoAgregar');
    var selInstitucion = document.getElementById('institucionAgregar');
    var inputFecha  = document.getElementById('fechaObtencionAgregar');

    if (!selGrado || !selGrado.value) {
      SIGDEUI.mostrarAviso('warning', 'Grado requerido', 'Selecciona el grado académico del título.');
      return;
    }
    if (!selTitulo || !selTitulo.value) {
      SIGDEUI.mostrarAviso('warning', 'Título requerido', 'Selecciona un título para agregarlo al historial.');
      return;
    }
    if (!selInstitucion || !selInstitucion.value) {
      SIGDEUI.mostrarAviso('warning', 'Institución requerida', 'Selecciona la institución donde se obtuvo el título.');
      return;
    }
    if (!inputFecha || !inputFecha.value) {
      SIGDEUI.mostrarAviso('warning', 'Fecha requerida', 'Indica la fecha de obtención del título.');
      return;
    }
    // Los límites (min/max) viven en el HTML del input; checkValidity() los lee.
    // Este botón no es un submit, así que el navegador no los aplica solo.
    if (!inputFecha.checkValidity()) {
      SIGDEUI.mostrarAviso('warning', 'Fecha inválida', 'La fecha de obtención no puede ser anterior a 1950 ni futura.');
      return;
    }

    TITULOS.push({
      id_titulo:          Number(selTitulo.value),
      titulo:             textoOpcion(selTitulo),
      id_grado_academico: Number(selGrado.value),
      grado:              textoOpcion(selGrado),
      id_institucion:     Number(selInstitucion.value),
      institucion:        textoOpcion(selInstitucion),
      fecha_obtencion:    inputFecha.value,
    });

    pintarTablaTitulos();
    sincronizarTitulos();
    SIGDEUI.ocultarModal('modalAgregarTitulo');
  }

  var btnConfirmarTitulo = document.getElementById('btnConfirmarTitulo');
  if (btnConfirmarTitulo) btnConfirmarTitulo.addEventListener('click', agregarTitulo);

  // Al cambiar el grado académico se filtran los títulos que muestra #tituloAgregar.
  var selGradoAgregar = document.getElementById('gradoAgregar');
  if (selGradoAgregar) {
    selGradoAgregar.addEventListener('change', filtrarTitulosPorGrado);
  }

  // Quitar título de la tabla (delegación: los botones se repintan).
  document.addEventListener('click', function (evento) {
    var boton = evento.target.closest('[data-titulo-eliminar]');
    if (!boton) return;
    TITULOS.splice(Number(boton.dataset.tituloEliminar), 1);
    pintarTablaTitulos();
    sincronizarTitulos();
  });

  // ---------- Modales anidados del paso 5 (título / institución) ----------
  // Bootstrap 5 (data-api de [data-bs-toggle="modal"]) oculta el modal que
  // está abierto antes de mostrar el nuevo, así que la SCSS de apilado
  // (.modal.show + .modal.show en _form-wizard.scss) nunca llega a verse.
  // Para que "Nuevo título" y "Nueva institución" se abran ENCIMA del modal
  // "Agregar Título" (sin cerrarlo) se lanzan con .show() por JS.
  var btnAbrirModalTitulo = document.getElementById('btnAbrirAgregarTitulo');
  if (btnAbrirModalTitulo) {
    btnAbrirModalTitulo.addEventListener('click', function () {
      // Reaplica el filtro por grado por si el modal se reabre con grados
      // ya seleccionados de una visita anterior.
      filtrarTitulosPorGrado();
      SIGDEUI.mostrarModal('modalAgregarTitulo');
    });
  }

  document.addEventListener('click', function (evento) {
    var boton = evento.target.closest('.js-abrir-modal-anidado');
    if (!boton) return;
    var destino = boton.dataset.bsTarget
      ? document.querySelector(boton.dataset.bsTarget)
      : null;
    if (!destino) return;
    SIGDEUI.mostrarModal(destino);

    // "Nuevo Título": el alta rápida queda siempre vinculada a un grado; se
    // hereda el grado elegido en el modal padre #gradoAgregar para no pedirlo
    // otra vez.
    if (destino.id === 'modalNuevoTitulo') {
      var gradoAgregar = document.getElementById('gradoAgregar');
      var gradoNuevo = document.getElementById('nuevoTituloGrado');
      if (gradoNuevo && gradoAgregar && gradoAgregar.value) {
        gradoNuevo.value = gradoAgregar.value;
      }
    }
  });

  // Al ocultar un modal anidado, Bootstrap quita modal-open del body; el
  // padre sigue abierto, así que hay que restaurarlo.
  document.addEventListener('hidden.bs.modal', function (evento) {
    var cerrado = evento.target;
    if (cerrado === modalNuevoEl) return;
    if (modalNuevoEl && modalNuevoEl.classList.contains('show')) {
      document.body.classList.add('modal-open');
    }
  });

  // ---------- Alta rápida de título / institución ----------
  // Quien llama cierra el modal anidado cuando recibe truthy.
  /* El alta rapida vive en SIGDEUI, compartida con el wizard de inscripciones:
 arma el POST, avisa el resultado con el mismo texto que los modulos de
 catalogos y agrega la <option> nueva al select de destino.*/
  function guardarCatalogoRapido(accion, campos, selectDestino, dataAttr) {
    return SIGDEUI.guardarCatalogoRapido(
      baseUrl + rutaControlador + '/' + accion,
      campos,
      selectDestino,
      dataAttr
    );
  }

  var btnNuevoTitulo = document.getElementById('btnGuardarNuevoTitulo');
  if (btnNuevoTitulo) {
    btnNuevoTitulo.addEventListener('click', async function () {
      var selGradoNuevo = document.getElementById('nuevoTituloGrado');
      var inputNombre   = document.getElementById('nuevoTituloNombre');
      var nombre        = (inputNombre && inputNombre.value || '').trim();
      var idGrado       = (selGradoNuevo && selGradoNuevo.value) || '';

      if (!nombre) {
        SIGDEUI.mostrarAviso('warning', 'Nombre requerido', 'Escribe el nombre del título.');
        return;
      }
      if (!idGrado) {
        SIGDEUI.mostrarAviso('warning', 'Grado requerido', 'Selecciona el grado académico del título.');
        return;
      }

      var creado = await guardarCatalogoRapido(
        'guardarTitulo',
        { nombre: nombre, id_grado_academico: idGrado },
        document.getElementById('tituloAgregar'),
        { idGrado: idGrado }
      );
      if (creado) {
        if (inputNombre) inputNombre.value = '';
        // El título nuevo queda visible (y seleccionado) en el grado correcto.
        var selGradoPadre = document.getElementById('gradoAgregar');
        if (selGradoPadre && selGradoPadre.value !== idGrado) {
          selGradoPadre.value = idGrado;
        }
        filtrarTitulosPorGrado();
        SIGDEUI.ocultarModal('modalNuevoTitulo');
      }
    });
  }

  var btnNuevaInstitucion = document.getElementById('btnGuardarNuevaInstitucion');
  if (btnNuevaInstitucion) {
    btnNuevaInstitucion.addEventListener('click', async function () {
      var elNombre = document.getElementById('nuevaInstitucionNombre');
      var elTipo   = document.getElementById('nuevaInstitucionTipo');
      var nombre = (elNombre && elNombre.value || '').trim();
      var tipo   = (elTipo && elTipo.value) || '';
      if (!nombre) {
        SIGDEUI.mostrarAviso('warning', 'Nombre requerido', 'Escribe el nombre de la institución.');
        return;
      }
      if (!tipo) {
        SIGDEUI.mostrarAviso('warning', 'Tipo requerido', 'Selecciona el tipo de institución.');
        return;
      }
      var creado = await guardarCatalogoRapido(
        'guardarInstitucion',
        { nombre: nombre, tipo: tipo },
        document.getElementById('institucionAgregar')
      );
      if (creado) {
        if (elNombre) elNombre.value = '';
        if (elTipo) elTipo.value = '';
        SIGDEUI.ocultarModal('modalNuevaInstitucion');
      }
    });
  }

  // ---------- Resumen (paso 6) ----------
  function textoCampo(nombre) {
    var el = formNuevo.elements[nombre];
    if (!el) return '';
    if (el instanceof RadioNodeList) {
      var marcado = null;
      for (var i = 0; i < el.length; i++) { if (el[i].checked) { marcado = el[i]; break; } }
      return marcado ? marcado.value : '';
    }
    return (el.value || '').trim();
  }

  function textoSeleccion(nombre) {
    var el = formNuevo.elements[nombre];
    if (!el || el.selectedIndex < 0) return '';
    var opcion = el.options[el.selectedIndex];
    return opcion.value !== '' ? opcion.text : '';
  }

  function pintarTitulosResumen() {
    var contenedor = document.getElementById('resumenTitulos');
    if (!contenedor) return;
    contenedor.innerHTML = '';

    if (TITULOS.length === 0) {
      contenedor.textContent = 'Sin títulos registrados.';
      return;
    }

    TITULOS.forEach(function (t) {
      var item = document.createElement('div');
      item.textContent = [t.titulo, t.grado, t.institucion, t.fecha_obtencion ? formatearFecha(t.fecha_obtencion) : '']
        .filter(Boolean).join(' · ');
      contenedor.appendChild(item);
    });
  }

  function pintarResumen() {
    formNuevo.querySelectorAll('[data-resumen]').forEach(function (el) {
      // La foto no es texto: se pinta como <img> (o placeholder si no hay).
      if (el.dataset.resumen === 'foto') {
        var urlFoto = fotoRegistro ? fotoRegistro.alCambiar() : null;
        el.innerHTML = urlFoto
          ? '<img src="' + urlFoto + '" alt="Foto de perfil">'
          : '<i class="bi bi-person" aria-hidden="true"></i>';
        return;
      }
      var valor;
      switch (el.dataset.resumen) {
          case 'nombre_completo':
            /* Se normaliza por CAMPO y después se une, en ese orden y no al revés: es el mismo que sigue el
 servidor (EmployeeController normaliza primer_nombre, segundo_nombre, primer_apellido y
 segundo_apellido por separado). Si se uniera primero, "de la" perdería la minúscula.*/
            valor = ['primer_nombre', 'segundo_nombre', 'primer_apellido', 'segundo_apellido']
              .map(function (campo) { return SIGDEUI.normalizarNombre(textoCampo(campo)); })
              .filter(Boolean).join(' ');
            break;
        case 'documento': {
          var tipo = textoCampo('tipo_documento');
          var numero = textoCampo('numero_documento');
          valor = tipo && numero ? tipo + '-' + numero : '';
          break;
        }
        case 'fecha_nacimiento': valor = formatearFecha(textoCampo('fecha_nacimiento')); break;
        case 'sexo': valor = { M: 'Masculino', F: 'Femenino' }[textoCampo('sexo')] || ''; break;
        case 'telefono_principal': {
          var codigo = textoCampo('codigo_telefono_principal');
          var numero2 = textoCampo('numero_telefono_principal');
          valor = codigo && numero2 ? codigo + '-' + numero2 : '';
          break;
        }
        case 'telefono_alternativo': {
          var codigoAlt = textoCampo('codigo_telefono_alternativo');
          var numeroAlt = textoCampo('numero_telefono_alternativo');
          valor = codigoAlt && numeroAlt ? codigoAlt + '-' + numeroAlt : '';
          break;
        }
        case 'correo': valor = textoCampo('correo_electronico'); break;
        case 'estado': valor = textoSeleccion('id_estado_ui'); break;
        case 'municipio': valor = textoSeleccion('id_municipio_ui'); break;
        case 'parroquia': valor = textoSeleccion('id_parroquia'); break;
        case 'direccion':
          valor = [textoCampo('sector_urbanizacion'), textoCampo('calle_avenida'), textoCampo('nro_casa_apto')]
            .filter(Boolean).join(', ');
          break;
        case 'punto_referencia': valor = textoCampo('punto_referencia'); break;
        case 'cargo': valor = textoSeleccion('id_cargo'); break;
        case 'turno': valor = textoSeleccion('id_turno'); break;
        case 'fecha_ingreso': valor = formatearFecha(textoCampo('fecha_ingreso')); break;
        default: valor = textoCampo(el.dataset.resumen);
      }
      el.textContent = valor || '—';
    });
    pintarTitulosResumen();
  }

  // ---------- Envío del registro (empleado/store) ----------
  // Valida todos los pasos antes de enviar y usa el mismo patrón de
  // submit de los demás módulos: spinner + fetch + error con detalles.
  formNuevo.addEventListener('submit', async function (evento) {
    evento.preventDefault();

    // Igual que en validarPasoCompleto(): refrescar el customValidity del
    // paso 2 ANTES de revisar panelCompleto() en todos los paneles, para
    // que un customValidity forzado en un intento anterior no deje el
    // envío bloqueado aunque el usuario ya haya corregido el prefijo.
    validarTelefonoAlternativo(panelDe(2));
    if (!paneles.every(panelCompleto)) {
      SIGDEUI.mostrarAviso('warning', 'Campos incompletos', 'Revisa los pasos marcados antes de finalizar.');
      return;
    }

    if (btnFinalizar) {
      btnFinalizar.disabled = true;
      btnFinalizar.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Guardando...';
    }

    try {
      var formData = new FormData(formNuevo);
      var res = await SIGDEUI.enviar(baseUrl + rutaControlador + '/store', { metodo: 'POST', cuerpo: formData });
      var data = res.data;

      if (!res.ok || !data.ok) {
        var msgError = (data && data.mensaje) || 'No se pudo registrar el empleado. Verifique los datos.';
        var errs = (data && (data.errors || data.data));
        if (errs && typeof errs === 'object') {
          var detalles = Object.values(errs).map(function(v) { return Array.isArray(v) ? v.join('\n') : v; }).filter(Boolean).join('\n');
          if (detalles) msgError += '\n\n' + detalles;
        }
        SIGDEUI.mostrarError(msgError, 'Error al registrar empleado');
        return;
      }

      if (modalNuevoEl) {
        SIGDEUI.alCerrarModal(modalNuevoEl, function mostrarSwalRegistrado() {
          formNuevo.reset();
          if (typeof formNuevo.reiniciarWizard === 'function') formNuevo.reiniciarWizard();

          SIGDEUI.exito(data.mensaje || 'Empleado registrado correctamente.', 'Registrado')
            .then(function () { window.location.reload(); });
        });

        SIGDEUI.ocultarModal(modalNuevoEl);
      } else {
        window.location.reload();
      }
    } catch (err) {
      console.error('Error al procesar petición AJAX (store):', err);
      SIGDEUI.mostrarError('Ocurrió un fallo de conexión al registrar el empleado.');
    } finally {
      if (btnFinalizar) {
        btnFinalizar.disabled = false;
        btnFinalizar.innerHTML = htmlFinalizarOriginal;
      }
    }
  });

  // ---------- Reinicio del wizard ----------
  function reiniciarWizard() {
    pasoActual = 1;
    edicionDesdeResumen = false;
    TITULOS.length = 0;
    sincronizarTitulos();
    pintarTablaTitulos();
    activarPaso();
    paneles.forEach(function (p) { p.classList.remove('was-validated'); });
    if (fotoRegistro) fotoRegistro.vaciar();

    // El formulario se reutiliza entre registros (el modal no se recrea),
    // así que sin esto un customValidity() forzado en un intento anterior
    // seguiría marcando el select como inválido en el siguiente registro.
    var codAlt = formNuevo.querySelector('#codigo_telefono_alternativo');
    if (codAlt) codAlt.setCustomValidity('');
  }
  formNuevo.reiniciarWizard = reiniciarWizard;

  // Al cerrar el modal de registro dejo el formulario limpio. Filtro por
  // target porque los modales anidados (título, institución) también
  // disparan hidden.bs.modal y burbujean hasta aquí.
  if (modalNuevoEl) {
    modalNuevoEl.addEventListener('hidden.bs.modal', function (evento) {
      if (evento.target !== modalNuevoEl) return;
      formNuevo.reset();
      if (typeof formNuevo.reiniciarWizard === 'function') formNuevo.reiniciarWizard();
    });
  }

  // ---------- Arranque ----------
  pintarTablaTitulos();
  activarPaso();
}
