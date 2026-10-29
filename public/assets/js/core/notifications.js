(function () {
  'use strict';

  var BASE = window.BASE_URL || '/';
  var ENDPOINTS = {
    contador: BASE + 'notificacion/contador',
    listar: BASE + 'notificacion/listar',
    leer: BASE + 'notificacion/leer',
    leerTodas: BASE + 'notificacion/leerTodas'
  };

  function csrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.content : '';
  }

  var badge = document.getElementById('notifyCount');
  var dot = document.getElementById('notifyDot');
  var list = document.getElementById('notifyList');
  var markAll = document.getElementById('notifyMarkAll');
  var headerBadge = document.getElementById('notifyDropdownCount');

  if (!badge || !list) return;

  var ultimoContador = null;

  function post(url, data) {
    var body = new URLSearchParams(data || {});
    body.append('csrf', csrfToken());
    return fetch(url, {
      method: 'POST',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-Token': csrfToken()
      },
      body: body
    }).then(function (r) { return r.json(); });
  }

  function get(url) {
    return fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(function (r) { return r.json(); });
  }

  function escapeHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function tiempoRelativo(fecha) {
    if (!fecha) return '';
    try {
      var d = new Date(fecha.replace(/-/g, '/'));
      if (isNaN(d.getTime())) return fecha;
      var ahora = new Date();
      var diffSeg = Math.floor((ahora - d) / 1000);
      if (diffSeg < 60) return 'Hace un momento';
      var diffMin = Math.floor(diffSeg / 60);
      if (diffMin < 60) return 'Hace ' + diffMin + (diffMin === 1 ? ' min' : ' mins');
      var diffHoras = Math.floor(diffMin / 60);
      if (diffHoras < 24) return 'Hace ' + diffHoras + (diffHoras === 1 ? ' hora' : ' horas');
      var diffDias = Math.floor(diffHoras / 24);
      if (diffDias < 7) return 'Hace ' + diffDias + (diffDias === 1 ? ' día' : ' días');
      return d.toLocaleDateString('es-VE', { day: '2-digit', month: '2-digit', year: 'numeric' });
    } catch (e) {
      return fecha;
    }
  }

  function renderItems(items) {
    if (!items || !items.length) {
      list.innerHTML = '<div class="notify-dropdown__empty text-secondary small p-4 text-center">'
        + '<i class="bi bi-bell-slash fs-2 d-block mb-2 text-muted opacity-50"></i>'
        + '<div class="fw-semibold text-dark">Sin notificaciones</div>'
        + '<div class="text-muted small">Estás al día con todos tus avisos</div>'
        + '</div>';
      return;
    }
    list.innerHTML = items.map(function (n) {
      var unread = !n.leida;
      var icon = 'bi-bell';
      var iconTone = 'primary';
      var tipo = (n.tipo || '').toLowerCase();

      if (tipo.indexOf('danger') !== -1 || tipo.indexOf('error') !== -1 || tipo.indexOf('alerta') !== -1) {
        icon = 'bi-exclamation-octagon';
        iconTone = 'danger';
      } else if (tipo.indexOf('warning') !== -1 || tipo.indexOf('atencion') !== -1) {
        icon = 'bi-exclamation-triangle';
        iconTone = 'warning';
      } else if (tipo.indexOf('success') !== -1 || tipo.indexOf('exito') !== -1) {
        icon = 'bi-check-circle';
        iconTone = 'success';
      } else if (tipo.indexOf('info') !== -1) {
        icon = 'bi-info-circle';
        iconTone = 'info';
      }

      return '<button type="button" class="notify-dropdown__item' + (unread ? ' is-unread' : '') + '" data-id="' + n.id_notificacion + '" data-url="' + (n.url_destino || '#') + '">'
        + '<span class="notify-dropdown__icon notify-dropdown__icon--' + iconTone + '"><i class="bi ' + icon + '" aria-hidden="true"></i></span>'
        + '<div class="notify-dropdown__body">'
        + (n.titulo ? '<div class="notify-dropdown__item-title">' + escapeHtml(n.titulo) + '</div>' : '')
        + '<p class="notify-dropdown__text">' + escapeHtml(n.mensaje || '') + '</p>'
        + '<time class="notify-dropdown__time"><i class="bi bi-clock me-1" aria-hidden="true"></i>' + tiempoRelativo(n.fecha_creacion) + '</time>'
        + '</div>'
        + (unread ? '<span class="notify-dropdown__unread-dot" title="No leída"></span>' : '')
        + '</button>';
    }).join('');

    list.querySelectorAll('.notify-dropdown__item').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = btn.getAttribute('data-id');
        var url = btn.getAttribute('data-url');
        post(ENDPOINTS.leer, { id: id }).finally(function () {
          actualizarContador();
          if (url && url !== '#') window.location.href = url;
        });
      });
    });
  }

  function actualizarContador() {
    get(ENDPOINTS.contador).then(function (res) {
      if (!res || !res.ok) return;
      var total = (res.data && typeof res.data.total !== 'undefined') ? res.data.total : 0;
      if (ultimoContador !== null && total > ultimoContador && window.Swal) {
        window.Swal.fire({
          toast: true,
          position: 'top-end',
          icon: 'info',
          title: 'Nueva notificación',
          showConfirmButton: false,
          timer: 2500
        });
      }
      ultimoContador = total;
      if (total > 0) {
        badge.textContent = total > 99 ? '99+' : total;
        badge.classList.remove('d-none');
        if (dot) dot.classList.add('d-none');
        if (headerBadge) {
          headerBadge.textContent = total + (total === 1 ? ' nueva' : ' nuevas');
          headerBadge.classList.remove('d-none');
        }
      } else {
        badge.classList.add('d-none');
        if (dot) dot.classList.add('d-none');
        if (headerBadge) {
          headerBadge.classList.add('d-none');
        }
      }
    }).catch(function () {});
  }

  function cargarLista() {
    get(ENDPOINTS.listar).then(function (res) {
      if (!res || !res.ok) return;
      renderItems((res.data && res.data.items) || []);
    }).catch(function () {});
  }

  if (markAll) {
    markAll.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      post(ENDPOINTS.leerTodas, {}).then(function () {
        ultimoContador = 0;
        actualizarContador();
        cargarLista();
      });
    });
  }

  var dropdown = document.getElementById('notifyDropdown');
  if (dropdown) {
    dropdown.addEventListener('show.bs.dropdown', cargarLista);
  }

  actualizarContador();
  cargarLista();
  setInterval(actualizarContador, 45000);
})();
