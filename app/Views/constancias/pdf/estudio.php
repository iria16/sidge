<?php
declare(strict_types=1);

/**
 * Plantilla Oficial: Constancia de Estudio.
 * 
 * Sigue fielmente el formato y criterios normativos oficiales:
 * - Redacción formal, texto corrido, justificado, con sangría de primera línea (1.25 cm).
 * - Sin subrayados en el cuerpo ni líneas para rellenar a mano.
 * - Negrita únicamente en: nombre del director(a), número de documento, nombre de la institución,
 *   código DEA, nombre del estudiante, número de documento del estudiante, fecha de nacimiento,
 *   grado, nivel y período escolar.
 * - Cédula de Identidad ("titular de la Cédula de Identidad N.° V-xx.xxx.xxx") vs Cédula Escolar ("titular de la Cédula Escolar N.° V-xxxxxxxxxx").
 * - Sin "N° V.-": formato canónico "N.°" con espacio no separable (&nbsp;).
 * - Concordancia de género estricta ("el estudiante / la estudiante", "nacido / nacida", "Director / Directora").
 * - Grado en ordinal con letras (PRIMER, SEGUNDO, TERCER, CUARTO, QUINTO, SEXTO).
 * - Omisión completa de la cláusula de nacimiento si falta algún dato opcional (municipio/estado).
 * - Fecha de expedición formal en línea propia con mes en minúscula.
 *
 * @var array  $config      Configuración institucional
 * @var array  $estudiante  Datos del estudiante
 * @var string $modalidad   'activo', 'retirado' o 'egresado'
 */

$tituloConstancia = 'CONSTANCIA DE ESTUDIO';
$mostrarCodigoDependencia = true;

// 1. Datos y concordancia de la autoridad directiva
$directorNom = mb_strtoupper((string) ($config['director_nombre'] ?? ''), 'UTF-8');

$dirCedulaRaw = (string) ($config['director_cedula'] ?? '');
$dirLetra = preg_match('/^[VE]/i', $dirCedulaRaw, $m) ? strtoupper($m[0]) : 'V';
$dirNumDigits = preg_replace('/\D/', '', $dirCedulaRaw);
$dirNumFormatted = ($dirNumDigits !== '') ? number_format((int) $dirNumDigits, 0, '', '.') : $dirCedulaRaw;
$dirDocTexto = $dirLetra . '-' . $dirNumFormatted;

$cargoConfig = trim((string) ($config['director_cargo'] ?? ''));
$dirSexo = strtoupper(trim((string) ($config['director_sexo'] ?? '')));
if ($dirSexo === 'F' || preg_match('/\bDirectora\b/i', $cargoConfig)) {
    $cargoDirectorTexto = 'Directora';
} elseif ($dirSexo === 'M' || preg_match('/\bDirector\b/i', $cargoConfig)) {
    $cargoDirectorTexto = 'Director';
} else {
    $cargoDirectorTexto = 'Director(a)';
}

// 2. Datos institucionales
$plantelNom = mb_strtoupper((string) ($config['plantel_nombre'] ?? 'ESCUELA BÁSICA "ISIDRO RAMÍREZ"'), 'UTF-8');
$plantelDea = mb_strtoupper((string) ($config['plantel_codigo_dea'] ?? ''), 'UTF-8');
$plantelMunicipio = trim((string) ($config['plantel_municipio'] ?? 'Valencia'));
$plantelParroquia = trim((string) ($config['plantel_parroquia'] ?? 'Miguel Peña'));
$cdceEstado = trim((string) ($config['cdce_estado'] ?? 'Carabobo'));

// 3. Estudiante: concordancia de género
$sexoEst = strtoupper(trim((string) ($estudiante['sexo'] ?? '')));
if ($sexoEst === 'F') {
    $artEstudiante = 'la estudiante';
    $nacidoPalabra = 'nacida';
} elseif ($sexoEst === 'M') {
    $artEstudiante = 'el estudiante';
    $nacidoPalabra = 'nacido';
} else {
    $artEstudiante = 'el/la estudiante';
    $nacidoPalabra = 'nacido(a)';
}

$nombreEstudiante = mb_strtoupper((string) ($estudiante['nombre_completo'] ?? ''), 'UTF-8');

