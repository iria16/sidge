<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AcademicYearModel;
use App\Models\BitacoraModel;
use App\Models\ResultadoAcademicoModel;
use PDO;
use Throwable;
use function App\Traits\sigde_rol_clave;

/**
 * ResultadoAcademicoController
 * -----------------------------------------------------------------------------
 * Controlador para la gestión de resultados académicos de fin de año
 * (Promoción y No Promoción).
 *
 * Flujo en dos fases:
 * - Docentes: Proponen el literal pedagógico y resultado preliminar (PENDIENTE).
 * - Directivos/Admin: Validan y confirman oficialmente la promoción.
 */
class ResultadoAcademicoController extends BaseController
{
    protected string $viewPath  = 'resultado_academico';
    protected string $routeName = 'resultadoAcademico';

    private function modelo(): ResultadoAcademicoModel
    {
        /** @var ResultadoAcademicoModel $model */
        $model = $this->getModel();

        return $model;
    }

    protected function createModel(PDO $pdo): object
    {
        return new ResultadoAcademicoModel($pdo);
    }

    /**
     * Bandeja principal de resultados académicos:
     * Lista de secciones agrupadas por año escolar.
     */
    public function index(): void
    {
        $idUsuario = (int) ($this->idUsuarioActual() ?? 0);
        $rol = sigde_rol_clave($_SESSION['rol'] ?? '');

        $academicYearModel = new AcademicYearModel($this->conexion());
        $idAnioActivo = $academicYearModel->getActiveId();

        if ($rol === 'docente') {
            // El docente únicamente puede visualizar el período escolar activo
            $idAnio = $idAnioActivo;
            $todosAnios = $academicYearModel->getAll();
            $anios = array_values(array_filter(
                $todosAnios,
                static fn ($a): bool => (int) ($a['id_anio_escolar'] ?? $a['id'] ?? 0) === (int) $idAnioActivo
            ));
        } else {
            $idAnio = isset($_GET['anio']) ? (int) $_GET['anio'] : $idAnioActivo;
            $anios = $academicYearModel->getAll();
        }

        $secciones = $this->modelo()->getSeccionesPorAnio($idAnio, $idUsuario, $rol);

        $nombreAnioActivo = $academicYearModel->getActiveName() ?: 'En curso';
        $nombreUsuarioActual = $_SESSION['nombre'] ?? '';

        $flash = $this->getFlashMessages();
        $data = [
            'secciones'           => $secciones,
            'anios'               => $anios,
            'idAnio'              => $idAnio,
            'nombreAnioActivo'    => $nombreAnioActivo,
            'nombreUsuarioActual' => $nombreUsuarioActual,
            'rolUsuario'          => $rol,
            'flash_error'         => $flash['error'],
            'flash_success'       => $flash['success'],
        ];

        extract($data);
        require __DIR__ . '/../Views/' . $this->viewPath . '/index.php';
    }

