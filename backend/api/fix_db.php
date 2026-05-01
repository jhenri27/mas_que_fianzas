<?php
require_once '../config.php';
$db = Database::getInstance()->getConnection();
$sql = "ALTER TABLE cotizaciones MODIFY cobertura TEXT";
if ($db->query($sql)) {
    echo "<h1>OK: Tabla 'cotizaciones' actualizada (cobertura -> TEXT)</h1>";
} else {
    echo "<h1>Error: " . $db->error . "</h1>";
}
?>
