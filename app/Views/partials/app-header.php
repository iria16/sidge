<?php
/**
 * Partial: app-header.php
 * Barra superior: menú móvil, breadcrumb, notificaciones y perfil.
 *
 * Variables esperadas:
 * - array  $breadcrumbs     Lista de ['label' => ..., 'href' => ...] (último = activo)
 * - string $nombreUsuario
 * - string $rolSesion
 * - bool   $tieneNotificaciones  Opcional. Muestra el punto rojo.
 */
$breadcrumbs          = $breadcrumbs ?? [];
$nombreUsuario        = $nombreUsuario ?? 'Usuario';
$rolSesion            = $rolSesion ?? '';
$tieneNotificaciones  = $tieneNotificaciones ?? in_array($rolSesion, ['admin', 'secretaria', 'directivo']);
$iniciales            = sigde_iniciales($nombreUsuario);
$rolEtiqueta          = sigde_rol_etiqueta($rolSesion);
?>
<header class="app-header">
  <div class="app-header__left">
    <button
      type="button"
      class="app-header__toggle"
      id="sidebarToggle"
      aria-controls="appSidebar"
      aria-expanded="false"
      aria-label="Abrir menú de navegación"
    >
      <i class="bi bi-list" aria-hidden="true"></i>
    </button>

    <?php if (!empty($breadcrumbs)): ?>
      <nav aria-label="Miga de pan">
        <ol class="breadcrumb app-header__breadcrumb mb-0">
          <?php foreach ($breadcrumbs as $i => $crumb): ?>
            <?php $esUltimo = ($i === array_key_last($breadcrumbs)); ?>
            <li class="breadcrumb-item<?php echo $esUltimo ? ' active' : ''; ?>">
              <?php if (!$esUltimo && !empty($crumb['href'])): ?>
                <a href="<?php echo htmlspecialchars($crumb['href']); ?>">
                  <?php echo htmlspecialchars($crumb['label']); ?>
                </a>
              <?php else: ?>
                <?php echo htmlspecialchars($crumb['label']); ?>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ol>
      </nav>
    <?php endif; ?>
  </div>

  <div class="app-header__right">
    <?php if ($tieneNotificaciones): ?>
    <div class="dropdown">
      <button
        type="button"
        class="app-header__notify"
        id="notifyDropdown"
        data-bs-toggle="dropdown"
        aria-expanded="false"
        aria-label="Notificaciones"
      >
        <i class="bi bi-bell" aria-hidden="true"></i>
        <span class="app-header__notify-dot d-none" id="notifyDot" aria-hidden="true"></span>
        <span class="app-header__notify-count d-none" id="notifyCount">0</span>
      </button>
      <div class="dropdown-menu dropdown-menu-end notify-dropdown shadow" aria-labelledby="notifyDropdown">
        <div class="notify-dropdown__head">
          <div class="d-flex align-items-center gap-2">
            <span class="notify-dropdown__title">Notificaciones</span>
            <span class="badge bg-danger-subtle text-danger rounded-pill d-none" id="notifyDropdownCount">0</span>
          </div>
          <button type="button" class="btn btn-link btn-sm p-0 notify-dropdown__mark-all text-decoration-none" id="notifyMarkAll">Marcar todas</button>
        </div>
        <div class="notify-dropdown__list" id="notifyList">
          <div class="notify-dropdown__empty text-secondary small p-3">Sin notificaciones</div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <div class="dropdown">
      <button
        type="button"
        class="app-header__profile"
        id="profileDropdown"
        data-bs-toggle="dropdown"
        aria-expanded="false"
        aria-label="Menú de perfil"
      >
        <div class="app-header__profile-info">
          <div class="app-header__profile-name"><?php echo htmlspecialchars($nombreUsuario); ?></div>
          <div class="app-header__profile-role"><?php echo htmlspecialchars($rolEtiqueta); ?></div>
        </div>
        <?php
          $avatarSize = 'sm';
          $avatarTone = 'dark';
          $avatarInitials = $iniciales;
          include __DIR__ . '/../components/avatar.php';
        ?>
      </button>
      <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDropdown">
        <li>
          <span class="dropdown-item-text small text-secondary d-md-none">
            <?php echo htmlspecialchars($nombreUsuario); ?><br>
            <?php echo htmlspecialchars($rolEtiqueta); ?>
          </span>
        </li>
        <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>perfil/index"><i class="bi bi-person me-2" aria-hidden="true"></i>Mi perfil</a></li>
        <li><hr class="dropdown-divider"></li>
        <li>
          <a class="dropdown-item text-danger" href="<?php echo BASE_URL; ?>auth/logout">
            <i class="bi bi-power me-2" aria-hidden="true"></i>Cerrar sesión
          </a>
        </li>
      </ul>
    </div>
  </div>
</header>
