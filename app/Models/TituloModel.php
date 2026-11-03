<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * TituloModel Catálogo `titulo`: desde que la tabla `titulo` tiene la columna
 * `id_grado_academico` (NOT NULL, FK a grado_academico), todo título pertenece a un
 * grado. Por eso sobrescribe create()/update() de CatalogModel (que solo permite la
 * columna 'nombre') con SQL directo, igual que hace InstitucionModel con la columna
 * `tipo`.
 */
class TituloModel extends CatalogModel
{
    protected string $table = 'titulo';
    protected string $primaryKey = 'id_titulo';

    protected array $columns = ['id_titulo', 'nombre', 'id_grado_academico', 'estado'];
    protected array $allowedColumns = ['id_titulo', 'nombre', 'id_grado_academico', 'estado'];
    protected string $campoNombre = 'nombre';

    /**
 * Un mismo nombre de título puede existir en varios grados académicos (p. ej.
 *
 * @param string      $nombre    Nombre normalizado (case-insensitive).
 * @param int         $idGrado   Grado académico donde se intenta guardar.
 * @param int|null    $excludeId Id de título a ignorar (edición).
 */
    public function existeTituloEnGrado(string $nombre, int $idGrado, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*)
                FROM titulo
                WHERE LOWER(nombre) = LOWER(:nombre)
                  AND id_grado_academico = :id_grado_academico';
        $params = [
            'nombre'             => trim($nombre),
            'id_grado_academico' => $idGrado,
        ];

        if ($excludeId !== null && $excludeId > 0) {
            $sql .= ' AND id_titulo != :excludeId';
            $params['excludeId'] = $excludeId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * @return array<int, array{id_grado_academico:int, nombre:string}>
     */
    public function gradosAcademicosActivos(): array
    {
        return $this->pdo->query(
            "SELECT id_grado_academico, nombre
               FROM grado_academico
              WHERE estado = 'ACTIVO'
              ORDER BY nombre"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function gradoAcademicoActivo(int $idGrado): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*)
               FROM grado_academico
              WHERE id_grado_academico = :id AND estado = 'ACTIVO'"
        );
        $stmt->execute(['id' => $idGrado]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * @param array $data Se espera 'nombre' y 'id_grado_academico'.
     */
    public function create(array $data): bool
    {
        return $this->createAndGetId($data) > 0;
    }

    /**
     * Inserta el título y devuelve su id autogenerado.
     * Lo usa la alta rápida del wizard, que necesita el id para la fila.
     */
    public function createAndGetId(array $data): int
    {
        $this->pdo->prepare(
            'INSERT INTO titulo (nombre, id_grado_academico)
             VALUES (:nombre, :id_grado_academico)'
        )->execute([
            'nombre'             => trim((string) ($data['nombre'] ?? '')),
            'id_grado_academico' => (int) ($data['id_grado_academico'] ?? 0),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array $data Se espera 'nombre' y 'id_grado_academico'.
     */
    public function update(int $id, array $data): bool
    {
        return $this->pdo->prepare(
            'UPDATE titulo
             SET nombre = :nombre, id_grado_academico = :id_grado_academico
             WHERE id_titulo = :id'
        )->execute([
            'id'                 => $id,
            'nombre'             => trim((string) ($data['nombre'] ?? '')),
            'id_grado_academico' => (int) ($data['id_grado_academico'] ?? 0),
        ]);
    }
}