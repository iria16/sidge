<?php
declare(strict_types=1);

namespace App\Models;

use PDO;
use Throwable;

abstract class Model
{
    protected PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Ejecuta una operación atómica dentro de una transacción de base de datos.
     *
     * Si ya existe una transacción activa en la conexión PDO, delega en ella sin
     * intentar abrir otra (lo cual provocaría un fallo lanzado por PDO:
     * "There is already an active transaction").
     *
     * En caso de transacciones anidadas, cualquier excepción capturada debe
     * propagarse obligatoriamente hacia el llamador original para que la transacción
     * externa efectúe el rollBack() correspondiente y no deje estados inconsistentes.
     *
     * @param callable(static): mixed $callback Función que recibe la instancia del modelo.
     * @return mixed El valor retornado por el callback.
     * @throws Throwable Si ocurre algún fallo durante la ejecución.
     */
    protected function escapeLike(string $valor): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $valor);
    }

    protected function transaction(callable $callback): mixed
    {
        $yaEnTransaccion = $this->pdo->inTransaction();

        if (!$yaEnTransaccion) {
            $this->pdo->beginTransaction();
        }

        try {
            $resultado = $callback($this);

            if (!$yaEnTransaccion) {
                $this->pdo->commit();
            }

            return $resultado;
        } catch (Throwable $e) {
            if (!$yaEnTransaccion && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }
}