// 4. Identificación del estudiante: Cédula de Identidad vs Cédula Escolar
$tipoDocEst = strtoupper(trim((string) ($estudiante['tipo_documento'] ?? '')));
$numDocRawEst = trim((string) ($estudiante['numero_documento'] ?? ''));
$esCedulaEscolar = ($tipoDocEst === 'CE') || str_starts_with(strtoupper($numDocRawEst), 'CE');

if ($esCedulaEscolar) {
    // Cédula Escolar: usa el formato del sistema sin "C.I./C.E."
    $docNumeroEst = $numDocRawEst;
    if (preg_match('/^([VE])(\d+)$/i', $numDocRawEst, $mCE)) {
        $docNumeroEst = strtoupper($mCE[1]) . '-' . $mCE[2];
    } elseif (ctype_digit($numDocRawEst)) {
        $docNumeroEst = 'V-' . $numDocRawEst;
    }
    $textoDocEstudiante = 'titular de la Cédula Escolar N.°&nbsp;<strong>' . htmlspecialchars($docNumeroEst, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong>';
} else {
    // Cédula de Identidad: "titular de la Cédula de Identidad N.° V-xx.xxx.xxx"
    $letraEst = ($tipoDocEst === 'E' || str_starts_with(strtoupper($numDocRawEst), 'E')) ? 'E' : 'V';
    $numSoloDigitos = preg_replace('/\D/', '', $numDocRawEst);
    $numFmt = ($numSoloDigitos !== '') ? number_format((int) $numSoloDigitos, 0, '', '.') : $numDocRawEst;
    $docNumeroEst = $letraEst . '-' . $numFmt;
    $textoDocEstudiante = 'titular de la Cédula de Identidad N.°&nbsp;<strong>' . htmlspecialchars($docNumeroEst, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong>';
}

// 5. Fecha y lugar de nacimiento
$mesesEspanolLower = [
    1  => 'enero',
    2  => 'febrero',
    3  => 'marzo',
    4  => 'abril',
    5  => 'mayo',
    6  => 'junio',
    7  => 'julio',
    8  => 'agosto',
    9  => 'septiembre',
    10 => 'octubre',
    11 => 'noviembre',
    12 => 'diciembre',
];

$fechaNacObj = null;
$fechaNacRaw = $estudiante['fecha_nacimiento'] ?? null;
if ($fechaNacRaw instanceof \DateTimeInterface) {
    $fechaNacObj = $fechaNacRaw;
} elseif (is_string($fechaNacRaw) && $fechaNacRaw !== '') {
    try {
        $fechaNacObj = new \DateTimeImmutable($fechaNacRaw);
    } catch (\Throwable) {
        $fechaNacObj = null;
    }
}

if ($fechaNacObj !== null) {
    $diaNac = (int) $fechaNacObj->format('j');
    $mesNac = (int) $fechaNacObj->format('n');
    $anioNac = (int) $fechaNacObj->format('Y');
    $fechaNacTexto = $diaNac . ' de ' . ($mesesEspanolLower[$mesNac] ?? '') . ' de ' . $anioNac;
} else {
    $fechaNacTexto = trim((string) ($estudiante['fecha_nacimiento_texto'] ?? ''));
}

// Cláusula opcional de lugar de nacimiento
$municipioNac = trim((string) ($estudiante['municipio_nacimiento'] ?? ''));
$estadoNac = trim((string) ($estudiante['estado_nacimiento'] ?? ''));

$clausulaLugar = '';
if ($municipioNac !== '' && $estadoNac !== '') {
    $clausulaLugar = ' en el Municipio ' . htmlspecialchars($municipioNac, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ' del Estado ' . htmlspecialchars($estadoNac, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
} elseif ($municipioNac !== '') {
    $clausulaLugar = ' en el Municipio ' . htmlspecialchars($municipioNac, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
} elseif ($estadoNac !== '') {
    $clausulaLugar = ' en el Estado ' . htmlspecialchars($estadoNac, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

if ($clausulaLugar !== '') {
    $clausulaNacimiento = $nacidoPalabra . $clausulaLugar . ', el <strong>' . htmlspecialchars($fechaNacTexto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong>';
} else {
    $clausulaNacimiento = $nacidoPalabra . ' el <strong>' . htmlspecialchars($fechaNacTexto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong>';
}

// 6. Grado escolar en ordinal con letras, nivel y período
$gradoActual = trim((string) ($estudiante['grado'] ?? ($estudiante['grado_origen'] ?? '')));
$numGrado = 0;
if (preg_match('/(\d+)/', $gradoActual, $mGrado)) {
    $numGrado = (int) $mGrado[1];
}
$ordinales = [
    1 => 'PRIMER',
    2 => 'SEGUNDO',
    3 => 'TERCER',
    4 => 'CUARTO',
    5 => 'QUINTO',
    6 => 'SEXTO',
];
$gradoTexto = $ordinales[$numGrado] ?? ($numGrado > 0 ? $numGrado . '°' : mb_strtoupper($gradoActual, 'UTF-8'));

$nivelEducativo = !empty($config['nivel_educativo']) ? $config['nivel_educativo'] : 'Educación Primaria';
$nivelCodigo = !empty($config['nivel_codigo']) ? $config['nivel_codigo'] : '21000';
$periodoEscolar = trim((string) ($estudiante['periodo_escolar'] ?? ''));

$modalidadActual = $modalidad ?? 'activo';
if ($modalidadActual === 'retirado') {
    $clausulaEstudios = 'cursó estudios en esta institución en el <strong>'
        . htmlspecialchars($gradoTexto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '</strong> grado de <strong>'
        . htmlspecialchars($nivelEducativo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '</strong> (código '
        . htmlspecialchars($nivelCodigo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '), durante el período escolar <strong>'
        . htmlspecialchars($periodoEscolar, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '</strong>, habiendo sido retirado(a) de la misma.';
} elseif ($modalidadActual === 'egresado') {
    $clausulaEstudios = 'cursó y culminó estudios en esta institución en el <strong>'
        . htmlspecialchars($gradoTexto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '</strong> grado de <strong>'
        . htmlspecialchars($nivelEducativo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '</strong> (código '
        . htmlspecialchars($nivelCodigo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '), durante el período escolar <strong>'
        . htmlspecialchars($periodoEscolar, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '</strong>, habiendo egresado satisfactoriamente.';
} else {
    $clausulaEstudios = 'cursa estudios en esta institución en el <strong>'
        . htmlspecialchars($gradoTexto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '</strong> grado de <strong>'
        . htmlspecialchars($nivelEducativo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '</strong> (código '
        . htmlspecialchars($nivelCodigo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '), durante el período escolar <strong>'
        . htmlspecialchars($periodoEscolar, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '</strong>.';
}

// 7. Redacción formal completa del cuerpo (texto corrido, justificado)
$cuerpoConstancia = 'Quien suscribe, <strong>' . htmlspecialchars($directorNom, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong>, '
    . 'titular de la Cédula de Identidad N.°&nbsp;<strong>' . htmlspecialchars($dirDocTexto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong>, '
    . htmlspecialchars($cargoDirectorTexto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ' de la Institución Educativa <strong>' . htmlspecialchars($plantelNom, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong>, '
    . 'código DEA <strong>' . htmlspecialchars($plantelDea, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong>, '
    . 'ubicada en el Municipio ' . htmlspecialchars($plantelMunicipio, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ', '
    . 'Parroquia ' . htmlspecialchars($plantelParroquia, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ', '
    . 'adscrita al Centro de Desarrollo de la Calidad Educativa Estadal ' . htmlspecialchars($cdceEstado, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ', '
    . 'hace constar por medio de la presente que ' . $artEstudiante . ' <strong>' . htmlspecialchars($nombreEstudiante, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong>, '
    . $textoDocEstudiante . ', '
    . $clausulaNacimiento . ', '
    . $clausulaEstudios;

// 8. Cierre: Expedición en línea propia
$ciudadExp = !empty($config['lugar_expedicion']) ? $config['lugar_expedicion'] : ($config['plantel_municipio'] ?? 'Valencia');
$diaExpInt = (int) ($diaExp ?? date('j'));
$mesExpLower = mb_strtolower((string) ($mesExpTexto ?? $mesesEspanolLower[(int)date('n')]), 'UTF-8');
$anioExpStr = (string) ($anioExpTexto ?? date('Y'));

$textoDia = ($diaExpInt === 1) ? 'al primer (1) día' : ('a los ' . $diaExpInt . ' días');

$fechaExpedicionTexto = 'Constancia que se expide en ' . htmlspecialchars($ciudadExp, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . ', ' . $textoDia . ' del mes de ' . htmlspecialchars($mesExpLower, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . ' de ' . htmlspecialchars($anioExpStr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '.';

require __DIR__ . '/base.php';
