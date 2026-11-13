<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\AniosEscolares;
use App\Support\ConQuienVive;
use App\Support\Instituciones;
use App\Interfaces\Listable;
use App\Support\Matriculas;

use App\Traits\CupoEstructuraTrait;
use App\Traits\FamiliaresModelTrait;
use App\Traits\RecaudosModelTrait;

use PDO;
use Throwable;
use RuntimeException;

/**
 * Ratificación de matrícula: proyecta al estudiante al siguiente grado (misma sección y turno) en el año en curso.
 * INSCRIPCIÓN es el primer ingreso y ocurre una sola vez; RATIFICACIÓN es cada año siguiente, y se decide con flujoPara().
 * Ratificar crea una MATRÍCULA NUEVA de tipo RATIFICACION; la inscripción es única y no se vuelve a crear.
*/
class RatificationModel extends BaseModel implements Listable
{
    use CupoEstructuraTrait;
    use FamiliaresModelTrait;
    use RecaudosModelTrait;

    // Misma fuente de disponibilidad que la inscripción.
    /* La escritura de familiares es la MISMA que hace la inscripción (grado
 siguiente, misma sección, etc. no aplica aquí): se comparte para que las dos
 pantallas no se desincronicen. Ver FamiliaresModelTrait.*/
    
    


    /**
 * La ratificación ESCRIBE en `matricula`, no en una tabla propia. Apuntar aquí a
 * `matricula` es lo que hace el CRUD genérico de BaseModel coherente con lo que el
 * modelo realmente hace, y quita la tabla fantasma de en medio.
 */
    protected string $table = 'matricula';
    protected string $primaryKey = 'id_matricula';

    protected array $columns = ['id_matricula', 'id_estudiante', 'fecha_matricula', 'id_anio_escolar', 'id_estructura_academica', 'tipo', 'motivo_anticipo', 'fecha_creacion'];
    protected array $allowedColumns = ['id_matricula', 'id_estudiante', 'fecha_matricula', 'id_anio_escolar', 'id_estructura_academica', 'tipo', 'motivo_anticipo', 'fecha_creacion'];

