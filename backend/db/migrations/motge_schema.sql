-- SQL Schema Migration: MOTGE-BOTS Database
-- MAS QUE FIANZAS - Core Asegurador v3.0

-- 1. Tabla de Incidencias / Anomalías Registradas
CREATE TABLE IF NOT EXISTS `motge_incidencias` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `firma_error` VARCHAR(64) NOT NULL,
  `modulo_afectado` VARCHAR(100) NOT NULL,
  `descripcion_error` TEXT NOT NULL,
  `stack_trace` LONGTEXT NOT NULL,
  `fecha_registro` DATETIME NOT NULL,
  INDEX (`firma_error`),
  INDEX (`modulo_afectado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tabla de Experiencia y Soluciones Históricas
CREATE TABLE IF NOT EXISTS `motge_experiencia` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `firma_error` VARCHAR(64) NOT NULL UNIQUE,
  `solucion_propuesta` TEXT NOT NULL,
  `comando_autocuracion` TEXT DEFAULT NULL,
  `incidencia_id` INT DEFAULT NULL,
  `exito_confirmado` TINYINT(1) DEFAULT 0,
  INDEX (`firma_error`),
  FOREIGN KEY (`incidencia_id`) REFERENCES `motge_incidencias`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Semilla de Conocimiento Inicial (Heurística Estructural para Autocuración)
INSERT IGNORE INTO `motge_experiencia` (`firma_error`, `solucion_propuesta`, `comando_autocuracion`, `exito_confirmado`) VALUES
('db_missing_usuarios', 'Reconstruir la tabla de usuarios del sistema a partir del esquema semilla.', 'REBUILD_TABLE:usuarios', 1),
('db_missing_sesiones', 'Reconstruir la tabla de sesiones de usuario del sistema para restaurar inicios de sesión.', 'REBUILD_TABLE:sesiones_usuario', 1),
('ncf_sequence_corrupt', 'Recalibrar secuencias de NCF de comprobantes fiscales.', 'RECALIBRATE_NCF', 1),
('reset_permissions_matrix', 'Restablecer privilegios de accesos del administrador y perfiles asociados.', 'RESET_PERMISSIONS', 1);
