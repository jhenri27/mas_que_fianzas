<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');

$dataFile = __DIR__ . '/../data/control_remoto.json';
$dataDir = dirname($dataFile);

if (!file_exists($dataDir)) {
    mkdir($dataDir, 0777, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);

    if (!$input) {
        $input = [
            'comando' => $_POST['comando'] ?? 'IDLE',
            'timestamp' => time(),
            'parametros' => []
        ];
    } else {
        $input['timestamp'] = time();
    }

    file_put_contents($dataFile, json_encode($input, JSON_PRETTY_PRINT));
    echo json_encode(['exito' => true, 'mensaje' => 'Comando remoto registrado', 'comando' => $input]);
    exit;
}

// GET Request: Leer último comando
if (file_exists($dataFile)) {
    $content = file_get_contents($dataFile);
    echo $content;
} else {
    echo json_encode(['comando' => 'IDLE', 'timestamp' => time()]);
}
?>
