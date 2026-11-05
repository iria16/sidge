<?php
/**
 * Vista: login.php
 * Ruta: app/Views/auth/login.php
 * Pantalla de inicio de sesión (split screen). El <form> envía a
 * auth/authenticate (AuthController); el JS/CSS aquí es puramente de
 * presentación. El cascarón lo provee layouts/auth.php.
 */

$pageTitle       = 'Iniciar Sesión';
$pageDescription = 'Acceso al sistema de gestión escolar SIGDE - E.B. Isidro Ramírez.';
$authTagline     = 'Excelencia en gestión académica para la formación del futuro';
$authFeatures    = [
  ['icon' => 'bi-people',         'label' => 'Personal'],
  ['icon' => 'bi-mortarboard',    'label' => 'Estudiantes'],
  ['icon' => 'bi-clipboard-data', 'label' => 'Reportes'],
];
$authCardAriaLabelledby = 'loginTitle';

ob_start();
?>
<h1 id="loginTitle" class="auth-card__title">Iniciar Sesión</h1>
<p class="auth-card__subtitle text-support">
  Bienvenido al ecosistema SIGDE. Por favor, identifíquese para continuar.
</p>

<?php /* autocomplete="off" en el formulario y "new-password" en la contraseña
         impiden que el navegador guarde o rellene la clave y que muestre su
         propio botón de revelado. El campo de usuario conserva
         autocomplete="username". */ ?>
<form novalidate class="login-form needs-validation" autocomplete="off" method="post" action="<?php echo BASE_URL; ?>auth/authenticate">
  <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrfToken()); ?>">

  <div class="mb-3">
    <label for="usuario" class="label-sigde">Usuario</label>
    <div class="input-icon-group">
      <i class="bi bi-person" aria-hidden="true"></i>
      <input
        type="text"
        class="form-control"
        id="usuario"
        name="usuario"
        placeholder="Ingrese su USUARIO"
        value="<?php echo htmlspecialchars($_SESSION['last_usuario'] ?? ''); ?>"
        autocomplete="username"
        maxlength="20"
        required
        aria-describedby="usuarioError"
      >
    </div>
    <div id="usuarioError" class="invalid-feedback">
      Ingrese su usuario para continuar.
    </div>
  </div>

  <div class="mb-2">
    <div class="d-flex justify-content-between align-items-center">
      <label for="password" class="label-sigde mb-0">Contraseña</label>
      <a href="<?php echo BASE_URL; ?>auth/recoverAccess" class="link-sigde">¿Olvidó su contraseña?</a>
    </div>
    <div class="input-icon-group mt-1">
      <i class="bi bi-lock" aria-hidden="true"></i>
      <input
        type="password"
        class="form-control"
        id="password"
        name="password"
        placeholder="••••••••••"
        autocomplete="new-password"
        maxlength="20"
        required
        data-password-field
        aria-describedby="passwordError"
      >
      <button
        type="button"
        class="input-icon-group__toggle"
        data-password-toggle
        aria-label="Mostrar contraseña"
      >
        <i class="bi bi-eye" aria-hidden="true"></i>
      </button>
    </div>
    <div id="passwordError" class="invalid-feedback">
      Ingrese su contraseña para continuar.
    </div>
  </div>

  <button type="submit" class="btn btn-primary w-100 mt-4 text-uppercase" disabled>
    Ingresar al Sistema
    <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i>
  </button>
</form>

<p class="text-support text-center mt-4 mb-0" style="font-size: 0.78rem;">
  El acceso al sistema es otorgado por el administrador institucional
</p>
<?php
$pageContent = ob_get_clean();
$extraScripts = ['/js/auth/login.js'];
include __DIR__ . '/../layouts/auth.php';