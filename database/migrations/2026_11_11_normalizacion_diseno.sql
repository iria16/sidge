-- ===========================================================================
-- SIGDE - Ajustes de diseño verificados
-- Fecha: 2026-11-11
-- Base: bd_sigde (MariaDB 11.x)
--
-- Esta migración NO elimina datos. Corrige redundancias y separa conceptos:
--   1. Separa el motivo de egreso del motivo de salida de la institución previa.
--   2. Normaliza el grado de procedencia con una FK al catálogo grado.
--   3. Valida el formato del año escolar de procedencia.
--   4. Unifica el nombre id_institucion -> id_institucion_procedencia.
--   5. Unifica fecha_egreso a DATE.
--   6. Completa reingreso con estado, usuario de registro y fecha de resolución.
--   7. Agrega disparadores que impiden contradicciones entre matricula e inscripcion.
--
-- Ejecutar una sola vez sobre una base existente. Para instalaciones nuevas,
-- usar los scripts schema_actual.sql / schema_infinityfree.sql ya actualizados.
-- ===========================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- 1. Motivo de egreso: la tabla egreso es su lugar, no inscripcion
-- ---------------------------------------------------------------------------
ALTER TABLE `egreso`
  ADD COLUMN `motivo_egreso` varchar(200) DEFAULT NULL AFTER `id_matricula`;

UPDATE `egreso`
   SET `motivo_egreso` = 'Culminación exitosa'
 WHERE `motivo_egreso` IS NULL;

ALTER TABLE `inscripcion`
  CHANGE COLUMN `motivo_egreso` `motivo_salida_institucion_previa` varchar(100) DEFAULT NULL;

-- Los egresos anteriores guardaban el motivo en inscripcion. Se limpia para que
-- esa columna vuelva a significar solo "por qué salió de la institución previa".
UPDATE `inscripcion`
   SET `motivo_salida_institucion_previa` = NULL
 WHERE `motivo_salida_institucion_previa` = 'Culminación exitosa';

-- ---------------------------------------------------------------------------
-- 2. Grado de procedencia normalizado (FK al catálogo grado)
-- ---------------------------------------------------------------------------
ALTER TABLE `inscripcion`
  ADD COLUMN `id_grado_procedencia` int(11) DEFAULT NULL AFTER `ult_grado_cursado`;

UPDATE `inscripcion` i
  JOIN `grado` g ON g.`nombre` = i.`ult_grado_cursado`
   SET i.`id_grado_procedencia` = g.`id_grado`;

ALTER TABLE `inscripcion`
  ADD KEY `idx_inscripcion_grado_procedencia` (`id_grado_procedencia`),
  ADD CONSTRAINT `fk_inscripcion_grado_procedencia`
      FOREIGN KEY (`id_grado_procedencia`) REFERENCES `grado` (`id_grado`)
      ON DELETE SET NULL ON UPDATE CASCADE;

-- ---------------------------------------------------------------------------
-- 3. Formato del año escolar de procedencia
-- ---------------------------------------------------------------------------
ALTER TABLE `inscripcion`
  ADD CONSTRAINT `ck_inscripcion_anio_pre`
      CHECK (`anio_escolar_pre` IS NULL OR `anio_escolar_pre` REGEXP '^[0-9]{4}-[0-9]{4}$');

-- ---------------------------------------------------------------------------
-- 4. Nombre unificado de la institución de procedencia
-- ---------------------------------------------------------------------------
ALTER TABLE `inscripcion`
  CHANGE COLUMN `id_institucion` `id_institucion_procedencia` int(11) NOT NULL;

ALTER TABLE `inscripcion`
  DROP KEY `idx_inscripcion_institucion`,
  ADD KEY `idx_inscripcion_institucion_procedencia` (`id_institucion_procedencia`);

-- ---------------------------------------------------------------------------
-- 5. fecha_egreso: solo interesa el día, igual que retiro y reingreso
-- ---------------------------------------------------------------------------
ALTER TABLE `egreso`
  MODIFY COLUMN `fecha_egreso` date DEFAULT NULL;

-- ---------------------------------------------------------------------------
-- 6. reingreso: paridad mínima con retiro
-- ---------------------------------------------------------------------------
ALTER TABLE `reingreso`
  ADD COLUMN `id_usuario_registro` int(11) DEFAULT NULL AFTER `id_usuario_resolucion`,
  ADD COLUMN `estado_solicitud` enum('PENDIENTE','APROBADO','RECHAZADO') NOT NULL DEFAULT 'APROBADO' AFTER `id_usuario_registro`,
  ADD COLUMN `fecha_resolucion` datetime DEFAULT NULL AFTER `fecha_reincorporacion`;

