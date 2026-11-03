-- Cuentas ficticias de prueba para InfinityFree.
-- Ejecutar una sola vez, después de importar schema_infinityfree.sql.
-- El primer acceso obliga a cambiar la clave provisional.
-- No importar en producción ni en una base con personas/usuarios reales.

START TRANSACTION;

INSERT INTO `persona`
    (`tipo_documento`, `numero_documento`, `fecha_nacimiento`, `primer_nombre`,
     `primer_apellido`, `sexo`)
VALUES
    ('CE', 'DEMO-SIGDE-ADM-01', '1990-01-01', 'Usuario', 'Prueba Admin', 'M'),
    ('CE', 'DEMO-SIGDE-DIR-01', '1990-01-02', 'Usuario', 'Prueba Directivo', 'F');

INSERT INTO `usuario`
    (`id_persona`, `id_rol`, `nombre_usuario`, `password_provisional`,
     `password_hash`, `estado`)
SELECT p.`id_persona`, r.`id_rol`, 'demo_admin', 1,
       '$2y$12$4sW/wcsPt5WxWKWRfOmIDexLPzRw1FxfowjC31pP2vsA7vGYnsXKC', 'ACTIVO'
  FROM `persona` p
  JOIN `rol` r ON r.`nombre` = 'Admin' AND r.`estado` = 'ACTIVO'
 WHERE p.`numero_documento` = 'DEMO-SIGDE-ADM-01';

INSERT INTO `usuario`
    (`id_persona`, `id_rol`, `nombre_usuario`, `password_provisional`,
     `password_hash`, `estado`)
SELECT p.`id_persona`, r.`id_rol`, 'demo_directivo', 1,
       '$2y$12$JeqDMaj042RHjGsrdiETRuno/LYnriIjpAxRNwdmxJDipHY7Be0Mm', 'ACTIVO'
  FROM `persona` p
  JOIN `rol` r ON r.`nombre` = 'Directivo' AND r.`estado` = 'ACTIVO'
 WHERE p.`numero_documento` = 'DEMO-SIGDE-DIR-01';

COMMIT;
