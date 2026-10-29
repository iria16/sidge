<?php

namespace App\Controllers;

use App\Models\DirectivoDashboardModel;
use App\Models\SecretariaDashboardModel;
use App\Support\Saludo;
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

        $saludo = Saludo::actual();
        $nombreUsuario = trim((string) ($_SESSION['nombre'] ?? 'Administrador'));

        $model = new \App\Models\DashboardModel($this->conexion());

        $anioActivo        = $model->getAnioActivo();
        $indicadores       = $model->getMetricasUsuarios();
        $requiereAtencion  = $model->getAtencionRequerida();
        $actividad         = $model->getActividadBitacora(30);
        $usuariosPorRol    = $model->getUsuariosPorRol();
        $coberturaPersonal = $model->getCoberturaPersonal();

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
        $saludo = Saludo::actual();
        $nombreUsuario = trim((string) ($_SESSION['nombre'] ?? ($rol === 'directivo' ? 'Directora' : 'Coordinación')));

        $model = new DirectivoDashboardModel($this->conexion());
        $anioActivo = $model->getAnioActivo();
        $idAnio = $anioActivo !== null ? (int) $anioActivo['id'] : null;

        return [
            'saludo'             => $saludo,
            'nombreUsuario'      => $nombreUsuario,
            'anioActivo'         => $anioActivo,
            'metricasMatricula'  => $model->getMetricasMatricula($idAnio),
            'metricasCapacidad'  => $model->getMetricasCapacidad($idAnio),
            'metricasPersonal'   => $model->getMetricasPersonal(),
            'distribucionGrados' => $model->getMatriculaPorGradoYSexo($idAnio),
            'avanceResultados'   => $model->getResultadosPorSeccion($idAnio),
            'alertas'            => $model->getAlertas($idAnio, $rol),
            'retirosPendientes'  => $rol === 'directivo' ? $model->getRetirosPendientes($idAnio) : 0,
        ];
    }

/**
 * Dashboard de secretaría.
 * Ruta: dashboard/secretaria. Vista: Views/dashboard/secretaria/index.php.
*/
    public function secretaria(): void
    {
        $this->verificarAccesoRol('secretaria');

        $saludo = Saludo::actual();
        $nombreUsuario = trim((string) ($_SESSION['nombre'] ?? 'Secretaría'));

        $turnoId = (int) ($_SESSION['id_turno'] ?? 0);
        $datos = (new SecretariaDashboardModel($this->conexion()))->getDatos($turnoId);
        extract($datos);

        require_once __DIR__ . '/../Views/dashboard/secretaria/index.php';
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