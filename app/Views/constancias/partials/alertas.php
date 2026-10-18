<?php

declare(strict_types=1);

/**
 * Partial: constancias/partials/alertas.php
 * Los tres avisos de la cabecera (error de flash, éxito de flash y autoridad
 * directiva incompleta).
 *
 * Este bloque estaba copiado byte a byte en las dos vistas de constancias: 31
 * líneas idénticas que se editaban por separado, con el riesgo de que una se
 * corrigiera y la otra no. Es el único trozo realmente compartido entre ellas;
 * el resto (cabecera de tabla y filas) difiere en columnas y en el formulario
 * de emisión, así que no se unifica.
 *
 * Variables esperadas del controlador:
 * @var array{error: ?string, success: ?string} $flash
 * @var bool $directorConfigurado
 */
?>
  <!-- Notificaciones Flash -->
  <?php if (!empty($flash['error'])): ?>
    <div class="alert alert-danger d-flex align-items-center gap-3 shadow-sm mb-4" role="alert">
      <i class="bi bi-exclamation-octagon-fill fs-4 flex-shrink-0" aria-hidden="true"></i>
      <div>
        <h6 class="alert-heading fw-bold mb-1">No se pudo emitir la constancia</h6>
        <p class="mb-0 small"><?= htmlspecialchars($flash['error']) ?></p>
      </div>
    </div>
  <?php endif; ?>

  <?php if (!empty($flash['success'])): ?>
    <div class="alert alert-success d-flex align-items-center gap-3 shadow-sm mb-4" role="alert">
      <i class="bi bi-check-circle-fill fs-4 flex-shrink-0" aria-hidden="true"></i>
      <div><?= htmlspecialchars($flash['success']) ?></div>
    </div>
  <?php endif; ?>

  <!-- Alerta si falta configurar directivo -->
  <?php if (!$directorConfigurado): ?>
    <div class="alert alert-warning d-flex align-items-center gap-3 shadow-sm mb-4" role="alert">
      <i class="bi bi-exclamation-triangle-fill fs-3 text-warning flex-shrink-0" aria-hidden="true"></i>
      <div>
        <h6 class="alert-heading fw-bold mb-1">Autoridad Directiva Incompleta</h6>
        <p class="mb-0 small text-dark">
          Falta el nombre, cédula o cargo del director(a) en <a href="<?= BASE_URL ?>configuracionInstitucional/index" class="fw-bold text-dark text-decoration-underline">Datos Institucionales</a>.
          La emisión de documentos oficiales requiere obligatoriamente estos datos antes de su descarga.
        </p>
      </div>
    </div>
  <?php endif; ?>