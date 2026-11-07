document.addEventListener('DOMContentLoaded', function () {
  var modal = document.getElementById('modalVistaPreviaExpediente');
  var iframe = document.getElementById('iframeVistaPreviaExpediente') || (modal ? modal.querySelector('.student-record__pdf-preview iframe') : null);
  var btnImprimir = document.getElementById('btnImprimirExpedientePdf');

  if (!modal || !iframe) return;

  modal.addEventListener('show.bs.modal', function () {
    var pdfUrl = iframe.dataset.src;
    if (!pdfUrl) return;

    iframe.src = pdfUrl;
    delete iframe.dataset.src;
  });

  if (btnImprimir) {
    btnImprimir.addEventListener('click', function () {
      try {
        iframe.contentWindow.focus();
        iframe.contentWindow.print();
      } catch (e) {
        if (iframe.src) {
          window.open(iframe.src, '_blank');
        }
      }
    });
  }
});
