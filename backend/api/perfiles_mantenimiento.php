<?php
/**
 * API: Mantenimiento y Auditoría de Perfiles (Fase 1)
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
require_once dirname(__DIR__) . '/PerfilManager.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validar token de sesión
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

$db = Database::getInstance()->getConnection();

// Verificar si el usuario tiene permisos de configuración o total
$stmt_check = $db->prepare("SELECT perfil_id FROM usuarios WHERE id = ? LIMIT 1");
$stmt_check->bind_param("i", $usuario_id);
$stmt_check->execute();
$usr_data = $stmt_check->get_result()->fetch_assoc();
$stmt_check->close();

$perfil_id_usr = (int)($usr_data['perfil_id'] ?? 0);
$tiene_acceso = ($usuario_id === 1 || $perfil_id_usr === 1 || tienePermiso($usuario_id, 'CONF_TOTAL') || tienePermiso($usuario_id, 'PER_GESTIONAR'));

if (!$tiene_acceso) {
    http_response_code(403);
    echo json_encode(["exito" => false, "mensaje" => "No tiene privilegios para administrar perfiles."]);
    exit;
}

$action = $_GET['action'] ?? 'listar';
$manager = new PerfilManager();

try {
    switch ($action) {
        case 'listar':
            $res = $db->query("SELECT * FROM perfiles ORDER BY id ASC");
            $perfiles = [];
            while ($row = $res->fetch_assoc()) {
                $perfiles[] = [
                    "id" => (int)$row['id'],
                    "nombre_perfil" => $row['nombre_perfil'],
                    "descripcion" => $row['descripcion'],
                    "nivel_jerarquico" => (int)$row['nivel_jerarquico'],
                    "estado" => $row['estado'],
                    "creado_el" => $row['fecha_creacion'] ?? null
                ];
            }
            echo json_encode(["exito" => true, "datos" => $perfiles]);
            break;

        case 'obtener':
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) {
                throw new Exception("ID de perfil requerido");
            }
            $stmt = $db->prepare("SELECT * FROM perfiles WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $p = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$p) {
                throw new Exception("Perfil no encontrado");
            }

            echo json_encode([
                "exito" => true,
                "datos" => [
                    "id" => (int)$p['id'],
                    "nombre_perfil" => $p['nombre_perfil'],
                    "descripcion" => $p['descripcion'],
                    "nivel_jerarquico" => (int)$p['nivel_jerarquico'],
                    "estado" => $p['estado']
                ]
            ]);
            break;

        case 'guardar':
            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true);
            if (!$data) {
                throw new Exception("Datos vacíos o inválidos");
            }

            $id = isset($data['id']) ? (int)$data['id'] : null;
            $nombre = trim($data['nombre_perfil'] ?? '');
            $descripcion = trim($data['descripcion'] ?? '');
            $nivel = isset($data['nivel_jerarquico']) ? (int)$data['nivel_jerarquico'] : null;
            $estado = trim($data['estado'] ?? 'activo');
            $heredar_de = isset($data['heredar_de']) && !empty($data['heredar_de']) ? (int)$data['heredar_de'] : null;

            if (empty($nombre) || is_null($nivel)) {
                throw new Exception("Nombre de perfil y nivel jerárquico son obligatorios");
            }

            $ip = $_SERVER['REMOTE_ADDR'] ?? 'N/D';
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'N/D';

            $db->begin_transaction();

            if ($id) {
                // Modificar existente
                // Obtener valor anterior para auditoría
                $stmt = $db->prepare("SELECT * FROM perfiles WHERE id = ? LIMIT 1");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $prev = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$prev) {
                    throw new Exception("Perfil a editar no existe");
                }

                $resEdit = $manager->editarPerfil($id, [
                    "nombre_perfil" => $nombre,
                    "descripcion" => $descripcion,
                    "estado" => $estado,
                    "nivel_jerarquico" => $nivel
                ], $usuario_id);

                if (!$resEdit['exito']) {
                    throw new Exception($resEdit['mensaje']);
                }

                // Guardar log inmutable de cambios específicos
                $cambios = [];
                if ($prev['nombre_perfil'] !== $nombre) $cambios['nombre_perfil'] = ['de' => $prev['nombre_perfil'], 'a' => $nombre];
                if ($prev['descripcion'] !== $descripcion) $cambios['descripcion'] = ['de' => $prev['descripcion'], 'a' => $descripcion];
                if ((int)$prev['nivel_jerarquico'] !== $nivel) $cambios['nivel_jerarquico'] = ['de' => (int)$prev['nivel_jerarquico'], 'a' => $nivel];
                if ($prev['estado'] !== $estado) $cambios['estado'] = ['de' => $prev['estado'], 'a' => $estado];

                if (!empty($cambios)) {
                    $sqlAudit = "INSERT INTO auditoria_accesos 
                                 (usuario_id, modulo_accedido, tipo_evento, descripcion_evento, direccion_ip, navegador_user_agent, valor_anterior, valor_nuevo)
                                 VALUES (?, 'configuracion', 'update', ?, ?, ?, ?, ?)";
                    $stmtAudit = $db->prepare($sqlAudit);
                    $desc = "Modificó perfil ID $id - $nombre";
                    $valAntStr = json_encode($cambios);
                    $valNueStr = json_encode($data);
                    $stmtAudit->bind_param("isssss", $usuario_id, $desc, $ip, $ua, $valAntStr, $valNueStr);
                    $stmtAudit->execute();
                    $stmtAudit->close();
                }

                $db->commit();
                echo json_encode(["exito" => true, "mensaje" => "Perfil actualizado exitosamente."]);
            } else {
                // Crear nuevo perfil
                $resCreate = $manager->crearPerfil([
                    "nombre_perfil" => $nombre,
                    "descripcion" => $descripcion,
                    "nivel_jerarquico" => $nivel,
                    "hereda_de" => $heredar_de
                ], $usuario_id);

                if (!$resCreate['exito']) {
                    throw new Exception($resCreate['mensaje']);
                }

                $newId = (int)$resCreate['perfil_id'];

                // Registrar auditoría de creación
                $sqlAudit = "INSERT INTO auditoria_accesos 
                             (usuario_id, modulo_accedido, tipo_evento, descripcion_evento, direccion_ip, navegador_user_agent, valor_nuevo)
                             VALUES (?, 'configuracion', 'create', ?, ?, ?, ?)";
                $stmtAudit = $db->prepare($sqlAudit);
                $desc = "Creó perfil ID $newId - $nombre" . ($heredar_de ? " (clonó permisos de perfil ID $heredar_de)" : "");
                $valNueStr = json_encode($data);
                $stmtAudit->bind_param("issss", $usuario_id, $desc, $ip, $ua, $valNueStr);
                $stmtAudit->execute();
                $stmtAudit->close();

                $db->commit();
                echo json_encode(["exito" => true, "mensaje" => "Perfil registrado exitosamente" . ($heredar_de ? " con copia de permisos" : "") . ".", "id" => $newId]);
            }
            break;

        case 'actualizar_estado':
            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true);
            if (!$data || !isset($data['id']) || !isset($data['estado'])) {
                throw new Exception("Datos incompletos");
            }

            $id = (int)$data['id'];
            $estado = trim($data['estado']);
            if ($estado !== 'activo' && $estado !== 'inactivo') {
                throw new Exception("Estado inválido");
            }

            $db->begin_transaction();

            $stmt = $db->prepare("SELECT nombre_perfil, estado FROM perfiles WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $prev = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$prev) {
                throw new Exception("Perfil no encontrado");
            }

            if ($prev['estado'] === $estado) {
                echo json_encode(["exito" => true, "mensaje" => "El perfil ya se encuentra en ese estado."]);
                $db->commit();
                break;
            }

            $resEdit = $manager->editarPerfil($id, ["estado" => $estado], $usuario_id);
            if (!$resEdit['exito']) {
                throw new Exception($resEdit['mensaje']);
            }

            // Registrar auditoría de cambio de estado
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'N/D';
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'N/D';
            $event = ($estado === 'activo') ? 'enable' : 'disable';
            $desc = ($estado === 'activo') ? "Activó perfil ID $id - {$prev['nombre_perfil']}" : "Desactivó perfil ID $id - {$prev['nombre_perfil']}";
            
            $sqlAudit = "INSERT INTO auditoria_accesos 
                         (usuario_id, modulo_accedido, tipo_evento, descripcion_evento, direccion_ip, navegador_user_agent, valor_anterior, valor_nuevo)
                         VALUES (?, 'configuracion', ?, ?, ?, ?, ?, ?)";
            $stmtAudit = $db->prepare($sqlAudit);
            $stmtAudit->bind_param("issssss", $usuario_id, $event, $desc, $ip, $ua, $prev['estado'], $estado);
            $stmtAudit->execute();
            $stmtAudit->close();

            $db->commit();
            echo json_encode(["exito" => true, "mensaje" => "Estado de perfil actualizado a '$estado' de forma auditada."]);
            break;

        case 'auditoria':
            $perfil_id = (int)($_GET['perfil_id'] ?? 0);
            if (!$perfil_id) {
                throw new Exception("ID de perfil requerido para auditoría");
            }

            // Obtener bitácora inmutable de auditoria_accesos
            $query = "SELECT a.*, u.username 
                      FROM auditoria_accesos a 
                      LEFT JOIN usuarios u ON a.usuario_id = u.id 
                      WHERE a.modulo_accedido = 'configuracion' 
                        AND (a.descripcion_evento LIKE ? OR a.descripcion_evento LIKE ?) 
                      ORDER BY a.fecha_evento DESC, a.id DESC LIMIT 100";
            
            $pattern1 = "%perfil ID $perfil_id%";
            $pattern2 = "%Perfil ID $perfil_id%";
            
            $stmt = $db->prepare($query);
            $stmt->bind_param("ss", $pattern1, $pattern2);
            $stmt->execute();
            $res = $stmt->get_result();
            
            $logs = [];
            while ($row = $res->fetch_assoc()) {
                $logs[] = [
                    "id" => (int)$row['id'],
                    "usuario_id" => (int)$row['usuario_id'],
                    "username" => $row['username'] ?? 'Sistema',
                    "tipo_evento" => $row['tipo_evento'],
                    "descripcion_evento" => $row['descripcion_evento'],
                    "direccion_ip" => $row['direccion_ip'],
                    "navegador_user_agent" => $row['navegador_user_agent'],
                    "valor_anterior" => $row['valor_anterior'],
                    "valor_nuevo" => $row['valor_nuevo'],
                    "fecha_evento" => $row['fecha_evento']
                ];
            }
            $stmt->close();

            echo json_encode(["exito" => true, "datos" => $logs]);
            break;

        case 'copiar_permisos':
            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true);
            if (!$data || !isset($data['origen_id']) || !isset($data['destino_id'])) {
                throw new Exception("Datos de origen y destino requeridos");
            }
            $origen = (int)$data['origen_id'];
            $destino = (int)$data['destino_id'];
            
            $db->begin_transaction();
            $resCopy = $manager->copiarPermisos($origen, $destino, $usuario_id);
            if (!$resCopy['exito']) {
                throw new Exception($resCopy['mensaje']);
            }
            
            // Registrar auditoría de copia de permisos en la bitácora de ambos perfiles
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'N/D';
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'N/D';
            $desc = "Copió malla de permisos desde perfil ID $origen hacia perfil ID $destino";
            
            $sqlAudit = "INSERT INTO auditoria_accesos 
                         (usuario_id, modulo_accedido, tipo_evento, descripcion_evento, direccion_ip, navegador_user_agent, valor_anterior, valor_nuevo)
                         VALUES (?, 'configuracion', 'copy_permissions', ?, ?, ?, ?, ?)";
            $stmtAudit = $db->prepare($sqlAudit);
            $valAntStr = json_encode(["perfil_destino" => $destino]);
            $valNueStr = json_encode(["perfil_origen" => $origen]);
            $stmtAudit->bind_param("issssss", $usuario_id, $desc, $ip, $ua, $valAntStr, $valNueStr);
            $stmtAudit->execute();
            $stmtAudit->close();
            
            $db->commit();
            echo json_encode(["exito" => true, "mensaje" => "Malla de permisos copiada exitosamente de forma transaccional."]);
            break;

        default:
            throw new Exception("Acción no válida");
    }
} catch (Exception $e) {
    if (isset($db) && $db->ping()) {
        $db->rollback();
    }
    http_response_code(400);
    echo json_encode(["exito" => false, "mensaje" => $e->getMessage()]);
}
?>
