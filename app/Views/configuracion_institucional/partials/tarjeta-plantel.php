<?php
declare(strict_types=1);

/**
 * Partial: configuracion_institucional/partials/tarjeta-plantel.php
 * Ficha de Identificación Oficial del Plantel para la pestaña Plantel.
 *
 * @var array<string, string> $config
 * @var string $valNivel
 * @var string $valCodigoNivel
 */

$plantelConfigurado = !empty($config['plantel_nombre']) && !empty($config['plantel_codigo_dea']);
?>
<div class="config-accordion-item">
  <div class="config-accordion-header"
       data-bs-toggle="collapse"
       data-bs-target="#collapsePlantelDatos"
       aria-expanded="false"
       aria-controls="collapsePlantelDatos">
    <div>
      <div class="config-accordion-title">
        <i class="bi bi-building text-primary" aria-hidden="true"></i>
        <span>Identificación y Registro Oficial del Plantel</span>
      </div>
      <p class="config-accordion-desc">
        Nombre legal, siglas oficiales, Código DEA y registro administrativo ante el MPPE.
      </p>
    </div>
    <div class="d-flex align-items-center gap-3">
      <span class="badge <?= $plantelConfigurado ? 'bg-success' : 'bg-warning text-dark' ?>">
        <?= $plantelConfigurado ? 'Configurado' : 'Pendiente' ?>
      </span>
      <i class="bi bi-chevron-down config-accordion-chevron" aria-hidden="true"></i>
    </div>
  </div>

  <div class="collapse config-accordion-body border-top" id="collapsePlantelDatos">
    <div class="d-flex justify-content-end mb-3">
      <button type="button"
              class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1 shadow-sm px-3"
              data-bs-toggle="modal"
              data-bs-target="#modalEditarPlantel">
        <i class="bi bi-pencil-square" aria-hidden="true"></i>
        <span>Editar Datos Oficiales</span>
      </button>
    </div>

    <div class="row g-3">
      <div class="col-12 col-md-8">
        <div class="config-info-tile">
          <div class="config-info-tile__label">Nombre Oficial del Plantel</div>
          <div class="config-info-tile__value fs-6" id="view-plantel_nombre">
            <?= e($config['plantel_nombre'] ?? 'Plantel Sin Configurar') ?>
          </div>
        </div>
      </div>

      <div class="col-12 col-md-4">
        <div class="config-info-tile">
          <div class="config-info-tile__label">Siglas / Nombre Corto</div>
          <div class="config-info-tile__value" id="view-plantel_nombre_corto">
            <?= e($config['plantel_nombre_corto'] ?? 'E.B.') ?>
          </div>
        </div>
      </div>

      <div class="col-12 col-md-4">
        <div class="config-info-tile">
          <div class="config-info-tile__label">Código DEA</div>
          <div class="config-info-tile__value font-monospace text-primary" id="view-plantel_codigo_dea">
            <?= e(!empty($config['plantel_codigo_dea']) ? $config['plantel_codigo_dea'] : 'No asignado') ?>
          </div>
        </div>
      </div>

      <div class="col-12 col-md-4">
        <div class="config-info-tile">
          <div class="config-info-tile__label">Código de Dependencia</div>
          <div class="config-info-tile__value font-monospace">
            <?= e($config['plantel_codigo_dependencia'] ?? '006562012') ?>
          </div>
        </div>
      </div>

      <div class="col-12 col-md-4">
        <div class="config-info-tile">
          <div class="config-info-tile__label">Nivel Educativo (MPPE)</div>
          <div class="config-info-tile__value">
            <span><?= e($valNivel) ?></span>
            <span class="badge bg-light text-primary border font-monospace ms-1">(<?= e($valCodigoNivel) ?>)</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>