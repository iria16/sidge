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
        'plantel_cintillo',
    ];

    /**
     * Retorna todas las configuraciones indexadas por clave.
     *
     * @return array<string, string>
     */
    public function getAll(array $filters = [], int $page = 1, int $perPage = 100): array
    {
        $stmt = $this->pdo->query('SELECT clave, valor FROM configuracion_institucional');
        $resultado = [];
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $resultado[(string) $fila['clave']] = (string) ($fila['valor'] ?? '');
        }
        return $resultado;
    }

    /**
     * Obtiene el valor de una clave individual.
     */
    public function get(string $clave, string $default = ''): string
    {
        $stmt = $this->pdo->prepare('SELECT valor FROM configuracion_institucional WHERE clave = :clave LIMIT 1');
        $stmt->execute(['clave' => $clave]);
        $val = $stmt->fetchColumn();

        return ($val !== false && trim((string) $val) !== '') ? (string) $val : $default;
    }

    /**
     * Actualiza masivamente los campos de texto dentro de una transacción con
     * INSERT ... ON DUPLICATE KEY UPDATE.
     *
     * @param array<string, string> $datos Arreglo asociativo [clave => valor]
     * @param int $idUsuario ID del administrador que realiza la modificación.
     * @return array{ok: bool, mensaje: string, errores?: array<string, string>}
     */
    public function guardarTextos(array $datos, int $idUsuario): array
    {
        $errores = [];
        $limpios = [];

        foreach (self::CLAVES_TEXTO as $clave) {
            $raw = (string) ($datos[$clave] ?? '');
            $valor = preg_replace('/\s+/u', ' ', trim(strip_tags($raw))) ?? '';

            // Validar obligatoriedad
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

            // Validaciones de longitud según el tipo de campo
            $limite = match ($clave) {
                'plantel_codigo_dea'   => 50,
                'director_cedula'      => 25,
                'plantel_nombre_corto' => 100,
                default                => 200,
            };

            // Validación específica de formato de cédula del director si viene indicada
            if ($clave === 'director_cedula' && $valor !== '') {
                $digitos = preg_replace('/\D/', '', $valor);
                $letra = strtoupper(substr(trim($valor), 0, 1));
                if (!in_array($letra, ['V', 'E'], true) || strlen($digitos) < 6 || strlen($digitos) > 9) {
                    $errores['director_cedula'] = 'La cédula del director debe tener un formato válido (V o E seguida de 6 a 9 dígitos, ej. V-12.345.678).';
                    continue;
                }
            }

            $limpios[$clave] = $valor;
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
            $bitacora = new BitacoraModel($this->pdo);
            $bitacoraOk = $bitacora->registrar(
                $idUsuario,
                'ACTUALIZAR CONFIGURACIÓN INSTITUCIONAL · Datos del plantel y directiva',
                'Configuración'
            );
            if (!$bitacoraOk) {
                throw new \RuntimeException('No se pudo registrar la auditoría en la bitácora.');
            }

            $this->pdo->commit();

            // Invalidar la caché en memoria del helper global
            Configuracion::invalidateCache();

            return [
                'ok'      => true,
                'mensaje' => 'Configuración institucional actualizada correctamente.',
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
        $esFirmaOSello = in_array($clave, ['director_firma', 'institucion_sello'], true);
        if ($esFirmaOSello && $mime !== 'image/png') {
            return ['ok' => false, 'mensaje' => 'La firma y el sello deben estar en formato PNG con fondo transparente.'];
        }

        // Logos: PNG o JPG
        if (!$esFirmaOSello && !in_array($mime, ['image/png', 'image/jpeg'], true)) {
            return ['ok' => false, 'mensaje' => 'Formato no admitido. Solo se permiten imágenes PNG y JPG.'];
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
            $subcarpeta = ($clave === 'director_firma') ? 'firma' : 'sello';
            $directorioDestino = $projectRoot . DIRECTORY_SEPARATOR . 'storage'
                . DIRECTORY_SEPARATOR . 'institucion' . DIRECTORY_SEPARATOR . $subcarpeta;
        } else {
            $directorioDestino = $projectRoot . DIRECTORY_SEPARATOR . 'public'
                . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'institucion';
        }

        if (!is_dir($directorioDestino) && !@mkdir($directorioDestino, 0755, true) && !is_dir($directorioDestino)) {
            return ['ok' => false, 'mensaje' => 'No se pudo crear el directorio de almacenamiento en el servidor.'];
        }

        // 6. Generar nombre de archivo único e inalterable
        $extension = ($mime === 'image/png') ? 'png' : 'jpg';
        $nombreArchivo = bin2hex(random_bytes(16)) . '.' . $extension;
        $rutaCompleta = $directorioDestino . DIRECTORY_SEPARATOR . $nombreArchivo;

        // 7. Re-codificación y reducción de escala con GD (máximo ~1000 px por lado largo)
        $gdOk = false;
        try {
            $recursoOriginal = ($mime === 'image/png')
                ? @imagecreatefrompng($tmpPath)
                : @imagecreatefromjpeg($tmpPath);

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
                    if ($mime === 'image/png') {
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
                if ($mime === 'image/png') {
                    imagealphablending($recursoOriginal, false);
                    imagesavealpha($recursoOriginal, true);
                }
            }

            // Guardar imagen re-codificada
            if ($mime === 'image/png') {
                $gdOk = imagepng($recursoOriginal, $rutaCompleta, 8);
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
                'ministerio_logo'   => 'Logo del Ministerio',
                'plantel_logo'      => 'Logo del Plantel',
                'director_firma'    => 'Firma del Director',
                'institucion_sello' => 'Sello Institucional',
                'plantel_cintillo'  => 'Franja Tricolor',
                default             => $clave,
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
                'mensaje'   => "Se ha actualizado {$nombreLegible} correctamente.",
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
                    'mensaje'   => "Se ha actualizado {$nombreLegible} correctamente.",
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
                'plantel_cintillo' => 'La franja tricolor ha sido retirada correctamente. Las constancias se emitirán sin cintillo inferior.',
                'director_firma'   => 'La firma digitalizada ha sido retirada correctamente.',
                'institucion_sello' => 'El sello institucional ha sido retirado correctamente.',
                default            => 'El logotipo ha sido restablecido a su versión predeterminada oficial.',
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

        if (in_array($clave, ['director_firma', 'institucion_sello'], true)) {
            $subcarpeta = ($clave === 'director_firma') ? 'firma' : 'sello';
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
        return is_uploaded_file($tmpPath);
    }
}
