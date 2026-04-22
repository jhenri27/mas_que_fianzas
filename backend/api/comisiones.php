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
