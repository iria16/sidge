<?php

declare(strict_types=1);

/**
 * Verificación de reglas de negocio de Constancias (SIGDE).
 *
 * Cubre:
 * 1. Prosecución: solo estudiantes promovidos con retiro APROBADO.
 * 2. Retiro: solo retiros APROBADOS que sean el último movimiento.
 * 3. Inscripción: una fila por estudiante y REINGRESO identificado.
 *
 * Usa una transacción y revierte todos los datos de prueba al finalizar.
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

use App\Models\CertificatesModel;
use App\Support\AniosEscolares;

$pdo = (new \App\Config\Database())->getConnection();
$model = new CertificatesModel($pdo);
$fallos = [];

$check = static function (string $nombre, bool $ok, string $detalle = '') use (&$fallos): void {
    if ($ok) {
        echo "  [OK] {$nombre}\n";
        return;
    }
    $fallos[] = $nombre . ($detalle !== '' ? " ({$detalle})" : '');
    echo "  [FALLO] {$nombre}" . ($detalle !== '' ? " ({$detalle})" : '') . "\n";
};

$idAnio = AniosEscolares::idEnCurso($pdo);
if ($idAnio <= 0) {
    fwrite(STDERR, "No hay año escolar activo; no se pueden verificar las reglas.\n");
    exit(1);
}

$pdo->beginTransaction();

try {
    echo "\n--- Reglas de Constancias ---\n";

    // Estructura de 1.º a 5.º grado para probar Prosecución.
    $stmt = $pdo->prepare(
        "SELECT ea.id_estructura_academica, ea.id_grado, g.nombre
           FROM estructura_academica ea
           INNER JOIN grado g ON g.id_grado = ea.id_grado
          WHERE ea.id_anio_escolar = :id_anio
            AND CAST(REGEXP_SUBSTR(g.nombre, '[0-9]+') AS UNSIGNED) BETWEEN 1 AND 5
          ORDER BY g.id_grado ASC
          LIMIT 1"
    );
    $stmt->execute(['id_anio' => $idAnio]);
    $estructura = $stmt->fetch(PDO::FETCH_ASSOC);

    // Estudiante con retiro aprobado y sin resultado en el año activo.
    $stmt = $pdo->prepare(
        "SELECT est.id_estudiante, m.id_matricula
           FROM retiro r
           INNER JOIN matricula m ON m.id_matricula = r.id_matricula
           INNER JOIN estudiante est ON est.id_estudiante = m.id_estudiante
          WHERE r.estado_solicitud = 'APROBADO'
            AND m.id_anio_escolar = :id_anio
            AND NOT EXISTS (
                SELECT 1 FROM resultado_academico ra
                 WHERE ra.id_estudiante = est.id_estudiante
                   AND ra.id_anio_escolar = :id_anio_ra
            )
          LIMIT 1"
    );
    $stmt->execute(['id_anio' => $idAnio, 'id_anio_ra' => $idAnio]);
    $conRetiro = $stmt->fetch(PDO::FETCH_ASSOC);

    // Estudiante sin retiro aprobado y sin resultado en el año activo.
    $stmt = $pdo->prepare(
        "SELECT est.id_estudiante, m.id_matricula
           FROM estudiante est
           INNER JOIN matricula m ON m.id_matricula = (
                SELECT m2.id_matricula
                  FROM matricula m2
                 WHERE m2.id_estudiante = est.id_estudiante
                   AND m2.id_anio_escolar = :id_anio
                   AND m2.tipo IN ('INSCRIPCION', 'RATIFICACION', 'REINGRESO')
                 ORDER BY m2.fecha_matricula DESC, m2.id_matricula DESC
                 LIMIT 1
           )
          WHERE NOT EXISTS (
                SELECT 1 FROM retiro r
                INNER JOIN matricula mr ON mr.id_matricula = r.id_matricula
                 WHERE mr.id_estudiante = est.id_estudiante
                   AND r.estado_solicitud = 'APROBADO'
          )
            AND NOT EXISTS (
                SELECT 1 FROM resultado_academico ra
                 WHERE ra.id_estudiante = est.id_estudiante
                   AND ra.id_anio_escolar = :id_anio_ra
          )
          LIMIT 1"
    );
    $stmt->execute(['id_anio' => $idAnio, 'id_anio_ra' => $idAnio]);
    $sinRetiro = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($estructura && $conRetiro) {
        $stmt = $pdo->prepare(
            "INSERT INTO resultado_academico
                (id_estudiante, id_anio_escolar, id_estructura_academica, id_matricula, literal, estado_resultado)
             VALUES
                (:id_estudiante, :id_anio, :id_estructura, :id_matricula, 'A', 'PROMOVIDO')"
        );
        $stmt->execute([
            'id_estudiante' => (int) $conRetiro['id_estudiante'],
            'id_anio'       => $idAnio,
            'id_estructura' => (int) $estructura['id_estructura_academica'],
            'id_matricula'  => (int) $conRetiro['id_matricula'],
        ]);
        $idResultadoConRetiro = (int) $pdo->lastInsertId();

        $check(
            'Prosecución: estudiante promovido con retiro APROBADO es elegible',
            $model->esElegibleParaProsecucion($idResultadoConRetiro)
        );
        $check(
            'Prosecución: datos de constancia disponibles para retiro APROBADO',
            $model->getDatosConstanciaProsecucion($idResultadoConRetiro) !== null
        );

        $listaPros = $model->getEstudiantesProsecucion([], 1, 100);
        $idsPros = array_map(static fn(array $f): int => (int) $f['id_resultado'], $listaPros);
        $check(
            'Prosecución: el resultado aparece en el listado',
            in_array($idResultadoConRetiro, $idsPros, true)
        );

        $pdo->prepare("UPDATE resultado_academico SET estado_resultado = 'PENDIENTE' WHERE id_resultado = :id")
            ->execute(['id' => $idResultadoConRetiro]);

        $check(
            'Prosecución: literal A en PENDIENTE NO es elegible',
            !$model->esElegibleParaProsecucion($idResultadoConRetiro)
        );
        $check(
            'Prosecución: datos bloqueados si el resultado está PENDIENTE',
            $model->getDatosConstanciaProsecucion($idResultadoConRetiro) === null
        );
    } else {
        echo "  [OMITIDO] Prosecución positiva: faltan estructura 1.º-5.º o estudiante con retiro aprobado.\n";
    }

    if ($estructura && $sinRetiro) {
        $stmt = $pdo->prepare(
            "INSERT INTO resultado_academico
                (id_estudiante, id_anio_escolar, id_estructura_academica, id_matricula, literal, estado_resultado)
             VALUES
                (:id_estudiante, :id_anio, :id_estructura, :id_matricula, 'A', 'PROMOVIDO')"
        );
        $stmt->execute([
            'id_estudiante' => (int) $sinRetiro['id_estudiante'],
            'id_anio'       => $idAnio,
            'id_estructura' => (int) $estructura['id_estructura_academica'],
            'id_matricula'  => (int) $sinRetiro['id_matricula'],
        ]);
        $idResultadoSinRetiro = (int) $pdo->lastInsertId();

        $check(
            'Prosecución: estudiante promovido sin retiro APROBADO NO es elegible',
            !$model->esElegibleParaProsecucion($idResultadoSinRetiro)
        );
        $check(
            'Prosecución: datos de constancia bloqueados sin retiro APROBADO',
            $model->getDatosConstanciaProsecucion($idResultadoSinRetiro) === null
        );
    } else {
        echo "  [OMITIDO] Prosecución negativa: faltan estructura 1.º-5.º o estudiante sin retiro.\n";
    }

    // Retiro actual: un retiro con matrícula posterior no es elegible.
    $stmt = $pdo->prepare(
        "SELECT r.id_retiro
           FROM retiro r
           INNER JOIN matricula m ON m.id_matricula = r.id_matricula
           INNER JOIN anio_escolar a ON a.id_anio_escolar = m.id_anio_escolar
          WHERE r.estado_solicitud = 'APROBADO'
            AND EXISTS (
                SELECT 1
                  FROM matricula m_post
                  INNER JOIN anio_escolar a_post ON a_post.id_anio_escolar = m_post.id_anio_escolar
                 WHERE m_post.id_estudiante = m.id_estudiante
                   AND (
                       a_post.anio_inicio > a.anio_inicio
                       OR (a_post.anio_inicio = a.anio_inicio AND m_post.id_matricula > m.id_matricula)
                   )
            )
          LIMIT 1"
    );
    $stmt->execute();
    $idRetiroConPosterior = (int) ($stmt->fetchColumn() ?: 0);

    if ($idRetiroConPosterior > 0) {
        $check(
            'Retiro: retiro con matrícula posterior NO es elegible',
            !$model->esElegibleParaRetiro($idRetiroConPosterior)
        );
        $check(
            'Retiro: retiro con matrícula posterior no aparece en el listado',
            $model->getDatosConstanciaRetiro($idRetiroConPosterior) === null
        );
    } else {
        echo "  [OMITIDO] Retiro negativo: no hay retiro con matrícula posterior.\n";
    }

    // Retiro actual: insertar un retiro aprobado para una matrícula sin movimientos posteriores.
    $stmt = $pdo->prepare(
        "SELECT m.id_matricula
           FROM matricula m
           INNER JOIN anio_escolar a ON a.id_anio_escolar = m.id_anio_escolar
          WHERE m.id_anio_escolar = :id_anio
            AND m.tipo IN ('INSCRIPCION', 'RATIFICACION', 'REINGRESO')
            AND NOT EXISTS (SELECT 1 FROM retiro r WHERE r.id_matricula = m.id_matricula)
            AND NOT EXISTS (
                SELECT 1
                  FROM matricula m_post
                  INNER JOIN anio_escolar a_post ON a_post.id_anio_escolar = m_post.id_anio_escolar
                 WHERE m_post.id_estudiante = m.id_estudiante
                   AND (
                       a_post.anio_inicio > a.anio_inicio
                       OR (a_post.anio_inicio = a.anio_inicio AND m_post.id_matricula > m.id_matricula)
                   )
            )
          ORDER BY m.id_matricula DESC
          LIMIT 1"
    );
    $stmt->execute(['id_anio' => $idAnio]);
    $idMatriculaRetiroActual = (int) ($stmt->fetchColumn() ?: 0);

    if ($idMatriculaRetiroActual > 0) {
        $idMotivo = (int) $pdo->query("SELECT id_motivo_retiro FROM motivo_retiro WHERE estado = 'ACTIVO' ORDER BY id_motivo_retiro LIMIT 1")->fetchColumn();
        if ($idMotivo > 0) {
            $stmt = $pdo->prepare(
                "INSERT INTO retiro (id_matricula, id_motivo_retiro, estado_solicitud, fecha_solicitud, fecha_retiro, fecha_resolucion)
                 VALUES (:id_matricula, :id_motivo, 'APROBADO', NOW(), CURDATE(), NOW())"
            );
            $stmt->execute(['id_matricula' => $idMatriculaRetiroActual, 'id_motivo' => $idMotivo]);
            $idRetiroActual = (int) $pdo->lastInsertId();

            $check(
                'Retiro: retiro APROBADO sin movimientos posteriores es elegible',
                $model->esElegibleParaRetiro($idRetiroActual)
            );
            $check(
                'Retiro: datos de constancia disponibles para retiro actual',
                $model->getDatosConstanciaRetiro($idRetiroActual) !== null
            );

            $listaRetiro = $model->getEstudiantesRetiro([], 1, 100);
            $idsRetiro = array_map(static fn(array $f): int => (int) $f['id_retiro'], $listaRetiro);
            $check(
                'Retiro: retiro actual aparece en el listado',
                in_array($idRetiroActual, $idsRetiro, true)
            );
        } else {
            echo "  [OMITIDO] Retiro positivo: no hay motivo de retiro activo.\n";
        }
    } else {
        echo "  [OMITIDO] Retiro positivo: no hay matrícula sin movimientos posteriores.\n";
    }

    // Estudio: un reingreso vigente debe poder emitir constancia de estudio.
    $stmt = $pdo->prepare(
        "SELECT m.id_estudiante
           FROM matricula m
          WHERE m.id_anio_escolar = :id_anio
            AND m.tipo = 'REINGRESO'
          ORDER BY m.id_matricula DESC
          LIMIT 1"
    );
    $stmt->execute(['id_anio' => $idAnio]);
    $estudianteReingreso = (int) ($stmt->fetchColumn() ?: 0);
    if ($estudianteReingreso > 0) {
        $check(
            'Estudio: estudiante con reingreso vigente es elegible',
            $model->esElegibleParaEstudio($estudianteReingreso)
        );
        $listaEstudio = $model->getEstudiantesEstudio([], 1, 100);
        $idsEstudio = array_map(static fn(array $f): int => (int) $f['id_estudiante'], $listaEstudio);
        $check(
            'Estudio: el reingreso aparece en el listado',
            in_array($estudianteReingreso, $idsEstudio, true)
        );
    } else {
        echo "  [OMITIDO] Estudio: no hay reingresos en el año activo.\n";
    }

    // Inscripción: una fila por estudiante.
    $inscripcion = $model->getEstudiantesInscripcion([], 1, 100);
    $idsInscripcion = array_map(static fn(array $f): int => (int) $f['id_estudiante'], $inscripcion);
    $check(
        'Inscripción: no hay estudiantes duplicados',
        count($idsInscripcion) === count(array_unique($idsInscripcion))
    );
    $check(
        'Inscripción: el total coincide con las filas del listado',
        $model->countEstudiantesInscripcion([]) === count($inscripcion)
    );

    $stmt = $pdo->prepare(
        "SELECT m.id_estudiante, m.id_matricula, m.tipo
           FROM matricula m
          WHERE m.id_anio_escolar = :id_anio
            AND m.tipo = 'REINGRESO'
          ORDER BY m.id_matricula DESC
          LIMIT 1"
    );
    $stmt->execute(['id_anio' => $idAnio]);
    $reingreso = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($reingreso) {
        $filaReingreso = null;
        foreach ($inscripcion as $fila) {
            if ((int) $fila['id_estudiante'] === (int) $reingreso['id_estudiante']) {
                $filaReingreso = $fila;
                break;
            }
        }
        $check(
            'Inscripción: el reingreso aparece una sola vez',
            $filaReingreso !== null
        );
        $check(
            'Inscripción: el reingreso se identifica como REINGRESO',
            $filaReingreso !== null && $filaReingreso['tipo_matricula'] === 'REINGRESO'
        );
        $check(
            'Inscripción: se muestra la matrícula más reciente del reingreso',
            $filaReingreso !== null && (int) $filaReingreso['id_matricula'] === (int) $reingreso['id_matricula']
        );
    } else {
        echo "  [OMITIDO] Inscripción: no hay reingresos en el año activo.\n";
    }
} catch (Throwable $e) {
    $fallos[] = 'Excepción: ' . $e->getMessage();
    echo '  [FALLO] Excepción: ' . $e->getMessage() . "\n";
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

echo "\n";
if ($fallos !== []) {
    echo "RESULTADO: " . count($fallos) . " fallo(s).\n";
    foreach ($fallos as $fallo) {
        echo " - {$fallo}\n";
    }
    exit(1);
}

echo "RESULTADO: todas las reglas verificadas correctamente.\n";
exit(0);
