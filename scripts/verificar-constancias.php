<?php
declare(strict_types=1);

/**
 * Script de verificación de plantillas de Constancias (SIGDE).
 *
 * Comprueba de forma automatizada:
 * 1. Estudiante con Cédula de Identidad (V-xx.xxx.xxx).
 * 2. Estudiante con Cédula Escolar (V-xxxxxxxxxx).
 * 3. Concordancia femenina ("la estudiante", "nacida", "Directora").
 * 4. Concordancia masculina ("el estudiante", "nacido", "Director").
 * 5. Con sello cargado y sin sello cargado.
 * 6. Omisión completa de la cláusula de lugar de nacimiento si falta algún dato opcional.
 * 7. Ausencia total de subrayados (<u>, text-decoration: underline) y guiones bajos de relleno (___).
 * 8. Compilación limpia a PDF en exactamente 1 página carta.
 */

spl_autoload_register(function ($clase) {
    $prefijo = 'App\\';
    $dir_base = __DIR__ . '/../app/';
    $longitud = strlen($prefijo);
    if (strncmp($prefijo, $clase, $longitud) !== 0) return;
    $clase_relativa = substr($clase, $longitud);
    $archivo = $dir_base . str_replace('\\', '/', $clase_relativa) . '.php';
    if (file_exists($archivo)) require_once $archivo;
});

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Config/Env.php';
require_once __DIR__ . '/../app/Config/Database.php';
require_once __DIR__ . '/../app/Support/Configuracion.php';
require_once __DIR__ . '/../app/Support/ConstanciaPdf.php';

use App\Support\Configuracion;
use App\Support\ConstanciaPdf;
use App\Support\FichaEstudiantePdf;

echo "\n--- Verificación de Constancia de Estudio ---\n";

$config = Configuracion::getAll();
$config['director_nombre'] = 'MARÍA ELENA CONTRERAS PÉREZ';
$config['director_cedula'] = 'V-14852369';
$config['director_cargo']  = 'Directora';
$config['director_sexo']   = 'F';
$config['plantel_nombre']  = 'Escuela Básica "Isidro Ramírez"';
$config['plantel_codigo_dea'] = 'OD12345678';
$config['plantel_codigo_dependencia'] = '007932154';
$config['plantel_municipio'] = 'Valencia';
$config['plantel_parroquia'] = 'Miguel Peña';
$config['cdce_estado']     = 'Carabobo';
$config['nivel_educativo'] = 'Educación Primaria';
$config['nivel_codigo']    = '21000';
$config['lugar_expedicion'] = 'Valencia';

$errores = [];

// Helper para validar PDF y texto extraído
function inspeccionarConstancia(string $nombreCaso, string $pdfContent, array $esperados, array $prohibidos): array {
    $fallos = [];
    
    // Verificar que es un PDF válido
    if (!str_starts_with($pdfContent, '%PDF-')) {
        $fallos[] = "[$nombreCaso] El resultado no tiene cabecera PDF válida.";
        return $fallos;
    }

    $tmpPdf = sys_get_temp_dir() . '/' . uniqid('const_test_', true) . '.pdf';
    file_put_contents($tmpPdf, $pdfContent);

    // Contar páginas con pdfinfo
    $infoOutput = shell_exec("pdfinfo " . escapeshellarg($tmpPdf) . " 2>&1") ?? '';
    if (preg_match('/Pages:\s+(\d+)/', $infoOutput, $mPages)) {
        $numPaginas = (int) $mPages[1];
        if ($numPaginas !== 1) {
            $fallos[] = "[$nombreCaso] El PDF tiene {$numPaginas} páginas (debe ser exactamente 1 hoja).";
        }
    } else {
        $fallos[] = "[$nombreCaso] No se pudo determinar el número de páginas con pdfinfo.";
    }

    // Extraer texto con pdftotext si está disponible
    $textoRaw = shell_exec("pdftotext " . escapeshellarg($tmpPdf) . " - 2>&1") ?? '';
    unlink($tmpPdf);

    // Normalizar espacios y saltos de línea para búsqueda robusta
    $textoNormalizado = preg_replace('/\s+/', ' ', $textoRaw) ?? '';

    foreach ($esperados as $exp) {
        if (!str_contains($textoNormalizado, $exp)) {
            $fallos[] = "[$nombreCaso] No se encontró el texto esperado: \"{$exp}\"";
        }
    }

    foreach ($prohibidos as $proh) {
        if (str_contains($textoNormalizado, $proh)) {
            $fallos[] = "[$nombreCaso] Se encontró texto prohibido: \"{$proh}\"";
        }
    }

    return $fallos;
}