    /**
 * Une cada estudiante con su matrícula de ORIGEN y con la del año en curso (`sig`).
 * La de origen decide el próximo grado; la del año en curso, si ya está ratificado.
 * Devuelve una fila por estudiante, aunque no tenga matrícula en el año en curso.
*/
    private function joinActual(): string
    {
        $anio = $this->anioEnCurso();

        return "LEFT JOIN matricula m ON m.id_matricula = (
                    /**
 * La última matrícula del alumno de un año ANTERIOR al en curso. Va como subconsulta
 * ESCALAR en el ON y no como derived table con WHERE correlacionado: MariaDB no tiene
 * LATERAL, así que una tabla derivada que menciona `est` en su WHERE no se puede
 * resolver.
 */
                    SELECT m2.id_matricula
                      FROM matricula m2
                      JOIN anio_escolar a2 ON a2.id_anio_escolar = m2.id_anio_escolar
                     WHERE m2.id_estudiante = est.id_estudiante
                       AND " . $this->condicionAnioAnterior('a2', 'a2.id_anio_escolar') . "
                     ORDER BY a2.anio_inicio DESC, m2.id_matricula DESC
                     LIMIT 1
                )
                LEFT JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
                /* El año es el de la MATRÍCULA, no el de la estructura: son el mismo
 dato hoy, pero el de la matrícula es el que dice de qué año se
 está ratificando, y el de la estructura depende de cuándo se
 cargó la combinación.*/
                LEFT JOIN anio_escolar a ON a.id_anio_escolar = m.id_anio_escolar
                LEFT JOIN grado g ON g.id_grado = ea.id_grado
                LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion
                LEFT JOIN turno t ON t.id_turno = ea.id_turno
                LEFT JOIN matricula sig ON sig.id_estudiante = est.id_estudiante
                                     AND sig.id_anio_escolar = $anio
                LEFT JOIN resultado_academico ra ON ra.id_estudiante = m.id_estudiante
                                                 AND ra.id_anio_escolar = m.id_anio_escolar";
    }

    /**
 * Situación del alumno, de más a menos terminal: egresado, retirado, ratificado (tiene fila en el año en curso) o pendiente.
 * Se lee del tipo de la fila del año en curso y de si existe una posterior.
*/
    private const EXPRESION_ESTADO = "CASE
            WHEN m.tipo = 'EGRESO' THEN 'egresado'
            WHEN m.tipo = 'RETIRO' THEN 'retirado'
            WHEN sig.id_matricula IS NOT NULL THEN 'ratificado'
            ELSE 'pendiente'
        END";

    /**
 * Proceso que le corresponde a cada estudiante (misma pregunta que el guardado: ¿tiene matrícula previa?).
 * Se evalúa sobre toda la historia del alumno y no sobre la matrícula vigente.
*/
    private const EXPRESION_FLUJO = "CASE WHEN EXISTS (
                SELECT 1 FROM matricula mi WHERE mi.id_estudiante = est.id_estudiante
             ) THEN 'RATIFICACION' ELSE 'INSCRIPCION' END";

    /** Tipos con el ciclo cerrado (no es un error del dato): se muestran para que se vea por qué quedan fuera.*/
    private const ESTADOS_CERRADOS = ['EGRESO', 'RETIRO'];

    /* Estado de una relación que sigue vigente. Va como constante y no en la consulta
 porque el string de SQL va entrecomillado en PHP y un "'ACTIVO'" suelto ahí lo
 cortaría en dos.*/
    private const ESTADO_VIGENTE = 'ACTIVO';
    private const ESTADO_DESVINCULADO = 'INACTIVO';

    /** Procesos de ingreso al plantel. La inscripción es única y la primera.*/
    public const FLUJO_INSCRIPCION  = 'INSCRIPCION';
    public const FLUJO_RATIFICACION = 'RATIFICACION';
    public const FLUJO_REINGRESO    = 'REINGRESO';

    /**
 * EL CRITERIO: ¿tiene el estudiante alguna matrícula previa? No → INSCRIPCIÓN; sí
 * → RATIFICACIÓN.
 *
 * @return self::FLUJO_*
 */
    public function flujoPara(int $estudianteId): string
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM matricula WHERE id_estudiante = :estudiante'
        );
        $stmt->execute(['estudiante' => $estudianteId]);
        return ((int) $stmt->fetchColumn() > 0)
            ? self::FLUJO_RATIFICACION
            : self::FLUJO_INSCRIPCION;
    }

    /**
 * Lista de la ratificación: quién está pendiente y por qué.
 *
 * @return array<int, array<string, mixed>>
*/
    public function getAll(array $filters = [], int $page = 1, int $perPage = 10): array
    {
        [$where, $params] = $this->filters($filters);
        $grados = $this->gradosOrdenados();

        $sql = "SELECT est.id_estudiante AS id,
                       CONCAT_WS(' ', p.primer_nombre, p.primer_apellido) AS nombre,
                       CONCAT(p.tipo_documento, '-', p.numero_documento) AS cedula,
                       p.foto AS avatar_img,
                       UPPER(CONCAT(LEFT(p.primer_nombre, 1), COALESCE(LEFT(p.primer_apellido, 1), ''))) AS iniciales,
                       COALESCE(g.nombre, 'Sin grado') AS grado_actual,
                       ea.id_grado AS id_grado,
                       ea.id_seccion AS id_seccion,
                       ea.id_turno AS id_turno,
                       COALESCE(s.nombre, 'Sin sección') AS seccion,
                       COALESCE(t.nombre, 'Sin turno') AS turno,
                       a.nombre_periodo AS anio_actual,
                       ra.estado_resultado AS resultado_academico_estado,
                       ra.literal AS resultado_academico_literal,
                       ra.id_grado_destino AS resultado_id_grado_destino,
                       " . self::EXPRESION_ESTADO . " AS estado,
                       " . self::EXPRESION_FLUJO . " AS flujo
                FROM estudiante est
                INNER JOIN persona p ON p.id_persona = est.id_persona
                " . $this->joinActual();
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
        foreach ($rows as &$row) {
            $row['id']           = (int) $row['id'];
            $row['avatar_color'] = 'primary';
            $row['iniciales']    = $row['iniciales'] ?: 'ES';
            $row['id_grado']     = (int) $row['id_grado'];
            $row['id_seccion']   = (int) $row['id_seccion'];
            $row['id_turno']     = (int) $row['id_turno'];

            // Lógica de grado sugerido según literal cargado del año cursado:
            $estadoRes  = $row['resultado_academico_estado'] ?? null;
            $literalRes = $row['resultado_academico_literal'] ?? null;

            if (in_array($literalRes, ['A', 'B', 'C', 'D'], true) || $estadoRes === 'PROMOVIDO') {
                $siguiente = $this->siguienteGradoConId($grados, $row['id_grado']);
                $row['proximo_grado']    = $siguiente['nombre'] ?? '—';
                $row['proximo_grado_id'] = (int) ($siguiente['id'] ?? 0);
                $row['promocion_badge']  = 'Promovido' . ($literalRes ? " ({$literalRes})" : '');
                $row['promocion_tipo']   = 'promovido';
            } elseif ($literalRes === 'E' || $estadoRes === 'NO_PROMOVIDO') {
                // Alumno no promovido: sugerir el MISMO grado (repitiente)
                $row['proximo_grado']    = ($row['grado_actual'] ?? 'Grado actual') . ' (Repite)';
                $row['proximo_grado_id'] = (int) $row['id_grado'];
                $row['promocion_badge']  = 'No promovido' . ($literalRes ? " ({$literalRes})" : '');
                $row['promocion_tipo']   = 'no_promovido';
            } else {
                // Sin literal cargado por la docente
                $row['proximo_grado']    = '—';
                $row['proximo_grado_id'] = 0;
                $row['promocion_badge']  = 'Sin literal';
                $row['promocion_tipo']   = 'sin_literal';
            }
        }
        return $rows;
    }

    public function countAll(array $filters = []): int
    {
        [$where, $params] = $this->filters($filters);

        $sql = "SELECT COUNT(*) FROM estudiante est
                INNER JOIN persona p ON p.id_persona = est.id_persona
                " . $this->joinActual();
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
 * Números del panel, sobre el mismo criterio que el listado para que cuadren con la tabla.
 * "Por ratificar" son solo los pendientes con matrícula previa; retirados y egresados no cuentan, y los alumnos para inscribir son otro proceso.
*/
    public function getStats(?int $idTurno = null): array
    {
        $where = $this->condicionesBase();
        $params = [];
        if ($idTurno !== null) {
            $where[] = 't.id_turno = :turno';
            $params['turno'] = $idTurno;
        }

        $sql = "SELECT COUNT(*) AS total,
                       COALESCE(SUM(" . self::EXPRESION_ESTADO . " = 'ratificado'), 0) AS ya_ratificados,
                       COALESCE(SUM(" . self::EXPRESION_ESTADO . " = 'pendiente'
                                    AND " . self::EXPRESION_FLUJO . " = 'RATIFICACION'), 0) AS por_ratificar,
                       COALESCE(SUM(m.tipo = 'EGRESO'), 0) AS egresados,
                       COALESCE(SUM(m.tipo = 'RETIRO'), 0) AS retirados
                FROM estudiante est
                INNER JOIN persona p ON p.id_persona = est.id_persona
                " . $this->joinActual() . "
                WHERE " . implode(' AND ', $where);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $yaRatificados = (int) ($row['ya_ratificados'] ?? 0);
        $porRatificar  = (int) ($row['por_ratificar'] ?? 0);
        $egresados     = (int) ($row['egresados'] ?? 0);
        $retirados     = (int) ($row['retirados'] ?? 0);

        // Denominador: pendientes más ya hechos; retirados y egresados quedan fuera porque su año se cerró.
        $ratificables = $porRatificar + $yaRatificados;

        return [
            'porRatificar' => $porRatificar,
            'yaRatificados' => $yaRatificados,
            'egresados'    => $egresados,
            'retirados'    => $retirados,
            // El avance se mide sobre los ratificables; si no, una tanda de graduados inflaría el porcentaje.
            'avance'       => $ratificables > 0
                ? (int) round(($yaRatificados / $ratificables) * 100)
                : 0,
        ];
    }

    public function getGrados(): array
    {
        return array_column($this->gradosOrdenados(), 'nombre');
    }

    public function getSecciones(): array
    {
        return $this->pdo->query("SELECT nombre FROM seccion WHERE estado = 'ACTIVO' ORDER BY nombre ASC")
                         ->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
 * El año escolar al que se ratifica: el único y fijo para toda la pantalla. Es el
 * ACTIVO porque solo en un período abierto tiene sentido registrar una matrícula
 * nueva.
 *
 * @return array{id:int,nombre:string,estado:string,es_escribible:bool}
 */
    public function getAnioDestino(): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id_anio_escolar AS id, nombre_periodo AS nombre, estado, anio_inicio
               FROM anio_escolar
              WHERE estado IN ('ACTIVO', 'PLANIFICADO')
              ORDER BY (estado = 'ACTIVO') DESC, anio_inicio ASC
              LIMIT 1"
        );
        $stmt->execute();
        $anio = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$anio) {
            return ['id' => 0, 'nombre' => '—', 'estado' => '', 'es_escribible' => false, 'anio_inicio' => 0];
        }

        return [
            'id'            => (int) $anio['id'],
            'nombre'        => (string) $anio['nombre'],
            'estado'        => (string) $anio['estado'],
            'es_escribible' => strtoupper((string) $anio['estado']) === 'ACTIVO',
            'anio_inicio'   => (int) ($anio['anio_inicio'] ?? 0),
        ];
    }

    /**
 * Secciones que existen para un grado en el año escolar indicado. Lo usa el selector
 * de sección del modal cuando la secretaria cambia el grado (ratificacion/secciones,
 * por AJAX).
 *
 * @return array<int, array{id:int,nombre:string}>
 */
    public function getSeccionesPorGrado(int $idGrado, int $idAnioEscolar): array
    {
        if ($idGrado <= 0 || $idAnioEscolar <= 0) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            'SELECT DISTINCT s.id_seccion AS id, s.nombre
               FROM estructura_academica ea
               INNER JOIN seccion s ON s.id_seccion = ea.id_seccion
              WHERE ea.id_grado = :grado AND ea.id_anio_escolar = :anio
                AND s.estado = \'ACTIVO\'
              ORDER BY s.nombre'
        );
        $stmt->execute(['grado' => $idGrado, 'anio' => $idAnioEscolar]);

        return array_map(static fn (array $s): array => [
            'id'     => (int) $s['id'],
            'nombre' => (string) $s['nombre'],
        ], $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
 * Combinaciones registradas en el año en curso, para los <select> del modal. Se manda
 * la lista UNA vez en el HTML y el navegador filtra por grado y por sección, igual que
 * hace el paso 3 del wizard de inscripción (enrollments.js::cascadaAcademica).
 *
 * @return array<int, array{id_grado:int,id_seccion:int,id_turno:int,
 */
    public function getCombinacionesAnioEnCurso(?int $idTurno = null): array
    {
        if ($this->anioEnCurso() <= 0) {
            return [];
        }

        return array_map(static fn (array $c): array => [
            'id_grado'     => (int) $c['id_grado'],
            'id_seccion'   => (int) $c['id_seccion'],
            'id_turno'     => (int) $c['id_turno'],
            'grado'        => (string) $c['grado'],
            'seccion'      => (string) $c['seccion'],
            'turno'        => (string) $c['turno'],
            'ocupados'     => (int) $c['ocupados'],
            'capacidad'    => (int) $c['capacidad_maxima'],
            'disponibles'  => (int) $c['disponibles'],
        ], $this->estructurasConCupo($this->anioEnCurso(), $idTurno));
    }

    /** Turnos del catálogo, para el filtro del listado (misma fuente que las secciones).*/
    public function getTurnos(?int $idTurno = null): array
    {
        if ($idTurno !== null) {
            $stmt = $this->pdo->prepare("SELECT nombre FROM turno WHERE id_turno = :id AND estado = 'ACTIVO' ORDER BY nombre ASC");
            $stmt->execute(['id' => $idTurno]);
            return $stmt->fetchAll(\PDO::FETCH_COLUMN);
        }

        return $this->pdo->query("SELECT nombre FROM turno WHERE estado = 'ACTIVO' ORDER BY nombre ASC")
                         ->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
 * Datos del estudiante para el modal de edición, y también su id_persona. Devuelve
 * persona + talla + ficha en una sola consulta con subconsultas escalares, que es lo
 * mismo que se hace al escribir (ver actualizarEstudiante()): leer y escribir tienen
 * que mirar los mismos campos, o el formulario se llenaría con unos y se guardarían
 * otros.
 *
 * @param bool $incluirDatosSensibles false para docentes: no trae tallas ni ficha médica.
 * @return array<string, mixed>|null null si el estudiante no existe.
 */
    public function getEstudianteEditable(int $idEstudiante, bool $incluirDatosSensibles = true): ?array
    {
        $datosSensibles = $incluirDatosSensibles
            ? ",
                    (SELECT tu.talla_camisa    FROM talla_uniforme tu WHERE tu.id_estudiante = e.id_estudiante) AS talla_camisa,
                    (SELECT tu.talla_pantalon  FROM talla_uniforme tu WHERE tu.id_estudiante = e.id_estudiante) AS talla_pantalon,
                    (SELECT tu.talla_zapato    FROM talla_uniforme tu WHERE tu.id_estudiante = e.id_estudiante) AS talla_zapato,
                    (SELECT tu.observaciones  FROM talla_uniforme tu WHERE tu.id_estudiante = e.id_estudiante) AS talla_observaciones,
                    (SELECT fm.condiciones_especiales FROM ficha_medica fm WHERE fm.id_estudiante = e.id_estudiante) AS condiciones_especiales,
                    (SELECT fm.medicamentos   FROM ficha_medica fm WHERE fm.id_estudiante = e.id_estudiante) AS medicamentos,
                    (SELECT fm.observaciones  FROM ficha_medica fm WHERE fm.id_estudiante = e.id_estudiante) AS observaciones_medicas,
                    (SELECT fm.alergias       FROM ficha_medica fm WHERE fm.id_estudiante = e.id_estudiante) AS alergias,
                    (SELECT fm.enfermedades   FROM ficha_medica fm WHERE fm.id_estudiante = e.id_estudiante) AS enfermedades,
                    (SELECT fm.tipo_sangre    FROM ficha_medica fm WHERE fm.id_estudiante = e.id_estudiante) AS tipo_sangre"
            : '';

        $stmt = $this->pdo->prepare(
            'SELECT e.id_estudiante AS id_estudiante,
                    e.id_persona,
                    ' . ConQuienVive::expresionSql('e.id_estudiante') . ' AS con_quien_vive,
                    p.tipo_documento, p.numero_documento, p.fecha_nacimiento,
                    p.id_estado_nacimiento, p.id_parroquia_nacimiento, p.lugar_nacimiento_extranjero, p.id_nacionalidad,
                    pn.id_municipio AS id_municipio_nacimiento,
                    p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido,
                    p.sexo, p.foto' . $datosSensibles . '
               FROM estudiante e
               INNER JOIN persona p ON p.id_persona = e.id_persona
               LEFT JOIN parroquia pn ON pn.id_parroquia = p.id_parroquia_nacimiento
              WHERE e.id_estudiante = :id
              LIMIT 1'
        );
        $stmt->execute(['id' => $idEstudiante]);
        $fila = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $fila ?: null;
    }

    /**
 * Guarda los datos editables del estudiante: persona, talla y ficha médica. NO toca la
 * matrícula ni el año escolar: ratificar no es el proceso de cambiar de grado, y el
 * destino lo elige la secretaria en su propio modal.
 *
 * @param array $data ['persona'=>[...], 'talla_uniforme'=>[...],
 * @param array $familiares bloques ya normalizados por el controlador. Van
 */
    public function actualizarEstudiante(int $idEstudiante, array $data, array $familiares = []): bool
    {
        $idPersona = (int) ($this->getEstudianteEditable($idEstudiante)['id_persona'] ?? 0);
        if ($idPersona <= 0) {
            throw new RuntimeException("El estudiante {$idEstudiante} no existe.");
        }

        $this->pdo->beginTransaction();

        try {
            $persona = $data['persona'] ?? [];
            if ($persona) {
                $sets = array_map(
                    static fn (string $columna): string => "{$columna} = :{$columna}",
                    array_keys($persona)
                );
                $persona['id_persona'] = $idPersona;
                $this->pdo->prepare('UPDATE persona SET ' . implode(', ', $sets) . ' WHERE id_persona = :id_persona')
                      ->execute($persona);
            }

            $talla = $data['talla_uniforme'] ?? [];
            if ($talla) {
                $talla['id_estudiante'] = $idEstudiante;
                $columnas = ['camisa' => 'talla_camisa', 'pantalon' => 'talla_pantalon', 'zapato' => 'talla_zapato', 'observaciones' => 'observaciones'];
                $set  = [];
                $vals = [];
                foreach ($columnas as $clave => $columna) {
                    // Las tallas son NOT NULL: vacío se guarda como cadena vacía, no como NULL.
                    $set[] = "{$columna} = :{$clave}";
                    $vals[$clave] = $talla[$clave] ?? '';
                }
                /* fecha_actualizacion es NOT NULL y NO tiene default: si no se
 manda, el INSERT falla con el error 1364. Por eso el CURDATE()
 en las dos ramas, como en EnrollmentModel::create().*/
                $this->pdo->prepare(
                    'INSERT INTO talla_uniforme (id_estudiante, talla_camisa, talla_pantalon, talla_zapato, observaciones, fecha_actualizacion)
                     VALUES (:id_estudiante, :camisa, :pantalon, :zapato, :observaciones, CURDATE())
                     ON DUPLICATE KEY UPDATE talla_camisa = VALUES(talla_camisa),
                                             talla_pantalon = VALUES(talla_pantalon),
                                             talla_zapato = VALUES(talla_zapato),
                                             observaciones = VALUES(observaciones),
                                             fecha_actualizacion = CURDATE()'
                )->execute(array_merge($vals, ['id_estudiante' => $idEstudiante]));
            }

            $ficha = $data['ficha_medica'] ?? [];
            if ($ficha) {
                $columnas = [
                    'condiciones_especiales' => 'condiciones_especiales',
                    'medicamentos'            => 'medicamentos',
                    'observaciones'           => 'observaciones',
                    'alergias'                => 'alergias',
                    'enfermedades'            => 'enfermedades',
                    'tipo_sangre'             => 'tipo_sangre',
                ];
                $set  = [];
                $vals = [];
                foreach ($columnas as $clave => $columna) {
                    $set[] = "{$columna} = :{$clave}";
                    $vals[$clave] = $ficha[$clave] ?? null;
                }
                $this->pdo->prepare(
                    'INSERT INTO ficha_medica (id_estudiante, condiciones_especiales, medicamentos, observaciones,
                                               alergias, enfermedades, tipo_sangre, fecha_actualizacion)
                     VALUES (:id_estudiante, :condiciones_especiales, :medicamentos, :observaciones,
                             :alergias, :enfermedades, :tipo_sangre, CURDATE())
                     ON DUPLICATE KEY UPDATE condiciones_especiales = VALUES(condiciones_especiales),
                                             medicamentos        = VALUES(medicamentos),
                                             observaciones       = VALUES(observaciones),
                                             alergias            = VALUES(alergias),
                                             enfermedades        = VALUES(enfermedades),
                                             tipo_sangre         = VALUES(tipo_sangre),
                                             fecha_actualizacion = CURDATE()'
                )->execute(array_merge($vals, ['id_estudiante' => $idEstudiante]));
            }

            /* Se llama siempre, incluso con la lista vacía: si se omite, los
 contadores de documentos quedan sin recalcular. */
            $this->guardarFamiliares($idEstudiante, $familiares, true);

            // Sincronizar vínculos de persona_estudiante en estudiante_documento si cambió el representante
            $stmtRep = $this->pdo->prepare(
                'SELECT pe.id_persona_estudiante, pa.codigo AS parentesco_codigo
                   FROM persona_estudiante pe
                   LEFT JOIN parentesco pa ON pa.id_parentesco = pe.id_parentesco
                  WHERE pe.id_estudiante = :id
                    AND pe.es_representante_legal = 1
                    AND pe.estado_relacion = :estado
                  LIMIT 1'
            );
            $stmtRep->execute(['id' => $idEstudiante, 'estado' => self::ESTADO_VIGENTE]);
            $repVigente = $stmtRep->fetch(PDO::FETCH_ASSOC);

            if ($repVigente) {
                $idPeRep = (int) $repVigente['id_persona_estudiante'];
                $parentescoRep = strtolower(trim((string) ($repVigente['parentesco_codigo'] ?? '')));
                $esPadreMadre = in_array($parentescoRep, ['padre', 'madre'], true);

                $this->pdo->prepare(
                    'UPDATE estudiante_documento ed
                       JOIN tipo_documento t ON t.id_tipo_documento = ed.id_tipo_documento
                        SET ed.id_persona_estudiante = :pe,
                            ed.id_persona_estudiante_unico = :pe_unico
                      WHERE ed.id_estudiante = :estudiante
                        AND t.codigo IN (\'foto_representante\', \'cedula_representante\')'
                )->execute([
                    'pe'         => $idPeRep,
                    'pe_unico'   => $idPeRep,
                    'estudiante' => $idEstudiante,
                ]);

                if (!$esPadreMadre) {
                    $this->pdo->prepare(
                        'UPDATE estudiante_documento ed
                           JOIN tipo_documento t ON t.id_tipo_documento = ed.id_tipo_documento
                            SET ed.id_persona_estudiante = :pe,
                                ed.id_persona_estudiante_unico = :pe_unico,
                                ed.requerido = 1
                          WHERE ed.id_estudiante = :estudiante
                            AND t.codigo = \'permiso_lopnna\''
                    )->execute([
                        'pe'         => $idPeRep,
                        'pe_unico'   => $idPeRep,
                        'estudiante' => $idEstudiante,
                    ]);
                } else {
                    $this->pdo->prepare(
                        'UPDATE estudiante_documento ed
                           JOIN tipo_documento t ON t.id_tipo_documento = ed.id_tipo_documento
                            SET ed.id_persona_estudiante = NULL,
                                ed.id_persona_estudiante_unico = 0,
                                ed.requerido = 0
                          WHERE ed.id_estudiante = :estudiante
                            AND t.codigo = \'permiso_lopnna\''
                    )->execute([
                        'estudiante' => $idEstudiante,
                    ]);
                }
            }

            // Sincronizar recaudos de personas autorizadas para retiro en estudiante_documento
            foreach ($familiares as $fam) {
                $docFam = (string) preg_replace('/\D/', '', (string) ($fam['numero_documento'] ?? ''));
                if ($docFam === '') {
                    continue;
                }
                $esAutorizado = !empty($fam['autorizado_retiros']) && empty($fam['representante_principal']);
                $parentescoFam = strtolower(trim((string) ($fam['parentesco'] ?? '')));
                if (in_array($parentescoFam, ['padre', 'madre', 'papa', 'papá', 'mama', 'mamá'], true)) {
                    $esAutorizado = false;
                }
                $idPeAut = $relaciones[$docFam] ?? null;
                if ($idPeAut && $esAutorizado) {
                    $this->pdo->prepare(
                        'UPDATE estudiante_documento ed
                           JOIN tipo_documento t ON t.id_tipo_documento = ed.id_tipo_documento
                            SET ed.id_persona_estudiante = :pe,
                                ed.id_persona_estudiante_unico = :pe_unico,
                                ed.requerido = 1
                          WHERE ed.id_estudiante = :estudiante
                            AND t.codigo IN (\'autorizacion_retiro\', \'cedula_autorizada\')
                            AND (ed.id_persona_estudiante = :pe OR ed.id_persona_estudiante IS NULL)'
                    )->execute([
                        'pe'         => $idPeAut,
                        'pe_unico'   => $idPeAut,
                        'estudiante' => $idEstudiante,
                    ]);
                }
            }

            /**
 * Los recaudos van en la MISMA transacción y no en una llamada aparte del controlador:
 * el modal "Actualizar expediente" tiene un solo botón de guardar, y Guardar Cambios
 * tiene que guardar todo o nada.
 */
            $this->guardarRecaudosEnTransaccion(
                $idEstudiante,
                $data['recaudos'] ?? [],
                (int) ($data['id_usuario'] ?? 0)
            );

            if (!empty($data['cambio_ce'])) {
                $bitacora = new AuditLogModel($this->pdo);
                $accion = "ACTUALIZACIÓN CE: {$data['cambio_ce']['ce_anterior']} → {$data['cambio_ce']['ce_nueva']}. Motivo: {$data['cambio_ce']['motivo']}";
                $bitacora->registrar(
                    (int) ($data['id_usuario'] ?? 0),
                    $accion,
                    'Ratificaciones'
                );
            }

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('RatificationModel::actualizarEstudiante(): ' . $e->getMessage());
            throw $e;
        }
    }

    /** ¿Existe el estudiante? Lo usa el controlador para el 404.*/
    public function existeEstudiante(int $idEstudiante): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM estudiante WHERE id_estudiante = :id');
        $stmt->execute(['id' => $idEstudiante]);
        return (int) $stmt->fetchColumn() > 0;
    }


    /**
 * Nacionalidades para el <select> del paso 1 del estudiante. El modal de edición
 * reutiliza el paso 1 de la inscripción, así que necesita la misma lista.
 *
 * @return array{nacionalidades:array<int,array<string,mixed>>,id_por_defecto:int}
 */
    public function getNacionalidades(): array
    {
        return [
            'nacionalidades' => $this->pdo->query("SELECT id_nacionalidad, nombre FROM nacionalidad
                                                    WHERE estado = 'ACTIVO' ORDER BY nombre")
                                     ->fetchAll(\PDO::FETCH_ASSOC),
            'id_por_defecto'  => (int) \App\Models\NacionalidadModel::ID_POR_DEFECTO,
        ];
    }

    /**
 * Catálogos del sub-modal de familiares: parentescos, nivel de instrucción y
 * ocupación. Los tres vienen juntos porque el formulario los necesita siempre juntos:
 * el nivel y la ocupación solo se preguntan cuando el familiar es el representante o
 * el padre o la madre (ver FamiliaresModelTrait), pero leer las tres listas de una vez
 * evita dos peticiones más al abrir el modal.
 *
 * @return array{parentescos:array,nivelInstruccion:array,ocupaciones:array}
 */
    public function getCatalogosFamiliar(): array
    {
        return [
'parentescos'         => $this->pdo->query("SELECT nombre, COALESCE(codigo, '') AS codigo
                                           FROM parentesco
                                          WHERE estado = 'ACTIVO'
                                          ORDER BY nombre")
                                 ->fetchAll(\PDO::FETCH_ASSOC),
            'niveles_instruccion' => [],
            'ocupaciones'         => $this->pdo->query("SELECT nombre FROM ocupacion WHERE estado = 'ACTIVO' ORDER BY nombre")
                                ->fetchAll(\PDO::FETCH_COLUMN),
            /* Los estados se devuelven con la clave `id_estado`, que es como los entrega
 EnrollmentModel::getEstados() y como los lee el sub-modal. No se
 usa un alias `id` porque el marcado del <select> pide `id_estado`, y si
 no coincidiera saldrían todos los estados con value="0": elegir un
 estado no filtraría nada y la cascada de municipios parecería rota.*/
            'estados'          => $this->pdo->query("SELECT id_estado, nombre FROM estado WHERE estado = 'ACTIVO' ORDER BY nombre")
                                ->fetchAll(\PDO::FETCH_ASSOC),
        ];
    }

    /**
 * Familiares de un estudiante, con el contacto y el domicilio, listos para que el
 * formulario venga con lo guardado y no haya que re-declararlo. La forma de cada
 * elemento es la INVERSA de decodificarFamiliares(), que es la que espera
 * guardarFamiliares().
 *
 * @param bool $sinDatosSensibles Cuando es true, las columnas de documento de
 * @return array<int, array<string, mixed>>
 */
    public function getFamiliares(int $idEstudiante, bool $sinDatosSensibles = false): array
    {
        /**
 * Columnas que se sustituyen por NULL cuando el destino es el docente.
 */
        $columnasDocumento = $sinDatosSensibles
            ? 'NULL AS tipo_documento, NULL AS numero_documento'
            : 'p.tipo_documento, p.numero_documento';

        $columnasNacionalidad = $sinDatosSensibles
            ? 'NULL AS id_nacionalidad, NULL AS nacionalidad'
            : 'p.id_nacionalidad, COALESCE(nac.nombre, "") AS nacionalidad';

        $stmt = $this->pdo->prepare(
            'SELECT pe.id_persona,
                    ' . $columnasDocumento . ',
                    p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido,
                    p.sexo, p.fecha_nacimiento,
                    p.id_estado_nacimiento, p.id_parroquia_nacimiento,
                    ' . $columnasNacionalidad . ',
                    COALESCE(pa.nombre, "") AS parentesco,
                    COALESCE(pa.nombre, "") AS parentesco_texto,
                    pe.id_parentesco,
                    COALESCE(pa.codigo, "") AS parentesco_codigo,
                    "" AS nivel_instruccion,
                    COALESCE(oc.nombre, (
                        SELECT oc_rec.nombre
                          FROM persona_estudiante pe_rec
                          JOIN ocupacion oc_rec ON oc_rec.id_ocupacion = pe_rec.id_ocupacion
                         WHERE pe_rec.id_persona = pe.id_persona
                           AND pe_rec.id_ocupacion IS NOT NULL
                         ORDER BY pe_rec.id_persona_estudiante DESC
                         LIMIT 1
                    ), "") AS ocupacion,
                    pe.es_representante_legal, pe.es_autorizado_retiro,
                    pe.vive_con_estudiante, pe.estado_relacion,
                    p.es_fallecido,
                    c.codigo_telefono_principal, c.numero_telefono_principal,
                    c.codigo_telefono_alternativo, c.numero_telefono_alternativo,
                    c.correo_electronico,
                    d.id_parroquia, d.sector_urbanizacion, d.calle_avenida,
                    d.nro_casa_apto, d.punto_referencia,
                    pq.id_municipio, pq.nombre AS parroquia_nombre,
                    mu.id_estado, mu.nombre AS municipio_nombre,
es.nombre AS estado_nombre
                FROM persona_estudiante pe
                  INNER JOIN persona p ON p.id_persona = pe.id_persona
                    LEFT JOIN parentesco pa         ON pa.id_parentesco         = pe.id_parentesco
                    LEFT JOIN ocupacion oc         ON oc.id_ocupacion         = pe.id_ocupacion
                    LEFT JOIN contacto c           ON c.id_persona            = pe.id_persona
                    LEFT JOIN direccion d          ON d.id_persona            = pe.id_persona
                    LEFT JOIN parroquia pq         ON pq.id_parroquia         = d.id_parroquia
                    LEFT JOIN municipio mu         ON mu.id_municipio         = pq.id_municipio
                    LEFT JOIN estado es            ON es.id_estado            = mu.id_estado
                     LEFT JOIN nacionalidad nac      ON nac.id_nacionalidad  = p.id_nacionalidad
                 WHERE pe.id_estudiante = :id
                   /**
* Solo las relaciones VIGENTES. Lo desvinculado se marca
  * con estado_relacion = INACTIVO y se conserva la fila: es un menor, y su
  * parentesco con el alumno es un hecho que no se borra porque la secretaria lo quitó de la lista.
  */
                   AND pe.estado_relacion = :estado
                 ORDER BY pe.es_representante_legal DESC, p.primer_nombre, p.primer_apellido'
          );
          $stmt->execute(['id' => $idEstudiante, 'estado' => self::ESTADO_VIGENTE]);

          $filas = $stmt->fetchAll(\PDO::FETCH_ASSOC);

/* Las personas que tienen ficha propia en el plantel. Su contacto y dirección están en las mismas filas de contacto y dirección que usa su expediente laboral, por lo que escribirlos desde aquí cambiaría el teléfono de un empleado sin que nadie lo pidiera. Se marca aquí para que el sub‑modal desactive los campos con su aviso.*/
          $ids = array_map(static fn (array $f): int => (int) $f['id_persona'], $filas);
          $conOtroRol = $this->personasConPropiaFicha($ids);

          $salida = [];
          foreach ($filas as $fila) {
            $nombre = trim(implode(' ', array_filter([
                $fila['primer_nombre'], $fila['segundo_nombre'],
                $fila['primer_apellido'], $fila['segundo_apellido'],
            ])));

            $salida[] = [
                'id_persona'      => (int) $fila['id_persona'],
                'nombre_referencia' => $nombre,
                'primer_nombre'   => (string) $fila['primer_nombre'],
                'segundo_nombre'  => (string) ($fila['segundo_nombre'] ?? ''),
                'primer_apellido' => (string) $fila['primer_apellido'],
                'segundo_apellido'=> (string) ($fila['segundo_apellido'] ?? ''),
                'parentesco'      => (string) $fila['parentesco'],
                'parentesco_texto'=> (string) $fila['parentesco'],
                'id_parentesco'   => (int) ($fila['id_parentesco'] ?? 0),
                'parentesco_codigo' => (string) ($fila['parentesco_codigo'] ?? ''),
                'nivel_instruccion' => (string) $fila['nivel_instruccion'],
                'ocupacion'       => (string) $fila['ocupacion'],
                'profesion'       => (string) $fila['ocupacion'],
                'tipo_documento'  => (string) $fila['tipo_documento'],
                'numero_documento'=> (string) $fila['numero_documento'],
                'sexo'            => ((string) $fila['sexo']) === 'F' ? 'F' : 'M',
                'fecha_nacimiento'=> (string) $fila['fecha_nacimiento'],
                /* Entidad federal de nacimiento para precargar en el submodal al editar */
                'id_estado_nacimiento' => (int) ($fila['id_estado_nacimiento'] ?? 0),
                /* La nacionalidad viaja como nombre Y como id: el nombre para pintar la
                   tabla y el PDF, el id para que el sub-modal vuelva a marcar el mismo
                   <select> cuando se reabre una fila ya guardada. */
                'nacionalidad'    => (string) ($fila['nacionalidad'] ?? ''),
                'id_nacionalidad' => (int) ($fila['id_nacionalidad'] ?? 0),

                'representante_principal' => (int) $fila['es_representante_legal'],
                'autorizado_retiros'      => (int) $fila['es_autorizado_retiro'],
                'vive_con_estudiante'     => (int) $fila['vive_con_estudiante'],
                'es_fallecido'            => (int) ($fila['es_fallecido'] ?? 0),
                'solo_autorizado'         => $fila['parentesco'] === '' ? 1 : 0,
                  'estado_relacion'         => (string) $fila['estado_relacion'],
                  /* 1 = su contacto y su dirección son los mismos datos de su
 expediente laboral (o de su propia ficha de estudiante), y desde
 aquí no se escriben. El sub-modal lo consulta con
 contactoEditable() para desactivar los campos y mostrar el aviso.*/
                  'contacto_bloqueado'       => 0,

                'contacto' => [
                    'codigo'    => (string) ($fila['codigo_telefono_principal'] ?? ''),
                    'numero'    => (string) ($fila['numero_telefono_principal'] ?? ''),
                    'codigo_alt'=> (string) ($fila['codigo_telefono_alternativo'] ?? ''),
                    'numero_alt'=> (string) ($fila['numero_telefono_alternativo'] ?? ''),
                    'correo'    => (string) ($fila['correo_electronico'] ?? ''),
                ],
                'direccion' => [
                    'id_parroquia' => (int) ($fila['id_parroquia'] ?? 0),
                    /* Estado y municipio no se guardan: `direccion` solo tiene la
 parroquia. Se deducen de ella para que el sub-modal pueda
 dejar los tres <select> de la cascada elegidos sin tener
 que adivinar el estado a partir del nombre.*/
                    'estado'       => (int) ($fila['id_estado'] ?? 0),
                    'municipio'    => (int) ($fila['id_municipio'] ?? 0),
                    'sector'       => (string) ($fila['sector_urbanizacion'] ?? ''),
                    'calle'        => (string) ($fila['calle_avenida'] ?? ''),
                    'casa'         => (string) ($fila['nro_casa_apto'] ?? ''),
                    'referencia'   => (string) ($fila['punto_referencia'] ?? ''),
                    'parroquia_nombre' => (string) ($fila['parroquia_nombre'] ?? ''),
                    'municipio_nombre' => (string) ($fila['municipio_nombre'] ?? ''),
                    'estado_nombre' => (string) ($fila['estado_nombre'] ?? ''),
                ],
            ];
        }

        return $salida;
    }

    /* Expediente: separar el núcleo familiar de los autorizados a retirar.
 * Son dos preguntas distintas que la ficha mezclaba en una sola lista: quién es la FAMILIA
 * del menor (a quién se le informa) y QUIÉN PUEDE RETIRARLO (una autorización de seguridad,
 * que se concede también al transporte, al vecino de confianza o al portero). Juntas, un
 * nombre en la lista del niño parecía un familiar cuando podía no serlo.*/

    /**
     * Parentescos que describen familiares directos del estudiante (padre / madre).
     */
    private const PARENTESCOS_DIRECTOS = ['padre', 'madre', 'papá', 'papa', 'mamá', 'mama'];

    /**
     * Los vinculados al alumno, ya separados en los dos grupos de la ficha:
     * - Grupo Familiar: ÚNICAMENTE familiares directos (padre, madre) o el representante legal.
     * - Personas autorizadas para retirar: Personas asociadas que NO sean familiares directos
     *   (ni el representante legal), pero que cuentan con autorización para el retiro del menor.
     *
     * @param bool $soloDocente true cuando quien lee es un docente: se quitan datos sensibles
     * @return array{nucleo: array<int, array<string,mixed>>, autorizados: array<int, array<string,mixed>>}
     */
    public function getFamiliaresParaExpediente(int $idEstudiante, bool $soloDocente = false): array
    {
        $nucleo = [];
        $autorizados = [];

        /* Para el docente la consulta ya viene sin documento ni nacionalidad:
           el dato no sale de la base, no llega a PHP y se quita después. El
           unset de más abajo se queda como segunda barrera, para que ningún
           cambio futuro en la consulta pueda filtrar un documento por accidente. */
        foreach ($this->getFamiliares($idEstudiante, $soloDocente) as $familiar) {
            $esRepresentante = (int) ($familiar['representante_principal'] ?? 0) === 1;
            $parentesco = trim((string) ($familiar['parentesco'] ?? ''));
            $parentescoNorm = mb_strtolower($parentesco, 'UTF-8');
            $idParentesco = (int) ($familiar['id_parentesco'] ?? 0);
            $parentescoCodigo = strtolower(trim((string) ($familiar['parentesco_codigo'] ?? '')));

            $esFamiliarDirecto = $idParentesco === 2 || $idParentesco === 3
                || in_array($parentescoCodigo, ['padre', 'madre'], true)
                || in_array($parentescoNorm, self::PARENTESCOS_DIRECTOS, true);

            /* DATOS DEL GRUPO FAMILIAR:
               Deben ser los datos de familiares directos (padre, madre) o representante legal únicamente. */
            if ($esRepresentante || $esFamiliarDirecto) {
                $nucleo[] = $familiar;
            }

            /* PERSONAS AUTORIZADAS PARA RETIRAR AL ESTUDIANTE:
               Deben ir personas asociadas al estudiante que NO sean familiares directos es decir padre, madre
               o representante legal, y que tengan permiso de retiro. */
            if (!$esRepresentante && !$esFamiliarDirecto && (int) ($familiar['autorizado_retiros'] ?? 0) === 1) {
                $autorizados[] = $familiar;
            }
        }

        /**
 * El docente verifica de quién se trata mirando a la persona y con su teléfono. El
 * documento de un tercero y su nacionalidad no le hacen falta, así que no se piden
 * arriba (getFamiliares) y además se quitan aquí.
 */
        if ($soloDocente) {
            foreach ([&$nucleo, &$autorizados] as &$grupo) {
                foreach ($grupo as &$persona) {
                    unset(
                        $persona['tipo_documento'],
                        $persona['numero_documento'],
                        $persona['nacionalidad']
                    );
                }
                unset($persona);
            }
            unset($grupo);
        }

        return ['nucleo' => $nucleo, 'autorizados' => $autorizados];
    }

    /**
 * Guarda los familiares de un estudiante. Es un envoltorio sobre el trait, y existe
 * para que el controlador no tenga que alcanzar un método protected ni conocer de qué
 * modelo viene.
 */
    public function guardarFamiliaresDeEstudiante(int $idEstudiante, array $familiares): void
    {
        $this->guardarFamiliares($idEstudiante, $familiares, true);
    }

    /**
 * ¿Cuáles de estas personas tienen ficha PROPIA en el plantel? `contacto` y
 * `direccion` tienen UNIQUE(id_persona): hay una sola fila por persona, compartida por
 * todos sus roles.
 *
 * @param int[] $idsPersona
 * @return array<int, true> Clave = id_persona con ficha propia.
 */
    public function personasConPropiaFicha(array $idsPersona): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $idsPersona),
            static fn (int $id): bool => $id > 0
        )));
        if ($ids === []) {
            return [];
        }

        /* Marcadores POSICIONALES y replicados por mitad. Con marcadores con nombre
 reutilizados en las dos ramas del UNION, PDO no los expande y la consulta
 muere con "Invalid parameter number".*/
        $marcas = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare(
            'SELECT DISTINCT id_persona FROM empleado  WHERE id_persona IN (' . $marcas . ')
             UNION
             SELECT DISTINCT id_persona FROM estudiante WHERE id_persona IN (' . $marcas . ')'
        );
        /* Los mismos ids, una vez por cada mitad del UNION.*/
        $stmt->execute(array_merge($ids, $ids));

        $conFicha = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $idPersona) {
            $conFicha[(int) $idPersona] = true;
        }

        return $conFicha;
    }

    /**
 * Contacto y domicilio del representante legal, ya aplanados para que el formulario los
 * pinte directo. Devuelve [] si el estudiante no tiene representante marcado.
 *
 * @return array<string, mixed>
 */
    public function getRepresentanteLegal(int $idEstudiante): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.codigo_telefono_principal, c.numero_telefono_principal,
                    c.codigo_telefono_alternativo, c.numero_telefono_alternativo,
                    c.correo_electronico,
                    d.id_parroquia, d.sector_urbanizacion, d.calle_avenida,
                    d.nro_casa_apto, d.punto_referencia
               FROM persona_estudiante pe
               INNER JOIN persona p   ON p.id_persona = pe.id_persona
                 LEFT JOIN contacto c ON c.id_persona = pe.id_persona
                 LEFT JOIN direccion d ON d.id_persona = pe.id_persona
              WHERE pe.id_estudiante = :id AND pe.es_representante_legal = 1
                AND p.es_fallecido = 0
              LIMIT 1'
        );
        $stmt->execute(['id' => $idEstudiante]);
        $fila = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$fila) {
            return [];
        }

        return [
            'contacto' => [
                'codigo'    => (string) ($fila['codigo_telefono_principal'] ?? ''),
                'numero'    => (string) ($fila['numero_telefono_principal'] ?? ''),
                'codigo_alt'=> (string) ($fila['codigo_telefono_alternativo'] ?? ''),
                'numero_alt'=> (string) ($fila['numero_telefono_alternativo'] ?? ''),
                'correo'    => (string) ($fila['correo_electronico'] ?? ''),
            ],
            'direccion' => [
                'id_parroquia' => (int) ($fila['id_parroquia'] ?? 0),
                'sector'       => (string) ($fila['sector_urbanizacion'] ?? ''),
                'calle'        => (string) ($fila['calle_avenida'] ?? ''),
                'casa'         => (string) ($fila['nro_casa_apto'] ?? ''),
                'referencia'   => (string) ($fila['punto_referencia'] ?? ''),
            ],
        ];
    }

    /**
 * Busca una persona por cédula para el sub-modal de familiares. El estudiante se pasa
 * como contexto porque el nivel de instrucción y la ocupación están en la relación
 * persona-estudiante, no en la persona: sin él no se sabría de qué menor tomar esos
 * dos datos.
 */
    public function buscarPersonaPorDocumento(string $documento, ?int $idEstudiante = null): ?array
    {
        return (new PersonaModel($this->pdo))->buscarPersonaPorDocumento($documento, $idEstudiante);
    }

    /* ¿El documento ya está en otra persona? $ignorarId es la persona que se está EDITANDO, para que
 no se detecte a sí misma: sin eso, guardar sin cambiar el documento fallaría siempre.
 Compara el valor TAL COMO LLEGÓ, sin quitar letras, porque quien llama ya lo normalizó
 según el tipo; quitar aquí 'V' de una cédula escolar haría fallar el UNIQUE.*/
    public function existeDocumento(string $documento, ?int $ignorarId = null): bool
    {
        return (new PersonaModel($this->pdo))->existeDocumento($documento, $ignorarId);
    }

    /**
 * Detalle de una ratificación ya registrada, para el modal de "ver". Trae las DOS
 * matrículas del alumno: la del año anterior (de dónde viene) y la del año en curso
 * (a dónde llegó).
 *
 * @return array<string, mixed>|null null si el estudiante no existe.
 */
    public function getDetalle(int $idEstudiante): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT est.id_estudiante AS id,
                    CONCAT_WS(' ', p.primer_nombre, p.primer_apellido) AS nombre,
                    CONCAT(p.tipo_documento, '-', p.numero_documento) AS cedula,
                    p.foto AS avatar_img,
                    UPPER(CONCAT(LEFT(p.primer_nombre, 1), COALESCE(LEFT(p.primer_apellido, 1), ''))) AS iniciales,
                    -- La fila del año en curso que significa cursando: la inscripción actual
                    ad.nombre_periodo AS anio_destino,
                    gd.nombre AS grado_destino,
                    sd.nombre AS seccion_destino,
                    tr_d.nombre AS turno_destino,
                    md.id_matricula AS id_matricula_destino,
                    md.tipo AS tipo_destino,
                    md.fecha_matricula AS fecha_ratificacion
               FROM estudiante est
               INNER JOIN persona p ON p.id_persona = est.id_persona
               LEFT JOIN matricula md ON md.id_estudiante = est.id_estudiante
                    AND md.id_anio_escolar = :anio
                    AND md.tipo IN ('INSCRIPCION', 'RATIFICACION', 'REINGRESO')
               LEFT JOIN anio_escolar ad        ON ad.id_anio_escolar  = md.id_anio_escolar
               LEFT JOIN estructura_academica ed ON ed.id_estructura_academica = md.id_estructura_academica
               LEFT JOIN grado gd    ON gd.id_grado   = ed.id_grado
               LEFT JOIN seccion sd  ON sd.id_seccion = ed.id_seccion
               LEFT JOIN turno tr_d  ON tr_d.id_turno = ed.id_turno
               WHERE est.id_estudiante = :id
              LIMIT 1"
        );
        $stmt->execute([
            'anio' => $this->anioEnCurso(),
            'id'   => $idEstudiante,
        ]);

        $fila = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$fila) {
            return null;
        }

        $nombre   = trim($fila['nombre'] ?? '') ?: 'Estudiante';
        $esRatif = $fila['id_matricula_destino'] !== null
            && $fila['tipo_destino'] === Matriculas::RATIFICACION;

        return [
            'id'                  => (int) $fila['id'],
            'nombre'              => $nombre,
            'cedula'              => $fila['cedula'] ?? '—',
            'avatar_img'          => $fila['avatar_img'] ?? '',
            'avatar_color'        => 'primary',
            'iniciales'           => $fila['iniciales'] ?: mb_strtoupper(mb_substr($nombre, 0, 1)),

            'anio_destino'        => $fila['anio_destino'] ?? '—',
            'grado_destino'       => $fila['grado_destino'] ?? '—',
            'seccion_destino'     => $fila['seccion_destino'] ?? '—',
            'turno_destino'       => $fila['turno_destino'] ?? '—',
            'tipo_destino'        => $fila['tipo_destino'] ?? null,
            'id_matricula_destino' => $fila['id_matricula_destino'] !== null
                ? (int) $fila['id_matricula_destino'] : null,
            'fecha_ratificacion'  => $fila['fecha_ratificacion']
                ? date('d/m/Y', strtotime((string) $fila['fecha_ratificacion'])) : '—',

            'es_ratificacion'     => $esRatif,
            'estado'              => $esRatif ? 'ratificado' : 'inscrito',
        ];
    }

    /** Proyecta al siguiente grado e inserta la ratificación.*/
    /**
 * elige la secretaria en el modal. Lo que no venga se deduce de la matrícula de origen
 * (ver estructuraDestino()).
 *
 * @param array $destino ['id_grado'=>int,'id_seccion'=>int,'id_turno'=>int] que
 */
    public function ratificar(int $estudianteId, array $destino = [], int $idUsuario = 0): array
    {
        /* Sin año en curso no hay destino posible. Se dice antes de mirar al alumno porque
 sin período abierto el mensaje que sale de abajo ("esa combinación no está
 registrada en la Estructura Académica del año escolar en curso")vuelve a culpar de
 la combinación elegida, y el problema no es esa elección.*/
        if ($this->anioEnCurso() <= 0) {
            return ['ok' => false, 'mensaje' => 'No hay un año escolar activo: no se puede ratificar a nadie. '
                . 'La directora o el administrador deben abrir un período en '
                . 'Configuración → Años escolares.'];
        }

    /* El año al que se ratifica es FIJO y el formulario lo manda en un campo oculto. Esa es la
 razón de que el selector de año no exista en la pantalla: cambiarlo por pantalla haría
 que el formulario pudiera decir un año y la estructura de otro. El servidor lo contrasta
 con el año de destino y responde 409 si no coinciden.*/
        $anioDestino = $this->getAnioDestino();
        $anioPostado = (int) ($destino['id_anio_escolar'] ?? 0);
        if ($anioPostado > 0 && $anioPostado !== $anioDestino['id']) {
            return ['ok' => false, 'mensaje' => 'El año escolar cambió mientras este formulario estaba abierto. '
                . 'Ahora el período de destino es ' . $anioDestino['nombre'] . ': '
                . 'recargue la pantalla y vuelva a elegir el grado y la sección.'];
        }

        /* El criterio va primero: un alumno sin matrícula previa no entra por aquí sino por inscripción,
 y preguntar por estructura o cupo antes daría un mensaje equivocado.*/
        if ($this->flujoPara($estudianteId) === self::FLUJO_INSCRIPCION) {
            return ['ok' => false, 'mensaje' => 'Este estudiante no tiene una inscripción previa en el plantel, '
                . 'así que no se ratifica: corresponde inscribirlo. La ratificación es solo para alumnos '
                . 'que ya estaban inscriptos de un año anterior.'];
        }

        $matriculaVigente = $this->matriculaConEstructura($estudianteId);
        if (!$matriculaVigente) {
            // Dos casos: ya cursa el año en curso (nada que ratificar aún) o nunca tuvo matrícula anterior.
            $enCurso = (int) $this->pdo->query(
                'SELECT COUNT(*) FROM matricula
                  WHERE id_estudiante = ' . (int) $estudianteId
                    . ' AND id_anio_escolar = ' . $this->anioEnCurso()
            )->fetchColumn();

            return ['ok' => false, 'mensaje' => $enCurso > 0
                ? 'El estudiante ya tiene matrícula en el año escolar en curso: está cursando. '
                  . 'La ratificación corresponde al cierre del año, cuando se abre el siguiente periodo.'
                : 'El estudiante no tiene ninguna matrícula de un año anterior, así que no hay nada que ratificar.'];
        }

        /* Primero lo que pasó con el estudiante y después la configuración: si no, a un egresado le dirían
 "no existe estructura académica" y se escondería que ya terminó su ciclo. Son casos válidos, no errores.*/
        $cerrado = $this->estadoCerrado($matriculaVigente['id_matricula']);
        if ($cerrado !== null) {
            /* Las claves son los TIPOS en minúsculas ('egreso', 'retiro') porque eso es lo que
 devuelve estadoCerrado(). Con las claves en pasado ('egresado', 'retirado') la
 búsqueda no encontraba nunca y todo caso cerrado caía en el mensaje genérico
 "El año de este estudiante ya está cerrado", que no dice por qué.*/
            $mensajes = [
                'egreso'  => 'El estudiante ya egresó de la institución: su ciclo está cerrado y no corresponde ratificarlo.',
                'retiro'  => 'El estudiante se retiró antes de culminar el año: su ciclo está cerrado y no corresponde ratificarlo.',
            ];
            return ['ok' => false, 'mensaje' => $mensajes[$cerrado] ?? 'El año de este estudiante ya está cerrado.'];
        }

        /* Un retiro EN CURSO también bloquea: es una decisión pendiente, no un hecho consumado.
 Sin esta guarda, con la página abierta o por URL el alumno quedaba con retiro en marcha y matrícula del año siguiente.*/
        if ($this->tieneRetiroPendiente((int) $matriculaVigente['id_matricula'])) {
            return ['ok' => false, 'mensaje' => 'Este estudiante tiene una solicitud de retiro en curso. '
                . 'Mientras no se resuelva no se puede ratificar: si se aprueba, se va; si se rechaza, sigue y '
                . 'entonces corresponde ratificarlo.'];
        }

        /* Validar que la matrícula que se está ratificando tenga literal cargado por la docente */
        $stmtLiteral = $this->pdo->prepare(
            'SELECT literal, estado_resultado FROM resultado_academico
              WHERE id_estudiante = :id_estudiante AND id_anio_escolar = :id_anio LIMIT 1'
        );
        $stmtLiteral->execute([
            'id_estudiante' => (int) $matriculaVigente['id_estudiante'],
            'id_anio'       => (int) $matriculaVigente['id_anio_escolar'],
        ]);
        $resLiteral = $stmtLiteral->fetch(\PDO::FETCH_ASSOC);

        if (!$resLiteral || empty($resLiteral['literal'])) {
            return [
                'ok' => false,
                'mensaje' => 'El estudiante no tiene literal cargado por la docente. No se puede ratificar sin resultado.',
            ];
        }

        // Estudiantes de 6.º grado promovidos (literal A–D) no se ratifican: su proceso es el egreso
        $ordinalGradoOrigen = (int) Instituciones::ordinalGrado($matriculaVigente['grado_nombre'] ?? null);
        if ($ordinalGradoOrigen === Instituciones::GRADO_MAYOR && in_array($resLiteral['literal'], ['A', 'B', 'C', 'D'], true)) {
            return [
                'ok' => false,
                'mensaje' => 'El estudiante culminó 6to grado con resultado promovido: su proceso correspondiente es el egreso, no la ratificación.',
            ];
        }

        $estructura = $this->estructuraDestino($matriculaVigente, $destino);
        if (!$estructura) {
            /* El mensaje cambia según si la secretaria eligió o si se dedujo: si
 eligió, el problema es su selección; si se dedujo, es que en la
 Estructura Académica no existe la sección que le tocaría.*/
            $eligio = (int) ($destino['id_grado'] ?? 0) > 0;
            return ['ok' => false, 'mensaje' => $eligio
                ? 'Esa combinación de grado, sección y turno no está registrada en la Estructura Académica del año escolar en curso. '
                  . 'Revise la selección o créela en Configuración → Estructura Académica.'
                : 'No existe una sección para el siguiente grado en el año escolar en curso. '
                  . 'Hay que crearla en la Estructura Académica antes de ratificar a este alumno.'];
        }

        /* Progresión de grado, DESPUÉS de resolver la estructura: con la estructura ya se
 sabe el grado de destino, y el motivo de un salto se pide solo cuando de verdad
 se está saltando. Va antes de "ya está ratificado" porque es una condición de la
 matrícula que se va a crear, no del estado actual.*/
        $motivoAnticipo = trim((string) ($destino['motivo'] ?? ''));
        $errorGrado = $this->validarProgresionGrado(
            (int) Instituciones::ordinalGrado($matriculaVigente['grado_nombre'] ?? null),
            $matriculaVigente['grado_nombre'] ?? null,
            Instituciones::ordinalGrado($nombreGradoDestino = $this->nombreGrado((int) $estructura['id_grado'] ?? 0)),
            $nombreGradoDestino,
            $motivoAnticipo
        );
        if ($errorGrado !== null) {
            return ['ok' => false, 'mensaje' => $errorGrado];
        }

        if ($this->yaTieneMatriculaEn($estudianteId, (int) $estructura['anio'])) {
            return ['ok' => false, 'mensaje' => 'El estudiante ya tiene matrícula para ese año escolar, así que no hay nada que ratificar.'];
        }

        // "¿Ya fue ratificado?" antes que el cupo; al revés se decía "sección llena" a quien ya estaba ratificado.
        if ($this->yaRatificado($matriculaVigente['id_matricula'])) {
            return ['ok' => false, 'mensaje' => 'El estudiante ya tiene matrícula en el año escolar en curso: ya está ratificado.'];
        }

        /* Lo que decide si entra va dentro de la transacción con el cupo bloqueado
 (reservarCupo hace SELECT ... FOR UPDATE), porque otra secretaria podría
 ratificar al último que cabía entre el aviso y el INSERT.

 Y el orden dentro de esa transacción importa más de lo que parece.*/
        $abrioTransaccion = false;
        try {
            if (!$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
                $abrioTransaccion = true;
            }

    /**
 * reservarCupo() abre con un SELECT ... FOR UPDATE sobre estructura_academica, y ese
 * bloqueo es lo que serializa a dos secretarias ratificando la misma sección.
 */
            $this->reservarCupo((int) $estructura['id']);

            /* La revalidación va DESPUÉS del bloqueo, no antes. Sigue haciendo falta: el
 "¿ya fue ratificado?" de arriba se revalida FUERA de la transacción, y
 entre ese momento y el lock otra secretaria pudo ratificar a este mismo
 alumno. Ahora sí se ve, porque la vista de lectura se creó después de
 esperar el bloqueo.*/
            if ($this->yaRatificado($matriculaVigente['id_matricula'])) {
                if ($abrioTransaccion && $this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                return ['ok' => false, 'mensaje' => 'El estudiante ya tiene matrícula en el año escolar en curso: ya está ratificado.'];
            }

            /* La ratificación es una matrícula nueva de tipo RATIFICACION en la estructura destino.
 No se toca la matrícula anterior (el historial queda entero) ni se crea otra inscripción,
 que es única para siempre (uk_inscripcion_estudiante). El motivo del salto de grado
 viaja con la fila, para que el historial muestre el porqué y no solo el salto.*/
            $this->pdo->prepare(
                'INSERT INTO matricula (id_estudiante, fecha_matricula, id_anio_escolar, id_estructura_academica, tipo, motivo_anticipo)
                 VALUES (:estudiante, CURDATE(), :anio, :estructura, :tipo, :motivo)'
            )->execute([
                'estudiante' => $estudianteId,
                'anio'       => (int) $estructura['anio'],
                'estructura' => (int) $estructura['id'],
                'tipo'       => Matriculas::RATIFICACION,
                'motivo'     => $motivoAnticipo !== '' ? mb_substr($motivoAnticipo, 0, 200) : null,
            ]);

            /* Dentro de la transacción, como en el resto de procesos: si el commit falla,
 el rollback se lleva también la bitácora y no queda constancia de una
 ratificación que no ocurrió. El mensaje incluye el origen y el destino, así
 que un cambio de sección queda trazado aunque no tenga registro propio.*/
            if ($idUsuario > 0) {
                (new AuditLogModel($this->pdo))->registrar(
                    $idUsuario,
                    'RATIFICACIÓN ' . $this->descripcionEstudiante($estudianteId)
                        . ' · ' . $this->descripcionEstructura((int) $matriculaVigente['id_estructura_academica'] ?? 0)
                        . ' → ' . $this->descripcionEstructura((int) $estructura['id']),
                    'Ratificaciones'
                );
            }

            if ($abrioTransaccion) {
                $this->pdo->commit();
            }
            return ['ok' => true, 'mensaje' => 'Ratificación registrada correctamente.'];
        } catch (RuntimeException $e) {
            // reservarCupo() lanza con un texto ya apto para la secretaria: es un aviso, no una falla.
            if ($abrioTransaccion && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            /**
 * El caso que eso invierte es uno solo: que otra secretaria ratifique a este alumno Y
 * la sección se llene en la misma carrera. Aquí, ya fuera de transacción, la lectura
 * es fresca, así que se recupera la prioridad sin tocar el orden del bloqueo.
 */
            if ($this->yaRatificado($matriculaVigente['id_matricula'])) {
                return ['ok' => false, 'mensaje' => 'El estudiante ya tiene matrícula en el año escolar en curso: ya está ratificado.'];
            }
            return ['ok' => false, 'mensaje' => $e->getMessage()];
        } catch (Throwable $e) {
            if ($abrioTransaccion && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('RatificationModel::ratificar(): ' . $e->getMessage());
            return ['ok' => false, 'mensaje' => 'No se pudo registrar la ratificación.'];
        }
    }

    /** Nombre y cédula de un estudiante, para que la bitácora se lea sin consultar.*/
    private function descripcionEstudiante(int $estudianteId): string
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.primer_nombre, p.primer_apellido, p.numero_documento
               FROM estudiante e
               INNER JOIN persona p ON p.id_persona = e.id_persona
              WHERE e.id_estudiante = :id
              LIMIT 1'
        );
        $stmt->execute(['id' => $estudianteId]);
        $fila = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$fila) {
            return "estudiante {$estudianteId}";
        }

        return trim($fila['primer_nombre'] . ' ' . $fila['primer_apellido'])
            . ' (' . $fila['numero_documento'] . ')';
    }

    /** "1er Grado · A · Tarde": con eso se ve de dónde a dónde va la ratificación.*/
    private function descripcionEstructura(int $idEstructura): string
    {
        if ($idEstructura <= 0) {
            return 'sin destino';
        }

        $stmt = $this->pdo->prepare(
            'SELECT CONCAT_WS(" · ", g.nombre, s.nombre, t.nombre)
               FROM estructura_academica ea
               INNER JOIN grado g   ON g.id_grado   = ea.id_grado
               INNER JOIN seccion s ON s.id_seccion = ea.id_seccion
               INNER JOIN turno t  ON t.id_turno   = ea.id_turno
              WHERE ea.id_estructura_academica = :id
              LIMIT 1'
        );
        $stmt->execute(['id' => $idEstructura]);
        $nombre = $stmt->fetchColumn();

        return $nombre !== false ? (string) $nombre : "estructura {$idEstructura}";
    }

    /**
 * Regla de progresión de grado al ratificar. Continuar al siguiente es lo normal;
 * repetir el mismo grado es legítimo (quien no alcanzó el nivel).
 *
 * @return string|null Mensaje de error, o null si la progresión es válida.
 */
    private function validarProgresionGrado(
        int $ordinalOrigen,
        ?string $nombreOrigen,
        ?int $ordinalDestino,
        ?string $nombreDestino,
        string $motivo
    ): ?string {
        if ($ordinalDestino === null) {
            return null;
        }

        $origen  = $nombreOrigen ?? $ordinalOrigen . '°';
        $destino = $nombreDestino ?? $ordinalDestino . '°';
        $diferencia = $ordinalDestino - $ordinalOrigen;

        if ($diferencia < 0) {
            return 'No se puede ratificar a un grado inferior: el estudiante está en '
                . $origen . ' y se intentó pasar a ' . $destino . '. '
                . 'Bajar de nivel no forma parte de la ratificación.';
        }

        if ($diferencia > 1) {
            if (trim($motivo) === '') {
                return 'Se está adelantando más de un grado (de ' . $origen . ' a ' . $destino . '). '
                    . 'Indique el motivo del salto: queda registrado en la matrícula del estudiante.';
            }
            if (mb_strlen(trim($motivo), 'UTF-8') < 10) {
                return 'El motivo del salto de grado debe tener al menos 10 caracteres.';
            }
        }

        return null;
    }

    /** Nombre del grado de un id, para deducir su número (los ids no siguen el orden).*/
    private function nombreGrado(int $idGrado): ?string
    {
        $stmt = $this->pdo->prepare('SELECT nombre FROM grado WHERE id_grado = :id LIMIT 1');
        $stmt->execute(['id' => $idGrado]);
        $nombre = $stmt->fetchColumn();

        return $nombre === false ? null : (string) $nombre;
    }

    /** Matrícula del último año del alumno (anterior al año en curso) con la estructura que cursó; no depende de la inscripción.*/
    private function matriculaConEstructura(int $estudianteId): ?array
    {
        /* El filtro de año anterior impide ratificar a quien ya cursa el año activo.
 Se compara por `anio_inicio` y no por id, porque los ids son de inserción y no siguen el orden.*/
        $stmt = $this->pdo->prepare(
            "SELECT m.id_matricula, m.id_estudiante, m.tipo, m.id_anio_escolar, ea.id_estructura_academica,
                    ea.id_grado, ea.id_seccion, ea.id_turno, g.nombre AS grado_nombre
               FROM matricula m
               INNER JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
               INNER JOIN anio_escolar ay          ON ay.id_anio_escolar = m.id_anio_escolar
               LEFT JOIN grado g            ON g.id_grado            = ea.id_grado
              WHERE m.id_estudiante = :estudiante
                AND " . $this->condicionAnioAnterior('ay', 'ay.id_anio_escolar') . "
              ORDER BY ay.anio_inicio DESC, m.id_matricula DESC LIMIT 1"
        );
        $stmt->execute(['estudiante' => $estudianteId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Estructura destino: grado siguiente, misma sección y turno, en el año en curso.*/
    /**
 * Estructura destino de la ratificación, en el año en curso. Lo que elige la
 * secretaria tiene prioridad sobre lo que se deduciría: grado, sección y turno vienen
 * en $destino cuando el modal los manda.
 *
 * @param array $matricula la matrícula de origen
 * @param array $destino ['id_grado'=>int,'id_seccion'=>int,'id_turno'=>int], opcional
 */
    private function estructuraDestino(array $matricula, array $destino = []): ?array
    {
        $gradoElegido = (int) ($destino['id_grado'] ?? 0);

        if ($gradoElegido > 0) {
            $gradoDestino = $gradoElegido;
        } else {
            // El resultado es por estudiante y año; puede estar ligado a otra
            // matrícula del mismo período si el reingreso se registró después.
            $stmtRes = $this->pdo->prepare(
                'SELECT literal, estado_resultado FROM resultado_academico
                  WHERE id_estudiante = :id_estudiante AND id_anio_escolar = :id_anio LIMIT 1'
            );
            $stmtRes->execute([
                'id_estudiante' => (int) $matricula['id_estudiante'],
                'id_anio'       => (int) $matricula['id_anio_escolar'],
            ]);
            $resAcad = $stmtRes->fetch(\PDO::FETCH_ASSOC);

            if ($resAcad && ($resAcad['literal'] === 'E' || $resAcad['estado_resultado'] === AcademicResultModel::ESTADO_NO_PROMOVIDO)) {
                // Si no fue promovido, debe repetir el mismo grado
                $gradoDestino = (int) $matricula['id_grado'];
            } else {
                $grados = $this->gradosOrdenados();
                $siguiente = $this->siguienteGradoConId($grados, $matricula['id_grado']);
                if (!$siguiente) {
                    return null;
                }
                $gradoDestino = (int) $siguiente['id'];
            }
        }

        $seccion = (int) ($destino['id_seccion'] ?? 0) ?: (int) $matricula['id_seccion'];
        $turno   = (int) ($destino['id_turno'] ?? 0) ?: (int) $matricula['id_turno'];

        // El destino es el año en curso, no "el próximo que exista": si no, el alumno se matricularía años antes de tiempo.
        $stmt = $this->pdo->prepare(
            'SELECT id_estructura_academica AS id, id_anio_escolar AS anio, id_grado AS id_grado
               FROM estructura_academica
              WHERE id_grado = :grado AND id_seccion = :seccion AND id_turno = :turno
                AND id_anio_escolar = :anio
              LIMIT 1'
        );
        $stmt->execute([
            'grado'   => $gradoDestino,
            'seccion' => $seccion,
            'turno'   => $turno,
            'anio'    => $this->anioEnCurso(),
        ]);
        $id = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $id ? [
            'id'       => (int) $id['id'],
            'anio'     => (int) $id['anio'],
            'id_grado' => (int) $id['id_grado'],
        ] : null;
    }

    /** ¿Ya tiene matrícula en ese año? El UNIQUE (estudiante, año) lo rechazaría igual; se pregunta antes para dar un mensaje claro.*/
    private function yaTieneMatriculaEn(int $estudianteId, int $anio): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM matricula WHERE id_estudiante = :estudiante AND id_anio_escolar = :anio'
        );
        $stmt->execute(['estudiante' => $estudianteId, 'anio' => $anio]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
 * Estado con el que cerró el año esa matrícula (el tipo de la fila), o null si sigue cursando.
 *
 * @return 'egresado'|'retirado'|null
*/
    private function estadoCerrado(int $matriculaId): ?string
    {
        $stmt = $this->pdo->prepare('SELECT tipo FROM matricula WHERE id_matricula = :m');
        $stmt->execute(['m' => $matriculaId]);
        $tipo = (string) ($stmt->fetchColumn() ?: Matriculas::INSCRIPCION);

        return in_array($tipo, self::ESTADOS_CERRADOS, true)
            ? mb_strtolower($tipo)
            : null;
    }

    /** ¿El alumno ya tiene matrícula en el año en curso? Es la misma pregunta y el mismo año que el listado, para que el botón no contradiga la pantalla.*/
    private function yaRatificado(int $matriculaId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM matricula
              WHERE id_estudiante = (SELECT id_estudiante FROM matricula WHERE id_matricula = :m)
                AND id_anio_escolar = :anio'
        );
        $stmt->execute(['m' => $matriculaId, 'anio' => $this->anioEnCurso()]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /** Año escolar en curso (el ACTIVO); sin año devuelve 0 y las consultas no traen a nadie. Sin caché: cachearlo hace que las pantallas se contradigan.*/
    private function anioEnCurso(): int
    {
        return AniosEscolares::idEnCurso($this->pdo);
    }

    /** Fecha de inicio del año en curso, para comparar años por fecha y no por id (los ids son de inserción y no siguen el orden).*/
    private function inicioDelAnioEnCurso(): string
    {
        /**
 * Con comillas: sin ellas MySQL lee `2026-12-10` como aritmética y la pantalla sale
 * vacía sin error. Si no hay año en curso el helper devuelve 9999-12-31, y todo
 * cuenta como anterior.
 */
        return "'" . AniosEscolares::inicioEnCurso($this->pdo) . "'";
    }

    /**
     * Condición SQL para "matrícula de un año anterior al activo".
     *
     * Normalmente basta con comparar `anio_inicio`, pero dos períodos pueden
     * compartir la misma fecha de inicio si se cargaron a mano. En ese caso el
     * año cerrado con la misma fecha sigue siendo el anterior; el activo se
     * excluye por id para no seleccionarlo como origen.
     */
    private function condicionAnioAnterior(string $aliasAnio, string $aliasId): string
    {
        $anio   = $this->anioEnCurso();
        $inicio = $this->inicioDelAnioEnCurso();

        if ($anio <= 0) {
            return "{$aliasAnio}.anio_inicio < {$inicio}";
        }

        return "({$aliasAnio}.anio_inicio < {$inicio}
                 OR ({$aliasAnio}.anio_inicio = {$inicio}
                     AND {$aliasAnio}.estado = 'CERRADO'
                     AND {$aliasId} <> {$anio}))";
    }

    /**
 * A quién le corresponde esta pantalla (la definición, no un filtro). Vive en un método para que listado y tarjetas cuadren.
 * (1) Su última matrícula es de un año anterior al en curso; (2) es de un tipo con continuación (INSCRIPCION o RATIFICACION);
 * (3) no tiene un retiro PENDIENTE sobre esa matrícula (uno rechazado no bloquea).
*/
    private function condicionesBase(): array
    {
        return [
            // (1) Año anterior, por FECHA y no por id del año (ver condicionAnioAnterior()).
            $this->condicionAnioAnterior('a', 'a.id_anio_escolar'),

            // (2) Último tipo con continuación; RETIRO y EGRESO son finales.
            "m.tipo IN ('" . self::FLUJO_INSCRIPCION . "', '" . self::FLUJO_RATIFICACION . "', '" . self::FLUJO_REINGRESO . "')",

            // (3) Sin retiro pendiente, sobre la matrícula concreta y no sobre el estudiante.
            'NOT EXISTS (
                 SELECT 1 FROM retiro r
                  WHERE r.id_matricula = m.id_matricula
                    AND r.estado_solicitud = \'PENDIENTE\'
             )',

            // (4) Excluir a estudiantes de 6.º grado con literal promovido (A, B, C, D):
            // su camino es el egreso. Quienes no promovieron (literal E / NO_PROMOVIDO) sí se
            // ratifican para repetir 6.º; y quienes aún no tienen literal cargado se muestran
            // en la lista con aviso y botón desactivado.
            '(
                CAST(REGEXP_SUBSTR(COALESCE(g.nombre, \'\'), \'^[0-9]+\') AS UNSIGNED) < ' . Instituciones::GRADO_MAYOR . '
                OR ra.literal IS NULL
                OR ra.literal = \'E\'
                OR ra.estado_resultado = \'NO_PROMOVIDO\'
             )',
        ];
    }

    /** ¿La matrícula tiene un retiro PENDIENTE? Solo ese bloquea; UNIQUE(id_matricula) garantiza a lo sumo una solicitud.*/
    private function tieneRetiroPendiente(int $matriculaId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM retiro
              WHERE id_matricula = :m AND estado_solicitud = 'PENDIENTE'"
        );
        $stmt->execute(['m' => $matriculaId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /** Listas de grados ACTIVO ordenados por el ordinal de su nombre.*/
    private function gradosOrdenados(): array
    {
        $rows = $this->pdo->query("SELECT id_grado AS id, nombre FROM grado WHERE estado = 'ACTIVO'")->fetchAll(\PDO::FETCH_ASSOC);
        usort($rows, function ($a, $b) {
            $ordA = (int) (preg_match('/^(\d+)/', $a['nombre'], $m) ? $m[1] : PHP_INT_MAX);
            $ordB = (int) (preg_match('/^(\d+)/', $b['nombre'], $m) ? $m[1] : PHP_INT_MAX);
            return $ordA <=> $ordB;
        });
        return $rows;
    }

    private function siguienteGradoConId(array $grados, ?int $idActual = null, ?string $nombreActual = null): ?array
    {
        $ordinalActual = PHP_INT_MAX;
        foreach ($grados as $grado) {
            if ($idActual !== null && $grado['id'] === $idActual) {
                $ordinalActual = $this->ordinal($grado['nombre']);
                break;
            }
            if ($nombreActual !== null && $grado['nombre'] === $nombreActual) {
                $ordinalActual = $this->ordinal($grado['nombre']);
                break;
            }
        }
        foreach ($grados as $grado) {
            if ($this->ordinal($grado['nombre']) > $ordinalActual) {
                return $grado;
            }
        }
        return null;
    }

    private function ordinal(string $nombre): int
    {
        return (int) (preg_match('/^(\d+)/', $nombre, $m) ? $m[1] : PHP_INT_MAX);
    }

    private function filters(array $filters): array
    {
        // Las condiciones base están en condicionesBase() (completadas con las del usuario); se comparten con las tarjetas.
        $where = $this->condicionesBase();
        $params = [];

        if (!empty($filters['grado'])) {
            /* El grado se filtra por NOMBRE y no por id porque es lo que viaja en
 el <select> del listado (getGrados() devuelve la lista de nombres).
 Con un id, el value del option y la comparación discreparían y el
 filtro no dejaría pasar a nadie; con el nombre, ambas cosas son la
 misma cadena y el filtro es legible en la URL.*/
            $where[] = 'g.nombre = :grado';
            $params['grado'] = $filters['grado'];
        }
        if (!empty($filters['seccion'])) {
            $where[] = 's.nombre = :seccion';
            $params['seccion'] = $filters['seccion'];
        }
        if (!empty($filters['turno'])) {
            $where[] = 't.nombre = :turno';
            $params['turno'] = $filters['turno'];
        }
        if (array_key_exists('id_turno', $filters)) {
            $where[] = 't.id_turno = :id_turno';
            $params['id_turno'] = (int) $filters['id_turno'];
        }
        if (!empty($filters['q'])) {
            foreach (['p.primer_nombre', 'p.primer_apellido', 'p.numero_documento'] as $i => $col) {
                $params["q$i"] = '%' . $filters['q'] . '%';
            }
            $where[] = '(p.primer_nombre LIKE :q0 OR p.primer_apellido LIKE :q1 OR p.numero_documento LIKE :q2)';
        }
        return [$where, $params];
    }
}
