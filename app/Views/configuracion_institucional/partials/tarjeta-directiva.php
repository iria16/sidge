<?php
declare(strict_types=1);

/**
 * Partial: configuracion_institucional/partials/tarjeta-directiva.php
 * Bloque: Autoridad Directiva y Firmante del Plantel (Pestaña Autoridades).
 *
 * @var array<string, string> $config
 * @var bool   $directorConfigurado
 * @var string $nombreCompletoDir
 * @var bool   $tieneFirma
 * @var string $firmaUrl
 * @var bool   $tieneSello
 * @var string $selloUrl
 */
?>
<div class="config-accordion-item">
  <div class="config-accordion-header"
       data-bs-toggle="collapse"
       data-bs-target="#collapseDirectorDatos"
       aria-expanded="<?= !$directorConfigurado ? 'true' : 'false' ?>"
       aria-controls="collapseDirectorDatos">
    <div>
      <div class="config-accordion-title">
        <i class="bi bi-person-vcard-fill text-primary" aria-hidden="true"></i>
        <span>Director(a) / Máxima Autoridad del Plantel</span>
      </div>
      <p class="config-accordion-desc">
        Identidad oficial, cédula, cargo legal y digitalización de firma y sello del plantel.
      </p>
    </div>
    <div class="d-flex align-items-center gap-3">
      <span id="badge-estado-director" class="badge <?= $directorConfigurado ? 'bg-success' : 'bg-warning text-dark' ?>">
        <?= $directorConfigurado ? 'Configurado' : 'Pendiente' ?>
      </span>
      <i class="bi bi-chevron-down config-accordion-chevron" aria-hidden="true"></i>
    </div>
  </div>

  <div class="collapse config-accordion-body border-top <?= !$directorConfigurado ? 'show' : '' ?>" id="collapseDirectorDatos">
    <div class="d-flex justify-content-end mb-3">
      <button type="button"
              class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1 shadow-sm px-3"
              data-bs-toggle="modal"
              data-bs-target="#modalEditarDirectiva">
        <i class="bi bi-pencil-square" aria-hidden="true"></i>
        <span>Editar Autoridad</span>
      </button>
    </div>

    <!-- Datos de la Autoridad -->
    <div class="row g-3 mb-4">
      <div class="col-12 col-md-5">
        <div class="config-info-tile">
          <div class="config-info-tile__label">Nombre del Director(a)</div>
          <div class="config-info-tile__value fs-6" id="view-director_nombre">
            <?= e($nombreCompletoDir !== '' ? $nombreCompletoDir : 'No configurado') ?>
          </div>
        </div>
      </div>

      <div class="col-12 col-md-3">
        <div class="config-info-tile">
          <div class="config-info-tile__label">Cédula de Identidad</div>
          <div class="config-info-tile__value font-monospace text-primary" id="view-director_cedula">
            <?= e(!empty($config['director_cedula']) ? $config['director_cedula'] : 'No registrada') ?>
          </div>
        </div>
      </div>

      <div class="col-12 col-md-4">
        <div class="config-info-tile">
          <div class="config-info-tile__label">Cargo Oficial</div>
          <div class="config-info-tile__value" id="view-director_cargo">
            <?= e(!empty($config['director_cargo']) ? $config['director_cargo'] : 'Director (E)') ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Firma y Sello del Plantel (Compacto cuando no hay imagen) -->
    <div class="row g-3">
      <!-- Firma -->
      <div class="col-12 col-md-6" id="wrapper-director_firma">
        <?php if ($tieneFirma): ?>
          <div class="border rounded p-3 bg-white h-100 d-flex flex-column justify-content-between">
            <div>
              <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-semibold text-dark small">Firma Digitalizada</span>
                <span id="badge-director_firma" class="badge bg-success">Configurada</span>
              </div>
              <div class="private-preview-box transparency-pattern mb-2" id="box-director_firma">
                <img id="preview-director_firma" src="<?= e($firmaUrl) ?>" alt="Firma del Director">
              </div>
            </div>
            <div class="d-flex gap-1 mt-2">
              <button type="button"
                      class="btn btn-sm btn-outline-primary flex-fill d-inline-flex align-items-center justify-content-center gap-1 btn-abrir-subida"
                      data-clave="director_firma"
                      data-titulo="Firma del Director"
                      data-privado="1">
                <i class="bi bi-upload"></i> Cambiar
              </button>
              <button type="button"
                      class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1 btn-quitar-imagen"
                      id="btn-quitar-director_firma"
                      data-clave="director_firma"
                      data-titulo="Firma del Director"
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
                <div class="compact-signature-slot__title">Firma Digitalizada</div>
                <span id="badge-director_firma" class="badge bg-secondary">Sin Firma</span>
              </div>
            </div>
            <div id="box-director_firma" class="d-none"></div>
            <button type="button"
                    class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1 btn-abrir-subida"
                    data-clave="director_firma"
                    data-titulo="Firma del Director"
                    data-privado="1">
              <i class="bi bi-upload"></i> Cargar
            </button>
          </div>
        <?php endif; ?>
      </div>

      <!-- Sello -->
      <div class="col-12 col-md-6" id="wrapper-institucion_sello">
        <?php if ($tieneSello): ?>
          <div class="border rounded p-3 bg-white h-100 d-flex flex-column justify-content-between">
            <div>
              <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-semibold text-dark small">Sello Institucional</span>
                <span id="badge-institucion_sello" class="badge bg-success">Configurado</span>
              </div>
              <div class="private-preview-box transparency-pattern mb-2" id="box-institucion_sello">
                <img id="preview-institucion_sello" src="<?= e($selloUrl) ?>" alt="Sello Institucional">
              </div>
            </div>
            <div class="d-flex gap-1 mt-2">
              <button type="button"
                      class="btn btn-sm btn-outline-primary flex-fill d-inline-flex align-items-center justify-content-center gap-1 btn-abrir-subida"
                      data-clave="institucion_sello"
                      data-titulo="Sello Institucional"
                      data-privado="1">
                <i class="bi bi-upload"></i> Cambiar
              </button>
              <button type="button"
                      class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1 btn-quitar-imagen"
                      id="btn-quitar-institucion_sello"
                      data-clave="institucion_sello"
                      data-titulo="Sello Institucional"
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
                <div class="compact-signature-slot__title">Sello Institucional</div>
                <span id="badge-institucion_sello" class="badge bg-secondary">Sin Sello</span>
              </div>
            </div>
            <div id="box-institucion_sello" class="d-none"></div>
            <button type="button"
                    class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1 btn-abrir-subida"
                    data-clave="institucion_sello"
                    data-titulo="Sello Institucional"
                    data-privado="1">
              <i class="bi bi-upload"></i> Cargar
            </button>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>