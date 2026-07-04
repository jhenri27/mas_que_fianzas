<?php
require_once dirname(__FILE__) . '/config.php';

$db = Database::getInstance()->getConnection();

try {
    echo "=== INICIANDO MIGRACIÓN DE REGLAS DE NEGOCIO Y SOLICITUDES ===\n";

    // 1. Asegurar columnas nuevas en reglas_negocio
    $columns = [];
    $res = $db->query("DESCRIBE reglas_negocio");
    while ($row = $res->fetch_assoc()) {
        $columns[] = strtolower($row['Field']);
    }

    if (!in_array('relaciones', $columns)) {
        $db->query("ALTER TABLE reglas_negocio ADD COLUMN relaciones TEXT NULL AFTER categoria");
        echo "Column 'relaciones' added to 'reglas_negocio'.\n";
    }
    if (!in_array('mejores_practicas', $columns)) {
        $db->query("ALTER TABLE reglas_negocio ADD COLUMN mejores_practicas TEXT NULL AFTER descripcion");
        echo "Column 'mejores_practicas' added to 'reglas_negocio'.\n";
    }

    // 2. Crear tabla reglas_negocio_solicitudes
    $table_check = $db->query("SHOW TABLES LIKE 'reglas_negocio_solicitudes'");
    if (!$table_check || $table_check->num_rows === 0) {
        $sql = "CREATE TABLE reglas_negocio_solicitudes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            regla_id INT NULL,
            tipo_solicitud VARCHAR(50) NOT NULL,
            codigo VARCHAR(100) NOT NULL,
            nombre VARCHAR(255) NOT NULL,
            categoria VARCHAR(100) NOT NULL,
            descripcion TEXT NULL,
            valor_configurado VARCHAR(255) NULL,
            relaciones TEXT NULL,
            mejores_practicas TEXT NULL,
            estado VARCHAR(50) DEFAULT 'pendiente',
            tipo_validacion VARCHAR(50) NOT NULL,
            usuario_solicita INT NOT NULL,
            usuario_aprueba INT NULL,
            fecha_solicitud TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            fecha_resolucion TIMESTAMP NULL,
            justificacion TEXT NOT NULL,
            motivo_resolucion TEXT NULL,
            FOREIGN KEY (usuario_solicita) REFERENCES usuarios(id),
            FOREIGN KEY (usuario_aprueba) REFERENCES usuarios(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        
        $db->query($sql);
        echo "Table 'reglas_negocio_solicitudes' created successfully.\n";
    } else {
        echo "Table 'reglas_negocio_solicitudes' already exists.\n";
    }

    echo "✅ Migración de Reglas de Negocio finalizada con éxito.\n";

} catch (Exception $e) {
    echo "❌ Error en la migración: " . $e->getMessage() . "\n";
}
?>
