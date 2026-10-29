<?php
declare(strict_types=1);

/**
 * Vista: resultado_academico/seccion.php
 * Carga de literales de promoción (solo docente).
 *
 * @var array  $detalle
 * @var string $rolUsuario
 * @var array  $literales
 * @var array  $literalesPromueven
 * @var array  $literalesExigenObservacion
 * @var bool   $anioCerrado
 * @var string $filtroEstadoInicial
 * @var string|null $flash_error
 * @var string|null $flash_success
 * @var string|null $flash_info
 */

$esDocente      = ($rolUsuario === 'docente');
$gradoNombre    = (string) ($detalle['grado'] ?? '');
$seccionNombre  = (string) ($detalle['seccion'] ?? '');
$turnoNombre    = (string) ($detalle['turno'] ?? '');
$anioNombre     = (string) ($detalle['anio_escolar'] ?? '');
$docenteNombre  = (string) ($detalle['docente_nombre'] ?? 'Sin asignar');
$idEstructura   = (int) $detalle['id'];
$idGradoActual  = (int) $detalle['id_grado'];

$resumen          = \App\Support\ResultadoAcademicoVista::resumenDetalle($detalle, $esDocente);
$filas            = $resumen['filas'];
$idGradoSiguiente = $resumen['id_grado_siguiente'];
$textoGradoAvanza = $resumen['texto_grado_avanza'];

$pageTitle       = "Literales Finales - {$gradoNombre} \"{$seccionNombre}\"";
$pageDescription = "Turno {$turnoNombre} · Carga oficial de calificaciones cualitativas";
$currentNav      = 'mi-seccion-promocion';

$breadcrumbs = [
    ['label' => 'Mi sección', 'href' => null],
    ['label' => 'Resultados y Promoción', 'href' => BASE_URL . 'resultadoAcademico/index'],
    ['label' => "{$gradoNombre} \"{$seccionNombre}\"", 'href' => null],
];

ob_start();

ob_start();
?>
<div class="d-flex align-items-center gap-2 flex-wrap">
  <a href="<?= BASE_URL ?>resultadoAcademico/index" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
    <i class="bi bi-arrow-left" aria-hidden="true"></i>
    <span>Volver</span>
  </a>
  <?php if ($resumen['con_literal'] > 0): ?>
    <button type="button" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1 shadow-sm btn-preview-certificado"
            data-url-preview="<?= BASE_URL ?>certificadoCalificaciones/generarPorSeccion?id_estructura=<?= $idEstructura ?>"
            data-titulo="Certificados de Calificaciones"
            data-subtitulo="<?= htmlspecialchars($gradoNombre . ' "' . $seccionNombre . '" · Turno ' . $turnoNombre . ' · Período ' . $anioNombre) ?>">
      <i class="bi bi-eye me-1" aria-hidden="true"></i>
      <span>Visualizar Certificados de la Sección</span>
    </button>
  <?php endif; ?>
</div>
<?php
$pageHeaderActions = ob_get_clean();
$pageHeading       = $pageTitle;
$pageSubheading    = "Turno {$turnoNombre} · Carga oficial de calificaciones cualitativas";
?>
<div class="d-flex align-items-center gap-2 mb-2">
  <span class="badge resultado-acta-badge">ACTA FINAL</span>
  <span class="text-secondary small fw-medium">
    Período Escolar <?= htmlspecialchars($anioNombre) ?>
  </span>
</div>
<?php
include __DIR__ . '/../components/page-header.php';
?>

<?php if ($flash_error !== null): ?>
  <div class="alert alert-danger" role="alert"><?= htmlspecialchars($flash_error) ?></div>
<?php endif; ?>
<?php if ($flash_success !== null): ?>
  <div class="alert alert-success" role="alert"><?= htmlspecialchars($flash_success) ?></div>
<?php endif; ?>
<?php if ($flash_info !== null): ?>
  <div class="alert alert-info" role="alert"><?= htmlspecialchars($flash_info) ?></div>
<?php endif; ?>

<?php include __DIR__ . '/partials/normativa-card.php'; ?>
<?php include __DIR__ . '/partials/nomina-panel.php'; ?>

<?php
$pageContent = ob_get_clean();

ob_start();
include __DIR__ . '/modals/guia-modal.php';
include __DIR__ . '/modals/preview-modal.php';
$pageModals = ob_get_clean();

$extraScripts = [
    '/js/modules/resultado_academico/seccion.js',
];

require __DIR__ . '/../layouts/app.php';
