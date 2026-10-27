<?php
declare(strict_types=1);

/**
 * Plantilla Oficial: Certificados de Calificaciones por Sección (Múltiples Estudiantes).
 * Cuadernillo formal según Art. 108 RGLOE.
 *
 * Características de diseño:
 * - Márgenes APA: 2.5 cm.
 * - Fuente: Times New Roman, color #000000.
 * - Ritmo vertical fijo:
 *   Encabezado -> 24pt -> Título -> 6pt -> AÑO ESCOLAR -> 30pt -> Cuerpo
 *   Cuerpo: 12pt, interlineado 1.5, sangría de 1.25cm, espacio antes/después de 18pt.
 *   Recuadro de la escala: separación 18pt arriba/abajo, padding 8pt, sangría francesa.
 *   Fecha: espacio 18pt antes, sangría de 1.25cm, coherente con el cuerpo.
 *   Firmas: 48pt de espacio antes, tabla 40% / 20% / 40%, líneas iguales, SELLO en círculo gris tenue.
 * - Cada estudiante en exactamente 1 página carta independiente.
 */

$totalEst = count($estudiantes);
$directorNom = mb_strtoupper((string) ($config['director_nombre'] ?? $directorNombre ?? ''), 'UTF-8');
$cargoDirector = trim((string) ($config['director_cargo'] ?? 'Director(a)'));
if ($cargoDirector === '') {
    $cargoDirector = 'Director(a)';
}
$nombrePlantel = trim((string) ($config['plantel_nombre'] ?? 'Escuela Básica "Isidro Ramírez"'));

