<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\ConQuienVive;
use App\Support\Instituciones;
use App\Support\Matriculas;
use App\Traits\OpcionesCatalogoTrait;
use PDO;
use Throwable;

/**
 * ReporteModel Consultas consolidadas para reportes institucionales, estadísticos y
 * nóminas: - Estadístico general oficial (Matrícula inicial / mensual por grado,
 * sexo y edad). - Distribución académica y asignación docente.
 */
class ReporteModel extends Model
{
    use OpcionesCatalogoTrait;

    /**
     * Grados registrados ordenados según el orden pedagógico oficial.
     */
    public function getGrados(): array
    {
        try {
            $stmt = $this->pdo->query("SELECT id_grado, nombre FROM grado WHERE estado = 'ACTIVO'");
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            usort($filas, static function (array $a, array $b): int {
                $ordA = Instituciones::ordinalGrado($a['nombre']) ?? PHP_INT_MAX;
                $ordB = Instituciones::ordinalGrado($b['nombre']) ?? PHP_INT_MAX;
                return $ordA <=> $ordB;
            });

            return $filas;
        } catch (Throwable $e) {
            error_log('ReporteModel::getGrados: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Secciones activas.
     */
    public function getSecciones(): array
    {
        try {
            return $this->pdo->query("SELECT id_seccion, nombre FROM seccion WHERE estado = 'ACTIVO' ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('ReporteModel::getSecciones: ' . $e->getMessage());
            return [];
        }
    }

    /**
 * Matrícula oficial desagregada por sección, sexo y edad cumplida. Estructura
 * estándar del Ministerio del Poder Popular para la Educación (MPPE / CDCE).
 *
 * @return array{
 */
    public function getEstadisticoGeneral(int $idAnio): array
    {
        $vacio = ['edades' => [], 'filas' => [], 'totales' => []];

        if ($idAnio <= 0) {
            return $vacio;
        }

        try {
            // 1. Obtener todas las secciones configuradas en el período
            $sqlSecciones = "SELECT ea.id_estructura_academica,
                                    g.nombre AS grado,
                                    s.nombre AS seccion,
                                    t.nombre AS turno,
                                    ea.capacidad_maxima
                               FROM estructura_academica ea
                              INNER JOIN grado g ON g.id_grado = ea.id_grado
                              INNER JOIN seccion s ON s.id_seccion = ea.id_seccion
                              INNER JOIN turno t ON t.id_turno = ea.id_turno
                              WHERE ea.id_anio_escolar = :anio";

            $stmtSec = $this->pdo->prepare($sqlSecciones);
            $stmtSec->execute(['anio' => $idAnio]);
            $secciones = $stmtSec->fetchAll(PDO::FETCH_ASSOC);

            if ($secciones === []) {
                return $vacio;
            }

            usort($secciones, static function (array $a, array $b): int {
                $ordA = Instituciones::ordinalGrado($a['grado']) ?? PHP_INT_MAX;
                $ordB = Instituciones::ordinalGrado($b['grado']) ?? PHP_INT_MAX;
                if ($ordA !== $ordB) {
                    return $ordA <=> $ordB;
                }
                return strcmp((string) $a['seccion'], (string) $b['seccion']);
            });

            // 2. Traer los estudiantes activos agrupados por sección, sexo y edad
            $sqlAlumnos = "SELECT m.id_estructura_academica,
                                  p.sexo,
                                  TIMESTAMPDIFF(YEAR, p.fecha_nacimiento, CURDATE()) AS edad,
                                  COUNT(DISTINCT m.id_estudiante) AS cantidad
                             FROM matricula m
                            INNER JOIN estudiante e ON e.id_estudiante = m.id_estudiante
                            INNER JOIN persona p ON p.id_persona = e.id_persona
                            WHERE m.id_anio_escolar = :anio
                              AND p.fecha_nacimiento IS NOT NULL
                              AND " . Matriculas::condicionCursando('m') . "
                            GROUP BY m.id_estructura_academica, p.sexo, edad";

            $stmtAl = $this->pdo->prepare($sqlAlumnos);
            $stmtAl->execute(['anio' => $idAnio]);
            $alumnos = $stmtAl->fetchAll(PDO::FETCH_ASSOC);

            // Mapeo por estructura
            $mapa = [];
            $edadesEncontradas = [];

            foreach ($alumnos as $al) {
                $idEst = (int) $al['id_estructura_academica'];
                $sexo  = (string) $al['sexo'];
                $edad  = (int) $al['edad'];
                $cant  = (int) $al['cantidad'];

                $mapa[$idEst][$sexo][$edad] = ($mapa[$idEst][$sexo][$edad] ?? 0) + $cant;
                $edadesEncontradas[$edad] = true;
            }

            // Rango de edades ordenado
            $edades = array_keys($edadesEncontradas);
            sort($edades);
            if ($edades === []) {
                $edades = [6, 7, 8, 9, 10, 11, 12];
            }

            // Construir sábana
            $filasFinales = [];
            $totales = [
                'varones' => 0,
                'hembras' => 0,
                'total'   => 0,
            ];
            foreach ($edades as $ed) {
                $totales['m_' . $ed] = 0;
                $totales['f_' . $ed] = 0;
                $totales['t_' . $ed] = 0;
            }

            foreach ($secciones as $sec) {
                $idEst = (int) $sec['id_estructura_academica'];
                $conteoM = 0;
                $conteoF = 0;
                $desgloseEdad = [];

                foreach ($edades as $ed) {
                    $m = $mapa[$idEst]['M'][$ed] ?? 0;
                    $f = $mapa[$idEst]['F'][$ed] ?? 0;
                    $t = $m + $f;

                    $desgloseEdad[$ed] = [
                        'm' => $m,
                        'f' => $f,
                        't' => $t,
                    ];

                    $conteoM += $m;
                    $conteoF += $f;

                    $totales['m_' . $ed] += $m;
                    $totales['f_' . $ed] += $f;
                    $totales['t_' . $ed] += $t;
                }

                $totalSec = $conteoM + $conteoF;
                $totales['varones'] += $conteoM;
                $totales['hembras'] += $conteoF;
                $totales['total']   += $totalSec;

                $filasFinales[] = [
                    'id'            => $idEst,
                    'grado'         => $sec['grado'],
                    'seccion'       => $sec['seccion'],
                    'turno'         => $sec['turno'],
                    'capacidad'     => (int) $sec['capacidad_maxima'],
                    'varones'       => $conteoM,
                    'hembras'       => $conteoF,
                    'total'         => $totalSec,
                    'edades'        => $desgloseEdad,
                ];
            }

            return [
                'edades'  => $edades,
                'filas'   => $filasFinales,
                'totales' => $totales,
            ];
        } catch (Throwable $e) {
            error_log('ReporteModel::getEstadisticoGeneral: ' . $e->getMessage());
            return $vacio;
        }
    }

    /**
     * Listado general de estudiantes filtrable por grado y sección,
     * con datos personales del estudiante y de su representante legal.
     */
    public function getListadoEstudiantes(int $idAnio, ?int $idGrado = null, ?int $idSeccion = null): array
    {
        if ($idAnio <= 0) {
            return [];
        }

        try {
            $sql = "SELECT e.id_estudiante,
                           p.tipo_documento,
                           p.numero_documento,
                           p.primer_nombre,
                           p.segundo_nombre,
                           p.primer_apellido,
                           p.segundo_apellido,
                           CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS nombre_completo,
                           p.sexo,
                           p.fecha_nacimiento,
                           TIMESTAMPDIFF(YEAR, p.fecha_nacimiento, CURDATE()) AS edad,
                           " . ConQuienVive::expresionSql('e.id_estudiante') . " AS con_quien_vive,
                           g.id_grado,
                           g.nombre AS grado,
                           s.id_seccion,
                           s.nombre AS seccion,
                           t.nombre AS turno,
                           m.tipo AS tipo_matricula,
                           m.fecha_matricula,
                           -- Representante legal: uno solo aunque haya varios activos.
                           prep.tipo_documento AS rep_tipo_doc,
                           prep.numero_documento AS rep_num_doc,
                           CONCAT_WS(' ', prep.primer_nombre, prep.primer_apellido) AS rep_nombre,
                           prep.es_fallecido AS rep_fallecido,
                           CONCAT_WS('-', crep.codigo_telefono_principal, crep.numero_telefono_principal) AS rep_telefono,
                           COALESCE(rc.rep_vigentes, 0) AS rep_vigentes,
                           COALESCE(rc.rep_fallecidos, 0) AS rep_fallecidos,
                           COALESCE(rc.rep_total, 0) AS rep_total
                      FROM matricula m
                     INNER JOIN estudiante e ON e.id_estudiante = m.id_estudiante
                     INNER JOIN persona p ON p.id_persona = e.id_persona
                     INNER JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
                     INNER JOIN grado g ON g.id_grado = ea.id_grado
                     INNER JOIN seccion s ON s.id_seccion = ea.id_seccion
                     INNER JOIN turno t ON t.id_turno = ea.id_turno
                      LEFT JOIN persona_estudiante pe ON pe.id_persona_estudiante = (
                          SELECT pe2.id_persona_estudiante
                            FROM persona_estudiante pe2
                            INNER JOIN persona prep2 ON prep2.id_persona = pe2.id_persona
                           WHERE pe2.id_estudiante = e.id_estudiante
                             AND pe2.es_representante_legal = 1
                             AND pe2.estado_relacion = 'ACTIVO'
                           ORDER BY prep2.es_fallecido ASC,
                                    pe2.id_persona_estudiante ASC
                           LIMIT 1
                      )
                      LEFT JOIN (
                          SELECT pe.id_estudiante,
                                 COUNT(CASE WHEN pe.es_representante_legal = 1
                                             AND pe.estado_relacion = 'ACTIVO'
                                             AND p.es_fallecido = 0 THEN 1 END) AS rep_vigentes,
                                 COUNT(CASE WHEN pe.es_representante_legal = 1
                                             AND pe.estado_relacion = 'ACTIVO'
                                             AND p.es_fallecido = 1 THEN 1 END) AS rep_fallecidos,
                                 COUNT(CASE WHEN pe.es_representante_legal = 1
                                             AND pe.estado_relacion = 'ACTIVO' THEN 1 END) AS rep_total
                            FROM persona_estudiante pe
                            INNER JOIN persona p ON p.id_persona = pe.id_persona
                           GROUP BY pe.id_estudiante
                      ) rc ON rc.id_estudiante = e.id_estudiante
                      LEFT JOIN persona prep ON prep.id_persona = pe.id_persona
                      LEFT JOIN contacto crep ON crep.id_persona = prep.id_persona
                     WHERE m.id_anio_escolar = :anio
                       AND " . Matriculas::condicionCursando('m');

            $params = ['anio' => $idAnio];

            if ($idGrado !== null && $idGrado > 0) {
                $sql .= " AND g.id_grado = :grado";
                $params['grado'] = $idGrado;
            }
            if ($idSeccion !== null && $idSeccion > 0) {
                $sql .= " AND s.id_seccion = :seccion";
                $params['seccion'] = $idSeccion;
            }

            $sql .= " ORDER BY g.id_grado ASC, s.nombre ASC, p.primer_apellido ASC, p.primer_nombre ASC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            usort($filas, static function (array $a, array $b): int {
                $ordA = Instituciones::ordinalGrado($a['grado']) ?? PHP_INT_MAX;
                $ordB = Instituciones::ordinalGrado($b['grado']) ?? PHP_INT_MAX;
                if ($ordA !== $ordB) {
                    return $ordA <=> $ordB;
                }
                $cmpSec = strcmp((string) $a['seccion'], (string) $b['seccion']);
                if ($cmpSec !== 0) {
                    return $cmpSec;
                }
                return strcmp((string) $a['nombre_completo'], (string) $b['nombre_completo']);
            });

            return $filas;
        } catch (Throwable $e) {
            error_log('ReporteModel::getListadoEstudiantes: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Distribución de aulas, secciones, capacidad, matrícula cursando y docente titular.
     */
    public function getDistribucionAcademica(int $idAnio): array
    {
        if ($idAnio <= 0) {
            return [];
        }

        try {
            $sql = "SELECT ea.id_estructura_academica,
                           g.nombre AS grado,
                           s.nombre AS seccion,
                           t.nombre AS turno,
                           ea.capacidad_maxima,
                           COUNT(DISTINCT m.id_estudiante) AS total_matricula,
                           COUNT(DISTINCT CASE WHEN p_est.sexo = 'M' THEN m.id_estudiante END) AS varones,
                           COUNT(DISTINCT CASE WHEN p_est.sexo = 'F' THEN m.id_estudiante END) AS hembras,
                           p_doc.tipo_documento AS doc_tipo_doc,
                           p_doc.numero_documento AS doc_num_doc,
                           CONCAT_WS(' ', p_doc.primer_nombre, p_doc.primer_apellido) AS docente_titular,
                           ad.id_asignacion
                      FROM estructura_academica ea
                     INNER JOIN grado g ON g.id_grado = ea.id_grado
                     INNER JOIN seccion s ON s.id_seccion = ea.id_seccion
                     INNER JOIN turno t ON t.id_turno = ea.id_turno
                      LEFT JOIN matricula m ON m.id_estructura_academica = ea.id_estructura_academica
                                           AND " . Matriculas::condicionCursando('m') . "
                      LEFT JOIN estudiante est ON est.id_estudiante = m.id_estudiante
                      LEFT JOIN persona p_est ON p_est.id_persona = est.id_persona
                      LEFT JOIN asignacion_docente ad ON ad.id_estructura_academica = ea.id_estructura_academica
                                                     AND (ad.fecha_fin IS NULL OR ad.fecha_fin >= CURDATE())
                      LEFT JOIN docente doc ON doc.id_docente = ad.id_docente
                      LEFT JOIN empleado emp ON emp.id_empleado = doc.id_empleado
                      LEFT JOIN persona p_doc ON p_doc.id_persona = emp.id_persona
                     WHERE ea.id_anio_escolar = :anio
                     GROUP BY ea.id_estructura_academica, g.nombre, s.nombre, t.nombre, ea.capacidad_maxima,
                              p_doc.tipo_documento, p_doc.numero_documento, p_doc.primer_nombre, p_doc.primer_apellido, ad.id_asignacion";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['anio' => $idAnio]);
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            usort($filas, static function (array $a, array $b): int {
                $ordA = Instituciones::ordinalGrado($a['grado']) ?? PHP_INT_MAX;
                $ordB = Instituciones::ordinalGrado($b['grado']) ?? PHP_INT_MAX;
                if ($ordA !== $ordB) {
                    return $ordA <=> $ordB;
                }
                return strcmp((string) $a['seccion'], (string) $b['seccion']);
            });

            return $filas;
        } catch (Throwable $e) {
            error_log('ReporteModel::getDistribucionAcademica: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Nómina consolidada del personal de la institución:
     * Datos laborales, cargo, turno, estado y si tiene reposo vigente.
     */
    public function getNominaPersonal(): array
    {
        try {
            $condicionReposo = "r.estado = 'VIGENTE' AND CURDATE() BETWEEN r.fecha_inicio AND r.fecha_fin";

            $sql = "SELECT e.id_empleado,
                           p.tipo_documento,
                           p.numero_documento,
                           CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS nombre_completo,
                           p.sexo,
                           p.estado AS estado_persona,
                           c.nombre AS cargo,
                           t.nombre AS turno,
                           e.fecha_ingreso,
                           CONCAT_WS('-', cont.codigo_telefono_principal, cont.numero_telefono_principal) AS telefono,
                           cont.correo_electronico,
                           MAX(CASE WHEN {$condicionReposo} THEN 1 ELSE 0 END) AS en_reposo,
                           MAX(CASE WHEN {$condicionReposo} THEN r.fecha_inicio END) AS reposo_inicio,
                           MAX(CASE WHEN {$condicionReposo} THEN r.fecha_fin END) AS reposo_fin
                      FROM empleado e
                     INNER JOIN persona p ON p.id_persona = e.id_persona
                      LEFT JOIN cargo c ON c.id_cargo = e.id_cargo
                      LEFT JOIN turno t ON t.id_turno = e.id_turno
                      LEFT JOIN contacto cont ON cont.id_persona = p.id_persona
                      LEFT JOIN reposo_empleado r ON r.id_empleado = e.id_empleado
                     GROUP BY e.id_empleado, p.tipo_documento, p.numero_documento, p.primer_nombre, p.segundo_nombre,
                              p.primer_apellido, p.segundo_apellido, p.sexo, p.estado, c.nombre, t.nombre, e.fecha_ingreso,
                              cont.codigo_telefono_principal, cont.numero_telefono_principal, cont.correo_electronico
                     ORDER BY p.estado ASC, c.nombre ASC, p.primer_apellido ASC";

            return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('ReporteModel::getNominaPersonal: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Relación oficial de estudiantes retirados en el período escolar.
     */
    public function getRetirosPeriodo(int $idAnio): array
    {
        if ($idAnio <= 0) {
            return [];
        }

        try {
            $sql = "SELECT r.id_retiro,
                           p.tipo_documento,
                           p.numero_documento,
                           CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS nombre_completo,
                           p.sexo,
                           g.nombre AS grado,
                           s.nombre AS seccion,
                           mr.nombre AS motivo,
                           r.estado_solicitud,
                           r.fecha_solicitud,
                           r.fecha_resolucion,
                           r.observaciones
                      FROM retiro r
                     INNER JOIN matricula m ON m.id_matricula = r.id_matricula
                     INNER JOIN estudiante e ON e.id_estudiante = m.id_estudiante
                     INNER JOIN persona p ON p.id_persona = e.id_persona
                     INNER JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
                     INNER JOIN grado g ON g.id_grado = ea.id_grado
                     INNER JOIN seccion s ON s.id_seccion = ea.id_seccion
                     INNER JOIN motivo_retiro mr ON mr.id_motivo_retiro = r.id_motivo_retiro
                     WHERE m.id_anio_escolar = :anio
                     ORDER BY r.fecha_solicitud DESC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['anio' => $idAnio]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('ReporteModel::getRetirosPeriodo: ' . $e->getMessage());
            return [];
        }
    }
}
