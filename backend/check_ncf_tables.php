<?php
require_once 'config.php';
$db = Database::getInstance()->getConnection();

echo "--- TABLAS NCF ---\n";
$res = $db->query("SHOW TABLES LIKE 'cf_ncf%'");
while($row = $res->fetch_array()) {
    echo $row[0] . "\n";
    $res2 = $db->query("DESCRIBE " . $row[0]);
    while($col = $res2->fetch_assoc()) {
        echo "  - " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
}
