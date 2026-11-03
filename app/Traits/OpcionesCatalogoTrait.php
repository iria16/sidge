<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\TurnoModel;
use App\Support\AniosEscolares;
use PDO;

/**
 * Trait OpcionesCatalogoTrait
 * -----------------------------------------------------------------------------
 * Centraliza la consulta de catálogos comunes (grado, seccion, turno, anio_escolar)
 * para filtros, selects y modales en los modelos que lo requieran.
 */
trait OpcionesCatalogoTrait
{
    /**
     * Catálogos activos (id + nombre) para filtros y modales:
     * anio_escolar, grado, seccion y turno.
     *
     * @param string $table Nombre del catálogo solicitado
     * @param array<string>|null $turnosExcluidos Lista opcional de nombres de turnos a excluir (ej. personal)
     * @return array<int, array{id: int|string, nombre: string}>
     */
    public function getOptions(string $table, ?array $turnosExcluidos = null): array
    {
        $catalogos = [
            'anio_escolar' => ['etiqueta' => 'nombre_periodo', 'orden' => 'anio_inicio DESC'],
            'grado'        => ['etiqueta' => 'nombre', 'orden' => 'nombre'],
            'seccion'      => ['etiqueta' => 'nombre', 'orden' => 'nombre'],
            'turno'        => ['etiqueta' => 'nombre', 'orden' => 'nombre'],
        ];

        if (!isset($catalogos[$table])) {
            return [];
        }

        $etiqueta = $catalogos[$table]['etiqueta'];
        $orden    = $catalogos[$table]['orden'];
        $whereExtra = '';

        if ($table === 'turno' && $turnosExcluidos !== null && !empty($turnosExcluidos)) {
            $placeholders = implode(',', array_map(fn($t) => $this->pdo->quote($t), $turnosExcluidos));
            $whereExtra = " AND {$etiqueta} NOT IN ({$placeholders})";
        }

        $stmt = $this->pdo->query(
            "SELECT id_{$table} AS id, {$etiqueta} AS nombre
             FROM {$table} WHERE estado = 'ACTIVO'{$whereExtra} ORDER BY {$orden}"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Obtiene la lista completa de años escolares para selectores, reportes o filtros.
     *
     * @return array<int, array{id_anio_escolar: int, nombre_periodo: string, estado: string}>
     */
    public function getAniosEscolares(): array
    {
        try {
            $stmt = $this->pdo->query(
                "SELECT id_anio_escolar, nombre_periodo, estado
                   FROM anio_escolar
                  ORDER BY anio_inicio DESC, id_anio_escolar DESC"
            );
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            error_log('OpcionesCatalogoTrait::getAniosEscolares: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene el ID del año escolar activo usando la clase centralizada AniosEscolares.
     */
    public function getActiveSchoolYearId(): ?int
    {
        $id = AniosEscolares::idEnCurso($this->pdo);
        return $id > 0 ? $id : null;
    }

    /**
     * Obtiene el nombre del año escolar activo usando la clase centralizada AniosEscolares.
     */
    public function getActiveSchoolYearName(): ?string
    {
        $nombre = AniosEscolares::nombreEnCurso($this->pdo);
        return $nombre !== '' ? $nombre : null;
    }
}
