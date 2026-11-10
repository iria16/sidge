<?php
declare(strict_types=1);

/**
 * Plantilla Oficial: Constancia de Retiro.
 *
 * Sigue la estructura física ministerial:
 * - Encabezado específico: solo logo del Ministerio del Poder Popular para la Educación,
 *   sin membrete de República Bolivariana ni logo del plantel.
 * - Sin subrayados en el cuerpo ni líneas de relleno (___).
 * - Negrita únicamente en los datos variables (director, cédula, plantel, DEA, estudiante,
 *   documento, nacimiento, grado, nivel, código, período, representante, cédula rep, fecha retiro, motivo).
 * - Cédula de Identidad ("titular de la Cédula de Identidad N.° V-xx.xxx.xxx") vs Cédula Escolar ("titular de la Cédula Escolar N.° V-xxxxxxxxxx").
 * - Formato canónico "N.°" con espacio indivisible (&nbsp;).
 * - Concordancia de género estricta ("el estudiante / la estudiante", "nacido / nacida", "es retirado / es retirada", "Director / Directora").
 * - Grado en ordinal con letras (PRIMER, SEGUNDO, TERCER, CUARTO, QUINTO, SEXTO).
 * - Omisión limpia de cláusula de nacimiento si falta algún dato opcional.
 * - Fecha de expedición en línea propia.
 * - Bloque de firma del Director(a) centrado y franja tricolor oficial al fondo.
 *
 * @var array $config     Configuración institucional
 * @var array $estudiante Datos del estudiante y del retiro
 */

$tituloConstancia = 'CONSTANCIA DE RETIRO';
$mostrarCodigoDependencia = false;
$encabezadoSoloMinisterio = true;

// 1. Autoridad directiva y concordancia
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
    $artEstudiante  = 'la estudiante';
    $nacidoPalabra  = 'nacida';
    $verboRetirado  = 'es retirada';
} elseif ($sexoEst === 'M') {
    $artEstudiante  = 'el estudiante';
    $nacidoPalabra  = 'nacido';
    $verboRetirado  = 'es retirado';
} else {
    $artEstudiante  = 'el/la estudiante';
    $nacidoPalabra  = 'nacido(a)';
    $verboRetirado  = 'es retirado(a)';
}

$nombreEstudiante = mb_strtoupper((string) ($estudiante['nombre_completo'] ?? ''), 'UTF-8');

// 4. Identificación del estudiante
$tipoDocEst = strtoupper(trim((string) ($estudiante['tipo_documento'] ?? '')));
$numDocRawEst = trim((string) ($estudiante['numero_documento'] ?? ''));
$esCedulaEscolar = ($tipoDocEst === 'CE') || str_starts_with(strtoupper($numDocRawEst), 'CE');

