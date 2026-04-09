<?php
require_once 'config.php';
$db = Database::getInstance()->getConnection();
$result = [];

// Check clientes columns
$r = $db->query("SHOW COLUMNS FROM clientes LIKE 'codigo_comisionante'");
$result['clientes.codigo_comisionante'] = $r->num_rows > 0 ? 'EXISTS' : 'MISSING - ADDING NOW';
if ($r->num_rows == 0) {
    $db->query("ALTER TABLE clientes ADD COLUMN codigo_comisionante VARCHAR(50) NULL AFTER comisionante");
    $result['clientes.codigo_comisionante'] = 'ADDED';
}

// Check usuarios columns
foreach (['es_comisionante','porcentaje_comision','porcentaje_comision_red','referente_id','codigo_usuario'] as $col) {
    $r = $db->query("SHOW COLUMNS FROM usuarios LIKE '$col'");
    $result["usuarios.$col"] = $r->num_rows > 0 ? 'EXISTS' : 'MISSING';
}

// Count usuarios
$r = $db->query("SELECT COUNT(*) as total FROM usuarios");
$result['total_usuarios'] = $r->fetch_assoc()['total'];

header('Content-Type: application/json');
echo json_encode($result, JSON_PRETTY_PRINT);
?>