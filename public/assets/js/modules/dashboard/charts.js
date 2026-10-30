/**
 * public/assets/js/modules/dashboard/charts.js
 * Módulo reutilizable de gráficas institucionales para SIGDE (Chart.js).
 *
 * Lee la configuración y los datos desde atributos data-* en el HTML,
 * extrayendo la paleta institucional desde las variables CSS (--chart-1 a --chart-6)
 * con getComputedStyle.
 *
 * Compatible con navegación dinámica, pestañas (Bootstrap Tabs) y modales.
 */
(function () {
  'use strict';

  if (typeof window.Chart === 'undefined') {
    console.warn('SIGDE: Chart.js no está cargado.');
    return;
  }

  // Desactiva animaciones si el usuario prefiere movimiento reducido, o ajusta la duración
  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (prefersReducedMotion) {
    Chart.defaults.animation = false;
  } else if (Chart.defaults.animation && typeof Chart.defaults.animation === 'object') {
    Chart.defaults.animation.duration = 400;
  }
  Chart.defaults.font.family = "'Work Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif";
  Chart.defaults.color = '#1c2340';

  /**
   * Obtiene el valor real de una variable CSS definida en :root.
   */
  function getCssVar(varName, fallback) {
    const value = getComputedStyle(document.documentElement).getPropertyValue(varName).trim();
    return value !== '' ? value : fallback;
  }

  const PALETTE = {
    c1: getCssVar('--chart-1', '#0a1440'),
    c2: getCssVar('--chart-2', '#3b6fe0'),
    c3: getCssVar('--chart-3', '#8a90a3'),
    c4: getCssVar('--chart-4', '#254698'),
    c5: getCssVar('--chart-5', '#6380c2'),
    c6: getCssVar('--chart-6', '#5d6580'),
  };

  /**
   * Renderiza el estado vacío dentro del contenedor si no hay datos.
   */
  function renderEmptyState(container, message) {
    container.innerHTML = `
      <div class="chart-container__empty" role="status">
        <i class="bi bi-pie-chart" aria-hidden="true"></i>
        <p>${message || 'Sin datos para el período activo'}</p>
      </div>
    `;
  }

  /**
   * Inicializa una gráfica de dona (Doughnut).
   */
  function initDonutChart(container, config) {
    const labels = config.labels || [];
    const values = config.values || [];
    const emptyMsg = config.emptyMessage || 'Sin datos para el período activo';

    const total = values.reduce((acc, val) => acc + (Number(val) || 0), 0);
    if (total <= 0 || labels.length === 0) {
      renderEmptyState(container, emptyMsg);
      return;
    }

    const canvas = container.querySelector('canvas');
    if (!canvas) return;

    // Destruir instancia previa si existe para evitar Canvas already in use
    const existingChart = Chart.getChart(canvas);
    if (existingChart) {
      existingChart.destroy();
    }

    const colors = config.colors && config.colors.length > 0
      ? config.colors.map(k => PALETTE[k] || k)
      : [PALETTE.c1, PALETTE.c2, PALETTE.c3, PALETTE.c4, PALETTE.c5, PALETTE.c6];

    new Chart(canvas.getContext('2d'), {
      type: 'doughnut',
      data: {
        labels: labels,
        datasets: [{
          data: values,
          backgroundColor: colors.slice(0, labels.length),
          borderWidth: 2,
          borderColor: '#ffffff',
          hoverOffset: 4,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '68%',
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              boxWidth: 12,
              boxHeight: 12,
              padding: 14,
              font: { size: 12, weight: '500' },
            },
          },
          tooltip: {
            callbacks: {
              label: function (context) {
                const val = context.parsed || 0;
                const pct = total > 0 ? Math.round((val / total) * 100) : 0;
                return ` ${context.label}: ${val} (${pct}%)`;
              },
            },
          },
        },
      },
    });
  }

  /**
   * Inicializa una gráfica de barras agrupadas.
   */
  function initGroupedBarChart(container, config) {
    const labels = config.labels || [];
    const datasetsRaw = config.datasets || [];
    const emptyMsg = config.emptyMessage || 'Sin datos para el período activo';

    const hasData = datasetsRaw.some(ds => (ds.data || []).some(v => Number(v) > 0));
    if (!hasData || labels.length === 0) {
      renderEmptyState(container, emptyMsg);
      return;
    }

    const canvas = container.querySelector('canvas');
    if (!canvas) return;

    // Destruir instancia previa si existe
    const existingChart = Chart.getChart(canvas);
    if (existingChart) {
      existingChart.destroy();
    }

    const datasets = datasetsRaw.map((ds, index) => {
      const colorKey = ds.colorKey || (index === 0 ? 'c1' : 'c3');
      const color = PALETTE[colorKey] || ds.color || PALETTE.c1;
      return {
        label: ds.label || '',
        data: ds.data || [],
        backgroundColor: color,
        borderRadius: 4,
        maxBarThickness: 32,
        categoryPercentage: 0.7,
        barPercentage: 0.85,
      };
    });

    new Chart(canvas.getContext('2d'), {
      type: 'bar',
      data: {
        labels: labels,
        datasets: datasets,
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
          mode: 'index',
          intersect: false,
        },
        scales: {
          x: {
            grid: { display: false },
            ticks: { font: { size: 12, weight: '500' } },
          },
          y: {
            beginAtZero: true,
            ticks: {
              precision: 0,
              font: { size: 12 },
            },
            grid: {
              color: 'rgba(10, 20, 64, 0.06)',
            },
          },
        },
        plugins: {
          legend: {
            position: 'top',
            align: 'end',
            labels: {
              boxWidth: 12,
              boxHeight: 12,
              padding: 12,
              font: { size: 12, weight: '500' },
            },
          },
          tooltip: {
            padding: 10,
          },
        },
      },
    });
  }

  /**
   * Inicializa o reajusta una gráfica individual en su contenedor.
   */
  function initContainerChart(container) {
    const canvas = container.querySelector('canvas');
    if (canvas && Chart.getChart(canvas)) {
      if (container.offsetParent !== null) {
        Chart.getChart(canvas).resize();
      }
      return;
    }

    // Si el contenedor está dentro de una pestaña oculta (display: none),
    // posponer hasta que la pestaña sea activada para evitar tamaño 0x0.
    if (container.offsetParent === null) {
      return;
    }

    const chartType = container.getAttribute('data-sigde-chart');
    const rawData = container.getAttribute('data-chart-config');
    if (!rawData) return;

    let config = null;
    try {
      config = JSON.parse(rawData);
    } catch (e) {
      console.error('SIGDE Charts: Error al parsear JSON en contenedor', container, e);
      return;
    }

    if (chartType === 'donut') {
      initDonutChart(container, config);
    } else if (chartType === 'grouped-bar') {
      initGroupedBarChart(container, config);
    }
  }

  /**
   * Inicializa todas las gráficas dentro del alcance dado.
   */
  function initCharts(scope) {
    const root = scope || document;
    const containers = root.querySelectorAll('[data-sigde-chart]');
    containers.forEach(initContainerChart);
  }

  // Escuchar cuando una pestaña de Bootstrap se muestra (ej. en Estadísticas)
  document.addEventListener('shown.bs.tab', function (event) {
    const targetSelector = event.target.getAttribute('data-bs-target') || event.target.getAttribute('href');
    const tabPane = targetSelector ? document.querySelector(targetSelector) : null;
    if (tabPane) {
      initCharts(tabPane);
      tabPane.querySelectorAll('canvas').forEach(function (c) {
        const ch = Chart.getChart(c);
        if (ch) ch.resize();
      });
    } else {
      initCharts(document);
    }
  });

  // Escuchar cuando un modal de Bootstrap se muestra
  document.addEventListener('shown.bs.modal', function (event) {
    initCharts(event.target);
  });

  // Inicialización inmediata o al cargar el DOM
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      initCharts(document);
    });
  } else {
    initCharts(document);
  }

  // Exponer API global
  window.SIGDECharts = {
    init: initCharts,
    resizeAll: function () {
      document.querySelectorAll('canvas').forEach(function (c) {
        const ch = Chart.getChart(c);
        if (ch) ch.resize();
      });
    }
  };
})();
