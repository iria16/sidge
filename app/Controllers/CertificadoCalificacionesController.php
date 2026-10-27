<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\BitacoraModel;
use App\Models\ResultadoAcademicoModel;
use App\Support\AniosEscolares;
use App\Support\CertificadoCalificacionesPdf;
use App\Support\Configuracion;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use PDO;
use Throwable;

use function App\Traits\sigde_rol_clave;

/**
 * CertificadoCalificacionesController
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
class CertificadoCalificacionesController extends BaseController
{
    protected string $viewPath  = 'resultado_academico';
    protected string $routeName = 'certificadoCalificaciones';

    private function modeloResultadoAcademico(): ResultadoAcademicoModel
    {
        return new ResultadoAcademicoModel($this->conexion());
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
            http_response_code(403);
            header('Content-Type: text/html; charset=UTF-8');
            echo '<!DOCTYPE html><meta charset="utf-8"><title>403 - Acceso denegado</title>'
               . '<h1>403 - Acceso denegado</h1>'
               . '<p>Solo la docente asignada a la sección puede emitir certificados de calificaciones.</p>';
            exit;
        }

        $modelo = $this->modeloResultadoAcademico();
        $idDocente = $modelo->getIdDocentePorUsuario($idUsuario);

        if ($idDocente <= 0 || !$modelo->docenteTieneSeccion($idDocente, $idEstructura)) {
            http_response_code(403);
            header('Content-Type: text/html; charset=UTF-8');
            echo '<!DOCTYPE html><meta charset="utf-8"><title>403 - Acceso denegado</title>'
               . '<h1>403 - Acceso denegado</h1>'
               . '<p>Solo la docente asignada a la sección puede emitir certificados de calificaciones.</p>';
            exit;
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
            http_response_code(400);
            header('Content-Type: text/html; charset=UTF-8');
            echo '<!DOCTYPE html><meta charset="utf-8"><title>400 - Solicitud inválida</title>'
               . '<h1>400 - Solicitud inválida</h1>'
               . '<p>Faltan parámetros requeridos para generar el certificado.</p>';
            exit;
        }

        // SEGURIDAD: Solo la docente asignada a la sección puede emitir
        $this->verificarDocenteAsignada($idEstructura);

        try {
            $sql = "
                SELECT 
                    est.id_estudiante,
                    p.primer_nombre,
                    p.segundo_nombre,
                    p.primer_apellido,
                    p.segundo_apellido,
                    p.tipo_documento,
                    p.numero_documento,
                    p.fecha_nacimiento,
                    p.sexo,
                    p.estado AS estado_persona,
                    par.nombre AS parroquia_nacimiento,
                    mun.nombre AS municipio_nacimiento,
                    COALESCE(est_nac.nombre, est_mun.nombre) AS estado_nacimiento,
                    g.nombre AS grado,
                    s.nombre AS seccion,
                    a.nombre_periodo AS periodo_escolar,
                    ra.literal,
                    ra.estado_resultado,
                    ra.id_grado_destino,
                    g_dest.nombre AS grado_destino_nombre,
                    ra.observaciones,
                    m.id_matricula,
                    m.id_anio_escolar,
                    m.id_estructura_academica,
                    CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS nombre_completo,
                    CONCAT_WS(' ', p_doc.primer_nombre, p_doc.segundo_nombre, p_doc.primer_apellido, p_doc.segundo_apellido) AS docente_nombre,
                    CONCAT(p_doc.tipo_documento, '-', p_doc.numero_documento) AS docente_ci
                FROM estudiante est
                INNER JOIN persona p ON p.id_persona = est.id_persona
                LEFT JOIN parroquia par ON par.id_parroquia = p.id_parroquia_nacimiento
                LEFT JOIN municipio mun ON mun.id_municipio = par.id_municipio
                LEFT JOIN estado est_mun ON est_mun.id_estado = mun.id_estado
                LEFT JOIN estado est_nac ON est_nac.id_estado = p.id_estado_nacimiento
                INNER JOIN matricula m ON m.id_estudiante = est.id_estudiante
                INNER JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
                INNER JOIN anio_escolar a ON a.id_anio_escolar = m.id_anio_escolar
                INNER JOIN grado g ON g.id_grado = ea.id_grado
                LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion
                LEFT JOIN asignacion_docente ad ON ad.id_estructura_academica = ea.id_estructura_academica
                    AND (ad.fecha_fin IS NULL OR ad.fecha_fin >= CURDATE())
                LEFT JOIN docente d_doc ON d_doc.id_docente = ad.id_docente
                LEFT JOIN empleado e_doc ON e_doc.id_empleado = d_doc.id_empleado
                LEFT JOIN persona p_doc ON p_doc.id_persona = e_doc.id_persona
                LEFT JOIN resultado_academico ra ON ra.id_estudiante = est.id_estudiante
                    AND ra.id_anio_escolar = m.id_anio_escolar
                LEFT JOIN grado g_dest ON g_dest.id_grado = ra.id_grado_destino
                WHERE est.id_estudiante = :id_estudiante
                  AND m.id_estructura_academica = :id_estructura
                  AND m.tipo IN ('INSCRIPCION', 'RATIFICACION')
                LIMIT 1
            ";

            $stmt = $this->conexion()->prepare($sql);
            $stmt->execute([
                'id_estudiante' => $idEstudiante,
                'id_estructura' => $idEstructura,
            ]);
            $estudiante = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$estudiante) {
                http_response_code(404);
                header('Content-Type: text/html; charset=UTF-8');
                echo '<!DOCTYPE html><meta charset="utf-8"><title>Estudiante no encontrado</title>'
                   . '<p>El estudiante no pertenece a la matrícula de esta sección.</p>';
                exit;
            }

            $literal = trim((string) ($estudiante['literal'] ?? ''));
            if ($literal === '') {
                http_response_code(422);
                header('Content-Type: text/html; charset=UTF-8');
                echo '<!DOCTYPE html><meta charset="utf-8"><title>Sin literal</title>'
                   . '<p>El estudiante no tiene literal cargado. No se puede emitir el certificado sin resultado pedagógico.</p>';
                exit;
            }

            $gradoNombre = (string) ($estudiante['grado'] ?? '');
            $esSexto = (bool) preg_match('/^6|sexto/i', $gradoNombre);
            if ($esSexto && in_array(strtoupper($literal), ['A', 'B', 'C', 'D'], true)) {
                http_response_code(422);
                header('Content-Type: text/html; charset=UTF-8');
                echo '<!DOCTYPE html><meta charset="utf-8"><title>Estudiante Graduando</title>'
                   . '<h1>Estudiante Promovido de 6.° Grado</h1>'
                   . '<p>El estudiante culminó la Educación Primaria y egresa al Liceo. Su documento oficial probatorio es el <strong>Certificado de Educación Primaria</strong> expedido por la Dirección y Secretaría del Plantel en el módulo de Emisión de Constancias, no la boleta de calificaciones ordinaria.</p>';
                exit;
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
                (new BitacoraModel($this->conexion()))->registrar($idUsuario, $accion, 'Evaluación');
            }

            $nombreLimpio = preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $datosEstudiante['nombre_completo']);
            $filename = "certificado_calificaciones_{$nombreLimpio}.pdf";

            header_remove('X-Frame-Options');
            header('X-Frame-Options: SAMEORIGIN');
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($pdfBinary));
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
            echo $pdfBinary;
            exit;

        } catch (DomainException $e) {
            http_response_code(422);
            header('Content-Type: text/html; charset=UTF-8');
            echo '<!DOCTYPE html><meta charset="utf-8"><title>Error de Configuración</title>'
               . '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
            exit;
        } catch (Throwable $e) {
            error_log('CertificadoCalificacionesController::generarIndividual: ' . $e->getMessage());
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
            echo '<!DOCTYPE html><meta charset="utf-8"><title>Error</title>'
               . '<p>Error al generar el certificado: ' . htmlspecialchars($e->getMessage()) . '</p>';
            exit;
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
            http_response_code(400);
            header('Content-Type: text/html; charset=UTF-8');
            echo '<!DOCTYPE html><meta charset="utf-8"><title>400 - Solicitud inválida</title>'
               . '<p>Falta el parámetro de la estructura académica.</p>';
            exit;
        }

        // SEGURIDAD: Solo la docente asignada a la sección puede emitir
        $this->verificarDocenteAsignada($idEstructura);

        try {
            $sql = "
                SELECT 
                    est.id_estudiante,
                    CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS nombre_completo,
                    p.tipo_documento,
                    p.numero_documento,
                    p.fecha_nacimiento,
                    p.sexo,
                    par.nombre AS parroquia_nacimiento,
                    mun.nombre AS municipio_nacimiento,
                    COALESCE(est_nac.nombre, est_mun.nombre) AS estado_nacimiento,
                    g.nombre AS grado,
                    s.nombre AS seccion,
                    a.nombre_periodo AS periodo_escolar,
                    ra.literal,
                    ra.estado_resultado,
                    ra.id_grado_destino,
                    g_dest.nombre AS grado_destino_nombre,
                    m.id_matricula,
                    m.id_anio_escolar,
                    m.id_estructura_academica,
                    CONCAT_WS(' ', p_doc.primer_nombre, p_doc.segundo_nombre, p_doc.primer_apellido, p_doc.segundo_apellido) AS docente_nombre,
                    CONCAT(p_doc.tipo_documento, '-', p_doc.numero_documento) AS docente_ci
                FROM estudiante est
                INNER JOIN persona p ON p.id_persona = est.id_persona
                LEFT JOIN parroquia par ON par.id_parroquia = p.id_parroquia_nacimiento
                LEFT JOIN municipio mun ON mun.id_municipio = par.id_municipio
                LEFT JOIN estado est_mun ON est_mun.id_estado = mun.id_estado
                LEFT JOIN estado est_nac ON est_nac.id_estado = p.id_estado_nacimiento
                INNER JOIN matricula m ON m.id_estudiante = est.id_estudiante
                INNER JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
                INNER JOIN anio_escolar a ON a.id_anio_escolar = m.id_anio_escolar
                INNER JOIN grado g ON g.id_grado = ea.id_grado
                LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion
                LEFT JOIN asignacion_docente ad ON ad.id_estructura_academica = ea.id_estructura_academica
                    AND (ad.fecha_fin IS NULL OR ad.fecha_fin >= CURDATE())
                LEFT JOIN docente d_doc ON d_doc.id_docente = ad.id_docente
                LEFT JOIN empleado e_doc ON e_doc.id_empleado = d_doc.id_empleado
                LEFT JOIN persona p_doc ON p_doc.id_persona = e_doc.id_persona
                LEFT JOIN resultado_academico ra ON ra.id_estudiante = est.id_estudiante
                    AND ra.id_anio_escolar = m.id_anio_escolar
                LEFT JOIN grado g_dest ON g_dest.id_grado = ra.id_grado_destino
                WHERE m.id_estructura_academica = :id_estructura
                  AND m.tipo IN ('INSCRIPCION', 'RATIFICACION')
                  AND ra.literal IS NOT NULL
                  AND ra.literal != ''
                ORDER BY p.primer_apellido ASC, p.primer_nombre ASC
            ";

            $stmt = $this->conexion()->prepare($sql);
            $stmt->execute(['id_estructura' => $idEstructura]);
            $estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($estudiantes)) {
                http_response_code(422);
                header('Content-Type: text/html; charset=UTF-8');
                echo '<!DOCTYPE html><meta charset="utf-8"><title>Sin estudiantes evaluados</title>'
                   . '<p>No hay estudiantes con literales cargados en esta sección para emitir certificados.</p>';
                exit;
            }

            $gradoNombre = (string) ($estudiantes[0]['grado'] ?? '');
            $esSexto = (bool) preg_match('/^6|sexto/i', $gradoNombre);
            if ($esSexto) {
                // En 6.° grado solo reciben Certificado de Calificaciones quienes no alcanzaron las competencias (literal E)
                $estudiantes = array_values(array_filter($estudiantes, function ($est) {
                    return strtoupper(trim((string) ($est['literal'] ?? ''))) === 'E';
                }));

                if (empty($estudiantes)) {
                    http_response_code(422);
                    header('Content-Type: text/html; charset=UTF-8');
                    echo '<!DOCTYPE html><meta charset="utf-8"><title>Sección de 6.° Grado</title>'
                       . '<h1>Certificados de Educación Primaria</h1>'
                       . '<p>En 6.° grado los estudiantes promovidos reciben el <strong>Certificado de Educación Primaria</strong> emitido por la Dirección y Secretaría en el módulo de Emisión de Constancias. No hay estudiantes no promovidos con literal E en esta sección.</p>';
                    exit;
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
                (new BitacoraModel($this->conexion()))->registrar($idUsuario, $accion, 'Evaluación');
            }

            $filename = "certificados_seccion_{$idEstructura}.pdf";

            header_remove('X-Frame-Options');
            header('X-Frame-Options: SAMEORIGIN');
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($pdfBinary));
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
            echo $pdfBinary;
            exit;

        } catch (DomainException $e) {
            http_response_code(422);
            header('Content-Type: text/html; charset=UTF-8');
            echo '<!DOCTYPE html><meta charset="utf-8"><title>Error de Configuración</title>'
               . '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
            exit;
        } catch (Throwable $e) {
            error_log('CertificadoCalificacionesController::generarPorSeccion: ' . $e->getMessage());
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
            echo '<!DOCTYPE html><meta charset="utf-8"><title>Error</title>'
               . '<p>Error al generar los certificados de la sección: ' . htmlspecialchars($e->getMessage()) . '</p>';
            exit;
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
