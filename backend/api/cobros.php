<?php
/**
 * API del Portal de Gestión de Cobros (PGC) y Motor de Prorrata
 * MAS QUE FIANZAS - Core Asegurador v3.0
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
require_once '../CobroManager.php';
require_once '../CobroBot.php';

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

// Control Granular de Permisos (NOFTRAB): Solo perfiles autorizados (PAG_PGC_ACCEDER)
if (!tienePermiso($usuario_id, 'PAG_PGC_ACCEDER') && !tienePermiso($usuario_id, 'PAG_TOTAL')) {
    http_response_code(403);
    echo json_encode(["exito" => false, "mensaje" => "Acceso restringido: No posee permisos (PAG_PGC_ACCEDER) para el Portal de Gestión de Cobros."]);
    exit;
}

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$cobroManager = new CobroManager();
$db = Database::getInstance()->getConnection();

try {
    switch ($method) {
        case 'GET':
            if ($action === 'get_reporte') {
                $reporte = $cobroManager->obtenerReporteProrataYFinanzas($usuario_id);
                echo json_encode(["exito" => true, "data" => $reporte]);
            } elseif ($action === 'listar_gestiones') {
                $polizaId = intval($_GET['poliza_id'] ?? 0);
                if (!$polizaId) {
                    http_response_code(400);
                    echo json_encode(["exito" => false, "mensaje" => "ID de póliza requerido"]);
                    break;
                }
                $gestiones = $cobroManager->obtenerHistorialGestiones($polizaId);
                echo json_encode(["exito" => true, "data" => $gestiones]);
            } elseif ($action === 'get_bot_config') {
                $res = $db->query("SELECT valor_config FROM configuracion_sistema WHERE clave_config = 'PGC_BOT_ACTIVO' LIMIT 1");
                $activo = 1;
                if ($res && $row = $res->fetch_assoc()) {
                    $activo = intval($row['valor_config']);
                }
                echo json_encode(["exito" => true, "bot_activo" => $activo]);
            } else {
                http_response_code(400);
                echo json_encode(["exito" => false, "mensaje" => "Acción GET no válida"]);
            }
            break;

        case 'POST':
            $datos = json_decode(file_get_contents('php://input'), true);
            if (!$datos) $datos = $_POST;

            if ($action === 'registrar_gestion') {
                if (empty($datos['poliza_id']) || empty($datos['tipo_gestion'])) {
                    http_response_code(400);
                    echo json_encode(["exito" => false, "mensaje" => "Datos de gestión incompletos (poliza_id y tipo_gestion requeridos)"]);
                    break;
                }
                
                $datos['usuario_id'] = $usuario_id;
                $res = $cobroManager->registrarGestion($datos);
                echo json_encode($res);
            } elseif ($action === 'save_bot_config') {
                // Requiere permisos administrativos adicionales para cambiar configuraciones generales
                if (!tienePermiso($usuario_id, 'CF_GESTIONAR_NCF') && !tienePermiso($usuario_id, 'PAG_TOTAL')) {
                    http_response_code(403);
                    echo json_encode(["exito" => false, "mensaje" => "No posee permisos (CF_GESTIONAR_NCF) para modificar configuraciones globales del bot."]);
                    break;
                }

                if (!isset($datos['bot_activo'])) {
                    http_response_code(400);
                    echo json_encode(["exito" => false, "mensaje" => "Parámetro bot_activo requerido"]);
                    break;
                }

                $nuevoValor = intval($datos['bot_activo']) === 1 ? '1' : '0';

                // Obtener valor actual
                $res_act = $db->query("SELECT valor_config FROM configuracion_sistema WHERE clave_config = 'PGC_BOT_ACTIVO' LIMIT 1");
                $valorAnterior = "1";
                if ($res_act && $row_act = $res_act->fetch_assoc()) {
                    $valorAnterior = $row_act['valor_config'];
                }

                $stmt = $db->prepare("INSERT INTO configuracion_sistema (clave_config, valor_config) VALUES ('PGC_BOT_ACTIVO', ?) ON DUPLICATE KEY UPDATE valor_config = ?");
                $stmt->bind_param("ss", $nuevoValor, $nuevoValor);
                
                if ($stmt->execute()) {
                    $stmt->close();
                    $justificacion = "Modificación de la configuración del asistente de cobros automatizado global a estado " . ($nuevoValor === '1' ? 'ACTIVO' : 'INACTIVO');
                    registrarAjuste($usuario_id, 'configuracion', 'PGC_BOT_ACTIVO', 0, ['bot_activo' => $valorAnterior], ['bot_activo' => $nuevoValor], $justificacion);
                    echo json_encode(["exito" => true, "mensaje" => "Configuración del bot guardada correctamente"]);
                } else {
                    $stmt->close();
                    echo json_encode(["exito" => false, "mensaje" => "Error al actualizar configuración: " . $db->error]);
                }
            } elseif ($action === 'toggle_bot_poliza') {
                $polizaId = intval($datos['poliza_id'] ?? 0);
                if (!$polizaId) {
                    http_response_code(400);
                    echo json_encode(["exito" => false, "mensaje" => "ID de póliza requerido"]);
                    break;
                }

                // Obtener estado actual
                $stmt_curr = $db->prepare("SELECT bot_excluir, numero_poliza FROM polizas WHERE id = ? LIMIT 1");
                $stmt_curr->bind_param("i", $polizaId);
                $stmt_curr->execute();
                $polData = $stmt_curr->get_result()->fetch_assoc();
                $stmt_curr->close();

                if (!$polData) {
                    http_response_code(404);
                    echo json_encode(["exito" => false, "mensaje" => "Póliza no encontrada"]);
                    break;
                }

                $nuevoEstado = ($polData['bot_excluir'] == 1) ? 0 : 1;
                $stmt_upd = $db->prepare("UPDATE polizas SET bot_excluir = ? WHERE id = ?");
                $stmt_upd->bind_param("ii", $nuevoEstado, $polizaId);
                
                if ($stmt_upd->execute()) {
                    $stmt_upd->close();
                    $justificacion = "Se cambió el estatus de exclusión del bot de cobranza para la póliza " . $polData['numero_poliza'] . " a estado " . ($nuevoEstado === 1 ? 'EXCLUIDO' : 'INCLUIDO');
                    registrarAjuste($usuario_id, 'polizas', 'bot_excluir', $polizaId, ['bot_excluir' => $polData['bot_excluir']], ['bot_excluir' => $nuevoEstado], $justificacion);
                    echo json_encode(["exito" => true, "bot_excluir" => $nuevoEstado, "mensaje" => "Estado del bot para la póliza actualizado correctamente"]);
                } else {
                    $stmt_upd->close();
                    echo json_encode(["exito" => false, "mensaje" => "Error al actualizar estado de exclusión: " . $db->error]);
                }
            } elseif ($action === 'ejecutar_bot_manual') {
                if (!tienePermiso($usuario_id, 'CF_GESTIONAR_NCF') && !tienePermiso($usuario_id, 'PAG_TOTAL')) {
                    http_response_code(403);
                    echo json_encode(["exito" => false, "mensaje" => "No posee permisos para ejecutar el bot de forma manual."]);
                    break;
                }

                $bot = new CobroBot();
                $conteo = $bot->ejecutarSecuenciaDiaria();
                echo json_encode(["exito" => true, "mensaje" => "Se ejecutó la secuencia de cobro del bot de forma manual exitosamente. Notificaciones enviadas: $conteo"]);
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
