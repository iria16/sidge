<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\AniosEscolares;
use App\Support\Instituciones;
use App\Support\Matriculas;
use App\Support\Tallas;
use PDO;
use Throwable;

/**
 * DirectivoDashboardModel
 * Lecturas y agregaciones para el panel de Dirección y Coordinación:
 * métricas demográficas de matrícula, capacidad institucional, personal escolar,
 * avance de literales y alertas operativas.
 */
class DirectivoDashboardModel extends Model
{
    /**
     * Retorna el año escolar activo o null.
     */
    public function getAnioActivo(): ?array
    {
        try {
            $stmt = $this->pdo->query(
                "SELECT id_anio_escolar, nombre_periodo, estado, anio_inicio, anio_cierre
                   FROM anio_escolar
                  WHERE estado = 'ACTIVO'
                  LIMIT 1"
            );
            $fila = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($fila) {
                return [
                    'id'           => (int) $fila['id_anio_escolar'],
                    'nombre'       => (string) $fila['nombre_periodo'],
                    'estado'       => (string) $fila['estado'],
                    'fecha_inicio' => $fila['anio_inicio'] ? (string) $fila['anio_inicio'] : null,
                    'fecha_fin'    => $fila['anio_cierre'] ? (string) $fila['anio_cierre'] : null,
                ];
            }
        } catch (Throwable $e) {
            error_log('DirectivoDashboardModel::getAnioActivo: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Métricas de matrícula del período: total general, varones, hembras,
     * inscripciones nuevas, ratificaciones y retiros.
     */
    public function getMetricasMatricula(?int $idAnio = null): array
    {
        $idAnio = $idAnio !== null && $idAnio > 0 ? $idAnio : AniosEscolares::idEnCurso($this->pdo);

        $resultado = [
            'total_estudiantes'   => 0,
            'varones'             => 0,
            'hembras'             => 0,
            'porcentaje_varones'  => 0,
            'porcentaje_hembras'  => 0,
            'inscripciones'       => 0,
            'ratificaciones'      => 0,
            'retirados'           => 0,
        ];

        if ($idAnio <= 0) {
            return $resultado;
        }

        try {
            $sql = "SELECT COUNT(DISTINCT m.id_estudiante) AS total,
                           COUNT(DISTINCT CASE WHEN p.sexo = 'M' THEN m.id_estudiante END) AS varones,
                           COUNT(DISTINCT CASE WHEN p.sexo = 'F' THEN m.id_estudiante END) AS hembras,
                           COUNT(DISTINCT CASE WHEN m.tipo = 'INSCRIPCION' THEN m.id_estudiante END) AS inscripciones,
                           COUNT(DISTINCT CASE WHEN m.tipo = 'RATIFICACION' THEN m.id_estudiante END) AS ratificaciones
                      FROM matricula m
                      INNER JOIN estudiante e ON e.id_estudiante = m.id_estudiante
                      INNER JOIN persona p ON p.id_persona = e.id_persona
                     WHERE m.id_anio_escolar = :anio
                       AND " . Matriculas::condicionCursando('m');

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['anio' => $idAnio]);
            $fila = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($fila) {
                $total   = (int) ($fila['total'] ?? 0);
                $varones = (int) ($fila['varones'] ?? 0);
                $hembras = (int) ($fila['hembras'] ?? 0);

                $resultado['total_estudiantes']  = $total;
                $resultado['varones']            = $varones;
                $resultado['hembras']            = $hembras;
                $resultado['porcentaje_varones'] = $total > 0 ? (int) round(($varones / $total) * 100) : 0;
                $resultado['porcentaje_hembras'] = $total > 0 ? (int) round(($hembras / $total) * 100) : 0;
                $resultado['inscripciones']      = (int) ($fila['inscripciones'] ?? 0);
                $resultado['ratificaciones']     = (int) ($fila['ratificaciones'] ?? 0);
            }

            // Retiros en este año escolar
            $stmtRet = $this->pdo->prepare(
                "SELECT COUNT(DISTINCT id_estudiante)
                   FROM matricula
                  WHERE id_anio_escolar = :anio AND tipo = 'RETIRO'"
            );
            $stmtRet->execute(['anio' => $idAnio]);
            $resultado['retirados'] = (int) $stmtRet->fetchColumn();

        } catch (Throwable $e) {
            error_log('DirectivoDashboardModel::getMetricasMatricula: ' . $e->getMessage());
        }

        return $resultado;
    }

    /**
     * Métricas de capacidad escolar: cupos totales, ocupados, disponibles,
     * ocupación porcentual, secciones totales, con docente y sin docente.
     */
    public function getMetricasCapacidad(?int $idAnio = null): array
    {
        $idAnio = $idAnio !== null && $idAnio > 0 ? $idAnio : AniosEscolares::idEnCurso($this->pdo);

        $resultado = [
            'cupos_totales'         => 0,
            'cupos_ocupados'        => 0,
            'cupos_disponibles'     => 0,
            'porcentaje_ocupacion'  => 0,
            'total_secciones'       => 0,
            'secciones_con_docente' => 0,
            'secciones_sin_docente' => 0,
        ];

        if ($idAnio <= 0) {
            return $resultado;
        }

        try {
            $sql = "SELECT COALESCE(SUM(ea.capacidad_maxima), 0) AS cupos_totales,
                           COUNT(DISTINCT ea.id_estructura_academica) AS total_secciones,
                           COUNT(DISTINCT CASE WHEN ad.id_docente IS NOT NULL THEN ea.id_estructura_academica END) AS secciones_con_docente
                      FROM estructura_academica ea
                      LEFT JOIN asignacion_docente ad ON ad.id_estructura_academica = ea.id_estructura_academica
                                                     AND (ad.fecha_fin IS NULL OR ad.fecha_fin >= CURDATE())
                     WHERE ea.id_anio_escolar = :anio";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['anio' => $idAnio]);
            $fila = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($fila) {
                $totales = (int) ($fila['cupos_totales'] ?? 0);
                $seccTot = (int) ($fila['total_secciones'] ?? 0);
                $seccDoc = (int) ($fila['secciones_con_docente'] ?? 0);

                // Ocupados reales de la matrícula activa
                $stmtOcup = $this->pdo->prepare(
                    "SELECT COUNT(DISTINCT id_estudiante)
                       FROM matricula m
                      WHERE m.id_anio_escolar = :anio
                        AND " . Matriculas::condicionCursando('m')
                );
                $stmtOcup->execute(['anio' => $idAnio]);
                $ocupados = (int) $stmtOcup->fetchColumn();

                $resultado['cupos_totales']         = $totales;
                $resultado['cupos_ocupados']        = $ocupados;
                $resultado['cupos_disponibles']     = max(0, $totales - $ocupados);
                $resultado['porcentaje_ocupacion']  = $totales > 0 ? (int) min(100, round(($ocupados / $totales) * 100)) : 0;
                $resultado['total_secciones']       = $seccTot;
                $resultado['secciones_con_docente'] = $seccDoc;
                $resultado['secciones_sin_docente'] = max(0, $seccTot - $seccDoc);
            }
        } catch (Throwable $e) {
            error_log('DirectivoDashboardModel::getMetricasCapacidad: ' . $e->getMessage());
        }

        return $resultado;
    }

    /**
     * Métricas del personal escolar: total empleados activos, desglose de docentes,
     * administrativos, obreros y ausencias médicas (en reposo hoy).
     */
    public function getMetricasPersonal(): array
    {
        $resultado = [
            'total_empleados'   => 0,
            'empleados_activos' => 0,
            'docentes_activos'  => 0,
            'administrativos'   => 0,
            'obreros'           => 0,
            'en_reposo_hoy'     => 0,
        ];

        try {
            $sql = "SELECT COUNT(DISTINCT e.id_empleado) AS total,
                           COUNT(DISTINCT CASE WHEN p.estado = 'ACTIVO' THEN e.id_empleado END) AS activos,
                           COUNT(DISTINCT CASE WHEN p.estado = 'ACTIVO' AND LOWER(c.nombre) LIKE '%docente%' THEN e.id_empleado END) AS docentes,
                           COUNT(DISTINCT CASE WHEN p.estado = 'ACTIVO' AND (LOWER(c.nombre) LIKE '%administrativ%' OR LOWER(c.nombre) LIKE '%secretar%') THEN e.id_empleado END) AS administrativos,
                           COUNT(DISTINCT CASE WHEN p.estado = 'ACTIVO' AND (LOWER(c.nombre) LIKE '%obrer%' OR LOWER(c.nombre) LIKE '%mantenimiento%' OR LOWER(c.nombre) LIKE '%vigilante%') THEN e.id_empleado END) AS obreros
                      FROM empleado e
                      INNER JOIN persona p ON p.id_persona = e.id_persona
                      LEFT JOIN cargo c ON c.id_cargo = e.id_cargo";

            $fila = $this->pdo->query($sql)->fetch(PDO::FETCH_ASSOC);

            if ($fila) {
                $resultado['total_empleados']   = (int) ($fila['total'] ?? 0);
                $resultado['empleados_activos'] = (int) ($fila['activos'] ?? 0);
                $resultado['docentes_activos']  = (int) ($fila['docentes'] ?? 0);
                $resultado['administrativos']   = (int) ($fila['administrativos'] ?? 0);
                $resultado['obreros']           = (int) ($fila['obreros'] ?? 0);
            }

            // Desglose dinámico de personal activo por cargo real registrado
            $sqlCargos = "SELECT COALESCE(c.nombre, 'Sin cargo asignado') AS cargo,
                                 COUNT(DISTINCT e.id_empleado) AS total
                            FROM empleado e
                           INNER JOIN persona p ON p.id_persona = e.id_persona
                            LEFT JOIN cargo c ON c.id_cargo = e.id_cargo
                           WHERE p.estado = 'ACTIVO'
                           GROUP BY c.id_cargo, c.nombre
                           ORDER BY total DESC, c.nombre ASC";
            $filasCargos = $this->pdo->query($sqlCargos)->fetchAll(PDO::FETCH_ASSOC);
            $resultado['por_cargo'] = $filasCargos ?: [];

            // Empleados en reposo vigente hoy
            $condicionReposo = "r.estado = 'VIGENTE' AND CURDATE() BETWEEN r.fecha_inicio AND r.fecha_fin";
            $sqlRep = "SELECT COUNT(DISTINCT e.id_empleado)
                         FROM empleado e
                        INNER JOIN persona p ON p.id_persona = e.id_persona
                        INNER JOIN reposo_empleado r ON r.id_empleado = e.id_empleado
                        WHERE p.estado = 'ACTIVO' AND {$condicionReposo}";
            $resultado['en_reposo_hoy'] = (int) $this->pdo->query($sqlRep)->fetchColumn();

        } catch (Throwable $e) {
            error_log('DirectivoDashboardModel::getMetricasPersonal: ' . $e->getMessage());
        }

        return $resultado;
    }

    /**
     * Estadísticas detalladas del talento humano y personal escolar:
     * desglose por cargo institucional y turno, con conteo de activos, inactivos,
     * distribución por género (varones/hembras) y personal en reposo médico vigente.
     */
    public function getEstadisticasPersonal(): array
    {
        try {
            $sql = "SELECT c.id_cargo,
                           COALESCE(c.nombre, 'Sin cargo asignado') AS cargo,
                           COALESCE(t.nombre, 'General') AS turno,
                           COUNT(DISTINCT e.id_empleado) AS total_personal,
                           COUNT(DISTINCT CASE WHEN p.estado = 'ACTIVO' THEN e.id_empleado END) AS activos,
                           COUNT(DISTINCT CASE WHEN p.estado != 'ACTIVO' THEN e.id_empleado END) AS inactivos,
                           COUNT(DISTINCT CASE WHEN p.sexo = 'M' THEN e.id_empleado END) AS varones,
                           COUNT(DISTINCT CASE WHEN p.sexo = 'F' THEN e.id_empleado END) AS hembras,
                           COUNT(DISTINCT CASE WHEN r.id_reposo IS NOT NULL AND r.estado = 'VIGENTE' AND CURDATE() BETWEEN r.fecha_inicio AND r.fecha_fin THEN e.id_empleado END) AS en_reposo
                      FROM empleado e
                      INNER JOIN persona p ON p.id_persona = e.id_persona
                      LEFT JOIN cargo c ON c.id_cargo = e.id_cargo
                      LEFT JOIN turno t ON t.id_turno = e.id_turno
                      LEFT JOIN reposo_empleado r ON r.id_empleado = e.id_empleado
                                                 AND r.estado = 'VIGENTE'
                                                 AND CURDATE() BETWEEN r.fecha_inicio AND r.fecha_fin
                     GROUP BY c.id_cargo, c.nombre, t.nombre
                     ORDER BY c.nombre ASC, t.nombre ASC";

            $stmt  = $this->pdo->query($sql);
            $filas = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

            $totalGeneral = (int) array_sum(array_column($filas, 'total_personal'));

            foreach ($filas as &$f) {
                $f['id_cargo']       = (int) ($f['id_cargo'] ?? 0);
                $f['total_personal'] = (int) ($f['total_personal'] ?? 0);
                $f['activos']        = (int) ($f['activos'] ?? 0);
                $f['inactivos']      = (int) ($f['inactivos'] ?? 0);
                $f['varones']        = (int) ($f['varones'] ?? 0);
                $f['hembras']        = (int) ($f['hembras'] ?? 0);
                $f['en_reposo']      = (int) ($f['en_reposo'] ?? 0);
                $f['porcentaje']     = $totalGeneral > 0
                    ? round(($f['total_personal'] / $totalGeneral) * 100, 1)
                    : 0.0;
            }

            return $filas;
        } catch (Throwable $e) {
            error_log('DirectivoDashboardModel::getEstadisticasPersonal: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Estadísticas agregadas de la ficha médica de los estudiantes que cursan.
     *
     * No devuelve nombres ni textos clínicos: solo cuántos estudiantes tienen
     * cada dato registrado. Así Dirección y Coordinación ven la cobertura y las
     * alertas sin exponer la salud individual de un alumno.
     *
     * @return array{
     *   resumen: array<string, int>,
     *   por_grado: list<array<string, mixed>>,
     *   tipos_sangre: array<string, int>
     * }
     */
    public function getEstadisticasSalud(?int $idAnio = null): array
    {
        $idAnio = $idAnio !== null && $idAnio > 0 ? $idAnio : AniosEscolares::idEnCurso($this->pdo);

        $resumen = [
            'total_estudiantes'   => 0,
            'fichas'              => 0,
            'con_tipo_sangre'     => 0,
            'con_alergias'        => 0,
            'con_medicamentos'    => 0,
            'con_enfermedades'    => 0,
            'con_condiciones'     => 0,
            'con_observaciones'   => 0,
            'cobertura'           => 0,
            'sin_tipo_sangre'     => 0,
        ];
        $porGrado = [];
        $tiposSangre = array_fill_keys(
            ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'sin_registrar'],
            0
        );

        if ($idAnio <= 0) {
            return ['resumen' => $resumen, 'por_grado' => [], 'tipos_sangre' => $tiposSangre];
        }

        try {
            $sql = "SELECT g.nombre AS grado,
                           COUNT(DISTINCT m.id_estudiante) AS total_estudiantes,
                           COUNT(DISTINCT fm.id_estudiante) AS fichas,
                           COUNT(DISTINCT CASE WHEN NULLIF(TRIM(fm.tipo_sangre), '') IS NOT NULL THEN m.id_estudiante END) AS con_tipo_sangre,
                           COUNT(DISTINCT CASE WHEN NULLIF(TRIM(fm.alergias), '') IS NOT NULL THEN m.id_estudiante END) AS con_alergias,
                           COUNT(DISTINCT CASE WHEN NULLIF(TRIM(fm.medicamentos), '') IS NOT NULL THEN m.id_estudiante END) AS con_medicamentos,
                           COUNT(DISTINCT CASE WHEN NULLIF(TRIM(fm.enfermedades), '') IS NOT NULL THEN m.id_estudiante END) AS con_enfermedades,
                           COUNT(DISTINCT CASE WHEN NULLIF(TRIM(fm.condiciones_especiales), '') IS NOT NULL THEN m.id_estudiante END) AS con_condiciones,
                           COUNT(DISTINCT CASE WHEN NULLIF(TRIM(fm.observaciones), '') IS NOT NULL THEN m.id_estudiante END) AS con_observaciones
                      FROM matricula m
                      INNER JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
                      INNER JOIN grado g ON g.id_grado = ea.id_grado
                      LEFT JOIN ficha_medica fm ON fm.id_estudiante = m.id_estudiante
                     WHERE m.id_anio_escolar = :anio
                       AND " . Matriculas::condicionCursando('m') . "
                     GROUP BY g.id_grado, g.nombre";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['anio' => $idAnio]);
            $porGrado = $stmt->fetchAll(PDO::FETCH_ASSOC);

            usort($porGrado, static function (array $a, array $b): int {
                $ordA = Instituciones::ordinalGrado($a['grado']) ?? PHP_INT_MAX;
                $ordB = Instituciones::ordinalGrado($b['grado']) ?? PHP_INT_MAX;
                return $ordA <=> $ordB;
            });

            foreach ($porGrado as &$fila) {
                foreach (['total_estudiantes', 'fichas', 'con_tipo_sangre', 'con_alergias', 'con_medicamentos', 'con_enfermedades', 'con_condiciones', 'con_observaciones'] as $campo) {
                    $fila[$campo] = (int) ($fila[$campo] ?? 0);
                    $resumen[$campo] += $fila[$campo];
                }
                $fila['cobertura'] = $fila['total_estudiantes'] > 0
                    ? (int) round(($fila['fichas'] / $fila['total_estudiantes']) * 100)
                    : 0;
            }
            unset($fila);

            $resumen['cobertura'] = $resumen['total_estudiantes'] > 0
                ? (int) round(($resumen['fichas'] / $resumen['total_estudiantes']) * 100)
                : 0;

            $sqlTipos = "SELECT COALESCE(NULLIF(TRIM(fm.tipo_sangre), ''), 'sin_registrar') AS tipo,
                                COUNT(DISTINCT m.id_estudiante) AS total
                           FROM matricula m
                           LEFT JOIN ficha_medica fm ON fm.id_estudiante = m.id_estudiante
                          WHERE m.id_anio_escolar = :anio
                            AND " . Matriculas::condicionCursando('m') . "
                          GROUP BY fm.tipo_sangre";
            $stmtTipos = $this->pdo->prepare($sqlTipos);
            $stmtTipos->execute(['anio' => $idAnio]);

            foreach ($stmtTipos->fetchAll(PDO::FETCH_ASSOC) as $tipo) {
                $clave = (string) ($tipo['tipo'] ?? 'sin_registrar');
                if (array_key_exists($clave, $tiposSangre)) {
                    $tiposSangre[$clave] = (int) ($tipo['total'] ?? 0);
                }
            }

            $resumen['sin_tipo_sangre'] = $tiposSangre['sin_registrar'];
        } catch (Throwable $e) {
            error_log('DirectivoDashboardModel::getEstadisticasSalud: ' . $e->getMessage());
        }

        return ['resumen' => $resumen, 'por_grado' => $porGrado, 'tipos_sangre' => $tiposSangre];
    }

    /**
     * Estadísticas agregadas de medidas antropométricas por grado.
     *
     * Solo cuenta valores registrados: un peso o una estatura vacíos no se
     * interpretan como cero. La cobertura exige ambas medidas.
     *
     * @return array{
     *   resumen: array<string, int|float|null>,
     *   por_grado: list<array<string, mixed>>
     * }
     */
    public function getEstadisticasAntropometricas(?int $idAnio = null): array
    {
        $idAnio = $idAnio !== null && $idAnio > 0 ? $idAnio : AniosEscolares::idEnCurso($this->pdo);

        $resumen = [
            'total_estudiantes' => 0,
            'con_peso'          => 0,
            'con_estatura'      => 0,
            'con_ambas'         => 0,
            'cobertura'         => 0,
            'peso_promedio'     => null,
            'peso_min'          => null,
            'peso_max'          => null,
            'estatura_promedio' => null,
            'estatura_min'      => null,
            'estatura_max'      => null,
        ];
        $porGrado = [];

        if ($idAnio <= 0) {
            return ['resumen' => $resumen, 'por_grado' => $porGrado];
        }

        try {
            $sql = "SELECT g.nombre AS grado,
                           COUNT(DISTINCT m.id_estudiante) AS total_estudiantes,
                           COUNT(DISTINCT CASE WHEN tu.peso_kg IS NOT NULL THEN m.id_estudiante END) AS con_peso,
                           COUNT(DISTINCT CASE WHEN tu.estatura_cm IS NOT NULL THEN m.id_estudiante END) AS con_estatura,
                           COUNT(DISTINCT CASE WHEN tu.peso_kg IS NOT NULL AND tu.estatura_cm IS NOT NULL THEN m.id_estudiante END) AS con_ambas,
                           SUM(tu.peso_kg) AS suma_peso,
                           SUM(tu.estatura_cm) AS suma_estatura,
                           MIN(tu.peso_kg) AS peso_min,
                           MAX(tu.peso_kg) AS peso_max,
                           MIN(tu.estatura_cm) AS estatura_min,
                           MAX(tu.estatura_cm) AS estatura_max
                      FROM matricula m
                      INNER JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
                      INNER JOIN grado g ON g.id_grado = ea.id_grado
                      LEFT JOIN talla_uniforme tu ON tu.id_estudiante = m.id_estudiante
                     WHERE m.id_anio_escolar = :anio
                       AND " . Matriculas::condicionCursando('m') . "
                     GROUP BY g.id_grado, g.nombre";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['anio' => $idAnio]);
            $porGrado = $stmt->fetchAll(PDO::FETCH_ASSOC);

            usort($porGrado, static function (array $a, array $b): int {
                $ordA = Instituciones::ordinalGrado($a['grado']) ?? PHP_INT_MAX;
                $ordB = Instituciones::ordinalGrado($b['grado']) ?? PHP_INT_MAX;
                return $ordA <=> $ordB;
            });

            $sumaPeso = 0.0;
            $sumaEstatura = 0.0;
            $pesoMin = null;
            $pesoMax = null;
            $estaturaMin = null;
            $estaturaMax = null;

            foreach ($porGrado as &$fila) {
                $fila['total_estudiantes'] = (int) ($fila['total_estudiantes'] ?? 0);
                $fila['con_peso'] = (int) ($fila['con_peso'] ?? 0);
                $fila['con_estatura'] = (int) ($fila['con_estatura'] ?? 0);
                $fila['con_ambas'] = (int) ($fila['con_ambas'] ?? 0);
                $fila['suma_peso'] = (float) ($fila['suma_peso'] ?? 0);
                $fila['suma_estatura'] = (float) ($fila['suma_estatura'] ?? 0);
                $fila['peso_min'] = $fila['peso_min'] !== null ? (float) $fila['peso_min'] : null;
                $fila['peso_max'] = $fila['peso_max'] !== null ? (float) $fila['peso_max'] : null;
                $fila['estatura_min'] = $fila['estatura_min'] !== null ? (float) $fila['estatura_min'] : null;
                $fila['estatura_max'] = $fila['estatura_max'] !== null ? (float) $fila['estatura_max'] : null;
                $fila['peso_promedio'] = $fila['con_peso'] > 0 ? $fila['suma_peso'] / $fila['con_peso'] : null;
                $fila['estatura_promedio'] = $fila['con_estatura'] > 0 ? $fila['suma_estatura'] / $fila['con_estatura'] : null;
                $fila['cobertura'] = $fila['total_estudiantes'] > 0
                    ? (int) round(($fila['con_ambas'] / $fila['total_estudiantes']) * 100)
                    : 0;

                $resumen['total_estudiantes'] += $fila['total_estudiantes'];
                $resumen['con_peso'] += $fila['con_peso'];
                $resumen['con_estatura'] += $fila['con_estatura'];
                $resumen['con_ambas'] += $fila['con_ambas'];
                $sumaPeso += $fila['suma_peso'];
                $sumaEstatura += $fila['suma_estatura'];

                if ($fila['peso_min'] !== null && ($pesoMin === null || $fila['peso_min'] < $pesoMin)) {
                    $pesoMin = $fila['peso_min'];
                }
                if ($fila['peso_max'] !== null && ($pesoMax === null || $fila['peso_max'] > $pesoMax)) {
                    $pesoMax = $fila['peso_max'];
                }
                if ($fila['estatura_min'] !== null && ($estaturaMin === null || $fila['estatura_min'] < $estaturaMin)) {
                    $estaturaMin = $fila['estatura_min'];
                }
                if ($fila['estatura_max'] !== null && ($estaturaMax === null || $fila['estatura_max'] > $estaturaMax)) {
                    $estaturaMax = $fila['estatura_max'];
                }
            }
            unset($fila);

            $resumen['cobertura'] = $resumen['total_estudiantes'] > 0
                ? (int) round(($resumen['con_ambas'] / $resumen['total_estudiantes']) * 100)
                : 0;
            $resumen['peso_promedio'] = $resumen['con_peso'] > 0 ? $sumaPeso / $resumen['con_peso'] : null;
            $resumen['estatura_promedio'] = $resumen['con_estatura'] > 0 ? $sumaEstatura / $resumen['con_estatura'] : null;
            $resumen['peso_min'] = $pesoMin;
            $resumen['peso_max'] = $pesoMax;
            $resumen['estatura_min'] = $estaturaMin;
            $resumen['estatura_max'] = $estaturaMax;
        } catch (Throwable $e) {
            error_log('DirectivoDashboardModel::getEstadisticasAntropometricas: ' . $e->getMessage());
        }

        return ['resumen' => $resumen, 'por_grado' => $porGrado];
    }

    /**
     * Estadísticas agregadas de tallas de uniforme por grado y por prenda.
     *
     * Solo devuelve conteos: no expone la talla de un estudiante concreto. Las
     * tallas vacías cuentan como "sin_registrar"; las que ya no están en el
     * catálogo (datos históricos) se conservan para no ocultarlas.
     *
     * @return array{
     *   resumen: array<string, int>,
     *   por_grado: list<array<string, mixed>>,
     *   por_prenda: array<string, array{titulo: string, tallas: array<string, int>, total: int}>
     * }
     */
    public function getEstadisticasTallas(?int $idAnio = null): array
    {
        $idAnio = $idAnio !== null && $idAnio > 0 ? $idAnio : AniosEscolares::idEnCurso($this->pdo);

        $resumen = [
            'total_estudiantes' => 0,
            'con_camisa'        => 0,
            'con_pantalon'      => 0,
            'con_zapato'        => 0,
            'con_todas'         => 0,
            'cobertura'         => 0,
        ];
        $porGrado = [];
        $porPrenda = [
            'camisa'   => ['titulo' => 'Camisa',  'tallas' => [], 'total' => 0],
            'pantalon' => ['titulo' => 'Pantalón', 'tallas' => [], 'total' => 0],
            'zapato'   => ['titulo' => 'Calzado',  'tallas' => [], 'total' => 0],
        ];
        $catalogos = [
            'camisa'   => Tallas::CAMISA,
            'pantalon' => Tallas::PANTALON,
            'zapato'   => Tallas::zapato(),
        ];
        foreach ($catalogos as $prenda => $tallas) {
            $porPrenda[$prenda]['tallas'] = array_fill_keys($tallas, 0) + ['sin_registrar' => 0];
        }

        if ($idAnio <= 0) {
            return ['resumen' => $resumen, 'por_grado' => $porGrado, 'por_prenda' => $porPrenda];
        }

        try {
            $sql = "SELECT g.nombre AS grado,
                           COUNT(DISTINCT m.id_estudiante) AS total_estudiantes,
                           COUNT(DISTINCT CASE WHEN NULLIF(TRIM(tu.talla_camisa), '') IS NOT NULL THEN m.id_estudiante END) AS con_camisa,
                           COUNT(DISTINCT CASE WHEN NULLIF(TRIM(tu.talla_pantalon), '') IS NOT NULL THEN m.id_estudiante END) AS con_pantalon,
                           COUNT(DISTINCT CASE WHEN NULLIF(TRIM(tu.talla_zapato), '') IS NOT NULL THEN m.id_estudiante END) AS con_zapato,
                           COUNT(DISTINCT CASE WHEN NULLIF(TRIM(tu.talla_camisa), '') IS NOT NULL
                                                 AND NULLIF(TRIM(tu.talla_pantalon), '') IS NOT NULL
                                                 AND NULLIF(TRIM(tu.talla_zapato), '') IS NOT NULL
                                                THEN m.id_estudiante END) AS con_todas
                      FROM matricula m
                      INNER JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
                      INNER JOIN grado g ON g.id_grado = ea.id_grado
                      LEFT JOIN talla_uniforme tu ON tu.id_estudiante = m.id_estudiante
                     WHERE m.id_anio_escolar = :anio
                       AND " . Matriculas::condicionCursando('m') . "
                     GROUP BY g.id_grado, g.nombre";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['anio' => $idAnio]);
            $porGrado = $stmt->fetchAll(PDO::FETCH_ASSOC);

            usort($porGrado, static function (array $a, array $b): int {
                $ordA = Instituciones::ordinalGrado($a['grado']) ?? PHP_INT_MAX;
                $ordB = Instituciones::ordinalGrado($b['grado']) ?? PHP_INT_MAX;
                return $ordA <=> $ordB;
            });

            foreach ($porGrado as &$fila) {
                foreach (['total_estudiantes', 'con_camisa', 'con_pantalon', 'con_zapato', 'con_todas'] as $campo) {
                    $fila[$campo] = (int) ($fila[$campo] ?? 0);
                    $resumen[$campo] += $fila[$campo];
                }
                $fila['cobertura'] = $fila['total_estudiantes'] > 0
                    ? (int) round(($fila['con_todas'] / $fila['total_estudiantes']) * 100)
                    : 0;
            }
            unset($fila);

            $resumen['cobertura'] = $resumen['total_estudiantes'] > 0
                ? (int) round(($resumen['con_todas'] / $resumen['total_estudiantes']) * 100)
                : 0;

            $columnas = ['camisa' => 'talla_camisa', 'pantalon' => 'talla_pantalon', 'zapato' => 'talla_zapato'];
            foreach ($columnas as $prenda => $columna) {
                $sqlPrenda = "SELECT COALESCE(NULLIF(TRIM(tu.{$columna}), ''), 'sin_registrar') AS talla,
                                     COUNT(DISTINCT m.id_estudiante) AS total
                                FROM matricula m
                                INNER JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
                                LEFT JOIN talla_uniforme tu ON tu.id_estudiante = m.id_estudiante
                               WHERE m.id_anio_escolar = :anio
                                 AND " . Matriculas::condicionCursando('m') . "
                               GROUP BY COALESCE(NULLIF(TRIM(tu.{$columna}), ''), 'sin_registrar')";

                $stmtPrenda = $this->pdo->prepare($sqlPrenda);
                $stmtPrenda->execute(['anio' => $idAnio]);

                $extras = [];
                foreach ($stmtPrenda->fetchAll(PDO::FETCH_ASSOC) as $fila) {
                    $talla = (string) ($fila['talla'] ?? 'sin_registrar');
                    $total = (int) ($fila['total'] ?? 0);
                    if ($talla === 'sin_registrar') {
                        $porPrenda[$prenda]['tallas']['sin_registrar'] = $total;
                    } elseif (array_key_exists($talla, $porPrenda[$prenda]['tallas'])) {
                        $porPrenda[$prenda]['tallas'][$talla] = $total;
                    } else {
                        $extras[$talla] = $total;
                    }
                }

                if ($extras !== []) {
                    $sinRegistrar = $porPrenda[$prenda]['tallas']['sin_registrar'] ?? 0;
                    unset($porPrenda[$prenda]['tallas']['sin_registrar']);
                    foreach ($extras as $talla => $total) {
                        $porPrenda[$prenda]['tallas'][$talla] = $total;
                    }
                    $porPrenda[$prenda]['tallas']['sin_registrar'] = $sinRegistrar;
                }

                $porPrenda[$prenda]['total'] = $resumen['con_' . $prenda] ?? 0;
            }
        } catch (Throwable $e) {
            error_log('DirectivoDashboardModel::getEstadisticasTallas: ' . $e->getMessage());
        }

        return ['resumen' => $resumen, 'por_grado' => $porGrado, 'por_prenda' => $porPrenda];
    }

    /**
     * Distribución de matrícula organizada por grado y sexo para gráficos y tablas.
     */
    public function getMatriculaPorGradoYSexo(?int $idAnio = null): array
    {
        $idAnio = $idAnio !== null && $idAnio > 0 ? $idAnio : AniosEscolares::idEnCurso($this->pdo);

        if ($idAnio <= 0) {
            return [];
        }

        try {
            $sql = "SELECT g.id_grado,
                           g.nombre AS grado,
                           COUNT(DISTINCT ea.id_estructura_academica) AS total_secciones,
                           COALESCE(SUM(ea.capacidad_maxima), 0) AS capacidad,
                           COUNT(DISTINCT m.id_estudiante) AS total_estudiantes,
                           COUNT(DISTINCT CASE WHEN p.sexo = 'M' THEN m.id_estudiante END) AS varones,
                           COUNT(DISTINCT CASE WHEN p.sexo = 'F' THEN m.id_estudiante END) AS hembras
                      FROM estructura_academica ea
                      INNER JOIN grado g ON g.id_grado = ea.id_grado
                      LEFT JOIN matricula m ON m.id_estructura_academica = ea.id_estructura_academica
                                           AND " . Matriculas::condicionCursando('m') . "
                      LEFT JOIN estudiante e ON e.id_estudiante = m.id_estudiante
                      LEFT JOIN persona p ON p.id_persona = e.id_persona
                     WHERE ea.id_anio_escolar = :anio
                     GROUP BY g.id_grado, g.nombre";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['anio' => $idAnio]);
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($filas as &$fila) {
                $fila['id_grado']          = (int) $fila['id_grado'];
                $fila['total_secciones']   = (int) $fila['total_secciones'];
                $fila['capacidad']         = (int) $fila['capacidad'];
                $fila['total_estudiantes'] = (int) $fila['total_estudiantes'];
                $fila['varones']           = (int) $fila['varones'];
                $fila['hembras']           = (int) $fila['hembras'];
            }
            unset($fila);

            usort($filas, static function (array $a, array $b): int {
                $ordA = Instituciones::ordinalGrado($a['grado']) ?? PHP_INT_MAX;
                $ordB = Instituciones::ordinalGrado($b['grado']) ?? PHP_INT_MAX;
                return $ordA <=> $ordB;
            });

            return $filas;
        } catch (Throwable $e) {
            error_log('DirectivoDashboardModel::getMatriculaPorGradoYSexo: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Distribución de estudiantes por edades cumplidas y sexo (Estadístico oficial).
     */
    public function getDistribucionPorEdadYSexo(?int $idAnio = null): array
    {
        $idAnio = $idAnio !== null && $idAnio > 0 ? $idAnio : AniosEscolares::idEnCurso($this->pdo);

        if ($idAnio <= 0) {
            return [];
        }

        try {
            $sql = "SELECT TIMESTAMPDIFF(YEAR, p.fecha_nacimiento, CURDATE()) AS edad,
                           COUNT(DISTINCT CASE WHEN p.sexo = 'M' THEN m.id_estudiante END) AS varones,
                           COUNT(DISTINCT CASE WHEN p.sexo = 'F' THEN m.id_estudiante END) AS hembras,
                           COUNT(DISTINCT m.id_estudiante) AS total
                      FROM matricula m
                      INNER JOIN estudiante e ON e.id_estudiante = m.id_estudiante
                      INNER JOIN persona p ON p.id_persona = e.id_persona
                     WHERE m.id_anio_escolar = :anio
                       AND p.fecha_nacimiento IS NOT NULL
                       AND " . Matriculas::condicionCursando('m') . "
                     GROUP BY edad
                     ORDER BY edad ASC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['anio' => $idAnio]);
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($filas as &$f) {
                $f['edad']    = (int) $f['edad'];
                $f['varones'] = (int) $f['varones'];
                $f['hembras'] = (int) $f['hembras'];
                $f['total']   = (int) $f['total'];
            }
            unset($f);

            return $filas;
        } catch (Throwable $e) {
            error_log('DirectivoDashboardModel::getDistribucionPorEdadYSexo: ' . $e->getMessage());
            return [];
        }
    }
    /**
     * Resultado por sección del año indicado (por defecto, el año activo).
     * Incluye secciones sin estudiantes y sin docente para que la ausencia se vea.
     */
    public function getResultadosPorSeccion(?int $idAnio = null): array
    {
        $idAnio = $idAnio !== null && $idAnio > 0
            ? $idAnio
            : AniosEscolares::idEnCurso($this->pdo);

        if ($idAnio <= 0) {
            return [];
        }

        try {
            $stmt = $this->pdo->prepare(
                "SELECT ea.id_estructura_academica AS id,
                        g.nombre AS grado,
                        s.nombre AS seccion,
                        t.nombre AS turno,
                        ae.nombre_periodo AS anio_escolar,
                        COUNT(DISTINCT m.id_estudiante) AS total_estudiantes,
                        COUNT(DISTINCT CASE WHEN ra.literal IS NOT NULL THEN ra.id_estudiante END) AS total_cargados,
                        COUNT(DISTINCT CASE WHEN m.id_estudiante IS NOT NULL AND ra.literal IS NULL THEN m.id_estudiante END) AS total_pendientes,
                        COUNT(DISTINCT CASE WHEN ra.estado_resultado = 'PROMOVIDO' OR (ra.literal IN ('A','B','C','D') AND ra.estado_resultado = 'PENDIENTE') THEN ra.id_estudiante END) AS total_promovidos,
                        COUNT(DISTINCT CASE WHEN ra.estado_resultado = 'NO_PROMOVIDO' OR (ra.literal = 'E' AND ra.estado_resultado = 'PENDIENTE') THEN ra.id_estudiante END) AS total_no_promovidos,
                        MAX(NULLIF(TRIM(CONCAT_WS(' ', p_doc.primer_nombre, p_doc.primer_apellido)), '')) AS docente_nombre
                   FROM estructura_academica ea
                   INNER JOIN anio_escolar ae ON ae.id_anio_escolar = ea.id_anio_escolar
                   INNER JOIN grado g ON g.id_grado = ea.id_grado
                   INNER JOIN seccion s ON s.id_seccion = ea.id_seccion
                   INNER JOIN turno t ON t.id_turno = ea.id_turno
                   LEFT JOIN matricula m ON m.id_estructura_academica = ea.id_estructura_academica
                                        AND m.tipo IN ('INSCRIPCION', 'RATIFICACION', 'REINGRESO')
                                        AND " . Matriculas::condicionCursando('m') . "
                   LEFT JOIN resultado_academico ra ON ra.id_estudiante = m.id_estudiante
                                                   AND ra.id_anio_escolar = ea.id_anio_escolar
                   LEFT JOIN asignacion_docente ad ON ad.id_estructura_academica = ea.id_estructura_academica
                                                  AND (ad.fecha_fin IS NULL OR ad.fecha_fin >= CURDATE())
                   LEFT JOIN docente doc ON doc.id_docente = ad.id_docente
                   LEFT JOIN empleado emp ON emp.id_empleado = doc.id_empleado
                   LEFT JOIN persona p_doc ON p_doc.id_persona = emp.id_persona
                  WHERE ea.id_anio_escolar = :id_anio
                  GROUP BY ea.id_estructura_academica, g.nombre, s.nombre, t.nombre, ae.nombre_periodo"
            );
            $stmt->execute(['id_anio' => $idAnio]);
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('DirectivoDashboardModel::getResultadosPorSeccion: ' . $e->getMessage());
            return [];
        }

        foreach ($filas as &$fila) {
            $fila['id']                = (int) $fila['id'];
            $fila['total_estudiantes'] = (int) $fila['total_estudiantes'];
            $fila['total_cargados']    = (int) $fila['total_cargados'];
            $fila['total_pendientes']  = (int) $fila['total_pendientes'];
            $fila['total_promovidos']  = (int) $fila['total_promovidos'];
            $fila['total_no_promovidos'] = (int) $fila['total_no_promovidos'];
            $fila['docente_nombre']    = trim((string) ($fila['docente_nombre'] ?? ''));
            $fila['turno']             = trim((string) ($fila['turno'] ?? ''));
            // Sección sin estudiantes: avance "—", no 0%
            $fila['porcentaje_carga']  = $fila['total_estudiantes'] > 0
                ? (int) round(($fila['total_cargados'] / $fila['total_estudiantes']) * 100)
                : -1; // -1 indica "sin estudiantes"
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
     * Resumen acumulado de rendimiento y promoción para KPIs.
     */
    public function getResumenRendimiento(?int $idAnio = null): array
    {
        $secciones = $this->getResultadosPorSeccion($idAnio);
        $totalEstudiantes = 0;
        $totalCargados = 0;
        $totalPromovidos = 0;
        $totalNoPromovidos = 0;
        $totalPendientes = 0;

        foreach ($secciones as $sec) {
            $totalEstudiantes   += $sec['total_estudiantes'];
            $totalCargados      += $sec['total_cargados'];
            $totalPromovidos    += $sec['total_promovidos'];
            $totalNoPromovidos  += $sec['total_no_promovidos'];
            $totalPendientes    += $sec['total_pendientes'];
        }

        $porcentajeCarga = $totalEstudiantes > 0
            ? (int) round(($totalCargados / $totalEstudiantes) * 100)
            : 0;
        $porcentajePromocion = $totalCargados > 0
            ? (int) round(($totalPromovidos / $totalCargados) * 100)
            : 0;

        return [
            'total_estudiantes'    => $totalEstudiantes,
            'total_cargados'       => $totalCargados,
            'total_promovidos'     => $totalPromovidos,
            'total_no_promovidos'  => $totalNoPromovidos,
            'total_pendientes'     => $totalPendientes,
            'porcentaje_carga'     => $porcentajeCarga,
            'porcentaje_promocion' => $porcentajePromocion,
            'total_secciones'      => count($secciones),
        ];
    }

    /** @var array<int, int> Caché de retiros pendientes indexada por id_anio_escolar */
    private array $retirosPendientesCache = [];

    /**
     * Retorna el número de solicitudes de retiro en estado PENDIENTE
     * para el año escolar indicado (o el activo).
     */
    public function getRetirosPendientes(?int $idAnio = null): int
    {
        $idAnioResuelto = $idAnio !== null && $idAnio > 0 ? $idAnio : AniosEscolares::idEnCurso($this->pdo);

        if ($idAnioResuelto <= 0) {
            return 0;
        }

        if (isset($this->retirosPendientesCache[$idAnioResuelto])) {
            return $this->retirosPendientesCache[$idAnioResuelto];
        }

        try {
            $sql = "SELECT COUNT(DISTINCT r.id_retiro)
                      FROM retiro r
                     INNER JOIN matricula m ON m.id_matricula = r.id_matricula
                     WHERE r.estado_solicitud = 'PENDIENTE'
                       AND m.id_anio_escolar = :anio";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['anio' => $idAnioResuelto]);
            $total = (int) $stmt->fetchColumn();

            $this->retirosPendientesCache[$idAnioResuelto] = $total;

            return $total;
        } catch (Throwable $e) {
            error_log('DirectivoDashboardModel::getRetirosPendientes: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Alertas accionables para Dirección y Coordinación: retiros pendientes de resolución
     * (solo para directivo), secciones con estudiantes y sin docente, y literales pendientes.
     *
     * @return list<array<string, mixed>>
     */
    public function getAlertas(?int $idAnio = null, ?string $rol = null): array
    {
        $alertas = [];

        // 1. Solicitudes de retiro pendientes de resolución (exclusivo de Dirección)
        if ($rol === null || $rol === 'directivo') {
            $retirosPendientes = $this->getRetirosPendientes($idAnio);
            if ($retirosPendientes > 0) {
                $alertas[] = [
                    'tipo'     => 'retiros_pendientes',
                    'nivel'    => 'warning',
                    'cantidad' => $retirosPendientes,
                    'seccion'  => 'Retiros',
                    'detalle'  => $retirosPendientes . ' solicitud' . ($retirosPendientes === 1 ? '' : 'es') . ' de retiro escolar pendiente' . ($retirosPendientes === 1 ? '' : 's') . ' por resolver.',
                ];
            }
        }

        // 2. Aulas sin docente titular y seguimiento condicional de literales (Acción ejecutiva de Dirección)
        if ($rol === null || $rol === 'directivo') {
            $anioInfo = $this->getAnioActivo();
            $fechaCierre = $anioInfo['fecha_fin'] ?? null;
            $enVentanaCierre = false;
            if ($fechaCierre !== null && trim($fechaCierre) !== '') {
                $tsCierre = strtotime($fechaCierre);
                if ($tsCierre !== false) {
                    // Ventana de cierre: 60 días previos a la fecha de fin de año
                    $tsInicioVentana = strtotime('-60 days', $tsCierre);
                    $enVentanaCierre = time() >= $tsInicioVentana;
                }
            }

            foreach ($this->getResultadosPorSeccion($idAnio) as $seccion) {
                $etiqueta = trim($seccion['grado'] . ' ' . $seccion['seccion']);

                if ($seccion['docente_nombre'] === '' && $seccion['total_estudiantes'] > 0) {
                    // Si no hay docente, la alerta prioritaria y raíz es asignar titular
                    $alertas[] = [
                        'tipo'     => 'sin_docente',
                        'nivel'    => 'warning',
                        'id'       => $seccion['id'],
                        'seccion'  => $etiqueta,
                        'detalle'  => 'La sección ' . $etiqueta . ' no tiene docente asignado.',
                    ];
                } elseif ($seccion['docente_nombre'] !== '' && $seccion['total_pendientes'] > 0) {
                    // Solo alertar de literales si la sección SÍ tiene docente Y (ya empezó la carga o estamos en fin de ciclo)
                    if ($seccion['total_cargados'] > 0 || $enVentanaCierre) {
                        $alertas[] = [
                            'tipo'     => 'sin_literal',
                            'nivel'    => 'info',
                            'id'       => $seccion['id'],
                            'seccion'  => $etiqueta,
                            'cantidad' => $seccion['total_pendientes'],
                            'detalle'  => $seccion['total_pendientes'] . ' estudiante'
                                . ($seccion['total_pendientes'] === 1 ? '' : 's')
                                . ' sin literal en ' . $etiqueta . '.',
                        ];
                    }
                }
            }
        }

        return $alertas;
    }
}
