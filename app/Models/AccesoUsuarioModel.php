<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Consultas de acceso y alcance por usuario.
 *
 * Aísla aquí el SQL que antes vivía en BaseController para que el controlador
 * solo decida según el rol y el modelo resuelva los datos. Todas las consultas
 * reciben el id ya validado por el controlador; si no hay fila devuelven 0.
 */
class AccesoUsuarioModel extends Model
{
    /**
     * Turno asignado a una secretaria.
     * Devuelve 0 cuando el usuario no tiene empleado o turno activo.
     */
    public function turnoDeSecretaria(int $idUsuario): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT t.id_turno
               FROM usuario u
               LEFT JOIN empleado e ON e.id_persona = u.id_persona
               LEFT JOIN turno t ON t.id_turno = e.id_turno AND t.estado = \'ACTIVO\'
              WHERE u.id_usuario = :id
              LIMIT 1'
        );
        $stmt->execute(['id' => $idUsuario]);
        $turno = $stmt->fetchColumn();

        return $turno === false || $turno === null ? 0 : (int) $turno;
    }

    /**
     * Id del docente asociado a un usuario.
     * Devuelve 0 si el usuario no tiene perfil de docente.
     */
    public function docenteIdDeUsuario(int $idUsuario): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT d.id_docente
               FROM usuario u
               INNER JOIN empleado e ON e.id_persona = u.id_persona
               INNER JOIN docente d ON d.id_empleado = e.id_empleado
              WHERE u.id_usuario = :id
              LIMIT 1'
        );
        $stmt->execute(['id' => $idUsuario]);
        $id = $stmt->fetchColumn();

        return $id === false ? 0 : (int) $id;
    }

    /**
     * Turno de la matrícula más reciente del estudiante.
     * Devuelve 0 cuando el estudiante no tiene matrícula con estructura.
     */
    public function turnoDeEstudiante(int $idEstudiante): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT ea.id_turno
               FROM matricula m
               LEFT JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
              WHERE m.id_estudiante = :id
              ORDER BY m.id_anio_escolar DESC, m.id_matricula DESC
              LIMIT 1'
        );
        $stmt->execute(['id' => $idEstudiante]);
        $idTurno = $stmt->fetchColumn();

        return $idTurno === false || $idTurno === null ? 0 : (int) $idTurno;
    }

    /**
     * Indica si una estructura académica pertenece al turno indicado.
     */
    public function estructuraPerteneceATurno(int $idEstructura, int $idTurno): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*)
               FROM estructura_academica
              WHERE id_estructura_academica = :id AND id_turno = :turno'
        );
        $stmt->execute(['id' => $idEstructura, 'turno' => $idTurno]);

        return (int) $stmt->fetchColumn() > 0;
    }
}
