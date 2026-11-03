<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Configuracion;
use PDO;
use Throwable;

/**
 * DashboardModel -------------- Consultas agregadas y métricas globales para el panel
 * de administración. Alcance estricto para las funciones del Administrador: cuentas de
 * usuario, recuperación de acceso, catálogos, datos institucionales y bitácora.
 */
class DashboardModel extends Model
{
    /**
     * Retorna el año escolar activo o null si no hay ninguno.
     *
     * @return array{id: int, nombre: string, estado: string}|null
     */
    public function getAnioActivo(): ?array
    {
        try {
            $stmt = $this->pdo->query(
                "SELECT id_anio_escolar, nombre_periodo, estado
                   FROM anio_escolar
                  WHERE estado = 'ACTIVO'
                  LIMIT 1"
            );
            $fila = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($fila) {
                return [
                    'id'     => (int) $fila['id_anio_escolar'],
                    'nombre' => (string) $fila['nombre_periodo'],
                    'estado' => (string) $fila['estado'],
                ];
            }
        } catch (Throwable $e) {
            error_log('DashboardModel::getAnioActivo: ' . $e->getMessage());
        }

        return null;
    }

    /**
 * 4 Indicadores globales de cuentas y seguridad: - usuarios registrados - usuarios
 * activos - cuentas bloqueadas - recuperaciones pendientes total_usuarios: int,
 * usuarios_activos: int, cuentas_bloqueadas: int, recuperaciones_pendientes: int }.
 *
 * @return array{
 */
    public function getMetricasUsuarios(): array
    {
        $totalUsuarios = 0;
        $activos = 0;
        $bloqueadas = 0;
        $recuperaciones = 0;

        try {
            $fila = $this->pdo->query(
                "SELECT COUNT(*) as total,
                        SUM(CASE WHEN estado = 'ACTIVO' THEN 1 ELSE 0 END) as activos,
                        SUM(CASE WHEN (bloqueado_hasta IS NOT NULL AND bloqueado_hasta > NOW()) OR intentos_fallidos >= 3 THEN 1 ELSE 0 END) as bloqueadas
                   FROM usuario"
            )->fetch(PDO::FETCH_ASSOC);

            if ($fila) {
                $totalUsuarios = (int) ($fila['total'] ?? 0);
                $activos       = (int) ($fila['activos'] ?? 0);
                $bloqueadas    = (int) ($fila['bloqueadas'] ?? 0);
            }
        } catch (Throwable $e) {
            error_log('DashboardModel::getMetricasUsuarios usuario: ' . $e->getMessage());
        }

        try {
            $recuperaciones = (int) $this->pdo->query(
                "SELECT COUNT(*) FROM recuperacion_acceso WHERE estado_recuperacion = 'PENDIENTE'"
            )->fetchColumn();
        } catch (Throwable $e) {
            error_log('DashboardModel::getMetricasUsuarios recuperaciones: ' . $e->getMessage());
        }

        return [
            'total_usuarios'            => $totalUsuarios,
            'usuarios_activos'          => $activos,
            'cuentas_bloqueadas'        => $bloqueadas,
            'recuperaciones_pendientes' => $recuperaciones,
        ];
    }

    /**
 * Cobertura de cuentas de usuario sobre el personal activo. total_empleados: int,
 * con_cuenta: int, sin_cuenta: int, porcentaje: int }.
 *
 * @return array{
 */
    public function getCoberturaPersonal(): array
    {
        $totalEmpleados = 0;
        $conCuenta = 0;
        try {
            $totalEmpleados = (int) $this->pdo->query(
                "SELECT COUNT(*) FROM empleado WHERE fecha_egreso IS NULL"
            )->fetchColumn();

            $conCuenta = (int) $this->pdo->query(
                "SELECT COUNT(DISTINCT e.id_empleado)
                   FROM empleado e
                  INNER JOIN usuario u ON u.id_persona = e.id_persona
                  WHERE e.fecha_egreso IS NULL"
            )->fetchColumn();
        } catch (Throwable $e) {
            error_log('DashboardModel::getCoberturaPersonal: ' . $e->getMessage());
        }

        $porcentaje = $totalEmpleados > 0 ? (int) round(($conCuenta / $totalEmpleados) * 100) : 0;

        return [
            'total_empleados' => $totalEmpleados,
            'con_cuenta'      => $conCuenta,
            'sin_cuenta'      => max(0, $totalEmpleados - $conCuenta),
            'porcentaje'      => $porcentaje,
        ];
    }

