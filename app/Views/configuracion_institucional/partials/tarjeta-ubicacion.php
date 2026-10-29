<?php
declare(strict_types=1);

/**
 * Partial: configuracion_institucional/partials/tarjeta-ubicacion.php
 * Bloque: Ubicación territorial, dependencia y nivel educativo (Pestaña Ubicación y membrete).
 *
 * @var array<string, string> $config
 * @var string $valEstado
 * @var string $valMunicipio
 * @var string $valParroquia
 * @var string $valNivel
 * @var string $valCodigoNivel
 */

$ubicacionConfigurada = !empty($valEstado) && !empty($valMunicipio) && !empty($valParroquia);
?>
<div class="config-accordion-item">
  <div class="config-accordion-header"
       data-bs-toggle="collapse"
       data-bs-target="#collapseUbicacionDatos"
       aria-expanded="false"
       aria-controls="collapseUbicacionDatos">
    <div>
      <div class="config-accordion-title">
        <i class="bi bi-geo-alt-fill text-primary" aria-hidden="true"></i>
        <span>Ubicación Territorial y Dependencia</span>
      </div>
      <p class="config-accordion-desc">
        Estado CDCE, municipio, parroquia, lugar de expedición y registro de dependencia del plantel.
      </p>
    </div>
    <div class="d-flex align-items-center gap-3">
      <span class="badge <?= $ubicacionConfigurada ? 'bg-success' : 'bg-warning text-dark' ?>">
        <?= $ubicacionConfigurada ? 'Configurado' : 'Pendiente' ?>
      </span>
      <i class="bi bi-chevron-down config-accordion-chevron" aria-hidden="true"></i>
    </div>
  </div>

  <div class="collapse config-accordion-body border-top" id="collapseUbicacionDatos">
    <div class="d-flex justify-content-end mb-3">
      <button type="button"
              class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1 shadow-sm px-3"
              data-bs-toggle="modal"
              data-bs-target="#modalEditarPlantel">
        <i class="bi bi-pencil-square" aria-hidden="true"></i>
        <span>Editar Ubicación</span>
      </button>
    </div>

    <div class="row g-3">
      <div class="col-12 col-md-6">
        <div class="config-info-tile">
          <div class="config-info-tile__label">Estado CDCE</div>
          <div class="config-info-tile__value" id="view-cdce_estado">
            <?= e($valEstado) ?>
          </div>
        </div>
      </div>

      <div class="col-12 col-md-6">
        <div class="config-info-tile">
          <div class="config-info-tile__label">Municipio del Plantel</div>
          <div class="config-info-tile__value" id="view-plantel_municipio">
            <?= e($valMunicipio) ?>
          </div>
        </div>
      </div>

      <div class="col-12 col-md-6">
        <div class="config-info-tile">
          <div class="config-info-tile__label">Parroquia del Plantel</div>
          <div class="config-info-tile__value" id="view-plantel_parroquia">
            <?= e($valParroquia) ?>
          </div>
        </div>
      </div>

      <div class="col-12 col-md-6">
        <div class="config-info-tile">
          <div class="config-info-tile__label">Lugar de Expedición</div>
          <div class="config-info-tile__value" id="view-lugar_expedicion">
            <?= e($config['lugar_expedicion'] ?? 'Carabobo') ?>
          </div>
        </div>
      </div>

      <div class="col-12 col-md-6">
        <div class="config-info-tile">
          <div class="config-info-tile__label">Código de Dependencia</div>
          <div class="config-info-tile__value font-monospace" id="view-plantel_codigo_dependencia">
            <?= e($config['plantel_codigo_dependencia'] ?? '006562012') ?>
          </div>
        </div>
      </div>

      <div class="col-12 col-md-6">
        <div class="config-info-tile">
          <div class="config-info-tile__label">Nivel Educativo (MPPE)</div>
          <div class="config-info-tile__value">
            <span id="view-nivel_educativo"><?= e($valNivel) ?></span>
            <span class="badge bg-primary text-white font-monospace ms-1" id="view-nivel_codigo"><?= e($valCodigoNivel) ?></span>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
