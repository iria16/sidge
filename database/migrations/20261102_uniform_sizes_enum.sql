DROP PROCEDURE IF EXISTS mig_20261102_uniform_sizes_enum_validate;
DELIMITER //
CREATE PROCEDURE mig_20261102_uniform_sizes_enum_validate()
BEGIN
    IF EXISTS (
        SELECT 1
          FROM talla_uniforme
         WHERE BINARY talla_camisa NOT IN (
                   BINARY '', BINARY '2', BINARY '4', BINARY '6', BINARY '8',
                   BINARY '10', BINARY '12', BINARY '14', BINARY '16',
                   BINARY 'S', BINARY 'M', BINARY 'L', BINARY 'XL'
               )
            OR BINARY talla_pantalon NOT IN (
                   BINARY '', BINARY '2', BINARY '4', BINARY '6', BINARY '8',
                   BINARY '10', BINARY '12', BINARY '14', BINARY '16',
                   BINARY '26', BINARY '28', BINARY '30', BINARY '32',
                   BINARY '34', BINARY '36'
               )
            OR BINARY talla_zapato NOT IN (
                   BINARY '', BINARY '24', BINARY '25', BINARY '26', BINARY '27',
                   BINARY '28', BINARY '29', BINARY '30', BINARY '31', BINARY '32',
                   BINARY '33', BINARY '34', BINARY '35', BINARY '36', BINARY '37',
                   BINARY '38', BINARY '39', BINARY '40'
               )
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Hay tallas guardadas fuera del catalogo. Revisa y normaliza los valores antes de repetir esta migracion.';
    END IF;
END//
DELIMITER ;

CALL mig_20261102_uniform_sizes_enum_validate();
DROP PROCEDURE mig_20261102_uniform_sizes_enum_validate;

ALTER TABLE talla_uniforme
    MODIFY talla_camisa ENUM('', '2', '4', '6', '8', '10', '12', '14', '16', 'S', 'M', 'L', 'XL')
        NOT NULL DEFAULT '',
    MODIFY talla_pantalon ENUM('', '2', '4', '6', '8', '10', '12', '14', '16', '26', '28', '30', '32', '34', '36')
        NOT NULL DEFAULT '',
    MODIFY talla_zapato ENUM('', '24', '25', '26', '27', '28', '29', '30', '31', '32', '33', '34', '35', '36', '37', '38', '39', '40')
        NOT NULL DEFAULT '';