    /**
 * Lista de ítems para el bloque "Requiere atención". Solo retorna filas con valor >
 * 0.
 *
 * @return list<array{
 */
    public function getAtencionRequerida(): array
    {
        $items = [];

        // 0. Solicitudes de recuperación de acceso pendientes (recuperacionAcceso/index)
        try {
            $recuperaciones = (int) $this->pdo->query(
                "SELECT COUNT(*) FROM recuperacion_acceso WHERE estado_recuperacion = 'PENDIENTE'"
            )->fetchColumn();

            if ($recuperaciones > 0) {
                $items[] = [
                    'id'           => 'recuperaciones_pendientes',
                    'texto'        => $recuperaciones === 1
                        ? 'Solicitud de recuperación de acceso pendiente'
                        : 'Solicitudes de recuperación de acceso pendientes',
                    'conteo'       => $recuperaciones,
                    'nivel'        => 'danger',
                    'url'          => BASE_URL . 'recuperacionAcceso/index',
                    'texto_enlace' => 'Resolver solicitudes',
                ];
            }
        } catch (Throwable $e) {
            error_log('DashboardModel::getAtencionRequerida recuperaciones: ' . $e->getMessage());
        }

        // 0.1 Cuentas bloqueadas por seguridad (cuentaUsuario/index)
        try {
            $bloqueadas = (int) $this->pdo->query(
                "SELECT COUNT(*) FROM usuario WHERE (bloqueado_hasta IS NOT NULL AND bloqueado_hasta > NOW()) OR intentos_fallidos >= 3"
            )->fetchColumn();

            if ($bloqueadas > 0) {
                $items[] = [
                    'id'           => 'cuentas_bloqueadas',
                    'texto'        => $bloqueadas === 1
                        ? 'Cuenta de usuario bloqueada por seguridad'
                        : 'Cuentas de usuario bloqueadas por seguridad',
                    'conteo'       => $bloqueadas,
                    'nivel'        => 'danger',
                    'url'          => BASE_URL . 'cuentaUsuario/index',
                    'texto_enlace' => 'Desbloquear cuentas',
                ];
            }
        } catch (Throwable $e) {
            error_log('DashboardModel::getAtencionRequerida bloqueadas: ' . $e->getMessage());
        }

        // 1. Empleados activos sin cuenta de usuario (cuentaUsuario/create)
        try {
            $sinCuenta = (int) $this->pdo->query(
                "SELECT COUNT(*)
                   FROM empleado e
              LEFT JOIN usuario u ON u.id_persona = e.id_persona
                  WHERE u.id_usuario IS NULL AND e.fecha_egreso IS NULL"
            )->fetchColumn();

            if ($sinCuenta > 0) {
                $items[] = [
                    'id'           => 'empleados_sin_cuenta',
                    'texto'        => $sinCuenta === 1
                        ? 'Empleado activo sin cuenta de usuario'
                        : 'Empleados activos sin cuenta de usuario',
                    'conteo'       => $sinCuenta,
                    'nivel'        => 'warning',
                    'url'          => BASE_URL . 'cuentaUsuario/index',
                    'texto_enlace' => 'Gestionar cuentas',
                ];
            }
        } catch (Throwable $e) {
            error_log('DashboardModel::getAtencionRequerida empleados: ' . $e->getMessage());
        }

        // 2. Contraseñas provisionales sin cambiar (cuentaUsuario/index)
        try {
            $provisionales = (int) $this->pdo->query(
                "SELECT COUNT(*) FROM usuario WHERE password_provisional = 1"
            )->fetchColumn();

            if ($provisionales > 0) {
                $items[] = [
                    'id'           => 'passwords_provisionales',
                    'texto'        => $provisionales === 1
                        ? 'Contraseña provisional sin cambiar'
                        : 'Contraseñas provisionales sin cambiar',
                    'conteo'       => $provisionales,
                    'nivel'        => 'warning',
                    'url'          => BASE_URL . 'cuentaUsuario/index',
                    'texto_enlace' => 'Gestionar cuentas',
                ];
            }
        } catch (Throwable $e) {
            error_log('DashboardModel::getAtencionRequerida provisionales: ' . $e->getMessage());
        }

        // 3. Datos institucionales incompletos (configuracionInstitucional/index)
        try {
            Configuracion::setPdo($this->pdo);
            $directorOk    = Configuracion::estaConfiguradoDirector();
            $plantelNombre = trim(Configuracion::get('plantel_nombre'));
            $plantelDea    = trim(Configuracion::get('plantel_codigo_dea'));
            $plantelEdo    = trim(Configuracion::get('cdce_estado'));

            $faltantes = [];
            if (!$directorOk) {
                $faltantes[] = 'autoridad directiva';
            }
            if ($plantelNombre === '' || $plantelDea === '' || $plantelEdo === '') {
                $faltantes[] = 'identificación oficial o CDCE';
            }

            if (!empty($faltantes)) {
                $items[] = [
                    'id'           => 'datos_institucionales_incompletos',
                    'texto'        => 'Datos institucionales incompletos: falta ' . implode(' y ', $faltantes),
                    'conteo'       => count($faltantes),
                    'nivel'        => 'danger',
                    'url'          => BASE_URL . 'configuracionInstitucional/index',
                    'texto_enlace' => 'Completar datos',
                ];
            }
        } catch (Throwable $e) {
            error_log('DashboardModel::getAtencionRequerida institucion: ' . $e->getMessage());
        }

        // 4. Catálogos sin elementos activos (catalogo/index)
        try {
            $catalogos = [
                'grado'           => 'Grado',
                'seccion'         => 'Sección',
                'turno'           => 'Turno',
                'tipo_documento'  => 'Tipo de Documento',
                'grado_academico' => 'Grado Académico',
                'tipo_asignacion' => 'Tipo de Asignación',
                'rol'             => 'Rol',
                'parentesco'      => 'Parentesco',
                'motivo_retiro'   => 'Motivo de Retiro',
                'ocupacion'       => 'Profesión / Ocupación',
                'nacionalidad'    => 'Nacionalidad',
                'institucion'     => 'Institución',
                'cargo'           => 'Cargo',
            ];

            $vacios = [];
            foreach ($catalogos as $tabla => $nombreCat) {
                $cant = (int) $this->pdo->query("SELECT COUNT(*) FROM {$tabla} WHERE estado = 'ACTIVO'")->fetchColumn();
                if ($cant === 0) {
                    $vacios[] = $nombreCat;
                }
            }

            if (!empty($vacios)) {
                $items[] = [
                    'id'           => 'catalogos_sin_elementos',
                    'texto'        => count($vacios) === 1
                        ? 'Catálogo sin elementos activos: ' . $vacios[0]
                        : 'Catálogos sin elementos activos: ' . implode(', ', $vacios),
                    'conteo'       => count($vacios),
                    'nivel'        => 'danger',
                    'url'          => BASE_URL . 'catalogo/index',
                    'texto_enlace' => 'Gestionar catálogos',
                ];
            }
        } catch (Throwable $e) {
            error_log('DashboardModel::getAtencionRequerida catalogos: ' . $e->getMessage());
        }

        // 5. Intentos fallidos acumulados actuales si > 0
        try {
            $fallidos = (int) $this->pdo->query(
                "SELECT COALESCE(SUM(intentos_fallidos), 0) FROM usuario WHERE intentos_fallidos > 0"
            )->fetchColumn();

            if ($fallidos > 0) {
                $items[] = [
                    'id'           => 'intentos_fallidos_acumulados',
                    'texto'        => $fallidos === 1
                        ? 'Intento fallido de acceso acumulado actual'
                        : 'Intentos fallidos de acceso acumulados actuales',
                    'conteo'       => $fallidos,
                    'nivel'        => 'warning',
                    'url'          => null,
                    'texto_enlace' => null,
                ];
            }
        } catch (Throwable $e) {
            error_log('DashboardModel::getAtencionRequerida fallidos: ' . $e->getMessage());
        }

        return $items;
    }

