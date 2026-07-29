<?php
/**
 * API Panel de Reportes Generales y Plan de Ventas - MAS QUE FIANZAS v1.0
 * Endpoint dedicado a los reportes de ventas, plan proyectado y margen comercial.
 */

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once '../config.php';
require_once '../Mailer.php';

// ─── Validación de sesión (doble vía: PHP session + Bearer token) ───────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$bearer_token = null;
$auth_header  = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
    ?? (function_exists('apache_request_headers') ? (apache_request_headers()['Authorization'] ?? apache_request_headers()['authorization'] ?? '') : '');

if (preg_match('/Bearer\s+(.+)$/i', $auth_header, $matches)) {
    $bearer_token = trim($matches[1]);
}
if (empty($bearer_token)) {
    $bearer_token = $_GET['token_sesion']
        ?? $_POST['token_sesion']
        ?? $_REQUEST['token']
        ?? $_REQUEST['token_sesion']
        ?? null;
}

$usuario_id = null;
if (isset($_SESSION['usuario_id']) && $_SESSION['usuario_id']) {
    $usuario_id = (int)$_SESSION['usuario_id'];
} elseif (!empty($bearer_token)) {
    $db_temp  = Database::getInstance()->getConnection();
    $stmt_tk  = $db_temp->prepare(
        "SELECT usuario_id FROM sesiones_usuario
         WHERE token_sesion = ? AND activa = 1 AND fecha_expiracion > NOW()
         LIMIT 1"
    );
    if ($stmt_tk) {
        $stmt_tk->bind_param("s", $bearer_token);
        $stmt_tk->execute();
        $res_tk = $stmt_tk->get_result();
        if ($row_tk = $res_tk->fetch_assoc()) {
            $usuario_id = (int)$row_tk['usuario_id'];
        }
        $stmt_tk->close();
    }
}

if (!$usuario_id) {
    http_response_code(401);
    echo json_encode(["exito" => false, "mensaje" => "Sesión no válida o expirada"]);
    exit;
}

$db = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

$mes  = isset($_GET['mes'])  ? (int)$_GET['mes']  : (int)date('n');
$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : (int)date('Y');

