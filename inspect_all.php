<?php
require_once 'backend/config.php';
$db = Database::getInstance()->getConnection();

$tables = ['historial_ajustes', 'cotizaciones', 'polizas', 'pagos', 'comisiones_poliza'];

foreach($tables as $t) {
    echo "\n--- Table: $t ---\n";
    $res = $db->query("SHOW COLUMNS FROM `$t`");
    if ($res) {
        while($row = $res->fetch_assoc()) {
            echo $row['Field'] . ' (' . $row['Type'] . ')' . "\n";
        }
    } else {
        echo "Table does not exist or query error: " . $db->error . "\n";
    }
}
?>
