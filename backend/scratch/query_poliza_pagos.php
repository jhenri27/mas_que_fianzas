<?php
require_once __DIR__ . '/../config.php';
$db = Database::getInstance()->getConnection();
$res = $db->query("SELECT id, numero_poliza, cliente_id, prima_total, cuota_total, estado FROM polizas WHERE numero_poliza = 'POL-2026-2082'");
if ($row = $res->fetch_assoc()) {
    print_r($row);
    $pol_id = $row['id'];
    $res2 = $db->query("SELECT id, cuota_numero, monto_pagado, tipo_pago, estado_pago, fecha_registro FROM pagos WHERE poliza_id = $pol_id");
    while ($row2 = $res2->fetch_assoc()) {
        print_r($row2);
    }
} else {
    echo "No se encontró la póliza POL-2026-2082\n";
}
