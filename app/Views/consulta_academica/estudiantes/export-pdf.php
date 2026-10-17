<?php
declare(strict_types=1);

/* La redacción del domicilio la pone Support\Direccion, la misma del modal de
   detalle del empleado y de Listados y Actas. Antes estaba escrita aquí tramo
   por tramo, y con un campo faltando cada copia se desincronizaba. */
use App\Support\Direccion;

$valor = static fn ($value): string => trim((string) ($value ?? ''));
$hayDato = static fn ($value): bool => $valor($value) !== '';
$hayDatos = static function (array $valores) use ($hayDato): bool {
    foreach ($valores as $valorActual) {
        if ($hayDato($valorActual)) {
            return true;
        }
    }
    return false;
};
$escape = static fn ($value): string => htmlspecialchars(
    $valor($value),
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);
$campo = static function (string $etiqueta, $valorCampo, bool $anchoCompleto = false) use ($hayDato, $escape): string {
    if (!$hayDato($valorCampo)) {
        return '';
    }
    $clase = $anchoCompleto ? 'field wide' : 'field';
    $colspan = $anchoCompleto ? ' colspan="2"' : '';
    return '<td class="' . $clase . '"' . $colspan . '><span class="field-label">'
        . $escape($etiqueta) . '</span><span class="field-value">'
        . $escape($valorCampo) . '</span></td>';
};
$filaCampos = static function (array $campos) use ($campo): string {
    $celdas = '';
    foreach ($campos as [$etiqueta, $valorCampo, $anchoCompleto]) {
        $celdas .= $campo($etiqueta, $valorCampo, $anchoCompleto);
    }
    return $celdas === '' ? '' : '<tr>' . $celdas . '</tr>';
};
$resumen = static function (string $etiqueta, $valorResumen) use ($hayDato, $escape): string {
    if (!$hayDato($valorResumen)) {
        return '<td class="summary-item"></td>';
    }
    return '<td class="summary-item"><span class="eyebrow">' . $escape($etiqueta)
        . '</span><strong>' . $escape($valorResumen) . '</strong></td>';
};
$nombreCompleto = trim(implode(' ', array_filter([
    $estudiante['primer_nombre'] ?? '',
    $estudiante['segundo_nombre'] ?? '',
    $estudiante['primer_apellido'] ?? '',
    $estudiante['segundo_apellido'] ?? '',
])));
$documento = trim(
    (string) ($estudiante['tipo_documento'] ?? '') . '-' . (string) ($estudiante['numero_documento'] ?? ''),
    '-'
);
$etiquetaDocumento = str_starts_with(strtoupper((string) ($estudiante['tipo_documento'] ?? '')), 'CE')
    ? 'Cédula escolar'
    : 'Cédula de identidad';
/* Se busca en $familiaresNucleo, el MISMO grupo que recorre la ficha digital
   (consulta_academica/estudiantes/show.php). Antes este bucle recorría la lista
   completa de getFamiliares(): con la separación actual —el representante entra
   siempre al núcleo, y también pueden entrar otros con parentesco informado— los
   dos recorridos eran distintos, y si algún día una ficha tuviera dos personas
   marcadas como representante, el PDF podía tomar una y la pantalla otra. Que sea
   el mismo grupo es lo que impide que el membrete y la ficha discrepan.

   No hace falta comprobar estado_relacion: getFamiliares(), que es la base de los
   dos grupos, ya devuelve solo relaciones VIGENTES (WHERE pe.estado_relacion =
   ACTIVO), de modo que lo que llega aquí está activo por construcción. La ausencia
   de representante se comprueba más abajo, contra la regla de negocio. */
$representante = null;
foreach ($familiaresNucleo as $familiar) {
    if (!empty($familiar['representante_principal'])) {
        $representante = $familiar;
        break;
    }
}
$telefonoRepresentante = $representante
    ? trim(($representante['contacto']['codigo'] ?? '') . ' ' . ($representante['contacto']['numero'] ?? ''))
    : '';
