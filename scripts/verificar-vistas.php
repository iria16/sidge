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
        $malos  = [];
        $avisos = [];
        foreach (ESTRUCTURA as $etiqueta) {
            $abren = preg_match_all('/<' . $etiqueta . '\b/i', $html);
            $cierran = preg_match_all('|</' . $etiqueta . '>|i', $html);
            if ($abren !== $cierran) {
                $malos[] = sprintf('<%s> %d/%d', $etiqueta, $abren, $cierran);
            }
        }
        foreach (revisarTablas($html) as $aviso) {
            $malos[] = $aviso;
        }
        [$malosModal, $avisosModal] = revisarModales($html);
        foreach ($malosModal as $m) {
            $malos[] = $m;
        }
        foreach ($avisosModal as $a) {
            $avisos[] = $a;
        }
        foreach (revisarIconos($html) as $aviso) {
            $malos[] = $aviso;
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

        foreach ($avisos as $aviso) {
            printf("       aviso: %s\n", $aviso);
        }
    }

    printf("\n  archivos renderizados con desbalance: %d\n", $problemas);
    exit($problemas > 0 ? 1 : 0);
}


/**
 * Comprueba que los modales guarden el mismo esquema.
 *
 * El portal tiene un solo diseño de modal, el de components/modal.php, que se
 * apoya en la clase .app-modal para el aspecto de marca. Un modal escrito a
 * mano sin esa clase sale con el Bootstrap crudo: otro radio, otra sombra, otro
 * color de título y sin el cuerpo con scroll, que es justo lo que hace que dos
 * pantallas del mismo sitio no parezcan las mismas.
 *
 * Solo mira el div raíz del modal (el que lleva .fade o .app-modal), no los
 * internos: modal-dialog, modal-content, modal-header... son sus hijos y
 * aparecerían como falsos positivos.
 *
 * @return array{0: string[], 1: string[]} [errores, avisos].
 */
function revisarModales(string $html): array
{
    $problemas = [];   // errores: hacen salir con codigo 1
    $avisos   = [];   // recomendaciones: se imprimen pero no fallan

    /* Selocalizan primero TODOS los div raiz de modal y luego se recorta entre
       ellos. Buscando el siguiente "class=\"modal" sobre la marcha se cruzaba
       con class="modal-dialog", que empieza igual, y la ventana se quedaba en
       una sola etiqueta: el modal saliaikatoriado como si le faltara todo. */
    preg_match_all(
        '/<div\b[^>]*class="([^"]*)"[^>]*>/i',
        $html,
        $m,
        PREG_OFFSET_CAPTURE
    );

    $sinCentrarGlobales = [];
    $raices = [];
    foreach ($m[1] as $i => $captura) {
        $clase = $captura[0];
        /* .fade a secas es tambien la transicion de Bootstrap (tab-pane fade,
           collapse, alert). Sin exigir la palabra "modal" el veridor senalaba
           cuatro pestanas de una vista como si fueran modales rotos. */
        if (!preg_match('/\bmodal\b/', $clase)) {
            continue;
        }
        if (!preg_match('/\bfade\b/', $clase) && !preg_match('/\bapp-modal\b/', $clase)) {
            continue;
        }
        $raices[] = ['clase' => $clase, 'tag' => $m[0][$i][0], 'ini' => $m[0][$i][1]];
    }

    foreach ($raices as $n => $raiz) {
        $falta       = [];
        $sinCentrar  = [];
        $modalId     = '';
        if (preg_match('/id=\"([^\"]+)\"/', $raiz['tag'], $im)) {
            $modalId = $im[1];
        }
        if (!preg_match('/\bapp-modal\b/', $raiz['clase'])) {
            $falta[] = '.app-modal';
        }
        if (!preg_match('/aria-labelledby=/i', $raiz['tag'])) {
            $falta[] = 'aria-labelledby';
        }

        $desde = $raiz['ini'] + strlen($raiz['tag']);
        $hasta = isset($raices[$n + 1]) ? $raices[$n + 1]['ini'] : min(strlen($html), $desde + 6000);
        $trozo = substr($html, $desde, max(0, $hasta - $desde));

        if (!preg_match('/\bmodal-header\b/', $trozo)) {
            $falta[] = '.modal-header';
        }
        if (!preg_match('/\bmodal-title\b/', $trozo)) {
            $falta[] = '.modal-title';
        }
        if (!preg_match('/\bbtn-close\b/', $trozo)) {
            $falta[] = '.btn-close';
        }
        /* modal-dialog-centered NO es obligatorio: components/modal.php acepta
           $modalCentered = false, y hay modales donde tiene sentido — una vista
           previa de PDF en alto completo, por ejemplo. Exigirlo en el HTML
           contradecía la propia API del componente. */
        if (!preg_match('/\bmodal-dialog-centered\b/', $trozo)) {
            $sinCentrar[] = $modalId;
        }

        if ($falta !== []) {
            $problemas[] = sprintf(
                'modal sin el esquema de components/modal.php, le falta: %s',
                implode(', ', $falta)
            );
        }

        if ($sinCentrar !== []) {
            $sinCentrarGlobales = array_merge($sinCentrarGlobales, $sinCentrar);
        }
    }

    if ($sinCentrarGlobales !== []) {
        $avisos[] = sprintf(
            'modal sin .modal-dialog-centered (puede ser intencionado): %s',
            implode(', ', $sinCentrarGlobales)
        );
    }

    return [$problemas, $avisos];
}


