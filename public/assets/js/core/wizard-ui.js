/* Comportamientos compartidos por los formularios tipo wizard (empleado, estudiante y familiar)
 * y por los sub-modales que cuelgan de ellos. Antes vivían duplicados en cada wizard: al corregir
 * un comportamiento había que acordarse de cambiar los tres sitios.
*/

window.SIGDEWizardUI = (function () {
  'use strict';

  /* ---------- Foto: elegir, previsualizar y quitar ----------*/

  /**
 * Conecta un <input type="file"> de avatar con su preview y su botón de
 * "Quitar foto".
 *
 * Usa URL.createObjectURL() en lugar de FileReader/dataURL: no lee el
 * archivo entero a memoria y la URL anterior se revoca, así que abrir y
 * cerrar el selector repetidamente no deja blobs huérfanos.
 *
 * @param {Object} opciones
 * @param {string} opciones.inputId id del input file
 * @param {string} opciones.previewId id del div donde se pinta la imagen
 * @param {string} [opciones.quitarId] id del botón de quitar (opcional)
 * @param {string} [opciones.inputName]Deprecated y sin efecto: el `name` se lee
 * del propio input, no se deduce. Se
 * mantiene en la firma para no romper
 * llamadas que lo pasarían.
 * @param {string} [opciones.vacio] HTML del estado sin foto
 * @param {Function}[opciones.onChange] se llama tras elegir o quitar
 * @param {Function}[opciones.alReiniciar] se llama al limpiar el formulario
*/
  function initFoto(opciones) {
    var o = opciones || {};
    var input   = document.getElementById(o.inputId);
    var preview = document.getElementById(o.previewId);
    if (!input || !preview) return null;

    var quitar   = o.quitarId ? document.getElementById(o.quitarId) : null;
    var vacio    = o.vacio || '<i class="bi bi-person"></i>';
    var objectUrl = null;

    preview.style.cursor = 'pointer';
    preview.addEventListener('click', function (e) {
      if (e.target.closest('label')) return;
      input.click();
    });

  /* El `name` REAL del input, leído del markup una sola vez al iniciar. Antes se deducía del id
 y, como ningún módulo pasa inputName, quedaba siendo 'fotoInput': al quitar la foto se
 escribía ese valor sobre el atributo y el formulario enviaba un campo que no existía.*/
    var nombreOriginal = input.getAttribute('name') || '';

    /* Vuelve a poner el nombre bueno. Se llama en AMBOS pintados, no solo al quitar:
 si otra cosa del código lo cambiara, elegir una foto nueva lo dejaría en
 cualquier valor y volveríamos al mismo fallo.*/
    function restaurarNombre() {
      if (nombreOriginal) {
        input.setAttribute('name', nombreOriginal);
      }
    }

    function liberar() {
      if (objectUrl) {
        URL.revokeObjectURL(objectUrl);
        objectUrl = null;
      }
    }

    /* El botón de quitar solo se ve cuando HAY foto, así que su visibilidad depende de
 eso y no del motivo del repintado.

 El flag es otra cosa: es lo que le dice al servidor "bórrala" cuando la quitaron
 del formulario. Se separa del botón porque las dos cosas no siempre van juntas —
 `vaciar()` desde `alReiniciar` también repinta a vacío, y ahí lo que se quiere es
 dejarla como estaba, no pedir que se borre.*/
    function pintarBotonQuitar(visible) {
      if (quitar) quitar.hidden = !visible;
    }

    /* Marca si la foto se quitó (1) o si se chose otra / sigue igual (0).
 El flag se busca por el nombre ORIGINAL del input (`<name>Eliminar`) y ya no por
 `id + 'Eliminar'`, que daba 'fotoInputEliminar' y no existía en ningún markup:
 esa parte llevaba tiempo sin hacer nada.*/
    function marcarFotoEliminada(eliminada) {
      var flag = nombreOriginal ? document.getElementById(nombreOriginal + 'Eliminar') : null;
      if (flag) flag.value = eliminada ? '1' : '0';
    }

    function pintarVacia(eliminada) {
      liberar();
      preview.innerHTML = vacio;
      input.value = '';
      restaurarNombre();
      pintarBotonQuitar(false);
      marcarFotoEliminada(eliminada === true);
    }

    function pintarArchivo(archivo) {
      liberar();
      objectUrl = URL.createObjectURL(archivo);
      preview.innerHTML = '<img src="' + objectUrl + '" alt="Vista previa">';
      restaurarNombre();
      pintarBotonQuitar(true);
      marcarFotoEliminada(false);
    }

    input.addEventListener('change', function () {
      var archivo = input.files[0];
      if (archivo) {
        pintarArchivo(archivo);
      } else {
        /* Se canceló el diálogo de archivos: no es que la secretaria haya quitado la
 foto, así que no se marca para que el servidor la borre.*/
        pintarVacia(false);
      }
      if (typeof o.onChange === 'function') o.onChange(archivo || null);
    });

    if (quitar) {
      quitar.addEventListener('click', function () {
        /* Esto sí es una baja explícita: el flag queda en 1 para que el servidor sepa
 que tiene que borrar la imagen anterior.*/
        pintarVacia(true);
        if (typeof o.onChange === 'function') o.onChange(null);
      });
    }

    if (typeof o.alReiniciar === 'function') {
      /* Al reiniciar el formulario la foto tampoco se "quita": se limpia el estado
 del wizard, así que se deja el flag como estaba.*/
      o.alReiniciar(function () { pintarVacia(false); });
    }

    return { vaciar: pintarVacia, alCambiar: function () { return objectUrl; } };
  }

  /* ---------- Tarjetas seleccionables (sexo y similares) ----------*/

  /**
 * Marca la tarjeta .is-selected según el radio que esté marcado dentro de
 * ella. Se sincroniza el grupo completo en cada cambio, para que nunca
 * queden dos tarjetas resaltadas.
 *
 * @param {string} [selector] por defecto '[data-sexo-card]'
*/
  function initTarjetasSeleccionables(selector) {
    var sel = selector || '[data-sexo-card]';
    var tarjetas = document.querySelectorAll(sel);
    if (!tarjetas.length) return;

    function sincronizar() {
      tarjetas.forEach(function (tarjeta) {
        var radio = tarjeta.querySelector('input[type="radio"]');
        if (radio) tarjeta.classList.toggle('is-selected', radio.checked);
      });
    }

    tarjetas.forEach(function (tarjeta) {
      var radio = tarjeta.querySelector('input[type="radio"]');
      if (radio) radio.addEventListener('change', sincronizar);
  /* Al hacer click en la tarjeta (no en el radio) se marca el radio, que dispara 'change' y reentra
 en sincronizar. El radio deshabilitado se respeta a propósito: una persona ya registrada tiene
 el sexo bloqueado.*/
      tarjeta.addEventListener('click', function () {
        var input = tarjeta.querySelector('input[type="radio"]');
        if (input && !input.disabled && !input.checked) input.checked = true;
        sincronizar();
      });
    });

    sincronizar();
  }

  /* ---------- Tipo de documento: solo la letra cuando está cerrado ----------*/

  /**
 * El <select> de tipo de documento muestra únicamente la letra (V, E, CE)
 * mientras está cerrado, y el texto completo ("V - Venezolano",
 * "E - Extranjero", "CE - Cédula escolar") solo al desplegarlo, en el
 * focus. Así el campo ocupa lo mismo que una letra, que es como el
 * estudiante la ve en la cédula, sin perder la claridad de qué está
 * eligiendo.
 *
 * El texto original de cada <option> queda guardado, así que el select
 * siempre puede alternar entre letra y texto completo, y arranca siempre
 * con la letra.
 *
 * @param {Object} opciones
 * @param {string} opciones.selectId id del <select>
*/
  function initTipoDocumentoLetra(opciones) {
    var o = opciones || {};
    var select = o.selectId ? document.getElementById(o.selectId) : null;
    if (!select) return;

    var originales = Array.from(select.options).map(function (opcion) {
      return { value: opcion.value, texto: opcion.textContent };
    });

    function pintar(mostrarTexto) {
      // Se guarda el valor porque reescribir el textContent de las opciones
      // puede resetear la selección en algunos navegadores.
      var valorActual = select.value;

      originales.forEach(function (opcion, i) {
        select.options[i].textContent = mostrarTexto ? opcion.texto : opcion.value;
      });

      select.value = valorActual;
    }

    pintar(false);                      // arranca mostrando solo la letra
    select.addEventListener('focus', function () { pintar(true); });
    select.addEventListener('blur', function () { pintar(false); });
    select.addEventListener('change', function () { pintar(false); });
  }

  /* ---------- Filtros de escritura ----------*/

  /**
 * Deja pasar solo dígitos, hasta 7, en los inputs de teléfono.
 *
 * El componente phone-input.php ya pone pattern="[0-9]{7}" y maxlength="7",
 * que hacen que checkValidity() marque el campo; esto evita además que la
 * letra llegue a escribirse.
 *
 * @param {string} [selector] por defecto 'input[pattern="[0-9]{7}"]'
*/
  function initSoloDigitos(selector) {
    var sel = selector || 'input[pattern="[0-9]{7}"]';
    document.addEventListener('input', function (e) {
      if (!e.target.matches(sel)) return;
      var limpio = e.target.value.replace(/\D/g, '').slice(0, 7);
      if (limpio !== e.target.value) e.target.value = limpio;
    });
  }

  /**
 * Solo letras y espacios en los campos de nombre/apellido.
 *
 * @param {string} [selector] por defecto '#primerNombre, #segundoNombre, #primerApellido, #segundoApellido'
*/
  function initSoloLetras(selector) {
    var sel = selector || '#primerNombre, #segundoNombre, #primerApellido, #segundoApellido';
    document.addEventListener('input', function (e) {
      if (!e.target.matches(sel)) return;
      var limpio = e.target.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/g, '');
      if (limpio !== e.target.value) e.target.value = limpio;
    });
  }

  /**
 * Textos de cada nivel de la cascada. Mientras falta el padre, el select no
 * solo queda deshabilitado: su única opción explica qué hay que elegir
 * primero, para que se entienda que no está roto.
*/
  var TEXTOS_UBICACION = {
    municipio: { esperando: 'Seleccione primero el estado', cargando: 'Cargando municipios...', vacio: 'Sin municipios registrados', elegir: 'Seleccione municipio' },
    parroquia: { esperando: 'Seleccione primero el municipio', cargando: 'Cargando parroquias...', vacio: 'Sin parroquias registradas', elegir: 'Seleccione parroquia' }
  };

  function textoUbicacion(tipo, clave) {
    return (TEXTOS_UBICACION[tipo] || {})[clave] || 'Seleccione...';
  }

  /**
 * Cascada estado -> municipio -> parroquia de la DIRECCIÓN.
 *
 * Está en el módulo compartido porque la usan dos wizards: el de empleados
 * (alta y edición) y el de inscripciones, en el sub-modal de familiares. Es
 * el mismo endpoint, el mismo comportamiento y los mismos textos.
 *
 * Los tres <select> están habilitados desde el principio. Los municipios y las
 * parroquias se piden completos (334 y 42 filas) una sola vez por formulario
 * y cada opción trae el id de su padre (data-id-estado / data-id-municipio);
 * al elegir arriba, el de abajo se queda solo con las opciones que dependen
 * de esa elección, usando el MISMO filtro de los títulos por grado
 * (SIGDEUI.filtrarOpciones).
 *
 * Antes se pedía el nivel hijo al elegir el padre y por eso el municipio
 * arrancaba deshabilitado con "Seleccione primero el estado". Con listas
 * completas no hace falta: se elige en cualquier orden y el filtro se encarga
 * de que la combinación siga siendo válida.
 *
 * @param {object} opciones
 * @param {string} opciones.estadoId id del <select> de estado.
 * @param {string} opciones.municipioId id del <select> de municipio.
 * @param {string} opciones.parroquiaId id del <select> de parroquia.
 * @param {string} opciones.url endpoint que devuelve el nivel.
 * @returns {object} API: { cargando, elegirParroquia, reiniciar }.
*/
  function initCascadaUbicacion(opciones) {
    var selEstado    = document.getElementById(opciones.estadoId);
    var selMunicipio = document.getElementById(opciones.municipioId);
    var selParroquia = document.getElementById(opciones.parroquiaId);
    /* Se acepta el endpoint con o sin '?' final y se normaliza acá: armarlo con
 dos produce "?tipo=municipio" con el signo de interrogación dentro del
 nombre del parámetro, y el servidor no ve el tipo.*/
    var url = String(opciones.url || '').replace(/[?&]+$/, '');
    var etiqueta = function (campo) { return (campo && campo.value) || ''; };
      var sinFiltro = function (select) {
        /* Deshace lo que hace SIGDEUI.filtrarOpciones. Tiene que limpiar el
 `disabled` además del `hidden`: si solo limpiara el `hidden`, las
 opciones seguirían sin poder elegirse con el ratón aunque se vieran,
 que es el mismo tipo de desincronización que se corrigió en el
 filtro.*/
        Array.prototype.forEach.call(select.options, function (o) {
          o.hidden = false;
          o.disabled = false;
        });
      };

    if (!selEstado || !selMunicipio || !selParroquia || !url) {
      return {
        cargando: Promise.resolve(false),
        elegirParroquia: function () { return Promise.resolve(false); },
        reiniciar: function () {}
      };
    }

    /**
 * Al elegir arriba, abajo solo deja pasar lo que le corresponde. Si la
 * elección que había abajo deja de ser válida, el propio filtro la limpia
 * (y por eso hay que filtrar también el nieto: quedó sin elegir).
*/
      function filtrarHijos() {
        var hayMunicipios = SIGDEUI.filtrarOpciones(selMunicipio, etiqueta(selEstado), 'data-id-estado');
        var hayParroquias = SIGDEUI.filtrarOpciones(selParroquia, etiqueta(selMunicipio), 'data-id-municipio');

        explicarVacio(selMunicipio, hayMunicipios, !etiqueta(selEstado) ? 'Seleccione el estado primero' : '');
        explicarVacio(selParroquia, hayParroquias, !etiqueta(selMunicipio) ? 'Seleccione el municipio primero' : 'Sin parroquías para este municipio');
      }

      /**
 * Pone el texto del placeholder según si el nivel tiene algo que mostrar.
 *
 * Un <select> sin opciones visibles parece una cascada rota, y aquí va a
 * pasar: el catálogo de parroquías solo tiene datos de dos municipios, así
 * que para los demás la lista queda vacía de verdad. Mejor decirlo en el
 * campo que dejar que quien registra piense que se rompió la página. El
 * texto original se guarda la primera vez y se restaura cuando el nivel
 * vuelve a tener opciones.
*/
      function explicarVacio(select, hay, texto) {
        if (!select) return;
        var vacia = select.options[0];
        if (!vacia) return;
        if (hay || !texto) {
          if (vacia.dataset.textoOriginal) vacia.textContent = vacia.dataset.textoOriginal;
          return;
        }
        if (!vacia.dataset.textoOriginal) vacia.dataset.textoOriginal = vacia.textContent;
        vacia.textContent = texto;
      }

    /**
 * Pide un nivel completo, lo deja en el <select> sin filtro y lo habilita.
 * No deja el select deshabilitado esperando al padre: el padre ya no hace
 * falta para traer los datos, solo para filtrarlos.
*/
    function pedirNivel(select, tipo, marcador) {
      return SIGDEUI.enviar(url + '?tipo=' + encodeURIComponent(tipo))
        .then(function (r) {
          if (!r.ok || !r.data || !r.data.ok || !Array.isArray(r.data.data)) {
            SIGDEUI.mostrarError((r.data && r.data.mensaje) || 'No se pudieron cargar las opciones.', 'Ubicación');
            SIGDEUI.rellenarSelect(select, [], marcador);
            return false;
          }
          SIGDEUI.rellenarSelect(select, r.data.data, r.data.data.length ? marcador : 'Sin opciones');
          sinFiltro(select);
          select.disabled = false;
          return r.data.data.length > 0;
        })
        .catch(function () {
          SIGDEUI.rellenarSelect(select, [], 'Sin opciones');
          SIGDEUI.mostrarError('Fallo de conexión al cargar las opciones de ubicación.', 'Ubicación');
          return false;
        });
    }

    /* Las dos listas se piden en paralelo: no hay dependencia entre ellas.*/
    var cargando = Promise.all([
      pedirNivel(selMunicipio, 'municipio', 'Seleccione el municipio'),
      pedirNivel(selParroquia, 'parroquia', 'Seleccione la parroquia')
    ]).then(function (r) {
      filtrarHijos();
      return r[0] && r[1];
    });

    /* Los dos cambios pasan por filtrarHijos(), no solo por filtrarOpciones()
 del nivel que toca: el texto del placeholder se actualiza ahí, y con el
 atajo anterior la parroquia se quedaba diciendo "seleccione el municipio
 primero" con el municipio ya elegido.*/
    selEstado.addEventListener('change', filtrarHijos);
    selMunicipio.addEventListener('change', filtrarHijos);

    /**
 * Deja la cascada con los tres niveles elegidos, para cuando ya se sabe la
 * parroquia (por ejemplo, al cargar los datos de un familiar que está
 * registrado). Se eligen de arriba hacia abajo para que cada filtro vea el
 * valor del padre; al terminar se comprueba que los tres quedaron, porque
 * asignar a un <select> un valor que no está en la lista no da error: lo
 * ignora en silencio.
 *
 * @param {number} estadoId
 * @param {number} municipioId
 * @param {number} parroquiaId
 * @returns {Promise<boolean>} true si los tres niveles quedaron elegidos.
*/
    function elegirParroquia(estadoId, municipioId, parroquiaId) {
      return cargando.then(function () {
        if (estadoId > 0) selEstado.value = String(estadoId);
        if (municipioId > 0) selMunicipio.value = String(municipioId);
        filtrarHijos();
        if (parroquiaId > 0) selParroquia.value = String(parroquiaId);

        return (!estadoId    || selEstado.value === String(estadoId))
            && (!municipioId || selMunicipio.value === String(municipioId))
            && (!parroquiaId || selParroquia.value === String(parroquiaId));
      });
    }

  /* Deja un nivel como recién llegado: sin valor y con la lista filtrada por el padre que haya.
 Antes hacía `sinFiltro` y se veía al abrir el formulario: el sub-modal del familiar y el del
 empleado llamaban a reiniciar esperando una lista vacía.*/
      function reiniciar(select) {
        if (!select) return;
        select.selectedIndex = 0;
        filtrarHijos();
        select.disabled = false;
      }

    return { cargando: cargando, elegirParroquia: elegirParroquia, reiniciar: reiniciar };
  }

  /* ---------- Cascada del lugar de NACIMIENTO del estudiante ----------

  Los tres selects los imprime components/lugar-nacimiento-form.php y son los mismos
  en el alta de inscripción y en el modal de edición de ratificación, porque las dos
  pantallas reutilizan el paso 1. Los ids vivían escritos en enrollments.js, y como esa
  pantalla no se carga en ratificación el modal de edición se quedaba sin cascada: al
  elegir la entidad federal no cargaban ni los municipios ni las parroquias.

  Con los ids en un solo lugar, los dos módulos llaman a esta función con SU endpoint y
  no pueden quedar desincronizados. El endpoint se recibe como parámetro justamente por
  eso: no es el mismo en los dos módulos. */
  function initCascadaNacimiento(url) {
    return initCascadaUbicacion({
      url: url,
      estadoId: 'nacimientoEstado',
      municipioId: 'nacimientoMunicipio',
      parroquiaId: 'nacimientoParroquia'
    });
  }

  /* ---------- Cámara: tomar la foto con la webcam o la cámara del móvil ----------

  Un <input type="file"> solo abre el selector de archivos en escritorio; la cámara
  únicamente aparece en móviles y de forma implícita. Para que "Tomar foto" exista
  en todos los formularios de persona (empleado, estudiante, familiar y edición) se
  usa getUserMedia y se entrega la captura como un File al input que ya existía,
  disparando su 'change'. Así la vista previa, el "Quitar foto" y el envío siguen
  funcionando sin duplicar lógica en cada módulo.

  Cualquier botón con data-foto-camara="<id del input>" activa la cámara. Si el
  navegador no la permite (HTTP, permisos denegados, equipo sin cámara) se cae al
  selector nativo, que en móviles ya ofrece la opción de cámara. */

  /**
   * Coloca un archivo en el input y dispara 'change' para que el manejador que ya
   * tenga el formulario lo procese (preview, estado interno, subida inmediata...).
   *
   * @param {HTMLInputElement} input
   * @param {File|Blob} archivo
   * @returns {boolean} true si el navegador permitió asignarlo
   */
  function asignarArchivo(input, archivo) {
    try {
      var transferencia = new DataTransfer();
      transferencia.items.add(archivo);
      input.files = transferencia.files;
    } catch (e) {
      /* Sin DataTransfer no se puede inyectar el archivo: se usa el selector nativo. */
      input.click();
      return false;
    }

    input.dispatchEvent(new Event('change', { bubbles: true }));
    return true;
  }

  /**
   * Abre un overlay con la cámara, permite capturar un fotograma y lo entrega al input.
   *
   * @param {HTMLInputElement} input
   */
  function abrirCamara(input) {
    if (!input) return;

    if (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== 'function') {
      input.click();
      return;
    }

    var overlay = document.createElement('div');
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.setAttribute('aria-label', 'Tomar foto');
    overlay.style.cssText = 'position:fixed;inset:0;z-index:2000;display:flex;align-items:center;justify-content:center;padding:1rem;background:rgba(10,20,64,.85);';

    var caja = document.createElement('div');
    caja.style.cssText = 'width:min(92vw,560px);background:#fff;border-radius:.75rem;overflow:hidden;box-shadow:0 1rem 3rem rgba(0,0,0,.4);';

    var titulo = document.createElement('div');
    titulo.textContent = 'Tomar foto';
    titulo.style.cssText = 'padding:.75rem 1rem;font-weight:600;color:#0a1440;border-bottom:1px solid #e5e7eb;';

    var escenario = document.createElement('div');
    escenario.style.cssText = 'position:relative;background:#000;';

    var video = document.createElement('video');
    video.setAttribute('autoplay', '');
    video.setAttribute('playsinline', '');
    video.muted = true;
    video.style.cssText = 'display:block;width:100%;max-height:60vh;object-fit:cover;';

    var acciones = document.createElement('div');
    acciones.style.cssText = 'display:flex;flex-wrap:wrap;gap:.5rem;justify-content:flex-end;padding:.75rem 1rem;';

    var btnCambiar = document.createElement('button');
    btnCambiar.type = 'button';
    btnCambiar.className = 'btn btn-outline-primary btn-sm me-auto';
    btnCambiar.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i>Cambiar cámara';
    btnCambiar.hidden = true;

    var btnCancelar = document.createElement('button');
    btnCancelar.type = 'button';
    btnCancelar.className = 'btn btn-outline-secondary btn-sm';
    btnCancelar.textContent = 'Cancelar';

    var btnCapturar = document.createElement('button');
    btnCapturar.type = 'button';
    btnCapturar.className = 'btn btn-primary btn-sm';
    btnCapturar.innerHTML = '<i class="bi bi-camera me-1"></i>Capturar';

    escenario.appendChild(video);
    acciones.appendChild(btnCambiar);
    acciones.appendChild(btnCancelar);
    acciones.appendChild(btnCapturar);
    caja.appendChild(titulo);
    caja.appendChild(escenario);
    caja.appendChild(acciones);
    overlay.appendChild(caja);
    document.body.appendChild(overlay);

    var stream = null;
    var cerrado = false;
    var cambiando = false;
    /* Por defecto la trasera: quien registra a otra persona suele fotografiarla
    de frente. El botón "Cambiar cámara" permite pasar a la frontal. */
    var camaraActual = 'environment';

    function detenerStream() {
      if (stream) {
        stream.getTracks().forEach(function (pista) { pista.stop(); });
        stream = null;
      }
    }

    function cerrar() {
      if (cerrado) return;
      cerrado = true;
      detenerStream();
      document.removeEventListener('keydown', alTeclear);
      if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
    }

    function alTeclear(evento) {
      if (evento.key === 'Escape') cerrar();
    }

    /* Muestra el botón de cambio solo cuando el equipo tiene más de una cámara.
    enumerateDevices necesita el permiso ya concedido, por eso se consulta
    después de abrir el primer stream. */
    function actualizarBotonCambiar() {
      if (!navigator.mediaDevices || typeof navigator.mediaDevices.enumerateDevices !== 'function') return;
      navigator.mediaDevices.enumerateDevices()
        .then(function (dispositivos) {
          var camaras = dispositivos.filter(function (d) { return d.kind === 'videoinput'; });
          if (!cerrado) btnCambiar.hidden = camaras.length < 2;
        })
        .catch(function () {});
    }

    /* Abre la cámara pedida. En un cambio se detiene antes la anterior (en
    móviles no se puede tener abiertas las dos). Si el cambio falla se
    recupera la cámara que estaba funcionando. */
    function iniciarStream(camara, esCambio) {
      if (cerrado || cambiando) return;
      cambiando = true;
      btnCambiar.disabled = true;
      detenerStream();

      navigator.mediaDevices.getUserMedia({
        audio: false,
        video: { facingMode: { ideal: camara } }
      })
        .then(function (capturado) {
          cambiando = false;
          btnCambiar.disabled = false;
          if (cerrado) {
            capturado.getTracks().forEach(function (pista) { pista.stop(); });
            return;
          }
          stream = capturado;
          camaraActual = camara;
          video.srcObject = capturado;
          var reproduccion = video.play();
          if (reproduccion && typeof reproduccion.catch === 'function') {
            reproduccion.catch(function () {});
          }
          actualizarBotonCambiar();
        })
        .catch(function () {
          cambiando = false;
          btnCambiar.disabled = false;
          if (esCambio) {
            iniciarStream(camaraActual, false);
            return;
          }
          cerrar();
          if (window.SIGDEUI && typeof SIGDEUI.mostrarError === 'function') {
            SIGDEUI.mostrarError('No se pudo abrir la cámara. Revisa los permisos del navegador o selecciona una foto.', 'Cámara no disponible');
          }
          input.click();
        });
    }

    document.addEventListener('keydown', alTeclear);
    overlay.addEventListener('click', function (evento) {
      if (evento.target === overlay) cerrar();
    });
    btnCancelar.addEventListener('click', cerrar);

    btnCambiar.addEventListener('click', function () {
      if (cambiando) return;
      iniciarStream(camaraActual === 'environment' ? 'user' : 'environment', true);
    });

    iniciarStream(camaraActual, false);

    btnCapturar.addEventListener('click', function () {
      if (!video.videoWidth || !video.videoHeight) return;

      var lienzo = document.createElement('canvas');
      lienzo.width = video.videoWidth;
      lienzo.height = video.videoHeight;
      lienzo.getContext('2d').drawImage(video, 0, 0, lienzo.width, lienzo.height);

      lienzo.toBlob(function (blob) {
        if (!blob) {
          cerrar();
          return;
        }
        var nombre = 'foto_camara_' + Date.now() + '.jpg';
        var archivo;
        try {
          archivo = new File([blob], nombre, { type: 'image/jpeg' });
        } catch (e) {
          archivo = blob;
          archivo.name = nombre;
        }
        cerrar();
        asignarArchivo(input, archivo);
      }, 'image/jpeg', 0.92);
    });
  }

  /* Delegación global: sirve para cualquier botón, aunque su formulario no use
  initFoto (por ejemplo el modal de edición de empleado). */
  document.addEventListener('click', function (evento) {
    var boton = evento.target && evento.target.closest
      ? evento.target.closest('[data-foto-camara]')
      : null;
    if (!boton) return;
    evento.preventDefault();
    abrirCamara(document.getElementById(boton.getAttribute('data-foto-camara')));
  });

  return {
    initCascadaNacimiento: initCascadaNacimiento,
    initFoto: initFoto,
    abrirCamara: abrirCamara,
    initTarjetasSeleccionables: initTarjetasSeleccionables,
    initSoloDigitos: initSoloDigitos,
    initSoloLetras: initSoloLetras,
    initTipoDocumentoLetra: initTipoDocumentoLetra,
    initCascadaUbicacion: initCascadaUbicacion
  };
})();
