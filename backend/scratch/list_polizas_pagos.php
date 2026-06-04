<?php
require_once __DIR__ . '/../config.php';
$db = Database::getInstance()->getConnection();
$res = $db->query("SELECT p.id, p.numero_poliza, p.prima_total, p.cuota_total, p.estado, 
                  (SELECT COUNT(*) FROM pagos WHERE poliza_id = p.id AND estado_pago = 'procesado') as processed_count,
                  (SELECT SUM(monto) FROM pagos WHERE poliza_id = p.id AND estado_pago = 'procesado') as paid_amount
                  FROM polizas p");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}
