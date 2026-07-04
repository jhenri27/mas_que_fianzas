<?php
require_once dirname(__DIR__) . '/config.php';

$db = Database::getInstance()->getConnection();

try {
    echo "=== INICIANDO MIGRACIÓN DEL CENTRO DE NEGOCIOS ===\n";

    // 1. Crear tabla enlaces_venta_online
    $check_enlaces = $db->query("SHOW TABLES LIKE 'enlaces_venta_online'");
    if (!$check_enlaces || $check_enlaces->num_rows === 0) {
        $sql = "CREATE TABLE enlaces_venta_online (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NOT NULL,
            codigo_enlace VARCHAR(64) NOT NULL UNIQUE,
            aseguradora VARCHAR(100) NOT NULL,
            ramo VARCHAR(80) NOT NULL,
            descripcion VARCHAR(255) NULL,
            descuento_aplicado DECIMAL(5,2) DEFAULT 0.00,
            vistas INT DEFAULT 0,
            conversiones INT DEFAULT 0,
            estado ENUM('activo', 'inactivo') DEFAULT 'activo',
            fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            fecha_expiracion DATETIME NULL,
            FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $db->query($sql);
        echo "Table 'enlaces_venta_online' created successfully.\n";
    } else {
        echo "Table 'enlaces_venta_online' already exists.\n";
    }

    // 2. Crear tabla bonos_configuracion
    $check_bonos = $db->query("SHOW TABLES LIKE 'bonos_configuracion'");
    if (!$check_bonos || $check_bonos->num_rows === 0) {
        $sql = "CREATE TABLE bonos_configuracion (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre_bono VARCHAR(150) NOT NULL,
            descripcion TEXT NULL,
            tipo_meta ENUM('ventas_cantidad', 'monto_prima') NOT NULL,
            valor_meta DECIMAL(15,2) NOT NULL,
            monto_bono DECIMAL(15,2) NOT NULL,
            perfil_id INT NULL,
            fecha_inicio DATE NOT NULL,
            fecha_fin DATE NOT NULL,
            estado ENUM('activo', 'inactivo') DEFAULT 'activo',
            fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $db->query($sql);
        echo "Table 'bonos_configuracion' created successfully.\n";
    } else {
        echo "Table 'bonos_configuracion' already exists.\n";
    }

    // 3. Crear tabla bonos_logros
    $check_logros = $db->query("SHOW TABLES LIKE 'bonos_logros'");
    if (!$check_logros || $check_logros->num_rows === 0) {
        $sql = "CREATE TABLE bonos_logros (
            id INT AUTO_INCREMENT PRIMARY KEY,
            bono_id INT NOT NULL,
            usuario_id INT NOT NULL,
            progreso_actual DECIMAL(15,2) DEFAULT 0.00,
            completado TINYINT(1) DEFAULT 0,
            fecha_completado DATETIME NULL,
            pagado TINYINT(1) DEFAULT 0,
            fecha_pago DATE NULL,
            referencia_pago VARCHAR(100) NULL,
            FOREIGN KEY (bono_id) REFERENCES bonos_configuracion(id) ON DELETE CASCADE,
            FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $db->query($sql);
        echo "Table 'bonos_logros' created successfully.\n";
    } else {
        echo "Table 'bonos_logros' already exists.\n";
    }

    echo "✅ Migración del Centro de Negocios finalizada con éxito.\n";

} catch (Exception $e) {
    echo "❌ Error en la migración: " . $e->getMessage() . "\n";
}
?>
