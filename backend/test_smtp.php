<?php
/**
 * SMTP Diagnostic Script
 * NOFTRAB Standards - Test de Diagnóstico Profundo
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/Mailer.php';

echo "===============================================\n";
echo "   DIAGNÓSTICO SMTP - PLATAFORMA INTEGRADA\n";
echo "===============================================\n\n";

$configFile = __DIR__ . '/config/smtp.json';
if (!file_exists($configFile)) {
    echo "[FAIL] Archivo de configuración no encontrado: $configFile\n";
    exit(1);
}

$config = json_decode(file_get_contents($configFile), true);
if (!$config) {
    echo "[FAIL] El archivo $configFile no es un JSON válido.\n";
    exit(1);
}

echo "[INFO] Servidor: " . $config['server'] . "\n";
echo "[INFO] Puerto: " . $config['port'] . "\n";
echo "[INFO] Usuario: " . $config['username'] . "\n";
echo "[INFO] Encriptación: " . $config['encryption'] . "\n";
echo "-----------------------------------------------\n\n";

echo "[INFO] Iniciando prueba de envío...\n";

$mailer = new Mailer();

// Destino de prueba (el mismo que el origen para evitar rebotes)
$to = $config['username'];
$subject = "TEST SMTP - Plataforma Integrada (" . date('H:i:s') . ")";
$body = "<h2>Prueba exitosa</h2><p>El motor de correos está funcionando correctamente.</p>";

$resultado = $mailer->enviar($to, $subject, $body);

echo "\n-----------------------------------------------\n";
if ($resultado) {
    echo "[EXITO] Correo enviado correctamente a $to.\n";
} else {
    echo "[ERROR] El envío falló. Revisar detalles arriba y en logs/smtp.log.\n";
    
    // Imprimir el log reciente
    $logFile = __DIR__ . '/logs/smtp.log';
    if (file_exists($logFile)) {
        echo "\n[ULTIMAS 15 LINEAS DEL LOG]\n";
        $lines = file($logFile);
        $recentLines = array_slice($lines, -15);
        foreach ($recentLines as $l) {
            echo trim($l) . "\n";
        }
    }
}
echo "===============================================\n";
