<?php
require_once 'c:/wamp64/www/PLATAFORMA_INTEGRADA/backend/config.php';
$db = Database::getInstance()->getConnection();

echo "=== COLUMNAS DE acceso_datos_usuario ===\n";
$res = $db->query("SHOW COLUMNS FROM acceso_datos_usuario");
while ($row = $res->fetch_assoc()) {
    echo "  " . $row['Field'] . " (" . $row['Type'] . ")\n";
}

echo "\n=== CONTENIDO DE acceso_datos_usuario ===\n";
$res = $db->query("SELECT * FROM acceso_datos_usuario LIMIT 20");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}
?>
