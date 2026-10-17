<?php
/**
 * Componente: featured-birthday-card.php
 * Tarjeta destacada de "Cumpleañera/o de hoy" para el dashboard docente.
 *
 * - array|null $birthdayData Datos del cumpleañero o null si hoy nadie cumple:
 *     - string $nombre
 *     - string $iniciales
 *     - int    $edad
 *     - string $texto
 *     - string $fecha_formateada
 */
$birthdayData = $birthdayData ?? null;
?>
<div class="featured-birthday-card">
  <div class="featured-birthday-card__watermark" aria-hidden="true">
    <i class="bi bi-cake2"></i>
  </div>
  <?php if (!empty($birthdayData)): ?>
    <div class="featured-birthday-card__header">
      <span class="featured-birthday-card__tag">
        <i class="bi bi-stars me-1" aria-hidden="true"></i>¡CUMPLEAÑERA/O DE HOY!
      </span>
      <span class="featured-birthday-card__date">
        <?php echo htmlspecialchars($birthdayData['fecha_formateada'] ?? ''); ?>
      </span>
    </div>
    <div class="featured-birthday-card__body">
      <span class="featured-birthday-card__avatar">
        <?php echo htmlspecialchars($birthdayData['iniciales'] ?? 'SG'); ?>
      </span>
      <div class="featured-birthday-card__info">
        <h3 class="featured-birthday-card__name mb-1">
          <?php echo htmlspecialchars($birthdayData['nombre'] ?? ''); ?>
        </h3>
        <p class="featured-birthday-card__subtitle mb-0">
          <?php echo htmlspecialchars($birthdayData['texto'] ?? '¡Feliz cumpleaños!'); ?>
        </p>
      </div>
    </div>
  <?php else: ?>
    <div class="featured-birthday-card__header">
      <span class="featured-birthday-card__tag">
        <i class="bi bi-calendar-heart me-1" aria-hidden="true"></i>CUMPLEAÑOS DE HOY
      </span>
      <span class="featured-birthday-card__date">
        <?php echo date('j'); ?> de <?php echo htmlspecialchars($mesNombre ?? ''); ?>
      </span>
    </div>
    <div class="featured-birthday-card__empty">
      <i class="bi bi-balloon-heart featured-birthday-card__empty-icon" aria-hidden="true"></i>
      <p class="featured-birthday-card__empty-text mb-0">
        Hoy no hay cumpleaños registrados en tu sección.
      </p>
    </div>
  <?php endif; ?>
</div>
<?php
unset($birthdayData);
?>
