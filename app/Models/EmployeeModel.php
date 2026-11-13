<?php

declare(strict_types=1);

namespace App\Models;

use App\Interfaces\Creatable;
use App\Interfaces\Listable;
use App\Interfaces\Toggleable;
use App\Interfaces\Updatable;
use App\Support\Documento;
use App\Support\Instituciones;
use App\Traits\AvatarTrait;
use PDO;
use RuntimeException;
use Throwable;

class EmployeeModel extends BaseModel implements Listable, Creatable, Updatable, Toggleable
{
    use AvatarTrait;

    protected string $table = 'empleado';
    protected string $primaryKey = 'id_empleado';

    protected array $columns = ['id_empleado', 'id_persona', 'fecha_ingreso', 'fecha_egreso', 'id_cargo', 'id_turno'];
    protected array $allowedColumns = ['id_empleado', 'id_persona', 'fecha_ingreso', 'fecha_egreso', 'id_cargo', 'id_turno'];

    /**
     * Columnas del reposo para el listado y el detalle.
     *
     * Va como constante y no pegado en cada SELECT porque "en reposo" tiene que significar
     * LO MISMO en las dos consultas, y el día que una se quede atrás el listado muestra que
     * el empleado descansa y el detalle dice que no, o al revés.
     *
     * Se arma fuera de la cadena del SELECT a propósito: un comentario de PHP dentro de un
     * string doble rompe el archivo, y el comentario es lo que explica por qué se calcula.
     */

    /** Columna para el badge del listado y para getById(). */
    private const COLUMNA_EN_REPOSO =
        ReposoEmployeeModel::EXPRESION_EN_REPOSO . ' AS en_reposo';

    /**
     * Motivo del reposo vigente: SOLO se consulta en getById() (detalle).
     * El listado (getAll) no lo consulta ni lo expone por privacidad (dato de salud).
     */
    private const COLUMNA_MOTIVO_REPOSO =
        '(SELECT r.motivo FROM reposo_empleado r'
        . '  WHERE r.id_empleado = e.id_empleado'
        . '    AND ' . ReposoEmployeeModel::CONDICION_VIGENTE
        . '  ORDER BY r.fecha_inicio DESC LIMIT 1) AS motivo_reposo';

    private const CAMPOS_EMPLEADO = ['id_cargo', 'fecha_ingreso', 'id_turno'];

