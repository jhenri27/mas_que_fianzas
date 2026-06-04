<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../PagoManager.php';

$pagoManager = new PagoManager();

// Test normal payment (no fraccionamiento) on Policy 3
$datos = [
    'poliza_id' => 3,
    'monto' => 2000,
    'tipo_pago' => 'transferencia',
    'banco' => 'Banco BHD',
    'numero_comprobante' => 'TEST-TXN-12345',
    'fecha_pago' => '2026-06-04',
    'comprobante_nombre' => 'test_file.png',
    'comprobante_ruta' => 'uploads/depositos/test_file.png',
    'comprobante_hash' => 'hash_test_' . time(),
    'registrado_por' => 1
];

try {
    echo "Registrando pago normal en Póliza 3...\n";
    $res = $pagoManager->registrarPago($datos);
    print_r($res);
} catch (Exception $e) {
    echo "ERROR en registrarPago: " . $e->getMessage() . "\n";
}

// Test fractionated payment on Policy 3
try {
    echo "\nRegistrando fraccionamiento en Póliza 3...\n";
    // First, let's delete any payments on Policy 3 so it's clean
    $db = Database::getInstance()->getConnection();
    $db->query("DELETE FROM pagos WHERE poliza_id = 3");
    $db->query("DELETE FROM documentos_poliza WHERE poliza_id = 3");
    $db->query("UPDATE polizas SET cuota_total = 1 WHERE id = 3");
    
    $datosFrac = [
        'poliza_id' => 3,
        'monto_inicial' => 1000,
        'tipo_pago' => 'transferencia',
        'banco' => 'Banco BHD',
        'numero_comprobante' => 'TEST-TXN-54321',
        'fecha_pago' => '2026-06-04',
        'comprobante_nombre' => 'test_file_frac.png',
        'comprobante_ruta' => 'uploads/depositos/test_file_frac.png',
        'comprobante_hash' => 'hash_test_frac_' . time(),
        'registrado_por' => 1
    ];
    $res2 = $pagoManager->registrarFraccionamiento($datosFrac);
    print_r($res2);
} catch (Exception $e) {
    echo "ERROR en registrarFraccionamiento: " . $e->getMessage() . "\n";
}
