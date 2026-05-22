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

// Auto-crear tabla si no existe
function crearTablaIfNeeded($db) {
    $sql = "CREATE TABLE IF NOT EXISTS `cotizaciones` (
        `id`                   INT AUTO_INCREMENT PRIMARY KEY,
        `numero`               VARCHAR(40)   NOT NULL,
        `tipo`                 VARCHAR(30)   NOT NULL,
        `subtipo`              VARCHAR(100)  DEFAULT NULL,
        `cliente`              VARCHAR(200)  DEFAULT NULL,
        `cedula`               VARCHAR(30)   DEFAULT NULL,
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
}

function insertar_cotizacion($db, $c) {
    $numero      = $c['numero'];
    $tipo        = $c['tipo'] ?? 'GENERAL';
    $subtipo     = $c['subtipo'] ?? '';
    $cliente     = $c['cliente'] ?? '';
    $cedula      = $c['cedula'] ?? '';
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
    // Normalizar fecha ISO a MySQL
    $fecha = date('Y-m-d H:i:s', strtotime($fecha_raw));

    $stmt = $db->prepare(
        "INSERT INTO cotizaciones 
         (numero, tipo, subtipo, cliente, cedula, beneficiario, uso, capacidad, aseguradora, cobertura,
          monto_afianzado, plazo, prima_base, impuesto, total, servicios_opcionales, fecha)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE
         tipo=VALUES(tipo), cliente=VALUES(cliente), beneficiario=VALUES(beneficiario), total=VALUES(total), fecha=VALUES(fecha)"
    );
    $stmt->bind_param('ssssssssssdidddss',
        $numero, $tipo, $subtipo, $cliente, $cedula, $beneficiario,
        $uso, $capacidad, $aseguradora, $cobertura,
        $monto, $plazo, $prima_base, $impuesto, $total,
        $servicios, $fecha
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
        
        $sql = "SELECT * FROM cotizaciones";
        if (!empty($numero)) {
            $stmt = $db->prepare("SELECT * FROM cotizaciones WHERE numero = ? LIMIT 1");
            $stmt->bind_param('s', $numero);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $db->query("SELECT * FROM cotizaciones ORDER BY fecha DESC LIMIT $limite");
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
        
        if (insertar_cotizacion($db, $datos)) {
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
                    'comision' => $datos['total'] * 0.10, // Ejemplo: 10% comisión
                    'itbis' => ($datos['total'] * 0.10) * 0.18, // ITBIS s/comisión
                    'monto_neto' => $datos['total'] - ($datos['total'] * 0.10)
                ]);

                \MQF\Finance\MotorContable::disparar('EMISION_POLIZA', $payloadContable);

                respuestaJSON(true, 'Cotizacion guardada y procesada contablemente', [
                    'numero' => $datos['numero'],
                    'ncf' => $ncf
                ], 201);

            } catch (\Exception $e) {
                // Si falla la contabilidad, el registro principal ya se guardó.
                // Logueamos pero devolvemos éxito de la venta para no interferir.
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

        $id = intval($datos['id']);
        $sql = "UPDATE cotizaciones SET 
                tipo = ?, subtipo = ?, cliente = ?, cedula = ?, beneficiario = ?, uso = ?, 
                capacidad = ?, aseguradora = ?, cobertura = ?, 
                monto_afianzado = ?, plazo = ?, prima_base = ?, 
                impuesto = ?, total = ?, servicios_opcionales = ?
                WHERE id = ?";
        
        $stmt = $db->prepare($sql);
        $servicios = isset($datos['servicios_opcionales']) ? 
                    (is_array($datos['servicios_opcionales']) ? json_encode($datos['servicios_opcionales']) : $datos['servicios_opcionales']) 
                    : '';
        $cobertura = isset($datos['cobertura']) ? 
                    (is_array($datos['cobertura']) ? json_encode($datos['cobertura']) : $datos['cobertura']) 
                    : ($datos['cobertura'] ?? '');
        $beneficiario = $datos['beneficiario'] ?? '';

        $tipo = $datos['tipo'] ?? 'GENERAL';
        $subtipo = $datos['subtipo'] ?? '';
        $cliente = $datos['cliente'] ?? '';
        $cedula = $datos['cedula'] ?? '';
        $uso = $datos['uso'] ?? '';
        $capacidad = $datos['capacidad'] ?? '';
        $aseguradora = $datos['aseguradora'] ?? '';
        $monto_afianzado = floatval($datos['monto_afianzado'] ?? 0);
        $plazo = intval($datos['plazo'] ?? 0);
        $prima_base = floatval($datos['prima_base'] ?? 0);
        $impuesto = floatval($datos['impuesto'] ?? 0);
        $total = floatval($datos['total'] ?? 0);

        $stmt->bind_param('sssssssssdidddsi',
            $tipo, $subtipo, $cliente, $cedula, $beneficiario, $uso,
            $capacidad, $aseguradora, $cobertura,
            $monto_afianzado, $plazo, $prima_base,
            $impuesto, $total, $servicios, $id
        );

        if ($stmt->execute()) {
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
            if (insertar_cotizacion($db, $c)) $ok++;
        }
        respuestaJSON(true, "$ok cotizaciones importadas a la base de datos", ['insertadas' => $ok], 201);

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
        $sql = "DELETE FROM cotizaciones WHERE id IN ($id_list)";
        
        if ($db->query($sql)) {
            respuestaJSON(true, count($ids) . ' cotizaciones eliminadas', ['eliminadas' => count($ids)], 200);
        } else {
            respuestaJSON(false, 'Error al eliminar: ' . $db->error, null, 500);
        }

    } else {
        respuestaJSON(false, 'Endpoint no encontrado. Use ?action=listar|guardar|importar|eliminar', null, 404);
    }

} catch (Exception $e) {
    respuestaJSON(false, 'Error interno: ' . $e->getMessage(), null, 500);
}
?>
