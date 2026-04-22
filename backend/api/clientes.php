<?php
/**
 * API de Gestión de Clientes
 * Endpoints para crear y listar clientes
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config.php';
require_once '../ClienteManager.php';

$metodo = $_SERVER['REQUEST_METHOD'];
$ruta = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Validar sesión opcional
session_start();

try {
    $manager = new ClienteManager();
} catch (Exception $e) {
    respuestaJSON(false, 'BD no disponible: ' . $e->getMessage(), null, 500);
    exit;
}

try {
    if (strpos($ruta, '/crear') !== false && $metodo === 'POST') {
        $datos = json_decode(file_get_contents('php://input'), true);
        if (empty($datos['nombre_razon_social']) || empty($datos['rnc'])) {
             respuestaJSON(false, 'Nombre/Razón Social y RNC son obligatorios', null, 400);
             exit;
        }
        $resultado = $manager->crearCliente($datos);
        respuestaJSON($resultado['exito'], $resultado['mensaje'], $resultado, $resultado['exito'] ? 201 : 400);

    } elseif (strpos($ruta, '/importar') !== false && $metodo === 'POST') {
        $datos = json_decode(file_get_contents('php://input'), true);
        if (empty($datos) || !isset($datos['clientes']) || !is_array($datos['clientes'])) {
            respuestaJSON(false, 'Formato de importación inválido', null, 400);
            exit;
        }
        $resultado = $manager->importarClientesMasivo($datos['clientes']);
        respuestaJSON($resultado['exito'], $resultado['mensaje'], $resultado, 201);

    } elseif (strpos($ruta, '/editar/') !== false && $metodo === 'PUT') {
        $partes = explode('/', $ruta);
        $id = intval(end($partes));
        $datos = json_decode(file_get_contents('php://input'), true);
        if (empty($datos['nombre_razon_social']) || empty($datos['rnc'])) {
             respuestaJSON(false, 'Nombre/Razón Social y RNC son obligatorios', null, 400);
             exit;
        }
        $resultado = $manager->editarCliente($id, $datos);
        respuestaJSON($resultado['exito'], $resultado['mensaje'], null, $resultado['exito'] ? 200 : 400);

    } elseif ($metodo === 'GET' && (strpos($ruta, '/listar') !== false || substr($ruta, -12) === 'clientes.php')) {
        $clientes = $manager->listarClientes();
        respuestaJSON(true, 'Clientes obtenidos', $clientes, 200);

    } else {
        respuestaJSON(false, 'Endpoint no encontrado', null, 404);
    }
} catch (Exception $e) {
    respuestaJSON(false, 'Error interno: ' . $e->getMessage(), null, 500);
}
?>
