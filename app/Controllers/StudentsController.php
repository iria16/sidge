<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\BinaryResponse;
use App\Models\StudentsModel;
use App\Services\ExpedienteEstudianteService;
use PDO;
use Throwable;
use function App\Traits\sigde_rol_clave;

/**
 * Consulta de expedientes (consulta_academica/estudiantes).
 * Endpoints: estudiantes/index (GET, listado con filtros) y estudiantes/show/{id} (GET, expediente completo).
*/
class StudentsController extends ReadOnlyController
{
    protected string $viewPath  = 'consulta_academica/estudiantes';
    protected string $routeName = 'estudiantes';
    private ?ExpedienteEstudianteService $expedientes = null;

    protected function createModel(PDO $pdo): object
    {
        return new StudentsModel($pdo);
    }

    private function servicioExpedientes(): ExpedienteEstudianteService
    {
        return $this->expedientes ??= new ExpedienteEstudianteService($this->conexion());
    }

    private function esSecretariaActual(): bool
    {
        return sigde_rol_clave($_SESSION['rol'] ?? '') === 'secretaria'
            || $this->turnoSecretariaActual() !== null;
    }

    protected function getFiltersFromRequest(): array
    {
        return $this->servicioExpedientes()->buildFilters(
            [
                'q'       => trim((string) ($_GET['q'] ?? '')),
                'anio'    => (int) ($_GET['anio'] ?? 0),
                'grado'   => trim((string) ($_GET['grado'] ?? '')),
                'seccion' => trim((string) ($_GET['seccion'] ?? '')),
                'estado'  => (string) ($_GET['estado'] ?? ''),
            ],
            $this->turnoSecretariaActual(),
            $this->docenteActualId(),
            $this->esSecretariaActual()
        );
    }

    protected function getExtraIndexData(array $items, array $filters): array
    {
        return $this->servicioExpedientes()->getIndexData(
            $items,
            $filters,
            $this->turnoSecretariaActual(),
            $this->docenteActualId(),
            $this->esSecretariaActual()
        );
    }

    public function show(): void
    {
        try {
            $id = (int) ($_GET['id'] ?? 0);
            $esDocente = $this->docenteActualId() !== null;
            $esSecretaria = $this->esSecretariaActual();
            $turno = $this->turnoSecretariaActual();
            $docenteId = $this->docenteActualId();
            $servicio = $this->servicioExpedientes();
            $base = $servicio->getEstudiante(
                $id,
                (int) ($_GET['anio'] ?? 0),
                $turno,
                $docenteId,
                $esDocente || $esSecretaria
            );
            if ($base === null) {
                $this->setFlash('error', 'El expediente solicitado no existe.');
                $this->redirect('index');
            }
            $estudiante = $base['estudiante'];

            if ($esSecretaria && ($estudiante['estado'] ?? '') !== 'activo') {
                $this->setFlash('error', 'Solo la Dirección puede consultar expedientes de estudiantes retirados o egresados.');
                $this->redirect('index');
            }
            $anioSeleccionado = $base['anioSeleccionado'];
            extract($servicio->getDatosExpediente($id, $turno, $esDocente), EXTR_SKIP);
            $puedeEditar = sigde_rol_clave($_SESSION['rol'] ?? '') === 'secretaria';

            require __DIR__ . "/../Views/{$this->viewPath}/show.php";
        } catch (Throwable $e) {
            $this->handleError($e, static::class);
        }
    }

