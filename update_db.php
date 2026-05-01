<?php
require_once dirname(__FILE__) . '/backend/config.php';
try {
    $db = Database::getInstance()->getConnection();
    // Check if font_weight exists
    $res = $db->query("SHOW COLUMNS FROM pdf_campos LIKE 'font_weight'");
    if ($res->num_rows == 0) {
        $db->query("ALTER TABLE pdf_campos ADD COLUMN font_weight VARCHAR(20) DEFAULT 'normal'");
        echo "Column font_weight added.";
    } else {
        echo "Column font_weight already exists.";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
