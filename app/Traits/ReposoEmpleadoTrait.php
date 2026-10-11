<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\BitacoraModel;
use App\Models\EmpleadoModel;
use App\Models\ReposoEmpleadoModel;
use PDO;
use Throwable;

/**
 * ReposoEmpleadoTrait
 *
 * Las tres acciones del reposo (registrar, editar y listar) viven aquí y no en
 * EmpleadoController por dos motivos. El primero es que el controlador ya tiene 620 líneas
 * y tres responsabilidades que no tienen nada que ver entre sí; el segundo es que este trait
 * se puede probar solo, sin levantar el controlador entero ni su sesión.
 *
 * Por qué un trait y no un servicio inyectado: es el mismo criterio que usa
 * InstitucionTrait y UbicacionTrait, los otros dos traits que comparten lógica entre
 * controladores. Todos los controladores extienden BaseController y resuelven sus
 * dependencias por `$this`, no por constructor; un trait reutiliza ese mecanismo sin
 * introducir un patrón distinto solo para este caso.
 *
 * LO QUE ESTÁ DECIDIDO Y POR QUÉ (las respuestas de la directora):
 *
 * - Solo `directivo` registra reposos. Eso NO se decide aquí sino en el whitelist de rutas de
 *   public/index.php, igual que el resto del módulo. Este trait asume que ya lo comprobó.
 * - "En reposo" solo avisa: no bloquea asignaciones de clases. Por eso este trait no toca
 *   TeacherAssignmentController ni pregunta por `docente`: un docente en reposo es un
 *   empleado en reposo, y el aviso se pinta en el listado del empleado.
 * - El motivo es texto libre, corto, y se ve solo en el detalle. No va a la bitácora, porque
 *   puede decir cosas de salud y la bitácora se exporta.
 * - No se borran reposos: se editan, o se cierran escribiendo fecha_fin. El historial es el
 *   motivo de que la tabla exista.
 *
 * Contrato con la clase que lo usa (ver los `abstract` al final): debe extender
 * BaseController, directa o indirectamente, para heredar conexion(), jsonResponse(),
 * jsonError(), idUsuarioActual() y fechaValida(). No hace falta declarar nada más.
 *
 * @author Logística
 * @package App\Traits
 */
trait ReposoEmpleadoTrait
{
    /**
     * Registra un reposo.
     * Ruta: empleado/registrarReposo (POST, JSON).
     *
     * El flujo tiene tres pasos y el orden importa:
     *
     *  1. Validar el rango y el solapamiento. Se acumulan todos los errores y se
     *     devuelven juntos, igual que hace guardarTitulo(), para que quien lo registra no
     *     descubra los problemas de a uno.
     *
     *  2. Bloquear al empleado con SELECT ... FOR UPDATE y recién entonces insertar.
     *
     *  3. Registrar en bitácora, DENTRO de la misma transacción.
     *
     * Por qué el bloqueo va antes del INSERT y no después: el modelo corre en
     * REPEATABLE READ, así que un SELECT COUNT previo fijaría la vista de lectura antes del
     * bloqueo y la segunda persona vería el estado viejo. Bloquear primero y comprobar
     * después es lo que hace RatificacionModel::reservarCupo(), y está explicado allí con el
     * mismo detalle. Con el bloqueo en su sitio, dos personas que guarden a la vez sobre el
     * mismo empleado se serializan y la segunda ve el reposo de la primera.
     *
     * MySQL no tiene restricciones de exclusión, así que el solapamiento NO se puede
     * garantizar en la base: se garantiza aquí y el CHECK de la tabla solo cubre el rango
     * (fecha_fin >= fecha_inicio), que es otra cosa.
     *
     * @return void
     */
    private function registrarReposoDeEmpleado(): void
    {
        $datos = $this->reposoDesdePost(null);

        $errores = $this->validarReposo($datos);

        if ($errores !== []) {
            $this->jsonResponse(false, $errores, 'Revisa los datos del reposo.', 422);
        }

        $this->jsonResponse(...$this->guardarReposoEnTransaccion($datos));
    }

