<?php
/**
 * API: Auditoría Lineal y Historial de Documentos (NOFTRAB)
 * Endpoint: GET /api/auditoria_lineal.php?tipo_documento=X&documento_id=Y
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once dirname(__DIR__) . '/config.php';

session_start();

// Validar token de autorización si no hay sesión PHP activa
$bearer_token = null;
$auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? (function_exists('apache_request_headers') ? (apache_request_headers()['Authorization'] ?? '') : '');
if (preg_match('/Bearer\s+(.+)$/i', $auth_header, $matches)) {
    $bearer_token = trim($matches[1]);
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
    respuestaJSON(false, 'Sesión no válida o expirada', null, 401);
}

// Verificar permiso general de Auditoría
if ($usuario_id != 1 && !tienePermiso($usuario_id, 'AUDITORIA_LINEAL_VER') && !tienePermiso($usuario_id, 'CONF_TOTAL')) {
    respuestaJSON(false, 'Acceso denegado: Se requiere perfil con permisos de auditoría.', null, 403);
}

$buscar = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$filtro_modulo = isset($_GET['modulo']) ? trim($_GET['modulo']) : '';
$db = Database::getInstance()->getConnection();

try {
    $historial = [];

    if (!empty($buscar)) {
        // --- MODO BÚSQUEDA UNIVERSAL ---
        $client_ids = [];
        $user_ids = [];
        $cotizacion_ids = [];
        $poliza_ids = [];
        $fianza_ids = [];
        $pago_ids = [];

        $like_term = "%" . $buscar . "%";

        // 1. Clientes
        $stmt = $db->prepare("SELECT id FROM clientes WHERE cedula LIKE ? OR nombre LIKE ? OR apellido LIKE ? OR razon_social LIKE ?");
        $stmt->bind_param("ssss", $like_term, $like_term, $like_term, $like_term);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $client_ids[] = (int)$row['id'];
        $stmt->close();

        // 2. Usuarios
        $stmt = $db->prepare("SELECT id FROM usuarios WHERE cedula LIKE ? OR username LIKE ? OR nombre LIKE ? OR apellido LIKE ?");
        $stmt->bind_param("ssss", $like_term, $like_term, $like_term, $like_term);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $user_ids[] = (int)$row['id'];
        $stmt->close();

        // 3. Cotizaciones
        $stmt = $db->prepare("SELECT id, cliente_id FROM cotizaciones WHERE numero_cotizacion LIKE ? OR numero LIKE ? OR cedula LIKE ?");
        $stmt->bind_param("sss", $like_term, $like_term, $like_term);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $cotizacion_ids[] = (int)$row['id'];
            if ($row['cliente_id']) $client_ids[] = (int)$row['cliente_id'];
        }
        $stmt->close();

        // 4. Pólizas
        $stmt = $db->prepare("SELECT id, cotizacion_id, cliente_id FROM polizas WHERE numero_poliza LIKE ? OR numero_poliza_aseguradora LIKE ?");
        $stmt->bind_param("ss", $like_term, $like_term);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $poliza_ids[] = (int)$row['id'];
            if ($row['cotizacion_id']) $cotizacion_ids[] = (int)$row['cotizacion_id'];
            if ($row['cliente_id']) $client_ids[] = (int)$row['cliente_id'];
        }
        $stmt->close();

        // 5. Fianzas
        $stmt = $db->prepare("SELECT id, cotizacion_id FROM fianzas WHERE numero_fianza LIKE ?");
        $stmt->bind_param("s", $like_term);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $fianza_ids[] = (int)$row['id'];
            if ($row['cotizacion_id']) $cotizacion_ids[] = (int)$row['cotizacion_id'];
        }
        $stmt->close();

        // 6. Pagos
        $stmt = $db->prepare("SELECT id, poliza_id, cliente_id FROM pagos WHERE numero_ncf LIKE ? OR numero_recibo LIKE ? OR numero_referencia LIKE ? OR numero_comprobante LIKE ?");
        $stmt->bind_param("ssss", $like_term, $like_term, $like_term, $like_term);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $pago_ids[] = (int)$row['id'];
            if ($row['poliza_id']) $poliza_ids[] = (int)$row['poliza_id'];
            if ($row['cliente_id']) $client_ids[] = (int)$row['cliente_id'];
        }
        $stmt->close();

        // Relaciones cruzadas (recursivas)
        if (!empty($client_ids)) {
            $client_ids = array_unique($client_ids);
            $in_clients = implode(',', $client_ids);

            $res = $db->query("SELECT id FROM cotizaciones WHERE cliente_id IN ($in_clients)");
            while ($row = $res->fetch_assoc()) $cotizacion_ids[] = (int)$row['id'];

            $res = $db->query("SELECT id FROM polizas WHERE cliente_id IN ($in_clients)");
            while ($row = $res->fetch_assoc()) $poliza_ids[] = (int)$row['id'];

            $res = $db->query("SELECT id FROM pagos WHERE cliente_id IN ($in_clients)");
            while ($row = $res->fetch_assoc()) $pago_ids[] = (int)$row['id'];
        }

        if (!empty($cotizacion_ids)) {
            $cotizacion_ids = array_unique($cotizacion_ids);
            $in_cots = implode(',', $cotizacion_ids);

            $res = $db->query("SELECT id FROM polizas WHERE cotizacion_id IN ($in_cots)");
            while ($row = $res->fetch_assoc()) $poliza_ids[] = (int)$row['id'];

            $res = $db->query("SELECT id FROM fianzas WHERE cotizacion_id IN ($in_cots)");
            while ($row = $res->fetch_assoc()) $fianza_ids[] = (int)$row['id'];
        }

        if (!empty($poliza_ids)) {
            $poliza_ids = array_unique($poliza_ids);
            $in_pols = implode(',', $poliza_ids);

            $res = $db->query("SELECT id FROM pagos WHERE poliza_id IN ($in_pols)");
            while ($row = $res->fetch_assoc()) $pago_ids[] = (int)$row['id'];
        }

        $cotizacion_ids = array_unique($cotizacion_ids);
        $poliza_ids = array_unique($poliza_ids);
        $fianza_ids = array_unique($fianza_ids);
        $pago_ids = array_unique($pago_ids);
        $client_ids = array_unique($client_ids);
        $user_ids = array_unique($user_ids);

        // Armar filtros de módulos
        $modulo_active = !empty($filtro_modulo) ? [$filtro_modulo] : ['cotizaciones', 'polizas', 'fianzas', 'pagos', 'clientes', 'usuarios'];
        $where_clauses_audit = [];
        $where_clauses_ajustes = [];

        if (in_array('cotizaciones', $modulo_active) && !empty($cotizacion_ids)) {
            $where_clauses_audit[] = "(tabla_afectada = 'cotizaciones' AND registro_afectado_id IN (" . implode(',', $cotizacion_ids) . "))";
            $where_clauses_ajustes[] = "(tabla_afectada = 'cotizaciones' AND registro_id IN (" . implode(',', $cotizacion_ids) . "))";
        }
        if (in_array('polizas', $modulo_active) && !empty($poliza_ids)) {
            $where_clauses_audit[] = "(tabla_afectada = 'polizas' AND registro_afectado_id IN (" . implode(',', $poliza_ids) . "))";
            $where_clauses_ajustes[] = "(tabla_afectada = 'polizas' AND registro_id IN (" . implode(',', $poliza_ids) . "))";
        }
        if (in_array('fianzas', $modulo_active) && !empty($fianza_ids)) {
            $where_clauses_audit[] = "(tabla_afectada = 'fianzas' AND registro_afectado_id IN (" . implode(',', $fianza_ids) . "))";
            $where_clauses_ajustes[] = "(tabla_afectada = 'fianzas' AND registro_id IN (" . implode(',', $fianza_ids) . "))";
        }
        if (in_array('pagos', $modulo_active) && !empty($pago_ids)) {
            $where_clauses_audit[] = "(tabla_afectada = 'pagos' AND registro_afectado_id IN (" . implode(',', $pago_ids) . "))";
            $where_clauses_ajustes[] = "(tabla_afectada = 'pagos' AND registro_id IN (" . implode(',', $pago_ids) . "))";
        }
        if (in_array('clientes', $modulo_active) && !empty($client_ids)) {
            $where_clauses_audit[] = "(tabla_afectada = 'clientes' AND registro_afectado_id IN (" . implode(',', $client_ids) . "))";
            $where_clauses_ajustes[] = "(tabla_afectada = 'clientes' AND registro_id IN (" . implode(',', $client_ids) . "))";
        }
        if (in_array('usuarios', $modulo_active) && !empty($user_ids)) {
            $where_clauses_audit[] = "(tabla_afectada = 'usuarios' AND registro_afectado_id IN (" . implode(',', $user_ids) . "))";
            $where_clauses_ajustes[] = "(tabla_afectada = 'usuarios' AND registro_id IN (" . implode(',', $user_ids) . "))";
        }

        // Operaciones del usuario
        if (!empty($user_ids)) {
            $where_clauses_audit[] = "(usuario_id IN (" . implode(',', $user_ids) . "))";
            $where_clauses_ajustes[] = "(usuario_id IN (" . implode(',', $user_ids) . "))";
        }

        // Fallback por descripción
        $where_clauses_audit[] = "(descripcion_evento LIKE ?)";
        $where_clauses_ajustes[] = "(justificacion LIKE ?)";

        // Consultar auditoría
        $sql_audit_where = implode(" OR ", $where_clauses_audit);
        $sql_audit = "SELECT a.*, u.username, u.nombre, u.apellido
                      FROM auditoria_accesos a
                      LEFT JOIN usuarios u ON a.usuario_id = u.id
                      WHERE $sql_audit_where
                      ORDER BY a.fecha_evento ASC LIMIT 500";
        $stmt_audit = $db->prepare($sql_audit);
        $stmt_audit->bind_param("s", $like_term);
        $stmt_audit->execute();
        $res_audit = $stmt_audit->get_result();
        while ($row = $res_audit->fetch_assoc()) {
            $historial[] = [
                'origen' => 'auditoria',
                'id' => (int)$row['id'],
                'fecha' => $row['fecha_evento'],
                'usuario' => $row['nombre'] . ' ' . $row['apellido'] . ' (' . $row['username'] . ')',
                'tipo_evento' => $row['tipo_evento'],
                'funcion' => $row['funcion_ejecutada'],
                'descripcion' => $row['descripcion_evento'],
                'tabla_afectada' => $row['tabla_afectada'],
                'registro_id' => $row['registro_afectado_id'],
                'operacion' => $row['operacion_realizada'] ?? 'select',
                'valor_anterior' => json_decode($row['valor_anterior'], true),
                'valor_nuevo' => json_decode($row['valor_nuevo'], true),
                'ip' => $row['direccion_ip'],
                'user_agent' => $row['navegador_user_agent']
            ];
        }
        $stmt_audit->close();

        // Consultar ajustes
        $sql_ajustes_where = implode(" OR ", $where_clauses_ajustes);
        $sql_ajustes = "SELECT h.*, u.username, u.nombre, u.apellido
                        FROM historial_ajustes h
                        LEFT JOIN usuarios u ON h.usuario_id = u.id
                        WHERE $sql_ajustes_where
                        ORDER BY h.fecha_ajuste ASC LIMIT 500";
        $stmt_ajustes = $db->prepare($sql_ajustes);
        $stmt_ajustes->bind_param("s", $like_term);
        $stmt_ajustes->execute();
        $res_ajustes = $stmt_ajustes->get_result();
        while ($row = $res_ajustes->fetch_assoc()) {
            $historial[] = [
                'origen' => 'ajustes',
                'id' => (int)$row['id'],
                'fecha' => $row['fecha_ajuste'],
                'usuario' => $row['nombre'] . ' ' . $row['apellido'] . ' (' . $row['username'] . ')',
                'tipo_evento' => 'ajuste_transaccion',
                'funcion' => 'AJUSTE_DOCUMENTO',
                'descripcion' => $row['justificacion'],
                'tabla_afectada' => $row['tabla_afectada'],
                'registro_id' => $row['registro_id'],
                'operacion' => 'update',
                'valor_anterior' => json_decode($row['valor_anterior'], true),
                'valor_nuevo' => json_decode($row['valor_nuevo'], true),
                'ip' => $row['direccion_ip'],
                'user_agent' => 'N/D'
            ];
        }
        $stmt_ajustes->close();

    } else {
        // --- MODO CONSULTA ESPECÍFICA (Direct Lookup) ---
        $tipo_documento = $_GET['tipo_documento'] ?? '';
        $documento_id = isset($_GET['documento_id']) ? (int)$_GET['documento_id'] : 0;

        if (empty($tipo_documento) || $documento_id <= 0) {
            respuestaJSON(false, 'Parámetro "buscar" o ("tipo_documento" y "documento_id") son requeridos', null, 400);
        }

        $tabla_map = [
            'cotizaciones' => 'cotizaciones',
            'polizas' => 'polizas',
            'fianzas' => 'fianzas',
            'pagos' => 'pagos',
            'clientes' => 'clientes',
            'usuarios' => 'usuarios'
        ];

        if (!isset($tabla_map[$tipo_documento])) {
            respuestaJSON(false, 'Tipo de documento no válido. Soportados: cotizaciones, polizas, fianzas, pagos, clientes, usuarios', null, 400);
        }

        $tabla = $tabla_map[$tipo_documento];

        // 1. auditoria_accesos
        $sql_audit = "SELECT a.*, u.username, u.nombre, u.apellido
                      FROM auditoria_accesos a
                      LEFT JOIN usuarios u ON a.usuario_id = u.id
                      WHERE a.tabla_afectada = ? AND a.registro_afectado_id = ?
                      ORDER BY a.fecha_evento ASC";
                      
        $stmt_audit = $db->prepare($sql_audit);
        $stmt_audit->bind_param('si', $tabla, $documento_id);
        $stmt_audit->execute();
        $res_audit = $stmt_audit->get_result();
        
        while ($row = $res_audit->fetch_assoc()) {
            $historial[] = [
                'origen' => 'auditoria',
                'id' => (int)$row['id'],
                'fecha' => $row['fecha_evento'],
                'usuario' => $row['nombre'] . ' ' . $row['apellido'] . ' (' . $row['username'] . ')',
                'tipo_evento' => $row['tipo_evento'],
                'funcion' => $row['funcion_ejecutada'],
                'descripcion' => $row['descripcion_evento'],
                'tabla_afectada' => $row['tabla_afectada'],
                'registro_id' => $row['registro_afectado_id'],
                'operacion' => $row['operacion_realizada'] ?? 'select',
                'valor_anterior' => json_decode($row['valor_anterior'], true),
                'valor_nuevo' => json_decode($row['valor_nuevo'], true),
                'ip' => $row['direccion_ip'],
                'user_agent' => $row['navegador_user_agent']
            ];
        }
        $stmt_audit->close();

        // 2. historial_ajustes
        $sql_ajustes = "SELECT h.*, u.username, u.nombre, u.apellido
                        FROM historial_ajustes h
                        LEFT JOIN usuarios u ON h.usuario_id = u.id
                        WHERE h.tabla_afectada = ? AND h.registro_id = ?
                        ORDER BY h.fecha_ajuste ASC";
        
        $stmt_ajustes = $db->prepare($sql_ajustes);
        $stmt_ajustes->bind_param('si', $tabla, $documento_id);
        $stmt_ajustes->execute();
        $res_ajustes = $stmt_ajustes->get_result();
        
        while ($row = $res_ajustes->fetch_assoc()) {
            $historial[] = [
                'origen' => 'ajustes',
                'id' => (int)$row['id'],
                'fecha' => $row['fecha_ajuste'],
                'usuario' => $row['nombre'] . ' ' . $row['apellido'] . ' (' . $row['username'] . ')',
                'tipo_evento' => 'ajuste_transaccion',
                'funcion' => 'AJUSTE_DOCUMENTO',
                'descripcion' => $row['justificacion'],
                'tabla_afectada' => $row['tabla_afectada'],
                'registro_id' => $row['registro_id'],
                'operacion' => 'update',
                'valor_anterior' => json_decode($row['valor_anterior'], true),
                'valor_nuevo' => json_decode($row['valor_nuevo'], true),
                'ip' => $row['direccion_ip'],
                'user_agent' => 'N/D'
            ];
        }
        $stmt_ajustes->close();
    }

    // Ordenar cronológicamente (antiguo a nuevo)
    usort($historial, function($a, $b) {
        return strcmp($a['fecha'], $b['fecha']);
    });

    respuestaJSON(true, 'Historial de auditoría obtenido con éxito.', $historial, 200);

} catch (Exception $e) {
    respuestaJSON(false, 'Error al obtener auditoría: ' . $e->getMessage(), null, 500);
}
?>