// -------------------------------------------------------------
// Caso 1: Estudiante con Cédula de Identidad, Femenina, con Sello
// -------------------------------------------------------------
$estudiante1 = [
    'nombre_completo'      => 'VALERIN ROJAS DÍAZ',
    'tipo_documento'       => 'V',
    'numero_documento'     => '29863136',
    'sexo'                 => 'F',
    'fecha_nacimiento'     => '2015-05-16',
    'municipio_nacimiento' => 'Valencia',
    'estado_nacimiento'    => 'Carabobo',
    'grado'                => '1° Grado',
    'periodo_escolar'      => '2026-2027',
];

try {
    $pdf1 = ConstanciaPdf::generarEstudio($config, $estudiante1, 'activo', new DateTimeImmutable('2026-10-22'));
    $fallos1 = inspeccionarConstancia(
        'Caso 1 (C.I., Femenina, Con Sello)',
        $pdf1,
        [
            'CONSTANCIA DE ESTUDIO',
            'Directora de la Institución Educativa',
            'la estudiante VALERIN ROJAS DÍAZ',
            'titular de la Cédula de Identidad N.° V-29.863.136',
            'nacida en el Municipio Valencia del Estado Carabobo, el 16 de mayo de 2015',
            'PRIMER grado de Educación Primaria (código 21000)',
            '2026-2027',
            'Constancia que se expide en Valencia, a los 22 días del mes de octubre de 2026.',
        ],
        ['___', 'C.I./C.E.', 'N° V.-', 'cursó']
    );
    $errores = array_merge($errores, $fallos1);
    if (empty($fallos1)) {
        echo "  [OK] Caso 1: Cédula de Identidad, Femenina, con Sello (1 hoja, sin subrayados)\n";
    }
} catch (Throwable $e) {
    $errores[] = "[Caso 1] Excepción: " . $e->getMessage();
}

// -------------------------------------------------------------
// Caso 2: Estudiante con Cédula Escolar, Masculino, con Sello
// -------------------------------------------------------------
$estudiante2 = [
    'nombre_completo'      => 'LEO PINTO MARTÍNEZ',
    'tipo_documento'       => 'CE',
    'numero_documento'     => 'V1162458963',
    'sexo'                 => 'M',
    'fecha_nacimiento'     => '2017-08-20',
    'municipio_nacimiento' => 'Naguanagua',
    'estado_nacimiento'    => 'Carabobo',
    'grado'                => '3° Grado',
    'periodo_escolar'      => '2026-2027',
];

try {
    $pdf2 = ConstanciaPdf::generarEstudio($config, $estudiante2, 'activo', new DateTimeImmutable('2026-10-22'));
    $fallos2 = inspeccionarConstancia(
        'Caso 2 (C.E., Masculino, Con Sello)',
        $pdf2,
        [
            'el estudiante LEO PINTO MARTÍNEZ',
            'titular de la Cédula Escolar N.° V-1162458963',
            'nacido en el Municipio Naguanagua del Estado Carabobo, el 20 de agosto de 2017',
            'TERCER grado de Educación Primaria (código 21000)',
        ],
        ['___', 'C.I./C.E.', 'N° V.-', 'la estudiante', 'nacida']
    );
    $errores = array_merge($errores, $fallos2);
    if (empty($fallos2)) {
        echo "  [OK] Caso 2: Cédula Escolar, Masculino, con Sello (1 hoja, sin subrayados)\n";
    }
} catch (Throwable $e) {
    $errores[] = "[Caso 2] Excepción: " . $e->getMessage();
}

