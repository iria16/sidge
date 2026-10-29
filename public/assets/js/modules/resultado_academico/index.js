/* SIGDE - modules/resultado_academico/index.js
 * Bandeja de secciones: activa los tooltips nativos de las acciones
 * (registrar / ver / sección cerrada) sin recargar la página.
 */
(function () {
  'use strict';

  var filtroAnio = document.getElementById('filtroAnio');
  if (filtroAnio && filtroAnio.form) {
    filtroAnio.addEventListener('change', function () {
      if (filtroAnio.value !== '') {
        filtroAnio.form.submit();
      }
    });
  }

  if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
    var disparadores = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    Array.prototype.forEach.call(disparadores, function (elemento) {
      if (!bootstrap.Tooltip.getInstance(elemento)) {
        new bootstrap.Tooltip(elemento, { container: 'body' });
      }
    });
  }

  /* Vista previa interactiva de Certificados de Calificaciones */
  var modalCertificado = document.getElementById('modalVistaPreviaCertificado');
  var iframeCertificado = document.getElementById('iframeVistaPreviaCertificado');
  var btnDescargarCertificado = document.getElementById('btnDescargarCertificadoPdf');
  var btnImprimirCertificado = document.getElementById('btnImprimirCertificado');

  if (modalCertificado && iframeCertificado) {
    var modalTitleEl = modalCertificado.querySelector('.modal-title');
    var modalSubtitleEl = modalCertificado.querySelector('.modal-header__subtitle, .modal-subtitle, .app-modal__subtitle, small.text-muted');

    document.addEventListener('click', function (e) {
      var btn = e.target.closest('.btn-preview-certificado');
      if (!btn) return;

      var urlPreview = btn.getAttribute('data-url-preview') || '';
      var urlDownload = btn.getAttribute('data-url-download') || urlPreview;
      var titulo = btn.getAttribute('data-titulo') || 'Certificados de Calificaciones';
      var subtitulo = btn.getAttribute('data-subtitulo') || '';

      if (!urlPreview) return;

      if (modalTitleEl) {
        modalTitleEl.textContent = titulo;
      }
      if (modalSubtitleEl) {
        modalSubtitleEl.textContent = subtitulo;
      }

      iframeCertificado.src = urlPreview;

      if (btnDescargarCertificado) {
        btnDescargarCertificado.setAttribute('href', urlDownload);
      }

      if (window.bootstrap && bootstrap.Modal) {
        var modal = bootstrap.Modal.getInstance(modalCertificado) || new bootstrap.Modal(modalCertificado);
        modal.show();
      } else if (window.SIGDEUI && SIGDEUI.mostrarModal) {
        SIGDEUI.mostrarModal(modalCertificado);
      }
    });

    if (btnImprimirCertificado) {
      btnImprimirCertificado.addEventListener('click', function () {
        try {
          iframeCertificado.contentWindow.focus();
          iframeCertificado.contentWindow.print();
        } catch (err) {
          var url = btnDescargarCertificado ? btnDescargarCertificado.getAttribute('href') : null;
          if (url && url !== '#') {
            window.open(url, '_blank');
          }
        }
      });
    }

    modalCertificado.addEventListener('hidden.bs.modal', function () {
      iframeCertificado.src = 'about:blank';
    });
  }
})();
