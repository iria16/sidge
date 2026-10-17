<?php
/**
 * Plantilla PDF para los listados de la sección del docente.
 *
 * Sigue el patrón institucional:
 * - El CSS no vive en la plantilla: se inyecta desde $estilosPdf (leído de public/assets/css/pdf/nomina-seccion.css).
 * - Utiliza el membrete oficial compartido (app/Views/partials/pdf-membrete.php).
 * - Encabezado: Nombre de la escuela, sección, año escolar y fecha de emisión.
 * - Tablas y CSS simple compatibles con Dompdf (sin flexbox ni grid).
 * - En acta, la columna "Firma" queda con línea en blanco y altura suficiente para firmar.
 * - Toda salida se escapa con htmlspecialchars.
 * - No incluye botones de WhatsApp ni información médica.
 *
 * @var array<string,mixed>         $config
 * @var array<string,mixed>         $seccion
 * @var string                      $vista
 * @var string                      $tituloVista
 * @var array<string,string>        $columnas
 * @var array<int,array<string,mixed>> $filas
 * @var array<string,mixed>         $filtros
 * @var string                      $fechaEmision
 * @var string                      $horaEmision
 * @var string                      $estilosPdf
 */

$esc = static fn($v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$fmtFecha = static fn(?string $f): string => $f ? date('d/m/Y', strtotime($f)) : '';

$nombreEscuela = trim((string) ($config['plantel_nombre'] ?? '')) ?: 'E.B. Isidro Ramírez';
$seccionTexto  = trim(($seccion['grado'] ?? '') . ' "' . ($seccion['seccion'] ?? '') . '" · Turno ' . ($seccion['turno'] ?? ''));
$anioEscolar   = trim((string) ($seccion['anio_escolar'] ?? ''));
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title><?= $esc($tituloVista) ?></title>
  <style><?= $estilosPdf ?></style>
</head>
<body>

  <?php /* ── Membrete oficial compartido ────────────────────────────────────── */ ?>
  <?php include dirname(__DIR__, 2) . '/partials/pdf-membrete.php'; ?>

  <?php /* ── Título del listado ─────────────────────────────────────────────── */ ?>
  <div class="nomina-titulo"><?= $esc($tituloVista) ?></div>

  <?php /* ── Datos de contexto institucional y sección ─────────────────────── */ ?>
  <table class="nomina-datos">
    <tr>
      <td class="nomina-datos__rotulo">Institución:</td>
      <td><?= $esc($nombreEscuela) ?></td>
      <td class="nomina-datos__rotulo">Año escolar:</td>
      <td><?= $esc($anioEscolar) ?></td>
    </tr>
    <tr>
      <td class="nomina-datos__rotulo">Sección:</td>
      <td><?= $esc($seccionTexto) ?></td>
      <td class="nomina-datos__rotulo">Emisión:</td>
      <td><?= $esc($fechaEmision) ?> · <?= $esc($horaEmision) ?></td>
    </tr>
    <?php if ($vista === 'acta'): ?>
      <?php /* El acta se imprime y se reparte: tiene que decir de quién es, qué
             sección es y por qué se firma, no solo la institución. */ ?>
      <tr>
        <td class="nomina-datos__rotulo">Motivo:</td>
        <td><?= $esc($filtros['motivo'] ?? '') ?></td>
        <td class="nomina-datos__rotulo">Fecha del acta:</td>
        <td><?= $esc($filtros['fecha_acta'] ?? $fechaEmision) ?></td>
      </tr>
      <tr>
        <td class="nomina-datos__rotulo">Docente:</td>
        <td><?= $esc(trim((string) ($seccion['docente_nombre'] ?? '')) ?: '—') ?></td>
        <td class="nomina-datos__rotulo">Orden:</td>
        <td>Alfabético por apellido</td>
      </tr>
    <?php endif; ?>
    <?php if ($vista === 'cumpleaneros' && !empty($filtros['mes'])): ?>
      <tr>
        <td class="nomina-datos__rotulo">Mes:</td>
        <td colspan="3">
          <?php
            $mesesNombres = [
                1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
                7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
            ];
            echo $esc($mesesNombres[(int) $filtros['mes']] ?? (string) $filtros['mes']);
          ?>
        </td>
      </tr>
    <?php endif; ?>
  </table>

  <?php /* ── Tabla de datos ─────────────────────────────────────────────────── */ ?>
  <table class="nomina-tabla <?= $vista === 'acta' ? 'nomina-tabla--acta' : '' ?>">
    <thead>
      <tr>
        <?php foreach ($columnas as $clave => $titulo): ?>
          <th class="nomina-col--<?= $esc($clave) ?>"><?= $esc($titulo) ?></th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
    <?php if (empty($filas)): ?>
      <tr>
        <td colspan="<?= count($columnas) ?>" class="nomina-vacio">
          No hay estudiantes registrados para los criterios seleccionados.
        </td>
      </tr>
    <?php else: ?>
<?php /* Igual que la pantalla: la vista de autorizados agrupa por alumno.
               El nombre del estudiante y el del representante salen una sola vez,
               arriba de su grupo, con rowspan sobre el número de personas
               autorizadas. $autorizadoActual es null cuando el alumno no tiene a
               nadie más autorizado, y esa persona ocupa una fila igual. */ ?>
      <?php foreach ($filas as $i => $f):
          $esAutorizados = ($vista === 'autorizados');
          $lista = $f['autorizados'] ?? [];
          $nFilas = $lista !== [] ? count($lista) : 1;
          $grupos = $lista !== [] ? $lista : [null];
          foreach ($grupos as $k => $autorizadoActual):
              $primera = ($k === 0);
      ?>
<tr<?= $primera && $esAutorizados ? ' class="nomina-grupo-inicio"' : '' ?>>
          <?php foreach ($columnas as $clave => $titulo):
              $celdaGrupo = $esAutorizados && $clave === 'estudiante';
              if ($celdaGrupo && !$primera) { continue; }
          ?>
            <td class="nomina-col--<?= $esc($clave) ?>"<?= $celdaGrupo ? ' rowspan="' . $nFilas . '"' : '' ?>>
              <?php switch ($clave):
                case 'n': ?>
                  <?= $i + 1 ?>
                  <?php break;
                case 'cedula': ?>
                  <?= $f['documento'] !== '' ? $esc($f['documento']) : '—' ?>
                  <?php break;
                case 'estudiante': ?>
                  <strong><?= $esc($f['estudiante']) ?></strong>
                  <?php break;
                case 'sexo': ?>
                  <?= $f['sexo'] === 'F' ? 'F' : 'M' ?>
                  <?php break;
                case 'fecha_nac': ?>
                  <?= $esc($fmtFecha($f['fecha_nacimiento'])) ?>
                  <?php break;
                case 'edad': ?>
                  <?= (int) $f['edad'] ?>
                  <?php break;
                case 'representante': ?>
                  <?php if (!empty($f['representante'])): ?>
                    <div><?= $esc($f['representante']) ?></div>
                    <?php if (!empty($f['parentesco'])): ?>
                      <div class="nomina-secundario">(<?= $esc($f['parentesco']) ?>)</div>
                    <?php endif; ?>
                  <?php else: ?>
                    <span class="nomina-secundario">Sin asignar</span>
                  <?php endif; ?>
                  <?php break;
                case 'cedula_rep': ?>
                  <?= $f['rep_documento'] !== '' ? $esc($f['rep_documento']) : '—' ?>
                  <?php break;
                case 'firma': ?>
                  &nbsp;
                  <?php break;
                case 'telefonos_rep': ?>
                  <?php if (empty($f['rep_telefonos'])): ?>
                    <span class="nomina-secundario">No registrado</span>
                  <?php else: ?>
                    <?php foreach ($f['rep_telefonos'] as $tel): ?>
                      <div><?= $esc($tel) ?></div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                  <?php break;
                case 'direccion_rep': ?>
                  <?php if (($f['rep_direccion'] ?? '') === ''): ?>
                    <span class="nomina-secundario">Sin dirección</span>
                  <?php else: ?>
                    <?= $esc($f['rep_direccion']) ?>
                  <?php endif; ?>
                  <?php break;
                case 'autorizado': ?>
                  <?php if (($autorizadoActual ?? null) === null): ?>
                    <span class="nomina-secundario">Solo el representante</span>
                  <?php else: ?>
                    <?= $esc($autorizadoActual['nombre']) ?>
                  <?php endif; ?>
                  <?php break;
                case 'parentesco_aut': ?>
                  <?php if (($autorizadoActual ?? null) === null || ($autorizadoActual['parentesco'] ?? '') === ''): ?>
                    <span class="nomina-secundario">—</span>
                  <?php else: ?>
                    <?= $esc($autorizadoActual['parentesco']) ?>
                  <?php endif; ?>
                  <?php break;
                case 'telefono_aut': ?>
                  <?php if (($autorizadoActual ?? null) === null || empty($autorizadoActual['telefonos'])): ?>
                    <span class="nomina-secundario">Sin teléfono</span>
                  <?php else: ?>
                    <?php foreach ($autorizadoActual['telefonos'] as $tel): ?>
                      <div><?= $esc($tel) ?></div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                  <?php break;
                case 'dia': ?>
                  <?= (int) $f['dia_cumple'] ?>
                  <?php break;
                case 'edad_cumple': ?>
                  <?= (int) $f['edad_cumple'] ?> años
                  <?php if (!empty($f['cumple_ya_paso'])): ?>
                    <span class="nomina-cumple-ok">Ya cumplió</span>
                  <?php endif; ?>
                  <?php break;
              endswitch; ?>
            </td>
          <?php endforeach; ?>
        </tr>
        <?php endforeach; /* una fila por persona autorizada */ ?>
      <?php endforeach; /* un grupo por estudiante */ ?>
    <?php endif; ?>
    </tbody>
  </table>

  <div class="nomina-pie">
    Total de registros: <?= count($filas) ?> estudiante<?= count($filas) === 1 ? '' : 's' ?>.
  </div>

</body>
</html>