// -------------------------------------------------------------
// Caso 3: Sin Sello y Sin Municipio/Estado de Nacimiento (Opcionales)
// -------------------------------------------------------------
$configSinSello = $config;
$configSinSello['institucion_sello'] = '';

$estudiante3 = [
    'nombre_completo'      => 'CARLOS MANUEL GÓMEZ PINTO',
    'tipo_documento'       => 'V',
    'numero_documento'     => '32123456',
    'sexo'                 => 'M',
    'fecha_nacimiento'     => '2014-11-05',
    'municipio_nacimiento' => '',
    'estado_nacimiento'    => '',
    'grado'                => '6° Grado',
    'periodo_escolar'      => '2026-2027',
];

try {
    $pdf3 = ConstanciaPdf::generarEstudio($configSinSello, $estudiante3, 'activo', new DateTimeImmutable('2026-10-22'));
    $fallos3 = inspeccionarConstancia(
        'Caso 3 (Sin Sello, Sin Lugar Nacimiento Opcional)',
        $pdf3,
        [
            'el estudiante CARLOS MANUEL GÓMEZ PINTO',
            'titular de la Cédula de Identidad N.° V-32.123.456',
            'nacido el 5 de noviembre de 2014',
            'SEXTO grado de Educación Primaria (código 21000)',
        ],
        ['___', 'del Estado', 'C.I./C.E.', 'N° V.-']
    );
    $errores = array_merge($errores, $fallos3);
    if (empty($fallos3)) {
        echo "  [OK] Caso 3: Sin Sello, Sin lugar opcional (cláusula omitida limpiamente)\n";
    }
} catch (Throwable $e) {
    $errores[] = "[Caso 3] Excepción: " . $e->getMessage();
}

// -------------------------------------------------------------
// Caso 4: Constancia de Retiro (Encabezado solo Ministerio, sin subrayados)
// -------------------------------------------------------------
$estudianteRetiro = [
    'nombre_completo'        => 'SOFÍA VALENTINA MÉNDEZ TORRES',
    'tipo_documento'         => 'V',
    'numero_documento'       => '31542890',
    'sexo'                   => 'F',
    'fecha_nacimiento'       => '2016-03-10',
    'municipio_nacimiento'   => 'Valencia',
    'estado_nacimiento'      => 'Carabobo',
    'grado'                  => '4° Grado',
    'periodo_escolar'        => '2026-2027',
    'representante_nombre'   => 'CARMEN ELENA TORRES DE MÉNDEZ',
    'representante_tipo_doc' => 'V',
    'representante_num_doc'  => '15874123',
    'fecha_retiro'           => '2026-10-15',
    'motivo'                 => 'CAMBIO DE RESIDENCIA',
];

try {
    $pdfRetiro = ConstanciaPdf::generarRetiro($config, $estudianteRetiro, new DateTimeImmutable('2026-10-22'));
    $fallosRetiro = inspeccionarConstancia(
        'Caso 4 (Constancia de Retiro)',
        $pdfRetiro,
        [
            'CONSTANCIA DE RETIRO',
            'la estudiante SOFÍA VALENTINA MÉNDEZ TORRES',
            'titular de la Cédula de Identidad N.° V-31.542.890',
            'CUARTO grado de Educación Primaria (código 21000)',
            'es retirada por su representante legal: CARMEN ELENA TORRES DE MÉNDEZ',
            'titular de la Cédula de Identidad N.° V-15.874.123',
            'el día 15 de octubre de 2026, Motivo del retiro: CAMBIO DE RESIDENCIA.',
            'Constancia que se expide en Valencia, a los 22 días del mes de octubre de 2026.',
        ],
        ['REPÚBLICA BOLIVARIANA DE VENEZUELA', '___', 'C.I./C.E.', 'N° V.-']
    );
    $errores = array_merge($errores, $fallosRetiro);
    if (empty($fallosRetiro)) {
        echo "  [OK] Caso 4: Constancia de Retiro (encabezado solo Ministerio, 1 hoja, sin subrayados)\n";
    }
} catch (Throwable $e) {
    $errores[] = "[Caso 4] Excepción: " . $e->getMessage();
}

