-- 🛡️ MIGRACIÓN: Optimización de Índices Relacionales y Llaves Foráneas (Normas NOFTRAB / Cláusula 4-VAF)
-- Objetivo: Indexar campos clave de unión para evitar cuellos de botella en consultas JOIN multimodulares.

-- 1. Indexar perfil_id en bonos_configuracion
ALTER TABLE `bonos_configuracion` ADD INDEX `idx_bonos_perfil` (`perfil_id`);

-- 2. Indexar cliente_id en cf_ncf
ALTER TABLE `cf_ncf` ADD INDEX `idx_ncf_cliente` (`cliente_id`);

-- 3. Indexar usuario_id en cf_ncf_log
ALTER TABLE `cf_ncf_log` ADD INDEX `idx_ncf_log_usuario` (`usuario_id`);

-- 4. Indexar cotizacion_id en fianzas
ALTER TABLE `fianzas` ADD INDEX `idx_fianzas_cotizacion` (`cotizacion_id`);

-- 5. Indexar usuario_id en mensajes_ticket
ALTER TABLE `mensajes_ticket` ADD INDEX `idx_msg_ticket_usuario` (`usuario_id`);

-- 6. Indexar aseguradora_id en pdf_plantillas
ALTER TABLE `pdf_plantillas` ADD INDEX `idx_pdf_aseguradora` (`aseguradora_id`);
