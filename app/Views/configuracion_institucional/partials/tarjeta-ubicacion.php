<?php
declare(strict_types=1);

/**
 * Partial: configuracion_institucional/partials/tarjeta-ubicacion.php
 * Tarjeta 2: ubicación territorial, dependencia y nivel educativo.
 *
 * @var array<string, string> $config
 * @var string $valEstado
 * @var string $valMunicipio
 * @var string $valParroquia
 * @var string $valNivel
 * @var string $valCodigoNivel
 */
?>
      <!-- Tarjeta 2: Ubicación Territorial y Dependencia -->
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
          <h5 class="card-title fw-bold mb-0 text-primary d-flex align-items-center gap-2">
            <i class="bi bi-geo-alt-fill" aria-hidden="true"></i> Ubicación Territorial y Dependencia
          </h5>
          <button type="button" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1 shadow-sm px-3" data-bs-toggle="modal" data-bs-target="#modalEditarPlantel">
            <i class="bi bi-pencil-square" aria-hidden="true"></i>
            <span>Editar</span>
          </button>
        </div>
        <div class="card-body p-4">
          <div class="row g-3">
            <div class="col-sm-6">
              <div class="p-3 bg-light rounded border h-100">
                <div class="text-muted small fw-semibold text-uppercase">Estado CDCE</div>
                <div class="fw-bold text-dark mt-1" id="view-cdce_estado">
                  <?php echo e($valEstado); ?>
                </div>
              </div>
            </div>

            <div class="col-sm-6">
              <div class="p-3 bg-light rounded border h-100">
                <div class="text-muted small fw-semibold text-uppercase">Municipio del Plantel</div>
                <div class="fw-bold text-dark mt-1" id="view-plantel_municipio">
                  <?php echo e($valMunicipio); ?>
                </div>
              </div>
            </div>

            <div class="col-sm-6">
              <div class="p-3 bg-light rounded border h-100">
                <div class="text-muted small fw-semibold text-uppercase">Parroquia del Plantel</div>
                <div class="fw-bold text-dark mt-1" id="view-plantel_parroquia">
                  <?php echo e($valParroquia); ?>
                </div>
              </div>
            </div>

            <div class="col-sm-6">
              <div class="p-3 bg-light rounded border h-100">
                <div class="text-muted small fw-semibold text-uppercase">Lugar de Expedición</div>
                <div class="fw-bold text-dark mt-1" id="view-lugar_expedicion">
                  <?php echo e($config['lugar_expedicion'] ?? 'Carabobo'); ?>
                </div>
              </div>
            </div>

            <div class="col-sm-6">
              <div class="p-3 bg-light rounded border h-100">
                <div class="text-muted small fw-semibold text-uppercase">Código Dependencia</div>
                <div class="mt-1">
                  <span class="badge bg-white text-dark border font-monospace px-2 py-1 fs-6" id="view-plantel_codigo_dependencia">
                    <?php echo e($config['plantel_codigo_dependencia'] ?? '006562012'); ?>
                  </span>
                </div>
              </div>
            </div>

            <div class="col-sm-6">
              <div class="p-3 bg-light rounded border h-100">
                <div class="text-muted small fw-semibold text-uppercase">Nivel Educativo y MPPE</div>
                <div class="d-flex align-items-center gap-2 mt-1">
                  <span class="fw-bold text-dark" id="view-nivel_educativo"><?php echo e($valNivel); ?></span>
                  <span class="badge bg-primary text-white font-monospace px-2 py-1" id="view-nivel_codigo">
                    <?php echo e($valCodigoNivel); ?>
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
