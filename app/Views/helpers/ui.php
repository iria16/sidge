<?php
/**
 * Helpers de presentación compartidos por el layout interno.
 * Solo se cargan una vez (guard function_exists).
 */

if (!function_exists('sigde_iniciales')) {
    function sigde_iniciales(string $nombre): string
    {
        $partes = preg_split('/\s+/u', trim($nombre), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $primera = $partes[0] ?? '';
        $ultima  = count($partes) > 1 ? (string) end($partes) : '';
        $a = $primera !== '' ? mb_strtoupper(mb_substr($primera, 0, 1)) : '';
        $b = $ultima !== '' ? mb_strtoupper(mb_substr($ultima, 0, 1)) : '';

        return $a . $b ?: 'SG';
    }
}

if (!function_exists('sigde_rol_clave')) {
    /**
     * Clave canónica de rol usada en menú, dashboard y guards.
     * La BD puede devolver "Administrador", "ADMIN", "admin", etc.
     */
    function sigde_rol_clave(string $rol): string
    {
        $rol = mb_strtolower(trim($rol), 'UTF-8');
        $rol = strtr($rol, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        ]);

        return match ($rol) {
            'administrador', 'administrator' => 'admin',
            'secretaria'                    => 'secretaria',
            'director', 'directora'        => 'directivo',
            default                         => $rol,
        };
    }
}

if (!function_exists('csrfToken')) {
    function csrfToken(): string
    {
        return (string) ($_SESSION['csrf'] ?? '');
    }
}