if ($esCedulaEscolar) {
    $docNumeroEst = $numDocRawEst;
    if (preg_match('/^([VE])(\d+)$/i', $numDocRawEst, $mCE)) {
        $docNumeroEst = strtoupper($mCE[1]) . '-' . $mCE[2];
    } elseif (ctype_digit($numDocRawEst)) {
        $docNumeroEst = 'V-' . $numDocRawEst;
    }
    $textoDocEstudiante = 'titular de la Cédula Escolar N.°&nbsp;<strong>' . htmlspecialchars($docNumeroEst, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong>';
} else {
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

$municipioNac = trim((string) ($estudiante['municipio_nacimiento'] ?? ''));
$estadoNac = trim((string) ($estudiante['estado_nacimiento'] ?? ''));
$lugarNacimientoExtranjero = trim((string) ($estudiante['lugar_nacimiento_extranjero'] ?? ''));

$clausulaLugar = '';
if ($lugarNacimientoExtranjero !== '') {
    $clausulaLugar = ' en ' . htmlspecialchars($lugarNacimientoExtranjero, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
} elseif ($municipioNac !== '' && $estadoNac !== '') {
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

// 6. Grado cursado, nivel y período
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
$periodoEscolar = trim((string) ($estudiante['periodo_escolar'] ?? ($estudiante['anio_escolar'] ?? '')));

// 7. Representante legal y detalles del retiro
$repNombre = mb_strtoupper(trim((string) ($estudiante['representante_nombre'] ?? '')), 'UTF-8');
$repTipoDoc = strtoupper(trim((string) ($estudiante['representante_tipo_doc'] ?? 'V')));
$repNumDocRaw = trim((string) ($estudiante['representante_num_doc'] ?? ''));

// Si no viene separado, intentar extraer de representante_cedula
if ($repNumDocRaw === '' && !empty($estudiante['representante_cedula'])) {
    if (preg_match('/^([VE])-?(\d+)/i', (string)$estudiante['representante_cedula'], $mRepDoc)) {
        $repTipoDoc = strtoupper($mRepDoc[1]);
        $repNumDocRaw = $mRepDoc[2];
    }
}

$repNumSoloDigitos = preg_replace('/\D/', '', $repNumDocRaw);
$repNumFmt = ($repNumSoloDigitos !== '') ? number_format((int) $repNumSoloDigitos, 0, '', '.') : $repNumDocRaw;
$repDocTexto = ($repTipoDoc === 'E' ? 'E' : 'V') . '-' . $repNumFmt;

// Fecha del retiro
$fechaRetiroRaw = $estudiante['fecha_retiro'] ?? $estudiante['fecha_resolucion'] ?? null;
$fechaRetiroTexto = '—';
if (!empty($fechaRetiroRaw)) {
    $time = strtotime((string) $fechaRetiroRaw);
    if ($time !== false) {
        $diaRet = (int) date('j', $time);
        $mesRet = (int) date('n', $time);
        $anioRet = (int) date('Y', $time);
        $fechaRetiroTexto = $diaRet . ' de ' . ($mesesEspanolLower[$mesRet] ?? '') . ' de ' . $anioRet;
    }
}

$motivoRetiro = mb_strtoupper(trim((string) ($estudiante['motivo'] ?? 'CAMBIO DE RESIDENCIA')), 'UTF-8');

// 8. Construcción del cuerpo formal
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
    . 'cursó el <strong>' . htmlspecialchars($gradoTexto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong> grado de <strong>'
    . htmlspecialchars($nivelEducativo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong> (código <strong>'
    . htmlspecialchars($nivelCodigo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong>), en esta institución durante el período escolar <strong>'
    . htmlspecialchars($periodoEscolar, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong>, '
    . $verboRetirado . ' por su representante legal: <strong>' . htmlspecialchars($repNombre, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong>, '
    . 'titular de la Cédula de Identidad N.°&nbsp;<strong>' . htmlspecialchars($repDocTexto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong>, '
    . 'el día <strong>' . htmlspecialchars($fechaRetiroTexto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong>, '
    . 'Motivo del retiro: <strong>' . htmlspecialchars($motivoRetiro, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong>.';

// 9. Cierre: Expedición en línea propia
$ciudadExp = !empty($config['lugar_expedicion']) ? $config['lugar_expedicion'] : ($config['plantel_municipio'] ?? 'Valencia');
$diaExpInt = (int) ($diaExp ?? date('j'));
$mesExpLower = mb_strtolower((string) ($mesExpTexto ?? $mesesEspanolLower[(int)date('n')]), 'UTF-8');
$anioExpStr = (string) ($anioExpTexto ?? date('Y'));

$textoDia = ($diaExpInt === 1) ? 'al primer (1) día' : ('a los ' . $diaExpInt . ' días');

$fechaExpedicionTexto = 'Constancia que se expide en ' . htmlspecialchars($ciudadExp, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . ', ' . $textoDia . ' del mes de ' . htmlspecialchars($mesExpLower, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . ' de ' . htmlspecialchars($anioExpStr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '.';

require __DIR__ . '/base.php';
