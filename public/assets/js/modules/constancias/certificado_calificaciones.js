(function () {
  'use strict';

  let estudianteSeleccionado = null;
  let estudiantesFiltrados = [];

  document.addEventListener('DOMContentLoaded', function () {
    inicializarFiltrosBusqueda();
    inicializarEventosModal();
  });

  function inicializarFiltrosBusqueda() {
    const filtroIndividual = document.getElementById('filtroEstudiante');
    if (filtroIndividual) {
      filtroIndividual.addEventListener('input', function () {
        filtrarEstudiantesIndividual();
      });
    }
  }

  function filtrarEstudiantesIndividual() {
    const filtro = document.getElementById('filtroEstudiante').value.toLowerCase();
    const lista = document.getElementById('listaEstudiantesIndividual');
    const botones = lista.getElementsByTagName('button');

    Array.from(botones).forEach(boton => {
      const texto = boton.textContent.toLowerCase();
      boton.style.display = texto.includes(filtro) ? '' : 'none';
    });
  }

  function inicializarEventosModal() {
    const modalIndividual = document.getElementById('modalGenerarIndividual');
    if (modalIndividual) {
      modalIndividual.addEventListener('shown.bs.modal', function () {
        document.getElementById('filtroEstudiante').focus();
      });

      modalIndividual.addEventListener('hidden.bs.modal', function () {
        limpiarSeleccionIndividual();
      });
    }

    const btnGenerarIndividual = document.getElementById('btnGenerarIndividual');
    if (btnGenerarIndividual) {
      btnGenerarIndividual.addEventListener('click', function () {
        if (estudianteSeleccionado) {
          generarCertificadoIndividual();
        }
      });
    }

    const btnGenerarPorSeccion = document.getElementById('btnGenerarPorSeccion');
    if (btnGenerarPorSeccion) {
      btnGenerarPorSeccion.addEventListener('click', function () {
        generarCertificadoPorSeccion();
      });
    }
  }

  function seleccionarEstudianteIndividual(id, nombre) {
    estudianteSeleccionado = {
      id: id,
      nombre: nombre
    };

    document.querySelectorAll('#listaEstudiantesIndividual button').forEach(boton => {
      boton.classList.remove('active');
      boton.style.borderColor = '';
    });

    const botonSeleccionado = event.currentTarget;
    botonSeleccionado.classList.add('active');
    botonSeleccionado.style.borderColor = '#0d6efd';
    botonSeleccionado.style.borderWidth = '2px';

    document.getElementById('btnGenerarIndividual').disabled = false;
  }

  function limpiarSeleccionIndividual() {
    estudianteSeleccionado = null;
    document.querySelectorAll('#listaEstudiantesIndividual button').forEach(boton => {
      boton.classList.remove('active');
      boton.style.borderColor = '';
      boton.style.borderWidth = '';
    });
    document.getElementById('btnGenerarIndividual').disabled = true;
  }

  function generarCertificadoIndividual() {
    if (!estudianteSeleccionado) return;

    const formData = new FormData();
    formData.append('id_estudiante', estudianteSeleccionado.id);
    formData.append('id_anio_escolar', <?= $idAnioEscolar ?>);
    formData.append('id_estructura_academica', <?= $idEstructura ?>);

    fetch('<?= BASE_URL ?>certificadoCalificaciones/generarIndividual', {
      method: 'POST',
      body: formData,
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          mostrarNotificacion('Éxito', data.message, 'success');
          setTimeout(() => {
            location.reload();
          }, 1500);
        } else {
          mostrarNotificacion('Error', data.message, 'error');
        }
      })
      .catch(error => {
        mostrarNotificacion('Error', 'Error al generar el certificado: ' + error.message, 'error');
      });
  }

  function generarCertificadoPorSeccion() {
    const textoArt108 = document.getElementById('textoArt108').value;

    const formData = new FormData();
    formData.append('id_estructura_academica', <?= $idEstructura ?>);
    formData.append('id_anio_escolar', <?= $idAnioEscolar ?>);
    if (textoArt108) {
      formData.append('texto_art108', textoArt108);
    }

    fetch('<?= BASE_URL ?>certificadoCalificaciones/generarPorSeccion', {
      method: 'POST',
      body: formData,
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          mostrarNotificacion('Éxito', data.message, 'success');
          setTimeout(() => {
            location.reload();
          }, 1500);
        } else {
          mostrarNotificacion('Error', data.message, 'error');
        }
      })
      .catch(error => {
        mostrarNotificacion('Error', 'Error al generar el certificado: ' + error.message, 'error');
      });
  }

  function mostrarNotificacion(titulo, mensaje, tipo) {
    const alerta = document.createElement('div');
    alerta.className = `alert alert-${tipo} alert-dismissible fade show position-fixed`;
    alerta.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 400px;';
    alerta.innerHTML = `
      <strong>${titulo}:</strong> ${mensaje}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    `;

    document.body.appendChild(alerta);

    setTimeout(() => {
      alerta.classList.remove('show');
      setTimeout(() => {
        alerta.remove();
      }, 300);
    }, 5000);
  }
})();
