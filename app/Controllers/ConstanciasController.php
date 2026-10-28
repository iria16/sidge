<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\BinaryResponse;
use App\Models\ConstanciaModel;
use App\Support\ConstanciaPdf;
use DomainException;
use InvalidArgumentException;
use PDO;
use Throwable;

use function App\Traits\sigde_rol_clave;

/**
 * ConstanciasController
 * ---------------------
 * Controlador unificado para la emisión oficial de Constancias:
 * - Estudio
 * - Inscripción / Ratificación
 * - Prosecución
 * - Retiro
 *
 * Cumple con los lineamientos de arquitectura y seguridad:
 * 1. Todos los tipos de constancia se gestionan desde una única interfaz con selector de tipo.
 * 2. Validación estricta de elegibilidad en el servidor por tipo de constancia.
 * 3. Restricción de permisos y turnos según el rol de la sesión.
 * 4. Registro formal de auditoría en la bitácora del sistema.
 */
class ConstanciasController extends BaseController
{
    protected string $viewPath  = 'constancias';
    protected string $routeName = 'constancias';

    private function modelo(): ConstanciaModel
    {
        /** @var ConstanciaModel $model */
        $model = $this->getModel();
        return $model;
    }

    protected function createModel(PDO $pdo): object
    {
        return new ConstanciaModel($pdo);
    }

    /**
     * Retorna los tipos de constancia a los que tiene permiso el usuario en sesión.
     * Retiro, Prosecución y Egreso son exclusivos del rol secretaria.
     */
    private function tiposPermitidosPorRol(): array
    {
        return ConstanciaModel::tiposPermitidosPorRol(sigde_rol_clave($_SESSION['rol'] ?? ''));
    }

    /**
     * Pantalla unificada de Constancias.
     * Carga el listado de estudiantes elegibles según el tipo seleccionado y los filtros aplicados.
     */
    public function index(): void
    {
        try {
            $tiposDisponibles = $this->tiposPermitidosPorRol();
            if (empty($tiposDisponibles)) {
                $this->prohibido('No tiene permisos para acceder al módulo de constancias.');
            }

            // Tipo solicitado por GET o primer tipo disponible por defecto
            $tipo = strtolower(trim((string) ($_GET['tipo'] ?? 'estudio')));
            if (!array_key_exists($tipo, $tiposDisponibles)) {
                $tipo = (string) array_key_first($tiposDisponibles);
            }

            $model = $this->modelo();
            $page  = max(1, (int) ($_GET['page'] ?? 1));
            $perPage = 10;

            $filters = [
                'q'       => trim((string) ($_GET['q'] ?? '')),
                'grado'   => trim((string) ($_GET['grado'] ?? '')),
                'seccion' => trim((string) ($_GET['seccion'] ?? '')),
                'turno'   => $this->turnoSecretariaActual(),
            ];

            $estudiantes    = $model->getListado($tipo, $filters, $page, $perPage);
            $total          = $model->countListado($tipo, $filters);
            $grados         = $model->getGrados();
            $secciones      = $model->getSecciones();

            $paginacion = [
                'desde'         => $total > 0 ? (($page - 1) * $perPage) + 1 : 0,
                'hasta'         => min($page * $perPage, $total),
                'total'         => $total,
                'pagina_actual' => $page,
                'total_paginas' => max(1, (int) ceil($total / $perPage)),
            ];

            $directorConfigurado = $model->estaConfiguradoDirector();
            $flash = $this->getFlashMessages();

            require __DIR__ . '/../Views/constancias/index.php';
        } catch (Throwable $e) {
            $this->handleError($e, static::class . '@index');
        }
    }

    /**
     * Redirecciones de compatibilidad hacia la pantalla unificada index.
     */
    public function estudio(): void
    {
        $this->redirect('index?tipo=estudio');
    }

    public function inscripcion(): void
    {
        $this->redirect('index?tipo=inscripcion');
    }

    public function retiro(): void
    {
        $this->redirect('index?tipo=retiro');
    }

    public function prosecucion(): void
    {
        $this->redirect('index?tipo=prosecucion');
    }

