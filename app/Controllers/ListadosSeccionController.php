<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\ListadosSeccionModel;
use App\Models\TeacherAssignmentModel;
use App\Support\AniosEscolares;
use App\Support\Configuracion;
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
            $idAnioActivo = AniosEscolares::idEnCurso($this->conexion()) ?: 0;
            if ((int) $seccion['id_anio_escolar'] !== (int) $idAnioActivo) {
                return null;
            }

            $stmtDoc = $this->conexion()->prepare(
                "SELECT 1 FROM asignacion_docente ad
                 INNER JOIN docente doc ON doc.id_docente = ad.id_docente
                 INNER JOIN empleado emp ON emp.id_empleado = doc.id_empleado
                 INNER JOIN usuario u ON u.id_persona = emp.id_persona
                 WHERE u.id_usuario = :id_usuario
                   AND ad.id_estructura_academica = :id_estructura
                   AND (ad.fecha_fin IS NULL OR ad.fecha_fin >= CURDATE())
                 LIMIT 1"
            );
            $stmtDoc->execute([
                'id_usuario'    => $idUsuario,
                'id_estructura' => $idEstructura,
            ]);

            if (!$stmtDoc->fetchColumn()) {
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

        $config = Configuracion::getAll($this->conexion());
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
            http_response_code(403);
            die("<h1>403 - Acceso no autorizado o sección no encontrada</h1>");
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
        $config = Configuracion::getAll($this->conexion());

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

        $disposition = (isset($_GET['inline']) && (string) $_GET['inline'] === '1') ? 'inline' : 'attachment';
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdfBytes));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        echo $pdfBytes;
        exit;
    }

    /**
     * Exporta los datos tabulares en formato CSV codificado en UTF-8 con BOM para Excel.
     */
    public function exportarCsv(): void
    {
        $seccion = $this->resolverSeccionObjetivo();
        if (!$seccion) {
            http_response_code(403);
            die("<h1>403 - Acceso no autorizado</h1>");
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

        $delimitador = ';';
        $salida = fopen('php://memory', 'w');

        // BOM UTF-8 para que Excel abra acentos y caracteres especiales sin distorsión
        fprintf($salida, chr(0xEF) . chr(0xBB) . chr(0xBF));

        if ($formato === 'asistencia') {
            fputcsv($salida, ['N°', 'Estudiante (Apellidos y Nombres)', 'Documento Identidad', 'Representante Legal', 'C.I. Representante', 'Firma', 'Observaciones'], $delimitador);
            foreach ($estudiantes as $idx => $e) {
                fputcsv($salida, [
                    $idx + 1,
                    $e['apellidos_nombres'],
                    $e['documento_etiqueta'],
                    $e['rep_apellidos_nombres'] ?: 'Sin asignar',
                    $e['rep_documento_etiqueta'],
                    '',
                    ''
                ], $delimitador);
            }
        } elseif ($formato === 'contactos') {
            fputcsv($salida, ['N°', 'Estudiante', 'Documento', 'Representante Legal', 'Parentesco', 'Teléfono Celular', 'Teléfono Alternativo', 'Correo Electrónico'], $delimitador);
            foreach ($estudiantes as $idx => $e) {
                $telAlt = trim((string) ($e['numero_telefono_alternativo'] ?? ''));
                if ($telAlt !== '' && !empty($e['codigo_telefono_alternativo'])) {
                    $telAlt = "{$e['codigo_telefono_alternativo']}-{$telAlt}";
                }
                fputcsv($salida, [
                    $idx + 1,
                    $e['apellidos_nombres'],
                    $e['documento_etiqueta'],
                    $e['rep_apellidos_nombres'] ?: 'Sin asignar',
                    $e['parentesco'],
                    $e['telefono_completo'],
                    $telAlt ?: 'No registrado',
                    $e['correo_electronico'] ?: 'No registrado'
                ], $delimitador);
            }
        } else {
            // nomina
            fputcsv($salida, ['N°', 'Cédula / Documento', 'Apellidos y Nombres', 'Edad', 'Género', 'Representante Legal', 'C.I. Representante', 'Teléfono Celular', 'Autorizado Retiro'], $delimitador);
            foreach ($estudiantes as $idx => $e) {
                fputcsv($salida, [
                    $idx + 1,
                    $e['documento_etiqueta'],
                    $e['apellidos_nombres'],
                    $e['edad'],
                    $e['genero'],
                    $e['rep_apellidos_nombres'] ?: 'Sin asignar',
                    $e['rep_documento_etiqueta'],
                    $e['telefono_completo'],
                    !empty($e['es_autorizado_retiro']) ? 'SÍ' : 'NO'
                ], $delimitador);
            }
        }

        fseek($salida, 0);
        $csvContent = stream_get_contents($salida);
        fclose($salida);

        $gradoSlug = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) ($seccion['grado'] ?? 'Grado'));
        $secSlug   = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) ($seccion['seccion'] ?? 'Seccion'));
        $anioSlug  = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) ($seccion['anio_escolar'] ?? 'Periodo'));
        $filename  = "Reporte_{$formato}_{$gradoSlug}_{$secSlug}_{$anioSlug}.csv";

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($csvContent));
        header('Cache-Control: private, max-age=0, must-revalidate');

        echo $csvContent;
        exit;
    }
}