    /**
 * Actividad de los últimos N días (bitácora): - Total de movimientos - Pico diario -
 * Serie de 30 días con fechas de inicio, mitad y fin total: int, pico: int,
 * fecha_inicio: string, fecha_mitad: string, fecha_fin: string, serie:
 * list<array{fecha: string, dia_mes: string, total: int, altura_pct: int}> }.
 *
 * @param int $dias
 * @return array{
 */
    public function getActividadBitacora(int $dias = 30): array
    {
        $res = [
            'total'        => 0,
            'pico'         => 0,
            'fecha_inicio' => '',
            'fecha_mitad'  => '',
            'fecha_fin'    => '',
            'serie'        => [],
        ];

        try {
            // Mapa de fechas de los últimos N días
            $diasMapa = [];
            $hoy = new \DateTimeImmutable('today');
            for ($i = $dias - 1; $i >= 0; $i--) {
                $fecha = $hoy->sub(new \DateInterval("P{$i}D"))->format('Y-m-d');
                $diasMapa[$fecha] = 0;
            }

            $stmt = $this->pdo->prepare(
                "SELECT fecha, COUNT(*) as total
                   FROM bitacora
                  WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL :dias DAY)
               GROUP BY fecha
               ORDER BY fecha ASC"
            );
            $stmt->execute(['dias' => $dias]);
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($filas as $f) {
                $fechaStr = (string) $f['fecha'];
                if (isset($diasMapa[$fechaStr])) {
                    $diasMapa[$fechaStr] = (int) $f['total'];
                }
            }

            $total = 0;
            $pico = 0;
            foreach ($diasMapa as $cant) {
                $total += $cant;
                if ($cant > $pico) {
                    $pico = $cant;
                }
            }

            $serie = [];
            $divisor = max(1, $pico);
            foreach ($diasMapa as $fStr => $cant) {
                $dt = new \DateTimeImmutable($fStr);
                $serie[] = [
                    'fecha'      => $fStr,
                    'dia_mes'    => $dt->format('d/m'),
                    'total'      => $cant,
                    'altura_pct' => $cant > 0 ? max(8, (int) round(($cant / $divisor) * 100)) : 2,
                ];
            }

            $fechasClaves = array_keys($diasMapa);
            $fInicio = !empty($fechasClaves) ? (new \DateTimeImmutable($fechasClaves[0]))->format('d M') : '';
            $fMitad  = !empty($fechasClaves) ? (new \DateTimeImmutable($fechasClaves[(int) (count($fechasClaves) / 2)]))->format('d M') : '';
            $fFin    = !empty($fechasClaves) ? (new \DateTimeImmutable(end($fechasClaves)))->format('d M') : '';

            $res = [
                'total'        => $total,
                'pico'         => $pico,
                'fecha_inicio' => $fInicio,
                'fecha_mitad'  => $fMitad,
                'fecha_fin'    => $fFin,
                'serie'        => $serie,
            ];
        } catch (Throwable $e) {
            error_log('DashboardModel::getActividadBitacora: ' . $e->getMessage());
        }