/**
 * Comprueba que cada icono que pide la vista exista de verdad en bootstrap-icons.
 *
 * El nombre de un icono que no existe no da error en ningun sitio: el navegador
 * dibuja un <i> vacio y el hueco aparece en pantalla sin que nada avise. Con
 * 2000 iconos en la libreria y los nombres tomados de memoria, un .bi-arrow-
 * repeat o un .bi-clipboard-check mal escrito son facilisimos de escribir y
 * silenciosos de fallar.
 *
 * Se lee el CSS que se sirve de verdad (public/assets/vendor/bootstrap-icons),
 * no el paquete de npm, porque es el que resuelve el navegador.
 *
 * @return string[] Iconos usados que la libreria no define.
 */
function revisarIconos(string $html): array
{
    $rutaCss = __DIR__ . '/../public/assets/vendor/bootstrap-icons/bootstrap-icons.min.css';
    if (!is_file($rutaCss)) {
        return []; // la libreria no esta desplegada: no hay contra que comparar
    }

    static $cache = null;
    if ($cache === null) {
        $css = (string) file_get_contents($rutaCss);
        preg_match_all('/\.(bi-[a-z0-9-]+)::?before/', $css, $c);
        $cache = array_flip($c[1]);
    }

    preg_match_all('/\b(bi-[a-z0-9-]+)\b/', $html, $u);
    $faltan = [];
    foreach (array_unique($u[1]) as $icono) {
        // bi-<numero> existe en la libreria, pero el patron de nombres alfabetico
        // tambien deja pasar cosas como bi-m4 o similar.
        if (!isset($cache[$icono])) {
            $faltan[] = $icono;
        }
    }
    sort($faltan);

    return array_map(
        static fn(string $i): string => sprintf('.%s no existe en bootstrap-icons', $i),
        $faltan
    );
}


/**
 * Comprueba que cada fila de la tabla tenga las mismas columnas que la cabecera.
 *
 * Cuenta la suma de los colspan de las celdas, no el número de `<td>`: la fila
 * de "no hay resultados" es una sola celda con colspan="6" y es correcta.
 * Solo mira tablas con `<th>` de cabecera; las de maquetación se saltan.
 *
 * @return string[] Problemas encontrados, uno por tabla.
 */
function revisarTablas(string $html): array
{
    $problemas = [];

    // Se recorren las tablas de fuera adentro, saltando las anidadas: el
    // código de una tabla interior no es una tabla propia de la página.
    $pos = 0;
    $total = strlen($html);

    while (($ini = strpos($html, '<table', $pos)) !== false) {
        $prof = 0;
        $fin = null;
        for ($i = $ini; $i < $total; $i++) {
            if (strncasecmp(substr($html, $i, 7), '<table', 6) === 0) {
                $prof++;
                $i += 5;
                continue;
            }
            if (strncasecmp(substr($html, $i, 8), '</table>', 8) === 0) {
                $prof--;
                $i += 7;
                if ($prof === 0) {
                    $fin = $i;
                    break;
                }
                continue;
            }
        }
        if ($fin === null) {
            break;
        }

        $tabla = substr($html, $ini, $fin - $ini + 8);
        $pos   = $fin + 8;

        $cuerpo = stripos($tabla, '<tbody');
        $cabeza = $cuerpo === false ? $tabla : substr($tabla, 0, $cuerpo);

        /* El colgroup tiene que tener tantos <col> como columnas tiene la
           cabecera. Con table-layout: fixed el navegador aplica los primeros
           anchos: un <col> de más squeezea la tabla al ancho que sumen los
           primeros y el contenido acaba encima de la celda de al lado. */
        if (preg_match('/<colgroup\b/i', $tabla)) {
            $colgroup = preg_match('#<colgroup\b[^>]*>(.*?)</colgroup>#is', $tabla, $cg) ? $cg[1] : '';
            $cols = preg_match_all('/<col\b/i', $colgroup, $cm);
            if (preg_match_all('/<th\b/i', $cabeza, $hm) && $cols !== count($hm[0])) {
                $problemas[] = sprintf(
                    'colgroup con %d <col> para %d columnas de cabecera',
                    $cols,
                    count($hm[0])
                );
            }
        }

        if (!preg_match_all('/<th\b/i', $cabeza, $m)) {
            continue; // tabla de maquetación, sin cabeceras
        }
        $columnas = count($m[0]);
        if ($columnas < 2) {
            continue; // una sola columna: no hay desalineación que ver
        }

        $cuerpoTxt = $cuerpo === false ? $tabla : substr($tabla, $cuerpo);
        if (!preg_match_all('#<tr\b[^>]*>(.*?)</tr>#is', $cuerpoTxt, $filas)) {
            continue;
        }

        foreach ($filas[1] as $fila) {
            if (!preg_match_all('/<td\b([^>]*)>/i', $fila, $celdas)) {
                continue;
            }
            $ancho = 0;
            foreach ($celdas[1] as $attrs) {
                $ancho += preg_match('/colspan\s*=\s*"?(\d+)/i', $attrs, $c) ? (int) $c[1] : 1;
            }
            if ($ancho !== $columnas) {
                $problemas[] = sprintf(
                    'tabla con %d columnas en la cabecera y una fila que suma %d',
                    $columnas,
                    $ancho
                );
                break;
            }
        }
    }

    return $problemas;
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
