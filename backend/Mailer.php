<?php
/**
 * Simple SMTP Mailer Class
 * Sin dependencias externas, con logs configurables y credenciales en JSON.
 */
class Mailer {
    private $server;
    private $port;
    private $username;
    private $password;
    private $timeout;
    private $encryption;
    
    private $logFile;

    public function __construct($manualConfig = null) {
        $this->logFile = __DIR__ . '/logs/smtp.log';
        $configFile = __DIR__ . '/config/smtp.json';

        $config = [];
        if ($manualConfig) {
            $config = $manualConfig;
        } elseif (file_exists($configFile)) {
            $config = json_decode(file_get_contents($configFile), true);
        }

        if (!empty($config)) {
            $this->server = $config['server'] ?? '';
            $this->port = $config['port'] ?? 465;
            $this->username = $config['username'] ?? '';
            $this->password = $config['password'] ?? '';
            $this->timeout = $config['timeout'] ?? 10;
            $this->encryption = $config['encryption'] ?? 'ssl';

            // Validación preventiva: Si la contraseña es el marcador de posición (asteriscos),
            // y existe un archivo de config real, intentar cargarla de ahí.
            if (preg_match('/^\*+$/', $this->password)) {
                $configFile = __DIR__ . '/config/smtp.json';
                if (file_exists($configFile)) {
                    $savedConfig = json_decode(file_get_contents($configFile), true);
                    if ($savedConfig && isset($savedConfig['password'])) {
                        $this->password = $savedConfig['password'];
                    }
                }
            }
        } else {
            // Valores por defecto como salvavidas
            $this->server = 'mail.masquefianzas.com';
            $this->port = 465;
            $this->username = 'info@masquefianzas.com';
            $this->password = 'M4sq53F14nz4s';
            $this->timeout = 10;
            $this->encryption = 'ssl';
            $this->log_message("Archivo de config no encontrado o vacío, usando defaults.", 'WARNING');
        }
    }

    private function log_message($msg, $level = 'ERROR') {
        $date = date('Y-m-d H:i:s');
        // Asegurar que el mensaje sea UTF-8 válido para evitar fallos en json_encode posterior
        $msg = mb_check_encoding($msg, 'UTF-8') ? $msg : utf8_encode($msg);
        $formatted_msg = "[{$date}] [{$level}] {$msg}\n";
        
        if (!is_dir(dirname($this->logFile))) {
            mkdir(dirname($this->logFile), 0777, true);
        }
        
        file_put_contents($this->logFile, $formatted_msg, FILE_APPEND);
        
        if ($level === 'ERROR') {
            error_log("SMTP Mailer Error: " . $msg);
        }
    }

    public function enviar($to, $subject, $message, $isHtml = true) {
        $newline = "\r\n";
        // Determinar el método de criptografía más compatible disponible
        $crypto_method = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) $crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) $crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;

        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
                'crypto_method' => $crypto_method
            ]
        ]);
        
        $this->log_message("Preparando conexión a {$this->server}:{$this->port} (Modo: {$this->encryption})...", "INFO");
        $this->log_message("Destino: {$to} | Asunto: {$subject}", "INFO");

        // SSL Implícito usa prefijo. TLS (STARTTLS) conecta en plano primero.
        $protocol = ($this->encryption === 'ssl') ? "ssl://" : "";
        
        $socket = @stream_socket_client("{$protocol}{$this->server}:{$this->port}", $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT, $context);
        
        if (!$socket) {
            $lastError = error_get_last();
            $this->log_message("Fallo crítico de conexión: [$errno] $errstr", "ERROR");
            if ($lastError) $this->log_message("Detalle PHP: " . $lastError['message'], "DEBUG");
            return false;
        }
        
        // Timeout para lectura
        stream_set_timeout($socket, $this->timeout);

        if (!$this->server_parse($socket, "220")) return false;
        
        // EHLO Inicial
        fputs($socket, "EHLO " . ($this->server) . $newline);
        if (!$this->server_parse($socket, "250")) return false;

        // Si es TLS (Explicit), enviamos STARTTLS
        if ($this->encryption === 'tls') {
            $this->log_message("Enviando STARTTLS...", "INFO");
            fputs($socket, "STARTTLS" . $newline);
            if (!$this->server_parse($socket, "220")) return false;

            $this->log_message("Iniciando negociación de cifrado (Handshake)...", "INFO");
            // Activar criptografía en el stream existente
            if (!stream_socket_enable_crypto($socket, true, $crypto_method)) {
                $this->log_message("Fallo al negociar TLS (Handshake error).", "ERROR");
                return false;
            }
            $this->log_message("Cifrado TLS establecido correctamente.", "SUCCESS");

            // EHLO de nuevo tras el cifrado
            fputs($socket, "EHLO " . ($this->server) . $newline);
            if (!$this->server_parse($socket, "250")) return false;
        }
        
        fputs($socket, "AUTH LOGIN" . $newline);
        if (!$this->server_parse($socket, "334")) return false;
        
        fputs($socket, base64_encode($this->username) . $newline);
        if (!$this->server_parse($socket, "334")) return false;
        
        fputs($socket, base64_encode($this->password) . $newline);
        if (!$this->server_parse($socket, "235")) return false;
        
        fputs($socket, "MAIL FROM: <{$this->username}>" . $newline);
        if (!$this->server_parse($socket, "250")) return false;
        
        fputs($socket, "RCPT TO: <$to>" . $newline);
        if (!$this->server_parse($socket, "250")) return false;
        
        fputs($socket, "DATA" . $newline);
        if (!$this->server_parse($socket, "354")) return false;
        
        $headers = "From: MAS QUE FIANZAS <{$this->username}>" . $newline;
        $headers .= "To: $to" . $newline;
        
        // Alternativa a mb_encode_mimeheader para evitar dependencia de extensión mbstring
        $subject_encoded = "=?UTF-8?B?" . base64_encode($subject) . "?=";
        $headers .= "Subject: " . $subject_encoded . $newline;
        
        $headers .= "MIME-Version: 1.0" . $newline;
        
        if ($isHtml) {
            $headers .= "Content-Type: text/html; charset=UTF-8" . $newline;
        } else {
            $headers .= "Content-Type: text/plain; charset=UTF-8" . $newline;
        }
        
        fputs($socket, $headers . $newline . $message . $newline . "." . $newline);
        if (!$this->server_parse($socket, "250")) return false;
        
        fputs($socket, "QUIT" . $newline);
        fclose($socket);
        
        $this->log_message("Correo enviado exitosamente a $to", "SUCCESS");
        return true;
    }
    
    private function server_parse($socket, $expected_response) {
        $server_response = '';
        while (substr($server_response, 3, 1) != ' ') {
            if (!($server_response = fgets($socket, 256))) {
                $this->log_message("Error al leer respuesta de SMTP.", "ERROR");
                return false;
            }
        }
        if (!(substr($server_response, 0, 3) == $expected_response)) {
            $this->log_message("SMTP Server error: $server_response", "ERROR");
            return false;
        }
        return true;
    }
}
?>
