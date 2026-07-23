<?php
/**
 * API: BOT-VISUAL-TEST-E2E - Controlador Backend para Pruebas E2E y Diagnóstico Multimodular
 * MAS QUE FIANZAS - Core InsurTech v4.0
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

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validar token de autorización si no hay sesión PHP activa
$bearer_token = null;
$auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? (function_exists('apache_request_headers') ? (apache_request_headers()['Authorization'] ?? '') : '');
if (preg_match('/Bearer\s+(.+)$/i', $auth_header, $matches)) {
    $bearer_token = trim($matches[1]);
}
if (empty($bearer_token)) {
    $bearer_token = $_GET['token_sesion'] ?? $_POST['token_sesion'] ?? $_REQUEST['token'] ?? null;
}

$usuario_id = null;
$db = null;
$db_conn_ok = false;

try {
    $db = Database::getInstance()->getConnection();
    $db_conn_ok = ($db && $db->connect_errno === 0);
} catch (Exception $e) {
    $db_conn_ok = false;
}

if (isset($_SESSION['usuario_id']) && $_SESSION['usuario_id']) {
    $usuario_id = (int)$_SESSION['usuario_id'];
} elseif (!empty($bearer_token) && $db_conn_ok && $db) {
    try {
        $stmt_tk = $db->prepare("SELECT usuario_id FROM sesiones_usuario WHERE token_sesion = ? AND activa = 1 AND fecha_expiracion > NOW() LIMIT 1");
        if ($stmt_tk) {
            $stmt_tk->bind_param("s", $bearer_token);
            $stmt_tk->execute();
            $res_tk = $stmt_tk->get_result();
            if ($row_tk = $res_tk->fetch_assoc()) $usuario_id = (int)$row_tk['usuario_id'];
            $stmt_tk->close();
        }
    } catch (Exception $ex) {
        // Ignorar
    }
}

if (php_sapi_name() === 'cli') {
    $usuario_id = 1;
}

if (!$usuario_id) {
    http_response_code(401);
    echo json_encode(["exito" => false, "mensaje" => "Sesión no válida o expirada. Inicie sesión para acceder a BOT-VISUAL-TEST-E2E."]);
    exit;
}

// Validar permiso RBAC `modulo_bot_visual_e2e` (Admin ID 1 tiene bypass)
if ($usuario_id != 1 && function_exists('tienePermiso') && !tienePermiso($usuario_id, 'modulo_bot_visual_e2e') && !tienePermiso($usuario_id, 'CONF_TOTAL')) {
    http_response_code(403);
    echo json_encode(["exito" => false, "mensaje" => "Acceso denegado: Su perfil no tiene asignado el permiso 'modulo_bot_visual_e2e'."]);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'get_scenarios';

if ($action === 'get_scenarios') {
    $scenarios = [
        "perfiles" => [
            ["codigo" => "admin", "nombre" => "👑 Administrador de Sistema (Full Access)"],
            ["codigo" => "pdv.prueba", "nombre" => "🏬 Socio PDV / Punto de Venta (pdv.prueba)"],
            ["codigo" => "cumplimiento", "nombre" => "🛡️ Oficial de Cumplimiento (NOFTRAB)"],
            ["codigo" => "operaciones", "nombre" => "💼 Operaciones y Emisión"],
            ["codigo" => "corredor", "nombre" => "📊 Corredor / Broker de Seguros"]
        ],
        "modulos" => [
            [
                "codigo" => "polizas",
                "nombre" => "📜 Módulo de Pólizas",
                "escenarios" => [
                    ["codigo" => "emision_individual", "nombre" => "Emisión de Póliza Individual (ISC 16% / ITBIS 0%)"],
                    ["codigo" => "endoso_cobertura", "nombre" => "Endoso de Cobertura con Recálculo Impositivo"],
                    ["codigo" => "cancelacion_masiva", "nombre" => "Cancelación Masiva con Justificación VAF"],
                    ["codigo" => "generacion_pdf", "nombre" => "Generación e Impresión de Carátula PDF"]
                ]
            ],
            [
                "codigo" => "fianzas",
                "nombre" => "🛡️ Módulo de Fianzas",
                "escenarios" => [
                    ["codigo" => "licitacion_1er_req", "nombre" => "Fianza de Licitación (1er Requerimiento)"],
                    ["codigo" => "emision_ncf_b02", "nombre" => "Emisión con Comprobante Fiscal NCF B02"],
                    ["codigo" => "cesion_derechos", "nombre" => "Declaración de Veracidad y Cesión de Información"]
                ]
            ],
            [
                "codigo" => "pagos_contabilidad",
                "nombre" => "💰 Pagos y Contabilidad",
                "escenarios" => [
                    ["codigo" => "recibo_ingreso", "nombre" => "Cobro de Prima y Recibo de Ingreso"],
                    ["codigo" => "asiento_partida_doble", "nombre" => "Asiento Contable Automático en Partida Doble"],
                    ["codigo" => "amortizacion_cuotas", "nombre" => "Tabla de Amortización y Financiamiento"]
                ]
            ],
            [
                "codigo" => "siniestros",
                "nombre" => "🚨 Módulo de Siniestros",
                "escenarios" => [
                    ["codigo" => "notificacion_reclamo", "nombre" => "Notificación de Reclamo y Reserva"],
                    ["codigo" => "ajustador_finiquito", "nombre" => "Inspección de Ajustador y Finiquito"]
                ]
            ],
            [
                "codigo" => "centro_negocios",
                "nombre" => "📊 Centro de Negocios",
                "escenarios" => [
                    ["codigo" => "liquidacion_comisiones", "nombre" => "Liquidación de Comisiones en Cascada"],
                    ["codigo" => "exportacion_dgii", "nombre" => "Generación de Archivos DGII (606, 607 e ISC)"]
                ]
            ],
            [
                "codigo" => "cumplimiento_vaf",
                "nombre" => "⚖️ Cumplimiento NOFTRAB / 4-VAF",
                "escenarios" => [
                    ["codigo" => "validar_luhn_mod10", "nombre" => "Algoritmo Luhn Mod 10 (Cédula Dominicana)"],
                    ["codigo" => "validar_rnc_mod11", "nombre" => "Algoritmo Mod 11 DGII (RNC)"],
                    ["codigo" => "unicidad_vin_chasis", "nombre" => "Enforzamiento Unicidad VIN / Chasis / Placa"]
                ]
            ]
        ]
    ];
    echo json_encode(["exito" => true, "datos" => $scenarios]);
    exit;
}

if ($action === 'run_test') {
    $raw_input = file_get_contents('php://input');
    $input = json_decode($raw_input, true) ?: $_POST;

    $perfil = escapeshellarg($input['perfil'] ?? 'pdv.prueba');
    $modulo = escapeshellarg($input['modulo'] ?? 'polizas');
    $escenario = escapeshellarg($input['escenario'] ?? 'emision_individual');
    $visible = ($input['visible'] ?? true) ? 'true' : 'false';

    $python_script = dirname(__DIR__) . '/tests/bot_visual_e2e/bot_visual_runner.py';
    $cmd = "python " . escapeshellarg($python_script) . " --perfil $perfil --modulo $modulo --escenario $escenario --visible $visible 2>&1";

    $output = [];
    $return_var = 0;
    exec($cmd, $output, $return_var);
    $output_str = implode("\n", $output);

    // Notificar al control remoto si es prueba visible en vivo
    if ($visible === 'true') {
        $cmd_remoto = 'NAVIGATE_DASHBOARD';
        $mod_clean = trim($input['modulo'] ?? '', "'");
        if ($mod_clean === 'polizas') $cmd_remoto = 'NAVIGATE_POLIZAS';
        elseif ($mod_clean === 'fianzas') $cmd_remoto = 'NAVIGATE_FIANZAS';
        elseif ($mod_clean === 'productos') $cmd_remoto = 'NAVIGATE_PRODUCTOS';

        @file_put_contents(
            dirname(__DIR__) . '/data/control_remoto.json',
            json_encode([
                "comando" => $cmd_remoto,
                "timestamp" => time(),
                "parametros" => ["perfil" => $input['perfil'] ?? '']
            ])
        );
    }

    // Extraer JSON_RESULT del output de Python
    $reporte_json = null;
    if (preg_match('/--- JSON_RESULT_START ---\s*(\{.*?\})\s*--- JSON_RESULT_END ---/s', $output_str, $matches)) {
        $reporte_json = json_decode($matches[1], true);
    }

    if ($reporte_json) {
        echo json_encode(["exito" => true, "reporte" => $reporte_json, "output_raw" => $output_str]);
    } else {
        echo json_encode([
            "exito" => false,
            "mensaje" => "Ejecución finalizada pero con salida raw no parseable",
            "output_raw" => $output_str
        ]);
    }
    exit;
}

echo json_encode(["exito" => false, "mensaje" => "Acción no reconocida"]);
?>
