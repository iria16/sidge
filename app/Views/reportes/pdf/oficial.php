<?php

/**
 * PDF: reportes oficiales del Centro de Reportes.
 *
 * La misma plantilla se usa para el iframe de vista previa y para el PDF.
 * Cada tipo de reporte decide su tabla según los datos que envía el
 * controlador; no se consulta la base desde aquí.
 *
 * @var array<string,string> $config
 * @var string $tipo
 * @var string $tituloReporte
 * @var string $nombrePeriodo
 * @var array<string,mixed> $datosReporte
 * @var array<string,mixed>|null $resumenRepresentantes
 * @var array{anio?:int,q?:string,grado?:int|null,seccion?:int|null} $filtros
 * @var int $totalRegistros
 * @var string $fechaEmision
 * @var string $horaEmision
 * @var array|null $membreteLogoMin
 * @var array|null $membreteLogoPla
 * @var array<string> $membreteLineas
 * @var string $membreteTextoClase
 * @var string $membreteAltEscuela
 * @var string $estilosPdf
 */

$esc = static fn ($valor): string => htmlspecialchars(
    (string) ($valor ?? ''),
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

$nombreEscuela = trim((string) ($config['plantel_nombre'] ?? '')) ?: 'E.B. Isidro Ramírez';
$busqueda = trim((string) ($filtros['q'] ?? ''));
$edad = static fn ($valor): string => $valor === null || $valor === '' ? '—' : (string) $valor;

$filas = [];
$edades = [];
$totalesMatriz = [];
if ($tipo === 'estadistico_general') {
    $filas = (array) ($datosReporte['filas'] ?? []);
    $edades = (array) ($datosReporte['edades'] ?? []);
    $totalesMatriz = (array) ($datosReporte['totales'] ?? []);
} elseif ($tipo === 'nomina_estudiantes' || $tipo === 'distribucion_aulas'
    || $tipo === 'personal_institucional' || $tipo === 'retiros_periodo') {
    $filas = $datosReporte;
}

$totalFilas = count($filas);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title><?= $esc($tituloReporte) ?></title>
  <style><?= $estilosPdf ?></style>
</head>
<body class="reporte-oficial">

  <?php include dirname(__DIR__, 2) . '/partials/pdf-membrete.php'; ?>

  <div class="nomina-titulo"><?= $esc($tituloReporte) ?></div>

  <table class="nomina-datos">
    <tr>
      <td class="nomina-datos__rotulo">Institución:</td>
      <td><?= $esc($nombreEscuela) ?></td>
      <td class="nomina-datos__rotulo">Período:</td>
      <td><?= $esc($nombrePeriodo !== '' ? $nombrePeriodo : 'No definido') ?></td>
    </tr>
    <tr>
      <td class="nomina-datos__rotulo">Emisión:</td>
      <td><?= $esc($fechaEmision) ?> · <?= $esc($horaEmision) ?></td>
      <td class="nomina-datos__rotulo">Registros:</td>
      <td><?= (int) $totalFilas ?></td>
    </tr>
    <?php if ($busqueda !== ''): ?>
      <tr>
        <td class="nomina-datos__rotulo">Búsqueda:</td>
        <td colspan="3"><?= $esc($busqueda) ?></td>
      </tr>
    <?php endif; ?>
  </table>

  <?php if ($tipo === 'estadistico_general'): ?>
    <table class="nomina-tabla reporte-oficial--matriz">
      <thead>
        <tr>
          <th>Grado y sección</th>
          <th>Turno</th>
          <th class="reporte-oficial__numero">Capacidad</th>
          <th class="reporte-oficial__numero">Varones</th>
          <th class="reporte-oficial__numero">Hembras</th>
          <th class="reporte-oficial__numero">Total</th>
          <?php foreach ($edades as $e): ?>
            <th class="reporte-oficial__edad"><?= $esc($e) ?> años</th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php if ($filas === []): ?>
          <tr><td colspan="<?= 6 + count($edades) ?>" class="reporte-oficial__vacio">Sin datos para el período seleccionado.</td></tr>
        <?php else: ?>
          <?php foreach ($filas as $fila): ?>
            <?php
            $porEdad = (array) ($fila['edades'] ?? []);
            ?>
            <tr>
              <td><?= $esc(($fila['grado'] ?? '') . ' ' . ($fila['seccion'] ?? '')) ?></td>
              <td><?= $esc($fila['turno'] ?? '—') ?></td>
              <td class="reporte-oficial__numero"><?= (int) ($fila['capacidad'] ?? 0) ?></td>
              <td class="reporte-oficial__numero"><?= (int) ($fila['varones'] ?? 0) ?></td>
              <td class="reporte-oficial__numero"><?= (int) ($fila['hembras'] ?? 0) ?></td>
              <td class="reporte-oficial__numero reporte-oficial__total"><?= (int) ($fila['total'] ?? 0) ?></td>
              <?php foreach ($edades as $e): ?>
                <td class="reporte-oficial__numero"><?= (int) ($porEdad[$e]['total'] ?? 0) ?></td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
          <tr class="reporte-oficial__fila-totales">
            <td colspan="2">Totales generales</td>
            <td class="reporte-oficial__numero"><?= array_sum(array_column($filas, 'capacidad')) ?></td>
            <td class="reporte-oficial__numero"><?= (int) ($totalesMatriz['varones'] ?? 0) ?></td>
            <td class="reporte-oficial__numero"><?= (int) ($totalesMatriz['hembras'] ?? 0) ?></td>
            <td class="reporte-oficial__numero reporte-oficial__total"><?= (int) ($totalesMatriz['total'] ?? 0) ?></td>
            <?php foreach ($edades as $e): ?>
              <?php
              $sumaEdad = 0;
              foreach ($filas as $fila) {
                  $sumaEdad += (int) ($fila['edades'][$e]['total'] ?? 0);
              }
              ?>
              <td class="reporte-oficial__numero reporte-oficial__total"><?= $sumaEdad > 0 ? $sumaEdad : '—' ?></td>
            <?php endforeach; ?>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
    <p class="reporte-oficial__nota">Matriz de matrícula por grado, sección, sexo y edad. Los totales se calculan con la matrícula activa del período.</p>

  <?php elseif ($tipo === 'nomina_estudiantes'): ?>
    <table class="nomina-tabla">
      <thead>
        <tr>
          <th class="reporte-oficial__numero">#</th>
          <th>Documento</th>
          <th>Estudiante</th>
          <th>Sexo</th>
          <th class="reporte-oficial__numero">Edad</th>
          <th>Grado y sección</th>
          <th>Representante</th>
          <th>Teléfono</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($filas === []): ?>
          <tr><td colspan="8" class="reporte-oficial__vacio">Sin estudiantes para los filtros seleccionados.</td></tr>
        <?php else: ?>
          <?php $i = 1; foreach ($filas as $fila): ?>
            <tr>
              <td class="reporte-oficial__numero"><?= $i++ ?></td>
              <td><?= $esc(trim(($fila['tipo_documento'] ?? '') . '-' . ($fila['numero_documento'] ?? ''), '-') ?: '—') ?></td>
              <td><?= $esc($fila['nombre_completo'] ?? '') ?></td>
              <td><?= $esc($fila['sexo'] ?? '—') ?></td>
              <td class="reporte-oficial__numero"><?= $esc($edad($fila['edad'] ?? null)) ?></td>
              <td><?= $esc(($fila['grado'] ?? '') . ' ' . ($fila['seccion'] ?? '')) ?></td>
              <td><?= $esc($fila['rep_nombre'] ?? 'Sin representante') ?></td>
              <td><?= $esc($fila['rep_telefono'] ?? '—') ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
    <?php if ($resumenRepresentantes !== null): ?>
      <p class="reporte-oficial__nota">
        Representación: <?= (int) ($resumenRepresentantes['total'] ?? 0) ?> estudiantes;
        con más de un representante: <?= (int) ($resumenRepresentantes['mas_de_un'] ?? 0) ?>;
        solo con representante fallecido: <?= (int) ($resumenRepresentantes['solo_fallecido'] ?? 0) ?>;
        sin representante: <?= (int) ($resumenRepresentantes['sin_rep'] ?? 0) ?>.
      </p>
    <?php endif; ?>

  <?php elseif ($tipo === 'distribucion_aulas'): ?>
    <table class="nomina-tabla">
      <thead>
        <tr>
          <th>Grado</th>
          <th>Sección</th>
          <th>Turno</th>
          <th class="reporte-oficial__numero">Capacidad</th>
          <th class="reporte-oficial__numero">Matriculados</th>
          <th>Docente titular</th>
          <th>Estado</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($filas === []): ?>
          <tr><td colspan="7" class="reporte-oficial__vacio">Sin aulas para el período seleccionado.</td></tr>
        <?php else: ?>
          <?php foreach ($filas as $fila): ?>
            <?php $asignada = !empty($fila['con_docente']); ?>
            <tr>
              <td><?= $esc($fila['grado'] ?? '') ?></td>
              <td><?= $esc($fila['seccion'] ?? '') ?></td>
              <td><?= $esc($fila['turno'] ?? '—') ?></td>
              <td class="reporte-oficial__numero"><?= (int) ($fila['capacidad_maxima'] ?? 0) ?></td>
              <td class="reporte-oficial__numero"><?= (int) ($fila['total_matricula'] ?? 0) ?></td>
              <td><?= $esc($fila['docente_titular'] ?? 'Sin asignar') ?></td>
              <td><?= $asignada ? 'Asignada' : 'Por asignar' ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>

  <?php elseif ($tipo === 'personal_institucional'): ?>
    <table class="nomina-tabla">
      <thead>
        <tr>
          <th>Documento</th>
          <th>Nombre completo</th>
          <th>Cargo</th>
          <th>Turno</th>
          <th>Teléfono</th>
          <th>Condición</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($filas === []): ?>
          <tr><td colspan="6" class="reporte-oficial__vacio">Sin personal para los filtros seleccionados.</td></tr>
        <?php else: ?>
          <?php foreach ($filas as $fila): ?>
            <tr>
              <td><?= $esc(trim(($fila['tipo_documento'] ?? '') . '-' . ($fila['numero_documento'] ?? ''), '-') ?: '—') ?></td>
              <td><?= $esc($fila['nombre_completo'] ?? '') ?></td>
              <td><?= $esc($fila['cargo'] ?? '—') ?></td>
              <td><?= $esc($fila['turno'] ?? '—') ?></td>
              <td><?= $esc($fila['telefono'] ?? '—') ?></td>
              <td><?= !empty($fila['en_reposo']) ? 'En reposo' : 'Activo' ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>

  <?php elseif ($tipo === 'retiros_periodo'): ?>
    <table class="nomina-tabla">
      <thead>
        <tr>
          <th>Documento</th>
          <th>Estudiante</th>
          <th>Grado y sección</th>
          <th>Motivo</th>
          <th>Fecha</th>
          <th>Estado</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($filas === []): ?>
          <tr><td colspan="6" class="reporte-oficial__vacio">Sin retiros registrados en el período.</td></tr>
        <?php else: ?>
          <?php foreach ($filas as $fila): ?>
            <tr>
              <td><?= $esc(trim(($fila['tipo_documento'] ?? '') . '-' . ($fila['numero_documento'] ?? ''), '-') ?: '—') ?></td>
              <td><?= $esc($fila['nombre_completo'] ?? '') ?></td>
              <td><?= $esc(($fila['grado'] ?? '') . ' ' . ($fila['seccion'] ?? '')) ?></td>
              <td><?= $esc($fila['motivo'] ?? '—') ?></td>
              <td><?= $esc(!empty($fila['fecha_solicitud']) ? date('d/m/Y', strtotime((string) $fila['fecha_solicitud'])) : '—') ?></td>
              <td><?= $esc($fila['estado_solicitud'] ?? '—') ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <p class="reporte-oficial__pie">
    Documento generado desde el Sistema de Información para la Gestión de Datos Escolares.
  </p>

</body>
</html>
