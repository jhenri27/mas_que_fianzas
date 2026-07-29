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

// Validar sesión: aceptar PHP session O Bearer token del header Authorization
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
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

$usuario_actual = $usuario_id;
$metodo = $_SERVER['REQUEST_METHOD'];
$ruta = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

try {
    $manager = new ClienteManager();
} catch (Exception $e) {
    respuestaJSON(false, 'BD no disponible: ' . $e->getMessage(), null, 500);
    exit;
}

try {
    $action = $_GET['action'] ?? '';
    if ((strpos($ruta, '/crear') !== false || $action === 'crear') && $metodo === 'POST') {
        $datos = json_decode(file_get_contents('php://input'), true);
        if (empty($datos['nombre_razon_social']) || empty($datos['rnc'])) {
             respuestaJSON(false, 'Nombre/Razón Social y RNC/Cédula son obligatorios', null, 400);
             exit;
        }
        
        // Validación técnica Dominicana (NOFTRAB)
        if (ValidadorDocumentos::isValidatorActive('clientes')) {
            $tipoDoc = $datos['tipo_documento'] ?? null;
            if (!ValidadorDocumentos::validarDocumento($datos['rnc'], $tipoDoc)) {
                $msg = ($tipoDoc === 'pasaporte') ? 'El pasaporte especificado no es válido (formato incorrecto).' : 'El RNC o Cédula especificado no es válido (dígito verificador incorrecto).';
                respuestaJSON(false, $msg, null, 400);
                exit;
            }
            if (!empty($datos['telefono']) && !ValidadorDocumentos::validarTelefono($datos['telefono'])) {
                respuestaJSON(false, 'El teléfono especificado no es válido (debe tener 10 dígitos y código de área 809, 829 o 849).', null, 400);
                exit;
            }
        }

        $datos['creado_por'] = $usuario_actual;
        $resultado = $manager->crearCliente($datos);
        respuestaJSON($resultado['exito'], $resultado['mensaje'], $resultado, $resultado['exito'] ? 201 : 400);

    } elseif (strpos($ruta, '/importar') !== false && $metodo === 'POST') {
        $datos = json_decode(file_get_contents('php://input'), true);
        if (empty($datos) || !isset($datos['clientes']) || !is_array($datos['clientes'])) {
            respuestaJSON(false, 'Formato de importación inválido', null, 400);
            exit;
        }
        $resultado = $manager->importarClientesMasivo($datos['clientes'], $usuario_actual);
        respuestaJSON($resultado['exito'], $resultado['mensaje'], $resultado, 201);

    } elseif (strpos($ruta, '/editar/') !== false && $metodo === 'PUT') {
        $partes = explode('/', $ruta);
        $id = intval(end($partes));
        $datos = json_decode(file_get_contents('php://input'), true);
        if (empty($datos['nombre_razon_social']) || empty($datos['rnc'])) {
             respuestaJSON(false, 'Nombre/Razón Social y RNC/Cédula son obligatorios', null, 400);
             exit;
        }
        
        // Validación técnica Dominicana (NOFTRAB)
        if (ValidadorDocumentos::isValidatorActive('clientes')) {
            $tipoDoc = $datos['tipo_documento'] ?? null;
            if (!ValidadorDocumentos::validarDocumento($datos['rnc'], $tipoDoc)) {
                $msg = ($tipoDoc === 'pasaporte') ? 'El pasaporte especificado no es válido (formato incorrecto).' : 'El RNC o Cédula especificado no es válido (dígito verificador incorrecto).';
                respuestaJSON(false, $msg, null, 400);
                exit;
            }
            if (!empty($datos['telefono']) && !ValidadorDocumentos::validarTelefono($datos['telefono'])) {
                respuestaJSON(false, 'El teléfono especificado no es válido (debe tener 10 dígitos y código de área 809, 829 o 849).', null, 400);
                exit;
            }
        }

        if (restringirSoloPropios($usuario_actual, 'clientes')) {
            if (!$manager->verificarCreador($id, $usuario_actual)) {
                respuestaJSON(false, 'No tiene permiso para editar este cliente', null, 403);
                exit;
            }
        }
        $resultado = $manager->editarCliente($id, $datos);
        respuestaJSON($resultado['exito'], $resultado['mensaje'], null, $resultado['exito'] ? 200 : 400);

    } elseif ($metodo === 'GET' && (strpos($ruta, '/listar') !== false || substr($ruta, -12) === 'clientes.php')) {
        // Soporte de búsqueda por parámetro ?search= (si está vacío, retorna los 15 más recientes)
        if (isset($_GET['search'])) {
            $searchVal = trim($_GET['search']);
            $db = Database::getInstance()->getConnection();
            if ($searchVal !== '') {
                $q = '%' . $searchVal . '%';
                if (restringirSoloPropios($usuario_actual, 'clientes')) {
                    $stmt = $db->prepare(
                        "SELECT id, nombre as nombre_razon_social, cedula as rnc, 
                                telefono, email,
                                IF(tipo_cliente='empresa','Juridica','Fisica') as tipo_persona,
                                estado as estatus
                         FROM clientes 
                         WHERE (nombre LIKE ? OR cedula LIKE ? OR razon_social LIKE ? OR email LIKE ? OR telefono LIKE ?) 
                           AND estado = 'activo'
                           AND creado_por = ?
                         ORDER BY nombre LIMIT 15"
                    );
                    $stmt->bind_param('sssssi', $q, $q, $q, $q, $q, $usuario_actual);
                } else {
                    $stmt = $db->prepare(
                        "SELECT id, nombre as nombre_razon_social, cedula as rnc, 
                                telefono, email,
                                IF(tipo_cliente='empresa','Juridica','Fisica') as tipo_persona,
                                estado as estatus
                         FROM clientes 
                         WHERE (nombre LIKE ? OR cedula LIKE ? OR razon_social LIKE ? OR email LIKE ? OR telefono LIKE ?) 
                           AND estado = 'activo'
                         ORDER BY nombre LIMIT 15"
                    );
                    $stmt->bind_param('sssss', $q, $q, $q, $q, $q);
                }
            } else {
                // Si la búsqueda es vacía (onfocus), listar los 15 más recientes
                if (restringirSoloPropios($usuario_actual, 'clientes')) {
                    $stmt = $db->prepare(
                        "SELECT id, nombre as nombre_razon_social, cedula as rnc, 
                                telefono, email,
                                IF(tipo_cliente='empresa','Juridica','Fisica') as tipo_persona,
                                estado as estatus
                         FROM clientes 
                         WHERE estado = 'activo' AND creado_por = ?
                         ORDER BY id DESC LIMIT 15"
                    );
                    $stmt->bind_param('i', $usuario_actual);
                } else {
                    $stmt = $db->prepare(
                        "SELECT id, nombre as nombre_razon_social, cedula as rnc, 
                                telefono, email,
                                IF(tipo_cliente='empresa','Juridica','Fisica') as tipo_persona,
                                estado as estatus
                         FROM clientes 
                         WHERE estado = 'activo'
                         ORDER BY id DESC LIMIT 15"
                    );
                }
            }
            $stmt->execute();
            $clientes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        } else {
            $clientes = $manager->listarClientes($usuario_actual);
        }
        respuestaJSON(true, 'Clientes obtenidos', $clientes, 200);

    } else {
        respuestaJSON(false, 'Endpoint no encontrado', null, 404);
    }
} catch (Exception $e) {
    respuestaJSON(false, 'Error interno: ' . $e->getMessage(), null, 500);
}
?>
