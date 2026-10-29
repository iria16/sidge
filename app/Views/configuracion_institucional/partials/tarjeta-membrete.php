<?php
declare(strict_types=1);

/**
 * Partial: configuracion_institucional/partials/tarjeta-membrete.php
 * Bloque: Membrete Oficial de la República y Ministerio (Pestaña Ubicación y membrete).
 *
 * @var array<string, string> $config
 */

$membreteConfigurado = !empty($config['ministerio_linea_1']) && !empty($config['ministerio_linea_2']);
?>
<div class="config-accordion-item">
  <div class="config-accordion-header"
       data-bs-toggle="collapse"
       data-bs-target="#collapseMembreteDatos"
       aria-expanded="false"
       aria-controls="collapseMembreteDatos">
    <div>
      <div class="config-accordion-title">
        <i class="bi bi-card-heading text-primary" aria-hidden="true"></i>
        <span>Membrete Oficial de la República y Ministerio</span>
      </div>
      <p class="config-accordion-desc">
        Cabecera oficial y epígrafe anual de la República para documentos y certificados oficiales.
      </p>
    </div>
    <div class="d-flex align-items-center gap-3">
      <span class="badge <?= $membreteConfigurado ? 'bg-success' : 'bg-warning text-dark' ?>">
        <?= $membreteConfigurado ? 'Configurado' : 'Pendiente' ?>
      </span>
      <i class="bi bi-chevron-down config-accordion-chevron" aria-hidden="true"></i>
    </div>
  </div>

  <div class="collapse config-accordion-body border-top" id="collapseMembreteDatos">
    <div class="d-flex justify-content-end mb-3">
      <button type="button"
              class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1 shadow-sm px-3"
              data-bs-toggle="modal"
              data-bs-target="#modalEditarMembrete">
        <i class="bi bi-pencil-square" aria-hidden="true"></i>
        <span>Editar Membrete</span>
      </button>
    </div>

    <div class="row g-3">
      <div class="col-12">
        <div class="config-info-tile">
          <div class="config-info-tile__label">Línea 1 del Ministerio</div>
          <div class="config-info-tile__value" id="view-ministerio_linea_1">
            <?= e($config['ministerio_linea_1'] ?? 'REPÚBLICA BOLIVARIANA DE VENEZUELA') ?>
          </div>
        </div>
      </div>

      <div class="col-12">
        <div class="config-info-tile">
          <div class="config-info-tile__label">Línea 2 del Ministerio</div>
          <div class="config-info-tile__value" id="view-ministerio_linea_2">
            <?= e($config['ministerio_linea_2'] ?? 'MINISTERIO DEL PODER POPULAR PARA LA EDUCACIÓN') ?>
          </div>
        </div>
      </div>

      <div class="col-12">
        <div class="config-info-tile">
          <div class="config-info-tile__label">Epígrafe Anual (Certificado de Educación Primaria)</div>
          <div class="config-info-tile__value small" id="view-certificado_epigrafe">
            <?= e($config['certificado_epigrafe'] ?? 'BICENTENARIO DE LA UNIDAD AMERICANA: CONGRESO ANFICTIÓNICO DE PANAMÁ') ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
