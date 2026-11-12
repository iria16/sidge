-- ===========================================================================
-- SIGDE - Coherencia entre aula y año escolar
-- Fecha: 2026-11-12
-- Base: bd_sigde (MariaDB 11.x)
--
-- matricula, inscripcion y resultado_academico guardan id_anio_escolar y
-- id_estructura_academica por separado. La estructura ya pertenece a un año,
-- pero la base no obligaba a que ambas columnas coincidieran. Esta migración
-- reemplaza la FK simple hacia estructura_academica por una FK compuesta
-- (id_estructura_academica, id_anio_escolar), de modo que el aula y el año
-- siempre formen una pareja válida.
--
-- No limita los años históricos: solo exige que, si se registra una matrícula
-- del año X, el aula indicada pertenezca también al año X. Los datos actuales
-- ya cumplen la regla (0 discrepancias).
--
-- Las sentencias van separadas a propósito: MariaDB rechaza con errno 121
-- cuando se elimina y se vuelve a crear el mismo índice/FK dentro de un solo
-- ALTER TABLE.
-- ===========================================================================

SET NAMES utf8mb4;

ALTER TABLE `estructura_academica`
  ADD UNIQUE KEY `uk_estructura_id_anio` (`id_estructura_academica`, `id_anio_escolar`);

ALTER TABLE `matricula`
  DROP FOREIGN KEY `fk_matricula_estructura`;

ALTER TABLE `matricula`
  DROP KEY `idx_matricula_estructura`;

ALTER TABLE `matricula`
  ADD KEY `idx_matricula_estructura` (`id_estructura_academica`, `id_anio_escolar`);

ALTER TABLE `matricula`
  ADD CONSTRAINT `fk_matricula_estructura`
    FOREIGN KEY (`id_estructura_academica`, `id_anio_escolar`)
    REFERENCES `estructura_academica` (`id_estructura_academica`, `id_anio_escolar`)
    ON UPDATE CASCADE;

ALTER TABLE `inscripcion`
  DROP FOREIGN KEY `fk_inscripcion_estructura`;

ALTER TABLE `inscripcion`
  DROP KEY `idx_inscripcion_estructura`;

ALTER TABLE `inscripcion`
  ADD KEY `idx_inscripcion_estructura` (`id_estructura_academica`, `id_anio_escolar`);

ALTER TABLE `inscripcion`
  ADD CONSTRAINT `fk_inscripcion_estructura`
    FOREIGN KEY (`id_estructura_academica`, `id_anio_escolar`)
    REFERENCES `estructura_academica` (`id_estructura_academica`, `id_anio_escolar`)
    ON UPDATE CASCADE;

ALTER TABLE `resultado_academico`
  DROP FOREIGN KEY `fk_res_acad_estructura`;

ALTER TABLE `resultado_academico`
  DROP KEY `idx_resultado_estructura`;

ALTER TABLE `resultado_academico`
  ADD KEY `idx_resultado_estructura` (`id_estructura_academica`, `id_anio_escolar`);

ALTER TABLE `resultado_academico`
  ADD CONSTRAINT `fk_res_acad_estructura`
    FOREIGN KEY (`id_estructura_academica`, `id_anio_escolar`)
    REFERENCES `estructura_academica` (`id_estructura_academica`, `id_anio_escolar`)
    ON UPDATE CASCADE;
