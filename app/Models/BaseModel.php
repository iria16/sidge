<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Modelo base con el CRUD genérico sobre $table y $primaryKey.
 * Los modelos hijos sobrescriben lo que necesiten (joins, estados propios, filtros).
 */
abstract class BaseModel extends Model
{
    /** Nombre de la tabla; cada hijo debe asignarlo. */
    protected string $table;

    /** Columna de clave primaria (por defecto 'id'). */
    protected string $primaryKey = 'id';

    /**
     * Lista registros con filtros y paginación, del más reciente al más antiguo por clave primaria.
     * Los filtros se arman con buildFilters().
     */
    public function getAll(array $filters = [], int $page = 1, int $perPage = 10): array
    {
        [$where, $params] = $this->buildFilters($filters);

        $sql = "SELECT * FROM {$this->table}";
        if ($where) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        $sql .= " ORDER BY {$this->primaryKey} DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cuenta los registros que cumplen los filtros; sirve para calcular el total de páginas.
     */
    public function countAll(array $filters = []): int
    {
        [$where, $params] = $this->buildFilters($filters);
        $sql = "SELECT COUNT(*) FROM {$this->table}";
        if ($where) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** @var array<string, array<string>> Cache de columnas por tabla */
    private static array $columnCache = [];

    /**
     * Obtiene la lista de columnas reales de la tabla (con cache en memoria).
     *
     * @return array<string>
     */
    protected function getTableColumns(): array
    {
        if (!isset(self::$columnCache[$this->table])) {
            $stmt = $this->pdo->query("SHOW COLUMNS FROM `{$this->table}`");
            self::$columnCache[$this->table] = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        }

        return self::$columnCache[$this->table];
    }

    /**
     * Comprueba que todas las claves del arreglo correspondan a columnas reales de la tabla.
     *
     * @throws \InvalidArgumentException si se detecta una columna que no existe en el esquema.
     */
    protected function validarColumnas(array $data): void
    {
        $columnasReales = $this->getTableColumns();
        foreach (array_keys($data) as $columna) {
            if (!in_array($columna, $columnasReales, true)) {
                throw new \InvalidArgumentException("La columna '{$columna}' no existe en la tabla '{$this->table}'.");
            }
        }
    }

    /**
     * Busca un registro por su clave primaria; devuelve null si no existe.
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = :p_pk");
        $stmt->execute([':p_pk' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Inserta un registro validando que las columnas existan en la tabla
     * y asociando parámetros con prefijo nombrado :p_columna.
     *
     * @throws \InvalidArgumentException si $data está vacío o contiene columnas inexistentes.
     */
    public function create(array $data): bool
    {
        if (empty($data)) {
            throw new \InvalidArgumentException("No se proporcionaron datos para insertar en la tabla '{$this->table}'.");
        }

        $this->validarColumnas($data);

        $cols = array_keys($data);
        $ph   = array_map(fn($c) => ":p_$c", $cols);
        $sql  = "INSERT INTO `{$this->table}` (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', $ph) . ")";

        $params = [];
        foreach ($data as $col => $val) {
            $params[":p_$col"] = $val;
        }

        return $this->pdo->prepare($sql)->execute($params);
    }

    /**
     * Actualiza las columnas indicadas en $data del registro con ese id.
     * Excluye la clave primaria del SET antes de validar que queden datos por actualizar.
     *
     * @throws \InvalidArgumentException si no hay datos para actualizar tras excluir la PK
     *                                   o si se especifican columnas ajenas a la tabla.
     */
    public function update(int $id, array $data): bool
    {
        unset($data[$this->primaryKey]);

        if (empty($data)) {
            throw new \InvalidArgumentException("No hay datos para actualizar en la tabla '{$this->table}'.");
        }

        $this->validarColumnas($data);

        $sets = array_map(fn($c) => "`$c` = :p_$c", array_keys($data));
        $sql  = "UPDATE `{$this->table}` SET " . implode(',', $sets) . " WHERE `{$this->primaryKey}` = :p_pk";

        $params = [':p_pk' => $id];
        foreach ($data as $col => $val) {
            $params[":p_$col"] = $val;
        }

        return $this->pdo->prepare($sql)->execute($params);
    }

    /**
     * Elimina físicamente el registro con ese id.
     */
    public function delete(int $id): bool
    {
        return $this->pdo->prepare("DELETE FROM `{$this->table}` WHERE `{$this->primaryKey}` = :p_pk")
                         ->execute([':p_pk' => $id]);
    }

    /**
     * Alterna $field entre 'ACTIVO' e 'INACTIVO'.
     * Los modelos con otro conjunto de estados (como AcademicYearModel) lo sobrescriben.
     */
    public function toggleStatus(int $id, string $field = 'estado'): bool
    {
        $sql = "UPDATE `{$this->table}` 
                SET `$field` = CASE WHEN `$field` = 'ACTIVO' THEN 'INACTIVO' ELSE 'ACTIVO' END
                WHERE `{$this->primaryKey}` = :p_pk";
        
        return $this->pdo->prepare($sql)->execute([':p_pk' => $id]);
    }

    /**
     * Convierte los filtros en condiciones WHERE y parámetros; ignora los vacíos.
     * La clave 'search' busca con LIKE en searchableFields(); cualquier otra clave se compara por igualdad.
     */
    protected function buildFilters(array $filters): array
    {
        $where = [];
        $params = [];

        foreach ($filters as $field => $value) {
            if ($value === '' || $value === null) {
                continue;
            }

            if ($field === 'search') {
                $conds = [];
                foreach ($this->searchableFields() as $i => $col) {
                    $key = "search$i";
                    $conds[] = "$col LIKE :$key";
                    $params[$key] = "%$value%";
                }
                if ($conds) {
                    $where[] = "(" . implode(" OR ", $conds) . ")";
                }
            } else {
                $where[] = "$field = :$field";
                $params[$field] = $value;
            }
        }

        return [$where, $params];
    }

    /**
     * Columnas donde busca el filtro 'search'; los hijos las sobrescriben (vacío por defecto).
     */
    protected function searchableFields(): array
    {
        return [];
    }
}