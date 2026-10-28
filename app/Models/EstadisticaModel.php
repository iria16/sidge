<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\AniosEscolares;

/**
 * Datos de la pantalla de Estadísticas Institucionales.
 *
 * El controlador solo entrega los parámetros de la petición; este modelo
 * resuelve el año, valida la vista pedida y reúne las filas de la tabla.
 * Internamente combina las métricas del dashboard directivo con los reportes.
 */
class EstadisticaModel extends Model
{
    private const VISTAS_PERMITIDAS = [
        'matricula',
        'rendimiento',
        'edades',
        'cobertura',
        'personal',
        'salud',
        'tallas',
    ];

    public function getAniosEscolares(): array
    {
        return (new ReporteModel($this->pdo))->getAniosEscolares();
    }

    /**
     * Año solicitado; si no llega o no es válido, usa el activo y, en su
     * defecto, el primero de la lista.
     */
    public function resolverAnio(array $anios, ?int $solicitado): int
    {
        if ($solicitado !== null && $solicitado > 0) {
            return $solicitado;
        }

        $idAnio = AniosEscolares::idEnCurso($this->pdo);

        if ($idAnio <= 0 && $anios !== []) {
            $idAnio = (int) ($anios[0]['id_anio_escolar'] ?? 0);
        }

        return $idAnio;
    }

    public function nombrePeriodo(array $anios, int $idAnio): string
    {
        foreach ($anios as $anio) {
            if ((int) ($anio['id_anio_escolar'] ?? 0) === $idAnio) {
                return (string) ($anio['nombre_periodo'] ?? '');
            }
        }

        return '';
    }

    public function normalizarVista(?string $vista): string
    {
        return in_array($vista, self::VISTAS_PERMITIDAS, true) ? $vista : 'matricula';
    }

    /**
     * Filas y series que consume la vista según la pestaña activa.
     *
     * @return array{
     *   filas: array,
     *   metricasSalud: array,
     *   tiposSangre: array,
     *   filasAntropometricas: array,
     *   distribucionTallas: array
     * }
     */
    public function getDatosVista(string $vista, int $idAnio): array
    {
        $dashboard = new DirectivoDashboardModel($this->pdo);
        $reporte   = new ReporteModel($this->pdo);

        $filas                = [];
        $metricasSalud        = [];
        $tiposSangre          = [];
        $filasAntropometricas = [];
        $distribucionTallas   = [];

        switch ($vista) {
            case 'salud':
                $salud = $dashboard->getEstadisticasSalud($idAnio);
                $metricasSalud        = $salud['resumen'];
                $tiposSangre          = $salud['tipos_sangre'];
                $filas                = $salud['por_grado'];
                $filasAntropometricas = $dashboard->getEstadisticasAntropometricas($idAnio)['por_grado'];
                break;

            case 'tallas':
                $tallas = $dashboard->getEstadisticasTallas($idAnio);
                $distribucionTallas = $tallas['por_prenda'];
                $filas              = $tallas['por_grado'];
                break;

            case 'personal':
                $filas = $dashboard->getEstadisticasPersonal();
                break;

            case 'rendimiento':
                $filas = $dashboard->getResultadosPorSeccion($idAnio);
                break;

            case 'edades':
                $filas = $dashboard->getDistribucionPorEdadYSexo($idAnio);
                break;

            case 'cobertura':
                $filas = $reporte->getDistribucionAcademica($idAnio);
                break;

            case 'matricula':
            default:
                $filas = $dashboard->getMatriculaPorGradoYSexo($idAnio);
                break;
        }

        return compact(
            'filas',
            'metricasSalud',
            'tiposSangre',
            'filasAntropometricas',
            'distribucionTallas'
        );
    }
}