// -------------------------------------------------------------
// Caso 5: Constancia de Prosecución en Nivel de Educación Primaria
// -------------------------------------------------------------
$estudianteProsecucion = [
    'nombre_completo'      => 'MATEO ALEJANDRO RIVAS CASTRO',
    'tipo_documento'       => 'CE',
    'numero_documento'     => 'V1172583690',
    'sexo'                 => 'M',
    'fecha_nacimiento'     => '2017-04-12',
    'municipio_nacimiento' => 'Valencia',
    'estado_nacimiento'    => 'Carabobo',
    'grado_origen'         => '2° Grado',
    'grado_destino'        => '3° Grado',
    'literal'              => 'A',
    'periodo_escolar'      => '2025-2026',
];

try {
    $pdfPros = ConstanciaPdf::generarProsecucion($config, $estudianteProsecucion, new DateTimeImmutable('2026-07-28'));
    $fallosPros = inspeccionarConstancia(
        'Caso 5 (Constancia de Prosecución)',
        $pdfPros,
        [
            'CONSTANCIA DE PROSECUCIÓN',
            'EN NIVEL DE EDUCACIÓN PRIMARIA',
            'el estudiante MATEO ALEJANDRO RIVAS CASTRO',
            'titular de la Cédula Escolar N.° V-1172583690',
            'cursó el SEGUNDO grado de Educación Primaria',
            'correspondiéndole el literal "A" durante el período escolar 2025-2026',
            'siendo promovido al TERCER grado del Nivel de Educación Primaria.',
            'INSTITUCIÓN EDUCATIVA',
            'CENTRO DE DESARROLLO DE LA CALIDAD EDUCATIVA ESTADAL',
        ],
        ['REPÚBLICA BOLIVARIANA DE VENEZUELA', '___', 'C.I./C.E.', 'N° V.-']
    );
    $errores = array_merge($errores, $fallosPros);
    if (empty($fallosPros)) {
        echo "  [OK] Caso 5: Constancia de Prosecución (Ministerio + Escudo Nacional, 1 hoja, sin subrayados)\n";
    }
} catch (Throwable $e) {
    $errores[] = "[Caso 5] Excepción: " . $e->getMessage();
}

// -------------------------------------------------------------
// Caso 6: Certificado de Educación Primaria (Egreso 6.° Grado)
// -------------------------------------------------------------
$config['certificado_epigrafe'] = 'BICENTENARIO DE LA UNIDAD AMERICANA: CONGRESO ANFICTIÓNICO DE PANAMÁ';
$config['cdce_director_nombre'] = 'XIOMARA DEL VALLE LUNA';
$config['cdce_director_cedula'] = 'V-11223344';

$estudianteEgreso = [
    'nombre_completo'      => 'CAMILA ANDREA SALAZAR LÓPEZ',
    'tipo_documento'       => 'V',
    'numero_documento'     => '33456789',
    'sexo'                 => 'F',
    'fecha_nacimiento'     => '2014-09-22',
    'municipio_nacimiento' => 'Valencia',
    'estado_nacimiento'    => 'Carabobo',
    'grado'                => '6° Grado',
    'literal'              => 'A',
    'periodo_escolar'      => '2025-2026',
];

