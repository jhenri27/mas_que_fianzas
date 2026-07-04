<?php
require_once 'c:\wamp64\www\PLATAFORMA_INTEGRADA\backend\config.php';
require_once 'c:\wamp64\www\PLATAFORMA_INTEGRADA\backend\PolizaManager.php';

try {
    $db = Database::getInstance()->getConnection();
    $polizaManager = new PolizaManager($db);
    // Find an active policy
    $res = $db->query("SELECT id FROM polizas WHERE estado = 'activa' LIMIT 1");
    if ($res && $row = $res->fetch_assoc()) {
        $id = $row['id'];
        $result = $polizaManager->cancelarPoliza($id, "Prueba de error de cancelacion", "individual", 1, null, true);
        echo json_encode($result);
    } else {
        echo "No active policies found";
    }
} catch (Throwable $e) {
    echo "Fatal: " . $e->getMessage();
}
