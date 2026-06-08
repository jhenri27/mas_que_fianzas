<?php
require_once dirname(__FILE__) . '/../config.php';
$db = Database::getInstance()->getConnection();

echo "=== ALL RECORDS IN tarifas_seguro ===\n";
$res = $db->query("SELECT * FROM tarifas_seguro");
while ($row = $res->fetch_assoc()) {
    echo "ID: {$row['id']} | Tipo: {$row['tipo']} | Capacidad: {$row['capacidad']} | Uso: {$row['uso']} | Tarifa: {$row['tarifa_base']} | Porcentaje: {$row['porcentaje_adicional']} | Activo: {$row['activo']}\n";
}
?>
