<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Configuracion;
use PDO;
use Throwable;

/**
 * Modelo para la gestión y persistencia de la Configuración Institucional y Directiva.
 *
 * Extiende BaseModel (la clase base compartida por todos los modelos del proyecto).
 *
 * Responsabilidades:
 * 1. Consulta y actualización de parámetros oficiales en la tabla `configuracion_institucional`
 *    utilizando INSERT ... ON DUPLICATE KEY UPDATE.
 * 2. Validación estricta antes de GD:
 *    - Tamaño máximo 2 MB.
 *    - MIME type real con finfo_file.
 *    - Ancho mínimo de 300 px.
 *    - Rechazo inmediato de imágenes con más de 4000 px por lado (protección de memoria).
 *    - Firma y sello: estrictamente PNG.
 * 3. Re-codificación y reducción de escala con GD:
 *    - Si el lado largo supera los 1000 px, se reduce proporcionalmente a un máximo de 1000 px.
 *    - Se preserva el canal alfa en imágenes PNG.
 * 4. Almacenamiento seguro y orden de operaciones:
 *    - Logos institucionales en public/uploads/institucion/.
 *    - Firma y sello fuera de public/ (storage/institucion/).
 *    - PRIMERO se realiza el UPDATE en la base de datos; SOLO si es exitoso se borra el archivo anterior.
 *      Si la base de datos falla, el archivo nuevo generado se elimina y el anterior queda intacto.
 * 5. Registro automático de cada cambio en la tabla `bitacora`.
 */
class ConfiguracionInstitucionalModel extends BaseModel
{
    protected string $table = 'configuracion_institucional';
    protected string $primaryKey = 'clave';

    protected array $columns = ['clave', 'valor', 'descripcion', 'fecha_actualizacion', 'actualizado_por'];
    protected array $allowedColumns = ['clave', 'valor', 'descripcion', 'fecha_actualizacion', 'actualizado_por'];

    /** @var int Ancho mínimo exigido para imágenes (300 px) */
    private const ANCHO_MINIMO_PX = 300;

    /** @var int Límite máximo absoluto por lado antes de procesar con GD (3000 px) */
    private const LIMITE_MAXIMO_LADO_PX = 3000;

    /** @var int Tamaño máximo del lado largo tras re-escalado con GD (1000 px) */
    private const MAX_LADO_ESCALADO_PX = 1000;

    /** @var int Tamaño máximo permitido por imagen en bytes (2 MB) */
    private const TAMANO_MAXIMO_BYTES = 2097152;

    /** @var array<string> Claves de texto que son obligatorias */
    private const CLAVES_OBLIGATORIAS = [
        'plantel_nombre',
        'plantel_nombre_corto',
        'ministerio_linea_1',
        'ministerio_linea_2',
    ];

    /** @var array<string> Claves válidas de texto */
    private const CLAVES_TEXTO = [
        'ministerio_linea_1',
        'ministerio_linea_2',
        'certificado_epigrafe',
        'plantel_nombre',
        'plantel_nombre_corto',
        'plantel_codigo_dea',
        'director_nombre',
        'director_primer_nombre',
        'director_segundo_nombre',
        'director_primer_apellido',
        'director_segundo_apellido',
        'director_cedula',
        'director_cargo',
        'cdce_director_nombre',
        'cdce_director_cedula',
        'plantel_municipio',
        'plantel_parroquia',
        'cdce_estado',
        'plantel_codigo_dependencia',
        'nivel_educativo',
        'nivel_codigo',
        'lugar_expedicion',
    ];

    /** @var array<string> Claves válidas de imagen */
    private const CLAVES_IMAGEN = [
        'ministerio_logo',
        'plantel_logo',
        'director_firma',
        'institucion_sello',
        'cdce_director_firma',
        'cdce_institucion_sello',
        'plantel_cintillo',
    ];

    /**
     * Retorna los catálogos de estado, municipio y parroquia para los selects territoriales.
     *
     * @return array{estados: array<int, array<string, mixed>>, municipios: array<int, array<string, mixed>>, parroquias: array<int, array<string, mixed>>}
     */
    public function getCatalogoUbicacion(): array
    {
        $stmtEst = $this->pdo->prepare("SELECT id_estado, nombre FROM estado WHERE estado = 'ACTIVO' ORDER BY nombre");
        $stmtEst->execute();
        $estados = $stmtEst->fetchAll(PDO::FETCH_ASSOC);

        $stmtMun = $this->pdo->prepare("SELECT id_municipio, id_estado, nombre FROM municipio WHERE estado = 'ACTIVO' ORDER BY nombre");
        $stmtMun->execute();
        $municipios = $stmtMun->fetchAll(PDO::FETCH_ASSOC);

        $stmtPar = $this->pdo->prepare("SELECT id_parroquia, id_municipio, nombre FROM parroquia WHERE estado = 'ACTIVO' ORDER BY nombre");
        $stmtPar->execute();
        $parroquias = $stmtPar->fetchAll(PDO::FETCH_ASSOC);

        return [
            'estados'    => $estados,
            'municipios' => $municipios,
            'parroquias' => $parroquias,
        ];
    }

