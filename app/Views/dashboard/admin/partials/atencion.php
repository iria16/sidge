<?php
declare(strict_types=1);

/**
 * Partial: dashboard/admin/partials/atencion.php
 * Centro único de alertas operativas, seguridad y soporte para Administración.
 *
 * Variables esperadas:
 * @var array $requiereAtencion
 */

$hayAlertas = count($requiereAtencion) > 0;
?>
<section class="content-card mb-4" aria-labelledby="atencionAdminHeading">
  <div class="content-card__header d-flex justify-content-between align-items-center">
    <h2 class="content-card__title mb-0" id="atencionAdminHeading">
      <i class="bi bi-bell text-warning me-2" aria-hidden="true"></i>Atención inmediata
    </h2>
    <?php if ($hayAlertas): ?>
      <span class="badge text-bg-warning">Acción requerida (<?= count($requiereAtencion) ?>)</span>
    <?php endif; ?>
  </div>

  <div class="content-card__body p-3">
    <?php if (!$hayAlertas): ?>
      <!-- Estado vacío: Todo al día -->
      <div class="d-flex align-items-center gap-3 p-3 text-success bg-success-subtle rounded border border-success-subtle" role="status">
        <i class="bi bi-check-circle-fill fs-3 flex-shrink-0" aria-hidden="true"></i>
        <div>
          <div class="fw-semibold">Sin pendientes hoy</div>
          <div class="small">Todo en orden en los módulos administrativos y de seguridad institucional.</div>
        </div>
      </div>
    <?php else: ?>
      <div class="d-flex flex-column gap-3">
        <?php foreach ($requiereAtencion as $item): ?>
          <?php
          $id    = (string) ($item['id'] ?? '');
          $nivel = (string) ($item['nivel'] ?? 'warning');
          $texto = (string) ($item['texto'] ?? '');
          $conteo = (int) ($item['conteo'] ?? 0);
          $url   = $item['url'] ?? null;
          $enlaceTexto = $item['texto_enlace'] ?? null;

          $icono = match ($id) {
              'recuperaciones_pendientes'         => 'bi-key',
              'cuentas_bloqueadas'                => 'bi-shield-x',
              'empleados_sin_cuenta'              => 'bi-person-plus',
              'passwords_provisionales'           => 'bi-shield-lock',
              'datos_institucionales_incompletos' => 'bi-building-exclamation',
              'catalogos_sin_elementos'           => 'bi-tags',
              'intentos_fallidos_acumulados'      => 'bi-exclamation-triangle',
              default                             => 'bi-exclamation-circle',
          };

          // Descripción orientativa según el tipo de alerta
          $descripcion = match ($id) {
              'recuperaciones_pendientes'         => 'Solicitudes de reseteo de contraseña en espera de aprobación.',
              'cuentas_bloqueadas'                => 'Cuentas con intentos fallidos excedidos o bloqueo de seguridad activo.',
              'empleados_sin_cuenta'              => 'Personal activo en nómina sin credenciales de acceso creadas.',
              'passwords_provisionales'           => 'Usuarios que no han realizado su primer cambio obligatorio de clave.',
              'datos_institucionales_incompletos' => 'Faltan parámetros oficiales requeridos para emisión de constancias.',
              'catalogos_sin_elementos'           => 'Tablas maestras vacías que pueden bloquear operaciones en otros módulos.',
              'intentos_fallidos_acumulados'      => 'Intentos de inicio de sesión fallidos registrados recientemente.',
              default                             => 'Atención requerida en la plataforma.',
          };
          ?>
          <div class="alert alert-<?= htmlspecialchars($nivel) ?> py-2 px-3 mb-0 d-flex align-items-center justify-content-between flex-wrap gap-2" role="alert">
            <div class="d-flex align-items-center gap-2">
              <i class="bi <?= htmlspecialchars($icono) ?> fs-5 flex-shrink-0" aria-hidden="true"></i>
              <div>
                <div class="fw-medium">
                  <?= htmlspecialchars($texto) ?>
                  <?php if ($conteo > 0): ?>
                    <span class="badge text-bg-<?= $nivel === 'danger' ? 'danger' : 'warning' ?> ms-1"><?= $conteo ?></span>
                  <?php endif; ?>
                </div>
                <div class="text-support small"><?= htmlspecialchars($descripcion) ?></div>
              </div>
            </div>
            <?php if (!empty($url) && !empty($enlaceTexto)): ?>
              <a href="<?= htmlspecialchars((string) $url) ?>" class="btn btn-sm btn-<?= $nivel === 'danger' ? 'danger' : 'outline-primary' ?>">
                <?= htmlspecialchars((string) $enlaceTexto) ?> &rsaquo;
              </a>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
