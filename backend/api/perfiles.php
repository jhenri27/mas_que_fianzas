<?php
/**
 * API de Gestión de Perfiles y Permisos
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
require_once '../PerfilManager.php';

$metodo = $_SERVER['REQUEST_METHOD'];
$request_uri = $_SERVER['REQUEST_URI'];
$script_name = $_SERVER['SCRIPT_NAME'];

// Obtener la ruta relativa al script
$ruta = $_SERVER['PATH_INFO'] ?? '';
if (empty($ruta)) {
    // Fallback si PATH_INFO no está disponible
    $pos = strpos($request_uri, $script_name);
    if ($pos !== false) {
        $ruta = substr($request_uri, $pos + strlen($script_name));
    } else {
        // Caso para URLs sin .php (MultiViews)
        $script_dir = dirname($script_name);
        $script_base = basename($script_name, '.php');
        $pattern = '#^' . preg_quote($script_dir) . '/' . preg_quote($script_base) . '(/?.*)#';
        if (preg_match($pattern, $request_uri, $matches)) {
            $ruta = $matches[1];
        }
    }
}

$partes = explode('/', trim($ruta, '/'));
$endpoint = $partes[0] ?? '';

// Validar sesión: aceptar PHP session O Bearer token del header Authorization
session_start();
$bearer_token = null;
$auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? apache_request_headers()['Authorization'] ?? '';
if (preg_match('/Bearer\s+(.+)$/i', $auth_header, $matches)) {
    $bearer_token = $matches[1];
}

if (!isset($_SESSION['usuario_id']) && empty($bearer_token)) {
    respuestaJSON(false, 'Sesión no válida', null, 401);
}

// En modo demo: usuario_id = 1 si viene por Bearer token
$usuario_actual = $_SESSION['usuario_id'] ?? 1;

// Crear manager (puede fallar si la BD no está disponible)
try {
    $manager = new PerfilManager();
} catch (Exception $e) {
    $manager = null;
}

try {
    if ($endpoint === 'crear' && $metodo === 'POST') {
        // CREAR PERFIL
        $datos = json_decode(file_get_contents('php://input'), true);
        $resultado = $manager->crearPerfil($datos, $usuario_actual);
        respuestaJSON($resultado['exito'], $resultado['mensaje'], $resultado, $resultado['exito'] ? 201 : 400);

    } elseif ($endpoint === 'editar' && ($metodo === 'PUT' || $metodo === 'POST')) {
        // EDITAR PERFIL
        $perfil_id = intval($partes[1] ?? 0);
        $datos = json_decode(file_get_contents('php://input'), true);
        $resultado = $manager->editarPerfil($perfil_id, $datos, $usuario_actual);
        respuestaJSON($resultado['exito'], $resultado['mensaje'], null, $resultado['exito'] ? 200 : 400);

    } elseif ($endpoint === 'asignar-permisos' && $metodo === 'POST') {
        // ASIGNAR PERMISOS A PERFIL
        $perfil_id = intval($partes[1] ?? 0);
        $datos = json_decode(file_get_contents('php://input'), true);
        $permisos = $datos['permisos'] ?? [];
        $resultado = $manager->asignarPermisosAPerfil($perfil_id, $permisos, $usuario_actual);
        respuestaJSON($resultado['exito'], $resultado['mensaje'], null, $resultado['exito'] ? 200 : 400);

    } elseif ($endpoint === 'obtener' && $metodo === 'GET') {
        // OBTENER PERFIL COMPLETO CON PERMISOS
        $perfil_id = intval($partes[1] ?? 0);
        $perfil = $manager->obtenerPerfilCompleto($perfil_id);

        if (!$perfil) {
            respuestaJSON(false, 'Perfil no encontrado', null, 404);
        }

        respuestaJSON(true, 'Perfil obtenido', $perfil, 200);

    } elseif ($endpoint === 'listar' && $metodo === 'GET') {
        // LISTAR PERFILES
        try {
            $perfiles = $manager ? $manager->listarPerfiles() : [];
        } catch (Exception $e) {
            $perfiles = [];
        }
        respuestaJSON(true, 'Perfiles obtenidos', $perfiles, 200);

    } elseif ($endpoint === 'malla-permisos' && $metodo === 'GET') {
        // OBTENER MALLA DE PERMISOS
        $malla = method_exists($manager, 'obtenerMallaPermisos') ? $manager->obtenerMallaPermisos() : [];
        respuestaJSON(true, 'Malla de permisos obtenida', $malla, 200);

    } elseif ($endpoint === 'eliminar' && ($metodo === 'DELETE' || $metodo === 'POST')) {
        // ELIMINAR PERFIL
        $perfil_id = intval($partes[1] ?? 0);
        if ($perfil_id === 0 && isset($_GET['id'])) $perfil_id = intval($_GET['id']);
        
        $resultado = $manager->eliminarPerfil($perfil_id, $usuario_actual);
        respuestaJSON($resultado['exito'], $resultado['mensaje'], null, $resultado['exito'] ? 200 : 400);

    } else {
        respuestaJSON(false, "Endpoint '$endpoint' no encontrado en $ruta", null, 404);
    }

} catch (Exception $e) {
    // Si es una solicitud de listar, devolver lista vacía en lugar de error 500
    if (strpos($ruta, '/listar') !== false || strpos($ruta, '/malla-permisos') !== false) {
        respuestaJSON(true, 'Sin datos (BD no disponible)', [], 200);
    }
    respuestaJSON(false, 'Error: ' . $e->getMessage(), null, 500);
}

?>
