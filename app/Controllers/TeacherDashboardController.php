<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\StudentsModel;
use App\Models\ResultadoAcademicoModel;
use App\Models\TeacherAssignmentModel;
use App\Support\Saludo;
use PDO;
use Throwable;
use function App\Traits\sigde_rol_clave;

/**
 * TeacherDashboardController
 *
 * Controlador del Dashboard del rol Docente.
 * Muestra el resumen del aula asignada, distribución por sexo y edad,
 * cumpleaños del mes / hoy y el avance de literales finales.
 *
 * Solo lectura: sin botones de escritura.
 * La sección del docente se resuelve exclusivamente desde la sesión activa.
 */
class TeacherDashboardController extends BaseController
{
    protected string $routeName = 'dashboard';
    protected string $viewPath  = 'docente';

    protected function createModel(PDO $pdo): ?object
    {
        return new StudentsModel($pdo);
    }

    /**
     * Punto de entrada principal al Dashboard Docente.
     */
    public function index(): void
    {
        // 1. Exigir rol docente
        $rol = sigde_rol_clave($_SESSION['rol'] ?? '');
        if ($rol !== 'docente') {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        // 2. Zona horaria de Venezuela y saludo según la hora de Caracas
        $saludo = Saludo::actual();

        $idUsuario = (int) ($this->idUsuarioActual() ?? 0);
        $nombreDocente = trim((string) ($_SESSION['nombre'] ?? 'Docente'));

        // 3. Resolver la sección activa del docente en el año escolar en curso
        $seccionActiva = null;
        try {
            $seccionActiva = (new TeacherAssignmentModel($this->conexion()))
                ->getSeccionActivaDeDocente($idUsuario);
        } catch (Throwable $e) {
            error_log('TeacherDashboardController: error al obtener seccion activa: ' . $e->getMessage());
        }

        $tieneSeccion = ($seccionActiva !== null);
        $idEstructura = $tieneSeccion ? (int) $seccionActiva['id_estructura_academica'] : 0;

        // Estructuras de datos por bloque
        $resumen = [
            'total'         => 0,
            'varones'       => 0,
            'pct_varones'   => 0.0,
            'hembras'       => 0,
            'pct_hembras'   => 0.0,
            'edad_promedio' => 0.0,
            'edad_min'      => null,
            'edad_max'      => null,
        ];

        $distribucion = [
            'total' => 0,
            'sexo'  => [
                'varones'     => 0,
                'pct_varones' => 0.0,
                'hembras'     => 0,
                'pct_hembras' => 0.0,
            ],
            'edades' => [],
        ];

        $cumpleanos = [
            'mes_nombre'      => date('F'),
            'cumpleanero_hoy' => null,
            'cumpleanos_mes'  => [],
        ];

        $literales = [
            'total'             => 0,
            'registrados'       => 0,
            'pendientes'        => 0,
            'porcentaje'        => 0.0,
            'literales_validos' => ResultadoAcademicoModel::LITERALES_VALIDOS,
            'escala_texto'      => implode(', ', ResultadoAcademicoModel::LITERALES_VALIDOS),
        ];

        $ingresos = [
            'total'           => 0,
            'ratificados'     => 0,
            'pct_ratificados' => 0.0,
            'nuevos'          => 0,
            'pct_nuevos'      => 0.0,
        ];

        $resultadoModel = new ResultadoAcademicoModel($this->conexion());

        if ($tieneSeccion && $idEstructura > 0) {
            /** @var StudentsModel $estudianteModel */
            $estudianteModel = $this->getModel();

            try {
                // Bloque 1: Resumen para stat-cards
                $resumen = $estudianteModel->getResumenDashboardDocente($idEstructura);

                // Bloque 2: Distribución por sexo y edad cumplida
                $distribucion = $estudianteModel->getDistribucionSeccion($idEstructura);

                // Bloque 3: Cumpleañero de hoy y lista del mes
                $cumpleanos = $estudianteModel->getCumpleanosDashboard($idEstructura);

                // Bloque 4: Avance de literales finales
                $literales = $resultadoModel->getAvanceLiteralesDocente($idEstructura);

                // Bloque 5: Nuevo ingreso y ratificados
                $ingresos = $estudianteModel->getIngresoYRatificados($idEstructura);
            } catch (Throwable $e) {
                error_log('TeacherDashboardController: error al cargar bloques del dashboard: ' . $e->getMessage());
            }
        }

        // 4. Determinar si se muestra la stat-card de literales finales
        $mostrarLiterales = $resultadoModel->debeMostrarAvanceLiterales(
            (int) ($literales['registrados'] ?? 0),
            $tieneSeccion ? ($seccionActiva['anio_cierre'] ?? null) : null
        );

        // 5. Renderizar vista app/Views/docente/dashboard.php
        $data = [
            'nombreDocente'    => $nombreDocente,
            'saludo'           => $saludo,
            'seccion'          => $seccionActiva,
            'tieneSeccion'     => $tieneSeccion,
            'resumen'          => $resumen,
            'distribucion'     => $distribucion,
            'cumpleanos'       => $cumpleanos,
            'literales'        => $literales,
            'ingresos'         => $ingresos,
            'mostrarLiterales' => $mostrarLiterales,
        ];

        extract($data);
        require __DIR__ . '/../Views/dashboard/docente/index.php';
    }
}
