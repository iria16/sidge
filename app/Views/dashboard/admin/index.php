<?php

declare(strict_types=1);

/**
 * Vista: dashboard/admin/index.php
 * Panel de administración sobrio, limpio y moderno.
 *
 * Variables esperadas desde DashboardController::admin():
 * @var string     $saludo            Saludo según la hora de Caracas.
 * @var string     $nombreUsuario     Nombre del administrador en sesión.
 * @var array{id: int, nombre: string, estado: string}|null $anioActivo
 * @var array{
 *   total_usuarios: int,
 *   usuarios_activos: int,
 *   cuentas_bloqueadas: int,
 *   recuperaciones_pendientes: int
 * } $indicadores
 * @var list<array{
 *   id: string,
 *   texto: string,
 *   conteo: int,
 *   nivel: 'warning'|'danger',
 *   url: string|null,
 *   texto_enlace: string|null
 * }> $requiereAtencion
 * @var array{
 *   total: int,
 *   pico: int,
 *   fecha_inicio: string,
 *   fecha_mitad: string,
 *   fecha_fin: string,
 *   serie: list<array{fecha: string, dia_mes: string, total: int, altura_pct: int}>
 * } $actividad
 * @var list<array{
 *   rol: string,
 *   total: int,
 *   activos: int,
 *   inactivos: int,
 *   porcentaje_relativo: int
 * }> $usuariosPorRol
 */

