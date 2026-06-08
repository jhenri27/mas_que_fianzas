<?php
require_once dirname(__FILE__) . '/config.php';

$db = Database::getInstance()->getConnection();

try {
    echo "=== INICIANDO MIGRACIÓN DEL MODELADOR DE PDF ===\n";

    // 1. Asegurar estructura de pdf_plantillas
    // La tabla pdf_plantillas ya existe, verifiquemos y agreguemos columnas si faltan.
    $columns_plantillas = [];
    $res = $db->query("DESCRIBE pdf_plantillas");
    while ($row = $res->fetch_assoc()) {
        $columns_plantillas[] = strtolower($row['Field']);
    }

    if (!in_array('aseguradora_id', $columns_plantillas)) {
        $db->query("ALTER TABLE pdf_plantillas ADD COLUMN aseguradora_id INT NULL AFTER id");
        echo "Column 'aseguradora_id' added to 'pdf_plantillas'.\n";
    }
    if (!in_array('aseguradora_nombre', $columns_plantillas)) {
        $db->query("ALTER TABLE pdf_plantillas ADD COLUMN aseguradora_nombre VARCHAR(255) NULL AFTER aseguradora_id");
        echo "Column 'aseguradora_nombre' added to 'pdf_plantillas'.\n";
    }

    // 2. Asegurar estructura de pdf_campos
    $columns_campos = [];
    $res = $db->query("DESCRIBE pdf_campos");
    while ($row = $res->fetch_assoc()) {
        $columns_campos[] = strtolower($row['Field']);
    }

    if (!in_array('pagina', $columns_campos)) {
        $db->query("ALTER TABLE pdf_campos ADD COLUMN pagina INT DEFAULT 1 AFTER plantilla_id");
        echo "Column 'pagina' added to 'pdf_campos'.\n";
    }
    if (!in_array('nombre_campo_pdf', $columns_campos)) {
        $db->query("ALTER TABLE pdf_campos ADD COLUMN nombre_campo_pdf VARCHAR(150) NULL AFTER variable");
        echo "Column 'nombre_campo_pdf' added to 'pdf_campos'.\n";
    }
    if (!in_array('fondo_opaco', $columns_campos)) {
        $db->query("ALTER TABLE pdf_campos ADD COLUMN fondo_opaco TINYINT(1) DEFAULT 0 AFTER ancho");
        echo "Column 'fondo_opaco' added to 'pdf_campos'.\n";
    }

    echo "✅ Estructura de tablas para pdf_plantillas y pdf_campos verificada y actualizada con éxito.\n";

} catch (Exception $e) {
    echo "❌ Error en la migración: " . $e->getMessage() . "\n";
}
?>
