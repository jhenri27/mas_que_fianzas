<?php
require_once 'c:/wamp64/www/PLATAFORMA_INTEGRADA/backend/config.php';
$db = Database::getInstance()->getConnection();

$res = $db->query("SHOW COLUMNS FROM funciones_modulo LIKE 'tipo_permiso'");
$row = $res->fetch_assoc();
print_r($row);

$res2 = $db->query("SHOW COLUMNS FROM funciones_modulo LIKE 'estado'");
$row2 = $res2->fetch_assoc();
print_r($row2);
?>
