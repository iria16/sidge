/**
 * Centro de Reportes Institucionales.
 *
 * Abre la vista previa del reporte en el mismo modal de constancias. El iframe
 * carga el HTML del reporte y los botones Imprimir/Descargar trabajan sobre esa
 * misma vista; la descarga pide el PDF con `descargar=1` al mismo endpoint.
 */
(function () {
    'use strict';

    var modalElement  = document.getElementById('modalVistaPreviaReporte');
    var iframeElement = document.getElementById('iframeVistaPreviaReporte');
    var btnDescargar  = document.getElementById('btnDescargarReportePdf');
    var btnImprimir   = document.getElementById('btnImprimirReporte');

    if (!modalElement || !iframeElement) {
        return;
    }

    var modalTitleEl    = modalElement.querySelector('.modal-title');
    var modalSubtitleEl = modalElement.querySelector('.modal-subtitle, .app-modal__subtitle, small.text-muted');

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.btn-previsualizar-reporte');
        if (!btn) {
            return;
        }

        var urlPreview  = btn.getAttribute('data-url-preview') || '';
        var urlDownload = btn.getAttribute('data-url-download') || urlPreview;
        var titulo      = btn.getAttribute('data-titulo') || 'Reporte oficial';
        var periodo     = btn.getAttribute('data-periodo') || 'Período no definido';

        if (!urlPreview) {
            return;
        }

        if (modalTitleEl) {
            modalTitleEl.textContent = titulo;
        }
        if (modalSubtitleEl) {
            modalSubtitleEl.textContent = periodo;
        }

        iframeElement.src = urlPreview;

        if (btnDescargar) {
            btnDescargar.setAttribute('href', urlDownload);
        }

        if (window.bootstrap && bootstrap.Modal) {
            var modal = bootstrap.Modal.getInstance(modalElement) || new bootstrap.Modal(modalElement);
            modal.show();
        } else if (window.SIGDEUI && SIGDEUI.mostrarModal) {
            SIGDEUI.mostrarModal(modalElement);
        }
    });

    if (btnImprimir) {
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

    modalElement.addEventListener('hidden.bs.modal', function () {
        iframeElement.src = 'about:blank';
    });
})();
