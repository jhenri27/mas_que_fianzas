<?php
require_once dirname(__FILE__) . '/config.php';
require_once dirname(__FILE__) . '/PolizaManager.php';

/**
 * Gestión de Cobranzas, Prorrata y Portal de Gestión de Cobros (PGC)
 * MAS QUE FIANZAS - Core Asegurador v3.0
 */
class CobroManager {
    private $db;

    public function __construct() {
        try {
            $this->db = Database::getInstance()->getConnection();
        } catch (Exception $e) {
            throw new Exception("Error al conectar en CobroManager: " . $e->getMessage());
        }
    }

    /**
     * Registra una gestión de cobro en la bitácora (NOFTRAB: Append-only)
     */
    public function registrarGestion($datos) {
        try {
            $polizaId = intval($datos['poliza_id']);
            $usuarioId = intval($datos['usuario_id']);
            $tipo = trim($datos['tipo_gestion']);
            $desc = trim($datos['descripcion'] ?? '');
            
            // Validación de longitud bajo NOFTRAB (mínimo 15 caracteres)
            if (strlen($desc) < 15) {
                return ['exito' => false, 'mensaje' => 'La descripción de la gestión de cobro debe tener al menos 15 caracteres para auditoría.'];
            }

            // Validar tipos de gestión válidos
            $tiposValidos = ['llamada', 'correo', 'whatsapp', 'visita', 'promesa_pago', 'bot_notificacion'];
            if (!in_array($tipo, $tiposValidos)) {
                return ['exito' => false, 'mensaje' => 'Tipo de gestión no válido.'];
            }

            $fechaPromesa = null;
            $montoPrometido = null;
            $estadoPromesa = 'n/a';

            if ($tipo === 'promesa_pago') {
                if (empty($datos['fecha_promesa']) || empty($datos['monto_prometido'])) {
                    return ['exito' => false, 'mensaje' => 'Para promesas de pago, es obligatorio ingresar fecha y monto comprometido.'];
                }
                $fechaPromesa = $datos['fecha_promesa'];
                $montoPrometido = floatval($datos['monto_prometido']);
                
                if (strtotime($fechaPromesa) < strtotime(date('Y-m-d'))) {
                    return ['exito' => false, 'mensaje' => 'La fecha de promesa de pago no puede estar en el pasado.'];
                }
                $estadoPromesa = 'pendiente';
            }

            $sql = "INSERT INTO cf_gestiones_cobro (
                        poliza_id, usuario_id, tipo_gestion, descripcion, 
                        fecha_promesa, monto_prometido, estado_promesa
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            if (!$stmt) {
                return ['exito' => false, 'mensaje' => 'Error de base de datos al preparar inserción.'];
            }

            $stmt->bind_param("iisssds", 
                $polizaId, $usuarioId, $tipo, $desc,
                $fechaPromesa, $montoPrometido, $estadoPromesa
            );

            if ($stmt->execute()) {
                $stmt->close();
                
                // Si es promesa de pago, registrar auditoría de ajuste
                if ($tipo === 'promesa_pago') {
                    $justificacion = "Registro de compromiso de pago del cliente por RD$ " . number_format($montoPrometido, 2) . " para la fecha $fechaPromesa";
                    registrarAjuste($usuarioId, 'cobros', 'cf_gestiones_cobro', $polizaId, ['estado' => 'sin_promesa'], ['estado' => 'promesa_registrada', 'fecha' => $fechaPromesa, 'monto' => $montoPrometido], $justificacion);
                }

                return ['exito' => true, 'mensaje' => 'Gestión de cobro registrada con éxito.'];
            } else {
                $stmt->close();
                return ['exito' => false, 'mensaje' => 'Error al insertar gestión: ' . $this->db->error];
            }

        } catch (Exception $e) {
            return ['exito' => false, 'mensaje' => $e->getMessage()];
        }
    }

