<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\EstadisticaModel;
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
        return new EstadisticaModel($pdo);
    }

    private function modelo(): EstadisticaModel
    {
        /** @var EstadisticaModel $model */
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
            $model = $this->modelo();

            $anios = $model->getAniosEscolares();
            $idAnio = $model->resolverAnio(
                $anios,
                isset($_GET['anio']) ? (int) $_GET['anio'] : null
            );
            $nombrePeriodo = $model->nombrePeriodo($anios, $idAnio);
            $vista = $model->normalizarVista(isset($_GET['vista']) ? (string) $_GET['vista'] : null);

            $datos = $model->getDatosVista($vista, $idAnio);
            extract($datos);

            require __DIR__ . '/../Views/estadisticas/index.php';
        } catch (Throwable $e) {
            $this->handleError($e, 'Estadísticas Institucionales');
        }
    }
}