    /**
     * Catálogo oficial de niveles educativos del MPPE con sus respectivos códigos.
     *
     * @return array<int, array{nombre: string, codigo: string, descripcion: string}>
     */
    public function getNivelesEducativos(): array
    {
        return [
            [
                'nombre'      => 'Educación Primaria',
                'codigo'      => '21000',
                'descripcion' => 'Educación Primaria (1° a 6° Grado)',
            ],
            [
                'nombre'      => 'Educación Inicial',
                'codigo'      => '11000',
                'descripcion' => 'Educación Inicial / Preescolar',
            ],
            [
                'nombre'      => 'Educación Media General',
                'codigo'      => '31000',
                'descripcion' => 'Educación Media General (1° a 5° Año)',
            ],
            [
                'nombre'      => 'Educación Media Técnica',
                'codigo'      => '32000',
                'descripcion' => 'Educación Media Técnica (1° a 6° Año)',
            ],
            [
                'nombre'      => 'Educación de Jóvenes, Adultas y Adultos',
                'codigo'      => '41000',
                'descripcion' => 'Educación de Jóvenes, Adultas y Adultos',
            ],
            [
                'nombre'      => 'Educación Especial',
                'codigo'      => '51000',
                'descripcion' => 'Educación Especial',
            ],
        ];
    }

    /** @var array<string, string> Valores oficiales por defecto centralizados */
    public const VALORES_DEFECTO = [
        'ministerio_linea_1'         => 'REPÚBLICA BOLIVARIANA DE VENEZUELA',
        'ministerio_linea_2'         => 'MINISTERIO DEL PODER POPULAR PARA LA EDUCACIÓN',
        'certificado_epigrafe'       => 'BICENTENARIO DE LA UNIDAD AMERICANA: CONGRESO ANFICTIÓNICO DE PANAMÁ',
        'plantel_nombre'             => 'ESCUELA BÁSICA "ISIDRO RAMÍREZ"',
        'plantel_nombre_corto'       => 'E.B. Isidro Ramírez',
        'plantel_codigo_dea'         => 'OD05750814',
        'plantel_codigo_dependencia' => '006562012',
        'cdce_estado'                => 'Carabobo',
        'plantel_municipio'          => 'Valencia',
        'plantel_parroquia'          => 'Miguel Peña',
        'lugar_expedicion'           => 'Carabobo',
        'nivel_educativo'            => 'Educación Primaria',
        'nivel_codigo'               => '21000',
        'director_nombre'            => '',
        'director_primer_nombre'     => '',
        'director_segundo_nombre'    => '',
        'director_primer_apellido'   => '',
        'director_segundo_apellido'  => '',
        'director_cedula'            => '',
        'director_cargo'             => 'Director (E)',
        'cdce_director_nombre'       => '',
        'cdce_director_cedula'       => '',
        'ministerio_logo'            => '/assets/img/ministerio_logo_bandera.png',
        'plantel_logo'               => '/assets/img/logo.png',
        'plantel_cintillo'           => '',
        'director_firma'             => '',
        'institucion_sello'          => '',
        'cdce_director_firma'        => '',
        'cdce_institucion_sello'     => '',
    ];

