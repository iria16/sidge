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

  if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) return;

  var disparadores = document.querySelectorAll('[data-bs-toggle="tooltip"]');
  Array.prototype.forEach.call(disparadores, function (elemento) {
    if (!bootstrap.Tooltip.getInstance(elemento)) {
      new bootstrap.Tooltip(elemento, { container: 'body' });
    }
  });
})();
