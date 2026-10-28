<?php
declare(strict_types=1);

/**
 * Partial: configuracion_institucional/partials/tarjeta-recursos.php
 * Tarjeta 4: logos oficiales y franja tricolor de las constancias.
 *
 * @var string $ministerioLogoUrl
 * @var string $plantelLogoUrl
 * @var bool   $tieneCintillo
 * @var string $plantelCintilloUrl
 * @var bool   $esCintilloOficial
 */
?>
      <!-- Tarjeta 4: Recursos Gráficos Oficiales -->
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom py-3">
          <h5 class="card-title fw-bold mb-0 text-primary d-flex align-items-center gap-2">
            <i class="bi bi-images" aria-hidden="true"></i> Recursos Gráficos y Logotipos
          </h5>
        </div>
        <div class="card-body p-4">
          <div class="row g-3">

            <!-- Logo Ministerial -->
            <div class="col-sm-6">
              <div class="border rounded p-3 bg-white h-100 d-flex flex-column justify-content-between">
                <div>
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-semibold text-dark small">Logo del Ministerio</span>
                    <span class="badge bg-light text-secondary border">Público</span>
                  </div>
                  <div class="image-preview-box transparency-pattern mb-2">
                    <img id="preview-ministerio_logo"
                         src="<?php echo e($ministerioLogoUrl); ?>"
                         alt="Logo del Ministerio">
                  </div>
                </div>
                <div class="d-flex gap-1 mt-2">
                  <button type="button"
                          class="btn btn-sm btn-outline-primary flex-fill d-inline-flex align-items-center justify-content-center gap-1 btn-abrir-subida"
                          data-clave="ministerio_logo"
                          data-titulo="Logo del Ministerio"
                          data-privado="0">
                    <i class="bi bi-upload"></i> Cambiar
                  </button>
                  <button type="button"
                          class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1 btn-quitar-imagen"
                          data-clave="ministerio_logo"
                          data-titulo="Logo del Ministerio"
                          data-privado="0"
                          title="Restablecer oficial">
                    <i class="bi bi-arrow-counterclockwise"></i>
                  </button>
                </div>
              </div>
            </div>

            <!-- Logo del Plantel -->
            <div class="col-sm-6">
              <div class="border rounded p-3 bg-white h-100 d-flex flex-column justify-content-between">
                <div>
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-semibold text-dark small">Logo del Plantel</span>
                    <span class="badge bg-light text-secondary border">Público</span>
                  </div>
                  <div class="image-preview-box transparency-pattern mb-2">
                    <img id="preview-plantel_logo"
                         src="<?php echo e($plantelLogoUrl); ?>"
                         alt="Logo del Plantel">
                  </div>
                </div>
                <div class="d-flex gap-1 mt-2">
                  <button type="button"
                          class="btn btn-sm btn-outline-primary flex-fill d-inline-flex align-items-center justify-content-center gap-1 btn-abrir-subida"
                          data-clave="plantel_logo"
                          data-titulo="Logo del Plantel"
                          data-privado="0">
                    <i class="bi bi-upload"></i> Cambiar
                  </button>
                  <button type="button"
                          class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1 btn-quitar-imagen"
                          data-clave="plantel_logo"
                          data-titulo="Logo del Plantel"
                          data-privado="0"
                          title="Restablecer oficial">
                    <i class="bi bi-arrow-counterclockwise"></i>
                  </button>
                </div>
              </div>
            </div>

            <!-- Franja Tricolor (Cintillo) -->
            <div class="col-12">
              <div class="border rounded p-3 bg-white">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <div>
                    <span class="fw-semibold text-dark small">Franja Tricolor (Cintillo de Pie de Página)</span>
                    <div class="text-muted small">Cintillo inferior en las constancias oficiales</div>
                  </div>
                  <span id="badge-plantel_cintillo" class="badge <?php echo $tieneCintillo ? ($esCintilloOficial ? 'bg-info text-dark border' : 'bg-success') : 'bg-secondary'; ?>">
                    <?php echo $tieneCintillo ? ($esCintilloOficial ? 'Oficial' : 'Personalizada') : 'Sin Franja'; ?>
                  </span>
                </div>
                <div class="cintillo-preview-box mb-2" id="box-plantel_cintillo">
                  <?php if ($tieneCintillo): ?>
                    <img id="preview-plantel_cintillo"
                         src="<?php echo e($plantelCintilloUrl); ?>"
                         alt="Franja Tricolor">
                  <?php else: ?>
                    <div class="text-center text-secondary small py-1" id="placeholder-plantel_cintillo">
                      <i class="bi bi-slash-circle me-1 text-muted"></i> Sin franja inferior activa
                    </div>
                  <?php endif; ?>
                </div>
                <div class="d-flex flex-wrap gap-2">
                  <button type="button"
                          class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1 btn-abrir-subida"
                          id="btn-subir-plantel_cintillo"
                          data-clave="plantel_cintillo"
                          data-titulo="Franja Tricolor"
                          data-privado="0">
                    <i class="bi bi-upload"></i> <?php echo $tieneCintillo ? 'Cambiar Imagen' : 'Cargar Franja'; ?>
                  </button>
                  <button type="button"
                          class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1 btn-quitar-imagen <?php echo $tieneCintillo ? '' : 'd-none'; ?>"
                          id="btn-quitar-plantel_cintillo"
                          data-clave="plantel_cintillo"
                          data-titulo="Franja Tricolor"
                          data-privado="0">
                    <i class="bi bi-trash"></i> Retirar
                  </button>
                  <button type="button"
                          class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1 btn-restablecer-oficial <?php echo (!$tieneCintillo || !$esCintilloOficial) ? '' : 'd-none'; ?>"
                          id="btn-oficial-plantel_cintillo"
                          data-clave="plantel_cintillo">
                    <i class="bi bi-arrow-counterclockwise"></i> Restablecer Oficial
                  </button>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>
