<?php
require_once __DIR__ . '/../config.php';
$db = Database::getInstance()->getConnection();
$res = $db->query("SELECT * FROM companias_registradas");
if ($res) {
    while($row = $res->fetch_assoc()) {
        print_r($row);
    }
}
?>