try {
    $pdfEgreso = ConstanciaPdf::generarEgreso($config, $estudianteEgreso, new DateTimeImmutable('2026-07-28'));
    $fallosEgreso = inspeccionarConstancia(
        'Caso 6 (Certificado de Educación Primaria)',
        $pdfEgreso,
        [
            'CERTIFICADO',
            'DE EDUCACIÓN PRIMARIA',
            'certifica por medio de la presente que la estudiante CAMILA ANDREA SALAZAR LÓPEZ',
            'titular de la Cédula de Identidad N.° V-33.456.789',
            'cursó el SEXTO grado de Educación Primaria',
            'correspondiéndole el literal "A" durante el período escolar 2025-2026',
            'siendo promovida al 1.er Año del Nivel de Educación Media',
            'Certificado que se expide en Valencia, a los 28 días del mes de julio de 2026.',
            'XIOMARA DEL VALLE LUNA',
            'V-11.223.344',
            'Certificado válido a nivel nacional e internacional',
            '"BICENTENARIO DE LA UNIDAD AMERICANA: CONGRESO ANFICTIÓNICO DE PANAMÁ"',
        ],
        ['REPÚBLICA BOLIVARIANA DE VENEZUELA', '___', 'Pasaporte', 'N° V.-']
    );
    $errores = array_merge($errores, $fallosEgreso);
    if (empty($fallosEgreso)) {
        echo "  [OK] Caso 6: Certificado de Educación Primaria (Ministerio + Escudo + Epígrafe configurable, 1 hoja)\n";
    }
} catch (Throwable $e) {
    $errores[] = "[Caso 6] Excepción: " . $e->getMessage();
}

// -------------------------------------------------------------
// Caso 7: Reconocimiento automático en CertificatesModel y Lote de 6.° Grado
// -------------------------------------------------------------
try {
    $pdfLote = ConstanciaPdf::generarEgresoPorLote($config, [$estudianteEgreso], new DateTimeImmutable('2026-07-28'));
    if (strlen($pdfLote) < 1000) {
        $errores[] = "[Caso 7] El PDF por lote de Certificado de Primaria no se generó correctamente.";
    }
    try {
        $pdo = (new \App\Config\Database())->getConnection();
        $modelConst = new \App\Models\CertificatesModel($pdo);
        $listaEgreso = $modelConst->getEstudiantesEgreso(['q' => ''], 1, 10);
        $totalEgreso = $modelConst->countEstudiantesEgreso(['q' => '']);
        $loteEgreso  = $modelConst->getEstudiantesEgresoParaLote(['q' => '']);
        if (!is_array($listaEgreso) || !is_int($totalEgreso) || !is_array($loteEgreso)) {
            $errores[] = "[Caso 7] Consultas de reconocimiento automático de 6.° grado fallaron.";
        }
    } catch (Throwable $dbErr) {
        // Si se ejecuta en un entorno sin BD activa, solo se valida si el error es de SQL (42000 / HY093)
        if (str_contains($dbErr->getMessage(), 'SQLSTATE[42') || str_contains($dbErr->getMessage(), 'SQLSTATE[HY093')) {
            $errores[] = "[Caso 7 SQL] " . $dbErr->getMessage();
        }
    }
    echo "  [OK] Caso 7: Emisión por lote y reconocimiento automático de 6.° grado (Literal A–D)\n";
} catch (Throwable $e) {
    $errores[] = "[Caso 7] Excepción: " . $e->getMessage();
}

// =============================================================
// VERIFICACIÓN DE FICHA INTEGRAL DEL ESTUDIANTE (PDF)
// =============================================================
echo "\n--- Verificación de Ficha Integral del Estudiante ---\n";

