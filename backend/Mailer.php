<?php
/**
 * Simple SMTP Mailer Class - v2.1
 * Sin dependencias externas, con logs configurables y credenciales en JSON.
 * 
 * CORRECCIONES v2.1:
 * - EHLO usa hostname real del servidor (no el servidor SMTP remoto)
 * - Cabeceras Date, Message-ID, Reply-To, X-Mailer añadidas (requeridas para evitar spam)
 * - Content-Transfer-Encoding: base64 para mejor compatibilidad UTF-8
 * - Log completo de conversación SMTP para diagnóstico real
 * - server_parse() ahora registra TODAS las respuestas del servidor
 */
class Mailer {
    private $server;
    private $port;
    private $username;
    private $password;
    private $timeout;
    private $encryption;
    private $fromName;
    
    private $logFile;

    public function __construct($manualConfig = null) {
        $this->logFile = __DIR__ . '/logs/smtp.log';
        $configFile    = __DIR__ . '/config/smtp.json';

        $config = [];
        if ($manualConfig) {
            $config = $manualConfig;
        } elseif (file_exists($configFile)) {
            $config = json_decode(file_get_contents($configFile), true);
        }

        if (!empty($config)) {
            $this->server     = $config['server']     ?? '';
            $this->port       = intval($config['port'] ?? 465);
            $this->username   = $config['username']   ?? '';
            $this->password   = $config['password']   ?? '';
            $this->timeout    = intval($config['timeout'] ?? 15);
            $this->encryption = strtolower($config['encryption'] ?? 'ssl');
            $this->fromName   = $config['from_name']  ?? 'MAS QUE FIANZAS';

            // Si la contraseña es el marcador de posición (asteriscos), leer la real del archivo
            if (preg_match('/^\*+$/', $this->password) && file_exists($configFile)) {
                $savedConfig = json_decode(file_get_contents($configFile), true);
                if ($savedConfig && isset($savedConfig['password'])) {
                    $this->password = $savedConfig['password'];
                }
            }
        } else {
            $this->server     = 'mail.masquefianzas.com';
            $this->port       = 465;
            $this->username   = 'info@masquefianzas.com';
            $this->password   = 'M4sq53F14nz4s';
            $this->timeout    = 15;
            $this->encryption = 'ssl';
            $this->fromName   = 'MAS QUE FIANZAS';
            $this->log_message("Config no encontrada o vacía, usando valores por defecto.", 'WARNING');
        }
    }

    // ─── Logging ────────────────────────────────────────────────────────────

    private function log_message($msg, $level = 'ERROR') {
        $date = date('Y-m-d H:i:s');
        $msg  = mb_convert_encoding($msg, 'UTF-8', 'UTF-8');
        $line = "[{$date}] [{$level}] {$msg}\n";

        if (!is_dir(dirname($this->logFile))) {
            mkdir(dirname($this->logFile), 0777, true);
        }
        file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);

