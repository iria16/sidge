<?php
/**
 * Vista: consulta_academica/estudiantes/index.php
 * Consulta de Expedientes — listado y búsqueda avanzada de estudiantes.
 *
 * Armada con los mismos componentes que el resto de módulos:
 * components/page-header.php, components/stat-card.php,
 * components/avatar.php, components/status-badge.php y
 * components/data-panel.php.
 *
 * Espera del controlador:
 * - array $stats ['total' => int, 'activos' => int, 'retirados' => int, 'egresados' => int]
 * - array $estudiantes cada uno: ['id','nombre','iniciales','avatar_color',
 * 'avatar_img' (opcional),'cedula_escolar','grado','seccion',
 * 'estado' => 'activo'|'retirado'|'egresado']
 * - array $aniosEscolares lista de años escolares para el filtro
 * - array $grados lista de grados para el filtro
 * - array $secciones lista de secciones para el filtro
 * - array $filters valores actuales de los filtros aplicados
 * - array $paginacion ['desde','hasta','total','pagina_actual','total_paginas']
 *
 * @var array $stats
 * @var array $estudiantes
 * @var array $aniosEscolares
 * @var array $grados
 * @var array $secciones
 * @var array $filters
 * @var array $paginacion
*/

$pageTitle       = 'Consulta de Expedientes';
$pageDescription = 'Gestión y búsqueda avanzada del alumnado de la institución.';
$currentNav      = 'estudiantes';

$breadcrumbs = [
    ['label' => 'Gestión de estudiantes', 'href' => null],
    ['label' => 'Estudiantes', 'href' => null],
];

$estudiantes    = $estudiantes ?? [];
$aniosEscolares = $aniosEscolares ?? [];
$esDocente      = !empty($esDocente);
$esSecretaria   = !empty($esSecretaria);
$grados         = $grados ?? [];
$secciones      = $secciones ?? [];
$filters        = $filters ?? ['q' => '', 'anio' => '', 'grado' => '', 'seccion' => '', 'estado' => ''];
$paginacion     = $paginacion ?? [
    'desde' => 0, 'hasta' => 0, 'total' => 0, 'pagina_actual' => 1, 'total_paginas' => 1,
];

ob_start();

  // ---------- Page header ----------
  // Sin acciones en el header: el módulo es de consulta y navegación.
  $pageHeading    = 'Consulta de Expedientes';
  $pageSubheading = $pageDescription;
  include __DIR__ . '/../../components/page-header.php';
?>

