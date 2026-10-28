<?php
declare(strict_types=1);

/**
 * Partial: configuracion_institucional/partials/banner-identidad.php
 * Banner con el logo, nombre y códigos oficiales del plantel.
 *
 * @var array<string, string> $config
 * @var string $plantelLogoUrl
 * @var string $valEstado
 * @var string $valMunicipio
 * @var string $valNivel
 * @var string $valCodigoNivel
 */
?>
  <!-- Cabecera de Identidad Institucional (Banner de Perfil) -->
  <div class="institutional-banner p-4 mb-4">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-4">
      
      <div class="d-flex align-items-center gap-3">
        <!-- Logo del Plantel -->
        <div class="school-logo-frame">
          <img id="header-plantel_logo"
               src="<?php echo e($plantelLogoUrl); ?>"
               alt="Logo del Plantel">
        </div>

        <div>
          <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
            <h3 class="fw-bold text-dark mb-0 fs-4" id="header-plantel_nombre">
              <?php echo e($config['plantel_nombre'] ?? 'Plantel Sin Configurar'); ?>
            </h3>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 fs-7" id="header-plantel_nombre_corto">
              <?php echo e($config['plantel_nombre_corto'] ?? 'E.B.'); ?>
            </span>
          </div>

          <div class="d-flex align-items-center gap-2 flex-wrap text-muted small mt-2">
            <span class="badge bg-white text-dark border font-monospace px-2 py-1">
              <i class="bi bi-upc-scan text-primary me-1"></i>DEA: <strong id="header-plantel_codigo_dea"><?php echo e(!empty($config['plantel_codigo_dea']) ? $config['plantel_codigo_dea'] : 'Sin asignar'); ?></strong>
            </span>
            <span class="badge bg-white text-secondary border px-2 py-1">
              <i class="bi bi-geo-alt-fill text-danger me-1"></i><span id="header-cdce_estado"><?php echo e($valEstado); ?></span>, <span id="header-plantel_municipio"><?php echo e($valMunicipio); ?></span>
            </span>
            <span class="badge bg-white text-dark border font-monospace px-2 py-1">
              <i class="bi bi-hash text-secondary me-1"></i>Dep: <span id="header-plantel_codigo_dependencia"><?php echo e($config['plantel_codigo_dependencia'] ?? '006562012'); ?></span>
            </span>
            <span class="badge bg-white text-primary border px-2 py-1">
              <i class="bi bi-mortarboard-fill me-1"></i><span id="header-nivel_educativo"><?php echo e($valNivel); ?></span> (<span id="header-nivel_codigo"><?php echo e($valCodigoNivel); ?></span>)
            </span>
          </div>
        </div>
      </div>

      <div class="d-flex flex-wrap align-items-center gap-2 flex-shrink-0">
        <button type="button" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1 btn-abrir-subida" data-clave="plantel_logo" data-titulo="Logo del Plantel" data-privado="0">
          <i class="bi bi-camera"></i> Cambiar Logo
        </button>
        <button type="button" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-2 px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalEditarPlantel">
          <i class="bi bi-pencil-square"></i>
          <span>Editar Datos Oficiales</span>
        </button>
      </div>

    </div>
  </div>
