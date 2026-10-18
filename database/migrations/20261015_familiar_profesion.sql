-- Nivel de instrucción fuera, Profesión en su lugar.
--
-- El familiar llevaba dos campos: "Nivel de instrucción" (catálogo `nivel_instruccion`) y
-- "Ocupación" (catálogo `ocupacion`). Se elimina el primero y el segundo pasa a llamarse
-- "Profesión" en pantalla.
--
-- POR QUÉ SE COPIA EL ID Y NO EL TEXTO:
--
-- La migración copia `id_ocupacion` tal cual a `id_profesion`, sin pasar por el nombre. Un
-- cambio de catálogo ("Ama de Casa" → "Amas de casa") actualiza las 46 filas una sola vez y
-- todos los familiares quedan al día; si se guardara el texto, esos cambios no llegarían a
-- los familiares ya registrados y el mismo oficio aparecería escrito de dos formas.
--
-- LA TABLA `ocupacion` SE QUEDA:
--
-- Es el catálogo que sigue alimentando el <select> de Profesión, así que no se borra: se
-- cambia lo que se ve, no dónde viven los 46 oficios. Lo que sí se borra es
-- `nivel_instruccion`, que se queda sin ningún consumidor en todo el sistema —el empleado
-- usa `cargo` y `turno`, no estos catálogos— y cuya llave foránea desaparece con la columna.
--
-- ATENCIÓN AL NOMBRE:
--
-- La tabla sigue llamándose `ocupacion` y su columna `id_ocupacion`, mientras que en
-- `persona_estudiante` la columna nueva se llama `id_profesion`. Es a propósito: el
-- catálogo es histórico y renombrarlo obligaría a tocar su modelo y todas las consultas que
-- lo leen, a cambio de nada. Lo que cambia es lo que la secretaria ve y lo que guarda el
-- familiar.
--
-- Es idempotente: se puede ejecutar más de una vez sin romper nada.

-- 1) La columna nueva, con los mismos datos de la vieja.
SET @tieneProfesion = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'persona_estudiante'
       AND COLUMN_NAME = 'id_profesion'
);
SET @sql = IF(@tieneProfesion = 0,
    'ALTER TABLE persona_estudiante ADD COLUMN id_profesion INT(11) NULL DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tieneOcupacion = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'persona_estudiante'
       AND COLUMN_NAME = 'id_ocupacion'
);
-- OJO: @tieneProfesion se vuelve a leer AQUÍ, no se reutiliza el valor de arriba.
-- Esa variable se calculó antes del ALTER TABLE, así que en una base donde la columna
-- acababa de crearse valía 0, la condición de la copia daba falsa y los 13 familiares
-- perdían la ocupación. Las variables de MySQL guardan el valor con que se asignaron:
-- para ver el estado actual hay que volver a consultar.
SET @tieneProfesionAhora = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'persona_estudiante'
       AND COLUMN_NAME = 'id_profesion'
);
SET @sql = IF(@tieneOcupacion = 1 AND @tieneProfesionAhora = 1,
    'UPDATE persona_estudiante
        SET id_profesion = id_ocupacion
      WHERE id_profesion IS NULL AND id_ocupacion IS NOT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) El índice de la columna nueva. La llave foránea también necesita índice, pero se
--    nombra explícitamente para que el nombre sea estable entre instalaciones.
SET @tieneIdx = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'persona_estudiante'
       AND INDEX_NAME = 'idx_relacion_profesion'
);
SET @sql = IF(@tieneIdx = 0 AND @tieneProfesionAhora = 1,
    'ALTER TABLE persona_estudiante ADD KEY idx_relacion_profesion (id_profesion)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tieneFk = (
    SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'persona_estudiante'
       AND REFERENCED_TABLE_NAME = 'ocupacion' AND REFERENCED_COLUMN_NAME = 'id_ocupacion'
);
SET @sql = IF(@tieneFk = 0 AND @tieneProfesionAhora = 1,
    'ALTER TABLE persona_estudiante
        ADD CONSTRAINT fk_relacion_profesion
        FOREIGN KEY (id_profesion) REFERENCES ocupacion (id_ocupacion)
        ON UPDATE CASCADE ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3) Fuera la llave, el índice y la columna del nivel de instrucción. En ese orden: la
--    llave primero porque el índice y la columna no se pueden quitar con una FK encima.
--    Se busca la llave en KEY_COLUMN_USAGE y no en TABLE_CONSTRAINTS porque
--    REFERENCED_TABLE_NAME vive en la primera.
SET @fkNivel = (
    SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'persona_estudiante'
       AND CONSTRAINT_NAME = 'fk_relacion_nivel_instruccion'
);
SET @sql = IF(@fkNivel > 0,
    'ALTER TABLE persona_estudiante DROP FOREIGN KEY fk_relacion_nivel_instruccion', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idxNivel = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'persona_estudiante'
       AND INDEX_NAME = 'idx_relacion_nivel_instruccion'
);
SET @sql = IF(@idxNivel > 0,
    'ALTER TABLE persona_estudiante DROP KEY idx_relacion_nivel_instruccion', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @colNivel = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'persona_estudiante'
       AND COLUMN_NAME = 'id_nivel_instruccion'
);
SET @sql = IF(@colNivel > 0,
    'ALTER TABLE persona_estudiante DROP COLUMN id_nivel_instruccion', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4) Fuera la llave, el índice y la columna viejos. En ese orden, por la misma
--    razón que el nivel: la llave sostiene el índice, y el índice no se puede
--    quitar con una llave encima (error 1553).
SET @fkOcupacion = (
    SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'persona_estudiante'
       AND CONSTRAINT_NAME = 'fk_relacion_ocupacion'
);
SET @sql = IF(@fkOcupacion > 0,
    'ALTER TABLE persona_estudiante DROP FOREIGN KEY fk_relacion_ocupacion', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idxOcupacion = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'persona_estudiante'
       AND INDEX_NAME = 'idx_relacion_ocupacion'
);
SET @sql = IF(@idxOcupacion > 0,
    'ALTER TABLE persona_estudiante DROP KEY idx_relacion_ocupacion', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @colOcupacion = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'persona_estudiante'
       AND COLUMN_NAME = 'id_ocupacion'
);
SET @sql = IF(@colOcupacion > 0,
    'ALTER TABLE persona_estudiante DROP COLUMN id_ocupacion', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 5) El catálogo del nivel ya no lo usa nadie.
SET @tieneTablaNivel = (
    SELECT COUNT(*) FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nivel_instruccion'
);
SET @sql = IF(@tieneTablaNivel > 0, 'DROP TABLE nivel_instruccion', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 6) Comprobación de salida. Las dos cuentas tienen que dar lo mismo: si no, algún
--    familiar perdió su ocupación al renombrar la columna.
SELECT CONCAT('  familiares con profesión: ', (SELECT COUNT(*) FROM persona_estudiante WHERE id_profesion IS NOT NULL)) AS aviso
UNION ALL
SELECT CONCAT('  nivel_instruccion ya no existe: ',
              IF((SELECT COUNT(*) FROM information_schema.TABLES
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nivel_instruccion') = 0, 'sí', 'NO'));