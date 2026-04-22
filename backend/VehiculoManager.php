<?php
require_once dirname(__FILE__) . '/config.php';

/**
 * Gestión de Vehículos Asegurados
 * MAS QUE FIANZAS - v3.0
 */
class VehiculoManager {
    private $db;

    public function __construct() {
        try {
            $this->db = Database::getInstance()->getConnection();
        } catch (Exception $e) {
            throw new Exception("Error de conexión a la base de datos: " . $e->getMessage());
        }
    }

    /**
     * Obtiene un vehículo por su ID
     */
    public function obtenerVehiculo($id) {
        $stmt = $this->db->prepare("SELECT * FROM vehiculos WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    /**
     * Obtiene un vehículo por su placa
     */
    public function obtenerVehiculoPorPlaca($placa) {
        $stmt = $this->db->prepare("SELECT * FROM vehiculos WHERE placa = ?");
        $stmt->bind_param("s", $placa);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    /**
     * Lista vehículos de un cliente
     */
    public function obtenerVehiculosPorCliente($cliente_id) {
        $stmt = $this->db->prepare("SELECT * FROM vehiculos WHERE cliente_id = ? ORDER BY id DESC");
        $stmt->bind_param("i", $cliente_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $vehiculos = [];
        while ($row = $result->fetch_assoc()) {
            $vehiculos[] = $row;
        }
        return $vehiculos;
    }

    /**
     * Crea o actualiza un vehículo basado en la placa
     */
    public function crearOActualizarVehiculo($datos) {
        if (empty($datos['cliente_id'])) {
            return ['exito' => false, 'mensaje' => 'ID de cliente es obligatorio'];
        }

        // Si tiene placa, buscar si ya existe
        if (!empty($datos['placa'])) {
            $existente = $this->obtenerVehiculoPorPlaca($datos['placa']);
            if ($existente) {
                return $this->actualizarVehiculo($existente['id'], $datos);
            }
        }

        return $this->crearVehiculo($datos);
    }

    /**
     * Inserta un nuevo registro de vehículo
     */
    public function crearVehiculo($datos) {
        $sql = "INSERT INTO vehiculos (
                    cliente_id, placa, chasis, motor, marca, modelo, 
                    anio, color, tipo_vehiculo, uso, capacidad, 
                    valor_comercial, creado_por
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return ['exito' => false, 'mensaje' => 'Error SQL: ' . $this->db->error];

        $cli_id = $datos['cliente_id'];
        $placa = $datos['placa'] ?? null;
        $chasis = $datos['chasis'] ?? null;
        $motor = $datos['motor'] ?? null;
        $marca = $datos['marca'] ?? null;
        $modelo = $datos['modelo'] ?? null;
        $anio = !empty($datos['anio']) ? $datos['anio'] : null;
        $color = $datos['color'] ?? null;
        $tipo = $datos['tipo_vehiculo'] ?? null;
        $uso = $datos['uso'] ?? 'PRIVADO';
        $capacidad = $datos['capacidad'] ?? null;
        $valor = !empty($datos['valor_comercial']) ? floatval($datos['valor_comercial']) : 0;
        $creado_por = $datos['creado_por'] ?? null;

        $stmt->bind_param("issssssssssdi", 
            $cli_id, $placa, $chasis, $motor, $marca, $modelo, 
            $anio, $color, $tipo, $uso, $capacidad, $valor, $creado_por
        );
        
        if ($stmt->execute()) {
            return ['exito' => true, 'id' => $this->db->insert_id];
        }
        return ['exito' => false, 'mensaje' => $stmt->error];
    }

    /**
     * Actualiza un vehículo existente
     */
    public function actualizarVehiculo($id, $datos) {
        $sql = "UPDATE vehiculos SET 
                    placa=?, chasis=?, motor=?, marca=?, modelo=?, 
                    anio=?, color=?, tipo_vehiculo=?, uso=?, capacidad=?, 
                    valor_comercial=? 
                WHERE id=?";
        
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return ['exito' => false, 'mensaje' => 'Error SQL: ' . $this->db->error];
        
        $placa = $datos['placa'] ?? null;
        $chasis = $datos['chasis'] ?? null;
        $motor = $datos['motor'] ?? null;
        $marca = $datos['marca'] ?? null;
        $modelo = $datos['modelo'] ?? null;
        $anio = !empty($datos['anio']) ? $datos['anio'] : null;
        $color = $datos['color'] ?? null;
        $tipo = $datos['tipo_vehiculo'] ?? null;
        $uso = $datos['uso'] ?? 'PRIVADO';
        $capacidad = $datos['capacidad'] ?? null;
        $valor = !empty($datos['valor_comercial']) ? floatval($datos['valor_comercial']) : 0;

        $stmt->bind_param("ssssssssssdi", 
            $placa, $chasis, $motor, $marca, $modelo, 
            $anio, $color, $tipo, $uso, $capacidad, $valor, $id
        );
        
        if ($stmt->execute()) {
            return ['exito' => true, 'id' => $id];
        }
        return ['exito' => false, 'mensaje' => $stmt->error];
    }
}
?>
