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

        $sql = "SELECT p.*, 
                       c.nombre as cliente_nombre, c.cedula as cliente_cedula,
                       v.placa as vehiculo_placa, v.marca as vehiculo_marca, v.modelo as vehiculo_modelo,
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
            // Cálculo de balance
            $prima = floatval($row['prima_total']);
            $pagado = floatval($row['total_pagado'] ?? 0);
            $row['balance'] = $prima - $pagado;
            $polizas[] = $row;
        }
        
        return $polizas;
    }

    /**
     * Obtiene el detalle completo de una póliza específica
     */
    public function obtenerPolizaDetalle($id) {
        $sql = "SELECT p.*, c.nombre as cliente_nombre, c.email as cliente_email, c.telefono as cliente_telefono 
                FROM polizas p 
                JOIN clientes c ON p.cliente_id = c.id 
                WHERE p.id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $poliza = $stmt->get_result()->fetch_assoc();

        if ($poliza) {
            $poliza['vehiculo'] = $this->vehiculoManager->obtenerVehiculo($poliza['vehiculo_id']);
            $poliza['comisiones'] = $this->comisionManager->listarComisiones(['poliza_id' => $id]);
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
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'activa', ?, ?)";
            
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
            $itbis = $total * 0.18; // Cálculo estándar RD si no viene desglosado
            $neta = $total - $itbis;
            $otros = 0;
            $periodo = $datos['periodicidad_pago'] ?? 'anual';
            $cuota_total = intval($datos['cuota_total'] ?? 1); 
            $vence = $datos['fecha_vencimiento'];
            $emitida_por = $datos['emitida_por'];
            $fecha_em = date('Y-m-d');

            $stmt->bind_param("ssiiisssssddddd s i s", 
                $num, $num_aseg, $cot_id, $cli_id, $vehiculoId,
                $tipo_seguro, $tipo_poliza, $ramo, $aseguradora, $perfil,
                $total, $neta, $itbis, $otros,
                $periodo, $cuota_total, $vence, $emitida_por, $fecha_em
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
                    ['cuenta' => '2.1.03.01', 'nombre' => 'ITBIS por Pagar', 'tipo' => 'credito', 'monto' => $itbis, 'glosa' => 'ITBIS 18%']
                ]
            ]);

            $this->db->commit();
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
        return $stmt->execute();
    }

    /**
     * Cambia el estado de una póliza
     */
    public function cambiarEstado($id, $nuevoEstado) {
        $sql = "UPDATE polizas SET estado = ? WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("si", $nuevoEstado, $id);
        return $stmt->execute();
    }
}
?>
