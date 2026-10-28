<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\AniosEscolares;
use App\Support\Matriculas;
use PDO;

/**
 * ListadosSeccionModel
 * -------------------
 * Consulta de datos para la generación de reportes tabulares oficiales
 * de secciones: Nómina Matriz, Directorio de Contactos y Asistencia a Asambleas.
 */
class ListadosSeccionModel extends Model
{
    /**
     * Obtiene los metadatos de la estructura académica / sección y del docente titular.
     */
    public function getDetalleSeccion(int $idEstructura): ?array
    {
        $sql = "SELECT ea.id_estructura_academica AS id,
                       ea.id_anio_escolar,
                       ae.nombre_periodo AS anio_escolar,
                       ae.estado AS estado_anio,
                       g.id_grado,
                       g.nombre AS grado,
                       s.id_seccion,
                       s.nombre AS seccion,
                       t.id_turno,
                       t.nombre AS turno,
                       ea.capacidad_maxima,
                       CONCAT_WS(' ', p_doc.primer_nombre, p_doc.primer_apellido) AS docente_nombre,
                       p_doc.tipo_documento AS docente_tipo_doc,
                       p_doc.numero_documento AS docente_cedula,
                       car_doc.nombre AS docente_cargo
                FROM estructura_academica ea
                INNER JOIN anio_escolar ae ON ae.id_anio_escolar = ea.id_anio_escolar
                INNER JOIN grado g ON g.id_grado = ea.id_grado
                INNER JOIN seccion s ON s.id_seccion = ea.id_seccion
                INNER JOIN turno t ON t.id_turno = ea.id_turno
                LEFT JOIN asignacion_docente ad ON ad.id_estructura_academica = ea.id_estructura_academica
                                               AND (ad.fecha_fin IS NULL OR ad.fecha_fin >= CURDATE())
                LEFT JOIN docente doc ON doc.id_docente = ad.id_docente
                LEFT JOIN empleado emp ON emp.id_empleado = doc.id_empleado
                LEFT JOIN cargo car_doc ON car_doc.id_cargo = emp.id_cargo
                LEFT JOIN persona p_doc ON p_doc.id_persona = emp.id_persona
                WHERE ea.id_estructura_academica = :id
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $idEstructura]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Obtiene la nómina completa de estudiantes activos cursando en la sección,
     * deduplicados y con su representante legal y datos de contacto.
     *
     * @param int $idEstructura
     * @param string $genero 'todos'|'M'|'F'
     * @param string $orden 'apellido'|'cedula'|'edad'
     * @return array
     */
    public function getEstudiantesSeccion(int $idEstructura, string $genero = 'todos', string $orden = 'apellido'): array
    {
        $condicionCursando = Matriculas::condicionCursando('m_sub');

        $sql = "SELECT 
                    m.id_matricula,
                    est.id_estudiante,
                    p.primer_nombre,
                    p.segundo_nombre,
                    p.primer_apellido,
                    p.segundo_apellido,
                    CONCAT_WS(' ', p.primer_nombre, p.primer_apellido) AS nombre_simple,
                    TRIM(CONCAT_WS(' ', 
                        CONCAT_WS(' ', p.primer_apellido, p.segundo_apellido),
                        CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre)
                    )) AS apellidos_nombres,
                    p.tipo_documento,
                    p.numero_documento,
                    p.fecha_nacimiento,
                    TIMESTAMPDIFF(YEAR, p.fecha_nacimiento, CURDATE()) AS edad,
                    p.sexo AS genero,
                    -- Representante legal
                    p_rep.id_persona AS id_rep_persona,
                    CONCAT_WS(' ', p_rep.primer_nombre, p_rep.primer_apellido) AS rep_nombre_simple,
                    TRIM(CONCAT_WS(' ',
                        CONCAT_WS(' ', p_rep.primer_apellido, p_rep.segundo_apellido),
                        CONCAT_WS(' ', p_rep.primer_nombre, p_rep.segundo_nombre)
                    )) AS rep_apellidos_nombres,
                    p_rep.tipo_documento AS rep_tipo_doc,
                    p_rep.numero_documento AS rep_cedula,
                    COALESCE(par.nombre, 'Representante Legal') AS parentesco,
                    c_rep.codigo_telefono_principal,
                    c_rep.numero_telefono_principal,
                    c_rep.codigo_telefono_alternativo,
                    c_rep.numero_telefono_alternativo,
                    c_rep.correo_electronico,
                    COALESCE(pe.es_autorizado_retiro, 0) AS es_autorizado_retiro
                FROM (
                    SELECT m_sub.id_estudiante, MAX(m_sub.id_matricula) AS id_matricula
                    FROM matricula m_sub
                    WHERE m_sub.id_estructura_academica = :id_estructura
                      AND m_sub.tipo IN ('INSCRIPCION', 'RATIFICACION')
                      AND {$condicionCursando}
                    GROUP BY m_sub.id_estudiante
                ) ult_m
                INNER JOIN matricula m ON m.id_matricula = ult_m.id_matricula
                INNER JOIN estudiante est ON est.id_estudiante = m.id_estudiante
                INNER JOIN persona p ON p.id_persona = est.id_persona
                LEFT JOIN (
                    SELECT pe1.*
                    FROM persona_estudiante pe1
                    INNER JOIN (
                        SELECT pex.id_estudiante, MIN(pex.id_persona_estudiante) AS min_id
                        FROM persona_estudiante pex
                        INNER JOIN persona pr ON pr.id_persona = pex.id_persona AND pr.es_fallecido = 0
                        WHERE pex.es_representante_legal = 1 AND pex.estado_relacion = 'ACTIVO'
                        GROUP BY pex.id_estudiante
                    ) pe_dedup ON pe_dedup.min_id = pe1.id_persona_estudiante
                ) pe ON pe.id_estudiante = est.id_estudiante
                LEFT JOIN persona p_rep ON p_rep.id_persona = pe.id_persona
                LEFT JOIN parentesco par ON par.id_parentesco = pe.id_parentesco
                LEFT JOIN contacto c_rep ON c_rep.id_persona = p_rep.id_persona
                WHERE 1 = 1";

