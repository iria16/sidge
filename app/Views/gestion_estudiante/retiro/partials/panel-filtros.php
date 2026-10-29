<?php
declare(strict_types=1);

/**
 * Partial: retiro/partials/panel-filtros.php
 * Filtro de estado. Se incluye dentro de un ob_start() para armar $panelFilters.
 *
 * @var array{estado?: string, q?: string} $filters
 */
?>
  <label for="filtroEstado" class="visually-hidden">Estado</label>
  <select id="filtroEstado" name="estado" class="form-select" aria-label="Filtrar por estado">
    <option value="">Estado (Todos)</option>
    <option value="pendiente" <?= ($filters['estado'] ?? '') === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
    <option value="aprobado"  <?= ($filters['estado'] ?? '') === 'aprobado'  ? 'selected' : '' ?>>Aprobado</option>
    <option value="rechazado" <?= ($filters['estado'] ?? '') === 'rechazado' ? 'selected' : '' ?>>Rechazado</option>
  </select>
