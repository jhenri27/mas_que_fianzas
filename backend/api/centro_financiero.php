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

        case 'crear_cuenta':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                $data = $_POST;
            }

            $codigo = trim($data['codigo'] ?? '');
            $nombre = trim($data['nombre'] ?? '');
            $tipo = trim($data['tipo'] ?? '');
            $naturaleza = trim($data['naturaleza'] ?? '');
            $es_detalle = isset($data['es_detalle']) ? (int)$data['es_detalle'] : 1;

            if (empty($codigo) || empty($nombre) || empty($tipo) || empty($naturaleza)) {
                respuestaJSON(false, "Todos los campos marcados con * son obligatorios.");
                break;
            }

            // Validar si el código ya existe
            $stmt = $db->prepare("SELECT id FROM cf_catalogo_cuentas WHERE codigo = ?");
            $stmt->bind_param("s", $codigo);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                respuestaJSON(false, "El código de cuenta '$codigo' ya está registrado.");
                break;
            }
            $stmt->close();

            // Calcular nivel y cuenta padre
            $tokens = explode('.', $codigo);
            $nivel = count($tokens);
            $cuenta_padre = '';
            if ($nivel > 1) {
                array_pop($tokens);
                $cuenta_padre = implode('.', $tokens);
            }

            // Insertar la cuenta
            $stmt = $db->prepare("INSERT INTO cf_catalogo_cuentas (codigo, nombre, tipo, naturaleza, nivel, cuenta_padre, es_detalle, activa) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
            $stmt->bind_param("ssssisi", $codigo, $nombre, $tipo, $naturaleza, $nivel, $cuenta_padre, $es_detalle);
            if ($stmt->execute()) {
                respuestaJSON(true, "Cuenta '$nombre' registrada con éxito.");
            } else {
                respuestaJSON(false, "Error al registrar la cuenta: " . $stmt->error);
            }
            $stmt->close();
            break;

        default:
            respuestaJSON(false, "Acción no definida");
            break;
    }
} catch (Exception $e) {
    respuestaJSON(false, $e->getMessage());
}