$e = static fn($v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$pageTitle   = 'Dashboard';
$currentNav  = 'dashboard';
$breadcrumbs = [
    ['label' => 'Panel', 'href' => BASE_URL . 'dashboard/admin'],
    ['label' => 'Dashboard'],
];

// Contexto de encabezado
$nombrePeriodo = !empty($anioActivo) ? (string) $anioActivo['nombre'] : 'Sin año escolar activo';
$fechaHoyTexto = date('d/m/Y');

ob_start();
?>
<div class="admin-dashboard">

  <!-- 1. ENCABEZADO (Espejo estilístico del panel docente) -->
  <header class="admin-dashboard__header">
    <div>
      <span class="admin-badge-rol">ADMINISTRADOR DEL SISTEMA</span>
      <h1 class="admin-dashboard__greeting">¡<?= $e($saludo ?? 'Buenos días') ?>, <?= $e($nombreUsuario ?? 'Administrador') ?>!</h1>
      <p class="admin-dashboard__title">Panel de control de acceso, seguridad institucional y catálogos</p>
    </div>
    <div class="admin-dashboard__meta">
      <span><?= $e($nombrePeriodo) ?> · <?= $fechaHoyTexto ?></span>
    </div>
  </header>

  <!-- 2. FILA DE 4 INDICADORES (Componente oficial stat-card.php) -->
  <section aria-labelledby="headingIndicadores">
    <h2 id="headingIndicadores" class="visually-hidden">Indicadores generales</h2>
    <div class="row g-3">

      <!-- Usuarios registrados -->
      <div class="col-12 col-sm-6 col-xl-3">
        <?php
        $statLabel   = 'Usuarios registrados';
        $statValue   = number_format((int) ($indicadores['total_usuarios'] ?? 0), 0, ',', '.');
        $statIcon    = 'bi-people';
        $statTone    = 'primary';
        $statVariant = 'default';
        $statHref    = BASE_URL . 'cuentaUsuario/index';
        include __DIR__ . '/../../components/stat-card.php';
        ?>
      </div>

      <!-- Usuarios activos -->
      <div class="col-12 col-sm-6 col-xl-3">
        <?php
        $statLabel   = 'Usuarios activos';
        $statValue   = number_format((int) ($indicadores['usuarios_activos'] ?? 0), 0, ',', '.');
        $statIcon    = 'bi-person-check';
        $statTone    = 'success';
        $statVariant = 'default';
        $statMeta    = 'de ' . (int) ($indicadores['total_usuarios'] ?? 0) . ' registrados';
        $statHref    = BASE_URL . 'cuentaUsuario/index';
        include __DIR__ . '/../../components/stat-card.php';
        ?>
      </div>

      <!-- Cuentas bloqueadas -->
      <div class="col-12 col-sm-6 col-xl-3">
        <?php
        $bloqueadas  = (int) ($indicadores['cuentas_bloqueadas'] ?? 0);
        $statLabel   = 'Cuentas bloqueadas';
        $statValue   = (string) $bloqueadas;
        $statIcon    = 'bi-shield-x';
        $statTone    = $bloqueadas > 0 ? 'danger' : 'info';
        $statVariant = $bloqueadas > 0 ? 'alert' : 'default';
        $statMeta    = $bloqueadas > 0 ? 'Requieren desbloqueo' : 'Sin bloqueos';
        $statHref    = BASE_URL . 'cuentaUsuario/index';
        include __DIR__ . '/../../components/stat-card.php';
        ?>
      </div>

      <!-- Recuperaciones pendientes -->
      <div class="col-12 col-sm-6 col-xl-3">
        <?php
        $recuperaciones = (int) ($indicadores['recuperaciones_pendientes'] ?? 0);
        $statLabel      = 'Recuperaciones pendientes';
        $statValue      = (string) $recuperaciones;
        $statIcon       = 'bi-key';
        $statTone       = $recuperaciones > 0 ? 'warning' : 'info';
        $statVariant    = $recuperaciones > 0 ? 'alert' : 'default';
        $statMeta       = $recuperaciones > 0 ? 'Solicitudes en espera' : 'Al día';
        $statHref       = BASE_URL . 'recuperacionAcceso/index';
        include __DIR__ . '/../../components/stat-card.php';
        ?>
      </div>

    </div>
  </section>

  <!-- 3. REQUIERE ATENCIÓN -->
  <section aria-labelledby="headingAtencion" class="admin-dashboard__panel">
    <div class="admin-dashboard__panel-header">
      <h2 id="headingAtencion" class="admin-dashboard__panel-title">
        <i class="bi bi-bell text-secondary" aria-hidden="true"></i>
        Requiere atención
      </h2>
      <?php if (!empty($requiereAtencion)): ?>
        <span class="admin-dashboard__panel-meta admin-dashboard__num"><?= count($requiereAtencion) ?> observaciones</span>
      <?php endif; ?>
    </div>

    <?php if (empty($requiereAtencion)): ?>
      <div class="admin-dashboard__attention-empty">
        <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
        <span>Todo en orden en los módulos administrativos y de seguridad.</span>
      </div>
    <?php else: ?>
      <div class="admin-dashboard__attention-list">
        <?php foreach ($requiereAtencion as $item): ?>
          <?php
          $iconoItem = match ($item['id'] ?? '') {
              'empleados_sin_cuenta'              => 'bi-person-plus',
              'passwords_provisionales'           => 'bi-key',
              'datos_institucionales_incompletos' => 'bi-building-exclamation',
              'catalogos_sin_elementos'           => 'bi-tags',
              default                             => 'bi-exclamation-triangle',
          };
          $tonoItem = $item['nivel'] === 'danger' ? 'danger' : 'warning';
          ?>
          <div class="admin-dashboard__attention-item">
            <div class="admin-dashboard__attention-left">
              <span class="admin-dashboard__attention-icon admin-dashboard__attention-icon--<?= $tonoItem ?>" aria-hidden="true">
                <i class="bi <?= $iconoItem ?>"></i>
              </span>
              <div class="admin-dashboard__attention-info">
                <p class="admin-dashboard__attention-title">
                  <span><?= $e($item['texto']) ?></span>
                  <span class="admin-dashboard__attention-badge"><?= (int) $item['conteo'] ?></span>
                </p>
                <p class="admin-dashboard__attention-desc">
                  <?php if ($item['id'] === 'empleados_sin_cuenta'): ?>
                    Personal en nómina sin credenciales de inicio de sesión asignadas.
                  <?php elseif ($item['id'] === 'passwords_provisionales'): ?>
                    Usuarios asignados con clave temporal que aún no completan su primer cambio.
                  <?php elseif ($item['id'] === 'datos_institucionales_incompletos'): ?>
                    Faltan registros requeridos para membretes y constancias oficiales.
                  <?php elseif ($item['id'] === 'catalogos_sin_elementos'): ?>
                    Tablas de referencia vacías que impiden registros en otros módulos.
                  <?php else: ?>
                    Acción pendiente en el sistema.
                  <?php endif; ?>
                </p>
              </div>
            </div>
            <?php if (!empty($item['url']) && !empty($item['texto_enlace'])): ?>
              <div class="admin-dashboard__attention-action">
                <a href="<?= $e($item['url']) ?>" class="admin-dashboard__attention-link">
                  <?= $e($item['texto_enlace']) ?> <i class="bi bi-arrow-right" aria-hidden="true"></i>
                </a>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <!-- 4. DOS COLUMNAS: ACTIVIDAD DEL SISTEMA & USUARIOS POR ROL -->
  <div class="row g-4">

    <!-- 4A. Actividad del sistema (30 días) -->
    <div class="col-12 col-lg-7">
      <section aria-labelledby="headingActividad" class="admin-dashboard__panel">
        <div class="admin-dashboard__panel-header">
          <h2 id="headingActividad" class="admin-dashboard__panel-title">
            <i class="bi bi-activity text-secondary" aria-hidden="true"></i>
            Actividad (30 días)
          </h2>
          <div class="admin-dashboard__chart-header-stats">
            <span>Total: <strong><?= number_format((int) ($actividad['total'] ?? 0), 0, ',', '.') ?></strong> ops</span>
            <span>Pico: <strong><?= (int) ($actividad['pico'] ?? 0) ?></strong></span>
          </div>
        </div>

        <?php if (empty($actividad['total'])): ?>
          <p class="admin-dashboard__attention-empty">
            <i class="bi bi-info-circle" aria-hidden="true"></i>
            <span>Sin actividad registrada en los últimos 30 días.</span>
          </p>
        <?php else: ?>
          <div class="admin-dashboard__chart-container">
            <!-- Referencia de valor máximo -->
            <div class="admin-dashboard__chart-reference" aria-hidden="true">
              <span>Máx <?= (int) ($actividad['pico'] ?? 0) ?></span>
            </div>

            <!-- Gráfico diario -->
            <div class="admin-dashboard__chart-bars" role="img" aria-label="Frecuencia diaria de operaciones en los últimos 30 días">
              <?php foreach ($actividad['serie'] as $punto): ?>
                <div class="admin-dashboard__chart-col">
                  <div class="admin-dashboard__chart-tooltip">
                    <?= $e($punto['dia_mes']) ?>: <?= (int) $punto['total'] ?> ops
                  </div>
                  <div class="admin-dashboard__chart-bar" style="height: <?= (int) $punto['altura_pct'] ?>%"></div>
                </div>
              <?php endforeach; ?>
            </div>

            <!-- Eje X con fechas legibles completas -->
            <div class="admin-dashboard__chart-axis" aria-hidden="true">
              <span><?= $e($actividad['fecha_inicio']) ?></span>
              <span><?= $e($actividad['fecha_mitad']) ?></span>
              <span><?= $e($actividad['fecha_fin']) ?></span>
            </div>
          </div>

          <div class="admin-dashboard__chart-footer">
            <a href="<?= BASE_URL ?>bitacora/index" class="admin-dashboard__attention-link">
              Consultar registro de auditoría <i class="bi bi-arrow-right" aria-hidden="true"></i>
            </a>
          </div>
        <?php endif; ?>
      </section>
    </div>

    <!-- 4B. Usuarios por rol -->
    <div class="col-12 col-lg-5">
      <section aria-labelledby="headingRoles" class="admin-dashboard__panel">
        <div class="admin-dashboard__panel-header">
          <h2 id="headingRoles" class="admin-dashboard__panel-title">
            <i class="bi bi-pie-chart text-secondary" aria-hidden="true"></i>
            Usuarios por rol
          </h2>
          <span class="admin-dashboard__panel-meta admin-dashboard__num">
            <?= (int) ($indicadores['total_usuarios'] ?? 0) ?> cuentas
          </span>
        </div>

        <?php if (empty($usuariosPorRol)): ?>
          <p class="admin-dashboard__attention-empty">No se encontraron cuentas de usuario registradas.</p>
        <?php else: ?>
          <div class="admin-dashboard__roles-list">
            <?php foreach ($usuariosPorRol as $r): ?>
              <?php
              $rolNormalizado = strtolower(trim((string) $r['rol']));
              $badgeClass = match (true) {
                  str_contains($rolNormalizado, 'admin')       => 'admin-dashboard__role-badge--admin',
                  str_contains($rolNormalizado, 'directiv')    => 'admin-dashboard__role-badge--directivo',
                  str_contains($rolNormalizado, 'coordina')    => 'admin-dashboard__role-badge--coordinador',
                  str_contains($rolNormalizado, 'docente')     => 'admin-dashboard__role-badge--docente',
                  str_contains($rolNormalizado, 'secretar')    => 'admin-dashboard__role-badge--secretaria',
                  default                                      => 'admin-dashboard__role-badge--otro',
              };
              ?>
              <div class="admin-dashboard__role-row">
                <!-- Etiqueta a la izquierda con badge de rol -->
                <div class="admin-dashboard__role-info">
                  <span class="admin-dashboard__role-badge <?= $badgeClass ?>"><?= $e($r['rol']) ?></span>
                  <p class="admin-dashboard__role-sub">
                    <?= (int) $r['activos'] ?> activos · <?= (int) $r['inactivos'] ?> inactivos
                  </p>
                </div>

                <!-- Barra alineada al centro -->
                <div class="admin-dashboard__role-track" aria-hidden="true">
                  <div class="admin-dashboard__role-fill" style="width: <?= (int) $r['porcentaje_relativo'] ?>%"></div>
                </div>

                <!-- Valor a la derecha -->
                <div class="admin-dashboard__role-count">
                  <?= (int) $r['total'] ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    </div>

  </div>

</div>
<?php
$pageContent = ob_get_clean();

// No modales en el dashboard de administrador
$pageModals = '';

include __DIR__ . '/../../layouts/app.php';
