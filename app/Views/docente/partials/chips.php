<?php
declare(strict_types=1);

/**
 * Partial: docente/partials/chips.php
 * Chips de cambio de vista (pestañas): nómina, contacto, cumpleañeros, boletas.
 *
 * @var string $vista
 * @var array  $vistas
 * @var callable $urlNomina
 * @var callable $e
 */
?>
<?php /* ── Chips de vista ─────────────────────────────────────────────── */ ?>
  <nav class="nomina-chips mb-3" aria-label="Tipo de listado">
    <?php foreach ($vistas as $clave => $def): ?>
      <a class="nomina-chip <?= $clave === $vista ? 'is-active' : '' ?>"
         href="<?= $e($urlNomina(['vista' => $clave])) ?>"
         data-filtros="<?= $e(implode(',', $def['filtros'] ?? ['q'])) ?>"
         <?= $clave === $vista ? 'aria-current="page"' : '' ?>><?= $e($def['etiqueta']) ?></a>
    <?php endforeach; ?>
  </nav>
