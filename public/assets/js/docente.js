/**
 * Módulo Docente: búsqueda sin recargar y agrupación por representante.
 *
 * La agrupación NO vuelve a pedir la lista al servidor: usa el mismo JSON que
 * ya venía con la página. Es de propósito. Si el interruptor cambiara el conjunto
 * de datos, el contador del encabezado dejaría de cuadrar con la tabla, que es
 * justo la clase de sorpresa que hace desconfiar de una pantalla.
 *
 * La búsqueda sí usa fetch, y manda los filtros actuales para que escribir en el
 * buscador no borre lo que el usuario seleccionó antes.
 */
(function () {
    'use strict';

    var datos = document.getElementById('datosNomina');
    if (!datos) { return; }

    var cfg;
    try { cfg = JSON.parse(datos.textContent); } catch (e) { return; }

    var form      = document.getElementById('filtrosNomina');
    var cuerpo    = document.getElementById('cuerpoNomina');
    var agrupada  = document.getElementById('vistaAgrupada');
    var interruptor = document.getElementById('agrupar');
    var tabla     = document.querySelector('.card .table-responsive');

    if (!form || !cuerpo || !agrupada || !interruptor || !tabla) { return; }

    var e = function (v) {
        var d = document.createElement('div');
        d.textContent = (v === null || v === undefined) ? '' : String(v);
        return d.innerHTML;
    };

    /* ── Fila de estudiante reutilizada por la búsqueda ───────────────────── */
    function filaEstudiante(f, indice) {
        var etiquetaLiteral = cfg.etiquetaLiteral || {};

        var telefono = '';
        if (f.numero_telefono_principal) {
            telefono = '<a class="text-decoration-none" href="tel:' +
                e((f.codigo_telefono_principal || '') + f.numero_telefono_principal) + '">' +
                e(f.numero_telefono_principal) + '</a>';
        } else {
            telefono = '<span class="badge bg-danger-subtle text-danger-emphasis">Sin teléfono</span>';
        }

        return '<tr>' +
            '<td class="ps-3 text-muted">' + indice + '</td>' +
            '<td><div class="fw-semibold">' + e(f.nombre) + '</div>' +
                '<div class="small text-muted">' + (f.sexo === 'F' ? 'Femenino' : 'Masculino') +
                '</div></td>' +
            '<td class="small">' + (f.cedula_escolar ? e(f.cedula_escolar) : '—') + '</td>' +
            '<td>' + parseInt(f.edad, 10) + ' años</td>' +
            '<td><div>' + e(f.representante || 'Sin representante') + '</div>' +
                '<div class="small text-muted">' + e(f.parentesco || '') + '</div></td>' +
            '<td class="small">' + telefono + '</td>' +
            '<td><span class="badge bg-light text-dark">' +
                e(etiquetaLiteral[f.estado_literal] || 'Pendiente') + '</span></td>' +
            '<td class="text-end pe-3"><a class="btn btn-sm btn-outline-primary" href="' +
                cfg.urlSeccion.replace('/seccion', '') + '/estudiante?id=' +
                encodeURIComponent(f.id_estudiante) + '">' +
                '<i class="bi bi-person-vcard me-1" aria-hidden="true"></i>Ver ficha</a></td>' +
        '</tr>';
    }

    /* ── Agrupación por representante ─────────────────────────────────────── */
    function pintarAgrupado() {
        var grupos = cfg.agrupado || {};
        var claves = Object.keys(grupos);

        if (claves.length === 0) {
            agrupada.innerHTML = '<div class="alert alert-light border text-muted text-center mb-0">' +
                'No hay estudiantes con los filtros seleccionados.</div>';
            return;
        }

        var html = '';
        claves.forEach(function (k) {
            var g = grupos[k];
            var tel = g.telefono
                ? '<a class="text-decoration-none" href="tel:' +
                  e((g.codigo_telefono || '') + g.telefono) + '">' + e(g.telefono) + '</a>'
                : '<span class="badge bg-danger-subtle text-danger-emphasis">Sin teléfono</span>';

            html += '<div class="card shadow-sm border-0 mb-3">' +
                '<div class="card-header bg-transparent d-flex flex-wrap justify-content-between align-items-center gap-2">' +
                    '<div><span class="fw-semibold">' + e(g.representante) + '</span>' +
                        '<span class="text-muted small ms-2">' + e(g.parentesco || '') + '</span></div>' +
                    '<div class="small">' + tel +
                        ' <span class="badge bg-primary-subtle text-primary-emphasis ms-2">' +
                        g.estudiantes.length + ' estudiante' +
                        (g.estudiantes.length === 1 ? '' : 's') + '</span></div>' +
                '</div>' +
                '<div class="table-responsive"><table class="table table-hover align-middle mb-0"><tbody>';

            g.estudiantes.forEach(function (f, i) { html += filaEstudiante(f, i + 1); });
            html += '</tbody></table></div></div>';
        });

        agrupada.innerHTML = html;
    }

    /* ── Alternar tabla / agrupado ────────────────────────────────────────── */
    function alternar() {
        var agrupado = interruptor.checked;
        tabla.classList.toggle('d-none', agrupado);
        agrupada.classList.toggle('d-none', !agrupado);
        if (agrupado) { pintarAgrupado(); }
    }
    interruptor.addEventListener('change', alternar);
    alternar();

    /* ── Búsqueda sin recargar ────────────────────────────────────────────── */
    var temporizador = null;

    function buscar() {
        var params = new URLSearchParams();
        ['q', 'sexo', 'vive_con', 'contacto', 'autorizados', 'literal']
            .forEach(function (campo) {
                var el = form.elements[campo];
                if (el && el.value) { params.set(campo, el.value); }
            });

        fetch(cfg.urlBuscar + '?' + params.toString(), {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res || res.ok !== true) { return; }
                var filas = res.data && res.data.nomina ? res.data.nomina : [];

                cfg.agrupado = agrupar(res.data ? res.data.nomina : []);
                if (interruptor.checked) { pintarAgrupado(); }

                cuerpo.innerHTML = filas.length
                    ? filas.map(function (f, i) { return filaEstudiante(f, i + 1); }).join('')
                    : '<tr><td colspan="8" class="text-center text-muted py-5">' +
                      'Ningún estudiante cumple los filtros seleccionados.</td></tr>';
            })
            .catch(function () { /* la tabla sigue mostrando lo último cargado */ });
    }

    function agrupar(nomina) {
        var grupos = {};
        (nomina || []).forEach(function (f) {
            var clave = f.id_representante ? 'rep:' + f.id_representante : 'est:' + f.id_estudiante;
            if (!grupos[clave]) {
                grupos[clave] = {
                    id_representante: f.id_representante,
                    representante: f.representante || 'Sin representante',
                    parentesco: f.parentesco,
                    telefono: f.numero_telefono_principal || '',
                    codigo_telefono: f.codigo_telefono_principal || '',
                    estudiantes: []
                };
            }
            grupos[clave].estudiantes.push(f);
        });
        return grupos;
    }

    var campo = form.elements['q'];
    if (campo) {
        campo.addEventListener('input', function () {
            clearTimeout(temporizador);
            temporizador = setTimeout(buscar, 300);
        });
    }
})();