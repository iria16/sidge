<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AcademicYearModel;
use App\Models\WithdrawalModel;
use App\Support\Validator;
use App\Traits\TextoTrait;
use PDO;

use function App\Traits\sigde_rol_clave;

/**
 * Retiros (gestion_estudiante/retiro): la secretaria los registra (store) y el directivo los resuelve (updateEstado).
 * Es un proceso de matrícula, igual que inscripción, ratificación y egreso; las notificaciones solo lo avisan.
 * Endpoints: withdrawal/index (GET), withdrawal/buscarEstudiante (GET, secretaria),
 * withdrawal/store (POST, secretaria) y withdrawal/updateEstado (POST, directivo).
 * El alta usa store() de CrudController (el modelo implementa Creatable); aquí solo se arman extractData() y validate().
*/
class WithdrawalController extends CrudController
{
    // Importamos TextoTrait para tener acceso a capitalizarOracion y protección XSS
    use TextoTrait;

    private const ESTADOS = ['PENDIENTE', 'APROBADO', 'RECHAZADO'];

    /** Caracteres mínimos de la cédula: con menos hay resultados de más.*/
    private const MIN_BUSQUEDA = 3;

    protected string $viewPath  = 'gestion_estudiante/retiro';
    protected string $routeName = 'withdrawal';

    /**
 * Devuelve el modelo con su tipo real, porque getModel() retorna ?object y el editor no resuelve sus métodos.
 * Este envoltorio fija el tipo de createModel() y evita los subrayados rojos.
*/
    private function modelo(): WithdrawalModel
    {
        /** @var WithdrawalModel $model*/
        $model = $this->getModel();

        return $model;
    }

    protected function createModel(PDO $pdo): object
    {
        return new WithdrawalModel($pdo);
    }

    protected function getFiltersFromRequest(): array
    {
        $anioActivo = (new AcademicYearModel($this->conexion()))->getActiveId();
        $filters = [
            // Casting a (string) para cumplir estrictamente con strict_types
            'motivo' => trim((string) ($_GET['motivo'] ?? '')),
            'estado' => trim((string) ($_GET['estado'] ?? '')),
            'anio'   => $anioActivo > 0 ? (string) $anioActivo : trim((string) ($_GET['anio'] ?? '')),
            'q'      => trim((string) ($_GET['q'] ?? '')),
        ];
        $turno = $this->turnoSecretariaActual();
        if ($turno !== null) {
            $filters['id_turno'] = $turno;
        }
        return $filters;
    }

    protected function getExtraIndexData(array $items, array $filters): array
    {
        $model = $this->modelo();
        $rol   = sigde_rol_clave($_SESSION['rol'] ?? '');

        // Solo la secretaria registra retiros: los candidatos se piden solo en ese caso.
        $esSecretaria = $rol === 'secretaria';
        $idAnio       = !empty($filters['anio']) ? (int) $filters['anio'] : null;

        return [
            'stats'          => $model->getStats($filters['id_turno'] ?? null, $idAnio),
            'solicitudes'    => $items,
            'aniosEscolares' => $model->getYears(),
            'motivos'        => $model->getReasons(),
            'puedeRegistrar' => $esSecretaria,
            'puedeResolver'  => $rol === 'directivo',
        ];
    }

    /* Busca el estudiante del retiro por cédula. Ruta: withdrawal/buscarEstudiante (GET, secretaria),
 responde JSON. Devuelve también a los que NO se pueden retirar, con el motivo, para que el
 formulario pueda decir "este alumno ya egresó" en vez de un "no se encontró" que no
 distinguiría las dos cosas.*/
    public function buscarEstudiante(): void
    {
        $termino = trim((string) ($_GET['q'] ?? ''));

        /* Prefijo sin número ("V", "CE-", "V."): se contesta antes que la regla
 del mínimo, porque "V" mide un carácter y de lo contrario saldría por
 ese camino con un "sin coincidencias" que parece un documento que no
 existe. Se comparan solo una o dos letras tras quitar separadores.*/
        $soloPrefijo = preg_replace('/[\s.\-]/u', '', $termino);
        if ($soloPrefijo !== '' && preg_match('/^[VEPCvepc]{1,2}$/', $soloPrefijo) === 1) {
            $this->jsonResponse(true, [
                'resultados' => [],
                'aviso'      => 'Falta el número de la cédula. El prefijo V, E o CE es opcional.',
                'esperando'  => false,
            ], null);
            return;
        }

        // Con menos dígitos hay demasiados resultados y ninguno útil.
        if (mb_strlen($termino, 'UTF-8') < self::MIN_BUSQUEDA) {
            $this->jsonResponse(true, ['resultados' => [], 'aviso' => null, 'esperando' => true], null);
            return;
        }

        try {
            $this->jsonResponse(true, [
                'resultados' => $this->modelo()->buscarPorDocumento(
                    $termino,
                    10,
                    $this->turnoSecretariaActual()
                ),
                'aviso'      => null,
                'esperando'  => false,
            ]);
        } catch (\Throwable $e) {
            $this->jsonError($e, static::class);
        }
    }

