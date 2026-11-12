-- ===========================================================================
-- SIGDE - Reingreso sin flujo de aprobación
-- Fecha: 2026-11-12
-- Base: bd_sigde (MariaDB 11.x)
--
-- La tabla reingreso registra reincorporaciones ya formalizadas, no
-- solicitudes pendientes. Los campos estado_solicitud, fecha_resolucion e
-- id_usuario_registro se agregaron en la migración del 2026-11-11 para un
-- flujo de aprobación que no se va a implementar, y hoy solo se escriben.
-- Se eliminan para no dejar columnas muertas.
--
-- Ejecutar una sola vez después de 2026_11_11_normalizacion_diseno.sql.
-- ===========================================================================

SET NAMES utf8mb4;

ALTER TABLE `reingreso`
  DROP FOREIGN KEY `fk_reingreso_usuario_registro`,
  DROP KEY `idx_reingreso_usuario_registro`,
  DROP COLUMN `estado_solicitud`,
  DROP COLUMN `fecha_resolucion`,
  DROP COLUMN `id_usuario_registro`;
