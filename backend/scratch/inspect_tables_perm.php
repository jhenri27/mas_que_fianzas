<?php
require_once 'c:/wamp64/www/PLATAFORMA_INTEGRADA/backend/config.php';
$db = Database::getInstance()->getConnection();

echo "=== COLUMNAS MODULOS ===\n";
$res = $db->query("SHOW COLUMNS FROM modulos");
while ($row = $res->fetch_assoc()) {
    echo "  " . $row['Field'] . "\n";
}

echo "=== COLUMNAS FUNCIONES_MODULO ===\n";
$res = $db->query("SHOW COLUMNS FROM funciones_modulo");
while ($row = $res->fetch_assoc()) {
    echo "  " . $row['Field'] . "\n";
}
?>
