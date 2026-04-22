<?php
/**
 * API de Gestión de Vehículos - Core v3.0
 */

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config.php';
require_once '../VehiculoManager.php';

$vehiculoManager = new VehiculoManager();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['cliente_id'])) {
                $vehiculos = $vehiculoManager->obtenerVehiculosPorCliente($_GET['cliente_id']);
                echo json_encode(["exito" => true, "data" => $vehiculos]);
            } elseif (isset($_GET['placa'])) {
                $vehiculo = $vehiculoManager->obtenerVehiculoPorPlaca($_GET['placa']);
                echo json_encode(["exito" => true, "data" => $vehiculo]);
            } elseif (isset($_GET['id'])) {
                $vehiculo = $vehiculoManager->obtenerVehiculo($_GET['id']);
                echo json_encode(["exito" => true, "data" => $vehiculo]);
            } else {
                http_response_code(400);
                echo json_encode(["exito" => false, "mensaje" => "Se requiere cliente_id, placa o id"]);
            }
            break;

        case 'POST':
            $datos = json_decode(file_get_contents('php://input'), true);
            if (!$datos || empty($datos['cliente_id'])) {
                http_response_code(400);
                echo json_encode(["exito" => false, "mensaje" => "Datos incompletos"]);
                break;
            }

            $resultado = $vehiculoManager->crearOActualizarVehiculo($datos);
            echo json_encode($resultado);
            break;

        default:
            http_response_code(405);
            echo json_encode(["exito" => false, "mensaje" => "Método no permitido"]);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["exito" => false, "mensaje" => "Error: " . $e->getMessage()]);
}
