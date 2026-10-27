<?php
/**
 * Plantilla Oficial: Certificado de Educación Primaria (Egreso).
 *
 * Sigue el formato oficial del Ministerio del Poder Popular para la Educación:
 * - Encabezado: Logo del Ministerio a la izquierda y Escudo Nacional centrado.
 * - Título en 2 líneas: CERTIFICADO / DE EDUCACIÓN PRIMARIA.
 * - Texto corrido formal, justificado, con sangría de primera línea, sin subrayados,
 *   con negrita únicamente en los datos variables y concordancia de género.
 * - Tabla de doble firma y sello: INSTITUCIÓN EDUCATIVA y CENTRO DE DESARROLLO DE LA CALIDAD EDUCATIVA ESTADAL.
 * - Leyenda de validez nacional e internacional y epígrafe anual configurable.
 * - Franja tricolor inferior de borde a borde.
 */

$tituloConstancia    = 'CERTIFICADO';
$subtituloConstancia = 'DE EDUCACIÓN PRIMARIA';
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

// 5. Datos académicos de egreso
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
    . 'certifica por medio de la presente que ' . htmlspecialchars($articuloEst, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . ' <strong>' . htmlspecialchars($nombreEstudiante, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong>, '
    . 'titular de la ' . htmlspecialchars($etiquetaDoc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . ' N.°&nbsp;<strong>' . htmlspecialchars($numDocFormateado, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong>, '
    . $clausulaNacimiento
    . 'cursó el <strong>SEXTO</strong> grado de <strong>' . htmlspecialchars($nivelEducativo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ',</strong> '
    . 'correspondiéndole el literal <strong>"' . htmlspecialchars($literal, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"</strong> '
    . 'durante el período escolar <strong>' . htmlspecialchars($periodoEscolar, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong>, '
    . 'siendo ' . htmlspecialchars($promovidoTexto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . ' al <strong>1.er Año del Nivel de Educación Media</strong>, previo cumplimiento de los requisitos establecidos en la normativa legal vigente.';

// 7. Cierre con fecha en línea propia (mes en minúscula)
$lugarExp = trim((string) ($config['plantel_municipio'] ?? ($config['lugar_expedicion'] ?? 'Valencia')));
if ($lugarExp === '') {
    $lugarExp = 'Valencia';
}
$mesExpMin = mb_strtolower((string) ($mesExpTexto ?? ''), 'UTF-8');
$diaExpTexto = ($diaExp === 1) ? 'al primer (1) día' : ('a los ' . (int) $diaExp . ' días');

$fechaExpedicionTexto = 'Certificado que se expide en ' . htmlspecialchars($lugarExp, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . ', ' . $diaExpTexto . ' del mes de ' . htmlspecialchars($mesExpMin, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . ' de ' . htmlspecialchars((string) ($anioExpTexto ?? date('Y')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '.';

// 8. Leyenda de validez y epígrafe anual configurable
$leyendaValidez = 'Certificado válido a nivel nacional e internacional';
$epigrafeRaw = trim((string) ($config['certificado_epigrafe'] ?? 'BICENTENARIO DE LA UNIDAD AMERICANA: CONGRESO ANFICTIÓNICO DE PANAMÁ'), " \"'«»");
$epigrafePie = $epigrafeRaw !== ''
    ? mb_strtoupper($epigrafeRaw, 'UTF-8')
    : 'BICENTENARIO DE LA UNIDAD AMERICANA: CONGRESO ANFICTIÓNICO DE PANAMÁ';

require __DIR__ . '/base.php';
