<?php
header('Content-Type: application/json; charset=utf-8');

$configFile = __DIR__ . '/../config/smtp.json';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!file_exists($configFile)) {
        echo json_encode(["exito" => false, "mensaje" => "Archivo de configuración no encontrado."]);
        exit;
    }
    
    $configData = file_get_contents($configFile);
    $config = json_decode($configData, true);
    
    // Ocultar contraseña en el GET para no dejarla expuesta en el payload
    // Aunque es admin, es buena práctica, pero como la vamos a mostrar en el input, podemos enviarla
    // para que el usuario sepa que está configurada.
    if ($config !== null) {
        echo json_encode(["exito" => true, "data" => $config]);
    } else {
        echo json_encode(["exito" => false, "mensaje" => "Error al leer json de config."]);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = file_get_contents('php://input');
    $config = json_decode($data, true);
    
    if (!$config || !isset($config['server'])) {
        echo json_encode(["exito" => false, "mensaje" => "Datos inválidos."]);
        exit;
    }
    
    // Si la contraseña viene con puros asteriscos, leer la anterior y mantenerla.
    if (isset($config['password']) && preg_match('/^\*+$/', $config['password'])) {
        if (file_exists($configFile)) {
            $oldConfig = json_decode(file_get_contents($configFile), true);
            $config['password'] = $oldConfig['password'] ?? '';
        }
    }
    
    if (!is_dir(dirname($configFile))) {
        mkdir(dirname($configFile), 0777, true);
    }
    
    $result = file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
    
    if ($result !== false) {
        echo json_encode(["exito" => true, "mensaje" => "Configuración guardada correctamente."]);
    } else {
        echo json_encode(["exito" => false, "mensaje" => "Error al escribir el archivo de configuración."]);
    }
}
?>
