<?php
/**
 * API de Consulta y Pago de Comisiones - v3.0
 */

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

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
require_once '../ComisionManager.php';

$mgr    = new ComisionManager();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'listar';

try {
    if ($method === 'GET') {
        $filtros = [];
        if (!empty($_GET['usuario_id']))   $filtros['usuario_id']  = $_GET['usuario_id'];
        if (!empty($_GET['estado_pago']))  $filtros['estado_pago'] = $_GET['estado_pago'];
        if (!empty($_GET['poliza_id']))    $filtros['poliza_id']   = $_GET['poliza_id'];

        $soloPropios = restringirSoloPropios($usuario_actual, 'Comisiones');
        if ($soloPropios) {
            $filtros['usuario_id'] = $usuario_actual;
        }

        $comisiones = $mgr->listarComisiones($filtros);
        echo json_encode(["exito" => true, "data" => $comisiones, "total" => count($comisiones)]);
    } elseif ($method === 'POST') {
        $datos = json_decode(file_get_contents('php://input'), true);
        // Futura lógica: marcar comisiones como pagadas
        echo json_encode(["exito" => false, "mensaje" => "En desarrollo"]);
    } else {
        http_response_code(405);
        echo json_encode(["exito" => false, "mensaje" => "Método no permitido"]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["exito" => false, "mensaje" => $e->getMessage()]);
}
