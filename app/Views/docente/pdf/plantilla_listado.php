<?php
/**
 * Plantilla PDF Oficial para Listados y Actas de Sección (SIGDE).
 * Compilada con Dompdf a tamaño Carta Vertical.
 *
 * @var array  $config
 * @var array  $seccion
 * @var array  $estudiantes
 * @var string $tipoDocumento
 * @var array  $opcionesColumnas
 * @var string $tituloDocumento
 * @var string $subtituloDoc
 * @var string $badgeDoc
 * @var string $fechaEmision
 * @var string $horaEmision
 * @var int    $totalEst
 * @var int    $totalFem
 * @var int    $totalMasc
 * @var string|null $ministerioLogo
 * @var string|null $plantelLogo
 * @var string|null $firmaUri
 * @var string|null $selloUri
 * @var string $dirNombre
 * @var string $dirCargo
 * @var string $dirCedulaFmt
 * @var string $docNombre
 * @var string $docCargo
 * @var string $docCedulaFmt
 */

$plantelNombre = strtoupper(trim((string) ($config['plantel_nombre'] ?? 'U.E.N. BICENTENARIO DE LA INDEPENDENCIA')));
$plantelDea    = strtoupper(trim((string) ($config['plantel_codigo_dea'] ?? '')));
$plantelDep    = strtoupper(trim((string) ($config['plantel_codigo_dependencia'] ?? '')));
$plantelRif    = strtoupper(trim((string) ($config['plantel_rif'] ?? '')));
$distritoEsc   = strtoupper(trim((string) ($config['plantel_distrito_escolar'] ?? '')));

$gradoNombre   = htmlspecialchars($seccion['grado'] ?? '');
$seccionNombre = htmlspecialchars($seccion['seccion'] ?? '');
$turnoNombre   = htmlspecialchars($seccion['turno'] ?? '');
$anioNombre    = htmlspecialchars($seccion['anio_escolar'] ?? '');

