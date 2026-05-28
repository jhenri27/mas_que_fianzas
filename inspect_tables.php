<?php
require_once 'backend/config.php';
$db = Database::getInstance()->getConnection();
foreach(['perfiles', 'funciones_modulo', 'permisos_perfil', 'auditoria_accesos'] as $t) {
    echo "\n--- $t ---\n";
    $res = $db->query("SHOW COLUMNS FROM $t");
    if ($res) {
        while($row = $res->fetch_assoc()) {
            echo $row['Field'] . ' (' . $row['Type'] . ')' . "\n";
        }
    } else {
        echo "Error or table does not exist.\n";
    }
}
?>
