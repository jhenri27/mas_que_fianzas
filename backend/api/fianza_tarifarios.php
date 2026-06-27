<?php
/**
 * API: Tarifarios de Fianzas
 * MAS QUE FIANZAS +QF, SRL — NOFTRAB
 *
 * REGLA R1 (NOFTRAB): Las tasas son información estratégica interna.
 * - listar_tipos: devuelve tipos SIN tasa (para dropdowns del UI)
 * - listar_admin: devuelve tasas SOLO con permiso FIANZAS_ADMIN_TARIFARIO
 * - actualizar_tasa: requiere FIANZAS_ADMIN_TARIFARIO + audita en historial_ajustes
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once '../config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Autenticación: sesión PHP o Bearer token
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

if (!$usuario_id) {
    respuestaJSON(false, 'Sesión no válida o expirada', null, 401);
}

$db     = Database::getInstance()->getConnection();
$action = $_GET['action'] ?? '';
$metodo = $_SERVER['REQUEST_METHOD'];

// =====================================================================
// GET: listar_aseguradoras — Público para autenticados (sin tasas)
// =====================================================================
if ($metodo === 'GET' && $action === 'listar_aseguradoras') {
    $sql = "SELECT id, codigo, nombre, logo_url FROM fianza_aseguradoras WHERE estado='activo' 
            ORDER BY 
              CASE 
                WHEN LOWER(nombre) LIKE '%multiseguros%' THEN 1
                WHEN LOWER(nombre) LIKE '%midas%' THEN 2
                WHEN LOWER(nombre) LIKE '%patria%' THEN 3
                WHEN LOWER(nombre) LIKE '%pepin%' OR LOWER(nombre) LIKE '%pep%n%' THEN 4
                ELSE 999
              END ASC, nombre ASC";
    $res = $db->query($sql);
    $data = [];
    while ($row = $res->fetch_assoc()) $data[] = $row;
    respuestaJSON(true, 'OK', $data);
}

// =====================================================================
// GET: listar_tipos — Público para autenticados — SIN TASA (NOFTRAB R1)
// =====================================================================
if ($metodo === 'GET' && $action === 'listar_tipos') {
    $aseguradora_id = isset($_GET['aseguradora_id']) ? (int)$_GET['aseguradora_id'] : 0;

    if ($aseguradora_id > 0) {
        // Solo tipos de una aseguradora específica
        // IMPORTANTE: El SELECT omite deliberadamente el campo `tasa` — NOFTRAB R1
        $stmt = $db->prepare("
            SELECT ft.id, ft.tipo_fianza, fc.modo_calculo, fc.prima_minima,
                   COALESCE(ft.prima_minima_override, fc.prima_minima) AS prima_minima_efectiva
            FROM fianza_tarifarios ft
            INNER JOIN fianza_categorias fc ON ft.categoria_id = fc.id
            WHERE ft.aseguradora_id = ? AND ft.estado = 'activo'
            ORDER BY fc.nombre, ft.tipo_fianza
        ");
        $stmt->bind_param('i', $aseguradora_id);
    } else {
        // Todos los tipos activos (para la primera aseguradora)
        $stmt = $db->prepare("
            SELECT ft.id, ft.tipo_fianza, fc.modo_calculo, fc.prima_minima,
                   COALESCE(ft.prima_minima_override, fc.prima_minima) AS prima_minima_efectiva,
                   fa.nombre AS aseguradora_nombre
            FROM fianza_tarifarios ft
            INNER JOIN fianza_categorias fc ON ft.categoria_id = fc.id
            INNER JOIN fianza_aseguradoras fa ON ft.aseguradora_id = fa.id
            WHERE ft.estado = 'activo' AND fa.estado = 'activo'
            ORDER BY fa.nombre, fc.nombre, ft.tipo_fianza
        ");
    }

    $stmt->execute();
    $res  = $stmt->get_result();
    $data = [];
    while ($row = $res->fetch_assoc()) $data[] = $row;
    $stmt->close();

    respuestaJSON(true, 'OK', $data);
}

// =====================================================================
// GET: listar_admin — CON TASA — Requiere FIANZAS_ADMIN_TARIFARIO
// =====================================================================
if ($metodo === 'GET' && $action === 'listar_admin') {
    if (!tienePermiso($usuario_id, 'FIANZAS_ADMIN_TARIFARIO')) {
        respuestaJSON(false, 'Acceso denegado: se requiere permiso de Administración de Tarifarios', null, 403);
    }

    $sql = "
        SELECT ft.id, fa.nombre AS aseguradora, fc.nombre AS categoria,
               fc.modo_calculo, ft.tipo_fianza,
               ft.tasa,                          -- Solo visible con FIANZAS_ADMIN_TARIFARIO
               ROUND(ft.tasa * 100, 4) AS tasa_porcentaje,
               COALESCE(ft.prima_minima_override, fc.prima_minima) AS prima_minima,
               ft.estado, ft.fecha_mod,
               u.nombre AS modificado_por_nombre
        FROM fianza_tarifarios ft
        INNER JOIN fianza_aseguradoras fa ON ft.aseguradora_id = fa.id
        INNER JOIN fianza_categorias   fc ON ft.categoria_id   = fc.id
        LEFT  JOIN usuarios            u  ON ft.modificado_por = u.id
        ORDER BY fa.nombre, fc.nombre, ft.tipo_fianza
    ";

    $res  = $db->query($sql);
    $data = [];
    while ($row = $res->fetch_assoc()) $data[] = $row;

    logAudit($usuario_id, 'consultar', 'fianza_tarifarios', 'listar_admin', 'Consultó tarifarios con tasas', 'exitoso', null, 'fianza_tarifarios');
    respuestaJSON(true, 'OK', $data);
}

// =====================================================================
// POST: actualizar_tasa — Requiere FIANZAS_ADMIN_TARIFARIO — AUDITADO
// =====================================================================
if ($metodo === 'POST' && $action === 'actualizar_tasa') {
    if (!tienePermiso($usuario_id, 'FIANZAS_ADMIN_TARIFARIO')) {
        respuestaJSON(false, 'Acceso denegado: se requiere permiso de Administración de Tarifarios', null, 403);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $id_tarifario  = isset($input['id']) ? (int)$input['id'] : 0;
    $nueva_tasa_pct = isset($input['tasa_porcentaje']) ? (float)$input['tasa_porcentaje'] : (isset($input['tasa']) ? (float)$input['tasa'] : 0);
    $justificacion = trim($input['justificacion'] ?? '');

    if ($id_tarifario <= 0) respuestaJSON(false, 'ID de tarifario inválido', null, 400);
    if ($nueva_tasa_pct <= 0 || $nueva_tasa_pct > 100) respuestaJSON(false, 'Tasa inválida. Debe ser un porcentaje entre 0.01 y 100', null, 400);
    if (strlen($justificacion) < 15) respuestaJSON(false, 'La justificación del cambio debe tener al menos 15 caracteres (NOFTRAB R10)', null, 400);

    // Obtener valor anterior
    $stmt_prev = $db->prepare("SELECT tipo_fianza, tasa FROM fianza_tarifarios WHERE id = ? LIMIT 1");
    $stmt_prev->bind_param('i', $id_tarifario);
    $stmt_prev->execute();
    $prev = $stmt_prev->get_result()->fetch_assoc();
    $stmt_prev->close();

    if (!$prev) respuestaJSON(false, 'Tarifario no encontrado', null, 404);

    $nueva_tasa = round($nueva_tasa_pct / 100, 6);

    // Actualizar
    $stmt_upd = $db->prepare("UPDATE fianza_tarifarios SET tasa = ?, modificado_por = ? WHERE id = ?");
    $stmt_upd->bind_param('dii', $nueva_tasa, $usuario_id, $id_tarifario);
    $ok = $stmt_upd->execute();
    $stmt_upd->close();

    if (!$ok) respuestaJSON(false, 'Error al actualizar la tasa', null, 500);

    // Auditoría NOFTRAB R10 — inmutable
    registrarAjuste(
        $usuario_id,
        'fianza_tarifarios',
        'fianza_tarifarios',
        $id_tarifario,
        ['tipo_fianza' => $prev['tipo_fianza'], 'tasa' => $prev['tasa'], 'tasa_pct' => round($prev['tasa'] * 100, 4)],
        ['tipo_fianza' => $prev['tipo_fianza'], 'tasa' => $nueva_tasa, 'tasa_pct' => $nueva_tasa_pct],
        $justificacion
    );

    respuestaJSON(true, 'Tasa actualizada correctamente. Cambio registrado en historial de auditoría.');
}

// =====================================================================
// POST: toggle_estado — Activar/Desactivar tipo de fianza
// =====================================================================
if ($metodo === 'POST' && $action === 'toggle_estado') {
    if (!tienePermiso($usuario_id, 'FIANZAS_ADMIN_TARIFARIO')) {
        respuestaJSON(false, 'Acceso denegado', null, 403);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $id_tarifario = isset($input['id']) ? (int)$input['id'] : 0;
    if ($id_tarifario <= 0) respuestaJSON(false, 'ID inválido', null, 400);

    $stmt = $db->prepare("UPDATE fianza_tarifarios SET estado = IF(estado='activo','inactivo','activo'), modificado_por=? WHERE id=?");
    $stmt->bind_param('ii', $usuario_id, $id_tarifario);
    $ok = $stmt->execute();
    $stmt->close();

    respuestaJSON($ok, $ok ? 'Estado actualizado' : 'Error al actualizar');
}

respuestaJSON(false, "Acción '$action' no reconocida", null, 400);
?>
