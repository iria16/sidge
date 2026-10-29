<?php
declare(strict_types=1);

/**
 * Partial: ratificacion/partials/panel-filtros.php
 * Filtros del panel. Se incluye dentro de un ob_start() para armar $panelFilters.
 *
 * @var array<int, string> $grados
 * @var array<int, string> $secciones
 * @var array{grado?: string, seccion?: string, turno?: string, q?: string} $filters
 */
?>
  <label for="filtroGrado" class="visually-hidden">Grado actual</label>
  <select id="filtroGrado" name="grado" class="form-select" aria-label="Filtrar por grado actual">
    <option value="">Grado (Todos)</option>
    <?php foreach ($grados as $grado): ?>
      <option value="<?= htmlspecialchars($grado) ?>" <?= (string) ($filters['grado'] ?? '') === (string) $grado ? 'selected' : '' ?>>
        <?= htmlspecialchars($grado) ?>
      </option>
    <?php endforeach; ?>
  </select>

  <label for="filtroSeccion" class="visually-hidden">Sección</label>
  <select id="filtroSeccion" name="seccion" class="form-select" aria-label="Filtrar por sección">
    <option value="">Sección (Todas)</option>
    <?php foreach ($secciones as $seccion): ?>
      <option value="<?= htmlspecialchars($seccion) ?>" <?= (string) ($filters['seccion'] ?? '') === (string) $seccion ? 'selected' : '' ?>>
        <?= htmlspecialchars($seccion) ?>
      </option>
    <?php endforeach; ?>
  </select>
