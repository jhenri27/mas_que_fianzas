-- =====================================================
-- MIGRACIÓN NOFTRAB v4.0 - MÁS QUE FIANZAS
-- =====================================================
USE masque_fianzas_integrada_01;

-- 1. Crear tabla historial_ajustes
CREATE TABLE IF NOT EXISTS historial_ajustes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    modulo_afectado VARCHAR(50) NOT NULL,
    tabla_afectada VARCHAR(50) NOT NULL,
    registro_id INT NOT NULL,
    valor_anterior JSON NOT NULL,
    valor_nuevo JSON NOT NULL,
    justificacion TEXT NOT NULL,
    fecha_ajuste TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    direccion_ip VARCHAR(45) NOT NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Asegurar que 'cotizaciones' tenga columna 'creado_por'
-- Usamos un procedimiento almacenado para añadir la columna solo si no existe
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS AsegurarColumnasAuditoria()
BEGIN
    -- Cotizaciones creado_por
    IF NOT EXISTS (
        SELECT * FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = 'masque_fianzas_integrada_01' 
        AND TABLE_NAME = 'cotizaciones' 
        AND COLUMN_NAME = 'creado_por'
    ) THEN
        ALTER TABLE cotizaciones ADD COLUMN creado_por INT DEFAULT 1;
        ALTER TABLE cotizaciones ADD CONSTRAINT fk_cotizaciones_creador 
            FOREIGN KEY (creado_por) REFERENCES usuarios(id) ON DELETE SET NULL;
    END IF;

    -- Polizas emitida_por
    IF NOT EXISTS (
        SELECT * FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = 'masque_fianzas_integrada_01' 
        AND TABLE_NAME = 'polizas' 
        AND COLUMN_NAME = 'emitida_por'
    ) THEN
        ALTER TABLE polizas ADD COLUMN emitida_por INT DEFAULT 1;
        ALTER TABLE polizas ADD CONSTRAINT fk_polizas_emisor 
            FOREIGN KEY (emitida_por) REFERENCES usuarios(id) ON DELETE SET NULL;
    END IF;

    -- Pagos registrado_por
    IF NOT EXISTS (
        SELECT * FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = 'masque_fianzas_integrada_01' 
        AND TABLE_NAME = 'pagos' 
        AND COLUMN_NAME = 'registrado_por'
    ) THEN
        ALTER TABLE pagos ADD COLUMN registrado_por INT DEFAULT 1;
        ALTER TABLE pagos ADD CONSTRAINT fk_pagos_registrador 
            FOREIGN KEY (registrado_por) REFERENCES usuarios(id) ON DELETE SET NULL;
    END IF;
END //
DELIMITER ;

CALL AsegurarColumnasAuditoria();
DROP PROCEDURE IF EXISTS AsegurarColumnasAuditoria;

-- 3. Crear columna 'solo_propios' en la tabla permisos_perfil si no existe
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS AsegurarSoloPropiosPermiso()
BEGIN
    IF NOT EXISTS (
        SELECT * FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = 'masque_fianzas_integrada_01' 
        AND TABLE_NAME = 'permisos_perfil' 
        AND COLUMN_NAME = 'solo_propios'
    ) THEN
        ALTER TABLE permisos_perfil ADD COLUMN solo_propios BOOLEAN DEFAULT 0;
    END IF;
END //
DELIMITER ;

CALL AsegurarSoloPropiosPermiso();
DROP PROCEDURE IF EXISTS AsegurarSoloPropiosPermiso;

-- 4. Registrar auditoría inicial de la migración
INSERT INTO auditoria_accesos 
(usuario_id, tipo_evento, modulo_accedido, funcion_ejecutada, descripcion_evento, direccion_ip, navegador_user_agent, resultado, operacion_realizada, fecha_evento)
VALUES 
(1, 'accion_fallida', 'configuracion', 'MIGRACION_NOFTRAB', 'Migración NOFTRAB v4.0 aplicada exitosamente a la base de datos', '127.0.0.1', 'CLI', 'exitoso', 'update', NOW());
