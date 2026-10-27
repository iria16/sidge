/* SIGDE - Comportamiento de Egresos / Certificados de Primaria */
document.addEventListener('DOMContentLoaded', function () {
  var btnLote = document.getElementById('btnCertificadosLote');
  var formFiltros = document.querySelector('form.data-panel__filters');
  var filtroSeccion = document.getElementById('filtroSeccion');

  function actualizarEnlaceLote() {
    if (!btnLote) return;
    var params = new URLSearchParams();
    if (filtroSeccion && filtroSeccion.value) {
      params.set('seccion', filtroSeccion.value);
    }
    var qInput = formFiltros ? formFiltros.querySelector('input[name="q"]') : null;
    if (qInput && qInput.value.trim()) {
      params.set('q', qInput.value.trim());
    }
    var qs = params.toString();
    var base = (window.BASE_URL || '/');
    btnLote.href = base + 'egresos/certificadosLote' + (qs ? ('?' + qs) : '');
  }

  if (formFiltros) {
    formFiltros.addEventListener('change', actualizarEnlaceLote);
    formFiltros.addEventListener('input', actualizarEnlaceLote);
  }
});
