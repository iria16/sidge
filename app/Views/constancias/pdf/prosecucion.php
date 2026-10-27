<?php
/**
 * Plantilla: Constancia de Prosecución en Nivel de Educación Primaria.
 *
 * Sigue el formato oficial del Ministerio del Poder Popular para la Educación:
 * - Encabezado: Logo del Ministerio a la izquierda y Escudo Nacional centrado.
 * - Título en 2 líneas: CONSTANCIA DE PROSECUCIÓN / EN NIVEL DE EDUCACIÓN PRIMARIA.
 * - Texto corrido formal, justificado, con sangría de primera línea, sin subrayados,
 *   con negrita únicamente en los datos variables y concordancia de género.
 * - Tabla de doble firma y sello: INSTITUCIÓN EDUCATIVA y CENTRO DE DESARROLLO DE LA CALIDAD EDUCATIVA ESTADAL.
 * - Franja tricolor inferior de borde a borde.
 */

$tituloConstancia    = 'CONSTANCIA DE PROSECUCIÓN';
$subtituloConstancia = 'EN NIVEL DE EDUCACIÓN PRIMARIA';
$encabezadoConEscudo = true;
$dobleFirmaCdce      = true;

// 1. Datos del director(a) e institución
$dirNombre  = mb_strtoupper(trim((string) ($config['director_nombre'] ?? '')), 'UTF-8');
$dirDoc     = $dirDocTexto ?? (($dirLetra ?? 'V') . '-' . ($dirNumFormatted ?? ''));
$cargoRaw   = trim((string) ($config['director_cargo'] ?? 'Director(a)'));
$cargoLower = mb_strtolower($cargoRaw, 'UTF-8');
if (str_contains($cargoLower, 'directora')) {
    $cargoTexto = 'Directora';
} elseif (str_contains($cargoLower, 'director') && !str_contains($cargoLower, 'director(a)') && !str_contains($cargoLower, 'director (e)')) {
    $cargoTexto = 'Director';
} else {
    $cargoTexto = 'Director(a)';
}

$institucionNombre = mb_strtoupper(trim((string) ($config['plantel_nombre'] ?? '')), 'UTF-8');
$codigoDea         = mb_strtoupper(trim((string) ($config['plantel_codigo_dea'] ?? '')), 'UTF-8');
$municipioPlantel  = trim((string) ($config['plantel_municipio'] ?? ''));
$parroquiaPlantel  = trim((string) ($config['plantel_parroquia'] ?? ''));
$cdceEstado        = trim((string) ($config['cdce_estado'] ?? ''));

// 2. Concordancia de género y datos del estudiante
$sexoRaw = strtoupper(trim((string) ($estudiante['sexo'] ?? '')));
if ($sexoRaw === 'M' || $sexoRaw === 'MASCULINO') {
    $articuloEst    = 'el estudiante';
    $nacidoPalabra  = 'nacido';
    $promovidoTexto = 'promovido';
} elseif ($sexoRaw === 'F' || $sexoRaw === 'FEMENINO') {
    $articuloEst    = 'la estudiante';
    $nacidoPalabra  = 'nacida';
    $promovidoTexto = 'promovida';
} else {
    $articuloEst    = 'el(la) estudiante';
    $nacidoPalabra  = 'nacido(a)';
    $promovidoTexto = 'promovido(a)';
}

$nombreEstudiante = mb_strtoupper(trim((string) ($estudiante['nombre_completo'] ?? '')), 'UTF-8');

