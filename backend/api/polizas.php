<?php
/**
 * API de Gestión de Pólizas - Core Asegurador v3.0
 * MAS QUE FIANZAS
 */

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config.php';

// Validar sesión: aceptar PHP session O Bearer token
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? (function_exists('apache_request_headers') ? (apache_request_headers()['Authorization'] ?? apache_request_headers()['authorization'] ?? '') : '');
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
require_once '../PolizaManager.php';

$polizaManager = new PolizaManager();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            $soloPropios = restringirSoloPropios($usuario_actual, 'Pólizas');
            if ($action === 'obtener') {
                $id = $_GET['id'] ?? null;
                if (!$id) {
                    echo json_encode(["exito" => false, "mensaje" => "ID de póliza requerido"]);
                    break;
                }
                $poliza = $polizaManager->obtenerPolizaDetalle($id);
                if ($poliza && $soloPropios && (int)$poliza['emitida_por'] !== $usuario_actual) {
                    http_response_code(403);
                    echo json_encode(["exito" => false, "mensaje" => "Acceso denegado: este registro no le pertenece"]);
                    break;
                }
                echo json_encode(["exito" => true, "data" => $poliza]);
            } else {
                // Listado por defecto con filtros
                $filtros = [];
                if (isset($_GET['search'])) $filtros['search'] = $_GET['search'];
                if (isset($_GET['start_date'])) $filtros['start_date'] = $_GET['start_date'];
                if (isset($_GET['end_date'])) $filtros['end_date'] = $_GET['end_date'];
                if (isset($_GET['estado'])) $filtros['estado'] = $_GET['estado'];
                
                if ($soloPropios) {
                    $filtros['emitida_por'] = $usuario_actual;
                }
                
                $polizas = $polizaManager->obtenerPolizas($filtros);
                echo json_encode(["exito" => true, "data" => $polizas]);
            }
            break;

        case 'POST':
            // Detectar desbordamiento silencioso de post_max_size:
            // cuando el body excede el límite, PHP vacía php://input y $_POST queda vacío
            $rawBody = file_get_contents('php://input');
            if (empty($rawBody) && isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > 0) {
                $postMaxSize = ini_get('post_max_size');
                http_response_code(413);
                echo json_encode([
                    "exito"   => false,
                    "mensaje" => "El archivo PDF es demasiado grande para ser enviado (límite actual: {$postMaxSize}). Contacte al administrador.",
                    "codigo"  => "POST_MAX_SIZE_EXCEEDED",
                    "limit"   => $postMaxSize,
                    "sent"    => (int)$_SERVER['CONTENT_LENGTH']
                ]);
                exit;
            }
            $datos = json_decode($rawBody, true);
            
            if ($action === 'emitir') {
                if (!$datos || empty($datos['cliente_id'])) {
                    http_response_code(400);
                    echo json_encode(["exito" => false, "mensaje" => "Datos de emisión incompletos (cliente_id requerido)"]);
                    break;
                }

                // Validación técnica Dominicana (NOFTRAB)
                if (ValidadorDocumentos::isValidatorActive('polizas')) {
                    if (!empty($datos['cedula']) && !ValidadorDocumentos::validarDocumento($datos['cedula'])) {
                        http_response_code(400);
                        echo json_encode(["exito" => false, "mensaje" => "La cédula o RNC especificado no es válido (dígito verificador incorrecto)."]);
                        break;
                    }
                    if (!empty($datos['telefono']) && !ValidadorDocumentos::validarTelefono($datos['telefono'])) {
                        http_response_code(400);
                        echo json_encode(["exito" => false, "mensaje" => "El teléfono especificado no es válido (debe tener 10 dígitos y código de área 809, 829 o 849)."]);
                        break;
                    }
                }

                // Inyectar el usuario activo como emisor
                $datos['emitida_por'] = $usuario_actual;

                $resultado = $polizaManager->emitirPoliza($datos);
                if ($resultado['exito']) {
                    echo json_encode($resultado);
                } else {
                    http_response_code(500);
                    echo json_encode($resultado);
                }
            } 
            elseif ($action === 'validar') {
                $id = $_GET['id'] ?? $datos['id'] ?? null;
                $userId = $_GET['user_id'] ?? $datos['user_id'] ?? $usuario_actual;
                if (!$id) {
                    echo json_encode(["exito" => false, "mensaje" => "ID de póliza requerido"]);
                    break;
                }
                $ok = $polizaManager->validarPoliza($id, $userId);
                echo json_encode(["exito" => $ok, "mensaje" => $ok ? "Póliza validada exitosamente" : "No se pudo validar la póliza"]);
            }
            elseif ($action === 'cambiar_estado') {
                $id = $datos['id'] ?? null;
                $estado = $datos['estado'] ?? null;
                if (!$id || !$estado) {
                    echo json_encode(["exito" => false, "mensaje" => "ID y estado requeridos"]);
                    break;
                }
                $ok = $polizaManager->cambiarEstado($id, $estado);
                echo json_encode(["exito" => $ok, "mensaje" => $ok ? "Estado actualizado" : "Error al actualizar estado"]);
            }
            elseif ($action === 'preview_cancelar') {
                if (!tienePermiso($usuario_actual, 'POLIZAS_CANCELAR_INDIVIDUAL')) {
                    echo json_encode(["exito" => false, "mensaje" => "No tiene permisos para calcular prorrata de cancelación"]);
                    break;
                }
                $id = $datos['id'] ?? $_GET['id'] ?? null;
                $fecha = $datos['fecha_cancelacion'] ?? $_GET['fecha_cancelacion'] ?? null;
                if (!$id) {
                    echo json_encode(["exito" => false, "mensaje" => "ID de póliza requerido"]);
                    break;
                }
                try {
                    $res = $polizaManager->calcularProrrataCancelacion($id, $fecha);
                    echo json_encode(["exito" => true, "data" => $res]);
                } catch (Exception $e) {
                    echo json_encode(["exito" => false, "mensaje" => $e->getMessage()]);
                }
            }
            elseif ($action === 'cancelar_individual') {
                if (!tienePermiso($usuario_actual, 'POLIZAS_CANCELAR_INDIVIDUAL')) {
                    echo json_encode(["exito" => false, "mensaje" => "No tiene permisos para cancelar pólizas de forma individual"]);
                    break;
                }
                $id = $datos['id'] ?? null;
                $justificacion = $datos['justificacion'] ?? '';
                $fecha = $datos['fecha_cancelacion'] ?? null;
                if (!$id || empty($justificacion)) {
                    echo json_encode(["exito" => false, "mensaje" => "ID de póliza y justificación requeridos"]);
                    break;
                }
                $res = $polizaManager->cancelarPoliza($id, $justificacion, 'individual', $usuario_actual, $fecha, true);
                echo json_encode($res);
            }
            elseif ($action === 'cancelar_seleccion') {
                if (!tienePermiso($usuario_actual, 'POLIZAS_CANCELAR_GRUPO')) {
                    echo json_encode(["exito" => false, "mensaje" => "No tiene permisos para realizar cancelación grupal por selección"]);
                    break;
                }
                $ids = $datos['ids'] ?? [];
                $justificacion = $datos['justificacion'] ?? '';
                if (empty($ids) || empty($justificacion)) {
                    echo json_encode(["exito" => false, "mensaje" => "Lista de pólizas y justificación requeridas"]);
                    break;
                }
                $exitosos = 0; $fallidos = 0; $errores = [];
                foreach ($ids as $id) {
                    $res = $polizaManager->cancelarPoliza($id, $justificacion, 'seleccion', $usuario_actual, null, true);
                    if ($res['exito']) {
                        $exitosos++;
                    } else {
                        $fallidos++;
                        $errores[] = "ID {$id}: " . $res['mensaje'];
                    }
                }
                echo json_encode([
                    "exito" => $exitosos > 0,
                    "mensaje" => "Proceso completado. Canceladas con éxito: {$exitosos}. Fallidas: {$fallidos}.",
                    "detalles" => [
                        "exitosas" => $exitosos,
                        "fallidas" => $fallidos,
                        "errores" => $errores
                    ]
                ]);
            }
            elseif ($action === 'cancelar_masiva') {
                if (!tienePermiso($usuario_actual, 'POLIZAS_CANCELAR_MASIVO')) {
                    echo json_encode(["exito" => false, "mensaje" => "No tiene permisos para realizar cancelación masiva de pólizas"]);
                    break;
                }
                $filtros = $datos['filtros'] ?? [];
                $justificacion = $datos['justificacion'] ?? '';
                if (empty($justificacion)) {
                    echo json_encode(["exito" => false, "mensaje" => "La justificación de la cancelación masiva es obligatoria"]);
                    break;
                }

                // Construir consulta para buscar las pólizas activas que cumplen los filtros
                $db = Database::getInstance()->getConnection();
                $sql = "SELECT p.id FROM polizas p WHERE p.estado = 'activa'";
                $params = [];
                $types = '';

                if (!empty($filtros['agente'])) {
                    $sql .= " AND p.emitida_por = ?";
                    $params[] = (int)$filtros['agente'];
                    $types .= 'i';
                }
                if (!empty($filtros['ramo'])) {
                    $sql .= " AND p.ramo = ?";
                    $params[] = $filtros['ramo'];
                    $types .= 's';
                }
                if (!empty($filtros['aseguradora'])) {
                    $sql .= " AND p.aseguradora = ?";
                    $params[] = $filtros['aseguradora'];
                    $types .= 's';
                }

                $stmt = $db->prepare($sql);
                if ($types) {
                    $stmt->bind_param($types, ...$params);
                }
                $stmt->execute();
                $resQuery = $stmt->get_result();
                
                $ids = [];
                while ($r = $resQuery->fetch_assoc()) {
                    $ids[] = (int)$r['id'];
                }
                $stmt->close();

                if (empty($ids)) {
                    echo json_encode(["exito" => false, "mensaje" => "No se encontraron pólizas activas con los filtros especificados."]);
                    break;
                }

                $exitosos = 0; $fallidos = 0;
                foreach ($ids as $id) {
                    $res = $polizaManager->cancelarPoliza($id, $justificacion, 'masiva', $usuario_actual, null, true);
                    if ($res['exito']) $exitosos++;
                    else $fallidos++;
                }

                echo json_encode([
                    "exito" => $exitosos > 0,
                    "mensaje" => "Cancelación masiva completada. Procesadas con éxito: {$exitosos}. Fallidas: {$fallidos}.",
                    "total_afectadas" => count($ids)
                ]);
            }
            elseif ($action === 'enviar_correo') {
                $email = trim($datos['email'] ?? '');
                $pdf_base64 = $datos['pdf_base64'] ?? '';
                $num_poliza = trim($datos['numero_poliza'] ?? '');
                
                if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    http_response_code(422);
                    echo json_encode(["exito" => false, "mensaje" => "Correo electrónico inválido"]);
                    break;
                }
                if (empty($pdf_base64)) {
                    http_response_code(422);
                    echo json_encode(["exito" => false, "mensaje" => "Documento PDF requerido"]);
                    break;
                }
                if (empty($num_poliza)) {
                    http_response_code(422);
                    echo json_encode(["exito" => false, "mensaje" => "Número de póliza requerido"]);
                    break;
                }
                
                require_once '../Mailer.php';
                $mailer = new Mailer();
                
                $subject = "Póliza Digital Emisión Oficial – N° {$num_poliza}";
                
                // Formatear un cuerpo de correo corporativo premium en HTML
                $message = "
                <html>
                <head>
                    <style>
                        body { font-family: 'Segoe UI', Arial, sans-serif; color: #334155; line-height: 1.6; background-color: #f8fafc; margin: 0; padding: 20px; }
                        .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
                        .header { background: linear-gradient(135deg, #4f46e5, #7c3aed); padding: 30px; text-align: center; color: #ffffff; }
                        .header h1 { margin: 0; font-size: 20px; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; }
                        .body { padding: 30px; }
                        .policy-card { background: #f1f5f9; border-radius: 8px; padding: 20px; margin: 20px 0; border-left: 4px solid #4f46e5; }
                        .policy-item { display: flex; justify-content: space-between; border-bottom: 1px solid #e2e8f0; padding: 8px 0; font-size: 13px; }
                        .policy-item:last-child { border-bottom: none; }
                        .label { font-weight: 700; color: #64748b; }
                        .value { font-weight: 600; color: #1e293b; }
                        .btn { display: inline-block; background: #4f46e5; color: #ffffff !important; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: 700; font-size: 14px; text-align: center; margin: 20px 0; }
                        .footer { background: #f8fafc; padding: 20px; text-align: center; font-size: 11px; color: #94a3b8; border-top: 1px solid #e2e8f0; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h1>MAS QUE FIANZAS</h1>
                        </div>
                        <div class='body'>
                            <p>Estimado Cliente,</p>
                            <p>Nos complace informarle que su póliza de seguro ha sido emitida con éxito a través de nuestra plataforma autorizada. Adjunto a este correo electrónico encontrará su **Marbete Digital Oficial** en formato PDF listo para imprimir o portar en su dispositivo móvil.</p>
                            
                            <div class='policy-card'>
                                <div class='policy-item'><span class='label'>Número de Póliza:</span><span class='value'>{$num_poliza}</span></div>
                                <div class='policy-item'><span class='label'>Aseguradora:</span><span class='value'>MULTISEGUROS</span></div>
                                <div class='policy-item'><span class='label'>Tipo de Seguro:</span><span class='value'>Seguro de Ley</span></div>
                            </div>
                            
                            <p>También puede verificar el estado técnico de su póliza en tiempo real o descargar sus certificados en línea haciendo clic en el siguiente botón:</p>
                            
                            <div style='text-align: center;'>
                                <a href='http://localhost/PLATAFORMA_INTEGRADA/frontend/verificar-poliza.html?n={$num_poliza}' class='btn'>Verificar Póliza en Línea</a>
                            </div>
                            
                            <p>Si tiene alguna pregunta o necesita asistencia adicional, no dude en contactarnos.</p>
                        </div>
                        <div class='footer'>
                            <p>Este correo electrónico fue generado automáticamente por la plataforma MAS QUE FIANZAS.</p>
                            <p>&copy; " . date('Y') . " MAS QUE FIANZAS, S.R.L. | Todos los derechos reservados.</p>
                        </div>
                    </div>
                </body>
                </html>";
                
                $filename = "Marbete_{$num_poliza}.pdf";
                $enviado = $mailer->enviarConAdjunto($email, $subject, $message, $pdf_base64, $filename, true);
                
                if ($enviado) {
                    echo json_encode(["exito" => true, "mensaje" => "Póliza enviada exitosamente a {$email}"]);
                } else {
                    http_response_code(502);
                    echo json_encode(["exito" => false, "mensaje" => "Error al enviar el correo. Revise el smtp.log"]);
                }
            }
            else {
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