    /**
     * Vista de nómina de estudiantes de una sección específica
     * para carga o confirmación de resultados.
     */
    public function seccion(): void
    {
        $idUsuario = (int) ($this->idUsuarioActual() ?? 0);
        $rol = sigde_rol_clave($_SESSION['rol'] ?? '');

        $idEstructura = (int) ($_GET['id'] ?? 0);
        if ($idEstructura <= 0 && $rol === 'docente') {
            $seccionActiva = (new \App\Models\TeacherAssignmentModel($this->conexion()))
                ->getSeccionActivaDeDocente($idUsuario);
            $idEstructura = (int) ($seccionActiva['id_estructura_academica'] ?? 0);
        }

        if ($idEstructura <= 0) {
            $this->setFlash('error', 'Sección no válida.');
            $this->redirect('index');
        }

        $detalle = $this->modelo()->getDetalleSeccion($idEstructura, $idUsuario, $rol);
        if (!$detalle) {
            $this->setFlash('error', 'No tiene permisos para acceder a esta sección o la sección no pertenece al período escolar activo.');
            $this->redirect('index');
        }

        $filtrosIniciales = ['pendiente' => 'PENDIENTE', 'cargados' => 'CARGADOS'];
        $estadoSolicitado = strtolower(trim((string) ($_GET['estado'] ?? '')));
        $filtroEstadoInicial = $filtrosIniciales[$estadoSolicitado] ?? 'todos';

        $flash = $this->getFlashMessages();
        $esDocenteAsignada = ($rol === 'docente') && $this->modelo()->esDocenteAsignada($idUsuario, $idEstructura);
        $data = [
            'detalle'             => $detalle,
            'rolUsuario'          => $rol,
            'esDirectivo'         => $rol === 'directivo',
            'esDocenteAsignada'   => $esDocenteAsignada,
            /* El coordinador ve la nómina y el resultado pero no propone: el
               literal lo pone el docente de la sección. Sin esto la vista le
               pintaría un botón de guardar sobre una ruta que ya no le
               pertenece, y el error le saltaría al pulsarlo. */
            'esConsulta'          => $this->esRolConsulta(),
            'anioCerrado'         => strtoupper((string) ($detalle['estado_anio'] ?? '')) === 'CERRADO',
            'literales'           => ResultadoAcademicoModel::LITERALES_VALIDOS,
            'literalesPromueven'  => ResultadoAcademicoModel::LITERALES_PROMUEVEN,
            'literalesExigenObservacion' => ResultadoAcademicoModel::LITERALES_EXIGEN_OBSERVACION,
            'filtroEstadoInicial' => $filtroEstadoInicial,
            'flash_error'         => $flash['error'],
            'flash_success'       => $flash['success'],
            'flash_info'          => $flash['info'],
        ];

        extract($data);
        require __DIR__ . '/../Views/' . $this->viewPath . '/seccion.php';
    }

    /**
     * Carga de propuesta académica por el docente (o directivo).
     * El estado de los resultados se guarda como PENDIENTE de confirmación.
     */
    public function guardarPropuesta(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->setFlash('error', 'Método no permitido.');
            $this->redirect('index');
        }

        $idEstructura = (int) ($_POST['id_estructura_academica'] ?? 0);
        if ($idEstructura <= 0) {
            $this->setFlash('error', 'Estructura académica inválida.');
            $this->redirect('index');
        }

        $estudiantesData = $_POST['estudiantes'] ?? [];
        if (!is_array($estudiantesData)) {
            $estudiantesData = [];
        }

        $idUsuario = (int) ($this->idUsuarioActual() ?? 0);
        $rol = sigde_rol_clave($_SESSION['rol'] ?? '');

