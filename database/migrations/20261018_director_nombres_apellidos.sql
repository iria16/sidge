-- Desglose de nombres y apellidos de la autoridad directiva.
-- Permite ingresar primer nombre, segundo nombre, primer apellido y segundo apellido
-- para que en las constancias oficiales aparezca la identidad completa de la directora.

INSERT INTO configuracion_institucional (clave, valor, descripcion)
VALUES
    ('director_primer_nombre',   'Tibisay', 'Primer nombre del Director(a)'),
    ('director_segundo_nombre',  '',        'Segundo nombre del Director(a)'),
    ('director_primer_apellido', 'López',   'Primer apellido del Director(a)'),
    ('director_segundo_apellido', '',       'Segundo apellido del Director(a)')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion);
