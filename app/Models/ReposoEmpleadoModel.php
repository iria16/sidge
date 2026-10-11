<?php

declare(strict_types=1);

namespace App\Models;

use App\Interfaces\Creatable;
use App\Interfaces\Updatable;
use PDO;

/**
 * Reposos del personal: empleados y docentes.
 *
 * Es su propio modelo y NO un método más de EmpleadoModel por dos razones. La primera es que
 * EmpleadoModel ya tiene 621 líneas y sus create()/update() son estrictos: escriben persona,
 * empleado, contacto, dirección, formación y sincronizan la fila de docente, todo en una
 * transacción. Un reposo no participa de ninguna de esas cosas y meterlo ahí obligaría a
 * tocar el alta de empleados, que es justo lo que no hay que romper. La segunda es que este
 * modelo tiene un dueño distinto: responde preguntas que ningún otro se hace ("¿quién está de
 * reposo?", "¿cuándo vuelve?"), y un listado con filtros propios.
 *
 * DOCENTES: no hay nada especial que hacer. `docente` es una tabla hija 1:1 de `empleado`
 * (id_docente, id_empleado) que EmpleadoModel crea según el cargo, así que todo docente ya
 * tiene id_empleado y queda cubierto por la misma llave foránea. Si algún día este modelo
 * preguntara "¿es docente?", sería una JOIN a `docente`, pero para registrar un reposo no
 * hace falta: la pregunta es del que LISTA, no del que guarda.
 *
 * "EN REPOSO" NO SE GUARDA, SE CALCULA.
 *
 * Es la decisión de diseño de toda la tabla. Un reposo es un evento con inicio y fin, así que
 * su estado es una pregunta sobre HOY, no un atributo que alguien mantenga a mano: el mismo
 * empleado puede tener tres reposos en un año y ninguno al siguiente; un reposo con fecha
 * de fin pasada deja de estar vigente automáticamente al cumplirse la fecha, mientras que
 * un reposo sin fecha de fin (abierto) queda vigente de forma continua hasta que la dirección
 * lo cierre registrando su fecha de culminación.
 *
 * Por eso la única fuente de verdad son las fechas y TODAS las consultas de estado pasan por
 * `EXPRESION_EN_REPOSO`. Si algún día se añadiera una columna `en_reposo`, quedaría
 * desincronizada de las fechas en cuanto nadie la actualizara.
 */
class ReposoEmpleadoModel extends BaseModel implements Creatable, Updatable
{
    protected string $table      = 'reposo_empleado';
    protected string $primaryKey = 'id_reposo';

