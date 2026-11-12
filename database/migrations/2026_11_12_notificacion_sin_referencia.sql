-- ===========================================================================
-- SIGDE - Notificación sin puntero de referencia
-- Fecha: 2026-11-12
-- Base: bd_sigde (MariaDB 11.x)
--
-- notificacion.id_referencia solo se escribía y nunca se leía: la navegación
-- usa url_destino. No tiene FK ni índice. Se elimina para no conservar una
-- columna muerta.
-- ===========================================================================

SET NAMES utf8mb4;

ALTER TABLE `notificacion`
  DROP COLUMN `id_referencia`;
