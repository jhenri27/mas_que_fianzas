<?php
require_once __DIR__ . '/../config.php';
$db = Database::getInstance()->getConnection();
foreach(['tarifas_seguro', 'productos', 'integraciones_aseguradoras', 'companias_registradas'] as $t) {
    echo "\n--- $t ---\n";
    $res = $db->query("SHOW COLUMNS FROM $t");
    if ($res) {
        while($row = $res->fetch_assoc()) {
            echo "  " . $row['Field'] . ' (' . $row['Type'] . ')' . "\n";
        }
    } else {
        echo "  Table does not exist or error: " . $db->error . "\n";
    }
}
?>