        $params = ['id_estructura' => $idEstructura];

        if ($genero === 'M' || $genero === 'F') {
            $sql .= " AND p.sexo = :genero";
            $params['genero'] = $genero;
        }

        switch ($orden) {
            case 'cedula':
                $sql .= " ORDER BY p.numero_documento ASC";
                break;
            case 'edad':
                $sql .= " ORDER BY edad ASC, p.primer_apellido ASC";
                break;
            case 'apellido':
            default:
                $sql .= " ORDER BY p.primer_apellido ASC, p.segundo_apellido ASC, p.primer_nombre ASC";
                break;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Formateo auxiliar amigable
        foreach ($estudiantes as &$est) {
            // Teléfono principal compuesto
            $cod = trim((string) ($est['codigo_telefono_principal'] ?? ''));
            $num = trim((string) ($est['numero_telefono_principal'] ?? ''));
            $est['telefono_completo'] = ($cod !== '' && $num !== '') ? "{$cod}-{$num}" : ($num ?: 'No registrado');

            // Cédula formateada
            $td = strtoupper((string) ($est['tipo_documento'] ?? 'V'));
            $nd = (string) ($est['numero_documento'] ?? '');
            if ($td === 'CE') {
                $est['documento_etiqueta'] = "CE-{$nd}";
            } else {
                $numLimpio = preg_replace('/\D/', '', $nd);
                $fmt = ($numLimpio !== '') ? number_format((int) $numLimpio, 0, '', '.') : $nd;
                $est['documento_etiqueta'] = "{$td}-{$fmt}";
            }

            // Cédula rep formateada
            $rtd = strtoupper((string) ($est['rep_tipo_doc'] ?? 'V'));
            $rnd = (string) ($est['rep_cedula'] ?? '');
            if ($rnd !== '') {
                $rNumLimpio = preg_replace('/\D/', '', $rnd);
                $rFmt = ($rNumLimpio !== '') ? number_format((int) $rNumLimpio, 0, '', '.') : $rnd;
                $est['rep_documento_etiqueta'] = "{$rtd}-{$rFmt}";
            } else {
                $est['rep_documento_etiqueta'] = 'S/I';
            }

            // Claves normalizadas
            $est['es_cedula_escolar']  = ($td === 'CE');
            $est['sexo']               = strtoupper((string) ($est['genero'] ?? ''));
            $est['genero']             = $est['sexo'];
            $est['edad_anios']         = (int) ($est['edad'] ?? 0);
            $est['autorizado_retirar'] = !empty($est['es_autorizado_retiro']);
        }
        unset($est);

        return $estudiantes;
    }

