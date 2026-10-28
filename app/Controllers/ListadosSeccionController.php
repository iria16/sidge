<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\BinaryResponse;
use App\Models\ListadosSeccionModel;
use App\Models\TeacherAssignmentModel;
use App\Support\Configuracion;
use App\Support\ListadoCsv;
use App\Support\ListadoPdf;
use PDO;
use function App\Traits\sigde_rol_clave;

/**
 * ListadosSeccionController
 * -------------------------
 * Controlador para la visualización, filtrado y generación de reportes
 * tabulares oficiales de la sección asignada al docente (Nómina Matriz,
 * Directorio de Contactos y Registro de Asistencia a Reuniones).
 */
class ListadosSeccionController extends BaseController
{
    protected string $viewPath = 'docente';
    protected string $routeName = 'listadosSeccion';

    protected function createModel(PDO $pdo): object
    {
        return new ListadosSeccionModel($pdo);
    }

    private function listadosModel(): ListadosSeccionModel
    {
        /** @var ListadosSeccionModel $m */
        $m = $this->getModel();
        return $m;
    }

    /**
     * Determina si la petición actual es asíncrona (AJAX) o solicita un payload JSON.
     */
    private function esPeticionAjax(): bool
    {
        return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (isset($_GET['ajax']) && (string) $_GET['ajax'] === '1')
            || (isset($_SERVER['HTTP_ACCEPT']) && str_contains(strtolower($_SERVER['HTTP_ACCEPT']), 'application/json'));
    }

    /**
     * Resuelve la sección objetivo y valida permisos según el rol del usuario.
     */
    private function resolverSeccionObjetivo(): ?array
    {
        $idUsuario = (int) ($this->idUsuarioActual() ?? 0);
        $rol = sigde_rol_clave($_SESSION['rol'] ?? '');
        $idEstructura = (int) ($_GET['id'] ?? 0);

        // Si es docente y no se pasó ID, autodetectar su sección activa
        if ($idEstructura <= 0 && $rol === 'docente') {
            $seccionActiva = (new TeacherAssignmentModel($this->conexion()))
                ->getSeccionActivaDeDocente($idUsuario);
            $idEstructura = (int) ($seccionActiva['id_estructura_academica'] ?? 0);
        }

        // Si es rol directivo/admin y no se pasó ID, seleccionar la primera sección del año activo
        if ($idEstructura <= 0 && in_array($rol, ['admin', 'directivo', 'coordinador'], true)) {
            $seccionesDisponibles = $this->listadosModel()->getSeccionesDisponibles($idUsuario, $rol);
            if (!empty($seccionesDisponibles)) {
                $idEstructura = (int) $seccionesDisponibles[0]['id'];
            }
        }

        if ($idEstructura <= 0) {
            return null;
        }

        $seccion = $this->listadosModel()->getDetalleSeccion($idEstructura);
        if (!$seccion) {
            return null;
        }

        // Si es docente, comprobar que sea la sección asignada y pertenezca al período activo
        if ($rol === 'docente') {
            $idAnioActivo = $this->listadosModel()->getAnioEnCursoId();
            if ((int) $seccion['id_anio_escolar'] !== (int) $idAnioActivo) {
                return null;
            }

            if (!$this->listadosModel()->usuarioTieneSeccion($idUsuario, $idEstructura)) {
                return null;
            }
        }

        return $seccion;
    }

    /**
     * Pantalla interactiva principal: visor de hoja digital, filtros y pestañas.
     */
    public function index(): void
    {
        $idUsuario = (int) ($this->idUsuarioActual() ?? 0);
        $rol = sigde_rol_clave($_SESSION['rol'] ?? '');

        $seccion = $this->resolverSeccionObjetivo();
        if (!$seccion) {
            $this->setFlash('error', 'No tiene una sección activa asignada o no cuenta con permisos para consultarla.');
            $this->redirect('dashboard/' . ($rol ?: 'docente'));
            return;
        }

        $idEstructura = (int) $seccion['id'];

        // Filtros solicitados
        $formato = strtolower(trim((string) ($_GET['formato'] ?? 'nomina')));
        if (!in_array($formato, ['nomina', 'contactos', 'asistencia'], true)) {
            $formato = 'nomina';
        }

        $orden = strtolower(trim((string) ($_GET['orden'] ?? 'apellido')));
        if (!in_array($orden, ['apellido', 'cedula', 'edad'], true)) {
            $orden = 'apellido';
        }

        $genero = strtoupper(trim((string) ($_GET['genero'] ?? 'todos')));
        if (!in_array($genero, ['TODOS', 'M', 'F'], true)) {
            $genero = 'TODOS';
        }

        // Toggles de columnas
        $mostrarCedulaEsc = isset($_GET['col_cedula']) ? (bool) $_GET['col_cedula'] : true;
        $mostrarTelefono  = isset($_GET['col_telefono']) ? (bool) $_GET['col_telefono'] : true;
        $mostrarRetiro    = isset($_GET['col_retiro']) ? (bool) $_GET['col_retiro'] : false;

        $opcionesColumnas = [
            'mostrar_cedula_escolar' => $mostrarCedulaEsc,
            'mostrar_telefono'       => $mostrarTelefono,
            'mostrar_retiro'         => $mostrarRetiro,
        ];

        // Obtener datos
        $estudiantes = $this->listadosModel()->getEstudiantesSeccion($idEstructura, $genero, $orden);
        $seccionesDisponibles = $this->listadosModel()->getSeccionesDisponibles($idUsuario, $rol);
        // Respuesta JSON si la petición es asíncrona o solicita datos directamente
        if ($this->esPeticionAjax()) {
            $totalEst  = count($estudiantes);
            $totalFem  = 0;
            $totalMasc = 0;
            foreach ($estudiantes as $e) {
                $gen = strtoupper((string) ($e['genero'] ?? ''));
                if ($gen === 'F') {
                    $totalFem++;
                } elseif ($gen === 'M') {
                    $totalMasc++;
                }
            }

            $this->jsonResponse(true, [
                'seccion'           => $seccion,
                'estudiantes'       => $estudiantes,
                'total'             => $totalEst,
                'total_femenino'    => $totalFem,
                'total_masculino'   => $totalMasc,
                'formato'           => $formato,
                'orden'             => $orden,
                'genero'            => $genero,
                'opciones_columnas' => $opcionesColumnas,
            ], 'Datos de sección cargados exitosamente.');
        }

        $config = Configuracion::getAll();
        $flash  = $this->getFlashMessages();

        $data = [
            'seccion'              => $seccion,
            'estudiantes'          => $estudiantes,
            'formatoActual'        => $formato,
            'ordenActual'          => $orden,
            'generoActual'         => $genero,
            'opcionesColumnas'     => $opcionesColumnas,
            'seccionesDisponibles' => $seccionesDisponibles,
            'config'               => $config,
            'rolUsuario'           => $rol,
            'esDocente'            => ($rol === 'docente'),
            'flash_error'          => $flash['error'],
            'flash_success'        => $flash['success'],
            'flash_info'           => $flash['info'],
        ];

        extract($data);
        require __DIR__ . '/../Views/' . $this->viewPath . '/listados_actas.php';
    }