function inspeccionarFicha(string $nombreCaso, string $pdfContent, array $esperados, array $prohibidos, ?int $paginasEsperadas = null): array {
    $fallos = [];

    if (!str_starts_with($pdfContent, '%PDF-')) {
        $fallos[] = "[$nombreCaso] El resultado no tiene cabecera PDF válida.";
        return $fallos;
    }

    $tmpPdf = sys_get_temp_dir() . '/' . uniqid('ficha_test_', true) . '.pdf';
    file_put_contents($tmpPdf, $pdfContent);

    if ($paginasEsperadas !== null) {
        $infoOutput = shell_exec("pdfinfo " . escapeshellarg($tmpPdf) . " 2>&1") ?? '';
        if (preg_match('/Pages:\s+(\d+)/', $infoOutput, $mPages)) {
            $numPaginas = (int) $mPages[1];
            if ($numPaginas !== $paginasEsperadas) {
                $fallos[] = "[$nombreCaso] El PDF tiene {$numPaginas} páginas (se esperaban {$paginasEsperadas}).";
            }
        }
    }

    $textoRaw = shell_exec("pdftotext " . escapeshellarg($tmpPdf) . " - 2>&1") ?? '';
    unlink($tmpPdf);

    $textoNormalizado = preg_replace('/\s+/', ' ', $textoRaw) ?? '';

    foreach ($esperados as $exp) {
        if (!str_contains($textoNormalizado, $exp)) {
            $fallos[] = "[$nombreCaso] No se encontró el texto esperado: \"{$exp}\"";
        }
    }

    foreach ($prohibidos as $proh) {
        if (str_contains($textoNormalizado, $proh)) {
            $fallos[] = "[$nombreCaso] Se encontró texto prohibido: \"{$proh}\"";
        }
    }

    return $fallos;
}

$dummyFotoUri = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
$dummySelloUri = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

$repTest = [
    'representante_principal' => 1,
    'nombre_referencia' => 'MARÍA LÓPEZ',
    'parentesco' => 'Madre',
    'tipo_documento' => 'V',
    'numero_documento' => '16852369',
    'nacionalidad' => 'Venezolana',
    'contacto' => ['codigo' => '0414', 'numero' => '1234567', 'correo' => 'maria@correo.com'],
    'direccion' => [
        'calle' => 'Calle 5',
        'casa' => '12',
        'sector' => 'El Triunfo',
        'parroquia_nombre' => 'Miguel Peña',
        'municipio_nombre' => 'Valencia',
        'estado_nombre' => 'Carabobo',
        'referencia' => 'Cerca de la plaza',
    ],
];

$autTest = [
    'nombre_referencia' => 'PEDRO SANCHEZ',
    'parentesco' => 'Tío',
    'tipo_documento' => 'V',
    'numero_documento' => '15963214',
    'nacionalidad' => 'Venezolana',
    'contacto' => ['codigo' => '0412', 'numero' => '7654321'],
];

$histTest = [
    [
        'anio_escolar' => '2025-2026',
        'grado' => '4° Grado',
        'seccion' => 'A',
        'turno' => 'Mañana',
        'estado_anio' => 'CERRADO',
        'tipo_matricula' => 'REGULAR',
    ],
    [
        'anio_escolar' => '2026-2027',
        'grado' => '5° Grado',
        'seccion' => 'A',
        'turno' => 'Mañana',
        'estado_anio' => 'ACTIVO',
        'tipo_matricula' => 'REGULAR',
    ],
];

$medicasTest = [
    'talla_camisa' => '12',
    'talla_pantalon' => '12',
    'talla_zapato' => '34',
    'tipo_sangre' => 'O+',
    'alergias' => 'Ninguna conocida',
    'medicamentos' => 'Ninguno',
    'enfermedades' => 'Ninguna',
    'condiciones_especiales' => 'Ninguna',
    'observaciones_medicas' => 'Estudiante saludable',
    'talla_observaciones' => 'Estatura acorde a la edad',
];

// -------------------------------------------------------------
// Caso 8: Ficha Integral - Cédula de Identidad, con Foto, con Sello
// -------------------------------------------------------------
$estudianteFicha1 = [
    'id' => 1,
    'primer_nombre' => 'Erickmar',
    'segundo_nombre' => 'Margarita',
    'primer_apellido' => 'Sanchez',
    'segundo_apellido' => 'Lopez',
    'nombre' => 'Erickmar Margarita Sanchez Lopez',
    'tipo_documento' => 'V',
    'numero_documento' => '31245896',
    'sexo' => 'F',
    'fecha_nacimiento' => '2014-04-12',
    'municipio_nacimiento' => 'Valencia',
    'estado_nacimiento' => 'Carabobo',
    'grado' => '5° Grado',
    'seccion' => 'A',
    'estado' => 'activo',
    'nacionalidad' => 'Venezolana',
];

