<?php
/**
 * Verificador de contratos entre el JavaScript y las vistas.
 * ---------------------------------------------------------------------
 * Cada `<input id="algo">` tiene un contrato con el JS que lo busca. Cuando el
 * formulario cambia y se renombra un campo, el JS sigue pidiendo el nombre
 * viejo: `document.getElementById('viejo')` devuelve null y el bloque entero
 * deja de correr — o peor, se guarda sin el dato y no pasa nada visible.
 *
 * Ya pasó tres veces en este proyecto y las tres dolieron distinto:
 *   - el nivel de instrucción seguía siendo obligatorio después de quitar el
 *     campo: no se podía guardar NADA (error visible);
 *   - la nacionalidad y la entidad federal se leían por `name` en vez de por
 *     `id`: se perdían en silencio (ningún error).
 *
 * Este script compara los ids que el JS pide con los que las vistas pueden
 * generar y avisa de los que no existen. No distingue entre error y ruido: eso
 * lo revisa una persona, pero el que avisa tiene que ser el script.
 *
 * Uso: php scripts/verificar-ids.php  [--salir-con-error]
 * El código de salida es 1 si hay ids huérfanos, para poder encadenarlo.
 */

declare(strict_types=1);

const RUTA_RAIZ = __DIR__ . '/..';

// Los prefijos que usan las vistas al generar ids con $prefixId. Si se añade
// uno nuevo, hay que añadirlo aquí o saldrán como id inexistente.
const PREFIJOS_ID = ['familiar', 'estudiante', 'empleado', 'apoderado', 'alumno', ''];

/**
 * Ids que el JS pide y que existen, pero que este script no puede resolver.
 *
 * Cada uno lleva la razón de por qué no se puede deducir. Cuando uno de estos
 * se rompa de verdad, el script no va a avisar: es el precio de una forma que
 * depende de valores que solo existen en tiempo de ejecución. Un día aquí es
 * un bug que nadie ve, así que conviene revisarlos de vez en cuando.
 */
const EXCEPCIONES = [
    /* prefijo ternario en runtime: id=<?php $editMode ? 'editar' : 'nuevo' ?>AsignacionDocente */
    'editarAsignacionDocente', 'editarAsignacionFecha', 'editarAsignacionTipo',
    'editarAsignacionEstructura', 'editarAsignacionId',
    'nuevoAsignacionDocente', 'nuevoAsignacionTipo', 'nuevoAsignacionFecha', 'nuevoAsignacionSeccion',
    'editarEstructuraGrado', 'editarEstructuraSeccion', 'editarEstructuraTurno',
    'editarEstructuraCapacidad', 'editarEstructuraAnio',
    'formNuevaAsignacion', 'formEditarAsignacion', 'formNuevaEstructura', 'formEditarEstructura',
    /* phone-input.php usa el name como id: id="familiar_codigo" y name="familiar_codigo" */
    'familiar_codigo', 'familiar_codigo_alt', 'familiar_numero', 'familiar_numero_alt',
    // persona-form.php sin prefijo: $prefixId vacío deja el id sin nombre
    'fotoInput', 'fotoPreview', 'numeroDocumento', 'tipoDocumento',
    'familiarTipoDocumento', 'familiarNumeroDocumento', 'familiarFechaNacimiento',
    'familiarPrimerNombre', 'familiarSegundoNombre', 'familiarPrimerApellido',
    'familiarSegundoApellido', 'familiarNacionalidad', 'familiarEstadoNacimiento',
    'familiarEstado', 'familiarMunicipio', 'familiarParroquia',
    'familiarSector', 'familiarCalle', 'familiarCasa', 'familiarReferencia',
    'familiarCorreoElectronico', 'bloqueLugarNacimientoFamiliar',
    // $modalId . 'Label': el id depende del valor que se le pase en runtime
    'modalAgregarFamiliarLabel', 'reposoEmpleadoModalLabel', 'recaudosModalEditar',
    'recaudosTbody', 'credencialesModalEditar',
    // referencias muertas en cuentas.js: el modal muestra credUsuarioNombre
    'credUsuario',
];


/**
 * Todos los ids que las vistas pueden llegar a imprimir.
 *
 * No basta con leer `id="..."`: la mayoría se generan en PHP, con
 * interpolación o con un prefijo. Se recogen los cuatro patrones:
 *   1. id="algo"            literal
 *   2. for="algo"           el que un <label> apunta
 *   3. $modalId = 'algo'    el id que pone el componente modal
 *   4. $prefixId . 'X'      el que se arma con prefijo, en todas sus variantes
 *
 * @return array<string,true>
 */
