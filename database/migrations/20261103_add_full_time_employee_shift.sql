INSERT INTO turno (nombre, estado)
VALUES ('Tiempo Completo', 'ACTIVO')
ON DUPLICATE KEY UPDATE estado = 'ACTIVO';