    /**
     * Edita un reposo ya registrado, o lo cierra escribiendo solo la fecha de fin.
     * Ruta: empleado/updateReposo (POST, JSON).
     *
     * Cerrar y editar son la misma acción con distinto alcance: el botón rápido de "cerrar
     * reposo" manda las mismas tres fechas de las que ya tenía, y por eso pasa por aquí en
     * lugar de tener un endpoint aparte. Así hay un solo camino que valida y uno solo que
     * escribe en la bitácora.
     *
     * La diferencia con el alta está en el solapamiento: al editar se excluye el propio
     * reposo de la comparación (el $ignorarId de haySolapamiento()), porque si no, cambiar
     * cualquier fecha haría que el registro se solapara consigo mismo y la edición sería
     * imposible.
     *
     * @return void
     */
    private function updateReposoDeEmpleado(): void
    {
        $idReposo = (int) ($_POST['id_reposo'] ?? 0);

        if ($idReposo <= 0) {
            $this->jsonResponse(false, null, 'No se indica qué reposo se está editando.', 400);
        }

        $anterior = $this->reposoDe($idReposo);

        if ($anterior === null) {
            $this->jsonResponse(false, null, 'Ese reposo no está registrado.', 404);
        }

        /* El id_empleado no se toma del POST: sale del registro que se está editando. Si
           viniera del formulario, un id manipulado movería el reposo a otro empleado. */
        $datos = $this->reposoDesdePost($idReposo);
        $datos['id_empleado'] = $anterior['id_empleado'];

        $errores = $this->validarReposo($datos);

        if ($errores !== []) {
            $this->jsonResponse(false, $errores, 'Revisa los datos del reposo.', 422);
        }

        $this->jsonResponse(...$this->actualizarReposoEnTransaccion($idReposo, $datos));
    }

    /**
     * Reposos de un empleado, para el historial del modal.
     * Ruta: empleado/getReposos?id_empleado= (GET, JSON).
     *
     * Es un GET porque solo lee. Devuelve el historial completo y, aparte, cuál está vigente
     * hoy, para que el modal tenga que resolverlo una sola vez en el servidor y no otra vez en el JS.
     *
     * @return void
     */
    private function getRepososDeEmpleado(): void
    {
        $idEmpleado = (int) ($_GET['id_empleado'] ?? 0);

        if ($idEmpleado <= 0) {
            $this->jsonResponse(false, null, 'Empleado no válido.', 400);
        }

        try {
            $model   = $this->reposo();
            $reposos = $model->getPorEmpleado($idEmpleado);

            /* El vigente se extrae de la lista que ya se trajo, sin consultar la tabla una segunda vez. */
            $vigente = null;
            foreach ($reposos as $r) {
                if (!empty($r['vigente'])) {
                    $vigente = $r;
                    break;
                }
            }
        } catch (Throwable $e) {
            $this->jsonError($e, 'Empleados');
        }

        $this->jsonResponse(true, [
            'reposos' => $reposos,
            'vigente' => $vigente,
        ]);
    }

    /**
     * Guarda el reposo y devuelve QUÉ PASÓ, sin responderle nada a quien lo pidió.
     *
     * @return array{0: bool, 1: mixed, 2: string, 3: int} Los cuatro argumentos de
     *         jsonResponse(): ok, data, mensaje y código.
     */
    private function guardarReposoEnTransaccion(array $datos): array
    {
        $pdo = $this->conexion();
        $pdo->beginTransaction();

        try {
            /* El bloqueo va sobre el empleado, no sobre reposo_empleado: es el empleado la
             fila que las dos personas compiten por. Además, validamos que el empleado esté
             ACTIVO para poder registrar un nuevo reposo (no aplica para editar/cerrar). */
            $bloqueo = $pdo->prepare(
                'SELECT e.id_empleado, p.estado
                   FROM empleado e
                  INNER JOIN persona p ON p.id_persona = e.id_persona
                  WHERE e.id_empleado = :id
                  FOR UPDATE'
            );
            $bloqueo->execute(['id' => $datos['id_empleado']]);
            $empleado = $bloqueo->fetch(PDO::FETCH_ASSOC);

            if ($empleado === false) {
                $pdo->rollBack();

                return [false, null, 'El empleado no existe.', 404];
            }

            if (strtoupper((string) ($empleado['estado'] ?? '')) !== 'ACTIVO') {
                $pdo->rollBack();

                return [false, null, 'Solo se pueden registrar reposos para empleados activos.', 422];
            }

            /* El solapamiento se comprueba DESPUÉS del bloqueo. Antes habría sido una
             comprobación sin garantía: entre la comprobación y el INSERT, la otra
             persona pudo escribir. */
            $model = new ReposoEmpleadoModel($pdo);
            $solape = $model->haySolapamiento(
                $datos['id_empleado'],
                $datos['fecha_inicio'],
                $datos['fecha_fin']
            );

            if ($solape !== null) {
                $pdo->rollBack();

                return [
                    false,
                    ['fecha_inicio' => 'Este empleado ya tiene un reposo que se superpone con esas fechas.'],
                    'El reposo se superpone con otro ya registrado.',
                    409,
                ];
            }

            $idReposo = $model->crearDentroDeTransaccion($datos);

            $this->registrarEnBitacora(
                'REPOSO ' . $this->nombreDeEmpleado($datos['id_empleado']) . ' · desde ' . $datos['fecha_inicio'],
                'Reposos'
            );

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->jsonError($e, 'Empleados');
        }

        return [true, ['id_reposo' => $idReposo], 'Reposo registrado correctamente.', 200];
    }

