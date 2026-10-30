<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\BinaryResponse;
use App\Models\AuditLogModel;
use App\Models\GradeCertificateModel;
use App\Models\ResultadoAcademicoModel;
use App\Support\CertificadoCalificacionesPdf;
use App\Support\Configuracion;
use DateTimeImmutable;
use DomainException;
use Throwable;

use function App\Traits\sigde_rol_clave;

/**
 * GradeCertificateController
 * -----------------------------------
 * Controlador para la emisión de Certificados de Calificaciones (según Art. 108 RGLOE).
 *
 * Cumple con los lineamientos de arquitectura y seguridad:
 * 1. Solo la docente asignada a la sección puede emitir certificados (403 a otros roles o docentes).
 * 2. Emisión directa en PDF streamed (sin almacenamiento persistente en disco).
 * 3. Independencia de 6.º grado respecto al trámite de egreso.
 * 4. Admite literales A-D (promovido) y E (no promovido).
 * 5. Registro formal de auditoría en bitácora.
 */
class GradeCertificateController extends BaseController
{
    protected string $viewPath  = 'resultado_academico';
    protected string $routeName = 'certificadoCalificaciones';

    private function modeloResultadoAcademico(): ResultadoAcademicoModel
    {
        return new ResultadoAcademicoModel($this->conexion());
    }

    private function modeloCertificado(): GradeCertificateModel
    {
        return new GradeCertificateModel($this->conexion());
    }

    /**
     * Valida estrictamente que el usuario autenticado sea una docente asignada a la sección.
     * De no cumplir, responde 403 Forbidden y termina la ejecución.
     */
    private function verificarDocenteAsignada(int $idEstructura): int
    {
        $idUsuario = (int) ($this->idUsuarioActual() ?? 0);
        $rol = sigde_rol_clave($_SESSION['rol'] ?? '');

        if ($idUsuario <= 0 || $rol !== 'docente' || $idEstructura <= 0) {
            $this->prohibido('Solo la docente asignada a la sección puede emitir certificados de calificaciones.');
        }

        $modelo = $this->modeloResultadoAcademico();
        $idDocente = $modelo->getIdDocentePorUsuario($idUsuario);

        if ($idDocente <= 0 || !$modelo->docenteTieneSeccion($idDocente, $idEstructura)) {
            $this->prohibido('Solo la docente asignada a la sección puede emitir certificados de calificaciones.');
        }

        return $idDocente;
    }

