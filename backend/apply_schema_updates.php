<?php
/**
 * Script de Actualización de Esquema v3.1
 * MAS QUE FIANZAS
 */

require_once 'config.php';

try {
    $db = Database::getInstance()->getConnection();
    
    echo "Iniciando actualización de esquema...\n";
    
    // 1. Agregar cuota_total a tabla polizas si no existe
    $result = $db->query("SHOW COLUMNS FROM polizas LIKE 'cuota_total'");
    if ($result->num_rows == 0) {
        if ($db->query("ALTER TABLE polizas ADD COLUMN cuota_total INT DEFAULT 1 AFTER periodicidad_pago")) {
            echo "[OK] Columna 'cuota_total' añadida a 'polizas'.\n";
        } else {
            echo "[ERROR] No se pudo añadir 'cuota_total': " . $db->error . "\n";
        }
    } else {
        echo "[INFO] La columna 'cuota_total' ya existe en 'polizas'.\n";
    }

    // 2. Asegurar que tipo_poliza tenga los enums correctos
    $db->query("ALTER TABLE polizas MODIFY COLUMN tipo_poliza ENUM('Individual','Flotilla','Colectiva') DEFAULT 'Individual'");
    echo "[OK] Columna 'tipo_poliza' verificada.\n";

    echo "\nActualización completada exitosamente.\n";

} catch (Exception $e) {
    echo "[CRITICAL ERROR] " . $e->getMessage() . "\n";
}
?>
