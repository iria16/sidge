/**
 * Módulo Docente: búsqueda sin recargar y agrupación por representante.
 *
 * La agrupación NO vuelve a pedir la lista al servidor: usa el mismo JSON que
 * ya venía con la página. Es a propósito. Si el interruptor cambiara el conjunto
 * de datos, el contador del encabezado dejaría de cuadrar con la tabla, que es
 * justo la clase de sorpresa que hace desconfiar de una pantalla.
 *
 * La búsqueda sí usa fetch, y manda los filtros actuales para que escribir en el
 * buscador no borre lo que el usuario seleccionó antes.
 */
(function () {
    'use strict';

    /* ======================================================================
       Listados de la sección (/docente/nomina)
       El filtrado automático (change + debounce de búsqueda) lo maneja
       initAutoFilters() en main.js sobre #filtrosListadoNomina. Aquí solo
       sincronizamos q, sexo y mes en el botón Exportar PDF y en los chips
       cuando data-panel:updated avisa que terminó un filtrado.
       ====================================================================== */
    var formListado = document.getElementById('filtrosListadoNomina');
    if (formListado) {
        function sincronizarEnlaces(urlActual) {
            var origen;
            try {
                origen = new URL(urlActual || window.location.href, window.location.origin);
            } catch (err) {
                return;
            }

            /* «Visualizar documento» es un botón, no un enlace: lleva la ruta
               en data-base-pdf. Los chips siguen siendo enlaces, así que cada
               uno recibe su filtro en el atributo que corresponda. */
            var enlaces = document.querySelectorAll('#btnExportarNominaPdf, .nomina-chip');
            enlaces.forEach(function (enlace) {
                var esBoton = enlace.tagName === 'BUTTON';
                var actual = esBoton ? enlace.getAttribute('data-base-pdf') : enlace.getAttribute('href');
                if (!actual) { return; }

                var destino;
                try {
                    destino = new URL(actual, window.location.origin);
                } catch (err) {
                    return;
                }

                var attrFiltros = enlace.getAttribute('data-filtros');
                /* Si el atributo no existe asumimos los tres filtros de siempre.
                   Si existe pero viene vacío —el acta, que no lleva barra— no se
                   arrastra ninguno: antes un "" caía en el valor por defecto y el
                   buscador de otra pestaña viajaba a una vista que no lo tiene. */
                var permitidos  = attrFiltros === null ? ['q', 'sexo', 'mes']
                                    : (attrFiltros ? attrFiltros.split(',') : []);

                ['q', 'sexo', 'mes'].forEach(function (clave) {
                    if (permitidos.indexOf(clave) === -1) {
                        destino.searchParams.delete(clave);
                        return;
                    }
                    var valor = origen.searchParams.get(clave);
                    if (valor !== null && valor.trim() !== '') {
                        destino.searchParams.set(clave, valor.trim());
                    } else {
                        destino.searchParams.delete(clave);
                    }
                });

                enlace.setAttribute(esBoton ? 'data-base-pdf' : 'href', destino.pathname + destino.search);
            });
        }

        document.addEventListener('data-panel:updated', function (ev) {
            if (!ev.detail || ev.detail.form !== formListado) { return; }
            sincronizarEnlaces(ev.detail.url || window.location.href);
        });
    }

})();