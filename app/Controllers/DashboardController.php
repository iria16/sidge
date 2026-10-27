<?php

namespace App\Controllers;

use App\Models\AcademicStructureModel;
use App\Models\DirectivoDashboardModel;
use App\Models\EstudianteModel;
use App\Models\InscripcionesModel;
use App\Models\RatificacionModel;
use App\Models\TurnoModel;
use App\Models\WithdrawalModel;
use App\Support\AniosEscolares;
use Throwable;
use function App\Traits\sigde_rol_clave;

/**
 * DashboardController
 *
 * Punto de entrada al panel principal de SIGDE una vez iniciada la sesión.
 * Cada rol tiene su propio dashboard, y este controlador decide cuál mostrar.
 *
 * Cómo funciona:
 * - Todos los destinos posteriores al login (AuthController) apuntan a
 * `dashboard/index`. Ahí resuelvo el rol de la sesión y ejecuto el método
 * con el mismo nombre (admin, docente, coordinador, directivo, secretaria).
 * - Cada método de rol carga su vista en Views/dashboard/{rol}/index.php.
 * - Cada método de rol comprueba además que el usuario realmente tenga ese
 * rol, para que nadie entre al dashboard de otro rol escribiendo la URL
 * a mano.
 *
 * Por qué así: AuthController no necesita conocer los roles. Basta con que
 * mande a `dashboard/index`, y esta clase concentra en un solo lugar la
 * decisión de a dónde va cada rol.
 *
 * Rutas: `dashboard/index` y `dashboard/{rol}`. Si agrego un rol nuevo debo:
 * 1. añadirlo a RUTAS_POR_ROL,
 * 2. crear el método y la vista correspondientes,
 * 3. asegurarme de que sigde_rol_clave() lo reconozca,
 * 4. registrar la acción en el whitelist `$rutasPermitidas` del front
 * controller (public/index.php).
 *
 * @author Logística
 * @package App\Controllers
*/
class DashboardController extends BaseController
{
    protected string $viewPath  = 'dashboard';
    protected string $routeName = 'dashboard';

    /**
 * Roles válidos del sistema y su método/ruta correspondiente.
 *
 * La clave es el rol normalizado por sigde_rol_clave(). El valor es la
 * ruta (controlador/acción/...) a la que se envía a un usuario que
 * intente entrar al dashboard de otro rol; lo usa verificarAccesoRol().
*/
    private const RUTAS_POR_ROL = [
        'admin'       => 'dashboard/admin/index',
        'docente'     => 'dashboard/docente',
        'coordinador' => 'dashboard/coordinador/index',
        'directivo'   => 'dashboard/directivo/index',
        'secretaria'  => 'dashboard/secretaria/index',
    ];

    /**
 * Punto de entrada genérico: ejecuta directamente el dashboard
 * correspondiente según el rol.
 * Ruta: dashboard/index.
 *
 * Toma el rol de la sesión, lo normaliza con sigde_rol_clave() y llama al
 * método que se llama igual que el rol. Si ese método no existe (rol
 * desconocido o sesión corrupta), redirijo al login.
*/
    public function index(): void
    {
        $rol = sigde_rol_clave($_SESSION['rol'] ?? '');

        if (!method_exists($this, $rol)) {
            // Rol desconocido o sesión corrupta: fuera.
            $this->redireccionar('auth/login');
        }

        // Ejecuta dinámicamente el método del rol ($this->admin(), $this->docente(), etc.)
        $this->$rol();
    }

    /**
 * Dashboard del administrador.
 * Ruta: dashboard/admin. Vista: Views/dashboard/admin/index.php.
*/
    public function admin(): void
    {
        $this->verificarAccesoRol('admin');

        date_default_timezone_set('America/Caracas');
        $horaCaracas = (int) (new \DateTimeImmutable('now'))->format('G');
        $saludo = match (true) {
            $horaCaracas < 12 => 'Buenos días',
            $horaCaracas < 19 => 'Buenas tardes',
            default           => 'Buenas noches',
        };
        $nombreUsuario = trim((string) ($_SESSION['nombre'] ?? 'Administrador'));

        $model = new \App\Models\DashboardModel($this->conexion());

        $anioActivo       = $model->getAnioActivo();
        $indicadores      = $model->getMetricasUsuarios();
        $requiereAtencion = $model->getAtencionRequerida();
        $actividad        = $model->getActividadBitacora(30);
        $usuariosPorRol   = $model->getUsuariosPorRol();

        require_once __DIR__ . '/../Views/dashboard/admin/index.php';
    }

    /**
 * Dashboard del docente.
 * Ruta: dashboard/docente. Lo renderiza DocenteDashboardController con la
 * vista Views/dashboard/docente/index.php.
*/
    public function docente(): void
    {
        $this->verificarAccesoRol('docente');
        (new DocenteDashboardController())->index();
    }

    /**
     * Dashboard del coordinador.
     * Ruta: dashboard/coordinador. Vista: Views/dashboard/coordinador/index.php.
     */
    public function coordinador(): void
    {
        $this->verificarAccesoRol('coordinador');
        $datos = $this->prepararDatosDirectivoYCoordinador('coordinador');
        extract($datos);
        $esCoordinador = true;

        require __DIR__ . '/../Views/dashboard/coordinador/index.php';
    }

    /**
     * Dashboard del directivo.
     * Ruta: dashboard/directivo. Vista: Views/dashboard/directivo/index.php.
     */
    public function directivo(): void
    {
        $this->verificarAccesoRol('directivo');
        $datos = $this->prepararDatosDirectivoYCoordinador('directivo');
        extract($datos);
        $esCoordinador = false;

        require __DIR__ . '/../Views/dashboard/directivo/index.php';
    }

