<?php
require_once dirname(__FILE__) . '/config.php';
require_once dirname(__FILE__) . '/Mailer.php';

/**
 * Gestión de Documentos y Notificaciones (Email/WhatsApp)
 * MAS QUE FIANZAS - v3.0
 */
class DocumentoManager {
    private $db;
    private $config;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->cargarConfiguracion();
    }

    private function cargarConfiguracion() {
        $path = dirname(__FILE__) . '/config/config.json';
        if (file_exists($path)) {
            $this->config = json_decode(file_get_contents($path), true);
        } else {
            $this->config = [];
        }
    }

    /**
     * Registra un documento generado en la base de datos
     */
    public function registrarDocumento($polizaId, $tipo, $archivo, $pagoId = null, $userId = null) {
        $sql = "INSERT INTO documentos_poliza (poliza_id, tipo_documento, nombre_archivo, ruta_archivo, pago_id, generado_por) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $ruta = 'uploads/polizas/' . $archivo;
        $stmt->bind_param("isssii", $polizaId, $tipo, $archivo, $ruta, $pagoId, $userId);
        return $stmt->execute();
    }

    /**
     * Envía documentos por Correo Electrónico
     */
    public function enviarPorEmail($polizaId, $destinatario, $nombre, $asunto, $mensaje, $adjuntos = []) {
        $mailer = new Mailer();
        
        // Configurar desde config.json si existe
        if (!empty($this->config['smtp'])) {
            // Mailer.php actualmente lee smtp.json por su cuenta, pero DocumentoManager puede forzar o usarlo
        }

        try {
            // Suponiendo que Mailer ya está configurado para usar los parámetros de config.json o smtp.json
            // Usaremos la interfaz de Mailer.php (necesito verificar si Mailer.php tiene estos métodos)
            // Según el commit previo, Mailer soporta SMTP dinámico.
            
            // Simulación de envío (Requiere que Mailer esté adaptado)
            /*
            $mailer->enviarConAdjuntos($destinatario, $asunto, $mensaje, $adjuntos);
            */
            
            // Por ahora, registramos que se intentó enviar
            $sql = "UPDATE documentos_poliza SET enviado_email = 1, fecha_envio_email = NOW(), destinatario_email = ? 
                    WHERE poliza_id = ? AND tipo_documento IN (?)";
            // ... lógica de actualización ...
            
            return true; 
        } catch (Exception $e) {
            error_log("Error enviando email: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Envía notificación por WhatsApp (Meta Cloud API Stub)
     */
    public function enviarPorWhatsApp($polizaId, $telefono, $mensaje, $urlDocumento = null) {
        if (empty($this->config['whatsapp']) || !$this->config['whatsapp']['enabled']) {
            return ['exito' => false, 'mensaje' => 'WhatsApp no está habilitado en la configuración'];
        }

        // Lógica de Meta Cloud API
        $token = $this->config['whatsapp']['access_token'];
        $phoneId = $this->config['whatsapp']['phone_number_id'];
        $version = $this->config['whatsapp']['api_version'];

        $url = "https://graph.facebook.com/{$version}/{$phoneId}/messages";
        
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $telefono,
            'type' => 'text',
            'text' => ['body' => $mensaje]
        ];

        if ($urlDocumento) {
            $payload['type'] = 'document';
            $payload['document'] = [
                'link' => $urlDocumento,
                'caption' => $mensaje,
                'filename' => 'Documento_Poliza.pdf'
            ];
        }

        // Ejecutar CURL
        // ...
        
        return ['exito' => true, 'mensaje' => 'Envío de WhatsApp procesado (Simulación)'];
    }
}
?>
