/**
 * public/assets/js/modules/estadisticas/estadisticas.js
 * Lógica de interacción para el módulo de Estadísticas Institucionales y Control de Hermanos.
 */
(function () {
  'use strict';

  function initAll() {
    initFiltrosGlobales();
    initBuscadorHermanos();
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
   * Búsqueda en tiempo real sobre la tabla de control de hermanos.
   */
  function initBuscadorHermanos() {
    const inputBuscar = document.getElementById('buscarHermanos');
    const tabla = document.getElementById('tablaControlHermanos');
    if (!inputBuscar || !tabla) return;

    const filas = tabla.querySelectorAll('tbody tr.fila-familia');
    const sinResultados = document.getElementById('sinResultadosHermanos');

    inputBuscar.addEventListener('input', function () {
      const q = this.value.toLowerCase().trim();
      let visibles = 0;

      filas.forEach(function (fila) {
        const textoFila = (fila.textContent || '').toLowerCase();
        const idDetalle = fila.getAttribute('data-bs-target');
        let textoDetalle = '';
        if (idDetalle) {
          const elDetalle = document.querySelector(idDetalle);
          if (elDetalle) {
            textoDetalle = (elDetalle.textContent || '').toLowerCase();
          }
        }

        const coincide = q === '' || textoFila.includes(q) || textoDetalle.includes(q);
        fila.classList.toggle('d-none', !coincide);

        if (idDetalle) {
          const elDetalleTr = document.querySelector(idDetalle);
          if (elDetalleTr && !coincide) {
            elDetalleTr.classList.add('d-none');
          }
        }

        if (coincide) {
          visibles++;
        }
      });

      if (sinResultados) {
        sinResultados.classList.toggle('d-none', visibles > 0);
      }
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