try {
    if ($method === 'GET') {

        switch ($action) {

            // ─── 1. OBTENER PLAN DE VENTAS ────────────────────────────────────
            case 'obtener_plan':
                // Requiere permiso de ver metas o de configurar metas
                if (!tienePermiso($usuario_id, 'TAB_POL_PROYECCION_VENTAS') && !tienePermiso($usuario_id, 'REP_VENTAS_LOGRADAS')) {
                    http_response_code(403);
                    echo json_encode(["exito" => false, "mensaje" => "Sin permisos para ver el plan de ventas"]);
                    exit;
                }

                // Cargar productos activos
                $res_p = $db->query("SELECT nombre_producto FROM productos WHERE estado = 'activo' ORDER BY nombre_producto");
                $active_products = [];
                while ($row = $res_p->fetch_assoc()) {
                    $active_products[] = $row['nombre_producto'];
                }
                if (empty($active_products)) {
                    $active_products = ['Seguro de Ley - Vehículo Liviano', 'Fianzas'];
                }

                // Cargar plan de la base de datos
                $stmt = $db->prepare("SELECT producto_nombre, cantidad_proyectada FROM plan_ventas_proyectado WHERE mes = ? AND anio = ?");
                $stmt->bind_param('ii', $mes, $anio);
                $stmt->execute();
                $res_plan = $stmt->get_result();
                $plan_map = [];
                while($row = $res_plan->fetch_assoc()) {
                    $plan_map[$row['producto_nombre']] = (int)$row['cantidad_proyectada'];
                }
                $stmt->close();

                // Unir productos activos con plan configurado (con fallbacks)
                $result_plan = [];
                foreach ($active_products as $pname) {
                    $target = 0;
                    if (isset($plan_map[$pname])) {
                        $target = $plan_map[$pname];
                    } else {
                        if ($pname === 'Seguro de Ley - Vehículo Liviano') $target = 20;
                        elseif ($pname === 'Fianzas') $target = 10;
                    }
                    $result_plan[] = [
                        'producto_nombre' => $pname,
                        'cantidad_proyectada' => $target
                    ];
                }

                echo json_encode(["exito" => true, "datos" => $result_plan]);
                break;

            // ─── 2. REPORTE DE VENTAS GENERALES ───────────────────────────────
            case 'reporte_ventas_generales':
                if (!tienePermiso($usuario_id, 'REP_VENTAS_GENERALES')) {
                    http_response_code(403);
                    echo json_encode(["exito" => false, "mensaje" => "Sin permiso para el reporte de Ventas Generales"]);
                    exit;
                }

                // Query de pólizas del mes con soporte de restricción granular Solo Propios (NOFTRAB)
                $solo_propios = restringirSoloPropios($usuario_id, 'reportes');
                $sql = "SELECT p.id, p.numero_poliza, p.tipo_seguro, p.aseguradora, p.prima_total, p.prima_neta, p.fecha_emision, p.estado, 
                               c.nombre as cliente_nombre,
                               COALESCE((SELECT SUM(pag.monto) FROM pagos pag WHERE pag.poliza_id = p.id AND pag.estado_pago = 'procesado'), 0) as total_pagado
                        FROM polizas p
                        LEFT JOIN clientes c ON p.cliente_id = c.id
                        WHERE MONTH(p.fecha_emision) = ? AND YEAR(p.fecha_emision) = ?";
                if ($solo_propios) {
                    $sql .= " AND p.emitida_por = ?";
                }
                $sql .= " ORDER BY p.fecha_emision DESC";

                $stmt = $db->prepare($sql);
                if ($solo_propios) {
                    $stmt->bind_param('iii', $mes, $anio, $usuario_id);
                } else {
                    $stmt->bind_param('ii', $mes, $anio);
                }
                $stmt->execute();
                $res = $stmt->get_result();

                $polizas = [];
                $kpis = [
                    'total_emitidas' => 0, 'monto_emitido' => 0.00,
                    'total_cobradas' => 0, 'monto_cobrado' => 0.00,
                    'total_pendientes' => 0, 'monto_pendiente' => 0.00
                ];

                while ($row = $res->fetch_assoc()) {
                    $prima_total = (float)$row['prima_total'];
                    $total_pagado = (float)$row['total_pagado'];
                    $fecha_emision = !empty($row['fecha_emision']) ? date('Y-m-d', strtotime($row['fecha_emision'])) : '';

                    // Determinar estado de cobro
                    if ($total_pagado >= $prima_total) {
                        $estado_cobro = 'cobrada';
                        $kpis['total_cobradas']++;
                        $kpis['monto_cobrado'] += $prima_total;
                    } elseif ($total_pagado > 0) {
                        $estado_cobro = 'parcial';
                        // Sumar lo efectivamente cobrado y lo pendiente
                        $kpis['monto_cobrado'] += $total_pagado;
                        $kpis['monto_pendiente'] += ($prima_total - $total_pagado);
                        $kpis['total_cobradas']++; // Se considera cobrada parcialmente en conteos o listados
                        $kpis['total_pendientes']++;
                    } else {
                        $estado_cobro = 'pendiente';
                        $kpis['total_pendientes']++;
                        $kpis['monto_pendiente'] += $prima_total;
                    }

                    $kpis['total_emitidas']++;
                    $kpis['monto_emitido'] += $prima_total;

                    $polizas[] = [
                        'id' => (int)$row['id'],
                        'numero_poliza' => $row['numero_poliza'],
                        'tipo_seguro' => $row['tipo_seguro'],
                        'aseguradora' => $row['aseguradora'],
                        'cliente' => $row['cliente_nombre'] ?? 'N/A',
                        'fecha_emision' => $fecha_emision,
                        'prima_total' => $prima_total,
                        'prima_neta' => (float)$row['prima_neta'],
                        'total_pagado' => $total_pagado,
                        'estado_cobro' => $estado_cobro,
                        'estado_poliza' => $row['estado']
                    ];
                }
                $stmt->close();

                // Cargar también las fianzas del mes (Integración Fianzas — NOFTRAB)
                $sql_fz = "SELECT f.id, f.numero_fianza, f.tipo_fianza, fa.nombre as aseguradora, f.total, f.prima_base, f.fecha_inicio, f.estado, 
                                  f.cliente_nombre
                           FROM fianzas f
                           INNER JOIN fianza_aseguradoras fa ON f.aseguradora_id = fa.id
                           WHERE MONTH(f.fecha_inicio) = ? AND YEAR(f.fecha_inicio) = ?";
                if ($solo_propios) {
                    $sql_fz .= " AND f.creado_por = ?";
                }
                $sql_fz .= " ORDER BY f.fecha_inicio DESC";

                $stmt_fz = $db->prepare($sql_fz);
                if ($solo_propios) {
                    $stmt_fz->bind_param('iii', $mes, $anio, $usuario_id);
                } else {
                    $stmt_fz->bind_param('ii', $mes, $anio);
                }
                $stmt_fz->execute();
                $res_fz = $stmt_fz->get_result();

                while ($row_fz = $res_fz->fetch_assoc()) {
                    $total = (float)$row_fz['total'];
                    // Para fianzas vigentes/emitidas asumimos cobro total
                    $total_pagado = ($row_fz['estado'] === 'vigente') ? $total : 0.00;
                    $fecha_emision = !empty($row_fz['fecha_inicio']) ? date('Y-m-d', strtotime($row_fz['fecha_inicio'])) : '';

                    if ($total_pagado >= $total) {
                        $estado_cobro = 'cobrada';
                        $kpis['total_cobradas']++;
                        $kpis['monto_cobrado'] += $total;
                    } else {
                        $estado_cobro = 'pendiente';
                        $kpis['total_pendientes']++;
                        $kpis['monto_pendiente'] += $total;
                    }

                    $kpis['total_emitidas']++;
                    $kpis['monto_emitido'] += $total;

                    $polizas[] = [
                        'id' => (int)$row_fz['id'],
                        'numero_poliza' => $row_fz['numero_fianza'],
                        'tipo_seguro' => 'Fianza: ' . $row_fz['tipo_fianza'],
                        'aseguradora' => $row_fz['aseguradora'],
                        'cliente' => $row_fz['cliente_nombre'],
                        'fecha_emision' => $fecha_emision,
                        'prima_total' => $total,
                        'prima_neta' => (float)$row_fz['prima_base'],
                        'total_pagado' => $total_pagado,
                        'estado_cobro' => $estado_cobro,
                        'estado_poliza' => $row_fz['estado']
                    ];
                }
                $stmt_fz->close();

                echo json_encode([
                    "exito" => true,
                    "datos" => [
                        "kpis" => $kpis,
                        "polizas" => $polizas
                    ]
                ]);
                break;

            // ─── 3. REPORTE DE VENTAS LOGRADAS ────────────────────────────────
            case 'reporte_ventas_logradas':
                if (!tienePermiso($usuario_id, 'REP_VENTAS_LOGRADAS')) {
                    http_response_code(403);
                    echo json_encode(["exito" => false, "mensaje" => "Sin permiso para el reporte de Ventas Logradas"]);
                    exit;
                }

                // Cargar productos
                $res_p = $db->query("SELECT nombre_producto FROM productos WHERE estado = 'activo' ORDER BY nombre_producto");
                $active_products = [];
                while ($row = $res_p->fetch_assoc()) {
                    $active_products[] = $row['nombre_producto'];
                }
                if (empty($active_products)) {
                    $active_products = ['Seguro de Ley - Vehículo Liviano', 'Fianzas'];
                }

                // Cargar plan proyectado
                $stmt = $db->prepare("SELECT producto_nombre, cantidad_proyectada FROM plan_ventas_proyectado WHERE mes = ? AND anio = ?");
                $stmt->bind_param('ii', $mes, $anio);
                $stmt->execute();
                $res_plan = $stmt->get_result();
                $plan_map = [];
                while($row = $res_plan->fetch_assoc()) {
                    $plan_map[$row['producto_nombre']] = (int)$row['cantidad_proyectada'];
                }
                $stmt->close();

                // Cargar ventas reales físicas (pólizas emitidas) con soporte de restricción granular Solo Propios (NOFTRAB)
                $solo_propios = restringirSoloPropios($usuario_id, 'reportes');
                $sql_real = "SELECT tipo_seguro, COUNT(*) as cantidad_real
                             FROM polizas
                             WHERE MONTH(fecha_emision) = ? AND YEAR(fecha_emision) = ?";
                if ($solo_propios) {
                    $sql_real .= " AND emitida_por = ?";
                }
                $sql_real .= " GROUP BY tipo_seguro";

                $stmt_real = $db->prepare($sql_real);
                if ($solo_propios) {
                    $stmt_real->bind_param('iii', $mes, $anio, $usuario_id);
                } else {
                    $stmt_real->bind_param('ii', $mes, $anio);
                }
                $stmt_real->execute();
                $res_real = $stmt_real->get_result();
                $real_map = [];
                while($row = $res_real->fetch_assoc()) {
                    $real_map[$row['tipo_seguro']] = (int)$row['cantidad_real'];
                }
                $stmt_real->close();

                // Contar también fianzas reales del mes (Integración Fianzas — NOFTRAB)
                $sql_fz_real = "SELECT COUNT(*) as cantidad_real FROM fianzas WHERE estado = 'vigente' AND MONTH(fecha_inicio) = ? AND YEAR(fecha_inicio) = ?";
                if ($solo_propios) {
                    $sql_fz_real .= " AND creado_por = ?";
                }
                $stmt_fz_real = $db->prepare($sql_fz_real);
                if ($solo_propios) {
                    $stmt_fz_real->bind_param('iii', $mes, $anio, $usuario_id);
                } else {
                    $stmt_fz_real->bind_param('ii', $mes, $anio);
                }
                $stmt_fz_real->execute();
                $qty_fz = (int)($stmt_fz_real->get_result()->fetch_assoc()['cantidad_real'] ?? 0);
                $stmt_fz_real->close();

                // Agregar al map de reales con clave 'Fianzas' para emparejar con el producto 'Fianzas'
                $real_map['Fianzas'] = $qty_fz;

                // Fusionar metas y realidades
                $logrados = [];
                $kpis = ['total_proyectado' => 0, 'total_real' => 0, 'cumplimiento_general' => 0];

                foreach ($active_products as $pname) {
                    $proyectado = 0;
                    if (isset($plan_map[$pname])) {
                        $proyectado = $plan_map[$pname];
                    } else {
                        if ($pname === 'Seguro de Ley - Vehículo Liviano') $proyectado = 20;
                        elseif ($pname === 'Fianzas') $proyectado = 10;
                    }

                    // Buscar real coincidente
                    $real = 0;
                    foreach ($real_map as $tseg => $qty) {
                        if (strpos(strtolower($tseg), strtolower($pname)) !== false || strpos(strtolower($pname), strtolower($tseg)) !== false) {
                            $real = $qty;
                            break;
                        }
                    }

                    $kpis['total_proyectado'] += $proyectado;
                    $kpis['total_real'] += $real;

                    $porcentaje = ($proyectado > 0) ? round(($real / $proyectado) * 100, 1) : ($real > 0 ? 100 : 0);

                    $logrados[] = [
                        'producto' => $pname,
                        'proyectado' => $proyectado,
                        'real' => $real,
                        'porcentaje' => $porcentaje
                    ];
                }

                $kpis['cumplimiento_general'] = ($kpis['total_proyectado'] > 0) ? round(($kpis['total_real'] / $kpis['total_proyectado']) * 100, 1) : 0;

                echo json_encode([
                    "exito" => true,
                    "datos" => [
                        "kpis" => $kpis,
                        "desglose" => $logrados
                    ]
                ]);
                break;

            // ─── 4. REPORTE DE MARGEN COMERCIAL ───────────────────────────────
            case 'reporte_margen_comercial':
                if (!tienePermiso($usuario_id, 'REP_MARGEN_COMERCIAL')) {
                    http_response_code(403);
                    echo json_encode(["exito" => false, "mensaje" => "Sin permiso para el reporte de Margen Comercial"]);
                    exit;
                }

                // Obtener pólizas emitidas en el periodo con soporte de restricción granular Solo Propios (NOFTRAB)
                $solo_propios = restringirSoloPropios($usuario_id, 'reportes');
                $sql = "SELECT p.id, p.numero_poliza, p.tipo_seguro, p.aseguradora, p.prima_total, p.prima_neta, p.fecha_emision,
                               c.nombre as cliente_nombre,
                               (SELECT SUM(pag.monto) FROM pagos pag WHERE pag.poliza_id = p.id AND pag.estado_pago = 'procesado') as total_pagado
                        FROM polizas p
                        LEFT JOIN clientes c ON p.cliente_id = c.id
                        WHERE MONTH(p.fecha_emision) = ? AND YEAR(p.fecha_emision) = ?";
                if ($solo_propios) {
                    $sql .= " AND p.emitida_por = ?";
                }
                $sql .= " HAVING COALESCE(total_pagado, 0) >= p.prima_total
                          ORDER BY p.fecha_emision DESC";

                $stmt = $db->prepare($sql);
                if ($solo_propios) {
                    $stmt->bind_param('iii', $mes, $anio, $usuario_id);
                } else {
                    $stmt->bind_param('ii', $mes, $anio);
                }
                $stmt->execute();
                $res = $stmt->get_result();

                $polizas = [];
                $kpis = [
                    'total_polizas' => 0,
                    'total_prima_bruta' => 0.00,
                    'total_prima_neta' => 0.00,
                    'total_margen' => 0.00,
                    'margen_promedio_porcentaje' => 0.00
                ];

                while ($row = $res->fetch_assoc()) {
                    $bruta = (float)$row['prima_total'];
                    $neta = (float)$row['prima_neta'];
                    $margen = $bruta - $neta;
                    $fecha_emision = !empty($row['fecha_emision']) ? date('Y-m-d', strtotime($row['fecha_emision'])) : '';

                    $kpis['total_polizas']++;
                    $kpis['total_prima_bruta'] += $bruta;
                    $kpis['total_prima_neta'] += $neta;
                    $kpis['total_margen'] += $margen;

                    $polizas[] = [
                        'id' => (int)$row['id'],
                        'numero_poliza' => $row['numero_poliza'],
                        'tipo_seguro' => $row['tipo_seguro'],
                        'aseguradora' => $row['aseguradora'],
                        'cliente' => $row['cliente_nombre'] ?? 'N/A',
                        'fecha_emision' => $fecha_emision,
                        'prima_bruta' => $bruta,
                        'prima_neta' => $neta,
                        'margen' => $margen,
                        'margen_porcentaje' => ($bruta > 0) ? round(($margen / $bruta) * 100, 1) : 0
                    ];
                }
                $stmt->close();

                // Obtener fianzas del periodo (vigentes) (Integración Fianzas — NOFTRAB)
                $sql_fz = "SELECT f.id, f.numero_fianza, f.tipo_fianza, fa.nombre as aseguradora, f.total, f.prima_base, f.itbis, f.fecha_inicio,
                                  f.cliente_nombre
                           FROM fianzas f
                           INNER JOIN fianza_aseguradoras fa ON f.aseguradora_id = fa.id
                           WHERE f.estado = 'vigente' AND MONTH(f.fecha_inicio) = ? AND YEAR(f.fecha_inicio) = ?";
                if ($solo_propios) {
                    $sql_fz .= " AND f.creado_por = ?";
                }
                $sql_fz .= " ORDER BY f.fecha_inicio DESC";

                $stmt_fz = $db->prepare($sql_fz);
                if ($solo_propios) {
                    $stmt_fz->bind_param('iii', $mes, $anio, $usuario_id);
                } else {
                    $stmt_fz->bind_param('ii', $mes, $anio);
                }
                $stmt_fz->execute();
                $res_fz = $stmt_fz->get_result();

                while ($row_fz = $res_fz->fetch_assoc()) {
                    $bruta = (float)$row_fz['total'];
                    $pbase = (float)$row_fz['prima_base'];
                    $itbis = (float)$row_fz['itbis'];

                    // Comisión del 15% es el margen real de MQF para fianzas
                    $margen = $pbase * 0.15;
                    $neta = $bruta - $margen; // Costo para MQF (lo que se le debe pagar a la aseguradora + itbis)
                    $fecha_emision = !empty($row_fz['fecha_inicio']) ? date('Y-m-d', strtotime($row_fz['fecha_inicio'])) : '';

                    $kpis['total_polizas']++;
                    $kpis['total_prima_bruta'] += $bruta;
                    $kpis['total_prima_neta'] += $neta;
                    $kpis['total_margen'] += $margen;

                    $polizas[] = [
                        'id' => (int)$row_fz['id'],
                        'numero_poliza' => $row_fz['numero_fianza'],
                        'tipo_seguro' => 'Fianza: ' . $row_fz['tipo_fianza'],
                        'aseguradora' => $row_fz['aseguradora'],
                        'cliente' => $row_fz['cliente_nombre'],
                        'fecha_emision' => $fecha_emision,
                        'prima_bruta' => $bruta,
                        'prima_neta' => $neta,
                        'margen' => $margen,
                        'margen_porcentaje' => ($bruta > 0) ? round(($margen / $bruta) * 100, 1) : 0
                    ];
                }
                $stmt_fz->close();

                $kpis['margen_promedio_porcentaje'] = ($kpis['total_prima_bruta'] > 0) ? round(($kpis['total_margen'] / $kpis['total_prima_bruta']) * 100, 1) : 0;

                echo json_encode([
                    "exito" => true,
                    "datos" => [
                        "kpis" => $kpis,
                        "polizas" => $polizas
                    ]
                ]);
                break;

            default:
                http_response_code(400);
                echo json_encode(["exito" => false, "mensaje" => "Acción GET no válida"]);
                break;
        }

    } elseif ($method === 'POST') {

        switch ($action) {

            // ─── 5. GUARDAR PLAN DE VENTAS ────────────────────────────────────
            case 'guardar_plan':
                if (!tienePermiso($usuario_id, 'TAB_POL_PROYECCION_VENTAS')) {
                    http_response_code(403);
                    echo json_encode(["exito" => false, "mensaje" => "Sin permisos para configurar el plan de ventas"]);
                    exit;
                }

                // Recibir datos JSON
                $raw = file_get_contents('php://input');
                $req = json_decode($raw, true);

                if (empty($req) || !is_array($req)) {
                    $req = $_POST['plan'] ?? [];
                }

                if (empty($req)) {
                    http_response_code(400);
                    echo json_encode(["exito" => false, "mensaje" => "Datos de plan no proporcionados"]);
                    exit;
                }

                // Iniciar transacción de seguridad
                $db->begin_transaction();

                $stmt = $db->prepare(
                    "INSERT INTO plan_ventas_proyectado (mes, anio, producto_nombre, cantidad_proyectada, creado_por)
                     VALUES (?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE cantidad_proyectada = VALUES(cantidad_proyectada)"
                );

                if (!$stmt) {
                    $db->rollback();
                    echo json_encode(["exito" => false, "mensaje" => "Error preparando consulta: " . $db->error]);
                    exit;
                }

                foreach ($req as $item) {
                    $pname = trim($item['producto_nombre'] ?? '');
                    $qty = (int)($item['cantidad_proyectada'] ?? 0);

                    if (empty($pname)) continue;

                    $stmt->bind_param('iisii', $mes, $anio, $pname, $qty, $usuario_id);
                    if (!$stmt->execute()) {
                        $db->rollback();
                        $stmt->close();
                        echo json_encode(["exito" => false, "mensaje" => "Error al guardar el plan de {$pname}: " . $stmt->error]);
                        exit;
                    }
                }
                $stmt->close();
                $db->commit();

                // Registrar en la auditoría inmutable
                logAudit(
                    $usuario_id, 
                    'editar_proyeccion', 
                    'polizas', 
                    'TAB_POL_PROYECCION_VENTAS', 
                    "Se actualizó la proyección de ventas de {$mes}/{$anio}.",
                    'exitoso',
                    null,
                    'plan_ventas_proyectado'
                );

                echo json_encode(["exito" => true, "mensaje" => "Plan de ventas guardado exitosamente"]);
                break;

            // ─── 6. ENVIAR REPORTE POR EMAIL ──────────────────────────────────
            case 'enviar_reporte':
                // Permitido si tiene acceso a cualquiera de los reportes
                if (!tienePermiso($usuario_id, 'REP_VENTAS_GENERALES') && !tienePermiso($usuario_id, 'REP_VENTAS_LOGRADAS') && !tienePermiso($usuario_id, 'REP_MARGEN_COMERCIAL')) {
                    http_response_code(403);
                    echo json_encode(["exito" => false, "mensaje" => "Sin permisos para enviar reportes"]);
                    exit;
                }

                $raw = file_get_contents('php://input');
                $req = json_decode($raw, true);

                if (empty($req)) {
                    $req = $_POST;
                }

                $email = trim($req['email'] ?? '');
                $html_reporte = $req['html_reporte'] ?? '';
                $titulo = trim($req['titulo_reporte'] ?? 'Reporte Corporativo');

                if (empty($email) || empty($html_reporte)) {
                    http_response_code(400);
                    echo json_encode(["exito" => false, "mensaje" => "Email o cuerpo del reporte faltante"]);
                    exit;
                }

                $mailer = new Mailer();
                $enviado = $mailer->enviar($email, $titulo, $html_reporte, true);

                if ($enviado) {
                    logAudit(
                        $usuario_id,
                        'enviar_email',
                        'reportes',
                        'REP_VENTAS_GENERALES',
                        "Reporte enviado por email a {$email}."
                    );
                    echo json_encode(["exito" => true, "mensaje" => "Reporte enviado exitosamente por correo electrónico"]);
                } else {
                    echo json_encode(["exito" => false, "mensaje" => "Error al enviar el correo. Verifique smtp.log para más detalles."]);
                }
                break;

            default:
                http_response_code(400);
                echo json_encode(["exito" => false, "mensaje" => "Acción POST no válida"]);
                break;
        }

    } else {
        http_response_code(405);
        echo json_encode(["exito" => false, "mensaje" => "Método no soportado"]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["exito" => false, "mensaje" => "Error interno de servidor: " . $e->getMessage()]);
}
?>
