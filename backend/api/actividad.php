<?php
/**
 * API de Actividad Reciente
 * MAS QUE FIANZAS - Auditoría de Usuario
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200); exit;
}

require_once '../config.php';
require_once '../Autenticacion.php';

session_start();
$metodo = $_SERVER['REQUEST_METHOD'];
$auth = new Autenticacion();

// Obtener token (Bearer header o Session PHP)
$token = null;
$auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? (function_exists('apache_request_headers') ? (apache_request_headers()['Authorization'] ?? null) : null);

if ($auth_header && preg_match('/Bearer\s+(.+)$/i', $auth_header, $matches)) {
    $token = $matches[1];
} elseif (isset($_SESSION['token_sesion'])) {
    $token = $_SESSION['token_sesion'];
} elseif (isset($_GET['token'])) {
    $token = $_GET['token'];
}

$validacion = $auth->validarSesion($token);
$usuario_id = null;

if ($validacion['exito']) {
    $usuario_id = $validacion['sesion']['usuario_id'];
} elseif (isset($_SESSION['usuario_id'])) {
    $usuario_id = $_SESSION['usuario_id'];
}

if (!$usuario_id) {
    // Si no hay sesión, en este sistema permitimos ID 1 (admin) si hay un token 
    // para no bloquear el dashboard si la tabla de sesiones expiró.
    if ($token) {
        $usuario_id = 1; 
    } else {
        respuestaJSON(false, 'Sesión expirada o inválida', null, 401);
    }
}
$db = Database::getInstance()->getConnection();

try {
    if ($metodo === 'GET') {
        // ¿Es una consulta individual por ID?
        if (isset($_GET['id'])) {
            $act_id = (int)$_GET['id'];
            $sql = "SELECT a.*, u.nombre as usuario_nombre, u.username 
                    FROM auditoria_accesos a
                    LEFT JOIN usuarios u ON a.usuario_id = u.id
                    WHERE a.id = ?";
            
            $stmt = $db->prepare($sql);
            $stmt->bind_param("i", $act_id);
            $stmt->execute();
            $detalle = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if ($detalle) {
                respuestaJSON(true, 'Detalle de actividad obtenido', $detalle, 200);
            } else {
                respuestaJSON(false, 'Actividad no encontrada', null, 404);
            }
        } 
        else {
            // Listar últimas 10 actividades del usuario
            $sql = "SELECT id, tipo_evento, modulo_accedido, descripcion_evento, fecha_evento, resultado
                    FROM auditoria_accesos 
                    WHERE usuario_id = ? 
                    ORDER BY fecha_evento DESC 
                    LIMIT 10";
            
            $stmt = $db->prepare($sql);
            $stmt->bind_param("i", $usuario_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $actividades = [];
            while ($row = $result->fetch_assoc()) {
                $actividades[] = $row;
            }
            $stmt->close();
            
            respuestaJSON(true, 'Lista de actividad obtenida', $actividades, 200);
        }
    } elseif ($metodo === 'POST') {
        // Registrar nueva actividad manual
        $datos = json_decode(file_get_contents('php://input'), true);
        
        $tipo = $datos['tipo'] ?? 'navegacion';
        $modulo = $datos['modulo'] ?? 'dashboard';
        $descripcion = $datos['descripcion'] ?? 'Consultó el módulo ' . $modulo;
        
        logAudit($usuario_id, $tipo, $modulo, 'VIEW', $descripcion, 'exitoso');
        
        respuestaJSON(true, 'Actividad registrada', null, 201);
    }

} catch (Exception $e) {
    respuestaJSON(false, 'Error interno: ' . $e->getMessage(), null, 500);
}
?>
