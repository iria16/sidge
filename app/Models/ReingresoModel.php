<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\AnioEscolarCerradoException;
use App\Support\Instituciones;
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
     * Busca estudiantes por cédula o nombre para el modal de reingreso,
     * evaluando su elegibilidad según su último evento académico.
     */
    public function buscarPorDocumento(string $termino, int $limite = 10, ?int $idTurno = null): array
    {
        $digitos = preg_replace('/\D/', '', $termino) ?? '';
        if ($digitos === '' && mb_strlen($termino, 'UTF-8') < 3) {
            return [];
        }

        $sql = "SELECT e.id_estudiante,
                       CONCAT_WS(' ', p.primer_nombre, p.primer_apellido) AS nombre,
                       CONCAT(p.tipo_documento, '-', p.numero_documento) AS cedula,
                       m.id_matricula,
                       m.tipo,
                       r.id_retiro,
                       r.estado_solicitud,
                       r.fecha_retiro,
                       mot.nombre AS motivo_retiro,
                       COALESCE(g.nombre, 'Sin grado') AS grado,
                       COALESCE(s.nombre, 'Sin sección') AS seccion,
                       COALESCE(t.nombre, 'Sin turno') AS turno,
                       COALESCE(a.nombre_periodo, 'Sin año') AS anio
                  FROM estudiante e
                  INNER JOIN persona p ON p.id_persona = e.id_persona
                  LEFT JOIN matricula m ON m.id_matricula = (
                      SELECT m2.id_matricula
                        FROM matricula m2
                        JOIN anio_escolar a2 ON a2.id_anio_escolar = m2.id_anio_escolar
                       WHERE m2.id_estudiante = e.id_estudiante
                       ORDER BY a2.anio_inicio DESC, m2.id_matricula DESC
                       LIMIT 1
                  )
                  LEFT JOIN retiro r ON r.id_matricula = m.id_matricula
                  LEFT JOIN motivo_retiro mot ON mot.id_motivo_retiro = r.id_motivo_retiro
                  LEFT JOIN anio_escolar a ON a.id_anio_escolar = m.id_anio_escolar
                  LEFT JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
                  LEFT JOIN grado g ON g.id_grado = ea.id_grado
                  LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion
                  LEFT JOIN turno t ON t.id_turno = ea.id_turno
                 WHERE (REGEXP_REPLACE(p.numero_documento, '[^0-9]', '') LIKE :terminoDoc
                        OR CONCAT_WS(' ', p.primer_nombre, p.primer_apellido) LIKE :terminoNom)";

        if ($idTurno !== null && $idTurno > 0) {
            $sql .= " AND (ea.id_turno = :turno OR ea.id_turno IS NULL)";
        }

        $sql .= " ORDER BY p.primer_nombre, p.primer_apellido LIMIT :limite";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':terminoDoc', ($digitos !== '' ? $digitos : $termino) . '%');
        $stmt->bindValue(':terminoNom', '%' . trim($termino) . '%');
        if ($idTurno !== null && $idTurno > 0) {
            $stmt->bindValue(':turno', $idTurno, PDO::PARAM_INT);
        }
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return array_map([$this, 'mapBusquedaEstudiante'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Evalúa si el resultado de búsqueda es elegible para reincorporación.
     */
    private function mapBusquedaEstudiante(array $fila): array
    {
        $tipo = $fila['tipo'] ?? null;
        $idMatricula = $fila['id_matricula'] !== null ? (int) $fila['id_matricula'] : null;
        $estadoRetiro = strtoupper(trim((string) ($fila['estado_solicitud'] ?? '')));

        if ($idMatricula === null) {
            $motivo = 'Este estudiante no tiene historial de matrícula en el plantel.';
        } elseif ($tipo === Matriculas::EGRESO) {
            $motivo = 'El estudiante ya egresó del plantel.';
        } elseif ($tipo === Matriculas::INSCRIPCION || $tipo === Matriculas::RATIFICACION) {
            $motivo = 'El estudiante ya se encuentra activo cursando en el sistema.';
        } elseif ($tipo === Matriculas::RETIRO) {
            if ($estadoRetiro === 'PENDIENTE') {
                $motivo = 'La solicitud de retiro del estudiante aún está pendiente de aprobación directiva.';
            } elseif ($estadoRetiro === 'RECHAZADO') {
                $motivo = 'La solicitud de retiro fue rechazada; el estudiante continúa matriculado.';
            } elseif ($estadoRetiro === 'APROBADO') {
                $motivo = ''; // Elegible para reingreso
            } else {
                $motivo = 'El retiro del estudiante no se encuentra en estado aprobado.';
            }
        } else {
            $motivo = 'El estado de la matrícula previa no admite reincorporación.';
        }

        return [
            'id_estudiante'  => (int) $fila['id_estudiante'],
            'id_matricula'   => $idMatricula,
            'nombre'         => trim((string) ($fila['nombre'] ?? '')) ?: 'Estudiante',
            'cedula'         => $fila['cedula'] ?? '—',
            'ultimo_grado'   => $fila['grado'] ?? '—',
            'ordinal_grado'  => Instituciones::ordinalGrado((string) ($fila['grado'] ?? '')),
            'ultima_seccion' => $fila['seccion'] ?? '—',
            'ultimo_turno'   => $fila['turno'] ?? '—',
            'anio_retiro'    => $fila['anio'] ?? '—',
            'fecha_retiro'   => $fila['fecha_retiro'] ?? '—',
            'motivo_retiro'  => $fila['motivo_retiro'] ?? '—',
            'elegible'       => $motivo === '',
            'motivo_bloqueo' => $motivo,
        ];
    }

    /**
     * Lista el historial paginado de reingresos formalizados para el data panel.
     */
    public function listarReingresos(array $filters = [], int $pagina = 1, int $porPagina = 10, ?int $idTurno = null): array
    {
        $offset = max(0, ($pagina - 1) * $porPagina);
        // El historial visible es el del período activo, igual que las tarjetas.
        $where = ["a_act.estado = 'ACTIVO'"];
        $params = [];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = "(p.primer_nombre LIKE :q0 OR p.primer_apellido LIKE :q1 OR p.numero_documento LIKE :q2)";
            $term = '%' . $q . '%';
            $params['q0'] = $term;
            $params['q1'] = $term;
            $params['q2'] = $term;
        }

        if ($idTurno !== null && $idTurno > 0) {
            $where[] = "ea_act.id_turno = :turno";
            $params['turno'] = $idTurno;
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Conteo total
        $sqlCount = "SELECT COUNT(*)
                       FROM reingreso rein
                       INNER JOIN matricula m ON m.id_matricula = rein.id_matricula
                       INNER JOIN estudiante e ON e.id_estudiante = m.id_estudiante
                       INNER JOIN persona p ON p.id_persona = e.id_persona
                       INNER JOIN anio_escolar a_act ON a_act.id_anio_escolar = m.id_anio_escolar
                       INNER JOIN estructura_academica ea_act ON ea_act.id_estructura_academica = m.id_estructura_academica
                       $whereClause";
        $stmtCount = $this->pdo->prepare($sqlCount);
        $stmtCount->execute($params);
        $total = (int) $stmtCount->fetchColumn();

        // Filas paginadas
        $sql = "SELECT rein.id_reingreso,
                       rein.fecha_reincorporacion,
                       rein.motivo_reingreso,
                       rein.observaciones,
                       e.id_estudiante,
                       CONCAT_WS(' ', p.primer_nombre, p.primer_apellido) AS estudiante,
                       CONCAT(p.tipo_documento, '-', p.numero_documento) AS cedula,
                       p.primer_nombre,
                       p.primer_apellido,
                       p.foto,
                       g_act.nombre AS grado,
                       s_act.nombre AS seccion,
                       t_act.nombre AS turno,
                       a_act.nombre_periodo AS anio,
                       a_ret.nombre_periodo AS anio_retiro,
                       g_ret.nombre AS grado_retiro,
                       mot.nombre AS motivo_retiro,
                       u.nombre_usuario AS usuario_resolucion
                  FROM reingreso rein
                  INNER JOIN matricula m ON m.id_matricula = rein.id_matricula
                  INNER JOIN estudiante e ON e.id_estudiante = m.id_estudiante
                  INNER JOIN persona p ON p.id_persona = e.id_persona
                  INNER JOIN anio_escolar a_act ON a_act.id_anio_escolar = m.id_anio_escolar
                  INNER JOIN estructura_academica ea_act ON ea_act.id_estructura_academica = m.id_estructura_academica
                  INNER JOIN grado g_act ON g_act.id_grado = ea_act.id_grado
                  INNER JOIN seccion s_act ON s_act.id_seccion = ea_act.id_seccion
                  INNER JOIN turno t_act ON t_act.id_turno = ea_act.id_turno
                  LEFT JOIN retiro r ON r.id_retiro = rein.id_retiro_previo
                  LEFT JOIN matricula m_ret ON m_ret.id_matricula = r.id_matricula
                  LEFT JOIN anio_escolar a_ret ON a_ret.id_anio_escolar = m_ret.id_anio_escolar
                  LEFT JOIN estructura_academica ea_ret ON ea_ret.id_estructura_academica = m_ret.id_estructura_academica
                  LEFT JOIN grado g_ret ON g_ret.id_grado = ea_ret.id_grado
                  LEFT JOIN motivo_retiro mot ON mot.id_motivo_retiro = r.id_motivo_retiro
                  LEFT JOIN usuario u ON u.id_usuario = rein.id_usuario_resolucion
                  $whereClause
                 ORDER BY rein.fecha_reincorporacion DESC, rein.id_reingreso DESC
                 LIMIT :limite OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':limite', $porPagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Iniciales para el avatar
        foreach ($solicitudes as &$item) {
            $p = mb_substr((string) ($item['primer_nombre'] ?? ''), 0, 1, 'UTF-8');
            $a = mb_substr((string) ($item['primer_apellido'] ?? ''), 0, 1, 'UTF-8');
            $item['iniciales'] = strtoupper($p . $a);
        }
        unset($item);

        $totalPaginas = $total > 0 ? (int) ceil($total / $porPagina) : 1;
        $desde = $total > 0 ? $offset + 1 : 0;
        $hasta = min($total, $offset + count($solicitudes));

        return [
            'solicitudes' => $solicitudes,
            'paginacion'  => [
                'desde'         => $desde,
                'hasta'         => $hasta,
                'total'         => $total,
                'pagina_actual' => $pagina,
                'total_paginas' => $totalPaginas,
            ],
        ];
    }

    /**
     * Indicadores numéricos para las tarjetas estadísticas del módulo.
     */
    public function getStats(?int $idTurno = null): array
    {
        $sqlReinc = "SELECT COUNT(*)
                       FROM reingreso rein
                       INNER JOIN matricula m ON m.id_matricula = rein.id_matricula
                       INNER JOIN anio_escolar a ON a.id_anio_escolar = m.id_anio_escolar
                       INNER JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
                      WHERE a.estado = 'ACTIVO'";
        if ($idTurno !== null && $idTurno > 0) {
            $sqlReinc .= " AND ea.id_turno = " . (int) $idTurno;
        }
        $totalReingresos = (int) $this->pdo->query($sqlReinc)->fetchColumn();

        $sqlRet = "SELECT COUNT(*)
                     FROM estudiante e
                     INNER JOIN matricula m ON m.id_matricula = (
                         SELECT m2.id_matricula
                           FROM matricula m2
                           JOIN anio_escolar a2 ON a2.id_anio_escolar = m2.id_anio_escolar
                          WHERE m2.id_estudiante = e.id_estudiante
                          ORDER BY a2.anio_inicio DESC, m2.id_matricula DESC
                          LIMIT 1
                     )
                     INNER JOIN retiro r ON r.id_matricula = m.id_matricula
                     LEFT JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
                    WHERE m.tipo = 'RETIRO' AND r.estado_solicitud = 'APROBADO'";
        if ($idTurno !== null && $idTurno > 0) {
            $sqlRet .= " AND (ea.id_turno = " . (int) $idTurno . " OR ea.id_turno IS NULL)";
        }
        $retiradosPendientes = (int) $this->pdo->query($sqlRet)->fetchColumn();

        return [
            'reingresos_activos'  => $totalReingresos,
            'retirados_elegibles' => $retiradosPendientes,
        ];
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
                "SELECT m.id_matricula, m.tipo, m.id_anio_escolar, r.id_retiro, r.estado_solicitud, an.anio_inicio AS anio_inicio_retiro,
                        COALESCE(g.nombre, '') AS ultimo_grado
                   FROM matricula m
                   INNER JOIN anio_escolar an ON an.id_anio_escolar = m.id_anio_escolar
                   LEFT JOIN retiro r ON r.id_matricula = m.id_matricula
                   LEFT JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
                   LEFT JOIN grado g ON g.id_grado = ea.id_grado
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

            // Validar que el grado de destino no sea inferior al cursado antes del retiro
            $ordinalRetiro = Instituciones::ordinalGrado((string) ($ultimo['ultimo_grado'] ?? ''));
            $ordinalDestino = Instituciones::ordinalGrado((string) ($disponibilidad['grado'] ?? ''));
            if ($ordinalRetiro !== null && $ordinalDestino !== null && $ordinalDestino < $ordinalRetiro) {
                throw new RuntimeException(
                    "No se permite reingreso en un grado inferior ({$disponibilidad['grado']}) al grado cursado antes del retiro ({$ultimo['ultimo_grado']})."
                );
            }

            $idRetiroPrevio = !empty($ultimo['id_retiro']) ? (int) $ultimo['id_retiro'] : null;

            // 3. Insertar nueva matrícula de cursada regular bajo tipo REINGRESO
            $stmtMat = $this->pdo->prepare(
                "INSERT INTO matricula (id_estudiante, fecha_matricula, id_anio_escolar, id_estructura_academica, tipo, motivo_anticipo)
                 VALUES (:estudiante, :fecha, :anio, :estructura, :tipo, :anticipo)"
            );
            $stmtMat->execute([
                'estudiante' => $idEstudiante,
                'fecha'      => $fechaReinc,
                'anio'       => $idAnioEscolar,
                'estructura' => $idEstructura,
                'tipo'       => Matriculas::REINGRESO,
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

            // 5. Reactivar la persona: el retiro la dejó INACTIVA y el reingreso
            // la vuelve a habilitar para constancias y demás procesos académicos.
            $stmtActivar = $this->pdo->prepare(
                "UPDATE persona p
                 INNER JOIN estudiante e ON e.id_persona = p.id_persona
                    SET p.estado = 'ACTIVO'
                  WHERE e.id_estudiante = :estudiante"
            );
            $stmtActivar->execute(['estudiante' => $idEstudiante]);

            $stmtEstado = $this->pdo->prepare(
                "SELECT p.estado
                   FROM persona p
                   INNER JOIN estudiante e ON e.id_persona = p.id_persona
                  WHERE e.id_estudiante = :estudiante"
            );
            $stmtEstado->execute(['estudiante' => $idEstudiante]);
            if ((string) $stmtEstado->fetchColumn() !== 'ACTIVO') {
                throw new RuntimeException('No se pudo reactivar el estado del estudiante reincorporado.');
            }

            // 6. Registrar bitácora
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