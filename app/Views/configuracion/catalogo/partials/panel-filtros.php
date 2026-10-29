<?php
declare(strict_types=1);

/**
 * Partial: catalogo/partials/panel-filtros.php
 * Filtro de estado. Se incluye dentro de un ob_start() para armar $panelFilters.
 *
 * @var string $catalogoActivo
 * @var array{estado?: string, q?: string} $filters
 */
?>
  <input type="hidden" name="tipo" value="<?= htmlspecialchars($catalogoActivo) ?>">

  <label for="filtroEstado" class="visually-hidden">Estado</label>
  <select id="filtroEstado" name="estado" class="form-select">
    <option value="" <?= empty($filters['estado']) ? 'selected' : '' ?>>Estado (Todos)</option>
    <option value="activo"   <?= ($filters['estado'] ?? '') === 'activo'   ? 'selected' : '' ?>>Activo</option>
    <option value="inactivo" <?= ($filters['estado'] ?? '') === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
  </select>
