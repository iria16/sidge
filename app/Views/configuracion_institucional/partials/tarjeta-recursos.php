<?php
declare(strict_types=1);

/**
 * Partial: configuracion_institucional/partials/tarjeta-recursos.php
 * Bloque: Logos y franja tricolor (Pestaña Logos y recursos).
 *
 * @var string $ministerioLogoUrl
 * @var string $plantelLogoUrl
 * @var bool   $tieneCintillo
 * @var string $plantelCintilloUrl
 * @var bool   $esCintilloOficial
 */

$logosConfigurados = !empty($ministerioLogoUrl) && !empty($plantelLogoUrl);
?>
<div class="d-flex flex-column gap-3">
  <!-- Bloque 1: Logotipos Oficiales -->
  <div class="config-accordion-item">
    <div class="config-accordion-header"
         data-bs-toggle="collapse"
         data-bs-target="#collapseLogosDatos"
         aria-expanded="false"
         aria-controls="collapseLogosDatos">
      <div>
        <div class="config-accordion-title">
          <i class="bi bi-images text-primary" aria-hidden="true"></i>
          <span>Logotipos Oficiales (Ministerio y Plantel)</span>
        </div>
        <p class="config-accordion-desc">
          Emblemas gráficos que encabezan las constancias, certificados y reportes oficiales.
        </p>
      </div>
      <div class="d-flex align-items-center gap-3">
        <span class="badge <?= $logosConfigurados ? 'bg-success' : 'bg-warning text-dark' ?>">
          <?= $logosConfigurados ? 'Configurado' : 'Pendiente' ?>
        </span>
        <i class="bi bi-chevron-down config-accordion-chevron" aria-hidden="true"></i>
      </div>
    </div>

    <div class="collapse config-accordion-body border-top" id="collapseLogosDatos">
      <div class="row g-3">
        <!-- Logo Ministerial -->
        <div class="col-12 col-md-6">
          <div class="border rounded p-3 bg-white h-100 d-flex flex-column justify-content-between">
            <div>
              <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-semibold text-dark small">Logo del Ministerio</span>
                <span class="badge bg-light text-secondary border">Público</span>
              </div>
              <div class="image-preview-box transparency-pattern mb-2">
                <img id="preview-ministerio_logo"
                     src="<?= e($ministerioLogoUrl) ?>"
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
        <div class="col-12 col-md-6">
          <div class="border rounded p-3 bg-white h-100 d-flex flex-column justify-content-between">
            <div>
              <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-semibold text-dark small">Logo del Plantel</span>
                <span class="badge bg-light text-secondary border">Público</span>
              </div>
              <div class="image-preview-box transparency-pattern mb-2">
                <img id="preview-plantel_logo"
                     src="<?= e($plantelLogoUrl) ?>"
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
      </div>
    </div>
  </div>

  <!-- Bloque 2: Franja Tricolor (Cintillo) -->
  <div class="config-accordion-item">
    <div class="config-accordion-header"
         data-bs-toggle="collapse"
         data-bs-target="#collapseCintilloDatos"
         aria-expanded="false"
         aria-controls="collapseCintilloDatos">
      <div>
        <div class="config-accordion-title">
          <i class="bi bi-aspect-ratio text-primary" aria-hidden="true"></i>
          <span>Franja Tricolor (Cintillo de Pie de Página)</span>
        </div>
        <p class="config-accordion-desc">
          Cintillo institucional inferior estandarizado para constancias de estudio y prosecución.
        </p>
      </div>
      <div class="d-flex align-items-center gap-3">
        <span id="badge-plantel_cintillo" class="badge <?= $tieneCintillo ? 'bg-success' : 'bg-secondary' ?>">
          <?= $tieneCintillo ? 'Personalizada' : 'Sin Franja' ?>
        </span>
        <i class="bi bi-chevron-down config-accordion-chevron" aria-hidden="true"></i>
      </div>
    </div>

    <div class="collapse config-accordion-body border-top" id="collapseCintilloDatos">
      <div class="border rounded p-3 bg-white">
        <div class="cintillo-preview-box mb-3" id="box-plantel_cintillo">
          <?php if ($tieneCintillo): ?>
            <img id="preview-plantel_cintillo"
                 src="<?= e($plantelCintilloUrl) ?>"
                 alt="Franja Tricolor">
          <?php else: ?>
            <div class="text-center text-secondary small py-2" id="placeholder-plantel_cintillo">
              <i class="bi bi-slash-circle me-1 text-muted"></i> Sin franja inferior activa
            </div>
          <?php endif; ?>
        </div>
        <div class="d-flex flex-wrap justify-content-center gap-2">
          <button type="button"
                  class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1 btn-abrir-subida"
                  id="btn-subir-plantel_cintillo"
                  data-clave="plantel_cintillo"
                  data-titulo="Franja Tricolor"
                  data-privado="0">
            <i class="bi bi-upload"></i> <?= $tieneCintillo ? 'Cambiar Imagen' : 'Cargar Franja' ?>
          </button>
          <button type="button"
                  class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1 btn-quitar-imagen <?= $tieneCintillo ? '' : 'd-none' ?>"
                  id="btn-quitar-plantel_cintillo"
                  data-clave="plantel_cintillo"
                  data-titulo="Franja Tricolor"
                  data-privado="0">
            <i class="bi bi-trash"></i> Retirar
          </button>
        </div>
      </div>
    </div>
  </div>
</div>
