<?php
require_once dirname(__FILE__) . '/config.php';
require_once dirname(__FILE__) . '/VehiculoManager.php';
require_once dirname(__FILE__) . '/ComisionManager.php';
require_once dirname(__FILE__) . '/AsientoContableManager.php';
require_once dirname(__FILE__) . '/DocumentoManager.php';

/**
 * Gestión Integral de Pólizas de Seguros y Fianzas
 * MAS QUE FIANZAS - Core Asegurador v3.0
 */
class PolizaManager {
    private $db;
    private $vehiculoManager;
    private $comisionManager;
    private $asientoManager;
    private $documentoManager;

    public function __construct() {
        try {
            $this->db = Database::getInstance()->getConnection();
            $this->vehiculoManager = new VehiculoManager();
            $this->comisionManager = new ComisionManager();
            $this->asientoManager = new AsientoContableManager();
            $this->documentoManager = new DocumentoManager();
        } catch (Exception $e) {
            throw new Exception("Error al inicializar PolizaManager: " . $e->getMessage());
        }
    }

    /**
     * Lista pólizas con filtros avanzados y datos relacionados
     */
    public function obtenerPolizas($filtros = []) {
        $whereClause = "1=1";
        $params = [];
        $types = "";

        if (!empty($filtros['search'])) {
            $search = "%" . $filtros['search'] . "%";
            $whereClause .= " AND (p.numero_poliza LIKE ? OR c.nombre LIKE ? OR v.placa LIKE ?)";
            $params[] = $search; $params[] = $search; $params[] = $search;
            $types .= "sss";
        }

        if (!empty($filtros['start_date']) && !empty($filtros['end_date'])) {
            $whereClause .= " AND p.fecha_emision BETWEEN ? AND ?";
            $params[] = $filtros['start_date'];
            $params[] = $filtros['end_date'] . ' 23:59:59';
            $types .= "ss";
        }

        if (!empty($filtros['estado'])) {
            $whereClause .= " AND p.estado = ?";
            $params[] = $filtros['estado'];
            $types .= "s";
        }

        if (!empty($filtros['emitida_por'])) {
            $whereClause .= " AND p.emitida_por = ?";
            $params[] = intval($filtros['emitida_por']);
            $types .= "i";
        }

        $sql = "SELECT p.*, 
                       c.nombre as cliente_nombre, c.cedula as cliente_cedula, c.email as cliente_email, c.telefono as cliente_telefono,
                       v.placa as vehiculo_placa, v.marca as vehiculo_marca, v.modelo as vehiculo_modelo,
                       v.anio as vehiculo_anio, v.chasis as vehiculo_chasis, v.uso as vehiculo_uso, v.tipo_vehiculo as vehiculo_tipo_vehiculo,
                       (SELECT SUM(monto) FROM pagos WHERE poliza_id = p.id AND estado_pago = 'procesado') as total_pagado
                FROM polizas p 
                LEFT JOIN clientes c ON p.cliente_id = c.id 
                LEFT JOIN vehiculos v ON p.vehiculo_id = v.id
                WHERE $whereClause 
                ORDER BY p.fecha_emision DESC";

        $stmt = $this->db->prepare($sql);
        if (!empty($params)) {
             $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $polizas = [];
        
        while ($row = $result->fetch_assoc()) {
            $this->inyectarProrrataInterna($row);
            $polizas[] = $row;
        }
        
        return $polizas;
    }

    /**
     * Obtiene el detalle completo de una póliza específica
     */
    public function obtenerPolizaDetalle($id) {
        $sql = "SELECT p.*, 
                       c.nombre as cliente_nombre, c.cedula as cliente_cedula, c.email as cliente_email, c.telefono as cliente_telefono,
                       v.placa as vehiculo_placa, v.marca as vehiculo_marca, v.modelo as vehiculo_modelo,
                       v.anio as vehiculo_anio, v.chasis as vehiculo_chasis, v.uso as vehiculo_uso, v.tipo_vehiculo as vehiculo_tipo_vehiculo
                FROM polizas p 
                JOIN clientes c ON p.cliente_id = c.id
                LEFT JOIN vehiculos v ON p.vehiculo_id = v.id
                WHERE p.id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $poliza = $stmt->get_result()->fetch_assoc();

        if ($poliza) {
            $poliza['vehiculo'] = $this->vehiculoManager->obtenerVehiculo($poliza['vehiculo_id']);
            $poliza['comisiones'] = $this->comisionManager->listarComisiones(['poliza_id' => $id]);
            $this->inyectarProrrataInterna($poliza);
            // Otros relacionados...
        }

        return $poliza;
    }

    /**
     * Workflow Principal: Emisión de Póliza
     */
    public function emitirPoliza($datos) {
        $this->db->begin_transaction();
        try {
            // 1. Gestionar Vehículo (si aplica)
            $vehiculoId = null;
            if (!empty($datos['vehiculo'])) {
                $vehData = $datos['vehiculo'];
                $vehData['cliente_id'] = $datos['cliente_id'];
                $vehData['creado_por'] = $datos['emitida_por'];
                $resVeh = $this->vehiculoManager->crearOActualizarVehiculo($vehData);
                if ($resVeh['exito']) {
                    $vehiculoId = $resVeh['id'];
                }
            }

            // 2. Insertar Póliza
            $sql = "INSERT INTO polizas (
                        numero_poliza, numero_poliza_aseguradora, cotizacion_id, cliente_id, vehiculo_id,
                        tipo_seguro, tipo_poliza, ramo, aseguradora, perfil_cobertura,
                        prima_total, prima_neta, itbis, otros_cargos,
                        periodicidad_pago, cuota_total, fecha_vencimiento, estado, emitida_por, fecha_emision
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            if (!$stmt) throw new Exception($this->db->error);

            $num = $datos['numero_poliza'] ?? ('POL-' . date('Y') . '-' . rand(1000, 9999));
            $num_aseg = $datos['numero_poliza_aseguradora'] ?? null;
            $cot_id = $datos['cotizacion_id'] ?? null;
            $cli_id = $datos['cliente_id'];
            $tipo_seguro = $datos['tipo_seguro'];
            $tipo_poliza = $datos['tipo_poliza'] ?? 'Individual';
            $ramo = $datos['ramo'] ?? 'Vehículos de Motor';
            $aseguradora = $datos['aseguradora'] ?? 'MULTISEGUROS';
            $perfil = $datos['perfil_cobertura'] ?? 'Seguro de Ley';
            $total = floatval($datos['prima_total']);
            if (isset($datos['prima_neta']) && floatval($datos['prima_neta']) > 0) {
                $neta = round(floatval($datos['prima_neta']), 2);
                $itbis = isset($datos['itbis']) ? round(floatval($datos['itbis']), 2) : round($neta * 0.16, 2);
                $total = isset($datos['prima_total']) ? round(floatval($datos['prima_total']), 2) : round($neta + $itbis, 2);
            } else {
                $total = round(floatval($datos['prima_total']), 2);
                $neta = round($total / 1.16, 2);
                $itbis = round($total - $neta, 2);
            }
            $otros = 0;
            $periodo = $datos['periodicidad_pago'] ?? 'anual';
            $cuota_total = intval($datos['cuota_total'] ?? 1); 
            $vence = $datos['fecha_vencimiento'];
            $estado = $datos['estado'] ?? 'activa';
            $emitida_por = $datos['emitida_por'];
            $fecha_em = date('Y-m-d');

            $stmt->bind_param("ssiiisssssddddsissis", 
                $num, $num_aseg, $cot_id, $cli_id, $vehiculoId,
                $tipo_seguro, $tipo_poliza, $ramo, $aseguradora, $perfil,
                $total, $neta, $itbis, $otros,
                $periodo, $cuota_total, $vence, $estado, $emitida_por, $fecha_em
            );
            
            if (!$stmt->execute()) throw new Exception($stmt->error);
            $polizaId = $this->db->insert_id;

            // 3. Calcular y Registrar Comisiones
            // Se calcula sobre la prima neta confirmada por el usuario
            $this->comisionManager->calcularYRegistrarComisiones($polizaId, $emitida_por, $neta);

            // 4. Registrar Asiento Contable de Emisión
            // DÉBITO: Primas por Cobrar / CRÉDITO: Ingreso y Pasivo ITBIS
            $this->asientoManager->registrarAsiento([
                'descripcion' => "Emisión de Póliza $num - Cliente ID: $cli_id",
                'modulo' => 'polizas',
                'ref_id' => $polizaId,
                'ref_tipo' => 'poliza',
                'user_id' => $emitida_por,
                'lineas' => [
                    ['cuenta' => '1.1.02.01', 'nombre' => 'Primas por Cobrar - Vigentes', 'tipo' => 'debito', 'monto' => $total, 'glosa' => 'Cuentas por cobrar clientes'],
                    ['cuenta' => '4.1.01.01', 'nombre' => 'Primas Netas de Seguros - Automóviles', 'tipo' => 'credito', 'monto' => $neta, 'glosa' => 'Ingreso por prima neta'],
                    ['cuenta' => '2.1.03.01', 'nombre' => 'ISC por Pagar (16%)', 'tipo' => 'credito', 'monto' => $itbis, 'glosa' => 'ISC Ley 146-02 16%']
                ]
            ]);

            $this->db->commit();
            
            // Auditoría (NOFTRAB)
            if (function_exists('logAudit')) {
                logAudit(
                    $emitida_por, 
                    'emision_poliza', 
                    'polizas', 
                    'emitirPoliza', 
                    "Póliza emitida exitosamente. Número: $num", 
                    'exitoso', 
                    null, 
                    'polizas', 
                    $polizaId, 
                    null, 
                    $datos
                );
            }

            return ['exito' => true, 'id' => $polizaId, 'numero' => $num];
        } catch (Exception $e) {
            $this->db->rollback();
            return ['exito' => false, 'mensaje' => $e->getMessage()];
        }
    }

    /**
     * Valida una póliza (aprobación técnica)
     */
    public function validarPoliza($id, $userId) {
        $sql = "UPDATE polizas SET validada = 'Si', validada_por = ?, fecha_validacion = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("ii", $userId, $id);
        $res = $stmt->execute();
        if ($res && function_exists('logAudit')) {
            logAudit(
                $userId,
                'validacion_poliza',
                'polizas',
                'validarPoliza',
                "Póliza ID $id aprobada/validada técnicamente",
                'exitoso',
                null,
                'polizas',
                $id,
                ['validada' => 'No'],
                ['validada' => 'Si', 'validada_por' => $userId]
            );
        }
        return $res;
    }

    /**
     * Cambia el estado de una póliza
     */
    public function cambiarEstado($id, $nuevoEstado) {
        // Obtener estado anterior
        $prevEstado = null;
        $stmt_prev = $this->db->prepare("SELECT estado FROM polizas WHERE id = ?");
        if ($stmt_prev) {
            $stmt_prev->bind_param("i", $id);
            $stmt_prev->execute();
            $res_prev = $stmt_prev->get_result()->fetch_assoc();
            $prevEstado = $res_prev['estado'] ?? null;
            $stmt_prev->close();
        }

        $sql = "UPDATE polizas SET estado = ? WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("si", $nuevoEstado, $id);
        $res = $stmt->execute();
        if ($res && function_exists('logAudit')) {
            $userId = $_SESSION['usuario_id'] ?? null;
            logAudit(
                $userId,
                'cambio_estado_poliza',
                'polizas',
                'cambiarEstado',
                "Póliza ID $id cambió de estado a $nuevoEstado",
                'exitoso',
                null,
                'polizas',
                $id,
                ['estado' => $prevEstado],
                ['estado' => $nuevoEstado]
            );
        }
        return $res;
    }

    /**
     * Calculates dynamic internal prorata fields for a policy
     */
    private function inyectarProrrataInterna(&$poliza) {
        $fechaInicio = $poliza['fecha_emision'];
        $fechaVence = $poliza['fecha_vencimiento'];
        $primaTotal = floatval($poliza['prima_total']);
        
        if (!isset($poliza['total_pagado'])) {
            $sql = "SELECT SUM(monto) as total FROM pagos WHERE poliza_id = ? AND estado_pago = 'procesado'";
            $stmt = $this->db->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("i", $poliza['id']);
                $stmt->execute();
                $poliza['total_pagado'] = floatval($stmt->get_result()->fetch_assoc()['total'] ?? 0);
                $stmt->close();
            } else {
                $poliza['total_pagado'] = 0.00;
            }
        }
        
        $totalPagado = floatval($poliza['total_pagado']);
        $poliza['balance'] = $primaTotal - $totalPagado;

        $d1 = new DateTime($fechaInicio);
        $d2 = new DateTime($fechaVence);
        $diffTotal = $d1->diff($d2);
        $diasVigenciaTotal = $diffTotal->days;
        if ($diasVigenciaTotal <= 0) $diasVigenciaTotal = 365;

        $valorDia = $primaTotal / $diasVigenciaTotal;
        $poliza['valor_dia'] = round($valorDia, 2);

        $diasPagados = ($valorDia > 0) ? floor($totalPagado / $valorDia) : 0;
        $poliza['dias_pagados'] = intval($diasPagados);

        $fechaVenceProrrata = date('Y-m-d', strtotime($fechaInicio . " + " . intval($diasPagados) . " days"));
        $poliza['fecha_vencimiento_prorrata'] = $fechaVenceProrrata;

        $hoy = new DateTime(date('Y-m-d'));
        $diasTranscurridos = 0;
        if ($hoy >= $d1) {
            $diffTranscurridos = $d1->diff($hoy);
            $diasTranscurridos = $diffTranscurridos->days;
        }
        $poliza['dias_transcurridos'] = intval($diasTranscurridos);

        $diasRestantes = $diasPagados - $diasTranscurridos;
        $poliza['dias_cobertura_restante_prorrata'] = intval($diasRestantes);

        if ($diasRestantes > 15) {
            $poliza['alerta_prorrata_nivel'] = 'bajo';
        } elseif ($diasRestantes >= 0) {
            $poliza['alerta_prorrata_nivel'] = 'medio';
        } else {
            $poliza['alerta_prorrata_nivel'] = 'critico'; // Tiempo Temerario
        }
        $poliza['riesgo_prorrata'] = $poliza['alerta_prorrata_nivel'];
    }

