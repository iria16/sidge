<?php
declare(strict_types=1);

/**
 * Partial: docente/partials/filters.php
 * Filtros automáticos por vista (sexo, mes, búsqueda).
 *
 * @var string $vista
 * @var array  $filtros
 * @var array  $filtrosVista
 * @var array  $meses
 * @var string $placeholderBuscar
 * @var bool   $tieneSelectFiltro
 * @var callable $e
 */
?>
<?php /* ── Filtros automáticos propios de cada vista (main.js) ──────────
           Se dibuja solo si la vista trae algún filtro. El acta no trae ninguno:
           sin esta comprobación la tarjeta salía vacía, con el hueco de los
           filtros, que es lo que veía el docente al entrar a esa pestaña. */ ?>
<?php if ($filtrosVista !== []): ?>
  <form class="card border-0 shadow-sm mb-3 auto-filters" id="filtrosListadoNomina" method="get" action="<?= BASE_URL ?>docente/nomina">
    <input type="hidden" name="vista" value="<?= $e($vista) ?>">
    <div class="card-body">
      <div class="row g-2 align-items-end">
        <?php if (in_array('sexo', $filtrosVista, true)): ?>
          <div class="col-12 col-md-4 col-lg-3">
            <label for="sexo" class="form-label small fw-semibold">Sexo</label>
            <select class="form-select" id="sexo" name="sexo">
              <option value="">Todos</option>
              <option value="F" <?= $filtros['sexo'] === 'F' ? 'selected' : '' ?>>Femenino</option>
              <option value="M" <?= $filtros['sexo'] === 'M' ? 'selected' : '' ?>>Masculino</option>
            </select>
          </div>
        <?php endif; ?>
        <?php if (in_array('mes', $filtrosVista, true)): ?>
          <div class="col-12 col-md-4 col-lg-3">
            <label for="mes" class="form-label small fw-semibold">Mes de cumpleaños</label>
            <select class="form-select" id="mes" name="mes">
              <?php foreach ($meses as $num => $nombreMes): ?>
                <option value="<?= (int) $num ?>" <?= $filtros['mes'] === $num ? 'selected' : '' ?>><?= $e($nombreMes) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>
        <?php if (in_array('q', $filtrosVista, true)): ?>
          <div class="<?= $tieneSelectFiltro ? 'col-12 col-md-8 col-lg-9' : 'col-12' ?>">
            <label for="q" class="form-label small fw-semibold">Buscar</label>
            <input type="search" class="form-control" id="q" name="q" maxlength="60"
                   value="<?= $e($filtros['q']) ?>" placeholder="<?= $e($placeholderBuscar) ?>">
          </div>
        <?php endif; ?>
      </div>
    </div>
  </form>
<?php endif; ?>