<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\DirectivoDashboardModel;
use App\Models\ReporteModel;
use App\Support\AniosEscolares;
use PDO;
use Throwable;
use function App\Traits\sigde_rol_clave;

/**
 * EstadisticaController
 * ---------------------
 * Interfaz única de Estadísticas Institucionales para Dirección y Coordinación.
 * Centraliza todos los indicadores demográficos, capacidad escolar, rendimiento y talento humano.
 */
class EstadisticaController extends BaseController
{
    protected string $viewPath  = 'estadisticas';
    protected string $routeName = 'estadistica';

    protected function createModel(PDO $pdo): object
    {
        return new DirectivoDashboardModel($pdo);
    }

    private function modelo(): DirectivoDashboardModel
    {
        /** @var DirectivoDashboardModel $model */
        $model = $this->getModel();
        return $model;
    }

    /**
     * Valida que el rol actual tenga acceso (directivo o coordinador).
     */
    private function validarAcceso(): void
    {
        $rol = sigde_rol_clave($_SESSION['rol'] ?? '');
        if (!in_array($rol, ['directivo', 'coordinador'], true)) {
            $this->redirect('dashboard/index');
            exit;
        }
    }

    /**
     * Pantalla maestra de Estadísticas con diseño estandarizado de Listado de Secciones.
     */
    public function index(): void
    {
        $this->validarAcceso();

        try {
            $pdo = $this->conexion();
            $dashboardModel = $this->modelo();
            $reporteModel = new ReporteModel($pdo);

            $anios = $reporteModel->getAniosEscolares();
            $idAnioActivo = AniosEscolares::idEnCurso($pdo);

            $idAnio = isset($_GET['anio']) && (int) $_GET['anio'] > 0
                ? (int) $_GET['anio']
                : $idAnioActivo;

            if ($idAnio <= 0 && $anios !== []) {
                $idAnio = (int) $anios[0]['id_anio_escolar'];
            }

            $nombrePeriodo = '';
            foreach ($anios as $a) {
                if ((int) $a['id_anio_escolar'] === $idAnio) {
                    $nombrePeriodo = (string) $a['nombre_periodo'];
                    break;
                }
            }

            // Vista activa: matricula | rendimiento | edades | cobertura | personal | salud | tallas
            $vistasPermitidas = ['matricula', 'rendimiento', 'edades', 'cobertura', 'personal', 'salud', 'tallas'];
            $vista = isset($_GET['vista']) && in_array($_GET['vista'], $vistasPermitidas, true)
                ? $_GET['vista']
                : 'matricula';

            // Datos de tabla según vista seleccionada
            $filas = [];
            $metricasSalud = [];
            $tiposSangre = [];
            $filasAntropometricas = [];
            $distribucionTallas = [];
            switch ($vista) {
                case 'salud':
                    $salud = $dashboardModel->getEstadisticasSalud($idAnio);
                    $metricasSalud = $salud['resumen'];
                    $tiposSangre = $salud['tipos_sangre'];
                    $filas = $salud['por_grado'];
                    $filasAntropometricas = $dashboardModel->getEstadisticasAntropometricas($idAnio)['por_grado'];
                    break;

                case 'tallas':
                    $tallas = $dashboardModel->getEstadisticasTallas($idAnio);
                    $distribucionTallas = $tallas['por_prenda'];
                    $filas = $tallas['por_grado'];
                    break;

                case 'personal':
                    $filas = $dashboardModel->getEstadisticasPersonal();
                    break;

                case 'rendimiento':
                    $filas = $dashboardModel->getResultadosPorSeccion($idAnio);
                    break;

                case 'edades':
                    $filas = $dashboardModel->getDistribucionPorEdadYSexo($idAnio);
                    break;

                case 'cobertura':
                    $filas = $reporteModel->getDistribucionAcademica($idAnio);
                    break;

                case 'matricula':
                default:
                    $filas = $dashboardModel->getMatriculaPorGradoYSexo($idAnio);
                    break;
            }

            require __DIR__ . '/../Views/estadisticas/index.php';
        } catch (Throwable $e) {
            $this->handleError($e, 'Estadísticas Institucionales');
        }
    }
}
