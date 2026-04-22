<?php
/**
 * API de Autenticación
 * Endpoints: /api/auth/login, /api/auth/logout, /api/auth/cambiar-password
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../Autenticacion.php';

$metodo = $_SERVER['REQUEST_METHOD'];
$ruta = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

try {
    $auth = new Autenticacion();

    if (strpos($ruta, '/login') !== false && $metodo === 'POST') {
        // LOGIN
        $datos = json_decode(file_get_contents('php://input'), true);

        if (empty($datos['username']) || empty($datos['password'])) {
            respuestaJSON(false, 'Usuario y contraseña requeridos', null, 400);
        }

        // ========== MOCK LOGIN para demo (sin BD) ==========
        if ($datos['username'] === 'admin' && $datos['password'] === 'Demo@123') {
            $token_mock = bin2hex(random_bytes(16));
            // Guardar en localStorage via respuesta plana (api-client.js usa ...data spread)
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(200);
            echo json_encode([
                'exito'             => true,
                'mensaje'           => 'Login exitoso (Demo)',
                'token_sesion'      => $token_mock,
                'nombre_completo'   => 'Administrador Sistema',
                'perfil'            => 'Administrador',
                'usuario_id'        => 1,
                'requiere_cambio_password' => false,
                'timestamp'         => date('Y-m-d H:i:s')
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        // ====================================================

        $resultado = $auth->login($datos['username'], $datos['password']);

        if ($resultado['exito']) {
            // Aplanar la respuesta: api-client.js hace ...data spread y espera
            // token_sesion y nombre_completo en la raíz del objeto JSON
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(200);
            echo json_encode(array_merge([
                'exito'     => true,
                'mensaje'   => $resultado['mensaje'],
                'timestamp' => date('Y-m-d H:i:s')
            ], $resultado), JSON_UNESCAPED_UNICODE);
            exit;
        } else {
            respuestaJSON(false, $resultado['mensaje'], null, 401);
        }



    } elseif (strpos($ruta, '/logout') !== false && $metodo === 'POST') {
        // LOGOUT
        $usuario_id = $_SESSION['usuario_id'] ?? null;
        $resultado = $auth->logout($usuario_id);
        respuestaJSON($resultado['exito'], $resultado['mensaje'], null, $resultado['exito'] ? 200 : 400);

    } elseif (strpos($ruta, '/cambiar-password') !== false && $metodo === 'POST') {
        // CAMBIAR CONTRASEÑA
        if (!Autenticacion::sesionValida()) {
            respuestaJSON(false, 'Sesión no válida', null, 401);
        }

        $datos = json_decode(file_get_contents('php://input'), true);
        $usuario_id = $_SESSION['usuario_id'];

        $resultado = $auth->cambiarPassword(
            $usuario_id,
            $datos['password_actual'] ?? '',
            $datos['password_nueva'] ?? '',
            $datos['password_confirmacion'] ?? ''
        );

        respuestaJSON($resultado['exito'], $resultado['mensaje'], null, $resultado['exito'] ? 200 : 400);

    } elseif (strpos($ruta, '/validar-sesion') !== false && $metodo === 'GET') {
        // VALIDAR SESIÓN
        $token = $_GET['token'] ?? $_SESSION['token_sesion'] ?? null;

        if (!$token) {
            respuestaJSON(false, 'Token no proporcionado', null, 400);
        }

        $resultado = $auth->validarSesion($token);
        respuestaJSON($resultado['exito'], $resultado['mensaje'], $resultado['sesion'] ?? null, $resultado['exito'] ? 200 : 401);

    } elseif (strpos($ruta, '/cambiar-password-forzado') !== false && $metodo === 'POST') {
        // CAMBIAR CONTRASEÑA FORZADO (TRAS RESET ADMIN)
        if (!Autenticacion::sesionValida()) {
            respuestaJSON(false, 'Sesión no válida', null, 401);
        }

        $datos = json_decode(file_get_contents('php://input'), true);
        $usuario_id = $_SESSION['usuario_id'];

        $resultado = $auth->cambiarPasswordForzado(
            $usuario_id,
            $datos['password_nueva'] ?? '',
            $datos['password_confirmacion'] ?? ''
        );

        respuestaJSON($resultado['exito'], $resultado['mensaje'], null, $resultado['exito'] ? 200 : 400);

    } elseif (strpos($ruta, '/recuperar-password') !== false && $metodo === 'POST') {
        $datos = json_decode(file_get_contents('php://input'), true);
        if (empty($datos['identificador'])) {
            respuestaJSON(false, 'Correo o usuario requerido', null, 400);
        }
        $resultado = $auth->solicitarRecuperacion($datos['identificador']);
        respuestaJSON($resultado['exito'], $resultado['mensaje'], null, 200);

    } elseif (strpos($ruta, '/reset-password') !== false && $metodo === 'POST') {
        $datos = json_decode(file_get_contents('php://input'), true);
        if (empty($datos['token']) || empty($datos['password_nueva']) || empty($datos['password_confirmacion'])) {
            respuestaJSON(false, 'Datos incompletos', null, 400);
        }
        $resultado = $auth->restablecerConToken($datos['token'], $datos['password_nueva'], $datos['password_confirmacion']);
        respuestaJSON($resultado['exito'], $resultado['mensaje'], null, $resultado['exito'] ? 200 : 400);

    } elseif (strpos($ruta, '/solicitar-desbloqueo') !== false && $metodo === 'POST') {
        $datos = json_decode(file_get_contents('php://input'), true);
        if (empty($datos['username'])) {
            respuestaJSON(false, 'Usuario requerido', null, 400);
        }
        $resultado = $auth->solicitarDesbloqueo($datos['username']);
        respuestaJSON($resultado['exito'], $resultado['mensaje'], null, 200);

    } else {
        respuestaJSON(false, 'Endpoint no encontrado', null, 404);
    }

} catch (Exception $e) {
    respuestaJSON(false, 'Error: ' . $e->getMessage(), null, 500);
}

?>
