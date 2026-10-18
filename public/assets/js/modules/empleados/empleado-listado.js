/* SIGDE - modules/empleados/empleado-listado.js
 * Listado de empleados: cambiar el estado (activo/inactivo) desde el switch de la fila y el
 * modal de Ver Detalle, que pide el empleado a empleado/show y lo pinta.
 *
 * Depende de core/sigde-ui.js y de este mismo directorio, empleado-comun.js.*/

(function () {
  'use strict';

// --- Cambiar Estado (Activo / Inactivo) ---
function initToggleEstadoEmpleado() {
  document.addEventListener('change', async function (e) {
    var checkbox = e.target.closest('.toggle-estado');
    if (!checkbox) return;
    var id = checkbox.dataset.empleadoId;
    var nuevoEstadoActivo = checkbox.checked;
    var accion = nuevoEstadoActivo ? 'activar' : 'desactivar';
    var checkboxAnterior = !checkbox.checked;
    var tr = checkbox.closest('tr');
    var badge = tr ? tr.querySelector('.status-badge') : null;

    var confirmacion = false;
    confirmacion = await SIGDEUI.confirmar({
      titulo: accion.charAt(0).toUpperCase() + accion.slice(1) + ' empleado?',
      texto: '¿Está seguro que desea ' + accion + ' este empleado?',
      confirmar: 'Sí, ' + accion
    });

    if (!confirmacion) {
      checkbox.checked = checkboxAnterior;
      return;
    }

    try {
      var res = await SIGDEUI.enviar(SIGDEEmpleados.baseUrl + SIGDEEmpleados.rutaControlador + '/toggleStatus', { metodo: 'POST', cuerpo: 'id=' + encodeURIComponent(id), cabeceras: { 'Content-Type': 'application/x-www-form-urlencoded' } });
      var data = res.data;

      if (!res.ok || !data.ok) {
        checkbox.checked = checkboxAnterior;
        SIGDEUI.mostrarError(data.mensaje || 'No se pudo actualizar el estado del empleado.');
        return;
      }

      if (tr) {
        tr.classList.toggle('is-muted', !nuevoEstadoActivo);
        var btnEditar = tr.querySelector('.editar-empleado');
        if (btnEditar) {
          btnEditar.disabled = !nuevoEstadoActivo;
          btnEditar.title = nuevoEstadoActivo ? 'Editar empleado' : 'No se puede editar un empleado inactivo';
        }
        var switchWrapper = tr.querySelector('.table-switch');
        if (switchWrapper && !checkbox.disabled) {
          switchWrapper.title = (nuevoEstadoActivo ? 'Desactivar' : 'Activar') + ' empleado';
        }
      }
      if (badge) {
        badge.className = 'status-badge status-badge--' + (nuevoEstadoActivo ? 'active' : 'inactive');
        badge.textContent = nuevoEstadoActivo ? 'Activo' : 'Inactivo';
      }

      SIGDEUI.exito('Empleado ' + accion + 'do correctamente.', '¡Listo!');
    } catch (err) {
      checkbox.checked = checkboxAnterior;
      console.error('Error al cambiar estado:', err);
      SIGDEUI.mostrarError('Error de conexión al actualizar el estado.');
    }
  });
}

// --- Modal Ver Detalle ---
function initVerEmpleadoModal() {
  var modalVerEl = document.getElementById('verEmpleadoModal');
  if (!modalVerEl) return;

  // Delegación en document: los botones de la tabla pueden ser
  // reemplazados por core/main.js al filtrar o paginar, y así siguen funcionando.
  document.addEventListener('click', function (e) {
    var btnVer = e.target.closest('.ver-empleado');
    if (!btnVer) return;

    SIGDEEmpleados.cargarEmpleado(btnVer.dataset.empleadoId).then(function (d) {
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

  /* La dirección la trae YA redactada el servidor (App\Support\Direccion, la misma
     que usa Listados y Actas y el PDF del expediente). Antes se recomponía acá con
     los mismos prefijos, y con un campo faltando el modal mostraba algo distinto
     de lo que mostraba la edición: la cuarta copia de la regla, en el navegador.
     Por si el servidor llegara sin el campo, se arma igual y no se pierde nada. */
  var direccionCompleta = d.direccion_completa || null;
  if (direccionCompleta === null) {
    var t = [];
    if (d.calle_avenida)        t.push(d.calle_avenida);
    if (d.nro_casa_apto)        t.push('Casa nro ' + d.nro_casa_apto);
    if (d.sector_urbanizacion)  t.push('Sector ' + d.sector_urbanizacion);
    if (d.parroquia_nombre)     t.push('Parroquia ' + d.parroquia_nombre);
    if (d.municipio_nombre)     t.push('Municipio ' + d.municipio_nombre);
    if (d.estado_nombre)        t.push('Estado ' + d.estado_nombre);
    if (d.punto_referencia) {
      t.push('Punto de referencia ' + d.punto_referencia);
    }
    direccionCompleta = t.join(', ');
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
    fecha_nacimiento: SIGDEUI.formatearFechaCorta(d.fecha_nacimiento),
    fecha_ingreso:    SIGDEUI.formatearFechaCorta(d.fecha_ingreso),
    fecha_egreso:     SIGDEUI.formatearFechaCorta(d.fecha_egreso),
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
      foto.src = SIGDEEmpleados.baseUrl + d.avatar_foto.replace(/^\//, '');
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

  cargarRepososDelDetalle(d.id_empleado, d.nombre_completo, activo);
}

  /* El modal de Ver Detalle tiene una pestaña con el historial de reposos, que pinta el módulo
   * de empleado-reposo.js. Se llama con guarda para que el detalle se abra igual aunque ese
   * archivo no esté cargado: la carga de scripts es síncrona y va antes de cualquier clic, así
   * que en la práctica siempre lo está, pero la dependencia queda explícita y no rompe nada. */
  function cargarRepososDelDetalle(idEmpleado, nombreCompleto, activo) {
    if (SIGDEEmpleados.reposos) {
      SIGDEEmpleados.reposos.cargar(idEmpleado, nombreCompleto, activo);
    }
  }

  // --- Inicialización Módulo ---
  function init() {
    initToggleEstadoEmpleado();
    initVerEmpleadoModal();
  }

  // Inicialización segura del DOM que evita problemas cuando los scripts
  // se cargan con 'defer', 'async' o al final de la plantilla layout.
  if (document.readyState === 'interactive' || document.readyState === 'complete') {
    init();
  } else {
    document.addEventListener('DOMContentLoaded', init);
  }
})();