    public function getAll(array $filters = [], int $page = 1, int $perPage = 10): array
    {
        [$where, $params] = $this->buildFilters($filters);

        $sql = "SELECT e.id_empleado,
                       p.tipo_documento,
                       p.numero_documento,
                       p.primer_nombre,
                       p.segundo_nombre,
                       p.primer_apellido,
                       p.segundo_apellido,
                       p.foto,
                       p.estado,
                       c.nombre AS cargo,
                       t.nombre AS turno,
                        COALESCE(nac.nombre, '') AS nacionalidad,
                        " . self::COLUMNA_EN_REPOSO . "
                FROM empleado e
                INNER JOIN persona p ON p.id_persona = e.id_persona
                 LEFT JOIN nacionalidad nac ON nac.id_nacionalidad = p.id_nacionalidad
                LEFT JOIN cargo c ON c.id_cargo = e.id_cargo
                LEFT JOIN turno t ON t.id_turno = e.id_turno";

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY p.primer_apellido, p.primer_nombre LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->execute();

        return array_map([$this, 'mapRowToVista'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function countAll(array $filters = []): int
    {
        [$where, $params] = $this->buildFilters($filters);

        $sql = "SELECT COUNT(*)
                FROM empleado e
                INNER JOIN persona p ON p.id_persona = e.id_persona
                LEFT JOIN cargo c ON c.id_cargo = e.id_cargo";

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function mapRowToVista(array $row): array
    {
        $nombreCompleto = trim(
            (($row['primer_nombre'] ?? '') . ' ' . ($row['segundo_nombre'] ?? '') . ' ' . ($row['primer_apellido'] ?? '') . ' ' . ($row['segundo_apellido'] ?? ''))
        );

        if ($nombreCompleto === '') {
            $nombreCompleto = 'Empleado';
        }

        $avatar = $this->resolverAvatar(
            $row['foto'] ?? null,
            $row['primer_nombre'] ?? null,
            $row['primer_apellido'] ?? null,
            'directivo',
            $nombreCompleto
        );

        return [
            'id_empleado'      => (int) ($row['id_empleado'] ?? 0),
            'nombre_completo'  => $nombreCompleto,
            'tipo_documento'   => (string) ($row['tipo_documento'] ?? ''),
            'numero_documento' => (string) ($row['numero_documento'] ?? ''),
            'cargo'            => $row['cargo'] ?? 'Sin cargo',
            'turno'            => $row['turno'] ?? 'Sin turno',
            /* Vacía y no "Sin nacionalidad": el listado es para leer de un vistazo y
               un texto de relleno por fila distrae de las que sí lo tienen. */
            'nacionalidad'     => (string) ($row['nacionalidad'] ?? ''),
            /* El badge del listado. La EXISTS devuelve 1/0 y se pasa a bool para que la
               vista no tenga que compararlo con 1. El motivo no se expone en el listado. */
            'en_reposo'        => (int) ($row['en_reposo'] ?? 0) === 1,
            'estado'           => strtoupper((string) ($row['estado'] ?? 'INACTIVO')),
            'avatar_tipo'      => $avatar['tipo'],
            'avatar_foto'      => $avatar['foto'],
            'iniciales'        => $avatar['iniciales'],
            'avatar_color'     => $avatar['avatar_color'],
        ];
    }

    protected function buildFilters(array $filters): array
    {
        $where  = [];
        $params = [];

        if (!empty($filters['q'])) {
            $where[] = '(p.numero_documento LIKE :q0 OR p.primer_nombre LIKE :q1 OR p.primer_apellido LIKE :q2)';
            $search = '%' . $filters['q'] . '%';
            $params['q0'] = $search;
            $params['q1'] = $search;
            $params['q2'] = $search;
        }

        if (!empty($filters['cargo'])) {
            $where[]         = "e.id_cargo = :cargo";
            $params['cargo'] = (int) $filters['cargo'];
        }

        if (!empty($filters['estado'])) {
            $where[]          = "p.estado = :estado";
            $params['estado'] = strtoupper((string) $filters['estado']);
        }

        return [$where, $params];
    }

    public function getEstadisticas(): array
    {
        $fila = $this->pdo->query("SELECT COUNT(*) AS total,
                SUM(CASE WHEN p.estado = 'ACTIVO' THEN 1 ELSE 0 END) AS activos
           FROM empleado e INNER JOIN persona p ON p.id_persona = e.id_persona")->fetch(PDO::FETCH_ASSOC) ?: [];
        $total = (int) ($fila['total'] ?? 0);
        $activos = (int) ($fila['activos'] ?? 0);

        return [
            'total'     => $total,
            'activos'   => $activos,
            'inactivos' => $total - $activos,
            'cargos'    => (int) $this->pdo->query('SELECT COUNT(DISTINCT id_cargo) FROM empleado WHERE id_cargo IS NOT NULL')->fetchColumn(),
        ];
    }

    /**
     * Nacionalidades para el <select> del paso 1.
     *
     * @return array{nacionalidades:array<int,array<string,mixed>>,id_por_defecto:int}
     */
    public function getNacionalidades(): array
    {
        return [
            'nacionalidades' => $this->pdo->query("SELECT id_nacionalidad, nombre FROM nacionalidad
                                                    WHERE estado = 'ACTIVO' ORDER BY nombre")
                                     ->fetchAll(PDO::FETCH_ASSOC),
            'id_por_defecto'  => (int) NacionalidadModel::ID_POR_DEFECTO,
        ];
    }

    public function getCargos(): array
    {
        $stmt = $this->pdo->query('SELECT id_cargo, nombre FROM cargo WHERE estado = "ACTIVO" ORDER BY nombre ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function existeTurno(int $idTurno): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM turno WHERE id_turno = :id AND estado = 'ACTIVO'");
        $stmt->execute(['id' => $idTurno]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
 * Un grado académico del catálogo existe.
 *
 * Se usa en empleados/guardarTitulo antes de crear el título. El <select> solo ofrece
 * los grados que hay, pero el id llega del formulario y el servidor no puede depender
 * de eso: sin esta comprobación, un id inventado llega al INSERT y MariaDB avisa por
 * log y guarda el 0, con el alta "exitosa" y el título sin grado.
 *
 * Antes se llamaba existeCatalogo('grado_academico', $grado) desde el controlador, y
 * ese método no existía en ningún modelo: era un error fatal que tumbaba la pantalla
 * con un 500 en blanco, sin mensaje ni aviso, y el título nunca se guardaba.
*/
    public function existeGradoAcademico(int $idGrado): bool
    {
        if ($idGrado <= 0) {
            return false;
        }

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM grado_academico WHERE id_grado_academico = :id');
        $stmt->execute(['id' => $idGrado]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function turnoCompatibleConAsignaciones(int $idEmpleado, ?int $idTurno): bool
    {
        $sql = "SELECT COUNT(*)
                  FROM docente d
                  INNER JOIN asignacion_docente ad ON ad.id_docente = d.id_docente
                  INNER JOIN estructura_academica ea ON ea.id_estructura_academica = ad.id_estructura_academica
                 WHERE d.id_empleado = :empleado
                   AND (ad.fecha_fin IS NULL OR ad.fecha_fin >= CURDATE())";
        $params = ['empleado' => $idEmpleado];

        if ($idTurno === null) {
            $sql .= ' AND ea.id_turno IS NOT NULL';
        } else {
            $sql .= ' AND ea.id_turno <> :turno';
            $params['turno'] = $idTurno;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() === 0;
    }

    /**
 * Catálogos de los formularios de alta y edición.
 *
 * Cada uno devuelve su columna tal como se llama en la tabla (id_grado_academico,
 * id_titulo, id_institucion...) y no renombrada a "id". Estas consultas traían "AS id"
 * y las vistas piden la clave real, así que los <option> salían con value="0" en
 * todos los títulos, grados e instituciones de los dos modales: al elegir el grado no se
 * desosliegaba nada porque todos los títulos valían lo mismo, y al guardar el servidor
 * recibía id_titulo=0 y descartaba la formación.
 *
 * "estados" y "turnos" nunca se renombraron, y por eso esos dos sí funcionaban.
*/
    public function getCatalogosFormulario(): array
    {
        $idTipoTitulo = (int) Instituciones::idCatalogo(Instituciones::TIPO_TITULO_ACADEMICO);

        return [
            'grados_academicos' => $this->pdo->query('SELECT id_grado_academico, nombre FROM grado_academico ORDER BY nombre ASC')->fetchAll(PDO::FETCH_ASSOC),
            'titulos'           => $this->pdo->query('SELECT id_titulo, nombre, id_grado_academico FROM titulo ORDER BY nombre ASC')->fetchAll(PDO::FETCH_ASSOC),
            'instituciones'     => $this->pdo->query("SELECT id_institucion, nombre FROM institucion WHERE estado = 'ACTIVO' AND id_tipo_institucion = {$idTipoTitulo} ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC),
            'tipos_institucion' => $this->pdo->query('SELECT id_tipo_institucion, nombre FROM tipo_institucion ORDER BY nombre ASC')->fetchAll(PDO::FETCH_ASSOC),
            'estados'           => $this->pdo->query('SELECT id_estado, nombre FROM estado ORDER BY nombre ASC')->fetchAll(PDO::FETCH_ASSOC),
            'turnos'            => $this->pdo->query("SELECT id_turno, nombre FROM turno WHERE estado = 'ACTIVO' ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    public function getById(int $id): ?array
    {
        $sql = "SELECT e.id_empleado,
                       e.id_cargo,
                       e.id_turno,
                       e.fecha_ingreso,
                       p.tipo_documento,
                       p.numero_documento,
                       p.fecha_nacimiento,
                       p.id_estado_nacimiento,
                       p.id_parroquia_nacimiento, p.id_nacionalidad,
                       p.sexo,
                       p.primer_nombre,
                       p.segundo_nombre,
                       p.primer_apellido,
                       p.segundo_apellido,
                       p.foto,
                       p.estado,
                       c.codigo_telefono_principal,
                       c.numero_telefono_principal,
                       c.codigo_telefono_alternativo,
                       c.numero_telefono_alternativo,
                       c.correo_electronico,
                       d.id_parroquia,
                       pq.nombre AS parroquia_nombre,
                       m.id_municipio,
                       m.nombre AS municipio_nombre,
                       m.id_estado,
                       est.nombre AS estado_nombre,
                       d.sector_urbanizacion,
                       d.calle_avenida,
                       d.nro_casa_apto,
                       d.punto_referencia,
                       car.nombre AS cargo,
                       t.nombre AS turno,
                        COALESCE(nac.nombre, '') AS nacionalidad,
                        " . self::COLUMNA_EN_REPOSO . ",
                        " . self::COLUMNA_MOTIVO_REPOSO . "
                FROM empleado e
                INNER JOIN persona p ON p.id_persona = e.id_persona
                 LEFT JOIN nacionalidad nac ON nac.id_nacionalidad = p.id_nacionalidad
                LEFT JOIN contacto c ON c.id_persona = p.id_persona
                LEFT JOIN direccion d ON d.id_persona = p.id_persona
                LEFT JOIN parroquia pq ON pq.id_parroquia = d.id_parroquia
                LEFT JOIN municipio m ON m.id_municipio = pq.id_municipio
                LEFT JOIN estado est ON est.id_estado = m.id_estado
                LEFT JOIN cargo car ON car.id_cargo = e.id_cargo
                LEFT JOIN turno t ON t.id_turno = e.id_turno
                WHERE e.id_empleado = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $nombreCompleto = trim(
            (($row['primer_nombre'] ?? '') . ' ' . ($row['segundo_nombre'] ?? '') . ' ' . ($row['primer_apellido'] ?? '') . ' ' . ($row['segundo_apellido'] ?? ''))
        );
        if ($nombreCompleto === '') {
            $nombreCompleto = 'Empleado';
        }

        $avatar = $this->resolverAvatar(
            $row['foto'] ?? null,
            $row['primer_nombre'] ?? null,
            $row['primer_apellido'] ?? null,
            'directivo',
            $nombreCompleto
        );

        $row['nombre_completo'] = $nombreCompleto;
        $row['avatar_tipo']     = $avatar['tipo'];
        $row['avatar_foto']     = $avatar['foto'];
        $row['iniciales']       = $avatar['iniciales'];
        $row['avatar_color']    = $avatar['avatar_color'];
        $row['telefono']        = trim(($row['codigo_telefono_principal'] ?? '') . '-' . ($row['numero_telefono_principal'] ?? ''));
        $row['telefono_alternativo'] = trim(($row['codigo_telefono_alternativo'] ?? '') . '-' . ($row['numero_telefono_alternativo'] ?? ''));
        $row['correo']          = $row['correo_electronico'] ?? '';

        $direccionPartes = array_filter([
            $row['calle_avenida'] ?? '',
            $row['nro_casa_apto'] ? 'Casa nro ' . $row['nro_casa_apto'] : '',
            $row['sector_urbanizacion'] ? 'Sector ' . $row['sector_urbanizacion'] : '',
            $row['parroquia_nombre'] ? 'Parroquia ' . $row['parroquia_nombre'] : '',
            $row['municipio_nombre'] ? 'Municipio ' . $row['municipio_nombre'] : '',
            $row['estado_nombre'] ? 'Estado ' . $row['estado_nombre'] : '',
            $row['punto_referencia'] ? 'Punto de referencia ' . $row['punto_referencia'] : '',
        ]);
        $row['direccion_completa'] = implode(', ', $direccionPartes);

        $stmtFormacion = $this->pdo->prepare('SELECT id_formacion_academica, id_titulo, id_grado_academico, id_institucion, fecha_obtencion FROM formacion_academica WHERE id_empleado = :id ORDER BY fecha_obtencion DESC');
        $stmtFormacion->execute(['id' => $id]);
        $row['formacion'] = $stmtFormacion->fetchAll(PDO::FETCH_ASSOC);

        $row['en_reposo']     = (int) ($row['en_reposo'] ?? 0) === 1;
        $row['motivo_reposo'] = (string) ($row['motivo_reposo'] ?? '');

        return $row;
    }

    /**
     * Nombre corto (nombre y apellido) de un empleado.
     *
     * Se usa en textos de bitácora y avisos donde el nombre completo sobra. El
     * CONCAT_WS es el mismo del listado para que el mismo empleado se lea igual
     * en la pantalla, en el detalle y en la auditoría.
     */
    public function getNombreCorto(int $idEmpleado): ?string
    {
        if ($idEmpleado <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            "SELECT CONCAT_WS(' ', p.primer_nombre, p.primer_apellido) AS nombre
               FROM empleado e
               INNER JOIN persona p ON p.id_persona = e.id_persona
              WHERE e.id_empleado = :id
              LIMIT 1"
        );
        $stmt->execute(['id' => $idEmpleado]);
        $nombre = $stmt->fetchColumn();

        return $nombre === false || trim((string) $nombre) === '' ? null : (string) $nombre;
    }

    public function getMunicipiosPorEstado(int $id_estado): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id_municipio, nombre FROM municipio
             WHERE id_estado = :id_estado AND estado = 'ACTIVO' ORDER BY nombre"
        );
        $stmt->execute(['id_estado' => $id_estado]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getParroquiasPorMunicipio(int $id_municipio): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id_parroquia, nombre FROM parroquia
             WHERE id_municipio = :id_municipio AND estado = 'ACTIVO' ORDER BY nombre"
        );
        $stmt->execute(['id_municipio' => $id_municipio]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Comprueba si una cédula ya pertenece a un empleado registrado.
     * $ignorarId excluye al propio empleado durante la edición.
     */
    public function existeDocumento(string $documento, ?int $ignorarId = null): bool
    {
        $documento = Documento::normalizar($documento);
        $sql = "SELECT COUNT(*) FROM empleado e
                INNER JOIN persona p ON p.id_persona = e.id_persona
                WHERE p.numero_documento = :doc";
        $params = ['doc' => $documento];

        if ($ignorarId !== null && $ignorarId > 0) {
            $sql .= " AND e.id_empleado != :ignorarId";
            $params['ignorarId'] = $ignorarId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return ((int) $stmt->fetchColumn()) > 0;
    }

    public function create(array $data): bool
    {
        $this->pdo->beginTransaction();

        try {
            $personaModel = new PersonaModel($this->pdo);
            $contactoModel = new ContactoModel($this->pdo);
            $direccionModel = new DireccionModel($this->pdo);

            $id_persona = $personaModel->buscarOCrear($data);

            /* Si la persona ya existía (por ejemplo, registrada como familiar de un
               estudiante), buscarOCrear() la reutiliza sin tocar sus campos. Se guardan
               aquí los datos que el wizard sí pide y que pueden faltarle. */
            $actualizarPersona = [];
            foreach (['id_estado_nacimiento', 'id_nacionalidad'] as $campo) {
                if ((int) ($data[$campo] ?? 0) > 0) {
                    $actualizarPersona[$campo] = (int) $data[$campo];
                }
            }
            if (!empty($data['foto'])) {
                $actualizarPersona['foto'] = $data['foto'];
            }
            if ($actualizarPersona !== []) {
                $personaModel->updatePersona($id_persona, $actualizarPersona);
            }

            $stmt = $this->pdo->prepare(
                    'INSERT INTO empleado (id_persona, fecha_ingreso, id_cargo, id_turno)
                     VALUES (:id_persona, :fecha_ingreso, :id_cargo, :id_turno)'
                );
            $stmt->execute(
                [
                    'id_persona'    => $id_persona,
                    'fecha_ingreso' => $data['fecha_ingreso'],
                    'id_cargo'      => (int) $data['id_cargo'],
                    'id_turno'      => $data['id_turno'] ?: null,
                ]
            );

            $id_empleado = (int) $this->pdo->lastInsertId();
            $this->sincronizarRegistroDocente($id_empleado, (int) $data['id_cargo']);

            if (!empty($data['contacto'])) {
                $contactoModel->upsert($id_persona, $data['contacto']);
            }

            if (!empty($data['direccion'])) {
                $direccionModel->upsert($id_persona, $data['direccion']);
            }

            $this->reemplazarFormacion($id_empleado, $data['formacion'] ?? []);

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare('SELECT id_persona FROM empleado WHERE id_empleado = :id');
        $stmt->execute(['id' => $id]);
        $id_persona = $stmt->fetchColumn();

        if ($id_persona === false) {
            throw new RuntimeException("El empleado {$id} no existe.");
        }

        $this->pdo->beginTransaction();

        try {
            $personaModel = new PersonaModel($this->pdo);
            $contactoModel = new ContactoModel($this->pdo);
            $direccionModel = new DireccionModel($this->pdo);

            $personaModel->updatePersona((int) $id_persona, $data);

            $empleadoData = array_intersect_key($data, array_flip(self::CAMPOS_EMPLEADO));
            if ($empleadoData) {
                $sets = array_map(fn (string $c) => "{$c} = :{$c}", array_keys($empleadoData));
                $empleadoData['id'] = $id;

                $stmt = $this->pdo->prepare('UPDATE empleado SET ' . implode(', ', $sets) . ' WHERE id_empleado = :id');
                $stmt->execute($empleadoData);
            }

            $stmt = $this->pdo->prepare('SELECT id_cargo FROM empleado WHERE id_empleado = :id');
            $stmt->execute(['id' => $id]);
            $idCargo = $stmt->fetchColumn();
            if ($idCargo !== false && $idCargo !== null) {
                $this->sincronizarRegistroDocente($id, (int) $idCargo);
            }

            if (!empty($data['contacto'])) {
                $contactoModel->upsert((int) $id_persona, $data['contacto']);
            }

            if (!empty($data['direccion'])) {
                $direccionModel->upsert((int) $id_persona, $data['direccion']);
            }

            /* La formación se actualiza fila a fila y no con "borro todo y reescribo".
 El modal de edición maneja una sola fila, pero un empleado puede tener
 varias: con el borrado completo, corregir el título de alguien borraba en
 silencio los demás. Ver actualizarFormacion().*/
            $this->actualizarFormacion($id, $data);

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function sincronizarRegistroDocente(int $idEmpleado, int $idCargo): void
    {
        $cargo = $this->pdo->prepare(
            "SELECT COUNT(*)
               FROM cargo
              WHERE id_cargo = :cargo
                AND nombre IN ('Docente de Aula', 'Docente de Educación Especial')"
        );
        $cargo->execute(['cargo' => $idCargo]);
        if ((int) $cargo->fetchColumn() === 0) {
            return;
        }

        $docente = $this->pdo->prepare('SELECT 1 FROM docente WHERE id_empleado = :empleado LIMIT 1');
        $docente->execute(['empleado' => $idEmpleado]);
        if ($docente->fetchColumn() !== false) {
            return;
        }

        $this->pdo->prepare('INSERT INTO docente (id_empleado) VALUES (:empleado)')
            ->execute(['empleado' => $idEmpleado]);
    }

    /** Formación académica con los nombres de título, grado e institución. */
    public function getFormacionAcademica(int $idEmpleado): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT fa.id_formacion_academica,
                    fa.fecha_obtencion,
                    t.nombre AS titulo,
                    ga.nombre AS grado_academico,
                    i.nombre AS institucion
               FROM formacion_academica fa
               INNER JOIN titulo t ON t.id_titulo = fa.id_titulo
               INNER JOIN grado_academico ga ON ga.id_grado_academico = fa.id_grado_academico
               INNER JOIN institucion i ON i.id_institucion = fa.id_institucion
              WHERE fa.id_empleado = :empleado
              ORDER BY fa.fecha_obtencion DESC'
        );
        $stmt->execute(['empleado' => $idEmpleado]);

        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($filas as &$fila) {
            $fila['id_formacion_academica'] = (int) $fila['id_formacion_academica'];
        }

        return $filas;
    }

    /** Asignaciones docentes del empleado, con la estructura y el período. */
    public function getAsignacionesDocente(int $idEmpleado): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT ad.id_asignacion,
                    ad.fecha_inicio,
                    ad.fecha_fin,
                    ta.nombre AS tipo_asignacion,
                    g.nombre AS grado,
                    s.nombre AS seccion,
                    t.nombre AS turno,
                    ae.nombre_periodo AS periodo,
                    (ad.fecha_fin IS NULL OR ad.fecha_fin >= CURDATE()) AS activa
               FROM docente d
               INNER JOIN asignacion_docente ad ON ad.id_docente = d.id_docente
               INNER JOIN estructura_academica ea ON ea.id_estructura_academica = ad.id_estructura_academica
               INNER JOIN grado g ON g.id_grado = ea.id_grado
               INNER JOIN seccion s ON s.id_seccion = ea.id_seccion
               INNER JOIN turno t ON t.id_turno = ea.id_turno
               INNER JOIN anio_escolar ae ON ae.id_anio_escolar = ea.id_anio_escolar
               INNER JOIN tipo_asignacion ta ON ta.id_tipo_asignacion = ad.id_tipo_asignacion
              WHERE d.id_empleado = :empleado
              ORDER BY ae.anio_inicio DESC, g.nombre, s.nombre, ad.fecha_inicio DESC"
        );
        $stmt->execute(['empleado' => $idEmpleado]);

        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($filas as &$fila) {
            $fila['id_asignacion'] = (int) $fila['id_asignacion'];
            $fila['activa']         = (int) $fila['activa'] === 1;
        }

        return $filas;
    }

    /** Historial de cambios de estado del empleado. */
    public function getHistorialEstados(int $idEmpleado): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT h.id_historial,
                    h.estado_anterior,
                    h.estado_nuevo,
                    h.motivo,
                    h.fecha_efectiva,
                    h.created_at,
                    h.id_usuario,
                    CONCAT_WS(' ', up.primer_nombre, up.primer_apellido) AS registrado_por
               FROM empleado_estado_historial h
               LEFT JOIN usuario u ON u.id_usuario = h.id_usuario
               LEFT JOIN persona up ON up.id_persona = u.id_persona
              WHERE h.id_empleado = :empleado
              ORDER BY h.fecha_efectiva DESC, h.id_historial DESC"
        );
        $stmt->execute(['empleado' => $idEmpleado]);

        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($filas as &$fila) {
            $fila['id_historial'] = (int) $fila['id_historial'];
            $fila['id_usuario']   = $fila['id_usuario'] !== null ? (int) $fila['id_usuario'] : null;
            $registradoPor = trim((string) ($fila['registrado_por'] ?? ''));
            $fila['registrado_por'] = $registradoPor !== '' ? $registradoPor : 'Usuario eliminado';
        }

        return $filas;
    }

    /**
     * Impacto de cambiar el estado, para la advertencia previa del modal.
     *
     * @return array{estado_actual:string,estado_nuevo:string,fecha_efectiva:string,asignaciones:array,reposo_vigente:?array,requiere_confirmacion:bool}
     */
    public function getImpactoCambioEstado(int $idEmpleado, string $nuevoEstado, string $fechaEfectiva): array
    {
        $actual = $this->getEstadoActual($idEmpleado);

        if ($actual === null) {
            throw new RuntimeException('El empleado no existe.');
        }

        $asignaciones = $this->getAsignacionesActivas($idEmpleado, $fechaEfectiva);
        $reposo = (new ReposoEmployeeModel($this->pdo))->getVigente($idEmpleado);

        return [
            'estado_actual'         => $actual,
            'estado_nuevo'          => $nuevoEstado,
            'fecha_efectiva'        => $fechaEfectiva,
            'asignaciones'          => $asignaciones,
            'reposo_vigente'        => $reposo,
            'requiere_confirmacion' => $nuevoEstado === 'INACTIVO'
                && ($asignaciones !== [] || $reposo !== null),
        ];
    }

    /**
     * Cambia el estado de la persona y deja rastro en el historial.
     *
     * Al desactivar, si se confirma, cierra las asignaciones docentes activas en
     * la fecha efectiva; no las borra.
     *
     * @return array{estado_anterior:string,estado_nuevo:string,asignaciones_cerradas:int,reposo_vigente:?array}
     */
    public function cambiarEstado(
        int $idEmpleado,
        string $nuevoEstado,
        string $motivo,
        string $fechaEfectiva,
        int $idUsuario,
        bool $confirmar
    ): array {
        $pdo = $this->pdo;
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'SELECT p.estado
                   FROM empleado e
                   INNER JOIN persona p ON p.id_persona = e.id_persona
                  WHERE e.id_empleado = :id
                  FOR UPDATE'
            );
            $stmt->execute(['id' => $idEmpleado]);
            $actual = $stmt->fetchColumn();

            if ($actual === false) {
                throw new RuntimeException('El empleado no existe.');
            }

            $actual = strtoupper((string) $actual);

            if ($actual === $nuevoEstado) {
                $pdo->rollBack();

                return [
                    'estado_anterior'      => $actual,
                    'estado_nuevo'         => $nuevoEstado,
                    'asignaciones_cerradas' => 0,
                    'reposo_vigente'       => (new ReposoEmployeeModel($pdo))->getVigente($idEmpleado),
                ];
            }

            $asignaciones = $this->getAsignacionesActivas($idEmpleado, $fechaEfectiva);
            $reposo = (new ReposoEmployeeModel($pdo))->getVigente($idEmpleado);

            if (!$confirmar && $nuevoEstado === 'INACTIVO' && ($asignaciones !== [] || $reposo !== null)) {
                $pdo->rollBack();

                return [
                    'estado_anterior'      => $actual,
                    'estado_nuevo'         => $nuevoEstado,
                    'asignaciones_cerradas' => 0,
                    'reposo_vigente'       => $reposo,
                    'requiere_confirmacion' => true,
                    'asignaciones'         => $asignaciones,
                ];
            }

            $cerradas = 0;

            if ($nuevoEstado === 'INACTIVO') {
                $cierre = $pdo->prepare(
                    'UPDATE asignacion_docente ad
                     INNER JOIN docente d ON d.id_docente = ad.id_docente
                        SET ad.fecha_fin = GREATEST(:fecha, ad.fecha_inicio)
                      WHERE d.id_empleado = :empleado
                        AND (ad.fecha_fin IS NULL OR ad.fecha_fin >= :fecha_limite)'
                );
                $cierre->execute([
                    'fecha'        => $fechaEfectiva,
                    'empleado'     => $idEmpleado,
                    'fecha_limite' => $fechaEfectiva,
                ]);
                $cerradas = $cierre->rowCount();
            }

            $pdo->prepare(
                'UPDATE persona p
                 INNER JOIN empleado e ON e.id_persona = p.id_persona
                    SET p.estado = :estado
                  WHERE e.id_empleado = :empleado'
            )->execute(['estado' => $nuevoEstado, 'empleado' => $idEmpleado]);

            $pdo->prepare(
                'INSERT INTO empleado_estado_historial
                    (id_empleado, estado_anterior, estado_nuevo, motivo, fecha_efectiva, id_usuario)
                 VALUES
                    (:empleado, :anterior, :nuevo, :motivo, :fecha, :usuario)'
            )->execute([
                'empleado' => $idEmpleado,
                'anterior' => $actual,
                'nuevo'    => $nuevoEstado,
                'motivo'   => $motivo,
                'fecha'    => $fechaEfectiva,
                'usuario'  => $idUsuario > 0 ? $idUsuario : null,
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }

        return [
            'estado_anterior'       => $actual,
            'estado_nuevo'          => $nuevoEstado,
            'asignaciones_cerradas' => $cerradas,
            'reposo_vigente'        => $reposo,
        ];
    }

    /** Estado actual de la persona asociada al empleado. */
    public function getEstadoActual(int $idEmpleado): ?string
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.estado
               FROM empleado e
               INNER JOIN persona p ON p.id_persona = e.id_persona
              WHERE e.id_empleado = :id
              LIMIT 1'
        );
        $stmt->execute(['id' => $idEmpleado]);
        $estado = $stmt->fetchColumn();

        return $estado === false ? null : strtoupper((string) $estado);
    }

    /** Asignaciones docentes que siguen activas en la fecha indicada. */
    public function getAsignacionesActivas(int $idEmpleado, string $fecha): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT ad.id_asignacion, ad.fecha_inicio, ad.fecha_fin,
                    ta.nombre AS tipo_asignacion,
                    g.nombre AS grado, s.nombre AS seccion, t.nombre AS turno,
                    ae.nombre_periodo AS periodo
               FROM docente d
               INNER JOIN asignacion_docente ad ON ad.id_docente = d.id_docente
               INNER JOIN estructura_academica ea ON ea.id_estructura_academica = ad.id_estructura_academica
               INNER JOIN grado g ON g.id_grado = ea.id_grado
               INNER JOIN seccion s ON s.id_seccion = ea.id_seccion
               INNER JOIN turno t ON t.id_turno = ea.id_turno
               INNER JOIN anio_escolar ae ON ae.id_anio_escolar = ea.id_anio_escolar
               INNER JOIN tipo_asignacion ta ON ta.id_tipo_asignacion = ad.id_tipo_asignacion
              WHERE d.id_empleado = :empleado
                AND (ad.fecha_fin IS NULL OR ad.fecha_fin >= :fecha)
              ORDER BY ae.anio_inicio DESC, g.nombre, s.nombre"
        );
        $stmt->execute(['empleado' => $idEmpleado, 'fecha' => $fecha]);

        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($filas as &$fila) {
            $fila['id_asignacion'] = (int) $fila['id_asignacion'];
        }

        return $filas;
    }

    public function toggleStatus(int $id, string $columna = 'estado'): bool
    {
        $sql = "UPDATE persona p
                INNER JOIN empleado e ON p.id_persona = e.id_persona
                SET p.estado = IF(p.estado = 'ACTIVO', 'INACTIVO', 'ACTIVO')
                WHERE e.id_empleado = :id";
        return $this->pdo->prepare($sql)->execute(['id' => $id]);
    }

    /**
 * Aplica lo que venga de la edición de la formación.
 *
 * Sustituye al "borro todo y reescribo" de la alta, y esa diferencia importa porque el
 * modal de edición solo maneja UNA fila: un empleado puede tener varias, y borrar todas
 * para reponer una perdía los demás títulos sin avisar.
 *
 * - Si viene eliminar_formacion, se borra la fila indicada (o todas, si no se indica
 * cuál: así un empleado con dos títulos se puede dejar sin formación completa).
 * - Si viene una fila completa, se actualiza la que se indica por su id o se inserta
 * como nueva.
 * - Si no viene nada de eso, no se toca la formación. Es el caso normal al editar otro
 * dato: que abrir y guardar el modal no borre información que la pantalla ni muestra.
*/
    private function actualizarFormacion(int $idEmpleado, array $data): void
    {
        $filas = $data['formacion'] ?? [];
        $idEnEdicion = (int) ($data['id_formacion_academica'] ?? 0);
        $eliminar = !empty($data['eliminar_formacion']);

        if ($eliminar) {
            if ($idEnEdicion > 0) {
                $this->pdo->prepare('DELETE FROM formacion_academica WHERE id_empleado = :emp AND id_formacion_academica = :id')
                     ->execute(['emp' => $idEmpleado, 'id' => $idEnEdicion]);
            } else {
                $this->pdo->prepare('DELETE FROM formacion_academica WHERE id_empleado = :emp')
                     ->execute(['emp' => $idEmpleado]);
            }

            return;
        }

        if ($filas === []) {
            return;
        }

        $fila = $filas[0];

        /* formacion_academica.fecha_obtencion es NOT NULL. validate() ya rechaza la fila sin
 fecha con un mensaje claro, pero si algún camino la escribiera igual, el INSERT
 reventaría con un error de MariaDB que el usuario vería como "ocurrió un problema".
 Aquí se corta antes y se avisa en el log: un dato que no se puede guardar se dice,
 no se traga.*/
        if (empty($fila['fecha_obtencion'])) {
            error_log(
                'EmployeeModel::actualizarFormacion(): no se guardó la formación del empleado '
                . $idEmpleado . ' porque fecha_obtencion venía vacía (columna NOT NULL).'
            );

            return;
        }

        if ($idEnEdicion > 0) {
            $this->pdo->prepare(
                'UPDATE formacion_academica
                    SET id_titulo = :titulo, id_grado_academico = :grado,
                        id_institucion = :inst, fecha_obtencion = :fecha
                  WHERE id_empleado = :emp AND id_formacion_academica = :id'
            )->execute([
                'titulo' => (int) $fila['id_titulo'],
                'grado'  => (int) $fila['id_grado_academico'],
                'inst'   => (int) $fila['id_institucion'],
                'fecha'  => $fila['fecha_obtencion'],
                'emp'    => $idEmpleado,
                'id'     => $idEnEdicion,
            ]);

            return;
        }

        // Fila nueva: se inserta sin pasar por reemplazarFormacion(), que borraría las de
        // más. El id no se toma del INSERT por si el UPDATE de arriba no tocó nada.
        $this->pdo->prepare(
            'INSERT INTO formacion_academica
                (id_empleado, id_titulo, id_grado_academico, id_institucion, fecha_obtencion)
             VALUES (:emp, :titulo, :grado, :inst, :fecha)'
        )->execute([
            'emp'    => $idEmpleado,
            'titulo' => (int) $fila['id_titulo'],
            'grado'  => (int) $fila['id_grado_academico'],
            'inst'   => (int) $fila['id_institucion'],
            'fecha'  => $fila['fecha_obtencion'],
        ]);
    }

    private function reemplazarFormacion(int $idEmpleado, array $formacion): void
    {
        $this->pdo->prepare('DELETE FROM formacion_academica WHERE id_empleado = :id')
             ->execute(['id' => $idEmpleado]);

        if ($formacion === []) {
            return;
        }

        $sql = 'INSERT INTO formacion_academica
                (id_empleado, id_titulo, id_grado_academico, id_institucion, fecha_obtencion)
                VALUES (:emp, :titulo, :grado, :inst, :fecha)';
        $stmt = $this->pdo->prepare($sql);

        foreach ($formacion as $item) {
            $stmt->execute([
                'emp'    => $idEmpleado,
                'titulo' => $item['id_titulo'],
                'grado'  => $item['id_grado_academico'],
                'inst'   => $item['id_institucion'],
                'fecha'  => $item['fecha_obtencion'],
            ]);
        }
    }
}
