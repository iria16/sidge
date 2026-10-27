<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\DirectivoDashboardModel;
use App\Models\ReporteModel;
use App\Support\AniosEscolares;
use App\Support\Configuracion;
use App\Support\ReportePersonalPdf;
use PDO;
use Throwable;
use function App\Traits\sigde_rol_clave;

/**
 * ReporteController
 * -----------------
 * Interfaz única del Centro de Reportes Institucionales para Directivo y Coordinador.
 * Centraliza la emisión oficial y consulta de:
 * 1. Estadístico General de Matrícula (Matriz MPPE por grado, edad y sexo).
 * 2. Nómina General de Estudiantes (con cédula, representante legal y contacto).
 * 3. Distribución Académica y Asignación de Aulas.
 * 4. Nómina y Directorio Institucional de Personal.
 * 5. Relación Oficial de Retiros y Deserciones del Período.
 */
class ReporteController extends BaseController
{
    protected string $viewPath  = 'reportes';
    protected string $routeName = 'reporte';

    protected function createModel(PDO $pdo): object
    {
        return new ReporteModel($pdo);
    }

    private function modelo(): ReporteModel
    {
        /** @var ReporteModel $model */
        $model = $this->getModel();
        return $model;
    }

    /**
     * Valida que el rol actual tenga acceso a reportes (directivo o coordinador).
     */
    private function validarAccesoReportes(): void
    {
        $rol = sigde_rol_clave($_SESSION['rol'] ?? '');
        if (!in_array($rol, ['directivo', 'coordinador'], true)) {
            $this->redirect('dashboard/index');
            exit;
        }
    }

    /**
     * Centro único de Reportes Institucionales.
     */
    public function index(): void
    {
        $this->validarAccesoReportes();

        try {
            $modelo = $this->modelo();
            $pdo = $this->conexion();

            $anios = $modelo->getAniosEscolares();
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

            // Tipo de reporte: estadistico_general | nomina_estudiantes | distribucion_aulas | personal_institucional | retiros_periodo
            $tiposValidos = [
                'estadistico_general',
                'nomina_estudiantes',
                'distribucion_aulas',
                'personal_institucional',
                'retiros_periodo',
            ];
            $tipo = isset($_GET['tipo']) && in_array($_GET['tipo'], $tiposValidos, true)
                ? $_GET['tipo']
                : 'estadistico_general';

            $q = trim((string) ($_GET['q'] ?? ''));
            $idGrado = isset($_GET['grado']) && (int) $_GET['grado'] > 0 ? (int) $_GET['grado'] : null;
            $idSeccion = isset($_GET['seccion']) && (int) $_GET['seccion'] > 0 ? (int) $_GET['seccion'] : null;

            $grados = $modelo->getGrados();
            $secciones = $modelo->getSecciones();

            $dashboardModel = new DirectivoDashboardModel($pdo);
            $metricasMatricula = $dashboardModel->getMetricasMatricula($idAnio);
            $metricasPersonal  = $dashboardModel->getMetricasPersonal();

            // Cargar datos según el reporte
            $datosReporte = [];
            $resumenRepresentantes = null;
            switch ($tipo) {
                case 'nomina_estudiantes':
                    $datosReporte = $modelo->getListadoEstudiantes($idAnio, $idGrado, $idSeccion);
                    if ($q !== '') {
                        $datosReporte = array_filter($datosReporte, static function (array $e) use ($q): bool {
                            return stripos((string) $e['nombre_completo'], $q) !== false
                                || stripos((string) ($e['numero_documento'] ?? ''), $q) !== false
                                || stripos((string) ($e['rep_nombre'] ?? ''), $q) !== false;
                        });
                    }
                    $resumenRepresentantes = $this->resumirRepresentantes($datosReporte);
                    break;

                case 'distribucion_aulas':
                    $datosReporte = $modelo->getDistribucionAcademica($idAnio);
                    if ($q !== '') {
                        $datosReporte = array_filter($datosReporte, static function (array $d) use ($q): bool {
                            return stripos((string) $d['grado'], $q) !== false
                                || stripos((string) $d['seccion'], $q) !== false
                                || stripos((string) ($d['docente_titular'] ?? ''), $q) !== false;
                        });
                    }
                    break;

                case 'personal_institucional':
                    $datosReporte = $this->filtrarPersonal($modelo->getNominaPersonal(), $q);
                    break;

                case 'retiros_periodo':
                    $datosReporte = $modelo->getRetirosPeriodo($idAnio);
                    if ($q !== '') {
                        $datosReporte = array_filter($datosReporte, static function (array $r) use ($q): bool {
                            return stripos((string) $r['nombre_completo'], $q) !== false
                                || stripos((string) ($r['motivo'] ?? ''), $q) !== false;
                        });
                    }
                    break;

                case 'estadistico_general':
                default:
                    $datosReporte = $modelo->getEstadisticoGeneral($idAnio);
                    break;
            }

            require __DIR__ . '/../Views/reportes/index.php';
        } catch (Throwable $e) {
            $this->handleError($e, 'Centro de Reportes');
        }
    }