/* El estudiante es menor de edad: la dirección que vale es la de su representante
   legal, no la suya. Por eso NO hay respaldo a la dirección del alumno —que además
   casi nunca está registrada— y por eso getById() no se molestó en traer los tres
   niveles de la suya. La redacción la pone Support\Direccion, igual que en el modal
   de detalle del empleado y en Listados y Actas.

   Que no haya representante es una INCUMPLIMIENTO de la regla de negocio, no un
   caso válido: el alta la rechaza (FamiliaresModelTrait, guarda 1) y tampoco se
   puede desvincular al último (guarda 2). Aquí se corta en vez de imprimir el campo
   en blanco, porque esto es un documento oficial: un membrete con la Dirección
   vacía se le entrega a la familia sin que nadie note que faltaba, y esa falla no
   se descubre hasta que un retiro o una constancia tienen que resolverse con ese
   expediente. El require está dentro del try del Controller, así que esto sale como
   error controlado y no como un PDF con un hueco. */
if ($representante === null) {
    throw new \RuntimeException(sprintf(
        'El estudiante %s no tiene representante legal activo, y la regla de negocio exige uno. '
        . 'No se emite el expediente sin él: revisa los familiares del alumno.',
        (string) ($estudiante['nombre'] ?? ('id ' . ($estudiante['id'] ?? 'desconocido')))
    ));
}
$direccion = Direccion::linea(
    $representante['direccion']['calle'] ?? null,
    $representante['direccion']['casa'] ?? null,
    $representante['direccion']['sector'] ?? null,
    $representante['direccion']['parroquia_nombre'] ?? null,
    $representante['direccion']['municipio_nombre'] ?? null,
    $representante['direccion']['estado_nombre'] ?? null,
    $representante['direccion']['referencia'] ?? null
);
$fechaNacimiento = '';
$edad = '';
if (!empty($estudiante['fecha_nacimiento'])) {
    try {
        $fecha = new DateTimeImmutable((string) $estudiante['fecha_nacimiento']);
        $fechaNacimiento = $fecha->format('d/m/Y');
        $edad = $fecha->diff(new DateTimeImmutable('today'))->y . ' años';
    } catch (Throwable) {
        $fechaNacimiento = '';
        $edad = '';
    }
}
$sexo = match ($estudiante['sexo'] ?? '') {
    'F' => 'Femenino',
    'M' => 'Masculino',
    default => '',
};

/* Lugar de nacimiento. Del estado y la parroquia salen el nombre y el municipio, que no
   se guarda: es el mismo JOIN de dos niveles que hace direccion con id_parroquia. */
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
$telefono = $telefonoRepresentante !== ''
    ? $telefonoRepresentante
    : (string) ($estudiante['telefono'] ?? '');
$nombreArchivoTitulo = $nombreCompleto;
$gradoSeccion = trim(implode(' · ', array_filter([
    $valor($estudiante['grado'] ?? ''),
    $valor($estudiante['seccion'] ?? ''),
], static fn (string $parte): bool => $parte !== '')));
// Estado del ciclo. El PDF usa la misma clave que la pantalla (activo/retirado/egresado),
// y se acepta 'inactivo' por si el reporte se genera con datos de otra fuente.
$estadoEstudiante = match (strtolower((string) ($estudiante['estado'] ?? ''))) {
    'activo' => 'Activo',
    'retirado' => 'Retirado',
    'egresado' => 'Egresado',
    'inactivo' => 'Inactivo',
    default => '',
};
$hayResumenEstudiante = $hayDato($nombreCompleto)
    || $hayDato($documento)
    || $hayDato($gradoSeccion)
    || $hayDato($edad)
    || $hayDato($estadoEstudiante)
    || !empty($fotoDataUri)
    || $hayDato($estudiante['iniciales'] ?? '');
$datosPersonales = [
    ['Nombres', trim(($estudiante['primer_nombre'] ?? '') . ' ' . ($estudiante['segundo_nombre'] ?? '')), false],
    ['Apellidos', trim(($estudiante['primer_apellido'] ?? '') . ' ' . ($estudiante['segundo_apellido'] ?? '')), false],
    ['Cédula de identidad', $documento, false],
    ['Fecha de nacimiento', $fechaNacimiento, false],
    ['Lugar de nacimiento', $lugarNacimiento, false],
    /* Solo el estudiante tiene nacionalidad, así que la fila se imprime aunque el dato
       no esté: el $datoVacio de la plantilla pone "No registra" y es preferible a una
       fila que aparece y desaparece según el alumno. */
    ['Nacionalidad', trim((string) ($estudiante['nacionalidad'] ?? '')), false],
    ['Edad', $edad, false],
    ['Género', $sexo, false],
];
$medidas = [
    ['Camisa', $datosEditables['talla_camisa'] ?? ''],
    ['Pantalón', $datosEditables['talla_pantalon'] ?? ''],
    ['Calzado', $datosEditables['talla_zapato'] ?? ''],
    ['Tipo de sangre', $datosEditables['tipo_sangre'] ?? ''],
];
$datosMedicos = [
    ['Alergias', $datosEditables['alergias'] ?? '', false],
    ['Medicamentos', $datosEditables['medicamentos'] ?? '', false],
    ['Enfermedades', $datosEditables['enfermedades'] ?? '', false],
    ['Condiciones especiales', $datosEditables['condiciones_especiales'] ?? '', false],
    ['Observaciones médicas', $datosEditables['observaciones_medicas'] ?? '', true],
    ['Observaciones de talla', $datosEditables['talla_observaciones'] ?? '', true],
];
$hayDatosMedicos = $hayDatos(array_column($medidas, 1))
    || $hayDatos(array_column($datosMedicos, 1));
