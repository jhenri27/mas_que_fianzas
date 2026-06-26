<?php
/**
 * API: Helpdesk Inteligente y Soporte con Auto-Diagnóstico (NOFTRAB)
 * MAS QUE FIANZAS - Core Asegurador v3.0
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/Mailer.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validar token de autorización si no hay sesión PHP activa
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

try {
    $db = Database::getInstance()->getConnection();

    // Obtener información del usuario actual
    $stmt_u = $db->prepare("SELECT perfil_id, referente_id, nombre, apellido, email, username FROM usuarios WHERE id = ? LIMIT 1");
    $stmt_u->bind_param("i", $usuario_id);
    $stmt_u->execute();
    $usr_data = $stmt_u->get_result()->fetch_assoc();
    $stmt_u->close();

    if (!$usr_data) {
        http_response_code(404);
        echo json_encode(["exito" => false, "mensaje" => "Usuario no encontrado"]);
        exit;
    }

    $es_admin_helpdesk = (
        $usuario_id === 1 || 
        (int)$usr_data['perfil_id'] === 1 || 
        tienePermiso($usuario_id, 'HELPDESK_ADMINISTRAR') || 
        tienePermiso($usuario_id, 'CONF_TOTAL')
    );

    $metodo = $_SERVER['REQUEST_METHOD'];

    if ($metodo === 'GET') {
        // A. Consultar un ticket específico
        if (isset($_GET['ticket_id'])) {
            $ticket_id = (int)$_GET['ticket_id'];

            // Cargar cabecera del ticket
            $sql_t = "
                SELECT t.*, u.username as creador_username, u.nombre as creador_nombre, u.apellido as creador_apellido
                FROM tickets_soporte t
                LEFT JOIN usuarios u ON t.usuario_id = u.id
                WHERE t.id = ?
            ";
            $stmt_t = $db->prepare($sql_t);
            $stmt_t->bind_param("i", $ticket_id);
            $stmt_t->execute();
            $ticket = $stmt_t->get_result()->fetch_assoc();
            $stmt_t->close();

            if (!$ticket) {
                http_response_code(404);
                echo json_encode(["exito" => false, "mensaje" => "Ticket no encontrado"]);
                exit;
            }

            // Validar acceso: el creador o un admin
            if (!$es_admin_helpdesk && (int)$ticket['usuario_id'] !== $usuario_id) {
                http_response_code(403);
                echo json_encode(["exito" => false, "mensaje" => "No tiene permisos para ver este ticket"]);
                exit;
            }

            // Cargar mensajes del ticket
            $sql_m = "
                SELECT m.*, u.username, u.nombre, u.apellido, u.perfil_id
                FROM mensajes_ticket m
                LEFT JOIN usuarios u ON m.usuario_id = u.id
                WHERE m.ticket_id = ?
                ORDER BY m.fecha_envio ASC
            ";
            $stmt_m = $db->prepare($sql_m);
            $stmt_m->bind_param("i", $ticket_id);
            $stmt_m->execute();
            $res_m = $stmt_m->get_result();
            $mensajes = [];
            while ($row = $res_m->fetch_assoc()) {
                $mensajes[] = [
                    "id" => (int)$row['id'],
                    "usuario_id" => $row['usuario_id'] ? (int)$row['usuario_id'] : null,
                    "nombre_usuario" => $row['usuario_id'] ? ($row['nombre'] . ' ' . $row['apellido']) : 'BHN-Bot-HelpNow',
                    "mensaje" => $row['mensaje'],
                    "fecha_envio" => $row['fecha_envio'],
                    "origen" => $row['origen'],
                    "yo" => ($row['usuario_id'] ? ((int)$row['usuario_id'] === $usuario_id) : false)
                ];
            }
            $stmt_m->close();

            echo json_encode([
                "exito" => true,
                "ticket" => $ticket,
                "mensajes" => $mensajes
            ]);
            exit;
        }

        // B. Listar todos los tickets (Admin ve todos, Socio/otros ven propios)
        if ($es_admin_helpdesk) {
            $sql_list = "
                SELECT t.*, u.username as creador_username, u.nombre as creador_nombre, u.apellido as creador_apellido
                FROM tickets_soporte t
                LEFT JOIN usuarios u ON t.usuario_id = u.id
                ORDER BY t.fecha_creacion DESC
            ";
            $stmt_list = $db->prepare($sql_list);
        } else {
            $sql_list = "
                SELECT t.*, u.username as creador_username, u.nombre as creador_nombre, u.apellido as creador_apellido
                FROM tickets_soporte t
                LEFT JOIN usuarios u ON t.usuario_id = u.id
                WHERE t.usuario_id = ?
                ORDER BY t.fecha_creacion DESC
            ";
            $stmt_list = $db->prepare($sql_list);
            $stmt_list->bind_param("i", $usuario_id);
        }

        $stmt_list->execute();
        $res_list = $stmt_list->get_result();
        $tickets = [];
        while ($row = $res_list->fetch_assoc()) {
            $tickets[] = $row;
        }
        $stmt_list->close();

        echo json_encode([
            "exito" => true,
            "tickets" => $tickets
        ]);
        exit;
    }

    if ($metodo === 'POST') {
        $raw_data = file_get_contents('php://input');
        $data = json_decode($raw_data, true);

        // Caso A: Responder a un ticket existente
        if (isset($_GET['action']) && $_GET['action'] === 'responder') {
            if (!$data || empty($data['ticket_id']) || empty($data['mensaje'])) {
                http_response_code(400);
                echo json_encode(["exito" => false, "mensaje" => "Datos incompletos para responder"]);
                exit;
            }

            $ticket_id = (int)$data['ticket_id'];
            $mensaje = trim($data['mensaje']);

            // Verificar existencia y acceso al ticket
            $stmt_check = $db->prepare("SELECT usuario_id, estado, titulo FROM tickets_soporte WHERE id = ? LIMIT 1");
            $stmt_check->bind_param("i", $ticket_id);
            $stmt_check->execute();
            $tk_check = $stmt_check->get_result()->fetch_assoc();
            $stmt_check->close();

            if (!$tk_check) {
                http_response_code(404);
                echo json_encode(["exito" => false, "mensaje" => "Ticket no encontrado"]);
                exit;
            }

            if (!$es_admin_helpdesk && (int)$tk_check['usuario_id'] !== $usuario_id) {
                http_response_code(403);
                echo json_encode(["exito" => false, "mensaje" => "No tiene permisos para comentar en este ticket"]);
                exit;
            }

            // Insertar mensaje
            $origen = $es_admin_helpdesk ? 'agente' : 'usuario';
            $stmt_msg = $db->prepare("INSERT INTO mensajes_ticket (ticket_id, usuario_id, mensaje, origen, fecha_envio) VALUES (?, ?, ?, ?, NOW())");
            $stmt_msg->bind_param("iiss", $ticket_id, $usuario_id, $mensaje, $origen);
            $stmt_msg->execute();
            $stmt_msg->close();

            // Si se pasa un cambio de estado
            if (isset($data['nuevo_estado']) && in_array($data['nuevo_estado'], ['abierto', 'en_proceso', 'resuelto'])) {
                $nuevo_estado = $data['nuevo_estado'];
                $sql_up = "UPDATE tickets_soporte SET estado = ?";
                if ($nuevo_estado === 'resuelto') {
                    $sql_up .= ", fecha_resolucion = NOW()";
                } else {
                    $sql_up .= ", fecha_resolucion = NULL";
                }
                $sql_up .= " WHERE id = ?";
                $stmt_up = $db->prepare($sql_up);
                $stmt_up->bind_param("si", $nuevo_estado, $ticket_id);
                $stmt_up->execute();
                $stmt_up->close();
            }

            echo json_encode(["exito" => true, "mensaje" => "Respuesta guardada con éxito"]);
            exit;
        }

        // Caso B: Crear nuevo ticket
        if (!$data || empty($data['modulo_afectado']) || empty($data['titulo']) || empty($data['descripcion'])) {
            http_response_code(400);
            echo json_encode(["exito" => false, "mensaje" => "Campos obligatorios incompletos (modulo_afectado, titulo, descripcion)"]);
            exit;
        }

        $modulo = trim($data['modulo_afectado']);
        $titulo = trim($data['titulo']);
        $descripcion = trim($data['descripcion']);
        $prioridad = isset($data['prioridad']) && in_array($data['prioridad'], ['baja', 'media', 'alta']) ? $data['prioridad'] : 'media';

        // Calcular SLA limite
        $horas_sla = 8;
        if ($prioridad === 'alta') $horas_sla = 2;
        if ($prioridad === 'baja') $horas_sla = 24;
        $sla_limite = date('Y-m-d H:i:s', strtotime("+{$horas_sla} hours"));

        // Insertar ticket
        $stmt_ins = $db->prepare("INSERT INTO tickets_soporte (usuario_id, modulo_afectado, titulo, descripcion, prioridad, estado, sla_limite, fecha_creacion) VALUES (?, ?, ?, ?, ?, 'abierto', ?, NOW())");
        $stmt_ins->bind_param("isssss", $usuario_id, $modulo, $titulo, $descripcion, $prioridad, $sla_limite);
        
        if (!$stmt_ins->execute()) {
            throw new Exception("Error al insertar ticket: " . $db->error);
        }
        $ticket_id = $stmt_ins->insert_id;
        $stmt_ins->close();

        // 🤖 PROCESO IA AUTO-RESOLVER / AUTO-DIAGNÓSTICO basado en error.log
        $diagnostico_ia = "";
        $error_encontrado = false;
        $log_path = dirname(__DIR__) . '/logs/error.log';

        if (file_exists($log_path)) {
            $lines = file($log_path);
            $last_lines = array_slice($lines, -15);
            $matching_errs = [];

            foreach ($last_lines as $line) {
                // Si la línea contiene palabras clave de error y coincide con el nombre del módulo afectado
                if ((strpos(strtolower($line), 'error') !== false || strpos(strtolower($line), 'exception') !== false || strpos(strtolower($line), 'warning') !== false) 
                    && strpos(strtolower($line), strtolower($modulo)) !== false) {
                    $matching_errs[] = trim($line);
                }
            }

            if (!empty($matching_errs)) {
                $error_encontrado = true;
                $traza = implode("\n", $matching_errs);
                $diagnostico_ia = "🤖 **BHN-Bot-HelpNow (Auto-Diagnóstico)**:\n" .
                    "He escaneado los registros del servidor (`logs/error.log`) y encontré la siguiente traza de error asociada al módulo **{$modulo}**:\n" .
                    "```\n{$traza}\n```\n" .
                    "Por esta razón, el ticket ha sido marcado **en_proceso** y escalado automáticamente a **Prioridad Alta**. Nuestro equipo de TI ha sido notificado.";
            }
        }

        if ($error_encontrado) {
            // Actualizar ticket
            $stmt_upd = $db->prepare("UPDATE tickets_soporte SET estado = 'en_proceso', prioridad = 'alta', sla_limite = DATE_ADD(NOW(), INTERVAL 2 HOUR) WHERE id = ?");
            $stmt_upd->bind_param("i", $ticket_id);
            $stmt_upd->execute();
            $stmt_upd->close();

            // Insertar respuesta del bot
            $stmt_bot = $db->prepare("INSERT INTO mensajes_ticket (ticket_id, usuario_id, mensaje, origen, fecha_envio) VALUES (?, NULL, ?, 'bot', NOW())");
            $stmt_bot->bind_param("is", $ticket_id, $diagnostico_ia);
            $stmt_bot->execute();
            $stmt_bot->close();
        } else {
            // Mensaje por defecto del robot
            $msg_bot_def = "🤖 **BHN-Bot-HelpNow**:\n" .
                "Hola **{$usr_data['nombre']}**, he registrado tu ticket exitosamente bajo el ID **#{$ticket_id}**.\n" .
                "Un supervisor responderá en un plazo máximo de **{$horas_sla} horas** (Límite SLA: {$sla_limite}).";
            $stmt_bot = $db->prepare("INSERT INTO mensajes_ticket (ticket_id, usuario_id, mensaje, origen, fecha_envio) VALUES (?, NULL, ?, 'bot', NOW())");
            $stmt_bot->bind_param("is", $ticket_id, $msg_bot_def);
            $stmt_bot->execute();
            $stmt_bot->close();
        }

        // ── NOTIFICACIÓN POR CORREO AL SUPERVISOR (referente_id) o ADMIN
        $supervisor_id = $usr_data['referente_id'] ? (int)$usr_data['referente_id'] : 1;
        
        $stmt_s = $db->prepare("SELECT email, nombre, apellido FROM usuarios WHERE id = ? LIMIT 1");
        $stmt_s->bind_param("i", $supervisor_id);
        $stmt_s->execute();
        $sup_data = $stmt_s->get_result()->fetch_assoc();
        $stmt_s->close();

        if ($sup_data && !empty($sup_data['email'])) {
            try {
                $mailer = new Mailer();
                $asunto = "⚠️ Nuevo Ticket de Soporte #{$ticket_id} - [{$modulo}]";
                $diagnostico_txt = $error_encontrado ? "<p style='color:#ef4444;background:#fef2f2;padding:10px;border-radius:4px;'><strong>Auto-Diagnóstico IA:</strong> Traza de error detectada en logs y escalada.</p>" : "";
                
                $cuerpo = "
                    <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;border:1px solid #cbd5e1;border-radius:8px;overflow:hidden;'>
                        <div style='background:#f59e0b;padding:20px;text-align:center;'>
                            <h2 style='color:#fff;margin:0;'>MÁS QUE FIANZAS - Helpdesk</h2>
                        </div>
                        <div style='padding:24px;color:#334155;'>
                            <h3>Nuevo reporte de incidencia</h3>
                            <p>El usuario <strong>{$usr_data['nombre']} {$usr_data['apellido']}</strong> ({$usr_data['username']}) ha creado un ticket de soporte:</p>
                            <hr style='border:none;border-top:1px solid #e2e8f0;margin:15px 0;'>
                            <table style='width:100%;font-size:14px;border-collapse:collapse;'>
                                <tr><td style='padding:4px 0;'><strong>Ticket ID:</strong></td><td>#{$ticket_id}</td></tr>
                                <tr><td style='padding:4px 0;'><strong>Módulo:</strong></td><td>{$modulo}</td></tr>
                                <tr><td style='padding:4px 0;'><strong>Título:</strong></td><td>{$titulo}</td></tr>
                                <tr><td style='padding:4px 0;'><strong>Prioridad:</strong></td><td><span style='text-transform:uppercase;font-weight:bold;'>{$prioridad}</span></td></tr>
                                <tr><td style='padding:4px 0;'><strong>Límite SLA:</strong></td><td>{$sla_limite}</td></tr>
                            </table>
                            <div style='background:#f8fafc;padding:12px;border-radius:6px;border:1px solid #e2e8f0;margin-top:15px;'>
                                <strong>Descripción del problema:</strong><br>
                                " . nl2br(htmlspecialchars($descripcion)) . "
                            </div>
                            {$diagnostico_txt}
                            <p style='margin-top:20px;text-align:center;'>
                                <a href='http://localhost/PLATAFORMA_INTEGRADA/frontend/#helpdesk' style='display:inline-block;padding:10px 20px;background:#1e293b;color:#fff;border-radius:6px;text-decoration:none;font-weight:bold;'>Ver Ticket en el Sistema</a>
                            </p>
                        </div>
                    </div>
                ";
                $mailer->enviar($sup_data['email'], $asunto, $cuerpo);
            } catch (Exception $e) {
                error_log("Error al enviar email de helpdesk al supervisor: " . $e->getMessage());
            }
        }

        echo json_encode([
            "exito" => true,
            "mensaje" => "Ticket creado exitosamente",
            "ticket_id" => $ticket_id,
            "ia_diagnostico_ejecutado" => $error_encontrado
        ]);
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["exito" => false, "mensaje" => "Error interno: " . $e->getMessage()]);
}
