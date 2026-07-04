<?php
/**
 * API de Mantenimiento de Compañías Registradas — v1.0
 * MAS QUE FIANZAS — Sistema Integrado
 * ==========================================================
 * Maneja el listado, creación, edición e inhabilitación de 
 * entidades comerciales dentro del ecosistema de la plataforma.
 */

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once '../config.php';

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
    if ($method !== 'GET') {
        if (!tienePermiso($usuario_actual, 'TAB_CONF_COMPANIAS') && $usuario_actual !== 1) {
            http_response_code(403);
            echo json_encode(["exito" => false, "mensaje" => "No tiene permisos para acceder a esta sección de configuración."]);
            exit;
        }
    }

    if ($method === 'GET') {
        if ($action === 'listar') {
            // LISTADO DE COMPAÑÍAS
            $sql = "SELECT id, nombre, rnc, direccion, telefono, email, tipo, estado, fecha_creacion 
                    FROM companias_registradas 
                    ORDER BY 
                      CASE 
                        WHEN LOWER(nombre) LIKE '%multiseguros%' THEN 1
                        WHEN LOWER(nombre) LIKE '%midas%' THEN 2
                        WHEN LOWER(nombre) LIKE '%patria%' THEN 3
                        WHEN LOWER(nombre) LIKE '%pepin%' OR LOWER(nombre) LIKE '%pep%n%' THEN 4
                        ELSE 999
                      END ASC, nombre ASC";
            
            $res = $db->query($sql);
            $companias = [];
            while ($row = $res->fetch_assoc()) {
                $row['id'] = (int)$row['id'];
                $row['estado'] = (int)$row['estado'];
                $companias[] = $row;
            }
            
            echo json_encode(["exito" => true, "data" => $companias]);
            exit;
        } elseif ($action === 'listar_aseguradoras') {
            $sql = "SELECT fa.id, fa.codigo, fa.nombre, fa.rnc, fa.logo_url, fa.estado, fa.creado_en,
                           COUNT(ft.id) AS total_tarifarios
                    FROM fianza_aseguradoras fa
                    LEFT JOIN fianza_tarifarios ft ON ft.aseguradora_id = fa.id
                    GROUP BY fa.id ORDER BY fa.nombre ASC";
            $res2 = $db->query($sql);
            $data = [];
            while ($row = $res2->fetch_assoc()) $data[] = $row;
            echo json_encode(["exito" => true, "data" => $data]);
            exit;
        } else {
            throw new Exception("Acción GET no soportada.");
        }
    } elseif ($method === 'POST') {
        // OPERACIONES DE ESCRITURA
        if (!tienePermiso($usuario_actual, 'CONF_COMPANIAS_EDITAR') && $usuario_actual !== 1) {
            http_response_code(403);
            echo json_encode(["exito" => false, "mensaje" => "No tiene autorización para modificar el registro de compañías."]);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        if ($action === 'guardar') {
            $id = isset($input['id']) ? (int)$input['id'] : null;
            $nombre = trim($input['nombre'] ?? '');
            $rnc = trim($input['rnc'] ?? '');
            $direccion = trim($input['direccion'] ?? '');
            $telefono = trim($input['telefono'] ?? '');
            $email = trim($input['email'] ?? '');
            $tipo = trim($input['tipo'] ?? '');
            $estado = isset($input['estado']) ? (int)$input['estado'] : 1;

            // Validaciones
            if (empty($nombre)) throw new Exception("El nombre de la compañía es obligatorio.");
            if (empty($rnc)) throw new Exception("El RNC es obligatorio.");
            if (!in_array($tipo, ['aseguradora', 'corredora', 'otra'])) {
                throw new Exception("El tipo de compañía seleccionado no es válido.");
            }

            // Validar RNC Dominicana (9 u 11 dígitos numéricos)
            $rnc_limpio = preg_replace('/[^0-9]/', '', $rnc);
            if (strlen($rnc_limpio) !== 9 && strlen($rnc_limpio) !== 11) {
                throw new Exception("El RNC de la República Dominicana debe tener exactamente 9 dígitos (empresas) u 11 dígitos (personas físicas).");
            }

            $db->begin_transaction();

            // Verificar si el RNC ya está registrado por otra compañía
            $sql_chk = "SELECT id, nombre FROM companias_registradas WHERE rnc = ? AND id != ?";
            $id_check = $id ?? 0;
            $stmt_chk = $db->prepare($sql_chk);
            $stmt_chk->bind_param("si", $rnc_limpio, $id_check);
            $stmt_chk->execute();
            $res_chk = $stmt_chk->get_result();
            $stmt_chk->close();
            if ($res_chk->num_rows > 0) {
                $colision = $res_chk->fetch_assoc();
                throw new Exception("El RNC ingresado ya está registrado por la compañía '{$colision['nombre']}'.");
            }

            $valor_anterior = null;
            if ($id) {
                // EDITAR COMPAÑÍA
                // Obtener valor anterior para auditoría
                $stmt_prev = $db->prepare("SELECT * FROM companias_registradas WHERE id = ? LIMIT 1");
                $stmt_prev->bind_param("i", $id);
                $stmt_prev->execute();
                $valor_anterior = $stmt_prev->get_result()->fetch_assoc();
                $stmt_prev->close();

                if (!$valor_anterior) throw new Exception("La compañía que intenta editar no existe.");

                $sql_upd = "UPDATE companias_registradas 
                            SET nombre = ?, rnc = ?, direccion = ?, telefono = ?, email = ?, tipo = ?, estado = ?, modificado_por = ?
                            WHERE id = ?";
                $stmt_upd = $db->prepare($sql_upd);
                $stmt_upd->bind_param("ssssssiii", $nombre, $rnc_limpio, $direccion, $telefono, $email, $tipo, $estado, $usuario_actual, $id);
                $stmt_upd->execute();
                $stmt_upd->close();

                // Auditoría NOFTRAB
                logAudit(
                    $usuario_actual, 'editar_compania', 'Configuracion', 'CONF_COMPANIAS_EDITAR',
                    "Modificada compañía '{$nombre}' (RNC: {$rnc_limpio})", 'exitoso', null,
                    'companias_registradas', $id, $valor_anterior, $input
                );

                $db->commit();
                echo json_encode(["exito" => true, "mensaje" => "Compañía actualizada con éxito.", "id" => $id]);
                exit;
            } else {
                // CREAR COMPAÑÍA
                $sql_ins = "INSERT INTO companias_registradas (nombre, rnc, direccion, telefono, email, tipo, estado, creado_por) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt_ins = $db->prepare($sql_ins);
                $stmt_ins->bind_param("ssssssii", $nombre, $rnc_limpio, $direccion, $telefono, $email, $tipo, $estado, $usuario_actual);
                $stmt_ins->execute();
                $new_id = $db->insert_id;
                $stmt_ins->close();

                // Auditoría NOFTRAB
                logAudit(
                    $usuario_actual, 'crear_compania', 'Configuracion', 'CONF_COMPANIAS_EDITAR',
                    "Creada nueva compañía '{$nombre}' (RNC: {$rnc_limpio})", 'exitoso', null,
                    'companias_registradas', $new_id, null, $input
                );

                $db->commit();
                echo json_encode(["exito" => true, "mensaje" => "Compañía registrada con éxito.", "id" => $new_id]);
                exit;
            }
        } elseif ($action === 'toggle_estado') {
            $id = isset($input['id']) ? (int)$input['id'] : null;
            if (!$id) throw new Exception("ID de compañía no especificado.");

            $db->begin_transaction();

            // Obtener datos actuales
            $stmt_prev = $db->prepare("SELECT nombre, estado FROM companias_registradas WHERE id = ? LIMIT 1");
            $stmt_prev->bind_param("i", $id);
            $stmt_prev->execute();
            $compania = $stmt_prev->get_result()->fetch_assoc();
            $stmt_prev->close();

            if (!$compania) throw new Exception("La compañía seleccionada no existe.");

            $nuevo_estado = $compania['estado'] == 1 ? 0 : 1;

            $stmt_upd = $db->prepare("UPDATE companias_registradas SET estado = ?, modificado_por = ? WHERE id = ?");
            $stmt_upd->bind_param("iii", $nuevo_estado, $usuario_actual, $id);
            $stmt_upd->execute();
            $stmt_upd->close();

            $desc_evt = ($nuevo_estado === 1 ? "Activada" : "Desactivada") . " compañía '{$compania['nombre']}' (ID: {$id})";
            
            logAudit(
                $usuario_actual, 'editar_compania', 'Configuracion', 'CONF_COMPANIAS_EDITAR',
                $desc_evt, 'exitoso', null, 'companias_registradas', $id, 
                ["estado" => $compania['estado']], ["estado" => $nuevo_estado]
            );

            $db->commit();
            echo json_encode(["exito" => true, "mensaje" => "Estado de la compañía modificado con éxito.", "nuevo_estado" => $nuevo_estado]);
            exit;
        // ─── GESTIÓN DE ASEGURADORAS (fianza_aseguradoras) ──────────────────────
        } elseif ($action === 'guardar_aseguradora') {
            $id     = isset($input['id']) ? (int)$input['id'] : null;
            $codigo = strtoupper(trim($input['codigo'] ?? ''));
            $nombre = trim($input['nombre'] ?? '');
            $rnc    = trim($input['rnc'] ?? '') ?: null;

            if (!$codigo || !$nombre) throw new Exception('Código y nombre de aseguradora son obligatorios.');

            if ($id) {
                $stmt = $db->prepare("UPDATE fianza_aseguradoras SET codigo=?, nombre=?, rnc=? WHERE id=?");
                $stmt->bind_param('sssi', $codigo, $nombre, $rnc, $id);
                $stmt->execute(); $stmt->close();
                logAudit($usuario_actual, 'editar_aseguradora', 'Configuracion', 'CONF_COMPANIAS_EDITAR', "Editó aseguradora ID $id: $nombre", 'exitoso', null, 'fianza_aseguradoras', $id, null, $input);
                echo json_encode(["exito" => true, "mensaje" => "Aseguradora actualizada con éxito.", "id" => $id]);
            } else {
                $chk = $db->prepare("SELECT id FROM fianza_aseguradoras WHERE codigo=? OR nombre=? LIMIT 1");
                $chk->bind_param('ss', $codigo, $nombre);
                $chk->execute();
                if ($chk->get_result()->fetch_assoc()) { $chk->close(); throw new Exception('Ya existe una aseguradora con ese código o nombre.'); }
                $chk->close();

                $stmt = $db->prepare("INSERT INTO fianza_aseguradoras (codigo, nombre, rnc, estado) VALUES (?, ?, ?, 'activo')");
                $stmt->bind_param('sss', $codigo, $nombre, $rnc);
                $stmt->execute(); $newId = $db->insert_id; $stmt->close();
                logAudit($usuario_actual, 'crear_aseguradora', 'Configuracion', 'CONF_COMPANIAS_EDITAR', "Creó aseguradora: $nombre", 'exitoso', null, 'fianza_aseguradoras', $newId, null, $input);
                echo json_encode(["exito" => true, "mensaje" => "Aseguradora creada con éxito.", "id" => $newId]);
            }
            exit;
        } elseif ($action === 'toggle_aseguradora') {
            $id = isset($input['id']) ? (int)$input['id'] : 0;
            if (!$id) throw new Exception('ID requerido.');
            $stmt = $db->prepare("UPDATE fianza_aseguradoras SET estado = IF(estado='activo','inactivo','activo') WHERE id=?");
            $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close();
            echo json_encode(["exito" => true, "mensaje" => "Estado de aseguradora actualizado."]);
            exit;
        } elseif ($action === 'subir_logo_aseguradora') {
            // ── Upload de logo (multipart/form-data) ─────────────────────────
            $logoId  = (int)($_POST['id'] ?? 0);
            $logDir  = realpath(dirname(__DIR__, 2) . '/frontend/assets/logos/aseguradoras');
            $logBase = '/PLATAFORMA_INTEGRADA/frontend/assets/logos/aseguradoras';

            if (!$logoId) throw new Exception('ID de aseguradora requerido.');

            if (empty($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
                $uploadErrors = [
                    UPLOAD_ERR_INI_SIZE  => 'El archivo supera upload_max_filesize del servidor.',
                    UPLOAD_ERR_FORM_SIZE => 'El archivo supera MAX_FILE_SIZE del formulario.',
                    UPLOAD_ERR_PARTIAL   => 'El archivo fue subido parcialmente.',
                    UPLOAD_ERR_NO_FILE   => 'No se seleccionó ningún archivo.',
                ];
                $errCode = $_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE;
                $errMsg  = $uploadErrors[$errCode] ?? 'Error desconocido al subir el archivo (código: ' . $errCode . ').';
                throw new Exception($errMsg);
            }

            $file    = $_FILES['logo'];
            $allowed = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp', 'image/svg+xml'];
            $mime    = mime_content_type($file['tmp_name']);

            if ($file['size'] > 2 * 1024 * 1024) throw new Exception('El archivo supera el tamaño máximo de 2 MB.');
            if (!in_array($mime, $allowed))         throw new Exception('Formato no permitido. Use PNG, JPG, WEBP o SVG.');
            if (!$logDir || !is_dir($logDir))       throw new Exception('Directorio de logos no accesible en el servidor.');

            // Eliminar logo anterior si existe
            $prevStmt = $db->prepare('SELECT logo_url FROM fianza_aseguradoras WHERE id=? LIMIT 1');
            $prevStmt->bind_param('i', $logoId); $prevStmt->execute();
            $prevRow = $prevStmt->get_result()->fetch_assoc(); $prevStmt->close();
            if (!empty($prevRow['logo_url'])) {
                $oldFile = realpath(dirname(__DIR__, 2) . parse_url($prevRow['logo_url'], PHP_URL_PATH));
                if ($oldFile && file_exists($oldFile)) @unlink($oldFile);
            }

            $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = 'logo_aseg_' . $logoId . '_' . time() . '.' . $ext;
            $dest     = $logDir . DIRECTORY_SEPARATOR . $filename;

            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                throw new Exception('Error al guardar el archivo en el servidor.');
            }

            $logoUrl  = $logBase . '/' . $filename;
            $updStmt  = $db->prepare('UPDATE fianza_aseguradoras SET logo_url=? WHERE id=?');
            $updStmt->bind_param('si', $logoUrl, $logoId); $ok = $updStmt->execute(); $updStmt->close();

            if (!$ok) throw new Exception('Logo guardado en disco pero no se pudo actualizar la base de datos.');

            if (function_exists('logAudit')) logAudit($usuario_actual, 'subir_logo', 'fianza_aseguradoras', $logoId, "Logo subido: $logoUrl", 'exitoso', null, 'companias');
            echo json_encode(['exito' => true, 'mensaje' => 'Logo subido exitosamente.', 'logo_url' => $logoUrl]);
            exit;
        } else {
            throw new Exception("Acción POST no soportada.");
        }
    } elseif ($method === 'GET' && $action === 'listar_aseguradoras') {
        $sql = "SELECT fa.id, fa.codigo, fa.nombre, fa.rnc, fa.logo_url, fa.estado, fa.creado_en,
                       COUNT(ft.id) AS total_tarifarios
                FROM fianza_aseguradoras fa
                LEFT JOIN fianza_tarifarios ft ON ft.aseguradora_id = fa.id
                GROUP BY fa.id ORDER BY fa.nombre ASC";
        $res = $db->query($sql);
        $data = [];
        while ($row = $res->fetch_assoc()) $data[] = $row;
        echo json_encode(["exito" => true, "data" => $data]);
        exit;
    } else {
        http_response_code(405);
        echo json_encode(["exito" => false, "mensaje" => "Método HTTP no soportado."]);
    }
} catch (Exception $e) {
    if (isset($db) && $db->in_transaction) $db->rollback();
    http_response_code(500);
    echo json_encode(["exito" => false, "mensaje" => $e->getMessage()]);
}

