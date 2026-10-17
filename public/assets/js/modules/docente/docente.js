/**
 * Módulo Docente: búsqueda sin recargar y agrupación por representante.
 *
 * La agrupación NO vuelve a pedir la lista al servidor: usa el mismo JSON que
 * ya venía con la página. Es a propósito. Si el interruptor cambiara el conjunto
 * de datos, el contador del encabezado dejaría de cuadrar con la tabla, que es
 * justo la clase de sorpresa que hace desconfiar de una pantalla.
 *
 * La búsqueda sí usa fetch, y manda los filtros actuales para que escribir en el
 * buscador no borre lo que el usuario seleccionó antes.
 */
(function () {
    'use strict';

    /* ======================================================================
       Listados de la sección (/docente/nomina)
       El filtrado automático (change + debounce de búsqueda) lo maneja
       initAutoFilters() en main.js sobre #filtrosListadoNomina. Aquí solo
       sincronizamos q, sexo y mes en el botón Exportar PDF y en los chips
       cuando data-panel:updated avisa que terminó un filtrado.
       ====================================================================== */
    var formListado = document.getElementById('filtrosListadoNomina');
    if (formListado) {
        function sincronizarEnlaces(urlActual) {
            var origen;
            try {
                origen = new URL(urlActual || window.location.href, window.location.origin);
            } catch (err) {
                return;
            }

            /* «Visualizar documento» es un botón, no un enlace: lleva la ruta
               en data-base-pdf. Los chips siguen siendo enlaces, así que cada
               uno recibe su filtro en el atributo que corresponda. */
            var enlaces = document.querySelectorAll('#btnVistaPreviaDocumento, .nomina-chip');
            enlaces.forEach(function (enlace) {
                var esBoton = enlace.tagName === 'BUTTON';
                var actual = esBoton ? enlace.getAttribute('data-base-pdf') : enlace.getAttribute('href');
                if (!actual) { return; }

                var destino;
                try {
                    destino = new URL(actual, window.location.origin);
                } catch (err) {
                    return;
                }

                var attrFiltros = enlace.getAttribute('data-filtros');
                /* Si el atributo no existe asumimos los tres filtros de siempre.
                   Si existe pero viene vacío —el acta, que no lleva barra— no se
                   arrastra ninguno: antes un "" caía en el valor por defecto y el
                   buscador de otra pestaña viajaba a una vista que no lo tiene. */
                var permitidos  = attrFiltros === null ? ['q', 'sexo', 'mes']
                                    : (attrFiltros ? attrFiltros.split(',') : []);

                ['q', 'sexo', 'mes'].forEach(function (clave) {
                    if (permitidos.indexOf(clave) === -1) {
                        destino.searchParams.delete(clave);
                        return;
                    }
                    var valor = origen.searchParams.get(clave);
                    if (valor !== null && valor.trim() !== '') {
                        destino.searchParams.set(clave, valor.trim());
                    } else {
                        destino.searchParams.delete(clave);
                    }
                });

                enlace.setAttribute(esBoton ? 'data-base-pdf' : 'href', destino.pathname + destino.search);
            });
        }

        document.addEventListener('data-panel:updated', function (ev) {
            if (!ev.detail || ev.detail.form !== formListado) { return; }
            sincronizarEnlaces(ev.detail.url || window.location.href);
        });
    }

    /* ── Vista previa y descarga del documento (todas las pestañas) ────── */
    var btnVistaPrevia    = document.getElementById('btnVistaPreviaDocumento');
    var panelActa         = document.getElementById('panelActa');
    var inputMotivo       = document.getElementById('actaMotivo');
    var inputFecha        = document.getElementById('actaFecha');
    var modalDocumento    = document.getElementById('modalVistaPreviaDocumento');
    var contenedorPreview = document.getElementById('contenedorPreviewDocumento');
    var iframeDocumento   = document.getElementById('iframeVistaPreviaDocumento');
    var btnDescargar      = document.getElementById('btnDescargarDocumentoPdf');
    var regexMotivo       = /^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ0-9\s.,;:()°ºª\-\/'"]+$/;

    function sanitizarMotivoActa(valor) {
        var limpio = String(valor || '')
            .replace(/<[^>]*>/g, '')
            .replace(/[\u0000-\u001F\u007F<>]/g, '')
            .replace(/\s+/g, ' ')
            .trim();

        if (limpio === '') { return ''; }

        var tieneLetras = /[A-Za-zÁÉÍÓÚÜÑáéíóúüñ]/.test(limpio);
        var tieneMinuscula = /[a-záéíóúüñ]/.test(limpio);
        if (tieneLetras && !tieneMinuscula) {
            limpio = limpio.toLowerCase();
        }

        return limpio.charAt(0).toUpperCase() + limpio.slice(1);
    }

    function esMotivoValido(motivo) {
        return motivo.length >= 3 && motivo.length <= 120 && regexMotivo.test(motivo);
    }

    if (inputMotivo) {
        inputMotivo.addEventListener('input', function () {
            var limpio = sanitizarMotivoActa(inputMotivo.value);
            if (esMotivoValido(limpio)) {
                inputMotivo.classList.remove('is-invalid');
            }
        });
        inputMotivo.addEventListener('blur', function () {
            if (inputMotivo.value !== '') {
                inputMotivo.value = sanitizarMotivoActa(inputMotivo.value);
            }
        });
        inputMotivo.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter' && btnVistaPrevia) {
                ev.preventDefault();
                btnVistaPrevia.click();
            }
        });
    }

    if (inputFecha) {
        inputFecha.addEventListener('change', function () {
            if (/^\d{4}-\d{2}-\d{2}$/.test(inputFecha.value.trim())) {
                inputFecha.classList.remove('is-invalid');
            }
        });
    }

    /* El motivo y la fecha viven en la propia pestaña del acta, así que al
       abrirla el foco cae en el motivo: es el primer dato que hay que escribir.
       Antes esto pasaba al abrirse el modal. */
    if (panelActa && inputMotivo) {
        inputMotivo.focus();
    }

    /* Un solo manejador para todas las pestañas. «Visualizar documento» arma el
       PDF de la vista en curso y lo abre en el modal; el enlace de descarga solo
       aparece después, que es cuando el docente ya lo vio. El acta es la única
       que además pide motivo y fecha, y se sabe por sus campos: si están, se
       validan y se mandan en la URL. */
    if (btnVistaPrevia && modalDocumento && iframeDocumento && btnDescargar) {
        btnVistaPrevia.addEventListener('click', function () {
            var basePdf = btnVistaPrevia.getAttribute('data-base-pdf') || window.location.pathname;
            var urlPdf;
            try {
                urlPdf = new URL(basePdf, window.location.origin);
            } catch (err) {
                return;
            }

            if (inputMotivo && inputFecha) {
                var motivo = sanitizarMotivoActa(inputMotivo.value);
                inputMotivo.value = motivo;
                var fecha  = inputFecha.value.trim();
                var valido = true;

                if (!esMotivoValido(motivo)) {
                    inputMotivo.classList.add('is-invalid');
                    inputMotivo.focus();
                    valido = false;
                } else {
                    inputMotivo.classList.remove('is-invalid');
                }

                if (!/^\d{4}-\d{2}-\d{2}$/.test(fecha)) {
                    inputFecha.classList.add('is-invalid');
                    if (valido) { inputFecha.focus(); }
                    valido = false;
                } else {
                    inputFecha.classList.remove('is-invalid');
                }

                if (!valido) { return; }

                urlPdf.searchParams.set('motivo', motivo);
                urlPdf.searchParams.set('fecha', fecha);
            }

            urlPdf.searchParams.set('formato', 'pdf');
            urlPdf.searchParams.delete('descargar');

            iframeDocumento.src = urlPdf.pathname + urlPdf.search;

            if (contenedorPreview) {
                contenedorPreview.classList.remove('d-none');
            }

            /* La vista previa se abre en modal, no sobre la página: el docente
               puede revisarla y cerrar sin perder el motivo que escribió. El
               enlace de descarga solo se destapa cuando ya la vio. */
            SIGDEUI.mostrarModal(modalDocumento);

            urlPdf.searchParams.set('descargar', '1');
            btnDescargar.setAttribute('href', urlPdf.pathname + urlPdf.search);
            btnDescargar.classList.remove('d-none');
        });
    }

    /* ── Enlace de grupo de WhatsApp de la sección (pestaña Contactos) ── */
    var formWa       = document.getElementById('formEnlaceWhatsapp');
    var inputWa      = document.getElementById('inputEnlaceWhatsapp');
    var errorWa      = document.getElementById('errorEnlaceWhatsapp');
    var btnGuardarWa = document.getElementById('btnGuardarEnlaceWhatsapp');
    var btnQuitarWa  = document.getElementById('btnQuitarEnlaceWhatsapp');
    var modalWa      = document.getElementById('modalEnlaceWhatsapp');
    var regexWa      = /^https:\/\/chat\.whatsapp\.com\/(?:invite\/)?[A-Za-z0-9_-]{10,64}$/;

    function enviarEnlaceWhatsapp(enlaceTexto, botonActivo) {
        if (!formWa || !window.SIGDEUI) { return; }
        var fd = new FormData();
        fd.append('enlace_whatsapp', enlaceTexto);

        if (botonActivo) { botonActivo.disabled = true; }

        SIGDEUI.enviar(formWa.getAttribute('action'), {
            metodo: 'POST',
            cuerpo: fd
        }).then(function (r) {
            if (botonActivo) { botonActivo.disabled = false; }

            if (!r.ok || !r.data || !r.data.ok) {
                var msg = (r.data && r.data.data && r.data.data.enlace_whatsapp)
                    || (r.data && r.data.mensaje)
                    || 'No se pudo guardar el enlace.';
                if (inputWa && errorWa) {
                    errorWa.textContent = msg;
                    inputWa.classList.add('is-invalid');
                    inputWa.focus();
                } else {
                    SIGDEUI.mostrarError(msg);
                }
                return;
            }

            if (modalWa) {
                SIGDEUI.ocultarModal(modalWa);
            }
            SIGDEUI.exito(r.data.mensaje || 'Enlace actualizado.', '¡Listo!')
                .then(function () { window.location.reload(); });
        }).catch(function () {
            if (botonActivo) { botonActivo.disabled = false; }
            SIGDEUI.mostrarError('No se pudo conectar con el servidor.');
        });
    }

    if (inputWa) {
        inputWa.addEventListener('input', function () {
            var v = inputWa.value.trim();
            if (v === '' || (v.length <= 120 && !/\s/.test(v) && regexWa.test(v))) {
                inputWa.classList.remove('is-invalid');
            }
        });
    }

    if (formWa && inputWa) {
        formWa.addEventListener('submit', function (ev) {
            ev.preventDefault();
            var valor = inputWa.value.trim();
            inputWa.value = valor;

            if (valor !== '' && (valor.length > 120 || /\s/.test(valor) || !regexWa.test(valor))) {
                if (errorWa) {
                    errorWa.textContent = 'El enlace debe comenzar por https://chat.whatsapp.com/ seguido del código de invitación, sin espacios.';
                }
                inputWa.classList.add('is-invalid');
                inputWa.focus();
                return;
            }

            inputWa.classList.remove('is-invalid');
            enviarEnlaceWhatsapp(valor, btnGuardarWa);
        });
    }

    if (btnQuitarWa && formWa) {
        btnQuitarWa.addEventListener('click', function () {
            btnQuitarWa.disabled = true;
            SIGDEUI.confirmar({
                icono: 'warning',
                titulo: '¿Quitar enlace del grupo?',
                texto: 'Los botones de invitación al grupo dejarán de mostrarse en Contactos.',
                confirmar: 'Sí, quitar enlace',
                cancelar: 'Cancelar'
            }).then(function (confirmado) {
                if (!confirmado) {
                    btnQuitarWa.disabled = false;
                    return;
                }
                enviarEnlaceWhatsapp('', btnQuitarWa);
            });
        });
    }

    var btnCopiarWa = document.getElementById('btnCopiarEnlaceWhatsapp');
    if (btnCopiarWa && navigator.clipboard) {
        btnCopiarWa.addEventListener('click', function () {
            var enlace = btnCopiarWa.getAttribute('data-enlace') || '';
            if (!enlace) { return; }
            navigator.clipboard.writeText(enlace).then(function () {
                var span = btnCopiarWa.querySelector('span');
                var icono = btnCopiarWa.querySelector('i');
                var original = span ? span.textContent : '';
                if (span) { span.textContent = '¡Enlace copiado!'; }
                if (icono) { icono.className = 'bi bi-check2 me-1'; }
                setTimeout(function () {
                    if (span) { span.textContent = original || 'Copiar enlace'; }
                    if (icono) { icono.className = 'bi bi-clipboard me-1'; }
                }, 2000);
            });
        });
    }

    var claveInvitadosWa = 'sigde_wa_invitados_' + window.location.pathname;

    function leerInvitadosWa() {
        try {
            var raw = sessionStorage.getItem(claveInvitadosWa);
            return raw ? JSON.parse(raw) : {};
        } catch (e) {
            return {};
        }
    }

    function guardarInvitadoWa(tel) {
        if (!tel) { return; }
        try {
            var mapa = leerInvitadosWa();
            mapa[tel] = 1;
            sessionStorage.setItem(claveInvitadosWa, JSON.stringify(mapa));
        } catch (e) {}
    }

    function pintarBotonInvitado(btn) {
        btn.classList.remove('btn-outline-primary');
        btn.classList.add('btn-secondary', 'opacity-75');
        btn.setAttribute('title', 'Invitación abierta en esta sesión (clic para volver a abrir)');
        var icono = btn.querySelector('i');
        if (icono) {
            icono.className = 'bi bi-person-check-fill';
        }
    }

    function restaurarEstadoInvitadosWa() {
        var mapa = leerInvitadosWa();
        document.querySelectorAll('a.nomina-wa--invitar[data-tel]').forEach(function (btn) {
            var tel = btn.getAttribute('data-tel');
            if (tel && mapa[tel]) {
                pintarBotonInvitado(btn);
            }
        });
    }

    restaurarEstadoInvitadosWa();

    var panelContactos = document.querySelector('.nomina-hoja--contacto .data-panel__body');
    if (panelContactos && window.MutationObserver) {
        new MutationObserver(function () {
            restaurarEstadoInvitadosWa();
        }).observe(panelContactos, { childList: true });
    }

    document.addEventListener('click', function (ev) {
        var linkWa = ev.target && ev.target.closest ? ev.target.closest('a.nomina-wa') : null;
        if (!linkWa) { return; }
        try {
            var urlWa = new URL(linkWa.href);
            var txt = urlWa.searchParams.get('text');
            if (txt) {
                var h = new Date().getHours();
                var saludo = h < 12 ? 'Buen día' : (h < 19 ? 'Buenas tardes' : 'Buenas noches');
                var actualizado = txt.replace(/^(Buen día|Buenos días|Buenas tardes|Buenas noches),/, saludo + ',');
                if (actualizado !== txt) {
                    urlWa.searchParams.set('text', actualizado);
                    linkWa.href = urlWa.toString();
                }
            }
        } catch (err) {}

        if (linkWa.classList.contains('nomina-wa--invitar')) {
            var tel = linkWa.getAttribute('data-tel') || '';
            guardarInvitadoWa(tel);
            document.querySelectorAll('a.nomina-wa--invitar[data-tel]').forEach(function (b) {
                if (b.getAttribute('data-tel') === tel) {
                    pintarBotonInvitado(b);
                }
            });
        }
    });

    /* ======================================================================
       Mi sección (/docente/seccion)
       ====================================================================== */
    var datos = document.getElementById('datosNomina');
    if (!datos) { return; }

    var cfg;
    try { cfg = JSON.parse(datos.textContent); } catch (e) { return; }

    var form      = document.getElementById('filtrosNomina');
    var cuerpo    = document.getElementById('cuerpoNomina');
    var agrupada  = document.getElementById('vistaAgrupada');
    var interruptor = document.getElementById('agrupar');
    var tabla     = document.querySelector('.card .table-responsive');

    if (!form || !cuerpo || !agrupada || !interruptor || !tabla) { return; }

    var e = function (v) {
        var d = document.createElement('div');
        d.textContent = (v === null || v === undefined) ? '' : String(v);
        return d.innerHTML;
    };

    /* ── Fila de estudiante reutilizada por la búsqueda ───────────────────── */
    function filaEstudiante(f, indice) {
        var etiquetaLiteral = cfg.etiquetaLiteral || {};

        var telefono = '';
        if (f.numero_telefono_principal) {
            telefono = '<a class="text-decoration-none" href="tel:' +
                e((f.codigo_telefono_principal || '') + f.numero_telefono_principal) + '">' +
                e(f.numero_telefono_principal) + '</a>';
        } else {
            telefono = '<span class="badge bg-danger-subtle text-danger-emphasis">Sin teléfono</span>';
        }

        return '<tr>' +
            '<td class="ps-3 text-muted">' + indice + '</td>' +
            '<td><div class="fw-semibold">' + e(f.nombre) + '</div>' +
                '<div class="small text-muted">' + (f.sexo === 'F' ? 'Femenino' : 'Masculino') + '</div></td>' +
            '<td class="small">' + (f.cedula_escolar ? e(f.cedula_escolar) : '—') + '</td>' +
            '<td>' + parseInt(f.edad, 10) + ' años</td>' +
            '<td><div>' + e(f.representante || 'Sin representante') + '</div>' +
                '<div class="small text-muted">' + e(f.parentesco || '') + '</div></td>' +
            '<td class="small">' + telefono + '</td>' +
            '<td><span class="badge bg-light text-dark">' +
                e(etiquetaLiteral[f.estado_literal] || 'Pendiente') + '</span></td>' +
            '<td class="text-end pe-3"><a class="btn btn-sm btn-outline-primary" href="' +
                cfg.urlSeccion.replace('/seccion', '') + '/estudiante?id=' +
                encodeURIComponent(f.id_estudiante) + '">' +
                '<i class="bi bi-person-vcard me-1" aria-hidden="true"></i>Ver ficha</a></td>' +
        '</tr>';
    }

    /* ── Agrupación por representante ─────────────────────────────────────── */
    function pintarAgrupado() {
        var grupos = cfg.agrupado || {};
        var claves = Object.keys(grupos);

        if (claves.length === 0) {
            agrupada.innerHTML = '<div class="alert alert-light border text-muted text-center mb-0">' +
                'No hay estudiantes con los filtros seleccionados.</div>';
            return;
        }

        var html = '';
        claves.forEach(function (k) {
            var g = grupos[k];
            var tel = g.telefono
                ? '<a class="text-decoration-none" href="tel:' +
                  e((g.codigo_telefono || '') + g.telefono) + '">' + e(g.telefono) + '</a>'
                : '<span class="badge bg-danger-subtle text-danger-emphasis">Sin teléfono</span>';

            html += '<div class="card shadow-sm border-0 mb-3">' +
                '<div class="card-header bg-transparent d-flex flex-wrap justify-content-between align-items-center gap-2">' +
                    '<div><span class="fw-semibold">' + e(g.representante) + '</span>' +
                        '<span class="text-muted small ms-2">' + e(g.parentesco || '') + '</span></div>' +
                    '<div class="small">' + tel +
                        ' <span class="badge bg-primary-subtle text-primary-emphasis ms-2">' +
                        g.estudiantes.length + ' estudiante' +
                        (g.estudiantes.length === 1 ? '' : 's') + '</span></div>' +
                '</div>' +
                '<div class="table-responsive"><table class="table table-hover align-middle mb-0"><tbody>';

            g.estudiantes.forEach(function (f, i) { html += filaEstudiante(f, i + 1); });
            html += '</tbody></table></div></div>';
        });

        agrupada.innerHTML = html;
    }

    /* ── Alternar tabla / agrupado ────────────────────────────────────────── */
    function alternar() {
        var agrupado = interruptor.checked;
        tabla.classList.toggle('d-none', agrupado);
        agrupada.classList.toggle('d-none', !agrupado);
        if (agrupado) { pintarAgrupado(); }
    }
    interruptor.addEventListener('change', alternar);
    alternar();

    /* ── Búsqueda sin recargar ────────────────────────────────────────────── */
    var temporizador = null;

    function buscar() {
        var params = new URLSearchParams();
        ['q', 'sexo', 'contacto', 'autorizados', 'literal']
            .forEach(function (campo) {
                var el = form.elements[campo];
                if (el && el.value) { params.set(campo, el.value); }
            });

        fetch(cfg.urlBuscar + '?' + params.toString(), {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res || res.ok !== true) { return; }
                var filas = res.data && res.data.nomina ? res.data.nomina : [];

                cfg.agrupado = agrupar(res.data ? res.data.nomina : []);
                if (interruptor.checked) { pintarAgrupado(); }

                cuerpo.innerHTML = filas.length
                    ? filas.map(function (f, i) { return filaEstudiante(f, i + 1); }).join('')
                    : '<tr><td colspan="8" class="text-center text-muted py-5">' +
                      'Ningún estudiante cumple los filtros seleccionados.</td></tr>';
            })
            .catch(function () { /* la tabla sigue mostrando lo último cargado */ });
    }

    function agrupar(nomina) {
        var grupos = {};
        (nomina || []).forEach(function (f) {
            var clave = f.id_representante ? 'rep:' + f.id_representante : 'est:' + f.id_estudiante;
            if (!grupos[clave]) {
                grupos[clave] = {
                    id_representante: f.id_representante,
                    representante: f.representante || 'Sin representante',
                    parentesco: f.parentesco,
                    telefono: f.numero_telefono_principal || '',
                    codigo_telefono: f.codigo_telefono_principal || '',
                    estudiantes: []
                };
            }
            grupos[clave].estudiantes.push(f);
        });
        return grupos;
    }

    var campo = form.elements['q'];
    if (campo) {
        campo.addEventListener('input', function () {
            clearTimeout(temporizador);
            temporizador = setTimeout(buscar, 300);
        });
    }
})();