-- Configuración institucional y directiva de la institución.
--
-- Almacena los datos centrales que deben reflejarse de manera uniforme y
-- simultánea en todos los reportes, constancias, membretes, login y barras
-- de navegación del sistema SIGDE:
--
-- 1. Nombre oficial del plantel, código DEA y logo institucional.
-- 2. Membrete oficial del Ministerio del Poder Popular para la Educación y logo.
-- 3. Nombre, apellido, cédula y cargo del Director(a) del plantel.
-- 4. Rutas en el servidor de la firma digitalizada y el sello húmedo (guardados
--    fuera del DocumentRoot público para evitar accesos no autorizados por URL).
--
-- REGLAS DE NEGOCIO:
-- - Los datos del director (nombre, cédula, cargo) se inicializan VACÍOS a propósito.
--   El sistema NO permite emitir constancias de estudio ni de inscripción mientras
--   estos datos no hayan sido formalmente configurados por un directivo.
-- - La firma y el sello se guardan fuera de `public/` y se leen solo en el servidor.
-- - No se manejan versiones históricas: todos los reportes y constancias usan siempre
--   los datos vigentes configurados en esta tabla.
--
-- COLLATION: utf8mb4_uca1400_ai_ci, idéntica a la colación real de la base de datos `sidge`
-- (verificada con SELECT TABLE_COLLATION FROM information_schema.TABLES).
--
-- Idempotente: se puede ejecutar múltiples veces sin alterar los datos ya personalizados.

CREATE TABLE IF NOT EXISTS configuracion_institucional (
    clave               VARCHAR(50)  NOT NULL,
    valor               TEXT         NOT NULL,
    descripcion         VARCHAR(255) NULL DEFAULT NULL,
    fecha_actualizacion DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    actualizado_por     INT(11)      NULL DEFAULT NULL,
    PRIMARY KEY (clave),
    CONSTRAINT fk_config_usuario FOREIGN KEY (actualizado_por)
        REFERENCES usuario (id_usuario) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- Carga inicial de claves oficiales. Si la clave ya existe (por haber sido personalizada
-- por el usuario), NO se sobreescribe el valor personalizado.
INSERT INTO configuracion_institucional (clave, valor, descripcion)
VALUES
    ('ministerio_linea_1',   'REPÚBLICA BOLIVARIANA DE VENEZUELA',                                 'Primera línea del membrete oficial del ministerio'),
    ('ministerio_linea_2',   'MINISTERIO DEL PODER POPULAR PARA LA EDUCACIÓN',                     'Segunda línea del membrete oficial del ministerio'),
    ('ministerio_logo',      '/assets/img/ministerio_logo_bandera.png',                             'Ruta pública del logo oficial del ministerio'),
    ('plantel_nombre',       'ESCUELA BÁSICA ISIDRO RAMÍREZ',                                      'Nombre oficial completo de la institución'),
    ('plantel_nombre_corto', 'E.B. “Isidro Ramírez”',                                              'Nombre abreviado para menús y pies de página'),
    ('plantel_codigo_dea',   'OD05750814',                                                         'Código DEA oficial del plantel'),
    ('plantel_logo',         '/assets/img/logo.png',                                               'Ruta pública del escudo/logo del plantel'),
    ('director_nombre',      '',                                                                   'Nombres y apellidos del Director(a) titular o encargado'),
    ('director_cedula',      '',                                                                   'Cédula de identidad del Director(a) con prefijo (V/E)'),
    ('director_cargo',       '',                                                                   'Denominación exacta del cargo del directivo (ej. Director (E))'),
    ('director_firma',       '',                                                                   'Nombre de archivo de la firma en almacenamiento privado'),
    ('institucion_sello',    '',                                                                   'Nombre de archivo del sello húmedo en almacenamiento privado')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion);

SELECT CONCAT('  configuracion_institucional lista con ', COUNT(*), ' claves registradas.') AS resultado
  FROM configuracion_institucional;
