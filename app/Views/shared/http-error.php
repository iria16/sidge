<?php
/**
 * Vista compartida de errores HTTP (400, 403, 404, 422, ...).
 *
 * La usan SecurityTrait::renderHttpError() y prohibido(). Sustituye los
 * `echo '<h1>...'` que había repartidos por los controladores: el HTML de error
 * deja de vivir en la capa de lógica.
 *
 * @var string $errorTitulo   Título corto, p. ej. "403 · Acceso denegado".
 * @var string $errorMensaje  Explicación para el usuario.
 */
$pageTitle       = $errorTitulo;
$pageDescription = $errorMensaje;
$assetsPath      = BASE_URL . 'assets';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php require __DIR__ . '/../partials/head.php'; ?>
</head>
<body>
<main class="container py-5 text-center">
    <h1 class="text-danger"><?php echo htmlspecialchars($errorTitulo); ?></h1>
    <p class="text-muted"><?php echo htmlspecialchars($errorMensaje); ?></p>
    <a href="<?php echo BASE_URL; ?>" class="btn btn-primary mt-3">Volver al inicio</a>
</main>
</body>
</html>