    /**
     * Devuelve las secciones accesibles por el usuario según su rol.
     */
    public function getSeccionesDisponibles(int $idUsuario, string $rol): array
    {
        $idAnioActivo = AniosEscolares::idEnCurso($this->pdo) ?: 0;
        if ($idAnioActivo <= 0) {
            return [];
        }

        if (strtolower($rol) === 'docente') {
            $stmt = $this->pdo->prepare(
                "SELECT ea.id_estructura_academica AS id,
                        g.nombre AS grado,
                        s.nombre AS seccion,
                        t.nombre AS turno,
                        ae.nombre_periodo AS anio_escolar
                 FROM asignacion_docente ad
                 INNER JOIN estructura_academica ea ON ea.id_estructura_academica = ad.id_estructura_academica
                 INNER JOIN grado g ON g.id_grado = ea.id_grado
                 INNER JOIN seccion s ON s.id_seccion = ea.id_seccion
                 INNER JOIN turno t ON t.id_turno = ea.id_turno
                 INNER JOIN anio_escolar ae ON ae.id_anio_escolar = ea.id_anio_escolar
                 INNER JOIN docente doc ON doc.id_docente = ad.id_docente
                 INNER JOIN empleado emp ON emp.id_empleado = doc.id_empleado
                 INNER JOIN usuario u ON u.id_persona = emp.id_persona
                 WHERE u.id_usuario = :id_usuario
                   AND ea.id_anio_escolar = :id_anio
                   AND (ad.fecha_fin IS NULL OR ad.fecha_fin >= CURDATE())
                 ORDER BY g.id_grado ASC, s.nombre ASC"
            );
            $stmt->execute([
                'id_usuario' => $idUsuario,
                'id_anio'    => $idAnioActivo,
            ]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Para directivos, coordinadores y administradores: todas las secciones del año activo
        $stmt = $this->pdo->prepare(
            "SELECT ea.id_estructura_academica AS id,
                    g.nombre AS grado,
                    s.nombre AS seccion,
                    t.nombre AS turno,
                    ae.nombre_periodo AS anio_escolar
             FROM estructura_academica ea
             INNER JOIN grado g ON g.id_grado = ea.id_grado
             INNER JOIN seccion s ON s.id_seccion = ea.id_seccion
             INNER JOIN turno t ON t.id_turno = ea.id_turno
             INNER JOIN anio_escolar ae ON ae.id_anio_escolar = ea.id_anio_escolar
             WHERE ea.id_anio_escolar = :id_anio
             ORDER BY g.id_grado ASC, s.nombre ASC"
        );
        $stmt->execute(['id_anio' => $idAnioActivo]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Id del año escolar en curso (0 si no hay ninguno abierto).
     */
    public function getAnioEnCursoId(): int
    {
        return AniosEscolares::idEnCurso($this->pdo);
    }

    /**
     * Indica si el usuario tiene una asignación vigente sobre la sección.
     * Lo usa el controlador para acotar a un docente a sus propias secciones.
     */
    public function usuarioTieneSeccion(int $idUsuario, int $idEstructura): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM asignacion_docente ad
             INNER JOIN docente doc ON doc.id_docente = ad.id_docente
             INNER JOIN empleado emp ON emp.id_empleado = doc.id_empleado
             INNER JOIN usuario u ON u.id_persona = emp.id_persona
             WHERE u.id_usuario = :id_usuario
               AND ad.id_estructura_academica = :id_estructura
               AND (ad.fecha_fin IS NULL OR ad.fecha_fin >= CURDATE())
             LIMIT 1"
        );
        $stmt->execute([
            'id_usuario'    => $idUsuario,
            'id_estructura' => $idEstructura,
        ]);

        return (bool) $stmt->fetchColumn();
    }
}
