<?php
require_once 'backend/config.php';
$db = Database::getInstance()->getConnection();

echo "=== COLUMNAS DE 'modulos' ===\n";
$res = $db->query("SHOW COLUMNS FROM modulos");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . ' (' . $row['Type'] . ")\n";
}

echo "\n=== COLUMNAS DE 'funciones_modulo' ===\n";
$res = $db->query("SHOW COLUMNS FROM funciones_modulo");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . ' (' . $row['Type'] . ")\n";
}
?>
