-- Migracion
ALTER TABLE retiro ADD COLUMN id_persona_representante INT(11) NULL DEFAULT NULL AFTER id_motivo_retiro, ADD COLUMN fecha_retiro DATE NULL DEFAULT NULL AFTER fecha_solicitud, ADD KEY idx_retiro_persona_rep (id_persona_representante), ADD CONSTRAINT fk_retiro_persona_rep FOREIGN KEY (id_persona_representante) REFERENCES persona (id_persona) ON UPDATE CASCADE ON DELETE SET NULL;
