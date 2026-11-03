<?php

declare(strict_types=1);

namespace App\Traits;

use App\Exceptions\CupoAgotadoException;
use App\Support\Matriculas;

/**
 * CupoEstructuraTrait
 *
 * La fuente de verdad de qué combinaciones existen y cuántas quedan libres es
 * `estructura_academica` (grado + sección + turno + año escolar, con
 * `capacidad_maxima`). Este trait es el único lugar donde se cruza esa
 * configuración con los estudiantes que realmente la ocupan, y lo usan los dos
 * módulos que necesitan el dato: EnrollmentModel (el paso académico) y
 * RatificationModel (que proyecta al periodo siguiente).
*/
trait CupoEstructuraTrait
{
    public function estructurasConCupo(?int $idAnioEscolar = null, ?int $idTurno = null): array
    {
        $condiciones = [];
        $parametros = [];
        if ($idAnioEscolar !== null) {
            $condiciones[] = 'ea.id_anio_escolar = :anio';
            $parametros['anio'] = $idAnioEscolar;
        }
        if ($idTurno !== null) {
            $condiciones[] = 'ea.id_turno = :turno';
            $parametros['turno'] = $idTurno;
        }
        $donde = $condiciones !== [] ? 'WHERE ' . implode(' AND ', $condiciones) : '';
        $stmt  = $this->pdo->prepare(
            "SELECT ea.id_estructura_academica,
                    ea.id_grado, ea.id_seccion, ea.id_turno, ea.id_anio_escolar,
                    ea.capacidad_maxima,
                    g.nombre AS grado, s.nombre AS seccion, t.nombre AS turno
               FROM estructura_academica ea
               LEFT JOIN grado g   ON g.id_grado   = ea.id_grado
               LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion
               LEFT JOIN turno t   ON t.id_turno   = ea.id_turno
               {$donde}
              ORDER BY g.nombre, s.nombre, t.nombre"
        );
        $stmt->execute($parametros);

        $ocupadosPor = $this->conteoDeOcupados();

        $filas = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $fila) {
            $id        = (int) $fila['id_estructura_academica'];
            $capacidad = (int) $fila['capacidad_maxima'];
            $ocupados  = (int) ($ocupadosPor[$id] ?? 0);
            $libres    = $capacidad - $ocupados;

            $filas[] = [
                'id_estructura_academica' => $id,
                'id_grado'                 => (int) $fila['id_grado'],
                'id_seccion'               => (int) $fila['id_seccion'],
                'id_turno'                 => (int) $fila['id_turno'],
                'id_anio_escolar'          => (int) $fila['id_anio_escolar'],
                'grado'                    => (string) $fila['grado'],
                'seccion'                  => (string) $fila['seccion'],
                'turno'                    => (string) $fila['turno'],
                'capacidad_maxima'         => $capacidad,
                'ocupados'                 => $ocupados,
                'disponibles'              => $libres,
                'lleno'                    => $libres <= 0,
                'nombre'                   => trim($fila['grado'] . ' · ' . $fila['seccion'] . ' · ' . $fila['turno']),
            ];
        }

