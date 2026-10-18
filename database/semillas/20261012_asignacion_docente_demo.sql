-- Semilla de demostración: catálogo de tipo de asignación y asignaciones de docente.
--
-- ESTO NO ES UNA MIGRACIÓN. No va en database/migrations/ a propósito: una migración
-- se ejecuta sola en cada instalación y en cada actualización, y meterle datos de
-- prueba significaría que un colegio real que actualiza el sistema de repente tiene
-- un docente asignado a una sección que nunca asignó nadie. Las semillas se
-- ejecutan a mano, solo en desarrollo:
--
--     mysql -u root -p sidge < database/semillas/20261012_asignacion_docente_demo.sql
--
-- QUÉ ARREGLA
--
-- `tipo_asignacion` es un catálogo y está VACÍO, tanto en esta base como en
-- schema_actual.sql. Es el quinto tipo de dato que el esquema declara pero nadie
-- siembra, y tiene tres consecuencias concretas:
--
--   1. La pantalla de Asignación Docente no puede ofrecer nada: TeacherAssignmentModel
--      lista `WHERE estado = 'ACTIVO'` y no devuelve ni una fila.
--   2. `asignacion_docente.id_tipo_asignacion` es NOT NULL con llave foránea, así que
--      no se puede crear la primera asignación sin antes sembrar el catálogo.
--   3. Sin asignaciones, un docente que entra ve la pantalla de resultados vacía: el
--      filtro de ámbito está correcto, simplemente no hay nada que filtrar.
--
-- LA REGLA DE "UN DOCENTE, UNA SECCIÓN, SU TURNO"
--
-- La regla vigente es la inversa de la que se documentaba antes aquí. Un docente
--
--   * debe tener `empleado.id_turno` asignado. Sin turno NO se le puede dar sección:
--     la asignación se rechaza y hay que ir primero a fijarle el turno.
--   * solo puede recibir secciones de ese mismo turno. 1er A Mañana no le sirve a
--     quien está en Tarde.
--   * solo puede tener UNA sección activa a la vez. Si ya tiene una, hay que quitarle
--     esa antes de darle otra; el mensaje de error dice cuál es.
--
-- Estas tres reglas las impone TeacherAssignmentModel::validarAsignacion() en
-- asignar() y actualizar(), dentro de la transacción y con FOR UPDATE. Esta semilla
-- NO las declara ni las relaja: simplemente siembra datos que ya las cumplen, para
-- que un colegio que la ejecute no se encuentre con filas que el backend rechazaría.
--
-- POR QUÉ ESTA SEMILLA TOCA `empleado.id_turno`
--
-- El docente de demostración llega sin turno, y con la regla nueva eso lo deja sin
-- poder tener aula: la pantalla quedaría vacía otra vez, que es justo el problema
-- que la semilla viene a resolver. Por eso el paso 2 le fija el turno ANTES de
-- asignarle sección, y solo a esa cuenta.
--
-- Nótese que el índice único del esquema es
--
--     uk_asignacion_docente_estructura (id_docente, id_estructura_academica, fecha_inicio)
--
-- que sigue permitindo varias filas por docente. Lo que impide el exceso no es el
-- índice sino la validación del modelo: el índice solo evita el duplicado exacto de
-- la MISMA estructura en la MISMA fecha.
--
-- Idempotente: se puede ejecutar más de una vez sin duplicar nada.

-- ── 1. Catálogo de tipo de asignación ──────────────────────────────────────────
INSERT INTO tipo_asignacion (nombre, estado)
VALUES
    ('Titular',  'ACTIVO'),
    ('Suplente', 'ACTIVO')
ON DUPLICATE KEY UPDATE estado = VALUES(estado);

-- ── 2. Turno del docente de demostración ──────────────────────────────────────
-- Condición de la regla: sin `empleado.id_turno` no hay asignación posible.
--
-- El alcance es UN docente, el de la cuenta de demostración `docente`, y no "todos
-- los docentes". Fijar el turno de toda la plantilla sería decidir en nombre del
-- colegio quién trabaja por la mañana, que es justo lo que una semilla de
-- desarrollo no tiene por qué hacer.
--
-- Los tres UPDATE/INSERT siguientes localizan al docente por su nombre de usuario,
-- no por su id: los ids cambian entre instalaciones y el script debe seguir siendo
-- válido. Ese mismo criterio, por nombre de usuario y no por id, es el que usa el
-- paso 3 para insertar la asignación.
--
-- El UPDATE solo actúa cuando `id_turno IS NULL`. Si a ese docente ya se le asignó
-- un turno a mano, la semilla no lo pisa ni lo corrige.
UPDATE empleado e
JOIN docente d  ON d.id_empleado = e.id_empleado
JOIN usuario u ON u.id_persona  = e.id_persona
JOIN turno   t ON t.nombre = 'Mañana'
SET e.id_turno = t.id_turno
WHERE e.id_turno IS NULL
  AND u.nombre_usuario = 'docente';

