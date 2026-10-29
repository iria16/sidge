<?php
declare(strict_types=1);

/**
 * Partial: asignacionDocente/partials/panel-filtros.php
 * Año activo y filtros por grado, sección y turno.
 *
 * @var string $anioActivo
 * @var array<int, array<string, mixed>> $grados
 * @var array<int, array<string, mixed>> $secciones
 * @var array<int, array<string, mixed>> $turnos
 * @var array<string, mixed> $filters
 */
?>
  <div class="data-panel__active-year" role="status" aria-label="Año escolar activo: <?= htmlspecialchars($anioActivo) ?>">
    <i class="bi bi-calendar3" aria-hidden="true"></i>
    <strong><?= htmlspecialchars($anioActivo) ?></strong>
  </div>

  <label for="filtroGrado" class="visually-hidden">Grado</label>
  <select id="filtroGrado" name="grado" class="form-select" aria-label="Filtrar por grado">
    <option value="">Grado (Todos)</option>
    <?php foreach ($grados as $option): ?>
      <option value="<?= (int) $option['id'] ?>" <?= (string) ($filters['grado'] ?? '') === (string) $option['id'] ? 'selected' : '' ?>>
        <?= htmlspecialchars($option['nombre']) ?>
      </option>
    <?php endforeach; ?>
  </select>

  <label for="filtroSeccion" class="visually-hidden">Sección</label>
  <select id="filtroSeccion" name="seccion" class="form-select" aria-label="Filtrar por sección">
    <option value="">Sección (Todas)</option>
    <?php foreach ($secciones as $option): ?>
      <option value="<?= (int) $option['id'] ?>" <?= (string) ($filters['seccion'] ?? '') === (string) $option['id'] ? 'selected' : '' ?>>
        <?= htmlspecialchars($option['nombre']) ?>
      </option>
    <?php endforeach; ?>
  </select>

  <label for="filtroTurno" class="visually-hidden">Turno</label>
  <select id="filtroTurno" name="turno" class="form-select" aria-label="Filtrar por turno">
    <option value="">Turno (Todos)</option>
    <?php foreach ($turnos as $option): ?>
      <option value="<?= (int) $option['id'] ?>" <?= (string) ($filters['turno'] ?? '') === (string) $option['id'] ? 'selected' : '' ?>>
        <?= htmlspecialchars($option['nombre']) ?>
      </option>
    <?php endforeach; ?>
  </select>
