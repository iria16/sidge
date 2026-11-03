ALTER TABLE `inscripcion`
    ADD COLUMN `documentos_evaluados_en` datetime DEFAULT NULL
        AFTER `id_institucion`;

ALTER TABLE `estudiante_documento`
    ADD COLUMN `id_persona_estudiante` int(11) DEFAULT NULL
        AFTER `id_estudiante`;

ALTER TABLE `estudiante_documento`
    DROP INDEX `uk_estudiante_documento`,
    ADD COLUMN `requerido` tinyint(1) DEFAULT NULL
        AFTER `consignado`,
    ADD COLUMN `id_persona_estudiante_unico` int(11) NOT NULL DEFAULT 0
        AFTER `id_persona_estudiante`,
    ADD UNIQUE KEY `uk_estudiante_documento_instancia`
        (`id_estudiante`,`id_tipo_documento`,`id_persona_estudiante_unico`),
    ADD KEY `idx_estudiante_documento_persona` (`id_persona_estudiante`);

ALTER TABLE `estudiante_documento`
    ADD CONSTRAINT `fk_estudiante_documento_persona_estudiante`
        FOREIGN KEY (`id_persona_estudiante`)
        REFERENCES `persona_estudiante` (`id_persona_estudiante`)
        ON DELETE RESTRICT ON UPDATE CASCADE;
