<?php
/**
 * API Principal: Módulo de Fianzas
 * MAS QUE FIANZAS +QF, SRL — NOFTRAB v4
 *
 * REGLA R2 (NOFTRAB): El cálculo de prima ocurre en el servidor.
 * El endpoint `calcular` devuelve SOLO montos — nunca la tasa.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once '../config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Autenticación
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

// =====================================================================
// HELPER: Calcular prima internamente (la tasa NUNCA sale de aquí)
// =====================================================================
function calcularPrimaInterna($db, $tarifario_id, $monto_afianzado, $tasa_manual = null) {
    // Obtener tasa + prima mínima INTERNAMENTE — NOFTRAB R2
    $stmt = $db->prepare("
        SELECT ft.tasa,
               COALESCE(ft.prima_minima_override, fc.prima_minima) AS prima_minima,
               fc.modo_calculo
        FROM fianza_tarifarios ft
        INNER JOIN fianza_categorias fc ON ft.categoria_id = fc.id
        WHERE ft.id = ? AND ft.estado = 'activo'
        LIMIT 1
    ");
    $stmt->bind_param('i', $tarifario_id);
    $stmt->execute();
    $tar = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$tar) return null;

    // Determinar la tasa a utilizar (si se proporciona una manual, se usa esa; si no, la del tarifario)
    $tasa_a_usar = $tar['tasa'];
    if ($tasa_manual !== null && $tasa_manual > 0) {
        $tasa_a_usar = ($tasa_manual >= 1) ? ($tasa_manual / 100) : $tasa_manual;
    }

    $prima_calculada   = $monto_afianzado * $tasa_a_usar;
    $prima_minima      = (float)$tar['prima_minima'];
    $prima_minima_aplic = false;

    if ($prima_calculada < $prima_minima) {
        $prima_base        = $prima_minima;
        $prima_minima_aplic = true;
    } else {
        $prima_base = $prima_calculada;
    }

    $isc = round($prima_base * 0.16, 2);
    $total = round($prima_base + $isc, 2);

    return [
        'prima_base'            => round($prima_base, 2),
        'itbis'                 => $isc, // Stored under itbis column for DB compatibility
        'isc'                   => $isc, // Explicit key for frontend
        'total'                 => $total,
        'prima_minima_aplicada' => $prima_minima_aplic,
        'modo_calculo'          => $tar['modo_calculo']
        // tasa NO se incluye — NOFTRAB R1
    ];
}

// =====================================================================
// POST: calcular — Cálculo de prima en servidor — NOFTRAB R2
// =====================================================================
if ($metodo === 'POST' && $action === 'calcular') {
    if (!tienePermiso($usuario_id, 'FIANZAS_VER') && !tienePermiso($usuario_id, 'FIANZAS_CREAR')) {
        respuestaJSON(false, 'Acceso denegado', null, 403);
    }

    $input         = json_decode(file_get_contents('php://input'), true);
    $tarifario_id  = isset($input['tarifario_id']) ? (int)$input['tarifario_id'] : (isset($input['tipo_id']) ? (int)$input['tipo_id'] : 0);
    $monto_afianzado = 0;

    // Determinar monto a afianzar según modo
    if (!empty($input['monto_afianzado'])) {
        // Modo B: directo
        $monto_afianzado = (float)$input['monto_afianzado'];
    } elseif (!empty($input['valor_afianzado'])) {
        // Modo B: directo (alias del wizard)
        $monto_afianzado = (float)$input['valor_afianzado'];
    } elseif (!empty($input['monto_contrato']) && isset($input['porcentaje_afianzar'])) {
        // Modo A: contractual
        $monto_contrato      = (float)$input['monto_contrato'];
        $porcentaje_afianzar = (float)$input['porcentaje_afianzar'];
        $monto_afianzado     = $monto_contrato * ($porcentaje_afianzar / 100);
    }

    if ($tarifario_id <= 0) respuestaJSON(false, 'Tipo de fianza no seleccionado', null, 400);
    if ($monto_afianzado <= 0) respuestaJSON(false, 'Monto a afianzar inválido', null, 400);

    $tasa_manual = isset($input['tasa_manual']) && !empty($input['tasa_manual']) ? (float)$input['tasa_manual'] : null;
    $resultado = calcularPrimaInterna($db, $tarifario_id, $monto_afianzado, $tasa_manual);
    if (!$resultado) respuestaJSON(false, 'Tipo de fianza no encontrado o inactivo', null, 404);

    $resultado['monto_afianzado'] = round($monto_afianzado, 2);
    if (!empty($input['monto_contrato'])) $resultado['monto_contrato'] = round((float)$input['monto_contrato'], 2);
    if (isset($input['porcentaje_afianzar'])) $resultado['porcentaje_afianzar'] = (float)$input['porcentaje_afianzar'];

    respuestaJSON(true, 'Cálculo realizado', $resultado);
}

// =====================================================================
// POST: crear — Crear nueva fianza/cotización
// =====================================================================
if ($metodo === 'POST' && $action === 'crear') {
    if (!tienePermiso($usuario_id, 'FIANZAS_CREAR')) {
        respuestaJSON(false, 'Acceso denegado: se requiere permiso FIANZAS_CREAR', null, 403);
    }

    $input = json_decode(file_get_contents('php://input'), true);

    // Normalizar fallbacks de parámetros del frontend
    if (!isset($input['tarifario_id']) && isset($input['tipo_id'])) {
        $input['tarifario_id'] = $input['tipo_id'];
    }
    if (!isset($input['cliente_nombre']) && isset($input['cliente'])) {
        $input['cliente_nombre'] = $input['cliente'];
    }
    if (!isset($input['declaracion_veracidad'])) {
        $input['declaracion_veracidad'] = true;
    }
    if (!isset($input['declaracion_cesion'])) {
        $input['declaracion_cesion'] = true;
    }
    if (empty($input['monto_afianzado']) && !empty($input['valor_afianzado'])) {
        $input['monto_afianzado'] = $input['valor_afianzado'];
    }

    // Validaciones obligatorias
    $requeridos = ['tarifario_id', 'cliente_nombre', 'plazo_meses',
                   'declaracion_veracidad', 'declaracion_cesion'];
    foreach ($requeridos as $campo) {
        if (empty($input[$campo])) {
            respuestaJSON(false, "Campo requerido faltante: $campo", null, 400);
        }
    }

    if (!$input['declaracion_veracidad'] || !$input['declaracion_cesion']) {
        respuestaJSON(false, 'Debe aceptar ambas declaraciones para continuar', null, 400);
    }

    $tarifario_id = (int)$input['tarifario_id'];
    $monto_afianzado = 0;
    $monto_contrato  = null;
    $pct_afianzar    = null;

    if (!empty($input['monto_afianzado'])) {
        $monto_afianzado = (float)$input['monto_afianzado'];
    } elseif (!empty($input['monto_contrato']) && isset($input['porcentaje_afianzar'])) {
        $monto_contrato  = (float)$input['monto_contrato'];
        $pct_afianzar    = (float)$input['porcentaje_afianzar'];
        $monto_afianzado = $monto_contrato * ($pct_afianzar / 100);
    }

    if ($monto_afianzado <= 0) respuestaJSON(false, 'Monto a afianzar inválido', null, 400);

    // Obtener datos del tarifario para aseguradora_id y categoria_id
    $stmt_tar = $db->prepare("SELECT aseguradora_id, categoria_id, tipo_fianza FROM fianza_tarifarios WHERE id = ? AND estado = 'activo' LIMIT 1");
    $stmt_tar->bind_param('i', $tarifario_id);
    $stmt_tar->execute();
    $tar = $stmt_tar->get_result()->fetch_assoc();
    $stmt_tar->close();
    if (!$tar) respuestaJSON(false, 'Tipo de fianza no válido', null, 400);

    // Calcular prima en servidor (soporte de tasa manual opcional)
    $tasa_manual = isset($input['tasa_manual']) && !empty($input['tasa_manual']) ? (float)$input['tasa_manual'] : null;
    $calc = calcularPrimaInterna($db, $tarifario_id, $monto_afianzado, $tasa_manual);
    if (!$calc) respuestaJSON(false, 'Error al calcular prima', null, 500);

    // Generar número de fianza
    $anio    = date('Y');
    $stmt_num = $db->query("SELECT COUNT(*) AS cnt FROM fianzas WHERE YEAR(creado_en) = $anio");
    $cnt_row  = $stmt_num->fetch_assoc();
    $num_seq  = str_pad($cnt_row['cnt'] + 1, 5, '0', STR_PAD_LEFT);
    $numero_fianza = "FZ-$anio-$num_seq";

    // Calcular fecha de vencimiento
    $fecha_inicio = !empty($input['fecha_inicio']) ? $input['fecha_inicio'] : date('Y-m-d');
    $plazo_meses  = (int)$input['plazo_meses'];
    $fecha_venc   = date('Y-m-d', strtotime("$fecha_inicio +$plazo_meses months"));

    // NCF
    $ncf = null;
    if (!empty($input['generar_ncf'])) {
        $ncf = 'B02' . date('Y') . str_pad(rand(1, 99999999), 8, '0', STR_PAD_LEFT);
    }

    // Insertar fianza
    $sql = "INSERT INTO fianzas (
        numero_fianza, aseguradora_id, categoria_id, tipo_fianza,
        declaracion_veracidad, declaracion_cesion,
        cliente_nombre, cliente_cedula, cliente_telefono, cliente_email,
        objeto_referencia, beneficiario, primer_requerimiento,
        numero_contrato, monto_contrato, porcentaje_afianzar, monto_afianzado,
        plazo_meses, fecha_inicio, fecha_vencimiento,
        prima_base, itbis, total, prima_minima_aplicada, ncf,
        observaciones, estado, creado_por
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

    $stmt = $db->prepare($sql);
    $ase_id   = (int)$tar['aseguradora_id'];
    $cat_id   = (int)$tar['categoria_id'];
    $tipo     = $tar['tipo_fianza'];
    $decl_v   = 1;
    $decl_c   = 1;
    $nom      = trim($input['cliente_nombre']);
    $ced      = trim($input['cliente_cedula'] ?? '');
    $tel      = trim($input['cliente_telefono'] ?? '');
    $email    = trim($input['cliente_email'] ?? '');
    $objeto   = trim($input['objeto_referencia'] ?? '');
    $benef    = trim($input['beneficiario'] ?? '');
    $primer_r = isset($input['primer_requerimiento']) && $input['primer_requerimiento'] ? 1 : 0;
    $num_cont = trim($input['numero_contrato'] ?? '');
    $pbase    = $calc['prima_base'];
    $itbis    = $calc['itbis'];
    $total    = $calc['total'];
    $pmin     = $calc['prima_minima_aplicada'] ? 1 : 0;
    $obs      = trim($input['observaciones'] ?? '');
    $estado   = !empty($input['estado']) && in_array($input['estado'], ['cotizacion','pendiente','vigente','vencida','cancelada','renovada']) 
                ? $input['estado'] 
                : 'cotizacion';

    $stmt->bind_param('siisiissssssisdddissdddisssi',
        $numero_fianza, $ase_id, $cat_id, $tipo,
        $decl_v, $decl_c,
        $nom, $ced, $tel, $email,
        $objeto, $benef, $primer_r,
        $num_cont, $monto_contrato, $pct_afianzar, $monto_afianzado,
        $plazo_meses, $fecha_inicio, $fecha_venc,
        $pbase, $itbis, $total, $pmin, $ncf,
        $obs, $estado, $usuario_id
    );

    if (!$stmt->execute()) {
        error_log("Error crear fianza: " . $stmt->error);
        respuestaJSON(false, 'Error al guardar la fianza: ' . $stmt->error, null, 500);
    }

    $fianza_id = $stmt->insert_id;
    $stmt->close();

    logAudit($usuario_id, 'crear_fianza', 'fianzas', 'crear',
        "Fianza $numero_fianza creada para cliente: $nom", 'exitoso', null, 'fianzas', $fianza_id);

    // --- INTEGRACIÓN CENTRO FINANCIERO (Motor Contable) ---
    if ($estado === 'vigente') {
        try {
            require_once '../MotorContable.php';
            $comision = round((float)$pbase * 0.15, 2);
            $itbis_sobre_comision = round($comision * 0.18, 2);
            $payloadContable = [
                'id' => $fianza_id,
                'numero' => $numero_fianza,
                'modulo' => 'FIANZAS',
                'fecha' => $fecha_inicio,
                'total' => (float)$total,
                'comision' => $comision,
                'itbis' => $itbis_sobre_comision,
                'monto_neto' => (float)$total - $comision - $itbis_sobre_comision
            ];
            \MQF\Finance\MotorContable::disparar('EMISION_POLIZA', $payloadContable);
        } catch (\Exception $e) {
            error_log("Error Contable en Fianza Emisión: " . $e->getMessage());
        }
    }

    respuestaJSON(true, 'Fianza creada exitosamente', [
        'fianza_id'     => $fianza_id,
        'numero_fianza' => $numero_fianza,
        'prima_base'    => $pbase,
        'itbis'         => $itbis,
        'total'         => $total,
        'prima_minima_aplicada' => (bool)$pmin,
        'fecha_inicio'  => $fecha_inicio,
        'fecha_vencimiento' => $fecha_venc,
        'ncf'           => $ncf
    ]);
}

// =====================================================================
// GET: listar — Lista de fianzas con filtros
// =====================================================================
if ($metodo === 'GET' && $action === 'listar') {
    if (!tienePermiso($usuario_id, 'FIANZAS_VER')) {
        respuestaJSON(false, 'Acceso denegado', null, 403);
    }

    $estado        = $_GET['estado'] ?? '';
    $aseguradora_id = isset($_GET['aseguradora_id']) ? (int)$_GET['aseguradora_id'] : 0;
    $busqueda      = trim($_GET['busqueda'] ?? '');
    $limit         = min((int)($_GET['limit'] ?? 50), 200);
    $offset        = max((int)($_GET['offset'] ?? 0), 0);

    $where   = [];
    $params  = [];
    $types   = '';

    if (restringirSoloPropios($usuario_id, 'fianzas')) {
        $where[] = "f.creado_por = ?";
        $params[] = $usuario_id;
        $types .= 'i';
    }

    if ($estado) { $where[] = "f.estado = ?"; $params[] = $estado; $types .= 's'; }
    if ($aseguradora_id > 0) { $where[] = "f.aseguradora_id = ?"; $params[] = $aseguradora_id; $types .= 'i'; }
    if ($busqueda) {
        $like = "%$busqueda%";
        $where[] = "(f.numero_fianza LIKE ? OR f.cliente_nombre LIKE ? OR f.tipo_fianza LIKE ? OR f.beneficiario LIKE ?)";
        $params  = array_merge($params, [$like, $like, $like, $like]);
        $types  .= 'ssss';
    }

    $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // NOTA: Prima se muestra pero TASA nunca se incluye — NOFTRAB R1
    $sql = "
        SELECT f.id, f.numero_fianza, f.tipo_fianza, f.estado,
               f.aseguradora_id, fa.nombre AS aseguradora, fa.nombre AS aseguradora_nombre,
               f.categoria_id, fc.nombre AS categoria_nombre,
               f.cliente_nombre, f.cliente_email, f.cliente_telefono,
               f.monto_afianzado, f.plazo_meses,
               f.prima_base, f.itbis, f.total,
               f.prima_minima_aplicada, f.primer_requerimiento,
               f.fecha_inicio, f.fecha_vencimiento, f.ncf,
               f.beneficiario, f.objeto_referencia,
               f.creado_en, f.email_enviado
        FROM fianzas f
        INNER JOIN fianza_aseguradoras fa ON f.aseguradora_id = fa.id
        LEFT JOIN fianza_categorias fc ON f.categoria_id = fc.id
        $where_sql
        ORDER BY f.creado_en DESC
        LIMIT ? OFFSET ?
    ";

    $params[] = $limit; $types .= 'i';
    $params[] = $offset; $types .= 'i';

    $stmt = $db->prepare($sql);
    if ($types) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res  = $stmt->get_result();
    $data = [];
    while ($row = $res->fetch_assoc()) $data[] = $row;
    $stmt->close();

    // Total sin límite
    $sql_cnt = "SELECT COUNT(*) AS total FROM fianzas f $where_sql";
    $cnt_params = array_slice($params, 0, -2);
    $cnt_types  = substr($types, 0, -2);
    $stmt_cnt   = $db->prepare($sql_cnt);
    if ($cnt_types) $stmt_cnt->bind_param($cnt_types, ...$cnt_params);
    $stmt_cnt->execute();
    $total_rows = $stmt_cnt->get_result()->fetch_assoc()['total'];
    $stmt_cnt->close();

    respuestaJSON(true, 'OK', ['fianzas' => $data, 'total' => $total_rows, 'limit' => $limit, 'offset' => $offset]);
}

// =====================================================================
// POST: actualizar_estado — Cambiar estado del ciclo de vida
// =====================================================================
if ($metodo === 'POST' && $action === 'actualizar_estado') {
    if (!tienePermiso($usuario_id, 'FIANZAS_EDITAR')) {
        respuestaJSON(false, 'Acceso denegado', null, 403);
    }

    $input     = json_decode(file_get_contents('php://input'), true);
    $fianza_id = isset($input['fianza_id']) ? (int)$input['fianza_id'] : 0;
    $nuevo_est = trim($input['estado'] ?? '');
    $justif    = trim($input['justificacion'] ?? '');
    $estados_validos = ['pendiente','vigente','vencida','cancelada','renovada'];

    if ($fianza_id <= 0) respuestaJSON(false, 'ID de fianza inválido', null, 400);
    if (!in_array($nuevo_est, $estados_validos)) respuestaJSON(false, 'Estado no válido', null, 400);
    if (strlen($justif) < 9) respuestaJSON(false, 'La justificación debe tener al menos 9 caracteres', null, 400);

    if (restringirSoloPropios($usuario_id, 'fianzas')) {
        $stmt_prev = $db->prepare("SELECT estado, numero_fianza FROM fianzas WHERE id = ? AND creado_por = ? LIMIT 1");
        $stmt_prev->bind_param('ii', $fianza_id, $usuario_id);
    } else {
        $stmt_prev = $db->prepare("SELECT estado, numero_fianza FROM fianzas WHERE id = ? LIMIT 1");
        $stmt_prev->bind_param('i', $fianza_id);
    }
    $stmt_prev->execute();
    $prev = $stmt_prev->get_result()->fetch_assoc();
    $stmt_prev->close();

    if (!$prev) respuestaJSON(false, 'Fianza no encontrada', null, 404);

    if (restringirSoloPropios($usuario_id, 'fianzas')) {
        $stmt = $db->prepare("UPDATE fianzas SET estado = ? WHERE id = ? AND creado_por = ?");
        $stmt->bind_param('sii', $nuevo_est, $fianza_id, $usuario_id);
    } else {
        $stmt = $db->prepare("UPDATE fianzas SET estado = ? WHERE id = ?");
        $stmt->bind_param('si', $nuevo_est, $fianza_id);
    }
    $ok = $stmt->execute();
    $stmt->close();

    registrarAjuste($usuario_id, 'fianzas', 'fianzas', $fianza_id,
        ['estado' => $prev['estado']], ['estado' => $nuevo_est], $justif);

    // --- INTEGRACIÓN CENTRO FINANCIERO (Motor Contable en actualización de estado) ---
    if ($ok && $nuevo_est === 'vigente' && $prev['estado'] !== 'vigente') {
        try {
            require_once '../MotorContable.php';
            // Obtener el desglose de la fianza
            $stmt_f = $db->prepare("SELECT total, prima_base, itbis, fecha_inicio, numero_fianza FROM fianzas WHERE id = ? LIMIT 1");
            $stmt_f->bind_param('i', $fianza_id);
            $stmt_f->execute();
            $f_data = $stmt_f->get_result()->fetch_assoc();
            $stmt_f->close();

            if ($f_data) {
                $pbase = (float)$f_data['prima_base'];
                $comision = round($pbase * 0.15, 2);
                $itbis_sobre_comision = round($comision * 0.18, 2);
                $payloadContable = [
                    'id' => $fianza_id,
                    'numero' => $f_data['numero_fianza'],
                    'modulo' => 'FIANZAS',
                    'fecha' => $f_data['fecha_inicio'],
                    'total' => (float)$f_data['total'],
                    'comision' => $comision,
                    'itbis' => $itbis_sobre_comision,
                    'monto_neto' => (float)$f_data['total'] - $comision - $itbis_sobre_comision
                ];
                \MQF\Finance\MotorContable::disparar('EMISION_POLIZA', $payloadContable);
            }
        } catch (\Exception $e) {
            error_log("Error Contable en Fianza Actualizar Estado: " . $e->getMessage());
        }
    }

    respuestaJSON($ok, $ok ? "Estado actualizado a '$nuevo_est'" : 'Error al actualizar estado');
}

// =====================================================================
// GET: obtener — Detalle de una fianza (para PDF)
// =====================================================================
if ($metodo === 'GET' && $action === 'obtener') {
    if (!tienePermiso($usuario_id, 'FIANZAS_VER')) {
        respuestaJSON(false, 'Acceso denegado', null, 403);
    }

    $fianza_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($fianza_id <= 0) respuestaJSON(false, 'ID inválido', null, 400);

    if (restringirSoloPropios($usuario_id, 'fianzas')) {
        $stmt = $db->prepare("
            SELECT f.*, fa.nombre AS aseguradora_nombre, fa.rnc AS aseguradora_rnc
            FROM fianzas f
            INNER JOIN fianza_aseguradoras fa ON f.aseguradora_id = fa.id
            WHERE f.id = ? AND f.creado_por = ?
            LIMIT 1
        ");
        $stmt->bind_param('ii', $fianza_id, $usuario_id);
    } else {
        $stmt = $db->prepare("
            SELECT f.*, fa.nombre AS aseguradora_nombre, fa.rnc AS aseguradora_rnc
            FROM fianzas f
            INNER JOIN fianza_aseguradoras fa ON f.aseguradora_id = fa.id
            WHERE f.id = ?
            LIMIT 1
        ");
        $stmt->bind_param('i', $fianza_id);
    }
    $stmt->execute();
    $fianza = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$fianza) respuestaJSON(false, 'Fianza no encontrada', null, 404);

    // ELIMINAR cualquier campo que pueda revelar la tasa — NOFTRAB R1
    unset($fianza['tasa'], $fianza['porcentaje_interno']);

    respuestaJSON(true, 'OK', $fianza);
}

// =====================================================================
// GET: estadisticas — KPIs para Centro Financiero
// =====================================================================
if ($metodo === 'GET' && $action === 'estadisticas') {
    if (!tienePermiso($usuario_id, 'FIANZAS_VER')) {
        respuestaJSON(false, 'Acceso denegado', null, 403);
    }

    $mes_actual = date('Y-m');
    $where_sql = "WHERE estado != 'cancelada'";
    $where_ase = "WHERE f.estado != 'cancelada'";
    $params = [];
    $types = '';

    if (restringirSoloPropios($usuario_id, 'fianzas')) {
        $where_sql .= " AND creado_por = ?";
        $where_ase .= " AND f.creado_por = ?";
        $params[] = $usuario_id;
        $types .= 'i';
    }

    $sql = "
        SELECT
          COUNT(*) AS total_fianzas,
          SUM(CASE WHEN estado = 'vigente' THEN 1 ELSE 0 END) AS fianzas_vigentes,
          SUM(CASE WHEN estado = 'vencida' THEN 1 ELSE 0 END) AS fianzas_vencidas,
          SUM(CASE WHEN estado = 'cotizacion' THEN 1 ELSE 0 END) AS cotizaciones_pendientes,
          SUM(prima_base) AS prima_total,
          SUM(itbis) AS itbis_total,
          SUM(total) AS ingreso_total,
          SUM(CASE WHEN DATE_FORMAT(creado_en,'%Y-%m') = '$mes_actual' THEN total ELSE 0 END) AS ingreso_mes_actual,
          SUM(CASE WHEN DATE_FORMAT(creado_en,'%Y-%m') = '$mes_actual' THEN 1 ELSE 0 END) AS fianzas_mes_actual
        FROM fianzas
        $where_sql
    ";

    $stmt_est = $db->prepare($sql);
    if ($types) $stmt_est->bind_param($types, ...$params);
    $stmt_est->execute();
    $data = $stmt_est->get_result()->fetch_assoc();
    $stmt_est->close();

    // Por aseguradora
    $sql_ase = "
        SELECT fa.nombre AS aseguradora,
               COUNT(f.id) AS cantidad,
               SUM(f.total) AS ingreso_total
        FROM fianzas f
        INNER JOIN fianza_aseguradoras fa ON f.aseguradora_id = fa.id
        $where_ase
        GROUP BY fa.id, fa.nombre
        ORDER BY ingreso_total DESC
    ";

    $stmt_ase = $db->prepare($sql_ase);
    if ($types) $stmt_ase->bind_param($types, ...$params);
    $stmt_ase->execute();
    $res_ase = $stmt_ase->get_result();
    $por_aseguradora = [];
    while ($row = $res_ase->fetch_assoc()) $por_aseguradora[] = $row;
    $stmt_ase->close();

    respuestaJSON(true, 'OK', array_merge($data, ['por_aseguradora' => $por_aseguradora]));
}

// =====================================================================
// POST: actualizar — Actualizar datos de una fianza/cotización existente
// NOFTRAB R3: solo campos editables; la tasa NUNCA se expone
// =====================================================================
if ($metodo === 'POST' && $action === 'actualizar') {
    if (!tienePermiso($usuario_id, 'FIANZAS_EDITAR')) {
        respuestaJSON(false, 'Acceso denegado: se requiere permiso FIANZAS_EDITAR', null, 403);
    }

    $input     = json_decode(file_get_contents('php://input'), true);
    $fianza_id = isset($input['id']) ? (int)$input['id'] : 0;
    if ($fianza_id <= 0) respuestaJSON(false, 'ID de fianza requerido para actualizar', null, 400);

    // Obtener estado anterior para auditoría y lógica contable
    if (restringirSoloPropios($usuario_id, 'fianzas')) {
        $stmt_prev = $db->prepare("SELECT estado, numero_fianza, prima_base, itbis, total, fecha_inicio, aseguradora_id, categoria_id FROM fianzas WHERE id = ? AND creado_por = ? LIMIT 1");
        $stmt_prev->bind_param('ii', $fianza_id, $usuario_id);
    } else {
        $stmt_prev = $db->prepare("SELECT estado, numero_fianza, prima_base, itbis, total, fecha_inicio, aseguradora_id, categoria_id FROM fianzas WHERE id = ? LIMIT 1");
        $stmt_prev->bind_param('i', $fianza_id);
    }
    $stmt_prev->execute();
    $prev = $stmt_prev->get_result()->fetch_assoc();
    $stmt_prev->close();
    if (!$prev) respuestaJSON(false, 'Fianza no encontrada', null, 404);

    // Campos editables del formulario
    $nom      = trim($input['cliente'] ?? $input['cliente_nombre'] ?? $prev['cliente_nombre'] ?? '');
    $ced      = trim($input['cedula']  ?? $input['cliente_cedula']  ?? $prev['cliente_cedula']  ?? '');
    $tel      = trim($input['telefono'] ?? $input['cliente_telefono'] ?? $prev['cliente_telefono'] ?? '');
    $email    = trim($input['email']    ?? $input['cliente_email']    ?? $prev['cliente_email']    ?? '');
    $objeto   = trim($input['objeto_referencia'] ?? $prev['objeto_referencia'] ?? '');
    $benef    = trim($input['beneficiario'] ?? $prev['beneficiario'] ?? '');
    $num_cont = trim($input['num_contrato'] ?? $input['numero_contrato'] ?? $prev['numero_contrato'] ?? '');
    $obs      = trim($input['observaciones'] ?? $prev['observaciones'] ?? '');

    // Monto y plazo (pueden disparar recálculo)
    $monto_afianzado = isset($input['monto_afianzado']) && $input['monto_afianzado'] > 0
        ? (float)$input['monto_afianzado']
        : (float)($prev['monto_afianzado'] ?? 0);

    $plazo_meses = isset($input['plazo_meses']) && $input['plazo_meses'] > 0
        ? (int)$input['plazo_meses']
        : (int)($prev['plazo_meses'] ?? 12);

    $fecha_inicio = !empty($input['fecha_inicio']) ? $input['fecha_inicio'] : ($prev['fecha_inicio'] ?? date('Y-m-d'));
    $fecha_venc   = date('Y-m-d', strtotime("$fecha_inicio +$plazo_meses months"));

    // Estado (solo cotizacion/pendiente/vigente/vencida/cancelada/renovada)
    $estados_validos = ['cotizacion','pendiente','vigente','vencida','cancelada','renovada'];
    $nuevo_estado = isset($input['estado']) && in_array($input['estado'], $estados_validos)
        ? $input['estado']
        : $prev['estado'];

    // Recalcular prima si hay tarifario_id o se proporcionó tasa_manual
    $pbase = (float)$prev['prima_base'];
    $itbis = (float)$prev['itbis'];
    $total = (float)$prev['total'];

    $tarifario_id = isset($input['tarifario_id']) ? (int)$input['tarifario_id']
                  : (isset($input['tipo_id'])     ? (int)$input['tipo_id'] : 0);
    $tasa_manual  = isset($input['tasa_manual']) && !empty($input['tasa_manual'])
        ? (float)$input['tasa_manual'] : null;

    if ($tarifario_id > 0 || $tasa_manual !== null) {
        // Para tasa manual sin tarifario, buscar el tarifario de la fianza actual
        if ($tarifario_id <= 0) {
            $stmt_tar_cat = $db->prepare("SELECT ft.id FROM fianza_tarifarios ft WHERE ft.categoria_id = ? AND ft.aseguradora_id = ? AND ft.estado='activo' LIMIT 1");
            $stmt_tar_cat->bind_param('ii', $prev['categoria_id'], $prev['aseguradora_id']);
            $stmt_tar_cat->execute();
            $row_tar = $stmt_tar_cat->get_result()->fetch_assoc();
            $stmt_tar_cat->close();
            if ($row_tar) $tarifario_id = (int)$row_tar['id'];
        }
        if ($tarifario_id > 0) {
            $calc = calcularPrimaInterna($db, $tarifario_id, $monto_afianzado, $tasa_manual);
            if ($calc) {
                $pbase = $calc['prima_base'];
                $itbis = $calc['itbis'];
                $total = $calc['total'];
            }
        }
    }

    // Actualizar registro
    if (restringirSoloPropios($usuario_id, 'fianzas')) {
        $sql_upd = "UPDATE fianzas SET
            cliente_nombre      = ?,
            cliente_cedula      = ?,
            cliente_telefono    = ?,
            cliente_email       = ?,
            objeto_referencia   = ?,
            beneficiario        = ?,
            numero_contrato     = ?,
            observaciones       = ?,
            monto_afianzado     = ?,
            plazo_meses         = ?,
            fecha_inicio        = ?,
            fecha_vencimiento   = ?,
            prima_base          = ?,
            itbis               = ?,
            total               = ?,
            estado              = ?,
            modificado_en       = NOW()
            WHERE id = ? AND creado_por = ?";

        $stmt_upd = $db->prepare($sql_upd);
        $stmt_upd->bind_param('ssssssssdissdddsii',
            $nom, $ced, $tel, $email,
            $objeto, $benef, $num_cont, $obs,
            $monto_afianzado, $plazo_meses,
            $fecha_inicio, $fecha_venc,
            $pbase, $itbis, $total,
            $nuevo_estado,
            $fianza_id,
            $usuario_id
        );
    } else {
        $sql_upd = "UPDATE fianzas SET
            cliente_nombre      = ?,
            cliente_cedula      = ?,
            cliente_telefono    = ?,
            cliente_email       = ?,
            objeto_referencia   = ?,
            beneficiario        = ?,
            numero_contrato     = ?,
            observaciones       = ?,
            monto_afianzado     = ?,
            plazo_meses         = ?,
            fecha_inicio        = ?,
            fecha_vencimiento   = ?,
            prima_base          = ?,
            itbis               = ?,
            total               = ?,
            estado              = ?,
            modificado_en       = NOW()
            WHERE id = ?";

        $stmt_upd = $db->prepare($sql_upd);
        $stmt_upd->bind_param('ssssssssdissdddsi',
            $nom, $ced, $tel, $email,
            $objeto, $benef, $num_cont, $obs,
            $monto_afianzado, $plazo_meses,
            $fecha_inicio, $fecha_venc,
            $pbase, $itbis, $total,
            $nuevo_estado,
            $fianza_id
        );
    }

    if (!$stmt_upd->execute()) {
        error_log("Error actualizar fianza: " . $stmt_upd->error);
        respuestaJSON(false, 'Error al actualizar la fianza: ' . $stmt_upd->error, null, 500);
    }
    $stmt_upd->close();

    logAudit($usuario_id, 'actualizar_fianza', 'fianzas', 'editar',
        "Fianza {$prev['numero_fianza']} actualizada por usuario $usuario_id", 'exitoso', null, 'fianzas', $fianza_id);

    // --- INTEGRACIÓN CENTRO FINANCIERO: solo si cambia a 'vigente' por primera vez ---
    if ($nuevo_estado === 'vigente' && $prev['estado'] !== 'vigente') {
        try {
            require_once '../MotorContable.php';
            $comision = round($pbase * 0.15, 2);
            $itbis_sobre_comision = round($comision * 0.18, 2);
            $payloadContable = [
                'id'         => $fianza_id,
                'numero'     => $prev['numero_fianza'],
                'modulo'     => 'FIANZAS',
                'fecha'      => $fecha_inicio,
                'total'      => $total,
                'comision'   => $comision,
                'itbis'      => $itbis_sobre_comision,
                'monto_neto' => $total - $comision - $itbis_sobre_comision
            ];
            \MQF\Finance\MotorContable::disparar('EMISION_POLIZA', $payloadContable);
        } catch (\Exception $e) {
            error_log("Error Contable en Fianza Actualizar: " . $e->getMessage());
        }
    }

    respuestaJSON(true, 'Fianza actualizada correctamente', [
        'fianza_id'         => $fianza_id,
        'numero_fianza'     => $prev['numero_fianza'],
        'prima_base'        => $pbase,
        'itbis'             => $itbis,
        'total'             => $total,
        'fecha_vencimiento' => $fecha_venc,
        'estado'            => $nuevo_estado
    ]);
}

respuestaJSON(false, "Acción '$action' no reconocida", null, 400);
?>
