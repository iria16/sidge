<?php
declare(strict_types=1);

/**
 * Plantilla Oficial: Ficha Integral del Estudiante (PDF).
 * Documento formal de consulta académica.
 *
 * Sigue fielmente el patrón unificado de Certificado de Calificaciones y Constancia de Estudio:
 * - Hoja tamaño Carta vertical con márgenes de 2.5 cm.
 * - Tipografía Times New Roman (#000000) y eliminación total de colores azules.
 * - Membrete ministerial e institucional de 3 columnas (20% / 60% / 20%).
 * - Cuadro de datos del estudiante con borde fino (0.75 pt), esquinas rectas y sin sombras.
 * - Foto proporcional fija con recuadro neutro si no hay foto.
 * - Cédula Escolar o Cédula de Identidad excluyentes con concordancia de género.
 * - Tablas con bordes finos (0.75 pt), cabeceras en gris claro (#f2f2f2) y texto negro.
 * - Cierre formal de fecha automática y bloque de firma/sello oficial idéntico.
 *
 * @var array       $config                Configuración institucional
 * @var array       $estudiante            Datos del estudiante
 * @var array       $datosEditables        Información médica y tallas
 * @var array       $familiaresNucleo      Grupo familiar del núcleo
 * @var array       $autorizadosParaRetiro Personas autorizadas para retiro
 * @var array       $historialAcademico    Historial de años escolares
 * @var bool        $esDocente             Si es generado por un docente (omite datos sensibles)
 * @var string|null $fotoDataUri           Data URI de la foto del estudiante (o null)
 * @var string|null $ministerioLogo        Data URI del logo ministerial
 * @var string|null $plantelLogo           Data URI del escudo del plantel
 * @var string|null $firmaUri              Data URI de la firma digital (o null)
 * @var string|null $selloUri              Data URI del sello húmedo digital (o null)
 * @var string      $dirNombre             Nombre completo del director(a)
 * @var string      $dirCargo              Cargo oficial del director(a)
 * @var string      $dirDocTexto           Documento oficial del director(a) (ej. V-14.852.369)
 * @var string      $nombrePlantel         Nombre oficial del plantel educativo
 * @var string      $plantelDea            Código DEA del plantel
 * @var string      $codigoDependencia     Código de Dependencia del plantel
 * @var string      $fechaCierreTexto      Texto de emisión automático con fecha formal
 * @var string      $estilosPdf            CSS compilado del documento
 */

use App\Support\ConQuienVive;
use App\Support\Direccion;

