<?php
require 'c:\wamp64\www\PLATAFORMA_INTEGRADA\backend\config.php';
$db = Database::getInstance()->getConnection();
$db->query("UPDATE modulos SET nombre_modulo = 'labs_qa' WHERE nombre_modulo = 'labs_masqf'");
echo "Affected rows: " . $db->affected_rows . "\n";
$res = $db->query("SELECT * FROM modulos WHERE nombre_modulo LIKE '%labs%'");
print_r($res->fetch_all(MYSQLI_ASSOC));
