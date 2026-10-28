<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\AniosEscolares;
use Throwable;

/**
 * Datos del dashboard de Secretaría.
 *
 * Reúne en un solo modelo el año activo, el turno, el estado de la estructura
 * y las estadísticas del turno, para que el controlador no instancie cinco
 * modelos ni decida el orden de las consultas.
 */
class SecretariaDashboardModel extends Model
{
    /**
     * @return array{
     *   anioActivo: array{id:int,nombre:string}|null,
     *   turnoNombre: string|null,
     *   estructura: mixed,
     *   estadisticasEstudiantes: array<string,mixed>|null,
     *   estadisticasInscripciones: array<string,mixed>|null,
     *   estadisticasRatificacion: array<string,mixed>|null,
     *   estadisticasRetiros: array<string,mixed>|null,
     *   cuposPorEstructura: array<string,mixed>|null
     * }
     */
    public function getDatos(int $idTurno): array
    {
        $anioId     = AniosEscolares::idEnCurso($this->pdo);
        $anioNombre = AniosEscolares::nombreEnCurso($this->pdo);
        $anioActivo = $anioId > 0
            ? ['id' => $anioId, 'nombre' => $anioNombre]
            : null;

        $turnoNombre = null;
        try {
            $turnoNombre = (new TurnoModel($this->pdo))->getNombreById($idTurno);
        } catch (Throwable $e) {
            error_log('SecretariaDashboardModel::getDatos turno: ' . $e->getMessage());
        }

        $estructura = null;
        try {
            $estructura = (new AcademicStructureModel($this->pdo))->pendientesDeConfiguracion();
        } catch (Throwable $e) {
            error_log('SecretariaDashboardModel::getDatos estructura: ' . $e->getMessage());
        }

        $estadisticasEstudiantes   = null;
        $estadisticasInscripciones = null;
        $estadisticasRatificacion  = null;
        $estadisticasRetiros       = null;
        $cuposPorEstructura        = null;

        if ($anioActivo !== null && $idTurno > 0) {
            $estadisticasEstudiantes = $this->segura(
                fn (): array => (new EstudianteModel($this->pdo))->getStats([
                    'anio'     => $anioId,
                    'id_turno' => $idTurno,
                ]),
                'estudiantes'
            );

            $estadisticasInscripciones = $this->segura(
                fn (): array => (new InscripcionesModel($this->pdo))->getStats($idTurno, $anioId),
                'inscripciones'
            );

            // RatificacionModel resuelve el año activo dentro de getStats(); aquí solo se acota el turno.
            $estadisticasRatificacion = $this->segura(
                fn (): array => (new RatificacionModel($this->pdo))->getStats($idTurno),
                'ratificación'
            );

            $estadisticasRetiros = $this->segura(
                fn (): array => (new WithdrawalModel($this->pdo))->getStats($idTurno, $anioId),
                'retiros'
            );

            $cuposPorEstructura = $this->segura(
                fn (): array => (new InscripcionesModel($this->pdo))->getCuposPorEstructura($idTurno, $anioId),
                'cupos por estructura'
            );
        }

        return compact(
            'anioActivo',
            'turnoNombre',
            'estructura',
            'estadisticasEstudiantes',
            'estadisticasInscripciones',
            'estadisticasRatificacion',
            'estadisticasRetiros',
            'cuposPorEstructura'
        );
    }

    /**
     * Ejecuta una consulta de indicadores sin tumbar el resto del panel.
     *
     * @param callable():array<string,mixed> $consulta
     * @return array<string,mixed>|null
     */
    private function segura(callable $consulta, string $nombre): ?array
    {
        try {
            return $consulta();
        } catch (Throwable $e) {
            error_log("SecretariaDashboardModel::getDatos {$nombre}: " . $e->getMessage());

            return null;
        }
    }
}
