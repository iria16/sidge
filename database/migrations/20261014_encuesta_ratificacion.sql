-- Encuesta de ratificación: Canaima, ingreso al SIGE y repetición de grado.
--
-- Se responde UNA VEZ POR PERÍODO, en el modal "Ratificación de Matrícula", antes de elegir
-- grado y sección: la respuesta a "¿Repite el grado?" es la que decide con qué se rellenan
-- esos dos campos, así que va primero.
--
-- POR QUÉ ES UNA TABLA PROPIA Y NO COLUMNAS EN `matricula`:
--
-- `matricula` guarda cuatro tipos de fila (INSCRIPCION, RATIFICACION, RETIRO, EGRESO) y esto
-- solo aplica a uno. Meter las columnas ahí dejaría seis campos en NULL en tres de cada cuatro
-- filas, y cualquier consulta que mire `matricula` tendría que acordarse de filtrar por tipo
-- para no leer datos de una encuesta que no existe.
--
-- POR QUÉ NO ESTÁ EN `estudiante`:
--
-- Las tres respuestas son del PERÍODO, no del alumno. "Repite el grado" solo significa algo
-- respecto de un año concreto; el mismo alumno repite en uno y no en el siguiente. Y "tiene
-- Canaima" tampoco es para siempre: el aparato se pierde o se cambia. Si esto viviera en
-- `estudiante`, al año siguiente la encuesta quedaría pisada y no se podría volver a preguntar.
--
-- Se engancha por `id_matricula`, que es UNIQUE: una encuesta por ratificación, y es la fila que
-- ya existe cuando la respuesta es válida (el modelo la crea en la misma transacción).
--
-- Los tres Sí/No son NOT NULL porque son obligatorios: no hay tercera opción. Serial, fecha y
-- motivo son NULL porque dependen de la respuesta: si no tiene Canaima no hay serial, y si no
-- ingresó al SIGE no hay fecha ni motivo. NULL = "no corresponde por la respuesta", que es una
-- respuesta válida y no un dato faltante.
--
-- Es idempotente: se puede ejecutar más de una vez sin romper nada.

CREATE TABLE IF NOT EXISTS encuesta_ratificacion (
    id_matricula        INT(11)     NOT NULL,
    tiene_canaima       TINYINT(1)  NOT NULL,
    serial_canaima      VARCHAR(50) NULL DEFAULT NULL,
    tiene_ingreso_sige  TINYINT(1)  NOT NULL,
    fecha_ingreso_sige  DATE        NULL DEFAULT NULL,
    motivo_sige         VARCHAR(200) NULL DEFAULT NULL,
    repite_grado        TINYINT(1)  NOT NULL,
    fecha_creacion      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_matricula),
    /* Los tres Sí/No solo admiten 0 y 1. TINYINT(1) en MySQL no es un booleano: es un entero
     pequeño y aceptaría -1 o 7. El CHECK los cierra para que un valor raro quede afuera aquí,
     en vez de terminar guardado y mostrando "Sí" en pantalla.*/
    CONSTRAINT `ck_encuesta_canaima`  CHECK (`tiene_canaima`      IN (0, 1)),
    CONSTRAINT `ck_encuesta_sige`     CHECK (`tiene_ingreso_sige` IN (0, 1)),
    CONSTRAINT `ck_encuesta_repite`   CHECK (`repite_grado`       IN (0, 1)),
    CONSTRAINT `fk_encuesta_matricula` FOREIGN KEY (`id_matricula`)
        REFERENCES `matricula` (`id_matricula`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- Índice sobre el año no hace falta: la búsqueda siempre entra por id_matricula, que es la PK.