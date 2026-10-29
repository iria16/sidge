<?php
declare(strict_types=1);

/**
 * Partial: estructuraAcademica/partials/panel-filtros.php
 * Año consultado y filtros por grado, sección y turno.
 *
 * @var string $anioActivo
 * @var int    $anioConsultado
 * @var array  $grados
 * @var array  $secciones
 * @var array  $turnos
 * @var array  $filters
 */
?>
  <div class="data-panel__active-year" role="status" aria-label="Año escolar activo: <?= htmlspecialchars($anioActivo) ?>">
    <i class="bi bi-calendar3" aria-hidden="true"></i>
    <strong><?= htmlspecialchars($anioActivo) ?></strong>
  </div>
  <input type="hidden" name="anio" value="<?= (int) $anioConsultado ?>">

<label for="filtroGrado" class="visually-hidden">Grado</label>
<select id="filtroGrado" name="grado" class="form-select" aria-label="Filtrar por grado">
  <option value="">Grado (Todos)</option>
  <?php foreach ($grados as $grado): ?>
    <option value="<?= (int) $grado['id'] ?>" <?= (string) ($filters['grado'] ?? '') === (string) $grado['id'] ? 'selected' : '' ?>>
      <?= htmlspecialchars($grado['nombre']) ?>
    </option>
  <?php endforeach; ?>
</select>

<label for="filtroSeccion" class="visually-hidden">Sección</label>
<select id="filtroSeccion" name="seccion" class="form-select" aria-label="Filtrar por sección">
  <option value="">Sección (Todas)</option>
  <?php foreach ($secciones as $seccion): ?>
    <option value="<?= (int) $seccion['id'] ?>" <?= (string) ($filters['seccion'] ?? '') === (string) $seccion['id'] ? 'selected' : '' ?>>
      <?= htmlspecialchars($seccion['nombre']) ?>
    </option>
  <?php endforeach; ?>
</select>

<label for="filtroTurno" class="visually-hidden">Turno</label>
<select id="filtroTurno" name="turno" class="form-select" aria-label="Filtrar por turno">
  <option value="">Turno (Todos)</option>
  <?php foreach ($turnos as $turno): ?>
    <option value="<?= (int) $turno['id'] ?>" <?= (string) ($filters['turno'] ?? '') === (string) $turno['id'] ? 'selected' : '' ?>>
      <?= htmlspecialchars($turno['nombre']) ?>
    </option>
  <?php endforeach; ?>
</select>
