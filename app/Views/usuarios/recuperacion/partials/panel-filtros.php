<?php
declare(strict_types=1);

/**
 * Partial: usuarios/recuperacion/partials/panel-filtros.php
 * Filtro único por estado de la solicitud.
 *
 * @var array $filters
 */
?>
  <label for="filtroEstado" class="visually-hidden">Estado</label>
  <select id="filtroEstado" name="estado" class="form-select">
    <option value="">Estado (Todos)</option>
    <option value="pendiente" <?= ($filters['estado'] ?? '') === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
    <option value="aprobado"  <?= ($filters['estado'] ?? '') === 'aprobado'  ? 'selected' : '' ?>>Aprobado</option>
    <option value="rechazado" <?= ($filters['estado'] ?? '') === 'rechazado' ? 'selected' : '' ?>>Rechazado</option>
  </select>