<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * CertificatesModel
 * ---------------
 * Modelo unificado para la gestión y consulta de datos académicos requeridos en la
 * emisión de Constancias oficiales (Estudio, Inscripción / Ratificación, Prosecución y Retiro).
 *
 * Los datos se resuelven exclusivamente desde la base de datos a partir de
 * las tablas estudiante, persona, matricula, estructura_academica, grado,
 * seccion, anio_escolar, parroquia, municipio, estado, retiro y resultado_academico.
 */
class CertificatesModel extends BaseModel
{
    protected string $table = 'estudiante';
    protected string $primaryKey = 'id_estudiante';

    protected array $columns = ['id_estudiante', 'id_persona'];
    protected array $allowedColumns = ['id_estudiante', 'id_persona'];

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
                  AND m_act.tipo IN ('INSCRIPCION', 'RATIFICACION', 'REINGRESO')
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
     * Fragmento SQL: el estudiante tiene al menos un retiro con estado APROBADO.
     * Se usa para la Constancia de Prosecución, que solo aplica a estudiantes
     * retirados y promovidos.
     */
    private function sqlRetiroAprobadoEstudiante(string $aliasEstudiante = 'est'): string
    {
        return "EXISTS (
            SELECT 1
            FROM retiro r
            INNER JOIN matricula m_ret ON m_ret.id_matricula = r.id_matricula
            WHERE m_ret.id_estudiante = {$aliasEstudiante}.id_estudiante
              AND r.estado_solicitud = 'APROBADO'
        )";
    }

    /**
     * Fragmento SQL: la matrícula del retiro es el último movimiento del estudiante.
     * Si existe una matrícula posterior (por ejemplo, un REINGRESO), el retiro ya
     * no representa el estado actual del estudiante.
     */
    private function sqlRetiroEsUltimoMovimiento(string $aliasMatricula = 'm', string $aliasAnio = 'a'): string
    {
        return "NOT EXISTS (
            SELECT 1
            FROM matricula m_post
            INNER JOIN anio_escolar a_post ON a_post.id_anio_escolar = m_post.id_anio_escolar
            WHERE m_post.id_estudiante = {$aliasMatricula}.id_estudiante
              AND (
                  a_post.anio_inicio > {$aliasAnio}.anio_inicio
                  OR (a_post.anio_inicio = {$aliasAnio}.anio_inicio AND m_post.id_matricula > {$aliasMatricula}.id_matricula)
              )
        )";
    }

    /**
     * Valida de forma única y centralizada si un estudiante es elegible para Constancia de Estudio:
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
     * Retorna el ID del año escolar aplicable a prosecución:
     * Opera exclusivamente sobre el año escolar ACTIVO en curso.
     * Si el año escolar ya se cerró, el proceso está cerrado y retorna 0.
     */
    public function getIdAnioProsecucion(): int
    {
        return \App\Support\AniosEscolares::idEnCurso($this->pdo);
    }

    /**
     * Valida si un registro de inscripción es elegible:
     * El estudiante tiene matrícula INSCRIPCION, RATIFICACION o REINGRESO en el año escolar activo.
     */
    public function esElegibleParaInscripcion(int $idEstudiante, ?int $idMatricula = null): bool
    {
        if ($idEstudiante <= 0) {
            return false;
        }

        $idAnioActivo = \App\Support\AniosEscolares::idEnCurso($this->pdo);
        if ($idAnioActivo <= 0) {
            return false;
        }

        if ($idMatricula !== null && $idMatricula > 0) {
            $stmt = $this->pdo->prepare(
                "SELECT 1 
                   FROM matricula 
                  WHERE id_matricula = :id_matricula 
                    AND id_estudiante = :id_estudiante 
                    AND id_anio_escolar = :id_anio_activo
                    AND tipo IN ('INSCRIPCION', 'RATIFICACION', 'REINGRESO') 
                  LIMIT 1"
            );
            $stmt->execute([
                'id_matricula'   => $idMatricula, 
                'id_estudiante'  => $idEstudiante,
                'id_anio_activo' => $idAnioActivo,
            ]);
            return (bool) $stmt->fetchColumn();
        }

        $stmt = $this->pdo->prepare(
            "SELECT 1 
               FROM matricula 
              WHERE id_estudiante = :id_estudiante 
                AND id_anio_escolar = :id_anio_activo
                AND tipo IN ('INSCRIPCION', 'RATIFICACION', 'REINGRESO') 
              LIMIT 1"
        );
        $stmt->execute([
            'id_estudiante'  => $idEstudiante,
            'id_anio_activo' => $idAnioActivo,
        ]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Valida si un registro de retiro es elegible:
     * Existe en tabla retiro con estado_solicitud = 'APROBADO' en el año escolar activo.
     */
    public function esElegibleParaRetiro(int $idRetiro): bool
    {
        if ($idRetiro <= 0) {
            return false;
        }

        $idAnioActivo = \App\Support\AniosEscolares::idEnCurso($this->pdo);
        if ($idAnioActivo <= 0) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            "SELECT 1 
               FROM retiro r
              INNER JOIN matricula m ON m.id_matricula = r.id_matricula
              INNER JOIN anio_escolar a ON a.id_anio_escolar = m.id_anio_escolar
              WHERE r.id_retiro = :id_retiro 
                AND r.estado_solicitud = 'APROBADO'
                AND m.id_anio_escolar = :id_anio_activo
                AND {$this->sqlRetiroEsUltimoMovimiento()}
              LIMIT 1"
        );
        $stmt->execute([
            'id_retiro'      => $idRetiro,
            'id_anio_activo' => $idAnioActivo,
        ]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Valida si un registro de prosecución es elegible:
     * Existe en resultado_academico con estado_resultado = 'PROMOVIDO',
     * grado entre 1 y 5, pertenece al año escolar aplicable a prosecución y el estudiante
     * tiene al menos un retiro con estado APROBADO.
     */
    public function esElegibleParaProsecucion(int $idResultado): bool
    {
        if ($idResultado <= 0) {
            return false;
        }

        $idAnioPros = $this->getIdAnioProsecucion();
        if ($idAnioPros <= 0) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            "SELECT 1 
               FROM resultado_academico ra
              INNER JOIN estructura_academica ea ON ea.id_estructura_academica = ra.id_estructura_academica
              INNER JOIN grado g ON g.id_grado = ea.id_grado
              WHERE ra.id_resultado = :id_resultado
                AND ra.id_anio_escolar = :id_anio_prosecucion
                AND ra.estado_resultado = 'PROMOVIDO'
                AND CAST(REGEXP_SUBSTR(g.nombre, '[0-9]+') AS UNSIGNED) BETWEEN 1 AND 5
                AND {$this->sqlRetiroAprobadoEstudiante('ra')}
              LIMIT 1"
        );
        $stmt->execute([
            'id_resultado'        => $idResultado,
            'id_anio_prosecucion' => $idAnioPros,
        ]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Valida si un estudiante o registro de egreso es elegible para el Certificado de Educación Primaria:
     * Opera exclusivamente mientras el año escolar está ACTIVO.
     * El estudiante cursa 6to grado en el año escolar activo y tiene literal aprobatorio (A, B, C o D)
     * cargado por el docente o un registro formal en la tabla egreso.
     */
    public function esElegibleParaEgreso(int $idEgreso, ?int $idEstudiante = null): bool
    {
        if ($idEgreso <= 0 && ($idEstudiante === null || $idEstudiante <= 0)) {
            return false;
        }

        $idAnioActivo = \App\Support\AniosEscolares::idEnCurso($this->pdo);
        if ($idAnioActivo <= 0) {
            return false;
        }

        if ($idEstudiante !== null && $idEstudiante > 0) {
            $whereCond = 'est.id_estudiante = :id_estudiante';
            $params = [
                'id_anio_activo' => $idAnioActivo,
                'id_estudiante'  => $idEstudiante,
            ];
        } else {
            $whereCond = '(est.id_estudiante = :id_est_match OR eg.id_egreso = :id_eg_match)';
            $params = [
                'id_anio_activo' => $idAnioActivo,
                'id_est_match'   => $idEgreso,
                'id_eg_match'    => $idEgreso,
            ];
        }

        $stmt = $this->pdo->prepare(
            "SELECT 1 
               FROM matricula m
              INNER JOIN estudiante est ON est.id_estudiante = m.id_estudiante
              INNER JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
              INNER JOIN grado g ON g.id_grado = ea.id_grado
               LEFT JOIN egreso eg ON eg.id_matricula = m.id_matricula
               LEFT JOIN resultado_academico ra ON ra.id_estudiante = est.id_estudiante
                                               AND ra.id_anio_escolar = m.id_anio_escolar
              WHERE {$whereCond}
                AND m.id_anio_escolar = :id_anio_activo
                AND m.tipo IN ('INSCRIPCION', 'RATIFICACION', 'REINGRESO', 'EGRESO')
                AND CAST(REGEXP_SUBSTR(g.nombre, '[0-9]+') AS UNSIGNED) = 6
                AND (
                    eg.id_egreso IS NOT NULL
                    OR (ra.literal IN ('A', 'B', 'C', 'D') AND COALESCE(ra.estado_resultado, 'PENDIENTE') != 'NO_PROMOVIDO')
                    OR ra.estado_resultado = 'PROMOVIDO'
                )
              LIMIT 1"
        );
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * MÉTODO UNIFICADO DE LISTADO:
     * Retorna las filas para el listado según el tipo seleccionado y sus condiciones de elegibilidad.
     *
     * @param string $tipo 'estudio' | 'inscripcion' | 'prosecucion' | 'retiro' | 'egreso'
     * @param array $filters
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function getListado(string $tipo, array $filters = [], int $page = 1, int $perPage = 10): array
    {
        return match ($tipo) {
            'inscripcion' => $this->getEstudiantesInscripcion($filters, $page, $perPage),
            'prosecucion' => $this->getEstudiantesProsecucion($filters, $page, $perPage),
            'retiro'      => $this->getEstudiantesRetiro($filters, $page, $perPage),
            'egreso'      => $this->getEstudiantesEgreso($filters, $page, $perPage),
            default       => $this->getEstudiantesEstudio($filters, $page, $perPage),
        };
    }

    /**
     * MÉTODO UNIFICADO DE CONTEO:
     * Cuenta el total de filas según el tipo seleccionado.
     */
    public function countListado(string $tipo, array $filters = []): int
    {
        return match ($tipo) {
            'inscripcion' => $this->countEstudiantesInscripcion($filters),
            'prosecucion' => $this->countEstudiantesProsecucion($filters),
            'retiro'      => $this->countEstudiantesRetiro($filters),
            'egreso'      => $this->countEstudiantesEgreso($filters),
            default       => $this->countEstudiantesEstudio($filters),
        };
    }

    /**
     * Listado para Constancia de Estudio (estudiantes activos en el año activo).
     */
    public function getEstudiantesEstudio(array $filters = [], int $page = 1, int $perPage = 10): array
    {
        $idAnioActivo = \App\Support\AniosEscolares::idEnCurso($this->pdo);
        if ($idAnioActivo <= 0) {
            return [];
        }

        [$where, $params] = $this->buildFiltrosEstudio($filters, $idAnioActivo);

        $sql = "SELECT est.id_estudiante,
                       est.id_estudiante AS id_registro,
                       CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS nombre_completo,
                       CONCAT_WS(' ', p.primer_nombre, p.primer_apellido) AS nombre_corto,
                       p.tipo_documento,
                       p.numero_documento,
                       p.sexo,
                       p.fecha_nacimiento,
                       p.lugar_nacimiento_extranjero,
                       p.estado AS estado_persona,
                       par.nombre AS parroquia_nacimiento,
                       mun.nombre AS municipio_nacimiento,
                       COALESCE(est_nac.nombre, est_mun.nombre) AS estado_nacimiento,
                       g.nombre AS grado,
                       s.nombre AS seccion,
                       a.nombre_periodo AS anio_escolar,
                       m.id_matricula,
                       m.tipo AS ultimo_tipo_matricula,
                       'activo' AS estado_academico
                FROM estudiante est
                INNER JOIN persona p ON p.id_persona = est.id_persona
                INNER JOIN matricula m ON m.id_matricula = (
                    SELECT m2.id_matricula
                    FROM matricula m2
                    WHERE m2.id_estudiante = est.id_estudiante
                      AND m2.id_anio_escolar = :id_anio_activo_join
                      AND m2.tipo IN ('INSCRIPCION', 'RATIFICACION', 'REINGRESO')
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
            $f['id_registro']   = (int) $f['id_estudiante'];
        }

        return $filas;
    }

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
                      AND m2.tipo IN ('INSCRIPCION', 'RATIFICACION', 'REINGRESO')
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
     * Listado para Constancia de Inscripción / Ratificación.
     */
    public function getEstudiantesInscripcion(array $filters = [], int $page = 1, int $perPage = 10): array
    {
        if (\App\Support\AniosEscolares::idEnCurso($this->pdo) <= 0) {
            return [];
        }

        [$where, $params] = $this->buildFiltrosInscripcion($filters);

        $sql = "SELECT est.id_estudiante,
                       m.id_matricula,
                       est.id_estudiante AS id_registro,
                       CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS nombre_completo,
                       CONCAT_WS(' ', p.primer_nombre, p.primer_apellido) AS nombre_corto,
                       p.tipo_documento,
                       p.numero_documento,
                       p.sexo,
                       p.fecha_nacimiento,
                       p.lugar_nacimiento_extranjero,
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
                FROM estudiante est
                INNER JOIN persona p ON p.id_persona = est.id_persona
                INNER JOIN matricula m ON m.id_matricula = (
                    SELECT m2.id_matricula
                    FROM matricula m2
                    WHERE m2.id_estudiante = est.id_estudiante
                      AND m2.id_anio_escolar = :id_anio_activo_join
                      AND m2.tipo IN ('INSCRIPCION', 'RATIFICACION', 'REINGRESO')
                    ORDER BY m2.fecha_matricula DESC, m2.id_matricula DESC
                    LIMIT 1
                )
                LEFT JOIN parroquia par ON par.id_parroquia = p.id_parroquia_nacimiento
                LEFT JOIN municipio mun ON mun.id_municipio = par.id_municipio
                LEFT JOIN estado est_mun ON est_mun.id_estado = mun.id_estado
                LEFT JOIN estado est_nac ON est_nac.id_estado = p.id_estado_nacimiento
                INNER JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
                INNER JOIN anio_escolar a ON a.id_anio_escolar = m.id_anio_escolar
                INNER JOIN grado g ON g.id_grado = ea.id_grado
                LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion
                WHERE m.tipo IN ('INSCRIPCION', 'RATIFICACION', 'REINGRESO')";

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
            $f['id_registro']   = (int) $f['id_estudiante'];
        }

        return $filas;
    }

    public function countEstudiantesInscripcion(array $filters = []): int
    {
        if (\App\Support\AniosEscolares::idEnCurso($this->pdo) <= 0) {
            return 0;
        }

        [$where, $params] = $this->buildFiltrosInscripcion($filters);

        $sql = "SELECT COUNT(*)
                FROM estudiante est
                INNER JOIN persona p ON p.id_persona = est.id_persona
                INNER JOIN matricula m ON m.id_matricula = (
                    SELECT m2.id_matricula
                    FROM matricula m2
                    WHERE m2.id_estudiante = est.id_estudiante
                      AND m2.id_anio_escolar = :id_anio_activo_join
                      AND m2.tipo IN ('INSCRIPCION', 'RATIFICACION', 'REINGRESO')
                    ORDER BY m2.fecha_matricula DESC, m2.id_matricula DESC
                    LIMIT 1
                )
                INNER JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
                INNER JOIN anio_escolar a ON a.id_anio_escolar = m.id_anio_escolar
                INNER JOIN grado g ON g.id_grado = ea.id_grado
                LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion
                WHERE m.tipo IN ('INSCRIPCION', 'RATIFICACION', 'REINGRESO')";

        if (!empty($where)) {
            $sql .= ' AND ' . implode(' AND ', $where);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Listado para Constancias de Retiro (retiros con estado APROBADO).
     */
    public function getEstudiantesRetiro(array $filters = [], int $page = 1, int $perPage = 10): array
    {
        [$where, $params] = $this->buildFiltrosRetiro($filters);

        $sql = "SELECT r.id_retiro,
                       r.id_retiro AS id_registro,
                       r.fecha_retiro,
                       r.fecha_resolucion,
                       r.observaciones,
                       mr.nombre AS motivo,
                       est.id_estudiante,
                       m.id_matricula,
                       CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS nombre_completo,
                       CONCAT_WS(' ', p.primer_nombre, p.primer_apellido) AS nombre_corto,
                       p.tipo_documento,
                       p.numero_documento,
                       p.sexo,
                       p.fecha_nacimiento,
                       p.lugar_nacimiento_extranjero,
                       par.nombre AS parroquia_nacimiento,
                       mun.nombre AS municipio_nacimiento,
                       COALESCE(est_nac.nombre, est_mun.nombre) AS estado_nacimiento,
                       g.nombre AS grado,
                       s.nombre AS seccion,
                       a.id_anio_escolar,
                       a.nombre_periodo AS anio_escolar,
                       CONCAT_WS(' ', prep.primer_nombre, prep.primer_apellido) AS representante_nombre,
                       CONCAT(prep.tipo_documento, '-', prep.numero_documento) AS representante_cedula
                FROM retiro r
                INNER JOIN matricula m ON m.id_matricula = r.id_matricula
                INNER JOIN estudiante est ON est.id_estudiante = m.id_estudiante
                INNER JOIN persona p ON p.id_persona = est.id_persona
                LEFT JOIN persona prep ON prep.id_persona = r.id_persona_representante
                LEFT JOIN parroquia par ON par.id_parroquia = p.id_parroquia_nacimiento
                LEFT JOIN municipio mun ON mun.id_municipio = par.id_municipio
                LEFT JOIN estado est_mun ON est_mun.id_estado = mun.id_estado
                LEFT JOIN estado est_nac ON est_nac.id_estado = p.id_estado_nacimiento
                INNER JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
                INNER JOIN anio_escolar a ON a.id_anio_escolar = m.id_anio_escolar
                INNER JOIN grado g ON g.id_grado = ea.id_grado
                LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion
                INNER JOIN motivo_retiro mr ON mr.id_motivo_retiro = r.id_motivo_retiro
                WHERE r.estado_solicitud = 'APROBADO'
                  AND {$this->sqlRetiroEsUltimoMovimiento()}";

        if (!empty($where)) {
            $sql .= ' AND ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY COALESCE(r.fecha_retiro, r.fecha_resolucion, r.fecha_solicitud) DESC, r.id_retiro DESC LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->execute();

        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($filas as &$f) {
            $f['id_registro']   = (int) $f['id_retiro'];
            $f['id_estudiante'] = (int) $f['id_estudiante'];
        }

        return $filas;
    }

    public function countEstudiantesRetiro(array $filters = []): int
    {
        [$where, $params] = $this->buildFiltrosRetiro($filters);

        $sql = "SELECT COUNT(*)
                FROM retiro r
                INNER JOIN matricula m ON m.id_matricula = r.id_matricula
                INNER JOIN estudiante est ON est.id_estudiante = m.id_estudiante
                INNER JOIN persona p ON p.id_persona = est.id_persona
                INNER JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
                INNER JOIN anio_escolar a ON a.id_anio_escolar = m.id_anio_escolar
                INNER JOIN grado g ON g.id_grado = ea.id_grado
                LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion
                INNER JOIN motivo_retiro mr ON mr.id_motivo_retiro = r.id_motivo_retiro
                WHERE r.estado_solicitud = 'APROBADO'
                  AND {$this->sqlRetiroEsUltimoMovimiento()}";

        if (!empty($where)) {
            $sql .= ' AND ' . implode(' AND ', $where);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Listado para Constancias de Prosecución (promovido, grado 1 a 5 y retiro APROBADO).
     */
    public function getEstudiantesProsecucion(array $filters = [], int $page = 1, int $perPage = 10): array
    {
        if ($this->getIdAnioProsecucion() <= 0) {
            return [];
        }

        [$where, $params] = $this->buildFiltrosProsecucion($filters);

        $sql = "SELECT ra.id_resultado,
                       ra.id_resultado AS id_registro,
                       ra.literal,
                       ra.estado_resultado,
                       COALESCE(ra.fecha_actualizacion, ra.fecha_propuesta, ra.fecha_creacion) AS fecha_resultado,
                       est.id_estudiante,
                       m.id_matricula,
                       CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS nombre_completo,
                       CONCAT_WS(' ', p.primer_nombre, p.primer_apellido) AS nombre_corto,
                       p.tipo_documento,
                       p.numero_documento,
                       p.sexo,
                       p.fecha_nacimiento,
                       p.lugar_nacimiento_extranjero,
                       p.estado AS estado_persona,
                       par.nombre AS parroquia_nacimiento,
                       mun.nombre AS municipio_nacimiento,
                       COALESCE(est_nac.nombre, est_mun.nombre) AS estado_nacimiento,
                       g.id_grado AS id_grado_origen,
                       g.nombre AS grado_origen,
                       g.nombre AS grado,
                       g_dest.id_grado AS id_grado_destino,
                       g_dest.nombre AS grado_destino,
                       s.nombre AS seccion,
                       a.id_anio_escolar,
                       a.nombre_periodo AS anio_escolar
                FROM resultado_academico ra
                INNER JOIN estudiante est ON est.id_estudiante = ra.id_estudiante
                INNER JOIN persona p ON p.id_persona = est.id_persona
                INNER JOIN matricula m ON m.id_matricula = ra.id_matricula
                LEFT JOIN parroquia par ON par.id_parroquia = p.id_parroquia_nacimiento
                LEFT JOIN municipio mun ON mun.id_municipio = par.id_municipio
                LEFT JOIN estado est_mun ON est_mun.id_estado = mun.id_estado
                LEFT JOIN estado est_nac ON est_nac.id_estado = p.id_estado_nacimiento
                INNER JOIN estructura_academica ea ON ea.id_estructura_academica = ra.id_estructura_academica
                INNER JOIN anio_escolar a ON a.id_anio_escolar = ra.id_anio_escolar
                INNER JOIN grado g ON g.id_grado = ea.id_grado
                LEFT JOIN grado g_dest ON g_dest.id_grado = ra.id_grado_destino
                LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion
                WHERE ra.estado_resultado = 'PROMOVIDO'
                  AND CAST(REGEXP_SUBSTR(g.nombre, '[0-9]+') AS UNSIGNED) BETWEEN 1 AND 5
                  AND {$this->sqlRetiroAprobadoEstudiante('est')}";

        if (!empty($where)) {
            $sql .= ' AND ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY a.anio_inicio DESC, g.nombre ASC, p.primer_nombre, p.primer_apellido LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->execute();

        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($filas as &$f) {
            $f['id_registro']   = (int) $f['id_resultado'];
            $f['id_estudiante'] = (int) $f['id_estudiante'];
        }

        return $filas;
    }

    public function countEstudiantesProsecucion(array $filters = []): int
    {
        if ($this->getIdAnioProsecucion() <= 0) {
            return 0;
        }

        [$where, $params] = $this->buildFiltrosProsecucion($filters);

        $sql = "SELECT COUNT(*)
                FROM resultado_academico ra
                INNER JOIN estudiante est ON est.id_estudiante = ra.id_estudiante
                INNER JOIN persona p ON p.id_persona = est.id_persona
                INNER JOIN estructura_academica ea ON ea.id_estructura_academica = ra.id_estructura_academica
                INNER JOIN anio_escolar a ON a.id_anio_escolar = ra.id_anio_escolar
                INNER JOIN grado g ON g.id_grado = ea.id_grado
                LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion
                WHERE ra.estado_resultado = 'PROMOVIDO'
                  AND CAST(REGEXP_SUBSTR(g.nombre, '[0-9]+') AS UNSIGNED) BETWEEN 1 AND 5
                  AND {$this->sqlRetiroAprobadoEstudiante('est')}";

        if (!empty($where)) {
            $sql .= ' AND ' . implode(' AND ', $where);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Obtiene los datos completos y verificados de un estudiante para emitir su constancia de Estudio o Inscripción.
     */
    public function getEstudianteParaConstancia(int $idEstudiante, ?int $idMatricula = null): ?array
    {
        $params = ['id_estudiante' => $idEstudiante];

        if ($idMatricula !== null && $idMatricula > 0) {
            $sql = "SELECT est.id_estudiante,
                           m.id_matricula,
                           CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS nombre_completo,
                           p.tipo_documento,
                           p.numero_documento,
                           p.fecha_nacimiento,
                           p.lugar_nacimiento_extranjero,
                       p.lugar_nacimiento_extranjero,
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
            $sql = "SELECT est.id_estudiante,
                           CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS nombre_completo,
                           p.tipo_documento,
                           p.numero_documento,
                           p.fecha_nacimiento,
                           p.lugar_nacimiento_extranjero,
                       p.lugar_nacimiento_extranjero,
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
                        WHERE tipo IN ('INSCRIPCION', 'RATIFICACION', 'REINGRESO')
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
        return $this->pdo->query("SELECT id_anio_escolar, nombre_periodo, estado FROM anio_escolar ORDER BY anio_inicio DESC")
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

        // Inscripción / Ratificación: SOLO año escolar activo
        $idAnioActivo = \App\Support\AniosEscolares::idEnCurso($this->pdo);
        if ($idAnioActivo > 0) {
            $where[] = 'm.id_anio_escolar = :id_anio_activo';
            $params['id_anio_activo'] = $idAnioActivo;
            $params['id_anio_activo_join'] = $idAnioActivo;
        } else {
            $where[] = "a.estado = 'ACTIVO'";
        }

        if (!empty($filters['turno'])) {
            $where[] = 'ea.id_turno = :turno';
            $params['turno'] = (int) $filters['turno'];
        }

        return [$where, $params];
    }

    private function buildFiltrosRetiro(array $filters): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['q'])) {
            $termino = '%' . trim($filters['q']) . '%';
            $where[] = "(p.primer_nombre LIKE :q1 OR p.primer_apellido LIKE :q2 OR p.numero_documento LIKE :q3)";
            $params['q1'] = $termino;
            $params['q2'] = $termino;
            $params['q3'] = $termino;
        }

        if (!empty($filters['grado'])) {
            $where[] = 'g.nombre = :grado';
            $params['grado'] = $filters['grado'];
        }

        if (!empty($filters['seccion'])) {
            $where[] = 's.nombre = :seccion';
            $params['seccion'] = $filters['seccion'];
        }

        // Retiro: SOLO año escolar activo
        $idAnioActivo = \App\Support\AniosEscolares::idEnCurso($this->pdo);
        if ($idAnioActivo > 0) {
            $where[] = 'm.id_anio_escolar = :id_anio_activo';
            $params['id_anio_activo'] = $idAnioActivo;
        } else {
            $where[] = "a.estado = 'ACTIVO'";
        }

        if (!empty($filters['turno'])) {
            $where[] = 'ea.id_turno = :turno';
            $params['turno'] = (int) $filters['turno'];
        }

        return [$where, $params];
    }

    public function getDatosConstanciaRetiro(int $idRetiro): ?array
    {
        if ($idRetiro <= 0) {
            return null;
        }

        $sql = "SELECT r.id_retiro,
                       r.fecha_retiro,
                       r.fecha_resolucion,
                       r.fecha_solicitud,
                       mr.nombre AS motivo,
                       est.id_estudiante,
                       m.id_matricula,
                       CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS nombre_completo,
                       p.tipo_documento,
                       p.numero_documento,
                       p.sexo,
                       p.fecha_nacimiento,
                       p.lugar_nacimiento_extranjero,
                       par.nombre AS parroquia_nacimiento,
                       mun.nombre AS municipio_nacimiento,
                       COALESCE(est_nac.nombre, est_mun.nombre) AS estado_nacimiento,
                       g.nombre AS grado,
                       s.nombre AS seccion,
                       a.id_anio_escolar,
                       a.nombre_periodo AS periodo_escolar,
                       prep.id_persona AS id_representante,
                       CONCAT_WS(' ', prep.primer_nombre, prep.segundo_nombre, prep.primer_apellido, prep.segundo_apellido) AS representante_nombre,
                       prep.tipo_documento AS representante_tipo_doc,
                       prep.numero_documento AS representante_num_doc
                FROM retiro r
                INNER JOIN matricula m ON m.id_matricula = r.id_matricula
                INNER JOIN estudiante est ON est.id_estudiante = m.id_estudiante
                INNER JOIN persona p ON p.id_persona = est.id_persona
                LEFT JOIN persona prep ON prep.id_persona = r.id_persona_representante
                LEFT JOIN parroquia par ON par.id_parroquia = p.id_parroquia_nacimiento
                LEFT JOIN municipio mun ON mun.id_municipio = par.id_municipio
                LEFT JOIN estado est_mun ON est_mun.id_estado = mun.id_estado
                LEFT JOIN estado est_nac ON est_nac.id_estado = p.id_estado_nacimiento
                INNER JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
                INNER JOIN anio_escolar a ON a.id_anio_escolar = m.id_anio_escolar
                INNER JOIN grado g ON g.id_grado = ea.id_grado
                LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion
                INNER JOIN motivo_retiro mr ON mr.id_motivo_retiro = r.id_motivo_retiro
                WHERE r.id_retiro = :id_retiro
                  AND r.estado_solicitud = 'APROBADO'
                  AND {$this->sqlRetiroEsUltimoMovimiento()}
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id_retiro' => $idRetiro]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        // Si por alguna razón histórica no tiene id_persona_representante, buscar el representante legal
        if (empty($row['id_representante'])) {
            $stmtRep = $this->pdo->prepare(
                "SELECT p.id_persona,
                        CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS nombre,
                        p.tipo_documento,
                        p.numero_documento
                   FROM persona_estudiante pe
                   INNER JOIN persona p ON p.id_persona = pe.id_persona
                  WHERE pe.id_estudiante = :id_estudiante
                    AND pe.es_representante_legal = 1
                    AND pe.estado_relacion = 'ACTIVO'
                    AND p.es_fallecido = 0
                  LIMIT 1"
            );
            $stmtRep->execute(['id_estudiante' => (int) $row['id_estudiante']]);
            $rep = $stmtRep->fetch(PDO::FETCH_ASSOC);
            if ($rep) {
                $row['representante_nombre'] = $rep['nombre'];
                $row['representante_tipo_doc'] = $rep['tipo_documento'];
                $row['representante_num_doc'] = $rep['numero_documento'];
            }
        }

        return $row;
    }

    private function buildFiltrosProsecucion(array $filters): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['q'])) {
            $termino = '%' . trim($filters['q']) . '%';
            $where[] = "(p.primer_nombre LIKE :q1 OR p.primer_apellido LIKE :q2 OR p.numero_documento LIKE :q3)";
            $params['q1'] = $termino;
            $params['q2'] = $termino;
            $params['q3'] = $termino;
        }

        if (!empty($filters['grado'])) {
            $where[] = 'g.nombre = :grado';
            $params['grado'] = $filters['grado'];
        }

        if (!empty($filters['seccion'])) {
            $where[] = 's.nombre = :seccion';
            $params['seccion'] = $filters['seccion'];
        }

        // Prosecución: SOLO el año escolar activo en curso
        $idAnioPros = $this->getIdAnioProsecucion();
        if ($idAnioPros > 0) {
            $where[] = 'ra.id_anio_escolar = :id_anio_prosecucion';
            $params['id_anio_prosecucion'] = $idAnioPros;
        }

        if (!empty($filters['turno'])) {
            $where[] = 'ea.id_turno = :turno';
            $params['turno'] = (int) $filters['turno'];
        }

        return [$where, $params];
    }

    public function getDatosConstanciaProsecucion(int $idResultado): ?array
    {
        if ($idResultado <= 0) {
            return null;
        }

        $sql = "SELECT ra.id_resultado,
                       ra.literal,
                       ra.estado_resultado,
                       COALESCE(ra.fecha_actualizacion, ra.fecha_propuesta, ra.fecha_creacion) AS fecha_resultado,
                       est.id_estudiante,
                       m.id_matricula,
                       CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS nombre_completo,
                       p.tipo_documento,
                       p.numero_documento,
                       p.sexo,
                       p.fecha_nacimiento,
                       p.lugar_nacimiento_extranjero,
                       par.nombre AS parroquia_nacimiento,
                       mun.nombre AS municipio_nacimiento,
                       COALESCE(est_nac.nombre, est_mun.nombre) AS estado_nacimiento,
                       g.id_grado AS id_grado_origen,
                       g.nombre AS grado_origen,
                       g_dest.id_grado AS id_grado_destino,
                       g_dest.nombre AS grado_destino,
                       s.nombre AS seccion,
                       a.id_anio_escolar,
                       a.nombre_periodo AS periodo_escolar
                FROM resultado_academico ra
                INNER JOIN estudiante est ON est.id_estudiante = ra.id_estudiante
                INNER JOIN persona p ON p.id_persona = est.id_persona
                INNER JOIN matricula m ON m.id_matricula = ra.id_matricula
                LEFT JOIN parroquia par ON par.id_parroquia = p.id_parroquia_nacimiento
                LEFT JOIN municipio mun ON mun.id_municipio = par.id_municipio
                LEFT JOIN estado est_mun ON est_mun.id_estado = mun.id_estado
                LEFT JOIN estado est_nac ON est_nac.id_estado = p.id_estado_nacimiento
                INNER JOIN estructura_academica ea ON ea.id_estructura_academica = ra.id_estructura_academica
                INNER JOIN anio_escolar a ON a.id_anio_escolar = ra.id_anio_escolar
                INNER JOIN grado g ON g.id_grado = ea.id_grado
                LEFT JOIN grado g_dest ON g_dest.id_grado = ra.id_grado_destino
                LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion
                WHERE ra.id_resultado = :id_resultado
                  AND ra.estado_resultado = 'PROMOVIDO'
                  AND CAST(REGEXP_SUBSTR(g.nombre, '[0-9]+') AS UNSIGNED) BETWEEN 1 AND 5
                  AND {$this->sqlRetiroAprobadoEstudiante('est')}
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id_resultado' => $idResultado]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $row['nombre_completo']      = trim((string) ($row['nombre_completo'] ?? ''));
        $row['numero_documento']    = trim((string) ($row['numero_documento'] ?? ''));
        $row['municipio_nacimiento'] = trim((string) ($row['municipio_nacimiento'] ?? ''));
        $row['estado_nacimiento']    = trim((string) ($row['estado_nacimiento'] ?? ''));
        $row['grado_origen']         = trim((string) ($row['grado_origen'] ?? ''));
        $row['grado_destino']        = trim((string) ($row['grado_destino'] ?? ''));
        $row['periodo_escolar']      = trim((string) ($row['periodo_escolar'] ?? ''));

        return $row;
    }

    /**
     * Listado para Certificado de Educación Primaria / Egreso:
     * Reconoce automáticamente a todos los estudiantes de 6to grado en el año escolar activo
     * que tengan literal aprobatorio (A, B, C o D) cargado por el docente o que ya posean
     * un registro en la tabla egreso.
     */
    public function getEstudiantesEgreso(array $filters = [], int $page = 1, int $perPage = 10): array
    {
        $idAnioActivo = \App\Support\AniosEscolares::idEnCurso($this->pdo);
        if ($idAnioActivo <= 0) {
            return [];
        }

        [$where, $params] = $this->buildFiltrosEgreso($filters, $idAnioActivo);

        $sql = "SELECT MAX(eg.id_egreso) AS id_egreso,
                       est.id_estudiante AS id_registro,
                       MAX(eg.fecha_egreso) AS fecha_egreso,
                       MAX(ra.literal) AS literal,
                       MAX(ra.estado_resultado) AS estado_resultado,
                       est.id_estudiante,
                       MAX(m.id_matricula) AS id_matricula,
                       CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS nombre_completo,
                       CONCAT_WS(' ', p.primer_nombre, p.primer_apellido) AS nombre_corto,
                       p.tipo_documento,
                       p.numero_documento,
                       p.sexo,
                       p.fecha_nacimiento,
                       p.lugar_nacimiento_extranjero,
                       p.estado AS estado_persona,
                       par.nombre AS parroquia_nacimiento,
                       mun.nombre AS municipio_nacimiento,
                       COALESCE(est_nac.nombre, est_mun.nombre) AS estado_nacimiento,
                       g.id_grado,
                       g.nombre AS grado,
                       s.nombre AS seccion,
                       a.id_anio_escolar,
                       a.nombre_periodo AS anio_escolar,
                       a.nombre_periodo AS periodo_escolar
                FROM matricula m
                INNER JOIN estudiante est ON est.id_estudiante = m.id_estudiante
                INNER JOIN persona p ON p.id_persona = est.id_persona
                INNER JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
                INNER JOIN anio_escolar a ON a.id_anio_escolar = m.id_anio_escolar
                INNER JOIN grado g ON g.id_grado = ea.id_grado
                LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion
                LEFT JOIN parroquia par ON par.id_parroquia = p.id_parroquia_nacimiento
                LEFT JOIN municipio mun ON mun.id_municipio = par.id_municipio
                LEFT JOIN estado est_mun ON est_mun.id_estado = mun.id_estado
                LEFT JOIN estado est_nac ON est_nac.id_estado = p.id_estado_nacimiento
                LEFT JOIN egreso eg ON eg.id_matricula = m.id_matricula
                LEFT JOIN resultado_academico ra ON ra.id_estudiante = est.id_estudiante
                                                AND ra.id_anio_escolar = m.id_anio_escolar
                WHERE m.id_anio_escolar = :id_anio_activo
                  AND m.tipo IN ('INSCRIPCION', 'RATIFICACION', 'REINGRESO', 'EGRESO')
                  AND CAST(REGEXP_SUBSTR(g.nombre, '[0-9]+') AS UNSIGNED) = 6
                  AND (
                      eg.id_egreso IS NOT NULL
                      OR (ra.literal IN ('A', 'B', 'C', 'D') AND COALESCE(ra.estado_resultado, 'PENDIENTE') != 'NO_PROMOVIDO')
                      OR ra.estado_resultado = 'PROMOVIDO'
                  )";

        if (!empty($where)) {
            $sql .= ' AND ' . implode(' AND ', $where);
        }

        $sql .= ' GROUP BY est.id_estudiante, p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido,
                           p.tipo_documento, p.numero_documento, p.sexo, p.fecha_nacimiento, p.lugar_nacimiento_extranjero, p.estado,
                           par.nombre, mun.nombre, est_nac.nombre, est_mun.nombre,
                           g.id_grado, g.nombre, s.nombre, a.id_anio_escolar, a.nombre_periodo
                  ORDER BY s.nombre ASC, p.primer_apellido ASC, p.primer_nombre ASC
                  LIMIT :limit OFFSET :offset';

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
            $f['id_registro']   = (int) $f['id_estudiante'];
        }

        return $filas;
    }

    public function countEstudiantesEgreso(array $filters = []): int
    {
        $idAnioActivo = \App\Support\AniosEscolares::idEnCurso($this->pdo);
        if ($idAnioActivo <= 0) {
            return 0;
        }

        [$where, $params] = $this->buildFiltrosEgreso($filters, $idAnioActivo);

        $sql = "SELECT COUNT(DISTINCT est.id_estudiante)
                FROM matricula m
                INNER JOIN estudiante est ON est.id_estudiante = m.id_estudiante
                INNER JOIN persona p ON p.id_persona = est.id_persona
                INNER JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
                INNER JOIN grado g ON g.id_grado = ea.id_grado
                LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion
                LEFT JOIN egreso eg ON eg.id_matricula = m.id_matricula
                LEFT JOIN resultado_academico ra ON ra.id_estudiante = est.id_estudiante
                                                AND ra.id_anio_escolar = m.id_anio_escolar
                WHERE m.id_anio_escolar = :id_anio_activo
                  AND m.tipo IN ('INSCRIPCION', 'RATIFICACION', 'REINGRESO', 'EGRESO')
                  AND CAST(REGEXP_SUBSTR(g.nombre, '[0-9]+') AS UNSIGNED) = 6
                  AND (
                      eg.id_egreso IS NOT NULL
                      OR (ra.literal IN ('A', 'B', 'C', 'D') AND COALESCE(ra.estado_resultado, 'PENDIENTE') != 'NO_PROMOVIDO')
                      OR ra.estado_resultado = 'PROMOVIDO'
                  )";

        if (!empty($where)) {
            $sql .= ' AND ' . implode(' AND ', $where);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Retorna todos los estudiantes de 6to grado elegibles para Certificado de Educación Primaria
     * bajo los filtros seleccionados (sin límite de paginación), para emisión por lote.
     */
    public function getEstudiantesEgresoParaLote(array $filters = []): array
    {
        return $this->getEstudiantesEgreso($filters, 1, 500);
    }

    private function buildFiltrosEgreso(array $filters, int $idAnioActivo): array
    {
        $where = [];
        $params = ['id_anio_activo' => $idAnioActivo];

        if (!empty($filters['turno'])) {
            $where[] = 'ea.id_turno = :filtro_turno';
            $params['filtro_turno'] = (int) $filters['turno'];
        }

        if (!empty($filters['grado'])) {
            $where[] = 'g.nombre = :filtro_grado';
            $params['filtro_grado'] = trim((string) $filters['grado']);
        }

        if (!empty($filters['seccion'])) {
            $where[] = 's.nombre = :filtro_seccion';
            $params['filtro_seccion'] = trim((string) $filters['seccion']);
        }

        if (!empty($filters['q'])) {
            $q = '%' . trim((string) $filters['q']) . '%';
            $where[] = '(p.primer_nombre LIKE :q1 OR p.primer_apellido LIKE :q2 OR p.numero_documento LIKE :q3 OR CONCAT_WS(" ", p.primer_nombre, p.primer_apellido) LIKE :q4)';
            $params['q1'] = $q;
            $params['q2'] = $q;
            $params['q3'] = $q;
            $params['q4'] = $q;
        }

        return [$where, $params];
    }

    /**
     * Retorna los datos requeridos para emitir el Certificado de Educación Primaria.
     */
    public function getDatosConstanciaEgreso(int $idEgreso, ?int $idEstudiante = null): ?array
    {
        if ($idEgreso <= 0 && ($idEstudiante === null || $idEstudiante <= 0)) {
            return null;
        }

        $idAnioActivo = \App\Support\AniosEscolares::idEnCurso($this->pdo);
        if ($idAnioActivo <= 0) {
            return null;
        }

        if ($idEstudiante !== null && $idEstudiante > 0) {
            $whereCond = 'est.id_estudiante = :id_estudiante AND m.id_anio_escolar = :id_anio_activo';
            $params = [
                'id_estudiante'  => $idEstudiante,
                'id_anio_activo' => $idAnioActivo,
            ];
        } else {
            $whereCond = '(est.id_estudiante = :id_est_match OR eg.id_egreso = :id_eg_match) AND m.id_anio_escolar = :id_anio_activo';
            $params = [
                'id_est_match'   => $idEgreso,
                'id_eg_match'    => $idEgreso,
                'id_anio_activo' => $idAnioActivo,
            ];
        }

        $sql = "SELECT eg.id_egreso,
                       eg.fecha_egreso,
                       ra.literal,
                       ra.estado_resultado,
                       est.id_estudiante,
                       m.id_matricula,
                       CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS nombre_completo,
                       p.tipo_documento,
                       p.numero_documento,
                       p.sexo,
                       p.fecha_nacimiento,
                       p.lugar_nacimiento_extranjero,
                       par.nombre AS parroquia_nacimiento,
                       mun.nombre AS municipio_nacimiento,
                       COALESCE(est_nac.nombre, est_mun.nombre) AS estado_nacimiento,
                       g.nombre AS grado,
                       s.nombre AS seccion,
                       a.id_anio_escolar,
                       a.nombre_periodo AS periodo_escolar
                FROM matricula m
                INNER JOIN estudiante est ON est.id_estudiante = m.id_estudiante
                INNER JOIN persona p ON p.id_persona = est.id_persona
                INNER JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
                INNER JOIN anio_escolar a ON a.id_anio_escolar = m.id_anio_escolar
                INNER JOIN grado g ON g.id_grado = ea.id_grado
                LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion
                LEFT JOIN parroquia par ON par.id_parroquia = p.id_parroquia_nacimiento
                LEFT JOIN municipio mun ON mun.id_municipio = par.id_municipio
                LEFT JOIN estado est_mun ON est_mun.id_estado = mun.id_estado
                LEFT JOIN estado est_nac ON est_nac.id_estado = p.id_estado_nacimiento
                LEFT JOIN egreso eg ON eg.id_matricula = m.id_matricula
                LEFT JOIN resultado_academico ra ON ra.id_estudiante = est.id_estudiante
                                                AND ra.id_anio_escolar = m.id_anio_escolar
                WHERE {$whereCond}
                  AND m.tipo IN ('INSCRIPCION', 'RATIFICACION', 'REINGRESO', 'EGRESO')
                  AND CAST(REGEXP_SUBSTR(g.nombre, '[0-9]+') AS UNSIGNED) = 6
                  AND (
                      eg.id_egreso IS NOT NULL
                      OR (ra.literal IN ('A', 'B', 'C', 'D') AND COALESCE(ra.estado_resultado, 'PENDIENTE') != 'NO_PROMOVIDO')
                      OR ra.estado_resultado = 'PROMOVIDO'
                  )
                ORDER BY (eg.id_egreso IS NOT NULL) DESC, eg.id_egreso DESC, m.id_matricula DESC
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $row['nombre_completo']      = trim((string) ($row['nombre_completo'] ?? ''));
        $row['numero_documento']    = trim((string) ($row['numero_documento'] ?? ''));
        $row['municipio_nacimiento'] = trim((string) ($row['municipio_nacimiento'] ?? ''));
        $row['estado_nacimiento']    = trim((string) ($row['estado_nacimiento'] ?? ''));
        $row['grado']                = '6to Grado';
        $row['literal']              = trim((string) ($row['literal'] ?? 'A'));
        $row['periodo_escolar']      = trim((string) ($row['periodo_escolar'] ?? ''));

        return $row;
    }

    /**
     * Tipos de constancia que puede emitir un rol.
     *
     * Estudio e Inscripción están abiertos a los roles con acceso al módulo;
     * Prosecución, Retiro y Egreso son exclusivos de secretaría. La regla vive
     * aquí y no en el controlador para que una nueva vista no la reimplemente.
     *
     * @return array<string, string> clave técnica => etiqueta visible
     */
    public static function tiposPermitidosPorRol(string $rol): array
    {
        $tipos = [
            'estudio'     => 'Estudio',
            'inscripcion' => 'Inscripción / Ratificación',
        ];

        if ($rol === 'secretaria') {
            $tipos['prosecucion'] = 'Prosecución';
            $tipos['retiro']      = 'Retiro';
            $tipos['egreso']      = 'Educación Primaria';
        }

        return $tipos;
    }

    /**
     * Carga la configuración institucional reutilizando la conexión del request.
     * Configuracion guarda el PDO en estático; inyectarlo aquí evita que abra
     * una segunda conexión y mantiene el PDO fuera del controlador.
     *
     * @return array<string, string>
     */
    public function getConfiguracion(): array
    {
        \App\Support\Configuracion::setPdo($this->pdo);

        return \App\Support\Configuracion::getAll();
    }

    /**
     * Indica si el director está configurado. Sin nombre, cédula y cargo no se
     * puede emitir un documento legal.
     */
    public function estaConfiguradoDirector(): bool
    {
        \App\Support\Configuracion::setPdo($this->pdo);

        return \App\Support\Configuracion::estaConfiguradoDirector();
    }

    /**
     * Registra en bitácora la emisión de una constancia (individual o por lote).
     * Si no hay usuario en sesión no escribe nada: la bitácora exige un actor.
     */
    public function registrarEmision(int $idUsuario, string $accion): void
    {
        if ($idUsuario <= 0) {
            return;
        }

        (new AuditLogModel($this->pdo))->registrar($idUsuario, $accion, 'Constancias');
    }
}
