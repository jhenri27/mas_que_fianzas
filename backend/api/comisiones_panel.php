<?php
/**
 * API Panel de Comisiones - MAS QUE FIANZAS v1.0
 * Endpoint dedicado al panel visual de comisiones del agente/supervisor.
 */

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once '../config.php';
require_once '../ComisionManager.php';

// ─── Validación de sesión (doble vía: PHP session + Bearer token) ───────────

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$bearer_token = null;
$auth_header  = $_SERVER['HTTP_AUTHORIZATION']
    ?? (function_exists('apache_request_headers') ? (apache_request_headers()['Authorization'] ?? '') : '');

if (preg_match('/Bearer\s+(.+)$/i', $auth_header, $matches)) {
    $bearer_token = trim($matches[1]);
}
if (empty($bearer_token)) {
    $bearer_token = $_GET['token_sesion']
        ?? $_POST['token_sesion']
        ?? $_REQUEST['token']
        ?? $_REQUEST['token_sesion']
        ?? null;
}

$usuario_id = null;
if (isset($_SESSION['usuario_id']) && $_SESSION['usuario_id']) {
    $usuario_id = (int)$_SESSION['usuario_id'];
} elseif (!empty($bearer_token)) {
    $db_temp  = Database::getInstance()->getConnection();
    $stmt_tk  = $db_temp->prepare(
        "SELECT usuario_id FROM sesiones_usuario
         WHERE token_sesion = ? AND activa = 1 AND fecha_expiracion > NOW()
         LIMIT 1"
    );
    if ($stmt_tk) {
        $stmt_tk->bind_param("s", $bearer_token);
        $stmt_tk->execute();
        $res_tk = $stmt_tk->get_result();
        if ($row_tk = $res_tk->fetch_assoc()) {
            $usuario_id = (int)$row_tk['usuario_id'];
        }
        $stmt_tk->close();
    }
}

if (!$usuario_id) {
    http_response_code(401);
    echo json_encode(["exito" => false, "mensaje" => "Sesión no válida o expirada"]);
    exit;
}

// ─── Verificar permiso base COM_PANEL_VER ────────────────────────────────────

$db = Database::getInstance()->getConnection();

if (!tienePermiso($usuario_id, 'COM_PANEL_VER')) {
    http_response_code(403);
    echo json_encode(["exito" => false, "mensaje" => "Sin permiso para acceder al Panel de Comisiones"]);
    exit;
}

// ¿Tiene acceso global?
$es_global = tienePermiso($usuario_id, 'COM_PANEL_GLOBAL');

// ─── Parámetros comunes ───────────────────────────────────────────────────────

$mes  = isset($_GET['mes'])  ? (int)$_GET['mes']  : (int)date('n');
$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : (int)date('Y');

// usuario_filtro solo si tiene permiso global
$uid_target = $usuario_id;
if ($es_global && !empty($_GET['usuario_filtro'])) {
    $uid_target = (int)$_GET['usuario_filtro'];
}

// ─── Instancia del manager ────────────────────────────────────────────────────

$mgr    = new ComisionManager();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// ─── Router ───────────────────────────────────────────────────────────────────

