<?php
require_once __DIR__ . '/../config.php';
$db = Database::getInstance()->getConnection();
$res = $db->query("SELECT * FROM tarifas_seguro LIMIT 10");
if ($res) {
    while($row = $res->fetch_assoc()) {
        print_r($row);
    }
}
?>
