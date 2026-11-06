<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\AnioEscolarCerradoException;
use App\Support\Matriculas;
use App\Traits\CupoEstructuraTrait;
use DateTime;
use PDO;
use RuntimeException;
use Throwable;

class ReingresoModel extends BaseModel
{
    use CupoEstructuraTrait;

    protected string $table = 'reingreso';
    protected string $primaryKey = 'id_reingreso';
    protected array $columns = [
        'id_reingreso',
        'id_matricula',
        'id_retiro_previo',
        'id_institucion_procedencia',
        'motivo_reingreso',
        'fecha_reincorporacion',
        'id_usuario_resolucion',
        'observaciones',
        'fecha_creacion',
    ];
    protected array $allowedColumns = [
        'id_reingreso',
        'id_matricula',
        'id_retiro_previo',
        'id_institucion_procedencia',
        'motivo_reingreso',
        'fecha_reincorporacion',
        'id_usuario_resolucion',
        'observaciones',
    ];

    /**
     * Obtiene el listado de estudiantes retirados que son candidatos a reingreso
     * (aquellos cuyo último evento en el sistema fue un RETIRO aprobado y no tienen matrícula posterior).
     */
    public function getRetiradosElegibles(?string $busqueda = null, int $limite = 100): array
    {
        $where = [
            "m.tipo = 'RETIRO'",
            "r.estado_solicitud = 'APROBADO'",
            "NOT EXISTS (
                SELECT 1 FROM matricula m_post
                INNER JOIN anio_escolar a_post ON a_post.id_anio_escolar = m_post.id_anio_escolar
                WHERE m_post.id_estudiante = m.id_estudiante
                  AND (a_post.anio_inicio > a.anio_inicio 
                       OR (a_post.anio_inicio = a.anio_inicio AND m_post.id_matricula > m.id_matricula))
            )",
        ];
        $params = [];

        if ($busqueda !== null && trim($busqueda) !== '') {
            $where[] = "(p.primer_nombre LIKE :q0 OR p.primer_apellido LIKE :q1 OR p.numero_documento LIKE :q2)";
            $term = '%' . trim($busqueda) . '%';
            $params['q0'] = $term;
            $params['q1'] = $term;
            $params['q2'] = $term;
        }

