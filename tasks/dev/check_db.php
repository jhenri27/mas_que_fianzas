<?php
require_once dirname(__FILE__) . '/../config.php';
$db = Database::getInstance()->getConnection();
$res = $db->query("DESCRIBE clientes");
$cols = [];
if($res) {
    while($row = $res->fetch_assoc()) $cols[] = $row;
} else {
    $cols = ['error' => $db->error];
}
echo json_encode($cols);
?>