// 3. Documento del estudiante (Cédula de Identidad o Cédula Escolar)
$tipoDoc = strtoupper(trim((string) ($estudiante['tipo_documento'] ?? 'V')));
$numDocRaw = trim((string) ($estudiante['numero_documento'] ?? ''));
if ($tipoDoc === 'CE') {
    $etiquetaDoc = 'Cédula Escolar';
    if (!preg_match('/^[VE]-/i', $numDocRaw)) {
        $numDocFormateado = 'V-' . preg_replace('/^(?:CE|V)[-.\s]*/i', '', $numDocRaw);
    } else {
        $numDocFormateado = strtoupper($numDocRaw);
    }
} else {
    $etiquetaDoc = 'Cédula de Identidad';
    $prefijo = ($tipoDoc === 'E') ? 'E' : 'V';
    $soloDigitos = preg_replace('/\D/', '', $numDocRaw);
    if ($soloDigitos !== '' && strlen($soloDigitos) <= 9) {
        $numDocFormateado = $prefijo . '-' . number_format((int) $soloDigitos, 0, '', '.');
    } else {
        $numDocFormateado = $prefijo . '-' . ltrim($numDocRaw, 'VvEe-. ');
    }
}

// 4. Lugar y fecha de nacimiento
$munNac = trim((string) ($estudiante['municipio_nacimiento'] ?? ''));
$edoNac = trim((string) ($estudiante['estado_nacimiento'] ?? ''));

$fechaNacPartes = explode(' DE ', (string) ($fechaNacTexto ?? ''));
if (count($fechaNacPartes) === 3) {
    $diaNacNum = (int) $fechaNacPartes[0];
    $mesNacMin = mb_strtolower($fechaNacPartes[1], 'UTF-8');
    $anioNacNum = trim($fechaNacPartes[2]);
    $fechaNacFormal = "{$diaNacNum} de {$mesNacMin} de {$anioNacNum}";
} else {
    $fechaNacFormal = mb_strtolower((string) ($fechaNacTexto ?? ''), 'UTF-8');
}

