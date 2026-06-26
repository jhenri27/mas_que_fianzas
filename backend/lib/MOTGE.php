<?php
/**
 * MOTGE: Motor de Generación de Experiencia de BOTS
 * Sistema Inteligente de Diagnóstico y Autocuración Asistida (NOFTRAB)
 */

class MOTGE {
    private static $initialized = false;

    /**
     * Inicializar el motor global de intercepción
     */
    public static function init() {
        if (self::$initialized) return;
        
        // Registrar handlers globales de PHP
        set_exception_handler([self::class, 'handleException']);
        set_error_handler([self::class, 'handleError']);
        
        self::$initialized = true;
    }

    /**
     * Manejador de Excepciones PHP
     */
    public static function handleException($exception) {
        $msg = $exception->getMessage();
        $code = $exception->getCode();
        $file = $exception->getFile();
        $line = $exception->getLine();
        $trace = $exception->getTraceAsString();

        // Determinar el módulo afectado basado en el archivo
        $modulo = self::detectModule($file);
        
        // Generar una firma de error única (SHA256 del mensaje de error y el archivo/línea)
        $clean_msg = preg_replace('/\'[^\']+\'|"[^"]+"|\d+/', '?', $msg); // Sanitizar nombres de tablas/IDs para unificar firmas
        $signature = hash('sha256', $clean_msg . '|' . basename($file) . '|' . $line);

        $error_desc = "Exception: [$code] $msg in " . basename($file) . " on line $line";

        self::processAnomaly($signature, $error_desc, $modulo, $trace);
    }

    /**
     * Convertir Errores PHP a Excepciones
     */
    public static function handleError($errno, $errstr, $errfile, $errline) {
        // Ignorar avisos menores o códigos suprimidos con @
        if (!(error_reporting() & $errno)) {
            return false;
        }
        
        // No interceptar avisos menores no críticos para no saturar
        if ($errno === E_NOTICE || $errno === E_USER_NOTICE || $errno === E_DEPRECATED || $errno === E_USER_DEPRECATED) {
            return false;
        }

        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    }

    /**
     * Procesar la anomalía detectada
     */
    public static function processAnomaly($signature, $error_desc, $modulo, $trace) {
        try {
            $db = self::getDBConnection();
            if (!$db) return;

            // 1. Guardar la incidencia en motge_incidencias
            $stmt = $db->prepare("INSERT INTO motge_incidencias (firma_error, modulo_afectado, descripcion_error, stack_trace, fecha_registro) VALUES (?, ?, ?, ?, NOW())");
            if ($stmt) {
                $stmt->bind_param("ssss", $signature, $modulo, $error_desc, $trace);
                $stmt->execute();
                $incidencia_id = $stmt->insert_id;
                $stmt->close();

                // 2. Buscar si hay una solución / experiencia previa
                $plan_resolucion = self::retrievePlan($signature, $error_desc, $modulo, $db);

                // 3. Notificar al Administrador inyectando ticket y alerta
                self::notifyAdmin($signature, $modulo, $error_desc, $plan_resolucion, $db);
            }
        } catch (Exception $e) {
            // Failsafe: Evitar bucle infinito si la DB falla al registrar el error
        }
    }

    /**
     * Detectar el módulo según la ruta del archivo
     */
    private static function detectModule($file) {
        $file = str_replace('\\', '/', $file);
        if (strpos($file, 'ContabilidadManager') !== false || strpos($file, 'MotorContable') !== false) {
            return 'Contabilidad';
        }
        if (strpos($file, 'NCF') !== false) {
            return 'NCF Sequencer';
        }
        if (strpos($file, 'helpdesk') !== false) {
            return 'Helpdesk';
        }
        if (strpos($file, 'chat') !== false) {
            return 'Chat-CSR';
        }
        if (strpos($file, 'auth') !== false || strpos($file, 'permisos') !== false) {
            return 'Seguridad';
        }
        return 'General';
    }

    /**
     * Obtener conexión segura a Base de Datos
     */
    private static function getDBConnection() {
        if (class_exists('Database')) {
            try {
                return Database::getInstance()->getConnection();
            } catch (Exception $e) {
                return null;
            }
        }
        return null;
    }

    /**
     * Obtener o generar plan de corrección (Default Planning NOFTRAB)
     */
    private static function retrievePlan($signature, $error_desc, $modulo, $db) {
        // Consultar si ya existe una corrección exitosa registrada para esta firma
        $stmt = $db->prepare("SELECT solucion_propuesta, comando_autocuracion FROM motge_experiencia WHERE firma_error = ? AND exito_confirmado = 1 LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $signature);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $stmt->close();
                return [
                    "solucion" => $row['solucion_propuesta'],
                    "comando" => $row['comando_autocuracion'],
                    "tipo" => "experiencia_previa"
                ];
            }
            $stmt->close();
        }

        // Si no hay experiencia previa, el motor genera un plan básico inteligente basado en heurísticas
        $solucion = "Verificar consistencia y realizar diagnóstico atómico de dependencias.";
        $comando = "";

