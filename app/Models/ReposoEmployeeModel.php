<?php

declare(strict_types=1);

namespace App\Models;

use App\Interfaces\Creatable;
use App\Interfaces\Updatable;
use PDO;
use Throwable;

/**
 * Reposos del personal: empleados y docentes. El reposo es un evento con inicio y fin.
 */
class ReposoEmployeeModel extends BaseModel implements Creatable, Updatable
{
    protected string $table      = 'reposo_empleado';
    protected string $primaryKey = 'id_reposo';

    protected array $columns = ['id_reposo', 'id_empleado', 'fecha_inicio', 'fecha_fin', 'motivo', 'observacion', 'estado', 'id_usuario_registro', 'fecha_creacion', 'fecha_actualizacion'];
    protected array $allowedColumns = ['id_reposo', 'id_empleado', 'fecha_inicio', 'fecha_fin', 'motivo', 'observacion', 'estado', 'id_usuario_registro', 'fecha_creacion', 'fecha_actualizacion'];

    /** Condición única de "reposo vigente hoy"; usa el alias `r`. */
    public const CONDICION_VIGENTE =
        "r.estado = 'VIGENTE' AND CURDATE() BETWEEN r.fecha_inicio AND r.fecha_fin";

    /**
     * Subconsulta escalar para marcar empleados en reposo en un SELECT que usa
     * el alias `e` para `empleado`.
     */
    public const EXPRESION_EN_REPOSO =
        'EXISTS (SELECT 1 FROM reposo_empleado r
                  WHERE r.id_empleado = e.id_empleado
                    AND ' . self::CONDICION_VIGENTE . ')';

    /**
 * Alta de un reposo. 'observacion' e 'id_usuario_registro' (nullable).
 *
 * @param array $data 'id_empleado', 'fecha_inicio', 'fecha_fin', 'motivo',
 */
    public function create(array $data): bool
    {
        $this->pdo->prepare(
            'INSERT INTO reposo_empleado
                (id_empleado, fecha_inicio, fecha_fin, motivo, observacion, estado, id_usuario_registro)
             VALUES
                (:empleado, :inicio, :fin, :motivo, :observacion, :estado, :usuario)'
        )->execute([
            'empleado'    => (int) ($data['id_empleado'] ?? 0),
            'inicio'      => (string) ($data['fecha_inicio'] ?? ''),
            'fin'         => ($data['fecha_fin'] ?? '') !== '' ? (string) $data['fecha_fin'] : null,
            'motivo'      => ($data['motivo'] ?? '') !== '' ? (string) $data['motivo'] : null,
            'observacion' => ($data['observacion'] ?? '') !== '' ? (string) $data['observacion'] : null,
            'estado'      => (string) ($data['estado'] ?? 'VIGENTE'),
            'usuario'     => (int) ($data['id_usuario_registro'] ?? 0) ?: null,
        ]);

        return true;
    }

    /**
     * Edición de un reposo vigente. Un reposo cerrado o anulado ya no se edita:
     * se conserva como historial.
     */
    public function update(int $id, array $data): bool
    {
        return $this->pdo->prepare(
            "UPDATE reposo_empleado
                SET fecha_inicio = :inicio,
                    fecha_fin    = :fin,
                    motivo       = :motivo,
                    observacion  = :observacion
              WHERE id_reposo = :id
                AND estado = 'VIGENTE'"
        )->execute([
            'id'          => $id,
            'inicio'      => (string) ($data['fecha_inicio'] ?? ''),
            'fin'         => ($data['fecha_fin'] ?? '') !== '' ? (string) $data['fecha_fin'] : null,
            'motivo'      => ($data['motivo'] ?? '') !== '' ? (string) $data['motivo'] : null,
            'observacion' => ($data['observacion'] ?? '') !== '' ? (string) $data['observacion'] : null,
        ]);
    }

    /** Reposos de un empleado, del más reciente al más antiguo. */
    public function getPorEmpleado(int $idEmpleado): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.id_reposo,
                    r.id_empleado,
                    r.fecha_inicio,
                    r.fecha_fin,
                    r.motivo,
                    r.observacion,
                    r.estado,
                    r.id_usuario_registro,
                    r.fecha_creacion,
                    CONCAT_WS(\' \', up.primer_nombre, up.primer_apellido) AS registrado_por,
                    (' . self::CONDICION_VIGENTE . ') AS vigente
               FROM reposo_empleado r
               LEFT JOIN usuario u ON u.id_usuario = r.id_usuario_registro
               LEFT JOIN persona up ON up.id_persona = u.id_persona
              WHERE r.id_empleado = :empleado
              ORDER BY r.fecha_inicio DESC, r.id_reposo DESC'
        );
        $stmt->execute(['empleado' => $idEmpleado]);

        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($filas as &$fila) {
            $fila['id_reposo']          = (int) $fila['id_reposo'];
            $fila['id_empleado']        = (int) $fila['id_empleado'];
            $fila['id_usuario_registro'] = $fila['id_usuario_registro'] !== null
                ? (int) $fila['id_usuario_registro']
                : null;
            $fila['vigente']            = (int) $fila['vigente'] === 1;
            $fila['motivo']             = $fila['motivo'] ?? '';
            $fila['observacion']        = $fila['observacion'] ?? '';
            $registradoPor = trim((string) ($fila['registrado_por'] ?? ''));
            $fila['registrado_por']     = $registradoPor !== '' ? $registradoPor : 'Usuario eliminado';
        }

        return $filas;
    }

    /** El reposo vigente hoy para este empleado, o null si está trabajando. */
    public function getVigente(int $idEmpleado): ?array
    {
        foreach ($this->getPorEmpleado($idEmpleado) as $reposo) {
            if ($reposo['vigente']) {
                return $reposo;
            }
        }

        return null;
    }

    /** Un reposo por id, con su estado y observación. */
    public function getPorId(int $idReposo): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id_reposo, id_empleado, fecha_inicio, fecha_fin, motivo, observacion,
                    estado, id_usuario_registro
               FROM reposo_empleado
              WHERE id_reposo = :id
              LIMIT 1'
        );
        $stmt->execute(['id' => $idReposo]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($fila === false) {
            return null;
        }

        $fila['id_reposo']           = (int) $fila['id_reposo'];
        $fila['id_empleado']         = (int) $fila['id_empleado'];
        $fila['id_usuario_registro'] = $fila['id_usuario_registro'] !== null
            ? (int) $fila['id_usuario_registro']
            : null;

        return $fila;
    }

    /**
 * ¿Hay un reposo VIGENTE solapado con el rango dado? Los reposos CERRADO y ANULADO se
 * ignoran de propósito: ya no ocupan agenda.
 */
    public function haySolapamiento(int $idEmpleado, string $fechaInicio, string $fechaFin, ?int $ignorarId = null): ?array
    {
        $fin = $fechaFin !== '' ? $fechaFin : '9999-12-31';

        $sql = "SELECT r.id_reposo, r.fecha_inicio, r.fecha_fin
                  FROM reposo_empleado r
                 WHERE r.id_empleado = :empleado
                   AND r.estado = 'VIGENTE'
                   AND :inicio <= COALESCE(r.fecha_fin, :infinito)
                   AND r.fecha_inicio <= :fin";

        $params = [
            'empleado' => $idEmpleado,
            'inicio'   => $fechaInicio,
            'fin'      => $fin,
            'infinito' => '9999-12-31',
        ];

        if ($ignorarId !== null) {
            $sql .= ' AND r.id_reposo != :ignorar';
            $params['ignorar'] = $ignorarId;
        }

        $sql .= ' ORDER BY r.fecha_inicio DESC LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }

    /**
     * Cierra anticipadamente un reposo. La observación se agrega al historial
     * del propio reposo y no se pierde la que ya tuviera.
     */
    public function cerrar(int $idReposo, string $fechaFin, string $observacion): bool
    {
        $reposo = $this->getPorId($idReposo);

        if ($reposo === null || $reposo['estado'] !== 'VIGENTE') {
            return false;
        }

        $observacion = $this->concatenarObservacion((string) $reposo['observacion'], $observacion);

        return $this->pdo->prepare(
            "UPDATE reposo_empleado
                SET fecha_fin = :fin,
                    estado = 'CERRADO',
                    observacion = :observacion
              WHERE id_reposo = :id
                AND estado = 'VIGENTE'"
        )->execute([
            'id'          => $idReposo,
            'fin'         => $fechaFin,
            'observacion' => $observacion,
        ]);
    }

    /** Anula un reposo registrado por error. */
    public function anular(int $idReposo, string $observacion): bool
    {
        $reposo = $this->getPorId($idReposo);

        if ($reposo === null || $reposo['estado'] === 'ANULADO') {
            return false;
        }

        $observacion = $this->concatenarObservacion((string) $reposo['observacion'], $observacion);

        return $this->pdo->prepare(
            "UPDATE reposo_empleado
                SET estado = 'ANULADO',
                    observacion = :observacion
              WHERE id_reposo = :id
                AND estado <> 'ANULADO'"
        )->execute([
            'id'          => $idReposo,
            'observacion' => $observacion,
        ]);
    }

    private function concatenarObservacion(string $anterior, string $nueva): string
    {
        $anterior = trim($anterior);
        $nueva    = trim($nueva);

        if ($nueva === '') {
            return $anterior;
        }

        return $anterior === '' ? $nueva : $anterior . "\n" . $nueva;
    }

    /** Registra el reposo dentro de una transacción que el controlador ya abrió. */
    public function crearDentroDeTransaccion(array $data): int
    {
        $this->create($data);

        return (int) $this->pdo->lastInsertId();
    }

    /**
 * Registra un reposo con bloqueo del empleado, control de solapamiento y bitácora
 * dentro de la misma transacción. Así dos altas simultáneas sobre el mismo empleado
 * se serializan.
 *
 * @param array{id_empleado:int,fecha_inicio:string,fecha_fin:string,motivo:string} $datos
 * @return array{ok:bool,motivo?:string,id_reposo?:int}
 */
    public function registrarConBloqueo(array $datos, ?int $idUsuario = null): array
    {
        $this->pdo->beginTransaction();

        try {
            $bloqueo = $this->pdo->prepare(
                'SELECT e.id_empleado, p.estado
                   FROM empleado e
                  INNER JOIN persona p ON p.id_persona = e.id_persona
                  WHERE e.id_empleado = :id
                  FOR UPDATE'
            );
            $bloqueo->execute(['id' => (int) $datos['id_empleado']]);
            $empleado = $bloqueo->fetch(PDO::FETCH_ASSOC);

            if ($empleado === false) {
                $this->pdo->rollBack();

                return ['ok' => false, 'motivo' => 'no_existe'];
            }

            if (strtoupper((string) ($empleado['estado'] ?? '')) !== 'ACTIVO') {
                $this->pdo->rollBack();

                return ['ok' => false, 'motivo' => 'inactivo'];
            }

            if ($this->haySolapamiento(
                (int) $datos['id_empleado'],
                (string) $datos['fecha_inicio'],
                (string) $datos['fecha_fin']
            ) !== null) {
                $this->pdo->rollBack();

                return ['ok' => false, 'motivo' => 'solape'];
            }

            $idReposo = $this->crearDentroDeTransaccion($datos);

            $this->registrarBitacora(
                $idUsuario,
                'REPOSO ' . $this->nombreCortoEmpleado((int) $datos['id_empleado'])
                . ' · desde ' . $datos['fecha_inicio']
            );

            $this->pdo->commit();

            return ['ok' => true, 'id_reposo' => $idReposo];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    /**
 * Edita un reposo vigente con bloqueo del empleado y control de solapamiento,
 * excluyendo el propio registro de la comparación.
 *
 * @param array{id_empleado:int,fecha_inicio:string,fecha_fin:string,motivo:string} $datos
 * @return array{ok:bool,motivo?:string,id_reposo?:int}
 */
    public function actualizarConBloqueo(int $idReposo, array $datos, ?int $idUsuario = null): array
    {
        $this->pdo->beginTransaction();

        try {
            $bloqueo = $this->pdo->prepare(
                'SELECT id_empleado FROM empleado WHERE id_empleado = :id FOR UPDATE'
            );
            $bloqueo->execute(['id' => (int) $datos['id_empleado']]);

            if ($this->haySolapamiento(
                (int) $datos['id_empleado'],
                (string) $datos['fecha_inicio'],
                (string) $datos['fecha_fin'],
                $idReposo
            ) !== null) {
                $this->pdo->rollBack();

                return ['ok' => false, 'motivo' => 'solape'];
            }

            $this->update($idReposo, $datos);

            $this->registrarBitacora(
                $idUsuario,
                'EDICIÓN REPOSO ' . $this->nombreCortoEmpleado((int) $datos['id_empleado'])
                . ' · ' . $datos['fecha_inicio']
                . ($datos['fecha_fin'] === '' ? ' (abierto)' : ' a ' . $datos['fecha_fin'])
            );

            $this->pdo->commit();

            return ['ok' => true, 'id_reposo' => $idReposo];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    /** Nombre corto para el texto de bitácora; fallback si el empleado no tiene nombre. */
    private function nombreCortoEmpleado(int $idEmpleado): string
    {
        return (new EmployeeModel($this->pdo))->getNombreCorto($idEmpleado) ?? ('empleado #' . $idEmpleado);
    }

    /** Bitácora dentro de la transacción; se omite si no hay usuario en sesión. */
    private function registrarBitacora(?int $idUsuario, string $accion): void
    {
        if ($idUsuario === null || $idUsuario <= 0) {
            return;
        }

        (new AuditLogModel($this->pdo))->registrar($idUsuario, $accion, 'Reposos');
    }
}
