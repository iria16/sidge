<?php
declare(strict_types=1);

/**
 * Vista: perfil/index.php
 * Módulo nativo de Perfil de Usuario en SIGDE (solo lectura).
 *
 * @var array<string, mixed>                          $perfil
 * @var string                                        $pageTitle
 * @var string                                        $pageDescription
 * @var string                                        $currentNav
 * @var list<array{label: string, href: string|null}> $breadcrumbs
 */

$activo   = ($perfil['estado_usuario'] ?? 'ACTIVO') === 'ACTIVO';
$nombre   = (string) ($perfil['nombre_completo'] ?? 'Usuario');
$username = (string) ($perfil['nombre_usuario'] ?? '');
$rol      = (string) ($perfil['rol_nombre'] ?? 'Usuario');
$cargo    = (string) ($perfil['cargo'] ?? 'Personal Institucional');
$cedula   = (string) ($perfil['cedula'] ?? '—');
$correo   = !empty($perfil['correo_electronico']) && $perfil['correo_electronico'] !== '—'
    ? (string) $perfil['correo_electronico']
    : 'No registrado';
$telefono = !empty($perfil['telefono_principal']) && $perfil['telefono_principal'] !== '—'
    ? (string) $perfil['telefono_principal']
    : 'No registrado';
$acceso   = (string) ($perfil['ultimo_acceso'] ?? 'Sin registros');