$escape = static fn ($val): string => htmlspecialchars(
    trim((string) ($val ?? '')),
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

$textoOVal = static fn ($val, string $fallback = '—'): string => trim((string) ($val ?? '')) !== ''
    ? htmlspecialchars(trim((string) $val), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    : $fallback;

// 1. Nombre completo en mayúsculas y formato APELLIDOS, NOMBRES
$primerApellido  = trim((string) ($estudiante['primer_apellido'] ?? ''));
$segundoApellido = trim((string) ($estudiante['segundo_apellido'] ?? ''));
$apellidos       = trim($primerApellido . ' ' . $segundoApellido);

$primerNombre  = trim((string) ($estudiante['primer_nombre'] ?? ''));
$segundoNombre = trim((string) ($estudiante['segundo_nombre'] ?? ''));
$nombres       = trim($primerNombre . ' ' . $segundoNombre);

if ($apellidos !== '' && $nombres !== '') {
    $nombreEstudianteFmt = mb_strtoupper($apellidos . ', ' . $nombres, 'UTF-8');
} else {
    $nombreRaw = (string) ($estudiante['nombre_completo'] ?? $estudiante['nombre'] ?? '');
    $nombreEstudianteFmt = mb_strtoupper($nombreRaw, 'UTF-8');
}

// 2. Identificación del estudiante: Cédula de Identidad vs Cédula Escolar
$tipoDocEst = strtoupper(trim((string) ($estudiante['tipo_documento'] ?? '')));
$numDocRawEst = trim((string) ($estudiante['numero_documento'] ?? ''));
$esCedulaEscolar = ($tipoDocEst === 'CE') || str_starts_with(strtoupper($numDocRawEst), 'CE');

if ($esCedulaEscolar) {
    $etiquetaDocumento = 'CÉDULA ESCOLAR';
    if (preg_match('/^([VE])(\d+)$/i', $numDocRawEst, $mCE)) {
        $documentoTexto = strtoupper($mCE[1]) . '-' . $mCE[2];
    } elseif (ctype_digit($numDocRawEst)) {
        $documentoTexto = 'V-' . $numDocRawEst;
    } else {
        $documentoTexto = $numDocRawEst;
    }
} else {
    $etiquetaDocumento = 'CÉDULA DE IDENTIDAD';
    $letraEst = ($tipoDocEst === 'E' || str_starts_with(strtoupper($numDocRawEst), 'E')) ? 'E' : 'V';
    $numSoloDigitos = preg_replace('/\D/', '', $numDocRawEst);
    $numFmt = ($numSoloDigitos !== '') ? number_format((int) $numSoloDigitos, 0, '', '.') : $numDocRawEst;
    $documentoTexto = $letraEst . '-' . $numFmt;
}

// 3. Grado y sección
$gradoSeccion = trim(implode(' · ', array_filter([
    trim((string) ($estudiante['grado'] ?? '')),
    trim((string) ($estudiante['seccion'] ?? '')),
], static fn (string $parte): bool => $parte !== '')));

// 4. Fechas, edad y concordancia de género
$fechaNacimiento = 'No registrado';
$edad = 'No registrado';
if (!empty($estudiante['fecha_nacimiento'])) {
    try {
        $fecha = new DateTimeImmutable((string) $estudiante['fecha_nacimiento']);
        $fechaNacimiento = $fecha->format('d/m/Y');
        $edad = $fecha->diff(new DateTimeImmutable('today'))->y . ' años';
    } catch (Throwable) {
        $fechaNacimiento = 'No registrado';
        $edad = 'No registrado';
    }
}

$sexoRaw = strtoupper(trim((string) ($estudiante['sexo'] ?? '')));
$sexo = match ($sexoRaw) {
    'F' => 'FEMENINO',
    'M' => 'MASCULINO',
    default => 'NO REGISTRADO',
};

// 5. Lugar de nacimiento
$lugarNacimiento = '';
if ((int) ($estudiante['id_estado_nacimiento'] ?? 0) > 0) {
    $partes = [];
    if ((int) ($estudiante['id_parroquia_nacimiento'] ?? 0) > 0) {
        $partes[] = (string) ($estudiante['parroquia_nacimiento'] ?? '');
        if ((string) ($estudiante['municipio_nacimiento'] ?? '') !== '') {
            $partes[] = 'Municipio ' . (string) $estudiante['municipio_nacimiento'];
        }
    }
    if ((string) ($estudiante['estado_nacimiento'] ?? '') !== '') {
        $partes[] = (string) $estudiante['estado_nacimiento'];
    }
    $lugarNacimiento = trim(implode(', ', array_filter($partes, static fn ($p): bool => trim((string) $p) !== '')));
}
if ($lugarNacimiento === '') {
    $lugarNacimiento = trim((string) ($estudiante['lugar_nacimiento'] ?? ''));
}
if ($lugarNacimiento === '') {
    $lugarNacimiento = 'No registrado';
}

$nacionalidad = trim((string) ($estudiante['nacionalidad'] ?? ''));
if ($nacionalidad === '') {
    $nacionalidad = 'No registrado';
}

$convivencia = ConQuienVive::etiqueta($datosEditables['con_quien_vive'] ?? null);

// 6. Estatus
$estadoEstudiante = match (strtolower((string) ($estudiante['estado'] ?? ''))) {
    'activo' => 'Activo',
    'retirado' => 'Retirado',
    'egresado' => 'Egresado',
    'inactivo' => 'Inactivo',
    default => 'Activo',
};

// 7. Representante legal y domicilio
$representante = null;
foreach ($familiaresNucleo as $familiar) {
    if (!empty($familiar['representante_principal'])) {
        $representante = $familiar;
        break;
    }
}
if ($representante === null && !empty($familiaresNucleo)) {
    $representante = $familiaresNucleo[0];
}

$telefonoRepresentante = $representante
    ? trim(($representante['contacto']['codigo'] ?? '') . ' ' . ($representante['contacto']['numero'] ?? ''))
    : '';
$telefono = $telefonoRepresentante !== ''
    ? $telefonoRepresentante
    : trim((string) ($estudiante['telefono'] ?? ''));

$direccion = $representante
    ? Direccion::linea(
        $representante['direccion']['calle'] ?? null,
        $representante['direccion']['casa'] ?? null,
        $representante['direccion']['sector'] ?? null,
        $representante['direccion']['parroquia_nombre'] ?? null,
        $representante['direccion']['municipio_nombre'] ?? null,
        $representante['direccion']['estado_nombre'] ?? null,
        $representante['direccion']['referencia'] ?? null
    )
    : '';

$correoRepresentante = $representante['contacto']['correo'] ?? '';

// 8. Preparación de listas de familiares y autorizados
$filaDeVinculado = static function (array $persona): array {
    $parentesco = (string) ($persona['parentesco'] ?? '');
    if (!empty($persona['representante_principal'])) {
        $parentesco = trim(($parentesco !== '' ? $parentesco : 'Representante') . ' (Rep. Legal)');
    }
    return [
        'parentesco'   => $parentesco !== '' ? $parentesco : '—',
        'nombre'       => (string) ($persona['nombre_referencia'] ?? '—'),
        'documento'    => trim(($persona['tipo_documento'] ?? '') . '-' . ($persona['numero_documento'] ?? '')),
        'nacionalidad' => (string) ($persona['nacionalidad'] ?? '—'),
        'telefono'     => trim(($persona['contacto']['codigo'] ?? '') . ' ' . ($persona['contacto']['numero'] ?? '')),
    ];
};

$familiaresTabla = array_map($filaDeVinculado, $familiaresNucleo);
$autorizadosTabla = array_map($filaDeVinculado, $autorizadosParaRetiro);

foreach ($autorizadosTabla as $i => $fila) {
    $persona = $autorizadosParaRetiro[$i] ?? [];
    $esTambienFamiliar = !empty($persona['representante_principal'])
        || (($persona['parentesco'] ?? '') !== '' && ($persona['parentesco'] ?? '') !== 'Otro');
    $autorizadosTabla[$i]['condicion'] = $esTambienFamiliar ? 'También es familiar' : 'Solo autorizado';
}

$historialConDatos = array_values(array_filter($historialAcademico, static function (array $reg): bool {
    return trim((string) ($reg['anio_escolar'] ?? '')) !== ''
        || trim((string) ($reg['grado'] ?? '')) !== ''
        || trim((string) ($reg['seccion'] ?? '')) !== '';
}));
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Ficha Integral del Estudiante - <?= $escape($nombreEstudianteFmt) ?></title>
  <style><?= $estilosPdf ?></style>
</head>
<body>

  <!-- Encabezado Institucional de 3 columnas (20% / 60% / 20%) -->
  <?php include dirname(__DIR__, 2) . '/partials/pdf-encabezado.php'; ?>

  <!-- Título del Documento -->
  <div class="doc-title">FICHA INTEGRAL DEL ESTUDIANTE</div>

  <!-- Cuadro de datos del estudiante (Borde fino 0.75pt, esquinas rectas, sin sombras) -->
  <div class="student-card">
    <table class="student-card-layout">
      <tr>
        <td class="student-photo-cell">
          <?php if (!empty($fotoDataUri)): ?>
            <img class="student-photo" src="<?= $fotoDataUri ?>" alt="Foto del estudiante">
          <?php else: ?>
            <div class="student-photo-placeholder">
              <span>SIN FOTO</span>
            </div>
          <?php endif; ?>
        </td>
        <td class="student-summary-cell">
          <div class="summary-name-box">
            <span class="field-label">NOMBRE COMPLETO</span>
            <div class="student-name-val"><?= $escape($nombreEstudianteFmt) ?></div>
          </div>
          <table class="student-details-grid">
            <tr>
              <td class="detail-cell">
                <span class="field-label"><?= $escape($etiquetaDocumento) ?></span>
                <strong class="detail-val"><?= $escape($documentoTexto) ?></strong>
              </td>
              <td class="detail-cell">
                <span class="field-label">GRADO Y SECCIÓN</span>
                <strong class="detail-val"><?= $textoOVal($gradoSeccion, 'No asignado') ?></strong>
              </td>
            </tr>
            <tr>
              <td class="detail-cell">
                <span class="field-label">EDAD</span>
                <strong class="detail-val"><?= $textoOVal($edad, 'No registrada') ?></strong>
              </td>
              <td class="detail-cell">
                <span class="field-label">ESTATUS</span>
                <strong class="detail-val"><?= $textoOVal($estadoEstudiante, 'Activo') ?></strong>
              </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </div>

  <!-- Sección 1: Datos Personales -->
  <div class="section-title">1. DATOS PERSONALES</div>
  <table class="field-grid">
    <tr>
      <td class="field">
        <span class="field-label">NOMBRES</span>
        <span class="field-value"><?= $textoOVal($nombres, 'No registrado') ?></span>
      </td>
      <td class="field">
        <span class="field-label">APELLIDOS</span>
        <span class="field-value"><?= $textoOVal($apellidos, 'No registrado') ?></span>
      </td>
    </tr>
    <tr>
      <td class="field">
        <span class="field-label"><?= $escape($etiquetaDocumento) ?></span>
        <span class="field-value"><?= $escape($documentoTexto) ?></span>
      </td>
      <td class="field">
        <span class="field-label">FECHA DE NACIMIENTO</span>
        <span class="field-value"><?= $textoOVal($fechaNacimiento, 'No registrada') ?></span>
      </td>
    </tr>
    <tr>
      <td class="field">
        <span class="field-label">LUGAR DE NACIMIENTO</span>
        <span class="field-value"><?= $textoOVal($lugarNacimiento, 'No registrado') ?></span>
      </td>
      <td class="field">
        <span class="field-label">NACIONALIDAD</span>
        <span class="field-value"><?= $textoOVal($nacionalidad, 'No registrada') ?></span>
      </td>
    </tr>
    <tr>
      <td class="field">
        <span class="field-label">EDAD</span>
        <span class="field-value"><?= $textoOVal($edad, 'No registrada') ?></span>
      </td>
      <td class="field">
        <span class="field-label">GÉNERO</span>
        <span class="field-value"><?= $escape($sexo) ?></span>
      </td>
    </tr>
    <tr>
      <td class="field wide" colspan="2">
        <span class="field-label">CON QUIÉN VIVE</span>
        <span class="field-value"><?= $escape($convivencia) ?></span>
      </td>
    </tr>
  </table>

  <!-- Recuadro de Dirección y Contacto del Representante Legal -->
  <div class="contact-box">
    <span class="contact-box-title">DATOS DE UBICACIÓN Y CONTACTO (REPRESENTANTE LEGAL)</span>
    <table class="field-grid">
      <tr>
        <td class="field">
          <span class="field-label">TELÉFONO DE CONTACTO</span>
          <span class="field-value"><?= $textoOVal($telefono, 'No registrado') ?></span>
        </td>
        <td class="field">
          <span class="field-label">CORREO ELECTRÓNICO</span>
          <span class="field-value"><?= $textoOVal($correoRepresentante, 'No registrado') ?></span>
        </td>
      </tr>
      <tr>
        <td class="field wide" colspan="2">
          <span class="field-label">DIRECCIÓN DE DOMICILIO</span>
          <span class="field-value"><?= $textoOVal($direccion, 'No registrada') ?></span>
        </td>
      </tr>
    </table>
  </div>

  <?php if (!$esDocente): ?>
  <!-- Página 2: Información Médica y Tallas, Familiares, Autorizados e Historial -->
  <div class="second-page">
    <!-- Sección 2: Información Médica y Tallas -->
    <div class="section-title">2. INFORMACIÓN MÉDICA Y TALLAS</div>
    <table class="measurements-table">
      <tr>
        <td class="measure-cell">
          <span class="field-label">CAMISA</span>
          <strong><?= $textoOVal($datosEditables['talla_camisa'] ?? '', '—') ?></strong>
        </td>
        <td class="measure-cell">
          <span class="field-label">PANTALÓN</span>
          <strong><?= $textoOVal($datosEditables['talla_pantalon'] ?? '', '—') ?></strong>
        </td>
        <td class="measure-cell">
          <span class="field-label">CALZADO</span>
          <strong><?= $textoOVal($datosEditables['talla_zapato'] ?? '', '—') ?></strong>
        </td>
        <td class="measure-cell">
          <span class="field-label">TIPO DE SANGRE</span>
          <strong><?= $textoOVal($datosEditables['tipo_sangre'] ?? '', '—') ?></strong>
        </td>
      </tr>
    </table>

    <table class="field-grid">
      <tr>
        <td class="field">
          <span class="field-label">ALERGIAS</span>
          <span class="field-value"><?= $textoOVal($datosEditables['alergias'] ?? '', 'No registrado') ?></span>
        </td>
        <td class="field">
          <span class="field-label">MEDICAMENTOS</span>
          <span class="field-value"><?= $textoOVal($datosEditables['medicamentos'] ?? '', 'No registrado') ?></span>
        </td>
      </tr>
      <tr>
        <td class="field">
          <span class="field-label">ENFERMEDADES</span>
          <span class="field-value"><?= $textoOVal($datosEditables['enfermedades'] ?? '', 'No registrado') ?></span>
        </td>
        <td class="field">
          <span class="field-label">CONDICIONES ESPECIALES</span>
          <span class="field-value"><?= $textoOVal($datosEditables['condiciones_especiales'] ?? '', 'No registrado') ?></span>
        </td>
      </tr>
      <tr>
        <td class="field wide" colspan="2">
          <span class="field-label">OBSERVACIONES MÉDICAS</span>
          <span class="field-value"><?= $textoOVal($datosEditables['observaciones_medicas'] ?? '', 'No registrado') ?></span>
        </td>
      </tr>
      <tr>
        <td class="field wide" colspan="2">
          <span class="field-label">OBSERVACIONES DE TALLA</span>
          <span class="field-value"><?= $textoOVal($datosEditables['talla_observaciones'] ?? '', 'No registrado') ?></span>
        </td>
      </tr>
    </table>
  <?php else: ?>
  <div class="second-page">
  <?php endif; ?>

    <!-- Sección 3: Datos del Grupo Familiar -->
    <div class="section-title">3. DATOS DEL GRUPO FAMILIAR</div>
    <?php if (!empty($familiaresTabla)): ?>
      <table class="data-table">
        <thead>
          <tr>
            <th class="col-parentesco">PARENTESCO</th>
            <th class="col-nombre">NOMBRE Y APELLIDO</th>
            <th class="col-documento">CÉDULA</th>
            <th class="col-telefono">TELÉFONO</th>
            <th class="col-nacionalidad">NACIONALIDAD</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($familiaresTabla as $familiar): ?>
            <tr>
              <td class="strong-cell"><?= $escape($familiar['parentesco']) ?></td>
              <td><?= $escape($familiar['nombre']) ?></td>
              <td><?= $textoOVal($familiar['documento']) ?></td>
              <td><?= $textoOVal($familiar['telefono']) ?></td>
              <td><?= $textoOVal($familiar['nacionalidad']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p class="empty-note">No hay familiares directos registrados para este estudiante.</p>
    <?php endif; ?>

    <!-- Sección 4: Personas Autorizadas para Retirar al Estudiante -->
    <div class="section-title">4. PERSONAS AUTORIZADAS PARA RETIRAR AL ESTUDIANTE</div>
    <?php if (!empty($autorizadosTabla)): ?>
      <table class="data-table">
        <thead>
          <tr>
            <th class="col-parentesco">PARENTESCO</th>
            <th class="col-nombre">NOMBRE Y APELLIDO</th>
            <th class="col-documento">CÉDULA</th>
            <th class="col-telefono">TELÉFONO</th>
            <th class="col-condicion">CONDICIÓN</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($autorizadosTabla as $autorizado): ?>
            <tr>
              <td class="strong-cell"><?= $escape($autorizado['parentesco']) ?></td>
              <td><?= $escape($autorizado['nombre']) ?></td>
              <td><?= $textoOVal($autorizado['documento']) ?></td>
              <td><?= $textoOVal($autorizado['telefono']) ?></td>
              <td><?= $escape($autorizado['condicion']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p class="empty-note">Además de sus familiares directos o representante legal, no hay otras personas autorizadas para retirar al estudiante.</p>
    <?php endif; ?>

    <!-- Sección 5: Historial Académico Institucional -->
    <div class="section-title">5. HISTORIAL ACADÉMICO INSTITUCIONAL</div>
    <?php if (!empty($historialConDatos)): ?>
      <?php foreach ($historialConDatos as $registro): ?>
        <?php
          $estadoAnio = strtoupper((string) ($registro['estado_anio'] ?? ''));
          $estadoMatricula = strtoupper((string) ($registro['tipo_matricula'] ?? ''));
          $estadoEtiqueta = $estadoAnio === 'ACTIVO'
              ? 'En curso'
              : ($estadoAnio === 'CERRADO' ? 'Finalizado' : ucfirst(strtolower($estadoAnio)));
          $gradoHistorico = trim(implode(' · ', array_filter([
              trim((string) ($registro['grado'] ?? '')),
              trim((string) ($registro['seccion'] ?? '')) !== '' ? 'Sección ' . trim((string) $registro['seccion']) : '',
          ], static fn (string $p): bool => $p !== '')));
          $estadoHistorico = trim(implode(' · ', array_filter([
              $estadoEtiqueta,
              $estadoMatricula !== '' ? ucfirst(strtolower($estadoMatricula)) : '',
          ], static fn (string $p): bool => $p !== '')));
        ?>
        <div class="history-box">
          <?php if (!empty($registro['anio_escolar'])): ?>
            <div class="history-year">PERÍODO <?= $escape($registro['anio_escolar']) ?></div>
          <?php endif; ?>
          <?php if ($gradoHistorico !== '' || !empty($registro['turno'])): ?>
            <div class="history-grade">
              <?= $escape($gradoHistorico) ?>
              <?php if (!empty($registro['turno'])): ?> · Turno <?= $escape($registro['turno']) ?><?php endif; ?>
            </div>
          <?php endif; ?>
          <?php if ($estadoHistorico !== ''): ?>
            <span class="history-status"><?= $escape($estadoHistorico) ?></span>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p class="empty-note">No hay registros de historial académico para este estudiante.</p>
    <?php endif; ?>

    <!-- Cierre: Fecha automática y bloque de firma/sello juntos para evitar firmas huérfanas -->
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
