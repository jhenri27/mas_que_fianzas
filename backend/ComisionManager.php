<?php
require_once dirname(__FILE__) . '/config.php';

/**
 * Gestión y Cálculo de Comisiones (Intermediarios y Supervisores)
 * MAS QUE FIANZAS - v3.0
 */
class ComisionManager {
    private $db;

    public function __construct() {
        try {
            $this->db = Database::getInstance()->getConnection();
        } catch (Exception $e) {
            throw new Exception("Error de conexión a la base de datos: " . $e->getMessage());
        }
    }

    /**
     * Calcula y registra las comisiones correspondientes a una póliza emitida/cobrada
     * @param int $polizaId ID de la póliza
     * @param int $vendedorId ID del usuario que realizó la venta
     * @param float $primaNeta Base para el cálculo (Prima total sin ITBIS)
     */
    public function calcularYRegistrarComisiones($polizaId, $vendedorId, $primaNeta) {
        // Obtener tipo de seguro de la póliza
        $sql_poliza = "SELECT tipo_seguro FROM polizas WHERE id = ?";
        $stmt_poliza = $this->db->prepare($sql_poliza);
        $tipoSeguro = '';
        if ($stmt_poliza) {
            $stmt_poliza->bind_param("i", $polizaId);
            $stmt_poliza->execute();
            $poliza = $stmt_poliza->get_result()->fetch_assoc();
            $tipoSeguro = $poliza ? ($poliza['tipo_seguro'] ?? '') : '';
            $stmt_poliza->close();
        }

        // Obtener datos del vendedor (incluyendo comisiones por ramos) y su posible supervisor (referente)
        $sql = "SELECT u.id, u.porcentaje_comision, u.referente_id, 
                       u.comision_autos_ley, u.comision_autos_full, u.comision_fianzas,
                       u.comision_incendio, u.comision_rc, u.comision_otros,
                       s.porcentaje_comision_red as porc_red
                FROM usuarios u
                LEFT JOIN usuarios s ON u.referente_id = s.id
                WHERE u.id = ?";
        
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return false;

        $stmt->bind_param("i", $vendedorId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) return false;

        $registros = 0;

        // 1. Comisión del Vendedor (Intermediario)
        // Resolver porcentaje de comisión específico por ramo o fallback
        $porcVendedor = 0.00;
        $tipoSeguroNorm = strtolower($tipoSeguro);
        
        if (strpos($tipoSeguroNorm, 'fianza') !== false) {
            $porcVendedor = floatval($user['comision_fianzas'] ?? 0);
        } elseif (strpos($tipoSeguroNorm, 'ley') !== false) {
            $porcVendedor = floatval($user['comision_autos_ley'] ?? 0);
        } elseif (strpos($tipoSeguroNorm, 'full') !== false || strpos($tipoSeguroNorm, 'vehiculo') !== false || strpos($tipoSeguroNorm, 'auto') !== false) {
            $porcVendedor = floatval($user['comision_autos_full'] ?? 0);
        } elseif (strpos($tipoSeguroNorm, 'incendio') !== false) {
            $porcVendedor = floatval($user['comision_incendio'] ?? 0);
        } elseif (strpos($tipoSeguroNorm, 'responsabilidad') !== false || strpos($tipoSeguroNorm, 'rc') !== false) {
            $porcVendedor = floatval($user['comision_rc'] ?? 0);
        } else {
            $porcVendedor = floatval($user['comision_otros'] ?? 0);
        }
        
        // Retrocompatibilidad: Si el porcentaje resuelto es 0.00, usar el general plano
        if ($porcVendedor <= 0.00) {
            $porcVendedor = floatval($user['porcentaje_comision'] ?? 0);
        }

        if ($porcVendedor > 0) {
            $monto = $primaNeta * ($porcVendedor / 100);
            if ($this->insertarComision($polizaId, $vendedorId, 'intermediario', $porcVendedor, $primaNeta, $monto)) {
                $registros++;
            }
        }

        // 2. Comisión del Supervisor (Red/Gerencia)
        // El supervisor siempre es el 'referente_id' del vendedor
        $supervisorId = $user['referente_id'];
        $porcRed = floatval($user['porc_red'] ?? 0);
        
        if ($supervisorId && $porcRed > 0) {
            $montoRed = $primaNeta * ($porcRed / 100);
            if ($this->insertarComision($polizaId, $supervisorId, 'supervisor', $porcRed, $primaNeta, $montoRed)) {
                $registros++;
            }
        }

        return $registros > 0;
    }

