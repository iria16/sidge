<?php
declare(strict_types=1);

/**
 * Partial: configuracion_institucional/partials/tarjeta-membrete.php
 * Tarjeta 1: membrete oficial de la República y del ministerio.
 *
 * @var array<string, string> $config
 */
?>
      <!-- Tarjeta 1: Membrete Oficial de la República y Ministerio -->
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
          <h5 class="card-title fw-bold mb-0 text-primary d-flex align-items-center gap-2">
            <i class="bi bi-card-heading" aria-hidden="true"></i> Membrete Oficial de la República
          </h5>
          <button type="button" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1 shadow-sm px-3" data-bs-toggle="modal" data-bs-target="#modalEditarMembrete">
            <i class="bi bi-pencil-square" aria-hidden="true"></i>
            <span>Editar Membrete</span>
          </button>
        </div>
        <div class="card-body p-4">
          <div class="d-flex flex-column gap-3">
            <div class="p-3 bg-light rounded border">
              <div class="text-muted small fw-semibold text-uppercase">Línea 1 del Ministerio</div>
              <div class="fw-bold text-dark mt-1" id="view-ministerio_linea_1">
                <?php echo e($config['ministerio_linea_1'] ?? 'REPÚBLICA BOLIVARIANA DE VENEZUELA'); ?>
              </div>
            </div>
            <div class="p-3 bg-light rounded border">
              <div class="text-muted small fw-semibold text-uppercase">Línea 2 del Ministerio</div>
              <div class="fw-bold text-dark mt-1" id="view-ministerio_linea_2">
                <?php echo e($config['ministerio_linea_2'] ?? 'MINISTERIO DEL PODER POPULAR PARA LA EDUCACIÓN'); ?>
              </div>
            </div>
            <div class="p-3 bg-light rounded border">
              <div class="text-muted small fw-semibold text-uppercase">Epígrafe Anual (Certificado de Educación Primaria)</div>
              <div class="fw-bold text-dark mt-1 small" id="view-certificado_epigrafe">
                <?php echo e($config['certificado_epigrafe'] ?? 'BICENTENARIO DE LA UNIDAD AMERICANA: CONGRESO ANFICTIÓNICO DE PANAMÁ'); ?>
              </div>
            </div>
          </div>
        </div>
      </div>
