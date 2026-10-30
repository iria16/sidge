<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AcademicResultModel;

/**
 * Cálculos de presentación de la pantalla de resultados académicos.
 *
 * La vista no debe sumar ni deducir reglas de negocio: recibe las filas ya
 * resueltas (totales, insignias, bloqueo y siguiente grado) y solo se encarga
 * de escapar y pintar. La regla de qué literal promueve vive en el modelo.
 */
final class ResultadoAcademicoVista
{
    /**
     * Totales y filas listas para la bandeja de secciones.
     *
     * @param array<int, array<string, mixed>> $secciones
     * @return array<string, mixed>
     */
    public static function resumenSecciones(array $secciones): array
    {
        $filas   = [];
        $totales = [
            'total_secciones'    => count($secciones),
            'total_estudiantes'  => 0,
            'total_cargados'     => 0,
            'total_pendientes'   => 0,
            'total_promovidos'   => 0,
            'total_no_promovidos' => 0,
        ];

        foreach ($secciones as $sec) {
            $estudiantes  = (int) ($sec['total_estudiantes'] ?? 0);
            $cargados     = (int) ($sec['total_cargados'] ?? 0);
            $pendientes   = (int) ($sec['total_pendientes'] ?? 0);
            $promovidos   = (int) ($sec['total_promovidos'] ?? 0);
            $noPromovidos = (int) ($sec['total_no_promovidos'] ?? 0);
            $docente      = trim((string) ($sec['docente_nombre'] ?? ''));

            $totales['total_estudiantes']   += $estudiantes;
            $totales['total_cargados']      += $cargados;
            $totales['total_pendientes']    += $pendientes;
            $totales['total_promovidos']    += $promovidos;
            $totales['total_no_promovidos'] += $noPromovidos;

            if ($estudiantes === 0) {
                $badgeStatus = 'inactive';
                $badgeTexto  = 'Sin estudiantes';
            } elseif ($pendientes === 0) {
                $badgeStatus = 'active';
                $badgeTexto  = 'Carga completa';
            } elseif ($cargados === 0) {
                $badgeStatus = 'pending';
                $badgeTexto  = 'Sin iniciar';
            } else {
                $badgeStatus = 'info';
                $badgeTexto  = 'En progreso';
            }

            $filas[] = [
                'id'             => (int) ($sec['id'] ?? 0),
                'grado'          => (string) ($sec['grado'] ?? ''),
                'seccion'        => (string) ($sec['seccion'] ?? ''),
                'anio_escolar'   => (string) ($sec['anio_escolar'] ?? ''),
                'turno'          => (string) ($sec['turno'] ?? ''),
                'docente'        => $docente !== '' ? $docente : 'Sin asignar',
                'estudiantes'    => $estudiantes,
                'cargados'       => $cargados,
                'pendientes'     => $pendientes,
                'promovidos'     => $promovidos,
                'no_promovidos'  => $noPromovidos,
                'badge_status'   => $badgeStatus,
                'badge_texto'    => $badgeTexto,
            ];
        }

        $totales['total_cargados'] = min($totales['total_cargados'], $totales['total_estudiantes']);
        $totales['porcentaje_cargado'] = $totales['total_estudiantes'] > 0
            ? (int) round(($totales['total_cargados'] / $totales['total_estudiantes']) * 100)
            : 0;

        return ['filas' => $filas] + $totales;
    }

