<?php
/**
 * API de Gestión de Pagos - Core Asegurador v3.0
 * MAS QUE FIANZAS
 */

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config.php';
require_once '../PagoManager.php';

// Validar sesión: aceptar PHP session O Bearer token
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$bearer_token = null;
$auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? (function_exists('apache_request_headers') ? (apache_request_headers()['Authorization'] ?? '') : '');
if (preg_match('/Bearer\s+(.+)$/i', $auth_header, $matches)) {
    $bearer_token = trim($matches[1]);
}

// Fallback robusto en caso de que Apache descarte la cabecera Authorization (muy común en entornos WAMP)
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

// Obtener la acción solicitada
$action = $_GET['action'] ?? '';

// Verificar sesión excepto para el endpoint público de verificar
if (!$usuario_id && $action !== 'verificar') {
    http_response_code(401);
    echo json_encode(["exito" => false, "mensaje" => "Sesión no válida o expirada"]);
    exit;
}

$pagoManager = new PagoManager();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            if ($action === 'verificar') {
                $ref = $_GET['ref'] ?? '';
                if (empty($ref)) {
                    http_response_code(400);
                    echo json_encode(["exito" => false, "mensaje" => "Referencia de pago requerida"]);
                    break;
                }

                $db = Database::getInstance()->getConnection();
                $sql_v = "SELECT p.*, pol.numero_poliza, c.nombre as cliente_nombre, c.cedula as cliente_cedula,
                                 u.nombre as validador_nombre, u.apellido as validador_apellido,
                                 d.ruta_archivo as comprobante_ruta, d.nombre_archivo as comprobante_nombre
                          FROM pagos p
                          JOIN polizas pol ON p.poliza_id = pol.id
                          JOIN clientes c ON p.cliente_id = c.id
                          LEFT JOIN usuarios u ON p.validado_por = u.id
                          LEFT JOIN documentos_poliza d ON d.pago_id = p.id AND d.tipo_documento = 'soporte_pago'
                          WHERE p.numero_referencia = ? LIMIT 1";

                $stmt_v = $db->prepare($sql_v);
                if (!$stmt_v) {
                    http_response_code(500);
                    echo json_encode(["exito" => false, "mensaje" => "Error de base de datos: " . $db->error]);
                    break;
                }
                $stmt_v->bind_param("s", $ref);
                $stmt_v->execute();
                $pago = $stmt_v->get_result()->fetch_assoc();
                $stmt_v->close();

                if (!$pago) {
                    http_response_code(404);
                    echo json_encode(["exito" => false, "mensaje" => "Pago no encontrado"]);
                    break;
                }

                // Obtener número de asiento si está procesado
                $asiento_numero = null;
                if ($pago['estado_pago'] === 'procesado') {
                    $sql_a = "SELECT numero_asiento FROM asientos_contables WHERE modulo_origen = 'pagos' AND referencia_id = ? LIMIT 1";
                    $stmt_a = $db->prepare($sql_a);
                    if ($stmt_a) {
                        $stmt_a->bind_param("i", $pago['id']);
                        $stmt_a->execute();
                        $res_a = $stmt_a->get_result()->fetch_assoc();
                        $asiento_numero = $res_a['numero_asiento'] ?? null;
                        $stmt_a->close();
                    }
                }

                echo json_encode([
                    "exito" => true,
                    "data" => [
                        "id" => $pago['id'],
                        "numero_referencia" => $pago['numero_referencia'],
                        "numero_recibo" => $pago['numero_recibo'],
                        "numero_ncf" => $pago['numero_ncf'],
                        "monto" => floatval($pago['monto']),
                        "fecha_pago" => $pago['fecha_pago'],
                        "tipo_pago" => $pago['tipo_pago'],
                        "banco" => $pago['banco'],
                        "numero_comprobante" => $pago['numero_comprobante'],
                        "estado_pago" => $pago['estado_pago'],
                        "fecha_registro" => $pago['fecha_registro'],
                        "fecha_validacion" => $pago['fecha_validacion'],
                        "validador" => $pago['validado_por'] ? ($pago['validador_nombre'] . " " . $pago['validador_apellido']) : null,
                        "numero_poliza" => $pago['numero_poliza'],
                        "cliente_nombre" => $pago['cliente_nombre'],
                        "cliente_cedula" => $pago['cliente_cedula'],
                        "comprobante_ruta" => $pago['comprobante_ruta'],
                        "comprobante_nombre" => $pago['comprobante_nombre'],
                        "asiento_contable" => $asiento_numero
                    ]
                ]);
            } else {
                $filtros = [];
                if (isset($_GET['poliza_id'])) $filtros['poliza_id'] = $_GET['poliza_id'];
                
                $pagos = $pagoManager->obtenerPagos($filtros);
                echo json_encode(["exito" => true, "data" => $pagos]);
            }
            break;

        case 'POST':
            // Check if multipart/form-data or standard application/json
            $content_type = $_SERVER['CONTENT_TYPE'] ?? '';
            if (stripos($content_type, 'multipart/form-data') !== false || !empty($_POST)) {
                $datos = $_POST;
            } else {
                $datos = json_decode(file_get_contents('php://input'), true);
            }

            if ($action === 'registrar') {
                if (!$datos || empty($datos['poliza_id']) || empty($datos['monto'])) {
                    http_response_code(400);
                    echo json_encode(["exito" => false, "mensaje" => "Datos de pago incompletos (poliza_id y monto requeridos)"]);
                    break;
                }

                // Inject registered_by user
                $datos['registrado_por'] = $usuario_id;

                // Handle file upload if present
                if (isset($_FILES['documento_deposito']) && $_FILES['documento_deposito']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['documento_deposito'];
                    $file_size = $file['size'];
                    $file_name = $file['name'];
                    $file_tmp = $file['tmp_name'];
                    
                    // Validate size (5MB max)
                    if ($file_size > 5 * 1024 * 1024) {
                        http_response_code(400);
                        echo json_encode(["exito" => false, "mensaje" => "El archivo excede el tamaño máximo permitido de 5 MB"]);
                        break;
                    }

                    // Validate extension
                    $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    $allowed_exts = ['pdf', 'png', 'jpg', 'jpeg'];
                    if (!in_array($ext, $allowed_exts)) {
                        http_response_code(400);
                        echo json_encode(["exito" => false, "mensaje" => "Formato de archivo no válido. Solo se permiten: PDF, PNG, JPG, JPEG"]);
                        break;
                    }

                    // Save file
                    $upload_dir = dirname(__FILE__) . '/../uploads/depositos/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }

                    $new_filename = hash('sha256', time() . rand(1000, 9999)) . '.' . $ext;
                    $dest_path = $upload_dir . $new_filename;

                    if (move_uploaded_file($file_tmp, $dest_path)) {
                        $datos['comprobante_nombre'] = $file_name;
                        $datos['comprobante_ruta'] = 'uploads/depositos/' . $new_filename;
                        $datos['comprobante_hash'] = hash_file('sha256', $dest_path);
                    } else {
                        http_response_code(500);
                        echo json_encode(["exito" => false, "mensaje" => "Error al guardar el archivo en el servidor"]);
                        break;
                    }
                }
                
                $resultado = $pagoManager->registrarPago($datos);
                if ($resultado['exito']) {
                    echo json_encode($resultado);
                } else {
                    http_response_code(500);
                    echo json_encode($resultado);
                }
            } elseif ($action === 'aprobar') {
                $pagoId = intval($datos['id'] ?? $_GET['id'] ?? 0);
                if (!$pagoId) {
                    http_response_code(400);
                    echo json_encode(["exito" => false, "mensaje" => "ID de pago requerido"]);
                    break;
                }
                $resultado = $pagoManager->aprobarPago($pagoId, $usuario_id);
                if ($resultado['exito']) {
                    echo json_encode($resultado);
                } else {
                    http_response_code(500);
                    echo json_encode($resultado);
                }
            } elseif ($action === 'rechazar') {
                $pagoId = intval($datos['id'] ?? $_GET['id'] ?? 0);
                if (!$pagoId) {
                    http_response_code(400);
                    echo json_encode(["exito" => false, "mensaje" => "ID de pago requerido"]);
                    break;
                }
                $resultado = $pagoManager->rechazarPago($pagoId, $usuario_id);
                if ($resultado['exito']) {
                    echo json_encode($resultado);
                } else {
                    http_response_code(500);
                    echo json_encode($resultado);
                }
            } else {
                http_response_code(400);
                echo json_encode(["exito" => false, "mensaje" => "Acción POST no válida"]);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(["exito" => false, "mensaje" => "Método no permitido"]);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["exito" => false, "mensaje" => "Error del servidor: " . $e->getMessage()]);
}
?>
