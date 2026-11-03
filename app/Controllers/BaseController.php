<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Http\JsonResponse;
use App\Models\AccesoUsuarioModel;
use App\Traits\SecurityTrait;
use App\Traits\TextoTrait;
use InvalidArgumentException;
use PDO;
use Throwable;
use function App\Traits\sigde_rol_clave;

/**
 * Controlador base: centraliza sesión, conexión a BD, errores y respuestas JSON.
 * Los hijos definen $viewPath, $routeName y createModel(); afterConstruct() es opcional.
 * AuthController no lo extiende porque debe mostrar el login sin exigir sesión.
*/
abstract class BaseController
{
    use SecurityTrait, TextoTrait;

    /** Modelo principal; es null hasta la primera llamada a getModel() (carga diferida).*/
    protected ?object $model = null;

    /** Conexión PDO, inicializada junto con el modelo en getModel().*/
    protected ?PDO $pdo = null;

    /** Modelo de consultas de acceso; se crea la primera vez que se necesita.*/
    private ?AccesoUsuarioModel $accesoModel = null;

    /** Carpeta de vistas del módulo, relativa a app/Views. Cada hijo debe asignarla.*/
    protected string $viewPath;

    /** Segmento de ruta del controlador, usado por redirect(). Cada hijo debe asignarlo.*/
    protected string $routeName;

    /**
 * Exige sesión activa y luego ejecuta el gancho afterConstruct() del hijo.
 * El orden importa: un visitante sin sesión es redirigido antes de configurar nada.
*/
    public function __construct()
    {
        $this->verificarSesion();
        $this->afterConstruct();
    }

    /**
 * Devuelve el modelo principal con Lazy Loading.
 * La primera llamada abre el PDO y crea el modelo; las siguientes reutilizan la instancia.
*/
    protected function getModel(): ?object
    {
        if ($this->model === null) {
            $this->pdo   = (new Database())->getConnection();
            $this->model = $this->createModel($this->pdo);
        }

        return $this->model;
    }

    /**
 * Devuelve la conexión PDO del request, abriéndola si aún no existe.
 * Los traits que necesitan un segundo modelo la usan para no abrir otra conexión.
*/
    protected function conexion(): PDO
    {
        $this->getModel();

        return $this->pdo;
    }

    /**
     * Modelo de consultas de acceso, reutilizando la conexión del request.
     */
    private function acceso(): AccesoUsuarioModel
    {
        if ($this->accesoModel === null) {
            $this->accesoModel = new AccesoUsuarioModel($this->conexion());
        }

        return $this->accesoModel;
    }

    protected function turnoSecretariaActual(): ?int
    {
        if (sigde_rol_clave($_SESSION['rol'] ?? '') !== 'secretaria') {
            return null;
        }

        return $this->acceso()->turnoDeSecretaria((int) ($_SESSION['usuario_id'] ?? 0));
    }

    /**
     * Determina si el rol del usuario en sesión es de solo consulta (lectura)
     * para el módulo actual (típicamente 'coordinador').
     */
    protected function esRolConsulta(): bool
    {
        return sigde_rol_clave($_SESSION['rol'] ?? '') === 'coordinador';
    }

    /**
 * Id del docente del usuario en sesión, o null si no es docente.
 *
 * Es lo que acota la consulta de expedientes a las estructuras que tiene asignadas.
 * Se resuelve por `empleado → docente` porque es la única cadena que existe: un docente
 * es un empleado con fila en la tabla `docente`, y sus clases están en
 * `asignacion_docente`, una por estructura académica.
*/
    protected function docenteActualId(): ?int
    {
        if (sigde_rol_clave($_SESSION['rol'] ?? '') !== 'docente') {
            return null;
        }

        // Si es rol docente pero aún no tiene perfil asignado, devuelve 0 para que
        // la consulta permanezca acotada y no muestre secciones ajenas.
        return $this->acceso()->docenteIdDeUsuario((int) ($_SESSION['usuario_id'] ?? 0));
    }

    protected function secretariaTieneAccesoAEstudiante(int $idEstudiante): bool
    {
        $turno = $this->turnoSecretariaActual();
        if ($turno === null) {
            return true;
        }
        if ($turno <= 0) {
            return false;
        }

        return $this->acceso()->turnoDeEstudiante($idEstudiante) === $turno;
    }

    protected function secretariaTieneAccesoAEstructura(int $idEstructura): bool
    {
        $turno = $this->turnoSecretariaActual();
        if ($turno === null) {
            return true;
        }
        if ($turno <= 0) {
            return false;
        }

        return $this->acceso()->estructuraPerteneceATurno($idEstructura, $turno);
    }

    /**
 * Fabrica el modelo en las clases hijas (por defecto no crea ninguno).
 * Ejemplo: `return new StudentsModel($pdo);`
*/
    protected function createModel(PDO $pdo): ?object
    {
        return null;
    }

    /**
 * Gancho vacío que los hijos sobrescriben para asignar $viewPath, $routeName, roles, etc.
 * Evita reescribir el constructor o llamar a parent::__construct().
*/
    protected function afterConstruct(): void {}

    // ---------- Respuestas JSON ----------

    /**
 * Emite una respuesta JSON estándar (éxito o error) y termina la ejecución.
 * Ramifica aquí porque JsonResponse::error() recibe $message y $data en orden invertido.
*/
    protected function jsonResponse(bool $ok, mixed $data = null, ?string $message = null, int $code = 200): never
    {
        $ok
            ? JsonResponse::success($data, $message, $code)->send()
            : JsonResponse::error($message, $data, $code)->send();
    }

