<?php

/**
 * PDF: planilla oficial de personal.
 *
 * Comparte el membrete y la hoja de listados del docente. La tabla muestra
 * los mismos campos que la pantalla del Centro de Reportes.
 *
 * @var array<string,string> $config
 * @var array<int,array<string,mixed>> $personal
 * @var array{q?:string} $filtros
 * @var string $fechaEmision
 * @var string $horaEmision
 * @var array|null $membreteLogoMin
 * @var array|null $membreteLogoPla
 * @var array<string> $membreteLineas
 * @var string $membreteTextoClase
 * @var string $membreteAltEscuela
 * @var int $totalPersonal
 * @var int $personalActivo
 * @var int $personalEnReposo
 * @var string $estilosPdf
 */

$esc = static fn ($valor): string => htmlspecialchars(
    (string) ($valor ?? ''),
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

$nombreEscuela = trim((string) ($config['plantel_nombre'] ?? '')) ?: 'E.B. Isidro Ramírez';
$busqueda = trim((string) ($filtros['q'] ?? ''));
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Planilla oficial de personal</title>
  <style><?= $estilosPdf ?></style>
</head>
<body>

  <?php include dirname(__DIR__, 2) . '/partials/pdf-membrete.php'; ?>

  <div class="nomina-titulo">Plantilla oficial de personal</div>

  <table class="nomina-datos">
    <tr>
      <td class="nomina-datos__rotulo">Institución:</td>
      <td><?= $esc($nombreEscuela) ?></td>
      <td class="nomina-datos__rotulo">Emisión:</td>
      <td><?= $esc($fechaEmision) ?> · <?= $esc($horaEmision) ?></td>
    </tr>
    <tr>
      <td class="nomina-datos__rotulo">Registros:</td>
      <td><?= (int) $totalPersonal ?></td>
      <td class="nomina-datos__rotulo">Activos:</td>
      <td><?= (int) $personalActivo ?></td>
    </tr>
    <?php if ($busqueda !== ''): ?>
      <tr>
        <td class="nomina-datos__rotulo">Búsqueda:</td>
        <td colspan="3"><?= $esc($busqueda) ?></td>
      </tr>
    <?php endif; ?>
  </table>

  <table class="nomina-tabla">
    <colgroup>
      <col class="reporte-personal__col--documento">
      <col class="reporte-personal__col--nombre">
      <col class="reporte-personal__col--cargo">
      <col class="reporte-personal__col--turno">
      <col class="reporte-personal__col--telefono">
      <col class="reporte-personal__col--condicion">
    </colgroup>
    <thead>
      <tr>
        <th class="reporte-personal__col--documento">Documento</th>
        <th class="reporte-personal__col--nombre">Nombre completo</th>
        <th class="reporte-personal__col--cargo">Cargo</th>
        <th class="reporte-personal__col--turno">Turno</th>
        <th class="reporte-personal__col--telefono">Teléfono</th>
        <th class="reporte-personal__col--condicion">Condición</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($personal === []): ?>
        <tr>
          <td colspan="6" class="nomina-vacio">No se encontró personal registrado.</td>
        </tr>
      <?php else: ?>
        <?php foreach ($personal as $persona): ?>
          <?php
          $documento = trim((string) ($persona['tipo_documento'] ?? '') . '-' . (string) ($persona['numero_documento'] ?? ''), '-');
          $activo = strtoupper((string) ($persona['estado_persona'] ?? '')) === 'ACTIVO';
          $condicion = !empty($persona['en_reposo'])
              ? 'En reposo'
              : ($activo ? 'Activo' : 'Inactivo');
          ?>
          <tr>
            <td class="reporte-personal__col--documento"><?= $esc($documento !== '' ? $documento : '—') ?></td>
            <td class="reporte-personal__col--nombre"><?= $esc($persona['nombre_completo'] ?? '') ?></td>
            <td class="reporte-personal__col--cargo"><?= $esc(($persona['cargo'] ?? '') !== '' ? $persona['cargo'] : 'Sin cargo') ?></td>
            <td class="reporte-personal__col--turno"><?= $esc(($persona['turno'] ?? '') !== '' ? $persona['turno'] : '—') ?></td>
            <td class="reporte-personal__col--telefono"><?= $esc(($persona['telefono'] ?? '') !== '' ? $persona['telefono'] : '—') ?></td>
            <td class="reporte-personal__col--condicion"><?= $esc($condicion) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>

  <div class="nomina-pie">
    Total de registros: <?= (int) $totalPersonal ?> ·
    Activos: <?= (int) $personalActivo ?> ·
    En reposo: <?= (int) $personalEnReposo ?>
  </div>

</body>
</html>
