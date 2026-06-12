<?php
/**
 * API: Visor Seguro de Logs de Errores (NOFTRAB)
 * MAS QUE FIANZAS
 */

header('Content-Type: text/plain; charset=utf-8');

require_once dirname(__DIR__) . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validar token de autorización (Bearer o parámetro)
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
    echo "Acceso denegado: Sesión no válida o expirada.";
    exit;
}

// Obtener datos del usuario para verificar rol
$db = Database::getInstance()->getConnection();
$stmt_u = $db->prepare("SELECT perfil_id FROM usuarios WHERE id = ? LIMIT 1");
$stmt_u->bind_param("i", $usuario_id);
$stmt_u->execute();
$usr_data = $stmt_u->get_result()->fetch_assoc();
$stmt_u->close();

if (!$usr_data) {
    http_response_code(404);
    echo "Acceso denegado: Usuario no encontrado.";
    exit;
}

$es_admin = (
    $usuario_id === 1 || 
    (int)$usr_data['perfil_id'] === 1 || 
    (function_exists('tienePermiso') && (tienePermiso($usuario_id, 'CONF_TOTAL') || tienePermiso($usuario_id, 'AUDITORIA_LINEAL_VER')))
);

if (!$es_admin) {
    http_response_code(403);
    echo "Acceso denegado: Se requieren permisos administrativos para visualizar los logs del servidor.";
    exit;
}

// Registrar consulta en auditoría (NOFTRAB)
if (function_exists('logAudit')) {
    logAudit($usuario_id, 'consulta_logs_sistema', 'logs', 'consultar', "Consulta de logs de error del servidor", 'exitoso');
}

$log = dirname(__DIR__) . '/logs/error.log';
if (file_exists($log)) {
    $lines = file($log);
    $last = array_slice($lines, -50);
    echo implode("", $last);
} else {
    echo "No hay archivo de log en $log";
}
?>
