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

    public function listarClientes($usuario_id = null) {
        $where = "";
        if ($usuario_id !== null && restringirSoloPropios($usuario_id, 'clientes')) {
            $where = " WHERE creado_por = " . (int)$usuario_id;
        }
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
                FROM clientes " . $where . " ORDER BY id DESC";
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
        $cedula = trim($datos['rnc'] ?? '');
        if ($this->cedulaExiste($cedula)) {
            return ['exito' => false, 'mensaje' => 'El número de documento (Cédula/RNC/Pasaporte) ya se encuentra registrado para otro cliente comercial.'];
        }

        $sql = "INSERT INTO clientes (numero_cliente, cedula, nombre, tipo_cliente, email, telefono, direccion, estado, comisionante, codigo_comisionante, nombre_comisionante, creado_por) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
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
        $creado_por = isset($datos['creado_por']) ? (int)$datos['creado_por'] : null;
        
        $stmt->bind_param("sssssssssssi", 
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
            $nombre_comisionante,
            $creado_por
        );
        
        try {
            $exito = $stmt->execute();
        } catch (mysqli_sql_exception $e) {
            $stmt->close();
            return ['exito' => false, 'mensaje' => 'Error de Base de Datos: ' . $e->getMessage()];
        }
        
        if ($exito) {
            $insert_id = $this->db->insert_id;
            if (function_exists('logAudit')) {
                logAudit(
                    $creado_por,
                    'crear_cliente',
                    'clientes',
                    'crearCliente',
                    "Cliente creado exitosamente. Nombre: $nombre, RNC/Cédula: $cedula",
                    'exitoso',
                    null,
                    'clientes',
                    $insert_id,
                    null,
                    $datos
                );
            }
            $stmt->close();
            return ['exito' => true, 'mensaje' => 'Cliente guardado exitosamente', 'id' => $insert_id];
        }
        $error = $stmt->error;
        $stmt->close();
        return ['exito' => false, 'mensaje' => 'Error al guardar cliente en BD: ' . $error];
    }

    public function editarCliente($id, $datos) {
        $cedula = trim($datos['rnc'] ?? '');
        if ($this->cedulaExiste($cedula, $id)) {
            return ['exito' => false, 'mensaje' => 'El número de documento (Cédula/RNC/Pasaporte) ya se encuentra registrado para otro cliente comercial.'];
        }

        // Obtener valor anterior para auditoría
        $val_anterior = null;
        $stmt_prev = $this->db->prepare("SELECT * FROM clientes WHERE id = ?");
        if ($stmt_prev) {
            $stmt_prev->bind_param("i", $id);
            $stmt_prev->execute();
            $val_anterior = $stmt_prev->get_result()->fetch_assoc();
            $stmt_prev->close();
        }

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
            if (function_exists('logAudit')) {
                $userId = $_SESSION['usuario_id'] ?? null;
                logAudit(
                    $userId,
                    'editar_cliente',
                    'clientes',
                    'editarCliente',
                    "Cliente ID $id actualizado. Nombre: $nombre",
                    'exitoso',
                    null,
                    'clientes',
                    $id,
                    $val_anterior,
                    $datos
                );
            }
            $stmt->close();
            return ['exito' => true, 'mensaje' => 'Cliente actualizado exitosamente'];
        }
        $error = $stmt->error;
        $stmt->close();
        return ['exito' => false, 'mensaje' => 'Error al actualizar cliente en BD: ' . $error];
    }

    public function importarClientesMasivo($clientesArray, $usuario_id = null) {
        $exitos = 0;
        $errores = 0;
        
        $sql = "INSERT INTO clientes (numero_cliente, cedula, nombre, tipo_cliente, email, telefono, direccion, estado, creado_por) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
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
            
            $creado_por = $usuario_id ? (int)$usuario_id : null;
            
            $stmt->bind_param("ssssssssi", $numero_cliente, $cedula, $nombre, $tipo_cliente, $email, $telefono, $direccion, $estado, $creado_por);
            
            try {
                if ($stmt->execute()) {
                    $new_id = $this->db->insert_id;
                    if (function_exists('logAudit')) {
                        logAudit(
                            $creado_por,
                            'importar_cliente',
                            'clientes',
                            'importarClientesMasivo',
                            "Cliente importado masivamente. Nombre: $nombre, RNC/Cédula: $cedula",
                            'exitoso',
                            null,
                            'clientes',
                            $new_id,
                            null,
                            $datos
                        );
                    }
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

    public function verificarCreador($cliente_id, $usuario_id) {
        $sql = "SELECT creado_por FROM clientes WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return false;
        
        $stmt->bind_param("i", $cliente_id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$res) return false;
        return (int)$res['creado_por'] === (int)$usuario_id;
    }

    /**
     * Verificar si el documento ya existe para otro cliente (Norma NOFTRAB)
     */
    public function cedulaExiste($cedula, $excluir_id = null) {
        if (empty($cedula)) return false;
        $cedula = trim($cedula);
        $sql = "SELECT id FROM clientes WHERE cedula = ?";
        if ($excluir_id !== null) {
            $sql .= " AND id != ?";
        }
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return false;
        if ($excluir_id !== null) {
            $stmt->bind_param("si", $cedula, $excluir_id);
        } else {
            $stmt->bind_param("s", $cedula);
        }
        $stmt->execute();
        $stmt->store_result();
        $existe = $stmt->num_rows > 0;
        $stmt->close();
        return $existe;
    }
}
?>