        $sql = "SELECT e.id_estudiante,
                       m.id_matricula AS id_matricula_retiro,
                       r.id_retiro,
                       CONCAT_WS(' ', p.primer_nombre, p.primer_apellido) AS nombre_completo,
                       CONCAT(p.tipo_documento, '-', p.numero_documento) AS documento,
                       p.foto,
                       COALESCE(g.nombre, 'Sin grado') AS ultimo_grado,
                       COALESCE(s.nombre, 'Sin sección') AS ultima_seccion,
                       COALESCE(t.nombre, 'Sin turno') AS ultimo_turno,
                       a.nombre_periodo AS anio_retiro,
                       r.fecha_retiro,
                       mot.nombre AS motivo_retiro
                  FROM matricula m
                  INNER JOIN estudiante e ON e.id_estudiante = m.id_estudiante
                  INNER JOIN persona p ON p.id_persona = e.id_persona
                  INNER JOIN anio_escolar a ON a.id_anio_escolar = m.id_anio_escolar
                  INNER JOIN retiro r ON r.id_matricula = m.id_matricula
                  INNER JOIN motivo_retiro mot ON mot.id_motivo_retiro = r.id_motivo_retiro
                  LEFT JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
                  LEFT JOIN grado g ON g.id_grado = ea.id_grado
                  LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion
                  LEFT JOIN turno t ON t.id_turno = ea.id_turno
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY m.fecha_matricula DESC, p.primer_apellido, p.primer_nombre
                 LIMIT " . (int) $limite;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene los datos del estudiante retirado para precargar el modal de reingreso.
     */
    public function getDatosEstudiante(int $idEstudiante): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT e.id_estudiante,
                    m.id_matricula AS id_matricula_retiro,
                    r.id_retiro,
                    p.primer_nombre, p.primer_apellido,
                    CONCAT(p.tipo_documento, '-', p.numero_documento) AS documento,
                    g.id_grado AS ultimo_id_grado,
                    COALESCE(g.nombre, 'Sin grado') AS ultimo_grado,
                    COALESCE(s.nombre, 'Sin sección') AS ultima_seccion,
                    COALESCE(t.nombre, 'Sin turno') AS ultimo_turno,
                    a.id_anio_escolar AS id_anio_retiro,
                    a.nombre_periodo AS anio_retiro,
                    r.fecha_retiro,
                    r.estado_solicitud,
                    mot.nombre AS motivo_retiro
               FROM matricula m
               INNER JOIN estudiante e ON e.id_estudiante = m.id_estudiante
               INNER JOIN persona p ON p.id_persona = e.id_persona
               INNER JOIN anio_escolar a ON a.id_anio_escolar = m.id_anio_escolar
               INNER JOIN retiro r ON r.id_matricula = m.id_matricula
               INNER JOIN motivo_retiro mot ON mot.id_motivo_retiro = r.id_motivo_retiro
               LEFT JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
               LEFT JOIN grado g ON g.id_grado = ea.id_grado
               LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion
               LEFT JOIN turno t ON t.id_turno = ea.id_turno
              WHERE e.id_estudiante = :id
              ORDER BY a.anio_inicio DESC, m.id_matricula DESC
              LIMIT 1"
        );
        $stmt->execute(['id' => $idEstudiante]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Procesa la reincorporación formal del estudiante con validaciones completas:
     * 1. Valida parámetros de entrada (fechas, longitudes, ids positivos).
     * 2. Reserva cupo en la estructura elegida (bloqueo for update).
     * 3. Valida año escolar activo y no cerrado.
     * 4. Valida retiro previo en estado APROBADO y coherencia temporal.
     * 5. Inserta en la supertabla `matricula` con tipo 'RATIFICACION' (continuidad académica).
     * 6. Inserta en la subtabla `reingreso` con los datos de trazabilidad del retorno.
     * 7. Registra en bitácora.
     */
    public function procesarReingreso(array $data, int $idUsuario): array
    {
        $idEstudiante = (int) ($data['id_estudiante'] ?? 0);
        $idEstructura = (int) ($data['id_estructura_academica'] ?? 0);
        $motivoReingreso = trim((string) ($data['motivo_reingreso'] ?? ''));
        $fechaReinc = trim((string) ($data['fecha_reincorporacion'] ?? '')) ?: date('Y-m-d');
        $idInstitucion = !empty($data['id_institucion_procedencia']) ? (int) $data['id_institucion_procedencia'] : null;
        $observaciones = !empty($data['observaciones']) ? trim((string) $data['observaciones']) : null;

        // Validaciones de integridad
        if ($idEstudiante <= 0) {
            throw new RuntimeException('Debe especificar un estudiante válido.');
        }
        if ($idEstructura <= 0) {
            throw new RuntimeException('Debe seleccionar el grado y sección a cursar.');
        }
        if (mb_strlen($motivoReingreso) < 5) {
            throw new RuntimeException('El motivo de reingreso debe contener al menos 5 caracteres.');
        }
        if (mb_strlen($motivoReingreso) > 255) {
            throw new RuntimeException('El motivo de reingreso no puede exceder los 255 caracteres.');
        }

        $d = DateTime::createFromFormat('Y-m-d', $fechaReinc);
        if (!$d || $d->format('Y-m-d') !== $fechaReinc) {
            throw new RuntimeException('La fecha de reincorporación no tiene un formato válido (AAAA-MM-DD).');
        }
        if ($fechaReinc > date('Y-m-d')) {
            throw new RuntimeException('La fecha de reincorporación no puede ser una fecha futura.');
        }

        try {
            $this->pdo->beginTransaction();

            // Validar institución de procedencia si fue indicada
            if ($idInstitucion !== null && $idInstitucion > 0) {
                $stmtInst = $this->pdo->prepare('SELECT COUNT(*) FROM institucion WHERE id_institucion = :id');
                $stmtInst->execute(['id' => $idInstitucion]);
                if ((int) $stmtInst->fetchColumn() === 0) {
                    throw new RuntimeException('La institución de procedencia seleccionada no existe.');
                }
            }

            // 1. Reservar y bloquear cupo
            $disponibilidad = $this->reservarCupo($idEstructura);
            $idAnioEscolar = (int) ($disponibilidad['id_anio_escolar'] ?? 0);
            if ($idAnioEscolar <= 0) {
                throw new RuntimeException('La estructura académica seleccionada no tiene un año escolar válido.');
            }

            // Comprobar estado del año escolar
            $stmtAnio = $this->pdo->prepare('SELECT nombre_periodo, estado, anio_inicio FROM anio_escolar WHERE id_anio_escolar = :anio');
            $stmtAnio->execute(['anio' => $idAnioEscolar]);
            $anio = $stmtAnio->fetch(PDO::FETCH_ASSOC);
            if (!$anio || strtoupper((string) $anio['estado']) !== 'ACTIVO') {
                throw new AnioEscolarCerradoException(
                    'No se puede reincorporar en un año escolar cerrado (' . ($anio['nombre_periodo'] ?? '') . ').'
                );
            }
            $anioInicioActivo = (int) ($anio['anio_inicio'] ?? 0);

            // 2. Obtener y validar la matrícula de retiro previa
            $stmtRetiro = $this->pdo->prepare(
                "SELECT m.id_matricula, m.tipo, m.id_anio_escolar, r.id_retiro, r.estado_solicitud, an.anio_inicio AS anio_inicio_retiro
                   FROM matricula m
                   INNER JOIN anio_escolar an ON an.id_anio_escolar = m.id_anio_escolar
                   LEFT JOIN retiro r ON r.id_matricula = m.id_matricula
                  WHERE m.id_estudiante = :id
                  ORDER BY an.anio_inicio DESC, m.id_matricula DESC
                  LIMIT 1
                  FOR UPDATE"
            );
            $stmtRetiro->execute(['id' => $idEstudiante]);
            $ultimo = $stmtRetiro->fetch(PDO::FETCH_ASSOC);

            if (!$ultimo || $ultimo['tipo'] !== Matriculas::RETIRO) {
                throw new RuntimeException(
                    'El estudiante no tiene un retiro previo vigente. Solo se pueden reincorporar estudiantes retirados.'
                );
            }

            if (strtoupper((string) ($ultimo['estado_solicitud'] ?? '')) !== 'APROBADO') {
                throw new RuntimeException(
                    'La solicitud de retiro del estudiante no ha sido aprobada por la dirección escolar.'
                );
            }

            $anioInicioRetiro = (int) ($ultimo['anio_inicio_retiro'] ?? 0);
            if ($anioInicioActivo < $anioInicioRetiro) {
                throw new RuntimeException(
                    'El año escolar del reingreso no puede ser anterior al año escolar en que se retiró el estudiante.'
                );
            }

            $idRetiroPrevio = !empty($ultimo['id_retiro']) ? (int) $ultimo['id_retiro'] : null;

            // 3. Insertar nueva matrícula de cursada regular
            $stmtMat = $this->pdo->prepare(
                "INSERT INTO matricula (id_estudiante, fecha_matricula, id_anio_escolar, id_estructura_academica, tipo, motivo_anticipo)
                 VALUES (:estudiante, :fecha, :anio, :estructura, :tipo, :anticipo)"
            );
            $stmtMat->execute([
                'estudiante' => $idEstudiante,
                'fecha'      => $fechaReinc,
                'anio'       => $idAnioEscolar,
                'estructura' => $idEstructura,
                'tipo'       => Matriculas::RATIFICACION,
                'anticipo'   => 'Reingreso tras retiro previo',
            ]);
            $idMatricula = (int) $this->pdo->lastInsertId();

            // 4. Insertar en la tabla especializada `reingreso`
            $stmtReinc = $this->pdo->prepare(
                "INSERT INTO reingreso (id_matricula, id_retiro_previo, id_institucion_procedencia, motivo_reingreso, fecha_reincorporacion, id_usuario_resolucion, observaciones)
                 VALUES (:mat, :retiro, :inst, :motivo, :fecha, :usuario, :obs)"
            );
            $stmtReinc->execute([
                'mat'     => $idMatricula,
                'retiro'  => $idRetiroPrevio,
                'inst'    => $idInstitucion,
                'motivo'  => $motivoReingreso,
                'fecha'   => $fechaReinc,
                'usuario' => $idUsuario > 0 ? $idUsuario : null,
                'obs'     => $observaciones,
            ]);

            // 5. Registrar bitácora
            if ($idUsuario > 0) {
                (new AuditLogModel($this->pdo))->registrar(
                    $idUsuario,
                    "Reincorporó al estudiante ID {$idEstudiante} en matrícula ID {$idMatricula} ({$disponibilidad['nombre']})",
                    'reingreso'
                );
            }

            $this->pdo->commit();
            return ['ok' => true, 'id_matricula' => $idMatricula, 'mensaje' => 'Estudiante reincorporado exitosamente.'];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}