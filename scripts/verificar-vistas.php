<?php
/**
 * Verificador de vistas y componentes PHP.
 * ---------------------------------------------------------------------
 * Revisa los .php de app/Views con dos reglas que nacieron de romper
 * pantallas de verdad en este proyecto:
 *
 *  1. `?>` dentro de un comentario de una línea.
 *     En PHP, `?>` CIERRA el modo PHP aunque esté dentro de un `//` o un
 *     `#`. Lo que viene después se imprime como texto. Es un error que
 *     `php -l` no marca: el archivo sigue siendo válido.
 *
 *  2. Código PHP fuera de un bloque.
 *     Una línea `?>` seguida de otra que empieza por `$variable =` o por
 *     `include` significa que el bloque se cerró antes de tiempo: esas
 *     líneas se van a imprimir en pantalla en vez de ejecutarse. Es lo que
 *     dejó el encabezado de las constancias sin renderizar.
 *
 * Con `--html <archivo>` revisa además el balance de etiquetas, pero SOLO
 * sobre HTML ya renderizado. No lo hace sobre el fuente porque allí las ramas
 * de PHP y los includes hacen que el conteo mienta.
 *
 * Uso:
 *   php scripts/verificar-vistas.php
 *   php scripts/verificar-vistas.php --html render.html otro.html
 */

declare(strict_types=1);

const RAIZ = __DIR__ . '/..';

/** Etiquetas cuyo desbalance suele ser un error de verdad. */
const ESTRUCTURA = ['div', 'td', 'tr', 'table', 'form'];

/**
 * Archivos con diferencia de etiquetas conocida y sin efecto.
 *
 * Cada uno dice por qué. Si alguno se arregla, se borra de aquí.
 */
const IGNORADOS = [
    // Nunca se renderizan: son plantillas de fragmento que recibe el layout.
    'Views/partials/pdf-membrete.php' => 'membrete del PDF, no es HTML de pantalla',
];

function vistas(string $raiz): array
{
    $base = "$raiz/app/Views";
    if (!is_dir($base)) {
        return [];
    }

    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
    );

    $archivos = [];
    foreach ($iter as $archivo) {
        if ($archivo->isFile() && strtolower($archivo->getExtension()) === 'php') {
            $archivos[] = $archivo->getPathname();
        }
    }

    sort($archivos);

    return $archivos;
}

/**
 * @return array<int, array{regla:string, linea:int, texto:string}>
 */
function revisar(string $ruta, string $contenido): array
{
    $lineas  = explode("\n", $contenido);
    $hallazgos = [];

    foreach ($lineas as $n => $linea) {
        // Regla 1: cierre de PHP dentro de un comentario de una línea.
        /* Delimitador ~ para no pelear con las barras y las almohadillas. */
        if (preg_match('~^\s*(//|\#).*\?>~', $linea)) {
            $hallazgos[] = [
                'regla' => '?> dentro de un comentario de una línea cierra el PHP',
                'linea' => $n + 1,
                'texto' => trim($linea),
            ];
        }
    }

    // Regla 2: código PHP fuera de un bloque. Se mira la línea siguiente a
    // cada cierre: si empieza por código, el bloque terminó antes de tiempo.
    foreach ($lineas as $n => $linea) {
        if (strpos($linea, '?>') === false) {
            continue;
        }
        $siguiente = $lineas[$n + 1] ?? null;
        if ($siguiente === null) {
            continue;
        }
        if (preg_match('/^\s*(\$[a-zA-Z_]|\$this|include\b|require\b|echo\b|print\b)/', $siguiente)) {
            $hallazgos[] = [
                'regla' => 'código PHP fuera del bloque: el ?> de arriba lo cerró antes de tiempo',
                'linea' => $n + 2,
                'texto' => trim($siguiente),
            ];
        }
    }

    /* Regla 3: búferes de salida descuadrados.
       Un ob_start() sin su ob_get_clean() hace que el ob_get_clean() final se
       quede con el búfer equivocado: la página se imprime ANTES del <!DOCTYPE>
       y el layout queda sin contenido. Rompe el documento entero, y
       `php -l` y el conteo de etiquetas no lo detectan. */
    /* Se cuenta solo el código: los comentarios Explain el ob_get_clean() que
       se está buscando y no deben contar como llamadas. */
    $codigo = preg_replace(['#/\*.*?\*/#s', '#//[^\n]*#'], ' ', $contenido);
    $abre = preg_match_all('/ob_start\(\)/', (string) $codigo);
    $cierra = preg_match_all('/ob_get_clean\(\)/', (string) $codigo);
    $end = preg_match_all('/ob_end_clean\(\)|ob_end_flush\(\)/', (string) $codigo);
    if ($abre !== $cierra + $end) {
        $hallazgos[] = [
            'regla' => sprintf(
                'búferes descuadrados: %d ob_start() y %d ob_get_clean()/ob_end (faltan %d)',
                $abre,
                $cierra + $end,
                $abre - $cierra - $end
            ),
            'linea' => 0,
            'texto' => '',
        ];
    }

    return $hallazgos;
}

