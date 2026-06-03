<?php
/**
 * API: Empresas del Cliente (Sección "Mis Empresas" del Módulo Fianzas)
 * MAS QUE FIANZAS +QF, SRL — NOFTRAB
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once '../config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$bearer_token = null;
$auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? (function_exists('apache_request_headers') ? (apache_request_headers()['Authorization'] ?? '') : '');
if (preg_match('/Bearer\s+(.+)$/i', $auth_header, $m)) $bearer_token = trim($m[1]);

$usuario_id = null;
if (!empty($_SESSION['usuario_id'])) {
    $usuario_id = (int)$_SESSION['usuario_id'];
} elseif (!empty($bearer_token)) {
    $db_t = Database::getInstance()->getConnection();
    $s = $db_t->prepare("SELECT usuario_id FROM sesiones_usuario WHERE token_sesion=? AND activa=1 AND fecha_expiracion>NOW() LIMIT 1");
    if ($s) { $s->bind_param('s', $bearer_token); $s->execute(); $r = $s->get_result(); if ($row = $r->fetch_assoc()) $usuario_id = (int)$row['usuario_id']; $s->close(); }
}

if (!$usuario_id) respuestaJSON(false, 'Sesión no válida o expirada', null, 401);

$db     = Database::getInstance()->getConnection();
$action = $_GET['action'] ?? '';
$metodo = $_SERVER['REQUEST_METHOD'];

// Auto-crear tabla si no existe
$db->query("CREATE TABLE IF NOT EXISTS fianza_empresas_cliente (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id      INT NOT NULL,
  razon_social    VARCHAR(200) NOT NULL,
  rnc             VARCHAR(20),
  direccion       TEXT,
  telefono        VARCHAR(30),
  email           VARCHAR(120),
  contacto_nombre VARCHAR(150),
  estado          ENUM('activo','inactivo') DEFAULT 'activo',
  creado_en       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// GET: listar
if ($metodo === 'GET' && $action === 'listar') {
    $stmt = $db->prepare("SELECT * FROM fianza_empresas_cliente WHERE usuario_id = ? AND estado = 'activo' ORDER BY razon_social");
    $stmt->bind_param('i', $usuario_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $data = [];
    while ($row = $res->fetch_assoc()) $data[] = $row;
    $stmt->close();
    respuestaJSON(true, 'OK', $data);
}

// POST: guardar (crear o actualizar)
if ($metodo === 'POST' && $action === 'guardar') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id    = isset($input['id']) ? (int)$input['id'] : 0;
    $rs    = trim($input['razon_social'] ?? '');
    $rnc   = trim($input['rnc'] ?? '');
    $dir   = trim($input['direccion'] ?? '');
    $tel   = trim($input['telefono'] ?? '');
    $email = trim($input['email'] ?? '');
    $cont  = trim($input['contacto_nombre'] ?? '');

    if (empty($rs)) respuestaJSON(false, 'Razón social requerida', null, 400);

    if ($id > 0) {
        // Actualizar (solo si pertenece al usuario)
        $stmt = $db->prepare("UPDATE fianza_empresas_cliente SET razon_social=?, rnc=?, direccion=?, telefono=?, email=?, contacto_nombre=? WHERE id=? AND usuario_id=?");
        $stmt->bind_param('ssssssii', $rs, $rnc, $dir, $tel, $email, $cont, $id, $usuario_id);
        $ok = $stmt->execute();
        $stmt->close();
        respuestaJSON($ok, $ok ? 'Empresa actualizada' : 'Error al actualizar');
    } else {
        // Crear
        $stmt = $db->prepare("INSERT INTO fianza_empresas_cliente (usuario_id, razon_social, rnc, direccion, telefono, email, contacto_nombre) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param('issssss', $usuario_id, $rs, $rnc, $dir, $tel, $email, $cont);
        $ok = $stmt->execute();
        $new_id = $stmt->insert_id;
        $stmt->close();
        respuestaJSON($ok, $ok ? 'Empresa registrada' : 'Error al registrar', $ok ? ['id' => $new_id] : null);
    }
}

// DELETE: eliminar
if ($metodo === 'POST' && $action === 'eliminar') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id    = isset($input['id']) ? (int)$input['id'] : 0;
    if ($id <= 0) respuestaJSON(false, 'ID inválido', null, 400);

    $stmt = $db->prepare("UPDATE fianza_empresas_cliente SET estado='inactivo' WHERE id=? AND usuario_id=?");
    $stmt->bind_param('ii', $id, $usuario_id);
    $ok = $stmt->execute();
    $stmt->close();
    respuestaJSON($ok, $ok ? 'Empresa eliminada' : 'Error al eliminar');
}

respuestaJSON(false, "Acción '$action' no reconocida", null, 400);
?>