$correoRepresentante = $representante['contacto']['correo'] ?? '';
$hayContactoRepresentante = $hayDato($telefono)
    || $hayDato($direccion)
    || $hayDato($correoRepresentante);
    /* GRUPO FAMILIAR Y AUTORIZADOS A RETIRO. Misma separación y misma regla que la pantalla: son
 dos preguntas distintas —a quién se le informa y quién puede retirar al alumno— y en una sola
 tabla un autorizado por confianza parecía un pariente. Por eso van en dos bloques y no en
 una lista con una columna.*/
$armarTablaVinculados = static function (array $personas) use ($hayDato): array {
    $filas = [];
    foreach ($personas as $persona) {
        $sinDatos = !$hayDato($persona['nombre_referencia'] ?? '')
            && !$hayDato($persona['parentesco'] ?? '')
            && !$hayDato(trim(($persona['tipo_documento'] ?? '') . '-' . ($persona['numero_documento'] ?? ''), '-'))
            && !$hayDato(trim(($persona['contacto']['codigo'] ?? '') . ' ' . ($persona['contacto']['numero'] ?? '')));
        if ($sinDatos) {
            continue;   // un vínculo sin nombre ni contacto no informa nada en un reporte
        }
        $filas[] = $persona;
    }
    return $filas;
};

$filasFamiliares = $armarTablaVinculados($familiaresNucleo);
$filasAutorizados = $armarTablaVinculados($autorizadosParaRetiro);

$filaDeVinculado = static function (array $persona): array {
    $parentesco = (string) ($persona['parentesco'] ?? '');
    if (!empty($persona['representante_principal'])) {
        $parentesco = trim(($parentesco !== '' ? $parentesco : 'Representante') . ' (Rep. Legal)');
    }
    return [
        'parentesco' => $parentesco,
        'nombre'     => $persona['nombre_referencia'] ?? '',
        'documento'  => trim(($persona['tipo_documento'] ?? '') . '-' . ($persona['numero_documento'] ?? '')),
        'nacionalidad' => (string) ($persona['nacionalidad'] ?? ''),
        'telefono'   => trim(($persona['contacto']['codigo'] ?? '') . ' ' . ($persona['contacto']['numero'] ?? '')),
    ];
};

$familiaresTabla = array_map($filaDeVinculado, $filasFamiliares);
$autorizadosTabla = array_map($filaDeVinculado, $filasAutorizados);

/* La CONDICIÓN va impresa porque en un papel no hay color ni badge: quien lee
 tiene que saber sin ambigüedad si esa persona es familia o solo está autorizada
 por confianza. Es la misma etiqueta que muestra la pantalla.*/
foreach ($autorizadosTabla as $i => $fila) {
    $persona = $filasAutorizados[$i];
    $esTambienFamiliar = !empty($persona['representante_principal'])
        || (($persona['parentesco'] ?? '') !== '' && ($persona['parentesco'] ?? '') !== 'Otro');
    $autorizadosTabla[$i]['condicion'] = $esTambienFamiliar ? 'También es familiar' : 'Solo autorizado';
}

/* Las columnas que no tengan ningún dato en toda la tabla no se imprimen: una
 columna vacía en un reporte oficial ocupa espacio y dice que falta un dato que
 en realidad no existe. Es lo que ya hacía la tabla de familiares.*/