if ($munNac !== '' && $edoNac !== '') {
    $clausulaNacimiento = htmlspecialchars($nacidoPalabra, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . ' en el Municipio ' . htmlspecialchars($munNac, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . ' del Estado ' . htmlspecialchars($edoNac, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . ', el <strong>' . htmlspecialchars($fechaNacFormal, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong>, ';
} elseif ($edoNac !== '') {
    $clausulaNacimiento = htmlspecialchars($nacidoPalabra, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . ' en el Estado ' . htmlspecialchars($edoNac, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . ', el <strong>' . htmlspecialchars($fechaNacFormal, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong>, ';
} else {
    $clausulaNacimiento = htmlspecialchars($nacidoPalabra, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . ' el <strong>' . htmlspecialchars($fechaNacFormal, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong>, ';
}

// 5. Grado cursado, literal, período y grado destino en ordinal con letras
$ordinales = [
    1 => 'PRIMER',
    2 => 'SEGUNDO',
    3 => 'TERCER',
    4 => 'CUARTO',
    5 => 'QUINTO',
    6 => 'SEXTO',
];

$gradoOrigenRaw = trim((string) ($estudiante['grado_origen'] ?? $estudiante['grado'] ?? ''));
if (preg_match('/(\d+)/', $gradoOrigenRaw, $m)) {
    $numGradoOrigen = (int) $m[1];
    $gradoOrigenOrdinal = $ordinales[$numGradoOrigen] ?? mb_strtoupper($gradoOrigenRaw, 'UTF-8');
} else {
    $numGradoOrigen = 0;
    $gradoOrigenOrdinal = mb_strtoupper(preg_replace('/\s*GRADO\s*/iu', '', $gradoOrigenRaw) ?? $gradoOrigenRaw, 'UTF-8');
}

$gradoDestinoRaw = trim((string) ($estudiante['grado_destino'] ?? ''));
if (preg_match('/(\d+)/', $gradoDestinoRaw, $m)) {
    $gradoDestinoOrdinal = $ordinales[(int) $m[1]] ?? mb_strtoupper($gradoDestinoRaw, 'UTF-8');
} elseif ($gradoDestinoRaw !== '') {
    $gradoDestinoOrdinal = mb_strtoupper(preg_replace('/\s*GRADO\s*/iu', '', $gradoDestinoRaw) ?? $gradoDestinoRaw, 'UTF-8');
} elseif ($numGradoOrigen >= 1 && $numGradoOrigen < 6) {
    $gradoDestinoOrdinal = $ordinales[$numGradoOrigen + 1];
} else {
    $gradoDestinoOrdinal = 'SIGUIENTE';
}

$nivelEducativo = trim((string) ($config['nivel_educativo'] ?? 'Educación Primaria'));
$literal        = mb_strtoupper(trim((string) ($estudiante['literal'] ?? 'A')), 'UTF-8');
$periodoEscolar = trim((string) ($estudiante['periodo_escolar'] ?? ''));

// Cláusulas territoriales opcionales del plantel
$ubicacionPartes = [];
if ($municipioPlantel !== '') {
    $ubicacionPartes[] = 'ubicada en el Municipio ' . htmlspecialchars($municipioPlantel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
if ($parroquiaPlantel !== '') {
    $ubicacionPartes[] = 'Parroquia ' . htmlspecialchars($parroquiaPlantel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
$clausulaUbicacion = $ubicacionPartes !== [] ? (implode(', ', $ubicacionPartes) . ', ') : '';

$clausulaCdce = $cdceEstado !== ''
    ? ('adscrita al Centro de Desarrollo de la Calidad Educativa Estadal ' . htmlspecialchars($cdceEstado, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ', ')
    : '';

// 6. Redacción formal del cuerpo (sin subrayados, negrita solo en datos variables)
$cuerpoConstancia = 'Quien suscribe, <strong>' . htmlspecialchars($dirNombre, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong>, '
    . 'titular de la Cédula de Identidad N.°&nbsp;' . htmlspecialchars($dirDoc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ', '
    . htmlspecialchars($cargoTexto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ' de la Institución Educativa <strong>'
    . htmlspecialchars($institucionNombre, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong>, '
    . 'código DEA <strong>' . htmlspecialchars($codigoDea, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong>, '
    . $clausulaUbicacion
    . $clausulaCdce
    . 'hace constar por medio de la presente que ' . htmlspecialchars($articuloEst, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . ' <strong>' . htmlspecialchars($nombreEstudiante, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong>, '
    . 'titular de la ' . htmlspecialchars($etiquetaDoc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . ' N.°&nbsp;<strong>' . htmlspecialchars($numDocFormateado, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong>, '
    . $clausulaNacimiento
    . 'cursó el <strong>' . htmlspecialchars($gradoOrigenOrdinal, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong> grado de '
    . '<strong>' . htmlspecialchars($nivelEducativo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ',</strong> '
    . 'correspondiéndole el literal <strong>"' . htmlspecialchars($literal, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"</strong> '
    . 'durante el período escolar <strong>' . htmlspecialchars($periodoEscolar, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong>, '
    . 'siendo ' . htmlspecialchars($promovidoTexto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ' al '
    . '<strong>' . htmlspecialchars($gradoDestinoOrdinal, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong> grado del Nivel de '
    . '<strong>' . htmlspecialchars($nivelEducativo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong>.';

// 7. Cierre con fecha en línea propia (mes en minúscula)
$lugarExp = trim((string) ($config['plantel_municipio'] ?? ($config['lugar_expedicion'] ?? 'Valencia')));
if ($lugarExp === '') {
    $lugarExp = 'Valencia';
}
$mesExpMin = mb_strtolower((string) ($mesExpTexto ?? ''), 'UTF-8');
$diaExpTexto = ($diaExp === 1) ? 'al primer (1) día' : ('a los ' . (int) $diaExp . ' días');

$fechaExpedicionTexto = 'Constancia que se expide en ' . htmlspecialchars($lugarExp, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . ', ' . $diaExpTexto . ' del mes de ' . htmlspecialchars($mesExpMin, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . ' de ' . htmlspecialchars((string) ($anioExpTexto ?? date('Y')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '.';

require __DIR__ . '/base.php';