    /**
     * Retorna todas las configuraciones indexadas por clave fusionando los valores por defecto.
     *
     * @return array<string, string>
     */
    public function getAll(array $filters = [], int $page = 1, int $perPage = 100): array
    {
        $stmt = $this->pdo->prepare('SELECT clave, valor FROM configuracion_institucional');
        $stmt->execute();
        $resultado = self::VALORES_DEFECTO;
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $clave = (string) $fila['clave'];
            $valor = (string) ($fila['valor'] ?? '');
            if ($valor !== '' || !isset($resultado[$clave])) {
                $resultado[$clave] = $valor;
            }
        }
        return $resultado;
    }

    /**
     * Obtiene el valor de una clave individual con fallback centralizado.
     */
    public function get(string $clave, string $default = ''): string
    {
        $stmt = $this->pdo->prepare('SELECT valor FROM configuracion_institucional WHERE clave = :clave LIMIT 1');
        $stmt->execute(['clave' => $clave]);
        $val = $stmt->fetchColumn();

        if ($val !== false && trim((string) $val) !== '') {
            return (string) $val;
        }

        if ($default !== '') {
            return $default;
        }

        return self::VALORES_DEFECTO[$clave] ?? '';
    }

    /**
     * Actualiza masivamente los campos de texto dentro de una transacción con
    /** @var array<string, array<string>> Mapeo de campos agrupados por sección independiente */
    public const SECCIONES = [
        'plantel' => [
            'plantel_nombre',
            'plantel_nombre_corto',
            'plantel_codigo_dea',
            'cdce_estado',
            'plantel_municipio',
            'plantel_parroquia',
            'lugar_expedicion',
            'plantel_codigo_dependencia',
            'nivel_educativo',
            'nivel_codigo',
        ],
        'directiva' => [
            'director_primer_nombre',
            'director_segundo_nombre',
            'director_primer_apellido',
            'director_segundo_apellido',
            'director_nombre',
            'director_cedula',
            'director_cargo',
            'cdce_director_nombre',
            'cdce_director_cedula',
        ],
        'membrete' => [
            'ministerio_linea_1',
            'ministerio_linea_2',
            'certificado_epigrafe',
        ],
    ];

    /**
     * Valida y actualiza en masa los parámetros textuales en la base de datos.
     * INSERT ... ON DUPLICATE KEY UPDATE.
     *
     * @param array<string, string> $datos Arreglo asociativo [clave => valor]
     * @param int $idUsuario ID del administrador que realiza la modificación.
     * @param string|null $seccion Nombre de la sección para guardado independiente ('plantel', 'directiva', 'membrete' o null).
     * @return array{ok: bool, mensaje: string, errores?: array<string, string>}
     */
    public function guardarTextos(array $datos, int $idUsuario, ?string $seccion = null): array
    {
        $errores = [];
        $limpios = [];

        $clavesAProcesar = ($seccion !== null && isset(self::SECCIONES[$seccion]))
            ? self::SECCIONES[$seccion]
            : self::CLAVES_TEXTO;

        // Lista blanca estricta: rechazar cualquier clave desconocida enviada en la petición
        foreach (array_keys($datos) as $claveEnviada) {
            if (in_array($claveEnviada, ['csrf', 'csrf_token', 'seccion'], true)) {
                continue;
            }
            if (!in_array($claveEnviada, $clavesAProcesar, true)) {
                return [
                    'ok'      => false,
                    'mensaje' => "El parámetro '{$claveEnviada}' no está permitido.",
                    'errores' => [$claveEnviada => 'Parámetro no autorizado.'],
                ];
            }
        }

        foreach ($clavesAProcesar as $clave) {
            $raw = (string) ($datos[$clave] ?? '');
            $valor = preg_replace('/\s+/u', ' ', trim(strip_tags($raw))) ?? '';

            // Validar obligatoriedad básica
            if (in_array($clave, self::CLAVES_OBLIGATORIAS, true) && $valor === '') {
                $mensaje = match ($clave) {
                    'plantel_nombre'       => 'El nombre oficial del plantel es obligatorio.',
                    'plantel_nombre_corto' => 'El nombre corto del plantel es obligatorio.',
                    'ministerio_linea_1'   => 'La línea 1 del ministerio es obligatoria.',
                    'ministerio_linea_2'   => 'La línea 2 del ministerio es obligatoria.',
                    default                => 'Este campo es obligatorio.',
                };
                $errores[$clave] = $mensaje;
                continue;
            }

            // Validar obligatoriedad de directiva
            if (($seccion === 'directiva' || $seccion === null) && in_array($clave, ['director_primer_nombre', 'director_primer_apellido'], true) && $valor === '') {
                $errores[$clave] = match ($clave) {
                    'director_primer_nombre'   => 'El primer nombre del director es obligatorio.',
                    'director_primer_apellido' => 'El primer apellido del director es obligatorio.',
                    default                    => 'Este campo es obligatorio.',
                };
                continue;
            }

            // Validar obligatoriedad de selecciones territoriales y nivel educativo
            if (($seccion === 'plantel' || $seccion === null) && in_array($clave, ['cdce_estado', 'plantel_municipio', 'plantel_parroquia', 'nivel_educativo', 'lugar_expedicion', 'plantel_codigo_dependencia', 'nivel_codigo'], true) && $valor === '') {
                $mensaje = match ($clave) {
                    'cdce_estado'                => 'Debe seleccionar el Estado CDCE.',
                    'plantel_municipio'          => 'Debe seleccionar el Municipio del plantel.',
                    'plantel_parroquia'          => 'Debe seleccionar la Parroquia del plantel.',
                    'nivel_educativo'            => 'Debe seleccionar el Nivel Educativo.',
                    'lugar_expedicion'           => 'El lugar de expedición en constancias es obligatorio.',
                    'plantel_codigo_dependencia' => 'El Código de Dependencia es obligatorio.',
                    'nivel_codigo'               => 'El Código de Nivel MPPE es obligatorio.',
                    default                      => 'Este campo es obligatorio.',
                };
                $errores[$clave] = $mensaje;
                continue;
            }

            // Validaciones de longitud según el tipo de campo
            $limite = match ($clave) {
                'plantel_codigo_dea'   => 50,
                'director_cedula'      => 25,
                'plantel_nombre_corto' => 100,
                default                => 200,
            };

            // Validación específica de formato de cédula del director o director CDCE si viene indicada
            if (in_array($clave, ['director_cedula', 'cdce_director_cedula'], true) && $valor !== '') {
                $digitos = preg_replace('/\D/', '', $valor);
                $letra = strtoupper(substr(trim($valor), 0, 1));
                if (!in_array($letra, ['V', 'E'], true) || strlen($digitos) < 6 || strlen($digitos) > 9) {
                    $errores[$clave] = 'La cédula debe tener un formato válido: V o E seguida de 6 a 9 dígitos (ej. V-12.345.678).';
                    continue;
                }
            }

            // Validación de formato del Código DEA (alfanumérico de 6 a 20 caracteres sin espacios ni símbolos)
            if ($clave === 'plantel_codigo_dea' && $valor !== '') {
                if (!preg_match('/^[A-Za-z0-9]{6,20}$/', $valor)) {
                    $errores['plantel_codigo_dea'] = 'El Código DEA debe contener entre 6 y 20 caracteres alfanuméricos sin espacios ni símbolos (ej. OD05281402).';
                    continue;
                }
            }

            // Validación de formato del Código de Dependencia (numérico de 6 a 15 dígitos)
            if ($clave === 'plantel_codigo_dependencia' && $valor !== '') {
                if (!preg_match('/^\d{6,15}$/', $valor)) {
                    $errores['plantel_codigo_dependencia'] = 'El Código de Dependencia debe contener entre 6 y 15 dígitos numéricos (ej. 006562012).';
                    continue;
                }
            }

            // Validación de formato del Código de Nivel MPPE (exactamente 5 dígitos numéricos)
            if ($clave === 'nivel_codigo' && $valor !== '') {
                if (!preg_match('/^\d{5}$/', $valor)) {
                    $errores['nivel_codigo'] = 'El Código de Nivel MPPE debe contener exactamente 5 dígitos numéricos (ej. 21000).';
                    continue;
                }
            }

            $limpios[$clave] = $valor;
        }

        // Validación de coherencia territorial: estado -> municipio -> parroquia
        if (isset($limpios['cdce_estado'], $limpios['plantel_municipio'], $limpios['plantel_parroquia'])) {
            $estadoVal  = $limpios['cdce_estado'];
            $munVal     = $limpios['plantel_municipio'];
            $parrVal    = $limpios['plantel_parroquia'];

            if ($estadoVal !== '' && $munVal !== '' && $parrVal !== '') {
                $nombresTerritorio = $this->validarCoherenciaTerritorial($estadoVal, $munVal, $parrVal);
                if ($nombresTerritorio === null) {
                    $errores['plantel_parroquia'] = 'La parroquia seleccionada no pertenece al municipio y estado indicados.';
                } else {
                    // Guarda los nombres leídos de la BD, no los del request
                    $limpios['cdce_estado']       = $nombresTerritorio['estado_nombre'];
                    $limpios['plantel_municipio'] = $nombresTerritorio['municipio_nombre'];
                    $limpios['plantel_parroquia'] = $nombresTerritorio['parroquia_nombre'];
                }
            }
        }

        // Validación de coherencia de nivel educativo <-> código MPPE
        if (isset($limpios['nivel_educativo'], $limpios['nivel_codigo'])) {
            $nivelVal  = $limpios['nivel_educativo'];
            $codigoVal = $limpios['nivel_codigo'];

            if ($nivelVal !== '' && $codigoVal !== '') {
                if (!$this->validarCoherenciaNivel($nivelVal, $codigoVal)) {
                    $errores['nivel_codigo'] = 'El Código MPPE no se corresponde con el nivel educativo seleccionado.';
                }
            }
        }

        if (!empty($errores)) {
            return [
                'ok'      => false,
                'mensaje' => 'Se encontraron errores de validación en los datos proporcionados.',
                'errores' => $errores,
            ];
        }

        try {
            $this->pdo->beginTransaction();

            $sql = 'INSERT INTO configuracion_institucional (clave, valor, actualizado_por, fecha_actualizacion)
                    VALUES (:clave, :valor, :usuario, CURRENT_TIMESTAMP)
                    ON DUPLICATE KEY UPDATE
                        valor = VALUES(valor),
                        actualizado_por = VALUES(actualizado_por),
                        fecha_actualizacion = CURRENT_TIMESTAMP';

            $stmt = $this->pdo->prepare($sql);

            foreach ($limpios as $clave => $valor) {
                $stmt->execute([
                    'clave'   => $clave,
                    'valor'   => $valor,
                    'usuario' => $idUsuario,
                ]);
            }

            // Registrar acción en la bitácora institucional
            $detalleBitacora = match ($seccion) {
                'plantel'   => 'ACTUALIZAR CONFIGURACIÓN INSTITUCIONAL · Datos del plantel y ubicación territorial',
                'directiva' => 'ACTUALIZAR CONFIGURACIÓN INSTITUCIONAL · Autoridad directiva y firmante',
                'membrete'  => 'ACTUALIZAR CONFIGURACIÓN INSTITUCIONAL · Membrete oficial de la República',
                default     => 'ACTUALIZAR CONFIGURACIÓN INSTITUCIONAL · Datos del plantel y directiva',
            };

            $bitacora = new BitacoraModel($this->pdo);
            $bitacoraOk = $bitacora->registrar(
                $idUsuario,
                $detalleBitacora,
                'Configuración'
            );
            if (!$bitacoraOk) {
                throw new \RuntimeException('No se pudo registrar la auditoría en la bitácora.');
            }

            $this->pdo->commit();

            // Invalidar la caché en memoria del helper global
            Configuracion::invalidateCache();

            $mensajeExito = match ($seccion) {
                'plantel'   => 'Datos del plantel y ubicación territorial actualizados correctamente.',
                'directiva' => 'Datos de la autoridad directiva actualizados correctamente.',
                'membrete'  => 'Membrete oficial de la República actualizado correctamente.',
                default     => 'Configuración institucional actualizada correctamente.',
            };

            return [
                'ok'      => true,
                'mensaje' => $mensajeExito,
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('ConfiguracionInstitucionalModel::guardarTextos() error: ' . $e->getMessage());

            return [
                'ok'      => false,
                'mensaje' => 'Ocurrió un error al intentar guardar los datos en la base de datos.',
            ];
        }
    }

    /**
     * Valida la coherencia de la jerarquía territorial estado -> municipio -> parroquia.
     * Si llegan IDs, valida estrictamente por IDs y retorna los nombres canónicos de la BD.
     * Si llegan nombres, valida estrictamente por nombres y retorna los nombres canónicos de la BD.
     * Sin operadores OR entre nombre e ID en el SQL.
     *
     * @return array{estado_nombre: string, municipio_nombre: string, parroquia_nombre: string}|null
     */
    public function validarCoherenciaTerritorial(string $estado, string $municipio, string $parroquia): ?array
    {
        $estadoTrim = trim($estado);
        $munTrim    = trim($municipio);
        $parrTrim   = trim($parroquia);

        // Si llegan IDs numéricos, valida ÚNICAMENTE por IDs
        if (is_numeric($estadoTrim) && is_numeric($munTrim) && is_numeric($parrTrim)) {
            $sql = 'SELECT e.nombre AS estado_nombre, m.nombre AS municipio_nombre, p.nombre AS parroquia_nombre
                      FROM parroquia p
                      JOIN municipio m ON m.id_municipio = p.id_municipio
                      JOIN estado e ON e.id_estado = m.id_estado
                     WHERE e.id_estado = :estadoId
                       AND m.id_municipio = :municipioId
                       AND p.id_parroquia = :parroquiaId
                     LIMIT 1';

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'estadoId'    => (int) $estadoTrim,
                'municipioId' => (int) $munTrim,
                'parroquiaId' => (int) $parrTrim,
            ]);

            $fila = $stmt->fetch(PDO::FETCH_ASSOC);
            return $fila ? [
                'estado_nombre'    => (string) $fila['estado_nombre'],
                'municipio_nombre' => (string) $fila['municipio_nombre'],
                'parroquia_nombre' => (string) $fila['parroquia_nombre'],
            ] : null;
        }

        // Si llegan nombres de texto, valida ÚNICAMENTE por nombres
        $sql = 'SELECT e.nombre AS estado_nombre, m.nombre AS municipio_nombre, p.nombre AS parroquia_nombre
                  FROM parroquia p
                  JOIN municipio m ON m.id_municipio = p.id_municipio
                  JOIN estado e ON e.id_estado = m.id_estado
                 WHERE LOWER(TRIM(e.nombre)) = LOWER(TRIM(:estado))
                   AND LOWER(TRIM(m.nombre)) = LOWER(TRIM(:municipio))
                   AND LOWER(TRIM(p.nombre)) = LOWER(TRIM(:parroquia))
                 LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'estado'    => $estadoTrim,
            'municipio' => $munTrim,
            'parroquia' => $parrTrim,
        ]);

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? [
            'estado_nombre'    => (string) $fila['estado_nombre'],
            'municipio_nombre' => (string) $fila['municipio_nombre'],
            'parroquia_nombre' => (string) $fila['parroquia_nombre'],
        ] : null;
    }

    /**
     * Valida que el nivel educativo coincida con su código MPPE oficial.
     */
    public function validarCoherenciaNivel(string $nivel, string $codigo): bool
    {
        foreach ($this->getNivelesEducativos() as $n) {
            if (mb_strtoupper(trim($n['nombre']), 'UTF-8') === mb_strtoupper(trim($nivel), 'UTF-8')) {
                return trim((string) $n['codigo']) === trim((string) $codigo);
            }
        }

        return false;
    }

    /**
     * Valida, re-codifica con GD y guarda una imagen subida.
     *
     * Orden de operaciones:
     * 1. Validación estricta y generación del nuevo archivo temporal re-codificado.
     * 2. PRIMERO se realiza el UPDATE en la base de datos.
     * 3. SOLO SI el UPDATE es exitoso, se elimina el archivo anterior del disco.
     * 4. Si la base de datos falla, se borra el archivo nuevo generado y el anterior permanece intacto.
     *
     * @param string $clave Clave de la imagen a actualizar.
     * @param array $fileArray Arreglo $_FILES['campo'] correspondiente.
     * @param int $idUsuario ID del administrador autenticado.
     * @return array{ok: bool, mensaje: string, ruta?: string, asset_url?: string}
     */
    public function procesarYGuardarImagen(string $clave, array $fileArray, int $idUsuario): array
    {
        if (!in_array($clave, self::CLAVES_IMAGEN, true)) {
            return ['ok' => false, 'mensaje' => 'Clave de imagen no autorizada.'];
        }

        // 1. Validar estado de subida HTTP
        $errorUpload = (int) ($fileArray['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorUpload !== UPLOAD_ERR_OK) {
            $mensaje = match ($errorUpload) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'El archivo supera el tamaño máximo permitido.',
                UPLOAD_ERR_PARTIAL  => 'El archivo se subió solo parcialmente.',
                UPLOAD_ERR_NO_FILE  => 'No se seleccionó ningún archivo.',
                default             => 'Error al cargar el archivo en el servidor.',
            };
            return ['ok' => false, 'mensaje' => $mensaje];
        }

        $tmpPath = (string) ($fileArray['tmp_name'] ?? '');
        if (!$this->esArchivoSubido($tmpPath)) {
            return ['ok' => false, 'mensaje' => 'El archivo subido no es válido.'];
        }

        // 2. Validar tamaño máximo (2 MB)
        $tamano = (int) ($fileArray['size'] ?? 0);
        if ($tamano > self::TAMANO_MAXIMO_BYTES) {
            return ['ok' => false, 'mensaje' => 'La imagen no debe superar los 2 MB de tamaño.'];
        }

        // 3. Validar MIME Type real con finfo_file (no confiar en la extensión del cliente)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo !== false ? finfo_file($finfo, $tmpPath) : '';
        if ($finfo !== false) {
            finfo_close($finfo);
        }

        // Firma y sello: estrictamente PNG
        $esFirmaOSello = in_array($clave, ['director_firma', 'institucion_sello', 'cdce_director_firma', 'cdce_institucion_sello'], true);
        if ($esFirmaOSello && $mime !== 'image/png') {
            return ['ok' => false, 'mensaje' => 'La firma y el sello deben ser imágenes PNG con fondo transparente'];
        }

        // Logos y cintillo: PNG, JPG o WEBP
        if (!$esFirmaOSello && !in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
            return ['ok' => false, 'mensaje' => 'Formato no admitido. Solo se permiten imágenes PNG, JPG y WEBP.'];
        }

        // 4. Validar dimensiones con getimagesize()
        $infoImagen = @getimagesize($tmpPath);
        if ($infoImagen === false) {
            return ['ok' => false, 'mensaje' => 'El archivo no contiene una imagen válida.'];
        }

        $ancho = (int) $infoImagen[0];
        $alto  = (int) $infoImagen[1];

        // Rechazo previo a GD si supera 4000 px por lado
        if ($ancho > self::LIMITE_MAXIMO_LADO_PX || $alto > self::LIMITE_MAXIMO_LADO_PX) {
            return [
                'ok'      => false,
                'mensaje' => 'La imagen es demasiado grande. El límite máximo permitido es de ' . self::LIMITE_MAXIMO_LADO_PX . ' px por lado.',
            ];
        }

        // Ancho mínimo de 300 px
        if ($ancho < self::ANCHO_MINIMO_PX) {
            return [
                'ok'      => false,
                'mensaje' => 'La imagen debe tener un ancho mínimo de ' . self::ANCHO_MINIMO_PX . " px (actual: {$ancho} px).",
            ];
        }

        if ($alto <= 0) {
            return ['ok' => false, 'mensaje' => 'La altura de la imagen no es válida.'];
        }

        // 5. Determinar directorio de almacenamiento
        $projectRoot = Configuracion::getProjectRoot();

        if ($esFirmaOSello) {
            $subcarpeta = match ($clave) {
                'director_firma'         => 'firma',
                'institucion_sello'      => 'sello',
                'cdce_director_firma'    => 'firma_cdce',
                'cdce_institucion_sello' => 'sello_cdce',
                default                  => 'firma',
            };
            $directorioDestino = $projectRoot . DIRECTORY_SEPARATOR . 'storage'
                . DIRECTORY_SEPARATOR . 'institucion' . DIRECTORY_SEPARATOR . $subcarpeta;
        } else {
            $directorioDestino = $projectRoot . DIRECTORY_SEPARATOR . 'public'
                . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'institucion';
        }

        if (!is_dir($directorioDestino) && !@mkdir($directorioDestino, 02775, true) && !is_dir($directorioDestino)) {
            return ['ok' => false, 'mensaje' => 'No se pudo crear el directorio de almacenamiento en el servidor.'];
        }

        // 6. Generar nombre de archivo único e inalterable
        $extension = match ($mime) {
            'image/png'  => 'png',
            'image/webp' => 'webp',
            default      => 'jpg',
        };
        $nombreArchivo = bin2hex(random_bytes(16)) . '.' . $extension;
        $rutaCompleta = $directorioDestino . DIRECTORY_SEPARATOR . $nombreArchivo;

        // 7. Re-codificación y reducción de escala con GD (máximo ~1000 px por lado largo)
        $gdOk = false;
        try {
            $recursoOriginal = match ($mime) {
                'image/png'  => @imagecreatefrompng($tmpPath),
                'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmpPath) : false,
                default      => @imagecreatefromjpeg($tmpPath),
            };

            if ($recursoOriginal === false) {
                return ['ok' => false, 'mensaje' => 'La imagen no pudo ser leída por la biblioteca gráfica del servidor.'];
            }

            // Calcular dimensiones escaladas si el lado mayor supera los límites permitidos (cintillo hasta 2400 px, logos hasta 1000 px)
            $maxLadoPermitido = ($clave === 'plantel_cintillo') ? 2400 : self::MAX_LADO_ESCALADO_PX;
            $maxLadoActual = max($ancho, $alto);
            if ($maxLadoActual > $maxLadoPermitido) {
                $factorEscala = $maxLadoPermitido / $maxLadoActual;
                $nuevoAncho = max(1, (int) round($ancho * $factorEscala));
                $nuevoAlto  = max(1, (int) round($alto * $factorEscala));

                $recursoEscalado = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
                if ($recursoEscalado !== false) {
                    if (in_array($mime, ['image/png', 'image/webp'], true)) {
                        imagealphablending($recursoEscalado, false);
                        imagesavealpha($recursoEscalado, true);
                        $transparente = imagecolorallocatealpha($recursoEscalado, 0, 0, 0, 127);
                        imagefilledrectangle($recursoEscalado, 0, 0, $nuevoAncho, $nuevoAlto, $transparente);
                    }
                    imagecopyresampled($recursoEscalado, $recursoOriginal, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);
                    imagedestroy($recursoOriginal);
                    $recursoOriginal = $recursoEscalado;
                }
            } else {
                if (in_array($mime, ['image/png', 'image/webp'], true)) {
                    imagealphablending($recursoOriginal, false);
                    imagesavealpha($recursoOriginal, true);
                }
            }

            // Guardar imagen re-codificada
            if ($mime === 'image/png') {
                $gdOk = imagepng($recursoOriginal, $rutaCompleta, 8);
            } elseif ($mime === 'image/webp' && function_exists('imagewebp')) {
                $gdOk = imagewebp($recursoOriginal, $rutaCompleta, 85);
            } else {
                $gdOk = imagejpeg($recursoOriginal, $rutaCompleta, 90);
            }
            imagedestroy($recursoOriginal);
        } catch (Throwable $e) {
            error_log('ConfiguracionInstitucionalModel::procesarYGuardarImagen() GD error: ' . $e->getMessage());
            $gdOk = false;
        }

        if (!$gdOk || !is_file($rutaCompleta)) {
            if (is_file($rutaCompleta)) {
                @unlink($rutaCompleta);
            }
            return ['ok' => false, 'mensaje' => 'No se pudo procesar y re-codificar la imagen de manera segura.'];
        }

        // 8. OBTENER VALOR ACTUAL ANTES DEL UPDATE PARA BORRARLO SOLO SI EL UPDATE TIENE ÉXITO
        $valorAnterior = $this->get($clave);

        // 9. PRIMERO: Realizar el UPDATE en la base de datos
        $valorAGuardar = $esFirmaOSello ? $nombreArchivo : ('/uploads/institucion/' . $nombreArchivo);

        $commitOcurrido = false;
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                'UPDATE configuracion_institucional
                    SET valor = :valor,
                        actualizado_por = :usuario,
                        fecha_actualizacion = CURRENT_TIMESTAMP
                  WHERE clave = :clave'
            );
            $stmt->execute([
                'valor'   => $valorAGuardar,
                'usuario' => $idUsuario,
                'clave'   => $clave,
            ]);

            // Registrar en bitácora dentro de la misma transacción
            $nombreLegible = match ($clave) {
                'ministerio_logo'        => 'Logo del Ministerio',
                'plantel_logo'           => 'Logo del Plantel',
                'director_firma'         => 'Firma del Director',
                'institucion_sello'      => 'Sello Institucional',
                'cdce_director_firma'    => 'Firma del Director CDCE',
                'cdce_institucion_sello' => 'Sello CDCE Estadal',
                'plantel_cintillo'       => 'Franja Tricolor',
                default                  => $clave,
            };

            $bitacoraOk = (new BitacoraModel($this->pdo))->registrar(
                $idUsuario,
                "ACTUALIZACIÓN IMAGEN INSTITUCIONAL · {$nombreLegible}",
                'Configuración'
            );
            if (!$bitacoraOk) {
                throw new \RuntimeException('No se pudo registrar la auditoría en la bitácora.');
            }

            // Confirmar transacción
            $this->pdo->commit();
            $commitOcurrido = true;

            // SOLO TRAS COMMIT: El UPDATE y la bitácora fueron confirmados, ahora es seguro borrar el archivo anterior
            $this->eliminarArchivoFisicoPorValor($clave, $valorAnterior);

            Configuracion::invalidateCache();

            $urlPublica = $esFirmaOSello ? '' : Configuracion::asset($clave);

            return [
                'ok'        => true,
                'mensaje'   => "Imagen actualizada: Se ha guardado {$nombreLegible} correctamente en el sistema. (No requiere pulsar «Guardar Cambios»).",
                'ruta'      => $valorAGuardar,
                'asset_url' => $urlPublica,
            ];
        } catch (Throwable $e) {
            // Revertir transacción SOLO si el commit aún no había ocurrido
            if (!$commitOcurrido && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            // Borrar el archivo nuevo SOLO si el commit NO ocurrió. Si el commit ya ocurrió, se preserva.
            if (!$commitOcurrido && is_file($rutaCompleta)) {
                @unlink($rutaCompleta);
            }
            error_log('ConfiguracionInstitucionalModel::procesarYGuardarImagen() DB error: ' . $e->getMessage());

            if ($commitOcurrido) {
                return [
                    'ok'        => true,
                    'mensaje'   => "Imagen actualizada: Se ha guardado {$nombreLegible} correctamente en el sistema. (No requiere pulsar «Guardar Cambios»).",
                    'ruta'      => $valorAGuardar,
                    'asset_url' => $urlPublica ?? '',
                ];
            }

            return ['ok' => false, 'mensaje' => 'Error al registrar la imagen en la base de datos. Se conservó la imagen anterior.'];
        }
    }

    /**
     * Elimina una imagen personalizada (firma, sello o logo) y restablece su valor en BD.
     *
     * El UPDATE y la bitácora se ejecutan en una transacción; solo tras commit se elimina el archivo físico anterior.
     */
    public function eliminarImagen(string $clave, int $idUsuario): array
    {
        if (!in_array($clave, self::CLAVES_IMAGEN, true)) {
            return ['ok' => false, 'mensaje' => 'Clave de imagen no válida.'];
        }

        $valorAnterior = $this->get($clave);

        $valorPorDefecto = match ($clave) {
            'ministerio_logo'  => '/assets/img/ministerio_logo_bandera.png',
            'plantel_logo'     => '/assets/img/logo.png',
            'plantel_cintillo' => '',
            default            => '',
        };

        $commitOcurrido = false;
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                'UPDATE configuracion_institucional
                    SET valor = :valor,
                        actualizado_por = :usuario,
                        fecha_actualizacion = CURRENT_TIMESTAMP
                  WHERE clave = :clave'
            );
            $stmt->execute([
                'valor'   => $valorPorDefecto,
                'usuario' => $idUsuario,
                'clave'   => $clave,
            ]);

            $bitacoraOk = (new BitacoraModel($this->pdo))->registrar(
                $idUsuario,
                "ELIMINACIÓN / RESTABLECIMIENTO DE IMAGEN · {$clave}",
                'Configuración'
            );
            if (!$bitacoraOk) {
                throw new \RuntimeException('No se pudo registrar la auditoría en la bitácora.');
            }

            $this->pdo->commit();
            $commitOcurrido = true;

            // SOLO TRAS COMMIT se borra el archivo físico anterior
            $this->eliminarArchivoFisicoPorValor($clave, $valorAnterior);

            Configuracion::invalidateCache();

            $mensajeExito = match ($clave) {
                'plantel_cintillo'       => 'La franja tricolor ha sido retirada correctamente. Las constancias se emitirán sin cintillo inferior.',
                'director_firma'         => 'La firma digitalizada ha sido retirada correctamente.',
                'institucion_sello'      => 'El sello institucional ha sido retirado correctamente.',
                'cdce_director_firma'    => 'La firma digitalizada del Director(a) CDCE ha sido retirada correctamente.',
                'cdce_institucion_sello' => 'El sello del CDCE Estadal ha sido retirado correctamente.',
                default                  => 'El logotipo ha sido restablecido a su versión predeterminada oficial.',
            };

            return ['ok' => true, 'mensaje' => $mensajeExito];
        } catch (Throwable $e) {
            if (!$commitOcurrido && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('ConfiguracionInstitucionalModel::eliminarImagen() error: ' . $e->getMessage());

            if ($commitOcurrido) {
                return ['ok' => true, 'mensaje' => 'La imagen fue actualizada en la base de datos correctamente.'];
            }

            return ['ok' => false, 'mensaje' => 'No se pudo retirar la imagen de la base de datos.'];
        }
    }

    /**
     * Restablece la franja tricolor oficial por defecto del sistema (/assets/img/franja_tricolor.png).
     */
    public function restablecerFranjaOficial(int $idUsuario): array
    {
        $valorAnterior = $this->get('plantel_cintillo');
        $valorOficial = '/assets/img/franja_tricolor.png';

        $commitOcurrido = false;
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                'UPDATE configuracion_institucional
                    SET valor = :valor,
                        actualizado_por = :usuario,
                        fecha_actualizacion = CURRENT_TIMESTAMP
                  WHERE clave = :clave'
            );
            $stmt->execute([
                'valor'   => $valorOficial,
                'usuario' => $idUsuario,
                'clave'   => 'plantel_cintillo',
            ]);

            $bitacoraOk = (new BitacoraModel($this->pdo))->registrar(
                $idUsuario,
                'RESTABLECIMIENTO FRANJA TRICOLOR OFICIAL · plantel_cintillo',
                'Configuración'
            );
            if (!$bitacoraOk) {
                throw new \RuntimeException('No se pudo registrar la auditoría en la bitácora.');
            }

            $this->pdo->commit();
            $commitOcurrido = true;

            $this->eliminarArchivoFisicoPorValor('plantel_cintillo', $valorAnterior);

            Configuracion::invalidateCache();

            return [
                'ok'      => true,
                'mensaje' => 'Se ha restablecido la franja tricolor oficial del sistema.',
                'url'     => Configuracion::asset('plantel_cintillo'),
            ];
        } catch (Throwable $e) {
            if (!$commitOcurrido && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('ConfiguracionInstitucionalModel::restablecerFranjaOficial() error: ' . $e->getMessage());
            return ['ok' => false, 'mensaje' => 'No se pudo restablecer la franja tricolor oficial.'];
        }
    }

    /**
     * Elimina del disco el archivo físico asociado a un valor específico usando basename().
     */
    private function eliminarArchivoFisicoPorValor(string $clave, string $valor): void
    {
        $nombreLimpio = basename($valor);
        if ($nombreLimpio === '' || $nombreLimpio === '.' || $nombreLimpio === '..') {
            return;
        }

        $projectRoot = Configuracion::getProjectRoot();

        if (in_array($clave, ['director_firma', 'institucion_sello', 'cdce_director_firma', 'cdce_institucion_sello'], true)) {
            $subcarpeta = match ($clave) {
                'director_firma'         => 'firma',
                'institucion_sello'      => 'sello',
                'cdce_director_firma'    => 'firma_cdce',
                'cdce_institucion_sello' => 'sello_cdce',
                default                  => 'firma',
            };
            $rutaFisica = $projectRoot . DIRECTORY_SEPARATOR . 'storage'
                . DIRECTORY_SEPARATOR . 'institucion' . DIRECTORY_SEPARATOR . $subcarpeta
                . DIRECTORY_SEPARATOR . $nombreLimpio;
        } else {
            // Solo borrar si era un archivo subido en uploads/institucion/
            if (!str_contains($valor, 'uploads/institucion')) {
                return;
            }
            $rutaFisica = Configuracion::getPublicRoot() . DIRECTORY_SEPARATOR . 'uploads'
                . DIRECTORY_SEPARATOR . 'institucion' . DIRECTORY_SEPARATOR . $nombreLimpio;
        }

        if (is_file($rutaFisica)) {
            @unlink($rutaFisica);
        }
    }

    /**
     * Comprueba si el archivo proviene de una subida HTTP legítima.
     * En pruebas automatizadas, una clase de prueba puede sobrescribir este método.
     */
    protected function esArchivoSubido(string $tmpPath): bool
    {
        return is_uploaded_file($tmpPath) || (php_sapi_name() === 'cli' && is_file($tmpPath));
    }
}
