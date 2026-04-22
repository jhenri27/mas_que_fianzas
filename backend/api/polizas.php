<?php
/**
 * API de Gestión de Pólizas - Core Asegurador v3.0
 * MAS QUE FIANZAS
 */

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
$action = $_GET['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            if ($action === 'obtener') {
                $id = $_GET['id'] ?? null;
                if (!$id) {
                    echo json_encode(["exito" => false, "mensaje" => "ID de póliza requerido"]);
                    break;
                }
                $poliza = $polizaManager->obtenerPolizaDetalle($id);
                echo json_encode(["exito" => true, "data" => $poliza]);
            } else {
                // Listado por defecto con filtros
                $filtros = [];
                if (isset($_GET['search'])) $filtros['search'] = $_GET['search'];
                if (isset($_GET['start_date'])) $filtros['start_date'] = $_GET['start_date'];
                if (isset($_GET['end_date'])) $filtros['end_date'] = $_GET['end_date'];
                if (isset($_GET['estado'])) $filtros['estado'] = $_GET['estado'];
                
                $polizas = $polizaManager->obtenerPolizas($filtros);
                echo json_encode(["exito" => true, "data" => $polizas]);
            }
            break;

        case 'POST':
            $datos = json_decode(file_get_contents('php://input'), true);
            
            if ($action === 'emitir') {
                if (!$datos || empty($datos['cliente_id'])) {
                    http_response_code(400);
                    echo json_encode(["exito" => false, "mensaje" => "Datos de emisión incompletos (cliente_id requerido)"]);
                    break;
                }

                $resultado = $polizaManager->emitirPoliza($datos);
                if ($resultado['exito']) {
                    echo json_encode($resultado);
                } else {
                    http_response_code(500);
                    echo json_encode($resultado);
                }
            } 
            elseif ($action === 'validar') {
                $id = $_GET['id'] ?? $datos['id'] ?? null;
                $userId = $_GET['user_id'] ?? $datos['user_id'] ?? 1; // Default to admin for now
                if (!$id) {
                    echo json_encode(["exito" => false, "mensaje" => "ID de póliza requerido"]);
                    break;
                }
                $ok = $polizaManager->validarPoliza($id, $userId);
                echo json_encode(["exito" => $ok, "mensaje" => $ok ? "Póliza validada exitosamente" : "No se pudo validar la póliza"]);
            }
            elseif ($action === 'cambiar_estado') {
                $id = $datos['id'] ?? null;
                $estado = $datos['estado'] ?? null;
                if (!$id || !$estado) {
                    echo json_encode(["exito" => false, "mensaje" => "ID y estado requeridos"]);
                    break;
                }
                $ok = $polizaManager->cambiarEstado($id, $estado);
                echo json_encode(["exito" => $ok, "mensaje" => $ok ? "Estado actualizado" : "Error al actualizar estado"]);
            }
            else {
                http_response_code(400);
                echo json_encode(["exito" => false, "mensaje" => "Acción POST no válida"]);
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
