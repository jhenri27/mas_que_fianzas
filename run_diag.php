<?php
require_once 'backend/config.php';
$db = Database::getInstance()->getConnection();

echo "=== POLIZAS RAMOS Y TIPOS ===\n";
$res = $db->query("SELECT DISTINCT ramo, tipo_seguro FROM polizas");
while ($row = $res->fetch_assoc()) {
    echo "Ramo: {$row['ramo']} | Tipo Seguro: {$row['tipo_seguro']}\n";
}
?>
