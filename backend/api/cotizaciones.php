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
    $uso         = $c['uso'] ?? '';
    $capacidad   = $c['capacidad'] ?? '';
    $aseguradora = $c['aseguradora'] ?? '';
    $cobertura   = $c['cobertura'] ?? '';
    $monto       = floatval($c['monto_afianzado'] ?? 0);
    $plazo       = intval($c['plazo'] ?? 0);
    $prima_base  = floatval($c['prima_base'] ?? 0);
    $impuesto    = floatval($c['impuesto'] ?? 0);
    $total       = floatval($c['total'] ?? 0);
    $servicios   = isset($c['servicios_opcionales']) ? json_encode($c['servicios_opcionales']) : '';
    $fecha_raw   = $c['fecha'] ?? date('Y-m-d H:i:s');
    // Normalizar fecha ISO a MySQL
    $fecha = date('Y-m-d H:i:s', strtotime($fecha_raw));

    $stmt = $db->prepare(
        "INSERT INTO cotizaciones 
         (numero, tipo, subtipo, cliente, cedula, uso, capacidad, aseguradora, cobertura,
          monto_afianzado, plazo, prima_base, impuesto, total, servicios_opcionales, fecha)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE
         tipo=VALUES(tipo), cliente=VALUES(cliente), total=VALUES(total), fecha=VALUES(fecha)"
    );
    $stmt->bind_param('sssssssssdidddss',
        $numero, $tipo, $subtipo, $cliente, $cedula,
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
        insertar_cotizacion($db, $datos);
        respuestaJSON(true, 'Cotizacion guardada en base de datos', ['numero' => $datos['numero']], 201);

    // ACTUALIZAR (por ID)
    } elseif ($action === 'actualizar' && $metodo === 'POST') {
        $datos = json_decode(file_get_contents('php://input'), true);
        if (empty($datos['id'])) {
            respuestaJSON(false, 'ID de cotización requerido para actualizar', null, 400);
        }

        $id = intval($datos['id']);
        $sql = "UPDATE cotizaciones SET 
                tipo = ?, subtipo = ?, cliente = ?, cedula = ?, uso = ?, 
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

        $stmt->bind_param('ssssssssdidddsi',
            $datos['tipo'], $datos['subtipo'], $datos['cliente'], $datos['cedula'], $datos['uso'],
            $datos['capacidad'], $datos['aseguradora'], $cobertura,
            $datos['monto_afianzado'], $datos['plazo'], $datos['prima_base'],
            $datos['impuesto'], $datos['total'], $servicios, $id
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
