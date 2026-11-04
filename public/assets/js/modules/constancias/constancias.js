/**
 * Módulo Constancias: Vista previa de documentos e impresión/descarga.
 *
 * Abre el modal con el documento en un iframe para revisión oficial.
 * Carga el documento vía Blob (fetch) para eludir bloqueos de iframe/proxy
 * en servidores remotos (InfinityFree, proxies inversos y cabeceras X-Frame-Options).
 */
(function () {
    'use strict';

    var modalElement        = document.getElementById('modalVistaPreviaConstancia');
    var iframeElement       = document.getElementById('iframeVistaPreviaConstancia');
    var loaderElement       = document.getElementById('loaderPreviewConstancia');
    var fallbackElement     = document.getElementById('fallbackPreviewConstancia');
    var btnDescargar        = document.getElementById('btnDescargarConstanciaPdf');
    var btnAbrirTab         = document.getElementById('btnAbrirNuevaPestanaConstancia');
    var btnImprimir         = document.getElementById('btnImprimirConstancia');
    var btnFallbackAbrir    = document.getElementById('btnFallbackAbrir');
    var btnFallbackDescargar = document.getElementById('btnFallbackDescargar');

    if (!modalElement || !iframeElement) {
        return;
    }

    var modalTitleEl    = modalElement.querySelector('.modal-title');
    var modalSubtitleEl = modalElement.querySelector('.modal-subtitle, .app-modal__subtitle, small.text-muted');
    var currentBlobUrl  = null;
    var abortCtrl       = null;

    function limpiarBlob() {
        if (currentBlobUrl) {
            URL.revokeObjectURL(currentBlobUrl);
            currentBlobUrl = null;
        }
        if (abortCtrl) {
            try { abortCtrl.abort(); } catch (_) {}
            abortCtrl = null;
        }
    }

    function mostrarLoader() {
        if (loaderElement) loaderElement.style.display = 'block';
        if (fallbackElement) fallbackElement.style.display = 'none';
        iframeElement.style.display = 'block';
        iframeElement.style.visibility = 'hidden';
    }

    function ocultarLoader() {
        if (loaderElement) loaderElement.style.display = 'none';
        iframeElement.style.visibility = 'visible';
    }

    function mostrarFallback(urlPreview, urlDownload) {
        limpiarBlob();
        if (loaderElement) loaderElement.style.display = 'none';
        iframeElement.style.display = 'none';
        if (fallbackElement) {
            fallbackElement.style.display = 'block';
            if (btnFallbackAbrir) btnFallbackAbrir.setAttribute('href', urlPreview);
            if (btnFallbackDescargar) btnFallbackDescargar.setAttribute('href', urlDownload);
        }
    }

    function resetearVista() {
        limpiarBlob();
        iframeElement.src = 'about:blank';
        iframeElement.style.display = 'block';
        iframeElement.style.visibility = 'visible';
        if (loaderElement) loaderElement.style.display = 'none';
        if (fallbackElement) fallbackElement.style.display = 'none';
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.btn-previsualizar-constancia');
        if (!btn) {
            return;
        }

        var urlPreview  = btn.getAttribute('data-url-preview') || '';
        var urlDownload = btn.getAttribute('data-url-download') || urlPreview;
        var estudiante  = btn.getAttribute('data-estudiante') || 'Estudiante';
        var tipoLabel   = btn.getAttribute('data-tipo') || 'Constancia';

        if (!urlPreview) {
            return;
        }

        // Configurar título y subtítulo del modal
        if (modalTitleEl) {
            var yaTienePrefijo = /^(constancia|certificado)/i.test(tipoLabel);
            modalTitleEl.textContent = yaTienePrefijo ? tipoLabel : ('Constancia de ' + tipoLabel);
        }
        if (modalSubtitleEl) {
            modalSubtitleEl.textContent = estudiante;
        }

        // Configurar botones de acción
        if (btnDescargar) {
            btnDescargar.setAttribute('href', urlDownload);
        }
        if (btnAbrirTab) {
            btnAbrirTab.setAttribute('href', urlPreview);
        }

        resetearVista();
        mostrarLoader();

        // Abrir el modal
        if (window.bootstrap && bootstrap.Modal) {
            var modal = bootstrap.Modal.getInstance(modalElement) || new bootstrap.Modal(modalElement);
            modal.show();
        } else if (window.SIGDEUI && SIGDEUI.mostrarModal) {
            SIGDEUI.mostrarModal(modalElement);
        }

        // Cargar documento vía fetch -> Blob para sortear restricciones de iframe/hosting
        abortCtrl = (typeof AbortController !== 'undefined') ? new AbortController() : null;
        var fetchOpts = {
            credentials: 'same-origin'
        };
        if (abortCtrl) {
            fetchOpts.signal = abortCtrl.signal;
        }

        fetch(urlPreview, fetchOpts)
            .then(function (res) {
                if (!res.ok) {
                    throw new Error('HTTP ' + res.status);
                }
                var ctype = res.headers.get('content-type') || '';
                if (ctype.indexOf('pdf') === -1) {
                    throw new Error('La respuesta del servidor no es un documento PDF');
                }
                return res.blob();
            })
            .then(function (blob) {
                var blobPdf = new Blob([blob], { type: 'application/pdf' });
                currentBlobUrl = URL.createObjectURL(blobPdf);
                iframeElement.src = currentBlobUrl;
                ocultarLoader();
            })
            .catch(function (err) {
                if (err && err.name === 'AbortError') {
                    return;
                }
                console.warn('Carga directa Blob no completada, activando contingencia:', err);
                mostrarFallback(urlPreview, urlDownload);
            });
    });

    // Manejar impresión directa desde el iframe
    if (btnImprimir && iframeElement) {
        btnImprimir.addEventListener('click', function () {
            try {
                iframeElement.contentWindow.focus();
                iframeElement.contentWindow.print();
            } catch (err) {
                var url = btnDescargar ? btnDescargar.getAttribute('href') : null;
                if (url) {
                    window.open(url, '_blank');
                }
            }
        });
    }

    // Limpiar iframe y blob al cerrar el modal para liberar memoria y recursos
    modalElement.addEventListener('hidden.bs.modal', function () {
        resetearVista();
    });
})();
