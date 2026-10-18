<?php
/**
 * Comprueba que cada recurso que el layout pide exista en disco.
 *
 *   php scripts/verificar-assets.php        (o: npm run verificar)
 *
 * El layout arma sus rutas con <?php echo $assetsPath, '...' ?>, así que no hay forma
 * fácil de saber a mano si algo falta: la página carga, responde 200 y simplemente
 * enseña una pantalla sin iconos. Eso es exactamente lo que pasó con la fuente de
 * bootstrap-icons, y se discovered mirando una carpeta, no leyendo el código.
 *
 * Aquí se listan todas las rutas que head.php y scripts.php piden y se avisa de las que
 * no están. No adivina nada: solo resuelve lo que el layout declara.
 */
declare(strict_types=1);

/* El script vive en scripts/, así que la raíz del proyecto es un nivel arriba. */
$raiz = dirname(__DIR__);
$public = $raiz . '/public';
$assets = $public . '/assets';

/* Rutas que el layout construye a mano, fuera de un <?php echo ?> literal. */
$patrones = [
    '/vendor/bootstrap-icons/bootstrap-icons.css',
    '/js/vendor/bootstrap.bundle.min.js',
    '/js/vendor/sweetalert2.all.min.js',
    '/js/core/sigde-ui.js',
    '/js/core/wizard-ui.js',
    '/js/core/main.js',
    '/css/app.css',
];

/* Scripts de módulo: los declara cada vista en $extraScripts. */
/* glob() no baja solo: '**' no es recursivo en PHP. Se recorre el árbol a mano. */
$modulos = [];
$iter = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($raiz . '/app/Views', FilesystemIterator::SKIP_DOTS)
);
foreach ($iter as $v) {
    if ($v->getExtension() !== 'php') {
        continue;
    }
    foreach (file($v->getPathname(), FILE_IGNORE_NEW_LINES) ?: [] as $linea) {
        /* Se descartan las líneas de comentario: en scripts.php hay un ejemplo escrito
           como ej. ['/js/usuarios.js'] que es documentación, no una referencia, y sin
           este filtro la comprobación pedía un archivo que nunca existió. */
        $t = ltrim($linea);
        if ($t === '' || $t[0] === '*' || $t[0] === '/' || $t[0] === '#') {
            continue;
        }
        /* Desde que el JavaScript está organizado en carpetas (core/, auth/,
           modules/<modulo>/) la ruta lleva subdirectorios: '/js/modules/
           empleados/empleado-alta.js'. El patrón tiene que admitirlos o la
           comprobación dejaría de mirar esos archivos en silencio, que es
           exactamente el fallo invisible que este script existe para evitar.

           preg_match_all y no preg_match: varias vistas declaran los tres
           scripts del módulo en la MISMA línea
           ($extraScripts = ['/js/.../familiares.js', '/js/.../enrollments.js']).
           Con preg_match solo se registraba el primero y los otros dos nunca
           se comprobaban: un archivo faltante pasaba el control sin avisar.*/
        if (preg_match_all("#'(/js/[a-z0-9-]+(?:/[a-z0-9-]+)*\.js)'#i", $linea, $m)) {
            foreach ($m[1] as $ruta) {
                $modulos[$ruta] = true;
            }
        }
    }
}
ksort($modulos);

$faltan = [];
$total = 0;

echo "  Recursos del layout\n";
foreach ($patrones as $r) {
    $total++;
    $r = '/' . ltrim($r, '/');
    $ruta = $assets . $r;
    $ok = is_file($ruta);
    printf("    %-42s %s\n", $r, $ok ? 'ok' : 'FALTA');
    if (!$ok) {
        $faltan[] = $r;
    }
}

echo "  Scripts de módulo (" . count($modulos) . ")\n";
foreach (array_keys($modulos) as $r) {
    $total++;
    /* Las vistas declaran '/js/x.js' y el layout '/js/x.js', pero el @import de SCSS
       llega sin la barra inicial. Sin normalizar, concatenar con $assets pegaba
       "assets" y "js/x.js" y daba una ruta que no existe. */
    $r = '/' . ltrim($r, '/');
    $ruta = $assets . $r;
    $ok = is_file($ruta);
    printf("    %-42s %s\n", $r, $ok ? 'ok' : 'FALTA');
    if (!$ok) {
        $faltan[] = $r;
    }
}

/* Los iconos que el CSS de bootstrap-icons declara tienen que existir de verdad: el CSS
   se sirve igual aunque la fuente falte, y eso es lo que hace el fallo invisible. */
echo "  Fuentes de iconos\n";
$css = $assets . '/vendor/bootstrap-icons/bootstrap-icons.css';
foreach (['fonts/bootstrap-icons.woff2', 'fonts/bootstrap-icons.woff'] as $f) {
    $total++;
    $ruta = $assets . '/vendor/bootstrap-icons/' . $f;
    $ok = is_file($ruta);
    printf("    %-42s %s\n", $f, $ok ? 'ok' : 'FALTA');
    if (!$ok) {
        $faltan[] = 'vendor/bootstrap-icons/' . $f;
    }
}

echo "\n";
if ($faltan === []) {
    printf("  -> %d recursos, todos presentes\n", $total);
    exit(0);
}

printf("  -> %d de %d recursos FALTAN:\n", count($faltan), $total);
foreach ($faltan as $f) {
    echo "       " . $f . "\n";
}
echo "\n  Se arregla con: npm run build\n";
exit(1);