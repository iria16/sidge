-- Migracion: Crear especializacion reingreso y actualizar trg_matricula_transicion
-- Fecha: 2026-11-06

CREATE TABLE IF NOT EXISTS `reingreso` (
  `id_reingreso` int(11) NOT NULL AUTO_INCREMENT,
  `id_matricula` int(11) NOT NULL,
  `id_retiro_previo` int(11) DEFAULT NULL,
  `id_institucion_procedencia` int(11) DEFAULT NULL,
  `motivo_reingreso` varchar(255) NOT NULL,
  `fecha_reincorporacion` date NOT NULL,
  `id_usuario_resolucion` int(11) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_reingreso`),
  UNIQUE KEY `uk_reingreso_matricula` (`id_matricula`),
  KEY `fk_reingreso_retiro` (`id_retiro_previo`),
  KEY `fk_reingreso_institucion` (`id_institucion_procedencia`),
  KEY `fk_reingreso_usuario` (`id_usuario_resolucion`),
  CONSTRAINT `fk_reingreso_matricula` FOREIGN KEY (`id_matricula`) REFERENCES `matricula` (`id_matricula`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_reingreso_retiro` FOREIGN KEY (`id_retiro_previo`) REFERENCES `retiro` (`id_retiro`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_reingreso_institucion` FOREIGN KEY (`id_institucion_procedencia`) REFERENCES `institucion` (`id_institucion`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_reingreso_usuario` FOREIGN KEY (`id_usuario_resolucion`) REFERENCES `usuario` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- Actualizar trigger trg_matricula_transicion
DROP TRIGGER IF EXISTS `trg_matricula_transicion`;

DELIMITER ;;
CREATE TRIGGER `trg_matricula_transicion`
BEFORE INSERT ON `matricula`
FOR EACH ROW
BEGIN
    DECLARE v_previo      VARCHAR(20) DEFAULT NULL;
    DECLARE v_previo_anio INT         DEFAULT NULL;
    DECLARE v_ordinal     INT         DEFAULT NULL;

    SELECT m.tipo, m.id_anio_escolar INTO v_previo, v_previo_anio
      FROM matricula m
      INNER JOIN anio_escolar a ON a.id_anio_escolar = m.id_anio_escolar
     WHERE m.id_estudiante = NEW.id_estudiante
     ORDER BY a.anio_inicio DESC, m.id_matricula DESC
     LIMIT 1;

    reglas: BEGIN

    IF v_previo IS NULL THEN
        IF NEW.tipo <> 'INSCRIPCION' THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Un estudiante sin matriculas previas no se ratifica, no se retira y no egresa: se inscribe.';
        END IF;

        LEAVE reglas;
    END IF;

    -- El egreso de 6to grado culmina el ciclo escolar y es terminal absoluto
    IF v_previo = 'EGRESO' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'El ciclo de este estudiante culmino por egreso, no admite mas matriculas.';
    END IF;

    -- Manejo de reingreso tras un retiro previo
    IF v_previo = 'RETIRO' THEN
        IF NEW.tipo = 'INSCRIPCION' THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'El estudiante ya tiene expediente: la inscripcion ocurre una sola vez de primer ingreso.';
        END IF;

        IF NEW.tipo IN ('RETIRO', 'EGRESO') THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'El estudiante ya se encuentra retirado.';
        END IF;

        -- NEW.tipo = 'RATIFICACION' (reincorporacion/continuidad):
        -- Permitido siempre que el anio escolar sea igual o posterior al anio del retiro
        IF (
            SELECT an2.anio_inicio FROM anio_escolar an2
             WHERE an2.id_anio_escolar = NEW.id_anio_escolar
        ) < (
            SELECT an1.anio_inicio FROM anio_escolar an1
             WHERE an1.id_anio_escolar = v_previo_anio
        ) THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'El reingreso no puede registrarse en un anio escolar anterior al del retiro.';
        END IF;

        LEAVE reglas;
    END IF;

    IF NEW.tipo = 'INSCRIPCION' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'La inscripcion ocurre una sola vez en toda la vida escolar del alumno: los que ya estan inscriptos se ratifican.';
    END IF;

    IF NEW.tipo = 'RATIFICACION' AND (
        SELECT an2.anio_inicio FROM anio_escolar an2
         WHERE an2.id_anio_escolar = NEW.id_anio_escolar
    ) <= (
        SELECT an1.anio_inicio FROM anio_escolar an1
         WHERE an1.id_anio_escolar = v_previo_anio
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'La ratificacion debe ir en un anio escolar posterior al de la anterior.';
    END IF;

    IF NEW.tipo IN ('RETIRO', 'EGRESO') AND (
        SELECT an3.anio_inicio FROM anio_escolar an3
         WHERE an3.id_anio_escolar = NEW.id_anio_escolar
    ) < (
        SELECT an4.anio_inicio FROM anio_escolar an4
         WHERE an4.id_anio_escolar = v_previo_anio
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'El cierre no puede ir en un anio anterior al de la matricula del alumno.';
    END IF;

    IF NEW.tipo = 'EGRESO' THEN
        SELECT CAST(REGEXP_SUBSTR(g.nombre, '^[0-9]+') AS UNSIGNED) INTO v_ordinal
          FROM estructura_academica ea
          INNER JOIN grado g ON g.id_grado = ea.id_grado
         WHERE ea.id_estructura_academica = NEW.id_estructura_academica
         LIMIT 1;

        IF v_ordinal IS NOT NULL AND v_ordinal <> 6 THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'El egreso es solo para quien culmina 6to grado.';
        END IF;
    END IF;

    END reglas;
END;;
DELIMITER ;