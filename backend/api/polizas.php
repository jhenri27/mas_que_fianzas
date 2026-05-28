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

// Validar sesión: aceptar PHP session O Bearer token
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$bearer_token = null;
$auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? (function_exists('apache_request_headers') ? (apache_request_headers()['Authorization'] ?? '') : '');
if (preg_match('/Bearer\s+(.+)$/i', $auth_header, $matches)) {
    $bearer_token = trim($matches[1]);
}
if (empty($bearer_token)) {
    $bearer_token = $_GET['token_sesion'] ?? $_POST['token_sesion'] ?? $_REQUEST['token'] ?? $_REQUEST['token_sesion'] ?? null;
}

$usuario_id = null;
if (isset($_SESSION['usuario_id']) && $_SESSION['usuario_id']) {
    $usuario_id = (int)$_SESSION['usuario_id'];
} elseif (!empty($bearer_token)) {
    $db_temp = Database::getInstance()->getConnection();
    $stmt_tk = $db_temp->prepare("SELECT usuario_id FROM sesiones_usuario WHERE token_sesion = ? AND activa = 1 AND fecha_expiracion > NOW() LIMIT 1");
    if ($stmt_tk) {
        $stmt_tk->bind_param("s", $bearer_token);
        $stmt_tk->execute();
        $res_tk = $stmt_tk->get_result();
        if ($row_tk = $res_tk->fetch_assoc()) $usuario_id = (int)$row_tk['usuario_id'];
        $stmt_tk->close();
    }
}

if (!$usuario_id) {
    http_response_code(401);
    echo json_encode(["exito" => false, "mensaje" => "Sesión no válida o expirada"]);
    exit;
}

$usuario_actual = $usuario_id;
require_once '../PolizaManager.php';

$polizaManager = new PolizaManager();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            $soloPropios = restringirSoloPropios($usuario_actual, 'Pólizas');
            if ($action === 'obtener') {
                $id = $_GET['id'] ?? null;
                if (!$id) {
                    echo json_encode(["exito" => false, "mensaje" => "ID de póliza requerido"]);
                    break;
                }
                $poliza = $polizaManager->obtenerPolizaDetalle($id);
                if ($poliza && $soloPropios && (int)$poliza['emitida_por'] !== $usuario_actual) {
                    http_response_code(403);
                    echo json_encode(["exito" => false, "mensaje" => "Acceso denegado: este registro no le pertenece"]);
                    break;
                }
                echo json_encode(["exito" => true, "data" => $poliza]);
            } else {
                // Listado por defecto con filtros
                $filtros = [];
                if (isset($_GET['search'])) $filtros['search'] = $_GET['search'];
                if (isset($_GET['start_date'])) $filtros['start_date'] = $_GET['start_date'];
                if (isset($_GET['end_date'])) $filtros['end_date'] = $_GET['end_date'];
                if (isset($_GET['estado'])) $filtros['estado'] = $_GET['estado'];
                
                if ($soloPropios) {
                    $filtros['emitida_por'] = $usuario_actual;
                }
                
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

                // Inyectar el usuario activo como emisor
                $datos['emitida_por'] = $usuario_actual;

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
                $userId = $_GET['user_id'] ?? $datos['user_id'] ?? $usuario_actual;
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
