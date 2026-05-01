<?php
require_once dirname(__FILE__) . '/config.php';

try {
    $db = Database::getInstance()->getConnection();

    $queries = [
        "CREATE TABLE IF NOT EXISTS pdf_plantillas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            aseguradora_id INT NULL,
            nombre VARCHAR(255) NOT NULL,
            archivo_base VARCHAR(255) NOT NULL,
            tipo_archivo VARCHAR(50) DEFAULT 'pdf',
            ancho_mm DECIMAL(10,2) DEFAULT 210,
            alto_mm DECIMAL(10,2) DEFAULT 297,
            estado TINYINT DEFAULT 1,
            fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            HTML_content TEXT NULL
        )",
        "CREATE TABLE IF NOT EXISTS pdf_campos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            plantilla_id INT NOT NULL,
            variable VARCHAR(100) NOT NULL,
            pos_x DECIMAL(10,2) NOT NULL,
            pos_y DECIMAL(10,2) NOT NULL,
            font_size INT DEFAULT 10,
            font_family VARCHAR(50) DEFAULT 'helvetica',
            color VARCHAR(20) DEFAULT '#000000',
            font_weight VARCHAR(20) DEFAULT 'normal',
            alineacion VARCHAR(20) DEFAULT 'left',
            ancho DECIMAL(10,2) NULL,
            FOREIGN KEY (plantilla_id) REFERENCES pdf_plantillas(id) ON DELETE CASCADE
        )"
    ];

    foreach ($queries as $query) {
        if (!$db->query($query)) {
            throw new Exception("Error al ejecutar query: " . $db->error);
        }
    }
    
    echo "Tablas de plantillas PDF creadas exitosamente.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
