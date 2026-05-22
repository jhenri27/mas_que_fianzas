<?php
require_once 'config.php';
$db = Database::getInstance()->getConnection();
$res = $db->query("SELECT * FROM perfiles");
while ($row = $res->fetch_assoc()) {
    echo json_encode($row) . "\n";
}
