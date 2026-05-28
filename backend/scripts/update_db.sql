ALTER TABLE `permisos_perfil`
ADD COLUMN `importar_datos` TINYINT(1) DEFAULT 0 AFTER `exportar_datos`,
ADD COLUMN `imprimir_datos` TINYINT(1) DEFAULT 0 AFTER `importar_datos`;
