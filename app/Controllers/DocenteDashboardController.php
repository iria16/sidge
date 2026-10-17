<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\EstudianteModel;
use App\Models\ResultadoAcademicoModel;
use App\Models\TeacherAssignmentModel;
use PDO;
use Throwable;
use function App\Traits\sigde_rol_clave;

/**
 * DocenteDashboardController
 *
 * Controlador del Dashboard del rol Docente.
 * Muestra el resumen del aula asignada, distribución por sexo y edad,
 * cumpleaños del mes / hoy y el avance de literales finales.
 *
 * Solo lectura: sin botones de escritura.
 * La sección del docente se resuelve exclusivamente desde la sesión activa.
 */
class DocenteDashboardController extends BaseController
{
    protected string $routeName = 'dashboard';
    protected string $viewPath  = 'docente';

    protected function createModel(PDO $pdo): ?object
    {
        return new EstudianteModel($pdo);
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

        // 2. Fijar zona horaria de Venezuela para consistencia de fechas y cumpleaños
        date_default_timezone_set('America/Caracas');

        $idUsuario = (int) ($this->idUsuarioActual() ?? 0);
        $nombreDocente = trim((string) ($_SESSION['nombre'] ?? 'Docente'));

        /* Saludo según la hora de Caracas. La zona ya quedó fijada arriba, así que
           la hora que se mira es la del sitio y no la del servidor: un docente en
           Venezuela no debe leer "Buenas noches" porque la máquina esté en UTC.
           Los cortes son los mismos que usa el saludo de WhatsApp del módulo de
           listados (antes de las 12, antes de las 19, después). */
        $horaCaracas = (int) (new \DateTimeImmutable('now'))->format('G');
        $saludo = match (true) {
            $horaCaracas < 12 => 'Buenos días',
            $horaCaracas < 19 => 'Buenas tardes',
            default           => 'Buenas noches',
        };

        // 3. Resolver la sección activa del docente en el año escolar en curso
        $seccionActiva = null;
        try {
            $seccionActiva = (new TeacherAssignmentModel($this->conexion()))
                ->getSeccionActivaDeDocente($idUsuario);
        } catch (Throwable $e) {
            error_log('DocenteDashboardController: error al obtener seccion activa: ' . $e->getMessage());
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

        if ($tieneSeccion && $idEstructura > 0) {
            /** @var EstudianteModel $estudianteModel */
            $estudianteModel = $this->getModel();
            $resultadoModel  = new ResultadoAcademicoModel($this->conexion());

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
                error_log('DocenteDashboardController: error al cargar bloques del dashboard: ' . $e->getMessage());
            }
        }

        // 4. Determinar si se muestra la stat-card de literales finales
        // Regla híbrida:
        // - Si el docente ya propuso/registró al menos 1 literal, se muestra siempre.
        // - O si la fecha de hoy se encuentra dentro de los N días previos al cierre del año escolar
        //   (sin límite superior: si el año escolar sigue ACTIVO, se mantiene visible incluso tras anio_cierre).
        // - Si anio_cierre es NULL o inválido, la condición por fecha se evalúa como false de forma segura.
        $mostrarLiterales = false;
        $registrados = (int) ($literales['registrados'] ?? 0);

        if ($registrados > 0) {
            $mostrarLiterales = true;
        } elseif (!empty($seccionActiva['anio_cierre'])) {
            $tsCierre = strtotime((string) $seccionActiva['anio_cierre']);
            if ($tsCierre !== false) {
                $tsInicioVentana = strtotime('-' . ResultadoAcademicoModel::DIAS_AVISO_CIERRE_LITERALES . ' days', $tsCierre);
                $hoy = strtotime(date('Y-m-d'));
                if ($hoy >= $tsInicioVentana) {
                    $mostrarLiterales = true;
                }
            }
        }

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
        require __DIR__ . '/../Views/docente/dashboard.php';
    }
}
