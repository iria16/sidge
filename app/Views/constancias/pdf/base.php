<?php
/**
 * Plantilla Base Oficial de Constancias (SIGDE).
 * 
 * Unifica la maquetación compartida: CSS, membrete ministerial de 3 columnas,
 * encabezado institucional corregido sin recuadro, título, cuerpo formal,
 * cierre de expedición en línea propia, bloque de firma/sello centrado
 * y franja tricolor oficial fijada al fondo de borde a borde.
 *
 * @var array       $config             Configuración institucional
 * @var array       $estudiante         Datos del estudiante
 * @var string      $codigoDependencia  Código de dependencia oficial
 * @var string|null $ministerioLogo     Data URI del logo ministerial
 * @var string|null $plantelLogo        Data URI del escudo del plantel
 * @var string|null $cintilloLogo       Data URI de la franja tricolor
 * @var array       $dimMin             Dimensiones del logo ministerial
 * @var array       $dimPla             Dimensiones del escudo del plantel
 * @var string|null $firmaUri           Data URI de la firma digital (o null)
 * @var string|null $selloUri           Data URI del sello húmedo digital (o null)
 * @var string      $tituloConstancia   Título del documento (ej. CONSTANCIA DE ESTUDIO)
 * @var string|null $cuerpoConstancia   Cuerpo formal ya renderizado sin subrayados
 * @var string|null $textoEspecifico    Texto del cuerpo particular (fallback legacy)
 * @var string|null $fechaExpedicionTexto Fecha de expedición formal en línea propia
 * @var string      $fechaNacTexto      Fecha de nacimiento formateada
 * @var int         $diaExp             Día de expedición (1..31)
 * @var string      $mesExpTexto        Nombre del mes de expedición en mayúsculas
 * @var string      $anioExpTexto       Año de expedición (ej. "2026")
 * @var string      $dirLetra           Prefijo de cédula del director (V o E)
 * @var string      $dirNumFormatted    Cédula formateada con puntos (ej. "14.852.369")
 * @var string      $dirNumLimpio       Cédula solo dígitos (ej. "14852369")
 * @var string|null $dirDocTexto        Cédula completa canónica (ej. "V-14.852.369")
 * @var string      $docEstEtiqueta     Etiqueta del documento del estudiante (legacy)
 * @var bool        $marcaAguaPrueba    Si es true, estampa "PRUEBA"
 * @var string|null $notaBorrador       Nota explicativa en pruebas (o null)
 */

