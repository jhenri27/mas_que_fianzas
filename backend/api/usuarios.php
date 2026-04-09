<?php
/**
 * API de Gestión de Usuarios
 * Endpoints para crear, editar, eliminar, bloquear usuarios
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
require_once '../UsuarioManager.php';

$metodo = $_SERVER['REQUEST_METHOD'];
$ruta = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$partes = explode('/', $ruta);

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
    $manager = new UsuarioManager();
} catch (Exception $e) {
    $manager = null;
}

try {
    if (strpos($ruta, '/crear') !== false && $metodo === 'POST') {
        // CREAR USUARIO
        $datos = json_decode(file_get_contents('php://input'), true);
        $resultado = $manager->crearUsuario($datos, $usuario_actual);
        respuestaJSON($resultado['exito'], $resultado['mensaje'], $resultado, $resultado['exito'] ? 201 : 400);

    } elseif (strpos($ruta, '/editar/') !== false && $metodo === 'PUT') {
        // EDITAR USUARIO
        $usuario_id = intval(end($partes));
        $datos = json_decode(file_get_contents('php://input'), true);
        $resultado = $manager->editarUsuario($usuario_id, $datos, $usuario_actual);
        respuestaJSON($resultado['exito'], $resultado['mensaje'], null, $resultado['exito'] ? 200 : 400);

    } elseif (strpos($ruta, '/bloquear/') !== false && $metodo === 'POST') {
        // BLOQUEAR USUARIO
        $usuario_id = intval(end($partes));
        $datos = json_decode(file_get_contents('php://input'), true);
        $resultado = $manager->bloquearUsuario($usuario_id, $datos['razon'] ?? 'Bloqueo administrativo', $usuario_actual);
        respuestaJSON($resultado['exito'], $resultado['mensaje'], null, $resultado['exito'] ? 200 : 400);

    } elseif (strpos($ruta, '/desbloquear/') !== false && $metodo === 'POST') {
        // DESBLOQUEAR USUARIO
        $usuario_id = intval(end($partes));
        $resultado = $manager->desbloquearUsuario($usuario_id, $usuario_actual);
        respuestaJSON($resultado['exito'], $resultado['mensaje'], null, $resultado['exito'] ? 200 : 400);

    } elseif (strpos($ruta, '/restablecer-password/') !== false && $metodo === 'POST') {
        // RESTABLECER CONTRASEÑA
        $usuario_id = intval(end($partes));
        $resultado = $manager->restablecerPassword($usuario_id, $usuario_actual);
        respuestaJSON($resultado['exito'], $resultado['mensaje'], $resultado, $resultado['exito'] ? 200 : 400);

    } elseif (strpos($ruta, '/obtener/') !== false && $metodo === 'GET') {
        // OBTENER USUARIO ESPECÍFICO
        $usuario_id = intval(end($partes));
        $usuario = $manager->obtenerUsuarioCompleto($usuario_id);

        if (!$usuario) {
            respuestaJSON(false, 'Usuario no encontrado', null, 404);
        }

        respuestaJSON(true, 'Usuario obtenido', $usuario, 200);

    } elseif (strpos($ruta, '/listar') !== false && $metodo === 'GET') {
        // LISTAR USUARIOS CON PAGINACIÓN
        $pagina = intval($_GET['pagina'] ?? 1);
        $por_pagina = intval($_GET['por_pagina'] ?? 20);
        $filtros = [
            'estado'    => $_GET['estado'] ?? null,
            'perfil_id' => intval($_GET['perfil_id'] ?? 0),
            'buscar'    => $_GET['buscar'] ?? null
        ];

        try {
            $resultado = $manager
                ? $manager->listarUsuarios($pagina, $por_pagina, array_filter($filtros))
                : ['usuarios' => [], 'paginacion' => ['pagina_actual' => 1, 'total_paginas' => 1, 'total_registros' => 0]];
        } catch (Exception $e) {
            $resultado = ['usuarios' => [], 'paginacion' => ['pagina_actual' => 1, 'total_paginas' => 1, 'total_registros' => 0]];
        }
        respuestaJSON(true, 'Usuarios obtenidos', $resultado, 200);

    } elseif (strpos($ruta, '/importar') !== false && $metodo === 'POST') {
        // IMPORTACIÓN MASIVA
        $datos = json_decode(file_get_contents('php://input'), true);
        if (!isset($datos['usuarios']) || !is_array($datos['usuarios'])) {
            respuestaJSON(false, 'Datos de importación inválidos', null, 400);
        }
        $resultado = $manager->importarUsuarios($datos['usuarios'], $usuario_actual);
        respuestaJSON($resultado['exito'], $resultado['mensaje'], $resultado, $resultado['exito'] ? 200 : 400);

    } elseif (strpos($ruta, '/eliminar/') !== false && $metodo === 'DELETE') {

        // ELIMINAR USUARIO
        $usuario_id = intval(end($partes));
        $resultado = $manager->eliminarUsuario($usuario_id, $usuario_actual);
        respuestaJSON($resultado['exito'], $resultado['mensaje'], null, $resultado['exito'] ? 200 : 400);

    } else {
        respuestaJSON(false, 'Endpoint no encontrado', null, 404);
    }

} catch (Exception $e) {
    // Si es listar, devolver estructura vacía en lugar de error 500
    if (strpos($ruta, '/listar') !== false) {
        $data_vacia = ['usuarios' => [], 'paginacion' => ['pagina_actual' => 1, 'total_paginas' => 1, 'total_registros' => 0]];
        respuestaJSON(true, 'Sin datos (BD no disponible)', $data_vacia, 200);
    }
    respuestaJSON(false, 'Error: ' . $e->getMessage(), null, 500);
}

?>
