<?php
declare(strict_types=1);

/**
 * Plantilla Oficial: Ficha Integral del Empleado / Expediente del Personal (PDF).
 * Documento formal de consulta y registro institucional.
 *
 * Sigue fielmente el patrón unificado de la Ficha Integral del Estudiante:
 * - Hoja tamaño Carta vertical con márgenes oficiales de 2.5 cm.
 * - Tipografía Times New Roman (#000000).
 * - Membrete ministerial e institucional de 3 columnas (20% / 60% / 20%).
 * - Cuadro de datos del empleado con borde fino (0.75 pt), esquinas rectas y sin sombras.
 * - Foto proporcional fija con recuadro neutro si no hay foto.
 * - Tablas con bordes finos (0.75 pt), cabeceras en gris claro (#f2f2f2) y texto negro.
 * - Cierre formal de fecha automática y bloque de firma/sello oficial idéntico.
 *
 * @var array       $config            Configuración institucional
 * @var array       $empleado          Datos del empleado
 * @var array       $formacion         Títulos y formación académica
 * @var array       $asignaciones      Asignaciones docentes
 * @var array       $reposos           Historial de reposos y licencias médicas
 * @var array       $historial         Historial de cambios de estado
 * @var string|null $fotoDataUri       Data URI de la foto del empleado (o null)
 * @var string|null $ministerioLogo    Data URI del logo ministerial
 * @var string|null $plantelLogo       Data URI del escudo del plantel
 * @var string|null $firmaUri          Data URI de la firma digital (o null)
 * @var string|null $selloUri          Data URI del sello húmedo digital (o null)
 * @var string      $dirNombre         Nombre completo del director(a)
 * @var string      $dirCargo          Cargo oficial del director(a)
 * @var string      $dirDocTexto       Documento oficial del director(a) (ej. V-14.852.369)
 * @var string      $nombrePlantel     Nombre oficial del plantel educativo
 * @var string      $plantelDea        Código DEA del plantel
 * @var string      $codigoDependencia Código de Dependencia del plantel
 * @var string      $fechaCierreTexto  Texto de emisión automático con fecha formal
 * @var string      $estilosPdf        CSS compilado del documento
 */

