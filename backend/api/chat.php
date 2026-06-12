<?php
/**
 * API: Chat de Comunicación Interna (Chat-CSR)
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
    $stmt_u = $db->prepare("SELECT perfil_id, referente_id, nombre, apellido FROM usuarios WHERE id = ? LIMIT 1");
    $stmt_u->bind_param("i", $usuario_id);
    $stmt_u->execute();
    $usr_data = $stmt_u->get_result()->fetch_assoc();
    $stmt_u->close();

    if (!$usr_data) {
        http_response_code(404);
        echo json_encode(["exito" => false, "mensaje" => "Usuario no encontrado"]);
        exit;
    }

    $metodo = $_SERVER['REQUEST_METHOD'];

    if ($metodo === 'GET') {
        // Opción C: Descarga segura de archivos
        if (isset($_GET['action']) && $_GET['action'] === 'descargar_archivo') {
            $msg_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($msg_id <= 0) {
                http_response_code(400);
                echo json_encode(["exito" => false, "mensaje" => "ID de mensaje requerido"]);
                exit;
            }

            // Obtener el mensaje y verificar permisos
            $stmt_file = $db->prepare("SELECT emisor_id, receptor_id, archivo_nombre, archivo_ruta, archivo_tipo, archivo_size FROM mensajes_chat WHERE id = ? LIMIT 1");
            $stmt_file->bind_param("i", $msg_id);
            $stmt_file->execute();
            $file_data = $stmt_file->get_result()->fetch_assoc();
            $stmt_file->close();

            if (!$file_data || empty($file_data['archivo_ruta'])) {
                http_response_code(404);
                echo json_encode(["exito" => false, "mensaje" => "El archivo solicitado no existe"]);
                exit;
            }

            // Validar si el usuario tiene permiso (emisor, receptor, admin/supervisor)
            $puede_ver = (
                $usuario_id === 1 || 
                $usuario_id === (int)$file_data['emisor_id'] || 
                $usuario_id === (int)$file_data['receptor_id'] ||
                (int)$usr_data['perfil_id'] === 1 || 
                (function_exists('tienePermiso') && (tienePermiso($usuario_id, 'CONF_TOTAL') || tienePermiso($usuario_id, 'CHAT_CSR_SUPERVISAR')))
            );

            if (!$puede_ver) {
                http_response_code(403);
                echo json_encode(["exito" => false, "mensaje" => "Acceso denegado: no tiene permisos para descargar este archivo"]);
                exit;
            }

            $ruta_completa = dirname(__DIR__) . '/' . $file_data['archivo_ruta'];
            if (!file_exists($ruta_completa)) {
                http_response_code(404);
                echo json_encode(["exito" => false, "mensaje" => "El archivo físico no se encuentra en el servidor"]);
                exit;
            }

            // Registrar descarga en auditoría si existe la función
            if (function_exists('logAudit')) {
                logAudit($usuario_id, 'descargar_archivo_chat', 'mensajes_chat', 'consultar', 
                    "Archivo descargado: " . $file_data['archivo_nombre'] . " (Msg ID: $msg_id)", 'exitoso', null, 'mensajes_chat', $msg_id);
            }

            // Servir el archivo con cabeceras binarias seguras
            header('Content-Description: File Transfer');
            header('Content-Type: ' . $file_data['archivo_tipo']);
            header('Content-Disposition: attachment; filename="' . basename($file_data['archivo_nombre']) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . $file_data['archivo_size']);
            readfile($ruta_completa);
            exit;
        }

        // Opción A: Listar conversaciones activas o usuarios disponibles para chatear
        if (!isset($_GET['chat_con_id'])) {
            // El supervisor por defecto
            $supervisor_id = $usr_data['referente_id'] ? (int)$usr_data['referente_id'] : 1;
            if ($supervisor_id === $usuario_id) {
                // Si el supervisor es el mismo usuario, apuntamos a 1 (Admin) a menos que él mismo sea el 1
                $supervisor_id = ($usuario_id == 1) ? null : 1;
            }

            // Obtener lista de usuarios con los que ha interactuado
            $sql_interacciones = "
                SELECT DISTINCT u.id, u.username, u.nombre, u.apellido, p.nombre_perfil,
                       (SELECT MAX(fecha_envio) FROM mensajes_chat 
                        WHERE (emisor_id = u.id AND receptor_id = ?) 
                           OR (emisor_id = ? AND receptor_id = u.id)) as ultima_fecha,
                       (SELECT COUNT(*) FROM mensajes_chat 
                        WHERE emisor_id = u.id AND receptor_id = ? AND leido = 0) as no_leidos
                FROM usuarios u
                JOIN perfiles p ON u.perfil_id = p.id
                WHERE u.id IN (
                    SELECT emisor_id FROM mensajes_chat WHERE receptor_id = ?
                    UNION
                    SELECT receptor_id FROM mensajes_chat WHERE emisor_id = ?
                )
                ORDER BY ultima_fecha DESC
            ";
            $stmt_int = $db->prepare($sql_interacciones);
            $stmt_int->bind_param("iiiii", $usuario_id, $usuario_id, $usuario_id, $usuario_id, $usuario_id);
            $stmt_int->execute();
            $res_int = $stmt_int->get_result();
            $conversaciones = [];
            $interacted_ids = [];
            while ($row = $res_int->fetch_assoc()) {
                $conversaciones[] = $row;
                $interacted_ids[] = (int)$row['id'];
            }
            $stmt_int->close();

            // Si el supervisor no está en la lista de interacciones, agregarlo como opción principal
            if ($supervisor_id && !in_array($supervisor_id, $interacted_ids)) {
                $stmt_sup = $db->prepare("SELECT u.id, u.username, u.nombre, u.apellido, p.nombre_perfil FROM usuarios u JOIN perfiles p ON u.perfil_id = p.id WHERE u.id = ? LIMIT 1");
                $stmt_sup->bind_param("i", $supervisor_id);
                $stmt_sup->execute();
                $sup_info = $stmt_sup->get_result()->fetch_assoc();
                $stmt_sup->close();
                if ($sup_info) {
                    $sup_info['ultima_fecha'] = null;
                    $sup_info['no_leidos'] = 0;
                    $sup_info['es_supervisor'] = true;
                    // Colocar de primero
                    array_unshift($conversaciones, $sup_info);
                }
            }

            echo json_encode([
                "exito" => true,
                "conversaciones" => $conversaciones,
                "usuario_actual" => [
                    "id" => $usuario_id,
                    "nombre" => $usr_data['nombre'] . ' ' . $usr_data['apellido'],
                    "perfil_id" => (int)$usr_data['perfil_id']
                ]
            ]);
            exit;
        }

        // Opción B: Obtener mensajes con un usuario específico
        $chat_con_id = (int)$_GET['chat_con_id'];

        // Marcar mensajes recibidos como leídos
        $stmt_read = $db->prepare("UPDATE mensajes_chat SET leido = 1 WHERE emisor_id = ? AND receptor_id = ? AND leido = 0");
        $stmt_read->bind_param("ii", $chat_con_id, $usuario_id);
        $stmt_read->execute();
        $stmt_read->close();

        // Obtener historial de chat
        $sql_msg = "
            SELECT m.*, 
                   e.username as emisor_username, e.nombre as emisor_nombre, e.apellido as emisor_apellido,
                   r.username as receptor_username, r.nombre as receptor_nombre, r.apellido as receptor_apellido
            FROM mensajes_chat m
            JOIN usuarios e ON m.emisor_id = e.id
            JOIN usuarios r ON m.receptor_id = r.id
            WHERE (m.emisor_id = ? AND m.receptor_id = ?) 
               OR (m.emisor_id = ? AND m.receptor_id = ?)
            ORDER BY m.fecha_envio ASC
        ";
        $stmt_msg = $db->prepare($sql_msg);
        $stmt_msg->bind_param("iiii", $usuario_id, $chat_con_id, $chat_con_id, $usuario_id);
        $stmt_msg->execute();
        $res_msg = $stmt_msg->get_result();
        $mensajes = [];
        while ($row = $res_msg->fetch_assoc()) {
            $mensajes[] = [
                "id" => (int)$row['id'],
                "emisor_id" => (int)$row['emisor_id'],
                "receptor_id" => (int)$row['receptor_id'],
                "mensaje" => $row['mensaje'],
                "fecha_envio" => $row['fecha_envio'],
                "leido" => (int)$row['leido'],
                "yo" => ((int)$row['emisor_id'] === $usuario_id),
                "archivo_nombre" => $row['archivo_nombre'] ?? null,
                "archivo_tipo" => $row['archivo_tipo'] ?? null,
                "archivo_size" => isset($row['archivo_size']) ? (int)$row['archivo_size'] : null,
                "archivo_hash" => $row['archivo_hash'] ?? null
            ];
        }
        $stmt_msg->close();

        echo json_encode([
            "exito" => true,
            "mensajes" => $mensajes
        ]);
        exit;
    }

    if ($metodo === 'POST') {
        $content_type = $_SERVER['CONTENT_TYPE'] ?? '';
        $data = [];
        if (stripos($content_type, 'application/json') !== false) {
            $raw_data = file_get_contents('php://input');
            $data = json_decode($raw_data, true) ?? [];
        } else {
            $data = $_POST;
        }

        // Validar que venga mensaje o archivo
        if (empty($data['mensaje']) && !isset($_FILES['archivo'])) {
            http_response_code(400);
            echo json_encode(["exito" => false, "mensaje" => "Mensaje o archivo adjunto requerido"]);
            exit;
        }

        $receptor_id = isset($data['receptor_id']) ? (int)$data['receptor_id'] : null;

        // Si no se especifica el receptor, auto-apuntar al supervisor/referente
        if (!$receptor_id) {
            $receptor_id = $usr_data['referente_id'] ? (int)$usr_data['referente_id'] : 1;
            if ($receptor_id === $usuario_id) {
                $receptor_id = ($usuario_id == 1) ? null : 1;
            }
        }

        if (!$receptor_id) {
            http_response_code(400);
            echo json_encode(["exito" => false, "mensaje" => "No se pudo determinar el destinatario del mensaje (Supervisor no configurado)"]);
            exit;
        }

        $mensaje = isset($data['mensaje']) ? trim($data['mensaje']) : '';
        
        $archivo_nombre = null;
        $archivo_ruta = null;
        $archivo_tipo = null;
        $archivo_size = null;
        $archivo_hash = null;

        // Procesar archivo adjunto si existe
        if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['archivo'];
            $file_size = $file['size'];
            $file_name = $file['name'];
            $file_tmp = $file['tmp_name'];

            // 1. Validar tamaño máximo (10MB)
            if ($file_size > 10 * 1024 * 1024) {
                http_response_code(400);
                echo json_encode(["exito" => false, "mensaje" => "El archivo excede el tamaño máximo permitido de 10 MB"]);
                exit;
            }

            // 2. Validar extensión permitida
            $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed_exts = ['xls', 'xlsx', 'csv', 'xml', 'json', 'doc', 'docx', 'ppt', 'pptx', 'pdf', 'jpeg', 'jpg', 'png'];
            if (!in_array($ext, $allowed_exts)) {
                http_response_code(400);
                echo json_encode(["exito" => false, "mensaje" => "Formato de archivo no permitido. Solo se admiten documentos estándar e imágenes."]);
                exit;
            }

            // 3. Validar MIME type
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime_type = $finfo->file($file_tmp);
            
            $allowed_mimes = [
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'text/csv',
                'text/plain',
                'text/xml',
                'application/xml',
                'application/json',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'application/pdf',
                'image/png',
                'image/jpeg',
                'image/jpg'
            ];

            if (!in_array($mime_type, $allowed_mimes) && $mime_type !== 'text/plain') {
                http_response_code(400);
                echo json_encode(["exito" => false, "mensaje" => "El tipo de archivo no corresponde con la extensión declarada."]);
                exit;
            }

            // 4. Crear carpeta dinámica /uploads/chat/YYYY/MM/
            $sub_dir = 'uploads/chat/' . date('Y/m') . '/';
            $upload_dir = dirname(__FILE__) . '/../' . $sub_dir;
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            // 5. Generar nombre hash único + calcular SHA-256
            $new_filename = hash('sha256', time() . rand(1000, 9999)) . '.' . $ext;
            $dest_path = $upload_dir . $new_filename;

            if (move_uploaded_file($file_tmp, $dest_path)) {
                $archivo_nombre = $file_name;
                $archivo_ruta = $sub_dir . $new_filename;
                $archivo_tipo = $mime_type;
                $archivo_size = $file_size;
                $archivo_hash = hash_file('sha256', $dest_path);
                
                if (empty($mensaje)) {
                    $mensaje = "📎 Archivo adjunto: " . $file_name;
                }
            } else {
                http_response_code(500);
                echo json_encode(["exito" => false, "mensaje" => "Error al almacenar el archivo en el servidor."]);
                exit;
            }
        }

        $stmt_ins = $db->prepare("INSERT INTO mensajes_chat (emisor_id, receptor_id, mensaje, fecha_envio, leido, archivo_nombre, archivo_ruta, archivo_tipo, archivo_size, archivo_hash) VALUES (?, ?, ?, NOW(), 0, ?, ?, ?, ?, ?)");
        $stmt_ins->bind_param("iissssiss", $usuario_id, $receptor_id, $mensaje, $archivo_nombre, $archivo_ruta, $archivo_tipo, $archivo_size, $archivo_hash);
        
        if ($stmt_ins->execute()) {
            $nuevo_id = $stmt_ins->insert_id;
            $stmt_ins->close();
            
            // 🤖 BHN-Bot-HelpNow
            $es_bot_trigger = false;
            $bot_keywords = ['bot', 'bhn', 'help', 'now'];
            $primer_palabra = strtolower(explode(' ', $mensaje)[0]);
            if (in_array($primer_palabra, $bot_keywords) || preg_match('/^(bot|bhn|help|now)\b/i', $mensaje)) {
                $es_bot_trigger = true;
            }

            if ($es_bot_trigger) {
                $bot_reply = "";
                $txtLower = strtolower($mensaje);
                
                if (strpos($txtLower, 'php') !== false) {
                    $bot_reply = "🤖 **BHN-Bot-HelpNow (Experto PHP 8.2)**: La plataforma corre sobre PHP 8.2. Usamos POO y patrón Singleton.";
                } elseif (strpos($txtLower, 'mysql') !== false || strpos($txtLower, 'bd') !== false) {
                    $bot_reply = "🤖 **BHN-Bot-HelpNow (Experto MySQL)**: La base de datos es MariaDB. Toda transacción crítica requiere commit/rollback.";
                } elseif (strpos($txtLower, 'javascript') !== false) {
                    $bot_reply = "🤖 **BHN-Bot-HelpNow (Experto JS/DOM)**: El frontend usa JS puro y fetch para llamadas asíncronas.";
                } else {
                    $bot_reply = "🤖 **BHN-Bot-HelpNow (Soporte Técnico)**: Hola, ¿en qué área necesitas asistencia técnica?";
                }

                $stmt_bot = $db->prepare("INSERT INTO mensajes_chat (emisor_id, receptor_id, mensaje, fecha_envio, leido) VALUES (?, ?, ?, NOW(), 0)");
                $stmt_bot->bind_param("iis", $receptor_id, $usuario_id, $bot_reply);
                $stmt_bot->execute();
                $stmt_bot->close();
            }
            
            echo json_encode([
                "exito" => true,
                "mensaje" => "Mensaje enviado con éxito",
                "datos" => [
                    "id" => $nuevo_id,
                    "emisor_id" => $usuario_id,
                    "receptor_id" => $receptor_id,
                    "mensaje" => $mensaje,
                    "fecha_envio" => date('Y-m-d H:i:s'),
                    "leido" => 0,
                    "yo" => true,
                    "archivo_nombre" => $archivo_nombre,
                    "archivo_tipo" => $archivo_tipo,
                    "archivo_size" => $archivo_size
                ]
            ]);
        } else {
            throw new Exception("Error al guardar el mensaje: " . $db->error);
        }
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["exito" => false, "mensaje" => "Error interno: " . $e->getMessage()]);
}
