<?php
declare(strict_types=1);

/**
 * Partial: estadisticas/partials/filtros-globales.php
 * Barra de filtros globales por Año Escolar, Grado y Sección, con acción de reporte oficial en PDF.
 *
 * @var array<int, array<string, mixed>> $anios
 * @var int                              $idAnio
 * @var string                           $nombrePeriodo
 * @var array<int, array<string, mixed>> $grados
 * @var int|null                         $idGrado
 * @var array<int, array<string, mixed>> $secciones
 * @var int|null                         $idSeccion
 * @var string                           $pestana
 * @var string                           $q
 */
?>
<div class="estadisticas-filter-bar mb-4">
  <form id="formFiltrosEstadisticas" method="GET" action="<?= BASE_URL ?>estadistica/index" class="row g-3 align-items-end">
    <input type="hidden" name="pestana" id="inputPestanaActiva" value="<?= htmlspecialchars($pestana) ?>">

    <!-- Filtro 1: Año Escolar -->
    <div class="col-12 col-md-4 col-lg-3">
      <label for="filtroAnio" class="form-label small fw-semibold text-secondary mb-1">
        <i class="bi bi-calendar3 me-1 text-primary"></i>Año Escolar
      </label>
      <select id="filtroAnio" name="anio" class="form-select form-select-sm">
        <?php foreach ($anios as $a): ?>
          <?php
          $idA      = (int) $a['id_anio_escolar'];
          $nomA     = (string) $a['nombre_periodo'];
          $estA     = (string) $a['estado'];
          $esActivo = $estA === 'ACTIVO';
          ?>
          <option value="<?= $idA ?>" <?= $idA === $idAnio ? 'selected' : '' ?>>
            <?= htmlspecialchars($nomA) ?><?= $esActivo ? ' (En curso)' : ' (' . htmlspecialchars($estA) . ')' ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Filtro 2: Grado -->
    <div class="col-12 col-sm-6 col-md-3">
      <label for="filtroGrado" class="form-label small fw-semibold text-secondary mb-1">
        <i class="bi bi-mortarboard me-1 text-primary"></i>Grado
      </label>
      <select id="filtroGrado" name="grado" class="form-select form-select-sm">
        <option value="">Todos los grados</option>
        <?php foreach ($grados as $g): ?>
          <?php $idG = (int) $g['id_grado']; ?>
          <option value="<?= $idG ?>" <?= $idG === $idGrado ? 'selected' : '' ?>>
            <?= htmlspecialchars((string) $g['nombre']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Filtro 3: Sección -->
    <div class="col-12 col-sm-6 col-md-3">
      <label for="filtroSeccion" class="form-label small fw-semibold text-secondary mb-1">
        <i class="bi bi-door-open me-1 text-primary"></i>Sección
      </label>
      <select id="filtroSeccion" name="seccion" class="form-select form-select-sm">
        <option value="">Todas las secciones</option>
        <?php foreach ($secciones as $s): ?>
          <?php $idS = (int) $s['id_seccion']; ?>
          <option value="<?= $idS ?>" <?= $idS === $idSeccion ? 'selected' : '' ?>>
            Sección "<?= htmlspecialchars((string) $s['nombre']) ?>"
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Reporte Oficial PDF (Exclusivo para toma de decisiones) -->
    <div class="col-12 col-md-2 col-lg-3 d-flex justify-content-md-end">
      <a class="btn btn-outline-danger btn-sm d-inline-flex align-items-center gap-1 shadow-sm px-3"
         target="_blank"
         href="<?= BASE_URL ?>estadistica/exportarHermanosPdf?anio=<?= $idAnio ?><?= $idGrado ? '&grado=' . $idGrado : '' ?><?= $idSeccion ? '&seccion=' . $idSeccion : '' ?>&inline=1">
        <i class="bi bi-file-earmark-pdf text-danger" aria-hidden="true"></i>
        <span>Reporte PDF</span>
      </a>
    </div>
  </form>
</div>
