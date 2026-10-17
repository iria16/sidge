-- Enlace de invitación al grupo de WhatsApp de representantes por sección (estructura_academica).
--
-- Se guarda en `estructura_academica` (año + grado + sección + turno) y no en `seccion`
-- (que es solo el catálogo de letras A, B, C) ni en `asignacion_docente` (para que un
-- cambio de docente durante el año no pierda el grupo de los representantes, y para que
-- cada nuevo período escolar nazca con `enlace_whatsapp = NULL`).
--
-- Es idempotente: se puede ejecutar más de una vez sin romper nada.

SET @tieneEnlaceWa = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'estructura_academica'
       AND COLUMN_NAME = 'enlace_whatsapp'
);
SET @sql = IF(@tieneEnlaceWa = 0,
    'ALTER TABLE estructura_academica ADD COLUMN enlace_whatsapp VARCHAR(120) NULL DEFAULT NULL AFTER capacidad_maxima',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Comprobación de salida
SELECT CONCAT('  estructura_academica.enlace_whatsapp existe: ',
              IF((SELECT COUNT(*) FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE()
                     AND TABLE_NAME = 'estructura_academica'
                     AND COLUMN_NAME = 'enlace_whatsapp') > 0, 'sí', 'NO')) AS aviso;
