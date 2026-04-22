<?php
ob_start();
header('Content-Type: application/json; charset=utf-8');

function respuestaJson($data, $limpiar = true) {
    if ($limpiar) ob_clean();
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$logFile = __DIR__ . '/../logs/smtp.log';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (!file_exists($logFile)) {
            respuestaJson(["exito" => true, "data" => "No hay registros de logs actualmente."]);
        }

        $contenido = @file_get_contents($logFile);
        if ($contenido === false) {
            respuestaJson(["exito" => false, "error" => "No se pudo leer el archivo de logs."]);
        }

        $tipo = $_GET['tipo'] ?? 'all';
        if ($tipo === 'auth') {
            $lineas = explode("\n", $contenido);
            $filtradas = array_filter($lineas, function($l) {
                return strpos($l, '[AUTH]') !== false || strpos($l, '[RECOVERY]') !== false;
            });
            $contenido = implode("\n", $filtradas);
        }

        if (isset($_GET['lines']) && is_numeric($_GET['lines'])) {
            $n = (int)$_GET['lines'];
            $lineas = array_filter(explode("\n", $contenido), fn($l) => trim($l) !== '');
            $lineas = array_slice(array_values($lineas), -$n);
            $contenido = implode("\n", $lineas);
        }

        $contenido = mb_convert_encoding($contenido, 'UTF-8', 'UTF-8');
        respuestaJson(["exito" => true, "data" => $contenido]);

    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (isset($data['action']) && $data['action'] === 'clear') {
            if (file_exists($logFile)) {
                file_put_contents($logFile, '');
            }
            respuestaJson(["exito" => true, "mensaje" => "Logs limpiados exitosamente."]);
        } else {
            respuestaJson(["exito" => false, "mensaje" => "Acción no válida."]);
        }
    }
} catch (Throwable $e) {
    respuestaJson(["exito" => false, "error" => "Error interno: " . $e->getMessage()]);    
}
?>