    /**
     * Edita el reposo dentro de la transacción y devuelve qué pasó.
     *
     * El id del reposo va aparte de $datos porque es el que se está editando, no un campo
     * más del formulario.
     *
     * @return array{0: bool, 1: mixed, 2: string, 3: int}
     */
    private function actualizarReposoEnTransaccion(int $idReposo, array $datos): array
    {
        $pdo = $this->conexion();
        $pdo->beginTransaction();

        try {
            $bloqueo = $pdo->prepare('SELECT id_empleado FROM empleado WHERE id_empleado = :id FOR UPDATE');
            $bloqueo->execute(['id' => $datos['id_empleado']]);

            $model = new ReposoEmpleadoModel($pdo);
            $solape = $model->haySolapamiento(
                $datos['id_empleado'],
                $datos['fecha_inicio'],
                $datos['fecha_fin'],
                $idReposo
            );

            if ($solape !== null) {
                $pdo->rollBack();

                return [
                    false,
                    ['fecha_inicio' => 'Este empleado ya tiene un reposo que se superpone con esas fechas.'],
                    'El reposo se superpone con otro ya registrado.',
                    409,
                ];
            }

            $model->update($idReposo, $datos);

            $this->registrarEnBitacora(
                'EDICIÓN REPOSO ' . $this->nombreDeEmpleado($datos['id_empleado'])
                . ' · ' . $datos['fecha_inicio'] . ($datos['fecha_fin'] === '' ? ' (abierto)' : ' a ' . $datos['fecha_fin']),
                'Reposos'
            );

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->jsonError($e, 'Empleados');
        }

        return [true, ['id_reposo' => $idReposo], 'Reposo actualizado correctamente.', 200];
    }

    /**
     * Normaliza el POST de un reposo.
     *
     * El motivo pasa por capitalizarOracion() para que quede "Licencia médica" y no
     * "licencia médica", igual que el resto de los textos de persona del sistema.
     *
     * @param int|null $idReposo El reposo que se edita, o null en el alta.
     * @return array{id_reposo:?int,id_empleado:int,fecha_inicio:string,fecha_fin:string,motivo:string}
     */
    private function reposoDesdePost(?int $idReposo): array
    {
        $motivo = trim((string) ($_POST['motivo'] ?? ''));

        return [
            'id_reposo'    => $idReposo,
            'id_empleado'  => (int) ($_POST['id_empleado'] ?? 0),
            'fecha_inicio' => trim((string) ($_POST['fecha_inicio'] ?? '')),
            /* Se guarda como cadena vacía y no como null: haySolapamiento() la traduce a
             9999-12-31, que es el "hasta el infinito" del caso abierto. Así el código que
             valida trabaja siempre con dos fechas y no con un null por medio. */
            'fecha_fin'    => trim((string) ($_POST['fecha_fin'] ?? '')),
            'motivo'       => $motivo === '' ? '' : $this->capitalizarOracion($motivo),
        ];
    }

