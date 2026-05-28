<?php
require_once dirname(__FILE__) . '/config.php';
require_once dirname(__FILE__) . '/MotorContable.php';

/**
 * Gestión de Pagos, Recibos y Desglose Financiero
 * MAS QUE FIANZAS - Core Asegurador v3.0
 */
class PagoManager {
    private $db;

    public function __construct() {
        try {
            $this->db = Database::getInstance()->getConnection();
        } catch (Exception $e) {
            throw new Exception("Error al inicializar PagoManager: " . $e->getMessage());
        }
    }

    /**
     * Registra un pago completo sobre una póliza y dispara el asiento contable automático
     */
    public function registrarPago($datos) {
        $this->db->begin_transaction();
        try {
            // 1. Validar póliza existente
            $polizaId = intval($datos['poliza_id']);
            $sql_p = "SELECT p.*, c.id as c_id, c.nombre as c_nombre FROM polizas p JOIN clientes c ON p.cliente_id = c.id WHERE p.id = ? LIMIT 1";
            $stmt_p = $this->db->prepare($sql_p);
            $stmt_p->bind_param("i", $polizaId);
            $stmt_p->execute();
            $poliza = $stmt_p->get_result()->fetch_assoc();
            $stmt_p->close();

            if (!$poliza) {
                throw new Exception("Póliza no encontrada.");
            }

            // 2. Generar campos automáticos de pago
            $num_ref = 'PAG-' . date('Ymd') . '-' . rand(1000, 9999);
            $num_rec = 'REC-' . date('Y') . '-' . rand(1000, 9999);
            $ncf = !empty($datos['numero_ncf']) ? $datos['numero_ncf'] : null;
            $tipo_comp = !empty($datos['tipo_comprobante']) ? $datos['tipo_comprobante'] : 'B02';
            $cliente_id = intval($poliza['c_id']);
            $monto = floatval($datos['monto']);
            $fecha_pago = !empty($datos['fecha_pago']) ? $datos['fecha_pago'] : date('Y-m-d');
            $tipo_pago = !empty($datos['tipo_pago']) ? $datos['tipo_pago'] : 'efectivo';
            $num_comp = !empty($datos['numero_comprobante']) ? $datos['numero_comprobante'] : null;
            $banco = !empty($datos['banco']) ? $datos['banco'] : null;
            $desc = !empty($datos['descripcion']) ? $datos['descripcion'] : ("Pago de Póliza #" . $poliza['numero_poliza']);
            $cuota_num = isset($datos['cuota_numero']) ? intval($datos['cuota_numero']) : 1;
            $cuota_tot = isset($datos['cuota_total']) ? intval($datos['cuota_total']) : 1;
            $reg_por = isset($datos['registrado_por']) ? intval($datos['registrado_por']) : 1;

            // ITBIS cobrado proporcionalmente en el pago
            $itbis_pago = $monto * 0.18; // 18% ITBIS

            // Determinar si tiene soporte adjunto
            $tiene_soporte = !empty($datos['comprobante_ruta']);
            $estado_pago = $tiene_soporte ? 'pendiente' : 'procesado';

            // 3. Insertar Pago en la tabla `pagos`
            $sql_ins = "INSERT INTO pagos (
                            numero_referencia, numero_recibo, numero_ncf, tipo_comprobante,
                            poliza_id, cliente_id, monto, fecha_pago, tipo_pago,
                            numero_comprobante, banco, estado_pago, registrado_por,
                            itbis_pago, descripcion, cuota_numero, cuota_total
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt_ins = $this->db->prepare($sql_ins);
            if (!$stmt_ins) {
                throw new Exception("Error al preparar inserción de pago: " . $this->db->error);
            }

            $stmt_ins->bind_param("ssssiidsssssidsii",
                $num_ref, $num_rec, $ncf, $tipo_comp,
                $polizaId, $cliente_id, $monto, $fecha_pago, $tipo_pago,
                $num_comp, $banco, $estado_pago, $reg_por,
                $itbis_pago, $desc, $cuota_num, $cuota_tot
            );

            if (!$stmt_ins->execute()) {
                throw new Exception("Error al insertar el pago: " . $stmt_ins->error);
            }
            $pagoId = $this->db->insert_id;
            $stmt_ins->close();

            // Guardar documento si existe soporte
            if ($tiene_soporte) {
                $sql_doc = "INSERT INTO documentos_poliza (
                                poliza_id, pago_id, tipo_documento, nombre_archivo,
                                ruta_archivo, hash_documento, generado_por
                            ) VALUES (?, ?, 'soporte_pago', ?, ?, ?, ?)";
                $stmt_doc = $this->db->prepare($sql_doc);
                if (!$stmt_doc) {
                    throw new Exception("Error al preparar inserción de documento: " . $this->db->error);
                }
                $stmt_doc->bind_param("iisssi",
                    $polizaId, $pagoId, $datos['comprobante_nombre'],
                    $datos['comprobante_ruta'], $datos['comprobante_hash'], $reg_por
                );
                if (!$stmt_doc->execute()) {
                    throw new Exception("Error al registrar el comprobante de pago: " . $stmt_doc->error);
                }
                $stmt_doc->close();
            } else {
                // 4. Integrar con el Motor Contable (Evento COBRO_PRIMA) - Solo si no es diferido
                $desc_personalizada = null;
                if (!empty($num_comp) || !empty($banco)) {
                    $ref_str = !empty($num_comp) ? $num_comp : 'S/N';
                    $banco_str = !empty($banco) ? $banco : 'S/B';
                    $desc_personalizada = "Cobro Prima Póliza #" . $poliza['numero_poliza'] . " — Soporte Depósito [" . $ref_str . "] - " . $banco_str;
                }

                $payloadContable = [
                    'modulo' => 'PAGOS',
                    'id' => $pagoId,
                    'numero' => $poliza['numero_poliza'],
                    'monto_cobrado' => $monto,
                    'fecha' => $fecha_pago
                ];
                if ($desc_personalizada) {
                    $payloadContable['descripcion_personalizada'] = $desc_personalizada;
                }

                $asientoId = \MQF\Finance\MotorContable::disparar('COBRO_PRIMA', $payloadContable);
                
                if ($asientoId) {
                    // Auto-aprobar el asiento para que afecte el libro mayor inmediatamente
                    $this->db->query("UPDATE cf_asientos SET estado = 'APROBADO' WHERE id = " . intval($asientoId));
                }
            }

            $this->db->commit();
            return [
                'exito' => true,
                'id' => $pagoId,
                'numero_referencia' => $num_ref,
                'numero_recibo' => $num_rec,
                'monto' => $monto,
                'ncf' => $ncf,
                'estado_pago' => $estado_pago
            ];

        } catch (Exception $e) {
            $this->db->rollback();
            return ['exito' => false, 'mensaje' => $e->getMessage()];
        }
    }

    /**
     * Aprueba un pago en estado 'pendiente' y genera el asiento contable correspondiente
     */
    public function aprobarPago($pagoId, $validadorId) {
        $this->db->begin_transaction();
        try {
            // 1. Obtener pago pendiente
            $sql_pago = "SELECT p.*, pol.numero_poliza 
                         FROM pagos p 
                         JOIN polizas pol ON p.poliza_id = pol.id 
                         WHERE p.id = ? LIMIT 1";
            $stmt_pago = $this->db->prepare($sql_pago);
            if (!$stmt_pago) {
                throw new Exception("Error al preparar consulta de pago: " . $this->db->error);
            }
            $stmt_pago->bind_param("i", $pagoId);
            $stmt_pago->execute();
            $pago = $stmt_pago->get_result()->fetch_assoc();
            $stmt_pago->close();

            if (!$pago) {
                throw new Exception("Pago no encontrado.");
            }

            if ($pago['estado_pago'] !== 'pendiente') {
                throw new Exception("El pago no está en estado pendiente para ser aprobado.");
            }

            // 2. Actualizar estado del pago a 'procesado' con validador y fecha
            $sql_upd = "UPDATE pagos SET estado_pago = 'procesado', validado_por = ?, fecha_validacion = NOW() WHERE id = ?";
            $stmt_upd = $this->db->prepare($sql_upd);
            if (!$stmt_upd) {
                throw new Exception("Error al preparar actualización: " . $this->db->error);
            }
            $stmt_upd->bind_param("ii", $validadorId, $pagoId);
            if (!$stmt_upd->execute()) {
                throw new Exception("Error al actualizar el estado del pago: " . $stmt_upd->error);
            }
            $stmt_upd->close();

            // 3. Generar el asiento contable con el custom description para trazabilidad
            $ref_str = !empty($pago['numero_comprobante']) ? $pago['numero_comprobante'] : 'S/N';
            $banco_str = !empty($pago['banco']) ? $pago['banco'] : 'S/B';
            $desc_personalizada = "Cobro Prima Póliza #" . $pago['numero_poliza'] . " — Soporte Depósito [" . $ref_str . "] - " . $banco_str;

            $payloadContable = [
                'modulo' => 'PAGOS',
                'id' => $pagoId,
                'numero' => $pago['numero_poliza'],
                'monto_cobrado' => floatval($pago['monto']),
                'fecha' => date('Y-m-d'),
                'descripcion_personalizada' => $desc_personalizada
            ];

            $asientoId = \MQF\Finance\MotorContable::disparar('COBRO_PRIMA', $payloadContable);
            
            if ($asientoId) {
                // Auto-aprobar el asiento contable
                $this->db->query("UPDATE cf_asientos SET estado = 'APROBADO' WHERE id = " . intval($asientoId));
            } else {
                throw new Exception("Error al generar el asiento contable.");
            }

            $this->db->commit();
            return ['exito' => true, 'mensaje' => 'Pago aprobado y contabilizado con éxito.', 'asiento_id' => $asientoId];

        } catch (Exception $e) {
            $this->db->rollback();
            return ['exito' => false, 'mensaje' => $e->getMessage()];
        }
    }

    /**
     * Rechaza un pago en estado 'pendiente'
     */
    public function rechazarPago($pagoId, $validadorId) {
        $this->db->begin_transaction();
        try {
            // 1. Obtener pago
            $sql_pago = "SELECT * FROM pagos WHERE id = ? LIMIT 1";
            $stmt_pago = $this->db->prepare($sql_pago);
            if (!$stmt_pago) {
                throw new Exception("Error al preparar consulta de pago: " . $this->db->error);
            }
            $stmt_pago->bind_param("i", $pagoId);
            $stmt_pago->execute();
            $pago = $stmt_pago->get_result()->fetch_assoc();
            $stmt_pago->close();

            if (!$pago) {
                throw new Exception("Pago no encontrado.");
            }

            if ($pago['estado_pago'] !== 'pendiente') {
                throw new Exception("El pago no está en estado pendiente para ser rechazado.");
            }

            // 2. Actualizar estado del pago a 'rechazado' con validador y fecha
            $sql_upd = "UPDATE pagos SET estado_pago = 'rechazado', validado_por = ?, fecha_validacion = NOW() WHERE id = ?";
            $stmt_upd = $this->db->prepare($sql_upd);
            if (!$stmt_upd) {
                throw new Exception("Error al preparar actualización: " . $this->db->error);
            }
            $stmt_upd->bind_param("ii", $validadorId, $pagoId);
            if (!$stmt_upd->execute()) {
                throw new Exception("Error al actualizar el estado del pago: " . $stmt_upd->error);
            }
            $stmt_upd->close();

            $this->db->commit();
            return ['exito' => true, 'mensaje' => 'Pago rechazado con éxito.'];

        } catch (Exception $e) {
            $this->db->rollback();
            return ['exito' => false, 'mensaje' => $e->getMessage()];
        }
    }

    /**
     * Obtiene el listado de pagos con filtros
     */
    public function obtenerPagos($filtros = []) {
        $where = "1=1";
        $params = [];
        $types = "";

        if (!empty($filtros['poliza_id'])) {
            $where .= " AND p.poliza_id = ?";
            $params[] = intval($filtros['poliza_id']);
            $types .= "i";
        }

        if (!empty($filtros['registrado_por'])) {
            $where .= " AND p.registrado_por = ?";
            $params[] = intval($filtros['registrado_por']);
            $types .= "i";
        }

        $sql = "SELECT p.*, pol.numero_poliza as poliza_numero, c.nombre as cliente_nombre 
                FROM pagos p
                JOIN polizas pol ON p.poliza_id = pol.id
                JOIN clientes c ON p.cliente_id = c.id
                WHERE $where 
                ORDER BY p.fecha_pago DESC";

        $stmt = $this->db->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
?>
