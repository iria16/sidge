<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\AuditLogModel;
use App\Models\EmployeeModel;
use App\Models\ReposoEmployeeModel;
use PDO;
use Throwable;

/**
 * ReposoEmpleadoTrait
 *
 * Las tres acciones del reposo (registrar, editar y listar) viven aquí y no en
 * EmployeeController por dos motivos. El primero es que el controlador ya tiene 620 líneas
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
     * después es lo que hace RatificationModel::reservarCupo(), y está explicado allí con el
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

        try {
            $resultado = $this->reposo()->registrarConBloqueo($datos, $this->idUsuarioActual());
        } catch (Throwable $e) {
            $this->jsonError($e, 'Empleados');
        }

        if (!$resultado['ok']) {
            if ($resultado['motivo'] === 'no_existe') {
                $this->jsonResponse(false, null, 'El empleado no existe.', 404);
            }

            if ($resultado['motivo'] === 'inactivo') {
                $this->jsonResponse(false, null, 'Solo se pueden registrar reposos para empleados activos.', 422);
            }

            $this->jsonResponse(
                false,
                ['fecha_inicio' => 'Este empleado ya tiene un reposo que se superpone con esas fechas.'],
                'El reposo se superpone con otro ya registrado.',
                409
            );
        }

        $this->jsonResponse(true, ['id_reposo' => $resultado['id_reposo']], 'Reposo registrado correctamente.', 200);
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

        $anterior = $this->reposo()->getPorId($idReposo);

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

        try {
            $resultado = $this->reposo()->actualizarConBloqueo($idReposo, $datos, $this->idUsuarioActual());
        } catch (Throwable $e) {
            $this->jsonError($e, 'Empleados');
        }

        if (!$resultado['ok']) {
            $this->jsonResponse(
                false,
                ['fecha_inicio' => 'Este empleado ya tiene un reposo que se superpone con esas fechas.'],
                'El reposo se superpone con otro ya registrado.',
                409
            );
        }

        $this->jsonResponse(true, ['id_reposo' => $idReposo], 'Reposo actualizado correctamente.', 200);
    }

    /**
     * Cierra anticipadamente un reposo médico.
     * Ruta: empleado/cerrarReposo (POST, JSON).
     */
    private function cerrarReposoDeEmpleado(): void
    {
        $idReposo    = (int) ($_POST['id_reposo'] ?? 0);
        $fechaFin    = trim((string) ($_POST['fecha_fin'] ?? ''));
        $observacion = trim((string) ($_POST['observacion'] ?? ''));

        if ($idReposo <= 0) {
            $this->jsonResponse(false, null, 'Reposo no válido.', 400);
        }

        if ($fechaFin === '' || !$this->fechaValida($fechaFin)) {
            $this->jsonResponse(false, ['fecha_fin' => 'Indica una fecha de cierre válida.'], 'Fecha de cierre no válida.', 422);
        }

        $anterior = $this->reposo()->getPorId($idReposo);
        if ($anterior === null) {
            $this->jsonResponse(false, null, 'Ese reposo no está registrado.', 404);
        }

        if ($fechaFin < $anterior['fecha_inicio']) {
            $this->jsonResponse(false, ['fecha_fin' => 'La fecha de fin no puede ser anterior a la de inicio.'], 'Fecha no válida.', 422);
        }

        try {
            $model = $this->reposo();
            $ok = $model->cerrar($idReposo, $fechaFin, $observacion);
            if (!$ok) {
                $this->jsonResponse(false, null, 'No se pudo cerrar el reposo. Verifique que esté vigente.', 400);
            }
            $this->registrarEnBitacora(
                'CIERRE REPOSO ' . $this->nombreDeEmpleado($anterior['id_empleado']) . ' · culminación ' . $fechaFin,
                'Reposos'
            );
        } catch (Throwable $e) {
            $this->jsonError($e, 'Empleados');
        }

        $this->jsonResponse(true, null, 'Reposo cerrado correctamente.', 200);
    }

    /**
     * Anula un reposo médico registrado por error.
     * Ruta: empleado/anularReposo (POST, JSON).
     */
    private function anularReposoDeEmpleado(): void
    {
        $idReposo    = (int) ($_POST['id_reposo'] ?? 0);
        $observacion = trim((string) ($_POST['observacion'] ?? ''));

        if ($idReposo <= 0) {
            $this->jsonResponse(false, null, 'Reposo no válido.', 400);
        }

        $anterior = $this->reposo()->getPorId($idReposo);
        if ($anterior === null) {
            $this->jsonResponse(false, null, 'Ese reposo no está registrado.', 404);
        }

        try {
            $model = $this->reposo();
            $ok = $model->anular($idReposo, $observacion);
            if (!$ok) {
                $this->jsonResponse(false, null, 'No se pudo anular el reposo.', 400);
            }
            $this->registrarEnBitacora(
                'ANULACIÓN REPOSO ' . $this->nombreDeEmpleado($anterior['id_empleado']),
                'Reposos'
            );
        } catch (Throwable $e) {
            $this->jsonError($e, 'Empleados');
        }

        $this->jsonResponse(true, null, 'Reposo anulado correctamente.', 200);
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
    private function reposo(): ReposoEmployeeModel
    {
        return new ReposoEmployeeModel($this->conexion());
    }

    /**
     * Nombre del empleado, solo para el texto de la bitácora.
     *
     * Lo resuelve EmployeeModel::getNombreCorto() con el mismo CONCAT_WS del
     * listado, para que el mismo empleado se lea igual en la pantalla y en la
     * auditoría. El motivo del reposo no entra aquí: la bitácora se exporta y
     * puede decir cosas de salud.
     */
    private function nombreDeEmpleado(int $idEmpleado): string
    {
        return (new EmployeeModel($this->conexion()))->getNombreCorto($idEmpleado)
            ?? ('empleado #' . $idEmpleado);
    }

    /**
     * Anota la acción en la bitácora.
     *
     * Va DENTRO de la transacción del registro, como la bitácora de ratificaciones: si el
     * commit falla, la anotación tampoco queda, porque de lo contrario el expediente diría
     * que alguien registró un reposo que en realidad no se registró.
     *
     * El id del usuario sale de la sesión. AuditLogModel::registrar() recorta a 100 y 50
     * caracteres y atrapa su propio error, así que un texto largo no rompe la operación.
     */
    private function registrarEnBitacora(string $accion, string $modulo): void
    {
        $idUsuario = (int) ($this->idUsuarioActual() ?? 0);

        if ($idUsuario <= 0) {
            return;
        }

        (new AuditLogModel($this->conexion()))->registrar($idUsuario, $accion, $modulo);
    }


    // ---------- Contrato con BaseController ----------
    // Sin cuerpo a propósito: declaran la firma exacta que este trait necesita de la clase
    // que lo use, para que el editor resuelva las llamadas.

    abstract protected function conexion(): PDO;

    abstract protected function jsonResponse(bool $ok, mixed $data = null, ?string $message = null, int $code = 200): never;

    abstract protected function jsonError(Throwable $e, string $seccion, int $code = 500): never;

    abstract protected function idUsuarioActual(): ?int;

    abstract protected function fechaValida(string $fecha): bool;
}