<?php
/**
 * Plantilla Base Oficial de Constancias (SIGDE).
 * 
 * Unifica la maquetación compartida: CSS, membrete ministerial de 3 columnas,
 * recuadro oficial con DEA y Código de Dependencia, párrafo inicial,
 * bloque de firma y sello, y cintillo tricolor inferior de borde a borde.
 *
 * Cada constancia aporta su $tituloConstancia y su $textoEspecifico.
 *
 * @var array       $config             Configuración institucional
 * @var array       $estudiante         Datos del estudiante
 * @var string      $codigoDependencia  Código de dependencia oficial
 * @var string      $ministerioLogo     Data URI del logo ministerial
 * @var string      $plantelLogo        Data URI del escudo del plantel
 * @var string      $cintilloLogo       Data URI de la franja tricolor
 * @var array       $dimMin             Dimensiones del logo ministerial
 * @var array       $dimPla             Dimensiones del escudo del plantel
 * @var string|null $firmaUri           Data URI de la firma digital (o null)
 * @var string|null $selloUri           Data URI del sello húmedo digital (o null)
 * @var string      $tituloConstancia   Título del documento (ej. CONSTANCIA DE ESTUDIO)
 * @var string      $textoEspecifico    Texto del cuerpo particular (valores ya escapados)
 * @var string      $fechaNacTexto      Fecha de nacimiento (ej. "18 DE JUNIO DE 2015")
 * @var int         $diaExp             Día de expedición (1..31)
 * @var string      $mesExpTexto        Nombre del mes de expedición en mayúsculas
 * @var string      $anioExpTexto       Año de expedición (ej. "2026")
 * @var string      $dirLetra           Prefijo de cédula del director (V o E)
 * @var string      $dirNumFormatted    Cédula formateada con puntos (ej. "14.852.369")
 * @var string      $dirNumLimpio       Cédula solo dígitos (ej. "14852369")
 * @var string      $docEstEtiqueta     Etiqueta del documento (ej. "la Cédula de Identidad Nº")
 * @var bool        $marcaAguaPrueba    Si es true, estampa "PRUEBA"
 * @var string|null $notaBorrador       Nota explicativa en pruebas (o null)
 */
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
      margin: 38pt auto 0 auto;
      width: 470pt; /* Margenes laterales de 2.5 cm: (612 - 470) / 2 = 71 pt = 2.5 cm */
    }
    .header-table {
      width: 570pt;
      margin-left: -50pt;
      margin-right: -50pt;
      margin-bottom: 16pt;
      border-collapse: collapse;
    }
    .header-table td {
      vertical-align: middle;
      padding: 0;
    }
    .col-min-logo {
      width: 141pt;
      text-align: left;
    }
    .col-min-logo img {
      max-width: 140pt;
      max-height: 42pt;
      height: auto;
      display: block;
    }
    .col-box-center {
      width: 288pt;
      text-align: center;
      padding: 0;
    }
    .col-plantel-logo {
      width: 141pt;
      text-align: right;
    }
    .col-plantel-logo img {
      max-width: 50pt;
      max-height: 58pt;
      height: auto;
      display: inline-block;
    }
    .recuadro-membrete {
      border: none;
      padding: 0;
      font-size: 10pt;
      line-height: 1.25;
      text-align: center;
      font-weight: bold;
      text-transform: uppercase;
    }
    .recuadro-membrete-linea {
      border: none;
      padding: 0;
      font-size: 10pt;
      line-height: 1.25;
      text-align: center;
      font-weight: bold;
      text-transform: uppercase;
    }
    .doc-title {
      text-align: center;
      font-size: 14pt;
      font-weight: bold;
      letter-spacing: 0.5px;
      margin: 16pt 0 15pt 0;
      text-transform: uppercase;
    }
    .doc-body {
      text-align: justify;
      font-size: 12pt;
      line-height: 1.5; /* Interlineado 1.5 */
      margin-bottom: 15pt;
    }
    .doc-body u strong,
    .doc-body strong u {
      font-weight: bold;
      text-decoration: underline;
    }
    .doc-closing {
      text-align: justify;
      font-size: 12pt;
      line-height: 1.5; /* Interlineado 1.5 */
      margin-bottom: 20pt;
    }
    .doc-closing u strong,
    .doc-closing strong u {
      font-weight: bold;
      text-decoration: underline;
    }
    .signature-table {
      margin: 22pt auto 0 auto;
      border-collapse: collapse;
      width: 100%;
      table-layout: fixed;
    }
    .signature-table td {
      vertical-align: bottom;
      padding: 0;
    }
    .seal-col {
      width: 33.33%;
      text-align: center;
    }
    .seal-img {
      max-height: 65pt;
      max-width: 95pt;
      display: inline-block;
    }
    .seal-empty {
      height: 65pt;
    }
    .sign-col {
      width: 33.34%;
      text-align: center;
    }
    .empty-col {
      width: 33.33%;
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
    .signature-box {
      border-top: 1px solid #000;
      padding-top: 4pt;
      width: 190pt;
      margin: 0 auto;
      text-align: center;
      font-size: 9.5pt;
      line-height: 1.35;
      font-weight: bold;
      text-transform: uppercase;
      word-wrap: break-word;
    }
    .signature-box .sign-name {
      margin-bottom: 1pt;
    }
    .signature-box .sign-ci {
      margin-bottom: 1pt;
    }
    .signature-box .sign-cargo {
      line-height: 1.25;
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
      font-size: 82pt;
      font-family: 'Helvetica', 'Arial', sans-serif;
      font-weight: bold;
      color: #d6d6d6;
      opacity: 0.30;
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
  <!-- Encabezado de 3 columnas -->
  <table class="header-table">
    <tr>
      <td class="col-min-logo">
        <?php if (!empty($ministerioLogo)): ?>
          <img src="<?= $ministerioLogo ?>" width="<?= (int)$dimMin['width'] ?>" height="<?= (int)$dimMin['height'] ?>" alt="Ministerio">
        <?php endif; ?>
      </td>
      <td class="col-box-center">
        <?php if (($recuadroModo ?? 'completo') === 'completo'): ?>
          <div class="recuadro-membrete">
            <div><?= htmlspecialchars($config['ministerio_linea_1'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            <div><?= htmlspecialchars($config['ministerio_linea_2'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            <div><?= htmlspecialchars($config['plantel_nombre'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            <div>DEA: <?= htmlspecialchars($config['plantel_codigo_dea'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            <?php if ($mostrarCodigoDependencia ?? true): ?>
              <div>CÓDIGO DE DEPENDENCIA: <?= htmlspecialchars($codigoDependencia, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            <?php endif; ?>
          </div>
        <?php elseif (($recuadroModo ?? '') === 'linea'): ?>
          <div class="recuadro-membrete-linea">
            <div>DEA: <?= htmlspecialchars($config['plantel_codigo_dea'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?><?php if ($mostrarCodigoDependencia ?? true): ?> &nbsp;|&nbsp; CÓDIGO DE DEPENDENCIA: <?= htmlspecialchars($codigoDependencia, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?><?php endif; ?></div>
          </div>
        <?php endif; /* Si $recuadroModo === 'quitar', la celda central permanece vacía */ ?>
      </td>
      <td class="col-plantel-logo">
        <?php if (!empty($plantelLogo)): ?>
          <img src="<?= $plantelLogo ?>" width="<?= (int)$dimPla['width'] ?>" height="<?= (int)$dimPla['height'] ?>" alt="Plantel">
        <?php endif; ?>
      </td>
    </tr>
  </table>

  <!-- Título específico de la constancia -->
  <div class="doc-title"><?= htmlspecialchars($tituloConstancia, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>

  <!-- Párrafo inicial oficial compartido + Texto específico aportado por cada constancia -->
  <div class="doc-body">
    Quien suscribe <u><strong><?= htmlspecialchars(mb_strtoupper($config['director_nombre'] ?? '', 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong></u> titular de la Cédula de Identidad Nº <?= htmlspecialchars($dirLetra, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>.-<u><strong><?= htmlspecialchars($dirNumFormatted, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong></u> <?= htmlspecialchars($config['director_cargo'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> de la Institución Educativa <u><strong><?= htmlspecialchars(mb_strtoupper($config['plantel_nombre'] ?? '', 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong></u>, CÓDIGO DEA: <u><strong><?= htmlspecialchars(mb_strtoupper($config['plantel_codigo_dea'] ?? '', 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong></u>, ubicada en el Municipio <u><strong><?= htmlspecialchars(mb_strtoupper($config['plantel_municipio'] ?? '', 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong></u> de la Parroquia <u><strong><?= htmlspecialchars(mb_strtoupper($config['plantel_parroquia'] ?? '', 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong></u> adscrita al Centro de Desarrollo de la Calidad Educativa Estadal <u><strong><?= htmlspecialchars(mb_strtoupper($config['cdce_estado'] ?? '', 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong></u>. Por la presente hace constar que el (la) estudiante <u><strong><?= htmlspecialchars(mb_strtoupper($estudiante['nombre_completo'] ?? '', 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong></u>, titular de <?= htmlspecialchars($docEstEtiqueta, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> <u><strong><?= htmlspecialchars($estudiante['numero_documento'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong></u> nacido (a) en el Municipio <u><strong><?= htmlspecialchars(mb_strtoupper($estudiante['municipio_nacimiento'] ?? '', 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong></u> del Estado <u><strong><?= htmlspecialchars(mb_strtoupper($estudiante['estado_nacimiento'] ?? '', 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong></u>, en fecha <u><strong><?= htmlspecialchars($fechaNacTexto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong></u>, <?= $textoEspecifico ?>
  </div>

  <!-- Cierre: Expedición -->
  <div class="doc-closing">
    Constancia que se expide en <u><strong><?= htmlspecialchars(mb_strtoupper($config['lugar_expedicion'] ?? '', 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong></u>, <?= ($diaExp === 1) ? 'al <u><strong>primer (1)</strong></u> día' : ('a los <u><strong>' . htmlspecialchars((string)$diaExp, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong></u> días') ?> del mes de <u><strong><?= htmlspecialchars($mesExpTexto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong></u> del año <u><strong><?= htmlspecialchars($anioExpTexto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong></u>.
  </div>

  <!-- Bloque de Firma y Sello: Tres columnas iguales (sello a la izquierda, firma en el centro, derecha vacía) -->
  <table class="signature-table">
    <tr>
      <td class="seal-col">
        <?php if (!empty($selloUri)): ?>
          <img src="<?= $selloUri ?>" class="seal-img" alt="Sello Institucional">
        <?php else: ?>
          <div class="seal-empty"></div>
        <?php endif; ?>
      </td>

      <td class="sign-col">
        <?php if (!empty($firmaUri)): ?>
          <div class="sign-img-wrapper">
            <img src="<?= $firmaUri ?>" class="sign-img" alt="Firma Director(a)">
          </div>
        <?php else: ?>
          <div class="sign-placeholder"></div>
        <?php endif; ?>
        <div class="signature-box">
          <div class="sign-name"><?= htmlspecialchars(mb_strtoupper($config['director_nombre'] ?? '', 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          <div class="sign-ci">C.I. Nº <?= htmlspecialchars($dirNumLimpio, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          <div class="sign-cargo"><?= htmlspecialchars(mb_strtoupper($config['director_cargo'] ?? '', 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        </div>
      </td>

      <td class="empty-col"></td>
    </tr>
  </table>

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
