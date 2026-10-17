<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * ConstanciaModel
 * ---------------
 * Modelo para la gestión y consulta de datos académicos requeridos en la
 * emisión de Constancias de Estudio y de Inscripción.
 *
 * Los datos se resuelven exclusivamente desde la base de datos a partir de
 * las tablas estudiante, persona, matricula, estructura_academica, grado,
 * seccion, anio_escolar, parroquia, municipio y estado.
 */
class ConstanciaModel extends BaseModel
{
    protected string $table = 'estudiante';
    protected string $primaryKey = 'id_estudiante';

    /**
     * Fragmento SQL canónico de la condición única de elegibilidad para Constancia de Estudio:
     * - Persona con estado ACTIVO.
     * - Matrícula de tipo INSCRIPCION o RATIFICACION en el año escolar activo.
     * - Último movimiento del estudiante distinto de RETIRO y EGRESO (ordenado por fecha_matricula DESC, id_matricula DESC).
     */
    public function sqlCondicionElegibilidadEstudio(): string
    {
        return "p.estado = 'ACTIVO'
            AND EXISTS (
                SELECT 1
                FROM matricula m_act
                WHERE m_act.id_estudiante = est.id_estudiante
                  AND m_act.id_anio_escolar = :id_anio_activo
                  AND m_act.tipo IN ('INSCRIPCION', 'RATIFICACION')
            )
            AND (
                SELECT m_sub.tipo
                FROM matricula m_sub
                WHERE m_sub.id_estudiante = est.id_estudiante
                ORDER BY m_sub.fecha_matricula DESC, m_sub.id_matricula DESC
                LIMIT 1
            ) NOT IN ('RETIRO', 'EGRESO')";
    }