<?php
  // ---------- Data panel: filtros ----------
  // El filtrado es automático (main.js, form.data-panel__filters): los
  // <select> y el buscador disparan el panel al cambiar, sin botón de buscar.
  $panelFormAction = BASE_URL . 'estudiantes/index';

  ob_start();
  ?>
  <?php /* El listado opera sobre el año escolar activo para docente/secretaría y sobre el
           archivo institucional para dirección; el expediente acumula el historial completo. */ ?>
  <input type="hidden" name="anio" value="<?= htmlspecialchars((string) ($filters['anio'] ?? '')) ?>">

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
  <?php
  $panelFilters           = ob_get_clean();
  $panelSearchName        = 'q';
  $panelSearchValue       = (string) ($filters['q'] ?? '');
  $panelSearchPlaceholder = 'Buscar por nombre o cédula...';

  // ---------- Data panel: colgroup ----------
  /* Los <col> tienen que coincidir con las columnas que realmente se pintan y
     sumar 100%. Con el colgroup viejo (cinco columnas al 85%) el docente, que ve
     cuatro, se quedaba con un 15% de tabla vacía al final: la quinta <col> no
     tenía celda a la que repartirse y el hueco aparecía a la derecha.

     El nombre del alumno es la columna larga y se lleva el ancho mayor; el estado
     y la acción son cortas y no necesitan tanto. */
  $anchosColumnas = $esDocente
      ? ['44%', '22%', '16%', '18%']                      // Nombre / Cédula / Estado / Acción
      : ['30%', '16%', '24%', '14%', '16%'];               // + Grado y sección
  ob_start();
  ?>
  <colgroup>
    <?php foreach ($anchosColumnas as $ancho): ?>
      <col style="width: <?= $ancho ?>;">
    <?php endforeach; ?>
  </colgroup>
  <?php
  $panelColgroup = ob_get_clean();

  // ---------- Data panel: thead ----------
  ob_start();
  ?>
  <tr>
      <th scope="col">Nombre completo</th>
      <th scope="col">Cédula escolar</th>
      <?php /* «Grado y sección» no se le pinta al docente: su lista ya es de una sola
               sección, la que da, así que la columna repite en cada fila lo mismo que
               ya dice el encabezado de la página. Es la misma razón por la que sus
               filtros de grado y sección no existen. */ ?>
      <?php if (!$esDocente): ?>
        <th scope="col">Grado y sección</th>
      <?php endif; ?>
      <th scope="col" class="text-center">Estado</th>
      <th scope="col" class="text-center data-panel__col-acciones">Acciones</th>
  </tr>
  <?php
  $panelTableHead = ob_get_clean();

  // ---------- Data panel: tbody ----------
  ob_start();
  foreach ($estudiantes as $estudiante):
    // El estado viene del ciclo del estudiante (activo, retirado o egresado), no de la
    // persona: retirado y egresado son desenlaces distintos y se distinguen en la tabla.
    $estadoCiclo    = (string) ($estudiante['estado'] ?? 'activo');
    $estadoEtiqueta = ['activo' => 'Activo', 'retirado' => 'Retirado', 'egresado' => 'Egresado'][$estadoCiclo] ?? 'Activo';
    // El color sale de los modificadores que ya existen en la hoja de estilos: retirado es
    // una salida antes de culminar (rojo, como el retiro) y egresado una culminación (informativo).
    $estadoColor    = ['activo' => 'active', 'retirado' => 'inactive', 'egresado' => 'info'][$estadoCiclo] ?? 'active';
  ?>
    <tr>
      <td data-label="">
        <div class="avatar-group">
          <?php
            $avatarSize     = 'md';
            $avatarTone     = $estudiante['avatar_color'] ?? 'primary';
            $avatarSrc      = $estudiante['avatar_img'] ?? '';
            $avatarAlt      = $estudiante['nombre'] ?? '';
            $avatarInitials = $estudiante['iniciales'] ?? 'ES';
            include __DIR__ . '/../../components/avatar.php';
          ?>
          <span class="avatar-group__name"><?= htmlspecialchars($estudiante['nombre'] ?? '') ?></span>
        </div>
      </td>
      <td class="text-support" data-label="Cédula escolar"><?= htmlspecialchars($estudiante['cedula_escolar'] ?? '') ?></td>
      <?php if (!$esDocente): ?>
        <td class="text-support text-wrap" data-label="Grado y sección">
          <div class="fw-medium text-dark text-break"><?= htmlspecialchars($estudiante['grado'] ?? 'Sin grado') ?></div>
          <div class="text-muted small">Sección <?= htmlspecialchars($estudiante['seccion'] ?? '—') ?></div>
        </td>
      <?php endif; ?>
      <td class="text-center" data-label="Estado">
        <?php
          $badgeLabel  = $estadoEtiqueta;
          $badgeStatus = $estadoColor;
          include __DIR__ . '/../../components/status-badge.php';
        ?>
      </td>
      <td class="text-center data-panel__col-acciones" data-label="Acciones">
        <div class="data-panel__actions">
          <a href="<?= BASE_URL ?>estudiantes/show/<?= (int) ($estudiante['id'] ?? 0) ?>?anio=<?= (int) ($filters['anio'] ?? 0) ?>"
             class="action-btn action-btn--view"
             title="Abrir expediente">
            <i class="bi bi-folder2-open" aria-hidden="true"></i>
          </a>
        </div>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$estudiantes): ?>
    <tr>
      <td colspan="<?= $esDocente ? 4 : 5 ?>" class="text-center text-support py-4">
        <?php /* Sin asignaciones la tabla vacía es la respuesta correcta, no un fallo: el
 docente solo ve lo que tiene asignado. Se dice explícitamente para que no
 parezca que el listado no funciona.*/ ?>
        <?php if (!empty($sinAsignaciones)): ?>
          <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
          No tiene estructuras asignadas en el año escolar en curso, así que todavía no le
          corresponden expedientes. Pida al coordinador o a la dirección que registren su
          asignación en Configuración → Asignación Docente.
        <?php else: ?>
          No se encontraron estudiantes con los filtros seleccionados.
        <?php endif; ?>
      </td>
    </tr>
  <?php endif; ?>
  <?php
  $panelTableBody = ob_get_clean();

  $panelSummary = 'Mostrando ' . (int) $paginacion['desde'] . ' a ' . (int) $paginacion['hasta']
                . ' de ' . number_format((int) $paginacion['total'], 0, ',', '.') . ' expedientes';
$panelPagination = [
      'pagina_actual' => $paginacion['pagina_actual'],
      'total_paginas' => $paginacion['total_paginas'],
  ];
  include __DIR__ . '/../../components/data-panel.php';
?>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';