        return $res;
    }

    /**
 * Usuarios agrupados por rol, ordenados de mayor a menor. rol: string, total: int,
 * activos: int, inactivos: int, porcentaje_relativo: int }>.
 *
 * @return list<array{
 */
    public function getUsuariosPorRol(): array
    {
        $resultado = [];

        try {
            $stmt = $this->pdo->query(
                "SELECT r.nombre as rol,
                        COUNT(u.id_usuario) as total,
                        SUM(CASE WHEN u.estado = 'ACTIVO' THEN 1 ELSE 0 END) as activos,
                        SUM(CASE WHEN u.estado != 'ACTIVO' THEN 1 ELSE 0 END) as inactivos
                   FROM rol r
              LEFT JOIN usuario u ON u.id_rol = r.id_rol
               GROUP BY r.id_rol, r.nombre
                 HAVING COUNT(u.id_usuario) > 0
               ORDER BY total DESC, r.nombre ASC"
            );
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $maxTotal = 1;
            foreach ($filas as $f) {
                if ((int) $f['total'] > $maxTotal) {
                    $maxTotal = (int) $f['total'];
                }
            }

            foreach ($filas as $f) {
                $tot = (int) $f['total'];
                $resultado[] = [
                    'rol'                 => (string) $f['rol'],
                    'total'               => $tot,
                    'activos'             => (int) ($f['activos'] ?? 0),
                    'inactivos'           => (int) ($f['inactivos'] ?? 0),
                    'porcentaje_relativo' => (int) round(($tot / $maxTotal) * 100),
                ];
            }
        } catch (Throwable $e) {
            error_log('DashboardModel::getUsuariosPorRol: ' . $e->getMessage());
        }

        return $resultado;
    }
}
