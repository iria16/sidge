/* SIGDE - core/institucion-procedencia.js
 * Comportamiento compartido del componente
 * app/Views/components/institucion-procedencia-form.php:
 * alterna los bloques nacional/extranjera y ajusta `required` y valores al
 * cambiar el interruptor.
 *
 * La cascada estado -> municipio -> parroquia NO se inicializa aquí: cada
 * pantalla la levanta con el endpoint de su controlador (catalogo.js,
 * enrollments.js y empleado-alta.js). Así el componente no necesita conocer
 * rutas ni duplicar peticiones.
 */
(function () {
  'use strict';

  function actualizar(interruptor) {
    if (!interruptor) return;

    var contenedor = interruptor.closest('form')
      || interruptor.closest('.modal-content')
      || document;
    var esExtranjera = interruptor.checked;
    var bloqueNacional = contenedor.querySelector('[data-bloque="nacional"]');
    var bloqueExtranjera = contenedor.querySelector('[data-bloque="extranjera"]');

    if (bloqueNacional) {
      bloqueNacional.classList.toggle('d-none', esExtranjera);

      var camposNacionales = bloqueNacional.querySelectorAll('select, input');
      for (var i = 0; i < camposNacionales.length; i++) {
        var campo = camposNacionales[i];
        var bloqueOculto = campo.closest('.d-none');
        campo.required = !esExtranjera && !bloqueOculto;

        if (esExtranjera && campo.tagName === 'SELECT') campo.value = '';
        if (esExtranjera && campo.tagName === 'INPUT') campo.value = '';
      }
    }

    if (bloqueExtranjera) {
      bloqueExtranjera.classList.toggle('d-none', !esExtranjera);
    }
  }

  function init() {
    var interruptores = document.querySelectorAll('[data-procedencia-prefijo]');

    Array.prototype.forEach.call(interruptores, function (interruptor) {
      if (interruptor.dataset.procedenciaInicializado === '1') return;

      interruptor.dataset.procedenciaInicializado = '1';
      interruptor.addEventListener('change', function () {
        actualizar(interruptor);
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  /* API para que un módulo sincronice el estado después de cargar datos,
     por ejemplo al abrir el modal de edición de catálogo. */
  window.SIGDEInstitucionProcedencia = { actualizar: actualizar };
})();