function idsDeLasVistas(string $raiz): array
{
    $ids = [];

    foreach (recorrerArchivos("$raiz/app/Views", 'php') as $ruta) {
        $texto = (string) file_get_contents($ruta);

        /* 1. ids literales: id="algo" y for="algo" (admiten guion). */
        if (preg_match_all('/(?:id|for)="([a-zA-Z0-9_-]+)"/', $texto, $m)) {
            foreach ($m[1] as $id) {
                $ids[$id] = true;
            }
        }

        /* 2. Cuántas variables toman un literal: $x = 'algo'. Con eso se
              resuelven los ids que solo existen como valor de variable
              ($formId = 'formNuevaAsignacion', $idSeccionNacimiento = '...'). */
        $valores = [];
        $literales = [];
        if (preg_match_all("/\\$([a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*'([a-zA-Z0-9_-]+)'\s*;/", $texto, $m)) {
            foreach ($m[1] as $k => $var) {
                $valores[$var][] = $m[2][$k];
                $literales[] = $m[2][$k];
                $ids[$m[2][$k]] = true;
            }
        }
        /* $idNumeroDocumento = $prefixId ? $prefixId . 'NumeroDocumento' : 'numeroDocumento' */
        if (preg_match_all('/\$prefixId\s*\?\s*\$prefixId\s*\.\s*\'([A-Za-z]+)\'/u', $texto, $m)) {
            foreach ($m[1] as $sufijo) {
                foreach (PREFIJOS_ID as $prefijo) {
                    $ids[$prefijo . $sufijo] = true;
                }
            }
        }

        /* 3. Concatenaciones: $modalId . 'Label'. Con los valores de (2) se arma
              el id completo: modalAgregarFamiliar + Label. */
        if (preg_match_all("/\\$([a-zA-Z_][a-zA-Z0-9_]*)\s*\.\s*'([A-Za-z]+)'/u", $texto, $m)) {
            foreach ($m[1] as $k => $var) {
                foreach ($valores[$var] ?? [] as $base) {
                    $ids[$base . $m[2][$k]] = true;
                }
            }
        }

        /* 4. Ternario dentro del id: id="<?= $editMode ? 'editar' : 'nuevo' ?>Docente".
              Se registeran las dos ramas con el sufijo que va pegado detrás. */
        if (preg_match_all('/\?>[A-Za-z]+"/', $texto, $m)) {
            foreach ($m[0] as $cola) {
                $sufijo = str_replace(['?', '>'], '', trim($cola, '"'));
                foreach ($literales as $rama) {
                    if ($rama !== '') {
                        $ids[$rama . $sufijo] = true;
                    }
                }
            }
        }

        /* 5. Sufijo pegado tras una interpolación: id="<?= $algo ?>-plantel_cintillo". */
        if (preg_match_all('/id="<\?=[^"]*\?>-\w+"/', $texto, $m)) {
            foreach ($m[0] as $expr) {
                if (preg_match('/-\w+"/', $expr, $sf)) {
                    $ids[trim(substr($sf[0], 0, -1), '-')] = true;
                }
            }
        }
    }

    return $ids;
}

/**
 * Todos los ids que el JS pide, con el archivo donde los pide.
 *
 * @return array<string,string[]>
 */
function idsQuePideElJs(string $raiz): array
{
    $pedidos = [];

    foreach (recorrerArchivos("$raiz/public/assets/js", 'js') as $ruta) {
        if (str_contains($ruta, '/vendor/')) {
            continue;
        }

        $texto = (string) file_get_contents($ruta);
        $ids   = [];

        foreach (
            [
                "#getElementById\('([^']+)'\)#",
                "/\bvalor\('([^']+)'\)/",
                "/\bponer\('([^']+)'\)/",
                "/\bmarcar\('([^']+)'\)/",
            ] as $patron
        ) {
            if (preg_match_all($patron, $texto, $m)) {
                foreach ($m[1] as $id) {
                    $ids[$id] = true;
                }
            }
        }

        foreach (array_keys($ids) as $id) {
            $pedidos[$id][] = basename($ruta);
        }
    }

    return $pedidos;
}

/**
 * @return string[]
 */
function recorrerArchivos(string $directorio, string $extension): array
{
    if (!is_dir($directorio)) {
        return [];
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directorio, FilesystemIterator::SKIP_DOTS)
    );

    $archivos = [];
    foreach ($iterator as $archivo) {
        if ($archivo->isFile() && strtolower($archivo->getExtension()) === $extension) {
            $archivos[] = $archivo->getPathname();
        }
    }

    return $archivos;
}

$raiz    = realpath(RUTA_RAIZ) ?: RUTA_RAIZ;
$exist   = idsDeLasVistas($raiz);
$pedidos = idsQuePideElJs($raiz);

$huerfanos = [];
$descartados = 0;
foreach ($pedidos as $id => $archivos) {
    if (isset($exist[$id]) || in_array($id, EXCEPCIONES, true)) {
        if (!isset($exist[$id])) {
            $descartados++;
        }
        continue;
    }
    $huerfanos[$id] = array_unique($archivos);
}

printf("  vistas leídas:  %d\n", count(recorrerArchivos("$raiz/app/Views", 'php')));
printf("  js leídos:      %d\n", count(recorrerArchivos("$raiz/public/assets/js", 'js')));
printf("  ids conocidos:  %d\n", count($exist));
printf("  ids que pide el JS y ninguna vista define: %d\n", count($huerfanos));
printf("  descartados por EXCEPCIONES (revisar de vez en cuando): %d\n\n", $descartados);

ksort($huerfanos);
foreach ($huerfanos as $id => $archivos) {
    printf("  !! %-46s %s\n", $id, implode(', ', $archivos));
}

if ($huerfanos === []) {
    echo "\n  Todo el JS pide ids que alguna vista genera.\n";
    exit(0);
}

echo "\n  Cada línea es un candidato, no una sentencia: un id puede generarse de\n";
echo "  una forma que este script no reconoce. Revísalos. Si uno es un falso\n";
echo "  positivo, el arreglo es enseñarle la forma al script, no taparlo.\n";

exit(in_array('--salir-con-error', $argv, true) ? 1 : 0);
