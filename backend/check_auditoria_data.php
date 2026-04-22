<?php
require_once 'config.php';
$db = Database::getInstance()->getConnection();

$res = $db->query("SELECT * FROM auditoria_accesos ORDER BY fecha_evento DESC LIMIT 10");
$rows = [];
while($row = $res->fetch_assoc()) {
    $rows[] = $row;
}

header('Content-Type: application/json');
echo json_encode($rows, JSON_PRETTY_PRINT);
?>