    /**
     * Datos del retiro tal como los manda el modal de la secretaria (contrato de CrudController::store()).
     */
    protected function extractData(array $source): array
    {
        return [
            'id_matricula'             => (int) ($source['id_matricula'] ?? 0),
            'id_motivo_retiro'         => (int) ($source['id_motivo_retiro'] ?? 0),
            'id_persona_representante' => ((int) ($source['id_persona_representante'] ?? 0)) ?: null,
            'fecha_retiro'             => !empty($source['fecha_retiro']) ? trim((string) $source['fecha_retiro']) : null,
            'id_usuario'               => (int) ($this->idUsuarioActual() ?? 0),
            'observaciones'            => mb_substr($this->capitalizarOracion((string) ($source['observaciones'] ?? '')), 0, 500),
        ];
    }

    /**
     * Devuelve los errores por campo del alta.
     * Los que requieren BD (motivo inexistente, retiro duplicado) se los pregunta al modelo.
     */
    protected function validate(array $data, ?int $id = null): array
    {
        $errores = [];

        $v = Validator::make($data, [
            'id_matricula'             => 'required|integer|min_value:1',
            'id_motivo_retiro'         => 'required|integer|min_value:1',
            'id_persona_representante' => 'required|integer|min_value:1',
            'fecha_retiro'             => 'required|date|date_not_future',
            'observaciones'            => 'string|max_len:500',
        ]);

        if (!$v->passes()) {
            foreach ($v->errors() as $campo => $mensajes) {
                $errores[$campo] = $mensajes[0];
            }
        }

        $turno = $this->turnoSecretariaActual();
        if ($turno !== null
            && $data['id_matricula'] > 0
            && !$this->modelo()->matriculaPerteneceTurno($data['id_matricula'], $turno)) {
            $errores['id_matricula'] = 'El estudiante no pertenece a su turno asignado.';
        } elseif ($data['id_matricula'] > 0) {
            /* La elegibilidad se comprueba AQUÍ, en el servidor, y no solo en el buscador:
               antes el formulario mostraba "elegible=false" para un egresado o un retirado,
               pero un POST directo se saltaba esa regla y creaba la solicitud igual. El
               motivo lo pone el modelo, que es donde vive la máquina de estados.*/
            $bloqueo = $this->modelo()->bloqueoParaRetiro($data['id_matricula']);
            if ($bloqueo !== '') {
                $errores['id_matricula'] = $bloqueo;
            }
        }

        if ($data['id_motivo_retiro'] > 0 && !$this->modelo()->existeMotivo($data['id_motivo_retiro'])) {
            $errores['id_motivo_retiro'] = 'El motivo seleccionado no es válido.';
        }

        if ($data['id_matricula'] > 0 && !empty($data['id_persona_representante'])) {
            if (!$this->modelo()->esRepresentanteOAutorizado($data['id_matricula'], (int) $data['id_persona_representante'])) {
                $errores['id_persona_representante'] = 'La persona seleccionada no está autorizada para retirar al estudiante.';
            }
        }

        return $errores;
    }

