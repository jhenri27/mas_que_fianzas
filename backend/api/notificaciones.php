<?php
/**
 * API de Flujos de Notificaciones — v1.0
 * MAS QUE FIANZAS — Sistema Integrado
 * ============================================================
 * Router HTTP que gestiona el CRUD de flujos y consulta de logs.
 * El motor de envío está en NotificacionesEngine.php.
 *
 * Acciones:
 *   GET  ?action=listar           → Lista todos los flujos configurados
 *   GET  ?action=listar_logs      → Historial de envíos (audit NOFTRAB)
 *   GET  ?action=listar_eventos   → Catálogo de eventos disponibles
 *   POST ?action=guardar          → Crear o editar un flujo
 *   POST ?action=eliminar         → Eliminar un flujo
 *   POST ?action=toggle           → Activar/desactivar flujo
 *   POST ?action=disparar         → Test manual de un flujo
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once '../config.php';
require_once '../NotificacionesEngine.php';

// ─── Validación de sesión ──────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();

$bearer_token = null;
$auth_header  = $_SERVER['HTTP_AUTHORIZATION']
    ?? (function_exists('apache_request_headers') ? (apache_request_headers()['Authorization'] ?? '') : '');
if (preg_match('/Bearer\s+(.+)$/i', $auth_header, $m)) $bearer_token = trim($m[1]);
if (empty($bearer_token)) $bearer_token = $_GET['token_sesion'] ?? $_POST['token_sesion'] ?? null;

$usuario_id = null;
if (!empty($_SESSION['usuario_id'])) {
    $usuario_id = (int)$_SESSION['usuario_id'];
} elseif (!empty($bearer_token)) {
    $db_t = Database::getInstance()->getConnection();
    $st   = $db_t->prepare("SELECT usuario_id FROM sesiones_usuario WHERE token_sesion=? AND activa=1 AND fecha_expiracion>NOW() LIMIT 1");
    if ($st) { $st->bind_param('s', $bearer_token); $st->execute(); $r = $st->get_result(); if ($row = $r->fetch_assoc()) $usuario_id = (int)$row['usuario_id']; $st->close(); }
}
if (!$usuario_id) { respuestaJSON(false, 'Sesión no válida o expirada', null, 401); }

$db     = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Asegurar tablas
notif_crearTablas($db);

// ─── Catálogo de eventos del sistema ──────────────────────────────────────
$CATALOGO_EVENTOS = [
    ['evento' => 'COTIZACION_NUEVA',    'label' => 'Cotización nueva generada',            'modulo' => 'Cotizaciones',
     'variables' => ['NUMERO','CLIENTE','CEDULA','TELEFONO','EMAIL','TIPO','TIPO_LABEL','SUBTIPO','ASEGURADORA','TOTAL_FMT','PRIMA_FMT','PLAZO','FECHA_LOCAL','BENEFICIARIO']],
    ['evento' => 'POLIZA_EMITIDA',      'label' => 'Póliza emitida / activada',            'modulo' => 'Pólizas',
     'variables' => ['NUMERO','CLIENTE','ASEGURADORA','TOTAL','FECHA_INICIO','FECHA_FIN']],
    ['evento' => 'FIANZA_EMITIDA',      'label' => 'Fianza emitida',                       'modulo' => 'Fianzas',
     'variables' => ['NUMERO','CLIENTE','ASEGURADORA','MONTO_FMT','TOTAL_FMT','BENEFICIARIO','PLAZO']],
    ['evento' => 'PAGO_REGISTRADO',     'label' => 'Pago registrado en el sistema',        'modulo' => 'Pagos',
     'variables' => ['NUMERO','CLIENTE','MONTO','METODO_PAGO','FECHA']],
    ['evento' => 'REPORTE_GENERADO',    'label' => 'Reporte generado por usuario',         'modulo' => 'Reportes',
     'variables' => ['TIPO_REPORTE','FECHA','USUARIO']],
    ['evento' => 'AUDITORIA_ACCESO',    'label' => 'Evento de auditoría de acceso',        'modulo' => 'Auditoría',
     'variables' => ['EVENTO','USUARIO','MODULO','FECHA','IP']],
    ['evento' => 'SINIESTRO_ABIERTO',   'label' => 'Siniestro abierto / reportado',        'modulo' => 'Siniestros',
     'variables' => ['NUMERO','CLIENTE','ASEGURADORA','DESCRIPCION','FECHA']],
    ['evento' => 'SINIESTRO_RESUELTO',  'label' => 'Siniestro resuelto / cerrado',         'modulo' => 'Siniestros',
     'variables' => ['NUMERO','CLIENTE','RESULTADO','FECHA']],
];

// ─── Router ────────────────────────────────────────────────────────────────
try {

    // ── CATÁLOGO DE EVENTOS ────────────────────────────────────────────────
    if ($action === 'listar_eventos' && $method === 'GET') {
        respuestaJSON(true, count($CATALOGO_EVENTOS) . ' eventos disponibles', $CATALOGO_EVENTOS);

    // ── LISTAR FLUJOS ──────────────────────────────────────────────────────
    } elseif ($action === 'listar' && $method === 'GET') {
        $res = $db->query(
            "SELECT f.*, u.nombre AS creado_por_nombre
             FROM flujos_notificacion f
             LEFT JOIN usuarios u ON u.id = f.creado_por
             ORDER BY f.evento, f.id"
        );
        $flujos = [];
        while ($row = $res->fetch_assoc()) {
            $row['id']            = (int)$row['id'];
            $row['activo']        = (int)$row['activo'];
            $row['destinatarios'] = json_decode($row['destinatarios'], true) ?: [];
            $flujos[]             = $row;
        }
        respuestaJSON(true, count($flujos) . ' flujos encontrados', $flujos);

    // ── LISTAR LOGS ────────────────────────────────────────────────────────
    } elseif ($action === 'listar_logs' && $method === 'GET') {
        $limite  = (int)($_GET['limite'] ?? 200);
        $evento  = trim($_GET['evento'] ?? '');
        $ref     = trim($_GET['referencia'] ?? '');
        $flujo_id = (int)($_GET['flujo_id'] ?? 0);

        $where = []; $params = []; $types = '';
        if ($evento)   { $where[] = 'l.evento = ?';            $params[] = $evento;      $types .= 's'; }
        if ($ref)      { $where[] = 'l.referencia LIKE ?';     $params[] = "%$ref%";     $types .= 's'; }
        if ($flujo_id) { $where[] = 'l.flujo_id = ?';          $params[] = $flujo_id;    $types .= 'i'; }
        $whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT l.*, f.nombre AS flujo_nombre, u.nombre AS usuario_nombre
                FROM log_notificaciones l
                LEFT JOIN flujos_notificacion f ON f.id = l.flujo_id
                LEFT JOIN usuarios u ON u.id = l.disparado_por
                $whereStr ORDER BY l.created_at DESC LIMIT $limite";

        if ($params) {
            $st = $db->prepare($sql);
            $st->bind_param($types, ...$params);
            $st->execute();
            $result = $st->get_result(); $st->close();
        } else {
            $result = $db->query($sql);
        }
        $logs = [];
        while ($row = $result->fetch_assoc()) { $row['id'] = (int)$row['id']; $logs[] = $row; }
        respuestaJSON(true, count($logs) . ' registros de log', $logs);

    // ── GUARDAR FLUJO ──────────────────────────────────────────────────────
    } elseif ($action === 'guardar' && $method === 'POST') {
        if (!tienePermiso($usuario_id, 'CONF_NOTIF_EDITAR') && $usuario_id !== 1) {
            respuestaJSON(false, 'Sin permisos para gestionar flujos', null, 403);
        }
        $d = json_decode(file_get_contents('php://input'), true);
        if (empty($d['nombre']) || empty($d['evento']) || empty($d['asunto_tpl']) || empty($d['cuerpo_tpl'])) {
            respuestaJSON(false, 'Campos obligatorios: nombre, evento, asunto_tpl, cuerpo_tpl', null, 400);
        }
        if (empty($d['destinatarios']) || !is_array($d['destinatarios'])) {
            respuestaJSON(false, 'Debe incluir al menos un destinatario', null, 400);
        }

        $id           = !empty($d['id']) ? (int)$d['id'] : null;
        $nombre       = trim($d['nombre']);
        $evento       = strtoupper(trim($d['evento']));
        $descripcion  = trim($d['descripcion'] ?? '');
        $destinatarios= json_encode($d['destinatarios']);
        $asunto_tpl   = trim($d['asunto_tpl']);
        $cuerpo_tpl   = $d['cuerpo_tpl'];
        $activo       = isset($d['activo']) ? (int)(bool)$d['activo'] : 1;

        if ($id) {
            $st = $db->prepare("UPDATE flujos_notificacion SET nombre=?,evento=?,descripcion=?,destinatarios=?,asunto_tpl=?,cuerpo_tpl=?,activo=?,modificado_por=? WHERE id=?");
            $st->bind_param('sssssssii', $nombre, $evento, $descripcion, $destinatarios, $asunto_tpl, $cuerpo_tpl, $activo, $usuario_id, $id);
            $st->execute(); $st->close();
            logAudit($usuario_id,'editar_flujo_notificacion','Notificaciones','CONF_NOTIF_EDITAR',"Flujo '$nombre' ID:$id actualizado",'exitoso',null,'flujos_notificacion',$id,null,$d);
            respuestaJSON(true, 'Flujo actualizado correctamente', ['id' => $id]);
        } else {
            $st = $db->prepare("INSERT INTO flujos_notificacion (nombre,evento,descripcion,destinatarios,asunto_tpl,cuerpo_tpl,activo,creado_por) VALUES (?,?,?,?,?,?,?,?)");
            $st->bind_param('ssssssii', $nombre, $evento, $descripcion, $destinatarios, $asunto_tpl, $cuerpo_tpl, $activo, $usuario_id);
            $st->execute(); $new_id = $db->insert_id; $st->close();
            logAudit($usuario_id,'crear_flujo_notificacion','Notificaciones','CONF_NOTIF_EDITAR',"Nuevo flujo '$nombre' ID:$new_id",'exitoso',null,'flujos_notificacion',$new_id,null,$d);
            respuestaJSON(true, 'Flujo creado correctamente', ['id' => $new_id], 201);
        }

    // ── ELIMINAR FLUJO ─────────────────────────────────────────────────────
    } elseif ($action === 'eliminar' && $method === 'POST') {
        if (!tienePermiso($usuario_id, 'CONF_NOTIF_EDITAR') && $usuario_id !== 1) {
            respuestaJSON(false, 'Sin permisos', null, 403);
        }
        $d  = json_decode(file_get_contents('php://input'), true);
        $id = (int)($d['id'] ?? 0);
        if (!$id) respuestaJSON(false, 'ID de flujo requerido', null, 400);

        $db->query("DELETE FROM flujos_notificacion WHERE id=$id");
        logAudit($usuario_id,'eliminar_flujo_notificacion','Notificaciones','CONF_NOTIF_EDITAR',"Flujo ID:$id eliminado",'exitoso',null,'flujos_notificacion',$id,null,null);
        respuestaJSON(true, 'Flujo eliminado');

    // ── TOGGLE ─────────────────────────────────────────────────────────────
    } elseif ($action === 'toggle' && $method === 'POST') {
        if (!tienePermiso($usuario_id, 'CONF_NOTIF_EDITAR') && $usuario_id !== 1) {
            respuestaJSON(false, 'Sin permisos', null, 403);
        }
        $d  = json_decode(file_get_contents('php://input'), true);
        $id = (int)($d['id'] ?? 0);
        if (!$id) respuestaJSON(false, 'ID requerido', null, 400);

        $r = $db->query("SELECT activo, nombre FROM flujos_notificacion WHERE id=$id LIMIT 1")->fetch_assoc();
        if (!$r) respuestaJSON(false, 'Flujo no encontrado', null, 404);

        $nuevo = $r['activo'] ? 0 : 1;
        $db->query("UPDATE flujos_notificacion SET activo=$nuevo, modificado_por=$usuario_id WHERE id=$id");
        $lbl = $nuevo ? 'activado' : 'desactivado';
        respuestaJSON(true, "Flujo '{$r['nombre']}' $lbl", ['activo' => $nuevo]);

    // ── DISPARAR (test manual) ─────────────────────────────────────────────
    } elseif ($action === 'disparar' && $method === 'POST') {
        $d      = json_decode(file_get_contents('php://input'), true);
        $evento = strtoupper(trim($d['evento'] ?? ''));
        $ctx    = $d['contexto'] ?? $d;
        $ref    = $d['referencia'] ?? ($ctx['numero'] ?? 'TEST');

        if (!$evento) respuestaJSON(false, 'El campo evento es obligatorio', null, 400);

        $resultado = notif_disparar($db, $evento, $ctx, $ref, $usuario_id);
        respuestaJSON(true, "Flujos disparados: $evento", $resultado);

    } else {
        respuestaJSON(false, 'Use ?action=listar|listar_logs|listar_eventos|guardar|eliminar|toggle|disparar', null, 404);
    }

} catch (Exception $e) {
    error_log('NotificacionesAPI: ' . $e->getMessage());
    respuestaJSON(false, 'Error interno: ' . $e->getMessage(), null, 500);
}
?>
