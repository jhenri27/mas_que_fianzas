<?php
require_once dirname(__FILE__) . '/../config.php';
$db = Database::getInstance()->getConnection();

echo "=== TABLES ===\n";
$res = $db->query("SHOW TABLES");
while ($row = $res->fetch_row()) {
    if (strpos($row[0], 'tarif') !== false || strpos($row[0], 'aseguradora') !== false || strpos($row[0], 'tasa') !== false || strpos($row[0], 'tarifa') !== false) {
        echo $row[0] . "\n";
    }
}
?>