$mostrarCedulaEsc = !empty($opcionesColumnas['mostrar_cedula_escolar']);
$mostrarTelefono  = !empty($opcionesColumnas['mostrar_telefono']);
$mostrarRetiro    = !empty($opcionesColumnas['mostrar_retiro']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($tituloDocumento) ?> - <?= $gradoNombre ?> "<?= $seccionNombre ?>"</title>
  <style>
    @page {
      size: letter portrait;
      margin: 25pt 28pt 32pt 28pt;
    }
    * {
      box-sizing: border-box;
    }
    body {
      font-family: 'Helvetica', 'Arial', sans-serif;
      font-size: 8pt;
      color: #0f172a;
      line-height: 1.25;
      margin: 0;
      padding: 0;
      background: #ffffff;
    }

    /* Membrete Oficial */
    .header-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 8pt;
    }
    .header-table td {
      vertical-align: middle;
      padding: 0;
    }
    .col-min-logo {
      width: 110pt;
      text-align: left;
    }
    .col-min-logo img {
      max-width: 110pt;
      max-height: 38pt;
      height: auto;
      display: block;
    }
    .col-header-text {
      text-align: center;
      padding: 0 4pt;
    }
    .col-plantel-logo {
      width: 75pt;
      text-align: right;
    }
    .col-plantel-logo img {
      max-width: 44pt;
      max-height: 48pt;
      height: auto;
      display: inline-block;
    }

    .inst-republica {
      font-size: 7.5pt;
      font-weight: bold;
      color: #1e293b;
      margin: 0;
      letter-spacing: 0.02em;
    }
    .inst-ministerio {
      font-size: 6.8pt;
      font-weight: 600;
      color: #334155;
      margin: 1pt 0;
    }
    .inst-plantel {
      font-size: 9pt;
      font-weight: bold;
      color: #0f233a;
      margin: 2pt 0 1pt 0;
      letter-spacing: 0.03em;
    }
    .inst-meta {
      font-size: 6.5pt;
      color: #64748b;
      margin: 0;
    }

    /* Caja de Metadatos de la Sección */
    .box-seccion {
      width: 100%;
      border: 1px solid #cbd5e1;
      border-radius: 4px;
      background-color: #f8fafc;
      margin-bottom: 8pt;
      padding: 4pt 6pt;
    }
    .box-seccion table {
      width: 100%;
      border-collapse: collapse;
    }
    .box-seccion td {
      padding: 1pt 4pt;
      font-size: 7.5pt;
      vertical-align: middle;
    }
    .box-seccion strong {
      color: #0f233a;
    }

    /* Título del Reporte */
    .doc-title-bar {
      margin-bottom: 6pt;
      border-bottom: 1.5pt solid #0f233a;
      padding-bottom: 3pt;
    }
    .doc-title {
      font-size: 10.5pt;
      font-weight: bold;
      color: #0f233a;
      margin: 0;
      text-transform: uppercase;
      letter-spacing: 0.02em;
      display: inline-block;
    }
    .doc-badge {
      display: inline-block;
      font-size: 6.5pt;
      font-weight: bold;
      background-color: #0f233a;
      color: #ffffff;
      padding: 2pt 5pt;
      border-radius: 3pt;
      margin-left: 6pt;
      vertical-align: middle;
      text-transform: uppercase;
    }
    .doc-subtitle {
      font-size: 7.5pt;
      color: #64748b;
      margin: 2pt 0 0 0;
    }

    /* Tabla de Datos Principal */
    .report-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 8pt;
      font-size: 7.2pt;
    }
    .report-table thead {
      display: table-header-group;
    }
    .report-table tr {
      page-break-inside: avoid;
    }
    .report-table th {
      background-color: #0f233a;
      color: #ffffff;
      font-size: 7pt;
      font-weight: bold;
      text-align: left;
      padding: 4pt 4pt;
      border: 1px solid #0f233a;
      letter-spacing: 0.02em;
      text-transform: uppercase;
    }
    .report-table th.text-center, .report-table td.text-center {
      text-align: center;
    }
    .report-table th.text-right, .report-table td.text-right {
      text-align: right;
    }
    .report-table td {
      padding: 3.5pt 4pt;
      border: 1px solid #cbd5e1;
      vertical-align: middle;
      color: #1e293b;
    }
    .report-table tbody tr:nth-child(even) {
      background-color: #f8fafc;
    }
    .report-table td.col-num {
      width: 18pt;
      text-align: center;
      font-weight: bold;
      color: #475569;
    }
    .report-table td.col-gender {
      width: 16pt;
      text-align: center;
      font-weight: bold;
    }
    .report-table td.col-age {
      width: 22pt;
      text-align: center;
    }

    .badge-f {
      color: #be123c;
    }
    .badge-m {
      color: #1d4ed8;
    }

    /* Resumen de Nómina */
    .summary-bar {
      background-color: #f1f5f9;
      border: 1px solid #e2e8f0;
      border-radius: 3px;
      padding: 4pt 8pt;
      margin-bottom: 14pt;
      font-size: 7.2pt;
      color: #334155;
    }

    /* Bloque de Firmas */
    .signatures-block {
      width: 100%;
      page-break-inside: avoid;
      margin-top: 10pt;
    }
    .signatures-table {
      width: 100%;
      border-collapse: collapse;
    }
    .signatures-table td {
      width: 50%;
      text-align: center;
      vertical-align: bottom;
      padding: 0 15pt;
    }
    .sig-line {
      border-top: 1px solid #1e293b;
      width: 170pt;
      margin: 0 auto 3pt auto;
    }
    .sig-name {
      font-size: 8pt;
      font-weight: bold;
      color: #0f233a;
      margin: 0;
    }
    .sig-role {
      font-size: 7pt;
      color: #475569;
      margin: 1pt 0;
    }
    .sig-seal {
      font-size: 6.5pt;
      font-weight: bold;
      color: #64748b;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      margin: 2pt 0 0 0;
    }

    .digital-seal-img {
      max-width: 60pt;
      max-height: 50pt;
      margin-bottom: -15pt;
    }
    .digital-sig-img {
      max-width: 85pt;
      max-height: 35pt;
      margin-bottom: -10pt;
    }
  </style>
