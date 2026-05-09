<?php
/**
 * CLASE: NCFManager
 * ROL: Gestión de Números de Comprobante Fiscal (DGII RD)
 * VERSION: 3.1-PRO
 */

namespace MQF\Finance;

class NCFManager {
    
    private $db;

    public function __construct($dbConnection = null) {
        $this->db = $dbConnection ?: \Database::getInstance()->getConnection();
    }

    /**
     * Obtiene y consume el siguiente NCF disponible
     * @param string $tipo Tipo de NCF ('B01', 'B02', etc.)
     * @param bool $usarNCF Si es false, devuelve null (opción de emergencia)
     * @return string|null NCF generado o null
     */
    public function generarSiguiente($tipo, $usarNCF = true) {
        if (!$usarNCF) return null;

        try {
            $this->db->begin_transaction();

            // 1. Bloquear fila para evitar colisiones de concurrencia
            $sql = "SELECT prefijo, secuencia_actual, secuencia_final, vencimiento 
                    FROM cf_ncf_secuencias 
                    WHERE tipo = ? AND activa = TRUE 
                    FOR UPDATE";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param("s", $tipo);
            $stmt->execute();
            $sec = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$sec) {
                throw new \Exception("No hay secuencias activas para el tipo $tipo.");
            }

            // 2. Validar vencimiento
            if ($sec['vencimiento'] && strtotime($sec['vencimiento']) < time()) {
                throw new \Exception("La secuencia de NCF $tipo ha vencido.");
            }

            // 3. Validar límite
            $siguiente = $sec['secuencia_actual'] + 1;
            if ($siguiente > $sec['secuencia_final']) {
                throw new \Exception("Se ha agotado la secuencia de NCF $tipo.");
            }

            // 4. Actualizar secuencia
            $stmtU = $this->db->prepare("UPDATE cf_ncf_secuencias SET secuencia_actual = ? WHERE tipo = ?");
            $stmtU->bind_param("is", $siguiente, $tipo);
            $stmtU->execute();
            $stmtU->close();

            // 5. Formatear NCF (B01 + 8 dígitos de secuencia)
            $ncf = $sec['prefijo'] . str_pad($siguiente, 8, '0', STR_PAD_LEFT);

            // 6. Registrar en Log
            $stmtL = $this->db->prepare("INSERT INTO cf_ncf_log (tipo, ncf, modulo_origen) VALUES (?, ?, 'LABS-MASQF')");
            $stmtL->bind_param("ss", $tipo, $ncf);
            $stmtL->execute();
            $stmtL->close();

            $this->db->commit();
            return $ncf;

        } catch (\Exception $e) {
            $this->db->rollback();
            error_log("NCFManager Error: " . $e->getMessage());
            return null; // En emergencia devolvemos null para no bloquear
        }
    }

    /**
     * Valida si un NCF tiene el formato correcto
     */
    public static function validarFormato($ncf) {
        return preg_match('/^[B|E][0-9]{10}$/', $ncf);
    }
}