    /**
     * Reglas de un reposo. Acumula todos los errores para devolverlos juntos.
     *
     * El rango (fecha_fin >= fecha_inicio) también lo revisa el CHECK de la tabla; se repite
     * aquí porque el mensaje del servidor es el que quien lo registra entiende, y porque un
     * INSERT puede venir de un script que no pase por este controlador.
     *
     * El solapamiento NO se comprueba acá: necesita la transacción con el bloqueo del
     * empleado, y por eso vive en registrarReposo()/updateReposo(). Acá solo se avisa que no
     * se solape, que es información para cuando se muestre el formulario.
     *
     * @return array<string,string> Campo => mensaje. Vacío si todo está bien.
     */
    private function validarReposo(array $datos): array
    {
        $errores = [];

        if ($datos['id_empleado'] <= 0) {
            $errores['id_empleado'] = 'No se indica de qué empleado es el reposo.';
        }

        if ($datos['fecha_inicio'] === '') {
            $errores['fecha_inicio'] = 'Indica la fecha de inicio del reposo.';
        } elseif (!$this->fechaValida($datos['fecha_inicio'])) {
            $errores['fecha_inicio'] = 'La fecha de inicio no es válida.';
        }

        if ($datos['fecha_fin'] !== '') {
            if (!$this->fechaValida($datos['fecha_fin'])) {
                $errores['fecha_fin'] = 'La fecha de fin no es válida.';
            } elseif ($datos['fecha_inicio'] !== '' && $this->fechaValida($datos['fecha_inicio'])
                      && $datos['fecha_fin'] < $datos['fecha_inicio']) {
                $errores['fecha_fin'] = 'La fecha de fin no puede ser anterior a la de inicio.';
            }
        }

        /* El motivo es opcional, pero si viene no puede ser un texto infinito: el campo es
         VARCHAR(200) y el modo es STRICT_TRANS_TABLES, así que más largo revienta el
         INSERT con un error que no dice nada útil. */
        if (mb_strlen($datos['motivo'], 'UTF-8') > 200) {
            $errores['motivo'] = 'El motivo no puede pasar de 200 caracteres.';
        }

        return $errores;
    }

    /**
     * Modelo de reposos con su tipo real, para que el editor resuelva sus métodos.
     */
    private function reposo(): ReposoEmpleadoModel
    {
        return new ReposoEmpleadoModel($this->conexion());
    }

    /** Un reposo por id, o null si no existe. */
    private function reposoDe(int $idReposo): ?array
    {
        $stmt = $this->conexion()->prepare(
            'SELECT id_reposo, id_empleado, fecha_inicio, fecha_fin, motivo
               FROM reposo_empleado
              WHERE id_reposo = :id
              LIMIT 1'
        );
        $stmt->execute(['id' => $idReposo]);
        $fila = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($fila === false) {
            return null;
        }

        $fila['id_reposo']   = (int) $fila['id_reposo'];
        $fila['id_empleado'] = (int) $fila['id_empleado'];

        return $fila;
    }

    /**
     * Nombre del empleado, solo para el texto de la bitácora.
     *
     * Se arma con el mismo CONCAT_WS que usa EmpleadoModel::getAll() para que el mismo
     * empleado se lea igual en el listado, en la pantalla y en la bitácora. Y es solo el
     * nombre: el motivo del reposo no entra ahí, porque la bitácora se exporta y puede
     * decir cosas de salud.
     */
    private function nombreDeEmpleado(int $idEmpleado): string
    {
        $stmt = $this->conexion()->prepare(
            'SELECT CONCAT_WS(\' \', p.primer_nombre, p.primer_apellido) AS nombre
               FROM empleado e
               INNER JOIN persona p ON p.id_persona = e.id_persona
              WHERE e.id_empleado = :id
              LIMIT 1'
        );
        $stmt->execute(['id' => $idEmpleado]);
        $nombre = $stmt->fetchColumn();

        return $nombre === false ? 'empleado #' . $idEmpleado : (string) $nombre;
    }

    /**
     * Anota la acción en la bitácora.
     *
     * Va DENTRO de la transacción del registro, como la bitácora de ratificaciones: si el
     * commit falla, la anotación tampoco queda, porque de lo contrario el expediente diría
     * que alguien registró un reposo que en realidad no se registró.
     *
     * El id del usuario sale de la sesión. BitacoraModel::registrar() recorta a 100 y 50
     * caracteres y atrapa su propio error, así que un texto largo no rompe la operación.
     */
    private function registrarEnBitacora(string $accion, string $modulo): void
    {
        $idUsuario = (int) ($this->idUsuarioActual() ?? 0);

        if ($idUsuario <= 0) {
            return;
        }

        (new BitacoraModel($this->conexion()))->registrar($idUsuario, $accion, $modulo);
    }


    // ---------- Contrato con BaseController ----------
    // Sin cuerpo a propósito: declaran la firma exacta que este trait necesita de la clase
    // que lo use, para que el editor resuelva las llamadas.

    abstract protected function conexion(): PDO;

    abstract protected function jsonResponse(bool $ok, mixed $data = null, ?string $message = null, int $code = 200): never;

    abstract protected function jsonError(Throwable $e, string $seccion, int $code = 500): never;

    abstract protected function idUsuarioActual(): ?int;
}