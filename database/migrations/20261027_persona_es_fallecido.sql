-- Estado vital de la persona.
--
-- `persona_estudiante.estado_vital` describía el estado físico dentro de una
-- relación alumno-familiar, pero el fallecimiento es un atributo de la persona:
-- la misma madre puede ser familiar de dos alumnos y no puede estar viva en una
-- fila y fallecida en la otra. La fuente única pasa a ser `persona.es_fallecido`.
--
-- La columna antigua NO se elimina en esta migración: se conserva como dato
-- histórico, pero el código deja de leerla y de escribirla. Así el cambio no
-- borra información sin una decisión explícita.
--
-- Es idempotente: si la columna ya existe, no la vuelve a crear; si la columna
-- antigua no existe, no intenta rellenar desde ella.

SET @existe_es_fallecido = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'persona'
       AND COLUMN_NAME = 'es_fallecido'
);

SET @sql = IF(@existe_es_fallecido = 0,
    'ALTER TABLE `persona` ADD COLUMN `es_fallecido` TINYINT(1) NOT NULL DEFAULT 0 AFTER `id_nacionalidad`',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @existe_estado_vital = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'persona_estudiante'
       AND COLUMN_NAME = 'estado_vital'
);

SET @sql = IF(@existe_estado_vital = 0,
    'DO 0',
    'UPDATE `persona` p
       INNER JOIN (
           SELECT id_persona, MAX(estado_vital = ''FALLECIDO'') AS fallecido
             FROM `persona_estudiante`
            GROUP BY id_persona
       ) v ON v.id_persona = p.id_persona
        SET p.es_fallecido = IF(v.fallecido = 1, 1, p.es_fallecido)');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
