<?php
require_once dirname(__FILE__) . '/../config.php';
$db = Database::getInstance()->getConnection();

// Query the policy POL-2026-1675
$sql = "SELECT * FROM polizas WHERE numero_poliza = 'POL-2026-1675'";
$res = $db->query($sql);
if ($res && $res->num_rows > 0) {
    echo "=== POLIZA ===\n";
    $poliza = $res->fetch_assoc();
    print_r($poliza);

    // Query payments associated with this policy
    $poliza_id = $poliza['id'];
    $sql_pagos = "SELECT * FROM pagos WHERE poliza_id = $poliza_id";
    $res_pagos = $db->query($sql_pagos);
    echo "\n=== PAGOS ===\n";
    if ($res_pagos && $res_pagos->num_rows > 0) {
        while ($pago = $res_pagos->fetch_assoc()) {
            print_r($pago);
        }
    } else {
        echo "No hay pagos registrados para esta póliza.\n";
    }
} else {
    echo "No se encontró la póliza POL-2026-1675.\n";
}
?>
