<?php
/**
 * API del Centro de Integración de Aseguradoras — v1.0
 * MAS QUE FIANZAS — Sistema Integrado
 * ==========================================================
 * Permite listar, configurar y auditar credenciales de APIs de terceros
 * y ejecutar pruebas de conectividad de servicios web en tiempo real.
 */

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once '../config.php';
require_once '../Vault.php'; // Inclusión del módulo de encriptación

// ─── VALIDACIÓN DE SESIÓN (PHP Session + Bearer Token) ──────────────────────
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
$db = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'listar';

try {
    // ─── VERIFICAR PERMISOS BASE DEL MÓDULO ──────────────────────────────────
    if (!tienePermiso($usuario_actual, 'TAB_CONF_INTEGRACIONES') && $usuario_actual !== 1) {
        http_response_code(403);
        echo json_encode(["exito" => false, "mensaje" => "No tiene permisos para acceder a las integraciones del sistema."]);
        exit;
    }

    if ($method === 'GET') {
        if ($action === 'listar') {
            // LISTAR INTEGRACIONES
            $sql = "SELECT i.id, i.compania_id, c.nombre AS nombre_compania, c.tipo AS tipo_compania,
                           i.url_base, i.client_id, i.auth_key, i.client_secret, i.headers_json, i.estado, i.fecha_modificacion
                    FROM integraciones_aseguradoras i
                    INNER JOIN companias_registradas c ON i.compania_id = c.id
                    ORDER BY c.nombre ASC";
            
            $res = $db->query($sql);
            $integraciones = [];
            while ($row = $res->fetch_assoc()) {
                $row['id'] = (int)$row['id'];
                $row['compania_id'] = (int)$row['compania_id'];
                $row['estado'] = (int)$row['estado'];

                // Enmascarar credenciales sensibles
                $row['auth_key'] = !empty($row['auth_key']) ? "••••••••" : "";
                $row['client_secret'] = !empty($row['client_secret']) ? "••••••••" : "";
                
                $integraciones[] = $row;
            }
            
            echo json_encode(["exito" => true, "data" => $integraciones]);
            exit;
        } elseif ($action === 'obtener') {
            $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
            if (!$id) throw new Exception("ID de integración no especificado.");

            $stmt = $db->prepare("SELECT id, compania_id, url_base, client_id, auth_key, client_secret, headers_json, estado 
                                  FROM integraciones_aseguradoras WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $integ = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$integ) throw new Exception("Integración no encontrada.");

            $integ['id'] = (int)$integ['id'];
            $integ['compania_id'] = (int)$integ['compania_id'];
            $integ['estado'] = (int)$integ['estado'];

            // Enmascarar credenciales sensibles para el formulario
            $integ['auth_key'] = !empty($integ['auth_key']) ? "••••••••" : "";
            $integ['client_secret'] = !empty($integ['client_secret']) ? "••••••••" : "";

            echo json_encode(["exito" => true, "data" => $integ]);
            exit;
        } else {
            throw new Exception("Acción GET no soportada.");
        }
    } elseif ($method === 'POST') {
        // OPERACIONES DE ESCRITURA
        if (!tienePermiso($usuario_actual, 'CONF_INTEGRACIONES_EDITAR') && $usuario_actual !== 1) {
            http_response_code(403);
            echo json_encode(["exito" => false, "mensaje" => "No tiene autorización para modificar credenciales de integraciones."]);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        if ($action === 'guardar') {
            $id = isset($input['id']) ? (int)$input['id'] : null;
            $compania_id = (int)($input['compania_id'] ?? 0);
            $url_base = trim($input['url_base'] ?? '');
            $client_id = trim($input['client_id'] ?? '');
            $auth_key = trim($input['auth_key'] ?? '');
            $client_secret = trim($input['client_secret'] ?? '');
            $headers_json = trim($input['headers_json'] ?? '');
            $estado = isset($input['estado']) ? (int)$input['estado'] : 1;

            if ($compania_id <= 0) throw new Exception("Debe seleccionar una compañía válida.");
            if (empty($url_base)) throw new Exception("La URL base es obligatoria.");

            // Validar si headers_json es JSON válido
            if (!empty($headers_json)) {
                json_decode($headers_json);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception("El formato de las cabeceras personalizadas (Headers JSON) no es válido.");
                }
            } else {
                $headers_json = "{}";
            }

            $db->begin_transaction();

            // Buscar valor anterior para actualizar / encriptar / auditar
            $valor_anterior = null;
            $encriptado_auth_key = null;
            $encriptado_client_secret = null;

            if ($id) {
                // Modo edición
                $stmt_prev = $db->prepare("SELECT * FROM integraciones_aseguradoras WHERE id = ? LIMIT 1");
                $stmt_prev->bind_param("i", $id);
                $stmt_prev->execute();
                $valor_anterior = $stmt_prev->get_result()->fetch_assoc();
                $stmt_prev->close();

                if (!$valor_anterior) throw new Exception("La configuración de integración no existe.");

                // Evaluar si se modificaron llaves
                if ($auth_key === "••••••••" || empty($auth_key)) {
                    $encriptado_auth_key = $valor_anterior['auth_key'];
                } else {
                    $encriptado_auth_key = Vault::encrypt($auth_key);
                }

                if ($client_secret === "••••••••" || empty($client_secret)) {
                    $encriptado_client_secret = $valor_anterior['client_secret'];
                } else {
                    $encriptado_client_secret = Vault::encrypt($client_secret);
                }

                $sql_upd = "UPDATE integraciones_aseguradoras 
                            SET compania_id = ?, url_base = ?, auth_key = ?, client_id = ?, client_secret = ?, headers_json = ?, estado = ?, modificado_por = ?
                            WHERE id = ?";
                $stmt_upd = $db->prepare($sql_upd);
                $stmt_upd->bind_param("isssssiii", $compania_id, $url_base, $encriptado_auth_key, $client_id, $encriptado_client_secret, $headers_json, $estado, $usuario_actual, $id);
                $stmt_upd->execute();
                $stmt_upd->close();

                // Enmascarar claves en los logs de auditoría para evitar fugas de secretos
                $valor_anterior_audit = $valor_anterior;
                if (!empty($valor_anterior_audit['auth_key'])) $valor_anterior_audit['auth_key'] = "••••••••";
                if (!empty($valor_anterior_audit['client_secret'])) $valor_anterior_audit['client_secret'] = "••••••••";

                $input_audit = $input;
                $input_audit['auth_key'] = !empty($auth_key) ? "••••••••" : "";
                $input_audit['client_secret'] = !empty($client_secret) ? "••••••••" : "";

                logAudit(
                    $usuario_actual, 'editar_integracion', 'Configuracion', 'CONF_INTEGRACIONES_EDITAR',
                    "Modificada integración de API (ID: {$id}, URL: {$url_base})", 'exitoso', null,
                    'integraciones_aseguradoras', $id, $valor_anterior_audit, $input_audit
                );

                $db->commit();
                echo json_encode(["exito" => true, "mensaje" => "Integración de API guardada con éxito.", "id" => $id]);
                exit;
            } else {
                // Modo creación
                $encriptado_auth_key = !empty($auth_key) ? Vault::encrypt($auth_key) : "";
                $encriptado_client_secret = !empty($client_secret) ? Vault::encrypt($client_secret) : "";

                $sql_ins = "INSERT INTO integraciones_aseguradoras (compania_id, url_base, auth_key, client_id, client_secret, headers_json, estado, modificado_por) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt_ins = $db->prepare($sql_ins);
                $stmt_ins->bind_param("isssssii", $compania_id, $url_base, $encriptado_auth_key, $client_id, $encriptado_client_secret, $headers_json, $estado, $usuario_actual);
                $stmt_ins->execute();
                $new_id = $db->insert_id;
                $stmt_ins->close();

                $input_audit = $input;
                $input_audit['auth_key'] = !empty($auth_key) ? "••••••••" : "";
                $input_audit['client_secret'] = !empty($client_secret) ? "••••••••" : "";

                logAudit(
                    $usuario_actual, 'crear_integracion', 'Configuracion', 'CONF_INTEGRACIONES_EDITAR',
                    "Creada nueva integración de API (ID: {$new_id}, URL: {$url_base})", 'exitoso', null,
                    'integraciones_aseguradoras', $new_id, null, $input_audit
                );

                $db->commit();
                echo json_encode(["exito" => true, "mensaje" => "Nueva integración registrada con éxito.", "id" => $new_id]);
                exit;
            }
        } elseif ($action === 'test_conexion') {
            // PROBAR CONEXIÓN (Ping cURL seco)
            $id = isset($input['id']) ? (int)$input['id'] : null;
            if (!$id) throw new Exception("ID de integración no especificado.");

            $stmt = $db->prepare("SELECT i.url_base, i.auth_key, i.client_id, i.client_secret, i.headers_json, c.nombre 
                                  FROM integraciones_aseguradoras i
                                  INNER JOIN companias_registradas c ON i.compania_id = c.id
                                  WHERE i.id = ? LIMIT 1");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $integ = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$integ) throw new Exception("La integración seleccionada no existe.");

            // Descifrar credenciales
            $auth_key = !empty($integ['auth_key']) ? Vault::decrypt($integ['auth_key']) : '';
            $client_id = $integ['client_id'];
            $client_secret = !empty($integ['client_secret']) ? Vault::decrypt($integ['client_secret']) : '';
            $url = $integ['url_base'];

            // Recopilar cabeceras
            $headers = ['Content-Type: application/json', 'User-Agent: MQF-Integration-Client/1.0'];
            if (!empty($auth_key)) {
                $headers[] = "Authorization: Bearer " . $auth_key;
            }
            if (!empty($client_id) && !empty($client_secret)) {
                $headers[] = "X-Client-Id: " . $client_id;
                $headers[] = "X-Client-Secret: " . $client_secret;
            }

            // Añadir cabeceras personalizadas de headers_json
            $custom_headers = json_decode($integ['headers_json'], true) ?: [];
            foreach ($custom_headers as $key => $val) {
                $headers[] = "$key: $val";
            }

            $curl_logs = [];
            $curl_logs[] = "[" . date('H:i:s') . "] Inicializando diagnóstico de cURL a " . htmlspecialchars($url);
            $curl_logs[] = "[" . date('H:i:s') . "] Cabeceras configuradas: " . count($headers);

            // Iniciar cURL
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8); // Timeout estricto de 8 segundos
            curl_setopt($ch, CURLOPT_HEADER, true); // Retornar cabeceras en output
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Permitir certificados autocertificados (común en desarrollo)

            $t_start = microtime(true);
            $response = curl_exec($ch);
            $t_end = microtime(true);
            $latency = round(($t_end - $t_start) * 1000); // Milisegundos

            $response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            $diagnostico_exito = false;
            if ($response !== false) {
                $curl_logs[] = "[" . date('H:i:s') . "] Respuesta HTTP recibida con éxito en {$latency}ms.";
                $curl_logs[] = "[" . date('H:i:s') . "] Código HTTP de Servidor Externo: " . $response_code;

                // Separar headers del body de la respuesta
                $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
                $resp_headers = substr($response, 0, $header_size);
                $resp_body = substr($response, $header_size);

                $curl_logs[] = "[" . date('H:i:s') . "] Handshake completado con éxito.";
                if ($response_code >= 200 && $response_code < 300) {
                    $diagnostico_exito = true;
                }
            } else {
                $curl_logs[] = "[" . date('H:i:s') . "] Error al establecer conexión con el host.";
                $curl_logs[] = "[" . date('H:i:s') . "] Detalle Técnico: " . $error;
                $resp_body = "";
            }

            // Guardar auditoría del test
            logAudit(
                $usuario_actual, 'test_integracion', 'Configuracion', 'CONF_INTEGRACIONES_EDITAR',
                "Ejecutada prueba de conexión para '{$integ['nombre']}' (Latencia: {$latency}ms, Código: {$response_code})",
                $diagnostico_exito ? 'exitoso' : 'fallido', $error ?: null, 'integraciones_aseguradoras', $id
            );

            echo json_encode([
                "exito" => true,
                "diagnostico_exito" => $diagnostico_exito,
                "codigo_http" => $response_code,
                "latencia" => $latency,
                "logs" => $curl_logs,
                "mensaje" => $diagnostico_exito ? "✅ Conexión establecida correctamente" : "❌ Falla de conectividad"
            ]);
            exit;
        } else {
            throw new Exception("Acción POST no soportada.");
        }
    } else {
        http_response_code(405);
        echo json_encode(["exito" => false, "mensaje" => "Método HTTP no soportado."]);
    }
} catch (Exception $e) {
    if (isset($db) && $db->in_transaction) $db->rollback();
    http_response_code(500);
    echo json_encode(["exito" => false, "mensaje" => $e->getMessage()]);
}
