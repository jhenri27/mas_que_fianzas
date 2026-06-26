<?php
/**
 * API de Gestión de Cotizaciones
 * MAS QUE FIANZAS - Sistema Integrado
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200); exit;
}

require_once '../config.php';

// Validar sesión: aceptar PHP session O Bearer token del header Authorization
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
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

$usuario_actual = $usuario_id;
$metodo = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Auto-crear tabla si no existe y agregar columnas faltantes (idempotente)
function crearTablaIfNeeded($db) {
    $sql = "CREATE TABLE IF NOT EXISTS `cotizaciones` (
        `id`                   INT AUTO_INCREMENT PRIMARY KEY,
        `numero`               VARCHAR(40)   NOT NULL,
        `tipo`                 VARCHAR(30)   NOT NULL,
        `subtipo`              VARCHAR(100)  DEFAULT NULL,
        `cliente`              VARCHAR(200)  DEFAULT NULL,
        `cedula`               VARCHAR(30)   DEFAULT NULL,
        `telefono`             VARCHAR(30)   DEFAULT NULL,
        `email`                VARCHAR(120)  DEFAULT NULL,
        `beneficiario`         VARCHAR(255)  DEFAULT NULL,
        `uso`                  VARCHAR(60)   DEFAULT NULL,
        `capacidad`            VARCHAR(100)  DEFAULT NULL,
        `aseguradora`          VARCHAR(100)  DEFAULT NULL,
        `cobertura`            VARCHAR(60)   DEFAULT NULL,
        `monto_afianzado`      DECIMAL(15,2) DEFAULT 0,
        `plazo`                INT           DEFAULT NULL,
        `prima_base`           DECIMAL(15,2) DEFAULT 0,
        `impuesto`             DECIMAL(15,2) DEFAULT 0,
        `total`                DECIMAL(15,2) DEFAULT 0,
        `servicios_opcionales` TEXT          DEFAULT NULL,
        `fecha`                DATETIME      DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uk_numero` (`numero`),
        INDEX `idx_fecha` (`fecha`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $db->query($sql);
    // Migración idempotente: agregar columnas si no existen (tabla ya creada anteriormente)
    $cols = [];
    $r = $db->query("SHOW COLUMNS FROM cotizaciones");
    while ($row = $r->fetch_assoc()) $cols[] = $row['Field'];
    if (!in_array('telefono', $cols)) $db->query("ALTER TABLE cotizaciones ADD COLUMN telefono VARCHAR(30) DEFAULT NULL AFTER cedula");
    if (!in_array('email',    $cols)) $db->query("ALTER TABLE cotizaciones ADD COLUMN email    VARCHAR(120) DEFAULT NULL AFTER telefono");
}

function insertar_cotizacion($db, $c, $usuario_actual = 1) {
    $numero      = $c['numero'];
    $tipo        = $c['tipo'] ?? 'GENERAL';
    $subtipo     = $c['subtipo'] ?? '';
    $cliente     = $c['cliente'] ?? '';
    $cedula      = $c['cedula'] ?? '';
    $telefono    = $c['telefono'] ?? '';
    $email       = $c['email']    ?? '';
    $beneficiario= $c['beneficiario'] ?? '';
    $uso         = $c['uso'] ?? '';
    $capacidad   = $c['capacidad'] ?? '';
    $aseguradora = $c['aseguradora'] ?? '';
    $cobertura   = $c['cobertura'] ?? '';
    $monto       = floatval($c['monto_afianzado'] ?? 0);
    $plazo       = intval($c['plazo'] ?? 0);
    $prima_base  = floatval($c['prima_base'] ?? 0);
    $impuesto    = floatval($c['impuesto'] ?? 0);
    $total       = floatval($c['total'] ?? 0);
    $servicios   = isset($c['servicios_opcionales']) ? (is_array($c['servicios_opcionales']) ? json_encode($c['servicios_opcionales']) : $c['servicios_opcionales']) : '';
    $fecha_raw   = $c['fecha'] ?? date('Y-m-d H:i:s');
    $fecha = date('Y-m-d H:i:s', strtotime($fecha_raw));
    $tasa_manual = (isset($c['tasa_manual']) && !empty($c['tasa_manual'])) ? floatval($c['tasa_manual']) : null;

    $stmt = $db->prepare(
        "INSERT INTO cotizaciones
         (numero, tipo, subtipo, cliente, cedula, telefono, email, beneficiario, uso, capacidad, aseguradora, cobertura,
          monto_afianzado, plazo, prima_base, impuesto, total, servicios_opcionales, fecha, creado_por, tasa_manual)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE
         tipo=VALUES(tipo), cliente=VALUES(cliente), cedula=VALUES(cedula),
         telefono=VALUES(telefono), email=VALUES(email),
         beneficiario=VALUES(beneficiario), aseguradora=VALUES(aseguradora),
         subtipo=VALUES(subtipo), total=VALUES(total), fecha=VALUES(fecha), tasa_manual=VALUES(tasa_manual)"
    );
    $stmt->bind_param('ssssssssssssdidddssid',
        $numero, $tipo, $subtipo, $cliente, $cedula, $telefono, $email, $beneficiario,
        $uso, $capacidad, $aseguradora, $cobertura,
        $monto, $plazo, $prima_base, $impuesto, $total,
        $servicios, $fecha, $usuario_actual, $tasa_manual
    );
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

try {
    $db = Database::getInstance()->getConnection();
    crearTablaIfNeeded($db);

    // LISTAR
    if ($action === 'listar' && $metodo === 'GET') {
        $limite = intval($_GET['limite'] ?? 200);
        $numero = $_GET['numero'] ?? '';
        
        $soloPropios = restringirSoloPropios($usuario_actual, 'Cotizaciones');
        
        if (!empty($numero)) {
            if ($soloPropios) {
                $stmt = $db->prepare("SELECT * FROM cotizaciones WHERE numero = ? AND creado_por = ? LIMIT 1");
                $stmt->bind_param('si', $numero, $usuario_actual);
            } else {
                $stmt = $db->prepare("SELECT * FROM cotizaciones WHERE numero = ? LIMIT 1");
                $stmt->bind_param('s', $numero);
            }
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            if ($soloPropios) {
                $stmt = $db->prepare("SELECT * FROM cotizaciones WHERE creado_por = ? ORDER BY fecha DESC LIMIT ?");
                $stmt->bind_param('ii', $usuario_actual, $limite);
                $stmt->execute();
                $result = $stmt->get_result();
            } else {
                $result = $db->query("SELECT * FROM cotizaciones ORDER BY fecha DESC LIMIT $limite");
            }
        }
        
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            if (!empty($row['servicios_opcionales'])) {
                $dec = json_decode($row['servicios_opcionales'], true);
                if (is_array($dec)) $row['servicios_opcionales'] = $dec;
            }
            $rows[] = $row;
        }
        respuestaJSON(true, count($rows) . ' cotizaciones encontradas', $rows, 200);

    // GUARDAR (una sola)
    } elseif ($action === 'guardar' && $metodo === 'POST') {
        $datos = json_decode(file_get_contents('php://input'), true);
        if (empty($datos['numero']) || empty($datos['tipo'])) {
            respuestaJSON(false, 'Numero y tipo son obligatorios', null, 400);
        }
        
        // Validación técnica Dominicana (NOFTRAB)
        if (ValidadorDocumentos::isValidatorActive('cotizaciones')) {
            if (!empty($datos['cedula']) && !ValidadorDocumentos::validarDocumento($datos['cedula'])) {
                respuestaJSON(false, 'El RNC o Cédula especificado no es válido (dígito verificador incorrecto).', null, 400);
            }
            if (!empty($datos['telefono']) && !ValidadorDocumentos::validarTelefono($datos['telefono'])) {
                respuestaJSON(false, 'El teléfono especificado no es válido (debe tener 10 dígitos y código de área 809, 829 o 849).', null, 400);
            }
        }
        
        if (insertar_cotizacion($db, $datos, $usuario_actual)) {
            // Auditoría (NOFTRAB)
            $cot_db_id = null;
            $res_id = $db->query("SELECT id FROM cotizaciones WHERE numero = '" . $db->real_escape_string($datos['numero']) . "'");
            if ($res_id && $r_id = $res_id->fetch_assoc()) {
                $cot_db_id = (int)$r_id['id'];
            }
            if (function_exists('logAudit')) {
                logAudit(
                    $usuario_actual,
                    'guardar_cotizacion',
                    'cotizaciones',
                    'guardar',
                    "Cotización guardada (creada o actualizada por clave duplicada). Número: {$datos['numero']}",
                    'exitoso',
                    null,
                    'cotizaciones',
                    $cot_db_id,
                    null,
                    $datos
                );
            }
            // --- MOTOR DE NOTIFICACIONES AUTOMÁTICAS (NOFTRAB) ---
            try {
                require_once '../NotificacionesEngine.php';
                $total_fmt  = 'RD$ ' . number_format(floatval($datos['total'] ?? 0), 2, '.', ',');
                $prima_fmt  = 'RD$ ' . number_format(floatval($datos['prima_base'] ?? 0), 2, '.', ',');
                $monto_fmt  = 'RD$ ' . number_format(floatval($datos['monto_afianzado'] ?? 0), 2, '.', ',');
                $tipo_label = ($datos['tipo'] === 'FIANZA') ? 'Fianza' : 'Seguro de Ley';
                $ctx_notif  = array_merge($datos, [
                    'creado_por'  => $usuario_actual,
                    'fecha_local' => date('d/m/Y H:i', strtotime($datos['fecha'] ?? 'now')),
                    'monto_fmt'   => $monto_fmt,
                    'total_fmt'   => $total_fmt,
                    'prima_fmt'   => $prima_fmt,
                    'tipo_label'  => $tipo_label,
                    // Aliases en MAYÚSCULAS para variables del template {{VAR}}
                    'NUMERO'      => $datos['numero'],
                    'CLIENTE'     => $datos['cliente']     ?? '',
                    'CEDULA'      => $datos['cedula']      ?? '',
                    'EMAIL'       => $datos['email']       ?? '',
                    'TELEFONO'    => $datos['telefono']    ?? '',
                    'TIPO'        => $datos['tipo']        ?? '',
                    'TIPO_LABEL'  => $tipo_label,
                    'SUBTIPO'     => $datos['subtipo']     ?? '',
                    'ASEGURADORA' => $datos['aseguradora'] ?? '',
                    'TOTAL_FMT'   => $total_fmt,
                    'PRIMA_FMT'   => $prima_fmt,
                    'MONTO_FMT'   => $monto_fmt,
                    'PLAZO'       => $datos['plazo']       ?? '0',
                    'FECHA_LOCAL' => date('d/m/Y H:i', strtotime($datos['fecha'] ?? 'now')),
                    'BENEFICIARIO'=> $datos['beneficiario'] ?? '',
                    'COBERTURA'   => $datos['cobertura']   ?? '',
                    'USO'         => $datos['uso']         ?? '',
                    'CAPACIDAD'   => $datos['capacidad']   ?? '',
                ]);
                notif_disparar($db, 'COTIZACION_NUEVA', $ctx_notif, $datos['numero'], $usuario_actual);
            } catch (\Exception $eNotif) {
                error_log('Notificación COTIZACION_NUEVA fallida: ' . $eNotif->getMessage());
            }


            // --- INTEGRACIÓN CENTRO FINANCIERO (HOOKS) ---
            try {
                require_once '../MotorContable.php';
                require_once '../NCFManager.php';

                // 1. Generar NCF (opcional)
                $usarNCF = isset($datos['usar_ncf']) ? (bool)$datos['usar_ncf'] : false;
                $ncfMgr = new \MQF\Finance\NCFManager($db);
                $ncf = $ncfMgr->generarSiguiente('B02', $usarNCF);

                // 2. Disparar Asiento Automático
                $payloadContable = array_merge($datos, [
                    'modulo' => 'COTIZACIONES',
                    'id' => $db->insert_id,
                    'ncf' => $ncf,
                    'comision' => $datos['total'] * 0.10,
                    'itbis' => ($datos['total'] * 0.10) * 0.18,
                    'monto_neto' => $datos['total'] - ($datos['total'] * 0.10)
                ]);

                \MQF\Finance\MotorContable::disparar('EMISION_POLIZA', $payloadContable);

                respuestaJSON(true, 'Cotizacion guardada y procesada contablemente', [
                    'numero' => $datos['numero'],
                    'ncf' => $ncf
                ], 201);

            } catch (\Exception $e) {
                error_log("Error Contable en Cotizacion: " . $e->getMessage());
                respuestaJSON(true, 'Cotizacion guardada (Contabilidad pendiente)', ['numero' => $datos['numero']], 201);
            }
        } else {
            respuestaJSON(false, 'Error al guardar cotización', null, 500);
        }

    // ACTUALIZAR (por ID)
    } elseif ($action === 'actualizar' && $metodo === 'POST') {
        $datos = json_decode(file_get_contents('php://input'), true);
        if (empty($datos['id'])) {
            respuestaJSON(false, 'ID de cotización requerido para actualizar', null, 400);
        }

        // Validación técnica Dominicana (NOFTRAB)
        if (ValidadorDocumentos::isValidatorActive('cotizaciones')) {
            if (!empty($datos['cedula']) && !ValidadorDocumentos::validarDocumento($datos['cedula'])) {
                respuestaJSON(false, 'El RNC o Cédula especificado no es válido (dígito verificador incorrecto).', null, 400);
            }
            if (!empty($datos['telefono']) && !ValidadorDocumentos::validarTelefono($datos['telefono'])) {
                respuestaJSON(false, 'El teléfono especificado no es válido (debe tener 10 dígitos y código de área 809, 829 o 849).', null, 400);
            }
        }

        $id = intval($datos['id']);
        // Obtener valor anterior para auditoría
        $val_anterior = null;
        $stmt_prev = $db->prepare("SELECT * FROM cotizaciones WHERE id = ?");
        if ($stmt_prev) {
            $stmt_prev->bind_param("i", $id);
            $stmt_prev->execute();
            $val_anterior = $stmt_prev->get_result()->fetch_assoc();
            $stmt_prev->close();
        }

        $sql = "UPDATE cotizaciones SET 
                tipo = ?, subtipo = ?, cliente = ?, cedula = ?, telefono = ?, email = ?,
                beneficiario = ?, uso = ?, 
                capacidad = ?, aseguradora = ?, cobertura = ?, 
                monto_afianzado = ?, plazo = ?, prima_base = ?, 
                impuesto = ?, total = ?, servicios_opcionales = ?,
                tasa_manual = ?
                WHERE id = ?";
        
        $stmt = $db->prepare($sql);
        $servicios = isset($datos['servicios_opcionales']) ? 
                    (is_array($datos['servicios_opcionales']) ? json_encode($datos['servicios_opcionales']) : $datos['servicios_opcionales']) 
                    : '';
        $cobertura = isset($datos['cobertura']) ? 
                    (is_array($datos['cobertura']) ? json_encode($datos['cobertura']) : $datos['cobertura']) 
                    : ($datos['cobertura'] ?? '');
        $beneficiario = $datos['beneficiario'] ?? '';

        $tipo     = $datos['tipo'] ?? 'GENERAL';
        $subtipo  = $datos['subtipo'] ?? '';
        $cliente  = $datos['cliente'] ?? '';
        $cedula   = $datos['cedula']   ?? '';
        $telefono = $datos['telefono'] ?? '';
        $email    = $datos['email']    ?? '';
        $uso      = $datos['uso']      ?? '';
        $capacidad   = $datos['capacidad']  ?? '';
        $aseguradora = $datos['aseguradora'] ?? '';
        $monto_afianzado = floatval($datos['monto_afianzado'] ?? 0);
        $plazo       = intval($datos['plazo'] ?? 0);
        $prima_base  = floatval($datos['prima_base'] ?? 0);
        $impuesto    = floatval($datos['impuesto'] ?? 0);
        $total       = floatval($datos['total'] ?? 0);
        $tasa_manual = (isset($datos['tasa_manual']) && !empty($datos['tasa_manual'])) ? floatval($datos['tasa_manual']) : null;

        $stmt->bind_param('sssssssssssdidddsdi',
            $tipo, $subtipo, $cliente, $cedula, $telefono, $email, $beneficiario, $uso,
            $capacidad, $aseguradora, $cobertura,
            $monto_afianzado, $plazo, $prima_base,
            $impuesto, $total, $servicios, $tasa_manual, $id
        );

        if ($stmt->execute()) {
            if (function_exists('logAudit')) {
                logAudit(
                    $usuario_actual,
                    'actualizar_cotizacion',
                    'cotizaciones',
                    'actualizar',
                    "Cotización ID $id actualizada",
                    'exitoso',
                    null,
                    'cotizaciones',
                    $id,
                    $val_anterior,
                    $datos
                );
            }
            respuestaJSON(true, 'Cotizacion actualizada correctamente', ['id' => $id], 200);
        } else {
            respuestaJSON(false, 'Error al actualizar cotización: ' . $db->error, null, 500);
        }

    // IMPORTAR MASIVO (desde localStorage via JSON)
    } elseif ($action === 'importar' && $metodo === 'POST') {
        $datos = json_decode(file_get_contents('php://input'), true);
        if (empty($datos) || !is_array($datos)) {
            respuestaJSON(false, 'Formato invalido: se espera array JSON', null, 400);
        }
        $ok = 0;
        foreach ($datos as $c) {
            if (empty($c['numero'])) continue;
            if (insertar_cotizacion($db, $c, $usuario_actual)) {
                $ok++;
                $cot_id = $db->insert_id;
                if (function_exists('logAudit')) {
                    logAudit(
                        $usuario_actual,
                        'guardar_cotizacion',
                        'cotizaciones',
                        'importar',
                        "Cotización importada masivamente. Número: {$c['numero']}",
                        'exitoso',
                        null,
                        'cotizaciones',
                        $cot_id,
                        null,
                        $c
                    );
                }
            }
        }
        respuestaJSON(true, "$ok cotizaciones importadas a la base de datos", ['insertadas' => $ok], 201);

    // OBTENER (por ID)
    } elseif ($action === 'obtener' && $metodo === 'GET') {
        $id = intval($_GET['id'] ?? 0);
        if ($id <= 0) {
            respuestaJSON(false, 'ID de cotización no válido', null, 400);
        }
        
        $soloPropios = restringirSoloPropios($usuario_actual, 'Cotizaciones');
        if ($soloPropios) {
            $stmt = $db->prepare("SELECT * FROM cotizaciones WHERE id = ? AND creado_por = ? LIMIT 1");
            $stmt->bind_param('ii', $id, $usuario_actual);
        } else {
            $stmt = $db->prepare("SELECT * FROM cotizaciones WHERE id = ? LIMIT 1");
            $stmt->bind_param('i', $id);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $cot = $res->fetch_assoc();
        $stmt->close();
        
        if (!$cot) {
            respuestaJSON(false, 'Cotización no encontrada', null, 404);
        }
        
        if (!empty($cot['servicios_opcionales'])) {
            $dec = json_decode($cot['servicios_opcionales'], true);
            if (is_array($dec)) $cot['servicios_opcionales'] = $dec;
        }
        
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            "exito" => true,
            "mensaje" => "Cotización obtenida con éxito",
            "dato" => $cot
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;

    // ELIMINAR (una o varias)
    } elseif ($action === 'eliminar' && $metodo === 'POST') {
        $datos = json_decode(file_get_contents('php://input'), true);
        if (empty($datos['ids']) && empty($datos['id'])) {
            respuestaJSON(false, 'Se requiere id o lista de ids para eliminar', null, 400);
        }
        
        $ids = [];
        if (!empty($datos['id'])) $ids[] = intval($datos['id']);
        if (!empty($datos['ids']) && is_array($datos['ids'])) {
            foreach ($datos['ids'] as $id) $ids[] = intval($id);
        }
        
        if (empty($ids)) {
            respuestaJSON(false, 'No se proporcionaron IDs válidos', null, 400);
        }
        
        $id_list = implode(',', $ids);
        
        // Obtener datos de las cotizaciones antes de eliminar para auditoría
        $val_anterior = [];
        $res_prev = $db->query("SELECT id, numero, total FROM cotizaciones WHERE id IN ($id_list)");
        if ($res_prev) {
            while ($row = $res_prev->fetch_assoc()) {
                $val_anterior[] = $row;
            }
        }

        $sql = "DELETE FROM cotizaciones WHERE id IN ($id_list)";
        
        if ($db->query($sql)) {
            if (function_exists('logAudit')) {
                foreach ($ids as $del_id) {
                    $del_info = null;
                    foreach ($val_anterior as $item) {
                        if ($item['id'] == $del_id) {
                            $del_info = $item;
                            break;
                        }
                    }
                    logAudit(
                        $usuario_actual,
                        'eliminar_cotizacion',
                        'cotizaciones',
                        'eliminar',
                        "Cotización ID $del_id eliminada. Número: " . ($del_info['numero'] ?? 'N/D'),
                        'exitoso',
                        null,
                        'cotizaciones',
                        $del_id,
                        $del_info,
                        null
                    );
                }
            }
            respuestaJSON(true, count($ids) . ' cotizaciones eliminadas', ['eliminadas' => count($ids)], 200);
        } else {
            respuestaJSON(false, 'Error al eliminar: ' . $db->error, null, 500);
        }

    } else {
        respuestaJSON(false, 'Endpoint no encontrado. Use ?action=listar|obtener|guardar|importar|eliminar', null, 404);
    }

} catch (Exception $e) {
    respuestaJSON(false, 'Error interno: ' . $e->getMessage(), null, 500);
}
?>
