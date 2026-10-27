-- Retiro de estudiante.con_quien_vive.
--
-- El dato ya existe en persona_estudiante.vive_con_estudiante y ahora se
-- deriva desde el núcleo familiar. Mantener la columna crearía dos fuentes
-- del mismo dato.
--
-- Es idempotente: si la columna no existe, no hace nada. Si existe y tiene
-- algún valor no nulo, se detiene en vez de borrar información sin avisar.

SET @existe = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'estudiante'
       AND COLUMN_NAME = 'con_quien_vive'
);

SET @sql = IF(@existe = 0,
    'DO 0',
    'SELECT COUNT(*) INTO @no_nulos FROM estudiante WHERE con_quien_vive IS NOT NULL');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = CASE
    WHEN @existe = 0 THEN 'DO 0'
    WHEN @no_nulos > 0 THEN
        'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT = ''No se puede retirar con_quien_vive: hay valores no nulos'''
    ELSE 'ALTER TABLE `estudiante` DROP COLUMN `con_quien_vive`'
END;
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
