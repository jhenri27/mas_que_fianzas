<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config.php';
require_once '../PolizaManager.php';

$polizaManager = new PolizaManager();

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            $filtros = [];
            if (isset($_GET['search'])) $filtros['search'] = $_GET['search'];
            if (isset($_GET['start_date'])) $filtros['start_date'] = $_GET['start_date'];
            if (isset($_GET['end_date'])) $filtros['end_date'] = $_GET['end_date'];
            
            $polizas = $polizaManager->obtenerPolizas($filtros);
            echo json_encode(["exito" => true, "data" => $polizas]);
            break;

        case 'POST':
            $action = $_GET['action'] ?? '';
            if ($action === 'emitir') {
                $datos = json_decode(file_get_contents('php://input'), true);
                if (!$datos || empty($datos['numero_poliza']) || empty($datos['cliente_id'])) {
                    http_response_code(400);
                    echo json_encode(["exito" => false, "mensaje" => "Datos de emisión incompletos"]);
                    break;
                }

                $id = $polizaManager->emitirPoliza($datos);
                if ($id) {
                    echo json_encode(["exito" => true, "mensaje" => "Póliza emitida exitosamente", "id" => $id]);
                } else {
                    http_response_code(500);
                    echo json_encode(["exito" => false, "mensaje" => "No se pudo emitir la póliza"]);
                }
            } else {
                http_response_code(400);
                echo json_encode(["exito" => false, "mensaje" => "Acción no válida"]);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(["exito" => false, "mensaje" => "Método no permitido"]);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["exito" => false, "mensaje" => "Error del servidor: " . $e->getMessage()]);
}
