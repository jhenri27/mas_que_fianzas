<?php
require_once 'config.php';
$db = Database::getInstance()->getConnection();
$r = $db->query("SHOW COLUMNS FROM clientes LIKE 'nombre_comisionante'");
if ($r->num_rows == 0) {
    $db->query("ALTER TABLE clientes ADD COLUMN nombre_comisionante VARCHAR(200) NULL AFTER codigo_comisionante");
    echo json_encode(['ok' => true, 'msg' => 'Columna nombre_comisionante agregada a clientes']);
} else {
    echo json_encode(['ok' => true, 'msg' => 'Columna nombre_comisionante ya existe']);
}
?>