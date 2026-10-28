<?php
declare(strict_types=1);

/**
 * Partial: configuracion_institucional/partials/alerta-director.php
 * Aviso cuando faltan datos de la autoridad directiva.
 *
 * @var bool   $directorConfigurado
 * @var string $mensajeDirector
 */
?>
  <!-- Alerta de estado de la autoridad directiva -->
  <?php if (!$directorConfigurado): ?>
    <div class="alert alert-warning d-flex align-items-center gap-3 shadow-sm mb-4" role="alert" id="alertaDirectorIncompleto">
      <i class="bi bi-exclamation-triangle-fill fs-3 text-warning flex-shrink-0" aria-hidden="true"></i>
      <div>
        <h6 class="alert-heading fw-bold mb-1">Autoridad Directiva Incompleta</h6>
        <p class="mb-0 small text-dark">
          <?php echo e($mensajeDirector); ?>
        </p>
      </div>
    </div>
  <?php endif; ?>
