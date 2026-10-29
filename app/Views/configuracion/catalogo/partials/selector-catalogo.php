<?php
declare(strict_types=1);

/**
 * Partial: catalogo/partials/selector-catalogo.php
 * Selector del tipo de catálogo y resumen del catálogo activo.
 *
 * @var array<int, array{clave: string, nombre: string, icono: string, total: int}> $tiposCatalogo
 * @var string $catalogoActivo
 * @var array{clave: string, nombre: string, icono: string, total: int} $catalogoInfo
 */
?>
<!-- ---------- Selector de catálogo (equivalente a los stat cards de cuentas) ---------- -->
<div class="row g-3 mb-3">
  <div class="col-12">
    <div class="data-panel">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 p-3">
        <div class="d-flex align-items-center gap-2 flex-grow-1 max-ancho-360px">
          <label for="tipoCatalogo" class="text-support small text-nowrap mb-0">Seleccionar tipo de catálogo:</label>
          <select id="tipoCatalogo" name="tipo" class="form-select form-select-sm">
            <?php foreach ($tiposCatalogo as $tipo): ?>
              <option value="<?= htmlspecialchars($tipo['clave']) ?>" <?= $tipo['clave'] === $catalogoActivo ? 'selected' : '' ?>>
                <?= htmlspecialchars($tipo['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="d-flex align-items-center gap-3 ps-3 border-start">
          <div class="stat-card__icon stat-card__icon--primary d-flex align-items-center justify-content-center">
            <i class="bi <?= htmlspecialchars($catalogoInfo['icono']) ?>" aria-hidden="true"></i>
          </div>
          <div class="d-flex flex-column justify-content-center">
            <div class="stat-card__label mb-0">Catálogo activo</div>
            <div class="content-card__title mb-0"><?= htmlspecialchars($catalogoInfo['nombre']) ?></div>
          </div>
          <span id="catalogoTotalBadge"
                class="badge bg-primary-subtle text-primary rounded-pill px-3 py-2 fw-semibold"
                data-total="<?= (int) $catalogoInfo['total'] ?>">
            <?= (int) $catalogoInfo['total'] ?> registro<?= $catalogoInfo['total'] === 1 ? '' : 's' ?>
          </span>
        </div>
      </div>
    </div>
  </div>
</div>
