<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\AniosEscolares;
use App\Support\Instituciones;
use App\Support\Matriculas;
use PDO;
use Throwable;

/**
 * StatisticsModel
 * ----------------
 * Modelo centralizado para la consolidación analítica institucional:
 * - Control de Representantes y Hermanos
 * - Demografía y Capacidad Escolar por Aula
 * - Familias, Parentescos, Empleados Representantes y Recaudos
 * - Salud, Tallas y Personal Escolar
 */
class StatisticsModel extends Model
{
    public function getAniosEscolares(): array
    {
        return (new ReporteModel($this->pdo))->getAniosEscolares();
    }

    public function resolverAnio(array $anios, ?int $solicitado): int
    {
        if ($solicitado !== null && $solicitado > 0) {
            return $solicitado;
        }

        $idAnio = AniosEscolares::idEnCurso($this->pdo);

        if ($idAnio <= 0 && $anios !== []) {
            $idAnio = (int) ($anios[0]['id_anio_escolar'] ?? 0);
        }

        return $idAnio;
    }

    public function nombrePeriodo(array $anios, int $idAnio): string
    {
        foreach ($anios as $anio) {
            if ((int) ($anio['id_anio_escolar'] ?? 0) === $idAnio) {
                return (string) ($anio['nombre_periodo'] ?? '');
            }
        }

        return '';
    }

