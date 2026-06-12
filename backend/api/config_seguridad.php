<?php
/**
 * API: Configuración de Políticas de Seguridad Setup (NOFTRAB)
 * Endpoints: GET /api/config_seguridad.php, POST /api/config_seguridad.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config.php';

session_start();

// Validar token de autorización si no hay sesión PHP activa
$bearer_token = null;
$auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? (function_exists('apache_request_headers') ? (apache_request_headers()['Authorization'] ?? '') : '');
if (preg_match('/Bearer\s+(.+)$/i', $auth_header, $matches)) {
    $bearer_token = trim($matches[1]);
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
    respuestaJSON(false, 'Sesión no válida o expirada', null, 401);
}

$db = Database::getInstance()->getConnection();
$metodo = $_SERVER['REQUEST_METHOD'];

try {
    if ($metodo === 'GET') {
        // Obtener valores actuales de políticas
        $res = $db->query("SELECT clave_config, valor_config FROM configuracion_sistema WHERE clave_config IN ('INTENTOS_LOGIN_MAX', 'MINUTOS_BLOQUEO', 'DIAS_EXPIRATION_PASSWORD', 'SESION_TIMEOUT_MINUTES', 'DOS_FACTOR_OPCIONAL')");
        
        $configuraciones = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $configuraciones[$row['clave_config']] = $row['valor_config'];
            }
        }
        
        // Mapear con fallbacks
        $datos = [
            'session_timeout_minutes' => isset($configuraciones['SESION_TIMEOUT_MINUTES']) ? (int)$configuraciones['SESION_TIMEOUT_MINUTES'] : 30,
            'max_login_attempts' => isset($configuraciones['INTENTOS_LOGIN_MAX']) ? (int)$configuraciones['INTENTOS_LOGIN_MAX'] : 5,
            'lockout_time_minutes' => isset($configuraciones['MINUTOS_BLOQUEO']) ? (int)$configuraciones['MINUTOS_BLOQUEO'] : 30,
            'password_expiration_days' => isset($configuraciones['DIAS_EXPIRATION_PASSWORD']) ? (int)$configuraciones['DIAS_EXPIRATION_PASSWORD'] : 90,
            'two_factor_enabled' => isset($configuraciones['DOS_FACTOR_OPCIONAL']) ? ((int)$configuraciones['DOS_FACTOR_OPCIONAL'] === 1) : false
        ];
        
        respuestaJSON(true, 'Configuraciones de seguridad obtenidas', $datos, 200);
        
    } elseif ($metodo === 'POST') {
        // Modificar políticas (Solo Administrador)
        $perfil_usuario = obtenerPerfilUsuario($usuario_id);
        $es_admin = ($usuario_id == 1 || (int)$perfil_usuario['id'] == 1 || tienePermiso($usuario_id, 'CONF_SEGURIDAD_POLITICAS_EDITAR') || tienePermiso($usuario_id, 'CONF_TOTAL'));
        
        if (!$es_admin) {
            respuestaJSON(false, 'Acceso denegado: Se requiere perfil de Administrador.', null, 403);
        }
        
        $datos = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($datos['session_timeout_minutes']) || !isset($datos['max_login_attempts']) || 
            !isset($datos['lockout_time_minutes']) || !isset($datos['password_expiration_days'])) {
            respuestaJSON(false, 'Parámetros incompletos', null, 400);
        }
        
        // 1. Obtener valores antiguos para la auditoría (NOFTRAB)
        $res_ant = $db->query("SELECT clave_config, valor_config FROM configuracion_sistema WHERE clave_config IN ('INTENTOS_LOGIN_MAX', 'MINUTOS_BLOQUEO', 'DIAS_EXPIRATION_PASSWORD', 'SESION_TIMEOUT_MINUTES', 'DOS_FACTOR_OPCIONAL')");
        $valores_antiguos = [];
        if ($res_ant) {
            while ($row = $res_ant->fetch_assoc()) {
                $valores_antiguos[$row['clave_config']] = $row['valor_config'];
            }
        }
        
        $session_timeout = (int)$datos['session_timeout_minutes'];
        $max_attempts = (int)$datos['max_login_attempts'];
        $lockout_time = (int)$datos['lockout_time_minutes'];
        $pwd_expiry = (int)$datos['password_expiration_days'];
        $two_factor = isset($datos['two_factor_enabled']) && ($datos['two_factor_enabled'] === true || (int)$datos['two_factor_enabled'] === 1) ? 1 : 0;
        
        // Iniciar transacción
        $db->begin_transaction();
        
        $sql_upd = "UPDATE configuracion_sistema SET valor_config = ? WHERE clave_config = ?";
        $stmt_upd = $db->prepare($sql_upd);
        
        // SESION_TIMEOUT_MINUTES
        $clave = 'SESION_TIMEOUT_MINUTES';
        $val_str = (string)$session_timeout;
        $stmt_upd->bind_param('ss', $val_str, $clave);
        $stmt_upd->execute();
        
        // INTENTOS_LOGIN_MAX
        $clave = 'INTENTOS_LOGIN_MAX';
        $val_str = (string)$max_attempts;
        $stmt_upd->bind_param('ss', $val_str, $clave);
        $stmt_upd->execute();
        
        // MINUTOS_BLOQUEO
        $clave = 'MINUTOS_BLOQUEO';
        $val_str = (string)$lockout_time;
        $stmt_upd->bind_param('ss', $val_str, $clave);
        $stmt_upd->execute();
        
        // DIAS_EXPIRATION_PASSWORD
        $clave = 'DIAS_EXPIRATION_PASSWORD';
        $val_str = (string)$pwd_expiry;
        $stmt_upd->bind_param('ss', $val_str, $clave);
        $stmt_upd->execute();
        
        // DOS_FACTOR_OPCIONAL
        $clave = 'DOS_FACTOR_OPCIONAL';
        $val_str = (string)$two_factor;
        $stmt_upd->bind_param('ss', $val_str, $clave);
        $stmt_upd->execute();
        
        $stmt_upd->close();
        
        $valores_nuevos = [
            'SESION_TIMEOUT_MINUTES' => $session_timeout,
            'INTENTOS_LOGIN_MAX' => $max_attempts,
            'MINUTOS_BLOQUEO' => $lockout_time,
            'DIAS_EXPIRATION_PASSWORD' => $pwd_expiry,
            'DOS_FACTOR_OPCIONAL' => $two_factor
        ];
        
        // Registrar Auditoría detallada (NOFTRAB)
        logAudit(
            $usuario_id,
            'editar_configuracion_seguridad',
            'configuracion',
            'CONF_SEGURIDAD_POLITICAS_EDITAR',
            "Políticas de seguridad modificadas por el Administrador.",
            'exitoso',
            null,
            'configuracion_sistema',
            1,
            $valores_antiguos,
            $valores_nuevos
        );
        
        $db->commit();
        
        respuestaJSON(true, 'Políticas de seguridad actualizadas con éxito.', null, 200);
    } else {
        respuestaJSON(false, 'Método no permitido', null, 405);
    }
} catch (Exception $e) {
    if (isset($db)) {
        $db->rollback();
    }
    respuestaJSON(false, 'Error en el servidor: ' . $e->getMessage(), null, 500);
}
?>
