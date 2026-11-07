<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EnrollmentModel;
use App\Models\RatificationModel;
use App\Models\StudentsModel;
use App\Support\Configuracion;
use App\Support\FichaEstudiantePdf;
use App\Support\ImagenPdf;
use PDO;

final class ExpedienteEstudianteService
{
    private StudentsModel $students;
    private RatificationModel $ratifications;
    private EnrollmentModel $enrollments;

    public function __construct(PDO $pdo)
    {
        $this->students = new StudentsModel($pdo);
        $this->ratifications = new RatificationModel($pdo);
        $this->enrollments = new EnrollmentModel($pdo);
    }

    /**
     * Aplica las reglas de consulta al filtro solicitado por la interfaz.
     *
     * @param array{q?:string,anio?:string|int|null,grado?:string,seccion?:string,estado?:string,id_turno?:string} $requested
     * @return array<string, string>
     */
    public function buildFilters(
        array $requested,
        ?int $turno,
        ?int $docenteId,
        bool $esSecretaria
    ): array {
        $anios = $this->students->getAniosEscolares($turno);
        $idsAnios = array_map(static fn (array $anio): int => (int) $anio['id'], $anios);
        $anioParam = $requested['anio'] ?? null;

        if ($docenteId !== null || $esSecretaria) {
            $anioActivo = $this->students->getAnioEscolarActivoId();
            $anioSolicitado = $anioActivo !== null ? (int) $anioActivo : 0;
            $estado = 'activo';
            $idTurnoFinal = $turno !== null ? (string) $turno : '';
        } else {
            if ($anioParam === null) {
                // Por defecto para Directivo/Coordinador: año escolar activo
                $anioActivo = $this->students->getAnioEscolarActivoId();
                $anioSolicitado = $anioActivo !== null ? (int) $anioActivo : 0;
            } elseif ($anioParam === '' || $anioParam === '0' || $anioParam === 'todos') {
                $anioSolicitado = 0;
            } else {
                $anioInt = (int) $anioParam;
                $anioSolicitado = in_array($anioInt, $idsAnios, true) ? $anioInt : 0;
            }

            $estadoSolicitado = strtolower(trim((string) ($requested['estado'] ?? '')));
            $estado = in_array($estadoSolicitado, ['activo', 'retirado', 'egresado'], true)
                ? $estadoSolicitado
                : '';

            $turnoSolicitado = trim((string) ($requested['id_turno'] ?? ''));
            $idTurnoFinal = ($turnoSolicitado !== '' && is_numeric($turnoSolicitado))
                ? (string) (int) $turnoSolicitado
                : '';
        }

        return [
            'q'          => trim((string) ($requested['q'] ?? '')),
            'anio'       => $anioSolicitado > 0 ? (string) $anioSolicitado : ($anioParam === 'todos' || $anioParam === '' ? 'todos' : ''),
            'grado'      => trim((string) ($requested['grado'] ?? '')),
            'seccion'    => trim((string) ($requested['seccion'] ?? '')),
            'estado'     => $estado,
            'id_turno'   => $idTurnoFinal,
            'id_docente' => $docenteId === null ? '' : (string) $docenteId,
        ];
    }

    /**
     * Prepara los catálogos y datos adicionales de la vista del listado.
     *
     * @return array<string, mixed>
     */
    public function getIndexData(
        array $items,
        array $filters,
        ?int $turno,
        ?int $docenteId,
        bool $esSecretaria
    ): array {
        $aniosEscolares = $this->students->getAniosEscolares($turno);
        $esDocente = $docenteId !== null;
        $esDirectivo = !$esDocente && !$esSecretaria;

        if ($esDocente || $esSecretaria) {
            $anioActivoId = $this->students->getAnioEscolarActivoId();
            $aniosEscolares = array_values(array_filter(
                $aniosEscolares,
                static fn (array $anio): bool => (int) $anio['id'] === (int) $anioActivoId
            ));
        }

        return [
            'stats'           => $this->students->getStats($filters),
            'estudiantes'     => $items,
            'sinAsignaciones' => $docenteId !== null
                && $this->students->getEstructurasDocente($docenteId) === [],
            'aniosEscolares'  => $aniosEscolares,
            'turnos'          => $this->students->getTurnos(),
            'esDocente'       => $esDocente,
            'esSecretaria'    => $esSecretaria,
            'esDirectivo'     => $esDirectivo,
            'grados'          => $this->students->getGrados(),
            'secciones'       => $this->students->getSecciones(),
        ];
    }