    /**
     * Grados disponibles en el período escolar consultado.
     */
    public function getGradosAnio(int $idAnio): array
    {
        try {
            $sql = "SELECT DISTINCT g.id_grado, g.nombre
                      FROM estructura_academica ea
                      INNER JOIN grado g ON g.id_grado = ea.id_grado
                     WHERE ea.id_anio_escolar = :anio
                     ORDER BY g.id_grado ASC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':anio' => $idAnio]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('StatisticsModel::getGradosAnio: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Secciones disponibles según año y grado opcional.
     */
    public function getSeccionesGrado(int $idAnio, ?int $idGrado = null): array
    {
        try {
            $where = "WHERE ea.id_anio_escolar = :anio";
            $params = [':anio' => $idAnio];
            if ($idGrado !== null && $idGrado > 0) {
                $where .= " AND ea.id_grado = :grado";
                $params[':grado'] = $idGrado;
            }

            $sql = "SELECT DISTINCT s.id_seccion, s.nombre
                      FROM estructura_academica ea
                      INNER JOIN seccion s ON s.id_seccion = ea.id_seccion
                     {$where}
                     ORDER BY s.nombre ASC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('StatisticsModel::getSeccionesGrado: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Resumen cuantitativo de Representantes y Control de Hermanos.
     */
    public function getResumenRepresentantesHermanos(int $idAnio, ?int $idGrado = null, ?int $idSeccion = null): array
    {
        $condCursando = Matriculas::condicionCursando('m');
        $params = [':anio' => $idAnio];
        $filtroEa = "";

        if ($idGrado !== null && $idGrado > 0) {
            $filtroEa .= " AND ea.id_grado = :grado";
            $params[':grado'] = $idGrado;
        }
        if ($idSeccion !== null && $idSeccion > 0) {
            $filtroEa .= " AND ea.id_seccion = :seccion";
            $params[':seccion'] = $idSeccion;
        }

        try {
            $sql = "SELECT
                        pe.id_persona AS id_rep,
                        COUNT(DISTINCT e.id_estudiante) AS total_hijos,
                        COUNT(DISTINCT g.id_grado) AS total_grados_distintos
                    FROM persona_estudiante pe
                    INNER JOIN estudiante e ON e.id_estudiante = pe.id_estudiante
                    INNER JOIN matricula m ON m.id_estudiante = e.id_estudiante
                    INNER JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
                    INNER JOIN grado g ON g.id_grado = ea.id_grado
                    WHERE pe.es_representante_legal = 1
                      AND m.id_anio_escolar = :anio
                      AND {$condCursando}
                      {$filtroEa}
                    GROUP BY pe.id_persona";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $familias = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $totalReps = count($familias);
            $totalEstudiantes = 0;
            $repsConHermanos = 0;
            $estudiantesConHermanos = 0;
            $distribucion = ['1' => 0, '2' => 0, '3_mas' => 0];
            $repsDistintosGrados = 0;

            foreach ($familias as $f) {
                $hijos = (int) $f['total_hijos'];
                $totalEstudiantes += $hijos;
                if ($hijos === 1) {
                    $distribucion['1']++;
                } elseif ($hijos === 2) {
                    $distribucion['2']++;
                    $repsConHermanos++;
                    $estudiantesConHermanos += $hijos;
                } else {
                    $distribucion['3_mas']++;
                    $repsConHermanos++;
                    $estudiantesConHermanos += $hijos;
                }
                if ((int) $f['total_grados_distintos'] > 1) {
                    $repsDistintosGrados++;
                }
            }

            $promedio = $totalReps > 0 ? round($totalEstudiantes / $totalReps, 2) : 0;

            return [
                'total_estudiantes'        => $totalEstudiantes,
                'total_representantes'     => $totalReps,
                'promedio_estudiantes'     => number_format($promedio, 1, ',', '.'),
                'familias_con_hermanos'    => $repsConHermanos,
                'estudiantes_con_hermanos' => $estudiantesConHermanos,
                'reps_distintos_grados'    => $repsDistintosGrados,
                'distribucion_familias'    => $distribucion,
            ];
        } catch (Throwable $e) {
            error_log('StatisticsModel::getResumenRepresentantesHermanos: ' . $e->getMessage());
            return [
                'total_estudiantes'        => 0,
                'total_representantes'     => 0,
                'promedio_estudiantes'     => '0',
                'familias_con_hermanos'    => 0,
                'estudiantes_con_hermanos' => 0,
                'reps_distintos_grados'    => 0,
                'distribucion_familias'    => ['1' => 0, '2' => 0, '3_mas' => 0],
            ];
        }
    }

    /**
     * Listado detallado desplegable de representantes y sus estudiantes a cargo (Control de Hermanos).
     */
    public function getDetalleControlHermanos(int $idAnio, ?int $idGrado = null, ?int $idSeccion = null, string $q = ''): array
    {
        $condCursando = Matriculas::condicionCursando('m');
        $params = [':anio' => $idAnio];
        $filtroEa = "";

        if ($idGrado !== null && $idGrado > 0) {
            $filtroEa .= " AND ea.id_grado = :grado";
            $params[':grado'] = $idGrado;
        }
        if ($idSeccion !== null && $idSeccion > 0) {
            $filtroEa .= " AND ea.id_seccion = :seccion";
            $params[':seccion'] = $idSeccion;
        }

        $filtroQ = "";
        $qLimpia = trim($q);
        if ($qLimpia !== '') {
            $filtroQ = " AND (
                CONCAT_WS(' ', pr.primer_nombre, pr.segundo_nombre, pr.primer_apellido, pr.segundo_apellido) LIKE :q
                OR pr.numero_documento LIKE :q
                OR CONCAT_WS(' ', pest.primer_nombre, pest.primer_apellido) LIKE :q
                OR pest.numero_documento LIKE :q
            )";
            $params[':q'] = "%" . $this->escapeLike((string) $qLimpia) . "%";
        }

        try {
            $sql = "SELECT 
                        pe.id_persona AS id_rep,
                        CONCAT_WS(' ', pr.primer_nombre, pr.segundo_nombre, pr.primer_apellido, pr.segundo_apellido) AS nombre_representante,
                        CONCAT_WS('-', pr.tipo_documento, pr.numero_documento) AS cedula_representante,
                        CONCAT_WS(' ', c.codigo_telefono_principal, c.numero_telefono_principal) AS telefono_representante,
                        c.correo_electronico AS correo_representante,
                        e.id_estudiante,
                        CONCAT_WS(' ', pest.primer_nombre, pest.segundo_nombre, pest.primer_apellido, pest.segundo_apellido) AS nombre_estudiante,
                        CONCAT_WS('-', pest.tipo_documento, pest.numero_documento) AS documento_estudiante,
                        pest.fecha_nacimiento AS nacimiento_estudiante,
                        pest.sexo AS sexo_estudiante,
                        g.id_grado,
                        g.nombre AS grado_nombre,
                        s.nombre AS seccion_nombre,
                        COALESCE(t.nombre, 'Mañana') AS turno_nombre
                    FROM persona_estudiante pe
                    INNER JOIN persona pr ON pr.id_persona = pe.id_persona
                    LEFT JOIN contacto c ON c.id_persona = pr.id_persona
                    INNER JOIN estudiante e ON e.id_estudiante = pe.id_estudiante
                    INNER JOIN persona pest ON pest.id_persona = e.id_persona
                    INNER JOIN matricula m ON m.id_estudiante = e.id_estudiante
                    INNER JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
                    INNER JOIN grado g ON g.id_grado = ea.id_grado
                    LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion
                    LEFT JOIN turno t ON t.id_turno = ea.id_turno
                    WHERE pe.es_representante_legal = 1
                      AND m.id_anio_escolar = :anio
                      AND {$condCursando}
                      {$filtroEa}
                      {$filtroQ}
                    ORDER BY pr.primer_apellido ASC, pr.primer_nombre ASC, g.id_grado ASC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Agrupar estudiantes por representante
            $familias = [];
            foreach ($filas as $f) {
                $idRep = (int) $f['id_rep'];
                if (!isset($familias[$idRep])) {
                    $familias[$idRep] = [
                        'id_representante'        => $idRep,
                        'nombre_representante'    => (string) $f['nombre_representante'],
                        'cedula_representante'    => (string) $f['cedula_representante'],
                        'telefono_representante'  => trim((string) $f['telefono_representante']) ?: 'Sin teléfono',
                        'correo_representante'    => (string) ($f['correo_representante'] ?? ''),
                        'estudiantes'             => [],
                        'grados_map'              => [],
                        'hermanos_distintos_grados' => false,
                        'total_estudiantes'       => 0,
                    ];
                }

                $familias[$idRep]['estudiantes'][] = [
                    'id_estudiante'      => (int) $f['id_estudiante'],
                    'nombre_completo'    => (string) $f['nombre_estudiante'],
                    'documento'          => (string) $f['documento_estudiante'],
                    'grado_nombre'       => (string) $f['grado_nombre'],
                    'seccion_nombre'     => (string) $f['seccion_nombre'],
                    'grado_seccion'      => $f['grado_nombre'] . ' "' . $f['seccion_nombre'] . '"',
                    'turno'              => (string) $f['turno_nombre'],
                ];

                $familias[$idRep]['grados_map'][(int) $f['id_grado']] = true;
                $familias[$idRep]['total_estudiantes']++;
            }

            foreach ($familias as $idRep => &$fam) {
                $fam['hermanos_distintos_grados'] = count($fam['grados_map']) > 1;
                unset($fam['grados_map']);
            }
            unset($fam);

            return array_values($familias);
        } catch (Throwable $e) {
            error_log('StatisticsModel::getDetalleControlHermanos: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Matrícula por aula con capacidad y cupos disponibles.
     */
    public function getMatriculaCapacidadDetallada(int $idAnio, ?int $idGrado = null, ?int $idSeccion = null): array
    {
        $condCursando = Matriculas::condicionCursando('m');
        $params = [':anio' => $idAnio];
        $filtroEa = "";

        if ($idGrado !== null && $idGrado > 0) {
            $filtroEa .= " AND ea.id_grado = :grado";
            $params[':grado'] = $idGrado;
        }
        if ($idSeccion !== null && $idSeccion > 0) {
            $filtroEa .= " AND ea.id_seccion = :seccion";
            $params[':seccion'] = $idSeccion;
        }

        try {
            $sql = "SELECT 
                        ea.id_estructura_academica,
                        g.nombre AS grado,
                        COALESCE(s.nombre, 'U') AS seccion,
                        COALESCE(t.nombre, 'Mañana') AS turno,
                        ea.capacidad_maxima,
                        COUNT(DISTINCT m.id_estudiante) AS total_matricula,
                        COUNT(DISTINCT CASE WHEN p.sexo = 'M' THEN m.id_estudiante END) AS varones,
                        COUNT(DISTINCT CASE WHEN p.sexo = 'F' THEN m.id_estudiante END) AS hembras
                    FROM estructura_academica ea
                    INNER JOIN grado g ON g.id_grado = ea.id_grado
                    LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion
                    LEFT JOIN turno t ON t.id_turno = ea.id_turno
                    LEFT JOIN matricula m ON m.id_estructura_academica = ea.id_estructura_academica 
                                         AND m.id_anio_escolar = ea.id_anio_escolar 
                                         AND {$condCursando}
                    LEFT JOIN estudiante e ON e.id_estudiante = m.id_estudiante
                    LEFT JOIN persona p ON p.id_persona = e.id_persona
                    WHERE ea.id_anio_escolar = :anio
                      {$filtroEa}
                    GROUP BY ea.id_estructura_academica
                    ORDER BY g.id_grado ASC, s.nombre ASC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('StatisticsModel::getMatriculaCapacidadDetallada: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Indicadores demográficos de matrícula: género, nuevos vs regulares, repitientes, retiros y edades.
     */
    public function getDemografiaMatricula(int $idAnio, ?int $idGrado = null, ?int $idSeccion = null): array
    {
        $condCursando = Matriculas::condicionCursando('m');
        $params = [':anio' => $idAnio];
        $filtroEa = "";

        if ($idGrado !== null && $idGrado > 0) {
            $filtroEa .= " AND ea.id_grado = :grado";
            $params[':grado'] = $idGrado;
        }
        if ($idSeccion !== null && $idSeccion > 0) {
            $filtroEa .= " AND ea.id_seccion = :seccion";
            $params[':seccion'] = $idSeccion;
        }

        try {
            // Totales por género y tipo de ingreso
            $sqlGen = "SELECT 
                            COUNT(DISTINCT m.id_estudiante) AS total_cursando,
                            COUNT(DISTINCT CASE WHEN p.sexo = 'M' THEN m.id_estudiante END) AS varones,
                            COUNT(DISTINCT CASE WHEN p.sexo = 'F' THEN m.id_estudiante END) AS hembras,
                            COUNT(DISTINCT CASE WHEN m.tipo = 'INSCRIPCION' THEN m.id_estudiante END) AS nuevos_ingresos,
                            COUNT(DISTINCT CASE WHEN m.tipo = 'RATIFICACION' THEN m.id_estudiante END) AS regulares,
                            COUNT(DISTINCT CASE WHEN m.tipo = 'REINGRESO' THEN m.id_estudiante END) AS reingresos
                        FROM matricula m
                        INNER JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
                        INNER JOIN estudiante e ON e.id_estudiante = m.id_estudiante
                        INNER JOIN persona p ON p.id_persona = e.id_persona
                        WHERE m.id_anio_escolar = :anio
                          AND {$condCursando}
                          {$filtroEa}";

            $stmtG = $this->pdo->prepare($sqlGen);
            $stmtG->execute($params);
            $datosG = $stmtG->fetch(PDO::FETCH_ASSOC) ?: [];

            // Repitientes según encuesta_ratificacion
            $sqlRep = "SELECT COUNT(DISTINCT er.id_matricula) AS total_repitientes
                         FROM encuesta_ratificacion er
                        INNER JOIN matricula m ON m.id_matricula = er.id_matricula
                        INNER JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
                        WHERE m.id_anio_escolar = :anio
                          AND er.repite_grado = 1
                          AND {$condCursando}
                          {$filtroEa}";
            $stmtR = $this->pdo->prepare($sqlRep);
            $stmtR->execute($params);
            $repitientes = (int) $stmtR->fetchColumn();

            // Retiros oficiales
            $sqlRet = "SELECT COUNT(DISTINCT m.id_estudiante) AS total_retirados
                         FROM matricula m
                        INNER JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
                        WHERE m.id_anio_escolar = :anio
                          AND m.tipo = 'RETIRO'
                          {$filtroEa}";
            $stmtRet = $this->pdo->prepare($sqlRet);
            $stmtRet->execute($params);
            $retirados = (int) $stmtRet->fetchColumn();

            // Distribución de edades y extraedad
            $sqlEdades = "SELECT 
                            g.id_grado,
                            g.nombre AS grado_nombre,
                            TIMESTAMPDIFF(YEAR, p.fecha_nacimiento, CURDATE()) AS edad,
                            p.sexo,
                            COUNT(DISTINCT m.id_estudiante) AS total
                          FROM matricula m
                          INNER JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
                          INNER JOIN grado g ON g.id_grado = ea.id_grado
                          INNER JOIN estudiante e ON e.id_estudiante = m.id_estudiante
                          INNER JOIN persona p ON p.id_persona = e.id_persona
                          WHERE m.id_anio_escolar = :anio
                            AND p.fecha_nacimiento IS NOT NULL
                            AND {$condCursando}
                            {$filtroEa}
                          GROUP BY g.id_grado, edad, p.sexo
                          ORDER BY edad ASC";
            $stmtE = $this->pdo->prepare($sqlEdades);
            $stmtE->execute($params);
            $filasEdades = $stmtE->fetchAll(PDO::FETCH_ASSOC);

            // Mapeo de edad esperada según grado (1er grado = 6 años, 2do = 7, etc.)
            // En caso de grados estándar por ID o nombre
            $desgloseEdades = [];
            $estudiantesExtraedad = 0;
            foreach ($filasEdades as $fe) {
                $edad = (int) $fe['edad'];
                $cant = (int) $fe['total'];
                if (!isset($desgloseEdades[$edad])) {
                    $desgloseEdades[$edad] = ['edad' => $edad, 'varones' => 0, 'hembras' => 0, 'total' => 0];
                }
                if ($fe['sexo'] === 'M') {
                    $desgloseEdades[$edad]['varones'] += $cant;
                } else {
                    $desgloseEdades[$edad]['hembras'] += $cant;
                }
                $desgloseEdades[$edad]['total'] += $cant;

                // Cálculo de extraedad: 1.° grado esperada <= 6 años, 2.° <= 7 años, ..., 6.° <= 11 años
                $idGradoInt = (int) $fe['id_grado'];
                $edadMaxEsperada = 5 + $idGradoInt;
                if ($edad > $edadMaxEsperada) {
                    $estudiantesExtraedad += $cant;
                }
            }

            ksort($desgloseEdades);

            $totCursando = (int) ($datosG['total_cursando'] ?? 0);
            $varones     = (int) ($datosG['varones'] ?? 0);
            $hembras     = (int) ($datosG['hembras'] ?? 0);

            return [
                'total_cursando'        => $totCursando,
                'varones'               => $varones,
                'hembras'               => $hembras,
                'porcentaje_varones'    => $totCursando > 0 ? (int) round(($varones / $totCursando) * 100) : 0,
                'porcentaje_hembras'    => $totCursando > 0 ? (int) round(($hembras / $totCursando) * 100) : 0,
                'nuevos_ingresos'       => (int) ($datosG['nuevos_ingresos'] ?? 0),
                'regulares'             => (int) ($datosG['regulares'] ?? 0),
                'reingresos'            => (int) ($datosG['reingresos'] ?? 0),
                'repitientes'           => $repitientes,
                'retirados'             => $retirados,
                'desglose_edades'       => array_values($desgloseEdades),
                'estudiantes_extraedad' => $estudiantesExtraedad,
            ];
        } catch (Throwable $e) {
            error_log('StatisticsModel::getDemografiaMatricula: ' . $e->getMessage());
            return [
                'total_cursando'        => 0,
                'varones'               => 0,
                'hembras'               => 0,
                'porcentaje_varones'    => 0,
                'porcentaje_hembras'    => 0,
                'nuevos_ingresos'       => 0,
                'regulares'             => 0,
                'reingresos'            => 0,
                'repitientes'           => 0,
                'retirados'             => 0,
                'desglose_edades'       => [],
                'estudiantes_extraedad' => 0,
            ];
        }
    }

    /**
     * Parentesco, representantes que son empleados y datos de contacto incompletos.
     */
    public function getEstadisticasFamilias(int $idAnio, ?int $idGrado = null, ?int $idSeccion = null): array
    {
        $condCursando = Matriculas::condicionCursando('m');
        $params = [':anio' => $idAnio];
        $filtroEa = "";

        if ($idGrado !== null && $idGrado > 0) {
            $filtroEa .= " AND ea.id_grado = :grado";
            $params[':grado'] = $idGrado;
        }
        if ($idSeccion !== null && $idSeccion > 0) {
            $filtroEa .= " AND ea.id_seccion = :seccion";
            $params[':seccion'] = $idSeccion;
        }

        try {
            // Parentescos
            $sqlPar = "SELECT 
                            COALESCE(par.nombre, 'NO ESPECIFICADO') AS parentesco, 
                            COUNT(DISTINCT pe.id_persona) AS total
                       FROM persona_estudiante pe
                       INNER JOIN matricula m ON m.id_estudiante = pe.id_estudiante
                       INNER JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
                       LEFT JOIN parentesco par ON par.id_parentesco = pe.id_parentesco
                      WHERE pe.es_representante_legal = 1
                        AND m.id_anio_escolar = :anio
                        AND {$condCursando}
                        {$filtroEa}
                      GROUP BY par.nombre
                      ORDER BY total DESC";
            $stmtP = $this->pdo->prepare($sqlPar);
            $stmtP->execute($params);
            $parentescos = $stmtP->fetchAll(PDO::FETCH_ASSOC);

            // Representantes con datos incompletos
            $sqlInc = "SELECT COUNT(DISTINCT pe.id_persona) AS total_incompletos
                         FROM persona_estudiante pe
                        INNER JOIN persona pr ON pr.id_persona = pe.id_persona
                        LEFT JOIN contacto c ON c.id_persona = pr.id_persona
                        INNER JOIN matricula m ON m.id_estudiante = pe.id_estudiante
                        INNER JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
                        WHERE pe.es_representante_legal = 1
                          AND m.id_anio_escolar = :anio
                          AND {$condCursando}
                          {$filtroEa}
                          AND (pr.numero_documento IS NULL OR pr.numero_documento = '' 
                               OR c.numero_telefono_principal IS NULL OR c.numero_telefono_principal = '')";
            $stmtI = $this->pdo->prepare($sqlInc);
            $stmtI->execute($params);
            $incompletos = (int) $stmtI->fetchColumn();

            // Representantes que son personal de la institución
            $sqlEmp = "SELECT 
                            pe.id_persona,
                            CONCAT_WS(' ', pr.primer_nombre, pr.primer_apellido) AS nombre,
                            CONCAT_WS('-', pr.tipo_documento, pr.numero_documento) AS cedula,
                            COALESCE(c.nombre, 'Personal escolar') AS cargo
                         FROM persona_estudiante pe
                        INNER JOIN persona pr ON pr.id_persona = pe.id_persona
                        INNER JOIN empleado emp ON emp.id_persona = pr.id_persona
                        LEFT JOIN cargo c ON c.id_cargo = emp.id_cargo
                        INNER JOIN matricula m ON m.id_estudiante = pe.id_estudiante
                        INNER JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
                        WHERE pe.es_representante_legal = 1
                          AND m.id_anio_escolar = :anio
                          AND {$condCursando}
                          {$filtroEa}
                        GROUP BY pe.id_persona
                        ORDER BY pr.primer_apellido ASC";
            $stmtEmp = $this->pdo->prepare($sqlEmp);
            $stmtEmp->execute($params);
            $repsEmpleados = $stmtEmp->fetchAll(PDO::FETCH_ASSOC);

            return [
                'parentescos'             => $parentescos,
                'incompletos'             => $incompletos,
                'representantes_empleados' => $repsEmpleados,
                'total_reps_empleados'    => count($repsEmpleados),
            ];
        } catch (Throwable $e) {
            error_log('StatisticsModel::getEstadisticasFamilias: ' . $e->getMessage());
            return [
                'parentescos'             => [],
                'incompletos'             => 0,
                'representantes_empleados' => [],
                'total_reps_empleados'    => 0,
            ];
        }
    }

    /**
     * Documentos físicos pendientes de consignación.
     */
    public function getDocumentosPendientes(int $idAnio, ?int $idGrado = null, ?int $idSeccion = null): array
    {
        $condCursando = Matriculas::condicionCursando('m');
        $params = [':anio' => $idAnio];
        $filtroEa = "";

        if ($idGrado !== null && $idGrado > 0) {
            $filtroEa .= " AND ea.id_grado = :grado";
            $params[':grado'] = $idGrado;
        }
        if ($idSeccion !== null && $idSeccion > 0) {
            $filtroEa .= " AND ea.id_seccion = :seccion";
            $params[':seccion'] = $idSeccion;
        }

        try {
            $sql = "SELECT DISTINCT
                ed.id_estudiante_documento,
                ed.id_estudiante,
                td.id_tipo_documento,
                        td.codigo,
                        td.nombre AS documento
                    FROM estudiante_documento ed
                    INNER JOIN tipo_documento td ON td.id_tipo_documento = ed.id_tipo_documento
                    INNER JOIN inscripcion i ON i.id_estudiante = ed.id_estudiante
                    INNER JOIN matricula m ON m.id_estudiante = ed.id_estudiante
                    INNER JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
                    WHERE ed.consignado = 0
                      AND ed.requerido IS NOT NULL
                      AND i.documentos_evaluados_en IS NOT NULL
                      AND m.id_anio_escolar = :anio
                      AND {$condCursando}
                      {$filtroEa}";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $conteo = [];
            $estudiantesAdeudan = [];

            foreach ($filas as $fila) {
                $codigo = (string) ($fila['codigo'] ?? '');
                $idDocumento = (int) $fila['id_tipo_documento'];
                if (!isset($conteo[$idDocumento])) {
                    $conteo[$idDocumento] = [
                        'documento'  => (string) $fila['documento'],
                        'pendientes' => 0,
                    ];
                }
                $conteo[$idDocumento]['pendientes']++;

                if ($codigo === 'cedula_alumno') {
                    $conteo[$idDocumento]['documento'] = 'Estudiantes sin cédula (9 años o más)';
                }

                $estudiantesAdeudan[(int) $fila['id_estudiante']] = true;
            }

            $documentos = array_values($conteo);
            usort($documentos, static fn (array $a, array $b): int => $b['pendientes'] <=> $a['pendientes']);

            return [
                'documentos'            => $documentos,
                'estudiantes_con_deuda' => count($estudiantesAdeudan),
            ];
        } catch (Throwable $e) {
            error_log('StatisticsModel::getDocumentosPendientes: ' . $e->getMessage());
            return ['documentos' => [], 'estudiantes_con_deuda' => 0];
        }
    }

    /**
     * Distribución territorial por parroquia de residencia.
     */
    public function getDistribucionParroquias(int $idAnio, ?int $idGrado = null, ?int $idSeccion = null): array
    {
        $condCursando = Matriculas::condicionCursando('m');
        $params = [':anio' => $idAnio];
        $filtroEa = "";

        if ($idGrado !== null && $idGrado > 0) {
            $filtroEa .= " AND ea.id_grado = :grado";
            $params[':grado'] = $idGrado;
        }
        if ($idSeccion !== null && $idSeccion > 0) {
            $filtroEa .= " AND ea.id_seccion = :seccion";
            $params[':seccion'] = $idSeccion;
        }

        try {
            $sql = "SELECT 
                        COALESCE(par.nombre, 'Sin parroquia registrada') AS parroquia,
                        COALESCE(mun.nombre, 'Municipio no asignado') AS municipio,
                        COUNT(DISTINCT m.id_estudiante) AS total
                    FROM matricula m
                    INNER JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
                    INNER JOIN estudiante e ON e.id_estudiante = m.id_estudiante
                    INNER JOIN persona p ON p.id_persona = e.id_persona
                    LEFT JOIN direccion d ON d.id_persona = p.id_persona
                    LEFT JOIN persona_estudiante pe ON pe.id_estudiante = e.id_estudiante 
                        AND pe.es_representante_legal = 1 
                        AND pe.estado_relacion = 'ACTIVO'
                    LEFT JOIN direccion d_rep ON d_rep.id_persona = pe.id_persona
                    LEFT JOIN parroquia par ON par.id_parroquia = COALESCE(d.id_parroquia, d_rep.id_parroquia)
                    LEFT JOIN municipio mun ON mun.id_municipio = par.id_municipio
                    WHERE m.id_anio_escolar = :anio
                      AND {$condCursando}
                      {$filtroEa}
                    GROUP BY par.id_parroquia
                    ORDER BY total DESC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('StatisticsModel::getDistribucionParroquias: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Conserva y adapta estadísticas existentes de salud y medidas antropométricas.
     */
    public function getEstadisticasSalud(int $idAnio): array
    {
        return (new DirectivoDashboardModel($this->pdo))->getEstadisticasSalud($idAnio);
    }

    public function getEstadisticasAntropometricas(int $idAnio): array
    {
        return (new DirectivoDashboardModel($this->pdo))->getEstadisticasAntropometricas($idAnio);
    }

    public function getEstadisticasTallas(int $idAnio): array
    {
        return (new DirectivoDashboardModel($this->pdo))->getEstadisticasTallas($idAnio);
    }

    public function getEstadisticasPersonal(): array
    {
        return (new DirectivoDashboardModel($this->pdo))->getEstadisticasPersonal();
    }

    public function getMetricasPersonal(): array
    {
        return (new DirectivoDashboardModel($this->pdo))->getMetricasPersonal();
    }

    public function getCoberturaAulas(int $idAnio): array
    {
        return (new ReporteModel($this->pdo))->getDistribucionAcademica($idAnio);
    }
    /**
     * Estadísticas de Calificaciones y Rendimiento por Grado y Sección.
     */
    public function getRendimientoSecciones(int $idAnio, ?int $idGrado = null, ?int $idSeccion = null): array
    {
        $condCursando = Matriculas::condicionCursando('m');
        $params = [':anio' => $idAnio];
        $filtroEa = "";

        if ($idGrado !== null && $idGrado > 0) {
            $filtroEa .= " AND ea.id_grado = :grado";
            $params[':grado'] = $idGrado;
        }
        if ($idSeccion !== null && $idSeccion > 0) {
            $filtroEa .= " AND ea.id_seccion = :seccion";
            $params[':seccion'] = $idSeccion;
        }

        try {
            $sql = "SELECT 
                        ea.id_estructura_academica AS id,
                        ea.id_grado,
                        ea.id_seccion,
                        g.nombre AS grado,
                        COALESCE(s.nombre, 'U') AS seccion,
                        COALESCE(t.nombre, 'Mañana') AS turno,
                        ae.nombre_periodo AS anio_escolar,
                        COUNT(DISTINCT m.id_estudiante) AS total_estudiantes,
                        COUNT(DISTINCT CASE WHEN ra.literal IS NOT NULL AND TRIM(ra.literal) != '' THEN ra.id_estudiante END) AS total_cargados,
                        COUNT(DISTINCT CASE WHEN m.id_estudiante IS NOT NULL AND (ra.literal IS NULL OR TRIM(ra.literal) = '') THEN m.id_estudiante END) AS total_pendientes,
                        COUNT(DISTINCT CASE WHEN ra.estado_resultado = 'PROMOVIDO' OR (ra.literal IN ('A','B','C','D') AND ra.estado_resultado = 'PENDIENTE') THEN ra.id_estudiante END) AS total_promovidos,
                        COUNT(DISTINCT CASE WHEN ra.estado_resultado = 'NO_PROMOVIDO' OR (ra.literal = 'E' AND ra.estado_resultado = 'PENDIENTE') THEN ra.id_estudiante END) AS total_no_promovidos,
                        COUNT(DISTINCT CASE WHEN ra.literal = 'A' THEN ra.id_estudiante END) AS total_a,
                        COUNT(DISTINCT CASE WHEN ra.literal = 'B' THEN ra.id_estudiante END) AS total_b,
                        COUNT(DISTINCT CASE WHEN ra.literal = 'C' THEN ra.id_estudiante END) AS total_c,
                        COUNT(DISTINCT CASE WHEN ra.literal = 'D' THEN ra.id_estudiante END) AS total_d,
                        COUNT(DISTINCT CASE WHEN ra.literal = 'E' THEN ra.id_estudiante END) AS total_e,
                        MAX(NULLIF(TRIM(CONCAT_WS(' ', p_doc.primer_nombre, p_doc.primer_apellido)), '')) AS docente_nombre
                    FROM estructura_academica ea
                    INNER JOIN anio_escolar ae ON ae.id_anio_escolar = ea.id_anio_escolar
                    INNER JOIN grado g ON g.id_grado = ea.id_grado
                    LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion
                    LEFT JOIN turno t ON t.id_turno = ea.id_turno
                    LEFT JOIN matricula m ON m.id_estructura_academica = ea.id_estructura_academica
                                         AND m.id_anio_escolar = ea.id_anio_escolar
                                         AND {$condCursando}
                    LEFT JOIN resultado_academico ra ON ra.id_estudiante = m.id_estudiante
                                                    AND ra.id_anio_escolar = ea.id_anio_escolar
                    LEFT JOIN asignacion_docente ad ON ad.id_estructura_academica = ea.id_estructura_academica
                                                   AND (ad.fecha_fin IS NULL OR ad.fecha_fin >= CURDATE())
                    LEFT JOIN docente doc ON doc.id_docente = ad.id_docente
                    LEFT JOIN empleado emp ON emp.id_empleado = doc.id_empleado
                    LEFT JOIN persona p_doc ON p_doc.id_persona = emp.id_persona
                    WHERE ea.id_anio_escolar = :anio
                      {$filtroEa}
                    GROUP BY ea.id_estructura_academica, ea.id_grado, ea.id_seccion, g.nombre, s.nombre, t.nombre, ae.nombre_periodo";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('StatisticsModel::getRendimientoSecciones: ' . $e->getMessage());
            return [];
        }

        foreach ($filas as &$fila) {
            $fila['id']                = (int) $fila['id'];
            $fila['total_estudiantes'] = (int) $fila['total_estudiantes'];
            $fila['total_cargados']    = (int) $fila['total_cargados'];
            $fila['total_pendientes']  = (int) $fila['total_pendientes'];
            $fila['total_promovidos']  = (int) $fila['total_promovidos'];
            $fila['total_no_promovidos'] = (int) $fila['total_no_promovidos'];
            $fila['total_a']           = (int) $fila['total_a'];
            $fila['total_b']           = (int) $fila['total_b'];
            $fila['total_c']           = (int) $fila['total_c'];
            $fila['total_d']           = (int) $fila['total_d'];
            $fila['total_e']           = (int) $fila['total_e'];
            $fila['docente_nombre']    = trim((string) ($fila['docente_nombre'] ?? ''));
            $fila['turno']             = trim((string) ($fila['turno'] ?? ''));
            $fila['porcentaje_carga']  = $fila['total_estudiantes'] > 0
                ? (int) round(($fila['total_cargados'] / $fila['total_estudiantes']) * 100)
                : -1;
        }
        unset($fila);

        usort($filas, static function (array $a, array $b): int {
            $ordA = Instituciones::ordinalGrado($a['grado']) ?? PHP_INT_MAX;
            $ordB = Instituciones::ordinalGrado($b['grado']) ?? PHP_INT_MAX;
            if ($ordA !== $ordB) {
                return $ordA <=> $ordB;
            }

            return strcmp((string) $a['seccion'], (string) $b['seccion']);
        });

        return $filas;
    }

    /**
     * Resumen acumulado de rendimiento y distribución de literales.
     */
    public function getResumenRendimiento(int $idAnio, ?int $idGrado = null, ?int $idSeccion = null): array
    {
        $secciones = $this->getRendimientoSecciones($idAnio, $idGrado, $idSeccion);
        $resumen = [
            'total_secciones'     => count($secciones),
            'total_estudiantes'   => 0,
            'total_cargados'      => 0,
            'total_pendientes'    => 0,
            'total_promovidos'    => 0,
            'total_no_promovidos' => 0,
            'total_a'             => 0,
            'total_b'             => 0,
            'total_c'             => 0,
            'total_d'             => 0,
            'total_e'             => 0,
            'porcentaje_carga'    => 0,
            'tasa_promocion'      => 0,
        ];

        foreach ($secciones as $sec) {
            $resumen['total_estudiantes']   += $sec['total_estudiantes'];
            $resumen['total_cargados']      += $sec['total_cargados'];
            $resumen['total_pendientes']    += $sec['total_pendientes'];
            $resumen['total_promovidos']    += $sec['total_promovidos'];
            $resumen['total_no_promovidos'] += $sec['total_no_promovidos'];
            $resumen['total_a']             += $sec['total_a'];
            $resumen['total_b']             += $sec['total_b'];
            $resumen['total_c']             += $sec['total_c'];
            $resumen['total_d']             += $sec['total_d'];
            $resumen['total_e']             += $sec['total_e'];
        }

        if ($resumen['total_estudiantes'] > 0) {
            $resumen['porcentaje_carga'] = (int) round(($resumen['total_cargados'] / $resumen['total_estudiantes']) * 100);
        }

        if ($resumen['total_cargados'] > 0) {
            $resumen['tasa_promocion'] = (int) round(($resumen['total_promovidos'] / $resumen['total_cargados']) * 100);
        }

        return $resumen;
    }
}
