<?php

declare(strict_types=1);

/**
 * Repara el estado de personas con reingreso vigente.
 *
 * El flujo de retiro marca la persona como INACTIVA. Los reingresos anteriores
 * a la corrección no la reactivaban, por lo que esos estudiantes no aparecían
 * en Constancia de Estudio. Este script reactiva únicamente a estudiantes cuyo
 * último movimiento académico es REINGRESO y cuya persona sigue INACTIVA.
 *
 * Uso:
 *   php scripts/reparar-reingresos-inactivos.php
 *   php scripts/reparar-reingresos-inactivos.php --dry-run
 */

spl_autoload_register(function (string $clase): void {
    $prefijo = 'App\\';
    if (strncmp($prefijo, $clase, strlen($prefijo)) !== 0) {
        return;
    }
    $archivo = __DIR__ . '/../app/' . str_replace('\\', '/', substr($clase, strlen($prefijo))) . '.php';
    if (file_exists($archivo)) {
        require_once $archivo;
    }
});

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Config/Env.php';
require_once __DIR__ . '/../app/Config/Database.php';

$dryRun = in_array('--dry-run', $argv, true);
$pdo = (new \App\Config\Database())->getConnection();

$sql = "SELECT e.id_estudiante,
               p.id_persona,
               CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS nombre
          FROM estudiante e
          INNER JOIN persona p ON p.id_persona = e.id_persona
         WHERE p.estado = 'INACTIVO'
           AND (
               SELECT m.tipo
                 FROM matricula m
                WHERE m.id_estudiante = e.id_estudiante
                ORDER BY m.fecha_matricula DESC, m.id_matricula DESC
                LIMIT 1
           ) = 'REINGRESO'
         ORDER BY e.id_estudiante";

$afectados = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

if ($afectados === []) {
    echo "No hay reingresos con persona INACTIVA. Nada que reparar.\n";
    exit(0);
}

echo ($dryRun ? '[DRY-RUN] ' : '') . 'Reingresos a reactivar: ' . count($afectados) . "\n";
foreach ($afectados as $fila) {
    echo " - Estudiante {$fila['id_estudiante']}: {$fila['nombre']}\n";
}

if ($dryRun) {
    echo "No se modificó la base de datos.\n";
    exit(0);
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("UPDATE persona SET estado = 'ACTIVO' WHERE id_persona = :id_persona");
    foreach ($afectados as $fila) {
        $stmt->execute(['id_persona' => (int) $fila['id_persona']]);
    }
    $pdo->commit();
    echo "Listo: " . count($afectados) . " persona(s) reactivada(s).\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'Error al reparar reingresos: ' . $e->getMessage() . "\n");
    exit(1);
}