    /**
     * Valida de forma única y centralizada si un estudiante es elegible para Constancia de Estudio:
     * - Persona con estado ACTIVO.
     * - Matrícula de tipo INSCRIPCION o RATIFICACION en el año escolar activo.
     * - Último movimiento del estudiante distinto de RETIRO y EGRESO (ordenado por fecha_matricula DESC, id_matricula DESC).
     */
    public function esElegibleParaEstudio(int $idEstudiante): bool
    {
        if ($idEstudiante <= 0) {
            return false;
        }

        $idAnioActivo = \App\Support\AniosEscolares::idEnCurso($this->pdo);
        if ($idAnioActivo <= 0) {
            return false;
        }

        $sql = "SELECT 1
                FROM estudiante est
                INNER JOIN persona p ON p.id_persona = est.id_persona
                WHERE est.id_estudiante = :id_estudiante
                  AND {$this->sqlCondicionElegibilidadEstudio()}
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id_anio_activo' => $idAnioActivo,
            'id_estudiante'  => $idEstudiante,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Devuelve el listado de estudiantes para Constancias de Estudio con filtros y paginación.
     * Aplica la regla única de elegibilidad: alumnos activos inscritos en el período activo.
     */
    public function getEstudiantesEstudio(array $filters = [], int $page = 1, int $perPage = 10): array
    {
        $idAnioActivo = \App\Support\AniosEscolares::idEnCurso($this->pdo);
        if ($idAnioActivo <= 0) {
            return [];
        }

        [$where, $params] = $this->buildFiltrosEstudio($filters, $idAnioActivo);

        $sql = "SELECT est.id_estudiante,
                       CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS nombre_completo,
                       CONCAT_WS(' ', p.primer_nombre, p.primer_apellido) AS nombre_corto,
                       p.tipo_documento,
                       p.numero_documento,
                       p.fecha_nacimiento,
                       p.estado AS estado_persona,
                       par.nombre AS parroquia_nacimiento,
                       mun.nombre AS municipio_nacimiento,
                       COALESCE(est_nac.nombre, est_mun.nombre) AS estado_nacimiento,
                       g.nombre AS grado,
                       s.nombre AS seccion,
                       a.nombre_periodo AS anio_escolar,
                       m.tipo AS ultimo_tipo_matricula,
                       'activo' AS estado_academico
                FROM estudiante est
                INNER JOIN persona p ON p.id_persona = est.id_persona
                INNER JOIN matricula m ON m.id_matricula = (
                    SELECT m2.id_matricula
                    FROM matricula m2
                    WHERE m2.id_estudiante = est.id_estudiante
                      AND m2.id_anio_escolar = :id_anio_activo_join
                      AND m2.tipo IN ('INSCRIPCION', 'RATIFICACION')
                    ORDER BY m2.fecha_matricula DESC, m2.id_matricula DESC
                    LIMIT 1
                )
                INNER JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
                INNER JOIN anio_escolar a ON a.id_anio_escolar = m.id_anio_escolar
                INNER JOIN grado g ON g.id_grado = ea.id_grado
                LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion
                LEFT JOIN parroquia par ON par.id_parroquia = p.id_parroquia_nacimiento
                LEFT JOIN municipio mun ON mun.id_municipio = par.id_municipio
                LEFT JOIN estado est_mun ON est_mun.id_estado = mun.id_estado
                LEFT JOIN estado est_nac ON est_nac.id_estado = p.id_estado_nacimiento
                WHERE {$this->sqlCondicionElegibilidadEstudio()}";

        if (!empty($where)) {
            $sql .= ' AND ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY p.primer_nombre, p.primer_apellido LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->execute();

        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($filas as &$f) {
            $f['id_estudiante'] = (int) $f['id_estudiante'];
            $f['datos_completos'] = !empty($f['nombre_completo'])
                && !empty($f['numero_documento'])
                && !empty($f['fecha_nacimiento'])
                && !empty($f['municipio_nacimiento'])
                && !empty($f['estado_nacimiento'])
                && !empty($f['grado'])
                && !empty($f['anio_escolar']);
        }

        return $filas;
    }

    /**
     * Cuenta el total de estudiantes para Constancias de Estudio.
     * Aplica la regla única de elegibilidad: alumnos activos inscritos en el período activo.
     */
    public function countEstudiantesEstudio(array $filters = []): int
    {
        $idAnioActivo = \App\Support\AniosEscolares::idEnCurso($this->pdo);
        if ($idAnioActivo <= 0) {
            return 0;
        }

        [$where, $params] = $this->buildFiltrosEstudio($filters, $idAnioActivo);

        $sql = "SELECT COUNT(DISTINCT est.id_estudiante)
                FROM estudiante est
                INNER JOIN persona p ON p.id_persona = est.id_persona
                INNER JOIN matricula m ON m.id_matricula = (
                    SELECT m2.id_matricula
                    FROM matricula m2
                    WHERE m2.id_estudiante = est.id_estudiante
                      AND m2.id_anio_escolar = :id_anio_activo_join
                      AND m2.tipo IN ('INSCRIPCION', 'RATIFICACION')
                    ORDER BY m2.fecha_matricula DESC, m2.id_matricula DESC
                    LIMIT 1
                )
                INNER JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
                INNER JOIN grado g ON g.id_grado = ea.id_grado
                LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion
                WHERE {$this->sqlCondicionElegibilidadEstudio()}";

        if (!empty($where)) {
            $sql .= ' AND ' . implode(' AND ', $where);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Devuelve el listado de inscripciones para Constancias de Inscripción con filtros y paginación.
     */
    public function getEstudiantesInscripcion(array $filters = [], int $page = 1, int $perPage = 10): array
    {
        [$where, $params] = $this->buildFiltrosInscripcion($filters);

        $sql = "SELECT est.id_estudiante,
                       m.id_matricula,
                       CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS nombre_completo,
                       CONCAT_WS(' ', p.primer_nombre, p.primer_apellido) AS nombre_corto,
                       p.tipo_documento,
                       p.numero_documento,
                       p.fecha_nacimiento,
                       p.estado AS estado_persona,
                       par.nombre AS parroquia_nacimiento,
                       mun.nombre AS municipio_nacimiento,
                       COALESCE(est_nac.nombre, est_mun.nombre) AS estado_nacimiento,
                       g.nombre AS grado,
                       s.nombre AS seccion,
                       a.id_anio_escolar,
                       a.nombre_periodo AS anio_escolar,
                       m.tipo AS tipo_matricula,
                       m.fecha_matricula
                FROM matricula m
                INNER JOIN estudiante est ON est.id_estudiante = m.id_estudiante
                INNER JOIN persona p ON p.id_persona = est.id_persona
                LEFT JOIN parroquia par ON par.id_parroquia = p.id_parroquia_nacimiento
                LEFT JOIN municipio mun ON mun.id_municipio = par.id_municipio
                LEFT JOIN estado est_mun ON est_mun.id_estado = mun.id_estado
                LEFT JOIN estado est_nac ON est_nac.id_estado = p.id_estado_nacimiento
                INNER JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
                INNER JOIN anio_escolar a ON a.id_anio_escolar = m.id_anio_escolar
                INNER JOIN grado g ON g.id_grado = ea.id_grado
                LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion
                WHERE m.tipo IN ('INSCRIPCION', 'RATIFICACION')";

        if (!empty($where)) {
            $sql .= ' AND ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY a.anio_inicio DESC, p.primer_nombre, p.primer_apellido LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->execute();

        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($filas as &$f) {
            $f['id_estudiante'] = (int) $f['id_estudiante'];
            $f['id_matricula']  = (int) $f['id_matricula'];
            $f['datos_completos'] = !empty($f['nombre_completo'])
                && !empty($f['numero_documento'])
                && !empty($f['fecha_nacimiento'])
                && !empty($f['municipio_nacimiento'])
                && !empty($f['estado_nacimiento'])
                && !empty($f['grado'])
                && !empty($f['anio_escolar']);
        }

        return $filas;
    }

    /**
     * Cuenta el total de inscripciones para Constancias de Inscripción.
     */
    public function countEstudiantesInscripcion(array $filters = []): int
    {
        [$where, $params] = $this->buildFiltrosInscripcion($filters);

        $sql = "SELECT COUNT(*)
                FROM matricula m
                INNER JOIN estudiante est ON est.id_estudiante = m.id_estudiante
                INNER JOIN persona p ON p.id_persona = est.id_persona
                INNER JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
                INNER JOIN anio_escolar a ON a.id_anio_escolar = m.id_anio_escolar
                INNER JOIN grado g ON g.id_grado = ea.id_grado
                LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion
                WHERE m.tipo IN ('INSCRIPCION', 'RATIFICACION')";

        if (!empty($where)) {
            $sql .= ' AND ' . implode(' AND ', $where);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Obtiene los datos completos y verificados de un estudiante para emitir su constancia.
     * Lectura estricta por ID desde la base de datos (nunca por POST).
     *
     * @param int $idEstudiante ID del estudiante
     * @param int|null $idMatricula ID específico de matrícula (opcional para constancia de inscripción)
     * @return array|null
     */
    public function getEstudianteParaConstancia(int $idEstudiante, ?int $idMatricula = null): ?array
    {
        $params = ['id_estudiante' => $idEstudiante];

        if ($idMatricula !== null && $idMatricula > 0) {
            // Consulta para una matrícula específica (ej. constancia de inscripción de un período dado)
            $sql = "SELECT est.id_estudiante,
                           m.id_matricula,
                           CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS nombre_completo,
                           p.tipo_documento,
                           p.numero_documento,
                           p.fecha_nacimiento,
                           p.sexo,
                           p.estado AS estado_persona,
                           par.nombre AS parroquia_nacimiento,
                           mun.nombre AS municipio_nacimiento,
                           COALESCE(est_nac.nombre, est_mun.nombre) AS estado_nacimiento,
                           g.nombre AS grado,
                           s.nombre AS seccion,
                           a.nombre_periodo AS periodo_escolar,
                           m.tipo AS tipo_matricula,
                           m.fecha_matricula,
                           'activo' AS modalidad
                    FROM matricula m
                    INNER JOIN estudiante est ON est.id_estudiante = m.id_estudiante
                    INNER JOIN persona p ON p.id_persona = est.id_persona
                    LEFT JOIN parroquia par ON par.id_parroquia = p.id_parroquia_nacimiento
                    LEFT JOIN municipio mun ON mun.id_municipio = par.id_municipio
                    LEFT JOIN estado est_mun ON est_mun.id_estado = mun.id_estado
                    LEFT JOIN estado est_nac ON est_nac.id_estado = p.id_estado_nacimiento
                    INNER JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
                    INNER JOIN anio_escolar a ON a.id_anio_escolar = m.id_anio_escolar
                    INNER JOIN grado g ON g.id_grado = ea.id_grado
                    LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion
                    WHERE est.id_estudiante = :id_estudiante AND m.id_matricula = :id_matricula
                    LIMIT 1";
            $params['id_matricula'] = $idMatricula;
        } else {
            // Consulta para la última matrícula académica (constancia de estudio)
            $sql = "SELECT est.id_estudiante,
                           CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS nombre_completo,
                           p.tipo_documento,
                           p.numero_documento,
                           p.fecha_nacimiento,
                           p.sexo,
                           p.estado AS estado_persona,
                           par.nombre AS parroquia_nacimiento,
                           mun.nombre AS municipio_nacimiento,
                           COALESCE(est_nac.nombre, est_mun.nombre) AS estado_nacimiento,
                           g.nombre AS grado,
                           s.nombre AS seccion,
                           a.nombre_periodo AS periodo_escolar,
                           m_ultimo.tipo AS ultimo_tipo_matricula,
                           CASE 
                               WHEN m_ultimo.tipo = 'RETIRO' THEN 'retirado'
                               WHEN m_ultimo.tipo = 'EGRESO' THEN 'egresado'
                               ELSE 'activo'
                           END AS modalidad
                    FROM estudiante est
                    INNER JOIN persona p ON p.id_persona = est.id_persona
                    LEFT JOIN parroquia par ON par.id_parroquia = p.id_parroquia_nacimiento
                    LEFT JOIN municipio mun ON mun.id_municipio = par.id_municipio
                    LEFT JOIN estado est_mun ON est_mun.id_estado = mun.id_estado
                    LEFT JOIN estado est_nac ON est_nac.id_estado = p.id_estado_nacimiento
                    LEFT JOIN (
                        SELECT id_estudiante, MAX(id_matricula) AS max_mat_cursando
                        FROM matricula
                        WHERE tipo IN ('INSCRIPCION', 'RATIFICACION')
                        GROUP BY id_estudiante
                    ) ult_cursando ON ult_cursando.id_estudiante = est.id_estudiante
                    LEFT JOIN matricula m_cursando ON m_cursando.id_matricula = ult_cursando.max_mat_cursando
                    LEFT JOIN estructura_academica ea ON ea.id_estructura_academica = m_cursando.id_estructura_academica
                    LEFT JOIN anio_escolar a ON a.id_anio_escolar = m_cursando.id_anio_escolar
                    LEFT JOIN grado g ON g.id_grado = ea.id_grado
                    LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion
                    LEFT JOIN (
                        SELECT id_estudiante, MAX(id_matricula) AS max_mat_global
                        FROM matricula
                        GROUP BY id_estudiante
                    ) ult_global ON ult_global.id_estudiante = est.id_estudiante
                    LEFT JOIN matricula m_ultimo ON m_ultimo.id_matricula = ult_global.max_mat_global
                    WHERE est.id_estudiante = :id_estudiante
                    LIMIT 1";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        // Si la persona tiene municipio de nacimiento en Carabobo pero no parroquia explícita, o viceversa, asegurar limpieza
        $row['nombre_completo']      = trim((string) ($row['nombre_completo'] ?? ''));
        $row['numero_documento']    = trim((string) ($row['numero_documento'] ?? ''));
        $row['municipio_nacimiento'] = trim((string) ($row['municipio_nacimiento'] ?? ''));
        $row['estado_nacimiento']    = trim((string) ($row['estado_nacimiento'] ?? ''));
        $row['grado']                = trim((string) ($row['grado'] ?? ''));
        $row['periodo_escolar']      = trim((string) ($row['periodo_escolar'] ?? ''));

        return $row;
    }

    /**
     * Estadísticas para el resumen de Constancias de Estudio.
     * Cuenta únicamente los estudiantes activos con matrícula en el período activo.
     */
    public function getStatsEstudio(): array
    {
        $idAnioActivo = \App\Support\AniosEscolares::idEnCurso($this->pdo);
        if ($idAnioActivo <= 0) {
            return ['total' => 0, 'activos' => 0];
        }

        $sql = "SELECT COUNT(DISTINCT est.id_estudiante) AS total
                FROM estudiante est
                INNER JOIN persona p ON p.id_persona = est.id_persona
                WHERE {$this->sqlCondicionElegibilidadEstudio()}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id_anio_activo' => $idAnioActivo]);
        $total = (int) $stmt->fetchColumn();

        return [
            'total'   => $total,
            'activos' => $total,
        ];
    }

    public function getAniosEscolares(): array
    {
        return $this->pdo->query("SELECT id_anio_escolar, nombre_periodo FROM anio_escolar ORDER BY anio_inicio DESC")
                         ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getGrados(): array
    {
        return $this->pdo->query("SELECT DISTINCT nombre FROM grado WHERE estado = 'ACTIVO' ORDER BY nombre ASC")
                         ->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getSecciones(): array
    {
        return $this->pdo->query("SELECT DISTINCT nombre FROM seccion WHERE estado = 'ACTIVO' ORDER BY nombre ASC")
                         ->fetchAll(PDO::FETCH_COLUMN);
    }

    private function buildFiltrosEstudio(array $filters, int $idAnioActivo): array
    {
        $where = [];
        $params = [
            'id_anio_activo'      => $idAnioActivo,
            'id_anio_activo_join' => $idAnioActivo,
        ];

        if (!empty($filters['q'])) {
            $termino = '%' . trim($filters['q']) . '%';
            $where[] = "(p.primer_nombre LIKE :q1 OR p.primer_apellido LIKE :q2 OR p.segundo_nombre LIKE :q3 OR p.segundo_apellido LIKE :q4 OR p.numero_documento LIKE :q5)";
            $params['q1'] = $termino;
            $params['q2'] = $termino;
            $params['q3'] = $termino;
            $params['q4'] = $termino;
            $params['q5'] = $termino;
        }

        if (!empty($filters['grado'])) {
            $where[] = 'g.nombre = :grado';
            $params['grado'] = $filters['grado'];
        }

        if (!empty($filters['seccion'])) {
            $where[] = 's.nombre = :seccion';
            $params['seccion'] = $filters['seccion'];
        }

        /* Alcance por turno. Lo inyecta el controlador con turnoSecretariaActual(),
           que devuelve null para admin y directivo: ahí no se filtra nada y ven todos.
           La secretaria sí queda acotada a su turno. Usa ea.id_turno porque la
           consulta ya une la estructura académica de la matrícula. */
        if (!empty($filters['turno'])) {
            $where[] = 'ea.id_turno = :turno';
            $params['turno'] = (int) $filters['turno'];
        }

        return [$where, $params];
    }

    private function buildFiltrosInscripcion(array $filters): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['q'])) {
            $termino = '%' . trim($filters['q']) . '%';
            $where[] = "(p.primer_nombre LIKE :q1 OR p.primer_apellido LIKE :q2 OR p.segundo_nombre LIKE :q3 OR p.segundo_apellido LIKE :q4 OR p.numero_documento LIKE :q5)";
            $params['q1'] = $termino;
            $params['q2'] = $termino;
            $params['q3'] = $termino;
            $params['q4'] = $termino;
            $params['q5'] = $termino;
        }

        if (!empty($filters['grado'])) {
            $where[] = 'g.nombre = :grado';
            $params['grado'] = $filters['grado'];
        }

        if (!empty($filters['seccion'])) {
            $where[] = 's.nombre = :seccion';
            $params['seccion'] = $filters['seccion'];
        }

        /* Sin filtro por año a propósito. La matrícula que se consulta ya está
           atada al período en curso (id_anio_activo_join), así que añadir un
           nombre de año encima solo podía hacer dos cosas: no cambiar nada, o
           vaciar la lista si el nombre no era el del año activo. */

        /* Mismo alcance por turno que en buildFiltrosEstudio(). */
        if (!empty($filters['turno'])) {
            $where[] = 'ea.id_turno = :turno';
            $params['turno'] = (int) $filters['turno'];
        }

        return [$where, $params];
    }
}