if (!function_exists('e')) {
    /**
     * Helper de presentación para escapar salida HTML segura.
     */
    function e(mixed $valor): string
    {
        return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('enlaceWhatsapp')) {
    /**
     * Enlace de WhatsApp para un teléfono venezolano.
     *
     * Quita todo lo que no sea dígito, quita el cero inicial y antepone 58.
     * Solo acepta celulares (0412, 0414, 0416, 0422, 0424, 0426 + 7 dígitos):
     * un fijo no tiene WhatsApp. Si el número no es válido devuelve '' y la
     * vista no pinta el botón.
     *
     * El resultado debe escaparse con htmlspecialchars al imprimirlo.
     */
    function enlaceWhatsapp(string $tel, string $msg = ''): string
    {
        $digitos = preg_replace('/\D/', '', $tel) ?? '';

        // Admite "04141234567", "4141234567" y "584141234567".
        if (strlen($digitos) === 12 && str_starts_with($digitos, '58')) {
            $digitos = '0' . substr($digitos, 2);
        } elseif (strlen($digitos) === 10) {
            $digitos = '0' . $digitos;
        }

        if (!preg_match('/^04(12|14|16|22|24|26)\d{7}$/', $digitos)) {
            return '';
        }

        return 'https://wa.me/58' . substr($digitos, 1) . '?text=' . rawurlencode($msg);
    }
}

if (!function_exists('sigde_rol_etiqueta')) {
    function sigde_rol_etiqueta(string $rol): string
    {
        return match (sigde_rol_clave($rol)) {
            'admin'       => 'Administrador',
            'directivo'   => 'Directivo',
            'secretaria'  => 'Secretaría',
            'docente'     => 'Docente',
            'coordinador' => 'Coordinador',
            default       => 'Usuario',
        };
    }
}

if (!function_exists('sigde_nav_sections')) {
    /**
     * Menú lateral filtrado por rol.
     *
     * @return list<array{label:string,items:list<array{id:string,label:string,icon:string,href:string}>}>
     */
    function sigde_nav_sections(string $rol): array
    {
        $rol = sigde_rol_clave($rol);
        $dashboardHref = BASE_URL . 'dashboard/' . ($rol !== '' ? $rol : 'index');

        $todas = [
            [
                'label' => 'Panel',
                'roles' => ['admin', 'directivo', 'secretaria', 'docente', 'coordinador'],
                'items' => [
                    ['id' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2', 'href' => $dashboardHref],
                ],
            ],
            [
                'label' => 'Usuarios',
                'roles' => ['admin'],
                'items' => [
                    ['id' => 'cuentas', 'label' => 'Cuentas de usuario', 'icon' => 'bi-people', 'href' => BASE_URL . 'cuentaUsuario/index'],
                    ['id' => 'recuperacion', 'label' => 'Recuperación de acceso', 'icon' => 'bi-key', 'href' => BASE_URL . 'recuperacionAcceso/index'],
                ],
            ],
            [
                'label' => 'Gestión de estudiantes',
                'roles' => ['secretaria', 'directivo', 'coordinador'],
                'items' => [
                    ['id' => 'inscripcion',  'label' => 'Inscripción',  'icon' => 'bi-person-plus',     'href' => BASE_URL . 'inscripciones/index', 'roles' => ['secretaria']],
                    ['id' => 'ratificacion', 'label' => 'Ratificación', 'icon' => 'bi-arrow-repeat',    'href' => BASE_URL . 'ratificacion/index',  'roles' => ['secretaria']],
                    ['id' => 'retiro',       'label' => 'Retiro',       'icon' => 'bi-box-arrow-right', 'href' => BASE_URL . 'withdrawal/index',    'roles' => ['secretaria', 'directivo']],
                    ['id' => 'promocion',    'label' => 'Promoción Académica', 'icon' => 'bi-award',     'href' => BASE_URL . 'resultadoAcademico/index', 'roles' => ['directivo', 'coordinador']],
                ],
            ],
            [
                'label' => 'Mi sección',
                'roles' => ['docente'],
                'items' => [
                    ['id' => 'mi-seccion-estudiantes', 'label' => 'Estudiantes', 'icon' => 'bi-mortarboard', 'href' => BASE_URL . 'estudiantes/index'],
                    ['id' => 'mi-seccion-listados',    'label' => 'Listados', 'icon' => 'bi-journal-text', 'href' => BASE_URL . 'docente/nomina'],
                    ['id' => 'mi-seccion-promocion',   'label' => 'Resultados y Promoción', 'icon' => 'bi-award', 'href' => BASE_URL . 'resultadoAcademico/index'],
                ],
            ],
            [
                'label' => 'Consulta académica',
                'roles' => ['directivo', 'coordinador', 'secretaria'],
                'items' => [
                    ['id' => 'estudiantes', 'label' => 'Estudiantes', 'icon' => 'bi-mortarboard', 'href' => BASE_URL . 'estudiantes/index'],
                ],
            ],

            [
                'label' => 'Constancias',
                'roles' => ['secretaria'],
                'items' => [
                    ['id' => 'constancias', 'label' => 'Emisión de Constancias', 'icon' => 'bi-file-earmark-text', 'href' => BASE_URL . 'constancias/index'],
                ],
            ],
            [
                'label' => 'Personal',
                'roles' => ['directivo', 'coordinador'],
                'items' => [
                    ['id' => 'empleados', 'label' => 'Empleados', 'icon' => 'bi-person-badge', 'href' => BASE_URL . 'empleado/index'],
                ],
            ],
            [
                'label' => 'Estructura académica',
                'roles' => ['coordinador'],
                'items' => [
                    ['id' => 'estructura-academica', 'label' => 'Estructura Académica', 'icon' => 'bi-diagram-3',     'href' => BASE_URL . 'academicStructure/index'],
                    ['id' => 'asignacion-docente',   'label' => 'Asignación Docente',   'icon' => 'bi-person-video3', 'href' => BASE_URL . 'teacherAssignment/index'],
                ],
            ],
            [
                'label' => 'Configuración académica',
                'roles' => ['directivo'],
                'items' => [
                    ['id' => 'anios-escolares',      'label' => 'Años Escolares',      'icon' => 'bi-calendar3',       'href' => BASE_URL . 'academicYear/index'],
                    ['id' => 'estructura-academica', 'label' => 'Estructura Académica', 'icon' => 'bi-diagram-3',     'href' => BASE_URL . 'academicStructure/index'],
                    ['id' => 'asignacion-docente',   'label' => 'Asignación Docente',   'icon' => 'bi-person-video3', 'href' => BASE_URL . 'teacherAssignment/index'],
                ],
            ],
            [
                'label' => 'Seguimiento institucional',
                'roles' => ['directivo', 'coordinador'],
                'items' => [
                    ['id' => 'estadisticas', 'label' => 'Estadísticas', 'icon' => 'bi-bar-chart', 'href' => BASE_URL . 'estadistica/index', 'roles' => ['directivo', 'coordinador']],
                    ['id' => 'reportes',     'label' => 'Reportes',     'icon' => 'bi-file-earmark-bar-graph', 'href' => BASE_URL . 'reporte/index', 'roles' => ['directivo', 'coordinador']],
                ],
            ],
            [
                'label' => 'Configuración',
                'roles' => ['admin'],
                'items' => [
                    ['id' => 'catalogos', 'label' => 'Catálogos', 'icon' => 'bi-sliders', 'href' => BASE_URL . 'catalogo/index'],
                    ['id' => 'configuracion-institucional', 'label' => 'Datos Institucionales', 'icon' => 'bi-building-gear', 'href' => BASE_URL . 'configuracionInstitucional/index'],
                ],
            ],
            [
                'label' => 'Auditoría',
                'roles' => ['admin'],
                'items' => [
                    ['id' => 'bitacora', 'label' => 'Bitácora', 'icon' => 'bi-clock-history', 'href' => BASE_URL . 'bitacora/index'],
                ],
            ],
        ];

        $secciones = [];
        foreach ($todas as $seccion) {
            if (!in_array($rol, $seccion['roles'], true)) {
                continue;
            }

            $itemsVisibles = array_values(array_filter(
                $seccion['items'],
                fn($item) => !isset($item['roles']) || in_array($rol, $item['roles'], true)
            ));

            if (empty($itemsVisibles)) {
                continue;
            }

            $secciones[] = [
                'label' => $seccion['label'],
                'items' => $itemsVisibles,
            ];
        }

        return $secciones;
    }
}