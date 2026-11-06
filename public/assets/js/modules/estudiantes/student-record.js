/**
 * Módulo Vista Previa de Expediente (Estudiante/Empleado)
 * Vista previa de documentos PDF con soporte móvil.
 *
 * Usa el mismo patrón que constancias.js: carga el PDF vía fetch → Blob
 * para evitar bloqueos de iframe en servidores remotos y móviles.
 */
(function () {
  'use strict';

  var modal = document.getElementById('modalVistaPreviaExpediente');
  var iframe = document.getElementById('iframeVistaPreviaExpediente');
  var loader = document.getElementById('loaderPreviewExpediente') || (modal ? modal.querySelector('#loaderPreviewConstancia') : null);
  var fallback = document.getElementById('fallbackPreviewExpediente') || (modal ? modal.querySelector('#fallbackPreviewConstancia') : null);
  var btnImprimir = document.getElementById('btnImprimirExpedientePdf');
  var btnFallbackAbrir = document.getElementById('btnFallbackAbrirExp') || (modal ? modal.querySelector('#btnFallbackAbrir') : null);
  var btnFallbackDescargar = document.getElementById('btnFallbackDescargarExp') || (modal ? modal.querySelector('#btnFallbackDescargar') : null);

  if (!modal || !iframe) return;

  var currentBlobUrl = null;
  var abortCtrl = null;

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
    if (loader) loader.style.display = 'block';
    if (fallback) fallback.style.display = 'none';
    iframe.style.display = 'block';
    iframe.style.visibility = 'hidden';
  }

  function ocultarLoader() {
    if (loader) loader.style.display = 'none';
    iframe.style.visibility = 'visible';
  }

  function mostrarFallback(urlPreview, urlDownload) {
    limpiarBlob();
    if (loader) loader.style.display = 'none';
    iframe.style.display = 'none';
    if (fallback) {
      fallback.style.display = 'block';
      if (btnFallbackAbrir) btnFallbackAbrir.setAttribute('href', urlPreview);
      if (btnFallbackDescargar) btnFallbackDescargar.setAttribute('href', urlDownload);
    }
  }

  function resetearVista() {
    limpiarBlob();
    iframe.src = 'about:blank';
    iframe.style.display = 'block';
    iframe.style.visibility = 'visible';
    if (loader) loader.style.display = 'none';
    if (fallback) fallback.style.display = 'none';
  }

  modal.addEventListener('show.bs.modal', function () {
    var pdfUrl = iframe.dataset.src || iframe.getAttribute('data-src');
    if (!pdfUrl) return;

    resetearVista();
    mostrarLoader();

    // Cargar documento vía fetch -> Blob para soporte móvil
    abortCtrl = (typeof AbortController !== 'undefined') ? new AbortController() : null;
    var fetchOpts = { credentials: 'same-origin' };
    if (abortCtrl) {
      fetchOpts.signal = abortCtrl.signal;
    }

    fetch(pdfUrl, fetchOpts)
      .then(function (res) {
        if (!res.ok) throw new Error('HTTP ' + res.status);
        var ctype = res.headers.get('content-type') || '';
        if (ctype.indexOf('pdf') === -1) throw new Error('No es PDF');
        return res.blob();
      })
      .then(function (blob) {
        var blobPdf = new Blob([blob], { type: 'application/pdf' });
        currentBlobUrl = URL.createObjectURL(blobPdf);
        iframe.src = currentBlobUrl;
        delete iframe.dataset.src;
        ocultarLoader();
      })
      .catch(function (err) {
        if (err && err.name === 'AbortError') return;
        console.warn('Carga Blob fallida, activando fallback:', err);
        mostrarFallback(pdfUrl, pdfUrl + '&descargar=1');
      });
  });

  if (btnImprimir) {
    btnImprimir.addEventListener('click', function () {
      try {
        iframe.contentWindow.focus();
        iframe.contentWindow.print();
      } catch (e) {
        if (currentBlobUrl || iframe.src) {
          window.open(currentBlobUrl || iframe.src, '_blank');
        }
      }
    });
  }

  modal.addEventListener('hidden.bs.modal', function () {
    resetearVista();
  });
})();
