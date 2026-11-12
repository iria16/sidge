-- ===========================================================================
-- SIGDE - Estructura académica sin enlace de WhatsApp
-- Fecha: 2026-11-12
-- Base: bd_sigde (MariaDB 11.x)
--
-- estructura_academica.enlace_whatsapp pertenecía a una función que ya no se
-- muestra en las vistas del docente: no hay formulario, modal ni botones que
-- lean o guarden ese enlace. La columna quedó muerta y se elimina junto con
-- sus referencias en el código.
-- ===========================================================================

SET NAMES utf8mb4;

ALTER TABLE `estructura_academica`
  DROP COLUMN `enlace_whatsapp`;