    /**
     * Prepara métricas e indicadores consolidados para Dirección y Coordinación.
     */
    private function prepararDatosDirectivoYCoordinador(string $rol): array
    {
        date_default_timezone_set('America/Caracas');
        $horaCaracas = (int) (new \DateTimeImmutable('now'))->format('G');
        $saludo = match (true) {
            $horaCaracas < 12 => 'Buenos días',
            $horaCaracas < 19 => 'Buenas tardes',
            default           => 'Buenas noches',
        };
        $nombreUsuario = trim((string) ($_SESSION['nombre'] ?? ($rol === 'directivo' ? 'Directora' : 'Coordinación')));

        $model = new DirectivoDashboardModel($this->conexion());

        return [
            'saludo'             => $saludo,
            'nombreUsuario'      => $nombreUsuario,
            'anioActivo'         => $model->getAnioActivo(),
            'metricasMatricula'  => $model->getMetricasMatricula(),
            'metricasCapacidad'  => $model->getMetricasCapacidad(),
            'metricasPersonal'   => $model->getMetricasPersonal(),
            'distribucionGrados' => $model->getMatriculaPorGradoYSexo(),
            'avanceResultados'   => $model->getResultadosPorSeccion(),
            'alertas'            => $model->getAlertas(),
        ];
    }

/**
 * Dashboard de secretaría.
 * Ruta: dashboard/secretaria. Vista: Views/dashboard/secretaria/index.php.
*/
    public function secretaria(): void
    {
        $this->verificarAccesoRol('secretaria');

        date_default_timezone_set('America/Caracas');
        $horaCaracas = (int) (new \DateTimeImmutable('now'))->format('G');
        $saludo = match (true) {
            $horaCaracas < 12 => 'Buenos días',
            $horaCaracas < 19 => 'Buenas tardes',
            default           => 'Buenas noches',
        };
        $nombreUsuario = trim((string) ($_SESSION['nombre'] ?? 'Secretaría'));

        $pdo = $this->conexion();
        $anioId = AniosEscolares::idEnCurso($pdo);
        $anioNombre = AniosEscolares::nombreEnCurso($pdo);
        $anioActivo = $anioId > 0
            ? ['id' => $anioId, 'nombre' => $anioNombre]
            : null;

        $turnoId = (int) ($_SESSION['id_turno'] ?? 0);
        $turnoNombre = null;
        try {
            $turnoNombre = (new TurnoModel($pdo))->getNombreById($turnoId);
        } catch (Throwable $e) {
            error_log('DashboardController::secretaria(): no se pudo obtener el turno: '
                . $e->getMessage());
        }

        $estructura = null;
        try {
            $estructura = (new AcademicStructureModel($pdo))->pendientesDeConfiguracion();
        } catch (Throwable $e) {
            error_log('DashboardController::secretaria(): no se pudo calcular el estado de la estructura: '
                . $e->getMessage());
        }

        $estadisticasEstudiantes = null;
        $estadisticasInscripciones = null;
        $estadisticasRatificacion = null;
        $estadisticasRetiros = null;
        $cuposPorEstructura = null;

        if ($anioActivo !== null && $turnoId > 0) {
            $estadisticasEstudiantes = $this->estadisticaSegura(
                static fn(): array => (new EstudianteModel($pdo))->getStats([
                    'anio' => $anioId,
                    'id_turno' => $turnoId,
                ]),
                'estudiantes de Secretaría'
            );

            $estadisticasInscripciones = $this->estadisticaSegura(
                static fn(): array => (new InscripcionesModel($pdo))->getStats($turnoId, $anioId),
                'inscripciones de Secretaría'
            );

            // RatificacionModel resuelve el año activo dentro de getStats(); aquí solo se acota el turno.
            $estadisticasRatificacion = $this->estadisticaSegura(
                static fn(): array => (new RatificacionModel($pdo))->getStats($turnoId),
                'ratificación de Secretaría'
            );

            $estadisticasRetiros = $this->estadisticaSegura(
                static fn(): array => (new WithdrawalModel($pdo))->getStats($turnoId, $anioId),
                'retiros de Secretaría'
            );

            $cuposPorEstructura = $this->estadisticaSegura(
                static fn(): array => (new InscripcionesModel($pdo))
                    ->getCuposPorEstructura($turnoId, $anioId),
                'cupos por estructura de Secretaría'
            );
        }

        require_once __DIR__ . '/../Views/dashboard/secretaria/index.php';
    }

    /**
     * Ejecuta una consulta de indicadores sin tumbar el resto del panel.
     *
     * @param callable():array<string, mixed> $consulta
     * @return array<string, mixed>|null
     */
    private function estadisticaSegura(callable $consulta, string $nombre): ?array
    {
        try {
            return $consulta();
        } catch (Throwable $e) {
            error_log("DashboardController::secretaria(): no se pudieron calcular {$nombre}: "
                . $e->getMessage());

            return null;
        }
    }

    /**
 * Evita que un usuario con rol X entre por URL directa al dashboard de rol Y.
 *
 * Compara el rol de la sesión (normalizado) con el rol que exige el
 * dashboard. Si no coinciden, redirijo al dashboard que sí le corresponde
 * según RUTAS_POR_ROL, o al login si su rol no está en la tabla. Termina la
 * ejecución con exit, así la vista nunca se carga.
 *
 * @param string $rolRequerido Clave del rol que exige el dashboard (por ejemplo 'admin').
*/
    private function verificarAccesoRol(string $rolRequerido): void
    {
        $rolSesion = sigde_rol_clave($_SESSION['rol'] ?? '');

        if ($rolSesion !== $rolRequerido) {
            $ruta = self::RUTAS_POR_ROL[$rolSesion] ?? 'auth/login';
            header('Location: ' . BASE_URL . $ruta);
            exit;
        }
    }
}