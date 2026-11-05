/**
 * public/assets/js/modules/estadisticas/estadisticas.js
 * Lógica de interacción para el módulo de Estadísticas Institucionales y Control de Hermanos.
 */
(function () {
  'use strict';

  function initAll() {
    initFiltrosGlobales();
    initBuscadorHermanos();
    initPerPageHermanos();
    initPersistenciaPestana();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
  } else {
    initAll();
  }

  /**
   * Sincronización automática de filtros globales (Año, Grado, Sección).
   */
  function initFiltrosGlobales() {
    const form = document.getElementById('formFiltrosEstadisticas');
    if (!form) return;

    const selectAnio = document.getElementById('filtroAnio');
    const selectGrado = document.getElementById('filtroGrado');
    const selectSeccion = document.getElementById('filtroSeccion');

    if (selectAnio) {
      selectAnio.addEventListener('change', function () {
        if (selectGrado) selectGrado.value = '';
        if (selectSeccion) selectSeccion.value = '';
        form.submit();
      });
    }

    if (selectGrado) {
      selectGrado.addEventListener('change', function () {
        if (selectSeccion) selectSeccion.value = '';
        form.submit();
      });
    }

    if (selectSeccion) {
      selectSeccion.addEventListener('change', function () {
        form.submit();
      });
    }
  }

  /**
   * Búsqueda server-side sobre la tabla de control de hermanos.
   * Al escribir se reenvía el formulario global para buscar en todas las páginas.
   */
  function initBuscadorHermanos() {
    const inputBuscar = document.getElementById('buscarHermanos');
    const form = document.getElementById('formFiltrosEstadisticas');
    if (!inputBuscar || !form) return;

    let timer = null;
    inputBuscar.addEventListener('input', function () {
      window.clearTimeout(timer);
      timer = window.setTimeout(function () {
        if (typeof form.requestSubmit === 'function') {
          form.requestSubmit();
        } else {
          form.submit();
        }
      }, 400);
    });
  }

  /**
   * Cambio de filas por página en el Control de Hermanos.
   */
  function initPerPageHermanos() {
    const select = document.getElementById('perPageHermanos');
    const form = document.getElementById('formFiltrosEstadisticas');
    if (!select || !form) return;

    select.addEventListener('change', function () {
      form.submit();
    });
  }

  /**
   * Persistencia de la pestaña activa en formularios y URL + sincronización de gráficas.
   */
  function initPersistenciaPestana() {
    const tabButtons = document.querySelectorAll('#estadisticasTabs button[data-bs-toggle="tab"]');
    const inputPestana = document.getElementById('inputPestanaActiva');

    tabButtons.forEach(function (btn) {
      btn.addEventListener('shown.bs.tab', function (e) {
        const targetId = e.target.getAttribute('data-bs-target');
        const pestanaKey = targetId ? targetId.replace('#tab-', '') : 'hermanos';

        if (inputPestana) {
          inputPestana.value = pestanaKey;
        }

        if (window.history && window.history.replaceState) {
          const url = new URL(window.location);
          url.searchParams.set('pestana', pestanaKey);
          window.history.replaceState({}, '', url);
        }

        // Si la pestaña mostrada contiene gráficas de Chart.js, inicializarlas o reajustar tamaño de inmediato
        if (window.SIGDECharts && typeof window.SIGDECharts.init === 'function') {
          const tabPane = targetId ? document.querySelector(targetId) : null;
          if (tabPane) {
            window.SIGDECharts.init(tabPane);
          }
        }
      });
    });
  }
})();
