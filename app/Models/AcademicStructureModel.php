<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Matriculas;
use App\Exceptions\CapacidadInsuficienteException;
use App\Exceptions\CupoAgotadoException;
use App\Interfaces\Creatable;
use App\Interfaces\Listable;
use App\Interfaces\Updatable;
use App\Traits\CupoEstructuraTrait;
use App\Traits\OpcionesCatalogoTrait;

/**
 * AcademicStructureModel
 * Gestiona las combinaciones de grado, sección, turno y año escolar.
 * Controla la capacidad máxima y disponibilidad de cupos por aula.
 */
class AcademicStructureModel extends BaseModel implements Listable, Creatable, Updatable
{
    use CupoEstructuraTrait;
    use OpcionesCatalogoTrait {
        getOptions as traitGetOptions;
    }

    protected string $table = 'estructura_academica';
    protected string $primaryKey = 'id_estructura_academica';

    protected array $columns = ['id_estructura_academica', 'id_anio_escolar', 'id_grado', 'id_seccion', 'id_turno', 'capacidad_maxima', 'enlace_whatsapp'];
    protected array $allowedColumns = ['id_estructura_academica', 'id_anio_escolar', 'id_grado', 'id_seccion', 'id_turno', 'capacidad_maxima', 'enlace_whatsapp'];

    public function getAll(array $filters = [], int $page = 1, int $perPage = 10): array
    {
        [$where, $params] = $this->filters($filters);

        $sql = "SELECT ea.id_estructura_academica AS id, ea.id_anio_escolar,
                        ea.id_grado, ea.id_seccion, ea.id_turno, ea.capacidad_maxima,
                        a.nombre_periodo AS anio,
                        g.nombre AS grado, s.nombre AS seccion, t.nombre AS turno,
                        (SELECT COUNT(DISTINCT m.id_estudiante) FROM matricula m
                          WHERE m.id_estructura_academica = ea.id_estructura_academica
                            AND " . Matriculas::condicionCursando('m') . ") AS estudiantes
                 FROM estructura_academica ea
                INNER JOIN anio_escolar a ON a.id_anio_escolar = ea.id_anio_escolar
                INNER JOIN grado g ON g.id_grado = ea.id_grado
                INNER JOIN seccion s ON s.id_seccion = ea.id_seccion
                INNER JOIN turno t ON t.id_turno = ea.id_turno";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY a.anio_inicio DESC, g.nombre, s.nombre LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['id'] = (int) $row['id'];
            $row['capacidad_maxima'] = (int) $row['capacidad_maxima'];
        }
        return $rows;
    }

    public function countAll(array $filters = []): int
    {
        [$where, $params] = $this->filters($filters);

        $sql = "SELECT COUNT(*) FROM estructura_academica ea
                INNER JOIN anio_escolar a ON a.id_anio_escolar = ea.id_anio_escolar
                INNER JOIN grado g ON g.id_grado = ea.id_grado
                INNER JOIN seccion s ON s.id_seccion = ea.id_seccion
                INNER JOIN turno t ON t.id_turno = ea.id_turno";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
 * Tarjetas de la pantalla: cuántas combinaciones hay, de cuántos grados y secciones,
 * y la capacidad total. $idAnio acota a un período.
 */
public function getStats(?int $idAnio = null): array
{
    if ($idAnio !== null && $idAnio > 0) {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(ea.id_estructura_academica) AS total,
                    COUNT(DISTINCT ea.id_grado) AS grados,
                    COUNT(DISTINCT ea.id_seccion) AS secciones,
                    COALESCE(SUM(ea.capacidad_maxima), 0) AS capacidad
               FROM estructura_academica ea
              WHERE ea.id_anio_escolar = :anio'
        );
        $stmt->execute(['anio' => $idAnio]);
    } else {
        $stmt = $this->pdo->query(
            "SELECT COUNT(ea.id_estructura_academica) AS total,
                    COUNT(DISTINCT ea.id_grado) AS grados,
                    COUNT(DISTINCT ea.id_seccion) AS secciones,
                    COALESCE(SUM(ea.capacidad_maxima), 0) AS capacidad
               FROM anio_escolar a
               LEFT JOIN estructura_academica ea ON ea.id_anio_escolar = a.id_anio_escolar
              WHERE a.estado = 'ACTIVO'"
        );
    }

    $stats = $stmt->fetch(\PDO::FETCH_ASSOC);

    return [
        'total'     => (int) $stats['total'],
        'grados'    => (int) $stats['grados'],
        'secciones' => (int) $stats['secciones'],
        'capacidad' => (int) $stats['capacidad'],
    ];
    }

    /**
     * Retorna los años escolares disponibles para consulta y filtrado en el listado.
     * Incluye indicador de editabilidad (solo períodos activos) y etiqueta de estado.
     */
    public function getAniosParaConsultar(): array
{
    $filas = $this->pdo->query(
        'SELECT id_anio_escolar AS id, nombre_periodo AS nombre, estado
           FROM anio_escolar
          ORDER BY anio_inicio DESC, id_anio_escolar DESC'
    )->fetchAll(\PDO::FETCH_ASSOC);

    return array_map(static function (array $fila): array {
        $estado = strtoupper((string) $fila['estado']);
        return [
            'id'          => (int) $fila['id'],
            'nombre'      => (string) $fila['nombre'],
            'estado'      => $estado,
            'es_editable' => $estado === 'ACTIVO',
            'sufijo_estado' => match ($estado) {
                'CERRADO' => ' (cerrado)',
                'PLANIFICADO' => ' (planificado)',
                default => '',
            },
        ];
    }, $filas);
}