UPDATE `reingreso`
   SET `id_usuario_registro` = `id_usuario_resolucion`,
       `fecha_resolucion`   = COALESCE(`fecha_creacion`, NOW())
 WHERE `id_usuario_registro` IS NULL
    OR `fecha_resolucion` IS NULL;

ALTER TABLE `reingreso`
  ADD KEY `idx_reingreso_usuario_registro` (`id_usuario_registro`),
  ADD CONSTRAINT `fk_reingreso_usuario_registro`
      FOREIGN KEY (`id_usuario_registro`) REFERENCES `usuario` (`id_usuario`)
      ON DELETE SET NULL ON UPDATE CASCADE;

-- ---------------------------------------------------------------------------
-- 7. Disparadores de consistencia
--
-- matricula.id_anio_escolar se deriva siempre de su estructura academica.
-- inscripcion repite id_estudiante / id_anio_escolar / id_estructura_academica
-- para sostener sus índices UNIQUE y evitar JOINs en los listados, pero esos
-- valores quedan atados a matricula: si alguien intenta escribir otro valor,
-- el disparador lo reemplaza por el de la matrícula.
-- ---------------------------------------------------------------------------
DROP TRIGGER IF EXISTS `trg_matricula_bi_anio`;
DROP TRIGGER IF EXISTS `trg_matricula_bu_anio`;
DROP TRIGGER IF EXISTS `trg_matricula_au_inscripcion`;
DROP TRIGGER IF EXISTS `trg_inscripcion_bi_matricula`;
DROP TRIGGER IF EXISTS `trg_inscripcion_bu_matricula`;

DELIMITER $$

CREATE TRIGGER `trg_matricula_bi_anio`
BEFORE INSERT ON `matricula`
FOR EACH ROW PRECEDES `trg_matricula_transicion`
BEGIN
    DECLARE v_anio int(11);

    SET v_anio = (SELECT `id_anio_escolar`
                    FROM `estructura_academica`
                   WHERE `id_estructura_academica` = NEW.`id_estructura_academica`);

    IF v_anio IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'La estructura académica indicada no existe.';
    END IF;

    SET NEW.`id_anio_escolar` = v_anio;
END$$

CREATE TRIGGER `trg_matricula_bu_anio`
BEFORE UPDATE ON `matricula`
FOR EACH ROW
BEGIN
    DECLARE v_anio int(11);

    SET v_anio = (SELECT `id_anio_escolar`
                    FROM `estructura_academica`
                   WHERE `id_estructura_academica` = NEW.`id_estructura_academica`);

    IF v_anio IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'La estructura académica indicada no existe.';
    END IF;

    SET NEW.`id_anio_escolar` = v_anio;
END$$

CREATE TRIGGER `trg_inscripcion_bi_matricula`
BEFORE INSERT ON `inscripcion`
FOR EACH ROW
BEGIN
    DECLARE v_estudiante int(11);
    DECLARE v_anio int(11);
    DECLARE v_estructura int(11);

    SELECT `id_estudiante`, `id_anio_escolar`, `id_estructura_academica`
      INTO v_estudiante, v_anio, v_estructura
      FROM `matricula`
     WHERE `id_matricula` = NEW.`id_matricula`;

    IF v_estudiante IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'La matrícula indicada no existe.';
    END IF;

    SET NEW.`id_estudiante` = v_estudiante;
    SET NEW.`id_anio_escolar` = v_anio;
    SET NEW.`id_estructura_academica` = v_estructura;
END$$

CREATE TRIGGER `trg_inscripcion_bu_matricula`
BEFORE UPDATE ON `inscripcion`
FOR EACH ROW
BEGIN
    DECLARE v_estudiante int(11);
    DECLARE v_anio int(11);
    DECLARE v_estructura int(11);

    SELECT `id_estudiante`, `id_anio_escolar`, `id_estructura_academica`
      INTO v_estudiante, v_anio, v_estructura
      FROM `matricula`
     WHERE `id_matricula` = NEW.`id_matricula`;

    IF v_estudiante IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'La matrícula indicada no existe.';
    END IF;

    SET NEW.`id_estudiante` = v_estudiante;
    SET NEW.`id_anio_escolar` = v_anio;
    SET NEW.`id_estructura_academica` = v_estructura;
END$$

CREATE TRIGGER `trg_matricula_au_inscripcion`
AFTER UPDATE ON `matricula`
FOR EACH ROW
BEGIN
    UPDATE `inscripcion`
       SET `id_estudiante` = NEW.`id_estudiante`,
           `id_anio_escolar` = NEW.`id_anio_escolar`,
           `id_estructura_academica` = NEW.`id_estructura_academica`
     WHERE `id_matricula` = NEW.`id_matricula`;
END$$

DELIMITER ;
