-- Reposos de empleados (y docentes, que son empleados: ver la nota de abajo).
--
-- La directora necesita poder indicar si un docente o un empleado está de reposo. Un reposo
-- es un EVENTO con inicio y fin, no un sí/no permanente: el mismo empleado puede tener tres
-- reposos en un año y ninguno al siguiente. Por eso va en su propia tabla y no como una
-- columna en `empleado`: una columna tendría que reescribirse cada vez que el reposo termina
-- y no guardaría el historial.
--
-- "Está en reposo" NO se guarda: se CALCULA en cada consulta a partir de las fechas
-- (fecha_inicio <= hoy AND (fecha_fin IS NULL OR fecha_fin >= hoy)). Un empleado con un
-- reposo sin fecha_fin que ya venció no está en reposo, y eso se ve solo, sin que ningún
-- proceso tenga que cerrar nada.
--
-- POR QUÉ CUELGA DE `empleado` Y NO DE `docente`:
--
-- `docente` es una tabla hija 1:1 de `empleado` (id_docente, id_empleado, nada más) que
-- EmpleadoModel::sincronizarRegistroDocente() crea sola según el cargo. Todo docente es
-- un empleado con fila en `docente`. Colgar de `empleado` cubre a los dos con una sola
-- llave y sin una regla que decida a quién preguntarle.
--
-- COLLATION: utf8mb4_uca1400_ai_ci, copiado de la tabla `empleado` de la base real
-- (SELECT TABLE_COLLATION FROM information_schema.TABLES), NO del dump. El
-- schema_actual.sql es un consolidado viejo y sus AUTO_INCREMENT y colaciones ya
-- divergieron de la base en varias tablas.
--
-- ÍNDICE ÚNICO DE VIGENCIA: los dos índices que tenía el borrador (uno por empleado y
-- otro por fechas) se funden en uno solo sobre (id_empleado, fecha_inicio, fecha_fin), que
-- es el orden real de las consultas: "los reposos de este empleado" y, dentro de ellos,
-- "¿cuál cubre hoy?". Con un solo índice el planificador no tiene que elegir.
--
-- Idempotente: se puede ejecutar más de una vez sin romper nada.

CREATE TABLE IF NOT EXISTS reposo_empleado (
    id_reposo           INT(11)      NOT NULL AUTO_INCREMENT,
    id_empleado         INT(11)      NOT NULL,
    fecha_inicio        DATE         NOT NULL,
    /* NULL = el reposo sigue abierto. La columna acepta NULL a propósito: un reposo
       que se протяjo más de lo previsto no tiene fecha de cierre, jjecutar un
       UPDATE todos los días para alargarlo sería mentir sobre cuándo empezó. */
    fecha_fin           DATE         NULL DEFAULT NULL,
    /* Texto corto y general ("licencia médica", "permiso por ibuja"). No es para
       diagnósticos: eso va en la ficha médica, que es otro módulo. */
    motivo              VARCHAR(200) NULL DEFAULT NULL,
    fecha_creacion      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_reposo),
    KEY idx_reposo_vigencia (id_empleado, fecha_inicio, fecha_fin),
    CONSTRAINT `fk_reposo_empleado` FOREIGN KEY (id_empleado)
        REFERENCES `empleado` (id_empleado) ON UPDATE CASCADE ON DELETE CASCADE,
    /* Segunda barrera, no la única. MariaDB la aplica desde la 10.2.1 (esta base es
       11.8.6, verificado con SELECT VERSION()) y MySQL desde la 8.0.16, así que
       hoy sí corta: se comprobó que una fila con fecha_fin anterior a fecha_inicio
       falla con ERROR 4025. Aun así la regla también se valida en el servidor, porque
       el mensaje que ve la secretaria es mucho más útil que el error de la base,
       y porque un INSERT puede venir de un script que no pase por el controlador. */
    CONSTRAINT `ck_reposo_rango` CHECK (fecha_fin IS NULL OR fecha_fin >= fecha_inicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- Comprobación de salida: si la tabla existe y el CHECK está, el resultado es 'sí'.
SELECT CONCAT('  reposo_empleado creada: ',
              IF((SELECT COUNT(*) FROM information_schema.TABLES
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reposo_empleado') > 0, 'sí', 'NO')) AS aviso
UNION ALL
SELECT CONCAT('  el CHECK ck_reposo_rango está: ',
              IF((SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                   WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'reposo_empleado'
                     AND CONSTRAINT_TYPE = 'CHECK') > 0, 'sí', 'NO'))
UNION ALL
SELECT CONCAT('  collation: ',
              (SELECT TABLE_COLLATION FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reposo_empleado'))