    /**
     * Emite la constancia oficial en PDF.
     * Valida la elegibilidad estricta en el servidor y registra en bitácora.
     */
    public function emitir(): void
    {
        $tiposDisponibles = $this->tiposPermitidosPorRol();
        $tipo = strtolower(trim((string) ($_POST['tipo'] ?? ($_GET['tipo'] ?? 'estudio'))));
        $id   = (int) ($_POST['id'] ?? ($_GET['id'] ?? 0));
        $idMatricula = isset($_POST['id_matricula']) ? (int) $_POST['id_matricula'] : (isset($_GET['id_matricula']) ? (int) $_GET['id_matricula'] : null);

        // Validación de permisos por rol
        if (!array_key_exists($tipo, $tiposDisponibles)) {
            $this->prohibido('No tiene permisos para emitir este tipo de constancia.');
        }

        if ($id <= 0) {
            $this->setFlash('error', 'Identificador inválido para la emisión.');
            $this->redirect('index?tipo=' . urlencode($tipo));
        }

        $model = $this->modelo();
        $estudiante = null;
        $idEstudianteAuditoria = 0;

        // Validación estricta de elegibilidad en el servidor según el tipo
        if ($tipo === 'retiro') {
            if (!$model->esElegibleParaRetiro($id)) {
                $this->setFlash('error', 'El retiro solicitado no existe o no se encuentra en estado APROBADO.');
                $this->redirect('index?tipo=retiro');
            }
            $estudiante = $model->getDatosConstanciaRetiro($id);
            if (!$estudiante) {
                $this->setFlash('error', 'No se pudieron recuperar los datos del retiro.');
                $this->redirect('index?tipo=retiro');
            }
            $idEstudianteAuditoria = (int) $estudiante['id_estudiante'];
        } elseif ($tipo === 'prosecucion') {
            if (!$model->esElegibleParaProsecucion($id)) {
                $this->setFlash('error', 'El resultado académico solicitado no existe o no corresponde a una prosecución aprobada (1º a 5º grado).');
                $this->redirect('index?tipo=prosecucion');
            }
            $estudiante = $model->getDatosConstanciaProsecucion($id);
            if (!$estudiante) {
                $this->setFlash('error', 'No se pudieron recuperar los datos de prosecución.');
                $this->redirect('index?tipo=prosecucion');
            }
            $idEstudianteAuditoria = (int) $estudiante['id_estudiante'];
        } elseif ($tipo === 'egreso') {
            if (!$model->esElegibleParaEgreso($id)) {
                $this->setFlash('error', 'El estudiante seleccionado no posee literal aprobatorio (A–D) ni registro de egreso en 6to grado en el período escolar activo.');
                $this->redirect('index?tipo=egreso');
            }
            $estudiante = $model->getDatosConstanciaEgreso($id);
            if (!$estudiante) {
                $this->setFlash('error', 'No se pudieron recuperar los datos de egreso.');
                $this->redirect('index?tipo=egreso');
            }
            $idEstudianteAuditoria = (int) $estudiante['id_estudiante'];
        } elseif ($tipo === 'inscripcion') {
            if (!$model->esElegibleParaInscripcion($id, $idMatricula)) {
                $this->setFlash('error', 'El estudiante no posee un registro de inscripción válido.');
                $this->redirect('index?tipo=inscripcion');
            }
            $idEstudianteAuditoria = $id;
            $estudiante = $model->getEstudianteParaConstancia($id, $idMatricula);
            if (!$estudiante) {
                $this->setFlash('error', 'El estudiante solicitado no existe en el sistema o no posee registros de matrícula.');
                $this->redirect('index?tipo=inscripcion');
            }
        } else {
            // estudio
            if (!$model->esElegibleParaEstudio($id)) {
                $this->prohibido('Solo se puede emitir constancia de estudio a estudiantes activos.');
            }
            $idEstudianteAuditoria = $id;
            $estudiante = $model->getEstudianteParaConstancia($id, null);
            if (!$estudiante) {
                $this->setFlash('error', 'El estudiante solicitado no existe en el sistema o no posee registros de matrícula activos.');
                $this->redirect('index?tipo=estudio');
            }
        }

        // Comprobación de turno para la secretaria
        if ($idEstudianteAuditoria > 0 && !$this->secretariaTieneAccesoAEstudiante($idEstudianteAuditoria)) {
            $this->setFlash('error', 'Ese estudiante no pertenece a su turno asignado.');
            $this->redirect('index?tipo=' . urlencode($tipo));
        }

        $config = $model->getConfiguracion();

        if (!$model->estaConfiguradoDirector()) {
            $this->setFlash('error', 'No se puede emitir la constancia: solicite al administrador que complete los datos del director(a) en Datos Institucionales.');
            $this->redirect('index?tipo=' . urlencode($tipo));
        }

        try {
            if ($tipo === 'retiro') {
                $pdf = ConstanciaPdf::generarRetiro($config, $estudiante);
            } elseif ($tipo === 'prosecucion') {
                $pdf = ConstanciaPdf::generarProsecucion($config, $estudiante);
            } elseif ($tipo === 'egreso') {
                $pdf = ConstanciaPdf::generarEgreso($config, $estudiante);
            } elseif ($tipo === 'inscripcion') {
                $pdf = ConstanciaPdf::generarInscripcion($config, $estudiante);
            } else {
                $modalidad = (string) ($estudiante['modalidad'] ?? 'activo');
                $pdf = ConstanciaPdf::generarEstudio($config, $estudiante, $modalidad);
            }

            // Registro en bitácora de auditoría (quién, a qué estudiante, tipo de constancia, cuándo, IP)
            $idUsuario = (int) ($this->idUsuarioActual() ?? 0);
            if ($idUsuario > 0) {
                $accionBitacora = ($tipo === 'egreso')
                    ? sprintf('EMISIÓN DE CERTIFICADO DE EDUCACIÓN PRIMARIA: %s (%s)', $estudiante['nombre_completo'] ?? 'Estudiante', $estudiante['numero_documento'] ?? 'Sin doc')
                    : sprintf('EMISIÓN DE CONSTANCIA (%s): %s (%s)', mb_strtoupper($tipo), $estudiante['nombre_completo'] ?? 'Estudiante', $estudiante['numero_documento'] ?? 'Sin doc');
                $model->registrarEmision($idUsuario, $accionBitacora);
            }

            $nombreLimpio = preg_replace('/[^A-Za-z0-9_-]/', '_', (string) ($estudiante['nombre_completo'] ?? 'estudiante'));
            $filename = ($tipo === 'egreso') ? "certificado_primaria_{$nombreLimpio}.pdf" : "constancia_{$tipo}_{$nombreLimpio}.pdf";

            $esDescarga = (isset($_GET['descargar']) && $_GET['descargar'] === '1')
                       || (isset($_POST['descargar']) && $_POST['descargar'] === '1');
            $disposition = $esDescarga ? 'attachment' : 'inline';

            BinaryResponse::pdf($pdf, $filename, $disposition === 'inline')->send();
        } catch (DomainException $e) {
            $this->setFlash('error', $e->getMessage());
            $this->redirect('index?tipo=' . urlencode($tipo));
        } catch (InvalidArgumentException $e) {
            $this->setFlash('error', $e->getMessage());
            $this->redirect('index?tipo=' . urlencode($tipo));
        } catch (Throwable $e) {
            $this->handleError($e, static::class . '@emitir');
        }
    }

