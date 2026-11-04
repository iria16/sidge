<?php
declare(strict_types=1);

/**
 * Partial: estudiantes/partials/panel-filtros.php
 * Año, grado, sección y estado según el rol que consulta.
 *
 * @var bool  $esDocente
 * @var bool  $esSecretaria
 * @var array $aniosEscolares
 * @var array $grados
 * @var array $secciones
 * @var array $filters
 */
?>
  <?php /* Selector de Año Escolar para Dirección y Coordinación.
           Secretaría y Docente quedan acotados al año escolar activo. */ ?>
  <?php if (!$esDocente && !$esSecretaria): ?>
  <label for="filtroAnio" class="visually-hidden">Año Escolar</label>
  <select id="filtroAnio" name="anio" class="form-select" aria-label="Filtrar por año escolar">
    <option value="">Año Escolar (Todos)</option>
    <?php foreach (($aniosEscolares ?? []) as $ae): ?>
      <option value="<?= (int) $ae['id'] ?>" <?= (string) ($filters['anio'] ?? '') === (string) $ae['id'] ? 'selected' : '' ?>>
        <?= htmlspecialchars($ae['nombre']) ?><?= ($ae['estado'] ?? '') === 'ACTIVO' ? ' (Activo)' : ' (Cerrado)' ?>
      </option>
    <?php endforeach; ?>
  </select>
  <?php else: ?>
  <input type="hidden" name="anio" value="<?= htmlspecialchars((string) ($filters['anio'] ?? '')) ?>">
  <?php endif; ?>

  <?php /* Grado y sección se pintan fuera del rol docente. El selector de Estado solo se
         pinta para Dirección/Coordinación, ya que Secretaría y Docente únicamente consultan
         estudiantes activos del período en curso. */ ?>
  <?php if (!$esDocente): ?>
  <label for="filtroGrado" class="visually-hidden">Grado</label>
  <select id="filtroGrado" name="grado" class="form-select" aria-label="Filtrar por grado">
    <option value="">Grado (Todos)</option>
    <?php foreach ($grados as $grado): ?>
      <option value="<?= htmlspecialchars($grado) ?>" <?= (string) ($filters['grado'] ?? '') === $grado ? 'selected' : '' ?>>
        <?= htmlspecialchars($grado) ?>
      </option>
    <?php endforeach; ?>
  </select>

  <label for="filtroSeccion" class="visually-hidden">Sección</label>
  <select id="filtroSeccion" name="seccion" class="form-select" aria-label="Filtrar por sección">
    <option value="">Sección (Todas)</option>
    <?php foreach ($secciones as $seccion): ?>
      <option value="<?= htmlspecialchars($seccion) ?>" <?= (string) ($filters['seccion'] ?? '') === $seccion ? 'selected' : '' ?>>
        <?= htmlspecialchars($seccion) ?>
      </option>
    <?php endforeach; ?>
  </select>
  <?php endif; ?>

  <?php if (!$esDocente && !$esSecretaria): ?>
  <label for="filtroEstado" class="visually-hidden">Estado</label>
  <select id="filtroEstado" name="estado" class="form-select" aria-label="Filtrar por estado">
    <option value="">Estado (Todos)</option>
    <option value="activo" <?= ($filters['estado'] ?? '') === 'activo' ? 'selected' : '' ?>>Activos</option>
    <option value="retirado" <?= ($filters['estado'] ?? '') === 'retirado' ? 'selected' : '' ?>>Retirados</option>
    <option value="egresado" <?= ($filters['estado'] ?? '') === 'egresado' ? 'selected' : '' ?>>Egresados</option>
  </select>
  <?php endif; ?>
