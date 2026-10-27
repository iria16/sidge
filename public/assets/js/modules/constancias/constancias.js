/**
 * Módulo Constancias: Vista previa de documentos e impresión/descarga.
 *
 * Mismo comportamiento de las nóminas y listados de docentes:
 * Al hacer clic en "Visualizar", se abre el modal con el documento
 * en un iframe para revisión oficial. Desde allí se puede imprimir o descargar en PDF.
 */
(function () {
    'use strict';

    var modalElement   = document.getElementById('modalVistaPreviaConstancia');
    var iframeElement  = document.getElementById('iframeVistaPreviaConstancia');
    var btnDescargar   = document.getElementById('btnDescargarConstanciaPdf');
    var btnImprimir    = document.getElementById('btnImprimirConstancia');

    if (!modalElement || !iframeElement) {
        return;
    }

    var modalTitleEl    = modalElement.querySelector('.modal-title');
    var modalSubtitleEl = modalElement.querySelector('.modal-subtitle, .app-modal__subtitle, small.text-muted');

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

        // Cargar vista previa en el iframe
        iframeElement.src = urlPreview;

        // Configurar botón de descarga
        if (btnDescargar) {
            btnDescargar.setAttribute('href', urlDownload);
        }

        // Abrir el modal
        if (window.bootstrap && bootstrap.Modal) {
            var modal = bootstrap.Modal.getInstance(modalElement) || new bootstrap.Modal(modalElement);
            modal.show();
        } else if (window.SIGDEUI && SIGDEUI.mostrarModal) {
            SIGDEUI.mostrarModal(modalElement);
        }
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

    // Limpiar iframe al cerrar el modal para liberar memoria y recursos
    modalElement.addEventListener('hidden.bs.modal', function () {
        iframeElement.src = 'about:blank';
    });
})();
