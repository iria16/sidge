-- ============================================================================
-- MIGRACIÓN: Gestión de Resultado Académico Anual (Promoción y No Promoción)
-- Archivo: database/migrations/20261019_resultado_academico_promocion.sql
-- ============================================================================

CREATE TABLE IF NOT EXISTS `resultado_academico` (
  `id_resultado` INT(11) NOT NULL AUTO_INCREMENT,
  `id_estudiante` INT(11) NOT NULL,
  `id_anio_escolar` INT(11) NOT NULL,
  `id_estructura_academica` INT(11) NOT NULL,
  `id_matricula` INT(11) NOT NULL,
  `literal` ENUM('A', 'B', 'C', 'D', 'E') NULL DEFAULT NULL COMMENT 'Calificación cualitativa oficial de Educación Primaria',
  `estado_resultado` ENUM('PENDIENTE', 'PROMOVIDO', 'NO_PROMOVIDO') NOT NULL DEFAULT 'PENDIENTE' COMMENT 'Estado del resultado anual',
  `id_grado_destino` INT(11) NULL DEFAULT NULL COMMENT 'Grado sugerido para la siguiente matrícula (siguiente si promovido, mismo si no)',
  `observaciones` TEXT NULL DEFAULT NULL COMMENT 'Observaciones pedagógicas del docente o directivo',
  
  -- Auditoría y trazabilidad en dos fases (Docente propone / Directivo confirma)
  `propuesto_por` INT(11) NULL DEFAULT NULL COMMENT 'Usuario del docente que cargó la propuesta',
  `fecha_propuesta` DATETIME NULL DEFAULT NULL,
  `confirmado_por` INT(11) NULL DEFAULT NULL COMMENT 'Usuario del directivo que validó y confirmó',
  `fecha_confirmacion` DATETIME NULL DEFAULT NULL,
  
  `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id_resultado`),
  UNIQUE KEY `uk_resultado_estudiante_anio` (`id_estudiante`, `id_anio_escolar`),
  KEY `idx_resultado_estructura` (`id_estructura_academica`),
  KEY `idx_resultado_matricula` (`id_matricula`),
  KEY `idx_resultado_estado` (`estado_resultado`),

  CONSTRAINT `fk_res_acad_estudiante` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiante` (`id_estudiante`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_res_acad_anio` FOREIGN KEY (`id_anio_escolar`) REFERENCES `anio_escolar` (`id_anio_escolar`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_res_acad_estructura` FOREIGN KEY (`id_estructura_academica`) REFERENCES `estructura_academica` (`id_estructura_academica`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_res_acad_matricula` FOREIGN KEY (`id_matricula`) REFERENCES `matricula` (`id_matricula`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_res_acad_grado_destino` FOREIGN KEY (`id_grado_destino`) REFERENCES `grado` (`id_grado`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_res_acad_propuesto` FOREIGN KEY (`propuesto_por`) REFERENCES `usuario` (`id_usuario`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_res_acad_confirmado` FOREIGN KEY (`confirmado_por`) REFERENCES `usuario` (`id_usuario`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
