<?php
declare(strict_types=1);

/**
 * Partial: ratificacion/modals/ver-ratificacion-modal.php
 * Modal de detalle. El cuerpo se puebla por AJAX desde ratificacion.js contra
 * ratificacion/ver, que responde ver.php sin layout.
 */
$modalBody     = '<div id="ratificacionVerBody"></div>';
$modalFooter   = '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>';
$modalId       = 'modalVerRatificacion';
$modalTitle    = 'Detalle de Ratificación';
$modalSubtitle = 'De dónde venía el estudiante y a qué grado quedó ratificado.';
$modalSize     = 'lg';
include __DIR__ . '/../../../components/modal.php';
