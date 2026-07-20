<?php
/**
 * Clase Utilitaria para Validaciones de Datos Dominicanos (Normas NOFTRAB)
 * - Cédula (11 dígitos, módulo 10)
 * - RNC (9 dígitos, módulo 11)
 * - Teléfonos (10 dígitos, códigos 809, 829, 849)
 * - Pasaporte (2 letras + 7 dígitos)
 * - Licencia de conducir (1 letra + 8 dígitos)
 */

class ValidadorDocumentos {
    private static $config_cache = null;

    /**
     * Cargar configuraciones del validador desde la base de datos
     */
    private static function loadConfig() {
        if (self::$config_cache === null) {
            self::$config_cache = [];
            try {
                $db = Database::getInstance()->getConnection();
                $res = $db->query("SELECT clave_config, valor_config FROM configuracion_sistema WHERE clave_config LIKE 'VALIDADOR_DOCS_%'");
                if ($res) {
                    while ($row = $res->fetch_assoc()) {
                        self::$config_cache[$row['clave_config']] = $row['valor_config'];
                    }
                }
            } catch (Exception $e) {
                // Fallback silencioso en caso de error de conexión
            }
        }
    }

    /**
     * Verificar si el validador está activo para un módulo específico
     */
    public static function isValidatorActive($modulo) {
        self::loadConfig();
        $global_active = isset(self::$config_cache['VALIDADOR_DOCS_ACTIVO']) && self::$config_cache['VALIDADOR_DOCS_ACTIVO'] === '1';
        if (!$global_active) {
            return false;
        }
        
        $modulo_key = 'VALIDADOR_DOCS_' . strtoupper($modulo);
        return isset(self::$config_cache[$modulo_key]) && self::$config_cache[$modulo_key] === '1';
    }

    /**
     * Validación de Cédula Dominicana (11 dígitos, Luhn mod 10)
     */
    public static function validarCedula($cedula) {
        if ($cedula === null) return false;
        $cedula = preg_replace('/\D/', '', $cedula);
        if (strlen($cedula) !== 11) return false;

        $pesos = [1, 2, 1, 2, 1, 2, 1, 2, 1, 2];
        $suma = 0;

        for ($i = 0; $i < 10; $i++) {
            $mult = (int)$cedula[$i] * $pesos[$i];
            if ($mult >= 10) {
                $suma += (int)($mult / 10) + ($mult % 10);
            } else {
                $suma += $mult;
            }
        }

        $digito_calculado = (10 - ($suma % 10)) % 10;
        $digito_real = (int)$cedula[10];

        return $digito_calculado === $digito_real;
    }

    /**
     * Validación de RNC Dominicano (9 dígitos, mod 11 oficial DGII)
     */
    public static function validarRNC($rnc) {
        if ($rnc === null) return false;
        $rnc = preg_replace('/\D/', '', $rnc);
        if (strlen($rnc) !== 9) return false;

        $pesos = [7, 9, 8, 6, 5, 4, 3, 2];
        $suma = 0;

        for ($i = 0; $i < 8; $i++) {
            $suma += (int)$rnc[$i] * $pesos[$i];
        }

        $residuo = $suma % 11;
        if ($residuo === 0) {
            $digito_calculado = 2;
        } elseif ($residuo === 1) {
            $digito_calculado = 1;
        } else {
            $digito_calculado = 11 - $residuo;
        }

        $digito_real = (int)$rnc[8];

        return $digito_calculado === $digito_real;
    }

    /**
     * Validación de Teléfono Dominicano (10 dígitos, prefijos 809/829/849)
     */
    public static function validarTelefono($telefono) {
        if ($telefono === null) return false;
        $telefono = preg_replace('/\D/', '', $telefono);
        if (strlen($telefono) !== 10) return false;
        
        $prefijo = substr($telefono, 0, 3);
        return in_array($prefijo, ['809', '829', '849']);
    }

    /**
     * Validación de Pasaporte (formato RD: 2 letras + 7 dígitos, o internacional: 5-15 caracteres alfanuméricos)
     */
    public static function validarPasaporte($pasaporte) {
        if ($pasaporte === null) return false;
        $pasaporte = trim($pasaporte);
        return (bool)preg_match('/^[a-zA-Z]{2}\d{7}$/', $pasaporte) || (bool)preg_match('/^[a-zA-Z0-9]{5,15}$/', $pasaporte);
    }

