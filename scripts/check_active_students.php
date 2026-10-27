<?php
require_once __DIR__ . '/../app/Config/Database.php';
require_once __DIR__ . '/../app/Support/AniosEscolares.php';

$pdo = (new App\Config\Database())->getConnection();
$anioId = App\Support\AniosEscolares::idEnCurso($pdo);
$anioNombre = App\Support\AniosEscolares::nombreEnCurso($pdo);

echo "Año Activo ID: $anioId | Nombre: $anioNombre\n\n";

$sql = "SELECT est.id_estudiante,
               CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS nombre_completo,
               p.tipo_documento,
               p.numero_documento,
               p.estado AS estado_persona,
               g.nombre AS grado,
               s.nombre AS seccion,
               a.nombre_periodo AS anio_escolar,
               m.tipo AS tipo_matricula_activa,
               (SELECT m2.tipo FROM matricula m2 WHERE m2.id_estudiante = est.id_estudiante ORDER BY m2.id_matricula DESC LIMIT 1) AS ultimo_tipo_global
        FROM estudiante est
        INNER JOIN persona p ON p.id_persona = est.id_persona
        INNER JOIN matricula m ON m.id_estudiante = est.id_estudiante AND m.id_anio_escolar = :id_anio AND m.tipo IN ('INSCRIPCION', 'RATIFICACION')
        INNER JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
        INNER JOIN anio_escolar a ON a.id_anio_escolar = m.id_anio_escolar
        INNER JOIN grado g ON g.id_grado = ea.id_grado
        LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion
        WHERE p.estado = 'ACTIVO'
          AND (SELECT m3.tipo FROM matricula m3 WHERE m3.id_estudiante = est.id_estudiante ORDER BY m3.id_matricula DESC LIMIT 1) NOT IN ('RETIRO', 'EGRESO')
        ORDER BY p.primer_nombre, p.primer_apellido";

$st = $pdo->prepare($sql);
$st->execute([':id_anio' => $anioId]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

echo "Total estudiantes activos en período activo: " . count($rows) . "\n";
foreach ($rows as $r) {
    echo "- ID: {$r['id_estudiante']} | {$r['nombre_completo']} | Doc: {$r['tipo_documento']}-{$r['numero_documento']} | Grado: {$r['grado']} {$r['seccion']} | Matrícula: {$r['tipo_matricula_activa']}\n";
}
