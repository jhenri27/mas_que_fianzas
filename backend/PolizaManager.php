<?php
require_once 'config.php';

class PolizaManager {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function obtenerPolizas($filtros = []) {
        $whereClause = "1=1";
        $params = [];
        $types = "";

        if (!empty($filtros['search'])) {
            $search = "%" . $filtros['search'] . "%";
            $whereClause .= " AND (p.numero_poliza LIKE ? OR c.nombre_razon_social LIKE ?)";
            $params[] = $search;
            $params[] = $search;
            $types .= "ss";
        }

        if (!empty($filtros['start_date']) && !empty($filtros['end_date'])) {
            $whereClause .= " AND p.fecha_emision BETWEEN ? AND ?";
            $params[] = $filtros['start_date'];
            $params[] = $filtros['end_date'] . ' 23:59:59';
            $types .= "ss";
        }

        $sql = "SELECT p.*, 
                CASE 
                    WHEN c.tipo_cliente = 'persona_natural' THEN CONCAT(c.nombre, ' ', COALESCE(c.apellido, ''))
                    ELSE c.razon_social 
                END as cliente_nombre 
                FROM polizas p 
                LEFT JOIN clientes c ON p.cliente_id = c.id 
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
            // Cálculos y adaptaciones para el panel
            $row['balance'] = 0; // Placeholder until payment logic is implemented
            $row['tipo_poliza'] = 'Individual'; // Placeholder as our table doesn't have it
            $row['validada'] = 'No'; // Placeholder
            $polizas[] = $row;
        }
        
        return $polizas;
    }

    public function emitirPoliza($datos) {
        $sql = "INSERT INTO polizas (
                    numero_poliza, cotizacion_id, cliente_id, tipo_seguro, 
                    prima_total, periodicidad_pago, fecha_vencimiento, estado
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'activa')";
        
        $stmt = $this->db->prepare($sql);
        
        $numero = $datos['numero_poliza'];
        $cot_id = $datos['cotizacion_id'] ?? null;
        $cli_id = $datos['cliente_id'];
        $tipo = $datos['tipo_seguro'];
        $prima = $datos['prima_total'];
        $periodo = $datos['periodicidad_pago'] ?? 'anual';
        $vence = $datos['fecha_vencimiento'];

        $stmt->bind_param("ssisdss", $numero, $cot_id, $cli_id, $tipo, $prima, $periodo, $vence);
        $ok = $stmt->execute();
        
        if ($ok) {
            return $this->db->insert_id;
        }
        return false;
    }
}