try {
    // ── GET ──────────────────────────────────────────────────────────────────
    if ($method === 'GET') {

        switch ($action) {

            // KPIs: cobrado, tránsito, proyección, total_polizas, mes, anio
            case 'panel_resumen':
                $datos = $mgr->obtenerPanelResumen($uid_target, $mes, $anio, $es_global);
                echo json_encode(["exito" => true, "datos" => $datos, "mensaje" => ""]);
                break;

            // Lista de pólizas del mes con prima_neta y monto_comision
            case 'polizas_comision':
                $datos = $mgr->obtenerPolizasConComision($uid_target, $mes, $anio, $es_global);
                echo json_encode(["exito" => true, "datos" => $datos, "mensaje" => ""]);
                break;

            // Pólizas con pagos pendientes y comisión en tránsito
            case 'cuentas_por_cobrar':
                $datos = $mgr->obtenerCuentasPorCobrar($uid_target, $mes, $anio, $es_global);
                echo json_encode(["exito" => true, "datos" => $datos, "mensaje" => ""]);
                break;

            // Proyección: cobrado, pendiente, proyeccion_total, porcentaje_cobrado
            case 'proyeccion_mensual':
                $datos = $mgr->obtenerProyeccionMensual($uid_target, $mes, $anio, $es_global);
                echo json_encode(["exito" => true, "datos" => $datos, "mensaje" => ""]);
                break;

            // Obtener lista de códigos de función autorizados para el usuario actual
            case 'mis_permisos':
                $db_temp = Database::getInstance()->getConnection();
                if ($usuario_id === 1) {
                    $res = $db_temp->query("SELECT codigo_funcion FROM funciones_modulo");
                } else {
                    $stmt_perms = $db_temp->prepare(
                        "SELECT fm.codigo_funcion 
                         FROM usuarios u
                         INNER JOIN permisos_perfil pp ON u.perfil_id = pp.perfil_id
                         INNER JOIN funciones_modulo fm ON pp.funcion_id = fm.id
                         WHERE u.id = ? AND pp.puede_ejecutar = 1"
                    );
                    $res = null;
                    if ($stmt_perms) {
                        $stmt_perms->bind_param("i", $usuario_id);
                        $stmt_perms->execute();
                        $res = $stmt_perms->get_result();
                        $stmt_perms->close();
                    }
                }
                $permisos = [];
                if ($res) {
                    while ($row = $res->fetch_assoc()) {
                        $permisos[] = $row['codigo_funcion'];
                    }
                }
                echo json_encode(["exito" => true, "permisos" => $permisos, "mensaje" => ""]);
                break;

            // Listar todos los agentes (usuarios) para el filtro global
            case 'listar_agentes':
                $db_temp = Database::getInstance()->getConnection();
                $res = $db_temp->query("SELECT id, nombre FROM usuarios ORDER BY nombre ASC");
                $agentes = [];
                if ($res) {
                    while ($row = $res->fetch_assoc()) {
                        $agentes[] = $row;
                    }
                }
                echo json_encode(["exito" => true, "agentes" => $agentes, "mensaje" => ""]);
                break;

            default:
                http_response_code(400);
                echo json_encode(["exito" => false, "mensaje" => "Acción GET no reconocida: '{$action}'"]);
        }

    // ── POST ─────────────────────────────────────────────────────────────────
    } elseif ($method === 'POST') {

        $body = json_decode(file_get_contents('php://input'), true);

        switch ($action) {

            // Enviar reporte por correo
            case 'enviar_reporte':
                $email       = trim($body['email']       ?? '');
                $html_reporte = $body['html_reporte']    ?? '';
                $periodo     = trim($body['periodo']     ?? "{$mes}/{$anio}");

                if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    http_response_code(422);
                    echo json_encode(["exito" => false, "mensaje" => "Correo electrónico inválido o no especificado"]);
                    break;
                }
                if (empty($html_reporte)) {
                    http_response_code(422);
                    echo json_encode(["exito" => false, "mensaje" => "El contenido del reporte (html_reporte) no puede estar vacío"]);
                    break;
                }

                require_once '../Mailer.php';
                $mailer  = new Mailer();
                $subject = "Reporte de Comisiones – Período {$periodo}";
                $enviado = $mailer->enviar($email, $subject, $html_reporte, true);

                if ($enviado) {
                    echo json_encode(["exito" => true, "datos" => null, "mensaje" => "Reporte enviado a {$email}"]);
                } else {
                    http_response_code(502);
                    echo json_encode(["exito" => false, "mensaje" => "No se pudo enviar el correo. Revisa logs/smtp.log"]);
                }
                break;

            default:
                http_response_code(400);
                echo json_encode(["exito" => false, "mensaje" => "Acción POST no reconocida: '{$action}'"]);
        }

    } else {
        http_response_code(405);
        echo json_encode(["exito" => false, "mensaje" => "Método HTTP no permitido"]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["exito" => false, "mensaje" => "Error interno: " . $e->getMessage()]);
}