    /**
     * Validación de Licencia de Conducir (1 letra + 8 dígitos)
     */
    public static function validarLicencia($licencia) {
        if ($licencia === null) return false;
        $licencia = trim($licencia);
        return (bool)preg_match('/^[a-zA-Z]{1}\d{8}$/', $licencia);
    }

    /**
     * Auto-detectar o forzar tipo de documento (RNC, Cédula o Pasaporte) y validar
     */
    public static function validarDocumento($doc, $tipo = null) {
        if ($doc === null) return false;
        $doc = trim($doc);
        
        if ($tipo === 'pasaporte' || (empty($tipo) && preg_match('/[a-zA-Z]/', $doc))) {
            return self::validarPasaporte($doc);
        }
        
        $clean = preg_replace('/\D/', '', $doc);
        if ($tipo === 'rnc' || (empty($tipo) && strlen($clean) === 9)) {
            return self::validarRNC($clean);
        } elseif ($tipo === 'cedula' || (empty($tipo) && strlen($clean) === 11)) {
            return self::validarCedula($clean);
        }
        return false;
    }

    /**
     * Normalizar cadenas de texto para comparación libre de diacríticos y caracteres especiales
     */
    public static function normalizarTexto($texto) {
        if (empty($texto)) return '';
        $str = mb_strtolower(trim($texto), 'UTF-8');
        $remplazos = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n','à'=>'a','è'=>'e','ì'=>'i','ò'=>'o','ù'=>'u'];
        $str = strtr($str, $remplazos);
        return preg_replace('/[^a-z0-9]/', '', $str);
    }

