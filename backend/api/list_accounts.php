<?php
header('Content-Type: application/json');
require_once '../config.php';
$db = Database::getInstance()->getConnection();
$res = $db->query("SELECT codigo, nombre, naturaleza FROM cf_catalogo_cuentas ORDER BY codigo");
$cuentas = [];
while($row = $res->fetch_assoc()) $cuentas[] = $row;
echo json_encode($cuentas, JSON_PRETTY_PRINT);
