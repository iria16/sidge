/* SIGDE - módulo de Ratificación de Matrícula.
 * El listado se refresca por AJAX y main.js reemplaza el <tbody>, así que los botones de fila
 * se enganchan por DELEGACIÓN en document: enganchados al cargar, los que llegan con el
 * tbody nuevo quedan sin listener y el clic no hace nada.*/
document.addEventListener('DOMContentLoaded', function () {
  let modalPadreActivo = null;
  const modalRatificarEl = document.getElementById('modalRatificar');
  const modalVerEl = document.getElementById('modalVerRatificacion');
  const verBody = document.getElementById('ratificacionVerBody');

  const bsModalRatificar = modalRatificarEl
    ? bootstrap.Modal.getOrCreateInstance(modalRatificarEl)
    : null;

  /* Cascada del lugar de nacimiento del estudiante. Este módulo reutiliza el paso 1 de
     la inscripción, así que los tres selects están en su formulario, pero enrollments.js
     —donde estaba inicializada— no se carga aquí: quedaban sin cascada y al elegir la
     entidad federal no cargaba ni un municipio.

     Se crea una sola vez, al cargar la página, y no al abrir el modal: si se creara al
     abrir, cada apertura dejaría un listener de change encima del anterior y cada
     cambio pediría los municipios dos veces. Los ids salen de wizard-ui.js para que los
     dos módulos no puedan desincronizarse. */
  if (!window.SIGDECascadaNacimiento && window.SIGDEWizardUI && SIGDEWizardUI.initCascadaNacimiento) {
    window.SIGDECascadaNacimiento = SIGDEWizardUI.initCascadaNacimiento(
      BASE_URL + 'ratificacion/getUbicacion'
    );
  }

  /* Cascada grado → sección → turno. Las opciones salen de las combinaciones del año activo que
 el servidor ya imprimió: dependen del año, no del alumno. Cada <option> lleva su clave
 compuesta y el navegador filtra por prefijo, como en enrollments.js.*/
  const COMBINACIONES = (function () {
    const nodo = document.getElementById('ratificarCombinaciones');
    if (!nodo) return [];
    try {
      return JSON.parse(nodo.textContent || '[]');
    } catch (e) {
      return [];
    }
  })();

  const selGrado = document.getElementById('ratificarGradoNuevo');
  const selSeccion = document.getElementById('ratificarSeccionNuevo');
  const selTurno = document.getElementById('ratificarTurnoNuevo');

  /* Un <option> por combinación, no por sección o turno sueltos: el value va
 siendo el id del nivel (lo que se manda al servidor) y el dato de a qué
 combinación pertenece va aparte, en data-clave.*/
  function llenar(select, clavePrevia, pares) {
    if (!select) return;
    select.innerHTML = '';
    pares.forEach(function (par) {
      const opcion = document.createElement('option');
      opcion.value = String(par.id);
      opcion.dataset.clave = par.clave;
      opcion.textContent = par.texto;
      select.appendChild(opcion);
    });
    if (clavePrevia) select.value = String(clavePrevia);
  }

  function textoTurno(c) {
    return c.turno + ' (' + c.ocupados + '/' + c.capacidad + ')'
      + (c.disponibles <= 0 ? ' · sin cupo' : '');
  }

  /* Secciones por grado, pedidas al servidor. Grado → sección va por AJAX porque depende del
 grado, que cambia por alumno, y traer el año entero sería enviar la estructura completa
 para descartar casi todo. Sección → turno se resuelve en el navegador con COMBINACIONES,
 que son pocas y ya están en la página. La petición lleva el año del campo oculto y el
 servidor responde 409 si no coincide con el destino.*/

  const avisoSeccion = document.getElementById('ratificarSeccionAviso');
  /* Un token por petición: si la secretaria cambia de grado dos veces rápido, la
 respuesta de la primera puede llegar después de la segunda y Pintar secciones
 del grado que ya no está elegido.*/
  let peticionSecciones = 0;

  function mostrarAvisoSeccion(texto) {
    if (!avisoSeccion) return;
    if (!texto) {
      avisoSeccion.hidden = true;
      avisoSeccion.textContent = '';
      return;
    }
    avisoSeccion.hidden = false;
    avisoSeccion.textContent = texto;
  }

  /* idSeccionPrevio / idTurnoPrevio son para la apertura del modal: el alumno
 venía de una sección y un turno concretos, y esa es la sugerencia que se marca.
 Se aplican DESPUÉS de que llegan las secciones, porque antes no hay <option>
 donde ponerlos y el value se perdería en silencio.*/
  const avisoGrado = document.getElementById('ratificarGradoAviso');

  function nombreAnioDestino() {
    const el = document.getElementById('ratificarAnioDestinoId');
    return (el && el.dataset.nombre) || 'este período';
  }

  function mostrarAvisoGrado(texto) {
    if (!avisoGrado) return;
    if (!texto) {
      avisoGrado.hidden = true;
      avisoGrado.textContent = '';
      return;
    }
    avisoGrado.hidden = false;
    avisoGrado.textContent = texto;
  }

  async function cargarSecciones(idGrado, idSeccionPrevio, idTurnoPrevio) {
    const anioEl = document.getElementById('ratificarAnioDestinoId');
    const anio = anioEl ? anioEl.value : '';

    if (!idGrado) {
      llenar(selSeccion, null, []);
      mostrarAvisoSeccion('Elija primero un grado.');
      cascadaSeccion(idTurnoPrevio);
      return;
    }

    const token = ++peticionSecciones;
    if (selSeccion) selSeccion.disabled = true;
    mostrarAvisoSeccion('Cargando secciones...');

    try {
      const r = await SIGDEUI.enviar(
        BASE_URL + 'ratificacion/secciones?grado=' + encodeURIComponent(idGrado)
        + '&anio=' + encodeURIComponent(anio),
        { headers: { 'X-Requested-With': 'XMLHttpRequest' } }
      );
      const cuerpo = await r.json();
      /* Si llegó una respuesta vieja, se descarta: la pantalla tiene que mostrar
 siempre el grado que está elegido ahora.*/
      if (token !== peticionSecciones) return;

      if (!cuerpo.ok) {
        llenar(selSeccion, null, []);
        mostrarAvisoSeccion(cuerpo.mensaje || 'No se pudieron cargar las secciones.');
        cascadaSeccion(idTurnoPrevio);
        return;
      }

      const secciones = (cuerpo.data || []).map(function (s) {
        return { id: s.id, clave: String(idGrado) + '-' + s.id, texto: s.nombre };
      });

      llenar(selSeccion, idSeccionPrevio, secciones);

      /* Lista vacía: ese grado no tiene estructura en este período. No es un error
 de red, y la secretaria tiene que verlo como tal —es el aviso de que aún no
 se puede ratificar en ese grado— en lugar de un <select> mudo sin
 explicación.*/
      mostrarAvisoSeccion(secciones.length
        ? ''
        : 'Ese grado no tiene secciones registradas en ' + nombreAnioDestino() + '.');

      cascadaSeccion(idTurnoPrevio);
    } catch (e) {
      if (token !== peticionSecciones) return;
      llenar(selSeccion, null, []);
      mostrarAvisoSeccion('No se pudieron cargar las secciones. Revise la conexión.');
      cascadaSeccion(idTurnoPrevio);
    } finally {
      /* Solo se rehabilita el select si la respuesta que llegó es la vigente: si no,
 dejaría habilitado un select que pertenece a otra petición todavía en
 curso.*/
      if (token === peticionSecciones && selSeccion) selSeccion.disabled = false;
    }
  }

  function cascadaGrado() {
    /* El aviso del grado predictivo desaparece en cuanto la secretaria elige uno:
 dejarlo pegado diría que el grado sigue sin estar disponible cuando ya no es
 cierto, y ese aviso es la única explicación de por qué el select vino vacío.*/
    mostrarAvisoGrado('');
    cargarSecciones(selGrado ? selGrado.value : '');
  }

  function cascadaSeccion(idTurnoPrevio) {
    const idGrado = selGrado ? selGrado.value : '';
    const idSeccion = selSeccion ? selSeccion.value : '';
    const turnos = [];
    COMBINACIONES.forEach(function (c) {
      if (String(c.id_grado) !== String(idGrado)) return;
      if (String(c.id_seccion) !== String(idSeccion)) return;
      turnos.push({
        id: c.id_turno,
        clave: String(c.id_grado) + '-' + c.id_seccion + '-' + c.id_turno,
        texto: textoTurno(c),
      });
    });
    llenar(selTurno, idTurnoPrevio, turnos);
  }

  if (selGrado) selGrado.addEventListener('change', function () { cascadaGrado(); });
  if (selSeccion) selSeccion.addEventListener('change', function () { cascadaSeccion(); });

  /* ---------- Encuesta de ratificación: eventos dinámicos ---------- */
  document.querySelectorAll('input[name="tiene_canaima"]').forEach(function (radio) {
    radio.addEventListener('change', function () {
      const bSerial = document.getElementById('bloqueSerialCanaima');
      const inpSerial = document.getElementById('serialCanaima');
      if (bSerial) {
        if (this.value === '1') {
          bSerial.style.display = 'block';
          if (inpSerial) inpSerial.focus();
        } else {
          bSerial.style.display = 'none';
          if (inpSerial) inpSerial.value = '';
        }
      }
    });
  });

  document.querySelectorAll('input[name="tiene_ingreso_sige"]').forEach(function (radio) {
    radio.addEventListener('change', function () {
      const bSige = document.getElementById('bloqueDetallesSige');
      if (bSige) {
        if (this.value === '1') {
          bSige.style.display = 'flex';
        } else {
          bSige.style.display = 'none';
          const f = document.getElementById('fechaIngresoSige');
          const m = document.getElementById('motivoSige');
          if (f) f.value = '';
          if (m) m.value = '';
        }
      }
    });
  });

  /* ---------- Ver detalle de una ratificación ----------
 El botón "ver" solo se pinta cuando el estudiante ya está ratificado, así
 que el cuerpo llega con datos y no hay estado que deducir desde la fila.

 El cuerpo se pide a ratificacion/ver, que es la MISMA vista que se abriría
 como página: esa vista detecta la cabecera X-Requested-With y responde solo
 el cuerpo. Es lo mismo que hace inscripción con inscripciones/ver, y evita
 mantener dos versiones del detalle que se desincronizan.*/
  function abrirDetalle(btn) {
    if (!modalVerEl || !verBody) return;

    const id = btn.getAttribute('data-id');

    verBody.innerHTML = '<div class="text-center p-5">'
      + '<div class="spinner-border text-primary" role="status"></div>'
      + '<p class="mt-2 text-support">Cargando detalle...</p></div>';

    bootstrap.Modal.getOrCreateInstance(modalVerEl).show();

    fetch(BASE_URL + 'ratificacion/ver?id=' + encodeURIComponent(id), {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(function (res) {
        if (!res.ok) throw new Error('Error en la respuesta');
        return res.text();
      })
      .then(function (html) { verBody.innerHTML = html; })
      .catch(function () {
        verBody.innerHTML = '<div class="alert alert-danger text-center mb-0">'
          + '<i class="bi bi-exclamation-triangle"></i> No se pudo cargar el detalle. Intente nuevamente.'
          + '</div>';
      });
  }

/* ---------- Preparar el modal de ratificar ----------
 Llena lo que se ve con los datos de la fila y deja el id listo para el
 POST. El grado, la sección y el turno vienen SUGERIDOS por el modelo (el
 siguiente grado, la misma sección y el mismo turno) pero la secretaria los
 puede cambiar; el año es el único que no se elige.*/
  async function abrirRatificar(btn) {

    if (!modalRatificarEl) return;

    const id = btn.getAttribute('data-id');
    const inputId = document.getElementById('ratificarEstudianteId');
    if (inputId) inputId.value = id;

    const row = btn.closest('tr');
    if (!row) {
      bsModalRatificar.show();
      return;
    }

    const nombre    = row.querySelector('.avatar-group__name')?.textContent ?? '';
    const cedula    = row.querySelector('.avatar-group__meta')?.textContent ?? '';
    const avatarSrc = row.querySelector('.avatar img')?.src;
    const iniciales = row.querySelector('.avatar:not(:has(img))')?.textContent;
    const estado    = row.querySelector('.status-badge')?.textContent.trim() ?? '';

    const poner = (idEl, valor) => {
      const el = document.getElementById(idEl);
      if (el) el.textContent = valor;
    };
    /* El destino (año, grado, sección y turno) lo calcula el modelo y viaja en
 atributos data- de la fila. No se arma en el navegador de propósito: el
 destino es una decisión de negocio, y antes se ponía el año del reloj con
 new Date(), que no es el año escolar. Todos vienen escapados por
 htmlspecialchars() en la vista.*/
    const dato = (attr, porDefecto) => {
      const v = btn.getAttribute('data-' + attr);
      return (v && v.trim() !== '' && v !== '—') ? v : porDefecto;
    };

    poner('ratificarNombre', nombre);
    poner('ratificarCedula', cedula);
    poner('ratificarAnioActual', dato('anio-origen', '—'));
    poner('ratificarEstatus', estado);

    /* La justificación se limpia en cada apertura: si quedó texto de un alumno anterior y la
 secretaria no lo ve, acabaría guardándose en la matrícula equivocada.*/
    const motivo = document.getElementById('ratificarMotivo');
    if (motivo) motivo.value = '';

    const avatarSpan = document.getElementById('ratificarAvatarInitials');
    const avatarImg = document.getElementById('ratificarAvatarImg');
    if (avatarSrc && avatarImg) {
      avatarImg.src = avatarSrc;
      avatarImg.classList.remove('d-none');
      if (avatarSpan) avatarSpan.textContent = '';
    } else if (avatarImg) {
      avatarImg.classList.add('d-none');
      if (avatarSpan) avatarSpan.textContent = iniciales ?? '';
    }

    /* Guardar datos para responder a "¿Repite grado?" y resetear la encuesta */
    if (modalRatificarEl) {
      modalRatificarEl.dataset.gradoOrigenId = dato('grado-origen-id', '0');
      modalRatificarEl.dataset.gradoOrigenNombre = dato('grado-origen', 'el grado actual');
      modalRatificarEl.dataset.gradoDestinoId = dato('grado-destino-id', '0');
      modalRatificarEl.dataset.gradoDestinoNombre = dato('grado-destino', 'el grado siguiente');
      modalRatificarEl.dataset.seccionDestinoId = dato('seccion-destino-id', '0');
      modalRatificarEl.dataset.turnoDestinoId = dato('turno-destino-id', '0');
    }

    const radCanaimaNo = document.getElementById('tieneCanaimaNo');
    if (radCanaimaNo) radCanaimaNo.checked = true;
    const bSerial = document.getElementById('bloqueSerialCanaima');
    if (bSerial) bSerial.style.display = 'none';
    const inpSerial = document.getElementById('serialCanaima');
    if (inpSerial) inpSerial.value = '';

    const radSigeNo = document.getElementById('tieneIngresoSigeNo');
    if (radSigeNo) radSigeNo.checked = true;
    const bSige = document.getElementById('bloqueDetallesSige');
    if (bSige) bSige.style.display = 'none';
    const inpFechaSige = document.getElementById('fechaIngresoSige');
    if (inpFechaSige) inpFechaSige.value = '';
    const inpMotivoSige = document.getElementById('motivoSige');
    if (inpMotivoSige) inpMotivoSige.value = '';

  /* Grado, sección y turno sí se eligen: por defecto el que corresponde (siguiente grado, misma
 sección, mismo turno), pero la secretaria puede cambiar los tres. El año no va en un
 <select>: es el destino, único para la pantalla, y lo imprime el servidor.*/
    if (selGrado) {
      llenar(selGrado, null, COMBINACIONES.filter(function (c, i, todas) {
        return todas.findIndex(function (o) { return o.id_grado === c.id_grado; }) === i;
      }).map(function (c) {
        return { id: c.id_grado, clave: String(c.id_grado), texto: c.grado };
      }));

      /* El grado predicho (el siguiente al que cursa) puede NO existir en la
 estructura del año destino: en una escuela que solo tiene 1er y 2do, quien
 venía de 2do no tiene a dónde pasar. Se deja el <select> en blanco y se
 explica, porque elegir por la secretaria la primera opción —que es lo que
 haría un fallback silencioso— matricularía al alumno en un grado que nadie
 eligió. Aquí la decisión es de ella: puede repetir el mismo grado, que es el
 caso legítimo.*/
      const gradoSugerido = dato('grado-destino-id', '0');
      const existeSugerido = !!gradoSugerido
        && Array.prototype.some.call(selGrado.options, function (o) { return o.value === String(gradoSugerido); });

      selGrado.value = existeSugerido ? String(gradoSugerido) : '';

      mostrarAvisoGrado(existeSugerido ? '' : (gradoSugerido
        ? 'A este alumno le tocaría ' + dato('grado-destino', 'el grado siguiente')
          + ', que no está disponible en ' + nombreAnioDestino() + '. Elija el grado que cursará.'
        : 'Elija el grado que cursará el alumno.'));
    }

    bsModalRatificar.show();

    /* Las secciones llegan por AJAX, así que la sugerencia se aplica después y no
 en la línea siguiente: si se pusiera el value antes de que existan los
 <option>, el navegador lo descartaría en silencio y la secretaria vería la
 primera sección del grado, que no es la del alumno.*/
    await cargarSecciones(
      selGrado ? selGrado.value : '',
      dato('seccion-destino-id', '0'),
      dato('turno-destino-id', '0')
    );
  }


  /* ---------- Un solo enganche para los botones de fila ----------*/
  document.addEventListener('click', function (e) {
    const verBtn = e.target.closest('.btn-ver-ratificacion');
    if (verBtn) {
      abrirDetalle(verBtn);
      return;
    }

    const ratificarBtn = e.target.closest('.btn-ratificar-modal');
    if (ratificarBtn) {
      abrirRatificar(ratificarBtn);
      return;
    }

    const editStudentBtn = e.target.closest('.student-record__edit');
    if (editStudentBtn) {
      abrirEditor(
        editStudentBtn.getAttribute('data-id'),
        editStudentBtn.getAttribute('data-tab'),
        editStudentBtn.hasAttribute('data-edit-representante')
      );
      return;
    }

    const btnEditar = e.target.closest('.btn-editar-datos');
    if (btnEditar) {
      const id = btnEditar.dataset.estudianteId
        || (document.getElementById('ratificarEstudianteId') || {}).value
        || (document.getElementById('reingresoIdEstudiante') || {}).value;
      const modalOrigen = btnEditar.closest('.modal');
      if (modalOrigen) {
        modalOrigen.dataset.enEdicion = '1';
      }
      abrirEditor(id, btnEditar.dataset.tabInicial, btnEditar.hasAttribute('data-edit-representante'), modalOrigen);
    }
  });

  /* ---------- Arrastrar los filtros al POST de ratificar ----------
 La URL ya tiene la búsqueda y los filtros que la secretaria puso (main.js
 los va escribiendo). Se copian a los campos ocultos del formulario para que
 ratificar devuelva el listado con el mismo filtro y no haya que escribirlo
 de nuevo.*/
  const parametros = new URLSearchParams(window.location.search);
  ['q', 'grado', 'seccion', 'turno', 'page'].forEach(function (campo) {
    const destino = document.getElementById('ratificarFiltro' + campo);
    if (destino && parametros.has(campo)) {
      destino.value = parametros.get(campo);
    }
  });

  /* ---------- Lógica del modal de editar estudiante ----------*/
  const modalEditarEl = document.getElementById('modalEditarEstudiante');

  /* ---------- Resumen de la confirmación ----------
 Se arma con lo que el modelo ya calculó y con lo que la secretaria dejó
 marcado. El año no se lista: es el año escolar en curso y no se elige.*/
  function resumen() {
    const grado = document.getElementById('ratificarGradoNuevo');
    const seccion = document.getElementById('ratificarSeccionNuevo');
    const turno = document.getElementById('ratificarTurnoNuevo');
    const nombre = document.getElementById('ratificarNombre');
    const deDonde = document.getElementById('ratificarAnioActual');
    /* El año es un <input readonly>, no un <div>: se lee con .value, no con
 .textContent. El texto visible ya lo pone el servidor.*/
    const anioEnCurso = document.getElementById('ratificarAnioEnCurso');
    const anioDestino = anioEnCurso ? anioEnCurso.value : '';

    return [
      nombre ? nombre.textContent : '',
      [deDonde ? deDonde.textContent : '', '→', anioDestino].filter(Boolean).join(' '),
      [grado ? grado.value : '', seccion ? seccion.value : '', turno ? turno.value : ''].filter(Boolean).join(' · '),
    ].filter(Boolean).join('   |   ');
  }

  /* ---------- Enviar la ratificación ----------
 El flujo es el del resto del sistema: se pregunta con SweetAlert2, se manda
 por AJAX y se avisa el resultado. Nada de recargar la página: el panel se
 refresca con form.refreshDataPanel(), que conserva la búsqueda y los filtros
 con los que la secretaria venía trabajando.*/
  /* Evita el doble envío con un flag por acción, no un estado común: un doble clic o un Enter
 repetido abría dos diálogos y, si ambos se confirmaban, dos POST compitiendo por el
 último cupo. Un flag y no solo `botón.disabled` porque el botón vive fuera del <form> y el
 submit también salta con Enter desde cualquier campo.*/
  let enviandoRatificacion = false;
  let enviandoDatos = false;

  /* El botón se bloquea ANTES de `await SIGDEUI.confirmar()`. Puesto después, el
 `await` devuelve el control al navegador con el botón todavía vivo: esa es
 exactamente la ventana del doble clic.*/
  function bloquearBoton(boton, html) {
    if (!boton) return;
    boton.disabled = true;
    boton.innerHTML = html;
  }

  function desbloquearBoton(boton, html) {
    if (!boton) return;
    boton.disabled = false;
    boton.innerHTML = html;
  }

  async function enviarRatificacion() {
    const form = document.getElementById('formRatificar');
    const id = (document.getElementById('ratificarEstudianteId') || {}).value;

    if (!form || !id) {
      SIGDEUI.mostrarError('No se sabe qué estudiante se va a ratificar.');
      return;
    }

    /* La segunda pulsación mientras la primera sigue en vuelo se descarta aquí y
 no en el servidor: comprobarlo allá costaría una transacción bloqueando un
 cupo para devolver un error que ya se sabe.*/
    if (enviandoRatificacion) return;
    enviandoRatificacion = true;

    /* Si la inscripción YA quedó registrada, el botón no vuelve a habilitarse: el
 modal se está cerrando y la tabla va a refrescar. Rehabilitarlo en ese
 instante es justo la ventana que dejó pasar dos POST.*/
    let guardada = false;

    const btnEnviar = document.querySelector('button[form="formRatificar"]');
    const htmlOriginal = btnEnviar ? btnEnviar.innerHTML : '';

    try {
      /* El año de destino viaja en el POST aunque el formulario lo muestre bloqueado:
 un <input readonly> no protege nada por sí solo, y el servidor necesita el id
 para saber a qué período se ratifica. El modelo lo contrasta con el año que
 él resuelve.*/
      const tieneCanaimaVal = document.querySelector('input[name="tiene_canaima"]:checked')?.value || '0';
      const serialCanaimaVal = (document.getElementById('serialCanaima')?.value || '').trim();
      const tieneSigeVal = document.querySelector('input[name="tiene_ingreso_sige"]:checked')?.value || '0';
      const fechaSigeVal = (document.getElementById('fechaIngresoSige')?.value || '').trim();
      const motivoSigeVal = (document.getElementById('motivoSige')?.value || '').trim();

      if (tieneCanaimaVal === '1' && !serialCanaimaVal) {
        SIGDEUI.mostrarError('Debe ingresar el serial del equipo Canaima.');
        enviandoRatificacion = false;
        return;
      }

      const cuerpo = new URLSearchParams({
        id: id,
        id_grado: (document.getElementById('ratificarGradoNuevo') || {}).value || '',
        id_seccion: (document.getElementById('ratificarSeccionNuevo') || {}).value || '',
        id_turno: (document.getElementById('ratificarTurnoNuevo') || {}).value || '',
        id_anio_escolar: (document.getElementById('ratificarAnioDestinoId') || {}).value || '',
        motivo: (document.getElementById('ratificarMotivo') || {}).value || '',
        tiene_canaima: tieneCanaimaVal,
        serial_canaima: serialCanaimaVal,
        tiene_ingreso_sige: tieneSigeVal,
        fecha_ingreso_sige: fechaSigeVal,
        motivo_sige: motivoSigeVal,
      });

      bloquearBoton(btnEnviar,
        '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Ratificando...');

      const confirmado = await SIGDEUI.confirmar({
        icono: 'warning',
        titulo: '¿Ratificar matrícula?',
        texto: resumen() + '. Quedará pendiente hasta que el directivo lo apruebe.',
        confirmar: 'Sí, ratificar',
      });
      if (!confirmado) return;

      const sobre = await SIGDEUI.enviar(BASE_URL + 'ratificacion/ratificar', { metodo: 'POST', cuerpo: cuerpo });

      if (!sobre.ok || !sobre.data || sobre.data.ok === false) {
        SIGDEUI.mostrarAviso('error', 'No se pudo ratificar',
          (sobre.data && sobre.data.mensaje) || 'Intente nuevamente.');
        return;
      }

      /* Se marca ANTES del aviso: si el SweetAlert falla, el catch no debe dejar el
 botón habilitable. El `finally` se encarga de no tocarlo en ese caso.*/
      guardada = true;

      SIGDEUI.exito(sobre.data.mensaje || 'Ratificación registrada.', '¡Listo!');

      /* El panel se refresca al cerrar el modal, para que el SweetAlert no se
 monte encima de la tabla que todavía no cambió.*/
      if (modalRatificarEl) {
        SIGDEUI.alCerrarModal(modalRatificarEl, function refrescar() { refrescarPanel(); });
        bootstrap.Modal.getOrCreateInstance(modalRatificarEl).hide();
        return;
      }
      refrescarPanel();
    } catch (err) {
      console.error('Error al ratificar:', err);
      SIGDEUI.mostrarError('No se pudo conectar con el servidor.');
    } finally {
      enviandoRatificacion = false;
      if (!guardada) desbloquearBoton(btnEnviar, htmlOriginal);
    }
  }

  /* Refresca la tabla conservando filtros y página; si la pantalla no expone el
 formulario de filtros, recarga, que es el respaldo de todo el sistema.*/
  function refrescarPanel() {
    const form = document.querySelector('form.data-panel__filters, form.auto-filters');
    if (form && typeof form.refreshDataPanel === 'function') {
      form.refreshDataPanel();
      return;
    }
    window.location.reload();
  }

  const formRatificar = document.getElementById('formRatificar');
  if (formRatificar) {
    formRatificar.addEventListener('submit', function (e) {
      e.preventDefault();
      enviarRatificacion();
    });
  }

  /* Editar datos del estudiante. Solo lo que la secretaria puede cambiar de una ficha ya
 cargada; el resto viene bloqueado y no viaja en el payload.*/
  function poner(campo, valor) {
    const el = document.getElementById(campo);
    if (!el) return;
    if (el.type === 'checkbox' || el.type === 'radio') {
      el.checked = !!valor;
    } else {
      el.value = valor == null ? '' : valor;
    }
  }

  /* Los expedientes viejos pueden tener una talla que ya no está en el catálogo
     (por ejemplo, pantalón "M"). Se conserva como opción heredada al editar para
     que abrir el modal y guardar no borre el dato sin que nadie lo cambie. */
  function ponerTalla(campo, valor) {
    const el = document.getElementById(campo);
    const actual = valor == null ? '' : String(valor);
    if (el && el.tagName === 'SELECT' && actual !== ''
        && !Array.from(el.options).some(function (opcion) { return opcion.value === actual; })) {
      const opcion = document.createElement('option');
      opcion.value = actual;
      opcion.textContent = actual;
      el.appendChild(opcion);
    }
    poner(campo, actual);
  }

  function marcarSexo(sexo) {
    const radio = document.querySelector('#pane-personales input[name="sexo"][value="' + sexo + '"]');
    if (radio) radio.checked = true;
  }

/* Iniciales para el avatar. Es el mismo criterio que sigde_iniciales() en
 app/Views/helpers/ui.php, replicado aquí porque JS no puede llamar a una
 función de PHP: primera y última palabra, primera letra de cada una.*/
  function inicialesDe(nombre) {
    const partes = String(nombre || '').trim().split(/\s+/).filter(Boolean);
    if (!partes.length) return 'SG';
    const primera = partes[0][0] || '';
    const ultima = partes.length > 1 ? partes[partes.length - 1][0] : '';
    return (primera + ultima || 'SG').toUpperCase();
  }

  function pintarFoto(foto) {
    const preview = document.getElementById('fotoPreview');
    const btnQuitar = document.getElementById('btnQuitarFotoEstudiante');
    const flagEliminar = document.getElementById('fotoEliminar');
    const inputFoto = document.getElementById('fotoInput');

    if (inputFoto) inputFoto.value = '';
    if (flagEliminar) flagEliminar.value = '0';

    if (foto) {
      if (preview) {
        preview.innerHTML = '<img src="' + BASE_URL + String(foto).replace(/^\//, '') + '" alt="Foto del estudiante">';
      }
      if (btnQuitar) btnQuitar.hidden = false;
    } else {
      if (preview) {
        preview.innerHTML = '<i class="bi bi-person"></i>';
      }
      if (btnQuitar) btnQuitar.hidden = true;
    }
  }

  let restriccionesNumeroDocumento = null;

  /* El paso 1 del wizard esconde el número de documento cuando el tipo es cédula
 escolar, porque lo genera el sistema. Aquí NO: el alumno ya tiene su cédula
 y solo se está corrigiendo un dato. Se conserva el número asignado y se
 muestra de solo lectura para no aplicar la validación V/E de 7-8 dígitos.*/
  function sincronizarTipoDocumento() {
    const select = document.getElementById('tipoDocumento');
    const numero = document.getElementById('numeroDocumento');
    if (!select || !numero) return;

    if (restriccionesNumeroDocumento === null) {
      restriccionesNumeroDocumento = {
        pattern: numero.getAttribute('pattern'),
        minlength: numero.getAttribute('minlength'),
        maxlength: numero.getAttribute('maxlength'),
        title: numero.getAttribute('title')
      };
    }

    const esEscolar = select.value === 'CE';
    numero.placeholder = esEscolar ? 'Cédula escolar asignada' : 'Ej: 25415866';
    numero.readOnly = esEscolar;

    if (esEscolar) {
      numero.removeAttribute('required');
      numero.removeAttribute('pattern');
      numero.removeAttribute('minlength');
      numero.removeAttribute('maxlength');
      numero.removeAttribute('title');
      numero.classList.remove('is-invalid');
      return;
    }

    numero.required = true;
    Object.keys(restriccionesNumeroDocumento).forEach(function (atributo) {
      const valor = restriccionesNumeroDocumento[atributo];
      if (valor === null) numero.removeAttribute(atributo);
      else numero.setAttribute(atributo, valor);
    });
  }

  function inicializarPuntosEdicion() {
    SIGDEWizardUI.initTipoDocumentoLetra({ selectId: 'tipoDocumento' });
    SIGDEWizardUI.initTarjetasSeleccionables('[data-sexo-card]');
    SIGDEWizardUI.initSoloLetras();
    SIGDEWizardUI.initFoto({
      inputId: 'fotoInput',
      previewId: 'fotoPreview',
      quitarId: 'btnQuitarFotoEstudiante'
    });

    const selectDoc = document.getElementById('tipoDocumento');
    if (selectDoc) selectDoc.addEventListener('change', sincronizarTipoDocumento);

    /* Los bloques plegables de tallas y ficha médica. enrollments.js lo acota al
 modal del wizard (línea 615) y aquí no existe, así que se hace sobre el
 documento: los ids (#extraBody-talla, #extraBody-medica) no chocan con
 nada de la página de ratificación.*/
    document.querySelectorAll('[data-extra-toggle]').forEach(function (boton) {
      boton.addEventListener('click', function () {
        const bloque = document.getElementById('extraBody-' + boton.dataset.extraToggle);
        if (!bloque) return;
        const plegado = bloque.hasAttribute('hidden');
        if (plegado) bloque.removeAttribute('hidden'); else bloque.setAttribute('hidden', '');
        boton.setAttribute('aria-expanded', plegado ? 'true' : 'false');
      });
    });
  }

  let recaudosBaseEstudiante = [];
  let idEstudianteActual = 0;
  let representanteInicial = null;

  function sincronizarRecaudosDinamicos() {
    const panelRec = document.getElementById('recaudosModalEditar');
    if (!panelRec || !window.SIGDERecaudos) return;

    // 1. Guardar estado visible actual marcado en la tabla
    const estadosActuales = {};
    panelRec.querySelectorAll('[data-recaudo-row]').forEach(function (tr) {
      const toggle = tr.querySelector('[data-recaudo-toggle]');
      const obs = tr.querySelector('[data-recaudo-observacion]');
      if (toggle) {
        const key = toggle.getAttribute('data-recaudo-toggle');
        estadosActuales[key] = {
          consignado: toggle.checked ? 1 : 0,
          observacion: obs ? obs.value : ''
        };
      }
    });

    const familiares = familiaresUI.lista();
    const repActual = familiares.find(function (f) {
      return (Number(f.representante_principal) === 1 || f.representante_principal === true)
        && f.estado_relacion !== 'INACTIVO'
        && !f.es_fallecido;
    });

    const repNombre = repActual ? (repActual.primer_nombre + ' ' + (repActual.primer_apellido || '')).trim() : '';
    const parentescoRep = repActual ? (repActual.parentesco || '').toLowerCase().trim() : '';
    const esPadreMadre = ['padre', 'madre', 'papa', 'papá', 'mama', 'mamá'].indexOf(parentescoRep) !== -1;
    const exigeLopnna = !!(repActual && !esPadreMadre);

    const repInicialDoc = (representanteInicial && representanteInicial.numero_documento)
      ? String(representanteInicial.numero_documento).replace(/\D/g, '')
      : '';
    const repActualDoc = (repActual && repActual.numero_documento)
      ? String(repActual.numero_documento).replace(/\D/g, '')
      : '';
    const cambioDeRepresentante = repActualDoc !== '' && repInicialDoc !== '' && repActualDoc !== repInicialDoc;

    // 2. Filtrar autorizados para retiro que NO sean el representante ni padre/madre
    const autorizadosRetiro = familiares.filter(function (f) {
      if (f.estado_relacion === 'INACTIVO' || f.es_fallecido) return false;
      if (Number(f.representante_principal) === 1 || f.representante_principal === true) return false;
      const p = (f.parentesco || '').toLowerCase().trim();
      if (['padre', 'madre', 'papa', 'papá', 'mama', 'mamá'].indexOf(p) !== -1) return false;
      return (Number(f.autorizado_retiros) === 1 || f.autorizado_retiros === true);
    });

    // 3. Reconstruir lista limpia de recaudos
    const lista = [];

    // 3.1 Documentos fijos del alumno
    recaudosBaseEstudiante.forEach(function (r) {
      if (['partida_nacimiento', 'foto_alumno', 'constancia_vacuna', 'constancia_prosecucion', 'cedula_alumno'].indexOf(r.codigo) !== -1) {
        const item = Object.assign({}, r);
        if (estadosActuales[item.id]) {
          item.consignado = estadosActuales[item.id].consignado;
          item.observacion = estadosActuales[item.id].observacion;
        }
        lista.push(item);
      }
    });

    // 3.2 Foto y Cédula del Representante Legal (SIEMPRE con el nombre del representante actual)
    const baseFotoRep = recaudosBaseEstudiante.find(function (r) { return r.codigo === 'foto_representante'; });
    const fotoKey = cambioDeRepresentante ? 'tipo_5' : (baseFotoRep ? baseFotoRep.id : 'tipo_5');
    lista.push({
      id: fotoKey,
      codigo: 'foto_representante',
      nombre: 'Fotos del representante legal',
      requerido: 0,
      consignado: estadosActuales[fotoKey] !== undefined
        ? estadosActuales[fotoKey].consignado
        : (cambioDeRepresentante ? 0 : (baseFotoRep ? Number(baseFotoRep.consignado) : 0)),
      observacion: estadosActuales[fotoKey] !== undefined
        ? estadosActuales[fotoKey].observacion
        : (cambioDeRepresentante ? '' : (baseFotoRep ? (baseFotoRep.observacion || '') : '')),
      persona_nombre: repNombre,
      evaluado: 1
    });

    const baseCedulaRep = recaudosBaseEstudiante.find(function (r) { return r.codigo === 'cedula_representante'; });
    const cedulaKey = cambioDeRepresentante ? 'tipo_9' : (baseCedulaRep ? baseCedulaRep.id : 'tipo_9');
    lista.push({
      id: cedulaKey,
      codigo: 'cedula_representante',
      nombre: 'Cédula del representante legal',
      requerido: 0,
      consignado: estadosActuales[cedulaKey] !== undefined
        ? estadosActuales[cedulaKey].consignado
        : (cambioDeRepresentante ? 0 : (baseCedulaRep ? Number(baseCedulaRep.consignado) : 0)),
      observacion: estadosActuales[cedulaKey] !== undefined
        ? estadosActuales[cedulaKey].observacion
        : (cambioDeRepresentante ? '' : (baseCedulaRep ? (baseCedulaRep.observacion || '') : '')),
      persona_nombre: repNombre,
      evaluado: 1
    });

    // 3.3 Permiso de la LOPNNA: SOLO si el representante actual no es padre ni madre
    if (exigeLopnna) {
      const baseLopnna = recaudosBaseEstudiante.find(function (r) { return r.codigo === 'permiso_lopnna'; });
      const lopnnaKey = cambioDeRepresentante ? 'tipo_12' : (baseLopnna ? baseLopnna.id : 'tipo_12');
      lista.push({
        id: lopnnaKey,
        codigo: 'permiso_lopnna',
        nombre: 'Permiso de la LOPNNA',
        requerido: 1,
        consignado: estadosActuales[lopnnaKey] !== undefined
          ? estadosActuales[lopnnaKey].consignado
          : (cambioDeRepresentante ? 0 : (baseLopnna ? Number(baseLopnna.consignado) : 0)),
        observacion: estadosActuales[lopnnaKey] !== undefined
          ? estadosActuales[lopnnaKey].observacion
          : (cambioDeRepresentante ? '' : (baseLopnna ? (baseLopnna.observacion || '') : '')),
        persona_nombre: repNombre,
        evaluado: 1
      });
    }

    // 3.4 Documentos para personas autorizadas a retirar (no padre/madre)
    autorizadosRetiro.forEach(function (aut) {
      const autNombre = (aut.primer_nombre + ' ' + (aut.primer_apellido || '')).trim();
      const autDoc = (aut.numero_documento || '').replace(/\D/g, '');
      const keyRetiro = 'tipo_10' + (autDoc ? '_' + autDoc : '');
      const keyCedula = 'tipo_11' + (autDoc ? '_' + autDoc : '');

      const baseRetiro = recaudosBaseEstudiante.find(function (r) {
        return r.codigo === 'autorizacion_retiro' && (r.persona_nombre || '').trim() === autNombre;
      });
      const baseCedulaAut = recaudosBaseEstudiante.find(function (r) {
        return r.codigo === 'cedula_autorizada' && (r.persona_nombre || '').trim() === autNombre;
      });

      const idRetiro = baseRetiro ? baseRetiro.id : keyRetiro;
      const idCedula = baseCedulaAut ? baseCedulaAut.id : keyCedula;

      lista.push({
        id: idRetiro,
        codigo: 'autorizacion_retiro',
        nombre: 'Autorización de retiro',
        requerido: 1,
        consignado: estadosActuales[idRetiro] !== undefined
          ? estadosActuales[idRetiro].consignado
          : (baseRetiro ? Number(baseRetiro.consignado) : 0),
        observacion: estadosActuales[idRetiro] !== undefined
          ? estadosActuales[idRetiro].observacion
          : (baseRetiro ? (baseRetiro.observacion || '') : ''),
        persona_nombre: autNombre,
        evaluado: 1
      });

      lista.push({
        id: idCedula,
        codigo: 'cedula_autorizada',
        nombre: 'Cédula de la persona autorizada',
        requerido: 1,
        consignado: estadosActuales[idCedula] !== undefined
          ? estadosActuales[idCedula].consignado
          : (baseCedulaAut ? Number(baseCedulaAut.consignado) : 0),
        observacion: estadosActuales[idCedula] !== undefined
          ? estadosActuales[idCedula].observacion
          : (baseCedulaAut ? (baseCedulaAut.observacion || '') : ''),
        persona_nombre: autNombre,
        evaluado: 1
      });
    });

    window.SIGDERecaudos.rellenar(panelRec, lista, Number(idEstudianteActual));
  }

  /* Familiares. La lista llega del servidor y solo se edita el contacto del REPRESENTANTE
 LEGAL; el resto de los vínculos es de consulta.*/
  const familiaresUI = FamiliaresUI.init({
    baseRoute: BASE_URL + 'ratificacion/',
    jsonInputId: 'familiaresJson',
    permitirDesvinculo: false,
    permitirDesactivar: true,
    contactoEditable: function (familiar) {
      return true;
    },
    alCambiar: function () {
      sincronizarRecaudosDinamicos();
    }
  });

  async function abrirEditor(id, tabInicial, editarRepresentante, modalOrigen) {
    if (!modalEditarEl || !id) return;

    modalPadreActivo = modalOrigen || modalRatificarEl;
    if (modalPadreActivo) {
      modalPadreActivo.dataset.enEdicion = '1';
      const abierto = bootstrap.Modal.getInstance(modalPadreActivo);
      if (abierto) abierto.hide();
    }

    const inputId = document.getElementById('editEstudianteId');
    if (!inputId || !id) return;
    inputId.value = id;

    inicializarPuntosEdicion();

    /* Los datos llegan DESPUÉS de abrir el modal, para que la pestaña se vea
 mientras carga en vez de quedarse vacía sin explicación.*/
    try {
      const sobre = await SIGDEUI.getJson(BASE_URL + 'ratificacion/datosEstudiante?id=' + encodeURIComponent(id));
      const d = (sobre.data && sobre.data.data) || null;

      if (!sobre.ok || !d) {
        SIGDEUI.mostrarError((sobre.data && sobre.data.mensaje) || 'No se pudieron cargar los datos del estudiante.');
        return;
      }

      /* La respuesta viene anidada: `data.estudiante` son los datos personales y
 medicales, y al lado vienen los familiares y el representante.

 OJO: se leen de `d.estudiante` y no de `d` a secas. Antes esta respuesta eran
 solo los datos del estudiante, y cuando se le agregaron los familiares
 (que los escribe el mismo módulo que el sub-modal) pasó a ser un objeto con
 esa envoltura. Si se leyera de `d`, `d.primer_nombre` sería undefined y el
 formulario se abriría VACÍO: el modal parece funcionar y no muestra nada.*/
      const est = d.estudiante || {};

      const formEl = document.getElementById('formEditarEstudiante');
      if (formEl) {
        formEl.classList.remove('was-validated');
        if (window.SIGDEUI && SIGDEUI.limpiarErroresDeCampos) {
          SIGDEUI.limpiarErroresDeCampos(formEl);
        }
        formEl.querySelectorAll('.is-invalid').forEach(function (el) {
          el.classList.remove('is-invalid');
        });
        formEl.dataset.tipoDocOriginal = est.tipo_documento || '';
        formEl.dataset.fechaOriginal = est.fecha_nacimiento ? String(est.fecha_nacimiento).slice(0, 10) : '';
      }
      const bloqueCE = document.getElementById('bloqueCambioAnioCE');
      const motivoInput = document.getElementById('motivo_cambio_anio');
      const contadorMotivo = document.getElementById('contadorMotivoCE');
      if (bloqueCE) bloqueCE.classList.add('d-none');
      if (motivoInput) motivoInput.value = '';
      if (contadorMotivo) contadorMotivo.textContent = '40';

      poner('tipoDocumento', est.tipo_documento);
      poner('numeroDocumento', est.numero_documento);
      poner('fechaNacimiento', est.fecha_nacimiento ? String(est.fecha_nacimiento).slice(0, 10) : '');
      poner('primerNombre', est.primer_nombre);
      poner('segundoNombre', est.segundo_nombre);
      poner('primerApellido', est.primer_apellido);
      poner('segundoApellido', est.segundo_apellido);
      marcarSexo(est.sexo);
      poner('nacionalidad', est.id_nacionalidad);
      const elNac = document.getElementById('nacionalidad');
      if (elNac) {
        if (est.id_nacionalidad) {
          elNac.classList.remove('is-invalid');
        } else {
          elNac.classList.add('is-invalid');
        }
      }

      /* El lugar de nacimiento también sale en d.estudiante, pero los tres selects no se
         pueden pintar con `poner`: el municipio depende del estado y la parroquia del
         municipio, así que hay que preguntarle a la cascada. Sin esto el modal abre los
         selects vacíos y, como son obligatorios, no deja guardar la edición.

         Este módulo NO carga enrollments.js, donde estaba inicializada: los selects
         quedaban sin cascada y al elegir la entidad federal no cargaba ni un municipio.
         Se crea al cargar la página, en inicializarPuntosEdicion(). */
      var cascadaNac = window.SIGDECascadaNacimiento;
      if (!cascadaNac && window.SIGDEWizardUI && SIGDEWizardUI.initCascadaNacimiento) {
        cascadaNac = window.SIGDECascadaNacimiento = SIGDEWizardUI.initCascadaNacimiento(
          BASE_URL + 'ratificacion/getUbicacion'
        );
      }
      if (cascadaNac && (est.id_parroquia_nacimiento || est.id_estado_nacimiento)) {
        cascadaNac.cargando.then(function () {
          return cascadaNac.elegirParroquia(
            est.id_estado_nacimiento || 0,
            est.id_municipio_nacimiento || 0,
            est.id_parroquia_nacimiento || 0
          );
        });
      }

      ponerTalla('tallaCamisa', est.talla_camisa);
      ponerTalla('tallaPantalon', est.talla_pantalon);
      ponerTalla('tallaZapato', est.talla_zapato);
      poner('tallaObservaciones', est.talla_observaciones);

      poner('tipoSangre', est.tipo_sangre);
      poner('medicamentos', est.medicamentos);
      poner('alergias', est.alergias);
      poner('enfermedades', est.enfermedades);
      poner('condicionesEspeciales', est.condiciones_especiales);
      poner('observacionesMedicas', est.observaciones_medicas);

      const nombre = [est.primer_nombre, est.primer_apellido].filter(Boolean).join(' ');
      const campoNombre = document.getElementById('editNombreCompleto');
      if (campoNombre) campoNombre.textContent = nombre;
      const campoCedula = document.getElementById('editCedulaId');
      if (campoCedula) campoCedula.textContent = est.tipo_documento + '-' + est.numero_documento;
      pintarFoto(est.foto);

      sincronizarTipoDocumento();

      /* Los familiares van al módulo compartido, que los pinta en la tabla y escribe
 el hidden #familiaresJson. Se llama a cargar() (y no a su setter) porque el
 módulo se responsible de dejar la lista y el hidden de acuerdo, y porque así
 el modal arranca siempre igual: si el fetch falló, la lista queda vacía en
 vez de conservar los datos de otro estudiante.*/
      idEstudianteActual = id;
      recaudosBaseEstudiante = Array.isArray(d.recaudos) ? d.recaudos.slice() : [];
      representanteInicial = (d.familiares || []).find(function (f) {
        return (Number(f.representante_principal) === 1 || f.representante_principal === true);
      }) || null;
      familiaresUI.cargar(d.familiares || []);
      sincronizarRecaudosDinamicos();

      // Los bloques plegables se abren si hay algo guardado adentro.
    ['talla', 'medica'].forEach(function (clave) {
      const boton = document.querySelector('[data-extra-toggle="' + clave + '"]');
      const bloque = document.getElementById('extraBody-' + clave);
      const tiene = est[clave === 'talla' ? 'talla_camisa' : 'condiciones_especiales'];
      if (boton && bloque && tiene && bloque.hasAttribute('hidden')) {
        bloque.removeAttribute('hidden');
        boton.setAttribute('aria-expanded', 'true');
      }
    });

      bootstrap.Modal.getOrCreateInstance(modalEditarEl).show();

      const paneId = ['pane-personales', 'pane-adicional', 'pane-familiares', 'pane-recaudos'].includes(tabInicial)
        ? tabInicial
        : 'pane-personales';
      const tabButton = modalEditarEl.querySelector('[data-bs-target="#' + paneId + '"]');
      if (tabButton) bootstrap.Tab.getOrCreateInstance(tabButton).show();
      if (editarRepresentante && !familiaresUI.editarRepresentante()) {
        SIGDEUI.mostrarError('No hay un representante legal activo registrado para este estudiante.');
      }
    } catch (err) {
      console.error('Error al cargar los datos del estudiante:', err);
      SIGDEUI.mostrarError('No se pudo conectar con el servidor.');
    }
  }

async function guardarDatosEstudiante() {
    const form = document.getElementById('formEditarEstudiante');
    if (!form) return;

    /* Mismo guardia que la ratificación: sin esto, dos clics abrían dos
 confirmaciones y, al confirmar las dos, se guardaba dos veces el mismo
 formulario. Aquí el costo de ese error es distinto: no compite un cupo,
 pero se pisa la ficha dos veces y con la foto se suben dos archivos.*/
    if (enviandoDatos) return;
    enviandoDatos = true;

    /* Si el guardado YA salió bien, el botón no vuelve a habilitarse: el modal se
 cierra y el expediente se recarga. Rehabilitarlo en esa ventana es
 justamente lo que abre la vía del segundo envío.*/
    let guardado = false;

    // El botón vive en el pie del modal, FUERA del <form>: se busca por atributo.
    const btn = document.querySelector('button[form="formEditarEstudiante"]');
    const oldHtml = btn ? btn.innerHTML : '';

    try {
      /* Las marcas del intento anterior se borran ANTES de preguntar, no después de
 enviar: si la secretaria cancela, el mensaje viejo tiene que haberse ido
 igual, o se queda pegado al campo que ya corrigió.*/
      SIGDEUI.limpiarErroresDeCampos(form);

      const bloqueCE = document.getElementById('bloqueCambioAnioCE');
      const motivoInput = document.getElementById('motivo_cambio_anio');
      if (bloqueCE && !bloqueCE.classList.contains('d-none')) {
        const motivo = (motivoInput ? motivoInput.value : '').trim();
        if (!motivo) {
          SIGDEUI.mostrarAviso('error', 'Debe indicar el motivo del cambio de año de nacimiento.');
          motivoInput?.focus();
          return;
        }
        if (motivo.length > 40) {
          SIGDEUI.mostrarAviso('error', 'El motivo no puede exceder 40 caracteres.');
          motivoInput?.focus();
          return;
        }
      }

      // Validar recaudos obligatorios en el cliente antes de confirmar
      const panelRec = document.getElementById('recaudosModalEditar');
      if (panelRec) {
        let faltante = null;
        let toggleFaltante = null;
        const filas = panelRec.querySelectorAll('[data-recaudo-row]');
        for (let i = 0; i < filas.length; i++) {
          const fila = filas[i];
          const asterisco = fila.querySelector('.text-danger');
          const toggle = fila.querySelector('[data-recaudo-toggle]');
          if (asterisco && toggle && !toggle.checked) {
            const nombreEl = fila.querySelector('.fw-semibold');
            faltante = nombreEl ? nombreEl.textContent.replace('*', '').trim() : 'un documento obligatorio';
            toggleFaltante = toggle;
            break;
          }
        }
        if (faltante) {
          const tabBtn = modalEditarEl.querySelector('[data-bs-target="#pane-recaudos"]');
          if (tabBtn) bootstrap.Tab.getOrCreateInstance(tabBtn).show();
          SIGDEUI.mostrarAviso(
            'warning',
            'Documento obligatorio pendiente',
            'Debe consignar "' + faltante + '" antes de guardar la actualización.'
          );
          if (toggleFaltante) toggleFaltante.focus();
          return;
        }
      }

      bloquearBoton(btn,
        '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Guardando...');

      const confirmado = await SIGDEUI.confirmar({
        icono: 'warning',
        titulo: '¿Guardar los cambios?',
        texto: 'Se actualizarán los datos del estudiante, su ficha y los familiares. La matrícula no se toca.',
        confirmar: 'Sí, guardar'
      });
      if (!confirmado) return;

      /* El JSON de los familiares lo escribe el módulo, que es quien tiene la lista:
 el hidden viaja dentro del FormData solo si ya está al día.*/
      familiaresUI.serializar();

      // FormData porque hay archivo: el paso 1 manda la foto.
      const cuerpo = new FormData(form);

      const sobre = await SIGDEUI.enviar(BASE_URL + 'ratificacion/updateEstudiante', { metodo: 'POST', cuerpo: cuerpo });

      if (!sobre.ok || !sobre.data || sobre.data.ok === false) {
  /* El backend responde 422 con un mapa campo → mensaje. Antes se tomaba el primero y se le
 endilaba a la secretaria un error que no tenía relación con lo que estaba escribiendo.*/
        SIGDEUI.mostrarErroresDeCampos(sobre.data, {
          form: form,
          /* Sin alias: aquí el servidor ya devuelve los `name` del formulario.
 `observaciones` llega de las tallas y `observaciones_medicas` de la
 ficha, y son campos distintos de propósito.*/
          pasos: {
            familiares: 'pane-familiares',
            recaudos: 'pane-recaudos'
          },
          titulo: 'No se pudo guardar',
          /* La pestaña se deduce del panel que contiene el campo, no de una tabla:
 si mañana se agrega una pestaña, no hay que registrar nada aquí.*/
          alDetectar: function (campo) {
            var pane = campo.closest('.tab-pane');
            return pane ? pane.id : null;
          },
          irAPantalla: function (paneId) {
            var tab = modalEditarEl && modalEditarEl.querySelector('[data-bs-target="#' + paneId + '"]');
            if (tab) bootstrap.Tab.getOrCreateInstance(tab).show();
          }
        });
        return;
      }

      /* Se marca ANTES del aviso: si el SweetAlert falla, el catch no debe dejar
 habilitable un botón cuyo guardado ya se hizo.*/
      guardado = true;

      SIGDEUI.exito(sobre.data.mensaje || 'Datos actualizados correctamente.', '¡Listo!');
      modalEditarEl.dataset.recargarExpediente = '1';
      bootstrap.Modal.getOrCreateInstance(modalEditarEl).hide();
    } catch (err) {
      console.error('Error al guardar los datos del estudiante:', err);
      SIGDEUI.mostrarError('No se pudo conectar con el servidor.');
    } finally {
      enviandoDatos = false;
      if (!guardado) desbloquearBoton(btn, oldHtml);
    }
  }

  const formEditar = document.getElementById('formEditarEstudiante');
  if (formEditar) {
    formEditar.addEventListener('submit', function (e) {
      e.preventDefault();
      guardarDatosEstudiante();
    });

    const inputFecha = document.getElementById('fechaNacimiento');
    const bloqueCE = document.getElementById('bloqueCambioAnioCE');
    const motivoInput = document.getElementById('motivo_cambio_anio');
    const contadorMotivo = document.getElementById('contadorMotivoCE');

    if (inputFecha) {
      inputFecha.addEventListener('change', function () {
        if (!bloqueCE) return;
        const tipoOrig = formEditar.dataset.tipoDocOriginal || '';
        const fechaOrig = formEditar.dataset.fechaOriginal || '';
        const anioOrig = fechaOrig ? fechaOrig.slice(0, 4) : '';
        const anioNuevo = this.value ? this.value.slice(0, 4) : '';

        if (tipoOrig === 'CE' && anioOrig && anioNuevo && anioOrig !== anioNuevo) {
          bloqueCE.classList.remove('d-none');
        } else {
          bloqueCE.classList.add('d-none');
        }
      });
    }

    if (motivoInput && contadorMotivo) {
      motivoInput.addEventListener('input', function () {
        const quedan = 40 - this.value.length;
        contadorMotivo.textContent = String(Math.max(0, quedan));
      });
    }
  }

  // Al cerrar el modal de editar, volver a abrir el modal padre (ratificar o reingreso).
  if (modalEditarEl) {
    modalEditarEl.addEventListener('hidden.bs.modal', function () {
      const padre = modalPadreActivo;
      modalPadreActivo = null;

      // Actualizar vista previa si el padre es el modal de reingreso y se guardaron cambios
      if (padre && padre.id === 'registrarReingresoModal' && modalEditarEl.dataset.recargarExpediente === '1') {
        const nom = [(document.getElementById('primerNombre') || {}).value, (document.getElementById('primerApellido') || {}).value].filter(Boolean).join(' ');
        const tip = (document.getElementById('tipoDocumento') || {}).value;
        const num = (document.getElementById('numeroDocumento') || {}).value;
        if (nom) {
          const elN = document.getElementById('reingresoDatosNombre');
          if (elN) elN.textContent = nom;
        }
        if (tip && num) {
          const elC = document.getElementById('reingresoDatosCedula');
          if (elC) elC.textContent = tip + '-' + num;
        }
      }

      if (padre) {
        setTimeout(function () {
          delete padre.dataset.enEdicion;
          bootstrap.Modal.getOrCreateInstance(padre).show();
        }, 150);
      } else if (modalRatificarEl) {
        setTimeout(function () {
          delete modalRatificarEl.dataset.enEdicion;
          bootstrap.Modal.getOrCreateInstance(modalRatificarEl).show();
        }, 150);
      } else if (modalEditarEl.dataset.recargarExpediente === '1') {
        window.location.reload();
      }
      delete modalEditarEl.dataset.recargarExpediente;
    });
  }

  // Exponer para otros módulos (como reingreso)
  window.abrirEditorEstudiante = abrirEditor;
});