    /**
 * Emite un payload JSON sin el envoltorio estándar.
 * Útil cuando el consumidor espera un formato propio, como DataTables.
*/
    protected function jsonRaw(array $payload, int $code = 200): never
    {
        JsonResponse::raw($payload, $code)->send();
    }

    /**
 * Registra un error en el log del servidor con su ubicación y la traza de llamadas.
 * Solo va al log: la traza puede incluir valores y nunca debe llegar al usuario.
*/
    private function registrarError(Throwable $e, string $seccion): void
    {
        error_log(
            static::class . " [{$seccion}]: {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}\n"
            . $e->getTraceAsString()
        );
    }

/**
     * Registra el error en el log y muestra la vista genérica de error 500 (peticiones HTML).
     * El detalle técnico solo va al log; $seccion identifica la operación que falló.
     *
     * A la pantalla no llega ni el mensaje de la excepción ni su ruta y línea: el primero
     * puede traer datos del negocio (un valor duplicado, una cédula) y las otras dos
     * revelan cómo está organizado el servidor. Todo eso queda en el log, que es lo
     * que el administrador puede leer y el usuario no.
     */
    protected function handleError(Throwable $e, string $seccion): never
    {
        $this->registrarError($e, $seccion);

        // 500, no 200: un error que responde "correcto"cuenta comoOK en los
        // monitors y esconde el fallo. La vista ya es genérica, no hace falta
        // rellenar el espacio con 1024 espacios.
        http_response_code(500);

        $seccion = $this->etiquetaLegible($seccion);

        require __DIR__ . '/../Views/shared/error.php';

        exit;
    }

    /**
     * Decide si $seccion puede mostrarse al usuario en la vista de error.
     *
     * Quien llama pasa dos cosas distintas en ese parámetro: una etiqueta legible
     * ('Cuentas de usuario') o el nombre de la clase (static::class), que es un
     * detalle interno. Una barra invertida solo aparece en la segunda forma, así que
     * sirve para distinguir una de otra sin adivinar. Cuando no hay etiqueta legible
     * se devuelve null y la vista usa su texto genérico.
     */
    private function etiquetaLegible(string $seccion): ?string
    {
        return str_contains($seccion, '\\') ? null : $seccion;
    }

    /**
 * Equivalente de handleError() para AJAX: mismo log, pero responde un JSON con mensaje neutro.
 * El mensaje incluye la sección para que el front lo muestre en SweetAlert.
*/
    protected function jsonError(Throwable $e, string $seccion, int $code = 500): never
    {
        $this->registrarError($e, $seccion);
        $this->jsonResponse(
            false,
            null,
            "Ocurrió un problema al procesar la solicitud ({$seccion}). Intenta nuevamente.",
            $code
        );
    }

    // ---------- Sesión / flash / navegación ----------

    /**
 * Devuelve el ID del usuario autenticado, o null si no hay sesión.
 * Lee 'usuario_id', la clave que guarda AuthController al iniciar sesión.
*/
    protected function idUsuarioActual(): ?int
    {
        return isset($_SESSION['usuario_id']) ? (int) $_SESSION['usuario_id'] : null;
    }

    /**
 * Devuelve los mensajes flash y los elimina de la sesión.
 * Se leen una sola vez, así no reaparecen al recargar la página.
 *
 * @return array{error: ?string, success: ?string}
*/
    protected function getFlashMessages(): array
    {
        $error   = $_SESSION['flash_error']   ?? null;
        $success = $_SESSION['flash_success'] ?? null;
        $info    = $_SESSION['flash_info']    ?? null;
        unset($_SESSION['flash_error'], $_SESSION['flash_success'], $_SESSION['flash_info']);
        return compact('error', 'success', 'info');
    }

    /**
     * Guarda un mensaje flash en sesión antes de una redirección.
     * El tipo debe ser 'error', 'success' o 'info', las claves que lee getFlashMessages().
     * Cualquier otro tipo lanza una excepción, para no perder el mensaje en silencio.
     *
     * CRITERIO DE USO (setFlash vs setSwal):
     * - setFlash(): Usar para avisos discretos en la cabecera de la página (banners Bootstrap
     *   renderizados por partials/alert-flash.php). Ideal para el resultado ordinario de
     *   operaciones de pantalla ("Registro actualizado", "Filtro no arrojó resultados").
     * - setSwal(): Heredado de SecurityTrait. Usar para avisos críticos, informaciones de sesión
     *   (sesión requerida, logout exitoso), bloqueos de cuenta o alertas modales interactivas
     *   que requieran acuse de recibo explícito del usuario mediante SweetAlert2.
     */
    protected function setFlash(string $type, string $message): void
    {
        if (!in_array($type, ['error', 'success', 'info'], true)) {
            throw new InvalidArgumentException("Tipo de flash no válido: {$type}");
        }

        $_SESSION["flash_{$type}"] = $message;
    }

    /**
 * Redirige a una acción del mismo controlador: BASE_URL + $routeName + acción (+ query).
 * Ejemplo: `$this->redirect('index', ['q' => 'perez'])` → `estudiantes/index?q=perez`.
*/
    protected function redirect(string $action, array $params = []): never
    {
        $query = $params ? '?' . http_build_query($params) : '';
        header('Location: ' . BASE_URL . "{$this->routeName}/{$action}{$query}");
        exit;
    }
}