    /**
     * Subconsulta escalar que dice si un empleado está en reposo HOY.
     *
     * Es una expresión, no un método, porque va pegada dentro de los SELECT de este modelo y
     * de EmpleadoModel::getAll(), donde una función de PHP no se puede usar en el WHERE de
     * forma directa. Si se cambia la definición de "en reposo", se cambia AQUÍ y en ningún
     * otro lado.
     *
     * La condición es `fecha_inicio <= hoy AND (fecha_fin IS NULL OR fecha_fin >= hoy)`:
     *
     * - El `IS NULL` es el caso del reposo abierto, que se cuenta como vigente para siempre
     *   hasta que alguien le ponga fecha de fin. Sin ese OR, un reposo abierto saldría "no".
     * - Las dos comparaciones son `<=`, no `<`: un reposo que termina HOY todavía termina hoy.
     *   Con `<` el mismo reposo se vería como vencido desde la medianoche, y quien lo registró
     *   vería en pantalla algo distinto de lo que acaba de escribir.
     * - Es CURDATE() y no una fecha de PHP, porque la decisión la toma el servidor. Si la
     *   tomara el navegador, dos máquinas con distinto reloj dirían cosas distintas.
     */
    public const EXPRESION_EN_REPOSO =
        'EXISTS (SELECT 1 FROM reposo_empleado r
                  WHERE r.id_empleado = e.id_empleado
                    AND r.fecha_inicio <= CURDATE()
                    AND (r.fecha_fin IS NULL OR r.fecha_fin >= CURDATE()))';

    /**
     * Alta de un reposo.
     *
     * @param array $data Se espera 'id_empleado' (int), 'fecha_inicio' (string ISO),
     *                    'fecha_fin' (string ISO o vacío) y 'motivo' (string o vacío).
     *                    Las fechas y el solapamiento los valida el controlador ANTES de
     *                    llegar acá; la base solo hace de segunda barrera con su CHECK.
     */
    public function create(array $data): bool
    {
        $this->pdo->prepare(
            'INSERT INTO reposo_empleado (id_empleado, fecha_inicio, fecha_fin, motivo)
             VALUES (:empleado, :inicio, :fin, :motivo)'
        )->execute([
            'empleado' => (int) ($data['id_empleado'] ?? 0),
            'inicio'   => (string) ($data['fecha_inicio'] ?? ''),
            'fin'      => ($data['fecha_fin'] ?? '') !== '' ? (string) $data['fecha_fin'] : null,
            'motivo'   => ($data['motivo'] ?? '') !== '' ? (string) $data['motivo'] : null,
        ]);

        return true;
    }

    /**
     * Edición de un reposo ya registrado.
     *
     * @param array $data Mismo contrato que create(), más 'id_reposo' para decir cuál.
     */
    public function update(int $id, array $data): bool
    {
        return $this->pdo->prepare(
            'UPDATE reposo_empleado
                SET fecha_inicio = :inicio,
                    fecha_fin    = :fin,
                    motivo       = :motivo
              WHERE id_reposo = :id'
        )->execute([
            'id'     => $id,
            'inicio' => (string) ($data['fecha_inicio'] ?? ''),
            'fin'    => ($data['fecha_fin'] ?? '') !== '' ? (string) $data['fecha_fin'] : null,
            'motivo' => ($data['motivo'] ?? '') !== '' ? (string) $data['motivo'] : null,
        ]);
    }

    /**
     * Reposos de un empleado, del más reciente al más antiguo.
     *
     * Trae TODOS, no solo el vigente: el historial es parte de lo que la dirección consulta
     * ("¿cuándo estuvo de reposo el año pasado?"). El vigente se distingue con la columna
     * `vigente`, que no está en la tabla: se calcula en el SELECT.
     */
    public function getPorEmpleado(int $idEmpleado): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.id_reposo,
                    r.id_empleado,
                    r.fecha_inicio,
                    r.fecha_fin,
                    r.motivo,
                    (r.fecha_inicio <= CURDATE() AND (r.fecha_fin IS NULL OR r.fecha_fin >= CURDATE())) AS vigente
               FROM reposo_empleado r
              WHERE r.id_empleado = :empleado
              ORDER BY r.fecha_inicio DESC, r.id_reposo DESC'
        );
        $stmt->execute(['empleado' => $idEmpleado]);

        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($filas as &$fila) {
            $fila['id_reposo'] = (int) $fila['id_reposo'];
            $fila['vigente']    = (int) $fila['vigente'] === 1;
            $fila['motivo']     = $fila['motivo'] ?? '';
        }

        return $filas;
    }

    /**
     * El reposo que está vigente hoy para este empleado, o null si está trabajando.
     *
     * Devuelve UNO, no una lista: si dos reposos vigentes se solaparan, la base no lo impide
     * (no tiene restricciones de exclusión) y el controlador tampoco siempre alcanza, así que
     * esta consulta ordena por fecha_inicio DESC y toma el primero. Es el que se muestra en el
     * detalle, y el orden garantiza que sea el más reciente en vez de un caso arbitrario.
     */
    public function getVigente(int $idEmpleado): ?array
    {
        $reposos = $this->getPorEmpleado($idEmpleado);

        foreach ($reposos as $reposo) {
            if ($reposo['vigente']) {
                return $reposo;
            }
        }

        return null;
    }

    /**
     * ¿Hay un reposo solapado con el rango dado?
     *
     * `$ignorarId` es lo que permite EDITAR un reposo sin que el modelo lo detecte como
     * solapado consigo mismo: al comparar, la fila que se está editando tiene que quedar
     * fuera, o cualquier edición cambiaría una fecha y se rechazaría contra su propio
     * registro. Por eso el `r.id_reposo != :ignorar` va en la propia consulta y no como un
     * filtro de PHP.
     *
* El solapamiento se define como intersección de intervalos cerrados:
     *
     *     nuevo.inicio <= viejo.fin  Y  viejo.inicio <= nuevo.fin
     *
     * Con `fecha_fin` NULL (abierto hasta el infinito) el primer lado siempre es cierto, así
     * que cualquier fecha de inicio futura solaparía — que es lo correcto: no se puede abrir
     * un reposo nuevo mientras el anterior siga abierto. Y un `nuevo.fin` NULL hace que el
     * segundo lado también sea cierto.
     *
     * Se comparan TODOS los reposos del empleado, no solo los vigentes. La alternativa
     * (ignorar los ya terminados) dejaría pasar dos reposos históricos pisen las mismas
     * fechas, que es justo el duplicado que se quiere evitar. El costo es que no se pueden
     * cargar a posteriori dos reposos que se pisen en el pasado; si eso hiciera falta, el
     * filtro va en el WHERE de esta consulta, no en el controlador.
     *
     * @param string   $fechaInicio ISO YYYY-MM-DD
     * @param string   $fechaFin    ISO YYYY-MM-DD, o '' si el reposo queda abierto
     * @param int|null $ignorarId   Reposo que se está editando; null en el alta.
     * @return array|null La fila que se solapa, para poder decir cuál reposo choca.
     */
    public function haySolapamiento(int $idEmpleado, string $fechaInicio, string $fechaFin, ?int $ignorarId = null): ?array
    {
        $fin = $fechaFin !== '' ? $fechaFin : '9999-12-31';

        $sql = 'SELECT r.id_reposo, r.fecha_inicio, r.fecha_fin
                  FROM reposo_empleado r
                 WHERE r.id_empleado = :empleado
                   AND :inicio <= COALESCE(r.fecha_fin, :infinito)
                   AND r.fecha_inicio <= :fin';

        $params = [
            'empleado' => $idEmpleado,
            'inicio'   => $fechaInicio,
            'fin'      => $fin,
            'infinito' => '9999-12-31',
        ];

        if ($ignorarId !== null) {
            $sql .= ' AND r.id_reposo != :ignorar';
            $params['ignorar'] = $ignorarId;
        }

        $sql .= ' ORDER BY r.fecha_inicio DESC LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }

    /**
     * Registra el reposo dentro de una transacción que el controlador ya abrió.
     *
     * No es un método público de la API: existe para que el CONTROLADOR abra la transacción,
     * bloquee al empleado con SELECT ... FOR UPDATE y recién entonces inserte. Ver
     * ReposoEmpleadoTrait::guardarReposoEnTransaccion() para por qué el bloqueo va antes
     * del INSERT. Un INSERT suelto desde la interfaz perdería esa protección contra dos
     * personas que guarden a la vez.
     *
     * @return int El id_reposo generado.
     */
    public function crearDentroDeTransaccion(array $data): int
    {
        $this->create($data);

        return (int) $this->pdo->lastInsertId();
    }
}