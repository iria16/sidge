<?php
/**
 * Verificador: el menú lateral y los permisos de ruta.
 *
 * Qué detecta, y por qué hace falta:
 *
 * El menú de app/Views/helpers/ui.php declara, por sección y por ítem, los roles
 * que ven cada enlace. La barrera real está en public/index.php, en
 * $rutasPermitidas. Son dos listas escritas a mano en archivos distintos, y
 * cuando se separan el síntoma es que el usuario ve el enlace, pulsa, y recibe
 * un die("<h1>403 - Acceso denegado</h1>") pelado: el enlace solo desaparece de
 * la lista de permisos, pero la sesión ya lo había autorizado.
 *
 * Un rol puede ver en el menú algo que la ruta no abre (403), y un enlace
 * puede apuntar a una ruta que no existe (404). Los dos son falloscertainos.
 *
 * Los href="#" no son fallo: son funciones planificadas. Se informan aparte
 * para que no se pierdan de vista, pero no hacen fallar la verificación.
 *
 * Uso:  php scripts/verificar-menu.php
 * Salida: 0 si el menú y las rutas coinciden, 1 si hay algún 403/404.
 */

// ---------------------------------------------------------------- rutas ---
$raiz          = dirname(__DIR__);
$rutaFront     = $raiz . '/public/index.php';
$rutaHelperNav = $raiz . '/app/Views/helpers/ui.php';

foreach ([$rutaFront, $rutaHelperNav] as $ruta) {
    if (!is_file($ruta)) {
        fwrite(STDERR, "  No se encuentra: {$ruta}\n");
        exit(2);
    }
}

/**
 * Extrae el literal del array $rutasPermitidas sin ejecutarlo.
 *
 * No se puede hacer `include public/index.php` porque ese archivo arranca la
 * aplicación: enruta, valida sesión y llama al controlador. Se recorta el
 * literal cuento corchetes por fuera, respetando las comillas simples, y se
 * evalúa solo ese trozo: es un array de literales, sin llamadas.
 */
function extraerRutasPermitidas(string $archivo): array
{
    $src    = (string) file_get_contents($archivo);
    $marca  = strpos($src, '$rutasPermitidas');
    $inicio = $marca === false ? false : strpos($src, '[', $marca);

    if ($inicio === false) {
        fwrite(STDERR, "  No se encuentra \$rutasPermitidas en {$archivo}\n");
        exit(2);
    }

    $profundo = 0;
    $enComilla = false;
    $escapada = false;
    $fin = $inicio;

    for ($i = $inicio, $largo = strlen($src); $i < $largo; $i++) {
        $c = $src[$i];

        if ($enComilla) {
            if ($escapada) {
                $escapada = false;
            } elseif ($c === '\\') {
                $escapada = true;
            } elseif ($c === "'") {
                $enComilla = false;
            }
            continue;
        }

        if ($c === "'") {
            $enComilla = true;
        } elseif ($c === '[') {
            $profundo++;
        } elseif ($c === ']') {
            $profundo--;
            if ($profundo === 0) {
                $fin = $i + 1;
                break;
            }
        }
    }

    $literal = substr($src, $inicio, $fin - $inicio);

    try {
        $rutas = eval('return ' . $literal . ';');
    } catch (\ParseError $e) {
        fwrite(STDERR, "  \$rutasPermitidas no se pudo leer: {$e->getMessage()}\n");
        exit(2);
    }

    return is_array($rutas) ? $rutas : [];
}

$rutasPermitidas = extraerRutasPermitidas($rutaFront);

// ----------------------------------------------------------------- menú ---
// ui.php arma los href con BASE_URL, así que tiene que existir antes.
if (!defined('BASE_URL')) {
    define('BASE_URL', '/');
}
require_once $rutaHelperNav;

if (!function_exists('sigde_nav_sections')) {
    fwrite(STDERR, "  sigde_nav_sections() no está definida en {$rutaHelperNav}\n");
    exit(2);
}

/* Los roles se sacan de las propias rutas, no de una lista fija: si mañana se
   añade uno nuevo al front controller, este verificador lo revisa solo. */
$roles = [];
foreach ($rutasPermitidas as $acciones) {
    foreach ($acciones as $permitidos) {
        foreach ((array) $permitidos as $rol) {
            $rol = (string) $rol;
            if ($rol !== '' && $rol !== 'publico') {
                $roles[$rol] = true;
            }
        }
    }
}
$roles = array_keys($roles);
sort($roles);

// ------------------------------------------------------------ ejecución ---
echo "  Menu lateral contra permisos de ruta\n";

$errores  = 0;
$avisos   = [];
$revisados = 0;