    /**
     * Inserta el registro de comisión en la base de datos
     */
    private function insertarComision($polizaId, $usuarioId, $tipo, $porcentaje, $base, $monto) {
        $sql = "INSERT INTO comisiones_poliza (
                    poliza_id, usuario_id, tipo_comision, 
                    porcentaje_comision, monto_base, monto_comision, 
                    estado_pago
                ) VALUES (?, ?, ?, ?, ?, ?, 'pendiente')";
        
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return false;

        $stmt->bind_param("iisddd", $polizaId, $usuarioId, $tipo, $porcentaje, $base, $monto);
        return $stmt->execute();
    }

    /**
     * Lista comisiones según filtros (para reportes o panel de pagos)
     */
    public function listarComisiones($filtros = []) {
        $where = "1=1";
        $params = [];
        $types = "";

        if (!empty($filtros['usuario_id'])) {
            $where .= " AND c.usuario_id = ?";
            $params[] = $filtros['usuario_id'];
            $types .= "i";
        }

        if (!empty($filtros['estado_pago'])) {
            $where .= " AND c.estado_pago = ?";
            $params[] = $filtros['estado_pago'];
            $types .= "s";
        }

        $sql = "SELECT c.*, u.nombre as usuario_nombre, p.numero_poliza, p.tipo_seguro
                FROM comisiones_poliza c
                JOIN usuarios u ON c.usuario_id = u.id
                JOIN polizas p ON c.poliza_id = p.id
                WHERE $where
                ORDER BY c.fecha_calculo DESC";
        
        $stmt = $this->db->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // =========================================================================
    // PANEL DE COMISIONES — Métodos v1.0
    // =========================================================================

    /**
     * KPIs del panel: cobrado, tránsito, proyección, total_polizas, mes, anio.
     *
     * @param int  $uid    ID del usuario objetivo
     * @param int  $mes    Mes (1-12)
     * @param int  $anio   Año (ej. 2025)
     * @param bool $global Si true ignora filtro por usuario
     */
    public function obtenerPanelResumen(int $uid, int $mes, int $anio, bool $global): array {
        $filtroUsuario = $global ? "" : "AND cp.usuario_id = ?";

        // ── Cobrado: pagos procesados en el mes ──────────────────────────────
        $sqlCobrado = "SELECT COALESCE(SUM(cp.monto_comision), 0) AS cobrado
                       FROM comisiones_poliza cp
                       JOIN pagos pg ON pg.poliza_id = cp.poliza_id
                       WHERE pg.estado_pago  = 'procesado'
                         AND MONTH(pg.fecha_pago) = ?
                         AND YEAR(pg.fecha_pago)  = ?
                         $filtroUsuario";

        $stmtC = $this->db->prepare($sqlCobrado);
        if ($global) {
            $stmtC->bind_param("ii", $mes, $anio);
        } else {
            $stmtC->bind_param("iii", $mes, $anio, $uid);
        }
        $stmtC->execute();
        $cobrado = (float)($stmtC->get_result()->fetch_assoc()['cobrado'] ?? 0);
        $stmtC->close();

        // ── Tránsito: comisiones con pagos registrados/pendientes ese mes ────
        $sqlTransito = "SELECT COALESCE(SUM(cp.monto_comision), 0) AS transito
                        FROM comisiones_poliza cp
                        WHERE EXISTS (
                            SELECT 1 FROM pagos pg
                            WHERE pg.poliza_id    = cp.poliza_id
                              AND pg.estado_pago IN ('registrado', 'pendiente')
                              AND MONTH(pg.fecha_pago) = ?
                              AND YEAR(pg.fecha_pago)  = ?
                        )
                        $filtroUsuario";

        $stmtT = $this->db->prepare($sqlTransito);
        if ($global) {
            $stmtT->bind_param("ii", $mes, $anio);
        } else {
            $stmtT->bind_param("iii", $mes, $anio, $uid);
        }
        $stmtT->execute();
        $transito = (float)($stmtT->get_result()->fetch_assoc()['transito'] ?? 0);
        $stmtT->close();

        // ── Total pólizas del mes ────────────────────────────────────────────
        $filtroUsuarioP = $global ? "" : "AND cp.usuario_id = ?";
        $sqlPolizas = "SELECT COUNT(DISTINCT p.id) AS total
                       FROM polizas p
                       JOIN comisiones_poliza cp ON cp.poliza_id = p.id
                       WHERE MONTH(p.fecha_emision) = ?
                         AND YEAR(p.fecha_emision)  = ?
                         $filtroUsuarioP";

        $stmtP = $this->db->prepare($sqlPolizas);
        if ($global) {
            $stmtP->bind_param("ii", $mes, $anio);
        } else {
            $stmtP->bind_param("iii", $mes, $anio, $uid);
        }
        $stmtP->execute();
        $totalPolizas = (int)($stmtP->get_result()->fetch_assoc()['total'] ?? 0);
        $stmtP->close();

        return [
            'cobrado'       => $cobrado,
            'transito'      => $transito,
            'proyeccion'    => $cobrado + $transito,
            'total_polizas' => $totalPolizas,
            'mes'           => $mes,
            'anio'          => $anio,
        ];
    }

    /**
     * Lista pólizas del mes con datos de comisión, cliente y agente.
     */
    public function obtenerPolizasConComision(int $uid, int $mes, int $anio, bool $global): array {
        $filtroUsuario = $global ? "" : "AND cp.usuario_id = ?";

        $sql = "SELECT
                    p.id                  AS poliza_id,
                    p.numero_poliza,
                    p.tipo_seguro,
                    p.prima_neta,
                    p.fecha_emision,
                    p.fecha_vencimiento,
                    p.estado              AS estado_poliza,
                    cp.monto_comision,
                    cp.porcentaje_comision,
                    cp.tipo_comision,
                    cp.estado_pago        AS estado_pago_comision,
                    cl.nombre             AS nombre_asegurado,
                    u.nombre              AS nombre_agente
                FROM polizas p
                JOIN comisiones_poliza cp ON cp.poliza_id = p.id
                JOIN clientes cl          ON cl.id        = p.cliente_id
                JOIN usuarios u           ON u.id         = cp.usuario_id
                WHERE MONTH(p.fecha_emision) = ?
                  AND YEAR(p.fecha_emision)  = ?
                  $filtroUsuario
                ORDER BY p.fecha_emision DESC";

        $stmt = $this->db->prepare($sql);
        if ($global) {
            $stmt->bind_param("ii", $mes, $anio);
        } else {
            $stmt->bind_param("iii", $mes, $anio, $uid);
        }
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Pólizas del mes con comisión pendiente de cobro (cuentas por cobrar).
     * Incluye datos del pago pendiente asociado.
     */
    public function obtenerCuentasPorCobrar(int $uid, int $mes, int $anio, bool $global): array {
        $filtroUsuario = $global ? "" : "AND cp.usuario_id = ?";

        $sql = "SELECT
                    p.id                  AS poliza_id,
                    p.numero_poliza,
                    p.tipo_seguro,
                    p.prima_neta,
                    p.fecha_emision,
                    p.fecha_vencimiento,
                    p.estado              AS estado_poliza,
                    cp.monto_comision,
                    cp.porcentaje_comision,
                    cp.tipo_comision,
                    cl.nombre             AS nombre_asegurado,
                    u.nombre              AS nombre_agente,
                    pg.monto              AS monto_pago_pendiente,
                    pg.tipo_pago,
                    pg.fecha_pago         AS fecha_registro_pago,
                    pg.estado_pago        AS estado_pago
                FROM polizas p
                JOIN comisiones_poliza cp ON cp.poliza_id = p.id
                                        AND cp.estado_pago = 'pendiente'
                JOIN clientes cl          ON cl.id         = p.cliente_id
                JOIN usuarios u           ON u.id          = cp.usuario_id
                LEFT JOIN pagos pg        ON pg.poliza_id  = p.id
                                        AND pg.estado_pago IN ('registrado','pendiente')
                WHERE MONTH(p.fecha_emision) = ?
                  AND YEAR(p.fecha_emision)  = ?
                  $filtroUsuario
                ORDER BY p.fecha_emision DESC";

        $stmt = $this->db->prepare($sql);
        if ($global) {
            $stmt->bind_param("ii", $mes, $anio);
        } else {
            $stmt->bind_param("iii", $mes, $anio, $uid);
        }
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Proyección mensual: cobrado, pendiente, proyeccion_total,
     * porcentaje_cobrado y breakdown por tipo_comision.
     */
    public function obtenerProyeccionMensual(int $uid, int $mes, int $anio, bool $global): array {
        $filtroUsuario = $global ? "" : "AND cp.usuario_id = ?";

        // ── Cobrado por tipo ─────────────────────────────────────────────────
        $sqlCobrado = "SELECT cp.tipo_comision,
                              COALESCE(SUM(cp.monto_comision), 0) AS monto
                       FROM comisiones_poliza cp
                       JOIN pagos pg ON pg.poliza_id = cp.poliza_id
                       WHERE pg.estado_pago  = 'procesado'
                         AND MONTH(pg.fecha_pago) = ?
                         AND YEAR(pg.fecha_pago)  = ?
                         $filtroUsuario
                       GROUP BY cp.tipo_comision";

        $stmtC = $this->db->prepare($sqlCobrado);
        if ($global) {
            $stmtC->bind_param("ii", $mes, $anio);
        } else {
            $stmtC->bind_param("iii", $mes, $anio, $uid);
        }
        $stmtC->execute();
        $rowsCobrado = $stmtC->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtC->close();

        // ── Pendiente por tipo ───────────────────────────────────────────────
        $sqlPendiente = "SELECT cp.tipo_comision,
                                COALESCE(SUM(cp.monto_comision), 0) AS monto
                         FROM comisiones_poliza cp
                         JOIN polizas p ON p.id = cp.poliza_id
                         WHERE cp.estado_pago != 'procesado'
                           AND MONTH(p.fecha_emision) = ?
                           AND YEAR(p.fecha_emision)  = ?
                           $filtroUsuario
                         GROUP BY cp.tipo_comision";

        $stmtP = $this->db->prepare($sqlPendiente);
        if ($global) {
            $stmtP->bind_param("ii", $mes, $anio);
        } else {
            $stmtP->bind_param("iii", $mes, $anio, $uid);
        }
        $stmtP->execute();
        $rowsPendiente = $stmtP->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtP->close();

        // ── Consolidar breakdown ─────────────────────────────────────────────
        $breakdown = [];
        $totalCobrado  = 0.0;
        $totalPendiente = 0.0;

        foreach ($rowsCobrado as $r) {
            $tipo = $r['tipo_comision'];
            $breakdown[$tipo]['tipo']    = $tipo;
            $breakdown[$tipo]['cobrado'] = (float)$r['monto'];
            $breakdown[$tipo]['pendiente'] = $breakdown[$tipo]['pendiente'] ?? 0.0;
            $totalCobrado += (float)$r['monto'];
        }
        foreach ($rowsPendiente as $r) {
            $tipo = $r['tipo_comision'];
            $breakdown[$tipo]['tipo']      = $tipo;
            $breakdown[$tipo]['cobrado']   = $breakdown[$tipo]['cobrado'] ?? 0.0;
            $breakdown[$tipo]['pendiente'] = (float)$r['monto'];
            $totalPendiente += (float)$r['monto'];
        }

        // Calcular proyeccion y porcentaje por tipo
        foreach ($breakdown as &$item) {
            $item['proyeccion'] = $item['cobrado'] + $item['pendiente'];
            $total = $item['proyeccion'];
            $item['porcentaje_cobrado'] = $total > 0
                ? round(($item['cobrado'] / $total) * 100, 2)
                : 0.0;
        }
        unset($item);

        $proyeccionTotal   = $totalCobrado + $totalPendiente;
        $porcentajeCobrado = $proyeccionTotal > 0
            ? round(($totalCobrado / $proyeccionTotal) * 100, 2)
            : 0.0;

        return [
            'cobrado'           => $totalCobrado,
            'pendiente'         => $totalPendiente,
            'proyeccion_total'  => $proyeccionTotal,
            'porcentaje_cobrado'=> $porcentajeCobrado,
            'breakdown'         => array_values($breakdown),
            'mes'               => $mes,
            'anio'              => $anio,
        ];
    }
}
?>