    /**
 * Actualiza los recaudos de un estudiante desde su ficha.
 *
 * Es la vía para el caso normal: el representante entrega un documento días después de la
 * inscripción, y sin esto el requisito quedaba marcado como pendiente para siempre.
 *
 * Comprueba el turno con secretariaTieneAccesoAEstudiante() y no solo la ruta: la ficha
 * se puede pedir con cualquier id por URL, así que sin ese control una secretaria del
 * turno de la tarde podría marcar recaudos de un alumno de la mañana.
*/
    public function guardarRecaudos(): void
    {
        try {
            $idEstudiante = (int) ($_POST['id_estudiante'] ?? 0);
            if ($idEstudiante <= 0) {
                $this->jsonResponse(false, null, 'Estudiante no válido.', 422);
            }
            if (!$this->secretariaTieneAccesoAEstudiante($idEstudiante)) {
                $this->jsonResponse(false, null,
                    'Ese estudiante no pertenece a su turno.', 403);
            }

            $idUsuario = (int) ($this->idUsuarioActual() ?? 0);
            $tocados = $this->servicioExpedientes()->actualizarRecaudos(
                $idEstudiante,
                $this->decodificarRecaudos($_POST['recaudos'] ?? []),
                $idUsuario
            );

            $this->jsonResponse(true, ['actualizados' => $tocados],
                $tocados > 0
                    ? 'Recaudos actualizados correctamente.'
                    : 'No hubo cambios que guardar.');
        } catch (Throwable $e) {
            $this->jsonError($e, static::class);
        }
    }

    /**
 * Normaliza el bloque de recaudos que llega del formulario de actualización.
 *
 * Mismo criterio que en la inscripción: aquí se limpian los tipos, pero decidir si un id
 * es válido lo hace el modelo contra el catálogo. Sanear el texto con normalizarEspacios
 * (que quita HTML) es lo que evita guardar un <script> en la observación.
 *
 * @return array<int|string, array{consignado: int, observacion: string}>
*/
    protected function decodificarRecaudos($crudo): array
    {
        if (!is_array($crudo)) {
            return [];
        }

        $recaudos = [];
        foreach ($crudo as $id => $estado) {
            $id = (string) $id;
            if (!preg_match('/^(?:[1-9]\d*|tipo_[1-9]\d*)$/', $id) || !is_array($estado)) {
                continue;
            }
            $consignado = $estado['consignado'] ?? 0;
            $recaudos[$id] = [
                'consignado'  => in_array($consignado, [1, '1', true], true) ? 1 : 0,
                'observacion' => mb_substr(
                    $this->normalizarEspacios((string) ($estado['observacion'] ?? '')),
                    0,
                    255
                ),
            ];
        }

        return $recaudos;
    }

    public function exportar(): void
    {
        try {
            $id = (int) ($_GET['id'] ?? 0);
            $esDocente = $this->docenteActualId() !== null;
            $esSecretaria = $this->esSecretariaActual();
            $turno = $this->turnoSecretariaActual();
            $docenteId = $this->docenteActualId();
            $servicio = $this->servicioExpedientes();
            $base = $servicio->getEstudiante(
                $id,
                (int) ($_GET['anio'] ?? 0),
                $turno,
                $docenteId,
                $esDocente || $esSecretaria
            );
            if ($base === null) {
                $this->renderHttpError(404, '404 · Expediente no encontrado', 'No se encontró el expediente solicitado.');
            }
            $estudiante = $base['estudiante'];
            if ($esSecretaria && ($estudiante['estado'] ?? '') !== 'activo') {
                $this->renderHttpError(403, '403 · Acceso denegado', 'Solo la Dirección puede exportar expedientes de estudiantes retirados o egresados.');
            }

            $pdfContent = $servicio->generarPdf($id, $estudiante, $turno, $esDocente);
            $nombreArchivo = 'expediente-' . preg_replace('/[^A-Za-z0-9-]+/', '-', strtolower((string) ($estudiante['nombre'] ?? 'estudiante'))) . '.pdf';
            $disposition = (($_GET['descargar'] ?? '') === '1') ? 'attachment' : 'inline';
            BinaryResponse::pdf($pdfContent, $nombreArchivo, $disposition === 'inline')->send();
        } catch (Throwable $e) {
            $this->handleError($e, static::class);
        }
    }

}