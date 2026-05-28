<?php
/**
 * API de Estadísticas de Pólizas Emitidas
 * MAS QUE FIANZAS - Core Asegurador v3.0
 */

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

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

try {
    $db = Database::getInstance()->getConnection();
    
    // Determinar si aplica la restricción de datos propios
    $soloPropios = restringirSoloPropios($usuario_actual, 'Pólizas');
    
    // Cláusula SQL para el filtro de visibilidad
    $whereFiltro = "1=1";
    if ($soloPropios) {
        $whereFiltro = "emitida_por = " . intval($usuario_actual);
    }
    
    // 1. Pólizas Emitidas hoy (Diario)
    $sql_diario = "SELECT COUNT(*) as total FROM polizas WHERE DATE(fecha_emision) = CURDATE() AND $whereFiltro";
    $res_diario = $db->query($sql_diario);
    $total_diario = $res_diario ? (int)$res_diario->fetch_assoc()['total'] : 0;
    
    // 2. Pólizas Emitidas en los últimos 7 días (Semanal)
    $sql_semanal = "SELECT COUNT(*) as total FROM polizas WHERE fecha_emision >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND $whereFiltro";
    $res_semanal = $db->query($sql_semanal);
    $total_semanal = $res_semanal ? (int)$res_semanal->fetch_assoc()['total'] : 0;
    
    // 3. Pólizas Emitidas en los últimos 30 días (Mensual)
    $sql_mensual = "SELECT COUNT(*) as total FROM polizas WHERE fecha_emision >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND $whereFiltro";
    $res_mensual = $db->query($sql_mensual);
    $total_mensual = $res_mensual ? (int)$res_mensual->fetch_assoc()['total'] : 0;
    
    // 4. Top 5 clientes con más pólizas y sus conteos
    $sql_top5 = "SELECT c.nombre as cliente_nombre, c.cedula as cliente_cedula, COUNT(p.id) as cantidad_polizas 
                 FROM polizas p 
                 JOIN clientes c ON p.cliente_id = c.id 
                 WHERE $whereFiltro
                 GROUP BY c.id, c.nombre, c.cedula
                 ORDER BY cantidad_polizas DESC 
                 LIMIT 5";
    
    $res_top5 = $db->query($sql_top5);
    $top5 = [];
    if ($res_top5) {
        while ($row = $res_top5->fetch_assoc()) {
            $top5[] = [
                "cliente_nombre" => $row['cliente_nombre'],
                "cliente_cedula" => $row['cliente_cedula'],
                "cantidad_polizas" => (int)$row['cantidad_polizas']
            ];
        }
    }
    
    // Retornar la respuesta JSON premium
    echo json_encode([
        "exito" => true,
        "mensaje" => "Estadísticas obtenidas correctamente",
        "data" => [
            "solo_propios" => $soloPropios,
            "diario" => $total_diario,
            "semanal" => $total_semanal,
            "mensual" => $total_mensual,
            "top_clientes" => $top5
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["exito" => false, "mensaje" => "Error interno: " . $e->getMessage()]);
}
