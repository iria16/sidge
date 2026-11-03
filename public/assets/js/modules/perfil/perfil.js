/**
 * public/assets/js/modules/perfil/perfil.js
 * Gestión de cambio y eliminación de foto de perfil con feedback visual inmediato.
 */
(function () {
  'use strict';

  function initPerfilFoto() {
    var inputFoto = document.getElementById('inputFotoPerfil');
    var preview = document.getElementById('perfilFotoPreview');
    var btnQuitar = document.getElementById('btnQuitarFotoPerfil');

    if (!inputFoto || !preview) return;

    var urlActualizar = (typeof BASE_URL !== 'undefined' ? BASE_URL : '/') + 'perfil/actualizarFoto';

    function actualizarAvatarEncabezado(urlFoto, iniciales) {
      var avatar = document.getElementById('headerUserAvatar');
      if (!avatar) return;

      if (urlFoto) {
        var imagen = document.createElement('img');
        imagen.src = urlFoto + (urlFoto.indexOf('?') === -1 ? '?' : '&') + 't=' + Date.now();
        var nombreUsuario = document.querySelector('.app-header__profile-name');
        imagen.alt = nombreUsuario ? nombreUsuario.textContent : 'Foto de perfil';
        avatar.replaceChildren(imagen);
        avatar.setAttribute('aria-hidden', 'false');
        return;
      }

      avatar.textContent = iniciales || avatar.dataset.avatarInitials || 'U';
      avatar.setAttribute('aria-hidden', 'true');
    }

    // Manejar selección de nueva foto
    inputFoto.addEventListener('change', function () {
      var archivo = inputFoto.files && inputFoto.files[0];
      if (!archivo) return;

      // Validar tipo
      var tiposValidos = ['image/jpeg', 'image/png', 'image/webp'];
      if (!tiposValidos.includes(archivo.type)) {
        if (window.SIGDEUI && SIGDEUI.mostrarError) {
          SIGDEUI.mostrarError('La foto debe ser en formato JPG, PNG o WEBP.', 'Formato no permitido');
        } else {
          alert('La foto debe ser en formato JPG, PNG o WEBP.');
        }
        inputFoto.value = '';
        return;
      }

      // Validar tamaño (máximo 2 MB)
      if (archivo.size > 2 * 1024 * 1024) {
        if (window.SIGDEUI && SIGDEUI.mostrarError) {
          SIGDEUI.mostrarError('La foto no puede superar los 2 MB.', 'Archivo muy pesado');
        } else {
          alert('La foto no puede superar los 2 MB.');
        }
        inputFoto.value = '';
        return;
      }

      var fd = new FormData();
      fd.append('foto', archivo);

      // Si existe token CSRF en meta
      var metaCsrf = document.querySelector('meta[name="csrf-token"]');
      if (metaCsrf) {
        fd.append('csrf', metaCsrf.getAttribute('content'));
      }

      SIGDEUI.enviar(urlActualizar, {
        metodo: 'POST',
        cuerpo: fd
      })
        .then(function (respuesta) {
          var payload = respuesta.data;
          if (respuesta.ok && payload && payload.ok) {
            // Actualizar imagen en preview
            var urlFoto = payload.data && payload.data.foto_url;
            if (urlFoto) {
              preview.innerHTML = '<img src="' + urlFoto + '?t=' + Date.now() + '" alt="Foto de perfil" id="imgPerfilFoto">';
              actualizarAvatarEncabezado(urlFoto, payload.data.avatar_iniciales);
            }
            if (btnQuitar) {
              btnQuitar.classList.remove('d-none');
            }

            SIGDEUI.exito(payload.mensaje || 'Foto de perfil actualizada correctamente.', '¡Foto actualizada!');
          } else {
            SIGDEUI.mostrarError((payload && payload.mensaje) || 'No se pudo actualizar la foto de perfil.', 'Error');
          }
        })
        .catch(function () {
          SIGDEUI.mostrarError('Ocurrió un error al procesar la subida de la foto.', 'Error de red');
        })
        .finally(function () {
          inputFoto.value = '';
        });
    });

    // Manejar eliminación de foto
    if (btnQuitar) {
      btnQuitar.addEventListener('click', function () {
        SIGDEUI.confirmar({
          titulo: '¿Quitar foto de perfil?',
          texto: 'Se restablecerá la silueta estándar de usuario en tu cuenta.',
          confirmar: 'Sí, quitar foto',
          cancelar: 'Cancelar',
          icono: 'warning'
        }).then(function (confirmado) {
          if (!confirmado) return;

          var fd = new FormData();
          fd.append('eliminar_foto', '1');

          var metaCsrf = document.querySelector('meta[name="csrf-token"]');
          if (metaCsrf) {
            fd.append('csrf', metaCsrf.getAttribute('content'));
          }

          SIGDEUI.enviar(urlActualizar, {
            metodo: 'POST',
            cuerpo: fd
          })
            .then(function (respuesta) {
              var payload = respuesta.data;
              if (respuesta.ok && payload && payload.ok) {
                preview.innerHTML = '<i class="bi bi-person" id="iconoPerfilFallback"></i>';
                actualizarAvatarEncabezado('', payload.data && payload.data.avatar_iniciales);
                btnQuitar.classList.add('d-none');
                SIGDEUI.exito(payload.mensaje || 'Foto eliminada correctamente.', '¡Foto retirada!');
              } else {
                SIGDEUI.mostrarError((payload && payload.mensaje) || 'No se pudo quitar la foto.', 'Error');
              }
            })
            .catch(function () {
              SIGDEUI.mostrarError('Ocurrió un error al quitar la foto de perfil.', 'Error de red');
            });
        });
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPerfilFoto, { once: true });
  } else {
    initPerfilFoto();
  }
})();
