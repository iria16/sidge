<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\AvatarTrait;
use DateTimeImmutable;
use PDO;
use Throwable;

/**
 * Modelo de Perfil de Usuario
 *
 * Consulta la información consolidada del usuario conectado:
 * - Cuenta y rol (usuario, rol)
 * - Datos personales y demográficos (persona, nacionalidad)
 * - Datos laborales e institucionales (empleado, cargo, turno)
 * - Información de contacto y dirección (contacto, direccion, parroquia, municipio, estado)
 * - Último acceso a la plataforma (bitacora)
 */
class PerfilModel extends BaseModel
{
    use AvatarTrait;

    protected string $table = 'usuario';
    protected string $primaryKey = 'id_usuario';

    /**
     * Obtiene el perfil completo y estructurado para la vista de solo lectura.
     *
     * @param int $idUsuario
     * @return array<string, mixed>|null
     */
    public function getPerfilUsuario(int $idUsuario): ?array
    {
        $sql = "SELECT 
                    u.id_usuario,
                    u.nombre_usuario,
                    u.estado AS estado_usuario,
                    u.fecha_creacion AS usuario_creacion,
                    r.id_rol,
                    r.nombre AS rol_nombre,
                    p.id_persona,
                    p.tipo_documento,
                    p.numero_documento,
                    p.primer_nombre,
                    p.segundo_nombre,
                    p.primer_apellido,
                    p.segundo_apellido,
                    p.sexo,
                    p.fecha_nacimiento,
                    p.foto,
                    p.estado AS estado_persona,
                    nac.nombre AS nacionalidad,
                    e.id_empleado,
                    e.fecha_ingreso,
                    e.fecha_egreso,
                    car.nombre AS cargo,
                    t.nombre AS turno,
                    c.codigo_telefono_principal,
                    c.numero_telefono_principal,
                    c.codigo_telefono_alternativo,
                    c.numero_telefono_alternativo,
                    c.correo_electronico,
                    d.sector_urbanizacion,
                    d.calle_avenida,
                    d.nro_casa_apto,
                    d.punto_referencia,
                    pq.nombre AS parroquia_nombre,
                    m.nombre AS municipio_nombre,
                    est.nombre AS estado_nombre,
                    (SELECT CONCAT(b.fecha, ' ', b.hora)
                       FROM bitacora b
                      WHERE b.id_usuario = u.id_usuario AND b.accion = 'LOGIN'
                      ORDER BY b.fecha DESC, b.hora DESC
                      LIMIT 1) AS ultimo_acceso
                FROM usuario u
                INNER JOIN persona p ON p.id_persona = u.id_persona
                INNER JOIN rol r ON r.id_rol = u.id_rol
                LEFT JOIN empleado e ON e.id_persona = p.id_persona
                LEFT JOIN cargo car ON car.id_cargo = e.id_cargo
                LEFT JOIN turno t ON t.id_turno = e.id_turno
                LEFT JOIN nacionalidad nac ON nac.id_nacionalidad = p.id_nacionalidad
                LEFT JOIN contacto c ON c.id_persona = p.id_persona
                LEFT JOIN direccion d ON d.id_persona = p.id_persona
                LEFT JOIN parroquia pq ON pq.id_parroquia = d.id_parroquia
                LEFT JOIN municipio m ON m.id_municipio = pq.id_municipio
                LEFT JOIN estado est ON est.id_estado = m.id_estado
                WHERE u.id_usuario = :id_usuario
                LIMIT 1";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['id_usuario' => $idUsuario]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                return null;
            }

