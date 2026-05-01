<?php
require_once 'c:/wamp64/www/PLATAFORMA_INTEGRADA/backend/config.php';
try {
    $db = Database::getInstance()->getConnection();
    // Check if logo_b64 exists
    $res = $db->query("SHOW COLUMNS FROM pdf_plantillas LIKE 'logo_b64'");
    if ($res->num_rows == 0) {
        $db->query("ALTER TABLE pdf_plantillas ADD COLUMN logo_b64 MEDIUMTEXT");
        echo "Column logo_b64 added.";
    } else {
        echo "Column logo_b64 already exists.";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
