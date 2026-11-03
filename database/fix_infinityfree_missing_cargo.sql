-- Reparación no destructiva para una base InfinityFree que ya importó
-- schema_infinityfree.sql sin la tabla cargo.
-- Ejecutar una sola vez en phpMyAdmin. No elimina ni modifica datos existentes.

CREATE TABLE IF NOT EXISTS `cargo` (
  `id_cargo` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  PRIMARY KEY (`id_cargo`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `cargo` (`id_cargo`, `nombre`, `estado`) VALUES
(1,'Director','ACTIVO'),
(2,'Subdirector','ACTIVO'),
(3,'Docente de Aula','ACTIVO'),
(4,'Coordinador Pedagógico','ACTIVO'),
(5,'Administrador del Sistema','ACTIVO'),
(6,'Psicopedagogo(a)','ACTIVO'),
(7,'Docente de Educación Especial','ACTIVO'),
(8,'Secretaria','ACTIVO');
