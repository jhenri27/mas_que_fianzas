<?php
ob_start();
header('Content-Type: application/json; charset=utf-8');

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        "exito" => false, 
        "mensaje" => "Error en el servidor: " . $errstr,
        "debug" => ["file" => $errfile, "line" => $errline]
    ], JSON_UNESCAPED_UNICODE);
    exit;
});

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        http_response_code(500);
        echo json_encode([
            "exito" => false, 
            "mensaje" => "Error crítico: " . $error['message']
        ], JSON_UNESCAPED_UNICODE);
    }
});

require_once __DIR__ . '/../Mailer.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['to']) || !isset($data['config'])) {
    ob_clean();
    echo json_encode(["exito" => false, "mensaje" => "Datos de prueba incompletos (destino o configuración)."]);
    exit;
}

$to = $data['to'];
$config = $data['config'];

// Resolver contraseña si viene el marcador de posición (asteriscos)
if (isset($config['password']) && preg_match('/^\*+$/', $config['password'])) {
    $configFile = __DIR__ . '/../config/smtp.json';
    if (file_exists($configFile)) {
        $savedConfig = json_decode(file_get_contents($configFile), true);
        if ($savedConfig && isset($savedConfig['password'])) {
            $config['password'] = $savedConfig['password'];
        }
    }
}

// Validar email de destino
if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
    ob_clean();
    echo json_encode(["exito" => false, "mensaje" => "Dirección de correo de destino no válida."]);
    exit;
}

try {
    // Instanciar Mailer con la configuración inyectada
    $mailer = new Mailer($config);
    
    $subject = "Prueba de Conexión SMTP - MAS QUE FIANZAS";
    $message = "
        <div style='font-family: Arial, sans-serif; color: #333;'>
            <h2 style='color: #4f46e5;'>Prueba de Conexión Exitosa</h2>
            <p>Hola,</p>
            <p>Este es un correo de prueba generado desde el panel de configuración de <strong>MAS QUE FIANZAS</strong>.</p>
            <p>Si has recibido este mensaje, significa que los parámetros SMTP (Host, Puerto, Usuario y Contraseña) son correctos y el servidor tiene conectividad.</p>
            <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
            <p style='font-size: 12px; color: #666;'>Fecha del intento: " . date('Y-m-d H:i:s') . "</p>
        </div>
    ";
    
    $resultado = $mailer->enviar($to, $subject, $message, true);
    
    if ($resultado) {
        ob_clean();
        echo json_encode([
            "exito" => true, 
            "mensaje" => "Envío de prueba exitoso. Revisa la bandeja de entrada de: $to"
        ]);
    } else {
        ob_clean();
        echo json_encode([
            "exito" => false, 
            "mensaje" => "El servidor SMTP rechazó la conexión o el envío. Revisa los logs para más detalle."
        ]);
    }
} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        "exito" => false, 
        "mensaje" => "Error excepcional durante la prueba: " . $e->getMessage()
    ]);
}
?>