ob_start();
?>
<div class="container-fluid px-0">

  <!-- Encabezado estándar de módulo SIGDE -->
  <?php
  $pageHeading       = 'Mi Perfil';
  $pageSubheading    = 'Consulta tu foto de perfil, datos personales y credenciales de acceso registradas.';
  $pageHeaderActions = '';
  include __DIR__ . '/../components/page-header.php';
  ?>

  <div class="row g-4">

    <!-- Columna 1: Tarjeta de Foto y Resumen de Perfil -->
    <div class="col-12 col-md-5 col-lg-4">
      <div class="card border-0 shadow-sm rounded-3 text-center p-4 h-100">
        
        <!-- Foto de Perfil con Estilo Nativo SIGDE y Botón de Edición -->
        <div class="d-flex flex-column align-items-center mb-3">
          <label class="wizard-photo-upload__circle" for="inputFotoPerfil" role="button" title="Cambiar foto de perfil">
            <div class="wizard-photo-upload__preview" id="perfilFotoPreview">
              <?php if (!empty($perfil['avatar_foto'])): ?>
                <img src="<?= htmlspecialchars(BASE_URL . $perfil['avatar_foto']) ?>" alt="<?= htmlspecialchars($nombre) ?>" id="imgPerfilFoto">
              <?php else: ?>
                <i class="bi bi-person" id="iconoPerfilFallback"></i>
              <?php endif; ?>
            </div>
            <span class="wizard-photo-upload__edit">
              <i class="bi bi-pencil-fill"></i>
            </span>
            <input type="file" id="inputFotoPerfil" name="foto" accept="image/jpeg,image/png,image/webp" class="visually-hidden">
          </label>
          <label for="inputFotoPerfil" class="wizard-photo-upload__hint mt-2" role="button" style="cursor: pointer;">
            <i class="bi bi-camera me-1"></i>Foto de perfil (Opcional)
          </label>
          <button type="button" class="btn btn-link btn-sm text-danger p-0 mt-1 <?= empty($perfil['avatar_foto']) ? 'd-none' : '' ?>" id="btnQuitarFotoPerfil">
            <i class="bi bi-x-circle me-1"></i>Quitar foto
          </button>
        </div>

        <!-- Nombre e Identificador -->
        <h2 class="h5 fw-bold text-dark mb-1"><?= htmlspecialchars($nombre) ?></h2>
        <p class="text-secondary small font-monospace mb-2"><?= htmlspecialchars($username) ?></p>

        <!-- Badge de Rol -->
        <div class="mb-3">
          <span class="badge bg-primary-subtle text-primary fw-semibold px-3 py-1 rounded-pill">
            <?= htmlspecialchars($rol) ?>
          </span>
        </div>

        <!-- Metadatos de Cuenta -->
        <div class="pt-3 border-top text-start">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="text-secondary small">Estado</span>
            <?php
            $badgeLabel  = $activo ? 'Activo' : 'Inactivo';
            $badgeStatus = $activo ? 'active' : 'inactive';
            include __DIR__ . '/../components/status-badge.php';
            ?>
          </div>

          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="text-secondary small">Cédula</span>
            <span class="text-dark small fw-medium"><?= htmlspecialchars($cedula) ?></span>
          </div>

          <div class="d-flex justify-content-between align-items-center">
            <span class="text-secondary small">Miembro desde</span>
            <span class="text-dark small fw-medium"><?= htmlspecialchars((string) ($perfil['fecha_creacion'] ?? '—')) ?></span>
          </div>
        </div>

      </div>
    </div>

    <!-- Columna 2: Ficha de Datos Básicos -->
    <div class="col-12 col-md-7 col-lg-8">
      <div class="card border-0 shadow-sm rounded-3 h-100">
        <div class="card-header bg-white border-bottom p-3">
          <h3 class="h6 fw-bold mb-0 text-dark d-flex align-items-center gap-2">
            <i class="bi bi-person-vcard text-primary fs-5" aria-hidden="true"></i>
            <span>Datos Básicos</span>
          </h3>
        </div>

        <div class="card-body p-4">
          <div class="row g-4">
            
            <div class="col-12 col-sm-6">
              <label class="form-label text-secondary small fw-semibold text-uppercase mb-1">Nombre Completo</label>
              <p class="fs-6 fw-medium text-dark mb-0"><?= htmlspecialchars($nombre) ?></p>
            </div>

            <div class="col-12 col-sm-6">
              <label class="form-label text-secondary small fw-semibold text-uppercase mb-1">Cédula de Identidad</label>
              <p class="fs-6 fw-medium text-dark mb-0"><?= htmlspecialchars($cedula) ?></p>
            </div>

            <div class="col-12 col-sm-6">
              <label class="form-label text-secondary small fw-semibold text-uppercase mb-1">Cargo Institucional</label>
              <p class="fs-6 fw-medium text-dark mb-0"><?= htmlspecialchars($cargo) ?></p>
            </div>

            <div class="col-12 col-sm-6">
              <label class="form-label text-secondary small fw-semibold text-uppercase mb-1">Rol en el Sistema</label>
              <p class="fs-6 fw-medium text-dark mb-0"><?= htmlspecialchars($rol) ?></p>
            </div>

            <div class="col-12 col-sm-6">
              <label class="form-label text-secondary small fw-semibold text-uppercase mb-1">Correo Electrónico</label>
              <p class="fs-6 fw-medium text-dark mb-0">
                <?php if ($correo !== 'No registrado'): ?>
                  <a href="mailto:<?= htmlspecialchars($correo) ?>" class="text-primary text-decoration-none">
                    <i class="bi bi-envelope me-1"></i><?= htmlspecialchars($correo) ?>
                  </a>
                <?php else: ?>
                  <span class="text-secondary">No registrado</span>
                <?php endif; ?>
              </p>
            </div>

            <div class="col-12 col-sm-6">
              <label class="form-label text-secondary small fw-semibold text-uppercase mb-1">Teléfono</label>
              <p class="fs-6 fw-medium text-dark mb-0">
                <?php if ($telefono !== 'No registrado'): ?>
                  <i class="bi bi-telephone me-1 text-primary"></i><?= htmlspecialchars($telefono) ?>
                <?php else: ?>
                  <span class="text-secondary">No registrado</span>
                <?php endif; ?>
              </p>
            </div>

            <div class="col-12">
              <hr class="text-muted my-1">
            </div>

            <div class="col-12 col-sm-6">
              <label class="form-label text-secondary small fw-semibold text-uppercase mb-1">Nombre de Usuario</label>
              <p class="fs-6 font-monospace text-primary fw-medium mb-0"><?= htmlspecialchars($username) ?></p>
            </div>

            <div class="col-12 col-sm-6">
              <label class="form-label text-secondary small fw-semibold text-uppercase mb-1">Último Acceso</label>
              <p class="fs-6 text-dark mb-0">
                <i class="bi bi-clock-history me-1 text-secondary"></i>
                <?= htmlspecialchars($acceso) ?>
              </p>
            </div>

          </div>
        </div>

      </div>
    </div>

  </div>

</div>
<?php
$pageContent = ob_get_clean();

$extraScripts = ['/js/modules/perfil/perfil.js'];

require __DIR__ . '/../layouts/app.php';