foreach ($roles as $rol) {
    $secciones = sigde_nav_sections($rol);

    /* De qué controladores le ofrece el menú enlaces a este rol. Se usa después
       para el chequeo inverso: rutas que abren a un módulo que su menú no ofrece. */
    $enMenu = [];
    foreach ($secciones as $seccion) {
        foreach ($seccion['items'] as $item) {
            $ruta = (string) parse_url((string) ($item['href'] ?? ''), PHP_URL_PATH);
            $partes = array_values(array_filter(explode('/', (string) $ruta), static fn($p) => $p !== ''));
            if (($partes[0] ?? '') !== '') {
                $enMenu[$partes[0]] = true;
            }
        }
    }

    printf("\n  %s\n", $rol);
    if ($secciones === []) {
        printf("    (sin secciones visibles)\n");
        continue;
    }

    foreach ($secciones as $seccion) {
        foreach ($seccion['items'] as $item) {
            $revisados++;

            $href  = (string) ($item['href'] ?? '');
            $rotulo = (string) ($item['label'] ?? $item['id'] ?? '?');

            if ($href === '' || $href === '#') {
                $avisos[] = sprintf('%s → "%s": enlace sin destino (href vacío)', $rotulo, $item['id'] ?? '?');
                printf("    %-30s %s\n", $rotulo, 'sin destino');
                continue;
            }

            /* El href viene como BASE_URL . 'controlador/accion'. No se quita el
               prefijo con str_replace porque BASE_URL es '/' y se comería también
               las barras internas: 'inscripciones/index' se volvía
               'inscripcionesindex'. Se toma la ruta y se recorta el prefijo. */
            $ruta = (string) parse_url($href, PHP_URL_PATH);
            if ($ruta !== '' && $ruta[0] !== '/') {
                $ruta = '/' . $ruta;
            }
            $partes = array_values(array_filter(explode('/', $ruta), static fn($p) => $p !== ''));
            $controlador = $partes[0] ?? '';
            $accion      = $partes[1] ?? 'index';

            if (!isset($rutasPermitidas[$controlador][$accion])) {
                printf("    %-30s %s\n", $rotulo, '404 - la ruta ' . $controlador . '/' . $accion . ' no existe');
                $errores++;
                continue;
            }

            $permitidos = (array) $rutasPermitidas[$controlador][$accion];
            if (in_array('publico', $permitidos, true)) {
                printf("    %-30s %s\n", $rotulo, 'ok (pública)');
                continue;
            }

            if (!in_array($rol, $permitidos, true)) {
                printf(
                    "    %-30s %s\n",
                    $rotulo,
                    '403 - la ruta ' . $controlador . '/' . $accion . ' solo abre a [' . implode(', ', $permitidos) . ']'
                );
                $errores++;
                continue;
            }

            printf("    %-30s %s\n", $rotulo, 'ok');
        }
    }

    /* Chequeo inverso: rutas que abren a este rol un módulo que su menú no le
       ofrece. Con el menú se tapa el acceso, no la autorización: el enlace no
       aparece, pero escribir la URL a mano entra igual. Sucedió con academicYear para el admin. Se informa y no falla, porque puede ser
       legítimo: un módulo al que se llega desde el detalle de otro y no desde
       el menú. 'auth' queda fuera a propósito: cerrar sesión y cambiar clave los
       tiene todo el mundo y no son un módulo de navegación. */
    foreach ($rutasPermitidas as $controlador => $acciones) {
        if ($controlador === 'auth' || $controlador === 'perfil' || isset($enMenu[$controlador])) {
            continue;
        }
        foreach ($acciones as $accion => $permitidos) {
            if (in_array('publico', (array) $permitidos, true)) {
                continue;
            }
            if (in_array($rol, (array) $permitidos, true)) {
                $avisos[] = sprintf(
                    '%s puede abrir %s/%s, y su menú no le ofrece ese módulo',
                    $rol,
                    $controlador,
                    $accion
                );
                break;
            }
        }
    }
}

// --------------------------------------------------------------- salida ---
if ($avisos !== []) {
    printf("\n  Avisos (no fallan la verificación):\n");
    foreach ($avisos as $aviso) {
        printf("    - %s\n", $aviso);
    }
}

printf("\n  %d enlaces revisados en %d roles | %d con error\n", $revisados, count($roles), $errores);

if ($errores > 0) {
    printf("\n  Un enlace que el menú ofrece y la ruta no abre vuelve como\n");
    printf("  403 al pulsarlo, y uno que apunta a una ruta inexistente, como 404.\n");
    printf("  Lo que hay que corregir es el menú: app/Views/helpers/ui.php,\n");
    printf("  en el 'roles' del item o de su sección.\n");
    exit(1);
}

echo "\n  El menu y las rutas coinciden.\n";
exit(0);