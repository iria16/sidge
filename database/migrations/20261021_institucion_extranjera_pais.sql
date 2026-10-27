-- Migración: Soporte para instituciones extranjeras y catálogo de países
-- Fecha: 2026-10-21
--
-- Reglas:
--  1. Tabla `pais`: catálogo de países para instituciones extranjeras.
--  2. `institucion`:
--     - `es_extranjera`: booleano (tinyint 1) por defecto 0.
--     - `pais_id`: foreign key a `pais.id_pais` nullable (solo requerido si es extranjera).
--     - `direccion_id`: nullable (las instituciones extranjeras no requieren registro en direccion).
--     - `codigo_dea`: varchar(20) nullable.
--     - `id_estado_geo`, `id_municipio`, `id_parroquia`: ubicación nacional nullable.
--
-- Es idempotente: se puede ejecutar múltiples veces sin error.

-- 1) Tabla `pais`
CREATE TABLE IF NOT EXISTS `pais` (
  `id_pais` INT(11) NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(80) NOT NULL,
  `estado` ENUM('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  PRIMARY KEY (`id_pais`),
  UNIQUE KEY `ux_pais_nombre` (`nombre`),
  KEY `idx_pais_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- Cargar lista inicial de países si la tabla está vacía
INSERT IGNORE INTO `pais` (`nombre`) VALUES
('Venezuela'),
('Colombia'),
('Ecuador'),
('Perú'),
('Chile'),
('Argentina'),
('Brasil'),
('Bolivia'),
('Uruguay'),
('Paraguay'),
('Panamá'),
('Costa Rica'),
('Nicaragua'),
('Honduras'),
('El Salvador'),
('Guatemala'),
('México'),
('Cuba'),
('República Dominicana'),
('Puerto Rico'),
('España'),
('Estados Unidos'),
('Canadá'),
('Portugal'),
('Italia'),
('Francia'),
('Alemania'),
('Reino Unido'),
('China'),
('Japón');

-- 2) Columnas en `institucion`
-- es_extranjera
SET @tieneEsExtranjera = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'institucion' AND COLUMN_NAME = 'es_extranjera'
);
SET @sql = IF(@tieneEsExtranjera = 0,
    'ALTER TABLE institucion ADD COLUMN es_extranjera TINYINT(1) NOT NULL DEFAULT 0 AFTER codigo_dea', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- pais_id
SET @tienePaisId = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'institucion' AND COLUMN_NAME = 'pais_id'
);
SET @sql = IF(@tienePaisId = 0,
    'ALTER TABLE institucion ADD COLUMN pais_id INT(11) NULL DEFAULT NULL AFTER es_extranjera', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Llave foránea fk_institucion_pais
SET @tieneFkPais = (
    SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'institucion'
       AND REFERENCED_TABLE_NAME = 'pais'
       AND REFERENCED_COLUMN_NAME = 'id_pais'
);
SET @sql = IF(@tieneFkPais = 0,
    'ALTER TABLE institucion ADD CONSTRAINT fk_institucion_pais FOREIGN KEY (pais_id) REFERENCES pais (id_pais) ON UPDATE CASCADE ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- direccion_id: asegurar que exista y sea nullable
SET @tieneDireccionId = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'institucion' AND COLUMN_NAME = 'direccion_id'
);
SET @sql = IF(@tieneDireccionId = 0,
    'ALTER TABLE institucion ADD COLUMN direccion_id INT(11) NULL DEFAULT NULL',
    'ALTER TABLE institucion MODIFY COLUMN direccion_id INT(11) NULL DEFAULT NULL');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- id_tipo_institucion: asegurar que admita NULL o default para evitar bloqueos si no se provee
SET @tieneTipoInst = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'institucion' AND COLUMN_NAME = 'id_tipo_institucion'
);
SET @sql = IF(@tieneTipoInst > 0,
    'ALTER TABLE institucion MODIFY COLUMN id_tipo_institucion INT(11) NULL DEFAULT 8', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
