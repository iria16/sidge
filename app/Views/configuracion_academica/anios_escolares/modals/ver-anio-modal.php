<?php
declare(strict_types=1);

/**
 * Modal: anios_escolares/modals/ver-anio-modal.php
 * Detalle de un período académico.
 */
$modalId = 'verAnioModal';
$modalTitle = 'Información del Año Escolar';
$modalSubtitle = 'Consulta del período seleccionado.';
ob_start();
?>
<dl class="row mb-0">
  <dt class="col-sm-5">Período</dt><dd class="col-sm-7" id="verAnioNombre">—</dd>
  <dt class="col-sm-5">Estado</dt><dd class="col-sm-7" id="verAnioEstado">—</dd>
  <dt class="col-sm-5">Fecha de inicio</dt><dd class="col-sm-7" id="verAnioInicio">—</dd>
  <dt class="col-sm-5">Fecha de cierre institucional</dt><dd class="col-sm-7" id="verAnioCierre">—</dd>
  <?php /* Los tres bloques de abajo se OCULTAN si el dato no existe, en vez de mostrar un
 guion permanente. Un período activo no tiene "cerrado por" porque todavía no se ha
 cerrado, y un período abierto antes de que existiera el registro tampoco tiene
 "iniciado por": pintar cuatro filas con guion permanente es ruido, y además hace
 creer que la información existe y se perdió. El guion queda solo para los datos
 que el propio formulario de alta siempre tiene (nombre, fechas).*/ ?>
  <dt class="col-sm-5 anio-dato-etiqueta" id="verAnioIniciadoPorEtiqueta">Iniciado por</dt>
  <dd class="col-sm-7" id="verAnioIniciadoPor" hidden></dd>
  <dt class="col-sm-5 anio-dato-etiqueta" id="verAnioIniciadoEnEtiqueta">Iniciado el</dt>
  <dd class="col-sm-7" id="verAnioIniciadoEn" hidden></dd>
  <dt class="col-sm-5 anio-dato-etiqueta" id="verAnioReabiertoPorEtiqueta">Reabierto por</dt>
  <dd class="col-sm-7" id="verAnioReabiertoPor" hidden></dd>
  <dt class="col-sm-5 anio-dato-etiqueta" id="verAnioReaperturaEtiqueta">Motivo de la reapertura</dt>
  <dd class="col-sm-7" id="verAnioReapertura" hidden></dd>
  <dt class="col-sm-5 anio-dato-etiqueta" id="verAnioCerradoPorEtiqueta">Cerrado por</dt>
  <dd class="col-sm-7" id="verAnioCerradoPor" hidden></dd>
  <dt class="col-sm-5 anio-dato-etiqueta" id="verAnioCerradoEnEtiqueta">Cerrado el</dt>
  <dd class="col-sm-7" id="verAnioCerradoEn" hidden></dd>
</dl>
<?php
/* Si el período no tiene registro de inicio ni de cierre, se avisa una sola vez abajo en
 lugar de dejar cuatro filas vacías: así queda claro que el dato no se registró y no
 que la pantalla falló.*/ ?>
<p class="text-support small mb-0 mt-2" id="verAnioSinRegistro" hidden>
  <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
  Este período no tiene registrado quién lo inició ni quién lo cerró: se activó fuera del
  sistema, antes de que existiera ese registro.
</p>
<?php
$modalBody = ob_get_clean();
$modalFooter = '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>';
include __DIR__ . '/../../../components/modal.php';
