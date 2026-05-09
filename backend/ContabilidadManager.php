<?php
/**
 * CLASE: ContabilidadManager
 * ROL: Motor Central del Centro Financiero (Core Contable)
 * MARCO: SIS / DGII RD
 * VERSION: 3.1-PRO (Emergency Plan)
 */

namespace MQF\Finance;

class ContabilidadManager {
    
    private static $instance = null;
    private $db;

    public function __construct($dbConnection = null) {
        $this->db = $dbConnection ?: \Database::getInstance()->getConnection();
    }

    public static function getInstance($db = null) {
        if (self::$instance === null) {
            self::$instance = new self($db);
        }
        return self::$instance;
    }

    /**
     * Crea un nuevo asiento contable
     */
    public function crearAsiento($header, $lineas) {
        // 1. Validar Partida Doble
        $totalDebe = 0;
        $totalHaber = 0;
        foreach ($lineas as $l) {
            $totalDebe += (float)($l['debe'] ?? 0);
            $totalHaber += (float)($l['haber'] ?? 0);
        }

        if (abs($totalDebe - $totalHaber) > 0.001) {
            throw new \Exception("Error: El asiento no cuadra (D: $totalDebe, H: $totalHaber).");
        }

        // 2. Obtener Período Actual
        $fecha = $header['fecha'] ?? date('Y-m-d');
        $periodoId = $this->getPeriodoIdForDate($fecha);

        // 3. Generar número de asiento
        $numero = $this->generarNumeroAsiento($fecha);

        try {
            $this->db->begin_transaction();

            // Insertar encabezado
            $sql = "INSERT INTO cf_asientos 
                (numero, fecha, descripcion, tipo, origen_modulo, origen_id, periodo_id, creado_por) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            if (!$stmt) throw new \Exception($this->db->error);

            $tipo = $header['tipo'] ?? 'AUTOMATICO';
            $modulo = $header['origen_modulo'] ?? null;
            $origenId = $header['origen_id'] ?? null;
            $usuarioId = $_SESSION['user_id'] ?? 1;

            $stmt->bind_param("sssssiii", 
                $numero, $fecha, $header['descripcion'], $tipo, 
                $modulo, $origenId, $periodoId, $usuarioId
            );
            $stmt->execute();
            $asientoId = $this->db->insert_id;
            $stmt->close();

            // Insertar líneas
            $sqlL = "INSERT INTO cf_asiento_lineas 
                (asiento_id, cuenta_codigo, descripcion_linea, debe, haber, orden) 
                VALUES (?, ?, ?, ?, ?, ?)";
            $stmtL = $this->db->prepare($sqlL);

            foreach ($lineas as $idx => $l) {
                $debe = (float)($l['debe'] ?? 0);
                $haber = (float)($l['haber'] ?? 0);
                $desc = $l['descripcion'] ?? $header['descripcion'];
                
                $stmtL->bind_param("issddi", 
                    $asientoId, $l['cuenta_codigo'], $desc, $debe, $haber, $idx
                );
                $stmtL->execute();
            }
            $stmtL->close();

            $this->db->commit();
            return $asientoId;

        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Obtiene el ID del período contable
     */
    private function getPeriodoIdForDate($fecha) {
        $anio = (int)date('Y', strtotime($fecha));
        $mes = (int)date('n', strtotime($fecha));

        $stmt = $this->db->prepare("SELECT id, estado FROM cf_periodos WHERE anio = ? AND mes = ?");
        $stmt->bind_param("ii", $anio, $mes);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$res) {
            $stmt = $this->db->prepare("INSERT INTO cf_periodos (anio, mes, estado) VALUES (?, ?, 'ABIERTO')");
            $stmt->bind_param("ii", $anio, $mes);
            $stmt->execute();
            $id = $this->db->insert_id;
            $stmt->close();
            return $id;
        }

        if ($res['estado'] === 'CERRADO' || $res['estado'] === 'BLOQUEADO') {
            throw new \Exception("Error: El período $mes-$anio está CERRADO.");
        }

        return $res['id'];
    }

    /**
     * Genera un número de asiento correlativo
     */
    private function generarNumeroAsiento($fecha) {
        $prefijo = "AS-" . date('Ym', strtotime($fecha));
        $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM cf_asientos WHERE numero LIKE ?");
        $like = $prefijo . "%";
        $stmt->bind_param("s", $like);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $count = $res['total'] + 1;
        $stmt->close();
        
        return $prefijo . "-" . str_pad($count, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Obtiene el saldo de una cuenta
     */
    public function getSaldoCuenta($codigo, $fechaHasta = null) {
        $fechaHasta = $fechaHasta ?: date('Y-m-d');
        
        $sql = "SELECT SUM(l.debe) as total_debe, SUM(l.haber) as total_haber 
                FROM cf_asiento_lineas l
                JOIN cf_asientos a ON l.asiento_id = a.id
                WHERE l.cuenta_codigo LIKE ? 
                AND a.fecha <= ? 
                AND a.estado = 'APROBADO'";
        
        $stmt = $this->db->prepare($sql);
        $like = $codigo . '%';
        $stmt->bind_param("ss", $like, $fechaHasta);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $nat = $this->getNaturalezaCuenta($codigo);
        
        if ($nat === 'DEUDORA') {
            return (float)($res['total_debe'] ?? 0) - (float)($res['total_haber'] ?? 0);
        } else {
            return (float)($res['total_haber'] ?? 0) - (float)($res['total_debe'] ?? 0);
        }
    }

    private function getNaturalezaCuenta($codigo) {
        $stmt = $this->db->prepare("SELECT naturaleza FROM cf_catalogo_cuentas WHERE codigo = ?");
        $first = substr($codigo, 0, 1);
        $stmt->bind_param("s", $first);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        return $res['naturaleza'] ?? 'DEUDORA';
    }
}