            return $this->formatearPerfil($row);
        } catch (Throwable $e) {
            error_log('Error en PerfilModel::getPerfilUsuario: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Formatea y complementa los campos para presentación en la vista.
     *
     * @param array<string, mixed> $r
     * @return array<string, mixed>
     */
    private function formatearPerfil(array $r): array
    {
        // Nombre completo
        $nombres = trim(($r['primer_nombre'] ?? '') . ' ' . ($r['segundo_nombre'] ?? ''));
        $apellidos = trim(($r['primer_apellido'] ?? '') . ' ' . ($r['segundo_apellido'] ?? ''));
        $nombreCompleto = trim($nombres . ' ' . $apellidos);
        if ($nombreCompleto === '') {
            $nombreCompleto = (string) ($r['nombre_usuario'] ?? 'Usuario');
        }

        // Cédula formateada
        $cedula = '—';
        if (!empty($r['numero_documento'])) {
            $tipoDoc = (string) ($r['tipo_documento'] ?? 'V');
            $numDoc = (string) $r['numero_documento'];
            $cedula = $tipoDoc . '-' . number_format((float) preg_replace('/\D/', '', $numDoc), 0, '', '.');
        }

        // Cálculo de edad
        $edad = null;
        if (!empty($r['fecha_nacimiento'])) {
            try {
                $fechaNac = new DateTimeImmutable((string) $r['fecha_nacimiento']);
                $hoy = new DateTimeImmutable('today');
                $edad = $hoy->diff($fechaNac)->y;
            } catch (Throwable) {
                $edad = null;
            }
        }

        // Cálculo de tiempo de servicio
        $tiempoServicio = null;
        if (!empty($r['fecha_ingreso'])) {
            try {
                $fechaIng = new DateTimeImmutable((string) $r['fecha_ingreso']);
                $hoy = new DateTimeImmutable('today');
                $diff = $hoy->diff($fechaIng);
                $anios = $diff->y;
                $meses = $diff->m;
                if ($anios > 0) {
                    $tiempoServicio = $anios . ($anios === 1 ? ' año' : ' años');
                    if ($meses > 0) {
                        $tiempoServicio .= ' y ' . $meses . ($meses === 1 ? ' mes' : ' meses');
                    }
                } elseif ($meses > 0) {
                    $tiempoServicio = $meses . ($meses === 1 ? ' mes' : ' meses');
                } else {
                    $tiempoServicio = 'Menos de un mes';
                }
            } catch (Throwable) {
                $tiempoServicio = null;
            }
        }

        // Teléfonos formateados
        $telPrincipal = '—';
        if (!empty($r['numero_telefono_principal'])) {
            $cod = (string) ($r['codigo_telefono_principal'] ?? '');
            $num = (string) $r['numero_telefono_principal'];
            $telPrincipal = ($cod !== '' ? $cod . '-' : '') . $num;
        }

        $telAlternativo = '—';
        if (!empty($r['numero_telefono_alternativo'])) {
            $cod = (string) ($r['codigo_telefono_alternativo'] ?? '');
            $num = (string) $r['numero_telefono_alternativo'];
            $telAlternativo = ($cod !== '' ? $cod . '-' : '') . $num;
        }

        // Dirección estructurada
        $partesDir = array_filter([
            $r['sector_urbanizacion'] ? 'Sector/Urb. ' . $r['sector_urbanizacion'] : null,
            $r['calle_avenida'] ? 'Calle/Av. ' . $r['calle_avenida'] : null,
            $r['nro_casa_apto'] ? 'Casa/N° ' . $r['nro_casa_apto'] : null,
            $r['parroquia_nombre'] ? 'Pq. ' . $r['parroquia_nombre'] : null,
            $r['municipio_nombre'] ? 'Mcpo. ' . $r['municipio_nombre'] : null,
            $r['estado_nombre'] ? 'Edo. ' . $r['estado_nombre'] : null,
        ]);
        $direccionCompleta = $partesDir !== [] ? implode(', ', $partesDir) : 'No registrada';

        // Avatar
        $avatar = $this->resolverAvatar(
            $r['foto'] ?? null,
            $r['primer_nombre'] ?? null,
            $r['primer_apellido'] ?? null,
            $r['rol_nombre'] ?? null,
            $r['nombre_usuario'] ?? 'U'
        );

        // Género legible
        $sexoTexto = '—';
        if (!empty($r['sexo'])) {
            $sexoTexto = strtoupper((string) $r['sexo']) === 'M' ? 'Masculino' : 'Femenino';
        }

        // Fechas legibles
        $fechaIngresoFormateada = !empty($r['fecha_ingreso'])
            ? date('d/m/Y', strtotime((string) $r['fecha_ingreso']))
            : '—';

        $fechaNacimientoFormateada = !empty($r['fecha_nacimiento'])
            ? date('d/m/Y', strtotime((string) $r['fecha_nacimiento']))
            : '—';

        $fechaCreacionFormateada = !empty($r['usuario_creacion'])
            ? date('d/m/Y H:i', strtotime((string) $r['usuario_creacion']))
            : '—';

        $ultimoAccesoFormateado = !empty($r['ultimo_acceso'])
            ? date('d/m/Y H:i', strtotime((string) $r['ultimo_acceso']))
            : 'Sin accesos previos';

        return [
            'id_usuario'             => (int) $r['id_usuario'],
            'nombre_usuario'         => (string) ($r['nombre_usuario'] ?? ''),
            'estado_usuario'         => strtoupper((string) ($r['estado_usuario'] ?? 'ACTIVO')),
            'rol_id'                 => (int) ($r['id_rol'] ?? 0),
            'rol_nombre'             => (string) ($r['rol_nombre'] ?? 'Usuario'),
            'id_persona'             => (int) ($r['id_persona'] ?? 0),
            'nombre_completo'        => $nombreCompleto,
            'primer_nombre'          => (string) ($r['primer_nombre'] ?? ''),
            'primer_apellido'        => (string) ($r['primer_apellido'] ?? ''),
            'cedula'                 => $cedula,
            'sexo'                   => $sexoTexto,
            'fecha_nacimiento'       => $fechaNacimientoFormateada,
            'edad'                   => $edad,
            'nacionalidad'           => !empty($r['nacionalidad']) ? (string) $r['nacionalidad'] : 'Venezolana',
            'id_empleado'            => !empty($r['id_empleado']) ? (int) $r['id_empleado'] : null,
            'cargo'                  => !empty($r['cargo']) ? (string) $r['cargo'] : 'Personal Institucional',
            'turno'                  => !empty($r['turno']) ? (string) $r['turno'] : 'No asignado',
            'fecha_ingreso'          => $fechaIngresoFormateada,
            'tiempo_servicio'        => $tiempoServicio,
            'telefono_principal'     => $telPrincipal,
            'telefono_alternativo'   => $telAlternativo,
            'correo_electronico'     => !empty($r['correo_electronico']) ? (string) $r['correo_electronico'] : '—',
            'direccion_completa'     => $direccionCompleta,
            'punto_referencia'       => !empty($r['punto_referencia']) ? (string) $r['punto_referencia'] : '—',
            'fecha_creacion'         => $fechaCreacionFormateada,
            'ultimo_acceso'          => $ultimoAccesoFormateado,
            'avatar_tipo'            => $avatar['tipo'],
            'avatar_foto'            => $avatar['foto'],
            'iniciales'              => $avatar['iniciales'],
            'avatar_color'           => $avatar['avatar_color'],
        ];
    }
/**
     * Obtiene los datos básicos de persona asociados al usuario en sesión.
     *
     * @param int $idUsuario
     * @return array<string, mixed>|null
     */
    public function getPersonaUsuario(int $idUsuario): ?array
    {
        $sql = "SELECT p.id_persona, p.foto
                  FROM usuario u
                  INNER JOIN persona p ON p.id_persona = u.id_persona
                 WHERE u.id_usuario = :id_usuario
                 LIMIT 1";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':id_usuario' => $idUsuario]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row !== false ? $row : null;
        } catch (\Throwable $e) {
            error_log('Error en PerfilModel::getPersonaUsuario: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Actualiza la foto de perfil en la tabla persona.
     *
     * @param int $idPersona
     * @param string|null $rutaFoto
     * @return bool
     */
    public function actualizarFotoPersona(int $idPersona, ?string $rutaFoto): bool
    {
        try {
            $stmt = $this->pdo->prepare("UPDATE persona SET foto = :foto WHERE id_persona = :id_persona");
            return $stmt->execute([
                ':foto'       => $rutaFoto,
                ':id_persona' => $idPersona,
            ]);
        } catch (\Throwable $e) {
            error_log('Error en PerfilModel::actualizarFotoPersona: ' . $e->getMessage());
            return false;
        }
    }
}
