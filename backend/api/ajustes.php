<?php
/**
 * API de Ajustes y Auditoría de Expedientes (Norma NOFTRAB v4.0)
 * MAS QUE FIANZAS - Core Asegurador v3.0
 */

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config.php';

// Validar sesión: aceptar PHP session O Bearer token
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
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

$usuario_actual = $usuario_id;

$datos = json_decode(file_get_contents('php://input'), true);

if (!$datos || empty($datos['tipo_ajuste']) || empty($datos['registro_id']) || empty($datos['justificacion'])) {
    http_response_code(400);
    echo json_encode(["exito" => false, "mensaje" => "Datos de ajuste incompletos (se requieren tipo_ajuste, registro_id y justificacion)"]);
    exit;
}

$tipo = $datos['tipo_ajuste']; // 'poliza', 'pago', 'comision'
$registro_id = intval($datos['registro_id']);
$justificacion = trim($datos['justificacion']);

if (strlen($justificacion) < 10) {
    http_response_code(400);
    echo json_encode(["exito" => false, "mensaje" => "La justificación debe tener al menos 10 caracteres para cumplir con los estándares de auditoría de la norma NOFTRAB"]);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Configurar tabla y campos según tipo
    $tabla = '';
    $modulo = '';
    $campoModificar = '';
    $valorNuevo = null;
    
    switch ($tipo) {
        case 'poliza':
            $tabla = 'polizas';
            $modulo = 'Pólizas';
            $campoModificar = $datos['campo'] ?? 'estado';
            $valorNuevo = $datos['valor_nuevo'];
            break;
            
        case 'pago':
            $tabla = 'pagos';
            $modulo = 'Pagos';
            $campoModificar = $datos['campo'] ?? 'estado_pago';
            $valorNuevo = $datos['valor_nuevo'];
            break;
            
        case 'comision':
            $tabla = 'comisiones_poliza';
            $modulo = 'Comisiones';
            $campoModificar = $datos['campo'] ?? 'estado_pago';
            $valorNuevo = $datos['valor_nuevo'];
            break;
            
        default:
            http_response_code(400);
            echo json_encode(["exito" => false, "mensaje" => "Tipo de ajuste no reconocido"]);
            exit;
    }
    
    // 1. Obtener estado anterior (BEFORE)
    $stmt_before = $db->prepare("SELECT * FROM `$tabla` WHERE id = ? LIMIT 1");
    $stmt_before->bind_param("i", $registro_id);
    $stmt_before->execute();
    $res_before = $stmt_before->get_result();
    $valor_anterior = $res_before->fetch_assoc();
    $stmt_before->close();
    
    if (!$valor_anterior) {
        http_response_code(404);
        echo json_encode(["exito" => false, "mensaje" => "El registro a ajustar no existe en la base de datos"]);
        exit;
    }
    
    // Iniciar transacción
    $db->begin_transaction();
    
    // 2. Aplicar la actualización
    // Sanitizamos el nombre del campo ya que no se puede usar con prepare statements tradicionales
    $campoModificarClean = preg_replace('/[^a-zA-Z0-9_]/', '', $campoModificar);
    
    $sql_update = "UPDATE `$tabla` SET `$campoModificarClean` = ? WHERE id = ?";
    $stmt_update = $db->prepare($sql_update);
    if (!$stmt_update) {
        throw new Exception("Error al preparar la actualización: " . $db->error);
    }
    
    // Vincular tipos de datos dinámicos
    if (is_int($valorNuevo)) {
        $stmt_update->bind_param("ii", $valorNuevo, $registro_id);
    } elseif (is_double($valorNuevo) || is_float($valorNuevo)) {
        $stmt_update->bind_param("di", $valorNuevo, $registro_id);
    } else {
        $stmt_update->bind_param("si", $valorNuevo, $registro_id);
    }
    
    if (!$stmt_update->execute()) {
        throw new Exception("Error al ejecutar la actualización: " . $stmt_update->error);
    }
    $stmt_update->close();
    
    // 3. Obtener el estado posterior (AFTER)
    $stmt_after = $db->prepare("SELECT * FROM `$tabla` WHERE id = ? LIMIT 1");
    $stmt_after->bind_param("i", $registro_id);
    $stmt_after->execute();
    $res_after = $stmt_after->get_result();
    $valor_nuevo = $res_after->fetch_assoc();
    $stmt_after->close();
    
    // 4. Registrar en historial_ajustes
    $ok_ajuste = registrarAjuste($usuario_actual, $modulo, $tabla, $registro_id, $valor_anterior, $valor_nuevo, $justificacion);
    
    if (!$ok_ajuste) {
        throw new Exception("Fallo al guardar el historial inmutable de ajustes (NOFTRAB)");
    }
    
    // Confirmar cambios
    $db->commit();
    
    // Registrar también en auditoría de accesos global
    logAudit(
        $usuario_actual, 
        'editar_perfil', 
        $modulo, 
        'ajuste_manual_expediente', 
        "Ajuste en la tabla $tabla registro $registro_id. Campo modificado: $campoModificarClean a valor '$valorNuevo'", 
        'exitoso', 
        null, 
        $tabla, 
        $registro_id, 
        $valor_anterior, 
        $valor_nuevo
    );
    
    echo json_encode([
        "exito" => true,
        "mensaje" => "Ajuste aplicado y registrado de forma inmutable correctamente",
        "data" => [
            "modulo" => $modulo,
            "registro_id" => $registro_id,
            "campo" => $campoModificarClean,
            "valor_nuevo" => $valorNuevo
        ]
    ]);
    
} catch (Exception $e) {
    if (isset($db)) {
        $db->rollback();
    }
    http_response_code(500);
    echo json_encode(["exito" => false, "mensaje" => "Error interno al procesar el ajuste: " . $e->getMessage()]);
}