    /**
     * Registra una solicitud de retiro garantizando la captura de colisiones concurrentes (SQLSTATE 23000 / 1062).
     */
    public function store(): void
    {
        if ($this->idUsuarioActual() === null) {
            $this->jsonResponse(false, null, 'Sesión no válida.', 401);
            return;
        }

        $source = $_POST;
        $data = $this->extractData($source);
        $errores = $this->validate($data);

        if (!empty($errores)) {
            $this->jsonResponse(false, $errores, 'Corrige los errores antes de continuar.', 422);
            return;
        }

        try {
            $ok = $this->modelo()->create($data);
            if ($ok) {
                try {
                    $estudiante = $this->modelo()->descripcionEstudiantePublica((int) $data['id_matricula']);
                    $directivos = (new \App\Models\NotificationModel($this->conexion()))->destinatariosPorRol('Directivo');
                    (new \App\Models\NotificationModel($this->conexion()))->crear(
                        $directivos,
                        'retiro_pendiente',
                        'Solicitud de retiro registrada',
                        'Se ha registrado una nueva solicitud de retiro para ' . $estudiante . '.',
                        BASE_URL . 'withdrawal/index'
                    );
                } catch (\Throwable $e) {
                    error_log('Notificacion retiro: ' . $e->getMessage());
                }
                $this->jsonResponse(true, null, 'Solicitud de retiro registrada correctamente.');
            } else {
                $this->jsonResponse(false, null, 'No se pudo registrar la solicitud de retiro.', 500);
            }
        } catch (\PDOException $e) {
            $codigo = (int) ($e->errorInfo[1] ?? 0);
            if ($e->getCode() === '23000' || $codigo === 1062) {
                $this->jsonResponse(
                    false,
                    ['id_matricula' => 'Ya existe una solicitud de retiro registrada para esta matrícula.'],
                    'Solicitud duplicada.',
                    422
                );
                return;
            }
            $this->jsonError($e, static::class);
        } catch (\Throwable $e) {
            $this->jsonError($e, static::class);
        }
    }

    /**
 * Aprueba o rechaza una solicitud pendiente.
 * Ruta: withdrawal/updateEstado (POST, responde JSON). Solo directivo.
*/
    /* Detalle de una solicitud de retiro (GET withdrawal/ver/{id}). El botón "ver" de la fila ya no
 lleva los datos en atributos data-: duplicar el modelo en el markup obliga a mantenerlo en dos
 lugares y se desincroniza solo. Con el id basta y los datos se piden aquí.

 También comprueba el turno con secretariaTieneAccesoAEstudiante(), igual que buscarEstudiante()
 y store(): sin eso, la ficha se pediría con cualquier id por la URL y una secretaria del turno
 de la tarde vería las solicitudes de la mañana. El id del estudiante no se pide aparte, viene
 en el detalle que ya se carga. */
    public function ver(): void
    {
        $id = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            $this->setFlash('error', 'Solicitud de retiro no válida.');
            $this->redirect('index');
        }

        $item = $this->modelo()->getDetail($id);
        if ($item === null) {
            $this->setFlash('error', 'La solicitud de retiro no existe.');
            $this->redirect('index');
        }

        // Mismo mensaje y mismo destino que una solicitud inexistente: así tampoco
        // se confirma si ese id existe o solo se le falta el turno.
        if (!$this->secretariaTieneAccesoAEstudiante((int) $item['id_estudiante'])) {
            $this->setFlash('error', 'La solicitud de retiro no existe.');
            $this->redirect('index');
        }

