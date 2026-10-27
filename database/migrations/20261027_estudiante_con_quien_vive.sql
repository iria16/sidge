-- Con quién vive el estudiante.
--
-- Es un dato del ESTUDIANTE, no de cada familiar: la pregunta es una sola por
-- alumno y no cambia según cuántos representantes tenga registrados.
--
-- La columna es NULL a propósito. Los estudiantes que ya existen no tienen el
-- dato y forzar NOT NULL obligaría a inventarles un valor o a bloquear
-- cualquier guardado de su expediente. El formulario de inscripción sí lo
-- exige; la ratificación lo deja opcional para no romper los expedientes
-- anteriores.
--
-- Es idempotente: se puede ejecutar más de una vez sin romper nada.

SET @existe = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'estudiante'
       AND COLUMN_NAME = 'con_quien_vive'
);
SET @sql = IF(@existe = 0,
    "ALTER TABLE estudiante
        ADD COLUMN con_quien_vive ENUM(
            'ambos_padres',
            'solo_madre',
            'solo_padre',
            'otro_familiar'
        ) NULL DEFAULT NULL AFTER id_persona",
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