        // Si es petición AJAX
        $esAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        try {
            $res = $this->modelo()->guardarPropuesta($idEstructura, $estudiantesData, $idUsuario, $rol);

            $r = $res['resumen'] ?? [
                'guardados'           => 0,
                'promovidos'          => 0,
                'no_promovidos'       => 0,
                'sin_literal'         => 0,
                'omitidos'            => 0,
            ];

            /* La nómina se guarda por AJAX: el front necesita saber qué filas
               quedaron bien y cuáles no para pintar el estado de cada una. */
            $datosAjax = [
                'guardados_ids' => $r['guardados_ids'] ?? [],
                'errores'       => $r['errores'] ?? [],
            ];

            /* Cada guardado de literales queda en la bitácora: quién lo hizo,
               sobre qué sección, y cuántas filas de cada tipo. */
            if ($res['ok'] && $idUsuario > 0) {
                (new BitacoraModel($this->conexion()))->registrar(
                    $idUsuario,
                    sprintf(
                        'LITERALES: %d guardados (%d prom, %d no prom, %d sin lit) · %d omitidos · estruct %d',
                        (int) ($r['guardados'] ?? 0),
                        (int) ($r['promovidos'] ?? 0),
                        (int) ($r['no_promovidos'] ?? 0),
                        (int) ($r['sin_literal'] ?? 0),
                        (int) ($r['omitidos'] ?? 0),
                        (int) $idEstructura
                    ),
                    'Resultado académico'
                );
            }

            if (!$res['ok']) {
                if ($esAjax) {
                    $this->jsonResponse(false, $datosAjax, $res['mensaje'], 422);
                }
                $this->setFlash('error', $res['mensaje']);
                $this->redirect('seccion', ['id' => $idEstructura]);
            }

            if ($esAjax) {
                $this->jsonResponse(true, $datosAjax, $res['mensaje'], 200);
            }

            $tipoFlash = ($r['guardados'] ?? 0) > 0 ? 'success' : 'info';
            $this->setFlash($tipoFlash, $res['mensaje']);
            $this->redirect('seccion', ['id' => $idEstructura]);
        } catch (Throwable $e) {
            if ($esAjax) {
                $this->jsonError($e, 'Guardar propuesta académica');
            }
            $this->handleError($e, static::class . '@guardarPropuesta');
        }
    }

    /**
     * Confirmación oficial de resultados por la Directora / Directivo.
     * Pasa el resultado a PROMOVIDO o NO_PROMOVIDO y sella fecha y usuario confirmador.
     */
    public function confirmar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->setFlash('error', 'Método no permitido.');
            $this->redirect('index');
        }

        $esAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        $rol = sigde_rol_clave($_SESSION['rol'] ?? '');
        /* Confirmar es escribir: solo lo hace el directivo. El rol llega normalizado
           desde sigde_rol_clave, y la comparación es exacta a propósito: una
           variante mal guardada en la base no debe abrir la puerta. */
        if ($rol !== 'directivo') {
            if ($esAjax) {
                $this->jsonResponse(false, null, 'Solo el personal directivo está autorizado para confirmar oficialmente los resultados académicos.', 403);
                return;
            }
            $this->setFlash('error', 'Solo el personal directivo está autorizado para confirmar oficialmente los resultados académicos.');
            $this->redirect('index');
            return;
        }

        $idEstructura = (int) ($_POST['id_estructura_academica'] ?? 0);
        if ($idEstructura <= 0) {
            $this->setFlash('error', 'Estructura académica inválida.');
            $this->redirect('index');
            return;
        }

        $estudiantesData = $_POST['estudiantes'] ?? [];
        if (!is_array($estudiantesData)) {
            $estudiantesData = [];
        }

        $idUsuario = (int) ($this->idUsuarioActual() ?? 0);

        try {
            $res = $this->modelo()->confirmarResultados($idEstructura, $estudiantesData, $idUsuario, $rol);

            $r = $res['resumen'] ?? [
                'confirmados'          => 0,
                'promovidos'           => 0,
                'no_promovidos'        => 0,
                'omitidos'             => 0,
            ];

            if ($res['ok'] && $idUsuario > 0) {
                (new BitacoraModel($this->conexion()))->registrar(
                    $idUsuario,
                    sprintf(
                        'CONFIRMACION: %d confirmados (%d prom, %d no prom) · %d omitidos · estruct %d',
                        (int) ($r['confirmados'] ?? 0),
                        (int) ($r['promovidos'] ?? 0),
                        (int) ($r['no_promovidos'] ?? 0),
                        (int) ($r['omitidos'] ?? 0),
                        (int) $idEstructura
                    ),
                    'Resultado académico'
                );
            }

            if (!$res['ok']) {
                if ($esAjax) {
                    $this->jsonResponse(false, null, $res['mensaje'], 422);
                }
                $this->setFlash('error', $res['mensaje']);
                $this->redirect('seccion', ['id' => $idEstructura]);
            }

            if ($esAjax) {
                $this->jsonResponse(true, null, $res['mensaje'], 200);
            }

            $tipoFlash = ($r['confirmados'] ?? 0) > 0 ? 'success' : 'info';
            $this->setFlash($tipoFlash, $res['mensaje']);
            $this->redirect('seccion', ['id' => $idEstructura]);
        } catch (Throwable $e) {
            if ($esAjax) {
                $this->jsonError($e, 'Confirmar resultados académicos');
            }
            $this->handleError($e, static::class . '@confirmar');
        }
    }
}