        if ($level === 'ERROR') {
            error_log("SMTP Mailer: " . $msg);
        }
    }

    // ─── Envío principal ────────────────────────────────────────────────────

    public function enviar($to, $subject, $message, $isHtml = true) {
        $NL = "\r\n";

        // Método de cifrado más amplio posible
        $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) $crypto |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) $crypto |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;

        $context = stream_context_create([
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
                'crypto_method'     => $crypto
            ]
        ]);

        // ── FIX 1: EHLO debe usar el hostname LOCAL del servidor que envía,
        //           NO el servidor SMTP remoto. Muchos filtros rechazan mails
        //           donde EHLO no coincide con el PTR / hostname real.
        $localHostname = gethostname() ?: 'localhost';

        $this->log_message("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━", 'INFO');
        $this->log_message("Iniciando envío → {$to}", 'INFO');
        $this->log_message("Servidor: {$this->server}:{$this->port} | Cifrado: {$this->encryption}", 'INFO');
        $this->log_message("EHLO hostname local: {$localHostname}", 'INFO');

        $protocol = ($this->encryption === 'ssl') ? "ssl://" : "";
        $socket   = @stream_socket_client(
            "{$protocol}{$this->server}:{$this->port}",
            $errno, $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$socket) {
            $lastErr = error_get_last();
            $this->log_message("FALLO DE CONEXIÓN TCP: [{$errno}] {$errstr}", 'ERROR');
            if ($lastErr) $this->log_message("PHP detail: " . $lastErr['message'], 'DEBUG');
            $this->log_message("► CAUSA PROBABLE: Puerto bloqueado por firewall/hosting, o servidor SMTP incorrecto.", 'HINT');
            return false;
        }

        stream_set_timeout($socket, $this->timeout);

        // Saludo del servidor
        if (!$this->server_parse($socket, "220", "GREETING")) return false;

        // ── FIX 1 aplicado: usamos $localHostname en EHLO
        $this->smtp_write($socket, "EHLO {$localHostname}", $NL);
        if (!$this->server_parse($socket, "250", "EHLO")) return false;

        // STARTTLS si es TLS explícito (puerto 587)
        if ($this->encryption === 'tls') {
            $this->log_message("Enviando STARTTLS...", 'INFO');
            $this->smtp_write($socket, "STARTTLS", $NL);
            if (!$this->server_parse($socket, "220", "STARTTLS")) return false;

            if (!stream_socket_enable_crypto($socket, true, $crypto)) {
                $this->log_message("Fallo al negociar TLS (Handshake). Verifica que el puerto 587 acepte STARTTLS.", 'ERROR');
                return false;
            }
            $this->log_message("Cifrado TLS establecido.", 'SUCCESS');

            // Re-EHLO después del cifrado
            $this->smtp_write($socket, "EHLO {$localHostname}", $NL);
            if (!$this->server_parse($socket, "250", "EHLO post-TLS")) return false;
        }

        // Autenticación
        $this->smtp_write($socket, "AUTH LOGIN", $NL);
        if (!$this->server_parse($socket, "334", "AUTH LOGIN")) return false;

        $this->smtp_write($socket, base64_encode($this->username), $NL);
        if (!$this->server_parse($socket, "334", "USERNAME")) return false;

        $this->smtp_write($socket, base64_encode($this->password), $NL);
        if (!$this->server_parse($socket, "235", "PASSWORD")) {
            $this->log_message("► CAUSA PROBABLE: Credenciales incorrectas (usuario/contraseña). Verifica en cPanel.", 'HINT');
            return false;
        }
        $this->log_message("Autenticación exitosa.", 'SUCCESS');

        // Sobre del mensaje
        $this->smtp_write($socket, "MAIL FROM:<{$this->username}>", $NL);
        if (!$this->server_parse($socket, "250", "MAIL FROM")) return false;

        $this->smtp_write($socket, "RCPT TO:<{$to}>", $NL);
        if (!$this->server_parse($socket, "250", "RCPT TO")) {
            $this->log_message("► CAUSA PROBABLE: Dirección destino rechazada. Puede ser lista negra o relay denegado.", 'HINT');
            return false;
        }

        $this->smtp_write($socket, "DATA", $NL);
        if (!$this->server_parse($socket, "354", "DATA")) return false;

        // ── FIX 2: Cabeceras completas y correctas
        //    Date y Message-ID son obligatorios por RFC 5322.
        //    Sin ellos Gmail y Outlook suelen filtrar como spam o desechar.
        $subjectEncoded  = "=?UTF-8?B?" . base64_encode($subject) . "?=";
        $fromNameEncoded = "=?UTF-8?B?" . base64_encode($this->fromName) . "?=";
        $messageId       = '<' . uniqid('mqf.', true) . '@' . $this->server . '>';
        $date            = date('r'); // RFC 2822 date format

        // ── FIX 3: Cuerpo en base64 para máxima compatibilidad UTF-8
        if ($isHtml) {
            $contentType = "text/html; charset=UTF-8";
        } else {
            $contentType = "text/plain; charset=UTF-8";
        }
        $bodyEncoded = base64_encode($message);

        $headers  = "From: {$fromNameEncoded} <{$this->username}>" . $NL;
        $headers .= "To: {$to}" . $NL;
        $headers .= "Reply-To: {$this->username}" . $NL;
        $headers .= "Date: {$date}" . $NL;
        $headers .= "Message-ID: {$messageId}" . $NL;
        $headers .= "Subject: {$subjectEncoded}" . $NL;
        $headers .= "MIME-Version: 1.0" . $NL;
        $headers .= "Content-Type: {$contentType}" . $NL;
        $headers .= "Content-Transfer-Encoding: base64" . $NL;
        $headers .= "X-Mailer: MasQueFianzas-Mailer/2.1" . $NL;
        $headers .= "X-Priority: 3" . $NL;

        // Dividir el body en líneas de 76 chars (estándar MIME base64)
        $bodyLines = chunk_split($bodyEncoded, 76, $NL);

        fputs($socket, $headers . $NL . $bodyLines . $NL . "." . $NL);
        if (!$this->server_parse($socket, "250", "END DATA")) {
            $this->log_message("► Servidor rechazó el mensaje tras DATA. Posible filtro de contenido o reputación de IP.", 'HINT');
            return false;
        }

        $this->smtp_write($socket, "QUIT", $NL);
        fclose($socket);

        $this->log_message("✔ Correo enviado exitosamente a {$to}", 'SUCCESS');
        $this->log_message("  Message-ID: {$messageId}", 'INFO');
        $this->log_message("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━", 'INFO');
        return true;
    }

    // ─── Helpers internos ───────────────────────────────────────────────────

    /** Escribe un comando al socket y lo registra en el log */
    private function smtp_write($socket, $command, $NL) {
        // No loguear la contraseña en base64
        if (base64_decode($command) === $this->password) {
            $this->log_message("C → [PASSWORD OCULTA]", 'SMTP');
        } else {
            $this->log_message("C → " . $command, 'SMTP');
        }
        fputs($socket, $command . $NL);
    }

    /** Lee la respuesta del servidor y verifica el código esperado */
    private function server_parse($socket, $expected, $step = '') {
        $server_response = '';
        $attempts = 0;
        while (substr($server_response, 3, 1) !== ' ') {
            if (!($server_response = fgets($socket, 512))) {
                $this->log_message("Error al leer respuesta SMTP en paso [{$step}].", 'ERROR');
                return false;
            }
            $this->log_message("S ← " . rtrim($server_response), 'SMTP');
            if (++$attempts > 30) break; // Evitar bucle infinito
        }

        $code = substr($server_response, 0, 3);
        if ($code !== $expected) {
            $this->log_message("Respuesta inesperada en [{$step}]: esperaba {$expected}, recibió {$code} → " . trim($server_response), 'ERROR');
            return false;
        }
        return true;
    }
}
?>