</head>
<body>

  <!-- 1. MEMBRETE OFICIAL DEL PLANTEL -->
  <table class="header-table">
    <tr>
      <td class="col-min-logo">
        <?php if (!empty($ministerioLogo)): ?>
          <img src="<?= $ministerioLogo ?>" alt="Ministerio">
        <?php endif; ?>
      </td>
      <td class="col-header-text">
        <p class="inst-republica">REPÚBLICA BOLIVARIANA DE VENEZUELA</p>
        <p class="inst-ministerio">MINISTERIO DEL PODER POPULAR PARA LA EDUCACIÓN</p>
        <p class="inst-plantel"><?= $plantelNombre ?></p>
        <p class="inst-meta">
          <?php if (!empty($plantelDea)): ?>CÓDIGO DEA: <?= $plantelDea ?> &nbsp;·&nbsp;<?php endif; ?>
          <?php if (!empty($plantelDep)): ?>DEP: <?= $plantelDep ?> &nbsp;·&nbsp;<?php endif; ?>
          <?php if (!empty($plantelRif)): ?>RIF: <?= $plantelRif ?><?php endif; ?>
        </p>
      </td>
      <td class="col-plantel-logo">
        <?php if (!empty($plantelLogo)): ?>
          <img src="<?= $plantelLogo ?>" alt="Escudo">
        <?php endif; ?>
      </td>
    </tr>
  </table>

  <!-- 2. DATOS DE LA SECCIÓN Y AULA REGULAR -->
  <div class="box-seccion">
    <table>
      <tr>
        <td style="width: 25%;"><strong>Nivel:</strong> Educación Primaria</td>
        <td style="width: 25%;"><strong>Grado:</strong> <?= $gradoNombre ?></td>
        <td style="width: 25%;"><strong>Sección:</strong> "<?= $seccionNombre ?>"</td>
        <td style="width: 25%;"><strong>Turno:</strong> <?= $turnoNombre ?></td>
      </tr>
      <tr>
        <td><strong>Año Escolar:</strong> <?= $anioNombre ?></td>
        <td colspan="2"><strong>Docente Titular:</strong> <?= htmlspecialchars($docNombre) ?></td>
        <td><strong>Fecha:</strong> <?= $fechaEmision ?></td>
      </tr>
    </table>
  </div>

  <!-- 3. TÍTULO Y SUBTÍTULO DEL DOCUMENTO -->
  <div class="doc-title-bar">
    <h1 class="doc-title"><?= htmlspecialchars($tituloDocumento) ?></h1>
    <span class="doc-badge"><?= htmlspecialchars($badgeDoc) ?></span>
    <p class="doc-subtitle"><?= htmlspecialchars($subtituloDoc) ?> · Control de Aula Regular: <?= $gradoNombre ?> Sección "<?= $seccionNombre ?>"</p>
  </div>

  <!-- 4. TABLA TABULAR SEGÚN EL TIPO DE REPORTE -->
  <table class="report-table">
    <thead>
      <?php if ($tipoDocumento === 'contactos'): ?>
        <tr>
          <th class="text-center" style="width: 20pt;">N°</th>
          <th style="width: 135pt;">ESTUDIANTE</th>
          <th style="width: 65pt;">CÉDULA</th>
          <th style="width: 130pt;">REPRESENTANTE</th>
          <th style="width: 55pt;">PARENTESCO</th>
          <th style="width: 70pt;">TELÉFONO</th>
          <th>CORREO ELECTRÓNICO</th>
        </tr>
      <?php else: /* nomina */ ?>
        <tr>
          <th class="text-center" style="width: 18pt;">N°</th>
          <?php if ($mostrarCedulaEsc): ?>
            <th style="width: 68pt;">CÉDULA</th>
          <?php endif; ?>
          <th>APELLIDOS Y NOMBRES</th>
          <th class="text-center" style="width: 22pt;">EDAD</th>
          <th class="text-center" style="width: 18pt;">G.</th>
          <th>REPRESENTANTE LEGAL</th>
          <th style="width: 65pt;">C.I. REP.</th>
          <?php if ($mostrarTelefono): ?>
            <th style="width: 70pt;">TELÉFONO</th>
          <?php endif; ?>
          <?php if ($mostrarRetiro): ?>
            <th class="text-center" style="width: 40pt;">RETIRO</th>
          <?php endif; ?>
        </tr>
      <?php endif; ?>
    </thead>
    <tbody>
      <?php if (empty($estudiantes)): ?>
        <tr>
          <td colspan="8" class="text-center" style="padding: 15pt; color: #64748b;">
            No existen estudiantes activos matriculados en esta sección para los filtros seleccionados.
          </td>
        </tr>
      <?php else: ?>
        <?php foreach ($estudiantes as $idx => $est): ?>
          <?php
            $num = $idx + 1;
            $gen = strtoupper((string) ($est['genero'] ?? ''));
            $genClass = ($gen === 'F') ? 'badge-f' : 'badge-m';
            $autorizaRetiro = !empty($est['es_autorizado_retiro']) ? 'SÍ' : 'NO';
          ?>
          <?php if ($tipoDocumento === 'contactos'): ?>
            <tr>
              <td class="col-num"><?= sprintf('%02d', $num) ?></td>
              <td style="font-weight: 600;"><?= htmlspecialchars($est['apellidos_nombres']) ?></td>
              <td style="font-family: monospace; font-size: 6.8pt;"><?= htmlspecialchars($est['documento_etiqueta']) ?></td>
              <td><?= htmlspecialchars($est['rep_apellidos_nombres'] ?: 'Sin asignar') ?></td>
              <td class="text-center"><?= htmlspecialchars($est['parentesco']) ?></td>
              <td><?= htmlspecialchars($est['telefono_completo']) ?></td>
              <td style="font-size: 6.5pt; color: #475569;"><?= htmlspecialchars($est['correo_electronico'] ?: 'No registrado') ?></td>
            </tr>
          <?php else: /* nomina */ ?>
            <tr>
              <td class="col-num"><?= sprintf('%02d', $num) ?></td>
              <?php if ($mostrarCedulaEsc): ?>
                <td style="font-family: monospace; font-size: 6.8pt;"><?= htmlspecialchars($est['documento_etiqueta']) ?></td>
              <?php endif; ?>
              <td style="font-weight: 600;"><?= htmlspecialchars($est['apellidos_nombres']) ?></td>
              <td class="col-age"><?= (int) $est['edad'] ?></td>
              <td class="col-gender <?= $genClass ?>"><?= $gen ?></td>
              <td><?= htmlspecialchars($est['rep_apellidos_nombres'] ?: 'Sin asignar') ?></td>
              <td style="font-family: monospace; font-size: 6.8pt;"><?= htmlspecialchars($est['rep_documento_etiqueta']) ?></td>
              <?php if ($mostrarTelefono): ?>
                <td><?= htmlspecialchars($est['telefono_completo']) ?></td>
              <?php endif; ?>
              <?php if ($mostrarRetiro): ?>
                <td class="text-center" style="font-weight: bold;"><?= $autorizaRetiro ?></td>
              <?php endif; ?>
            </tr>
          <?php endif; ?>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>

  <!-- 5. RESUMEN DE LA NÓMINA -->
  <div class="summary-bar">
    <strong>Resumen de Sección:</strong> <?= $totalEst ?> Estudiantes Matriculados &nbsp;|&nbsp; 
    Femenino: <strong class="badge-f"><?= $totalFem ?></strong> &nbsp;|&nbsp; 
    Masculino: <strong class="badge-m"><?= $totalMasc ?></strong> &nbsp;|&nbsp;
    <em>Documento oficial expedido el <?= $fechaEmision ?> a las <?= $horaEmision ?></em>
  </div>

  <!-- 6. BLOQUE DE FIRMAS INSTITUCIONALES -->
  <div class="signatures-block">
    <table class="signatures-table">
      <tr>
        <td>
          <div class="sig-line"></div>
          <p class="sig-name"><?= htmlspecialchars($docNombre) ?></p>
          <p class="sig-role"><?= htmlspecialchars($docCargo) ?> <?= $docCedulaFmt ? '· ' . $docCedulaFmt : '' ?></p>
          <p class="sig-seal">FIRMA DEL DOCENTE</p>
        </td>
        <td>
          <?php if (!empty($selloUri)): ?>
            <div><img src="<?= $selloUri ?>" class="digital-seal-img" alt="Sello"></div>
          <?php endif; ?>
          <div class="sig-line"></div>
          <p class="sig-name"><?= htmlspecialchars($dirNombre ?: 'Dirección del Plantel') ?></p>
          <p class="sig-role"><?= htmlspecialchars($dirCargo) ?> <?= $dirCedulaFmt ? '· ' . $dirCedulaFmt : '' ?></p>
          <p class="sig-seal">SELLO DE DIRECCIÓN Y FIRMA</p>
        </td>
      </tr>
    </table>
  </div>

</body>
</html>
