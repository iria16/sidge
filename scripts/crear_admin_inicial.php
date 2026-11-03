<?php

declare(strict_types=1);

use App\Config\Database;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if (!function_exists('stream_isatty') || !stream_isatty(STDIN) || !stream_isatty(STDOUT)) {
    fwrite(STDERR, "Ejecuta este proceso en una terminal interactiva; no redirijas su salida.\n");
    exit(1);
}

require_once __DIR__ . '/../app/Config/Env.php';
require_once __DIR__ . '/../app/Config/Database.php';

/**
 * Solicita un dato sin mostrar información personal en pantalla.
 */
function solicitarDato(string $etiqueta): string
{
    fwrite(STDOUT, $etiqueta . ': ');
    $valor = fgets(STDIN);

    if ($valor === false) {
        throw new RuntimeException('No se pudo leer la entrada de la terminal.');
    }

    return trim($valor);
}

function validarNombre(string $nombre, string $campo): string
{
    if (mb_strlen($nombre, 'UTF-8') > 50
        || !preg_match("/^[\\p{L}\\p{M}][\\p{L}\\p{M} '-]*$/u", $nombre)) {
        throw new RuntimeException("El {$campo} debe contener solo letras, espacios, apóstrofos o guiones (máximo 50 caracteres).");
    }

    return $nombre;
}

function generarClaveProvisional(): string
{
    $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    $clave = '';

    for ($i = 0; $i < 24; $i++) {
        $clave .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
    }

    return $clave;
}

function crearAdministradorInicial(): void
{
    $tipoDocumento = strtoupper(solicitarDato('Tipo de documento (V/E/CE/P)'));
    if (!in_array($tipoDocumento, ['V', 'E', 'CE', 'P'], true)) {
        throw new RuntimeException('El tipo de documento debe ser V, E, CE o P.');
    }

    $numeroDocumento = solicitarDato('Número de documento');
    if (!preg_match('/^[A-Za-z0-9-]{3,20}$/', $numeroDocumento)) {
        throw new RuntimeException('El número de documento debe tener entre 3 y 20 letras, números o guiones.');
    }

    $primerNombre = validarNombre(solicitarDato('Primer nombre'), 'primer nombre');
    $primerApellido = validarNombre(solicitarDato('Primer apellido'), 'primer apellido');

    $fechaNacimiento = solicitarDato('Fecha de nacimiento (AAAA-MM-DD)');
    $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $fechaNacimiento);
    $erroresFecha = DateTimeImmutable::getLastErrors();
    if ($fecha === false
        || $fecha->format('Y-m-d') !== $fechaNacimiento
        || ($erroresFecha !== false && ($erroresFecha['warning_count'] > 0 || $erroresFecha['error_count'] > 0))) {
        throw new RuntimeException('La fecha debe ser válida y tener el formato AAAA-MM-DD.');
    }

    $sexo = strtoupper(solicitarDato('Sexo registrado (F/M)'));
    if (!in_array($sexo, ['F', 'M'], true)) {
        throw new RuntimeException('El sexo registrado debe ser F o M.');
    }

    $nombreUsuario = strtolower(solicitarDato('Nombre de usuario (letras minúsculas, números, punto o guion bajo)'));
    if (!preg_match('/^[a-z0-9._]{3,50}$/', $nombreUsuario)) {
        throw new RuntimeException('El usuario debe tener entre 3 y 50 caracteres: letras minúsculas, números, punto o guion bajo.');
    }

    $pdo = (new Database())->getConnection();
    $lock = $pdo->query("SELECT GET_LOCK('sidge_bootstrap_initial_admin', 10)")->fetchColumn();
    if ((int) $lock !== 1) {
        throw new RuntimeException('No se pudo reservar el proceso de creación inicial. Inténtalo nuevamente.');
    }

    try {
        $rolStmt = $pdo->prepare(
            "SELECT id_rol FROM rol WHERE LOWER(nombre) = 'admin' AND estado = 'ACTIVO' LIMIT 1"
        );
        $rolStmt->execute();
        $rolId = $rolStmt->fetchColumn();
        if ($rolId === false) {
            throw new RuntimeException('No existe el rol Admin activo. Importa primero database/schema_actual.sql.');
        }

        $pdo->beginTransaction();

        if ((int) $pdo->query('SELECT COUNT(*) FROM usuario')->fetchColumn() !== 0) {
            throw new RuntimeException('Ya hay cuentas en esta base de datos; el alta inicial solo se permite cuando no existe ninguna.');
        }

        $personaStmt = $pdo->prepare(
            'INSERT INTO persona
                (tipo_documento, numero_documento, fecha_nacimiento, primer_nombre, primer_apellido, sexo)
             VALUES
                (:tipo_documento, :numero_documento, :fecha_nacimiento, :primer_nombre, :primer_apellido, :sexo)'
        );
        $personaStmt->execute([
            'tipo_documento' => $tipoDocumento,
            'numero_documento' => $numeroDocumento,
            'fecha_nacimiento' => $fechaNacimiento,
            'primer_nombre' => $primerNombre,
            'primer_apellido' => $primerApellido,
            'sexo' => $sexo,
        ]);
        $personaId = (int) $pdo->lastInsertId();

        $claveProvisional = generarClaveProvisional();
        $usuarioStmt = $pdo->prepare(
            "INSERT INTO usuario
                (id_persona, id_rol, nombre_usuario, password_hash, password_provisional, estado)
             VALUES
                (:persona_id, :rol_id, :nombre_usuario, :password_hash, 1, 'ACTIVO')"
        );
        $usuarioStmt->execute([
            'persona_id' => $personaId,
            'rol_id' => (int) $rolId,
            'nombre_usuario' => $nombreUsuario,
            'password_hash' => password_hash($claveProvisional, PASSWORD_DEFAULT),
        ]);

        $pdo->commit();

        fwrite(STDOUT, "\nAdministrador inicial creado.\n");
        fwrite(STDOUT, "Usuario: {$nombreUsuario}\n");
        fwrite(STDOUT, "Clave provisional (se muestra una sola vez): {$claveProvisional}\n");
        fwrite(STDOUT, "Guárdala en un gestor de contraseñas. El primer acceso obligará a cambiarla.\n");
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('sidge_bootstrap_initial_admin')");
    }
}

try {
    fwrite(STDOUT, "Creación de la cuenta administradora inicial de SIGDE\n");
    fwrite(STDOUT, "Los datos se guardan en la base configurada; la clave provisional no se solicita ni se reutiliza.\n\n");
    crearAdministradorInicial();
} catch (Throwable $e) {
    fwrite(STDERR, "\nNo se creó la cuenta: {$e->getMessage()}\n");
    exit(1);
}