    /**
     * Resuelve el año permitido y carga el registro principal del estudiante.
     *
     * @return array{estudiante:array<string,mixed>,anioSeleccionado:int}|null
     */
    public function getEstudiante(
        int $estudianteId,
        int $anioSolicitado,
        ?int $turno,
        ?int $docenteId,
        bool $restringirAnioActivo
    ): ?array {
        return $this->cargarEstudiante(
            $estudianteId,
            $anioSolicitado,
            $turno,
            $docenteId,
            $restringirAnioActivo
        );
    }

    /**
     * Carga los datos complementarios de la ficha después de que el controlador
     * haya comprobado que el usuario puede consultar al estudiante.
     *
     * @return array<string, mixed>
     */
    public function getDatosExpediente(int $estudianteId, ?int $turno, bool $esDocente): array
    {
        $catFamiliares = $this->ratifications->getCatalogosFamiliar();
        $vinculados = $this->ratifications->getFamiliaresParaExpediente($estudianteId, $esDocente);

        return [
            'historialAcademico'   => $this->historial($estudianteId, $turno, $esDocente),
            'datosEditables'       => $this->ratifications->getEstudianteEditable($estudianteId, !$esDocente) ?? [],
            'catFamiliares'        => $catFamiliares,
            'estados'              => $catFamiliares['estados'],
            'nacionalidades'       => $this->ratifications->getNacionalidades(),
            'familiaresNucleo'     => $vinculados['nucleo'],
            'autorizadosParaRetiro' => $vinculados['autorizados'],
            'recaudos'             => $this->enrollments->getRecaudosDeEstudiante($estudianteId),
        ];
    }

    /**
     * Genera el PDF integral; el controlador conserva el manejo de la respuesta HTTP.
     */
    public function generarPdf(
        int $estudianteId,
        array $estudiante,
        ?int $turno,
        bool $esDocente
    ): string {
        $vinculados = $this->ratifications->getFamiliaresParaExpediente($estudianteId, $esDocente);
        $fotoDataUri = ImagenPdf::publicaDataUri(
            (string) ($estudiante['avatar_img'] ?? ''),
            false,
            80 / 102
        );

        return FichaEstudiantePdf::generar(
            Configuracion::getAll(),
            $estudiante,
            $this->ratifications->getEstudianteEditable($estudianteId, !$esDocente) ?? [],
            $vinculados['nucleo'],
            $vinculados['autorizados'],
            $this->historial($estudianteId, $turno, $esDocente),
            $esDocente,
            null,
            $fotoDataUri
        );
    }

    public function actualizarRecaudos(int $estudianteId, array $recaudos, int $idUsuario): int
    {
        return $this->enrollments->actualizarRecaudos($estudianteId, $recaudos, $idUsuario);
    }

    /**
     * @return array{estudiante:array<string,mixed>,anioSeleccionado:int}|null
     */
    private function cargarEstudiante(
        int $estudianteId,
        int $anioSolicitado,
        ?int $turno,
        ?int $docenteId,
        bool $restringirAnioActivo
    ): ?array {
        $anios = $this->students->getAniosEscolares($turno);
        $idsAnios = array_map(static fn (array $anio): int => (int) $anio['id'], $anios);

        if ($restringirAnioActivo) {
            $anioActivo = $this->students->getAnioEscolarActivoId();
            $anioConsulta = $anioActivo !== null ? (int) $anioActivo : 0;
        } else {
            $anioConsulta = ($anioSolicitado > 0 && in_array($anioSolicitado, $idsAnios, true))
                ? $anioSolicitado
                : null;
        }

        if ($estudianteId <= 0 || ($restringirAnioActivo && $anioConsulta <= 0)) {
            return null;
        }

        $estudiante = $this->students->getById($estudianteId, $anioConsulta, $turno, $docenteId);
        if ($estudiante === null) {
            return null;
        }

        return [
            'estudiante'       => $estudiante,
            'anioSeleccionado' => $anioConsulta ?? 0,
        ];
    }

    private function historial(int $estudianteId, ?int $turno, bool $esDocente): array
    {
        $historial = $this->students->getHistorialAcademico($estudianteId, $turno);
        if (!$esDocente) {
            return $historial;
        }

        return array_values(array_filter(
            $historial,
            static fn (array $registro): bool => ($registro['estado_anio'] ?? '') === 'ACTIVO'
        ));
    }
}
