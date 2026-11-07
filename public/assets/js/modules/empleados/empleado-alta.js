/* SIGDE - modules/empleados/empleado-alta.js
 * Wizard "Registrar Nuevo Empleado": navegación de 6 pasos, validación de cada paso, foto,
 * cascada de dirección, títulos académicos (con sus modales de alta rápida) y resumen.
 * El <form> finalmente se envía a empleado/store con el mismo patrón de submit
 * (spinner + fetch + error con detalles) que los demás.
 *
 * Depende de core/sigde-ui.js, core/wizard-ui.js y de este mismo directorio, empleado-comun.js.*/

(function () {
  'use strict';

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
  var cascadaUbicacion = SIGDEEmpleados.iniciarCascadaUbicacion();

  // ---------- Búsqueda y Precarga de Persona Existente por Cédula (paso 1) ----------
  var inputCedula = formNuevo.querySelector('#numeroDocumento');
  var avisoPersona = document.getElementById('avisoPersonaExistente');
  var avisoTexto = document.getElementById('avisoPersonaExistenteTexto');
  var cedulaCargada = null;

  function limpiarAvisoPersona() {
    if (avisoPersona) {
      avisoPersona.classList.add('d-none');
      avisoPersona.classList.remove('alert-info', 'alert-warning', 'alert-danger');
    }
    if (avisoTexto) avisoTexto.innerHTML = '';
  }

  function precargarDatosPersona(pData) {
    if (!pData || !pData.persona) return;
    var per = pData.persona;

    var setVal = function (sel, val) {
      var el = formNuevo.querySelector(sel);
      if (el && val !== undefined && val !== null) el.value = val;
    };

    // Paso 1: Personales
    setVal('#primerNombre', per.primer_nombre || '');
    setVal('#segundoNombre', per.segundo_nombre || '');
    setVal('#primerApellido', per.primer_apellido || '');
    setVal('#segundoApellido', per.segundo_apellido || '');
    if (per.tipo_documento) setVal('#tipoDocumento', per.tipo_documento);
    if (per.fecha_nacimiento) setVal('#fechaNacimiento', per.fecha_nacimiento);
    if (per.id_estado_nacimiento) setVal('#nacimientoEstado', per.id_estado_nacimiento);
    if (per.id_nacionalidad) setVal('#nacionalidad', per.id_nacionalidad);

    if (per.sexo) {
      var radioSexo = formNuevo.querySelector('input[name="sexo"][value="' + per.sexo + '"]');
      if (radioSexo) {
        radioSexo.checked = true;
        radioSexo.dispatchEvent(new Event('change', { bubbles: true }));
      }
    }

    // Paso 2: Contacto
    if (pData.contacto) {
      var con = pData.contacto;
      setVal('#codigo_telefono_principal', con.codigo || '');
      setVal('#numero_telefono_principal', con.numero || '');
      setVal('#codigo_telefono_alternativo', con.codigo_alt || '');
      setVal('#numero_telefono_alternativo', con.numero_alt || '');
      setVal('#correoElectronico', con.correo || '');
    }

    // Paso 3: Dirección
    if (pData.direccion) {
      var dir = pData.direccion;
      setVal('#calleAvenida', dir.calle || '');
      setVal('#nroCasaApto', dir.casa || '');
      setVal('#sectorUrbanizacion', dir.sector || '');
      setVal('#punto_referencia', dir.referencia || '');

      if (cascadaUbicacion && typeof cascadaUbicacion.elegirParroquia === 'function') {
        cascadaUbicacion.elegirParroquia(
          Number(dir.estado || 0),
          Number(dir.municipio || 0),
          Number(dir.parroquia || 0)
        );
      }
    }
  }

  var buscarPersonaPorCedula = (function () {
    var timer = null;
    return function () {
      if (!inputCedula) return;
      var docLimpio = inputCedula.value.replace(/\D/g, '');

      if (timer) clearTimeout(timer);

      if (docLimpio.length < 7 || docLimpio.length > 8) {
        if (cedulaCargada && docLimpio !== cedulaCargada) {
          limpiarAvisoPersona();
          cedulaCargada = null;
        }
        return;
      }

      if (docLimpio === cedulaCargada) return;

      timer = setTimeout(function () {
        var url = SIGDEEmpleados.baseUrl + SIGDEEmpleados.rutaControlador + '/buscarPersona?documento=' + encodeURIComponent(docLimpio);
        SIGDEUI.enviar(url, { metodo: 'GET' })
          .then(function (r) {
            if (!r.ok || !r.data || !r.data.ok) {
              limpiarAvisoPersona();
              return;
            }

            var encontrado = r.data.data;
            if (!encontrado) {
              limpiarAvisoPersona();
              cedulaCargada = docLimpio;
              return;
            }

            cedulaCargada = docLimpio;

            if (encontrado.es_empleado === true) {
              if (avisoPersona && avisoTexto) {
                avisoPersona.className = 'alert alert-warning py-2 px-3 small mt-2 d-flex align-items-center gap-2';
                avisoTexto.innerHTML = '<strong>Atención:</strong> Esta persona ya se encuentra registrada como empleado en el sistema.';
                avisoPersona.classList.remove('d-none');
              }
              SIGDEUI.mostrarAviso('warning', 'Empleado ya registrado', 'Ya existe un empleado en nómina con la cédula indicada.');
              return;
            }

            var nom = [
              encontrado.persona && encontrado.persona.primer_nombre,
              encontrado.persona && encontrado.persona.primer_apellido
            ].filter(Boolean).join(' ');

            if (avisoPersona && avisoTexto) {
              avisoPersona.className = 'alert alert-info py-2 px-3 small mt-2 d-flex align-items-center gap-2';
              avisoTexto.innerHTML = '<strong>Persona encontrada:</strong> ' + SIGDEUI.escapeHtml(nom) + ' ya está registrada en el sistema. Se precargaron sus datos personales, contacto y dirección. Complete los datos laborales y académicos.';
              avisoPersona.classList.remove('d-none');
            }

            precargarDatosPersona(encontrado);
          })
          .catch(function () {
            limpiarAvisoPersona();
          });
      }, 400);
    };
  })();

  if (inputCedula) {
    inputCedula.addEventListener('input', buscarPersonaPorCedula);
    inputCedula.addEventListener('change', buscarPersonaPorCedula);
  }

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
      celdaFecha.textContent = t.fecha_obtencion ? SIGDEUI.formatearFechaCorta(t.fecha_obtencion) : '—';

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
      SIGDEEmpleados.baseUrl + SIGDEEmpleados.rutaControlador + '/' + accion,
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

  /* Arma el bloque de procedencia según el interruptor de institución
     extranjera. Este módulo solo registra universidades, así que el código DEA
     nunca aplica: se manda vacío y el modelo lo guarda NULL. */
  function construirProcedencia(estado) {
    var esExtranjera = !!document.querySelector('#nuevaInstitucionExtranjera:checked');

    if (esExtranjera) {
      return {
        es_extranjera: '1'
      };
    }

    return {
      es_extranjera: '0',
      id_estado_geo: estado || '',
      id_municipio: (document.getElementById('nuevaInstitucionMunicipio') || {}).value || '',
      id_parroquia: (document.getElementById('nuevaInstitucionParroquia') || {}).value || ''
    };
  }

  var btnNuevaInstitucion = document.getElementById('btnGuardarNuevaInstitucion');
  if (btnNuevaInstitucion) {
    btnNuevaInstitucion.addEventListener('click', async function () {
      var elNombre = document.getElementById('nuevaInstitucionNombre');
      var elTipo   = document.getElementById('nuevaInstitucionTipo');
      var elEstado = document.getElementById('nuevaInstitucionEstado');
      var nombre = (elNombre && elNombre.value || '').trim();
      var tipo   = (elTipo && elTipo.value) || '';
      var estado = (elEstado && elEstado.value) || '';
      var extranjera = !!document.querySelector('#nuevaInstitucionExtranjera:checked');
      if (!nombre) {
        SIGDEUI.mostrarAviso('warning', 'Nombre requerido', 'Escribe el nombre de la institución.');
        return;
      }
      if (!tipo) {
        SIGDEUI.mostrarAviso('warning', 'Tipo requerido', 'Selecciona el tipo de institución.');
        return;
      }
      if (!extranjera && !estado) {
        SIGDEUI.mostrarAviso('warning', 'Ubicación requerida', 'Selecciona el estado donde está la institución.');
        return;
      }

      /* Se manda el bloque entero según el interruptor. El servidor ignora el
         que no aplica y limpia esos datos al guardar, así que mandarlos
         always no rompe nada; mandarlos solo del camino activo es más claro. */
      var creado = await guardarCatalogoRapido(
        'guardarInstitucion',
        Object.assign({ nombre: nombre, tipo: tipo }, construirProcedencia(estado)),
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
      item.textContent = [t.titulo, t.grado, t.institucion, t.fecha_obtencion ? SIGDEUI.formatearFechaCorta(t.fecha_obtencion) : '']
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
        case 'fecha_nacimiento': valor = SIGDEUI.formatearFechaCorta(textoCampo('fecha_nacimiento')); break;
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
        case 'fecha_ingreso': valor = SIGDEUI.formatearFechaCorta(textoCampo('fecha_ingreso')); break;
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
      var res = await SIGDEUI.enviar(SIGDEEmpleados.baseUrl + SIGDEEmpleados.rutaControlador + '/store', { metodo: 'POST', cuerpo: formData });
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
    limpiarAvisoPersona();
    cedulaCargada = null;

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

  // --- Inicialización Módulo ---
  function init() {
    initWizardRegistro();
  }

  // Inicialización segura del DOM que evita problemas cuando los scripts
  // se cargan con 'defer', 'async' o al final de la plantilla layout.
  if (document.readyState === 'interactive' || document.readyState === 'complete') {
    init();
  } else {
    document.addEventListener('DOMContentLoaded', init);
  }
})();
