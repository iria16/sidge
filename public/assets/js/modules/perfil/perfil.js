/**
 * public/assets/js/modules/perfil/perfil.js
 * Gestión de cambio y eliminación de foto de perfil con feedback visual inmediato.
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var inputFoto = document.getElementById('inputFotoPerfil');
    var inputCamara = document.getElementById('inputFotoPerfilCamara');
    var preview = document.getElementById('perfilFotoPreview');
    var btnQuitar = document.getElementById('btnQuitarFotoPerfil');
    var btnCamara = document.getElementById('btnTomarFotoPerfil');

    if (!inputFoto || !preview) return;

    var urlActualizar = (typeof BASE_URL !== 'undefined' ? BASE_URL : '/') + 'perfil/actualizarFoto';

    function mostrarError(mensaje, titulo) {
      if (window.SIGDEUI && SIGDEUI.mostrarError) {
        SIGDEUI.mostrarError(mensaje, titulo);
      } else {
        alert(mensaje);
      }
    }

    function actualizarAvatarEncabezado(urlFoto, iniciales) {
      var headerAvatar = document.getElementById('headerUserAvatar') || document.querySelector('.app-header__profile .avatar');
      if (!headerAvatar) return;

      if (urlFoto) {
        headerAvatar.innerHTML = '<img src="' + urlFoto + '" alt="Avatar" onerror="this.style.display=\'none\'; if(this.parentElement){this.parentElement.textContent=this.parentElement.getAttribute(\'data-avatar-initials\') || \'\';}">';
        headerAvatar.setAttribute('aria-hidden', 'false');
        return;
      }

      headerAvatar.textContent = iniciales || headerAvatar.getAttribute('data-avatar-initials') || 'U';
      headerAvatar.setAttribute('aria-hidden', 'true');
    }

    function subirFoto(input) {
      var archivo = input.files && input.files[0];
      if (!archivo) return;

      // Validar tipo
      var tiposValidos = ['image/jpeg', 'image/png', 'image/webp'];
      if (!tiposValidos.includes(archivo.type)) {
        mostrarError('La foto debe ser en formato JPG, PNG o WEBP.', 'Formato no permitido');
        input.value = '';
        return;
      }

      // Validar tamaño (máximo 2 MB)
      if (archivo.size > 2 * 1024 * 1024) {
        mostrarError('La foto no puede superar los 2 MB.', 'Archivo muy pesado');
        input.value = '';
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
            // Actualizar imagen en preview y cabecera
            var urlFoto = payload.data && payload.data.foto_url;
            if (urlFoto) {
              var timestamped = urlFoto + '?t=' + Date.now();
              preview.innerHTML = '<img src="' + timestamped + '" alt="Foto de perfil" id="imgPerfilFoto">';
              actualizarAvatarEncabezado(timestamped);
            }
            if (btnQuitar) {
              btnQuitar.classList.remove('d-none');
            }

            SIGDEUI.exito(payload.mensaje || 'Foto de perfil actualizada correctamente.', '¡Foto actualizada!');
          } else {
            mostrarError((payload && payload.mensaje) || 'No se pudo actualizar la foto de perfil.', 'Error');
          }
        })
        .catch(function () {
          mostrarError('Ocurrió un error al procesar la subida de la foto.', 'Error de red');
        })
        .finally(function () {
          input.value = '';
        });
    }

    // Manejar selección de nueva foto (galería / archivo)
    inputFoto.addEventListener('change', function () {
      subirFoto(inputFoto);
    });

    // Manejar captura directa con la cámara (móviles)
    if (inputCamara) {
      inputCamara.addEventListener('change', function () {
        subirFoto(inputCamara);
      });
    }

    if (btnCamara && inputCamara) {
      btnCamara.addEventListener('click', function () {
        inputCamara.click();
      });
    }

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
                btnQuitar.classList.add('d-none');
                actualizarAvatarEncabezado('');
                SIGDEUI.exito(payload.mensaje || 'Foto eliminada correctamente.', '¡Foto retirada!');
              } else {
                mostrarError((payload && payload.mensaje) || 'No se pudo quitar la foto.', 'Error');
              }
            })
            .catch(function () {
              mostrarError('Ocurrió un error al quitar la foto de perfil.', 'Error de red');
            });
        });
      });
    }
  });
})();
