<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $_POST['action'] = 'procesar_pago';
    $_POST['ref'] = '23da6af7f5ebfaad613fe23308cdd573';
    $_POST['nombre_razon_social'] = 'Test';
    $_POST['rnc'] = '123';
    $_POST['correo'] = 'test@test.com';
    $_POST['telefono'] = '123';
    $_POST['placa'] = '456';
    $_POST['matricula'] = '456';
    $_POST['chasis'] = '456';
    $_POST['marca'] = '123';
    $_POST['modelo'] = '123';
    $_POST['anio'] = '2023';
    $_POST['color'] = 'red';
    $_POST['tipo_vehiculo'] = 'Sedan';
    $_POST['metodo_pago'] = 'transferencia';

    $_FILES['comprobante'] = [
        'name' => 'dummy.png',
        'type' => 'image/png',
        'tmp_name' => 'C:/wamp64/www/PLATAFORMA_INTEGRADA/dummy.png',
        'error' => UPLOAD_ERR_OK,
        'size' => 100
    ];

    require 'backend/api/checkout_process.php';
} catch (Throwable $e) {
    echo "THROWABLE CAUGHT: " . $e->getMessage() . "\n" . $e->getTraceAsString();
}
