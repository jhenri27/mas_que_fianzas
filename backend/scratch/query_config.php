<?php
require_once __DIR__ . '/../config.php';
$db = Database::getInstance()->getConnection();
$res = $db->query("SELECT * FROM configuracion_sistema WHERE clave_config = 'EMPRESA_CUENTAS_TRANSFERENCIA' OR clave_config LIKE 'EMPRESA_%'");
while ($row = $res->fetch_assoc()) {
    echo "CLAVE: " . $row['clave_config'] . "\n";
    echo "VALOR: " . $row['valor_config'] . "\n\n";
}