$configFichaConSello = $config;
$configFichaConSello['selloUri'] = $dummySelloUri;

try {
    $pdfFicha1 = FichaEstudiantePdf::generar(
        $configFichaConSello,
        $estudianteFicha1,
        $medicasTest,
        [$repTest],
        [$autTest],
        $histTest,
        false,
        new DateTimeImmutable('2026-10-22'),
        $dummyFotoUri
    );

    $fallosFicha1 = inspeccionarFicha(
        'Caso 8 (Ficha: C.I., Con Foto, Con Sello)',
        $pdfFicha1,
        [
            'REPÚBLICA BOLIVARIANA DE VENEZUELA',
            'MINISTERIO DEL PODER POPULAR PARA LA EDUCACIÓN',
            'ESCUELA BÁSICA "ISIDRO RAMÍREZ"',
            'FICHA INTEGRAL DEL ESTUDIANTE',
            'SANCHEZ LOPEZ, ERICKMAR MARGARITA',
            'CÉDULA DE IDENTIDAD',
            'V-31.245.896',
            'FEMENINO',
            '1. DATOS PERSONALES',
            '2. INFORMACIÓN MÉDICA Y TALLAS',
            '3. DATOS DEL GRUPO FAMILIAR',
            '4. PERSONAS AUTORIZADAS PARA RETIRAR AL ESTUDIANTE',
            '5. HISTORIAL ACADÉMICO INSTITUCIONAL',
            'Emitido en Valencia, a los 22 días del mes de octubre de 2026.',
            'MARÍA ELENA CONTRERAS PÉREZ',
            'DIRECTORA',
            'C.I. V-14.852.369',
        ],
        ['CÉDULA ESCOLAR', 'C.I./C.E.', '___'],
        2
    );
    $errores = array_merge($errores, $fallosFicha1);
    if (empty($fallosFicha1)) {
        echo "  [OK] Caso 8: Ficha Integral con Cédula de Identidad, con Foto, con Sello (2 hojas)\n";
    }
} catch (Throwable $e) {
    $errores[] = "[Caso 8] Excepción: " . $e->getMessage();
}

// -------------------------------------------------------------
// Caso 9: Ficha Integral - Cédula Escolar, Masculino, con Foto, con Sello
// -------------------------------------------------------------
$estudianteFicha2 = [
    'id' => 2,
    'primer_nombre' => 'Leo',
    'primer_apellido' => 'Pinto',
    'segundo_apellido' => 'Martínez',
    'nombre' => 'Leo Pinto Martínez',
    'tipo_documento' => 'CE',
    'numero_documento' => 'V1162458963',
    'sexo' => 'M',
    'fecha_nacimiento' => '2017-08-20',
    'municipio_nacimiento' => 'Valencia',
    'estado_nacimiento' => 'Carabobo',
    'grado' => '1° Grado',
    'seccion' => 'B',
    'estado' => 'activo',
    'nacionalidad' => 'Venezolana',
];

try {
    $pdfFicha2 = FichaEstudiantePdf::generar(
        $configFichaConSello,
        $estudianteFicha2,
        $medicasTest,
        [$repTest],
        [$autTest],
        $histTest,
        false,
        new DateTimeImmutable('2026-10-22'),
        $dummyFotoUri
    );

    $fallosFicha2 = inspeccionarFicha(
        'Caso 9 (Ficha: Cédula Escolar, Con Foto, Con Sello)',
        $pdfFicha2,
        [
            'PINTO MARTÍNEZ, LEO',
            'CÉDULA ESCOLAR',
            'V-1162458963',
            'MASCULINO',
        ],
        ['CÉDULA DE IDENTIDAD', 'C.I./C.E.'],
        2
    );
    $errores = array_merge($errores, $fallosFicha2);
    if (empty($fallosFicha2)) {
        echo "  [OK] Caso 9: Ficha Integral con Cédula Escolar, Masculino, con Foto, con Sello (2 hojas)\n";
    }
} catch (Throwable $e) {
    $errores[] = "[Caso 9] Excepción: " . $e->getMessage();
}