    /**
     * Verificar Unicidad Global de Documento de Identidad (Cédula, RNC, Pasaporte, Licencia)
     * en toda la plataforma (clientes, cotizaciones, usuarios) según Normas NOFTRAB / 4-VAF.
     * Retorna NULL si es único/válido, o un string con el mensaje de error si hay duplicado.
     */
    public static function validarDocumentoUnicoGlobal($db, $doc, $nombrePersona) {
        if (empty($doc)) return null;

        // Limpiar el documento dejando solo alfanuméricos en mayúsculas
        $docLimpio = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $doc));
        if (empty($docLimpio)) return null;

        $nombreNorm = self::normalizarTexto($nombrePersona);

        // 1. Buscar en la tabla de clientes comerciales
        $stmt = $db->prepare("SELECT nombre, razon_social FROM clientes WHERE REPLACE(REPLACE(REPLACE(UPPER(cedula), '-', ''), ' ', ''), '.', '') = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $docLimpio);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $nombreReg = !empty($row['nombre']) ? $row['nombre'] : ($row['razon_social'] ?? '');
                $nombreRegNorm = self::normalizarTexto($nombreReg);
                if (!empty($nombreNorm) && !empty($nombreRegNorm) && $nombreNorm !== $nombreRegNorm) {
                    $stmt->close();
                    return "El documento de identidad '$doc' ya pertenece al cliente registrado '$nombreReg'. Según las Normas NOFTRAB no se permite asignar el mismo documento a dos clientes diferentes.";
                }
            }
            $stmt->close();
        }

        // 2. Buscar en la tabla de cotizaciones previas
        $stmtCot = $db->prepare("SELECT cliente FROM cotizaciones WHERE REPLACE(REPLACE(REPLACE(UPPER(cedula), '-', ''), ' ', ''), '.', '') = ? AND cliente IS NOT NULL AND TRIM(cliente) != '' ORDER BY id DESC LIMIT 1");
        if ($stmtCot) {
            $stmtCot->bind_param("s", $docLimpio);
            $stmtCot->execute();
            $resCot = $stmtCot->get_result();
            if ($rowCot = $resCot->fetch_assoc()) {
                $nombreCot = $rowCot['cliente'];
                $nombreCotNorm = self::normalizarTexto($nombreCot);
                if (!empty($nombreNorm) && !empty($nombreCotNorm) && $nombreNorm !== $nombreCotNorm) {
                    $stmtCot->close();
                    return "El documento de identidad '$doc' ya fue registrado anteriormente por el cliente '$nombreCot'. Según las Normas NOFTRAB no se permite duplicar identificadores entre clientes distintos.";
                }
            }
            $stmtCot->close();
        }

        // 3. Buscar en la tabla de usuarios / administradores
        $stmtUsr = $db->prepare("SELECT nombre, apellido FROM usuarios WHERE REPLACE(REPLACE(REPLACE(UPPER(cedula), '-', ''), ' ', ''), '.', '') = ? LIMIT 1");
        if ($stmtUsr) {
            $stmtUsr->bind_param("s", $docLimpio);
            $stmtUsr->execute();
            $resUsr = $stmtUsr->get_result();
            if ($rowUsr = $resUsr->fetch_assoc()) {
                $nombreUsr = trim(($rowUsr['nombre'] ?? '') . ' ' . ($rowUsr['apellido'] ?? ''));
                $nombreUsrNorm = self::normalizarTexto($nombreUsr);
                if (!empty($nombreNorm) && !empty($nombreUsrNorm) && $nombreNorm !== $nombreUsrNorm) {
                    $stmtUsr->close();
                    return "El documento de identidad '$doc' pertenece al usuario/administrador '$nombreUsr'. Según las Normas NOFTRAB no se permite utilizar la identificación de un usuario para otro cliente.";
                }
            }
            $stmtUsr->close();
        }

        return null;
    }

    /**
     * Verificar Unicidad Global de Identificadores de Vehículos (Chasis/VIN, Placa)
     * según Normas NOFTRAB / 4-VAF.
     */
    public static function validarVehiculoUnicoGlobal($db, $chasis = '', $placa = '', $idExcluir = null) {
        $chasisLimpio = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $chasis ?? ''));
        $placaLimpia  = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $placa ?? ''));

        // 1. Validar Chasis/VIN
        if (!empty($chasisLimpio) && strlen($chasisLimpio) >= 5) {
            $stmt = $db->prepare("SELECT numero, cliente FROM cotizaciones WHERE (REPLACE(REPLACE(REPLACE(UPPER(servicios_opcionales), '-', ''), ' ', ''), '.', '') LIKE CONCAT('%', ?, '%') OR REPLACE(REPLACE(REPLACE(UPPER(subtipo), '-', ''), ' ', ''), '.', '') = ?) LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("ss", $chasisLimpio, $chasisLimpio);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($row = $res->fetch_assoc()) {
                    $stmt->close();
                    return "El Chasis/VIN '$chasis' ya se encuentra registrado en la cotización/póliza {$row['numero']} (Cliente: {$row['cliente']}). Según las Normas NOFTRAB no se permiten vehículos duplicados.";
                }
                $stmt->close();
            }
        }

        // 2. Validar Placa
        if (!empty($placaLimpia) && strlen($placaLimpia) >= 5) {
            $stmtP = $db->prepare("SELECT numero, cliente FROM cotizaciones WHERE (REPLACE(REPLACE(REPLACE(UPPER(uso), '-', ''), ' ', ''), '.', '') = ? OR REPLACE(REPLACE(REPLACE(UPPER(capacidad), '-', ''), ' ', ''), '.', '') = ?) LIMIT 1");
            if ($stmtP) {
                $stmtP->bind_param("ss", $placaLimpia, $placaLimpia);
                $stmtP->execute();
                $resP = $stmtP->get_result();
                if ($rowP = $resP->fetch_assoc()) {
                    $stmtP->close();
                    return "La Placa '$placa' ya se encuentra registrada en la cotización/póliza {$rowP['numero']} (Cliente: {$rowP['cliente']}). Según las Normas NOFTRAB no se permiten vehículos duplicados.";
                }
                $stmtP->close();
            }
        }

        return null;
    }

    /**
     * Verificar Unicidad Global de Identificadores de Fianzas (N° Fianza, N° Póliza de Fianza)
     * según Normas NOFTRAB / 4-VAF.
     */
    public static function validarFianzaUnicaGlobal($db, $numeroFianza, $idExcluir = null) {
        $numLimpio = strtoupper(trim($numeroFianza ?? ''));
        if (empty($numLimpio)) return null;

        $checkTable = $db->query("SHOW TABLES LIKE 'fianzas'");
        if ($checkTable && $checkTable->num_rows > 0) {
            $stmt = $db->prepare("SELECT numero_fianza, afianzado_nombre FROM fianzas WHERE UPPER(numero_fianza) = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("s", $numLimpio);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($row = $res->fetch_assoc()) {
                    $stmt->close();
                    return "La Fianza N° '$numeroFianza' ya se encuentra registrada en el sistema a nombre de {$row['afianzado_nombre']}. Según las Normas NOFTRAB no se permiten números de fianza duplicados.";
                }
                $stmt->close();
            }
        }

        return null;
    }
}
?>