        if (stripos($error_desc, "Table") !== false && stripos($error_desc, "doesn't exist") !== false) {
            preg_match("/table\s+'?([a-zA-Z0-9_-]+)'?/i", $error_desc, $matches);
            $table = $matches[1] ?? 'tabla_desconocida';
            $solucion = "Reconstruir la tabla faltante `$table` usando las semillas de base de datos.";
            $comando = "REBUILD_TABLE:" . $table;
        } elseif (stripos($error_desc, "Access denied") !== false || stripos($error_desc, "permission") !== false) {
            $solucion = "Restablecer los privilegios del perfil y refrescar las mallas de permisos del sistema.";
            $comando = "RESET_PERMISSIONS";
        } elseif (stripos($error_desc, "NCF") !== false || stripos($error_desc, "secuencia") !== false) {
            $solucion = "Recalibrar la secuencia de NCF en el administrador contable para evitar duplicidades.";
            $comando = "RECALIBRATE_NCF";
        }

        return [
            "solucion" => $solucion,
            "comando" => $comando,
            "tipo" => "heuristica_nueva"
        ];
    }

    /**
     * Crear el ticket de soporte de incidencia y enviar mensaje/alerta
     */
    private static function notifyAdmin($signature, $modulo, $error_desc, $plan, $db) {
        // 1. Crear un ticket de soporte automático asignado al Bot (usuario_id = NULL)
        $titulo = "💡 MOTGE: Anomalía en Módulo " . $modulo;
        $detalle = "Se detectó la siguiente incidencia en el sistema:\n\n" . $error_desc . "\n\nFirma de error: " . $signature;
        $estado = "PENDIENTE";
        
        $stmt = $db->prepare("INSERT INTO tickets_soporte (usuario_id, modulo_afectado, titulo, descripcion, estado, fecha_creacion) VALUES (NULL, ?, ?, ?, ?, NOW())");
        if ($stmt) {
            $stmt->bind_param("ssss", $modulo, $titulo, $detalle, $estado);
            $stmt->execute();
            $ticket_id = $stmt->insert_id;
            $stmt->close();

            // 2. Generar el Plan de Acción por Defecto NOFTRAB
            $solucion_texto = $plan['solucion'];
            $cmd = $plan['comando'];
            
            $plan_nof_trab = "### 📋 PLAN DE AUTOCURACIÓN NOFTRAB (Ticket #$ticket_id)\n\n";
            $plan_nof_trab .= "**Problema Detectado:**\n`$error_desc`\n\n";
            $plan_nof_trab .= "**Módulo Afectado:** $modulo\n\n";
            $plan_nof_trab .= "**Acción Propuesta:**\n$solucion_texto\n\n";
            $plan_nof_trab .= "> [!IMPORTANT]\n";
            $plan_nof_trab .= "> **Corrección Atómica Asistida:** La ejecución se realizará en vivo y de forma segura. Cumple con la normativa NOFTRAB (No se suspenderá la plataforma ni se disparará noftrab_backup_runner.php).\n\n";
            
            if (!empty($cmd)) {
                $plan_nof_trab .= "Para autorizar la autocuración, presione **[Autorizar Corrección]** en su panel de administración.";
            } else {
                $plan_nof_trab .= "Se requiere intervención manual del Administrador para resolver esta anomalía.";
            }

            // 3. Obtener el ID del bot BHN-Bot-HelpNow
            $bot_id = 121; // fallback
            $res_bot = $db->query("SELECT id FROM usuarios WHERE username = 'bot.helpnow' LIMIT 1");
            if ($res_bot && $row_bot = $res_bot->fetch_assoc()) {
                $bot_id = (int)$row_bot['id'];
            }

            // El receptor es el Administrador Principal (id = 1)
            $admin_id = 1;

            // Insertar el plan de autocuración como mensaje del Bot al Administrador
            $stmt_msg = $db->prepare("INSERT INTO mensajes_chat (emisor_id, receptor_id, mensaje, fecha_envio, leido) VALUES (?, ?, ?, NOW(), 0)");
            if ($stmt_msg) {
                $stmt_msg->bind_param("iis", $bot_id, $admin_id, $plan_nof_trab);
                $stmt_msg->execute();
                $stmt_msg->close();
            }

            // 4. Registrar en la base de datos de experiencia que este plan fue enviado
            $stmt_exp = $db->prepare("INSERT INTO motge_experiencia (firma_error, solucion_propuesta, comando_autocuracion, incidencia_id, exito_confirmado) VALUES (?, ?, ?, ?, 0) ON DUPLICATE KEY UPDATE incidencia_id = ?");
            if ($stmt_exp) {
                $stmt_exp->bind_param("ssiii", $signature, $solucion_texto, $cmd, $ticket_id, $ticket_id);
                $stmt_exp->execute();
                $stmt_exp->close();
            }
        }
    }
}
