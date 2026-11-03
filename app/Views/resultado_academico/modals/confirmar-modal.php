<?php
/**
 * Modal: confirmación oficial de promoción (solo directivo).
 *
 * @var string $gradoNombre
 * @var string $seccionNombre
 * @var string $anioNombre
 * @var bool   $esDirectivo
 */
if (empty($esDirectivo)) {
    return;
}

ob_start();
?>
<p class="text-secondary small mb-3">
  Está a punto de <strong>confirmar oficialmente</strong> los resultados de fin de año. Los estudiantes con literal confirmado quedarán sellados y listos para prosecución escolar.
</p>

<div class="p-3 rounded border mb-3 small resultado-resumen-box">
  <div class="d-flex justify-content-between mb-1">
    <span class="text-secondary">Estudiantes en nómina:</span>
    <strong id="modalResumenTotal" class="text-primary">0</strong>
  </div>
  <div class="d-flex justify-content-between mb-1 text-success">
    <span>A confirmar como Promovidos:</span>
    <strong id="modalResumenPromovidos">0</strong>
  </div>
  <div class="d-flex justify-content-between mb-1 text-danger">
    <span>A confirmar como No Promovidos:</span>
    <strong id="modalResumenNoPromovidos">0</strong>
  </div>
  <div class="d-flex justify-content-between mb-1 text-warning">
    <span>Sin literal (permanecerán pendientes):</span>
    <strong id="modalResumenSinLiteral">0</strong>
  </div>
  <div class="d-flex justify-content-between text-muted border-top pt-1 mt-1">
    <span>Ya confirmados previamente (protegidos):</span>
    <strong id="modalResumenYaConfirmados">0</strong>
  </div>
</div>

<div class="info-alert">
  <div class="info-alert__icon"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i></div>
  <div>
    <div class="info-alert__title">Aviso de Cierre</div>
    <div class="info-alert__text">
      Una vez confirmados, los resultados quedan blindados y no podrán ser modificados.
    </div>
  </div>
</div>
<?php
$modalBody = ob_get_clean();

ob_start();
?>
<button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
<button type="button" class="btn btn-success btn-sm" id="btnEjecutarConfirmacion">
  <i class="bi bi-check2-circle me-1"></i> Confirmar y Oficializar
</button>
<?php
$modalFooter = ob_get_clean();

$modalId       = 'modalConfirmarResultados';
$modalTitle    = 'Confirmación Oficial de Promoción';
$modalSubtitle = "Sección {$gradoNombre} \"{$seccionNombre}\" · Período {$anioNombre}";
$modalStatic   = true;
include __DIR__ . '/../../components/modal.php';
