<?php
/**
 * API CENTRO FINANCIERO v3.0
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config.php';
require_once '../ContabilidadManager.php';

$action = $_GET['action'] ?? '';
$db = Database::getInstance()->getConnection();

try {
    switch ($action) {
        case 'get_resumen':
            // 1. Disponibilidad (Caja y Bancos 1.1.01)
            $stmt = $db->query("SELECT SUM(debe - haber) as saldo FROM cf_asiento_lineas WHERE cuenta_codigo LIKE '1.1.01%'");
            $disponibilidad = (float)($stmt->fetch_assoc()['saldo'] ?? 0);

            // 2. Primas por Cobrar (1.1.02)
            $stmt = $db->query("SELECT SUM(debe - haber) as saldo FROM cf_asiento_lineas WHERE cuenta_codigo LIKE '1.1.02%'");
            $primas_cobrar = (float)($stmt->fetch_assoc()['saldo'] ?? 0);

            // 3. Comisiones Ganadas (4.1.01)
            // Naturaleza Acreedora: (Haber - Debe)
            $stmt = $db->query("SELECT SUM(haber - debe) as saldo FROM cf_asiento_lineas WHERE cuenta_codigo LIKE '4.1.01%'");
            $comisiones = (float)($stmt->fetch_assoc()['saldo'] ?? 0);

            // 4. ITBIS por Pagar (2.1.02)
            $stmt = $db->query("SELECT SUM(haber - debe) as saldo FROM cf_asiento_lineas WHERE cuenta_codigo LIKE '2.1.02%'");
            $itbis = (float)($stmt->fetch_assoc()['saldo'] ?? 0);

            respuestaJSON(true, "Resumen obtenido", [
                'disponibilidad' => $disponibilidad,
                'primas_cobrar' => $primas_cobrar,
                'comisiones' => $comisiones,
                'itbis' => $itbis
            ]);
            break;

        case 'get_diario':
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            $sql = "SELECT a.*, SUM(l.debe) as total_monto 
                    FROM cf_asientos a 
                    JOIN cf_asiento_lineas l ON a.id = l.asiento_id 
                    GROUP BY a.id 
                    ORDER BY a.fecha DESC, a.id DESC 
                    LIMIT ?";
            
            $stmt = $db->prepare($sql);
            $stmt->bind_param("i", $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            $asientos = [];
            while($row = $result->fetch_assoc()) {
                $asientos[] = $row;
            }
            
            respuestaJSON(true, "Libro Diario obtenido", $asientos);
            break;

        case 'get_asiento_detalle':
            $id = (int)($_GET['id'] ?? 0);
            $stmt = $db->prepare("SELECT l.*, c.nombre as cuenta_nombre 
                                 FROM cf_asiento_lineas l 
                                 JOIN cf_catalogo_cuentas c ON l.cuenta_codigo = c.codigo 
                                 WHERE l.asiento_id = ? 
                                 ORDER BY l.debe DESC");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $lineas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            respuestaJSON(true, "Detalle de asiento obtenido", $lineas);
            break;

        case 'get_catalogo':
            $stmt = $db->query("SELECT * FROM cf_catalogo_cuentas ORDER BY codigo");
            $cuentas = $stmt->fetch_all(MYSQLI_ASSOC);
            respuestaJSON(true, "Catálogo obtenido", $cuentas);
            break;

        default:
            respuestaJSON(false, "Acción no definida");
            break;
    }
} catch (Exception $e) {
    respuestaJSON(false, $e->getMessage());
}
