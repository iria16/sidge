<?php
declare(strict_types=1);

/**
 * Modal: inscripcion/modals/ver-inscripcion-modal.php
 * Detalle de la inscripción. El cuerpo se puebla por AJAX desde enrollments.js
 * sobre #modalVerInscripcionBody.
 */
$modalBody     = '<div id="modalVerInscripcionBody"></div>';
$modalFooter   = '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>';
$modalId       = 'modalVerInscripcion';
$modalTitle    = 'Detalle de Inscripción';
$modalSubtitle = 'Resumen de la inscripción registrada.';
$modalSize     = 'lg';
include __DIR__ . '/../../../components/modal.php';
