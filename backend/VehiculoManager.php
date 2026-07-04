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
        $placa = !empty($datos['placa']) ? trim($datos['placa']) : null;
        $chasis = !empty($datos['chasis']) ? trim($datos['chasis']) : null;
        $motor = !empty($datos['motor']) ? trim($datos['motor']) : null;

        if ($placa !== null && $this->placaExiste($placa)) {
            return ['exito' => false, 'mensaje' => 'La placa/matrícula especificada ya se encuentra registrada para otro vehículo.'];
        }
        if ($chasis !== null && $this->chasisExiste($chasis)) {
            return ['exito' => false, 'mensaje' => 'El número de chasis (VIN) especificado ya se encuentra registrado para otro vehículo.'];
        }
        if ($motor !== null && $this->motorExiste($motor)) {
            return ['exito' => false, 'mensaje' => 'El número de motor especificado ya se encuentra registrado para otro vehículo.'];
        }

        $sql = "INSERT INTO vehiculos (
                    cliente_id, placa, matricula, chasis, motor, marca, modelo, 
                    anio, color, tipo_vehiculo, uso, capacidad, 
                    valor_comercial, creado_por
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return ['exito' => false, 'mensaje' => 'Error SQL: ' . $this->db->error];

        $cli_id = $datos['cliente_id'];
        $placa = $datos['placa'] ?? null;
        $matricula = $datos['matricula'] ?? null;
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

        $stmt->bind_param("issssssssssdii", 
            $cli_id, $placa, $matricula, $chasis, $motor, $marca, $modelo, 
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
        $placa = !empty($datos['placa']) ? trim($datos['placa']) : null;
        $chasis = !empty($datos['chasis']) ? trim($datos['chasis']) : null;
        $motor = !empty($datos['motor']) ? trim($datos['motor']) : null;

        if ($placa !== null && $this->placaExiste($placa, $id)) {
            return ['exito' => false, 'mensaje' => 'La placa/matrícula especificada ya se encuentra registrada para otro vehículo.'];
        }
        if ($chasis !== null && $this->chasisExiste($chasis, $id)) {
            return ['exito' => false, 'mensaje' => 'El número de chasis (VIN) especificado ya se encuentra registrado para otro vehículo.'];
        }
        if ($motor !== null && $this->motorExiste($motor, $id)) {
            return ['exito' => false, 'mensaje' => 'El número de motor especificado ya se encuentra registrado para otro vehículo.'];
        }

        $sql = "UPDATE vehiculos SET 
                    placa=?, matricula=?, chasis=?, motor=?, marca=?, modelo=?, 
                    anio=?, color=?, tipo_vehiculo=?, uso=?, capacidad=?, 
                    valor_comercial=? 
                WHERE id=?";
        
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return ['exito' => false, 'mensaje' => 'Error SQL: ' . $this->db->error];
        
        $placa = $datos['placa'] ?? null;
        $matricula = $datos['matricula'] ?? null;
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

        $stmt->bind_param("ssssssssssdii", 
            $placa, $matricula, $chasis, $motor, $marca, $modelo, 
            $anio, $color, $tipo, $uso, $capacidad, $valor, $id
        );
        
        if ($stmt->execute()) {
            return ['exito' => true, 'id' => $id];
        }
        return ['exito' => false, 'mensaje' => $stmt->error];
    }

    /**
     * Verificar si la placa ya existe para otro vehículo (Norma NOFTRAB)
     */
    public function placaExiste($placa, $excluir_id = null) {
        if (empty($placa)) return false;
        $placa = trim($placa);
        $sql = "SELECT id FROM vehiculos WHERE placa = ?";
        if ($excluir_id !== null) {
            $sql .= " AND id != ?";
        }
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return false;
        if ($excluir_id !== null) {
            $stmt->bind_param("si", $placa, $excluir_id);
        } else {
            $stmt->bind_param("s", $placa);
        }
        $stmt->execute();
        $stmt->store_result();
        $existe = $stmt->num_rows > 0;
        $stmt->close();
        return $existe;
    }

    /**
     * Verificar si el chasis ya existe para otro vehículo (Norma NOFTRAB)
     */
    public function chasisExiste($chasis, $excluir_id = null) {
        if (empty($chasis)) return false;
        $chasis = trim($chasis);
        $sql = "SELECT id FROM vehiculos WHERE chasis = ?";
        if ($excluir_id !== null) {
            $sql .= " AND id != ?";
        }
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return false;
        if ($excluir_id !== null) {
            $stmt->bind_param("si", $chasis, $excluir_id);
        } else {
            $stmt->bind_param("s", $chasis);
        }
        $stmt->execute();
        $stmt->store_result();
        $existe = $stmt->num_rows > 0;
        $stmt->close();
        return $existe;
    }

    /**
     * Verificar si el número de motor ya existe para otro vehículo (Norma NOFTRAB)
     */
    public function motorExiste($motor, $excluir_id = null) {
        if (empty($motor)) return false;
        $motor = trim($motor);
        $sql = "SELECT id FROM vehiculos WHERE motor = ?";
        if ($excluir_id !== null) {
            $sql .= " AND id != ?";
        }
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return false;
        if ($excluir_id !== null) {
            $stmt->bind_param("si", $motor, $excluir_id);
        } else {
            $stmt->bind_param("s", $motor);
        }
        $stmt->execute();
        $stmt->store_result();
        $existe = $stmt->num_rows > 0;
        $stmt->close();
        return $existe;
    }
}
?>
