<?php
header('Content-Type: application/json');
require_once '../config.php';

try {
    $db = Database::getInstance()->getConnection();
    $tables = ['cf_catalogo_cuentas', 'cf_periodos', 'cf_asientos', 'cf_asiento_lineas', 'cf_ncf', 'cf_ncf_secuencias'];
    $status = [];

    foreach ($tables as $t) {
        $res = $db->query("SHOW TABLES LIKE '$t'");
        $exists = $res->num_rows > 0;
        $count = 0;
        if ($exists) {
            $resC = $db->query("SELECT COUNT(*) as total FROM $t");
            $count = $resC->fetch_assoc()['total'];
        }
        $status[$t] = ['exists' => $exists, 'count' => $count];
    }

    echo json_encode(['exito' => true, 'status' => $status], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode(['exito' => false, 'mensaje' => $e->getMessage()]);
}
