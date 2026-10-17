<?php

declare(strict_types=1);

namespace App\Models;

use App\Interfaces\Listable;
use App\Support\Direccion;
use App\Support\Matriculas;

/**
 * Consulta de expedientes (estudiante ↔ persona).
 * Resuelve el grado y la sección vigentes de cada estudiante a partir de su matrícula más reciente (inscripción o ratificación).
*/
class EstudianteModel extends BaseModel implements Listable
{
    protected string $table = 'estudiante';
    protected string $primaryKey = 'id_estudiante';

    /* Estado del CICLO del estudiante, en una sola expresión SQL. Se lee de la última fila de
 `matricula`, no de `persona.estado`: ese campo es de la persona, se comparte con empleados y
 familiares, y el retiro también lo apaga.*/
    private const EXPRESION_ESTADO = "CASE
            WHEN ciclo.tipo = 'RETIRO' THEN 'retirado'
            WHEN ciclo.tipo = 'EGRESO' THEN 'egresado'
            ELSE 'activo'
        END";

    /* La última matrícula de cada estudiante, como tabla derivada. "Última" se decide por la FECHA
 del año escolar y no por id_anio_escolar: los ids son de inserción y comparar por ellos
 puede devolver el año equivocado.*/
    private function joinCiclo(): string
    {
        return "LEFT JOIN (
                    SELECT m.id_estudiante, m.tipo
                      FROM matricula m
                      INNER JOIN anio_escolar a ON a.id_anio_escolar = m.id_anio_escolar
                     WHERE NOT EXISTS (
                           SELECT 1 FROM matricula m2
                             INNER JOIN anio_escolar a2 ON a2.id_anio_escolar = m2.id_anio_escolar
                            WHERE m2.id_estudiante = m.id_estudiante
                              AND (a2.anio_inicio > a.anio_inicio
                                   OR (a2.anio_inicio = a.anio_inicio AND m2.id_matricula > m.id_matricula))
                     )
                ) ciclo ON ciclo.id_estudiante = est.id_estudiante";
    }

    /**
 * Joins each student's latest enrollment within the requested year/turn,
 * or their most recent school year when no year was selected.
*/
    private function joinActual(?int $anioId = null, ?int $idTurno = null): array
    {
        $params = [];
        if ($anioId !== null) {
            if ($anioId <= 0) {
                return [
                    'sql' => "INNER JOIN matricula m ON 1 = 0
                              LEFT JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
                              LEFT JOIN anio_escolar a ON a.id_anio_escolar = ea.id_anio_escolar
                              LEFT JOIN grado g ON g.id_grado = ea.id_grado
                              LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion",
                    'params' => [],
                ];
            }
            $params['matricula_anio'] = $anioId;
            $turnoCondition = '';
            if ($idTurno !== null) {
                $turnoCondition = ' AND ea_filtro.id_turno = :matricula_turno';
                $params['matricula_turno'] = $idTurno;
            }

            $join = "INNER JOIN (
                        SELECT m_filtro.id_estudiante, MAX(m_filtro.id_matricula) AS id_matricula
                          FROM matricula m_filtro
                          LEFT JOIN estructura_academica ea_filtro
                                 ON ea_filtro.id_estructura_academica = m_filtro.id_estructura_academica
                         WHERE m_filtro.id_anio_escolar = :matricula_anio{$turnoCondition}
                         GROUP BY m_filtro.id_estudiante
                    ) ult ON ult.id_estudiante = est.id_estudiante
                    INNER JOIN matricula m ON m.id_matricula = ult.id_matricula";
        } else {
            $turnoJoin = '';
            $turnoCondition = '';
            if ($idTurno !== null) {
                $turnoJoin = ' LEFT JOIN estructura_academica ea_filtro ON ea_filtro.id_estructura_academica = m_filtro.id_estructura_academica';
                $turnoCondition = ' WHERE ea_filtro.id_turno = :matricula_turno';
                $params['matricula_turno'] = $idTurno;
            }
/* Sin año seleccionado se muestra la matrícula más reciente del alumno.
 ROW_NUMBER y no MAX(id_anio_escolar): los ids de año son de inserción y no
 siguen el orden, así que comparar por id invertía "último" y "primero" cuando
 se cargaba un año viejo, que es justo lo que se hace al abrir un período.
 El desempate por id_matricula además deja UNA fila por estudiante: con
 MAX() el alumno que tiene dos filas en el mismo año (inscripción y retiro)
 salía duplicado en el listado.*/
            /* id_matricula tiene que estar en la proyección: el ON de más abajo
               (INNER JOIN matricula m ON m.id_matricula = ult.id_matricula) la
               necesita, y sin ella la consulta reventaba con "Unknown column
               'ult.id_matricula' in 'ON'" cada vez que se pedía la ficha sin
               indicar año escolar. */
            $join = "INNER JOIN (
                        SELECT id_estudiante, id_anio_escolar, id_matricula FROM (
                            SELECT m_filtro.id_estudiante, m_filtro.id_matricula, m_filtro.id_anio_escolar,
                                   ROW_NUMBER() OVER (
                                       PARTITION BY m_filtro.id_estudiante
                                       ORDER BY a_filtro.anio_inicio DESC, m_filtro.id_matricula DESC
                                   ) AS puesto
                              FROM matricula m_filtro
                              INNER JOIN anio_escolar a_filtro ON a_filtro.id_anio_escolar = m_filtro.id_anio_escolar{$turnoJoin}
                              {$turnoCondition}
                        ) UltimaFila
                         WHERE UltimaFila.puesto = 1
                     ) ult ON ult.id_estudiante = est.id_estudiante
                     INNER JOIN matricula m ON m.id_matricula = ult.id_matricula";
        }

        return [
            'sql' => $join . "
                LEFT JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
                LEFT JOIN anio_escolar a ON a.id_anio_escolar = ea.id_anio_escolar
                LEFT JOIN grado g ON g.id_grado = ea.id_grado
                LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion",
            'params' => $params,
        ];
    }

    public function getAll(array $filters = [], int $page = 1, int $perPage = 10): array
    {
        [$where, $params] = $this->filters($filters);
        $matricula = $this->joinActual(
            isset($filters['anio']) ? (int) $filters['anio'] : null,
            isset($filters['id_turno']) && $filters['id_turno'] !== '' ? (int) $filters['id_turno'] : null
        );
        $params = array_merge($matricula['params'], $params);

$sql = "SELECT est.id_estudiante AS id, est.id_estudiante,
                       CONCAT_WS(' ', p.primer_nombre, p.primer_apellido) AS nombre,
                       p.numero_documento AS cedula_escolar,
                       p.foto AS avatar_img,
                       UPPER(CONCAT(LEFT(p.primer_nombre, 1), COALESCE(LEFT(p.primer_apellido, 1), ''))) AS iniciales,
                       " . self::EXPRESION_ESTADO . " AS estado_ciclo,
                       g.nombre AS grado, s.nombre AS seccion,
                       a.nombre_periodo AS anio_escolar
                 FROM estudiante est
                 INNER JOIN persona p ON p.id_persona = est.id_persona
                 " . $matricula['sql'] . '
                 ' . $this->joinCiclo();
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY p.primer_nombre, p.primer_apellido LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($rows as &$estudiante) {
            $estudiante['id']        = (int) $estudiante['id'];
            $estudiante['estado'] = $estudiante['estado_ciclo'];
            $estudiante['avatar_color'] = 'primary';
            $estudiante['iniciales'] = $estudiante['iniciales'] ?: 'ES';
            unset($estudiante['estado_ciclo']);
        }
        return $rows;
    }

    public function countAll(array $filters = []): int
    {
        [$where, $params] = $this->filters($filters);
        $matricula = $this->joinActual(
            isset($filters['anio']) ? (int) $filters['anio'] : null,
            isset($filters['id_turno']) && $filters['id_turno'] !== '' ? (int) $filters['id_turno'] : null
        );
        $params = array_merge($matricula['params'], $params);

        $sql = "SELECT COUNT(*) FROM estudiante est
                INNER JOIN persona p ON p.id_persona = est.id_persona
                " . $matricula['sql'] . '
                ' . $this->joinCiclo();
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function getStats(array $filters = []): array
    {
        $matricula = $this->joinActual(
            isset($filters['anio']) ? (int) $filters['anio'] : null,
            isset($filters['id_turno']) && $filters['id_turno'] !== '' ? (int) $filters['id_turno'] : null
        );
$sql = "SELECT COUNT(DISTINCT est.id_estudiante) AS total,
                       COUNT(DISTINCT CASE WHEN " . self::EXPRESION_ESTADO . " = 'activo'
                                           THEN est.id_estudiante END) AS activos,
                       COUNT(DISTINCT CASE WHEN " . self::EXPRESION_ESTADO . " = 'retirado'
                                           THEN est.id_estudiante END) AS retirados,
                       COUNT(DISTINCT CASE WHEN " . self::EXPRESION_ESTADO . " = 'egresado'
                                           THEN est.id_estudiante END) AS egresados
                 FROM estudiante est
                 INNER JOIN persona p ON p.id_persona = est.id_persona";
        $sql .= ' ' . $matricula['sql'];
        $sql .= ' ' . $this->joinCiclo();

        /* El docente ve el conteo de lo suyo, no el de toda la institución: las tarjetas
 deben cuadrar con la tabla que tiene delante, que ya viene filtrada.*/
        $params = $matricula['params'];
        [$whereDocente, $paramsDocente] = $this->restriccionDocente(
            isset($filters['id_docente']) && $filters['id_docente'] !== '' ? (int) $filters['id_docente'] : null
        );
        if ($whereDocente !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $whereDocente);
            $params = array_merge($params, $paramsDocente);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $retirados = (int) ($row['retirados'] ?? 0);
        $egresados = (int) ($row['egresados'] ?? 0);
        return [
            'total'     => (int) ($row['total'] ?? 0),
            'activos'   => (int) ($row['activos'] ?? 0),
            'retirados' => $retirados,
            'egresados' => $egresados,
            // Quien no está activo es cualquiera de los dos desenlaces, que es lo que la
            // tarjeta "Inactivos" resume; el desglose va en el filtro por estado.
            'inactivos' => $retirados + $egresados,
        ];
    }

    /** Detalle completo para la ficha del estudiante.*/
    public function getById(int $id, ?int $anioId = null, ?int $idTurno = null, ?int $idDocente = null): ?array
    {
        $matricula = $this->joinActual($anioId, $idTurno);
        $sql = "SELECT est.id_estudiante AS id,
                       CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS nombre,
                       CONCAT_WS(' ', p.primer_nombre, p.primer_apellido) AS nombre_corto,
                       p.tipo_documento, p.numero_documento, p.fecha_nacimiento,
                       p.id_estado_nacimiento,
                       p.id_nacionalidad, nac.nombre AS nacionalidad,
                       p.id_parroquia_nacimiento, p.sexo, p.id_nacionalidad,
                       p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido,
                       p.foto AS avatar_img,
                       " . self::EXPRESION_ESTADO . " AS estado_ciclo,
                       UPPER(CONCAT(LEFT(p.primer_nombre, 1), COALESCE(LEFT(p.primer_apellido, 1), ''))) AS iniciales,
                       g.nombre AS grado, s.nombre AS seccion, a.nombre_periodo AS anio_escolar,
                       t.nombre AS turno,
                       c.correo_electronico,
                       CONCAT(c.codigo_telefono_principal, '-', c.numero_telefono_principal) AS telefono,
                       CONCAT(c.codigo_telefono_alternativo, '-', c.numero_telefono_alternativo) AS telefono_alternativo,
                        esN.nombre AS estado_nacimiento,
                        pn.nombre AS parroquia_nacimiento,
                        mn.nombre AS municipio_nacimiento,
                        d.sector_urbanizacion, d.calle_avenida, d.nro_casa_apto, d.punto_referencia
                FROM estudiante est
                INNER JOIN persona p ON p.id_persona = est.id_persona
                LEFT JOIN contacto c ON c.id_persona = p.id_persona
LEFT JOIN direccion d          ON d.id_persona            = p.id_persona
                 /* El lugar de nacimiento solo guarda estado y parroquia; los nombres de
                    los tres niveles salen de esos dos, igual que la dirección deduce el
                    suyo de id_parroquia. */
                 /* La nacionalidad se trae con JOIN porque `persona` guarda el id del
                    catálogo, no el texto: sin este JOIN la ficha mostraría el número. */
                 LEFT JOIN nacionalidad nac       ON nac.id_nacionalidad    = p.id_nacionalidad
                 LEFT JOIN estado     esN        ON esN.id_estado          = p.id_estado_nacimiento
                 LEFT JOIN parroquia  pn         ON pn.id_parroquia        = p.id_parroquia_nacimiento
                 LEFT JOIN municipio  mn         ON mn.id_municipio        = pn.id_municipio
                " . $matricula['sql'] . '
                ' . $this->joinCiclo() . "
                LEFT JOIN turno t ON t.id_turno = ea.id_turno
                WHERE est.id_estudiante = :id";
        $params = array_merge($matricula['params'], ['id' => $id]);

        /* Un docente solo abre el expediente de quien tiene en sus estructuras asignadas.*/
        [$whereDocente, $paramsDocente] = $this->restriccionDocente($idDocente);
        if ($whereDocente !== []) {
            $sql .= ' AND ' . implode(' AND ', $whereDocente);
            $params = array_merge($params, $paramsDocente);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $row['id']          = (int) $row['id'];
        $row['estado']      = $row['estado_ciclo'];
        $row['avatar_color'] = 'primary';
        $row['iniciales']   = $row['iniciales'] ?: 'ES';
        unset($row['estado_ciclo']);
        return $row;
    }

    public function getHistorialAcademico(int $idEstudiante, ?int $idTurno = null): array
    {
        $sql = "SELECT a.nombre_periodo AS anio_escolar, a.estado AS estado_anio,
                       g.nombre AS grado, s.nombre AS seccion, t.nombre AS turno,
                       m.tipo AS tipo_matricula, m.fecha_matricula, m.motivo_anticipo
                  FROM matricula m
                  INNER JOIN anio_escolar a ON a.id_anio_escolar = m.id_anio_escolar
                  LEFT JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
                  LEFT JOIN grado g ON g.id_grado = ea.id_grado
                  LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion
                  LEFT JOIN turno t ON t.id_turno = ea.id_turno
                 WHERE m.id_estudiante = :estudiante";
        $params = ['estudiante' => $idEstudiante];
        if ($idTurno !== null) {
            $sql .= ' AND ea.id_turno = :turno';
            $params['turno'] = $idTurno;
        }
        $sql .= ' ORDER BY a.anio_inicio DESC, m.fecha_matricula DESC, m.id_matricula DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getAniosEscolares(?int $idTurno = null): array
    {
        $sql = "SELECT DISTINCT a.id_anio_escolar AS id, a.nombre_periodo AS nombre, a.estado
                  FROM anio_escolar a
                  INNER JOIN matricula m ON m.id_anio_escolar = a.id_anio_escolar
                  LEFT JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica";
        if ($idTurno !== null) {
            $sql .= ' WHERE ea.id_turno = :turno';
        }
        $sql .= ' ORDER BY a.anio_inicio DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($idTurno !== null ? ['turno' => $idTurno] : []);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getAnioEscolarActivoId(): ?int
    {
        $id = $this->pdo->query(
            "SELECT id_anio_escolar FROM anio_escolar WHERE estado = 'ACTIVO' LIMIT 1"
        )->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    public function getGrados(): array
    {
        return $this->pdo->query("SELECT nombre FROM grado WHERE estado = 'ACTIVO' ORDER BY nombre ASC")
                         ->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function getSecciones(): array
    {
        return $this->pdo->query("SELECT nombre FROM seccion WHERE estado = 'ACTIVO' ORDER BY nombre ASC")
                         ->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
 * Restricción de un docente a las estructuras que tiene asignadas.
 *
 * Sin esto el docente veía TODOS los expedientes de la institución, incluidos los de
 * secciones en las que no da clase. La asignación docente es la única fuente de "qué
 * le toca a cada cual", así que se toma de ahí y no de un turno ni de un grado.
 *
 * Un docente sin asignaciones no ve a nadie: antes del filtro esto tampoco sefiltraba
 * y por eso la pantalla queda vacía a propósito, con un aviso que lo explica.
 *
 * @return array{0:array<int,string>,1:array<string,int>}
*/
    private function restriccionDocente(?int $idDocente): array
    {
        if ($idDocente === null) {
            return [[], []];
        }

        return [
            ['ea.id_estructura_academica IN (
                SELECT ad.id_estructura_academica
                  FROM asignacion_docente ad
                  JOIN estructura_academica asignada ON asignada.id_estructura_academica = ad.id_estructura_academica
                  JOIN anio_escolar asignada_anio ON asignada_anio.id_anio_escolar = asignada.id_anio_escolar
                 WHERE ad.id_docente = :docente
                   AND asignada_anio.estado = \'ACTIVO\'
            )'],
            ['docente' => $idDocente],
        ];
    }

    /**
 * Estructuras asignadas al docente en el año en curso; [] si no tiene ninguna.
 * Existe para que la interfaz pueda explicar una tabla vacía en lugar de dejar
 * al usuario pensando que la consulta falló.
 *
 * @return array<int, int>
*/
    public function getEstructurasDocente(int $idDocente): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ad.id_estructura_academica
               FROM asignacion_docente ad
               INNER JOIN estructura_academica ea ON ea.id_estructura_academica = ad.id_estructura_academica
               INNER JOIN anio_escolar a ON a.id_anio_escolar = ea.id_anio_escolar
              WHERE ad.id_docente = :docente AND a.estado = \'ACTIVO\'
              ORDER BY ad.id_estructura_academica'
        );
        $stmt->execute(['docente' => $idDocente]);

        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    /* ======================================================================
       MÓDULO DOCENTE
       Todo lo de aquí está acotado a UNA estructura académica: la que el
       servidor resolvió desde la sesión. La estructura viaja como parámetro
       desde DocenteController, que la obtiene con
       TeacherAssignmentModel::getSeccionActivaDeDocente() y NUNCA desde GET.
       ====================================================================== */

    /**
     * Nombre completo de una persona, como expresión SQL reutilizable.
     *
     * El NULLIF en cada parte no es cosmetico: CONCAT_WS separa con espacio pero
     * NO salta las cadenas vacías, así que un alumno sin segundo nombre salía
     * como "Leo  Pinto " con espacio doble y cola. Convirtiendo cada parte
     * opcional en NULL, CONCAT_WS la omite y el nombre queda limpio.
     *
     * Se devuelve como expresión y no como resultado porque el buscador necesita
     * el mismo nombre en el WHERE, y MySQL no deja usar un alias del SELECT ahí.
     */
    private function exprNombre(string $alias): string
    {
        return "CONCAT_WS(' ', NULLIF({$alias}.primer_nombre, ''), NULLIF({$alias}.segundo_nombre, ''),
"
             . "                           NULLIF({$alias}.primer_apellido, ''), NULLIF({$alias}.segundo_apellido, ''))";
    }


    /**
     * Nómina de la sección del docente: una fila por estudiante matriculado.
     *
     * La búsqueda y los filtros se aplican AQUÍ, en SQL, y no en el PHP: la
     * pantalla pide que se combinen entre sí y que el agrupado por representante
     * respete lo filtrado.
     *
     * NO calcula inferencia familiar: ni «con quién vive» ni cuántos compañeros de
     * la sección comparten representante. No es que se escondan al pintar, es que
     * no se piden. Se quitaron junto con los filtros `vive_con` y `hermanos`,
     * que ningún controller enviaba y que además filtraban por esa misma
     * información: quien no puede ver el dato tampoco puede buscarlo.
     *
     * @param array{q?:string,sexo?:string,contacto?:string,
     *              autorizados?:string,literal?:string} $filtros
     * @return array<int,array<string,mixed>>
     */
    public function getNominaDocente(int $idEstructura, array $filtros = []): array
    {
        $where = ["m.id_estructura_academica = :estructura",
                  Matriculas::condicionCursando('m')];
        $params = ['estructura' => $idEstructura];

        $q = trim((string) ($filtros['q'] ?? ''));
        if ($q !== '') {
            // El nombre del representante se busca en sus mismos campos de persona.
            /* Cada parámetro nombrado se declara por separado porque con prepares
               nativos un mismo marcador no puede repetirse en la sentencia.
               Se compara el nombre completo además de cada campo suelto, para
               que "leo pinto" encuentre a "Leo Pinto" y no solo "leo". */
            $where[] = "({$this->exprNombre('p')} LIKE :q
                         OR p.numero_documento LIKE :q2
                         OR {$this->exprNombre('rep')} LIKE :q3)";
            $params += ['q' => "%$q%", 'q2' => "%$q%", 'q3' => "%$q%"];
        }

        $sexo = strtolower(trim((string) ($filtros['sexo'] ?? '')));
        if (in_array($sexo, ['m', 'f'], true)) {
            $where[] = 'p.sexo = :sexo';
            $params['sexo'] = strtoupper($sexo);
        }

        // "Contacto incompleto": el representante no tiene teléfono principal.
        if (($filtros['contacto'] ?? '') === 'incompleto') {
            $where[] = "(c.id_contacto IS NULL OR c.numero_telefono_principal IS NULL
                         OR c.numero_telefono_principal = '')";
        }

        // "Sin autorizados adicionales": solo el representante legal, nadie más
        // autorizado para el retiro.
        if (($filtros['autorizados'] ?? '') === 'sin') {
            $where[] = "NOT EXISTS (
                SELECT 1 FROM persona_estudiante ax
                 WHERE ax.id_estudiante = e.id_estudiante
                   AND ax.es_representante_legal = 0
                   AND ax.es_autorizado_retiro = 1
                   AND ax.estado_relacion = 'ACTIVO')";
        }

        $literal = strtolower(trim((string) ($filtros['literal'] ?? '')));
        if (in_array($literal, ['pendiente', 'promovido', 'no_promovido'], true)) {
            $where[] = 'COALESCE(ra.estado_resultado, :lit_pte) = :literal';
            $params['lit_pte']  = 'PENDIENTE';
            $params['literal']  = strtoupper($literal);
        }

        $sql = "SELECT e.id_estudiante,
                       m.id_matricula,
                       {$this->exprNombre('p')} AS nombre,
                       p.primer_nombre, p.primer_apellido,
                       p.fecha_nacimiento,
                       TIMESTAMPDIFF(YEAR, p.fecha_nacimiento, CURDATE()) AS edad,
                       p.sexo,
                       p.tipo_documento,
                       p.numero_documento,
                       CASE WHEN p.tipo_documento = 'CE'
                            THEN p.numero_documento END AS cedula_escolar,
                       pe.id_persona AS id_representante,
                       {$this->exprNombre('rep')} AS representante,
                       par.nombre AS parentesco,
                       c.codigo_telefono_principal,
c.numero_telefono_principal,
                       COALESCE(ra.estado_resultado, 'PENDENTE') AS estado_literal,
                       ra.literal,
                       (SELECT COUNT(*)
                          FROM persona_estudiante ax
                         WHERE ax.id_estudiante = e.id_estudiante
                           AND ax.es_representante_legal = 0
                           AND ax.es_autorizado_retiro = 1
                           AND ax.estado_relacion = 'ACTIVO') AS autorizados_adicionales
                  FROM matricula m
                  INNER JOIN estudiante e ON e.id_estudiante = m.id_estudiante
                  INNER JOIN persona p ON p.id_persona = e.id_persona
                  LEFT JOIN persona_estudiante pe
                         ON pe.id_estudiante = e.id_estudiante
                        AND pe.es_representante_legal = 1
                        AND pe.estado_relacion = 'ACTIVO'
                  LEFT JOIN persona rep ON rep.id_persona = pe.id_persona
                  LEFT JOIN parentesco par ON par.id_parentesco = pe.id_parentesco
                  LEFT JOIN contacto c ON c.id_persona = pe.id_persona
                  LEFT JOIN resultado_academico ra
                         ON ra.id_matricula = m.id_matricula
                        AND ra.id_anio_escolar = m.id_anio_escolar
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY p.primer_nombre, p.primer_apellido";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Nómina de solo lectura de la sección del docente (vistas nomina, contacto,
     * acta y cumpleaneros). La pantalla y el PDF usan ESTA misma consulta.
     *
     * Los filtros llegan ya validados por el Controller (lista blanca); aquí se
     * vuelven a acotar por tipo para que el método no dependa de quien lo llame.
     *
     * El representante legal no es una entidad propia: se deriva de
     * `persona_estudiante` (es_representante_legal = 1, relación ACTIVA). Si un
     * estudiante tuviera más de una fila marcada, se toma la más antigua para no
     * duplicar al alumno en la lista.
     *
     * Nunca toca `ficha_medica`, `retiro` ni `motivo_retiro`.
     *
     * @param array{q?:string,sexo?:string,mes?:int|null,vista?:string} $filtros
     * @return array<int,array<string,mixed>>
     */
    public function nominaSeccion(int $seccionId, array $filtros = []): array
    {
        $where  = [
            'm.id_estructura_academica = :estructura',
            Matriculas::condicionCursando('m'),
        ];
        $params = ['estructura' => $seccionId];

        $vista = (string) ($filtros['vista'] ?? 'nomina');

        /* BÚSQUEDA: un solo campo para todas las vistas, y busca por nombre,
           apellido Y representante legal. Antes solo lo buscaban el representante
           en las vistas de acta y contacto, así que en Nómina y Cumpleañeros
           escribir el nombre del representante no encontraba a nadie. Cada
           parámetro nombrado se declara por separado porque con prepares nativos
           un mismo marcador no puede repetirse en la sentencia. Se compara el
           nombre completo además de cada campo suelto, para que "leo pinto"
           encuentre a "Leo Pinto" y no solo a "leo". */
        $q = trim((string) ($filtros['q'] ?? ''));
        if ($q !== '') {
            $where[] = "({$this->exprNombre('p')} LIKE :q
                         OR CONCAT_WS(' ', NULLIF(p.primer_apellido, ''), NULLIF(p.segundo_apellido, ''), NULLIF(p.primer_nombre, ''), NULLIF(p.segundo_nombre, '')) LIKE :q2
                         OR p.numero_documento LIKE :q3
                         OR {$this->exprNombre('rep')} LIKE :q4
                         OR CONCAT_WS(' ', NULLIF(rep.primer_apellido, ''), NULLIF(rep.segundo_apellido, ''), NULLIF(rep.primer_nombre, ''), NULLIF(rep.segundo_nombre, '')) LIKE :q5
                         OR rep.numero_documento LIKE :q6)";
            $params += ['q' => "%$q%", 'q2' => "%$q%", 'q3' => "%$q%", 'q4' => "%$q%", 'q5' => "%$q%", 'q6' => "%$q%"];
        }

        $sexo = strtoupper(trim((string) ($filtros['sexo'] ?? '')));
        if ($sexo === 'F' || $sexo === 'M') {
            $where[] = 'p.sexo = :sexo';
            $params['sexo'] = $sexo;
        }

        $mes = isset($filtros['mes']) ? (int) $filtros['mes'] : 0;
        if ($mes >= 1 && $mes <= 12) {
            $where[] = 'MONTH(p.fecha_nacimiento) = :mes';
            $params['mes'] = $mes;
        }

        /* ORDEN. La pantalla permite cambiarlo sin recargar (lo reordena el JS
           sobre la lista ya cargada) y el PDF se arma con el mismo criterio, así
           que la clave se define UNA vez acá y el JS la replica. La collation de
           las tablas es utf8mb4_uca1400_ai_ci, que ordena el español igual que
           localeCompare('es').
             - apellido: 1er apellido, 2do apellido, 1er nombre, 2do nombre
             - nombre:    1er nombre, 1er apellido
             - edad:      por fecha de nacimiento. Ascendente = del más joven al
                          mayor, o sea fecha de nacimiento DESC.
           Cumpleañeros y Acta no son ordenables: el primero va siempre por día
           del mes y el segundo por apellido, fijo. */
        $sentido = strtolower(trim((string) ($filtros['sentido'] ?? 'asc'))) === 'desc' ? 'DESC' : 'ASC';
        $clave   = strtolower(trim((string) ($filtros['orden'] ?? 'apellido')));

        if ($vista === 'cumpleaneros') {
            $orderBy = 'DAY(p.fecha_nacimiento), p.primer_apellido, p.segundo_apellido, p.primer_nombre';
        } elseif ($vista === 'acta') {
            /* Fijo por apellido, sin opción de invertirlo. */
            $orderBy = 'p.primer_apellido, p.segundo_apellido, p.primer_nombre, p.segundo_nombre';
        } else {
            $dir     = $sentido === 'DESC' ? 'DESC' : 'ASC';
            $desempate = ', p.primer_apellido, p.primer_nombre, e.id_estudiante';
            $orderBy = match ($clave) {
                'nombre' => 'p.primer_nombre ' . $dir . ', p.primer_apellido ' . $dir . $desempate,
                /* La edad se ordena por la fecha de nacimiento, pero al revés:
                   el más joven es el que nació más tarde. */
                'edad'   => 'p.fecha_nacimiento ' . ($dir === 'DESC' ? 'ASC' : 'DESC') . $desempate,
                default  => 'p.primer_apellido ' . $dir . ', p.segundo_apellido ' . $dir
                            . ', p.primer_nombre ' . $dir . $desempate,
            };
        }

        $sql = "SELECT e.id_estudiante,
                       p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido,
                       TRIM(CONCAT_WS(' ', NULLIF(p.primer_apellido, ''), NULLIF(p.segundo_apellido, ''))) AS apellidos,
                       TRIM(CONCAT_WS(' ', NULLIF(p.primer_nombre, ''), NULLIF(p.segundo_nombre, ''))) AS nombres,
                       p.tipo_documento,
                       p.numero_documento,
                       p.sexo,
                       p.fecha_nacimiento,
                       TIMESTAMPDIFF(YEAR, p.fecha_nacimiento, CURDATE()) AS edad,
                       DAY(p.fecha_nacimiento) AS dia_cumple,
                       YEAR(CURDATE()) - YEAR(p.fecha_nacimiento) AS edad_cumple,
                       pe.id_persona AS id_representante,
                       TRIM(CONCAT_WS(' ', NULLIF(rep.primer_apellido, ''), NULLIF(rep.segundo_apellido, ''))) AS rep_apellidos,
                       TRIM(CONCAT_WS(' ', NULLIF(rep.primer_nombre, ''), NULLIF(rep.segundo_nombre, ''))) AS rep_nombres,
                       rep.tipo_documento AS rep_tipo_documento,
                       rep.numero_documento AS rep_numero_documento,
                       par.nombre AS parentesco,
                       c.codigo_telefono_principal AS rep_cod_tel,
                       c.numero_telefono_principal AS rep_num_tel,
                       c.codigo_telefono_alternativo AS rep_cod_tel_alt,
                       c.numero_telefono_alternativo AS rep_num_tel_alt,
                       d.calle_avenida       AS rep_calle,
                       d.nro_casa_apto       AS rep_nro,
                       d.sector_urbanizacion AS rep_sector,
                       d.punto_referencia    AS rep_referencia,
                       parq.nombre           AS rep_parroquia,
                       mun.nombre            AS rep_municipio,
                       est.nombre            AS rep_estado
                  FROM matricula m
                  INNER JOIN estudiante e ON e.id_estudiante = m.id_estudiante
                  INNER JOIN persona p ON p.id_persona = e.id_persona
                  LEFT JOIN persona_estudiante pe
                         ON pe.id_persona_estudiante = (
                              SELECT MIN(px.id_persona_estudiante)
                                FROM persona_estudiante px
                               WHERE px.id_estudiante = e.id_estudiante
                                 AND px.es_representante_legal = 1
                                 AND px.estado_relacion = 'ACTIVO')
                  LEFT JOIN persona rep ON rep.id_persona = pe.id_persona
                  LEFT JOIN parentesco par ON par.id_parentesco = pe.id_parentesco
                  LEFT JOIN contacto c ON c.id_persona = pe.id_persona
                  LEFT JOIN direccion d ON d.id_persona = pe.id_persona
                  LEFT JOIN parroquia parq ON parq.id_parroquia = d.id_parroquia
                  LEFT JOIN municipio mun ON mun.id_municipio = parq.id_municipio
                  LEFT JOIN estado est ON est.id_estado = mun.id_estado
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY {$orderBy}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $filas = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($filas as &$f) {
            $f['estudiante']    = $this->apellidosNombres($f['apellidos'], $f['nombres']);
            $f['representante'] = $this->apellidosNombres($f['rep_apellidos'], $f['rep_nombres']);
            $f['documento']     = $this->etiquetaDocumento($f['tipo_documento'], $f['numero_documento']);
            $f['rep_documento'] = $this->etiquetaDocumento($f['rep_tipo_documento'], $f['rep_numero_documento']);
            $f['rep_telefonos'] = array_values(array_filter([
                $this->telefonoCompleto($f['rep_cod_tel'], $f['rep_num_tel']),
                $this->telefonoCompleto($f['rep_cod_tel_alt'], $f['rep_num_tel_alt']),
            ]));

            /* Domicilio del representante con la redacción de Support\Direccion: la
               misma del modal de detalle del empleado, para que una dirección se lea
               igual en las dos pantallas. */
            $f['rep_direccion'] = Direccion::linea(
                $f['rep_calle']     ?? null,
                $f['rep_nro']       ?? null,
                $f['rep_sector']    ?? null,
                $f['rep_parroquia'] ?? null,
                $f['rep_municipio'] ?? null,
                $f['rep_estado']    ?? null,
                $f['rep_referencia'] ?? null
            );
        }
        unset($f);

        /* Las personas autorizadas para retirar solo se buscan cuando la vista las
           muestra. Es una segunda consulta, así que no se paga en las demás. */
        if ($vista === 'autorizados' && $filas !== []) {
            $porEstudiante = $this->autorizadosRetiro(array_map(
                static fn(array $f): int => (int) $f['id_estudiante'],
                $filas
            ));
            foreach ($filas as &$f) {
                $f['autorizados'] = $porEstudiante[(int) $f['id_estudiante']] ?? [];
            }
            unset($f);
        }

        /* Orden FIJO de las personas autorizadas de cada estudiante: por apellido y
           luego por nombre, con collation del español para que las tildes y la ñ
           caigan donde toca.

           La consulta ya viene en ese orden; se repite aquí a propósito. El orden
           de los AUTORIZADOS no lo elige el docente —el selector de la barra solo
           mueve a los estudiantes de grupo en grupo—, así que la garantía tiene
           que estar en el sitio donde se arma la lista y no depender de un
           ORDER BY que alguien puede tocar sin darse cuenta. `apellidosNombres()`
           devuelve "apellidos, nombres", y comparar el texto completo da el mismo
           resultado: por apellido primero, y el nombre solo desempataría. */
        foreach ($filas as &$f) {
            if (!empty($f['autorizados']) && is_array($f['autorizados'])) {
                usort(
                    $f['autorizados'],
                    static fn(array $a, array $b): int => strcoll(
                        $a['nombre'] ?? '',
                        $b['nombre'] ?? ''
                    )
                );
            }
        }
        unset($f);

        /* Cumpleañeros: marcar a quién ya le pasó el cumpleaños del mes que se está
           viendo. Sin esto, la columna se titula «Cumple» y al mirar un mes ya
           vencido parece que todos los cumpleaños están por llegar.

           El mes es el que eligió el docente (o el que está en curso, que es lo
           que el controller ya resuelve). Si es un mes anterior al actual, todos
           cumplieron; si es el de hoy, solo los que ya pasaron su día; si es
           posterior, ninguno. La comparación es de mes en mes, no de fechas, que
           es lo que un docente entiende al mirar un mes del calendario. */
        if ($vista === 'cumpleaneros' && $filas !== []) {
            $hoyCaracas = new \DateTimeImmutable('now', new \DateTimeZone('America/Caracas'));
            $mesActual  = (int) $hoyCaracas->format('n');
            $diaHoy     = (int) $hoyCaracas->format('j');
            $mesVista   = isset($filtros['mes']) && $filtros['mes'] !== null
                ? (int) $filtros['mes']
                : $mesActual;

            foreach ($filas as &$f) {
                $dia = (int) ($f['dia_cumple'] ?? 0);
                $f['cumple_ya_paso'] = match (true) {
                    $mesVista < $mesActual                            => true,
                    $mesVista === $mesActual && $dia > 0 && $dia < $diaHoy => true,
                    default                                           => false,
                };
            }
            unset($f);
        }

        return $filas;
    }

    /**
     * Personas autorizadas para retirar a cada estudiante (sin el representante
     * legal, que ya sale en su propia columna), con su parentesco y sus teléfonos.
     *
     * @param  array<int,int> $idsEstudiantes
     * @return array<int,array<int,array{nombre:string,parentesco:string,telefonos:array<int,string>}>>
     */
    private function autorizadosRetiro(array $idsEstudiantes): array
    {
        $idsEstudiantes = array_values(array_unique(array_map('intval', $idsEstudiantes)));
        if ($idsEstudiantes === []) {
            return [];
        }

        $marcadores = implode(',', array_fill(0, count($idsEstudiantes), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT ax.id_estudiante,
                    TRIM(CONCAT_WS(' ', NULLIF(pa.primer_apellido, ''), NULLIF(pa.segundo_apellido, ''))) AS apellidos,
                    TRIM(CONCAT_WS(' ', NULLIF(pa.primer_nombre, ''), NULLIF(pa.segundo_nombre, ''))) AS nombres,
                    par.nombre AS parentesco,
                    c.codigo_telefono_principal, c.numero_telefono_principal,
                    c.codigo_telefono_alternativo, c.numero_telefono_alternativo
               FROM persona_estudiante ax
               INNER JOIN persona pa ON pa.id_persona = ax.id_persona
               LEFT JOIN parentesco par ON par.id_parentesco = ax.id_parentesco
               LEFT JOIN contacto c ON c.id_persona = ax.id_persona
              WHERE ax.id_estudiante IN ({$marcadores})
                AND ax.es_autorizado_retiro = 1
                AND ax.es_representante_legal = 0
                AND ax.estado_relacion = 'ACTIVO'
              ORDER BY ax.id_estudiante, pa.primer_apellido, pa.segundo_apellido, pa.primer_nombre"
        );
        $stmt->execute($idsEstudiantes);

        $resultado = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $resultado[(int) $r['id_estudiante']][] = [
                'nombre'     => $this->apellidosNombres($r['apellidos'], $r['nombres']),
                'parentesco' => (string) ($r['parentesco'] ?? ''),
                'telefonos'  => array_values(array_filter([
                    $this->telefonoCompleto($r['codigo_telefono_principal'], $r['numero_telefono_principal']),
                    $this->telefonoCompleto($r['codigo_telefono_alternativo'], $r['numero_telefono_alternativo']),
                ])),
            ];
        }

        return $resultado;
    }

    /** "Apellidos, Nombres" armado desde columnas separadas (sin coma suelta si falta una parte). */
    private function apellidosNombres(?string $apellidos, ?string $nombres): string
    {
        $partes = array_filter(
            [trim((string) $apellidos), trim((string) $nombres)],
            static fn(string $v): bool => $v !== ''
        );

        return implode(', ', $partes);
    }

    /** "V-12.345.678", "CE-11512345678" o '' si no hay número. */
    private function etiquetaDocumento(?string $tipo, ?string $numero): string
    {
        $numero = trim((string) $numero);
        if ($numero === '') {
            return '';
        }
        $tipo = strtoupper(trim((string) $tipo)) ?: 'V';
        if ($tipo === 'CE' || $tipo === 'P') {
            return "{$tipo}-{$numero}";
        }
        $digitos = preg_replace('/\D/', '', $numero);

        return $tipo . '-' . ($digitos !== '' ? number_format((int) $digitos, 0, '', '.') : $numero);
    }

    /** "0414-1234567" o '' si falta el número. */
    private function telefonoCompleto(?string $codigo, ?string $numero): string
    {
        $codigo = trim((string) $codigo);
        $numero = trim((string) $numero);
        if ($numero === '') {
            return '';
        }

        return $codigo !== '' ? "{$codigo}-{$numero}" : $numero;
    }

    /**
     * Cumpleaños del mes en curso, ordenados por día.
     * Solo informativo: no dispara nada ni guarda preferencias.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getCumpleanosDocente(int $idEstructura): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT e.id_estudiante,
                    {$this->exprNombre('p')} AS nombre,
                    p.fecha_nacimiento,
                    DAY(p.fecha_nacimiento) AS dia,
                    TIMESTAMPDIFF(YEAR, p.fecha_nacimiento, CURDATE()) AS edad,
                    DATEDIFF(
                        DATE(CONCAT(YEAR(CURDATE()), '-', MONTH(p.fecha_nacimiento), '-', DAY(p.fecha_nacimiento))),
                        CURDATE()
                    ) AS dias_faltantes
               FROM matricula m
               INNER JOIN estudiante e ON e.id_estudiante = m.id_estudiante
               INNER JOIN persona p ON p.id_persona = e.id_persona
              WHERE m.id_estructura_academica = :estructura
                AND " . Matriculas::condicionCursando('m') . "
                AND MONTH(p.fecha_nacimiento) = MONTH(CURDATE())
              ORDER BY dia, p.primer_nombre"
        );
        $stmt->execute(['estructura' => $idEstructura]);
        $filas = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($filas as &$f) {
            $d = (int) $f['dias_faltantes'];
            $f['etiqueta'] = $d === 0 ? 'Hoy' : ($d <= 7 ? 'Próximos 7 días' : '');
        }

        return $filas;
    }

    /**
     * Resumen general de la sección para las stat-cards del dashboard docente.
     *
     * @param int $idEstructura
     * @return array{total: int, varones: int, pct_varones: float, hembras: int, pct_hembras: float, edad_promedio: float, edad_min: int|null, edad_max: int|null}
     */
    public function getResumenDashboardDocente(int $idEstructura): array
    {
        $filas = $this->getNominaDocente($idEstructura);
        $total = count($filas);

        $varones = 0;
        $hembras = 0;
        $sumaEdades = 0;
        $edadMin = null;
        $edadMax = null;

        foreach ($filas as $f) {
            $sexo = strtoupper((string) ($f['sexo'] ?? ''));
            if ($sexo === 'M') {
                $varones++;
            } else {
                $hembras++;
            }
            $edad = (int) ($f['edad'] ?? 0);
            $sumaEdades += $edad;
            $edadMin = $edadMin === null ? $edad : min($edadMin, $edad);
            $edadMax = $edadMax === null ? $edad : max($edadMax, $edad);
        }

        $pctVarones = $total > 0 ? round(($varones / $total) * 100, 1) : 0.0;
        $pctHembras = $total > 0 ? round(($hembras / $total) * 100, 1) : 0.0;
        $promedio   = $total > 0 ? round($sumaEdades / $total, 1) : 0.0;

        return [
            'total'         => $total,
            'varones'       => $varones,
            'pct_varones'   => $pctVarones,
            'hembras'       => $hembras,
            'pct_hembras'   => $pctHembras,
            'edad_promedio' => $promedio,
            'edad_min'      => $edadMin,
            'edad_max'      => $edadMax,
        ];
    }

    /**
     * Distribución de la sección por sexo y por edad cumplida.
     * Calcula cantidades, porcentajes y marca la edad más frecuente con 'es_mas_comun'.
     *
     * @param int $idEstructura
     * @return array{total: int, sexo: array{varones: int, pct_varones: float, hembras: int, pct_hembras: float}, edades: list<array{edad: int, cantidad: int, porcentaje: float, es_mas_comun: bool}>}
     */
    public function getDistribucionSeccion(int $idEstructura): array
    {
        $filas = $this->getNominaDocente($idEstructura);
        $total = count($filas);

        $varones = 0;
        $hembras = 0;
        $conteoEdades = [];

        foreach ($filas as $f) {
            $sexo = strtoupper((string) ($f['sexo'] ?? ''));
            if ($sexo === 'M') {
                $varones++;
            } else {
                $hembras++;
            }
            $edad = (int) ($f['edad'] ?? 0);
            if ($edad > 0) {
                $conteoEdades[$edad] = ($conteoEdades[$edad] ?? 0) + 1;
            }
        }

        ksort($conteoEdades);

        // Determinar la edad más común (la de mayor frecuencia)
        $maxFrecuencia = 0;
        $edadMasComun = null;
        foreach ($conteoEdades as $edad => $cant) {
            if ($cant > $maxFrecuencia) {
                $maxFrecuencia = $cant;
                $edadMasComun = $edad;
            }
        }

        $edades = [];
        foreach ($conteoEdades as $edad => $cant) {
            $pct = $total > 0 ? round(($cant / $total) * 100, 1) : 0.0;
            $edades[] = [
                'edad'        => $edad,
                'cantidad'    => $cant,
                'porcentaje'  => $pct,
                'es_mas_comun' => ($edad === $edadMasComun && $cant > 1),
            ];
        }

        return [
            'total' => $total,
            'sexo'  => [
                'varones'     => $varones,
                'pct_varones' => $total > 0 ? round(($varones / $total) * 100, 1) : 0.0,
                'hembras'     => $hembras,
                'pct_hembras' => $total > 0 ? round(($hembras / $total) * 100, 1) : 0.0,
            ],
            'edades' => $edades,
        ];
    }

    /**
     * Datos de cumpleaños para el dashboard docente:
     * - Cumpleañero/a de hoy (tarjeta destacada)
     * - Lista de cumpleaños del mes actual con estados ("Pasado", "HOY", "En N días")
     *
     * @param int $idEstructura
     * @return array{mes_nombre: string, cumpleanero_hoy: array|null, cumpleanos_mes: list<array>}
     */
    public function getCumpleanosDashboard(int $idEstructura): array
    {
        // Forzar zona horaria local Venezuela
        date_default_timezone_set('America/Caracas');

        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];
        $mesesAbrev = [
            1 => 'FEB', 2 => 'FEB', 3 => 'MAR', 4 => 'ABR',
            5 => 'MAY', 6 => 'JUN', 7 => 'JUL', 8 => 'AGO',
            9 => 'SEP', 10 => 'OCT', 11 => 'NOV', 12 => 'DIC'
        ];
        // Corregir mes 1 = ENE
        $mesesAbrev[1] = 'ENE';

        $mesActual = (int) date('n');
        $diaActual = (int) date('j');

        $stmt = $this->pdo->prepare(
            "SELECT e.id_estudiante,
                    {$this->exprNombre('p')} AS nombre_completo,
                    p.primer_nombre,
                    p.segundo_nombre,
                    p.primer_apellido,
                    p.segundo_apellido,
                    p.fecha_nacimiento,
                    DAY(p.fecha_nacimiento) AS dia_nac,
                    MONTH(p.fecha_nacimiento) AS mes_nac,
                    TIMESTAMPDIFF(YEAR, p.fecha_nacimiento, CURDATE()) AS edad_actual
               FROM matricula m
               INNER JOIN estudiante e ON e.id_estudiante = m.id_estudiante
               INNER JOIN persona p ON p.id_persona = e.id_persona
              WHERE m.id_estructura_academica = :estructura
                AND m.tipo IN ('INSCRIPCION', 'RATIFICACION')
                AND MONTH(p.fecha_nacimiento) = :mes
              ORDER BY dia_nac ASC, p.primer_nombre ASC, p.primer_apellido ASC"
        );
        $stmt->execute(['estructura' => $idEstructura, 'mes' => $mesActual]);
        $filas = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $cumpleaneroHoy = null;
        $cumpleanosMes  = [];

        foreach ($filas as $f) {
            $diaNac     = (int) $f['dia_nac'];
            $edadActual = (int) $f['edad_actual'];

            // Formatear nombre corto para el listado (ej: Andrés E. Silva)
            $inicialSegundo = !empty($f['segundo_nombre']) ? ' ' . mb_substr($f['segundo_nombre'], 0, 1, 'UTF-8') . '.' : '';
            $nombreCorto    = trim(($f['primer_nombre'] ?? '') . $inicialSegundo . ' ' . ($f['primer_apellido'] ?? ''));

            // Iniciales para avatar
            $iniciales = mb_strtoupper(mb_substr($f['primer_nombre'] ?? '', 0, 1, 'UTF-8'))
                       . mb_strtoupper(mb_substr($f['primer_apellido'] ?? '', 0, 1, 'UTF-8'));

            if ($diaNac < $diaActual) {
                // Pasado
                $edad = $edadActual;
                $estado = 'Pasado';
                $estadoTipo = 'pasado';
                $textoEdad = "Cumplió {$edad} años";
                $esHoy = false;
            } elseif ($diaNac === $diaActual) {
                // HOY
                $edad = $edadActual;
                $estado = '¡HOY!';
                $estadoTipo = 'hoy';
                $textoEdad = "Cumple {$edad} años";
                $esHoy = true;

                if ($cumpleaneroHoy === null) {
                    $cumpleaneroHoy = [
                        'id_estudiante'    => (int) $f['id_estudiante'],
                        'nombre'           => $f['nombre_completo'],
                        'iniciales'        => $iniciales ?: 'ES',
                        'edad'             => $edad,
                        'texto'            => "¡Cumple {$edad} años hoy!",
                        'fecha_formateada' => "{$diaActual} de " . ($meses[$mesActual] ?? ''),
                    ];
                }
            } else {
                // Futuro este mes
                $diasFaltantes = $diaNac - $diaActual;
                $edad = $edadActual + 1; // Cumplirá su siguiente año
                $estado = ($diasFaltantes === 1) ? 'Mañana' : "En {$diasFaltantes} días";
                $estadoTipo = 'proximo';
                $textoEdad = "Cumple {$edad} años";
                $esHoy = false;
            }

            $cumpleanosMes[] = [
                'id_estudiante'   => (int) $f['id_estudiante'],
                'dia'             => str_pad((string) $diaNac, 2, '0', STR_PAD_LEFT),
                'mes_abrev'       => $mesesAbrev[$mesActual] ?? '',
                'nombre'          => $nombreCorto ?: $f['nombre_completo'],
                'nombre_completo' => $f['nombre_completo'],
                'edad'            => $edad,
                'texto_edad'      => $textoEdad,
                'estado'          => $estado,
                'estado_tipo'     => $estadoTipo,
                'es_hoy'          => $esHoy,
            ];
        }

        return [
            'mes_nombre'      => $meses[$mesActual] ?? '',
            'cumpleanero_hoy' => $cumpleaneroHoy,
            'cumpleanos_mes'  => $cumpleanosMes,
        ];
    }

    /**
     * Obtiene el total de estudiantes cursando en la sección agrupados por continuidad:
     * - Ratificados (tipo 'RATIFICACION')
     * - Nuevos ingresos (tipo 'INSCRIPCION')
     *
     * @param int $idEstructura
     * @return array{
     *     total: int,
     *     ratificados: int,
     *     pct_ratificados: float,
     *     nuevos: int,
     *     pct_nuevos: float
     * }
     */
    public function getIngresoYRatificados(int $idEstructura): array
    {
        $condCursando = Matriculas::condicionCursando('m');

        $sql = "SELECT m.tipo,
                       COUNT(DISTINCT m.id_estudiante) AS cantidad
                  FROM matricula m
                 WHERE m.id_estructura_academica = :estructura
                   AND {$condCursando}
                 GROUP BY m.tipo";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['estructura' => $idEstructura]);
        $filas = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $ratificados = 0;
        $nuevos = 0;

        foreach ($filas as $fila) {
            $tipo = (string) ($fila['tipo'] ?? '');
            $cant = (int) ($fila['cantidad'] ?? 0);

            if ($tipo === 'RATIFICACION') {
                $ratificados += $cant;
            } elseif ($tipo === 'INSCRIPCION') {
                $nuevos += $cant;
            }
        }

        $total = $ratificados + $nuevos;

        $pctRatificados = $total > 0 ? round(($ratificados / $total) * 100, 1) : 0.0;
        $pctNuevos      = $total > 0 ? round(($nuevos / $total) * 100, 1) : 0.0;

        return [
            'total'           => $total,
            'ratificados'     => $ratificados,
            'pct_ratificados' => $pctRatificados,
            'nuevos'          => $nuevos,
            'pct_nuevos'      => $pctNuevos,
        ];
    }

    private function filters(array $filters): array
    {
        $where = [];
        $params = [];
        [$whereDocente, $paramsDocente] = $this->restriccionDocente(
            isset($filters['id_docente']) && $filters['id_docente'] !== '' ? (int) $filters['id_docente'] : null
        );
        foreach ($whereDocente as $condicion) {
            $where[] = $condicion;
        }
        $params = array_merge($params, $paramsDocente);

        if (!empty($filters['q'])) {
            foreach (['p.primer_nombre', 'p.primer_apellido', 'p.numero_documento'] as $i => $col) {
                $params["q$i"] = '%' . $filters['q'] . '%';
            }
            $where[] = "(p.primer_nombre LIKE :q0 OR p.primer_apellido LIKE :q1 OR p.numero_documento LIKE :q2)";
        }
        if (!empty($filters['grado'])) {
            $where[] = 'g.nombre = :grado';
            $params['grado'] = $filters['grado'];
        }
        if (!empty($filters['seccion'])) {
            $where[] = 's.nombre = :seccion';
            $params['seccion'] = $filters['seccion'];
        }
        /* El filtro trabaja sobre el estado del CICLO (la misma expresión que la columna), no
 sobre `persona.estado`: un egresado no tiene por qué estar inactivo como persona,
 y con el filtro anterior se podía filtrar "activos" y recibirlos como si lo
 fueran. Los tres valores son excluyentes, así que `inactivo` agrupa a los dos
 desenlaces finales para no romper los enlaces ya compartidos.*/
        if (!empty($filters['estado'])) {
            $estado = strtolower(trim((string) $filters['estado']));
            if ($estado === 'inactivo') {
                $where[] = "(" . self::EXPRESION_ESTADO . " IN ('retirado', 'egresado'))";
            } elseif (in_array($estado, ['activo', 'retirado', 'egresado'], true)) {
                $where[] = self::EXPRESION_ESTADO . " = :estado_ciclo";
                $params['estado_ciclo'] = $estado;
            }
        }
        return [$where, $params];
    }
}