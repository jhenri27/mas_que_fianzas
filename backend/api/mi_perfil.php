<?php
/**
 * API Mi Perfil - Usuario Autenticado
 * GET: obtener datos propios | POST foto: subir foto | PUT: actualizar datos
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once '../config.php';

session_start();
$bearer_token = null;
$auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? (function_exists('apache_request_headers') ? (apache_request_headers()['Authorization'] ?? '') : '');
if (preg_match('/Bearer\s+(.+)$/i', $auth_header, $matches)) $bearer_token = trim($matches[1]);

$db = Database::getInstance()->getConnection();

// Resolver usuario_id: sesión PHP > token bearer en tabla sesiones > fallo
$usuario_id = null;
if (isset($_SESSION['usuario_id']) && $_SESSION['usuario_id']) {
    $usuario_id = (int)$_SESSION['usuario_id'];
} elseif (!empty($bearer_token)) {
    $stmt_tk = $db->prepare("SELECT usuario_id FROM sesiones_usuario WHERE token_sesion = ? AND activa = 1 AND fecha_expiracion > NOW() LIMIT 1");
    if ($stmt_tk) {
        $stmt_tk->bind_param("s", $bearer_token);
        $stmt_tk->execute();
        $res_tk = $stmt_tk->get_result();
        if ($row_tk = $res_tk->fetch_assoc()) $usuario_id = (int)$row_tk['usuario_id'];
        $stmt_tk->close();
    }
}

if (!$usuario_id) {
    respuestaJSON(false, 'Sesión no válida o expirada', null, 401);
    exit;
}

// Asegurar columna foto_perfil — compatible con MySQL 5.x
$col_check = $db->query("SHOW COLUMNS FROM usuarios LIKE 'foto_perfil'");
if ($col_check && $col_check->num_rows === 0) {
    $db->query("ALTER TABLE usuarios ADD COLUMN foto_perfil VARCHAR(255) NULL");
}

$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'GET') {
    $sql = "SELECT u.id, u.codigo_usuario, u.nombre, u.apellido, u.email, u.telefono,
                   u.username, u.estado, u.foto_perfil, p.nombre_perfil, u.fecha_ultimo_acceso
            FROM usuarios u
            LEFT JOIN perfiles p ON u.perfil_id = p.id
            WHERE u.id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $datos = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$datos) { respuestaJSON(false, 'Usuario no encontrado', null, 404); exit; }
    respuestaJSON(true, 'Datos cargados', $datos, 200);

} elseif ($metodo === 'POST') {
    // Subir foto de perfil
    if (!isset($_FILES['foto'])) { respuestaJSON(false, 'No se recibió imagen', null, 400); exit; }
    $foto = $_FILES['foto'];
    $tipos_permitidos = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($foto['type'], $tipos_permitidos)) {
        respuestaJSON(false, 'Tipo de archivo no permitido. Use JPG, PNG, GIF o WebP', null, 400); exit;
    }
    if ($foto['size'] > 2 * 1024 * 1024) {
        respuestaJSON(false, 'La imagen no debe superar 2MB', null, 400); exit;
    }
    $directorio = dirname(__FILE__, 3) . '/frontend/assets/fotos/';
    if (!is_dir($directorio)) mkdir($directorio, 0777, true);
    $extension = strtolower(pathinfo($foto['name'], PATHINFO_EXTENSION));
    $nombre_archivo = 'user_' . $usuario_id . '_' . time() . '.' . $extension;
    $ruta_destino = $directorio . $nombre_archivo;
    if (!move_uploaded_file($foto['tmp_name'], $ruta_destino)) {
        respuestaJSON(false, 'Error al guardar la imagen en el servidor', null, 500); exit;
    }
    $ruta_bd = '/PLATAFORMA_INTEGRADA/frontend/assets/fotos/' . $nombre_archivo;
    $stmt = $db->prepare("UPDATE usuarios SET foto_perfil = ? WHERE id = ?");
    $stmt->bind_param("si", $ruta_bd, $usuario_id);
    $stmt->execute();
    $stmt->close();
    respuestaJSON(true, 'Foto actualizada', ['foto_url' => $ruta_bd], 200);

} elseif ($metodo === 'PUT') {
    $datos = json_decode(file_get_contents('php://input'), true);
    $nombre   = trim($datos['nombre'] ?? '');
    $apellido = trim($datos['apellido'] ?? '');
    $telefono = trim($datos['telefono'] ?? '');
    if (empty($nombre) || empty($apellido)) {
        respuestaJSON(false, 'Nombre y apellido son requeridos', null, 400); exit;
    }
    $stmt = $db->prepare("UPDATE usuarios SET nombre=?, apellido=?, telefono=?, fecha_modificacion=NOW() WHERE id=?");
    $stmt->bind_param("sssi", $nombre, $apellido, $telefono, $usuario_id);
    if (!$stmt->execute()) { respuestaJSON(false, 'Error al actualizar', null, 500); exit; }
    $stmt->close();
    logAudit($usuario_id, 'editar_perfil_propio', 'usuarios', 'MI_PERFIL', 'Usuario actualizó su propio perfil', 'exitoso');
    respuestaJSON(true, 'Perfil actualizado exitosamente', null, 200);
}
?>
