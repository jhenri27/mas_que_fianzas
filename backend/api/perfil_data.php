<?php
/**
 * API: Obtener datos de Perfil, Permisos Granulares y Políticas del Sistema
 * MAS QUE FIANZAS - Core Asegurador v3.0
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/PerfilManager.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validar token de autorización si no hay sesión PHP activa
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

try {
    $db = Database::getInstance()->getConnection();
    
    // 1. Obtener perfil_id del usuario
    $stmt = $db->prepare("SELECT perfil_id, username, nombre, apellido, email FROM usuarios WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $usuario = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$usuario) {
        http_response_code(404);
        echo json_encode(["exito" => false, "mensaje" => "Usuario no encontrado"]);
        exit;
    }
    
    $perfil_id = (int)$usuario['perfil_id'];
    
    // Permitir consultar otro perfil si es Administrador o tiene permisos de gestión
    if (isset($_GET['perfil_id']) && !empty($_GET['perfil_id'])) {
        $solicitado_id = (int)$_GET['perfil_id'];
        $es_admin = ($usuario_id == 1 || (int)$usuario['perfil_id'] == 1 || tienePermiso($usuario_id, 'CONF_TOTAL') || tienePermiso($usuario_id, 'PER_GESTIONAR'));
        if ($es_admin) {
            $perfil_id = $solicitado_id;
        } else {
            http_response_code(403);
            echo json_encode(["exito" => false, "mensaje" => "Acceso denegado: No tiene permisos para consultar otros perfiles"]);
            exit;
        }
    }
    
    // 2. Obtener el perfil completo con sus permisos
    $perfilManager = new PerfilManager();
    $perfil = $perfilManager->obtenerPerfilCompleto($perfil_id);
    
    if (!$perfil) {
        http_response_code(404);
        echo json_encode(["exito" => false, "mensaje" => "Perfil no encontrado"]);
        exit;
    }
    
    // 3. Obtener el catálogo completo de todos los módulos y funciones del sistema
    // Esto nos permite mostrar los permisos concedidos vs denegados de forma clara en la UI
    $modulos = [];
    $res_mods = $db->query("SELECT id, nombre_modulo, descripcion, icono FROM modulos ORDER BY orden_menu ASC, id ASC");
    while ($m = $res_mods->fetch_assoc()) {
        $m_id = (int)$m['id'];
        $m['funciones'] = [];
        $modulos[$m_id] = $m;
    }
    
    $res_funs = $db->query("SELECT id, modulo_id, nombre_funcion, codigo_funcion, descripcion, tipo_permiso FROM funciones_modulo ORDER BY modulo_id ASC, id ASC");
    while ($f = $res_funs->fetch_assoc()) {
        $m_id = (int)$f['modulo_id'];
        if (isset($modulos[$m_id])) {
            $modulos[$m_id]['funciones'][] = $f;
        }
    }
    
    // 4. Obtener políticas de seguridad
    $politicas = [
        "session_timeout_minutes" => defined('SESSION_TIMEOUT_MINUTES') ? SESSION_TIMEOUT_MINUTES : 30,
        "max_login_attempts" => defined('MAX_LOGIN_ATTEMPTS') ? MAX_LOGIN_ATTEMPTS : 5,
        "lockout_time_minutes" => defined('LOCKOUT_TIME_MINUTES') ? LOCKOUT_TIME_MINUTES : 30,
        "password_expiration_days" => defined('PASSWORD_EXPIRATION_DAYS') ? PASSWORD_EXPIRATION_DAYS : 90,
        "two_factor_enabled" => defined('TWO_FACTOR_ENABLED') ? TWO_FACTOR_ENABLED : false,
        "two_factor_provider" => defined('TWO_FACTOR_PROVIDER') ? TWO_FACTOR_PROVIDER : 'totp'
    ];
    
    // Responder exitosamente
    echo json_encode([
        "exito" => true,
        "datos" => [
            "usuario" => [
                "username" => $usuario['username'],
                "nombre_completo" => $usuario['nombre'] . ' ' . $usuario['apellido'],
                "email" => $usuario['email']
            ],
            "perfil" => [
                "id" => $perfil['id'],
                "nombre_perfil" => $perfil['nombre_perfil'],
                "descripcion" => $perfil['descripcion'],
                "nivel_jerarquico" => $perfil['nivel_jerarquico'],
                "estado" => $perfil['estado']
            ],
            "permisos_activos" => $perfil['permisos'],
            "catalogo_modulos" => array_values($modulos),
            "politicas_seguridad" => $politicas
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["exito" => false, "mensaje" => "Error interno: " . $e->getMessage()]);
}
?>
