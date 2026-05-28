<?php
/**
 * Wrapper de API para el Motor transaccional en Python de Perfiles y Permisos (NOFTRAB)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config.php';

session_start();

// Validar token de autorización si no hay sesión PHP activa
$bearer_token = null;
$auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (empty($auth_header) && function_exists('apache_request_headers')) {
    $headers = apache_request_headers();
    $auth_header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
}
if (preg_match('/Bearer\s+(.+)$/i', $auth_header, $matches)) {
    $bearer_token = $matches[1];
}

if (!isset($_SESSION['usuario_id']) && empty($bearer_token)) {
    respuestaJSON(false, 'Sesión no válida', null, 401);
}

$usuario_actual = $_SESSION['usuario_id'] ?? 1;

// Solo el Administrador (perfil_id = 1) o alguien con permisos especiales puede gestionar esto
$perfil_usuario = obtenerPerfilUsuario($usuario_actual);
if ($usuario_actual != 1 && $perfil_usuario['id'] != 1 && !tienePermiso($usuario_actual, 'CONF_TOTAL') && !tienePermiso($usuario_actual, 'PER_GESTIONAR')) {
    respuestaJSON(false, 'Acceso denegado: Se requiere perfil de Administrador.', null, 403);
}

$metodo = $_SERVER['REQUEST_METHOD'];
$request_uri = $_SERVER['REQUEST_URI'];
$script_name = $_SERVER['SCRIPT_NAME'];

// Obtener la ruta relativa al script
$ruta = $_SERVER['PATH_INFO'] ?? '';
if (empty($ruta)) {
    $pos = strpos($request_uri, $script_name);
    if ($pos !== false) {
        $ruta = substr($request_uri, $pos + strlen($script_name));
    }
}

$partes = explode('/', trim($ruta, '/'));
$endpoint = $partes[0] ?? '';

$python_script = dirname(__DIR__) . '/perfiles_engine.py';

// Función para ejecutar comando python
function runPythonCommand($cmd, $args = []) {
    $python_bin = 'python'; // Por defecto
    
    // Si conocemos la ruta de Python de WAMP podemos usarla o simplemente 'python'
    $full_cmd = $python_bin . ' ' . escapeshellarg($cmd);
    foreach ($args as $arg) {
        $full_cmd .= ' ' . escapeshellarg($arg);
    }
    
    $output = [];
    $return_var = 0;
    exec($full_cmd . ' 2>&1', $output, $return_var);
    
    $output_str = implode("\n", $output);
    $json = json_decode($output_str, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'exito' => false,
            'mensaje' => 'Error al invocar el motor en Python',
            'error_detalle' => $output_str
        ];
    }
    
    return $json;
}

try {
    if ($endpoint === 'listar' && $metodo === 'GET') {
        // Listar módulos y funciones
        $res = runPythonCommand($python_script, ['list_modules_functions']);
        respuestaJSON($res['exito'], $res['mensaje'], $res['datos'] ?? null);
        
    } elseif ($endpoint === 'obtener' && $metodo === 'GET') {
        // Obtener permisos de un perfil específico
        $perfil_id = intval($partes[1] ?? 0);
        if ($perfil_id === 0) {
            respuestaJSON(false, 'ID de perfil no proporcionado', null, 400);
        }
        
        $res = runPythonCommand($python_script, ['get_profile_permissions', $perfil_id]);
        respuestaJSON($res['exito'], $res['mensaje'], $res['datos'] ?? null);
        
    } elseif ($endpoint === 'guardar' && $metodo === 'POST') {
        // Guardar permisos granulares para un perfil
        $perfil_id = intval($partes[1] ?? 0);
        if ($perfil_id === 0) {
            respuestaJSON(false, 'ID de perfil no proporcionado', null, 400);
        }
        
        $json_input = file_get_contents('php://input');
        
        // Escribir a un archivo temporal para evitar problemas de escape en CLI de Windows
        $temp_file = dirname(__DIR__) . '/logs/temp_permisos_' . $perfil_id . '_' . time() . '.json';
        file_put_contents($temp_file, $json_input);
        
        $res = runPythonCommand($python_script, ['save_profile_permissions', $perfil_id, $temp_file, $usuario_actual]);
        
        // Limpiar archivo temporal
        if (file_exists($temp_file)) {
            unlink($temp_file);
        }
        
        respuestaJSON($res['exito'], $res['mensaje'], null);
        
    } elseif ($endpoint === 'crear' && $metodo === 'POST') {
        // Crear un nuevo perfil
        $json_input = file_get_contents('php://input');
        
        $temp_file = dirname(__DIR__) . '/logs/temp_crear_perfil_' . time() . '.json';
        file_put_contents($temp_file, $json_input);
        
        $res = runPythonCommand($python_script, ['create_profile', $temp_file, $usuario_actual]);
        
        if (file_exists($temp_file)) {
            unlink($temp_file);
        }
        
        respuestaJSON($res['exito'], $res['mensaje'], $res['perfil_id'] ?? null, $res['exito'] ? 201 : 400);
        
    } elseif ($endpoint === 'editar' && ($metodo === 'PUT' || $metodo === 'POST')) {
        // Editar un perfil existente
        $perfil_id = intval($partes[1] ?? 0);
        if ($perfil_id === 0) {
            respuestaJSON(false, 'ID de perfil no proporcionado', null, 400);
        }
        
        $json_input = file_get_contents('php://input');
        
        $temp_file = dirname(__DIR__) . '/logs/temp_editar_perfil_' . $perfil_id . '_' . time() . '.json';
        file_put_contents($temp_file, $json_input);
        
        $res = runPythonCommand($python_script, ['update_profile', $perfil_id, $temp_file, $usuario_actual]);
        
        if (file_exists($temp_file)) {
            unlink($temp_file);
        }
        
        respuestaJSON($res['exito'], $res['mensaje'], null);
        
    } elseif ($endpoint === 'eliminar' && ($metodo === 'DELETE' || $metodo === 'POST')) {
        // Eliminar perfil
        $perfil_id = intval($partes[1] ?? 0);
        if ($perfil_id === 0 && isset($_GET['id'])) {
            $perfil_id = intval($_GET['id']);
        }
        
        if ($perfil_id === 0) {
            respuestaJSON(false, 'ID de perfil no proporcionado', null, 400);
        }
        
        $res = runPythonCommand($python_script, ['delete_profile', $perfil_id, $usuario_actual]);
        respuestaJSON($res['exito'], $res['mensaje'], null);
        
    } else {
        respuestaJSON(false, "Endpoint '$endpoint' no encontrado", null, 404);
    }
} catch (Exception $e) {
    respuestaJSON(false, 'Error en Wrapper API: ' . $e->getMessage(), null, 500);
}
?>