    /**
     * Genera y descarga el archivo PDF oficial compilado con Dompdf.
     */
    public function descargarPdf(): void
    {
        $seccion = $this->resolverSeccionObjetivo();
        if (!$seccion) {
            $this->prohibido('No tiene una sección activa asignada o no cuenta con permisos para consultarla.');
        }

        $idEstructura = (int) $seccion['id'];

        $formato = strtolower(trim((string) ($_GET['formato'] ?? 'nomina')));
        if (!in_array($formato, ['nomina', 'contactos', 'asistencia'], true)) {
            $formato = 'nomina';
        }

        $orden = strtolower(trim((string) ($_GET['orden'] ?? 'apellido')));
        if (!in_array($orden, ['apellido', 'cedula', 'edad'], true)) {
            $orden = 'apellido';
        }

        $genero = strtoupper(trim((string) ($_GET['genero'] ?? 'todos')));
        if (!in_array($genero, ['TODOS', 'M', 'F'], true)) {
            $genero = 'TODOS';
        }

        $mostrarCedulaEsc = isset($_GET['col_cedula']) ? (bool) $_GET['col_cedula'] : true;
        $mostrarTelefono  = isset($_GET['col_telefono']) ? (bool) $_GET['col_telefono'] : true;
        $mostrarRetiro    = isset($_GET['col_retiro']) ? (bool) $_GET['col_retiro'] : false;

        $opcionesColumnas = [
            'mostrar_cedula_escolar' => $mostrarCedulaEsc,
            'mostrar_telefono'       => $mostrarTelefono,
            'mostrar_retiro'         => $mostrarRetiro,
        ];

        $estudiantes = $this->listadosModel()->getEstudiantesSeccion($idEstructura, $genero, $orden);
        $config = Configuracion::getAll();

        $pdfBytes = ListadoPdf::generar(
            $config,
            $seccion,
            $estudiantes,
            $formato,
            $opcionesColumnas
        );

        $gradoSlug = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) ($seccion['grado'] ?? 'Grado'));
        $secSlug   = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) ($seccion['seccion'] ?? 'Seccion'));
        $anioSlug  = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) ($seccion['anio_escolar'] ?? 'Periodo'));
        $tipoSlug  = ucfirst($formato);

        $filename = "{$tipoSlug}_{$gradoSlug}_{$secSlug}_{$anioSlug}.pdf";

        $inline = isset($_GET['inline']) && (string) $_GET['inline'] === '1';

        BinaryResponse::pdf($pdfBytes, $filename, $inline)->send();
    }

    /**
     * Exporta los datos tabulares en formato CSV codificado en UTF-8 con BOM para Excel.
     */
    public function exportarCsv(): void
    {
        $seccion = $this->resolverSeccionObjetivo();
        if (!$seccion) {
            $this->prohibido('No tiene una sección activa asignada o no cuenta con permisos para consultarla.');
        }

        $idEstructura = (int) $seccion['id'];

        $formato = strtolower(trim((string) ($_GET['formato'] ?? 'nomina')));
        if (!in_array($formato, ['nomina', 'contactos', 'asistencia'], true)) {
            $formato = 'nomina';
        }

        $orden = strtolower(trim((string) ($_GET['orden'] ?? 'apellido')));
        if (!in_array($orden, ['apellido', 'cedula', 'edad'], true)) {
            $orden = 'apellido';
        }

        $genero = strtoupper(trim((string) ($_GET['genero'] ?? 'todos')));
        if (!in_array($genero, ['TODOS', 'M', 'F'], true)) {
            $genero = 'TODOS';
        }

        $estudiantes = $this->listadosModel()->getEstudiantesSeccion($idEstructura, $genero, $orden);

        $csvContent = ListadoCsv::generar($estudiantes, $formato);

        $gradoSlug = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) ($seccion['grado'] ?? 'Grado'));
        $secSlug   = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) ($seccion['seccion'] ?? 'Seccion'));
        $anioSlug  = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) ($seccion['anio_escolar'] ?? 'Periodo'));
        $filename  = "Reporte_{$formato}_{$gradoSlug}_{$secSlug}_{$anioSlug}.csv";

        BinaryResponse::file($csvContent, 'text/csv; charset=UTF-8', $filename)->send();
    }
}