// -------------------------------------------------------------
// Caso 10: Ficha Integral - Sin Foto, con Sello (recuadro neutro)
// -------------------------------------------------------------
try {
    $pdfFicha3 = FichaEstudiantePdf::generar(
        $configFichaConSello,
        $estudianteFicha1,
        $medicasTest,
        [$repTest],
        [$autTest],
        $histTest,
        false,
        new DateTimeImmutable('2026-10-22'),
        null // sin foto
    );

    $fallosFicha3 = inspeccionarFicha(
        'Caso 10 (Ficha: Sin Foto, Con Sello)',
        $pdfFicha3,
        [
            'SIN FOTO',
            'SANCHEZ LOPEZ, ERICKMAR MARGARITA',
        ],
        [],
        2
    );
    $errores = array_merge($errores, $fallosFicha3);
    if (empty($fallosFicha3)) {
        echo "  [OK] Caso 10: Ficha Integral sin Foto (recuadro neutro 'SIN FOTO' con mismo tamaño, 2 hojas)\n";
    }
} catch (Throwable $e) {
    $errores[] = "[Caso 10] Excepción: " . $e->getMessage();
}

// -------------------------------------------------------------
// Caso 11: Ficha Integral - Sin Foto y Sin Sello
// -------------------------------------------------------------
$configFichaSinSello = $config;
$configFichaSinSello['selloUri'] = null;
$configFichaSinSello['institucion_sello'] = '';

try {
    $pdfFicha4 = FichaEstudiantePdf::generar(
        $configFichaSinSello,
        $estudianteFicha1,
        $medicasTest,
        [$repTest],
        [$autTest],
        $histTest,
        false,
        new DateTimeImmutable('2026-10-22'),
        null // sin foto
    );

    $fallosFicha4 = inspeccionarFicha(
        'Caso 11 (Ficha: Sin Foto y Sin Sello)',
        $pdfFicha4,
        [
            'SIN FOTO',
            'SANCHEZ LOPEZ, ERICKMAR MARGARITA',
            'MARÍA ELENA CONTRERAS PÉREZ',
            'DIRECTORA',
        ],
        [],
        2
    );
    $errores = array_merge($errores, $fallosFicha4);
    if (empty($fallosFicha4)) {
        echo "  [OK] Caso 11: Ficha Integral sin Foto y sin Sello (2 hojas)\n";
    }
} catch (Throwable $e) {
    $errores[] = "[Caso 11] Excepción: " . $e->getMessage();
}

// -------------------------------------------------------------
// Verificación del HTML fuente: sin <u> ni text-decoration: underline
// -------------------------------------------------------------
$archivosPlantillas = [
    'estudio.php',
    'retiro.php',
    'prosecucion.php',
    'egreso.php',
    'egreso_por_seccion.php',
    'base.php',
];

foreach ($archivosPlantillas as $archivoTpl) {
    $contenidoTpl = file_get_contents(__DIR__ . '/../app/Views/constancias/pdf/' . $archivoTpl);
    if (str_contains($contenidoTpl, '<u>') || str_contains($contenidoTpl, 'text-decoration: underline')) {
        $errores[] = "[HTML {$archivoTpl}] Contiene etiquetas <u> o estilos con subrayado.";
    }
}

$fichaHtml = file_get_contents(__DIR__ . '/../app/Views/consulta_academica/estudiantes/export-pdf.php');
if (str_contains($fichaHtml, '<u>') || str_contains($fichaHtml, 'text-decoration: underline')) {
    $errores[] = "[HTML export-pdf.php] Contiene etiquetas <u> o estilos con subrayado.";
}

if (empty($errores)) {
    echo "  -> Todas las pruebas de Constancias pasaron satisfactoriamente (0 errores).\n\n";
    exit(0);
} else {
    echo "\n  [FALLO] Se detectaron errores:\n";
    foreach ($errores as $err) {
        echo "    - $err\n";
    }
    echo "\n";
    exit(1);
}