        require __DIR__ . "/../Views/{$this->viewPath}/ver.php";
    }

    public function updateEstado(): void
    {
        // 1. Validar que la petición sea POST (aunque las rutas devuelvan 405, es buena defensa en profundidad)
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(false, null, 'Método no permitido.', 405);
            return; // Detiene la ejecución
        }

        // 2. Control de autorización (exclusivo para directivo)
        $rol = sigde_rol_clave($_SESSION['rol'] ?? '');
        if ($rol !== 'directivo') {
            $this->jsonResponse(false, null, 'Solo el personal directivo puede resolver solicitudes de retiro.', 403);
            return;
        }

        $id     = (int) ($_POST['id'] ?? 0);
        $estado = strtoupper(trim((string) ($_POST['estado'] ?? '')));

        $v = Validator::make(['id' => $id, 'estado' => $estado], [
            'id'     => 'required|integer|min_value:1',
            'estado' => 'required|in:PENDIENTE,APROBADO,RECHAZADO',
        ]);

        if (!$v->passes()) {
            $this->jsonResponse(false, $v->errors(), 'Parámetros inválidos.', 422);
            return;
        }

        $model = $this->modelo();

        try {
            $ok = $model->updateStatus($id, $estado, (int) ($this->idUsuarioActual() ?? 0));
            if ($ok) {
                try {
                    $detalle = $model->getDetail($id);
                    $idRegistrante = (int) ($detalle['id_usuario_registro'] ?? 0);
                    $estudiante = (string) ($detalle['estudiante'] ?? 'el estudiante');
                    $secretarias = (new \App\Models\NotificationModel($this->conexion()))->destinatariosPorRol('Secretaria');
                    $destinatarios = array_values(array_unique(array_filter(array_merge([$idRegistrante], $secretarias))));
                    if (!empty($destinatarios)) {
                        $verbo = $estado === 'APROBADO' ? 'aprobada' : 'rechazada';
                        (new \App\Models\NotificationModel($this->conexion()))->crear(
                            $destinatarios,
                            'retiro_' . strtolower($estado),
                            'Solicitud de retiro ' . $verbo,
                            'La dirección ha ' . ($estado === 'APROBADO' ? 'aprobado' : 'rechazado') . ' la solicitud de retiro de ' . $estudiante . '.',
                            BASE_URL . 'withdrawal/index'
                        );
                    }
                } catch (\Throwable $e) {
                    error_log('Notificacion retiro estado: ' . $e->getMessage());
                }
            }
            $this->jsonResponse(
                $ok,
                null,
                $ok ? 'Estado de la solicitud actualizado.' : ($model->ultimoError() ?: 'No se pudo actualizar la solicitud.'),
                $ok ? 200 : 409
            );
        } catch (\Throwable $e) {
            error_log('Error en WithdrawalController::updateEstado: ' . $e->getMessage());
            $this->jsonResponse(false, null, 'Ocurrió un error interno al actualizar el estado.', 500);
        }
    }

    public function updateStatus(): void
    {
        $this->updateEstado();
    }

    /**
     * Elimina una solicitud de retiro rechazada o pendiente.
     * Ruta: withdrawal/delete (POST, responde JSON).
     */
    public function delete(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(false, null, 'Método no permitido.', 405);
            return;
        }

        $rol = sigde_rol_clave($_SESSION['rol'] ?? '');
        if ($rol !== 'secretaria' && $rol !== 'directivo') {
            $this->jsonResponse(false, null, 'Solo secretaría y dirección pueden eliminar solicitudes de retiro.', 403);
            return;
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->jsonResponse(false, null, 'ID no válido.', 400);
            return;
        }

        try {
            $detalle = $this->modelo()->getDetail($id);
            $ok = $this->modelo()->delete($id);
            if ($ok) {
                if ($rol === 'directivo' && $detalle) {
                    try {
                        $idRegistrante = (int) ($detalle['id_usuario_registro'] ?? 0);
                        $estudiante = (string) ($detalle['estudiante'] ?? 'el estudiante');
                        $secretarias = (new \App\Models\NotificationModel($this->conexion()))->destinatariosPorRol('Secretaria');
                        $destinatarios = array_values(array_unique(array_filter(array_merge([$idRegistrante], $secretarias))));
                        if (!empty($destinatarios)) {
                            (new \App\Models\NotificationModel($this->conexion()))->crear(
                                $destinatarios,
                                'retiro_eliminado',
                                'Solicitud de retiro eliminada',
                                'La dirección ha eliminado la solicitud de retiro de ' . $estudiante . '.',
                                BASE_URL . 'withdrawal/index'
                            );
                        }
                    } catch (\Throwable $e) {
                        error_log('Notificacion retiro eliminado: ' . $e->getMessage());
                    }
                }
                $this->jsonResponse(true, null, 'Solicitud de retiro eliminada correctamente.');
            } else {
                $this->jsonResponse(false, null, 'No se encontró la solicitud de retiro a eliminar.', 404);
            }
        } catch (\DomainException $e) {
            $this->jsonResponse(false, null, $e->getMessage(), 422);
        } catch (\Throwable $e) {
            error_log('Error en WithdrawalController::delete: ' . $e->getMessage());
            $this->jsonResponse(false, null, 'No se pudo eliminar la solicitud de retiro.', 500);
        }
    }
}