        return $filas;
    }

    public function conteoDeOcupados(?int $soloEstructura = null): array
    {
        $filtro = '';
        $params = [];

        if ($soloEstructura !== null && $soloEstructura > 0) {
            $filtro = ' AND m.id_estructura_academica = :estructura';
            $params['estructura'] = $soloEstructura;
        }

        $stmt = $this->pdo->prepare(
            'SELECT id_estructura_academica AS combo, COUNT(DISTINCT id_estudiante) AS ocupados
               FROM matricula m
              WHERE ' . Matriculas::condicionCursando('m') . '
                AND m.id_estructura_academica IS NOT NULL'
            . $filtro . '
              GROUP BY m.id_estructura_academica'
        );
        $stmt->execute($params);

        $mapa = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_KEY_PAIR) as $combo => $ocupados) {
            $mapa[(int) $combo] = (int) $ocupados;
        }

        return $mapa;
    }

    public function combinacionConCupo(int $idGrado, int $idSeccion, int $idTurno, int $idAnioEscolar): ?array
    {
        if ($idAnioEscolar <= 0) {
            return null;
        }

        foreach ($this->estructurasConCupo($idAnioEscolar) as $combinacion) {
            if ($combinacion['id_grado'] === $idGrado
                && $combinacion['id_seccion'] === $idSeccion
                && $combinacion['id_turno'] === $idTurno) {
                return $combinacion;
            }
        }

        return null;
    }

    public function disponibilidadDe(int $idEstructura): ?array
    {
        if ($idEstructura <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT ea.id_estructura_academica,
                    ea.id_grado, ea.id_seccion, ea.id_turno, ea.id_anio_escolar,
                    ea.capacidad_maxima,
                    g.nombre AS grado, s.nombre AS seccion, t.nombre AS turno
               FROM estructura_academica ea
               LEFT JOIN grado g   ON g.id_grado   = ea.id_grado
               LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion
               LEFT JOIN turno t   ON t.id_turno   = ea.id_turno
              WHERE ea.id_estructura_academica = :id
              LIMIT 1'
        );
        $stmt->execute(['id' => $idEstructura]);
        $fila = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$fila) {
            return null;
        }

        $capacidad = (int) $fila['capacidad_maxima'];
        $ocupados = (int) ($this->conteoDeOcupados($idEstructura)[$idEstructura] ?? 0);
        $libres   = $capacidad - $ocupados;

        return [
            'id_estructura_academica' => (int) $fila['id_estructura_academica'],
            'id_grado'                 => (int) $fila['id_grado'],
            'id_seccion'               => (int) $fila['id_seccion'],
            'id_turno'                 => (int) $fila['id_turno'],
            'id_anio_escolar'          => (int) $fila['id_anio_escolar'],
            'grado'                    => (string) $fila['grado'],
            'seccion'                  => (string) $fila['seccion'],
            'turno'                    => (string) $fila['turno'],
            'capacidad_maxima'         => $capacidad,
            'ocupados'                 => $ocupados,
            'disponibles'              => $libres,
            'lleno'                    => $libres <= 0,
            'nombre'                   => trim($fila['grado'] . ' · ' . $fila['seccion'] . ' · ' . $fila['turno']),
        ];
    }

    public function opcionesAcademicas(int $idAnioEscolar, ?int $idTurno = null): array
    {
        $combos = $this->estructurasConCupo($idAnioEscolar, $idTurno);

        $grados    = [];
        $secciones = [];
        $turnos    = [];

        foreach ($combos as $combo) {
            $gr = (int) $combo['id_grado'];
            $grados[$gr] ??= ['id' => $gr, 'nombre' => $combo['grado'], 'libres' => 0, 'total' => 0];
            $grados[$gr]['total']++;
            $grados[$gr]['libres'] += max(0, (int) $combo['disponibles']);

            $se = (int) $combo['id_seccion'];
            $secciones[$se] ??= ['id' => $se, 'nombre' => $combo['seccion'], 'libres' => 0, 'total' => 0];
            $secciones[$se]['total']++;
            $secciones[$se]['libres'] += max(0, (int) $combo['disponibles']);

            $tu = (int) $combo['id_turno'];
            $turnos[$tu] ??= ['id' => $tu, 'nombre' => $combo['turno']];
        }

        return [
            'grados'        => array_values($grados),
            'secciones'     => array_values($secciones),
            'turnos'        => array_values($turnos),
            'combinaciones' => $combos,
        ];
    }

    public function reservarCupo(int $idEstructura): array
    {
        $this->pdo->prepare(
            'SELECT id_estructura_academica FROM estructura_academica
              WHERE id_estructura_academica = :id FOR UPDATE'
        )->execute(['id' => $idEstructura]);

        $disponibilidad = $this->disponibilidadDe($idEstructura);
        if ($disponibilidad === null) {
            throw new CupoAgotadoException(
                'La sección seleccionada no está registrada en la Estructura Académica.',
                'id_seccion'
            );
        }
        if ($disponibilidad['lleno']) {
            throw new CupoAgotadoException(
                'La sección está llena: ' . (int) $disponibilidad['ocupados'] . ' de '
                . (int) $disponibilidad['capacidad_maxima'] . ' cupos ocupados ('
                . $disponibilidad['nombre'] . ').'
            );
        }

        return $disponibilidad;
    }
}