$columnasFamiliares = [
    'parentesco' => $hayDatos(array_column($familiaresTabla, 'parentesco')),
    'nombre'     => $hayDatos(array_column($familiaresTabla, 'nombre')),
    'documento'  => $hayDatos(array_column($familiaresTabla, 'documento')),
    'telefono'   => $hayDatos(array_column($familiaresTabla, 'telefono')),
    /* La nacionalidad se imprime solo si algún familiar la tiene registrada. Con la
       tabla casi vacía al arrancar el sistema, una columna fija sería una columna de
       guiones; el mecanismo de ocultar columnas vacías ya existe y se reaprovecha. */
    'nacionalidad' => $hayDatos(array_column($familiaresTabla, 'nacionalidad')),
];
$columnasAutorizados = [
    'parentesco' => $hayDatos(array_column($autorizadosTabla, 'parentesco')),
    'nombre'     => $hayDatos(array_column($autorizadosTabla, 'nombre')),
    'documento'  => $hayDatos(array_column($autorizadosTabla, 'documento')),
    'telefono'   => $hayDatos(array_column($autorizadosTabla, 'telefono')),
    'nacionalidad' => $hayDatos(array_column($autorizadosTabla, 'nacionalidad')),
    /* La condición se imprime siempre: es el dato que distingue esta tabla.*/
    'condicion'  => true,
];
$hayFamiliares = $familiaresTabla !== [];
$hayAutorizados = $autorizadosTabla !== [];
$historialConDatos = array_values(array_filter($historialAcademico, static function (array $registro) use ($hayDato): bool {
    return $hayDato($registro['anio_escolar'] ?? '')
        || $hayDato($registro['grado'] ?? '')
        || $hayDato($registro['seccion'] ?? '')
        || $hayDato($registro['turno'] ?? '')
        || $hayDato($registro['estado_anio'] ?? '')
        || $hayDato($registro['tipo_matricula'] ?? '');
}));
$hayHistorial = $historialConDatos !== [];
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Expediente estudiantil - <?= $escape($nombreArchivoTitulo) ?></title>
  <style><?= $estilosPdf ?></style>