$escape = static fn ($val): string => htmlspecialchars(
    trim((string) ($val ?? '')),
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

$textoOVal = static fn ($val, string $fallback = '—'): string => trim((string) ($val ?? '')) !== ''
    ? htmlspecialchars(trim((string) $val), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    : $fallback;

$fechaFmt = static fn (?string $f): string => !empty($f) ? date('d/m/Y', strtotime((string) $f)) : '—';

// Formato de nombre: Apellidos, Nombres en mayúsculas
$primerApellido  = trim((string) ($empleado['primer_apellido'] ?? ''));
$segundoApellido = trim((string) ($empleado['segundo_apellido'] ?? ''));
$apellidos       = trim($primerApellido . ' ' . $segundoApellido);

$primerNombre  = trim((string) ($empleado['primer_nombre'] ?? ''));
$segundoNombre = trim((string) ($empleado['segundo_nombre'] ?? ''));
$nombres       = trim($primerNombre . ' ' . $segundoNombre);

if ($apellidos !== '' && $nombres !== '') {
    $nombreEmpleadoFmt = mb_strtoupper($apellidos . ', ' . $nombres, 'UTF-8');
} else {
    $nombreRaw = (string) ($empleado['nombre_completo'] ?? 'EMPLEADO');
    $nombreEmpleadoFmt = mb_strtoupper($nombreRaw, 'UTF-8');
}

// Cédula de Identidad
$tipoDoc = strtoupper(trim((string) ($empleado['tipo_documento'] ?? 'V')));
$numDoc  = trim((string) ($empleado['numero_documento'] ?? ''));
$documentoTexto = ($numDoc !== '') ? ($tipoDoc . '-' . $numDoc) : '—';

$sexo = ['F' => 'Femenino', 'M' => 'Masculino'][(string) ($empleado['sexo'] ?? '')] ?? '—';

// Teléfonos
$telP = trim((string) ($empleado['codigo_telefono_principal'] ?? '') . '-' . (string) ($empleado['numero_telefono_principal'] ?? ''), '-');
$telA = trim((string) ($empleado['codigo_telefono_alternativo'] ?? '') . '-' . (string) ($empleado['numero_telefono_alternativo'] ?? ''), '-');
$telefonos = array_filter([$telP, $telA]);
$telefonosTexto = !empty($telefonos) ? implode(' / ', $telefonos) : '—';

// Dirección domiciliaria completa
$dirPartes = array_filter([
    (string) ($empleado['sector_urbanizacion'] ?? ''),
    (string) ($empleado['calle_avenida'] ?? ''),
    (string) ($empleado['nro_casa_apto'] ?? ''),
    (string) ($empleado['parroquia_nombre'] ?? ''),
    (string) ($empleado['municipio_nombre'] ?? ''),
    (string) ($empleado['estado_nombre'] ?? ''),
]);
$direccionCompleta = !empty($dirPartes) ? implode(', ', $dirPartes) : '—';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Ficha Integral del Personal - <?= $escape($nombreEmpleadoFmt) ?></title>
  <style><?= $estilosPdf ?></style>
</head>
<body>

  <!-- Encabezado Institucional de 3 columnas (20% / 60% / 20%) -->
  <?php include __DIR__ . '/../partials/pdf-encabezado.php'; ?>

  <!-- Título del Documento -->
  <div class="doc-title">EXPEDIENTE OFICIAL DE PERSONAL<br><span style="font-size: 10pt; font-weight: normal; letter-spacing: 0;">FICHA INTEGRAL DEL EMPLEADO</span></div>

  <!-- Cuadro de datos del empleado -->
  <div class="student-card">
    <table class="student-card-layout">
      <tr>
        <td class="student-photo-cell">
          <?php if (!empty($fotoDataUri)): ?>
            <img class="student-photo" src="<?= $fotoDataUri ?>" alt="Foto del empleado">
          <?php else: ?>
            <div class="student-photo-placeholder">
              <span>SIN FOTO</span>
            </div>
          <?php endif; ?>
        </td>
        <td class="student-summary-cell">
          <div class="summary-name-box">
            <span class="summary-name-label">EMPLEADO</span>
            <div class="summary-name"><?= $escape($nombreEmpleadoFmt) ?></div>
          </div>

          <table class="info-table">
            <tr>
              <td class="info-label ancho-15">CÉDULA:</td>
              <td class="info-value ancho-35"><?= $escape($documentoTexto) ?></td>
              <td class="info-label ancho-15">ESTADO:</td>
              <td class="info-value ancho-35"><strong><?= strtoupper((string) ($empleado['estado'] ?? 'ACTIVO')) ?></strong></td>
            </tr>
            <tr>
              <td class="info-label">NACIMIENTO:</td>
              <td class="info-value"><?= $fechaFmt($empleado['fecha_nacimiento'] ?? null) ?></td>
              <td class="info-label">GÉNERO:</td>
              <td class="info-value"><?= $escape($sexo) ?></td>
            </tr>
            <tr>
              <td class="info-label">NACIONALIDAD:</td>
              <td class="info-value"><?= $textoOVal($empleado['nacionalidad'] ?? null) ?></td>
              <td class="info-label">TELÉFONO(S):</td>
              <td class="info-value"><?= $escape($telefonosTexto) ?></td>
            </tr>
            <tr>
              <td class="info-label">CORREO:</td>
              <td class="info-value" colspan="3"><?= $textoOVal($empleado['correo_electronico'] ?? null) ?></td>
            </tr>
            <tr>
              <td class="info-label">DOMICILIO:</td>
              <td class="info-value" colspan="3"><?= $escape($direccionCompleta) ?></td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </div>

  <div class="sections-wrapper">

    <!-- Sección 1: Información Laboral -->
    <div class="section-title">1. INFORMACIÓN LABORAL E INSTITUCIONAL</div>
    <table class="data-table mb-12">
      <thead>
        <tr>
          <th>Cargo Oficial</th>
          <th>Turno Asignado</th>
          <th>Fecha de Ingreso</th>
          <th>Condición Operativa</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><strong><?= $escape((string) ($empleado['cargo'] ?? 'Sin cargo')) ?></strong></td>
          <td><?= $escape((string) ($empleado['turno'] ?? '')) ?></td>
          <td><?= $fechaFmt($empleado['fecha_ingreso'] ?? null) ?></td>
          <td>
            <?php if (!empty($empleado['en_reposo'])): ?>
              <span style="color: #b85d00; font-weight: bold;">En Reposo Médico</span>
            <?php else: ?>
              <span style="color: #1a7f37; font-weight: bold;">Activo / Operativo</span>
            <?php endif; ?>
          </td>
        </tr>
      </tbody>
    </table>

    <!-- Sección 2: Formación Académica -->
    <div class="section-title">2. FORMACIÓN ACADÉMICA Y TÍTULOS</div>
    <?php if (!empty($formacion)): ?>
      <table class="data-table mb-12">
        <thead>
          <tr>
            <th class="ancho-35">Título Obtenido</th>
            <th class="ancho-20">Grado Académico</th>
            <th class="ancho-30">Institución Emisora</th>
            <th class="ancho-15">Fecha Obtención</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($formacion as $f): ?>
            <tr>
              <td><strong><?= $escape((string) ($f['titulo'] ?? '—')) ?></strong></td>
              <td><?= $escape((string) ($f['grado_academico'] ?? '—')) ?></td>
              <td><?= $escape((string) ($f['institucion'] ?? '—')) ?></td>
              <td><?= $fechaFmt($f['fecha_obtencion'] ?? null) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p class="empty-note">No posee títulos de formación académica registrados en el sistema.</p>
    <?php endif; ?>

    <!-- Sección 3: Asignaciones Docentes (si aplica) -->
    <?php if (!empty($asignaciones)): ?>
      <div class="section-title">3. ASIGNACIONES ACADÉMICAS INSTITUCIONALES</div>
      <table class="data-table mb-12">
        <thead>
          <tr>
            <th class="ancho-20">Período Escolar</th>
            <th class="ancho-25">Grado y Sección</th>
            <th class="ancho-20">Turno</th>
            <th class="ancho-20">Tipo Asignación</th>
            <th class="ancho-15">Condición</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($asignaciones as $asig): ?>
            <?php $esActiva = !empty($asig['activa']); ?>
            <tr>
              <td><?= $escape((string) ($asig['periodo'] ?? '—')) ?></td>
              <td><strong><?= $escape((string) ($asig['grado'] ?? '—')) ?></strong> - Sección <?= $escape((string) ($asig['seccion'] ?? '—')) ?></td>
              <td><?= $escape((string) ($asig['turno'] ?? '—')) ?></td>
              <td><?= $escape((string) ($asig['tipo_asignacion'] ?? '—')) ?></td>
              <td>
                <span style="font-weight: bold; color: <?= $esActiva ? '#1a7f37' : '#666666' ?>;">
                  <?= $esActiva ? 'Vigente' : 'Finalizada' ?>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <!-- Sección 4: Historial de Reposos y Permisos Médicos -->
    <div class="section-title"><?= !empty($asignaciones) ? '4' : '3' ?>. HISTORIAL DE REPOSOS Y LICENCIAS MÉDICAS</div>
    <?php if (!empty($reposos)): ?>
      <table class="data-table mb-12">
        <thead>
          <tr>
            <th class="ancho-40">Motivo / Causa</th>
            <th class="ancho-15">Fecha Inicio</th>
            <th class="ancho-15">Fecha Fin</th>
            <th class="ancho-15">Estado</th>
            <th class="ancho-15">Registrado Por</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($reposos as $r): ?>
            <?php
              $vigente = !empty($r['vigente']);
              $estadoReposo = strtoupper((string) ($r['estado'] ?? 'CERRADO'));
            ?>
            <tr>
              <td><?= $escape((string) ($r['motivo'] ?? 'Reposo médico')) ?></td>
              <td><?= $fechaFmt($r['fecha_inicio'] ?? null) ?></td>
              <td><?= $fechaFmt($r['fecha_fin'] ?? null) ?></td>
              <td>
                <span style="font-weight: bold; color: <?= $vigente ? '#b85d00' : ($estadoReposo === 'ANULADO' ? '#cf222e' : '#57606a') ?>;">
                  <?= $vigente ? 'Vigente' : ($estadoReposo === 'ANULADO' ? 'Anulado' : 'Concluido') ?>
                </span>
              </td>
              <td><?= $escape((string) ($r['registrado_por'] ?? 'Dirección')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p class="empty-note">No se registran reposos ni licencias médicas para este empleado.</p>
    <?php endif; ?>

    <!-- Cierre formal: Fecha y Firma/Sello -->
    <div class="cierre-documento">
      <div class="fecha-cierre">
        <?= htmlspecialchars($fechaCierreTexto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
      </div>

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
          <div class="firma-nombre"><?= htmlspecialchars($dirNombre, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          <div class="firma-cargo"><?= htmlspecialchars($dirCargo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          <?php if ($dirDocTexto !== ''): ?>
            <div class="firma-ci">C.I. <?= htmlspecialchars($dirDocTexto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div>

</body>
</html>
