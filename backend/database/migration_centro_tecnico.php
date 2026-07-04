<?php
/**
 * Migración: Estructura de base de datos para Centro Técnico de Seguros (Fase 2)
 * MAS QUE FIANZAS
 */

require_once dirname(__DIR__) . '/config.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== MIGRACIÓN: CENTRO TÉCNICO DE SEGUROS ===\n\n";

try {
    $db = Database::getInstance()->getConnection();
    
    // 1. Crear tabla polizas_ajustes_solicitudes
    echo "Creando tabla 'polizas_ajustes_solicitudes'...\n";
    $sqlAjustes = "CREATE TABLE IF NOT EXISTS polizas_ajustes_solicitudes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        poliza_id INT NOT NULL,
        usuario_solicita INT NOT NULL,
        fecha_solicitud DATETIME DEFAULT CURRENT_TIMESTAMP,
        campos_originales TEXT NOT NULL,
        campos_nuevos TEXT NOT NULL,
        justificacion TEXT NOT NULL,
        soporte_documental VARCHAR(255) NULL,
        estado ENUM('pendiente', 'aprobada', 'rechazada') DEFAULT 'pendiente',
        usuario_aprueba INT NULL,
        fecha_resolucion DATETIME NULL,
        motivo_resolucion TEXT NULL,
        asiento_ajuste_id INT NULL,
        FOREIGN KEY (poliza_id) REFERENCES polizas(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    if ($db->query($sqlAjustes)) {
        echo "[ÉXITO] Tabla 'polizas_ajustes_solicitudes' creada o ya existente.\n";
    } else {
        throw new Exception("Error al crear tabla 'polizas_ajustes_solicitudes': " . $db->error);
    }
    
    // 2. Crear tabla reglas_negocio
    echo "\nCreando tabla 'reglas_negocio'...\n";
    $sqlReglas = "CREATE TABLE IF NOT EXISTS reglas_negocio (
        id INT AUTO_INCREMENT PRIMARY KEY,
        codigo VARCHAR(50) UNIQUE NOT NULL,
        nombre VARCHAR(150) NOT NULL,
        categoria VARCHAR(50) NOT NULL,
        descripcion TEXT NOT NULL,
        valor_configurado VARCHAR(255) NOT NULL,
        estado ENUM('activo', 'inactivo') DEFAULT 'activo',
        modificado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        modificado_por INT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    if ($db->query($sqlReglas)) {
        echo "[ÉXITO] Tabla 'reglas_negocio' creada o ya existente.\n";
    } else {
        throw new Exception("Error al crear tabla 'reglas_negocio': " . $db->error);
    }
    
    // 3. Insertar semillas (seeds) de reglas de negocio
    echo "\nSembrando reglas de negocio iniciales...\n";
    $sqlInsertSeeds = "INSERT INTO reglas_negocio (codigo, nombre, categoria, descripcion, valor_configurado, estado) VALUES
    ('LIM_DESC_MAX', 'Límite de Descuento Máximo', 'descuento', 'Porcentaje de descuento máximo que un agente comercial puede aplicar sin requerir aprobación (ej. 15%).', '15', 'activo'),
    ('EDAD_MIN_COND', 'Edad Mínima de Conductor', 'vehiculo', 'Edad mínima del conductor permitida para emitir un seguro de vehículo.', '18', 'activo'),
    ('ANTIG_MAX_VEH', 'Antigüedad Máxima del Vehículo', 'vehiculo', 'Cantidad máxima de años de antigüedad permitida para un vehículo en seguros de ley.', '20', 'activo'),
    ('RECAR_USO_COM', 'Recargo por Uso Comercial', 'emision', 'Porcentaje de recargo aplicado sobre la prima neta para vehículos de uso comercial (ej. 10%).', '10', 'activo'),
    ('TASA_COM_MAX', 'Comisión Máxima de Agente', 'comision', 'Porcentaje máximo de comisión permitido por defecto para un agente comercial.', '25', 'activo')
    ON DUPLICATE KEY UPDATE nombre=VALUES(nombre), descripcion=VALUES(descripcion);";
    
    if ($db->query($sqlInsertSeeds)) {
        echo "[ÉXITO] Reglas de negocio iniciales sembradas con éxito.\n";
    } else {
        throw new Exception("Error al sembrar reglas de negocio: " . $db->error);
    }
    
    echo "\n=== MIGRACIÓN COMPLETADA EXITOSAMENTE ===";
    
} catch (Exception $e) {
    echo "\n[ERROR CRÍTICO]: " . $e->getMessage() . "\n";
    exit(1);
}
