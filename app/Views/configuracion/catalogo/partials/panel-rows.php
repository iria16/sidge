<?php
declare(strict_types=1);

/**
 * Partial: catalogo/partials/panel-rows.php
 * Filas del listado. Se incluye dentro de un ob_start() para armar
 * $panelTableBody.
 *
 * @var array<int, array<string, mixed>> $elementos
 * @var array<int, array<string, mixed>> $estadosGeo
 * @var array $tiposInstitucion
 * @var bool $pideGrado
 * @var array<int, string> $gradosAcademicosPorId
 */
?>
  <?php foreach ($elementos as $el):
    // La BD guarda el estado en MAYÚSCULA ('ACTIVO' / 'INACTIVO'),
    // ver comentario de CatalogModel. strtoupper() evita que esto
    // se rompa si algún día se guarda distinto.
    $esInactivo = strtoupper($el['estado']) !== 'ACTIVO';
  ?>
    <tr class="<?= $esInactivo ? 'is-muted' : '' ?>">
      <td class="fw-semibold celda-texto-largo">
        <?= htmlspecialchars($el['nombre']) ?>
        <?php if (!empty($estadosGeo)): ?>
          <!-- Ubicacion y codigo DEA van bajo el nombre, en letra pequena, y no
               como columnas: con cuatro columnas de datos mas acciones la
               tabla se aprieta en pantalla de portatil. -->
          <div class="text-support catalogo-ubicacion">
            <?php
              $estadoNombre = '';
              foreach ($estadosGeo as $opcionEstado) {
                  if ((int) $opcionEstado['id_estado'] === (int) ($el['id_estado_geo'] ?? 0)) {
                      $estadoNombre = $opcionEstado['nombre'];
                      break;
                  }
              }
              echo htmlspecialchars($estadoNombre !== '' ? $estadoNombre : 'Sin ubicación');
              if (!empty($el['codigo_dea'])) {
                  echo ' · DEA ' . htmlspecialchars((string) $el['codigo_dea']);
              }
            ?>
          </div>
        <?php endif; ?>
      </td>
      <?php if (!empty($tiposInstitucion)): ?>
        <td class="text-nowrap"><?= htmlspecialchars(\App\Support\Instituciones::etiquetaPorCatalogo(
                isset($el['id_tipo_institucion']) ? (int) $el['id_tipo_institucion'] : null
            )) ?></td>
      <?php endif; ?>
      <?php if (!empty($pideGrado)): ?>
        <td class="text-nowrap"><?= htmlspecialchars($gradosAcademicosPorId[(int) ($el['id_grado_academico'] ?? 0)] ?? 'Grado inactivo') ?></td>
      <?php endif; ?>
      <td class="text-center">
        <?php
          $badgeLabel  = $esInactivo ? 'Inactivo' : 'Activo';
          $badgeStatus = $esInactivo ? 'inactive' : 'active';
          include __DIR__ . '/../../../components/status-badge.php';
        ?>
      </td>
      <td class="text-center">
        <div class="data-panel__actions">
          <button type="button"
                  class="action-btn action-btn--edit editar-elemento"
                  data-elemento-id="<?= (int) $el['id'] ?>"
                  data-elemento-nombre="<?= htmlspecialchars($el['nombre']) ?>"
                  <?php if (!empty($pideGrado)): ?>data-elemento-grado="<?= (int) ($el['id_grado_academico'] ?? 0) ?>"<?php endif; ?>
                  <?php if (!empty($tiposInstitucion)): ?>data-elemento-tipo="<?= htmlspecialchars(
                        isset($el['id_tipo_institucion']) ? (string) $el['id_tipo_institucion'] : ''
                      ) ?>"
                  data-elemento-estado="<?= (int) ($el['id_estado_geo'] ?? 0) ?>"
                  data-elemento-municipio="<?= (int) ($el['id_municipio'] ?? 0) ?>"
                  data-elemento-parroquia="<?= (int) ($el['id_parroquia'] ?? 0) ?>"
                  data-elemento-dea="<?= htmlspecialchars((string) ($el['codigo_dea'] ?? '')) ?>"
                  data-elemento-extranjera="<?= !empty($el['es_extranjera']) ? '1' : '0' ?>"<?php endif; ?>
                  title="<?= $esInactivo ? 'No se puede editar un elemento inactivo' : 'Editar elemento' ?>"
                  <?= $esInactivo ? 'disabled' : '' ?>>
            <i class="bi bi-pencil" aria-hidden="true"></i>
          </button>
          <div class="form-check form-switch table-switch mb-0" title="<?= $esInactivo ? 'Activar' : 'Desactivar' ?> elemento">
            <input class="form-check-input toggle-estado"
                   type="checkbox"
                   role="switch"
                   data-elemento-id="<?= (int) $el['id'] ?>"
                   <?= $esInactivo ? '' : 'checked' ?>
                   aria-label="Activar o desactivar <?= htmlspecialchars($el['nombre']) ?>">
          </div>
        </div>
      </td>
    </tr>
  <?php endforeach; ?>

  <?php if (empty($elementos)): ?>
    <tr class="catalogo-empty-row">
      <td colspan="<?= (int) ($catalogoColumnas ?? 3) ?>" class="text-center text-support py-4">
        No se encontraron elementos con los filtros seleccionados.
      </td>
    </tr>
  <?php endif; ?>
