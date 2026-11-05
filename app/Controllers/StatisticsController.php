<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\BinaryResponse;
use App\Support\Configuracion;
use App\Models\StatisticsModel;
use App\Support\EstadisticaExport;
use PDO;
use Throwable;
use function App\Traits\sigde_rol_clave;

/**
 * StatisticsController
 * ---------------------
 * Interfaz única de Estadísticas Institucionales para Dirección y Coordinación.
 * Control de Representantes y Hermanos, Matrícula y Capacidad, Demografía, Salud y Talento Humano.
 */
class StatisticsController extends BaseController
{
    protected string $viewPath  = 'estadisticas';
    protected string $routeName = 'estadistica';

    protected function createModel(PDO $pdo): object
    {
        return new StatisticsModel($pdo);
    }

    private function modelo(): StatisticsModel
    {
        /** @var StatisticsModel $model */
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
     * Pantalla maestra de Estadísticas Institucionales.
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

            $idGrado = isset($_GET['grado']) && (int) $_GET['grado'] > 0 ? (int) $_GET['grado'] : null;
            $idSeccion = isset($_GET['seccion']) && (int) $_GET['seccion'] > 0 ? (int) $_GET['seccion'] : null;

            $grados = $model->getGradosAnio($idAnio);
            $secciones = $model->getSeccionesGrado($idAnio, $idGrado);

            $pestana = isset($_GET['pestana']) ? strtolower(trim((string) $_GET['pestana'])) : '';
            if ($pestana === '' && isset($_GET['vista'])) {
                $pestana = strtolower(trim((string) $_GET['vista']));
            }
            if (!in_array($pestana, ['hermanos', 'matricula', 'salud', 'institucional', 'rendimiento'], true)) {
                $pestana = 'hermanos';
            }

            $q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';

            $perPageHermanos = (int) ($_GET['per_page'] ?? 25);
            if (!in_array($perPageHermanos, [25, 50, 100], true)) {
                $perPageHermanos = 25;
            }

            $paginaHermanos = max(1, (int) ($_GET['page'] ?? 1));
            $totalHermanos = $model->contarControlHermanos($idAnio, $idGrado, $idSeccion, $q);
            $totalPaginasHermanos = max(1, (int) ceil($totalHermanos / $perPageHermanos));
            $paginaHermanos = min($paginaHermanos, $totalPaginasHermanos);

            // 1. Datos de Representantes y Control de Hermanos
            $resumenHermanos = $model->getResumenRepresentantesHermanos($idAnio, $idGrado, $idSeccion);
            $detalleHermanos = $model->getDetalleControlHermanos(
                $idAnio,
                $idGrado,
                $idSeccion,
                $q,
                $perPageHermanos,
                ($paginaHermanos - 1) * $perPageHermanos
            );
            $paginacionHermanos = [
                'desde'         => $totalHermanos > 0 ? (($paginaHermanos - 1) * $perPageHermanos) + 1 : 0,
                'hasta'         => min($paginaHermanos * $perPageHermanos, $totalHermanos),
                'total'         => $totalHermanos,
                'pagina_actual' => $paginaHermanos,
                'total_paginas' => $totalPaginasHermanos,
                'per_page'      => $perPageHermanos,
            ];

            // 2. Datos de Matrícula y Familias
            $demografiaMatricula = $model->getDemografiaMatricula($idAnio, $idGrado, $idSeccion);
            $matriculaAulas = $model->getMatriculaCapacidadDetallada($idAnio, $idGrado, $idSeccion);
            $estadisticasFamilias = $model->getEstadisticasFamilias($idAnio, $idGrado, $idSeccion);
            $distribucionParroquias = $model->getDistribucionParroquias($idAnio, $idGrado, $idSeccion);

            // 3. Datos de Dotación y Salud (Conservados y Optimizados)
            $saludData = $model->getEstadisticasSalud($idAnio);
            $metricasSalud = $saludData['resumen'] ?? [];
            $tiposSangre = $saludData['tipos_sangre'] ?? [];
            $filasSalud = $saludData['por_grado'] ?? [];
            $antropometriaData = $model->getEstadisticasAntropometricas($idAnio);
            $filasAntropometricas = $antropometriaData['por_grado'] ?? [];
            $tallasData = $model->getEstadisticasTallas($idAnio);
            $distribucionTallas = $tallasData['por_prenda'] ?? [];
            $filasTallas = $tallasData['por_grado'] ?? [];

            // 4. Datos de Control Administrativo (Documentos, Cobertura y Personal)
            $documentosPendientes = $model->getDocumentosPendientes($idAnio, $idGrado, $idSeccion);
            $estadisticasPersonal = $model->getEstadisticasPersonal();
            $metricasPersonal = $model->getMetricasPersonal();
            $coberturaAulas = $model->getCoberturaAulas($idAnio);

            // 5. Datos de Rendimiento Académico y Calificaciones
            $filasRendimiento = $model->getRendimientoSecciones($idAnio, $idGrado, $idSeccion);
            $resumenRendimiento = $model->getResumenRendimiento($idAnio, $idGrado, $idSeccion);

            require __DIR__ . '/../Views/estadisticas/index.php';
        } catch (Throwable $e) {
            $this->handleError($e, 'Estadísticas Institucionales');
        }
    }

    /**
     * Exporta el reporte de Control de Hermanos a CSV/Excel (UTF-8 con BOM).
     */
    public function exportarHermanosExcel(): void
    {
        $this->validarAcceso();

        try {
            $model = $this->modelo();
            $anios = $model->getAniosEscolares();
            $idAnio = $model->resolverAnio($anios, isset($_GET['anio']) ? (int) $_GET['anio'] : null);
            $nombrePeriodo = $model->nombrePeriodo($anios, $idAnio);

            $idGrado = isset($_GET['grado']) && (int) $_GET['grado'] > 0 ? (int) $_GET['grado'] : null;
            $idSeccion = isset($_GET['seccion']) && (int) $_GET['seccion'] > 0 ? (int) $_GET['seccion'] : null;
            $q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';

            $detalleHermanos = $model->getDetalleControlHermanos($idAnio, $idGrado, $idSeccion, $q);
            $csv = EstadisticaExport::generarHermanosCsv($detalleHermanos);

            $periodoSlug = preg_replace('/[^a-zA-Z0-9_-]/', '_', $nombrePeriodo);
            $filename = "Control_Hermanos_{$periodoSlug}.csv";

            BinaryResponse::file($csv, 'text/csv; charset=UTF-8', $filename)->send();
        } catch (Throwable $e) {
            $this->handleError($e, 'Exportar Hermanos CSV');
        }
    }

    /**
     * Exporta el reporte de Control de Hermanos a PDF oficial ministerial.
     */
    public function exportarHermanosPdf(): void
    {
        $this->validarAcceso();

        try {
            $model = $this->modelo();
            $anios = $model->getAniosEscolares();
            $idAnio = $model->resolverAnio($anios, isset($_GET['anio']) ? (int) $_GET['anio'] : null);
            $nombrePeriodo = $model->nombrePeriodo($anios, $idAnio);

            $idGrado = isset($_GET['grado']) && (int) $_GET['grado'] > 0 ? (int) $_GET['grado'] : null;
            $idSeccion = isset($_GET['seccion']) && (int) $_GET['seccion'] > 0 ? (int) $_GET['seccion'] : null;
            $q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';

            $resumenHermanos = $model->getResumenRepresentantesHermanos($idAnio, $idGrado, $idSeccion);
            $detalleHermanos = $model->getDetalleControlHermanos($idAnio, $idGrado, $idSeccion, $q);

            $config = Configuracion::getAll();
            $periodo = ['id' => $idAnio, 'nombre' => $nombrePeriodo];

            $pdf = EstadisticaExport::generarHermanosPdf($config, $periodo, $detalleHermanos, $resumenHermanos);

            $periodoSlug = preg_replace('/[^a-zA-Z0-9_-]/', '_', $nombrePeriodo);
            $filename = "Control_Hermanos_{$periodoSlug}.pdf";
            $inline = isset($_GET['inline']) && (string) $_GET['inline'] === '1';

            BinaryResponse::pdf($pdf, $filename, $inline)->send();
        } catch (Throwable $e) {
            $this->handleError($e, 'Exportar Hermanos PDF');
        }
    }

    /**
     * Exporta el consolidado de Matrícula y Capacidad Escolar a CSV/Excel.
     */
    public function exportarMatriculaExcel(): void
    {
        $this->validarAcceso();

        try {
            $model = $this->modelo();
            $anios = $model->getAniosEscolares();
            $idAnio = $model->resolverAnio($anios, isset($_GET['anio']) ? (int) $_GET['anio'] : null);
            $nombrePeriodo = $model->nombrePeriodo($anios, $idAnio);

            $idGrado = isset($_GET['grado']) && (int) $_GET['grado'] > 0 ? (int) $_GET['grado'] : null;
            $idSeccion = isset($_GET['seccion']) && (int) $_GET['seccion'] > 0 ? (int) $_GET['seccion'] : null;

            $matriculaAulas = $model->getMatriculaCapacidadDetallada($idAnio, $idGrado, $idSeccion);
            $csv = EstadisticaExport::generarMatriculaCsv($matriculaAulas);

            $periodoSlug = preg_replace('/[^a-zA-Z0-9_-]/', '_', $nombrePeriodo);
            $filename = "Matricula_Capacidad_{$periodoSlug}.csv";

            BinaryResponse::file($csv, 'text/csv; charset=UTF-8', $filename)->send();
        } catch (Throwable $e) {
            $this->handleError($e, 'Exportar Matrícula CSV');
        }
    }
}
