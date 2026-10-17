<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\ConstanciaModel;
use App\Support\Configuracion;
use App\Support\ConstanciaPdf;
use DomainException;
use InvalidArgumentException;
use PDO;
use Throwable;

/**
 * ConstanciasController
 * ---------------------
 * Controlador para la visualización, comparación de formatos de prueba y
 * emisión oficial de Constancias de Estudio y de Inscripción.
 *
 * Cumple con los lineamientos de seguridad y arquitectura:
 * 1. Los datos del estudiante se consultan estrictamente por ID desde la BD (nunca por POST).
 * 2. Bloqueo estricto de estudiantes retirados y egresados en producción hasta aprobación formal.
 * 3. Validación de completitud de datos institucionales (director, código de dependencia).
 * 4. Servicio de PDFs de prueba para comparación directa con los formatos físicos impresos.
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
     * Redirige al listado principal de Constancias de Estudio por defecto.
     */
    public function index(): void
    {
        $this->redirect('estudio');
    }

    /**
     * Pantalla de Constancias de Estudio.
     * Lista estudiantes con su estado académico actual y permite emitir la constancia.
     */
    public function estudio(): void
    {
        try {
            $model = $this->modelo();
            $page  = max(1, (int) ($_GET['page'] ?? 1));
            $perPage = 10;

            $filters = [
                'q'       => trim((string) ($_GET['q'] ?? '')),
                'grado'   => trim((string) ($_GET['grado'] ?? '')),
                'seccion' => trim((string) ($_GET['seccion'] ?? '')),
                // null para admin y directivo (ven todos); el id del turno para la
                // secretaria, que solo debe listar los alumnos de su turno.
                'turno'   => $this->turnoSecretariaActual(),
            ];

            $estudiantes = $model->getEstudiantesEstudio($filters, $page, $perPage);
            $total       = $model->countEstudiantesEstudio($filters);
            $stats       = $model->getStatsEstudio();
            $grados      = $model->getGrados();
            $secciones   = $model->getSecciones();

            $paginacion = [
                'desde'         => $total > 0 ? (($page - 1) * $perPage) + 1 : 0,
                'hasta'         => min($page * $perPage, $total),
                'total'         => $total,
                'pagina_actual' => $page,
                'total_paginas' => max(1, (int) ceil($total / $perPage)),
            ];

            $directorConfigurado = Configuracion::estaConfiguradoDirector();
            $flash = $this->getFlashMessages();

            require __DIR__ . '/../Views/constancias/estudio.php';
        } catch (Throwable $e) {
            $this->handleError($e, static::class . '@estudio');
        }
    }

    /**
     * Pantalla de Constancias de Inscripción.
     * Lista estudiantes inscritos por año escolar y permite emitir la constancia.
     */
    public function inscripcion(): void
    {
        try {
            $model = $this->modelo();
            $page  = max(1, (int) ($_GET['page'] ?? 1));
            $perPage = 10;

            $filters = [
                'q'       => trim((string) ($_GET['q'] ?? '')),
                'grado'   => trim((string) ($_GET['grado'] ?? '')),
                'seccion' => trim((string) ($_GET['seccion'] ?? '')),
                /* Sin filtro de año: la constancia se emite sobre el período en
                   curso. Leerlo de la URL además abría la puerta a pedir un
                   período que ya no está en marcha; la consulta ya se limita al
                   año activo por su cuenta. */
                // Ver estudio(): null para admin y directivo, el turno para la secretaria.
                'turno'   => $this->turnoSecretariaActual(),
            ];

            $inscripciones  = $model->getEstudiantesInscripcion($filters, $page, $perPage);
            $total          = $model->countEstudiantesInscripcion($filters);
            /* Solo el activo, que es el único que la vista muestra. Antes llegaba
               la lista entera para alimentar un selector que ya no existe. */
            $aniosEscolares = [[
                'id_anio_escolar' => \App\Support\AniosEscolares::idEnCurso($this->conexion()),
                'nombre_periodo' => \App\Support\AniosEscolares::nombreEnCurso($this->conexion()),
            ]];
            $grados         = $model->getGrados();
            $secciones      = $model->getSecciones();

            $paginacion = [
                'desde'         => $total > 0 ? (($page - 1) * $perPage) + 1 : 0,
                'hasta'         => min($page * $perPage, $total),
                'total'         => $total,
                'pagina_actual' => $page,
                'total_paginas' => max(1, (int) ceil($total / $perPage)),
            ];

            $directorConfigurado = Configuracion::estaConfiguradoDirector();
            $flash = $this->getFlashMessages();

            require __DIR__ . '/../Views/constancias/inscripcion.php';
        } catch (Throwable $e) {
            $this->handleError($e, static::class . '@inscripcion');
        }
    }

    /**
     * Emite la constancia oficial de un estudiante real.
     * Los datos se obtienen estrictamente por ID desde la base de datos (nunca por POST).
     *
     * Parámetros POST:
     * - id: ID del estudiante
     * - tipo: 'estudio' o 'inscripcion' (por defecto 'estudio')
     * - id_matricula: opcional, ID específico de matrícula para constancia de inscripción
     */
    public function emitir(): void
    {
        $tipo        = strtolower(trim((string) ($_POST['tipo'] ?? ($_GET['tipo'] ?? 'estudio'))));
        $idEst       = (int) ($_POST['id'] ?? ($_GET['id'] ?? 0));
        $idMatricula = isset($_POST['id_matricula']) ? (int) $_POST['id_matricula'] : (isset($_GET['id_matricula']) ? (int) $_GET['id_matricula'] : null);

        if (!in_array($tipo, ['estudio', 'inscripcion'], true)) {
            $tipo = 'estudio';
        }

        if ($idEst <= 0) {
            $this->setFlash('error', 'Identificador de estudiante inválido.');
            $this->redirect($tipo);
        }

        /* El id llega por POST, así que sin esto una secretaria podría emitir la
           constancia de cualquier alumno de la escuela, no solo de su turno: el PDF
           sale firmado por la institución. Mismo helper que usan retiro y fichas.
           Devuelve true para admin y directivo, que sí están autorizados a todos. */
        if (!$this->secretariaTieneAccesoAEstudiante($idEst)) {
            $this->setFlash('error', 'Ese estudiante no pertenece a su turno.');
            $this->redirect($tipo);
        }

        $model = $this->modelo();

        // Validación de elegibilidad para Constancia de Estudio:
        // Solo se puede emitir a estudiantes con estado Activo inscritos en el período escolar activo.
        if ($tipo === 'estudio') {
            if (!$model->esElegibleParaEstudio($idEst)) {
                http_response_code(403);
                header('Content-Type: text/plain; charset=utf-8');
                echo 'Solo se puede emitir constancia de estudio a estudiantes activos';
                exit;
            }
        }

        $estudiante = $model->getEstudianteParaConstancia($idEst, $idMatricula);

        if (!$estudiante) {
            $this->setFlash('error', 'El estudiante solicitado no existe en el sistema o no posee registros de matrícula.');
            $this->redirect($tipo);
        }

        Configuracion::setPdo($this->conexion());
        $config = Configuracion::getAll();

        if (!Configuracion::estaConfiguradoDirector()) {
            $this->setFlash('error', 'No se puede emitir la constancia: solicite al administrador que complete los datos del director(a) en Datos Institucionales.');
            $this->redirect($tipo);
        }

        try {
            if ($tipo === 'inscripcion') {
                $pdf = ConstanciaPdf::generarInscripcion($config, $estudiante);
            } else {
                $modalidad = (string) ($estudiante['modalidad'] ?? 'activo');
                $pdf = ConstanciaPdf::generarEstudio($config, $estudiante, $modalidad);
            }

            $nombreLimpio = preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $estudiante['nombre_completo']);
            $filename = "constancia_{$tipo}_{$nombreLimpio}.pdf";

            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($pdf));
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
            echo $pdf;
            exit;
        } catch (DomainException $e) {
            // Error de negocio: falta de director o modalidad bloqueada (retirados/egresados)
            $this->setFlash('error', $e->getMessage());
            $this->redirect($tipo);
        } catch (InvalidArgumentException $e) {
            // Error de datos incompletos en la ficha del alumno (nacimiento, documento, etc.)
            $this->setFlash('error', $e->getMessage());
            $this->redirect($tipo);
        } catch (Throwable $e) {
            $this->handleError($e, static::class . '@emitir');
        }
    }
}