-- ── 3. Asignación de demostración ──────────────────────────────────────────────
-- Se escribe por nombre en vez de por id para que el script siga funcionando si
-- los ids cambian. La estructura se busca por grado, sección, turno y período, que
-- es como la piensa alguien que lee la pantalla; los ids son de la base.
--
-- El filtro `e.id_turno = t.id_turno` no es decorativo: es la regla. Aunque el paso 2
-- deja el turno listo, repetir aquí la condición evita que la semilla cree una fila
-- que TeacherAssignmentModel::validarAsignacion() rechazaría.
INSERT INTO asignacion_docente (id_docente, id_estructura_academica, id_tipo_asignacion, fecha_inicio, fecha_fin)
SELECT d.id_docente,
       ea.id_estructura_academica,
       ta.id_tipo_asignacion,
       a.anio_inicio,
       NULL
FROM docente d
JOIN empleado e   ON e.id_empleado  = d.id_empleado
JOIN usuario u    ON u.id_persona   = e.id_persona
JOIN rol r        ON r.id_rol       = u.id_rol
JOIN anio_escolar a ON a.estado = 'ACTIVO' AND a.activo_unico = 1
JOIN grado g      ON g.nombre  = '1er Grado'
JOIN seccion s    ON s.nombre  = 'A'
JOIN turno t      ON t.nombre  = 'Mañana'
JOIN estructura_academica ea
     ON ea.id_anio_escolar = a.id_anio_escolar
    AND ea.id_grado        = g.id_grado
    AND ea.id_seccion      = s.id_seccion
    AND ea.id_turno        = t.id_turno
JOIN tipo_asignacion ta ON ta.nombre = 'Titular'
WHERE r.nombre = 'Docente'
  AND u.estado = 'ACTIVO'
  AND u.nombre_usuario = 'docente'
  AND e.id_turno = t.id_turno
  AND NOT EXISTS (
        SELECT 1 FROM asignacion_docente ad
        WHERE ad.id_docente = d.id_docente
          AND ad.id_estructura_academica = ea.id_estructura_academica
  );

-- Comprobación de salida. El detalle va en su propia consulta y no unido al UNION
-- de contadores: en un UNION, ORDER BY solo puede referirse a las columnas de salida,
-- no a las de las tablas del SELECT.
SELECT CONCAT('  tipos de asignación: ', (SELECT COUNT(*) FROM tipo_asignacion WHERE estado = 'ACTIVO')) AS aviso
UNION ALL
SELECT CONCAT('  docentes con turno: ', (SELECT COUNT(DISTINCT d.id_docente)
                                              FROM docente d JOIN empleado e ON e.id_empleado = d.id_empleado
                                             WHERE e.id_turno IS NOT NULL))
UNION ALL
SELECT CONCAT('  docentes asignados: ', (SELECT COUNT(DISTINCT id_docente) FROM asignacion_docente))
UNION ALL
SELECT CONCAT('  asignaciones activas: ', (SELECT COUNT(*) FROM asignacion_docente
                                             WHERE fecha_fin IS NULL OR fecha_fin >= CURDATE()))
UNION ALL
SELECT CONCAT('  asignaciones que rompen la regla: ', (SELECT COUNT(*)
                                                           FROM asignacion_docente ad
                                                           JOIN docente d ON d.id_docente = ad.id_docente
                                                           JOIN empleado e ON e.id_empleado = d.id_empleado
                                                           JOIN estructura_academica ea
                                                             ON ea.id_estructura_academica = ad.id_estructura_academica
                                                          WHERE (ad.fecha_fin IS NULL OR ad.fecha_fin >= CURDATE())
                                                            AND (e.id_turno IS NULL OR e.id_turno <> ea.id_turno)));

SELECT CONCAT('  docente ', ad.id_docente, ' | ', g.nombre, ' ', s.nombre, ' | turno del docente: ', te.nombre,
              ' | turno de la sección: ', t.nombre, ' | ', a.nombre_periodo, ' | ', ta.nombre,
              CASE WHEN te.nombre = t.nombre THEN '  [ok]' ELSE '  [REGLA INCUMPLIDA]' END) AS detalle
FROM asignacion_docente ad
JOIN estructura_academica ea ON ea.id_estructura_academica = ad.id_estructura_academica
JOIN grado g      ON g.id_grado   = ea.id_grado
JOIN seccion s    ON s.id_seccion = ea.id_seccion
JOIN turno t      ON t.id_turno   = ea.id_turno
JOIN docente d    ON d.id_docente = ad.id_docente
JOIN empleado e   ON e.id_empleado = d.id_empleado
LEFT JOIN turno te ON te.id_turno = e.id_turno
JOIN anio_escolar a ON a.id_anio_escolar = ea.id_anio_escolar
JOIN tipo_asignacion ta ON ta.id_tipo_asignacion = ad.id_tipo_asignacion
WHERE ad.fecha_fin IS NULL OR ad.fecha_fin >= CURDATE()
ORDER BY ad.id_docente;