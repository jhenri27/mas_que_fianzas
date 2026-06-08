<?php
require_once dirname(__FILE__) . '/../config.php';
$db = Database::getInstance()->getConnection();

$res = $db->query("SELECT * FROM modulos");
echo "=== TABLA MODULOS ===\n";
while ($row = $res->fetch_assoc()) {
    echo "ID: {$row['id']} | Nombre: {$row['nombre']} | Codigo: {$row['codigo_modulo']}\n";
}
?>
