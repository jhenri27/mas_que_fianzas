<?php
require_once dirname(__FILE__) . '/config.php';

/**
 * Interfaz Contable - MAS QUE FIANZAS v3.0
 * Compatible con Catálogo de Cuentas Superintendencia de Seguros RD
 */
class AsientoContableManager {
    private $db;

    public function __construct() {
        try {
            $this->db = Database::getInstance()->getConnection();
        } catch (Exception $e) {
            throw new Exception("Error de conexión a la base de datos: " . $e->getMessage());
        }
    }

    /**
     * Crea un asiento contable automático basado en una transacción del sistema
     * @param array $datos {
     *   descripcion: string,
     *   modulo: 'polizas'|'pagos'|'siniestros'|'comisiones'|'fianzas',
     *   ref_id: int,
     *   ref_tipo: string,
     *   user_id: int,
     *   lineas: Array de { cuenta, nombre, tipo('debito'|'credito'), monto, glosa }
     * }
     */
    public function registrarAsiento($datos) {
        $this->db->begin_transaction();
        try {
            // 1. Generar número de asiento único
            $numAsiento = 'AST-' . date('Ymd') . '-' . rand(100, 999);
            
            $sql = "INSERT INTO asientos_contables (
                        numero_asiento, fecha_asiento, descripcion, 
                        modulo_origen, referencia_id, referencia_tipo, 
                        estado, creado_por
                    ) VALUES (?, ?, ?, ?, ?, ?, 'publicado', ?)";
            
            $stmt = $this->db->prepare($sql);
            if (!$stmt) throw new Exception($this->db->error);

            $fecha = date('Y-m-d');
            $stmt->bind_param("ssssisi", 
                $numAsiento, 
                $fecha, 
                $datos['descripcion'], 
                $datos['modulo'], 
                $datos['ref_id'], 
                $datos['ref_tipo'], 
                $datos['user_id']
            );
            $stmt->execute();
            $asientoId = $this->db->insert_id;

            // 2. Insertar líneas del asiento (Partida Doble)
            foreach ($datos['lineas'] as $linea) {
                $sqlL = "INSERT INTO lineas_asiento (
                            asiento_id, codigo_cuenta, nombre_cuenta, 
                            tipo_movimiento, monto, descripcion_linea
                        ) VALUES (?, ?, ?, ?, ?, ?)";
                
                $stmtL = $this->db->prepare($sqlL);
                if (!$stmtL) throw new Exception($this->db->error);

                $stmtL->bind_param("isssds", 
                    $asientoId, 
                    $linea['cuenta'], 
                    $linea['nombre'], 
                    $linea['tipo'], 
                    $linea['monto'], 
                    $linea['glosa']
                );
                $stmtL->execute();
            }

            $this->db->commit();
            return ['exito' => true, 'id' => $asientoId, 'numero' => $numAsiento];
        } catch (Exception $e) {
            $this->db->rollback();
            return ['exito' => false, 'mensaje' => $e->getMessage()];
        }
    }

    /**
     * Obtiene el nombre de una cuenta desde el catálogo oficial
     */
    public function obtenerNombreCuenta($codigo) {
        $stmt = $this->db->prepare("SELECT nombre_cuenta FROM catalogo_cuentas_super WHERE codigo_cuenta = ?");
        $stmt->bind_param("s", $codigo);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        return $res ? $res['nombre_cuenta'] : "Cuenta Desconocida ($codigo)";
    }
}
?>
