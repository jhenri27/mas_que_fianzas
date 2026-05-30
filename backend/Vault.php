<?php
/**
 * Vault — Cifrado de Credenciales y Llaves de API
 * MAS QUE FIANZAS — Sistema Integrado
 * =======================================================
 * Provee métodos estáticos de encriptación y desencriptación simétrica
 * utilizando AES-256-CBC bajo el estándar de seguridad NOFTRAB.
 */

class Vault {
    private static $method = 'AES-256-CBC';
    private static $salt = 'MQF_VAULT_SECRET_INTEGRACIONES_2026';
    private static $iv_salt = 'MQF_VAULT_IV_INTEGRACIONES_2026';

    /**
     * Genera la llave binaria a partir del salt
     */
    private static function getKey() {
        return hash('sha256', self::$salt, true);
    }

    /**
     * Genera el IV a partir del iv_salt
     */
    private static function getIV() {
        return substr(hash('sha256', self::$iv_salt, true), 0, 16);
    }

    /**
     * Cifra un texto en texto plano
     * @param string $data
     * @return string (base64)
     */
    public static function encrypt($data) {
        if ($data === null || $data === '') return '';
        
        try {
            $key = self::getKey();
            $iv = self::getIV();
            $encrypted = openssl_encrypt($data, self::$method, $key, 0, $iv);
            
            if ($encrypted === false) {
                throw new Exception("Error al encriptar los datos: " . openssl_error_string());
            }
            
            return base64_encode($encrypted);
        } catch (Exception $e) {
            error_log("Vault Error (encrypt): " . $e->getMessage());
            // Fallback de contingencia (Base64 simple)
            return 'FALLBACK_B64:' . base64_encode($data);
        }
    }

    /**
     * Descifra un texto cifrado
     * @param string $encryptedData (base64)
     * @return string
     */
    public static function decrypt($encryptedData) {
        if ($encryptedData === null || $encryptedData === '') return '';

        try {
            // Verificar si es fallback
            if (strpos($encryptedData, 'FALLBACK_B64:') === 0) {
                return base64_decode(substr($encryptedData, 13));
            }

            $decoded = base64_decode($encryptedData);
            if ($decoded === false) return '';

            $key = self::getKey();
            $iv = self::getIV();
            $decrypted = openssl_decrypt($decoded, self::$method, $key, 0, $iv);

            if ($decrypted === false) {
                // Si falla, probar si era texto plano original antes del cifrado
                return $encryptedData;
            }

            return $decrypted;
        } catch (Exception $e) {
            error_log("Vault Error (decrypt): " . $e->getMessage());
            return '';
        }
    }
}