    /**
     * Calcula la prorrata económica al momento de la cancelación de una póliza
     */
    public function calcularProrrataCancelacion($polizaId, $fechaCancelacion = null) {
        $sql = "SELECT p.*, 
                       (SELECT SUM(monto) FROM pagos WHERE poliza_id = p.id AND estado_pago = 'procesado') as total_pagado
                FROM polizas p 
                WHERE p.id = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            throw new Exception("Error al preparar la consulta de prorrata.");
        }
        $stmt->bind_param("i", $polizaId);
        $stmt->execute();
        $poliza = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$poliza) {
            throw new Exception("Póliza no encontrada.");
        }

        $fechaInicio = $poliza['fecha_emision'];
        $fechaVence = $poliza['fecha_vencimiento'];
        $primaTotal = floatval($poliza['prima_total']);
        $totalPagado = floatval($poliza['total_pagado'] ?? 0);

        if (!$fechaCancelacion) {
            $fechaCancelacion = date('Y-m-d');
        }

        // Convertir a DateTime para cálculos precisos
        $d1 = new DateTime($fechaInicio);
        $d2 = new DateTime($fechaVence);
        $dc = new DateTime($fechaCancelacion);

        $diasVigenciaTotal = $d1->diff($d2)->days;
        if ($diasVigenciaTotal <= 0) $diasVigenciaTotal = 365;

        // Limitar la fecha de cancelación al rango de la póliza
        if ($dc < $d1) {
            $diasTranscurridos = 0;
        } elseif ($dc > $d2) {
            $diasTranscurridos = $diasVigenciaTotal;
        } else {
            $diasTranscurridos = $d1->diff($dc)->days;
        }

        $diasRestantes = $diasVigenciaTotal - $diasTranscurridos;

        $valorDia = $primaTotal / $diasVigenciaTotal;
        $primaDevengada = round($valorDia * $diasTranscurridos, 2);
        
        // Devolución: lo que el cliente pagó menos lo que consumió en días
        $saldoDevolucion = round($totalPagado - $primaDevengada, 2);

        return [
            'poliza_id' => $polizaId,
            'numero_poliza' => $poliza['numero_poliza'],
            'aseguradora' => $poliza['aseguradora'],
            'ramo' => $poliza['ramo'],
            'fecha_emision' => $fechaInicio,
            'fecha_vencimiento' => $fechaVence,
            'fecha_cancelacion' => $fechaCancelacion,
            'dias_vigencia_total' => $diasVigenciaTotal,
            'dias_transcurridos' => $diasTranscurridos,
            'dias_restantes' => $diasRestantes,
            'prima_total' => $primaTotal,
            'total_pagado' => $totalPagado,
            'prima_devengada' => $primaDevengada,
            'saldo_devolucion' => $saldoDevolucion
        ];
    }

    /**
     * Ejecuta la cancelación de una póliza en la base de datos con ajuste de cobros y log de auditoría
     */
    public function cancelarPoliza($polizaId, $justificacion, $tipoCancelacion, $usuarioId, $fechaCancelacion = null, $notificar = true) {
        $this->db->begin_transaction();

        try {
            // 1. Calcular la prorrata
            $prorrata = $this->calcularProrrataCancelacion($polizaId, $fechaCancelacion);

            // 2. Modificar el estado de la póliza y excluirla del bot de cobros
            $sql_upd = "UPDATE polizas SET estado = 'cancelada', bot_excluir = 1 WHERE id = ?";
            $stmt_upd = $this->db->prepare($sql_upd);
            if (!$stmt_upd) {
                throw new Exception("Error al preparar la actualización de la póliza.");
            }
            $stmt_upd->bind_param("i", $polizaId);
            $stmt_upd->execute();
            $stmt_upd->close();

            // 3. Registrar en cancelaciones_polizas
            $notifList = [];
            if ($notificar) {
                $notifList[] = 'cliente';
                $notifList[] = 'corredor';
                $notifList[] = 'aseguradora';
                $notifList[] = 'plataforma';
            }
            $notifStr = implode(',', $notifList);

            $sql_ins = "INSERT INTO cancelaciones_polizas 
                (poliza_id, tipo_cancelacion, cancelado_por, justificacion, dias_transcurridos, dias_restantes, prima_total, total_pagado, prima_devengada, saldo_devolucion, notificaciones_enviadas)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt_ins = $this->db->prepare($sql_ins);
            if (!$stmt_ins) {
                throw new Exception("Error al preparar el registro de cancelación.");
            }
            $stmt_ins->bind_param(
                "isisiidddds", 
                $polizaId, 
                $tipoCancelacion, 
                $usuarioId, 
                $justificacion, 
                $prorrata['dias_transcurridos'], 
                $prorrata['dias_restantes'], 
                $prorrata['prima_total'], 
                $prorrata['total_pagado'], 
                $prorrata['prima_devengada'], 
                $prorrata['saldo_devolucion'],
                $notifStr
            );
            $stmt_ins->execute();
            $stmt_ins->close();

            // 4. Registrar auditoría (logAudit)
            if (function_exists('logAudit')) {
                logAudit(
                    $usuarioId,
                    'cancelacion_poliza',
                    'polizas',
                    'cancelarPoliza',
                    "Póliza N° {$prorrata['numero_poliza']} cancelada (" . strtoupper($tipoCancelacion) . "). Justificación: {$justificacion}",
                    'exitoso',
                    null,
                    'polizas',
                    $polizaId,
                    ['estado' => 'activa'],
                    ['estado' => 'cancelada', 'bot_excluir' => 1]
                );
            }

            // 5. Enviar notificaciones si está habilitado
            if ($notificar) {
                $this->enviarNotificacionesCancelacion($prorrata, $justificacion);
            }

            $this->db->commit();
            return ['exito' => true, 'mensaje' => "Póliza cancelada exitosamente.", 'prorrata' => $prorrata];

        } catch (\Throwable $e) {
            $this->db->rollback();
            return ['exito' => false, 'mensaje' => "Error al cancelar la póliza: " . $e->getMessage()];
        }
    }

    /**
     * Envía notificaciones electrónicas corporativas a los involucrados en la cancelación de la póliza
     */
    private function enviarNotificacionesCancelacion($prorrata, $justificacion) {
        // Consultar el correo del cliente y del vendedor
        $sql = "SELECT c.nombre as cliente_nombre, c.email as cliente_email,
                       u.nombre as vendedor_nombre, u.apellido as vendedor_apellido, u.email as vendedor_email
                FROM polizas p
                LEFT JOIN clientes c ON p.cliente_id = c.id
                LEFT JOIN usuarios u ON p.emitida_por = u.id
                WHERE p.id = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $prorrata['poliza_id']);
            $stmt->execute();
            $datos = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if ($datos) {
                require_once __DIR__ . '/Mailer.php';
                $mailer = new Mailer();

                $numPoliza = $prorrata['numero_poliza'];
                $subject = "AVISO OFICIAL: Póliza N° {$numPoliza} Cancelada / Anulada";

                $devolucionStr = $prorrata['saldo_devolucion'] >= 0 
                    ? "Monto a Devolver: RD$ " . number_format($prorrata['saldo_devolucion'], 2)
                    : "Saldo Deudor Pendiente: RD$ " . number_format(abs($prorrata['saldo_devolucion']), 2);

                $html = "
                <html>
                <body style=\"font-family: Arial, sans-serif; color: #333; line-height: 1.6;\">
                    <div style=\"max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px;\">
                        <h2 style=\"color: #dc2626; border-bottom: 2px solid #dc2626; padding-bottom: 10px;\">Notificación de Cancelación de Póliza</h2>
                        <p>Estimado(a) <strong>{$datos['cliente_nombre']}</strong>,</p>
                        <p>Le informamos que de acuerdo con los registros de la plataforma de seguros <strong>Más Que Fianzas</strong>, su póliza ha sido cancelada formalmente.</p>
                        
                        <div style=\"background: #f8fafc; border-left: 4px solid #dc2626; padding: 15px; margin: 20px 0;\">
                            <strong>Detalles de la Cancelación:</strong><br>
                            - Póliza N°: {$numPoliza}<br>
                            - Aseguradora: {$prorrata['aseguradora']}<br>
                            - Ramo: {$prorrata['ramo']}<br>
                            - Fecha de Cancelación: {$prorrata['fecha_cancelacion']}<br>
                            - Días de Cobertura Consumidos: {$prorrata['dias_transcurridos']} días<br>
                            - {$devolucionStr}<br>
                            - <strong>Motivo/Justificación:</strong> {$justificacion}
                        </div>

                        <p>Si la póliza fue cancelada por falta de pago y desea reactivar su cobertura, por favor comuníquese de inmediato con su asesor de seguros asignado:</p>
                        <p><strong>Asesor:</strong> {$datos['vendedor_nombre']} {$datos['vendedor_apellido']}<br>
                           <strong>Email:</strong> {$datos['vendedor_email']}</p>
                        
                        <p style=\"font-size: 11px; color: #64748b; margin-top: 30px; border-top: 1px solid #e2e8f0; padding-top: 10px;\">
                            Este es un mensaje automático de control oficial. Por favor no responda directamente a este correo.
                        </p>
                    </div>
                </body>
                </html>";

                // Enviar al cliente
                if (!empty($datos['cliente_email']) && filter_var($datos['cliente_email'], FILTER_VALIDATE_EMAIL)) {
                    $mailer->enviar($datos['cliente_email'], $subject, $html, true);
                }
                // Enviar copia al agente corredor
                if (!empty($datos['vendedor_email']) && filter_var($datos['vendedor_email'], FILTER_VALIDATE_EMAIL)) {
                    $mailer->enviar($datos['vendedor_email'], "[Copia Corredor] " . $subject, $html, true);
                }
            }
        }
    }
}
?>
