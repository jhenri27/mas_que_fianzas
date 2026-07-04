<?php
require_once __DIR__ . '/config.php';
$db = Database::getInstance()->getConnection();
$res = $db->query("SHOW CREATE TABLE reglas_negocio");
if($res) {
    $row = $res->fetch_row();
    echo $row[1] . "\n\n";
} else {
    echo "Table reglas_negocio does not exist.\n";
}
?>
