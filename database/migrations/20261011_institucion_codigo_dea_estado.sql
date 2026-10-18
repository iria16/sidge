-- Código DEA y estado geográfico de la institución de procedencia.
--
-- La tabla `institucion` es el catálogo compartido por el alta de instituciones de procedencia
-- (paso 2 del wizard de inscripciones) y por las instituciones de formación académica de los
-- empleados. Antes solo guardaba nombre + tipo + estado ACTIVO/INACTIVO, y con eso no se podía
-- registrar una institución en el sistema: el Ministerio exige el código DEA que identifica
-- al plantel (el mismo que aparece en el membrete de los documentos oficiales) y el estado
-- geográfico donde está ubica. Se agregan aquí.
--
-- `id_estado_geo` fk a la tabla `estado` (los 24 estados de Venezuela) en lugar de guardar el
-- texto, igual que hace `persona.id_estado_nacimiento`: cambiar "Caracas" por "Distrito
-- Capital" se resuelve en un solo lugar y no obliga a editar cada institución.
--
-- `codigo_dea` lleva un índice ÚNICO: es el identificador del plantel ante el Ministerio, así
-- que dos instituciones no pueden compartirlo. El chequeo del formulario y esta restricción son
-- las dos capas: el formulario da el mensaje útil ("Ya existe una institución con ese código
-- DEA") y la base impide que se cuele un duplicado por otra vía (un script, un phpMyAdmin, una
-- carga masiva).
--
-- OJO con el nombre de las columnas: `institucion` YA tenía una columna `estado` que es el
-- estado del REGISTRO (enum ACTIVO/INACTIVO, la que usa CatalogoModel::toggleStatus()). Por eso
-- la nueva no puede llamarse `estado` —lataken— ni solo `id_estado`, que se confunde con ella al
-- leer un SELECT; se llama `id_estado_geo` siguiendo el precedente de `persona`.
--
-- Las dos columnas nacen NULL a propósito. Hay instituciones ya registradas (procedencia y
-- títulos académicos) que no tienen código DEA y no se inventa uno: se rellenan a mano con el
-- dato real. El alta desde el wizard sí las exige, pero eso lo aplica el formulario, no la base,
-- para no dejar inaccesible una institución que ya existe.
--
-- Es idempotente: se puede ejecutar más de una vez sin romper nada.

-- 1) Las dos columnas.
SET @tieneDea = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'institucion' AND COLUMN_NAME = 'codigo_dea'
);
SET @sql = IF(@tieneDea = 0,
    'ALTER TABLE institucion ADD COLUMN codigo_dea VARCHAR(20) NULL DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tieneEstadoGeo = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'institucion' AND COLUMN_NAME = 'id_estado_geo'
);
SET @sql = IF(@tieneEstadoGeo = 0,
    'ALTER TABLE institucion ADD COLUMN id_estado_geo INT(11) NULL DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) El código DEA es ÚNICO, y es la base la que lo garantiza.
--
--    Se indexa y no nada más: el <select> de procedencia busca por este valor y lo
--    ordena, así que el índice se paga solo.
--
--    UNIQUE no choca con las 10 instituciones que quedaron en NULL: MySQL considera
--    que NULL no es igual a NULL, así que un índice único tolera cualquier cantidad
--    de NULL y solo restringe los valores repetidos de verdad. Por eso se puede
--    poner único desde el primer día, sin tener que rellenar antes los datos viejos.
--
--    La comparación es sin distinguir mayúsculas porque la collation de la tabla es
--    utf8mb4_uca1400_ai_ci: "od05750814" y "OD05750814" son la misma institución.
--    Es el mismo criterio que ya aplica InstitucionModel::existeCodigoDea() con
--    LOWER(), así que la base y el formulario rechazan exactamente lo mismo.
--
--    Si algún día existieran dos instituciones con el mismo código, el índice no se
--    crea: es preferible quedarse sin él, avisando por pantalla, que dejarla
--   reventar con un error 1062 a mitad de una migración.
SET @tieneIdxDea = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'institucion' AND INDEX_NAME = 'idx_institucion_codigo_dea'
);
SET @sql = IF(@tieneIdxDea > 0,
    'ALTER TABLE institucion DROP KEY idx_institucion_codigo_dea', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @dupesDea = (
    SELECT COUNT(*) FROM (
        SELECT codigo_dea
          FROM institucion
         WHERE codigo_dea IS NOT NULL
         GROUP BY codigo_dea
        HAVING COUNT(*) > 1
    ) d
);
SET @yaEsUnico = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'institucion'
       AND INDEX_NAME = 'ux_institucion_codigo_dea' AND NON_UNIQUE = 0
);
SET @sql = IF(@yaEsUnico = 0 AND @dupesDea = 0,
    'ALTER TABLE institucion ADD UNIQUE KEY ux_institucion_codigo_dea (codigo_dea)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Si quedaron duplicados, el índice único no se creó y hay que resolverlos a mano.
SELECT CONCAT('  codigos DEA repetidos: ', @dupesDea,
              IF(@dupesDea > 0, ' — NO se creó el índice único, corrígelos y repite la migración', ''))
    AS aviso;

-- 3) La llave foránea, solo si la columna existe y todavía no está enlazada.
--    La llave se busca en KEY_COLUMN_USAGE y no en TABLE_CONSTRAINTS porque
--    REFERENCED_TABLE_NAME vive en la primera: en TABLE_CONSTRAINTS no existe esa columna y la
--    migración revienta con "Unknown column".
SET @tieneFk = (
    SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'institucion'
       AND REFERENCED_TABLE_NAME = 'estado'
       AND REFERENCED_COLUMN_NAME = 'id_estado'
);
SET @sql = IF(@tieneFk = 0 AND @tieneEstadoGeo = 1,
    'ALTER TABLE institucion
        ADD CONSTRAINT fk_institucion_estado_geo
        FOREIGN KEY (id_estado_geo) REFERENCES estado (id_estado)
        ON UPDATE CASCADE ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4) Aviso de salida: cuántas instituciones quedan sin código DEA, para que quien aplique esto a
--    mano sepa si tiene datos pendientes de completar. Todas deberían quedar en NULL, porque
--    las columnas se crean vacías.
SELECT CONCAT('  instituciones sin codigo_dea (quedan en NULL): ',
              (SELECT COUNT(*) FROM institucion WHERE codigo_dea IS NULL))
UNION ALL
SELECT CONCAT('  instituciones sin id_estado_geo (quedan en NULL): ',
              (SELECT COUNT(*) FROM institucion WHERE id_estado_geo IS NULL));