/* ── Modo --html: balance de etiquetas sobre HTML YA RENDERIZADO ────────
   El conteo no sirve sobre el fuente de una vista: los formularios se abren en
   una rama de PHP y se cierran en otra, y los componentes se incluyen aparte.
   Sobre el HTML que el navegador recibe sí sirve, y ahí es donde se detectaron
   el <td> sin abrir y el </div> huérfano. */
if (in_array('--html', $argv, true)) {
    $archivosHtml = array_slice($argv, array_search('--html', $argv, true) + 1);
    if ($archivosHtml === []) {
        fwrite(STDERR, "  --html necesita al menos un archivo renderizado\n");
        exit(2);
    }

    $problemas = 0;
    foreach ($archivosHtml as $archivoHtml) {
        $html = is_file($archivoHtml) ? (string) file_get_contents($archivoHtml) : '';
        if ($html === '') {
            printf("  !! %s: vacio o inexistente\n", $archivoHtml);
            $problemas++;
            continue;
        }
        $malos = [];
        foreach (ESTRUCTURA as $etiqueta) {
            $abren = preg_match_all('/<' . $etiqueta . '\b/i', $html);
            $cierran = preg_match_all('|</' . $etiqueta . '>|i', $html);
            if ($abren !== $cierran) {
                $malos[] = sprintf('<%s> %d/%d', $etiqueta, $abren, $cierran);
            }
        }
        if ($malos !== []) {
            printf("  !! %s\n", basename($archivoHtml));
            foreach ($malos as $m) {
                printf("       %s\n", $m);
            }
            $problemas++;
        } else {
            printf("  ok  %s\n", basename($archivoHtml));
        }
    }

    printf("\n  archivos renderizados con desbalance: %d\n", $problemas);
    exit($problemas > 0 ? 1 : 0);
}

$raiz = realpath(RAIZ) ?: RAIZ;
$archivos = vistas($raiz);

printf("  vistas revisadas: %d\n\n", count($archivos));

$conHallazgos = 0;
$ignorados   = 0;

foreach ($archivos as $ruta) {
    $relativo  = str_replace("$raiz/", '', $ruta);
    $contenido = (string) file_get_contents($ruta);

    if (isset(IGNORADOS[$relativo])) {
        $ignorados++;
        continue;
    }

    $hallazgos = revisar($ruta, $contenido);
    if ($hallazgos === []) {
        continue;
    }

    $conHallazgos++;
    printf("  !! %s\n", $relativo);
    foreach ($hallazgos as $h) {
        if ($h['linea'] > 0) {
            printf("       L%-4d %s\n           %s\n", $h['linea'], $h['regla'], substr($h['texto'], 0, 78));
        } else {
            printf("       %s\n", $h['regla']);
        }
    }
}

printf("\n  vistas con hallazgos: %d | ignoradas a proposito: %d\n", $conHallazgos, $ignorados);

if ($conHallazgos === 0) {
    echo "\n  Ninguna vista imprime codigo PHP fuera de bloque ni tiene etiquetas descuadradas.\n";
    exit(0);
}

echo "\n  Las dos reglas son errores certainos: rompen la pantalla. Para el balance\n";
echo "  de etiquetas usa el modo --html sobre el HTML ya renderizado.\n";

exit(in_array('--salir-con-error', $argv, true) ? 1 : 0);
