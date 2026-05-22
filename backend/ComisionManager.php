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
}
?>
