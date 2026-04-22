<?php
require_once 'config.php';
$db = Database::getInstance()->getConnection();

$res = $db->query("SELECT * FROM sesiones_usuario WHERE activa = 1 AND fecha_expiracion > NOW() ORDER BY id DESC LIMIT 5");
$rows = [];
while($row = $res->fetch_assoc()) {
    $rows[] = $row;
}

header('Content-Type: application/json');
echo json_encode($rows, JSON_PRETTY_PRINT);
?>
