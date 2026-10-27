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
    var modalDocumento    = document.getElementById('modalVistaPreviaDocumento');
    var contenedorPreview = document.getElementById('contenedorPreviewDocumento');
    var iframeDocumento   = document.getElementById('iframeVistaPreviaDocumento');
    var btnDescargar      = document.getElementById('btnDescargarDocumentoPdf');
    var btnImprimir       = document.getElementById('btnImprimirDocumentoPdf');
    var modalTitleEl      = modalDocumento ? modalDocumento.querySelector('.modal-title') : null;
    var modalSubtitleEl   = modalDocumento ? modalDocumento.querySelector('.modal-subtitle, .app-modal__subtitle, small.text-muted') : null;
    var tituloBase        = modalTitleEl ? modalTitleEl.textContent : '';
    var subtituloBase     = modalSubtitleEl ? modalSubtitleEl.textContent : '';

    /* Un solo manejador para todas las pestañas. «Visualizar documento» arma el
       PDF de la vista en curso y lo abre en el modal; el enlace de descarga solo
       aparece después, que es cuando el docente ya lo vio. */
    if (btnVistaPrevia && modalDocumento && iframeDocumento && btnDescargar) {
        btnVistaPrevia.addEventListener('click', function () {
            if (modalTitleEl) { modalTitleEl.textContent = tituloBase; }
            if (modalSubtitleEl) { modalSubtitleEl.textContent = subtituloBase; }

            var basePdf = btnVistaPrevia.getAttribute('data-base-pdf') || window.location.pathname;
            var urlPdf;
            try {
                urlPdf = new URL(basePdf, window.location.origin);
            } catch (err) {
                return;
            }

            urlPdf.searchParams.set('formato', 'pdf');
            urlPdf.searchParams.delete('descargar');

            iframeDocumento.src = urlPdf.pathname + urlPdf.search;

            if (contenedorPreview) {
                contenedorPreview.classList.remove('d-none');
            }

            /* La vista previa se abre en modal, no sobre la página: el docente
               puede revisarla y cerrar sin perder los filtros aplicados. El
               enlace de descarga solo se destapa cuando ya la vio. */
            SIGDEUI.mostrarModal(modalDocumento);

            urlPdf.searchParams.set('descargar', '1');
            btnDescargar.setAttribute('href', urlPdf.pathname + urlPdf.search);
            btnDescargar.classList.remove('d-none');

            if (btnImprimir) {
                btnImprimir.classList.remove('d-none');
            }
        });
    }

    /* Imprimir desde la vista previa. Se imprime el iframe y no una descarga
       aparte, para que salga el mismo documento que el docente acaba de ver.
       El respaldo es el mismo: si el navegador no deja llamar a print() sobre
       un iframe de otro origen, se abre el PDF en otra pestaña y se imprime
       desde ahí. */
    if (btnImprimir && iframeDocumento) {
        btnImprimir.addEventListener('click', function () {
            try {
                iframeDocumento.contentWindow.focus();
                iframeDocumento.contentWindow.print();
            } catch (e) {
                var urlImprimir = btnDescargar ? btnDescargar.getAttribute('href') : null;
                window.open(urlImprimir || '#', '_blank');
            }
        });
    }

    /* Vista previa individual de boleta/certificado por estudiante.
       Abre el modal de vista previa antes de imprimir o descargar. */
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.btn-preview-boleta-individual');
        if (!btn || !modalDocumento || !iframeDocumento || !btnDescargar) {
            return;
        }

        var basePdf = btn.getAttribute('data-url-pdf');
        if (!basePdf) {
            return;
        }

        var estudiante = btn.getAttribute('data-estudiante') || '';

        if (modalTitleEl) {
            modalTitleEl.textContent = 'Certificado de Calificaciones';
        }
        if (modalSubtitleEl) {
            modalSubtitleEl.textContent = estudiante ? (estudiante + ' · ' + subtituloBase) : subtituloBase;
        }

        var urlPdf;
        try {
            urlPdf = new URL(basePdf, window.location.origin);
        } catch (err) {
            return;
        }

        urlPdf.searchParams.delete('descargar');
        iframeDocumento.src = urlPdf.pathname + urlPdf.search;

        if (contenedorPreview) {
            contenedorPreview.classList.remove('d-none');
        }

        SIGDEUI.mostrarModal(modalDocumento);

        urlPdf.searchParams.set('descargar', '1');
        btnDescargar.setAttribute('href', urlPdf.pathname + urlPdf.search);
        btnDescargar.classList.remove('d-none');

        if (btnImprimir) {
            btnImprimir.classList.remove('d-none');
        }
    });

    /* Limpiar iframe al cerrar el modal para liberar memoria y recursos */
    if (modalDocumento && iframeDocumento) {
        modalDocumento.addEventListener('hidden.bs.modal', function () {
            iframeDocumento.src = 'about:blank';
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
})();