</head>
<body>
  <div class="footer">Escuela Básica Isidro Ramírez · Ficha Integral del Estudiante</div>
  <table class="document-header">
    <tr>
      <td class="document-header__ministry">
        <?php if (!empty($ministryLogoDataUri)): ?>
          <img class="ministry-logo" src="<?= $escape($ministryLogoDataUri) ?>" alt="Ministerio del Poder Popular para la Educación">
        <?php endif; ?>
      </td>
      <td class="letterhead-text">
        <span>REPÚBLICA BOLIVARIANA DE VENEZUELA</span>
        <span>MINISTERIO DEL PODER POPULAR PARA LA EDUCACIÓN</span>
        <span>ESCUELA BÁSICA ISIDRO RAMÍREZ</span>
        <span class="letterhead-code">Código DEA: OD05750814</span>
      </td>
      <td class="document-header__school">
        <?php if (!empty($schoolLogoDataUri)): ?>
          <img class="school-logo" src="<?= $escape($schoolLogoDataUri) ?>" alt="Escuela Básica Isidro Ramírez">
        <?php endif; ?>
      </td>
    </tr>
  </table>

  <h1 class="document-title">FICHA INTEGRAL DEL ESTUDIANTE</h1>

  <?php if ($hayResumenEstudiante): ?>
  <section class="student-card">
    <table class="student-card-layout">
      <colgroup>
        <col class="student-card-layout__photo">
        <col class="student-card-layout__summary">
      </colgroup>
      <tr>
        <td class="student-photo-cell">
          <?php if (!empty($fotoDataUri)): ?>
            <img class="student-photo" src="<?= $escape($fotoDataUri) ?>" alt="">
          <?php elseif ($hayDato($estudiante['iniciales'] ?? '')): ?>
            <div class="student-placeholder"><?= $escape($estudiante['iniciales']) ?></div>
          <?php endif; ?>
        </td>
        <td class="student-summary-cell">
          <?php if ($hayDato($nombreArchivoTitulo)): ?>
            <span class="eyebrow">Nombre completo</span>
            <div class="student-name"><?= $escape($nombreArchivoTitulo) ?></div>
          <?php endif; ?>
          <table class="student-details">
            <tr>
              <?= $resumen($etiquetaDocumento, $documento) ?>
              <?= $resumen('Grado y sección', $gradoSeccion) ?>
            </tr>
            <tr>
              <?= $resumen('Edad', $edad) ?>
              <?= $resumen('Estatus', $estadoEstudiante) ?>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </section>
  <?php endif; ?>

  <?php if ($hayDatos(array_column($datosPersonales, 1))): ?>
    <h2 class="section-title">1. Datos personales</h2>
    <table class="field-grid">
      <?= $filaCampos(array_slice($datosPersonales, 0, 2)) ?>
      <?= $filaCampos(array_slice($datosPersonales, 2, 2)) ?>
      <?= $filaCampos(array_slice($datosPersonales, 4, 2)) ?>
    </table>
  <?php endif; ?>

  <?php if ($hayContactoRepresentante): ?>
    <div class="contact-box">
      <span class="field-label">Dirección y teléfono</span>
      <table class="field-grid">
        <?= $filaCampos([['Teléfono', $telefono, false]]) ?>
        <?= $filaCampos([['Dirección', $direccion, true]]) ?>
        <?= $filaCampos([['Correo electrónico', $correoRepresentante, true]]) ?>
      </table>
    </div>
  <?php endif; ?>

  <?php if ($hayDatosMedicos): ?>
    <h2 class="section-title">2. Información médica y tallas</h2>
    <?php if ($hayDatos(array_column($medidas, 1))): ?>
      <div class="measurements">
        <?php foreach ($medidas as [$etiqueta, $valorMedida]): ?>
          <?php if ($hayDato($valorMedida)): ?>
            <div class="measure"><span class="field-label"><?= $escape($etiqueta) ?></span><strong><?= $escape($valorMedida) ?></strong></div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <table class="field-grid">
      <?= $filaCampos(array_slice($datosMedicos, 0, 2)) ?>
      <?= $filaCampos(array_slice($datosMedicos, 2, 2)) ?>
      <?= $filaCampos(array_slice($datosMedicos, 4, 1)) ?>
      <?= $filaCampos(array_slice($datosMedicos, 5, 1)) ?>
    </table>
  <?php endif; ?>

  <?php /* La sección del familiar y la de autorizados van en la misma hoja: son la
 misma relación con el alumno partida en dos preguntas, y separarlas en
 páginas distintas obligaría a pasar la hoja para comparar.*/
         if ($hayFamiliares || $hayAutorizados || $hayHistorial): ?>
  <section class="second-page">
    <?php /* ── 3. Datos del grupo familiar El encabezado se imprime siempre, haya familia o no. Antes solo salía con
 datos, y el resultado era un expediente que saltaba del "2." al "4.": un
 hueco en la numeración de un documento oficial se lee como error, y quien
 lo lee se queda sin saber si la familia se registró o si nadie la buscó. La
 sección 4 afirma el vacío, esta tiene que afirmarlo igual.*/
    if ($hayFamiliares): ?>
    <h2 class="section-title">3. Datos del grupo familiar</h2>
      <table class="family-table">
        <thead>
          <tr>
            <?php if ($columnasFamiliares['parentesco']): ?><th>Parentesco</th><?php endif; ?>
            <?php if ($columnasFamiliares['nombre']): ?><th>Nombre y apellido</th><?php endif; ?>
            <?php if ($columnasFamiliares['documento']): ?><th>Cédula</th><?php endif; ?>
            <?php if ($columnasFamiliares['telefono']): ?><th>Teléfono</th><?php endif; ?>
            <?php if ($columnasFamiliares['nacionalidad']): ?><th>Nacionalidad</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($familiaresTabla as $vinculado): ?>
            <tr>
              <?php if ($columnasFamiliares['parentesco']): ?><td><?= $escape($vinculado['parentesco']) ?></td><?php endif; ?>
              <?php if ($columnasFamiliares['nombre']): ?><td><?= $escape($vinculado['nombre']) ?></td><?php endif; ?>
              <?php if ($columnasFamiliares['documento']): ?><td><?= $escape($vinculado['documento']) ?></td><?php endif; ?>
              <?php if ($columnasFamiliares['telefono']): ?><td><?= $escape($vinculado['telefono']) ?></td><?php endif; ?>
              <?php if ($columnasFamiliares['nacionalidad']): ?><td><?= $escape($vinculado['nacionalidad']) ?></td><?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
    <h2 class="section-title">3. Datos del grupo familiar</h2>
      <p class="empty">No hay familiares registrados para este alumno.</p>
    <?php endif; ?>

    <?php /* ── 4. Personas autorizadas para retirar Lista propia, aunque haya personas repetidas en la de arriba: quien
 retira al alumno puede ser un tercero de confianza y el papel tiene que
 dejarlo dicho. Por eso la columna "Condición" se imprime siempre y no
 se oculta cuando no hay datos, como las otras.
 La tabla va aunque esté vacía: el reporte tiene que afirmar que nadie
 está autorizado, porque el silencio se lee como "no se revisó".*/
    if ($hayAutorizados): ?>
    <h2 class="section-title">4. Personas autorizadas para retirar al alumno</h2>
      <table class="family-table family-table--autorizados">
        <thead>
          <tr>
            <?php if ($columnasAutorizados['parentesco']): ?><th class="col-parentesco">Parentesco</th><?php endif; ?>
            <?php if ($columnasAutorizados['nombre']): ?><th class="col-nombre">Nombre y apellido</th><?php endif; ?>
            <?php if ($columnasAutorizados['documento']): ?><th class="col-documento">Cédula</th><?php endif; ?>
            <?php if ($columnasAutorizados['telefono']): ?><th class="col-telefono">Teléfono</th><?php endif; ?>
            <?php if ($columnasAutorizados['nacionalidad']): ?><th class="col-nacionalidad">Nacionalidad</th><?php endif; ?>
            <th class="col-condicion">Condición</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($autorizadosTabla as $autorizado): ?>
            <tr>
              <?php if ($columnasAutorizados['parentesco']): ?><td><?= $escape($autorizado['parentesco']) ?></td><?php endif; ?>
              <?php if ($columnasAutorizados['nombre']): ?><td><?= $escape($autorizado['nombre']) ?></td><?php endif; ?>
              <?php if ($columnasAutorizados['documento']): ?><td><?= $escape($autorizado['documento']) ?></td><?php endif; ?>
              <?php if ($columnasAutorizados['telefono']): ?><td><?= $escape($autorizado['telefono']) ?></td><?php endif; ?>
              <?php if ($columnasAutorizados['nacionalidad']): ?><td><?= $escape($autorizado['nacionalidad']) ?></td><?php endif; ?>
              <td><?= $escape($autorizado['condicion']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
    <h2 class="section-title">4. Personas autorizadas para retirar al alumno</h2>
      <p class="empty">Ninguna persona está autorizada a retirar a este alumno.</p>
    <?php endif; ?>
    <?php if ($hayHistorial): ?>
    <h2 class="section-title">5. Historial académico institucional</h2>
      <?php foreach ($historialConDatos as $registro): ?>
        <?php
          $estadoAnio = strtoupper((string) ($registro['estado_anio'] ?? ''));
          $estadoMatricula = strtoupper((string) ($registro['tipo_matricula'] ?? ''));
          $estadoEtiqueta = $estadoAnio === 'ACTIVO'
              ? 'En curso'
              : ($estadoAnio === 'CERRADO' ? 'Finalizado' : ucfirst(strtolower($estadoAnio)));
        ?>
        <article class="history-item">
          <?php if ($hayDato($registro['anio_escolar'] ?? '')): ?><div class="history-year">Período <?= $escape($registro['anio_escolar']) ?></div><?php endif; ?>
          <?php
            $gradoHistorico = trim(implode(' · ', array_filter([
                $valor($registro['grado'] ?? ''),
                $hayDato($registro['seccion'] ?? '') ? 'Sección ' . $valor($registro['seccion']) : '',
            ], static fn (string $parte): bool => $parte !== '')));
            $estadoHistorico = trim(implode(' · ', array_filter([
                $estadoEtiqueta,
                $estadoMatricula !== '' ? ucfirst(strtolower($estadoMatricula)) : '',
            ], static fn (string $parte): bool => $parte !== '')));
          ?>
          <?php if ($hayDato($gradoHistorico) || $hayDato($registro['turno'] ?? '')): ?>
          <div class="history-grade">
            <?= $escape($gradoHistorico) ?>
            <?php if (!empty($registro['turno'])): ?> · Turno <?= $escape($registro['turno']) ?><?php endif; ?>
          </div>
          <?php endif; ?>
          <?php if ($hayDato($estadoHistorico)): ?><span class="history-status"><?= $escape($estadoHistorico) ?></span><?php endif; ?>
        </article>
      <?php endforeach; ?>
    <?php endif; ?>

  </section>
  <?php endif; ?>

  <div class="signature-area">
    <span class="signature">Firma del directivo</span>
  </div>
</body>
</html>
