<?php
declare(strict_types=1);

/**
 * Fragmento: empleados/partials/expediente-header.php
 * Cabecera hero del expediente del empleado (estilo uniforme con expediente del estudiante).
 *
 * @var array $empleado
 * @var bool  $puedeGestionar
 */
$activo  = strtoupper((string) ($empleado['estado'] ?? '')) === 'ACTIVO';
$nombre  = (string) ($empleado['nombre_completo'] ?? 'Empleado');
$doc     = trim((string) ($empleado['tipo_documento'] ?? '') . '-' . (string) ($empleado['numero_documento'] ?? ''), '-');
$cargo   = (string) ($empleado['cargo'] ?? 'Sin cargo');
$ingreso = !empty($empleado['fecha_ingreso']) ? date('d/m/Y', strtotime((string) $empleado['fecha_ingreso'])) : '';
$turno   = (string) ($empleado['turno'] ?? '');
?>
<section class="student-record__hero mb-4" aria-label="Resumen del empleado">
  <?php
  $avatarSize     = 'xl';
  $avatarTone     = $empleado['avatar_color'] ?? 'primary';
  $avatarSrc      = ($empleado['avatar_tipo'] ?? 'iniciales') === 'foto'
      ? BASE_URL . $empleado['avatar_foto']
      : '';
  $avatarInitials = $empleado['iniciales'] ?? 'EE';
  $avatarAlt      = $nombre;
  include __DIR__ . '/../../components/avatar.php';
  ?>

  <div class="student-record__identity">
    <h1 class="student-record__name"><?= htmlspecialchars($nombre) ?></h1>
    <div class="student-record__meta">
      <span><i class="bi bi-card-text" aria-hidden="true"></i><?= htmlspecialchars($doc !== '' ? $doc : 'Documento no disponible') ?></span>
      <span><i class="bi bi-briefcase" aria-hidden="true"></i><?= htmlspecialchars($cargo) ?></span>
      <?php if ($ingreso !== ''): ?>
        <span><i class="bi bi-calendar3" aria-hidden="true"></i>Ingreso: <?= htmlspecialchars($ingreso) ?></span>
      <?php endif; ?>
      <?php if ($turno !== ''): ?>
        <span><i class="bi bi-clock" aria-hidden="true"></i>Turno <?= htmlspecialchars($turno) ?></span>
      <?php endif; ?>
    </div>
  </div>

  <div class="student-record__hero-actions">
    <?php
    $badgeLabel  = $activo ? 'Activo' : 'Inactivo';
    $badgeStatus = $activo ? 'active' : 'inactive';
    include __DIR__ . '/../../components/status-badge.php';
    ?>
    <?php if (!empty($empleado['en_reposo'])): ?>
      <?php
      $badgeLabel  = 'En reposo';
      $badgeStatus = 'pending';
      include __DIR__ . '/../../components/status-badge.php';
      ?>
    <?php endif; ?>

    <button type="button" class="btn btn-sm btn-light"
            data-bs-toggle="modal" data-bs-target="#modalVistaPreviaExpediente">
      <i class="bi bi-printer me-1" aria-hidden="true"></i>Exportar ficha
    </button>
    <a class="btn btn-sm btn-light" href="<?= BASE_URL ?>empleado/index">
      <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Volver
    </a>
  </div>
</section>