    /**
     * Genera un Certificado de Calificaciones individual para un estudiante.
     */
    public function generarIndividual(): void
    {
        $idEstudiante = (int) ($_GET['id_estudiante'] ?? $_POST['id_estudiante'] ?? 0);
        $idEstructura = (int) ($_GET['id_estructura'] ?? $_POST['id_estructura_academica'] ?? $_POST['id_estructura'] ?? 0);

        if ($idEstructura <= 0) {
            $idUsuario = (int) ($this->idUsuarioActual() ?? 0);
            if ($idUsuario > 0) {
                $sec = (new \App\Models\TeacherAssignmentModel($this->conexion()))->getSeccionActivaDeDocente($idUsuario);
                $idEstructura = (int) ($sec['id_estructura_academica'] ?? 0);
            }
        }

        if ($idEstudiante <= 0 || $idEstructura <= 0) {
            $this->renderHttpError(
                400,
                '400 · Solicitud inválida',
                'Faltan parámetros requeridos para generar el certificado.'
            );
        }

        // SEGURIDAD: Solo la docente asignada a la sección puede emitir
        $this->verificarDocenteAsignada($idEstructura);

        try {
            $estudiante = $this->modeloCertificado()
                ->getDatosParaCertificado($idEstudiante, $idEstructura);

            if (!$estudiante) {
                $this->renderHttpError(
                    404,
                    '404 · Estudiante no encontrado',
                    'El estudiante no pertenece a la matrícula de esta sección.'
                );
            }

            $literal = trim((string) ($estudiante['literal'] ?? ''));
            if ($literal === '') {
                $this->renderHttpError(
                    422,
                    'Sin literal',
                    'El estudiante no tiene literal cargado. No se puede emitir el certificado sin resultado pedagógico.'
                );
            }

            $gradoNombre = (string) ($estudiante['grado'] ?? '');
            if (GradeCertificateModel::esGradoSexto($gradoNombre)
                && GradeCertificateModel::literalEsPromovido($literal)) {
                $this->renderHttpError(
                    422,
                    'Estudiante promovido de 6.° grado',
                    'El estudiante culminó la Educación Primaria y egresa al Liceo. Su documento oficial '
                    . 'probatorio es el Certificado de Educación Primaria expedido por la Dirección y '
                    . 'Secretaría del Plantel en el módulo de Emisión de Constancias, no la boleta de '
                    . 'calificaciones ordinaria.'
                );
            }

            $config = Configuracion::getAll();
            $datosEstudiante = [
                'nombre_completo'        => $estudiante['nombre_completo'] ?? '',
                'tipo_documento'         => $estudiante['tipo_documento'] ?? '',
                'numero_documento'       => $estudiante['numero_documento'] ?? '',
                'fecha_nacimiento'       => $estudiante['fecha_nacimiento'] ?? '',
                'sexo'                   => $estudiante['sexo'] ?? '',
                'municipio_nacimiento'   => $estudiante['municipio_nacimiento'] ?? '',
                'estado_nacimiento'      => $estudiante['estado_nacimiento'] ?? '',
                'grado'                  => $estudiante['grado'] ?? '',
                'seccion'                => $estudiante['seccion'] ?? '',
                'periodo_escolar'        => $estudiante['periodo_escolar'] ?? '',
                'literal'                => $literal,
                'estado_resultado'       => $estudiante['estado_resultado'] ?? '',
                'grado_destino_nombre'   => $estudiante['grado_destino_nombre'] ?? '',
                'id_grado_destino'       => $estudiante['id_grado_destino'] ?? null,
                'fecha_nacimiento_texto' => $this->formatearFechaNacimiento($estudiante['fecha_nacimiento'] ?? ''),
                'docente_nombre'         => $estudiante['docente_nombre'] ?? '',
                'docente_ci'             => $estudiante['docente_ci'] ?? '',
                'id_matricula'           => $estudiante['id_matricula'] ?? 0,
                'id_anio_escolar'        => $estudiante['id_anio_escolar'] ?? 0,
                'id_estructura_academica'=> $idEstructura,
            ];

            $pdfBinary = CertificadoCalificacionesPdf::generar(
                $config,
                $datosEstudiante,
                null,
                false,
                null
            );

            // Registro formal en bitácora de auditoría
            $idUsuario = (int) ($this->idUsuarioActual() ?? 0);
            if ($idUsuario > 0) {
                $accion = sprintf(
                    'EMISIÓN DE CERTIFICADO DE CALIFICACIONES (Art. 108 RGLOE): %s (%s, Literal %s)',
                    $datosEstudiante['nombre_completo'],
                    $datosEstudiante['numero_documento'],
                    $literal
                );
                (new AuditLogModel($this->conexion()))->registrar($idUsuario, $accion, 'Evaluación');
            }

            $nombreLimpio = preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $datosEstudiante['nombre_completo']);
            $filename = "certificado_calificaciones_{$nombreLimpio}.pdf";

            BinaryResponse::pdf($pdfBinary, $filename, true)->send();

        } catch (DomainException $e) {
            $this->renderHttpError(422, 'Error de configuración', $e->getMessage());
        } catch (Throwable $e) {
            $this->handleError($e, static::class . '@generarIndividual');
        }
    }

    /**
     * Genera los Certificados de Calificaciones de todos los estudiantes evaluados en la sección.
     */
    public function generarPorSeccion(): void
    {
        $idEstructura = (int) ($_GET['id_estructura'] ?? $_POST['id_estructura_academica'] ?? $_POST['id_estructura'] ?? 0);

        if ($idEstructura <= 0) {
            $idUsuario = (int) ($this->idUsuarioActual() ?? 0);
            if ($idUsuario > 0) {
                $sec = (new \App\Models\TeacherAssignmentModel($this->conexion()))->getSeccionActivaDeDocente($idUsuario);
                $idEstructura = (int) ($sec['id_estructura_academica'] ?? 0);
            }
        }

        if ($idEstructura <= 0) {
            $this->renderHttpError(
                400,
                '400 · Solicitud inválida',
                'Falta el parámetro de la estructura académica.'
            );
        }

        // SEGURIDAD: Solo la docente asignada a la sección puede emitir
        $this->verificarDocenteAsignada($idEstructura);

        try {
            $modelo = $this->modeloCertificado();
            $estudiantes = $modelo->getDatosParaCertificadosPorSeccion($idEstructura);

            if (empty($estudiantes)) {
                $this->renderHttpError(
                    422,
                    'Sin estudiantes evaluados',
                    'No hay estudiantes con literales cargados en esta sección para emitir certificados.'
                );
            }

            $gradoNombre = (string) ($estudiantes[0]['grado'] ?? '');
            if (GradeCertificateModel::esGradoSexto($gradoNombre)) {
                // En 6.° grado solo reciben Certificado de Calificaciones quienes no alcanzaron las competencias (literal E)
                $estudiantes = $modelo->filtrarCertificablesDeSexto($estudiantes);

                if (empty($estudiantes)) {
                    $this->renderHttpError(
                        422,
                        'Certificados de Educación Primaria',
                        'En 6.° grado los estudiantes promovidos reciben el Certificado de Educación '
                        . 'Primaria emitido por la Dirección y Secretaría en el módulo de Emisión de '
                        . 'Constancias. No hay estudiantes no promovidos con literal E en esta sección.'
                    );
                }
            }

            $config = Configuracion::getAll();
            $datosEstudiantes = array_map(function ($est) use ($idEstructura) {
                return [
                    'nombre_completo'        => $est['nombre_completo'] ?? '',
                    'tipo_documento'         => $est['tipo_documento'] ?? '',
                    'numero_documento'       => $est['numero_documento'] ?? '',
                    'fecha_nacimiento'       => $est['fecha_nacimiento'] ?? '',
                    'sexo'                   => $est['sexo'] ?? '',
                    'municipio_nacimiento'   => $est['municipio_nacimiento'] ?? '',
                    'estado_nacimiento'      => $est['estado_nacimiento'] ?? '',
                    'grado'                  => $est['grado'] ?? '',
                    'seccion'                => $est['seccion'] ?? '',
                    'periodo_escolar'        => $est['periodo_escolar'] ?? '',
                    'literal'                => $est['literal'] ?? '',
                    'estado_resultado'       => $est['estado_resultado'] ?? '',
                    'grado_destino_nombre'   => $est['grado_destino_nombre'] ?? '',
                    'id_grado_destino'       => $est['id_grado_destino'] ?? null,
                    'fecha_nacimiento_texto' => $this->formatearFechaNacimiento($est['fecha_nacimiento'] ?? ''),
                    'docente_nombre'         => $est['docente_nombre'] ?? '',
                    'docente_ci'             => $est['docente_ci'] ?? '',
                    'id_matricula'           => $est['id_matricula'] ?? 0,
                    'id_anio_escolar'        => $est['id_anio_escolar'] ?? 0,
                    'id_estructura_academica'=> $idEstructura,
                ];
            }, $estudiantes);

            $pdfBinary = CertificadoCalificacionesPdf::generarPorSeccion(
                $config,
                $datosEstudiantes,
                null,
                false,
                null
            );

            // Registro formal en bitácora de auditoría
            $idUsuario = (int) ($this->idUsuarioActual() ?? 0);
            if ($idUsuario > 0) {
                $totalEmitidos = count($datosEstudiantes);
                $accion = sprintf(
                    'EMISIÓN MASIVA DE CERTIFICADOS DE CALIFICACIONES (Art. 108 RGLOE): Sección id %d (%d estudiantes)',
                    $idEstructura,
                    $totalEmitidos
                );
                (new AuditLogModel($this->conexion()))->registrar($idUsuario, $accion, 'Evaluación');
            }

            $filename = "certificados_seccion_{$idEstructura}.pdf";

            BinaryResponse::pdf($pdfBinary, $filename, true)->send();

        } catch (DomainException $e) {
            $this->renderHttpError(422, 'Error de configuración', $e->getMessage());
        } catch (Throwable $e) {
            $this->handleError($e, static::class . '@generarPorSeccion');
        }
    }

    /**
     * Formatea la fecha de nacimiento en texto formal.
     */
    private function formatearFechaNacimiento(?string $fechaNacimiento): string
    {
        if (empty($fechaNacimiento)) {
            return '';
        }

        try {
            $fecha = new DateTimeImmutable($fechaNacimiento);
            $dia = (int) $fecha->format('j');
            $mes = (int) $fecha->format('n');
            $anio = (int) $fecha->format('Y');

            $meses = [
                1  => 'enero',
                2  => 'febrero',
                3  => 'marzo',
                4  => 'abril',
                5  => 'mayo',
                6  => 'junio',
                7  => 'julio',
                8  => 'agosto',
                9  => 'septiembre',
                10 => 'octubre',
                11 => 'noviembre',
                12 => 'diciembre',
            ];

            return sprintf('%02d de %s de %04d', $dia, $meses[$mes] ?? '', $anio);
        } catch (Throwable $e) {
            return '';
        }
    }
}
