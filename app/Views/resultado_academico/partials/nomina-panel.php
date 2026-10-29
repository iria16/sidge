<?php
/**
 * Partial: formulario y nómina de literales de una sección.
 *
 * @var int       $idEstructura
 * @var int       $idGradoActual
 * @var int|null  $idGradoSiguiente
 * @var string    $gradoNombre
 * @var string    $textoGradoAvanza
 * @var string    $filtroEstadoInicial
 * @var array     $literales
 * @var array     $literalesPromueven
 * @var array     $literalesExigenObservacion
 * @var array     $filas
 * @var array     $resumen
 * @var bool      $anioCerrado
 */
$literalesPromuevenJson    = htmlspecialchars(json_encode($literalesPromueven), ENT_QUOTES);
$literalesExigenObsJson    = htmlspecialchars(json_encode($literalesExigenObservacion), ENT_QUOTES);
$soloLectura               = $anioCerrado ? '1' : '0';
$filtroInicial             = in_array($filtroEstadoInicial, ['PENDIENTE', 'CARGADOS'], true) ? $filtroEstadoInicial : 'todos';
?>
<form id="formResultados" method="POST" action="<?= BASE_URL ?>resultadoAcademico/guardarPropuesta"
      data-literales-promueven="<?= $literalesPromuevenJson ?>"
      data-literales-exigen-observacion="<?= $literalesExigenObsJson ?>"
      data-id-grado-actual="<?= $idGradoActual ?>"
      data-id-grado-siguiente="<?= (int) ($idGradoSiguiente ?? 0) ?>"
      data-texto-grado-avanza="<?= htmlspecialchars($textoGradoAvanza) ?>"
      data-grado-nombre="<?= htmlspecialchars($gradoNombre) ?>"
      data-solo-lectura="<?= $soloLectura ?>"
      data-filtro-inicial="<?= htmlspecialchars($filtroEstadoInicial) ?>">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
  <input type="hidden" name="id_estructura_academica" value="<?= $idEstructura ?>">

  <div class="data-panel mb-5">
    <?php if ($anioCerrado): ?>
      <div class="resultado-aviso resultado-aviso--cerrada">
        <i class="bi bi-lock-fill" aria-hidden="true"></i>
        El período escolar está cerrado: la nómina se muestra en solo lectura.
      </div>
    <?php endif; ?>

    <div class="data-panel__toolbar d-flex align-items-center justify-content-between flex-wrap gap-3 p-3">
      <div class="btn-group btn-group-sm" role="group" aria-label="Filtro de estado de evaluación">
        <button type="button" class="btn btn-outline-secondary btn-filter-tab <?= $filtroInicial === 'todos' ? 'active' : '' ?>" data-filter="todos">
          Todos (<?= $resumen['total'] ?>)
        </button>
        <button type="button" class="btn btn-outline-secondary btn-filter-tab <?= $filtroInicial === 'PENDIENTE' ? 'active' : '' ?>" data-filter="PENDIENTE">
          Pendientes (<span id="filterCountPendientes"><?= $resumen['sin_literal'] ?></span>)
        </button>
        <button type="button" class="btn btn-outline-secondary btn-filter-tab <?= $filtroInicial === 'CARGADOS' ? 'active' : '' ?>" data-filter="CARGADOS">
          Cargados (<span id="filterCountCargados"><?= $resumen['con_literal'] ?></span>)
        </button>
      </div>

      <div class="d-flex align-items-center gap-2 flex-grow-1 flex-md-grow-0 justify-content-end resultado-toolbar__actions">
        <div class="position-relative flex-grow-1 resultado-toolbar__search">
          <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary resultado-toolbar__search-icon" aria-hidden="true"></i>
          <input type="search" id="filtroBuscador" class="form-control form-control-sm ps-5"
                 placeholder="Buscar por nombre o cédula..." autocomplete="off">
        </div>

        <?php if (!$anioCerrado): ?>
          <button type="submit" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1 shadow-sm" id="btnGuardarPropuesta">
            <i class="bi bi-floppy-fill" aria-hidden="true"></i>
            <span>Guardar Cambios</span>
          </button>
        <?php endif; ?>
      </div>
    </div>

    <div class="data-panel__body table-responsive">
      <table class="table data-panel__table align-middle mb-0 resultado-nomina-table" id="tablaResultados">
        <thead>
          <tr class="text-secondary small">
            <th scope="col" class="resultado-col-num text-center text-nowrap">N°</th>
            <th scope="col" class="resultado-col-estudiante">ESTUDIANTE</th>
            <th scope="col" class="resultado-col-literal text-center">LITERAL</th>
            <th scope="col" class="resultado-col-decision">DECISIÓN OFICIAL</th>
            <th scope="col" class="resultado-col-obs">OBSERVACIÓN PEDAGÓGICA</th>
            <th scope="col" class="resultado-col-boleta text-center">BOLETA OFICIAL</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($filas)): ?>
            <tr>
              <td colspan="6" class="text-center py-5 text-muted">
                <i class="bi bi-people fs-2 d-block mb-2 text-secondary opacity-50"></i>
                No hay estudiantes cursando en esta sección.
              </td>
            </tr>
          <?php else: ?>
            <?php include __DIR__ . '/nomina-rows.php'; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="data-panel__footer d-flex justify-content-between align-items-center flex-wrap gap-2 p-3">
      <span class="text-secondary small">
        Mostrando <?= $resumen['total'] ?> estudiante<?= $resumen['total'] === 1 ? '' : 's' ?> en la nómína de la sección.
        <span data-contador-texto><?= $resumen['con_literal'] ?> de <?= $resumen['total'] ?> evaluados</span>
      </span>
    </div>
  </div>
</form>
