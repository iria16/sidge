<?php
declare(strict_types=1);

/**
 * Partial: anios_escolares/partials/panel-filtros.php
 * Filtro de estado. Se incluye dentro de un ob_start() para armar $panelFilters.
 *
 * @var array{estado?: string, q?: string} $filters
 */
?>
  <label for="filtroEstado" class="visually-hidden">Estado</label>
  <select id="filtroEstado" name="estado" class="form-select" aria-label="Filtrar por estado">
    <option value="" <?= empty($filters['estado']) ? 'selected' : '' ?>>Estado (Todos)</option>
    <option value="planificado" <?= ($filters['estado'] ?? '') === 'planificado' ? 'selected' : '' ?>>Planificado</option>
    <option value="activo"      <?= ($filters['estado'] ?? '') === 'activo'      ? 'selected' : '' ?>>Activo</option>
    <option value="cerrado"     <?= ($filters['estado'] ?? '') === 'cerrado'     ? 'selected' : '' ?>>Cerrado</option>
  </select>
