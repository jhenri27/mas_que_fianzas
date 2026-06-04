<?php
require_once __DIR__ . '/../config.php';
$db = Database::getInstance()->getConnection();
$res = $db->query("SELECT * FROM pagos");
echo "Total pagos: " . $res->num_rows . "\n";
while ($row = $res->fetch_assoc()) {
    print_r($row);
}
