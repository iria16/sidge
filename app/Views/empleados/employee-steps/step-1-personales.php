<?php
/**
 * Paso 1 - Datos personales.
 * Sin variables externas. El id/name de cada campo lo lee empleado.js (validación y resumen).
*/
$prefixId = '';
$prefixName = '';
$showPhoto = true;
$minDate = date('Y-m-d', strtotime('-100 years'));
$maxDate = date('Y-m-d', strtotime('-18 years'));
$dateTitle = 'El empleado debe tener entre 18 y 100 años.';
$docMinLength = '7';
$docMaxLength = '8';
$btnQuitarFotoId = 'btnQuitarFotoRegistro';
/* El empleado declara SOLO la entidad federal de nacimiento. Se pone en false aunque
   ese sea el valor por defecto del componente, por la misma razón que en el sub-modal
   de familiares: los `include` de PHP comparten el scope y basta con que otra vista de
   la misma pantalla haya puesto la marca en true para que se cuele. */
$pideMunicipioParroquiaNacimiento = false;

// Aviso dinámico si la persona ya existe en el sistema (ej. representante / familiar)
$slotTrasIdentificacion = '
<div id="avisoPersonaExistente" class="alert alert-info py-2 px-3 small d-none mt-2 d-flex align-items-center gap-2">
  <i class="bi bi-info-circle-fill fs-5 text-primary"></i>
  <div id="avisoPersonaExistenteTexto"></div>
</div>';

include __DIR__ . '/../../components/persona-form.php';
?>
