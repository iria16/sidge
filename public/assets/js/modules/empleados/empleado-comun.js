/* SIGDE - modules/empleados/empleado-comun.js
 * Configuración y utilidades compartidas por los cuatro archivos del módulo de empleados
 * (listado, reposos, edición y alta). Publica window.SIGDEEmpleados.
 *
 * Antes eran variables y funciones globales al principio de empleado.js, un archivo que juntaba
 * las cuatro responsabilidades. Al partirlo, si cada hoja declarara las suyas volverían a
 * colisionar: baseUrl y rutaControlador ya estaban declarados igual en catalogo.js, cuentas.js
 * y recuperacion.js, y hoy solo se salvan porque esas pantallas nunca se cargan a la vez.
 *
 * Lo único que este archivo inicializa son los filtros de escritura, que se registran UNA vez
 * por página: SIGDEWizardUI.initSoloDigitos() e initSoloLetras() se enganchan a document, así que
 * llamarlos dos veces filtraría el mismo input dos veces.*/

window.SIGDEEmpleados = (function () {
  'use strict';

  // --- Configuración Global de Rutas ---
  var rawBaseUrl = window.BASE_URL || '';
  var baseUrl = rawBaseUrl.endsWith('/') ? rawBaseUrl : rawBaseUrl + '/';
  // Debe coincidir con la URL real del módulo (127.0.0.1:8000/empleado/...)
  // y con la clave 'empleado' de $rutasPermitidas en el front controller.
  // OJO: EmpleadoController::$routeName está en plural ('empleados'); si en
  // algún momento se unifica todo a plural, cambiar esto también.
  var rutaControlador = 'empleado';

  /* --- Funciones Auxiliares ---
   * formatearFecha / escapeHtml NO están aquí: los presta core/sigde-ui.js
   * (formatearFechaCorta y escapeHtml), que es donde ya viven para el resto de módulos. */

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
  // La implementación vive en public/assets/js/core/wizard-ui.js y la comparten el
  // wizard de empleado, el de estudiante y el sub-modal de familiar:
  // pattern="[0-9]{7}" y maxlength (de phone-input.php) ya invalidan el campo al
  // enviar; estos filtros además evitan que la letra llegue a escribirse.
  function initFiltrosDeEscritura() {
    SIGDEWizardUI.initSoloDigitos();
    SIGDEWizardUI.initSoloLetras();
  }

  return {
    baseUrl: baseUrl,
    rutaControlador: rutaControlador,
    fechaHoyISO: fechaHoyISO,
    actualizarBadgeReposoEnTabla: actualizarBadgeReposoEnTabla,
    cargarEmpleado: cargarEmpleado,
    iniciarCascadaUbicacion: iniciarCascadaUbicacion,
    initFiltrosDeEscritura: initFiltrosDeEscritura,
    /* Lo rellena empleado-reposo.js con su API. Va declarado aquí para que quede
       explícito que el listado depende de ese archivo y no al revés. */
    reposos: null
  };
})();

// Inicialización segura del DOM que evita problemas cuando los scripts
// se cargan con 'defer', 'async' o al final de la plantilla layout.
(function () {
  function initEmpleadoComun() {
    SIGDEEmpleados.initFiltrosDeEscritura();
  }

  if (document.readyState === 'interactive' || document.readyState === 'complete') {
    initEmpleadoComun();
  } else {
    document.addEventListener('DOMContentLoaded', initEmpleadoComun);
  }
})();
