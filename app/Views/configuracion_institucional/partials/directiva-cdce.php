<?php
declare(strict_types=1);

/**
 * Partial: configuracion_institucional/partials/directiva-cdce.php
 * Bloque: Autoridad CDCE Estadal (Opcional - Pestaña Autoridades).
 *
 * @var array<string, string> $config
 * @var bool   $tieneFirmaCdce
 * @var string $firmaCdceUrl
 * @var bool   $tieneSelloCdce
 * @var string $selloCdceUrl
 */

$cdceConfigurado = !empty($config['cdce_director_nombre']);
?>
<div class="config-accordion-item">
  <div class="config-accordion-header"
       data-bs-toggle="collapse"
       data-bs-target="#collapseCdceDatos"
       aria-expanded="false"
       aria-controls="collapseCdceDatos">
    <div>
      <div class="config-accordion-title">
        <i class="bi bi-shield-check text-secondary" aria-hidden="true"></i>
        <span>Director(a) CDCE Estadal</span>
      </div>
      <p class="config-accordion-desc">
        Autoridad estadal para trámites de Prosecución y Certificados de Educación Primaria.
      </p>
    </div>
    <div class="d-flex align-items-center gap-3">
      <span class="badge bg-light text-secondary border">Opcional</span>
      <span class="badge <?= $cdceConfigurado ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-light text-muted border' ?>">
        <?= $cdceConfigurado ? 'Registrado' : 'En blanco' ?>
      </span>
      <i class="bi bi-chevron-down config-accordion-chevron" aria-hidden="true"></i>
    </div>
  </div>

  <div class="collapse config-accordion-body border-top" id="collapseCdceDatos">
    <div class="d-flex justify-content-end mb-3">
      <button type="button"
              class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1 shadow-sm px-3"
              data-bs-toggle="modal"
              data-bs-target="#modalEditarDirectiva">
        <i class="bi bi-pencil-square" aria-hidden="true"></i>
        <span>Editar Autoridad CDCE</span>
      </button>
    </div>

    <!-- Datos CDCE -->
    <div class="row g-3 mb-4">
      <div class="col-12 col-md-5">
        <div class="config-info-tile">
          <div class="config-info-tile__label">Director(a) CDCE Estadal</div>
          <div class="config-info-tile__value fs-6" id="view-cdce_director_nombre">
            <?= e(!empty($config['cdce_director_nombre']) ? $config['cdce_director_nombre'] : 'En blanco (para llenado manual)') ?>
          </div>
        </div>
      </div>

      <div class="col-12 col-md-3">
        <div class="config-info-tile">
          <div class="config-info-tile__label">Cédula CDCE</div>
          <div class="config-info-tile__value font-monospace text-primary" id="view-cdce_director_cedula">
            <?= e(!empty($config['cdce_director_cedula']) ? $config['cdce_director_cedula'] : 'En blanco') ?>
          </div>
        </div>
      </div>

      <div class="col-12 col-md-4">
        <div class="config-info-tile">
          <div class="config-info-tile__label">Cargo</div>
          <div class="config-info-tile__value">DIRECTOR(A)</div>
        </div>
      </div>
    </div>

    <!-- Firma y Sello CDCE (Compactos cuando no hay imagen) -->
    <div class="row g-3">
      <!-- Firma CDCE -->
      <div class="col-12 col-md-6" id="wrapper-cdce_director_firma">
        <?php if (!empty($tieneFirmaCdce)): ?>
          <div class="border rounded p-3 bg-white h-100 d-flex flex-column justify-content-between">
            <div>
              <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-semibold text-dark small">Firma Director(a) CDCE</span>
                <span id="badge-cdce_director_firma" class="badge bg-success">Configurada</span>
              </div>
              <div class="private-preview-box transparency-pattern mb-2" id="box-cdce_director_firma">
                <img id="preview-cdce_director_firma" src="<?= e($firmaCdceUrl) ?>" alt="Firma del Director CDCE">
              </div>
            </div>
            <div class="d-flex gap-1 mt-2">
              <button type="button"
                      class="btn btn-sm btn-outline-primary flex-fill d-inline-flex align-items-center justify-content-center gap-1 btn-abrir-subida"
                      data-clave="cdce_director_firma"
                      data-titulo="Firma del Director CDCE"
                      data-privado="1">
                <i class="bi bi-upload"></i> Cambiar
              </button>
              <button type="button"
                      class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1 btn-quitar-imagen"
                      id="btn-quitar-cdce_director_firma"
                      data-clave="cdce_director_firma"
                      data-titulo="Firma del Director CDCE"
                      data-privado="1">
                <i class="bi bi-trash"></i>
              </button>
            </div>
          </div>
        <?php else: ?>
          <div class="compact-signature-slot">
            <div class="compact-signature-slot__info">
              <i class="bi bi-pen compact-signature-slot__icon" aria-hidden="true"></i>
              <div>
                <div class="compact-signature-slot__title">Firma Director(a) CDCE</div>
                <span id="badge-cdce_director_firma" class="badge bg-secondary">Sin Firma</span>
              </div>
            </div>
            <div id="box-cdce_director_firma" class="d-none"></div>
            <button type="button"
                    class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1 btn-abrir-subida"
                    data-clave="cdce_director_firma"
                    data-titulo="Firma del Director CDCE"
                    data-privado="1">
              <i class="bi bi-upload"></i> Cargar
            </button>
          </div>
        <?php endif; ?>
      </div>

      <!-- Sello CDCE -->
      <div class="col-12 col-md-6" id="wrapper-cdce_institucion_sello">
        <?php if (!empty($tieneSelloCdce)): ?>
          <div class="border rounded p-3 bg-white h-100 d-flex flex-column justify-content-between">
            <div>
              <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-semibold text-dark small">Sello CDCE Estadal</span>
                <span id="badge-cdce_institucion_sello" class="badge bg-success">Configurado</span>
              </div>
              <div class="private-preview-box transparency-pattern mb-2" id="box-cdce_institucion_sello">
                <img id="preview-cdce_institucion_sello" src="<?= e($selloCdceUrl) ?>" alt="Sello CDCE Estadal">
              </div>
            </div>
            <div class="d-flex gap-1 mt-2">
              <button type="button"
                      class="btn btn-sm btn-outline-primary flex-fill d-inline-flex align-items-center justify-content-center gap-1 btn-abrir-subida"
                      data-clave="cdce_institucion_sello"
                      data-titulo="Sello CDCE Estadal"
                      data-privado="1">
                <i class="bi bi-upload"></i> Cambiar
              </button>
              <button type="button"
                      class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1 btn-quitar-imagen"
                      id="btn-quitar-cdce_institucion_sello"
                      data-clave="cdce_institucion_sello"
                      data-titulo="Sello CDCE Estadal"
                      data-privado="1">
                <i class="bi bi-trash"></i>
              </button>
            </div>
          </div>
        <?php else: ?>
          <div class="compact-signature-slot">
            <div class="compact-signature-slot__info">
              <i class="bi bi-award compact-signature-slot__icon" aria-hidden="true"></i>
              <div>
                <div class="compact-signature-slot__title">Sello CDCE Estadal</div>
                <span id="badge-cdce_institucion_sello" class="badge bg-secondary">Sin Sello</span>
              </div>
            </div>
            <div id="box-cdce_institucion_sello" class="d-none"></div>
            <button type="button"
                    class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1 btn-abrir-subida"
                    data-clave="cdce_institucion_sello"
                    data-titulo="Sello CDCE Estadal"
                    data-privado="1">
              <i class="bi bi-upload"></i> Cargar
            </button>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
