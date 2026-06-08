<?php
require_once dirname(__FILE__) . '/../config.php';
$db = Database::getInstance()->getConnection();

echo "=== tarifas_seguro SCHEMA ===\n";
$res = $db->query("DESCRIBE tarifas_seguro");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}

echo "\n=== SAMPLE DATA ===\n";
$res2 = $db->query("SELECT * FROM tarifas_seguro LIMIT 10");
while ($row2 = $res2->fetch_assoc()) {
    print_r($row2);
}
?>
