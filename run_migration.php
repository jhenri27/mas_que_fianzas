<?php
require_once dirname(__FILE__) . '/backend/config.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Read the migration file
    $sql_file = dirname(__FILE__) . '/database/migration_noftrab_v4.sql';
    if (!file_exists($sql_file)) {
        die("Error: migration_noftrab_v4.sql not found.\n");
    }
    
    $content = file_get_contents($sql_file);
    
    // Remove comments
    $content = preg_replace('/--.*$/m', '', $content);
    
    // We want to execute statements. Let's split them carefully.
    // Instead of complex splitting, we can execute the queries one by one.
    // Let's rewrite the procedure creation and other statements to run sequentially.
    
    echo "Starting migration...\n";
    
    // 1. Create table historial_ajustes
    $q1 = "CREATE TABLE IF NOT EXISTS historial_ajustes (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($db->query($q1)) {
        echo "✓ Table 'historial_ajustes' verified/created.\n";
    } else {
        throw new Exception("Error creating table 'historial_ajustes': " . $db->error);
    }
    
    // 2. Ensure cotizaciones has creado_por, polizas has emitida_por, pagos has registrado_por
    // We can do this with simple check and alter queries in PHP instead of SQL procedures to avoid delimiter parsing issues!
    
    // Cotizaciones creado_por
    $res = $db->query("SHOW COLUMNS FROM cotizaciones LIKE 'creado_por'");
    if ($res->num_rows == 0) {
        if ($db->query("ALTER TABLE cotizaciones ADD COLUMN creado_por INT DEFAULT 1")) {
            $db->query("ALTER TABLE cotizaciones ADD CONSTRAINT fk_cotizaciones_creador FOREIGN KEY (creado_por) REFERENCES usuarios(id) ON DELETE SET NULL");
            echo "✓ Column 'creado_por' added to 'cotizaciones'.\n";
        } else {
            throw new Exception("Error adding 'creado_por' to 'cotizaciones': " . $db->error);
        }
    } else {
        echo "✓ Column 'creado_por' already exists in 'cotizaciones'.\n";
    }
    
    // Polizas emitida_por
    $res = $db->query("SHOW COLUMNS FROM polizas LIKE 'emitida_por'");
    if ($res->num_rows == 0) {
        if ($db->query("ALTER TABLE polizas ADD COLUMN emitida_por INT DEFAULT 1")) {
            $db->query("ALTER TABLE polizas ADD CONSTRAINT fk_polizas_emisor FOREIGN KEY (emitida_por) REFERENCES usuarios(id) ON DELETE SET NULL");
            echo "✓ Column 'emitida_por' added to 'polizas'.\n";
        } else {
            throw new Exception("Error adding 'emitida_por' to 'polizas': " . $db->error);
        }
    } else {
        echo "✓ Column 'emitida_por' already exists in 'polizas'.\n";
    }
    
    // Pagos registrado_por
    $res = $db->query("SHOW COLUMNS FROM pagos LIKE 'registrado_por'");
    if ($res->num_rows == 0) {
        if ($db->query("ALTER TABLE pagos ADD COLUMN registrado_por INT DEFAULT 1")) {
            $db->query("ALTER TABLE pagos ADD CONSTRAINT fk_pagos_registrador FOREIGN KEY (registrado_por) REFERENCES usuarios(id) ON DELETE SET NULL");
            echo "✓ Column 'registrado_por' added to 'pagos'.\n";
        } else {
            throw new Exception("Error adding 'registrado_por' to 'pagos': " . $db->error);
        }
    } else {
        echo "✓ Column 'registrado_por' already exists in 'pagos'.\n";
    }
    
    // 3. Ensure permisos_perfil has solo_propios
    $res = $db->query("SHOW COLUMNS FROM permisos_perfil LIKE 'solo_propios'");
    if ($res->num_rows == 0) {
        if ($db->query("ALTER TABLE permisos_perfil ADD COLUMN solo_propios BOOLEAN DEFAULT 0")) {
            echo "✓ Column 'solo_propios' added to 'permisos_perfil'.\n";
        } else {
            throw new Exception("Error adding 'solo_propios' to 'permisos_perfil': " . $db->error);
        }
    } else {
        echo "✓ Column 'solo_propios' already exists in 'permisos_perfil'.\n";
    }
    
    // 4. Record initial audit
    $desc = 'Migración NOFTRAB v4.0 aplicada exitosamente a la base de datos';
    $ip = '127.0.0.1';
    $db->query("INSERT INTO auditoria_accesos 
        (usuario_id, tipo_evento, modulo_accedido, funcion_ejecutada, descripcion_evento, direccion_ip, navegador_user_agent, resultado, operacion_realizada, fecha_evento)
        VALUES 
        (1, 'accion_fallida', 'configuracion', 'MIGRACION_NOFTRAB', '$desc', '$ip', 'CLI', 'exitoso', 'update', NOW())");
        
    echo "✓ Initial audit entry recorded.\n";
    echo "Migration completed successfully!\n";
    
} catch (Exception $e) {
    die("❌ Migration Error: " . $e->getMessage() . "\n");
}
?>
