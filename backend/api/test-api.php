<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$data = [
    'status' => 'ok',
    'message' => 'Backend API is working',
    'time' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'],
    'request_uri' => $_SERVER['REQUEST_URI']
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $data['received_data'] = $input;
    
    if (isset($input['username']) && isset($input['password'])) {
        $data['login_test'] = 'Datos recibidos correctamente';
        
        require_once dirname(__FILE__) . '/../config.php';
        require_once dirname(__FILE__) . '/../Autenticacion.php';
        
        try {
            $auth = new Autenticacion();
            $result = $auth->login($input['username'], $input['password']);
            $data['login_result'] = $result;
        } catch (Exception $e) {
            $data['login_error'] = $e->getMessage();
        }
    }
}

echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
