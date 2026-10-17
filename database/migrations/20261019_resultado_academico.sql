-- Resultado académico por estudiante y por período (el "literal" del docente).
--
-- ESTA TABLA YA EXISTE EN LAS BASES EN USO. Este archivo NO la crea de cero: la
-- documenta para el repositorio. Se hizo a mano en la base y nunca se versionó, y
-- eso rompía cualquier instalación que no fuera esta: el módulo de resultado
-- académico, el de egreso (exige estado_resultado = 'PROMOVIDO' para poder
-- egresar de 6to), la ratificación (lee el literal para repetir grado cuando sale
-- NO_PROMOVIDO) y el cierre de año escolar (se bloquea si quedan PENDIENTES)
-- consultan esta tabla sin ella.
--
-- QUÉ ES EL LITERAL
--
-- La calificación de fin de período no es un número sino una letra. En Educación
-- Primaria el docente cierra el año con A, B, C, D o E, y de esa letra sale el
-- destino del alumno: A, B, C y D promovido, E no promovido. La letra es lo que el
-- docente carga; `estado_resultado` y `id_grado_destino` se derivan de ella en
-- ResultadoAcademicoModel::estadoSugeridoPorLiteral(), que es la única fuente de
-- esa regla. Por eso `literal` acepta NULL: un alumno al que todavía no se le ha
-- asignado literal está PENDIENTE, y esa es una situación real, no un dato faltante
-- que haya que rellenar con un valor por defecto.
--
-- EL FLORECE ES DE DOS PASOS
--
-- `propuesto_por` y `confirmado_por` no son un campo y su historia: el docente
-- propone (guardarPropuesta) y la dirección confirma (confirmarResultados). Que
-- estén separados es lo que permite que el año no se cierre sobre una propuesta
-- que nadie validó. Por eso confirmar está restringido a admin y directivo en
-- public/index.php, y no solo en el modelo.
--
-- UNA FILA POR ESTUDIANTE Y AÑO
--
-- `uk_resultado_estudiante_anio` es lo que impide el defecto más caro de este
-- módulo: que el mismo alumno tenga dos literales para el mismo período, uno de
-- cada lado, porque dos docentes EACH tocaron la pantalla. Se elige por
-- estudiante y año, no por matrícula, porque la matrícula es el detalle de CÓMO
-- entró y el resultado es de QUÉ período trata.
--
-- COLLATION: utf8mb4_unicode_ci, copiado de la tabla real en la base
-- (SELECT TABLE_COLLATION FROM information_schema.TABLES), NO del dump ni del
-- resto del esquema. Las demás tablas usan utf8mb4_uca1400_ai_ci y esta quedó
-- creada con la collation antigua. Se reproduce tal cual para que una instalación
-- nueva sea idéntica a la que ya está en uso; igualar la collation en todo el
-- esquema es un trabajo aparte y no va aquí.
--
-- Idempotente: se puede ejecutar más de una vez sin romper nada.

