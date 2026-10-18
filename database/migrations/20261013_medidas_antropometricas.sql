-- Peso y estatura en la tarjeta de medidas del estudiante.
--
-- `talla_uniforme` guardaba las tres tallas de prenda y las observaciones. El paso 4 del
-- wizard pasó a registrar medidas antropométricas: a las tallas se le suman el peso y la
-- estatura, que es lo que pide la ficha del estudiante.
--
-- La tabla NO se renombra a `medidas_antropometricas`. Renombrarla obliga a rehacer las
-- dos vistas que la consultan por subconsulta, el UPSERT de los dos modelos y la llave
-- foránea, y el nombre corto no estorba: mientras la tabla sea la que guarda las medidas
-- del estudiante, `talla_uniforme` sigue siendo un nombre entendible.
--
-- Las dos columnas nacen NULL y quedan en NULL cuando el campo va vacío: a diferencia de
-- las tres tallas, que son NOT NULL y por eso el wizard de ratificación obliga a llenarlas,
-- el peso y la estatura son OPCIONALES. Una medida que no se registró es "no se registró",
-- no un cero: un peso de 0 o una estatura de 0 serían datos falsos en el expediente.
--
-- Los tipos cierran con el rango que valida PersonaValidacionTrait::validarMedidas():
-- DECIMAL(5,2) llega a 999.99 (cabe 150.00 con margen) y SMALLINT UNSIGNED llega a 65535
-- (cabe 220 con mucho margen). El rango como tal lo aplica el servidor; el tipo solo evita
-- que un dígito de más reviente la escritura con un error 1264.
--
-- Es idempotente: se puede ejecutar más de una vez sin romper nada.

SET @tienePeso = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'talla_uniforme' AND COLUMN_NAME = 'peso_kg'
);
SET @sql = IF(@tienePeso = 0,
    'ALTER TABLE talla_uniforme ADD COLUMN peso_kg DECIMAL(5,2) NULL DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tieneEstatura = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'talla_uniforme' AND COLUMN_NAME = 'estatura_cm'
);
SET @sql = IF(@tieneEstatura = 0,
    'ALTER TABLE talla_uniforme ADD COLUMN estatura_cm SMALLINT UNSIGNED NULL DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Aviso de salida: las dos columnas entran vacías, así que el total sale igual a las filas
-- que ya había menos las que tenían la medida puesta a mano antes de esta migración.
SELECT CONCAT('  estudiantes sin peso: ',    (SELECT COUNT(*) FROM talla_uniforme WHERE peso_kg IS NULL)) AS aviso
UNION ALL
SELECT CONCAT('  estudiantes sin estatura: ', (SELECT COUNT(*) FROM talla_uniforme WHERE estatura_cm IS NULL));
SELECT CONCAT('  filas en talla_uniforme: ',  (SELECT COUNT(*) FROM talla_uniforme)) AS aviso;