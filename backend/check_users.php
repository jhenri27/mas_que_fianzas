<?php
require_once __DIR__ . '/config.php';
$db = Database::getInstance()->getConnection();
$res = $db->query("SELECT id, username, nombre, apellido, perfil_id, referente_id FROM usuarios");
while($row = $res->fetch_assoc()) {
    print_r($row);
}
?>