    /**
     * Totales, siguiente grado y filas listas para la nómina de una sección.
     *
     * @param array<string, mixed> $detalle
     * @return array<string, mixed>
     */
    public static function resumenDetalle(array $detalle, bool $esDocente): array
    {
        $estudiantes = $detalle['estudiantes'] ?? [];
        $grados      = $detalle['grados_catalogo'] ?? [];
        $idGrado     = (int) ($detalle['id_grado'] ?? 0);

        $idGradoSiguiente     = null;
        $nombreGradoSiguiente = null;
        foreach ($grados as $idx => $grado) {
            if ((int) $grado['id'] === $idGrado && isset($grados[$idx + 1])) {
                $idGradoSiguiente     = (int) $grados[$idx + 1]['id'];
                $nombreGradoSiguiente = (string) $grados[$idx + 1]['nombre'];
                break;
            }
        }

        $esUltimoGrado    = $idGradoSiguiente === null;
        $textoGradoAvanza = $esUltimoGrado
            ? 'Egreso de Primaria'
            : ($nombreGradoSiguiente ?? 'Grado Superior');

        $filas            = [];
        $total            = count($estudiantes);
        $conLiteral       = 0;
        $sinLiteral       = 0;
        $promovidos       = 0;
        $noPromovidos     = 0;
        $pendientes       = 0;

        foreach ($estudiantes as $est) {
            $literal = strtoupper(trim((string) ($est['literal'] ?? '')));
            $estado  = strtoupper(trim((string) ($est['estado_resultado'] ?? AcademicResultModel::ESTADO_PENDIENTE)));
            if ($estado === '') {
                $estado = AcademicResultModel::ESTADO_PENDIENTE;
            }

            if ($literal !== '') {
                $conLiteral++;
            } else {
                $sinLiteral++;
            }

            if ($estado === AcademicResultModel::ESTADO_PROMOVIDO
                || in_array($literal, AcademicResultModel::LITERALES_PROMUEVEN, true)) {
                $promovidos++;
            } elseif ($estado === AcademicResultModel::ESTADO_NO_PROMOVIDO || $literal === 'E') {
                $noPromovidos++;
            } else {
                $pendientes++;
            }

            $tipoDocumento = strtoupper(trim((string) ($est['tipo_documento'] ?? '')));
            $numeroDoc     = trim((string) ($est['numero_documento'] ?? ''));
            $documento     = $numeroDoc !== ''
                ? (($tipoDocumento !== '' ? $tipoDocumento . '-' : '') . $numeroDoc)
                : 'Sin documento';

            $apellido = explode(' ', trim((string) ($est['primer_apellido'] ?? '')))[0] ?? '';
            $nombre   = explode(' ', trim((string) ($est['primer_nombre'] ?? '')))[0] ?? '';
            $iniciales = strtoupper(
                substr($apellido !== '' ? $apellido : 'E', 0, 1)
                . substr($nombre !== '' ? $nombre : 'S', 0, 1)
            );

            $propuestoPor = trim((string) ($est['propuesto_por_nombre'] ?? ''));
            $fechaCarga   = !empty($est['fecha_propuesta'])
                ? date('d/m/Y', strtotime((string) $est['fecha_propuesta']))
                : '';

            $filas[] = [
                'id_estudiante'   => (int) ($est['id_estudiante'] ?? 0),
                'id_matricula'    => (int) ($est['id_matricula'] ?? 0),
                'nombre_completo' => (string) ($est['nombre_completo'] ?? ''),
                'documento'       => $documento,
                'avatar_iniciales' => $iniciales,
                'literal'         => $literal,
                'estado'          => $estado,
                'id_grado_destino' => (int) ($est['id_grado_destino'] ?? 0),
                'observaciones'   => (string) ($est['observaciones'] ?? ''),
                'propuesto_por'   => $propuestoPor,
                'fecha_carga'     => $fechaCarga,
                'cargado_por'     => $propuestoPor !== '' ? $propuestoPor : 'Docente',
            ];
        }

        return [
            'filas'                => $filas,
            'total'                => $total,
            'con_literal'          => $conLiteral,
            'sin_literal'          => $sinLiteral,
            'promovidos'           => $promovidos,
            'no_promovidos'        => $noPromovidos,
            'pendientes'           => $pendientes,
            'porcentaje_carga'     => $total > 0 ? (int) round(($conLiteral / $total) * 100) : 0,
            'id_grado_siguiente'   => $idGradoSiguiente,
            'nombre_grado_siguiente' => $nombreGradoSiguiente,
            'es_ultimo_grado'      => $esUltimoGrado,
            'texto_grado_avanza'   => $textoGradoAvanza,
        ];
    }
}
