<?php
declare(strict_types=1);

/**
 * Modal: retiro/modals/detalle-retiro-modal.php
 * Detalle de la solicitud. El cuerpo lo carga por AJAX retiro/ver.php.
 */
  $modalId       = 'detalleRetiroModal';
  $modalTitle    = 'Detalle de Retiro';
  $modalSubtitle = 'Información de la solicitud seleccionada.';
  ob_start();
  ?>
<?php /* El cuerpo del modal lo arma el servidor (retiro/ver.php) y el JS lo vuelca
 dentro de #retiroDetalleCuerpo. Antes estos campos vivían en el markup con un
 id cada uno y los llenaba el navegador leyendo atributos data- del botón, que
 ya no lleva: quedaban todos en "—". Al pedirlo al servidor, el modal y la
 fila del listado no pueden mostrar dos versiones del mismo dato.*/ ?>
<div id="retiroDetalleCuerpo">
  <div class="text-center text-support py-4">
    <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
    Cargando...
  </div>
</div>
<?php
$modalBody = ob_get_clean();
$modalFooter = '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>';
include __DIR__ . '/../../../components/modal.php';
