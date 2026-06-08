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
     * Registra un plan de fraccionamiento de prima (Ley 146-02: Inicial >= 25% y 2 cuotas mensuales)
     */
    public function registrarFraccionamiento($datos) {
        $this->db->begin_transaction();
        try {
            $polizaId = intval($datos['poliza_id']);
            $montoInicial = floatval($datos['monto_inicial']);
            $tipoPago = !empty($datos['tipo_pago']) ? $datos['tipo_pago'] : 'efectivo';
            $banco = !empty($datos['banco']) ? $datos['banco'] : null;
            $numComp = !empty($datos['numero_comprobante']) ? $datos['numero_comprobante'] : null;
            $fechaPago = !empty($datos['fecha_pago']) ? $datos['fecha_pago'] : date('Y-m-d');
            $regPor = isset($datos['registrado_por']) ? intval($datos['registrado_por']) : 1;
            
            // Comprobante de transferencia si aplica
            $comprobanteNombre = $datos['comprobante_nombre'] ?? null;
            $comprobanteRuta = $datos['comprobante_ruta'] ?? null;
            $comprobanteHash = $datos['comprobante_hash'] ?? null;

            // 1. Validar póliza existente
            $sql_p = "SELECT p.*, c.id as c_id, c.nombre as c_nombre FROM polizas p JOIN clientes c ON p.cliente_id = c.id WHERE p.id = ? LIMIT 1";
            $stmt_p = $this->db->prepare($sql_p);
            if (!$stmt_p) throw new Exception($this->db->error);
            $stmt_p->bind_param("i", $polizaId);
            $stmt_p->execute();
            $poliza = $stmt_p->get_result()->fetch_assoc();
            $stmt_p->close();

            if (!$poliza) {
                throw new Exception("Póliza no encontrada.");
            }

            // 2. Validar que no tenga pagos procesados
            $sql_check = "SELECT COUNT(*) as cnt FROM pagos WHERE poliza_id = ? AND estado_pago = 'procesado'";
            $stmt_check = $this->db->prepare($sql_check);
            if (!$stmt_check) throw new Exception($this->db->error);
            $stmt_check->bind_param("i", $polizaId);
            $stmt_check->execute();
            $chk_res = $stmt_check->get_result()->fetch_assoc();
            $stmt_check->close();

            if ($chk_res['cnt'] > 0) {
                throw new Exception("No es posible fraccionar esta póliza: Ya existen cobros procesados registrados.");
            }

            // 3. Validar regla Superintendencia (Inicial >= 25% de prima_total)
            $primaTotal = floatval($poliza['prima_total']);
            $minimoInicial = round($primaTotal * 0.25, 2);
            if ($montoInicial < $minimoInicial) {
                throw new Exception("Regla Superintendencia: El pago inicial (RD$ " . number_format($montoInicial, 2) . ") debe ser al menos el 25% de la prima total (Mínimo: RD$ " . number_format($minimoInicial, 2) . ").");
            }

            // 4. Actualizar póliza a cuota_total = 3
            $sql_up_pol = "UPDATE polizas SET cuota_total = 3 WHERE id = ?";
            $stmt_up_pol = $this->db->prepare($sql_up_pol);
            if (!$stmt_up_pol) throw new Exception($this->db->error);
            $stmt_up_pol->bind_param("i", $polizaId);
            if (!$stmt_up_pol->execute()) {
                throw new Exception("Error al actualizar cuotas de póliza: " . $stmt_up_pol->error);
            }
            $stmt_up_pol->close();

            // 5. Eliminar pagos pendientes previos sin soporte para evitar duplicados
            $sql_del = "DELETE FROM pagos WHERE poliza_id = ? AND estado_pago = 'pendiente' AND id NOT IN (SELECT DISTINCT pago_id FROM documentos_poliza WHERE poliza_id = ? AND pago_id IS NOT NULL)";
            $stmt_del = $this->db->prepare($sql_del);
            if (!$stmt_del) throw new Exception($this->db->error);
            $stmt_del->bind_param("ii", $polizaId, $polizaId);
            $stmt_del->execute();
            $stmt_del->close();

            // 6. Registrar Cuota 1 (Inicial)
            // Si es efectivo o tarjeta, es procesado. Si es transferencia/cheque es pendiente.
            $estadoInicial = ($tipoPago === 'efectivo' || $tipoPago === 'tarjeta_credito' || $tipoPago === 'tarjeta_debito') ? 'procesado' : 'pendiente';
            
            $num_ref = 'PAG-' . date('Ymd') . '-' . rand(1000, 9999);
            $num_rec = 'REC-' . date('Y') . '-' . rand(1000, 9999);
            $itbis_pago = $montoInicial * 0.16; // 16% ISC
            $desc = "Pago Inicial (Cuota 1 de 3) - Póliza #" . $poliza['numero_poliza'];

            $sql_ins = "INSERT INTO pagos (
                            numero_referencia, numero_recibo, numero_ncf, tipo_comprobante,
                            poliza_id, cliente_id, monto, fecha_pago, tipo_pago,
                            numero_comprobante, banco, estado_pago, registrado_por,
                            itbis_pago, descripcion, cuota_numero, cuota_total
                        ) VALUES (?, ?, null, 'B02', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 3)";
            
            $stmt_ins = $this->db->prepare($sql_ins);
            if (!$stmt_ins) {
                throw new Exception("Error al preparar inserción del inicial: " . $this->db->error);
            }
            
            $stmt_ins->bind_param("ssiidsssssids",
                $num_ref, $num_rec,
                $polizaId, $poliza['cliente_id'], $montoInicial, $fechaPago, $tipoPago,
                $numComp, $banco, $estadoInicial, $regPor,
                $itbis_pago, $desc
            );

            if (!$stmt_ins->execute()) {
                throw new Exception("Error al registrar cuota inicial: " . $stmt_ins->error);
            }
            $pagoId = $this->db->insert_id;
            $stmt_ins->close();

            // Guardar documento si es transferencia con comprobante
            if ($estadoInicial === 'pendiente' && $comprobanteRuta) {
                // Validación contra fraude (NOFTRAB): verificar duplicidad de archivo soporte
                $sql_chk_hash = "SELECT COUNT(*) as cnt FROM documentos_poliza WHERE hash_documento = ? AND tipo_documento = 'soporte_pago'";
                $stmt_chk = $this->db->prepare($sql_chk_hash);
                if ($stmt_chk) {
                    $stmt_chk->bind_param("s", $comprobanteHash);
                    $stmt_chk->execute();
                    $res_chk = $stmt_chk->get_result()->fetch_assoc();
                    $stmt_chk->close();
                    
                    if (isset($res_chk['cnt']) && $res_chk['cnt'] > 0) {
                        throw new Exception("Error transaccional (NOFTRAB): El comprobante de pago adjunto ya ha sido utilizado para validar otro cobro en el sistema. Intento de duplicidad bloqueado.");
                    }
                }

                $sql_doc = "INSERT INTO documentos_poliza (
                                poliza_id, pago_id, tipo_documento, nombre_archivo,
                                ruta_archivo, hash_documento, generado_por
                            ) VALUES (?, ?, 'soporte_pago', ?, ?, ?, ?)";
                $stmt_doc = $this->db->prepare($sql_doc);
                if (!$stmt_doc) throw new Exception($this->db->error);
                $stmt_doc->bind_param("iisssi",
                    $polizaId, $pagoId, $comprobanteNombre,
                    $comprobanteRuta, $comprobanteHash, $regPor
                );
                if (!$stmt_doc->execute()) {
                    throw new Exception("Error al registrar el comprobante de pago inicial: " . $stmt_doc->error);
                }
                $stmt_doc->close();
            }

            // 7. Calcular y registrar Cuotas 2 y 3 (Vencimiento futuro y estado pendiente)
            $balanceRestante = $primaTotal - $montoInicial;
            $montoCuota = round($balanceRestante / 2, 2);
            $montoUltimaCuota = round($balanceRestante - $montoCuota, 2);

            for ($c = 2; $c <= 3; $c++) {
                $montoC = ($c === 3) ? $montoUltimaCuota : $montoCuota;
                $itbisC = $montoC * 0.16; // 16% ISC
                $dias = ($c - 1) * 30;
                $fechaVence = date('Y-m-d', strtotime($fechaPago . " + $dias days"));
                
                $num_ref_c = 'PAG-' . date('Ymd', strtotime($fechaVence)) . '-' . rand(1000, 9999);
                $num_rec_c = 'REC-' . date('Y', strtotime($fechaVence)) . '-' . rand(1000, 9999);
                $desc_c = "Financiamiento Cuota $c de 3 (Vence $fechaVence) - Póliza #" . $poliza['numero_poliza'];

                $sql_c = "INSERT INTO pagos (
                            numero_referencia, numero_recibo, numero_ncf, tipo_comprobante,
                            poliza_id, cliente_id, monto, fecha_pago, tipo_pago,
                            numero_comprobante, banco, estado_pago, registrado_por,
                            itbis_pago, descripcion, cuota_numero, cuota_total
                        ) VALUES (?, ?, null, 'B02', ?, ?, ?, ?, ?, ?, ?, 'pendiente', ?, ?, ?, ?, 3)";
                
                $stmt_c = $this->db->prepare($sql_c);
                if (!$stmt_c) {
                    throw new Exception("Error al preparar cuotas del financiamiento: " . $this->db->error);
                }
                
                $stmt_c->bind_param("ssiidsssssidi",
                    $num_ref_c, $num_rec_c,
                    $polizaId, $poliza['cliente_id'], $montoC, $fechaVence, $tipoPago,
                    $numComp, $banco, $regPor,
                    $itbisC, $desc_c, $c
                );
                
                if (!$stmt_c->execute()) {
                    throw new Exception("Error al insertar cuota $c: " . $stmt_c->error);
                }
                $stmt_c->close();
            }

            // 8. Contabilizar cuota inicial de inmediato si es procesado
            if ($estadoInicial === 'procesado') {
                $desc_personalizada = "Cobro Inicial (Cuota 1 de 3) Póliza #" . $poliza['numero_poliza'];
                if (!empty($banco) || !empty($numComp)) {
                    $desc_personalizada .= " [" . ($banco ?? 'Caja') . " - Ref: " . ($numComp ?? 'Directo') . "]";
                }
                
                $payloadContable = [
                    'modulo' => 'PAGOS',
                    'id' => $pagoId,
                    'numero' => $poliza['numero_poliza'],
                    'monto_cobrado' => $montoInicial,
                    'fecha' => $fechaPago,
                    'descripcion_personalizada' => $desc_personalizada
                ];

                $asientoId = \MQF\Finance\MotorContable::disparar('COBRO_PRIMA', $payloadContable);
                if ($asientoId) {
                    $this->db->query("UPDATE cf_asientos SET estado = 'APROBADO' WHERE id = " . intval($asientoId));
                }
            }

            // 9. Registrar ajuste de auditoría (NOFTRAB)
            $justificacion = "Registro de plan de fraccionamiento multicanal (Inicial: RD$ " . number_format($montoInicial, 2) . ", 2 cuotas mensuales de RD$ " . number_format($montoCuota, 2) . ")";
            registrarAjuste($regPor, 'pagos', 'pagos_plan', $polizaId, ['plan' => 'sin_fraccionar'], ['plan' => 'fraccionado_3_cuotas', 'inicial' => $montoInicial], $justificacion);

            $this->db->commit();
            return [
                'exito' => true,
                'id' => $pagoId,
                'numero_referencia' => $num_ref,
                'monto' => $montoInicial,
                'cuotas_generadas' => 3,
                'estado_pago' => $estadoInicial
            ];

        } catch (Exception $e) {
            $this->db->rollback();
            return ['exito' => false, 'mensaje' => $e->getMessage()];
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

            $monto = floatval($datos['monto']);

            // Validar que no sea un pago parcial menor al 50% del balance restante en cuotas futuras
            $sql_pagado = "SELECT COALESCE(SUM(monto), 0) as pagado FROM pagos WHERE poliza_id = ? AND estado_pago = 'procesado'";
            $stmt_pagado = $this->db->prepare($sql_pagado);
            if ($stmt_pagado) {
                $stmt_pagado->bind_param("i", $polizaId);
                $stmt_pagado->execute();
                $pagado = floatval($stmt_pagado->get_result()->fetch_assoc()['pagado']);
                $stmt_pagado->close();

                if ($pagado > 0) {
                    $balanceRestante = floatval($poliza['prima_total']) - $pagado;
                    if ($balanceRestante > 0) {
                        $minimoCuotaFutura = round($balanceRestante * 0.50, 2);
                        if ($monto < $minimoCuotaFutura && abs($monto - $balanceRestante) > 0.01) {
                            throw new Exception("El pago de cuotas futuras debe ser de al menos el 50% del balance restante (Mínimo: RD$ " . number_format($minimoCuotaFutura, 2) . ").");
                        }
                    }
                }
            }

            // 2. Generar campos automáticos de pago
            $num_ref = 'PAG-' . date('Ymd') . '-' . rand(1000, 9999);
            $num_rec = 'REC-' . date('Y') . '-' . rand(1000, 9999);
            $ncf = !empty($datos['numero_ncf']) ? $datos['numero_ncf'] : null;
            $tipo_comp = !empty($datos['tipo_comprobante']) ? $datos['tipo_comprobante'] : 'B02';
            $cliente_id = intval($poliza['c_id']);
            $fecha_pago = !empty($datos['fecha_pago']) ? $datos['fecha_pago'] : date('Y-m-d');
            $tipo_pago = !empty($datos['tipo_pago']) ? $datos['tipo_pago'] : 'efectivo';
            $num_comp = !empty($datos['numero_comprobante']) ? $datos['numero_comprobante'] : null;
            $banco = !empty($datos['banco']) ? $datos['banco'] : null;
            $desc = !empty($datos['descripcion']) ? $datos['descripcion'] : ("Pago de Póliza #" . $poliza['numero_poliza']);
            $cuota_num = isset($datos['cuota_numero']) ? intval($datos['cuota_numero']) : 1;
            $cuota_tot = isset($datos['cuota_total']) ? intval($datos['cuota_total']) : 1;
            $reg_por = isset($datos['registrado_por']) ? intval($datos['registrado_por']) : 1;

            // ITBIS/ISC cobrado proporcionalmente en el pago
            $itbis_pago = $monto * 0.16; // 16% ISC

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
                // Validación contra fraude (NOFTRAB): verificar duplicidad de archivo soporte
                $comprobante_hash = $datos['comprobante_hash'];
                $sql_chk_hash = "SELECT COUNT(*) as cnt FROM documentos_poliza WHERE hash_documento = ? AND tipo_documento = 'soporte_pago'";
                $stmt_chk = $this->db->prepare($sql_chk_hash);
                if ($stmt_chk) {
                    $stmt_chk->bind_param("s", $comprobante_hash);
                    $stmt_chk->execute();
                    $res_chk = $stmt_chk->get_result()->fetch_assoc();
                    $stmt_chk->close();
                    
                    if (isset($res_chk['cnt']) && $res_chk['cnt'] > 0) {
                        throw new Exception("Error transaccional (NOFTRAB): El comprobante de pago adjunto ya ha sido utilizado para validar otro cobro en el sistema. Intento de duplicidad bloqueado.");
                    }
                }

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

                // Fase 2 y 3: OCR y Conciliación Automática (con Fallback Manual)
                try {
                    $ocrResult = $this->analizarImagenOCR($datos['comprobante_ruta']);
                    if ($ocrResult['exito'] && !empty($ocrResult['texto'])) {
                        $textoOcr = $ocrResult['texto'];
                        
                        // Extraer referencia usando expresiones regulares del texto leído
                        $referenciaDetectada = null;
                        if (preg_match('/\b(TXN-\d+|DEP-\d+|REF-\d+|\d{8,15})\b/i', $textoOcr, $matchesRef)) {
                            $referenciaDetectada = trim($matchesRef[1]);
                        }
                        
                        if ($referenciaDetectada) {
                            // Buscar en cf_movimientos_bancarios si existe un movimiento con esa referencia y el monto correspondiente
                            $sql_reconcile = "SELECT id, banco, cuenta_destino, referencia_bancaria, monto 
                                              FROM cf_movimientos_bancarios 
                                              WHERE referencia_bancaria = ? AND monto = ? AND conciliado = 0 LIMIT 1";
                            $stmt_rec = $this->db->prepare($sql_reconcile);
                            if ($stmt_rec) {
                                $stmt_rec->bind_param("sd", $referenciaDetectada, $monto);
                                $stmt_rec->execute();
                                $movimiento = $stmt_rec->get_result()->fetch_assoc();
                                $stmt_rec->close();
                                
                                if ($movimiento) {
                                    // MATCH PERFECTO! Auto-conciliado!
                                    // 1. Actualizar el pago a estado = 'procesado'
                                    $movimiento_id = $movimiento['id'];
                                    $this->db->query("UPDATE pagos SET estado_pago = 'procesado', numero_comprobante = '" . $this->db->real_escape_string($referenciaDetectada) . "', banco = '" . $this->db->real_escape_string($movimiento['banco']) . "', validado_por = 1, fecha_validacion = NOW(), descripcion = CONCAT(descripcion, ' [Auto-Conciliado OCR]') WHERE id = " . intval($pagoId));
                                    
                                    // 2. Marcar el movimiento bancario como conciliado
                                    $this->db->query("UPDATE cf_movimientos_bancarios SET conciliado = 1, pago_id = " . intval($pagoId) . " WHERE id = " . intval($movimiento_id));
                                    
                                    // 3. Registrar el Asiento Contable (evento COBRO_PRIMA) automáticamente
                                    $desc_personalizada = "Cobro Prima Póliza #" . $poliza['numero_poliza'] . " — Auto-Conciliado OCR [Ref: " . $referenciaDetectada . "] - " . $movimiento['banco'];
                                    $payloadContable = [
                                        'modulo' => 'PAGOS',
                                        'id' => $pagoId,
                                        'numero' => $poliza['numero_poliza'],
                                        'monto_cobrado' => $monto,
                                        'fecha' => $fecha_pago,
                                        'descripcion_personalizada' => $desc_personalizada
                                    ];
                                    
                                    $asientoId = \MQF\Finance\MotorContable::disparar('COBRO_PRIMA', $payloadContable);
                                    if ($asientoId) {
                                        $this->db->query("UPDATE cf_asientos SET estado = 'APROBADO' WHERE id = " . intval($asientoId));
                                    }
                                    
                                    $estado_pago = 'procesado'; // Para el array de retorno
                                }
                            }
                        }
                    }
                } catch (Exception $eOcr) {
                    // FALLBACK MANUAL: Si falla el OCR o la conciliación, no bloqueamos la transacción.
                    // El pago permanece 'pendiente' para revisión manual.
                    error_log("Fallback Manual Activado: Error al ejecutar conciliación OCR: " . $eOcr->getMessage());
                }
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

            // Si es la cuota 1 de un plan de fraccionamiento, cancelar/rechazar las cuotas futuras
            if ($pago['cuota_numero'] == 1 && $pago['cuota_total'] > 1) {
                $sql_cancel_futures = "UPDATE pagos SET estado_pago = 'rechazado', descripcion = CONCAT(descripcion, ' [Cancelado por rechazo de inicial]') WHERE poliza_id = ? AND cuota_numero > 1 AND estado_pago = 'pendiente'";
                $stmt_cf = $this->db->prepare($sql_cancel_futures);
                if ($stmt_cf) {
                    $stmt_cf->bind_param("i", $pago['poliza_id']);
                    $stmt_cf->execute();
                    $stmt_cf->close();
                }
                
                // Registrar en historial_ajustes (NOFTRAB)
                $justificacion = "Cancelación automática de cuotas de financiamiento por rechazo de cuota inicial Pago ID: " . $pagoId;
                registrarAjuste($validadorId, 'pagos', 'pagos_plan_cancelar', $pago['poliza_id'], ['estado' => 'activo'], ['estado' => 'cancelado_rechazo_inicial'], $justificacion);
            }

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

    /**
     * Obtiene el token de acceso OAuth 2.0 para Google Cloud
     */
    private function obtenerVisionAccessToken($keyData) {
        $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
        $now = time();
        $payload = json_encode([
            'iss' => $keyData['client_email'],
            'sub' => $keyData['client_email'],
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
            'scope' => 'https://www.googleapis.com/auth/cloud-platform'
        ]);

        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

        $signatureInput = $base64UrlHeader . "." . $base64UrlPayload;
        $privateKey = $keyData['private_key'];

        $signature = '';
        if (!openssl_sign($signatureInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new Exception("Error al firmar JWT con OpenSSL.");
        }

        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        $jwt = $signatureInput . "." . $base64UrlSignature;

        // Exchange for OAuth token
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new Exception("Error en cURL al solicitar token: " . $err);
        }
        curl_close($ch);

        $resData = json_decode($response, true);
        if (isset($resData['access_token'])) {
            return $resData['access_token'];
        }

        throw new Exception("No se pudo obtener access token de Google: " . ($resData['error_description'] ?? $response));
    }

    /**
     * Envía la imagen de soporte a Google Cloud Vision API para extraer el texto (OCR)
     */
    public function analizarImagenOCR($imagePath) {
        try {
            $keyPath = dirname(__FILE__) . '/google-key.json';
            if (!file_exists($keyPath)) {
                throw new Exception("Archivo de llave google-key.json no encontrado.");
            }

            $keyData = json_decode(file_get_contents($keyPath), true);
            if (!$keyData || empty($keyData['private_key'])) {
                throw new Exception("Datos de credenciales de Google no válidos.");
            }

            // 1. Obtener Token de Acceso
            $accessToken = $this->obtenerVisionAccessToken($keyData);

            // 2. Leer imagen y codificar en base64
            $fullPath = dirname(__FILE__) . '/../' . $imagePath;
            if (!file_exists($fullPath)) {
                throw new Exception("Archivo de imagen no encontrado: " . $fullPath);
            }
            $imageData = base64_encode(file_get_contents($fullPath));

            // 3. Preparar llamada REST a Cloud Vision
            $payload = json_encode([
                'requests' => [
                    [
                        'image' => ['content' => $imageData],
                        'features' => [
                            ['type' => 'DOCUMENT_TEXT_DETECTION']
                        ]
                    ]
                ]
            ]);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://vision.googleapis.com/v1/images:annotate');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            if (curl_errno($ch)) {
                $err = curl_error($ch);
                curl_close($ch);
                throw new Exception("Error en cURL Vision API: " . $err);
            }
            curl_close($ch);

            $resData = json_decode($response, true);
            
            // Extraer el texto completo
            $fullText = '';
            if (isset($resData['responses'][0]['fullTextAnnotation']['text'])) {
                $fullText = $resData['responses'][0]['fullTextAnnotation']['text'];
            }

            return [
                'exito' => true,
                'texto' => $fullText,
                'raw' => $resData
            ];

        } catch (Exception $e) {
            error_log("Error en OCR Vision: " . $e->getMessage());
            return [
                'exito' => false,
                'mensaje' => $e->getMessage()
            ];
        }
    }

}
?>