    /**
     * Emite por lote el cuadernillo de Certificados de Educación Primaria (6.° grado)
     * para todos los estudiantes promovidos bajo los filtros activos (sección, búsqueda y turno).
     */
    public function emitirLote(): void
    {
        $tiposDisponibles = $this->tiposPermitidosPorRol();
        if (!array_key_exists('egreso', $tiposDisponibles)) {
            $this->prohibido('No tiene permisos para emitir Certificados de Educación Primaria por lote.');
        }

        $filters = [
            'q'       => trim((string) ($_GET['q'] ?? '')),
            'seccion' => trim((string) ($_GET['seccion'] ?? '')),
            'turno'   => $this->turnoSecretariaActual(),
        ];

        $model = $this->modelo();
        $candidatos = $model->getEstudiantesEgresoParaLote($filters);
        if ($candidatos === []) {
            $this->setFlash('error', 'No se encontraron estudiantes promovidos de 6.° grado para emitir certificados con los filtros seleccionados.');
            $this->redirect('index?tipo=egreso');
            return;
        }

        $config = $model->getConfiguracion();
        if (!$model->estaConfiguradoDirector()) {
            $this->setFlash('error', 'No se pueden emitir los certificados: solicite al administrador que complete los datos del director(a) en Datos Institucionales.');
            $this->redirect('index?tipo=egreso');
            return;
        }

        $idUsuario = (int) ($this->idUsuarioActual() ?? 0);
        $estudiantesData = [];

        foreach ($candidatos as $cand) {
            $idEst = (int) ($cand['id_estudiante'] ?? 0);
            if ($idEst <= 0) {
                continue;
            }
            $datos = $model->getDatosConstanciaEgreso(0, $idEst);
            if ($datos) {
                $estudiantesData[] = $datos;
            }
        }

        if ($estudiantesData === []) {
            $this->setFlash('error', 'No se pudieron recuperar los datos de egreso de los estudiantes seleccionados.');
            $this->redirect('index?tipo=egreso');
            return;
        }

        try {
            $pdf = ConstanciaPdf::generarEgresoPorLote($config, $estudiantesData);

            if ($idUsuario > 0) {
                $seccionTexto = $filters['seccion'] !== '' ? " (Sección {$filters['seccion']})" : '';
                $model->registrarEmision(
                    $idUsuario,
                    sprintf('EMISIÓN POR LOTE DE CERTIFICADOS DE EDUCACIÓN PRIMARIA: %d estudiantes%s', count($estudiantesData), $seccionTexto)
                );
            }

            $sufijo = $filters['seccion'] !== ''
                ? '_seccion_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $filters['seccion'])
                : '';
            $filename = "certificados_primaria_lote{$sufijo}.pdf";

            $esDescarga = (isset($_GET['descargar']) && $_GET['descargar'] === '1')
                       || (isset($_POST['descargar']) && $_POST['descargar'] === '1');
            $disposition = $esDescarga ? 'attachment' : 'inline';

            BinaryResponse::pdf($pdf, $filename, $disposition === 'inline')->send();
        } catch (Throwable $e) {
            $this->handleError($e, static::class . '@emitirLote');
        }
    }
}