CREATE TABLE IF NOT EXISTS resultado_academico (
    id_resultado           INT(11)      NOT NULL AUTO_INCREMENT,
    id_estudiante          INT(11)      NOT NULL,
    id_anio_escolar        INT(11)      NOT NULL,
    /* grado + sección + turno + año. Es la misma unidad que usa asignacion_docente
       para acotar al docente, y a propósito: la sección de un docente ES una
       estructura_academica, no la tabla `seccion` suelta. */
    id_estructura_academica INT(11)     NOT NULL,
    id_matricula           INT(11)      NOT NULL,
    literal                ENUM('A','B','C','D','E') DEFAULT NULL
        COMMENT 'Calificación cualitativa oficial de Educación Primaria',
    estado_resultado       ENUM('PENDIENTE','PROMOVIDO','NO_PROMOVIDO') NOT NULL DEFAULT 'PENDIENTE'
        COMMENT 'Estado del resultado anual',
    /* Grado al que va en la siguiente matrícula: el siguiente si fue promovido,
       el mismo si no. Lo calcula ResultadoAcademicoModel::calcularSiguienteGradoId()
       a partir del literal, y se guarda para que la ratificación no tenga que
       volver a deducirlo. NULL mientras el resultado esté PENDIENTE. */
    id_grado_destino       INT(11)      NULL DEFAULT NULL
        COMMENT 'Grado sugerido para la siguiente matrícula (siguiente si promovido, mismo si no)',
    observaciones          TEXT         NULL DEFAULT NULL
        COMMENT 'Observaciones pedagógicas del docente o directivo',
    propuesto_por          INT(11)      NULL DEFAULT NULL
        COMMENT 'Usuario del docente que cargó la propuesta',
    fecha_propuesta        DATETIME     NULL DEFAULT NULL,
    confirmado_por         INT(11)      NULL DEFAULT NULL
        COMMENT 'Usuario del directivo que validó y confirmó',
    fecha_confirmacion     DATETIME     NULL DEFAULT NULL,
    fecha_creacion         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_resultado),
    UNIQUE KEY uk_resultado_estudiante_anio (id_estudiante, id_anio_escolar),
    KEY idx_resultado_estructura (id_estructura_academica),
    KEY idx_resultado_matricula (id_matricula),
    KEY idx_resultado_estado (estado_resultado),
    /* ON DELETE SET NULL en propuesto_por y confirmado_por, no CASCADE: si se da
       de baja el usuario que hizo la propuesta, el resultado del alumno tiene que
       seguir existiendo. El documento ya emitido no puede desaparecer porque la
       persona que lo firmó dejó de trabajar aquí. */
    CONSTRAINT fk_res_acad_anio FOREIGN KEY (id_anio_escolar)
        REFERENCES anio_escolar (id_anio_escolar) ON UPDATE CASCADE,
    CONSTRAINT fk_res_acad_confirmado FOREIGN KEY (confirmado_por)
        REFERENCES usuario (id_usuario) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_res_acad_estructura FOREIGN KEY (id_estructura_academica)
        REFERENCES estructura_academica (id_estructura_academica) ON UPDATE CASCADE,
    CONSTRAINT fk_res_acad_estudiante FOREIGN KEY (id_estudiante)
        REFERENCES estudiante (id_estudiante) ON UPDATE CASCADE,
    CONSTRAINT fk_res_acad_grado_destino FOREIGN KEY (id_grado_destino)
        REFERENCES grado (id_grado) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_res_acad_matricula FOREIGN KEY (id_matricula)
        REFERENCES matricula (id_matricula) ON UPDATE CASCADE,
    CONSTRAINT fk_res_acad_propuesto FOREIGN KEY (propuesto_por)
        REFERENCES usuario (id_usuario) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Comprobación de salida: si la tabla existe con sus índices y sus llaves, está.
SELECT CONCAT('  resultado_academico existe: ',
              IF((SELECT COUNT(*) FROM information_schema.TABLES
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'resultado_academico') > 0, 'sí', 'NO')) AS aviso
UNION ALL
SELECT CONCAT('  índice único por estudiante y año: ',
              IF((SELECT COUNT(*) FROM information_schema.STATISTICS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'resultado_academico'
                     AND INDEX_NAME = 'uk_resultado_estudiante_anio' AND NON_UNIQUE = 0) > 0, 'sí', 'NO'))
UNION ALL
SELECT CONCAT('  llaves foráneas: ',
              (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'resultado_academico'
                  AND CONSTRAINT_TYPE = 'FOREIGN KEY'))
UNION ALL
SELECT CONCAT('  collation: ',
              (SELECT TABLE_COLLATION FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'resultado_academico'))
UNION ALL
SELECT CONCAT('  literales permitidos: ',
              (SELECT COLUMN_TYPE FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'resultado_academico'
                  AND COLUMN_NAME = 'literal'));