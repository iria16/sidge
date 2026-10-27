<?php
declare(strict_types=1);

/**
 * Plantilla Oficial: Cuadernillo de Certificados de Educación Primaria (Egreso por Lote / Sección).
 *
 * Emite múltiples certificados de educación primaria con el formato oficial ministerial,
 * aplicando salto de página (.page-break) entre estudiantes y manteniendo el cintillo
 * tricolor institucional al pie de cada página.
 */

$tituloDocumento = 'CERTIFICADO DE EDUCACIÓN PRIMARIA';
$totalEstudiantes = count($estudiantes);

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
$nivelEducativo    = trim((string) ($config['nivel_educativo'] ?? 'Educación Primaria'));

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

$lugarExp = trim((string) ($config['plantel_municipio'] ?? ($config['lugar_expedicion'] ?? 'Valencia')));
if ($lugarExp === '') {
    $lugarExp = 'Valencia';
}
$mesExpMin = mb_strtolower((string) ($mesExpTexto ?? ''), 'UTF-8');
$diaExpTexto = ($diaExp === 1) ? 'al primer (1) día' : ('a los ' . (int) $diaExp . ' días');
$fechaExpedicionTexto = 'Certificado que se expide en ' . htmlspecialchars($lugarExp, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . ', ' . $diaExpTexto . ' del mes de ' . htmlspecialchars($mesExpMin, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . ' de ' . htmlspecialchars((string) ($anioExpTexto ?? date('Y')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '.';

$epigrafeRaw = trim((string) ($config['certificado_epigrafe'] ?? 'BICENTENARIO DE LA UNIDAD AMERICANA: CONGRESO ANFICTIÓNICO DE PANAMÁ'), " \"'«»");
$epigrafePie = $epigrafeRaw !== ''
    ? mb_strtoupper($epigrafeRaw, 'UTF-8')
    : 'BICENTENARIO DE LA UNIDAD AMERICANA: CONGRESO ANFICTIÓNICO DE PANAMÁ';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($tituloDocumento, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></title>
  <style>
    @page {
      size: letter portrait;
      margin: 0;
    }
    body {
      margin: 0;
      padding: 0;
      font-family: 'Times New Roman', 'Times-Roman', Times, serif;
      color: #000000;
      background: #ffffff;
      font-size: 12pt;
      line-height: 1.5;
    }
    .page-break {
      page-break-after: always;
    }
    .page-container {
      margin: 71pt auto 0 auto;
      width: 470pt;
      position: relative;
    }
    .header-ministerio-escudo {
      width: 100%;
      margin-bottom: 10pt;
    }
    .header-min-top {
      text-align: left;
      margin-bottom: 6pt;
    }
    .header-min-top img {
      max-width: 175pt;
      max-height: 48pt;
      height: auto;
      display: block;
    }
    .header-escudo-below {
      text-align: center;
      margin-top: 2pt;
      margin-bottom: 6pt;
    }
    .header-escudo-below img {
      max-height: 56pt;
      width: auto;
      display: inline-block;
    }
    .doc-title {
      text-align: center;
      font-size: 15pt;
      font-weight: bold;
      letter-spacing: 0.5pt;
      text-transform: uppercase;
      margin-bottom: 16pt;
      line-height: 1.25;
    }
    .doc-title-sub {
      font-size: 14pt;
      font-weight: bold;
      margin-top: 2pt;
    }
    .doc-body {
      text-align: justify;
      font-size: 12pt;
      line-height: 1.5;
      text-indent: 1.25cm;
      margin-bottom: 18pt;
    }
    .doc-body strong {
      font-weight: bold;
    }
    .doc-closing {
      text-align: justify;
      font-size: 12pt;
      line-height: 1.5;
      text-indent: 1.25cm;
      margin-bottom: 0;
    }
    .tabla-doble-firma {
      width: 100%;
      border-collapse: collapse;
      margin-top: 16pt;
    }
    .tabla-doble-firma th,
    .tabla-doble-firma td {
      border: 1px solid #000000;
      padding: 4pt 6pt;
      font-size: 9.5pt;
      line-height: 1.25;
      width: 50%;
    }
    .tabla-doble-firma th {
      text-align: center;
      font-weight: bold;
      text-transform: uppercase;
      vertical-align: middle;
      height: 24pt;
    }
    .tabla-doble-firma .fila-cargo td {
      font-weight: bold;
      text-transform: uppercase;
      text-align: left;
    }
    .tabla-doble-firma .celda-firma-sello {
      height: 82pt;
      vertical-align: top;
      position: relative;
    }
    .doble-firma-area {
      text-align: center;
      margin-top: 6pt;
      position: relative;
      height: 60pt;
    }
    .doble-firma-area .sign-img {
      max-height: 44pt;
      max-width: 120pt;
      vertical-align: middle;
    }
    .doble-firma-area .sello-img-inline {
      max-height: 62pt;
      max-width: 68pt;
      vertical-align: middle;
      margin-left: 6pt;
    }
    .footer-validity {
      margin-top: 10pt;
      font-size: 10pt;
      font-weight: bold;
      text-align: left;
    }
    .footer-epigrafe {
      margin-top: 8pt;
      font-size: 9.5pt;
      font-weight: bold;
      text-align: center;
      text-transform: uppercase;
    }
    .footer-banner {
      position: fixed;
      bottom: 0;
      left: 0;
      width: 100%;
      z-index: -1;
      line-height: 0;
      margin: 0;
      padding: 0;
    }
    .footer-banner img {
      width: 100%;
      height: auto;
      display: block;
      margin: 0;
      padding: 0;
    }
    <?php if (!empty($marcaAguaPrueba)): ?>
    .watermark-prueba {
      position: fixed;
      top: 38%;
      left: 0;
      width: 100%;
      text-align: center;
      font-size: 80pt;
      font-family: 'Helvetica', 'Arial', sans-serif;
      font-weight: bold;
      color: #d6d6d6;
      opacity: 0.28;
      transform: rotate(-35deg);
      z-index: 1000;
      letter-spacing: 8px;
    }
    <?php endif; ?>
  </style>
</head>
<body>

<?php if (!empty($marcaAguaPrueba)): ?>
  <div class="watermark-prueba">PRUEBA</div>
<?php endif; ?>

<?php foreach ($estudiantes as $idx => $estudiante):
  $isLastStudent    = ($idx === $totalEstudiantes - 1);
  $sexoRaw          = strtoupper(trim((string) ($estudiante['sexo'] ?? '')));
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
  $tipoDoc          = strtoupper(trim((string) ($estudiante['tipo_documento'] ?? 'V')));
  $numDocRaw        = trim((string) ($estudiante['numero_documento'] ?? ''));
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

  $munNac = trim((string) ($estudiante['municipio_nacimiento'] ?? ''));
  $edoNac = trim((string) ($estudiante['estado_nacimiento'] ?? ''));
  $fechaNacTexto = (string) ($estudiante['fecha_nacimiento_texto'] ?? '');
  $fechaNacPartes = explode(' DE ', $fechaNacTexto);
  if (count($fechaNacPartes) === 3) {
      $diaNacNum = (int) $fechaNacPartes[0];
      $mesNacMin = mb_strtolower($fechaNacPartes[1], 'UTF-8');
      $anioNacNum = trim($fechaNacPartes[2]);
      $fechaNacFormal = "{$diaNacNum} de {$mesNacMin} de {$anioNacNum}";
  } else {
      $fechaNacFormal = mb_strtolower($fechaNacTexto, 'UTF-8');
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

  $literal        = mb_strtoupper(trim((string) ($estudiante['literal'] ?? 'A')), 'UTF-8');
  $periodoEscolar = trim((string) ($estudiante['periodo_escolar'] ?? ''));
?>
<div class="page-container <?= !$isLastStudent ? 'page-break' : '' ?>">
  <!-- Encabezado con Logo Ministerial arriba a la izquierda y Escudo Nacional debajo centrado -->
  <div class="header-ministerio-escudo">
    <div class="header-min-top">
      <?php if (!empty($ministerioLogo)): ?>
        <img src="<?= $ministerioLogo ?>" alt="Ministerio del Poder Popular para la Educación">
      <?php endif; ?>
    </div>
    <div class="header-escudo-below">
      <?php if (!empty($escudoNacionalUri)): ?>
        <img src="<?= $escudoNacionalUri ?>" alt="Escudo Nacional de Venezuela">
      <?php endif; ?>
    </div>
  </div>

  <!-- Título oficial del certificado -->
  <div class="doc-title">
    <div>CERTIFICADO</div>
    <div class="doc-title-sub">DE EDUCACIÓN PRIMARIA</div>
  </div>

  <!-- Párrafo de certificación oficial -->
  <div class="doc-body">
    Quien suscribe, <strong><?= htmlspecialchars($dirNombre, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong>, titular de la Cédula de Identidad N.°&nbsp;<?= htmlspecialchars($dirDoc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>, <?= htmlspecialchars($cargoTexto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> de la Institución Educativa <strong><?= htmlspecialchars($institucionNombre, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong>, código DEA <strong><?= htmlspecialchars($codigoDea, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong>, <?= $clausulaUbicacion ?><?= $clausulaCdce ?>certifica por medio de la presente que <?= htmlspecialchars($articuloEst, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> <strong><?= htmlspecialchars($nombreEstudiante, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong>, titular de la <?= htmlspecialchars($etiquetaDoc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> N.°&nbsp;<strong><?= htmlspecialchars($numDocFormateado, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong>, <?= $clausulaNacimiento ?>cursó el <strong>SEXTO</strong> grado de <strong><?= htmlspecialchars($nivelEducativo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>,</strong> correspondiéndole el literal <strong>"<?= htmlspecialchars($literal, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"</strong> durante el período escolar <strong><?= htmlspecialchars($periodoEscolar, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong>, siendo <?= htmlspecialchars($promovidoTexto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> al <strong>1.er Año del Nivel de Educación Media</strong>, previo cumplimiento de los requisitos establecidos en la normativa legal vigente.
  </div>

  <!-- Expedición -->
  <div class="doc-closing">
    <?= $fechaExpedicionTexto ?>
  </div>

  <!-- Tabla oficial de Doble Firma y Sello -->
  <table class="tabla-doble-firma">
    <thead>
      <tr>
        <th>INSTITUCIÓN EDUCATIVA</th>
        <th>CENTRO DE DESARROLLO DE LA CALIDAD EDUCATIVA ESTADAL</th>
      </tr>
    </thead>
    <tbody>
      <tr class="fila-cargo">
        <td>DIRECTOR(A)</td>
        <td>DIRECTOR(A)</td>
      </tr>
      <tr>
        <td><strong>Nombre y Apellido:</strong> <?= htmlspecialchars($dirNombre, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
        <td><strong>Nombre y Apellido:</strong> <?= !empty($cdceDirNombre) ? htmlspecialchars((string) $cdceDirNombre, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '' ?></td>
      </tr>
      <tr>
        <td><strong>Número de C.I.:</strong> <?= htmlspecialchars($dirDoc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
        <td><strong>Número de C.I.:</strong> <?= !empty($cdceDirDocTexto) ? htmlspecialchars((string) $cdceDirDocTexto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '' ?></td>
      </tr>
      <tr>
        <td class="celda-firma-sello">
          <div><strong>Firma y Sello:</strong></div>
          <div class="doble-firma-area">
            <?php if (!empty($firmaUri)): ?>
              <img src="<?= $firmaUri ?>" class="sign-img" alt="Firma Director(a)">
            <?php endif; ?>
            <?php if (!empty($selloUri)): ?>
              <img src="<?= $selloUri ?>" class="sello-img-inline" alt="Sello Institucional">
            <?php endif; ?>
          </div>
        </td>
        <td class="celda-firma-sello">
          <div><strong>Firma y Sello:</strong></div>
          <div class="doble-firma-area">
            <?php if (!empty($cdceFirmaUri)): ?>
              <img src="<?= $cdceFirmaUri ?>" class="sign-img" alt="Firma Director(a) CDCE">
            <?php endif; ?>
            <?php if (!empty($cdceSelloUri)): ?>
              <img src="<?= $cdceSelloUri ?>" class="sello-img-inline" alt="Sello CDCE Estadal">
            <?php endif; ?>
          </div>
        </td>
      </tr>
    </tbody>
  </table>

  <!-- Leyenda y Epígrafe oficial -->
  <div class="footer-validity">Certificado válido a nivel nacional e internacional</div>
  <div class="footer-epigrafe">"<?= htmlspecialchars($epigrafePie, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"</div>
</div>
<?php endforeach; ?>

<!-- Franja tricolor al pie repetida en todas las páginas -->
<?php if (!empty($cintilloLogo)): ?>
  <div class="footer-banner">
    <img src="<?= $cintilloLogo ?>" alt="Cintillo Tricolor">
  </div>
<?php endif; ?>

</body>
</html>
