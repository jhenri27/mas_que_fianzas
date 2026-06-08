<?php
require_once dirname(__FILE__) . '/../config.php';
$db = Database::getInstance()->getConnection();
$res = $db->query("SHOW TABLES LIKE 'vehiculos'");
if ($res->num_rows > 0) {
    $res2 = $db->query("DESCRIBE vehiculos");
    while ($row = $res2->fetch_assoc()) {
        print_r($row);
    }
} else {
    echo "No existe tabla vehiculos\n";
}
?>