/**
 * Catálogos activos (id + nombre) para filtros y modales: anio_escolar, grado, seccion y turno.
 * Cada tabla declara su columna de etiqueta (anio_escolar usa nombre_periodo) y siempre se devuelve como 'nombre'.
*/
    public function getOptions(string $table): array
    {
        $turnosExcluidos = ($table === 'turno') ? TurnoModel::TURNOS_EXCLUSIVOS_PERSONAL : null;
        return $this->traitGetOptions($table, $turnosExcluidos);
    }

    /**
 * Evalúa la completitud de la estructura académica en el período lectivo activo.
 *
 * @return array<string, mixed>
 */
    public function pendientesDeConfiguracion(): array
    {
        $idAnio  = $this->getActiveSchoolYearId();
        $nombre  = $this->getActiveSchoolYearName() ?? '';

        $grados = (int) $this->pdo
            ->query("SELECT COUNT(*) FROM grado WHERE estado = 'ACTIVO'")
            ->fetchColumn();
        $turnos = (int) $this->pdo
            ->query("SELECT COUNT(*) FROM turno WHERE estado = 'ACTIVO'")
            ->fetchColumn();

        /* Sin año abierto no hay nada que contar: el mensaje tiene que decir eso y no
 "faltan 12 combinaciones", que empuja a arreglar algo que no es el
 problema real.*/
        if ($idAnio === null) {
            return [
                'hay_anio_activo' => false,
                'anio'            => $nombre,
                'grados'           => $grados,
                'turnos'           => $turnos,
                'esperadas'        => 0,
                'registradas'      => 0,
                'pendientes'       => 0,
                'grados_sin_seccion' => [],
            ];
        }

        $registradas = (int) $this->pdo->query(
            'SELECT COUNT(*) FROM estructura_academica WHERE id_anio_escolar = ' . (int) $idAnio
        )->fetchColumn();

        /* Identificación crítica: los grados activos que carecen de sección configurada
           impiden la ratificación de alumnos promovidos desde el grado anterior.*/
        $stmt = $this->pdo->prepare(
            'SELECT g.nombre
               FROM grado g
              WHERE g.estado = \'ACTIVO\'
                AND NOT EXISTS (
                    SELECT 1 FROM estructura_academica ea
                     WHERE ea.id_grado = g.id_grado AND ea.id_anio_escolar = :anio
                )
              ORDER BY CAST(SUBSTRING_INDEX(g.nombre, \' \', 1) AS UNSIGNED), g.nombre'
        );
        $stmt->execute(['anio' => $idAnio]);

        $esperadas = $grados * $turnos;

        return [
            'hay_anio_activo'  => true,
            'anio'             => $nombre,
            'grados'           => $grados,
            'turnos'           => $turnos,
            'esperadas'        => $esperadas,
            'registradas'      => $registradas,
            'pendientes'       => max(0, $esperadas - $registradas),
            'grados_sin_seccion' => $stmt->fetchAll(\PDO::FETCH_COLUMN),
        ];
    }

    public function esAnioActivo(int $idAnioEscolar): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM anio_escolar
              WHERE id_anio_escolar = :id AND estado = 'ACTIVO'
              LIMIT 1"
        );
        $stmt->execute(['id' => $idAnioEscolar]);

        return $stmt->fetchColumn() !== false;
    }

    /**
 * Alta de una combinación académica (contrato Creatable).
 * El duplicado (UNIQUE año, grado, sección, turno) se valida en el controlador antes de llegar aquí.
*/
    public function create(array $data): bool
    {
        $this->pdo->beginTransaction();
        try {
            $this->validarAnioActivo((int) $data['id_anio_escolar']);
            $ok = $this->pdo->prepare(
                'INSERT INTO estructura_academica (id_anio_escolar, id_grado, id_seccion, id_turno, capacidad_maxima)
                 VALUES (:anio, :grado, :seccion, :turno, :capacidad)'
            )->execute([
                'anio'      => (int) $data['id_anio_escolar'],
                'grado'     => (int) $data['id_grado'],
                'seccion'   => (int) $data['id_seccion'],
                'turno'     => (int) $data['id_turno'],
                'capacidad' => (int) $data['capacidad_maxima'],
            ]);
            $this->pdo->commit();

            return $ok;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
 * Indica si ya existe esa combinación (año + grado + sección + turno).
 * Con $exceptoId se deja fuera la fila que se está editando.
*/
    public function existeCombination(array $data, ?int $exceptoId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM estructura_academica
                WHERE id_anio_escolar = :anio AND id_grado = :grado
                  AND id_seccion = :seccion AND id_turno = :turno';
        $params = [
            'anio'    => (int) $data['id_anio_escolar'],
            'grado'   => (int) $data['id_grado'],
            'seccion' => (int) $data['id_seccion'],
            'turno'   => (int) $data['id_turno'],
        ];
        if ($exceptoId !== null) {
            $sql .= ' AND id_estructura_academica != :id';
            $params['id'] = $exceptoId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    /* Cuántos estudiantes ocupan una fila de estructura concreta, por su id. Es el gemelo de
 ocupadosDeCombinacion() pero por fila y no por combinación, y esa es la diferencia que
 importa al editar: la combinación del formulario puede no ser la que se está tocando.*/
    public function ocupadosDeEstructura(int $idEstructura): int
    {
        if ($idEstructura <= 0) {
            return 0;
        }

        return (int) ($this->conteoDeOcupados($idEstructura)[$idEstructura] ?? 0);
    }

    /**
 * Última fila de la estructura con su año, grado, sección, turno y capacidad. Se usa
 * para comparar lo que hay en la BD contra lo que viene del formulario y poder decir si
 * la edición mueve la combinación o solo cambia la capacidad.
 *
 * @return array{id_anio_escolar:int,id_grado:int,id_seccion:int,id_turno:int,capacidad_maxima:int}|null
 */
    public function estructuraActual(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id_anio_escolar, id_grado, id_seccion, id_turno, capacidad_maxima
               FROM estructura_academica
              WHERE id_estructura_academica = :id
              LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $fila ? [
            'id_anio_escolar'  => (int) $fila['id_anio_escolar'],
            'id_grado'         => (int) $fila['id_grado'],
            'id_seccion'       => (int) $fila['id_seccion'],
            'id_turno'         => (int) $fila['id_turno'],
            'capacidad_maxima' => (int) $fila['capacidad_maxima'],
        ] : null;
    }

    /**
 * ¿La edición cambia la combinación (grado, sección o turno) o solo la capacidad?
 * El año se excluye a propósito: mover una fila de un período a otro no es un cambio
 * de combinación que arrastre alumnos, porque sin Combinar no se puede pasar a un año
 * que ya tenga esa combinación y el modelo impide el duplicado.
 */
    public function cambiaCombinacion(array $actual, array $nuevo): bool
    {
        return (int) $actual['id_grado'] !== (int) $nuevo['id_grado']
            || (int) $actual['id_seccion'] !== (int) $nuevo['id_seccion']
            || (int) $actual['id_turno'] !== (int) $nuevo['id_turno'];
    }

    /* Edición de la combinación académica (contrato Updatable). Recibe el id y los mismos campos
 que create(). La ocupación se comprueba SOBRE LA FILA QUE SE ESTÁ EDITANDO, no sobre la
 combinación que llega en el formulario, que pueden no ser la misma.*/
    public function update(int $id, array $data): bool
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                "SELECT ea.id_estructura_academica, ea.id_grado, ea.id_seccion, ea.id_turno
               FROM estructura_academica ea
               INNER JOIN anio_escolar a ON a.id_anio_escolar = ea.id_anio_escolar
              WHERE ea.id_estructura_academica = :id AND a.estado = 'ACTIVO'
              FOR UPDATE"
            );
            $stmt->execute(['id' => $id]);
            $actual = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($actual === false) {
                throw new \RuntimeException('No se pueden modificar estructuras de años escolares cerrados o inexistentes.');
            }

            $this->validarAnioActivo((int) $data['id_anio_escolar']);

            $ocupados = $this->ocupadosDeEstructura($id);
            if ($ocupados > 0 && (int) $data['capacidad_maxima'] < $ocupados) {
                throw new CapacidadInsuficienteException(
                    'La sección tiene ' . $ocupados . ' estudiante(s) matriculado(s): '
                    . 'la capacidad no puede quedar por debajo de esa cantidad. '
                    . 'Reduce el número de alumnos o aumenta la capacidad.',
                    $ocupados
                );
            }

            $ok = $this->pdo->prepare(
                'UPDATE estructura_academica
                 SET id_anio_escolar = :anio, id_grado = :grado, id_seccion = :seccion,
                     id_turno = :turno, capacidad_maxima = :capacidad
                 WHERE id_estructura_academica = :id'
            )->execute([
                'anio'      => (int) $data['id_anio_escolar'],
                'grado'     => (int) $data['id_grado'],
                'seccion'   => (int) $data['id_seccion'],
                'turno'     => (int) $data['id_turno'],
                'capacidad' => (int) $data['capacidad_maxima'],
                'id'        => $id,
            ]);
            $this->pdo->commit();

            return $ok;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
 * Cuenta los que cursan de verdad (INSCRIPCION y RATIFICACION, y solo su última fila),
 * que es la misma regla de Matriculas::condicionCursando que usa el conteo de cupos: si
 * esta cuenta y la del cupo no hicieran lo mismo, la validación de capacidad y el
 * bloqueo por sección llena se contradirían entre sí.
 */
    public function ocupadosDeCombinacion(int $idAnioEscolar, int $idGrado, int $idSeccion, int $idTurno): int
    {
        if ($idAnioEscolar <= 0 || $idGrado <= 0 || $idSeccion <= 0 || $idTurno <= 0) {
            return 0;
        }

        $stmt = $this->pdo->prepare(
            'SELECT ea.id_estructura_academica
               FROM estructura_academica ea
              WHERE ea.id_anio_escolar = :anio AND ea.id_grado = :grado
                AND ea.id_seccion = :seccion AND ea.id_turno = :turno
              LIMIT 1'
        );
        $stmt->execute([
            'anio'    => $idAnioEscolar,
            'grado'   => $idGrado,
            'seccion' => $idSeccion,
            'turno'   => $idTurno,
        ]);
        $idEstructura = $stmt->fetchColumn();
        if ($idEstructura === false) {
            return 0;
        }

        return (int) ($this->conteoDeOcupados()[(int) $idEstructura] ?? 0);
    }

    private function validarAnioActivo(int $idAnioEscolar): void
    {
        $stmt = $this->pdo->prepare(
            "SELECT id_anio_escolar FROM anio_escolar
              WHERE id_anio_escolar = :id AND estado = 'ACTIVO'
              FOR UPDATE"
        );
        $stmt->execute(['id' => $idAnioEscolar]);
        if ($stmt->fetchColumn() === false) {
            throw new \RuntimeException('Las nuevas estructuras académicas deben pertenecer al año escolar activo.');
        }
    }

    private function filters(array $filters): array
    {
        $where = [];
        $params = [];
        foreach (['anio' => 'ea.id_anio_escolar', 'grado' => 'ea.id_grado', 'seccion' => 'ea.id_seccion', 'turno' => 'ea.id_turno'] as $key => $column) {
            if (($filters[$key] ?? '') !== '') {
                $where[] = $column . ' = :' . $key;
                $params[$key] = (int) $filters[$key];
            }
        }
        if (!empty($filters['q'])) {
            foreach (['g.nombre', 's.nombre', 't.nombre'] as $i => $col) {
                $params["q$i"] = $filters['q'] . '%';
            }
            $where[] = '(g.nombre LIKE :q0 OR s.nombre LIKE :q1 OR t.nombre LIKE :q2)';
        }
        return [$where, $params];
    }
}
