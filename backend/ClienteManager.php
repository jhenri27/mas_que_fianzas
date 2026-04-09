<?php
require_once dirname(__FILE__) . '/config.php';

class ClienteManager {
    private $db;

    public function __construct() {
        try {
            $this->db = Database::getInstance()->getConnection();
        } catch (Exception $e) {
            throw new Exception("Error de conexión a la base de datos: " . $e->getMessage());
        }
    }

    public function listarClientes() {
        // Mapear nombres de la BD a lo que el frontend espera
        $sql = "SELECT id, 
                       nombre as nombre_razon_social, 
                       cedula as rnc, 
                       IF(tipo_cliente='empresa', 'Juridica', 'Fisica') as tipo_persona, 
                       telefono, 
                       estado as estatus,
                       comisionante,
                       codigo_comisionante,
                       nombre_comisionante
                FROM clientes ORDER BY id DESC";
        $result = $this->db->query($sql);
        $clientes = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $clientes[] = $row;
            }
        }
        return $clientes;
    }

    public function crearCliente($datos) {
        $sql = "INSERT INTO clientes (numero_cliente, cedula, nombre, tipo_cliente, email, telefono, direccion, estado, comisionante, codigo_comisionante, nombre_comisionante) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            return ['exito' => false, 'mensaje' => 'Error preparando consulta: ' . $this->db->error];
        }
        
        // Mapeo seguro de datos desde UI hacia columnas reales
        $tipo_cliente = (isset($datos['tipo_persona']) && $datos['tipo_persona'] === 'Juridica') ? 'empresa' : 'persona_natural';
        $numero_cliente = 'CLI-' . time() . rand(100, 999); // Campo requerido NOT NULL
        $cedula = $datos['rnc'] ?? ''; // Campo requerido NOT NULL
        $nombre = $datos['nombre_razon_social'] ?? ''; // Campo requerido NOT NULL
        $email = $datos['correo'] ?? null; 
        $telefono = $datos['telefono'] ?? null;
        $direccion = $datos['direccion'] ?? null;
        $estado = strtolower($datos['estatus'] ?? 'activo'); // 'activo', 'inactivo'
        $comisionante = $datos['comisionante'] ?? null;
        $codigo_comisionante = $datos['codigo_comisionante'] ?? null;
        $nombre_comisionante = $datos['nombre_comisionante'] ?? null;
        
        $stmt->bind_param("sssssssssss", 
            $numero_cliente, 
            $cedula, 
            $nombre, 
            $tipo_cliente, 
            $email, 
            $telefono, 
            $direccion, 
            $estado,
            $comisionante,
            $codigo_comisionante,
            $nombre_comisionante
        );
        
        try {
            $exito = $stmt->execute();
        } catch (mysqli_sql_exception $e) {
            $stmt->close();
            return ['exito' => false, 'mensaje' => 'Error de Base de Datos: ' . $e->getMessage()];
        }
        
        if ($exito) {
            $insert_id = $this->db->insert_id;
            $stmt->close();
            return ['exito' => true, 'mensaje' => 'Cliente guardado exitosamente', 'id' => $insert_id];
        }
        $error = $stmt->error;
        $stmt->close();
        return ['exito' => false, 'mensaje' => 'Error al guardar cliente en BD: ' . $error];
    }

    public function editarCliente($id, $datos) {
        $sql = "UPDATE clientes SET tipo_cliente=?, nombre=?, cedula=?, telefono=?, email=?, direccion=?, estado=?, comisionante=?, codigo_comisionante=?, nombre_comisionante=? WHERE id=?";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            return ['exito' => false, 'mensaje' => 'Error preparando consulta: ' . $this->db->error];
        }

        $tipo_cliente = (isset($datos['tipo_persona']) && $datos['tipo_persona'] === 'Juridica') ? 'empresa' : 'persona_natural';
        $nombre = $datos['nombre_razon_social'] ?? '';
        $cedula = $datos['rnc'] ?? '';
        $telefono = $datos['telefono'] ?? null;
        $email = $datos['correo'] ?? null;
        $direccion = $datos['direccion'] ?? null;
        $estado = strtolower($datos['estatus'] ?? 'activo');
        $comisionante = $datos['comisionante'] ?? null;
        $codigo_comisionante = $datos['codigo_comisionante'] ?? null;
        $nombre_comisionante = $datos['nombre_comisionante'] ?? null;

        $stmt->bind_param("ssssssssssi", $tipo_cliente, $nombre, $cedula, $telefono, $email, $direccion, $estado, $comisionante, $codigo_comisionante, $nombre_comisionante, $id);
        
        try {
            $exito = $stmt->execute();
        } catch (mysqli_sql_exception $e) {
            $stmt->close();
            return ['exito' => false, 'mensaje' => 'Error de Base de Datos: ' . $e->getMessage()];
        }
        
        if ($exito) {
            $stmt->close();
            return ['exito' => true, 'mensaje' => 'Cliente actualizado exitosamente'];
        }
        $error = $stmt->error;
        $stmt->close();
        return ['exito' => false, 'mensaje' => 'Error al actualizar cliente en BD: ' . $error];
    }

    public function importarClientesMasivo($clientesArray) {
        $exitos = 0;
        $errores = 0;
        
        $sql = "INSERT INTO clientes (numero_cliente, cedula, nombre, tipo_cliente, email, telefono, direccion, estado) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        
        if (!$stmt) {
            return ['exito' => false, 'mensaje' => 'Error preparando consulta múltiple: ' . $this->db->error];
        }

        foreach ($clientesArray as $datos) {
            $tipo_cliente = (isset($datos['tipo_persona']) && $datos['tipo_persona'] === 'Juridica') ? 'empresa' : 'persona_natural';
            $numero_cliente = 'CLI-' . time() . rand(1000, 9999); 
            $cedula = $datos['rnc'] ?? ''; 
            $nombre = $datos['nombre_razon_social'] ?? ''; 
            $email = $datos['correo'] ?? null; 
            $telefono = $datos['telefono'] ?? null;
            $direccion = $datos['direccion'] ?? null;
            
            // Estatus
            $estado_raw = strtolower($datos['estatus'] ?? 'activo');
            $estado = in_array($estado_raw, ['activo', 'inactivo', 'suspendido']) ? $estado_raw : 'activo';
            
            $stmt->bind_param("ssssssss", $numero_cliente, $cedula, $nombre, $tipo_cliente, $email, $telefono, $direccion, $estado);
            
            try {
                if ($stmt->execute()) {
                    $exitos++;
                } else {
                    $errores++;
                }
            } catch (Exception $e) {
                // Ignorar error de duplicidad (Unique Constraint) y continuar con el siguiente
                $errores++;
            }
        }
        
        $stmt->close();
        return ['exito' => true, 'insertados' => $exitos, 'errores' => $errores, 'mensaje' => "Importación procesada."];
    }
}
?>
