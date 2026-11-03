<?php
/**
 * Paso 6 - Recaudos del estudiante.
 *
 * La tabla la pinta el partial de recaudos, el MISMO que usan la ficha del expediente y el
 * modal "Actualizar expediente" (y este último, la ratificación). Estaba escrita aquí a mano
 * y terminó viéndose distinta en cada pantalla: mismo dato, tres aspectos. Un solo partial
 * hace que se vean iguales por construcción y no por memoria.
 *
 * Variables:
 * - array $recaudos Cada fila: 'id', 'nombre', 'detalle', 'consignado', 'observacion'.
*/
?>

<section class="wizard-summary__section">
  <div class="wizard-summary__section-header">
    <span class="wizard-summary__icon"><i class="bi bi-folder2-open" aria-hidden="true"></i></span>
    <span class="wizard-summary__section-title">Recaudos del estudiante</span>
    <span class="status-badge status-badge--active" id="recaudosBadge">
      <span id="recaudosConsignadosCount">0</span> de
      <span id="recaudosTotalCount">0</span> consignados
    </span>
  </div>
  <div class="wizard-summary__card">
    <p class="text-support">
      La lista se calcula con los datos del estudiante y las personas registradas.
    </p>

    <?php
      /* Editable y sin botón propio: el guardado de este paso es el botón de abajo del
 asistente, no uno que diga "Guardar recaudos". El id del tbody lo necesita
 enrollments.js, que engancha ahí el cambio del switch.*/
      $recaudosEditable   = true;
      $recaudosBotonPropio = false;
      $recaudosContenedor = 'recaudosPaso6';
      $recaudosCuerpoId   = 'recaudosTbody';
    ?>
    <div id="<?= htmlspecialchars($recaudosContenedor) ?>" data-recaudos-panel>
      <div class="table-responsive">
        <table class="wizard-titulos-table" style="table-layout: fixed;">
          <colgroup>
            <col style="width: 42%">
            <col style="width: 20%">
            <col style="width: 38%">
          </colgroup>
          <thead>
            <tr><th>Documento físico</th><th class="text-center">Consignado</th><th>Observación</th></tr>
          </thead>
          <tbody id="recaudosTbody">
            <tr><td colspan="3" class="text-center py-3 text-support">
              Completa los pasos anteriores para calcular los recaudos.
            </td></tr>
          </tbody>
        </table>
      </div>
    </div>
    <input type="hidden" name="recaudos_json" id="recaudosJson" value="{}">
  </div>
</section>
