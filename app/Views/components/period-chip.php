<?php
/**
 * Componente: period-chip.php
 * Muestra el chip indicador del período escolar activo.
 *
 * - string $periodText    Texto del período (ej. "Período 2024-2025")
 * - string $periodStatus  Texto de estado (default "Activo")
 * - bool   $periodIsActive (default true)
 */
$periodText     = $periodText ?? '';
$periodStatus   = $periodStatus ?? 'Activo';
$periodIsActive = $periodIsActive ?? true;
?>
<span class="period-chip<?php echo $periodIsActive ? ' period-chip--active' : ''; ?>">
  <i class="bi bi-calendar-check" aria-hidden="true"></i>
  <span class="period-chip__text"><?php echo htmlspecialchars($periodText); ?></span>
  <?php if ($periodStatus !== ''): ?>
    <span class="period-chip__status"><?php echo htmlspecialchars($periodStatus); ?></span>
  <?php endif; ?>
</span>
<?php
unset($periodText, $periodStatus, $periodIsActive);
?>
