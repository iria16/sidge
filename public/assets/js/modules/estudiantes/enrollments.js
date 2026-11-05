/* SIGDE - comportamiento del wizard de inscripciones.
 El listado (filtros, búsqueda y paginación) lo aplica automáticamente
 main.js (initAutoFilters) sobre el form del data-panel, igual que en el
 resto de módulos; aquí solo vive la lógica del asistente de 7 pasos.*/
document.addEventListener('DOMContentLoaded', function () {
  var route = (window.BASE_URL || '') + 'inscripciones/';

  /* Helpers
*/
  function textoSeleccionado(id) {
    var el = document.getElementById(id);
    return el && el.selectedOptions && el.selectedOptions[0]
      ? el.selectedOptions[0].textContent.trim()
      : '—';
  }

  /* El nombre pelado de una opción, sin lo que se le haya añadido a la etiqueta.
 El select del turno lleva "12/30" al final, que en el formulario sirve,
 pero en el resumen del paso 7 sería ruido: ahí se lee el nombre.*/
  function nombreSencilloDe(id) {
    var el = document.getElementById(id);
    var opcion = el && el.selectedOptions && el.selectedOptions[0];
    if (!opcion) { return '—'; }
    return (opcion.dataset.nombre || opcion.textContent).trim();
  }

  /* WIZARD "Registrar Nueva Inscripción" (7 pasos)
*/
  var wizardModalEl = document.getElementById('nuevaInscripcionModal');
  var form          = document.getElementById('formNuevaInscripcion');

  if (!form || !wizardModalEl) return;

  var pasoActual        = 1;
  var totalPasos        = 7;
  var editandoDesdePaso = null;   // modo edición desde resumen

  // Inicializar el wizard mostrando el paso 1 al cargar la página
  mostrarPaso(1);

  /* ---------- Navegación entre pasos ----------*/
  function mostrarPaso(numero) {
    var dialog = wizardModalEl.querySelector('.modal-dialog');
    if (dialog) {
      if (numero === totalPasos) {
        dialog.className = 'modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable';
      } else {
        dialog.className = 'modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable';
      }
    }

    var modalBody = wizardModalEl.querySelector('.modal-body');
    if (modalBody) {
      modalBody.scrollTop = 0;
    }

    wizardModalEl.querySelectorAll('.wizard-panel').forEach(function (panel) {
      panel.classList.toggle('is-active', Number(panel.dataset.step) === numero);
    });

    wizardModalEl.querySelectorAll('.wizard-steps__step').forEach(function (step) {
      var n = Number(step.dataset.stepIndicator);
      step.classList.toggle('is-active', n === numero);
      step.classList.toggle('is-completed', n < numero);
      var circle = step.querySelector('.wizard-steps__circle');
      circle.innerHTML = n < numero ? '<i class="bi bi-check-lg"></i>' : n;
    });

    document.getElementById('btnWizardAtras').classList.toggle('d-none', numero === 1);
    document.getElementById('btnWizardSiguiente').classList.toggle('d-none', numero === totalPasos);
    document.getElementById('btnWizardFinalizar').classList.toggle('d-none', numero !== totalPasos);

    if (numero === 6) actualizarRecaudos();
    if (numero === totalPasos) llenarResumen();
    pasoActual = numero;
  }

  /* Valida los campos obligatorios del paso `numero`. Marca el `novalidate` del formulario para que
 el navegador no muestre su propio globo y los mensajes sean los del sistema, que explican qué
 corregir.*/
  function validarPaso(numero) {
    if (numero === 5 && !familiaresUI.hayRepresentante()) {
      SIGDEUI.mostrarAviso(
        'warning',
        'Falta el representante legal',
        'Debe marcar un familiar como representante legal.'
      );
      return false;
    }

    if (numero === 6) {
      if (errorRecaudos) {
        SIGDEUI.mostrarAviso('error', 'No se pudieron calcular los recaudos', 'Actualiza la lista antes de continuar.');
        return false;
      }
      if (cargandoRecaudos) {
        SIGDEUI.mostrarAviso('info', 'Calculando recaudos', 'Espera a que se actualice la lista de documentos.');
        return false;
      }
      if (recaudosActuales.length === 0) {
        SIGDEUI.mostrarAviso('warning', 'Lista de recaudos incompleta', 'Actualiza la lista de documentos antes de continuar.');
        return false;
      }
      var faltanteObligatorio = recaudosActuales.find(function (recaudo) {
        var fila = recaudosTbody.querySelector('[data-recaudo-clave="' + recaudo.clave + '"]');
        var toggle = fila && fila.querySelector('[data-recaudo-toggle]');
        /* Solo estos cuatro pueden ser obligatorios, y solo en el caso que los exige; el
           servidor ya marca `requerido` únicamente en ellos. Lo demás nunca bloquea. */
        var obligatorios = ['constancia_prosecucion', 'permiso_lopnna', 'autorizacion_retiro', 'cedula_autorizada'];
        return recaudo.requerido && obligatorios.indexOf(recaudo.codigo) !== -1
          && (!toggle || !toggle.checked);
      });
      if (faltanteObligatorio) {
        SIGDEUI.mostrarAviso(
          'warning',
          'Faltan recaudos obligatorios',
          'Marca como consignados los documentos obligatorios antes de continuar.'
        );
        return false;
      }
    }

    var panel = wizardModalEl.querySelector('.wizard-panel[data-step="' + numero + '"]');
    if (!panel) return true;

    if (numero === 1) {
      var inputFecha = panel.querySelector('#fechaNacimiento') || panel.querySelector('[name="fecha_nacimiento"]');
      if (inputFecha && inputFecha.value) {
        var minVal = inputFecha.getAttribute('min');
        var maxVal = inputFecha.getAttribute('max');
        if (minVal && inputFecha.value < minVal) {
          inputFecha.classList.add('is-invalid');
          SIGDEUI.mostrarAviso(
            'warning',
            'Edad máxima superada',
            'La fecha de nacimiento (' + inputFecha.value + ') supera la edad máxima permitida para educación primaria (14 años).'
          );
          return false;
        }
        if (maxVal && inputFecha.value > maxVal) {
          inputFecha.classList.add('is-invalid');
          SIGDEUI.mostrarAviso(
            'warning',
            'Edad mínima no alcanzada',
            'El estudiante debe tener al menos 5 años y cumplir 6 en el año escolar activo.'
          );
          return false;
        }
      }
    }

    var campos = panel.querySelectorAll('[required]');
    var valido = true;

    campos.forEach(function (campo) {
      var okCampo = campo.checkValidity();
      if (!okCampo) valido = false;
      campo.classList.toggle('is-invalid', !okCampo);
    });

    if (!valido) {
      SIGDEUI.mostrarAviso('warning', 'Campos incompletos', 'Completa los campos obligatorios (*) antes de continuar.');
    }

    return valido;
  }

  function validarPasoActual() {
    return validarPaso(pasoActual);
  }

/* Al volver de editar un paso del resumen NO se puede saltar al 7: hay que recorrer
 los intermedios validando. Esos pasos ya se cruzaron bien la primera vez, pero al
 volver de editar, la secretaria puede haber vaciado cualquiera de ellos, y el
 resumen no los vuelve a mirar. Saltárselos llevaría los campos vacíos hasta el
 submit, que solo entonces los rechaza con un mensaje genérico.

 Se devuelve el número del primer paso inválido, o null si todo está bien.*/
  function primerPasoInvalido(desde) {
    for (var n = desde; n < totalPasos; n++) {
      if (!validarPaso(n)) return n;
    }
    return null;
  }

  document.getElementById('btnWizardSiguiente').addEventListener('click', function () {
    if (!validarPasoActual()) return;

    if (editandoDesdePaso !== null) {
      var pasoDestino = totalPasos;
      editandoDesdePaso = null;

      /* Se revalida desde el paso en el que está hasta el 6. Si algo falla, se
 muestra ese paso para que la secretaria vea qué corregir, en vez de
 devolverla al resumen con el problema escondido.*/
      var problema = primerPasoInvalido(pasoActual);
      if (problema !== null) {
        pasoDestino = problema;
      }

      if (pasoDestino !== pasoActual) mostrarPaso(pasoDestino);
      return;
    }

    if (pasoActual < totalPasos) mostrarPaso(pasoActual + 1);
  });

  document.getElementById('btnWizardAtras').addEventListener('click', function () {
    if (pasoActual > 1) mostrarPaso(pasoActual - 1);
  });

  /* Delegación: botones "Editar" del resumen (paso 7)*/
  wizardModalEl.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-editar-paso]');
    if (!btn) return;
    editandoDesdePaso = totalPasos;
    mostrarPaso(Number(btn.dataset.editarPaso));
  });

  /* Reset al cerrar el modal*/
  SIGDEUI.alCerrarModal(wizardModalEl, function reiniciarAlCerrar() {
    pasoActual        = 1;
    editandoDesdePaso = null;
    /* La lista de familiares vive en FamiliaresUI (abajo); aquí solo se vacía.*/
    familiaresUI.cargar([]);
    form.reset();
    form.classList.remove('was-validated');
    /* form.reset() NO dispara `change`, así que el paso 5 se queda con los contadores
 y las observaciones de la inscripción anterior: al reabrir, el badge prometería
 "3 de 5 consignados" con los cinco switches apagados, y las cajas de observación
 de los que sí estaban marcados seguirían a la vista. Se recalcula desde el DOM,
 que ya está en su estado limpio.*/
    estadoRecaudos = Object.create(null);
    recaudosActuales = [];
    dibujarRecaudos([]);
    recalcularRecaudos();
    mostrarPaso(1);
    var previewEl = document.getElementById('fotoPreview');
    if (previewEl) previewEl.innerHTML = '<i class="bi bi-person"></i>';
    /* Restaurar bloque procedencia por si se ocultó*/
    var bloqueProc = document.getElementById('bloqueInstitucionProcedencia');
    if (bloqueProc) {
      bloqueProc.style.display = '';
      /* Se devuelven los `required` que el interruptor de "primera vez" quitó. Al volver al paso hay que
 volver a ponerlos: si no, el campo queda editando libre y el navegador lo deja pasar.*/
      bloqueProc.querySelectorAll('[data-required-saved]').forEach(function (campo) {
        campo.setAttribute('required', '');
        delete campo.dataset.requiredSaved;
      });
    }
    var ingresoPV = document.getElementById('ingresoPrimeraVez');
    if (ingresoPV) ingresoPV.checked = false;
  });

  /* Paso 3: grado, sección y turno, con los cupos. Las opciones las pone el servidor desde
 estructura_academica, una por combinación, con la clave "grado|seccion" y
 "grado|seccion|turno". Por eso el filtrado es por prefijo: al elegir el grado solo quedan
 las secciones de ese grado, y al elegir la sección, los turnos de esa sección.*/
  (function cascadaAcademica() {
    var contenedor = document.querySelector('[data-academico-cascada]');
    if (!contenedor) return;

    var selGrado = document.getElementById('gradoCursar');
    var selSeccion = document.getElementById('seccion');
    var selTurno = document.getElementById('turno');
    if (!selGrado || !selSeccion || !selTurno) return;

    var cupos = {};
    try { cupos = JSON.parse(contenedor.dataset.cupos || '{}'); } catch (e) { cupos = {}; }

    /* La clave de la opción ELEGIDA. Es la que usa el filtrado por prefijo, y por eso no basta con el
 value: dos secciones de grados distintos pueden tener el mismo id de sección.*/
    var comboDe = function (select) {
      var o = select.options[select.selectedIndex];
      if (!o || o.hidden) { return ''; }
      return o.dataset.combinacion || '';
    };

  /* Muestra solo las opciones cuya clave empieza por el prefijo dado. Oculta con `hidden` Y
 deshabilita con `disabled`, siempre juntos: si solo se ocultan, la búsqueda por letra del
 <select> puede aterrizar en una opción escondida y el usuario ve un turno de otro grado.*/
      var filtrarPorPrefijo = function (select, prefijo) {
        Array.prototype.forEach.call(select.options, function (o) {
          if (!o.value) return;                       // el placeholder siempre
          var clave = o.dataset.combinacion || '';
          var visible = prefijo !== '' && clave.indexOf(prefijo + '|') === 0;
          o.hidden = !visible;
          o.disabled = !visible;
        });
      };

    var reescribirTurnos = function () {
      var clave = comboDe(selSeccion);              // "grado|seccion"
      var delPar = clave ? (cupos[clave] || []) : [];

      Array.prototype.forEach.call(selTurno.options, function (o) {
        if (!o.value) return;
        var base = o.dataset.nombre || o.textContent;
        var combo = null;
        for (var i = 0; i < delPar.length; i++) {
          if (String(delPar[i].id_turno) === String(o.value)) { combo = delPar[i]; break; }
        }
        if (!combo) { o.textContent = base; return; }

        var texto = base + ' (' + combo.ocupados + '/' + combo.capacidad + ')';
        if (combo.disponibles <= 0) {
          texto += ' · sin cupo';
          o.disabled = true;                        // no se puede elegir
        } else {
          o.disabled = false;
        }
        o.textContent = texto;
      });
    };

    selGrado.addEventListener('change', function () {
      filtrarPorPrefijo(selSeccion, selGrado.value);
      selSeccion.value = '';
      filtrarPorPrefijo(selTurno, '');
      selTurno.value = '';
      reescribirTurnos();
    });

    selSeccion.addEventListener('change', function () {
      filtrarPorPrefijo(selTurno, comboDe(selSeccion));
      selTurno.value = '';
      reescribirTurnos();
    });

    // Estado inicial: sin grado elegido no se ve ninguna sección ni turno.
    filtrarPorPrefijo(selSeccion, '');
    filtrarPorPrefijo(selTurno, '');
    reescribirTurnos();
  })();

  /* ---------- Foto del estudiante (paso 1) ----------
 Previsualización, "quitar foto" y filtros de escritura delegan en
 public/assets/js/core/wizard-ui.js, el mismo módulo que consume el wizard de
 empleado. La foto del FAMILIAR no se inicializa aquí: la inicializa
 public/assets/js/modules/estudiantes/familiares.js, que es el dueño del sub-modal.*/
  SIGDEWizardUI.initFoto({
    inputId: 'fotoInput',
    previewId: 'fotoPreview',
    quitarId: 'btnQuitarFotoEstudiante',
    onChange: function () { if (pasoActual === totalPasos) llenarResumen(); }
  });

  /* Documento del estudiante (paso 1). La búsqueda es por cédula y avisa si ya existe, para no
 dar de alta dos veces a la misma persona ni dejar un registro a medias.*/
  (function documentoEstudiante() {
    var select = document.getElementById('tipoDocumento');
    var numero = document.getElementById('numeroDocumento');
    if (!select || !numero) return;

    var TIPO_CE = 'CE';

    // El aviso va en el propio campo, en el placeholder: con la cédula escolar
    // se deshabilita y dice que la genera el sistema; con V o E se habilita y
    // aparece solo el ejemplo del número (App\Support\Documento::
    // PLACEHOLDER_HTML), que se replica aquí porque JS no puede leer la
    // constante de PHP. La nota del prefijo opcional vive únicamente en el
    // title del campo.
    var PLACEHOLDERS = {
      CE: 'Se genera automáticamente',
      V:  'Ej: 25415866',
      E:  'Ej: 25415866'
    };

    function sincronizar() {
      var esEscolar = select.value === TIPO_CE;

      numero.placeholder = PLACEHOLDERS[select.value] || PLACEHOLDERS[TIPO_CE];

      // Se deshabilita (y no solo se oculta) para que el campo no quede con
      // `required` dentro de un bloque invisible y trabe el avance del wizard.
      numero.disabled = esEscolar;
      if (esEscolar) {
        numero.value = '';
        numero.removeAttribute('required');
        numero.classList.remove('is-invalid');
      } else {
        numero.setAttribute('required', '');
      }
    }

    select.addEventListener('change', sincronizar);
    sincronizar();
  })();

  /* El tipo de documento se muestra solo con la letra mientras el select está
 cerrado, igual que en los formularios de empleados. El del familiar es un
 select aparte (dentro de su sub-modal), así que se inicializa aparte.*/
  SIGDEWizardUI.initTipoDocumentoLetra({ selectId: 'tipoDocumento' });
  SIGDEWizardUI.initTipoDocumentoLetra({ selectId: 'familiarTipoDocumento' });

  /* Una sola llamada alcanza: persona-form.php marca las tarjetas con
 `data-sexo-card`, sin prefijo, tanto para el estudiante como para el familiar
 (el que lleva prefijo es el `name` del radio). La segunda llamada que había
 aquí con `[data-familiar-sexo-card]` no matcheaba nada y salía enseguida.*/
  SIGDEWizardUI.initTarjetasSeleccionables('[data-sexo-card]');
  SIGDEWizardUI.initSoloDigitos();
  SIGDEWizardUI.initSoloLetras();

  /* Cascada del lugar de nacimiento del ESTUDIANDA (paso 1). Es la misma función que
     usa el formulario de dirección: mismo endpoint, mismo filtrado y misma precarga.
     El wizard de inscripción no la tenía porque hasta ahora no había ningún select de
     estado, municipio y parroquia en este módulo. */
  var cascadaNacimientoEstudiante = SIGDEWizardUI.initCascadaNacimiento(route + 'getUbicacion');

  /* El modal de edición de ratificación REUTILIZA este paso 1, así que los selects de
     nacimiento viven en su formulario, pero los rellena ratificacion.js cuando llegan
     los datos del estudiante. Para eso esta cascada tiene que quedar a mano: si se
     quedara local, el modal abriría los tres selects vacíos y, como son obligatorios,
     no se podría guardar ni la edición más mínima. */
  window.SIGDECascadaNacimiento = cascadaNacimientoEstudiante;
  window.SIGDECascadaNacimientoEndpoint = route + 'getUbicacion';

  /* ---------- Toggle "Ingreso por primera vez" (paso 2) ----------*/
  var ingresoPrimeraVez   = document.getElementById('ingresoPrimeraVez');
  var bloqueInstitucion   = document.getElementById('bloqueInstitucionProcedencia');
  var instSelect          = document.getElementById('institucionProcedencia');

  if (ingresoPrimeraVez && bloqueInstitucion) {
    ingresoPrimeraVez.addEventListener('change', function () {
      var esPrimera = ingresoPrimeraVez.checked;
      bloqueInstitucion.style.display = esPrimera ? 'none' : '';
      /* Quitar/poner required en campos del bloque*/
      bloqueInstitucion.querySelectorAll('[required]').forEach(function (campo) {
        if (esPrimera) {
          campo.removeAttribute('required');
          campo.dataset.requiredSaved = 'true';
        } else if (campo.dataset.requiredSaved) {
          campo.setAttribute('required', '');
          delete campo.dataset.requiredSaved;
        }
      });
      actualizarRecaudos();
    });
  }

  /* ---------- Tarjetas expandibles — Información Adicional (paso 4) ----------*/
  wizardModalEl.querySelectorAll('[data-extra-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var key  = btn.dataset.extraToggle;
      var body = document.getElementById('extraBody-' + key);
      var card = btn.closest('.wizard-extra-card');
      if (!body || !card) return;
      var abierto = !body.hidden;
      body.hidden = abierto;
      card.classList.toggle('is-open', !abierto);
    });
  });

  /* ---------- Recaudos dinámicos (paso 6) ----------*/
  var recaudosTbody = document.getElementById('recaudosTbody');
  var recaudosActuales = [];
  var estadoRecaudos = Object.create(null);
  var cargandoRecaudos = false;
  var errorRecaudos = false;
  var solicitudRecaudos = 0;

  function guardarEstadoVisibleRecaudos() {
    if (!recaudosTbody) return;
    recaudosTbody.querySelectorAll('[data-recaudo-row]').forEach(function (fila) {
      var clave = fila.dataset.recaudoClave;
      var toggle = fila.querySelector('[data-recaudo-toggle]');
      var observacion = fila.querySelector('[data-recaudo-observacion]');
      if (!clave || !toggle) return;
      estadoRecaudos[clave] = {
        consignado: toggle.checked ? 1 : 0,
        observacion: observacion ? observacion.value : ''
      };
    });
  }

  function actualizarContadorRecaudos() {
    if (!recaudosTbody) return;
    var toggles = recaudosTbody.querySelectorAll('[data-recaudo-toggle]');
    var marcadas = recaudosTbody.querySelectorAll('[data-recaudo-toggle]:checked').length;
    var totalEl = document.getElementById('recaudosTotalCount');
    var consigEl = document.getElementById('recaudosConsignadosCount');
    if (totalEl) totalEl.textContent = toggles.length;
    if (consigEl) consigEl.textContent = marcadas;
  }

  function dibujarRecaudos(recaudos) {
    if (!recaudosTbody) return;
    recaudosTbody.innerHTML = '';
    if (!recaudos.length) {
      var vacio = document.createElement('tr');
      var celdaVacia = document.createElement('td');
      celdaVacia.colSpan = 3;
      celdaVacia.className = 'text-center py-3 text-support';
      celdaVacia.textContent = 'Completa los pasos anteriores para calcular los recaudos.';
      vacio.appendChild(celdaVacia);
      recaudosTbody.appendChild(vacio);
      actualizarContadorRecaudos();
      return;
    }

    recaudos.forEach(function (recaudo) {
      var estado = estadoRecaudos[recaudo.clave] || { consignado: 0, observacion: '' };
      estadoRecaudos[recaudo.clave] = estado;

      var fila = document.createElement('tr');
      fila.dataset.recaudoRow = '';
      fila.dataset.recaudoClave = recaudo.clave;

      var nombre = document.createElement('td');
      var titulo = document.createElement('div');
      titulo.className = 'fw-semibold';
      titulo.textContent = recaudo.nombre
        + (recaudo.persona_nombre ? ' — ' + recaudo.persona_nombre : '');
      var obligatorios = ['constancia_prosecucion', 'permiso_lopnna', 'autorizacion_retiro', 'cedula_autorizada'];
      if (recaudo.requerido && obligatorios.indexOf(recaudo.codigo) !== -1) {
        var asterisco = document.createElement('span');
        asterisco.className = 'text-danger';
        asterisco.setAttribute('aria-label', 'Obligatorio');
        asterisco.textContent = ' *';
        titulo.appendChild(asterisco);
      }
      nombre.appendChild(titulo);

      var celdaCheck = document.createElement('td');
      celdaCheck.className = 'text-center';
      var marco = document.createElement('div');
      marco.className = 'form-check form-switch d-flex justify-content-center mb-0';
      var toggle = document.createElement('input');
      toggle.className = 'form-check-input';
      toggle.type = 'checkbox';
      toggle.setAttribute('role', 'switch');
      toggle.setAttribute('data-recaudo-toggle', '');
      toggle.checked = !!estado.consignado;
      marco.appendChild(toggle);
      celdaCheck.appendChild(marco);

      var celdaObservacion = document.createElement('td');
      var observacion = document.createElement('input');
      observacion.type = 'text';
      observacion.className = 'form-control form-control-sm';
      observacion.maxLength = 255;
      observacion.placeholder = 'Observación';
      observacion.setAttribute('data-recaudo-observacion', '');
      observacion.value = estado.observacion || '';
      celdaObservacion.appendChild(observacion);

      fila.appendChild(nombre);
      fila.appendChild(celdaCheck);
      fila.appendChild(celdaObservacion);
      recaudosTbody.appendChild(fila);
    });

    actualizarContadorRecaudos();
  }

  function actualizarRecaudos() {
    if (!recaudosTbody) return Promise.resolve();
    guardarEstadoVisibleRecaudos();
    if (familiaresUI) familiaresUI.serializar();

    var solicitud = ++solicitudRecaudos;
    cargandoRecaudos = true;
    errorRecaudos = false;
    var cuerpo = new FormData();
    cuerpo.append('fecha_nacimiento', document.getElementById('fechaNacimiento').value);
    cuerpo.append('id_grado', document.getElementById('gradoCursar').value);
    cuerpo.append('id_institucion', document.getElementById('institucionProcedencia').value);
    cuerpo.append('ultimo_grado_cursado', document.getElementById('ultimoGradoCursado').value);
    cuerpo.append('ingreso_primera_vez', document.getElementById('ingresoPrimeraVez').checked ? '1' : '0');
    cuerpo.append('familiares_json', document.getElementById('familiaresJson').value || '[]');

    return SIGDEUI.enviar(route + 'evaluarRecaudos', { metodo: 'POST', cuerpo: cuerpo })
      .then(function (resultado) {
        if (solicitud !== solicitudRecaudos) return;
        if (!resultado.ok || !resultado.data || !resultado.data.ok
            || !resultado.data.data || !Array.isArray(resultado.data.data.recaudos)) {
          errorRecaudos = true;
          recaudosTbody.innerHTML = '';
          var filaError = document.createElement('tr');
          var celdaError = document.createElement('td');
          celdaError.colSpan = 3;
          celdaError.className = 'text-center py-3 text-danger';
          celdaError.textContent = resultado.data && resultado.data.mensaje
            ? resultado.data.mensaje
            : 'No se pudieron calcular los recaudos. Intenta actualizar la lista.';
          filaError.appendChild(celdaError);
          recaudosTbody.appendChild(filaError);
          SIGDEUI.mostrarError(celdaError.textContent);
          return;
        }
        recaudosActuales = resultado.data.data.recaudos;
        dibujarRecaudos(recaudosActuales);
        if (pasoActual === totalPasos) llenarResumen();
      })
      .catch(function () {
        if (solicitud !== solicitudRecaudos) return;
        errorRecaudos = true;
        SIGDEUI.mostrarError('No se pudo conectar para calcular los recaudos. Revise su conexión e intente de nuevo.');
      })
      .finally(function () {
        if (solicitud === solicitudRecaudos) cargandoRecaudos = false;
      });
  }

  function recalcularRecaudos() {
    guardarEstadoVisibleRecaudos();
    actualizarContadorRecaudos();
  }

  function serializarRecaudos() {
    guardarEstadoVisibleRecaudos();
    var snapshot = {};
    recaudosActuales.forEach(function (recaudo) {
      snapshot[recaudo.clave] = estadoRecaudos[recaudo.clave] || {
        consignado: 0,
        observacion: ''
      };
    });
    var campo = document.getElementById('recaudosJson');
    if (campo) campo.value = JSON.stringify(snapshot);
  }

  if (recaudosTbody) {
    recaudosTbody.addEventListener('change', function (e) {
      var toggle = e.target.closest('[data-recaudo-toggle]');
      if (!toggle) return;
      recalcularRecaudos();
      if (pasoActual === totalPasos) llenarResumen();
    });

    recaudosTbody.addEventListener('input', function (e) {
      if (!e.target.matches('[data-recaudo-observacion]')) return;
      guardarEstadoVisibleRecaudos();
      if (pasoActual === totalPasos) llenarResumen();
    });
  }

  ['fechaNacimiento', 'gradoCursar', 'institucionProcedencia', 'ultimoGradoCursado'].forEach(function (id) {
    var campo = document.getElementById(id);
    if (campo) campo.addEventListener('change', actualizarRecaudos);
  });

  /* ---------- Alta rápida de la institución de procedencia (paso 2) ----------
 Igual que en el wizard de empleados: un modal anidado con el tipo
 (acotado a preescolar/primaria) y el nombre. Al guardar, la institución
 nueva se agrega al <select> de procedencia y queda elegida, para no tener
 que buscarla en la lista.*/
  var btnGuardarNuevaInstProcedencia = document.getElementById('btnGuardarNuevaInstitucionProcedencia');

  if (btnGuardarNuevaInstProcedencia) {
    btnGuardarNuevaInstProcedencia.addEventListener('click', async function () {
      var elNombre = document.getElementById('nuevaInstitucionProcedenciaNombre');
      var elTipo   = document.getElementById('nuevaInstitucionProcedenciaTipo');
      var elEstado = document.getElementById('nuevaInstitucionProcedenciaEstado');
      var elDea    = document.getElementById('nuevaInstitucionProcedenciaDea');
      var nombre   = (elNombre && elNombre.value || '').trim();
      var tipo     = (elTipo && elTipo.value) || '';
      var estado   = (elEstado && elEstado.value) || '';
      var dea      = (elDea && elDea.value || '').trim();
    var extranjera = !!document.querySelector('#nuevaInstitucionProcedenciaExtranjera:checked');

      if (!nombre) {
        SIGDEUI.mostrarAviso('warning', 'Nombre requerido', 'Escribe el nombre de la institución.');
        return;
      }
      if (!tipo) {
        SIGDEUI.mostrarAviso('warning', 'Tipo requerido', 'Selecciona el tipo de institución.');
        return;
      }
      if (!estado && !extranjera) {
        SIGDEUI.mostrarAviso('warning', 'Ubicación requerida', 'Selecciona el estado donde está la institución.');
        return;
      }

      /* Se manda el bloque entero. El servidor ignora el que no aplica y
         limpia esos datos al guardar, así que mandar de más no rompe nada. */


      var payloadInst = {
        nombre: nombre,
        tipo: tipo,
        es_extranjera: extranjera ? '1' : '0'
      };
      if (!extranjera) {
        payloadInst.id_estado_geo = estado;
        payloadInst.id_municipio = municipio;
        payloadInst.id_parroquia = parroquia;
        payloadInst.codigo_dea = dea;
      }

      var creado = await SIGDEUI.guardarCatalogoRapido(
        route + 'guardarInstitucion',
        payloadInst,
        document.getElementById('institucionProcedencia')
      );

      if (creado) {
        if (elNombre) elNombre.value = '';
        if (elTipo) elTipo.value = '';
        if (elDea) elDea.value = '';
        SIGDEUI.ocultarModal('modalNuevaInstitucionProcedencia');
      }
    });
  }

  /* Apertura de los modales anidados (js-abrir-modal-anidado) y restauracion
 del modal-open del wizard: mismo comportamiento que en empleado.js, para
 que "Nueva Institucion" se abra ENCIMA del wizard sin cerrarlo.*/
  document.addEventListener('click', function (evento) {
    var boton = evento.target.closest('.js-abrir-modal-anidado');
    if (!boton) return;

    var destino = boton.dataset.bsTarget ? document.querySelector(boton.dataset.bsTarget) : null;
    if (destino) SIGDEUI.mostrarModal(destino);
  });

  document.addEventListener('hidden.bs.modal', function (evento) {
    if (wizardModalEl && wizardModalEl.classList.contains('show')) {
      document.body.classList.add('modal-open');
    }
  });

  /* Familiares (paso 5). La lista se arma desde cero y todo es editable; el contacto se pregunta
 al representante, a la madre o al padre, y los demás solo requieren el teléfono principal.*/
  var familiaresUI = FamiliaresUI.init({
    baseRoute: route,
    jsonInputId: 'familiaresJson',
    /* En el alta la lista se está armando: nadie tiene datos que proteger.*/
    contactoEditable: function () { return true; },
    alCambiar: function () {
      actualizarRecaudos();
      if (pasoActual === totalPasos) llenarResumen();
    }
  });

  /* ---------- Resumen (paso 7) ----------*/
  function llenarResumen() {
    var f   = new FormData(form);
    var setR = function (campo, valor) {
      var el = wizardModalEl.querySelector('[data-resumen="' + campo + '"]');
      if (el) el.textContent = valor || '—';
    };

    /* Personales*/
    var nombreEstudiante = [f.get('primer_nombre'), f.get('segundo_nombre'), f.get('primer_apellido'), f.get('segundo_apellido')].filter(Boolean).join(' ');
    setR('nombre_completo', SIGDEUI.normalizarNombre(nombreEstudiante));
    var tDoc = f.get('tipo_documento') || '';
    var nDoc = f.get('numero_documento') || '';
    setR('documento', tDoc === 'CE' && !nDoc ? 'Cédula Escolar (Por generar)' : tDoc + '-' + nDoc);
    setR('fecha_nacimiento', f.get('fecha_nacimiento'));
    setR('sexo', f.get('sexo') === 'M' ? 'Masculino' : (f.get('sexo') === 'F' ? 'Femenino' : '—'));

    /* La foto se copia de la vista previa del paso 1 en vez de volver a leer el
 archivo: el <img> de la previa ya tiene un objectURL, y recrearlo aquí
 dejaría dos blob URLs vivos sobre el mismo archivo.*/
    var previaFoto = document.getElementById('fotoPreview');
    var imgResumen = document.getElementById('resumenAvatarPreview');
    var icoResumen = document.getElementById('resumenAvatarIcon');
    var fotoEnPaso1 = previaFoto && previaFoto.querySelector('img');
    if (imgResumen && icoResumen) {
      imgResumen.src = fotoEnPaso1 ? fotoEnPaso1.getAttribute('src') : '';
      imgResumen.hidden = !fotoEnPaso1;
      icoResumen.hidden = !!fotoEnPaso1;
    }

    /* Procedencia*/
    var ingresoPV = document.getElementById('ingresoPrimeraVez');
    if (ingresoPV && ingresoPV.checked) {
      setR('tipo_ingreso', 'Primera vez');
      setR('institucion_anterior', '—');
      setR('ult_grado_cursado', '—');
      setR('anio_escolar_procedencia', '—');
    } else {
      setR('tipo_ingreso', 'Traslado');
      setR('institucion_anterior', textoSeleccionado('institucionProcedencia'));
      setR('ult_grado_cursado', textoSeleccionado('ultimoGradoCursado') || '—');
      var anioProcInput = document.getElementById('anioEscolarProcedencia');
      setR('anio_escolar_procedencia', (anioProcInput && anioProcInput.value) ? anioProcInput.value : '—');
    }

    /* Académico*/
    setR('grado',       textoSeleccionado('gradoCursar'));
    setR('seccion',     textoSeleccionado('seccion'));
    setR('turno',       nombreSencilloDe('turno'));
    setR('anio_escolar', f.get('anio_escolar') || (document.getElementById('anioEscolarAcademico') ? document.getElementById('anioEscolarAcademico').value : '') || '—');

    /* Talla (paso 4)*/
    var resumenTalla = new FormData(form);
    setR('talla_camisa',   resumenTalla.get('talla_camisa'));
    setR('talla_pantalon', resumenTalla.get('talla_pantalon'));
    setR('talla_zapato',   resumenTalla.get('talla_zapato'));

    /* Las alergias y enfermedades no se muestran: de ellas solo interesa si hay
 alguna, para el estado de la ficha.*/
    var alergias = resumenTalla.get('alergias') || '';
    var enfermedades = resumenTalla.get('enfermedades') || '';

    var badgeFM = document.getElementById('badgeFichaMedica');
    if (badgeFM) {
      if (alergias.trim().length > 0 || enfermedades.trim().length > 0) {
        badgeFM.className = 'status-badge status-badge--active';
        badgeFM.textContent = 'Ficha registrada';
      } else {
        badgeFM.className = 'status-badge status-badge--inactive';
        badgeFM.textContent = 'Sin ficha registrada';
      }
    }

    /* Recaudos (paso 6)*/
    var listaDocs = document.getElementById('resumenListaDocumentos');
    if (listaDocs) {
      var htmlDocs = '';
      var filasDocs = form.querySelectorAll('tr[data-recaudo-row]');
      var marcadosDocs = 0;
      var totalDocs = filasDocs.length;
      filasDocs.forEach(function(fila) {
        var chk = fila.querySelector('[data-recaudo-toggle]');
        var recaudo = recaudosActuales.find(function (item) {
          return item.clave === fila.dataset.recaudoClave;
        });
        var nombreDoc = fila.querySelector('.fw-semibold').textContent;
        var tipo = recaudo && recaudo.requerido ? 'Obligatorio' : '';
        var estado = chk && chk.checked ? 'Consignado' : 'Pendiente';
        var detalle = nombreDoc + (tipo ? ' · ' + tipo : '') + ' · ' + estado;
        if (chk && chk.checked) {
          marcadosDocs++;
          htmlDocs += '<li class="mb-2 text-success d-flex align-items-start"><i class="bi bi-check-circle-fill me-2 mt-1"></i><span>' + SIGDEUI.escapeHtml(detalle) + '</span></li>';
        } else {
          htmlDocs += '<li class="mb-2 text-danger d-flex align-items-start"><i class="bi bi-x-circle me-2 mt-1"></i><span class="text-muted">' + SIGDEUI.escapeHtml(detalle) + '</span></li>';
        }
      });
      listaDocs.innerHTML = htmlDocs;
      
      var elProgresoDocs = document.getElementById('resumenDocsProgreso');
      if (elProgresoDocs) elProgresoDocs.textContent = marcadosDocs + ' de ' + totalDocs;
      
      var elProgressBar = document.getElementById('resumenDocsProgressBar');
      if (elProgressBar) {
        var pct = totalDocs > 0 ? (marcadosDocs / totalDocs * 100) : 0;
        elProgressBar.style.width = pct + '%';
        elProgressBar.setAttribute('aria-valuenow', pct);
        elProgressBar.className = pct === 100 ? 'progress-bar bg-success' : 'progress-bar bg-warning';
      }
    }

    /* Familiares*/
    var resumenFam = document.getElementById('resumenFamiliares');
    if (resumenFam) {
      var familiaLista = familiaresUI.lista();
      if (familiaLista.length === 0) {
        resumenFam.innerHTML = '<p class="text-support mb-0">Sin familiares registrados.</p>';
      } else {
        /* Las etiquetas van con .status-badge del design system en vez de
 "badge bg-*" de Bootstrap, y el avatar con las clases de components/avatar.php,
 para que este bloque se vea igual que el resto del resumen.*/
        var insignia = function (texto, tono) {
          return '<span class="status-badge status-badge--' + tono + ' me-1">' + texto + '</span>';
        };

        resumenFam.innerHTML = '<div class="row g-2">' + familiaLista.map(function (f) {
          var badges = '';
          if (f.representante_principal) {
            badges += insignia('Representante legal', 'info');
          }
          if (f.solo_autorizado || f.representante_principal) {
            badges += insignia('Autorizado a retirar', 'success');
          }
          if (f.vive_con_estudiante) {
            badges += insignia('Vive con el estudiante', 'pending');
          }
          if (f.estado_relacion === 'INACTIVO') {
            badges += insignia('Desactivado', 'inactive');
          }

          var parentesco = SIGDEUI.escapeHtml(f.parentesco_texto || 'Familiar');
          var nombre = (f.primer_nombre || '') + ' ' + (f.primer_apellido || '');
          /* Sin nombres propios queda el nombre de referencia que se escribió a mano;
 si tampoco está, se muestra "Familiar" en vez de un "undefined" suelto.*/
          var nombreCompleto = SIGDEUI.escapeHtml(SIGDEUI.normalizarNombre(nombre.trim() || f.nombre_referencia || 'Familiar'));
          var iniciales = SIGDEUI.escapeHtml(
            ((f.primer_nombre || '') + (f.primer_apellido || '')).trim().charAt(0).toUpperCase() || 'F'
          );

          return '<div class="col-sm-6">' +
            '<div class="d-flex align-items-center gap-3 p-2 border rounded h-100">' +
              '<span class="avatar avatar--md avatar--primary">' + iniciales + '</span>' +
              '<div>' +
                '<div class="wizard-summary__label mb-1">' + parentesco + '</div>' +
                '<div class="wizard-summary__value text-truncate">' + nombreCompleto + '</div>' +
                (badges ? '<div class="mt-2">' + badges + '</div>' : '') +
              '</div>' +
            '</div>' +
          '</div>';
        }).join('') + '</div>';
      }
    }
  }

  /* Errores por campo que devuelve el servidor. store() responde 422 con un mapa campo →
 mensaje y antes el JS solo leía el texto genérico, perdiendo los que dicen qué hacer:
 "La sección está llena: 30 de 30 cupos ocupados", "Solo puede registrar estudiantes en su
 turno asignado", "Este documento ya está registrado en el sistema".*/
  var ALIAS_CAMPOS = {
    /* El servidor usa estas dos claves sueltas en errores_combinacion() y las
 escribe con prefijo en el resto de validate(). Apuntan al mismo <select>.*/
    seccion: 'id_seccion',
    turno: 'id_turno'
  };

  /* Fallos que no tienen un input propio (los familiares viven en un sub-modal):
 sí indican un paso al que hay que llevar a la secretaria.*/
  var PASOS_SIN_CAMPO = { familiares: '5', recaudos: '6' };

  /* Limpia las marcas antes de cada reintento.*/
  function limpiarErroresServidor() {
    SIGDEUI.limpiarErroresDeCampos(form);
  }

  function mostrarErroresDelServidor(respuesta) {
    SIGDEUI.mostrarErroresDeCampos(respuesta, {
      form: form,
      alias: ALIAS_CAMPOS,
      pasos: PASOS_SIN_CAMPO,
      titulo: 'No se pudo registrar',
      /* Sin desglose (un 500, un 409 de año cerrado) no hay campo que señalar y el
 único texto útil es el del servidor; si tampoco viene, uno propio.*/
      mensajePorDefecto: 'No se pudo registrar la inscripción.',
      /* El paso sale del panel que contiene el campo: si el wizard se reordena,
 no hay que actualizar ninguna tabla.*/
      alDetectar: function (campo) {
        var panel = campo.closest('.wizard-panel');
        return panel ? panel.dataset.step : null;
      },
      irAPantalla: function (paso) {
        mostrarPaso(Number(paso));
      }
    });
  }

  /* ---------- Envío final ----------*/
  form.addEventListener('submit', function (event) {
    event.preventDefault();
    if (!validarPasoActual()) return;

    // El paso 5 es el único que registra al representante legal, y es
    // obligatorio: si falta, se devuelve al usuario ahí en vez de dejar que
    // el servidor rechace la inscripción.
    if (!familiaresUI.hayRepresentante()) {
      SIGDEUI.mostrarAviso(
        'warning',
        'Falta el representante legal',
        'Debe marcar un familiar como representante legal.'
      );
      mostrarPaso(5);
      return;
    }

    /* El JSON de la lista lo escribe el propio módulo, que es quien la tiene.*/
    familiaresUI.serializar();
    serializarRecaudos();

    var btnFinalizar = document.getElementById('btnWizardFinalizar');

    /* Se limpian los errores que hubiera dejado el intento anterior antes de volver
 a marcar el botón, para que un reintento no mezcle el mensaje viejo con el
 nuevo.*/
    limpiarErroresServidor();

    if (btnFinalizar) {
      btnFinalizar.disabled = true;
      btnFinalizar.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...';
    }

    /* Marca de que la inscripción YA SE GUARDÓ. Con ella el botón deja de ofrecer el alta y pasa a la
 edición, y el formulario ya no se manda como uno nuevo.*/
    var envioExitoso = false;

    SIGDEUI.enviar(route + 'store', { metodo: 'POST', cuerpo: new FormData(form) })
      .then(function (result) {
        if (!result.ok || !result.data || !result.data.ok) {
          mostrarErroresDelServidor(result.data);
          return;
        }
        envioExitoso = true;

        var idNuevo = result.data.data && result.data.data.id_estudiante
          ? parseInt(result.data.data.id_estudiante, 10)
          : 0;

        if (idNuevo > 0 && window.Swal) {
          return Swal.fire({
            icon: 'success',
            title: 'Inscripción registrada',
            text: result.data.mensaje || 'El estudiante quedó registrado.',
            confirmButtonText: 'Descargar ficha',
            cancelButtonText: 'Cerrar',
            showCancelButton: true,
            reverseButtons: true,
            allowOutsideClick: false
          }).then(function (respuesta) {
            if (respuesta.isConfirmed) {
              window.open(
                (window.BASE_URL || '') + 'estudiantes/exportar?id=' + idNuevo + '&descargar=1',
                '_blank'
              );
            }
            window.location.reload();
          });
        }

        return SIGDEUI.exito(result.data.mensaje, 'Inscripción registrada')
          .then(function () { window.location.reload(); });
      })
      .catch(function () {
        /* Si ya se guardó, lo que falló fue el aviso o el reload, no la conexión:
 decir "no se pudo conectar" sería mentira, y sobre todo NO debe quedar
 el botón habilitable, porque el segundo clic es justo lo que aquí se
 está evitando.*/
        if (envioExitoso) return;
        SIGDEUI.mostrarError('No se pudo conectar con el servidor. Revise su conexión e intente de nuevo.');
      })
      .finally(function () {
        if (envioExitoso) return;
        if (btnFinalizar) {
          btnFinalizar.disabled = false;
          btnFinalizar.innerHTML = '<i class="bi bi-check-lg"></i> Confirmar Inscripción';
        }
      });
  });

  // ========== MODAL VER INSCRIPCIÓN ==========
  document.addEventListener('click', function (e) {
    var btnVer = e.target.closest('.btn-ver-inscripcion');
    if (btnVer) {
      e.preventDefault();
      var id = btnVer.getAttribute('data-id');
      var modalBody = document.getElementById('modalVerInscripcionBody');
      var modalEl = document.getElementById('modalVerInscripcion');
      if (modalBody && modalEl) {
        modalBody.innerHTML = '<div class="text-center p-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-2 text-muted">Cargando detalles...</p></div>';
        var bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
        bsModal.show();
        
        fetch(BASE_URL + 'inscripciones/ver?id=' + id, {
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(res) {
          if (!res.ok) throw new Error('Error en la respuesta');
          return res.text();
        })
        .then(function(html) {
          modalBody.innerHTML = html;
        })
        .catch(function(err) {
          modalBody.innerHTML = '<div class="alert alert-danger text-center"><i class="bi bi-exclamation-triangle"></i> Error al cargar la información. Intente nuevamente.</div>';
        });
      }
    }
  });
});
