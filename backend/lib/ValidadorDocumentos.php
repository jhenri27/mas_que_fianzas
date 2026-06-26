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
}
?>
