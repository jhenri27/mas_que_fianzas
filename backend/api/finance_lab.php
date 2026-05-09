<?php
/**
 * API FINANCE LAB
 * Entorno de pruebas para el Centro Financiero v3.0
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config.php';
require_once '../ContabilidadManager.php';
require_once '../MotorContable.php';
require_once '../NCFManager.php';

$action = $_GET['action'] ?? '';
$db = Database::getInstance()->getConnection();

try {
    switch ($action) {
        case 'test_ncf':
            $datos = json_decode(file_get_contents('php://input'), true);
            $tipo = $datos['tipo'] ?? 'B02';
            $usar = $datos['usar'] ?? true;
            
            $ncfMgr = new \MQF\Finance\NCFManager($db);
            $ncf = $ncfMgr->generarSiguiente($tipo, $usar);
            
            respuestaJSON(true, "Prueba de NCF completada", ['ncf' => $ncf]);
            break;

        case 'test_asiento':
            $input = file_get_contents('php://input');
            error_log("Finance Lab Input: " . $input);
            $datos = json_decode($input, true);
            $evento = $datos['evento'] ?? 'EMISION_POLIZA';
            
            // Simular datos de entrada
            $montoOperacion = (float)($datos['monto'] ?? 5000);
            $comisionBruta = $montoOperacion * 0.10;
            $itbisComision = $comisionBruta * 0.18;
            $retencionISR = $comisionBruta * 0.10;
            
            // Para Emisión: Neto Aseguradora = Total - Comisión - ITBIS
            $netoAseguradora = $montoOperacion - $comisionBruta - $itbisComision;
            
            // Para Pago Agente: Neto Agente = Comisión - Retención
            $netoAgente = $comisionBruta - $retencionISR;

            $payload = [
                'id' => rand(100, 999),
                'modulo' => 'LAB_TEST',
                'numero' => 'TEST-' . date('is'),
                'total' => $montoOperacion,
                'monto_total' => $montoOperacion,
                'monto_neto' => ($evento === 'EMISION_POLIZA') ? $netoAseguradora : $netoAgente,
                'comision' => $comisionBruta,
                'itbis' => $itbisComision,
                'monto_cobrado' => $montoOperacion,
                'monto_bruto' => $comisionBruta,
                'retencion_isr' => $retencionISR,
                'agente' => 'Agente Prueba Lab'
            ];

            error_log("Finance Lab Evento: $evento, Payload: " . json_encode($payload));
            $asientoId = \MQF\Finance\MotorContable::disparar($evento, $payload);
            
            if ($asientoId) {
                // Obtener detalle del asiento para el feedback
                $stmt = $db->prepare("SELECT a.*, l.cuenta_codigo, l.debe, l.haber 
                                     FROM cf_asientos a 
                                     JOIN cf_asiento_lineas l ON a.id = l.asiento_id 
                                     WHERE a.id = ?");
                $stmt->bind_param("i", $asientoId);
                $stmt->execute();
                $result = $stmt->get_result();
                $lineas = [];
                $header = null;
                while($row = $result->fetch_assoc()) {
                    if (!$header) $header = $row;
                    $lineas[] = [
                        'cuenta' => $row['cuenta_codigo'],
                        'debe' => $row['debe'],
                        'haber' => $row['haber']
                    ];
                }

                respuestaJSON(true, "Asiento generado exitosamente", [
                    'asiento_id' => $asientoId,
                    'numero' => $header['numero'],
                    'lineas' => $lineas
                ]);
            } else {
                respuestaJSON(false, "No se pudo generar el asiento. Verifique los logs.");
            }
            break;

        case 'limpiar_lab':
            // Peligroso: solo para ambiente de desarrollo/lab
            $db->query("DELETE FROM cf_asiento_lineas WHERE asiento_id IN (SELECT id FROM cf_asientos WHERE origen_modulo = 'LAB_TEST')");
            $db->query("DELETE FROM cf_asientos WHERE origen_modulo = 'LAB_TEST'");
            respuestaJSON(true, "Datos de prueba del Lab eliminados");
            break;

        case 'get_ncf_status':
            $secuencias = $db->query("SELECT * FROM cf_ncf_secuencias")->fetch_all(MYSQLI_ASSOC);
            $logs = $db->query("SELECT * FROM cf_ncf_log ORDER BY fecha_emision DESC LIMIT 20")->fetch_all(MYSQLI_ASSOC);
            respuestaJSON(true, "Datos NCF obtenidos", [
                'secuencias' => $secuencias,
                'logs' => $logs
            ]);
            break;

        case 'update_ncf_sequence':
            $datos = json_decode(file_get_contents('php://input'), true);
            $tipo = $datos['tipo'];
            $nuevo_valor = (int)$datos['valor'];
            
            $stmt = $db->prepare("UPDATE cf_ncf_secuencias SET secuencia_actual = ? WHERE tipo = ?");
            $stmt->bind_param("is", $nuevo_valor, $tipo);
            if ($stmt->execute()) {
                respuestaJSON(true, "Secuencia $tipo actualizada a $nuevo_valor");
            } else {
                respuestaJSON(false, "Error al actualizar secuencia");
            }
            $stmt->close();
            break;

        default:
            respuestaJSON(false, "Acción no válida");
            break;
    }
} catch (Exception $e) {
    respuestaJSON(false, $e->getMessage());
}