    /**
     * Obtiene el historial de gestiones de una póliza (Inmutable)
     */
    public function obtenerHistorialGestiones($polizaId) {
        $sql = "SELECT g.*, u.nombre as gestor_nombre, u.apellido as gestor_apellido, u.username as gestor_username
                FROM cf_gestiones_cobro g
                JOIN usuarios u ON g.usuario_id = u.id
                WHERE g.poliza_id = ?
                ORDER BY g.fecha_gestion DESC";
        
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return [];
        $stmt->bind_param("i", $polizaId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Devuelve estadísticas, aging buckets y KPIs globales para el PGC
     */
    public function obtenerReporteProrataYFinanzas($usuario_id = null) {
        // 1. Obtener todas las pólizas activas con sus prorrata inyectada (filtrado si aplica)
        $polizaManager = new PolizaManager();
        $filtros = [];
        if ($usuario_id && restringirSoloPropios($usuario_id, 'pagos')) {
            $filtros['emitida_por'] = $usuario_id;
        }
        $todas = $polizaManager->obtenerPolizas($filtros);

        $totalReceivable = 0;
        $totalRiesgoTemerario = 0;
        $totalPrimaAcumulada = 0;

        $alertaBajo = 0;
        $alertaMedio = 0;
        $alertaCritico = 0;

        foreach ($todas as $p) {
            $totalReceivable += floatval($p['balance']);
            $totalPrimaAcumulada += floatval($p['prima_total']);
            
            if ($p['alerta_prorrata_nivel'] === 'critico') {
                $alertaCritico++;
                $totalRiesgoTemerario += floatval($p['balance']);
            } elseif ($p['alerta_prorrata_nivel'] === 'medio') {
                $alertaMedio++;
            } else {
                $alertaBajo++;
            }
        }

        // 2. DSO Promedio (Days Sales Outstanding)
        // DSO = (Receivable / Total Billed) * 365
        $dso = ($totalPrimaAcumulada > 0) ? round(($totalReceivable / $totalPrimaAcumulada) * 365, 0) : 0;

        // 3. Tasa de cumplimiento de promesas de pago (filtrado si aplica)
        $where_p = "WHERE tipo_gestion = 'promesa_pago'";
        $params_p = [];
        $types_p = "";
        if ($usuario_id && restringirSoloPropios($usuario_id, 'pagos')) {
            $where_p .= " AND usuario_id = ?";
            $params_p[] = $usuario_id;
            $types_p .= "i";
        }
        
        $sql_p = "SELECT 
                    SUM(CASE WHEN estado_promesa = 'cumplida' THEN 1 ELSE 0 END) as cumplidas,
                    SUM(CASE WHEN estado_promesa IN ('cumplida', 'incumplida') THEN 1 ELSE 0 END) as totales
                  FROM cf_gestiones_cobro 
                  $where_p";
        
        $stmt_p = $this->db->prepare($sql_p);
        if ($types_p) {
            $stmt_p->bind_param($types_p, ...$params_p);
        }
        $stmt_p->execute();
        $res_p = $stmt_p->get_result();
        
        $cumplimientoPromesas = 100;
        if ($res_p && $row = $res_p->fetch_assoc()) {
            $totales = intval($row['totales'] ?? 0);
            $cumplidas = intval($row['cumplidas'] ?? 0);
            if ($totales > 0) {
                $cumplimientoPromesas = round(($cumplidas / $totales) * 100, 1);
            }
        }
        if ($stmt_p) $stmt_p->close();

        // 4. Aging Buckets (Antigüedad de saldos vencidos en cuotas de pagos - filtrado si aplica)
        $where_aging = "WHERE pa.estado_pago = 'pendiente' AND pa.fecha_pago < DATE(NOW())";
        $params_ag = [];
        $types_ag = "";
        if ($usuario_id && restringirSoloPropios($usuario_id, 'pagos')) {
            $where_aging .= " AND po.emitida_por = ?";
            $params_ag[] = $usuario_id;
            $types_ag .= "i";
        }

        $sql_aging = "SELECT 
                        SUM(CASE WHEN DATEDIFF(NOW(), pa.fecha_pago) BETWEEN 1 AND 30 THEN pa.monto ELSE 0 END) as bucket_0_30,
                        SUM(CASE WHEN DATEDIFF(NOW(), pa.fecha_pago) BETWEEN 31 AND 60 THEN pa.monto ELSE 0 END) as bucket_31_60,
                        SUM(CASE WHEN DATEDIFF(NOW(), pa.fecha_pago) BETWEEN 61 AND 90 THEN pa.monto ELSE 0 END) as bucket_61_90,
                        SUM(CASE WHEN DATEDIFF(NOW(), pa.fecha_pago) > 90 THEN pa.monto ELSE 0 END) as bucket_90_mas
                      FROM pagos pa
                      INNER JOIN polizas po ON pa.poliza_id = po.id
                      $where_aging";
        
        $stmt_ag = $this->db->prepare($sql_aging);
        if ($types_ag) {
            $stmt_ag->bind_param($types_ag, ...$params_ag);
        }
        $stmt_ag->execute();
        $res_aging = $stmt_ag->get_result();
        
        $aging = [
            '0_30' => 0.00,
            '31_60' => 0.00,
            '61_90' => 0.00,
            '90_mas' => 0.00
        ];
        if ($res_aging && $row_ag = $res_aging->fetch_assoc()) {
            $aging['0_30'] = floatval($row_ag['bucket_0_30'] ?? 0);
            $aging['31_60'] = floatval($row_ag['bucket_31_60'] ?? 0);
            $aging['61_90'] = floatval($row_ag['bucket_61_90'] ?? 0);
            $aging['90_mas'] = floatval($row_ag['bucket_90_mas'] ?? 0);
        }
        if ($stmt_ag) $stmt_ag->close();

        return [
            'dso' => $dso,
            'aging_buckets' => [
                '0-30' => $aging['0_30'],
                '31-60' => $aging['31_60'],
                '61-90' => $aging['61_90'],
                '90+' => $aging['90_mas']
            ],
            'polizas' => $todas,
            'kpis' => [
                'dso' => $dso,
                'total_receivable' => $totalReceivable,
                'total_riesgo_temerario' => $totalRiesgoTemerario,
                'tasa_promesas' => $cumplimientoPromesas,
                'conteos_riesgo' => [
                    'bajo' => $alertaBajo,
                    'medio' => $alertaMedio,
                    'critico' => $alertaCritico
                ]
            ],
            'aging' => $aging
        ];
    }

    /**
     * Job diario automático para verificar y marcar promesas de pago vencidas como cumplidas o incumplidas
     */
    public function verificarPromesasVencidas() {
        // 1. Obtener todas las promesas de pago en estado 'pendiente' y cuya fecha promesa ya pasó (vencida)
        $sql = "SELECT id, poliza_id, fecha_promesa, monto_prometido 
                FROM cf_gestiones_cobro 
                WHERE tipo_gestion = 'promesa_pago' AND estado_promesa = 'pendiente' AND fecha_promesa <= DATE(NOW())";
        
        $res = $this->db->query($sql);
        if (!$res) return 0;

        $modificados = 0;
        while ($promesa = $res->fetch_assoc()) {
            $id = $promesa['id'];
            $polizaId = $promesa['poliza_id'];
            $fechaProm = $promesa['fecha_promesa'];
            $montoProm = floatval($promesa['monto_prometido']);

            // Verificar si hay algún pago procesado para esta póliza en la fecha_promesa o después
            // y que la fecha de registro del pago sea posterior a la fecha en que se registró la promesa
            $sql_pago = "SELECT SUM(monto) as total_pagado 
                         FROM pagos 
                         WHERE poliza_id = ? AND estado_pago = 'procesado' 
                           AND fecha_pago >= ? AND fecha_pago <= DATE_ADD(?, INTERVAL 3 DAY)";
            
            $stmt_p = $this->db->prepare($sql_pago);
            if ($stmt_p) {
                $stmt_p->bind_param("iss", $polizaId, $fechaProm, $fechaProm);
                $stmt_p->execute();
                $res_p = $stmt_p->get_result()->fetch_assoc();
                $totalPagado = floatval($res_p['total_pagado'] ?? 0);
                $stmt_p->close();

                // Si pagó al menos el 90% del monto prometido, la consideramos cumplida
                $estadoNuevo = ($totalPagado >= ($montoProm * 0.90)) ? 'cumplida' : 'incumplida';
                
                $sql_upd = "UPDATE cf_gestiones_cobro SET estado_promesa = ? WHERE id = ?";
                $stmt_upd = $this->db->prepare($sql_upd);
                if ($stmt_upd) {
                    $stmt_upd->bind_param("si", $estadoNuevo, $id);
                    $stmt_upd->execute();
                    $stmt_upd->close();
                    $modificados++;
                }
            }
        }
        return $modificados;
    }
}
?>
