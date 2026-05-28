<?php
require_once 'backend/config.php';
$db = Database::getInstance()->getConnection();

$res = $db->query("SELECT * FROM modulos");
while($row = $res->fetch_assoc()) {
    echo json_encode($row) . "\n";
}
?>
