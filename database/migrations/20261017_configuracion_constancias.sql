-- Migración: Claves institucionales adicionales para la emisión de constancias
-- y membretes oficiales.
--
-- Agrega las claves requeridas para las constancias de estudio e inscripción:
-- - plantel_municipio: 'Valencia'
-- - plantel_parroquia: 'Miguel Peña'
-- - cdce_estado: 'Carabobo'
-- - plantel_codigo_dependencia: '006562012'
-- - nivel_educativo: 'Educación Primaria'
-- - nivel_codigo: '21000'
-- - lugar_expedicion: 'Carabobo'
-- - plantel_cintillo: '/assets/img/franja_tricolor.png'
--
-- Idempotente: ON DUPLICATE KEY UPDATE solo actualiza la descripción si ya existen.

INSERT INTO configuracion_institucional (clave, valor, descripcion)
VALUES
    ('plantel_municipio',          'Valencia',                             'Municipio donde está ubicado el plantel'),
    ('plantel_parroquia',          'Miguel Peña',                          'Parroquia donde está ubicada la institución'),
    ('cdce_estado',                'Carabobo',                             'Estado del Centro de Desarrollo de la Calidad Educativa'),
    ('plantel_codigo_dependencia', '006562012',                            'Código de dependencia oficial de la institución'),
    ('nivel_educativo',            'Educación Primaria',                   'Nivel educativo impartido por la institución'),
    ('nivel_codigo',               '21000',                                'Código oficial del nivel educativo ante el MPPE'),
    ('lugar_expedicion',           'Carabobo',                             'Lugar/Ciudad de expedición impreso en las constancias'),
    ('plantel_cintillo',           '/assets/img/franja_tricolor.png',      'Ruta de la franja tricolor inferior para constancias')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion);