$dirDocTexto = $dirDocTexto ?? ($dirLetra . '-' . $dirNumFormatted);
$nombrePlantel = $config['plantel_nombre'] ?? 'Escuela Básica "Isidro Ramírez"';
$plantelDea = $config['plantel_codigo_dea'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($tituloConstancia, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></title>
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
    .page-container {
      margin: 71pt auto 0 auto; /* Margen superior y laterales oficiales de 2.5 cm (71 pt) */
      width: 470pt; /* (612 - 470) / 2 = 71 pt = 2.5 cm en los bordes laterales */
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
    .header-solo-ministerio {
      margin-bottom: 24pt;
      text-align: left;
    }
    .header-solo-ministerio img {
      max-width: 195pt;
      max-height: 58pt;
      height: auto;
      display: block;
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
    .doc-closing strong {
      font-weight: bold;
    }
    .seccion-firmas {
      margin-top: 48pt;
      text-align: center;
    }
    .firma-box {
      width: 220pt;
      margin: 0 auto;
      text-align: center;
      position: relative;
    }
    .sign-img-wrapper {
      height: 40pt;
      margin-bottom: -10pt;
      text-align: center;
    }
    .sign-img {
      max-height: 45pt;
      max-width: 140pt;
      display: inline-block;
    }
    .sign-placeholder {
      height: 40pt;
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
    .nota-borrador {
      font-size: 7.5pt;
      color: #777;
      font-style: italic;
      margin-top: 10pt;
      text-align: center;
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

<div class="page-container">
<?php if (!empty($encabezadoConEscudo)): ?>
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
<?php elseif (!empty($encabezadoSoloMinisterio)): ?>
  <!-- Encabezado simple: únicamente logo ministerial ampliado -->
  <div class="header-solo-ministerio">
    <?php if (!empty($ministerioLogo)): ?>
      <img src="<?= $ministerioLogo ?>" alt="Ministerio del Poder Popular para la Educación">
    <?php endif; ?>
  </div>
<?php else: ?>
  <!-- Encabezado Institucional de 3 columnas alineado al centro -->
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
          <?php if (($mostrarCodigoDependencia ?? true) && !empty($codigoDependencia)): ?>
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
<?php endif; ?>

  <!-- Título específico de la constancia -->
  <div class="doc-title">
    <div><?= htmlspecialchars($tituloConstancia, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
    <?php if (!empty($subtituloConstancia)): ?>
      <div class="doc-title-sub"><?= htmlspecialchars($subtituloConstancia, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
    <?php endif; ?>
  </div>

  <!-- Párrafo Principal (Texto corrido, justificado, con sangría de 1.25 cm) -->
  <div class="doc-body">
    <?php if (isset($cuerpoConstancia)): ?>
      <?= $cuerpoConstancia ?>
    <?php else: ?>
      Quien suscribe <strong><?= htmlspecialchars(mb_strtoupper($config['director_nombre'] ?? '', 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong> titular de la Cédula de Identidad N.° <?= htmlspecialchars($dirDocTexto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> <?= htmlspecialchars($config['director_cargo'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> de la Institución Educativa <strong><?= htmlspecialchars(mb_strtoupper($config['plantel_nombre'] ?? '', 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong>, CÓDIGO DEA: <strong><?= htmlspecialchars(mb_strtoupper($config['plantel_codigo_dea'] ?? '', 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong>, ubicada en el Municipio <?= htmlspecialchars(mb_strtoupper($config['plantel_municipio'] ?? '', 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> de la Parroquia <?= htmlspecialchars(mb_strtoupper($config['plantel_parroquia'] ?? '', 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> adscrita al Centro de Desarrollo de la Calidad Educativa Estadal <?= htmlspecialchars(mb_strtoupper($config['cdce_estado'] ?? '', 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>. Por la presente hace constar que el (la) estudiante <strong><?= htmlspecialchars(mb_strtoupper($estudiante['nombre_completo'] ?? '', 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong>, titular de <?= htmlspecialchars($docEstEtiqueta ?? 'la Cédula de Identidad N.°', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> <strong><?= htmlspecialchars($estudiante['numero_documento'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong> nacido (a) en el Municipio <?= htmlspecialchars(mb_strtoupper($estudiante['municipio_nacimiento'] ?? '', 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> del Estado <?= htmlspecialchars(mb_strtoupper($estudiante['estado_nacimiento'] ?? '', 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>, en fecha <strong><?= htmlspecialchars($fechaNacTexto ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong>, <?= $textoEspecifico ?? '' ?>
    <?php endif; ?>
  </div>

  <!-- Cierre: Expedición en línea propia -->
  <div class="doc-closing">
    <?php if (isset($fechaExpedicionTexto)): ?>
      <?= $fechaExpedicionTexto ?>
    <?php else: ?>
      Constancia que se expide en <?= htmlspecialchars(mb_strtoupper($config['lugar_expedicion'] ?? '', 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>, <?= ($diaExp === 1) ? 'al primer (1) día' : ('a los ' . htmlspecialchars((string)$diaExp, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ' días') ?> del mes de <?= htmlspecialchars(mb_strtolower($mesExpTexto ?? '', 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> del año <?= htmlspecialchars((string)($anioExpTexto ?? date('Y')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>.
    <?php endif; ?>
  </div>

  <?php if (!empty($dobleFirmaCdce)): ?>
  <!-- Tabla oficial de Doble Firma y Sello: Institución Educativa y CDCE Estadal -->
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
        <td><strong>Nombre y Apellido:</strong> <?= htmlspecialchars(mb_strtoupper($config['director_nombre'] ?? '', 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
        <td><strong>Nombre y Apellido:</strong> <?= !empty($cdceDirNombre) ? htmlspecialchars($cdceDirNombre, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '' ?></td>
      </tr>
      <tr>
        <td><strong>Número de C.I.:</strong> <?= htmlspecialchars($dirDocTexto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
        <td><strong>Número de C.I.:</strong> <?= !empty($cdceDirDocTexto) ? htmlspecialchars($cdceDirDocTexto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '' ?></td>
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
  <?php else: ?>
  <!-- Bloque de Firma y Sello: Centrado -->
  <div class="seccion-firmas">
    <div class="firma-box">
      <?php if (!empty($selloUri)): ?>
        <div class="sello-wrap">
          <img src="<?= $selloUri ?>" class="sello-img" alt="Sello Institucional">
        </div>
      <?php endif; ?>
      <?php if (!empty($firmaUri)): ?>
        <div class="sign-img-wrapper">
          <img src="<?= $firmaUri ?>" class="sign-img" alt="Firma Director(a)">
        </div>
      <?php else: ?>
        <div class="sign-placeholder"></div>
      <?php endif; ?>
      <div class="firma-linea"></div>
      <div class="firma-nombre"><?= htmlspecialchars(mb_strtoupper($config['director_nombre'] ?? '', 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
      <div class="firma-cargo"><?= htmlspecialchars(mb_strtoupper($config['director_cargo'] ?? 'Director(a)', 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
      <div class="firma-ci">C.I. <?= htmlspecialchars($dirDocTexto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
    </div>
  </div>
  <?php endif; ?>

  <?php if (!empty($leyendaValidez)): ?>
    <div class="footer-validity"><?= htmlspecialchars($leyendaValidez, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
  <?php endif; ?>
  <?php if (!empty($epigrafePie)): ?>
    <div class="footer-epigrafe">"<?= htmlspecialchars($epigrafePie, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"</div>
  <?php endif; ?>

  <?php if (!empty($notaBorrador)): ?>
    <div class="nota-borrador">
      * <?= htmlspecialchars($notaBorrador, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
    </div>
  <?php endif; ?>
</div>

<!-- Franja tricolor de borde a borde fijada al fondo de la página -->
<?php if (!empty($cintilloLogo)): ?>
  <div class="footer-banner">
    <img src="<?= $cintilloLogo ?>" alt="Cintillo Tricolor">
  </div>
<?php endif; ?>

</body>
</html>