    /**
     * Cuenta los casos de representación de las filas ya filtradas.
     *
     * Se calcula aquí y no en la vista porque es una lectura del reporte, no
     * presentación. Al usar las filas que ya pasaron por el buscador, los
     * conteos no se separan de lo que la secretaria está viendo.
     *
     * @param array<int, array<string, mixed>> $filas
     * @return array{total:int, mas_de_un:int, solo_fallecido:int, sin_rep:int}
     */
    private function resumirRepresentantes(array $filas): array
    {
        $resumen = [
            'total'          => count($filas),
            'mas_de_un'      => 0,
            'solo_fallecido' => 0,
            'sin_rep'        => 0,
        ];

        foreach ($filas as $fila) {
            $vigentes   = (int) ($fila['rep_vigentes'] ?? 0);
            $fallecidos = (int) ($fila['rep_fallecidos'] ?? 0);
            $total      = (int) ($fila['rep_total'] ?? 0);

            if ($vigentes > 1) {
                $resumen['mas_de_un']++;
            }
            if ($vigentes === 0 && $fallecidos > 0) {
                $resumen['solo_fallecido']++;
            }
            if ($total === 0) {
                $resumen['sin_rep']++;
            }
        }

        return $resumen;
    }

    /**
     * @param array<int, array<string, mixed>> $filas
     * @return array<int, array<string, mixed>>
     */
    private function filtrarPersonal(array $filas, string $q): array
    {
        if ($q === '') {
            return $filas;
        }

        return array_values(array_filter($filas, static function (array $p) use ($q): bool {
            return stripos((string) $p['nombre_completo'], $q) !== false
                || stripos((string) ($p['cargo'] ?? ''), $q) !== false
                || stripos((string) ($p['numero_documento'] ?? ''), $q) !== false;
        }));
    }

    /**
     * Exporta la planilla de personal en PDF.
     * Ruta: reporte/personalPdf (GET).
     */
    public function personalPdf(): void
    {
        $this->validarAccesoReportes();

        try {
            $q = trim((string) ($_GET['q'] ?? ''));
            $personal = $this->filtrarPersonal($this->modelo()->getNominaPersonal(), $q);

            $pdf = ReportePersonalPdf::generar(
                Configuracion::getAll(),
                $personal,
                ['q' => $q]
            );

            $nombreArchivo = 'planilla_personal_' . date('Ymd') . '.pdf';

            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
            header('Content-Length: ' . (string) strlen($pdf));

            echo $pdf;
            exit;
        } catch (Throwable $e) {
            $this->handleError($e, 'Reportes');
        }
    }

    /**
     * Redirecciones de compatibilidad hacia la interfaz única.
     */
    public function estudiantes(): void
    {
        $anio = (int) ($_GET['anio'] ?? 0);
        $this->redirect('reporte/index?tipo=nomina_estudiantes' . ($anio > 0 ? '&anio=' . $anio : ''));
    }

    public function distribucion(): void
    {
        $anio = (int) ($_GET['anio'] ?? 0);
        $this->redirect('reporte/index?tipo=distribucion_aulas' . ($anio > 0 ? '&anio=' . $anio : ''));
    }

    public function personal(): void
    {
        $this->redirect('reporte/index?tipo=personal_institucional');
    }
}
