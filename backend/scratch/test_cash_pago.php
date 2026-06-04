<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../PagoManager.php';

$pagoManager = new PagoManager();
$db = Database::getInstance()->getConnection();

// Clean policy 3
$db->query("DELETE FROM pagos WHERE poliza_id = 3");
$db->query("DELETE FROM documentos_poliza WHERE poliza_id = 3");
$db->query("UPDATE polizas SET cuota_total = 1 WHERE id = 3");

$datosFrac = [
    'poliza_id' => 3,
    'monto_inicial' => 1200,
    'tipo_pago' => 'efectivo',
    'banco' => 'Caja Principal MQF',
    'fecha_pago' => '2026-06-04',
    'registrado_por' => 1
];

try {
    echo "Registrando fraccionamiento en EFECTIVO en Póliza 3...\n";
    $res = $pagoManager->registrarFraccionamiento($datosFrac);
    print_r($res);
} catch (Exception $e) {
    echo "ERROR en registrarFraccionamiento: " . $e->getMessage() . "\n";
}