$dirCedulaLimpia = preg_replace('/\D/', '', (string) ($config['director_cedula'] ?? $directorCi ?? ''));
$dirTipo = preg_match('/^[VE]/i', (string) ($config['director_cedula'] ?? $directorCi ?? ''), $mDir) ? strtoupper($mDir[0]) : 'V';
$directorDoc = $dirCedulaLimpia !== '' ? ($dirTipo . '-' . number_format((int)$dirCedulaLimpia, 0, '', '.')) : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Certificados de Calificaciones de la Sección</title>
  <style>
    @page {
      size: letter portrait;
      margin: 2.5cm;
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
    .header-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 24pt;
    }
    .header-table td {
      vertical-align: middle;
      padding: 0;
    }
    .col-min-logo {
      width: 24%;
      text-align: left;
      vertical-align: middle;
    }
    .col-min-logo img {
      width: 100%;
      max-width: 112pt;
      height: auto;
      display: block;
    }
    .col-box-center {
      width: 58%;
      text-align: center;
      vertical-align: middle;
      padding: 0 2pt;
    }
    .membrete-texto {
      border: none;
      padding: 0;
      font-size: 9pt;
      line-height: 1.25;
      text-align: center;
      font-weight: bold;
      text-transform: uppercase;
    }
    .col-plantel-logo {
      width: 18%;
      text-align: right;
      vertical-align: middle;
    }
    .col-plantel-logo img {
      height: 40pt;
      width: auto;
      display: inline-block;
      vertical-align: middle;
    }
    .doc-title {
      text-align: center;
      font-size: 15pt;
      font-weight: bold;
      letter-spacing: 0.5pt;
      text-transform: uppercase;
      margin-bottom: 6pt;
    }
    .doc-periodo {
      text-align: center;
      font-size: 12pt;
      font-weight: bold;
      text-transform: uppercase;
      margin-bottom: 30pt;
    }
    .cuerpo-legal {
      text-align: justify;
      font-size: 12pt;
      line-height: 1.5;
      text-indent: 1.25cm;
      margin-top: 18pt;
      margin-bottom: 12pt;
    }
    .cuerpo-legal strong {
      font-weight: bold;
    }
    .cuerpo-promocion {
      text-align: justify;
      font-size: 12pt;
      line-height: 1.5;
      text-indent: 1.25cm;
      margin-top: 0;
      margin-bottom: 18pt;
    }
    .cuerpo-promocion strong {
      font-weight: bold;
    }
    .recuadro-escala {
      border: none;
      padding: 0;
      margin-top: 18pt;
      margin-bottom: 18pt;
      font-size: 9pt;
      line-height: 1.35;
    }
    .recuadro-escala__titulo {
      font-weight: bold;
      text-align: center;
      font-size: 9.5pt;
      margin-bottom: 4pt;
    }
    .recuadro-escala__intro {
      text-align: justify;
      margin-bottom: 6pt;
      font-size: 8.5pt;
      line-height: 1.3;
    }
    .recuadro-escala table {
      width: 100%;
      border-collapse: collapse;
    }
    .recuadro-escala td {
      vertical-align: top;
      padding: 1.5pt 0;
    }
    .escala-letra {
      width: 16pt;
      font-weight: bold;
      font-size: 9pt;
      line-height: 1.35;
    }
    .escala-desc {
      text-align: justify;
      font-size: 8.5pt;
      line-height: 1.35;
    }
    .fecha-expedicion {
      font-size: 12pt;
      line-height: 1.5;
      text-align: justify;
      text-indent: 1.25cm;
      margin-top: 18pt;
      margin-bottom: 0;
    }
    .fecha-expedicion strong {
      font-weight: bold;
    }
    .seccion-firmas {
      margin-top: 84pt;
    }
    .signature-table {
      width: 100%;
      border-collapse: collapse;
      table-layout: fixed;
    }
    .signature-table td {
      vertical-align: top;
      padding: 0;
    }
    .firm-col {
      width: 40%;
      text-align: center;
    }
    .seal-col {
      width: 20%;
      text-align: center;
      vertical-align: top;
      position: relative;
    }
    .firma-linea {
      width: 160pt;
      border-top: 1px solid #000000;
      margin: 0 auto 6pt auto;
    }
    .firma-nombre {
      font-weight: bold;
      font-size: 10pt;
      line-height: 1.25;
      text-transform: uppercase;
      white-space: nowrap;
    }
    .firma-cargo {
      font-size: 10pt;
      line-height: 1.25;
      font-weight: normal;
      margin-top: 2pt;
    }
    .firma-ci {
      font-size: 10pt;
      line-height: 1.25;
      font-weight: normal;
      margin-top: 2pt;
    }
    .sello-wrap {
      position: relative;
      width: 100%;
      height: 0;
      line-height: 0;
      margin: 0;
      padding: 0;
      text-align: center;
    }
    .sello-img {
      width: 90pt;
      max-width: 95pt;
      height: auto;
      margin-top: -30pt;
      display: inline-block;
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

<?php foreach ($estudiantes as $idx => $estudiante): ?>
  <?php
  $gradoActual = trim((string) ($estudiante['grado_origen'] ?? $estudiante['grado'] ?? $gradoCursado ?? ''));
  $seccionNombre = trim((string) ($estudiante['seccion'] ?? ''));

  $numGrado = 0;
  if (preg_match('/(\d+)/', $gradoActual, $m)) {
      $numGrado = (int) $m[1];
  }

  $ordinales = [
      1 => 'PRIMER',
      2 => 'SEGUNDO',
      3 => 'TERCER',
      4 => 'CUARTO',
      5 => 'QUINTO',
      6 => 'SEXTO',
  ];

  $gradoActualTexto = $ordinales[$numGrado] ?? ($numGrado > 0 ? $numGrado . '°' : mb_strtoupper($gradoActual, 'UTF-8'));
  $esSexto = ($numGrado === 6) || (bool) preg_match('/^6|sexto/i', $gradoActual);

  $numSiguiente = $numGrado + 1;
  if ($esSexto) {
      $gradoDestinoTexto = '1er Año del Nivel de Educación Media';
  } else {
      $gradoDestinoTexto = ($ordinales[$numSiguiente] ?? ($numSiguiente . '°')) . ' grado';
  }

  $seccionTexto = $seccionNombre !== '' ? mb_strtoupper($seccionNombre, 'UTF-8') : 'A';

  $periodoEscolarLimpio = trim((string) ($periodoEscolar ?? $estudiante['periodo_escolar'] ?? ''));
  if (preg_match('/^(\d{4})\s*-\s*(\d{4})$/', $periodoEscolarLimpio, $mPeriodo)) {
      $siguientePeriodo = ((int)$mPeriodo[1] + 1) . '-' . ((int)$mPeriodo[2] + 1);
  } else {
      $siguientePeriodo = 'siguiente';
  }

  $literalUpper = strtoupper(trim((string) ($estudiante['literal'] ?? '')));
  $esPromovido = in_array($literalUpper, ['A', 'B', 'C', 'D'], true);

  $sexo = strtoupper(trim((string) ($estudiante['sexo'] ?? '')));
  if ($sexo === 'F') {
      $artEstudiante    = 'La estudiante';
      $promovidoPalabra = 'PROMOVIDA';
  } elseif ($sexo === 'M') {
      $artEstudiante    = 'El estudiante';
      $promovidoPalabra = 'PROMOVIDO';
  } else {
      $artEstudiante    = 'El/La estudiante';
      $promovidoPalabra = 'PROMOVIDO(A)';
  }

  $nombreEst = mb_strtoupper((string) ($estudiante['nombre_completo'] ?? ''), 'UTF-8');

  // Determinación estricta de Cédula de Identidad vs Cédula Escolar
  $tipoDoc = strtoupper(trim((string) ($estudiante['tipo_documento'] ?? '')));
  $numDocRaw = trim((string) ($estudiante['numero_documento'] ?? ''));
  $numDocSoloDigitos = preg_replace('/\D/', '', $numDocRaw);

  if ($tipoDoc === 'CE' || str_starts_with(strtoupper($numDocRaw), 'CE')) {
      $textoDocIdentidad = 'titular de la <strong>Cédula Escolar N.° CE-' . htmlspecialchars($numDocSoloDigitos !== '' ? $numDocSoloDigitos : $numDocRaw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong>';
  } else {
      $letraDoc = ($tipoDoc === 'E') ? 'E' : 'V';
      $numFmt = $numDocSoloDigitos !== '' ? number_format((int) $numDocSoloDigitos, 0, '', '.') : $numDocRaw;
      $textoDocIdentidad = 'titular de la <strong>Cédula de Identidad N.° ' . $letraDoc . '-' . htmlspecialchars($numFmt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong>';
  }

  $docenteNom = mb_strtoupper((string) ($estudiante['docente_nombre'] ?? $docenteNombre ?? ''), 'UTF-8');

  $docNumRaw = preg_replace('/\D/', '', (string) ($estudiante['docente_ci'] ?? $docenteCi ?? ''));
  $docTipo = preg_match('/^[VE]/i', (string) ($estudiante['docente_ci'] ?? $docenteCi ?? ''), $mDoc) ? strtoupper($mDoc[0]) : 'V';
  $docenteDoc = $docNumRaw !== '' ? ($docTipo . '-' . number_format((int)$docNumRaw, 0, '', '.')) : '';

  $ciudadLimpia = trim((string) ($config['lugar_expedicion'] ?? $config['plantel_municipio'] ?? 'Valencia'));
  if ($ciudadLimpia === '') {
      $ciudadLimpia = 'Valencia';
  }
  $mesLower = mb_strtolower((string) $mesExpTexto, 'UTF-8');
  $plantelDea = trim((string) ($config['plantel_codigo_dea'] ?? ''));
  $codigoDependencia = trim((string) ($codigoDependencia ?? $config['plantel_codigo_dependencia'] ?? ''));
  ?>

  <?php if (!empty($marcaAguaPrueba)): ?>
    <div class="watermark-prueba">PRUEBA</div>
  <?php endif; ?>

  <!-- Encabezado Institucional (20% / 60% / 20%) alineado al centro -->
  <table class="header-table">
    <tr>
      <td class="col-min-logo">
        <?php if (!empty($ministerioLogo)): ?>
          <img src="<?= $ministerioLogo ?>" alt="Ministerio">
        <?php endif; ?>
      </td>
      <td class="col-box-center">
        <div class="membrete-texto">
          <div>REPÚBLICA BOLIVARIANA DE VENEZUELA</div>
          <div>MINISTERIO DEL PODER POPULAR PARA LA EDUCACIÓN</div>
          <div><?= htmlspecialchars(mb_strtoupper($nombrePlantel, 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          <?php if (!empty($plantelDea)): ?>
            <div>DEA: <?= htmlspecialchars(mb_strtoupper($plantelDea, 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          <?php endif; ?>
          <?php if (!empty($codigoDependencia)): ?>
            <div>CÓDIGO DE DEPENDENCIA <?= htmlspecialchars(mb_strtoupper($codigoDependencia, 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          <?php endif; ?>
        </div>
      </td>
      <td class="col-plantel-logo">
        <?php if (!empty($plantelLogo)): ?>
          <img src="<?= $plantelLogo ?>" alt="Plantel">
        <?php endif; ?>
      </td>
    </tr>
  </table>

  <!-- Título y Período (Espaciado de ritmo: 24pt antes, 6pt entre, 30pt después) -->
  <div class="doc-title">CERTIFICADO DE CALIFICACIONES</div>
  <div class="doc-periodo">AÑO ESCOLAR <?= htmlspecialchars($periodoEscolarLimpio, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>

  <!-- Párrafo Principal (Texto corrido, justificado) -->
  <div class="cuerpo-legal">
    <?= $artEstudiante ?> <strong><?= htmlspecialchars($nombreEst, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong>, <?= $textoDocIdentidad ?>, cursante del <strong><?= htmlspecialchars($gradoActualTexto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong> grado, sección <strong>"<?= htmlspecialchars($seccionTexto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"</strong>, obtuvo, de las categorías establecidas en la Escala Alfabética para la interpretación de los resultados de la Evaluación Estudiantil, la escala literal <strong>"<?= htmlspecialchars($literalUpper, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"</strong>, de acuerdo con el Art. 108 del RGLOE.
  </div>

  <!-- Párrafo de Promoción (Solo una condición, según resultado) -->
  <div class="cuerpo-promocion">
    <?php if ($esPromovido): ?>
      Siendo <strong><?= $promovidoPalabra ?></strong> al <strong><?= htmlspecialchars($gradoDestinoTexto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong>, para el año escolar <strong><?= htmlspecialchars($siguientePeriodo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong>.
    <?php else: ?>
      Siendo <strong>NO <?= $promovidoPalabra ?></strong> del <strong><?= htmlspecialchars($gradoActualTexto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong> grado, para el año escolar <strong><?= htmlspecialchars($siguientePeriodo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong>.
    <?php endif; ?>
  </div>

  <!-- Escala Normativa en Recuadro Aparte (Art. 108 RGLOE) -->
  <div class="recuadro-escala">
    <div class="recuadro-escala__titulo">Escala Alfabética para la interpretación de los resultados de la Evaluación del Rendimiento Estudiantil</div>
    <div class="recuadro-escala__intro">Artículo 108 del RGLOE: La expresión cualitativa de la evaluación de los alumnos de las dos primeras etapas de educación básica se hará de manera descriptiva, en forma global y en términos literales de la siguiente forma:</div>
    <table>
      <tr>
        <td class="escala-letra">A:</td>
        <td class="escala-desc">El alumno alcanzó todas las competencias y en algunos casos superó las expectativas para el grado.</td>
      </tr>
      <tr>
        <td class="escala-letra">B:</td>
        <td class="escala-desc">El alumno alcanzó todas las competencias previstas para el grado.</td>
      </tr>
      <tr>
        <td class="escala-letra">C:</td>
        <td class="escala-desc">El alumno alcanzó la mayoría de las competencias previstas para el grado.</td>
      </tr>
      <tr>
        <td class="escala-letra">D:</td>
        <td class="escala-desc">El alumno alcanzó algunas de las competencias previstas para el grado, pero requiere de un proceso de nivelación al inicio del nuevo año escolar.</td>
      </tr>
      <tr>
        <td class="escala-letra">E:</td>
        <td class="escala-desc">El alumno no logró adquirir las competencias mínimas requeridas para ser promovido al grado inmediatamente superior.</td>
      </tr>
    </table>
  </div>

  <!-- Fecha de expedición formal (18pt antes, sangría 1.25cm) -->
  <div class="fecha-expedicion">
    En <?= htmlspecialchars($ciudadLimpia, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>, <?= ($diaExp === 1) ? 'al <strong>primer (1)</strong> día' : ('a los <strong>' . htmlspecialchars((string)$diaExp, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong> días') ?> del mes de <strong><?= htmlspecialchars($mesLower, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong> de <strong><?= htmlspecialchars((string)$anioExpTexto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong>.
  </div>

  <!-- Bloque de Firmas y Sello (48pt antes, tabla 40% / 20% / 40%, líneas iguales de 160pt) -->
  <div class="seccion-firmas">
    <table class="signature-table">
      <tr>
        <!-- Columna 1: Docente (40%) -->
        <td class="firm-col">
          <div class="firma-linea"></div>
          <div class="firma-nombre"><?= htmlspecialchars($docenteNom, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          <div class="firma-cargo">Docente</div>
          <div class="firma-ci">C.I. <?= htmlspecialchars($docenteDoc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        </td>

        <!-- Columna 2: Sello (20%) -->
        <td class="seal-col">
          <?php if (!empty($selloUri)): ?>
            <div class="sello-wrap">
              <img src="<?= $selloUri ?>" class="sello-img" alt="Sello Institucional">
            </div>
          <?php endif; ?>
        </td>

        <!-- Columna 3: Directora (40%) -->
        <td class="firm-col">
          <div class="firma-linea"></div>
          <div class="firma-nombre"><?= htmlspecialchars($directorNom, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          <div class="firma-cargo"><?= htmlspecialchars($cargoDirector, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          <div class="firma-ci">C.I. <?= htmlspecialchars($directorDoc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        </td>
      </tr>
    </table>
  </div>

  <?php if ($idx < ($totalEst - 1)): ?>
    <div class="page-break"></div>
  <?php endif; ?>
<?php endforeach; ?>

</body>
</html>
