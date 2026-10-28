/* SIGDE - modules/resultado_academico/modal-confirmar.js
 * Modal de confirmación oficial para el rol directivo.
 *
 * Solo resume lo que ya está en pantalla y envía el formulario a la ruta de
 * confirmación; el servidor vuelve a validar cada fila.
 */
(function () {
  'use strict';

  var form = document.getElementById('formResultados');
  var modalEl = document.getElementById('modalConfirmarResultados');
  if (!form || !modalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
    return;
  }

  var modal = bootstrap.Modal.getOrCreateInstance(modalEl);

  function lista(valor) {
    try {
      var datos = JSON.parse(valor || '[]');
      return Array.isArray(datos) ? datos.map(function (item) { return String(item).toUpperCase(); }) : [];
    } catch (e) {
      return [];
    }
  }

  var literalesPromueven = lista(form.getAttribute('data-literales-promueven'));

  function normalizar(valor) {
    return String(valor || '').toUpperCase().trim();
  }

  function abrir() {
    var total = 0;
    var promovidos = 0;
    var noPromovidos = 0;
    var sinLiteral = 0;
    var confirmados = 0;

    document.querySelectorAll('.fila-estudiante').forEach(function (fila) {
      total++;
      var estaConfirmado = fila.getAttribute('data-esta-confirmado') === '1';
      var select = fila.querySelector('.select-literal-pill');
      var literal = normalizar(select ? select.value : '');

      if (estaConfirmado) {
        confirmados++;
      } else if (literal === 'E') {
        noPromovidos++;
      } else if (literalesPromueven.indexOf(literal) !== -1) {
        promovidos++;
      } else {
        sinLiteral++;
      }
    });

    document.getElementById('modalResumenTotal').textContent = total;
    document.getElementById('modalResumenPromovidos').textContent = promovidos;
    document.getElementById('modalResumenNoPromovidos').textContent = noPromovidos;
    document.getElementById('modalResumenSinLiteral').textContent = sinLiteral;
    document.getElementById('modalResumenYaConfirmados').textContent = confirmados;

    modal.show();
  }

  document.querySelectorAll('.btn-abrir-confirmar').forEach(function (boton) {
    boton.addEventListener('click', abrir);
  });

  var btnEjecutar = document.getElementById('btnEjecutarConfirmacion');
  if (btnEjecutar) {
    btnEjecutar.addEventListener('click', function () {
      var url = form.getAttribute('data-url-confirmar');
      if (url) {
        form.action = url;
      }
      modal.hide();
      form.submit();
    });
  }
})();
