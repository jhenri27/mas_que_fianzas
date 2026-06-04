<?php
require_once dirname(__FILE__) . '/config.php';
require_once dirname(__FILE__) . '/CobroManager.php';
require_once dirname(__FILE__) . '/PolizaManager.php';
require_once dirname(__FILE__) . '/Mailer.php';

/**
 * Bot de Gestión de Cobros Automatizado (Fase 1 - Opcional)
 * MAS QUE FIANZAS - Core Asegurador v3.0
 */
class CobroBot {
    private $db;
    private $mailer;
    private $cobroManager;

    public function __construct() {
        try {
            $this->db = Database::getInstance()->getConnection();
            $this->mailer = new Mailer();
            $this->cobroManager = new CobroManager();
        } catch (Exception $e) {
            throw new Exception("Error al inicializar CobroBot: " . $e->getMessage());
        }
    }

    /**
     * Escanea y procesa las notificaciones automáticas para todas las pólizas activas
     */
    public function ejecutarSecuenciaDiaria() {
        // Verificar si el bot está activo globalmente en la configuración del sistema
        $res_cfg = $this->db->query("SELECT valor_config FROM configuracion_sistema WHERE clave_config = 'PGC_BOT_ACTIVO' LIMIT 1");
        $botActivo = 1; // Activo por defecto
        if ($res_cfg && $row_cfg = $res_cfg->fetch_assoc()) {
            $botActivo = intval($row_cfg['valor_config']);
        }

        if ($botActivo === 0) {
            echo "[" . date('Y-m-d H:i:s') . "] BOT INACTIVO: Desactivado en la configuración general del sistema.\n";
            return 0;
        }

        // Ejecutar job diario de promesas vencidas antes
        $promesasVencidasMod = $this->cobroManager->verificarPromesasVencidas();
        echo "[" . date('Y-m-d H:i:s') . "] Job de Promesas: Se actualizaron $promesasVencidasMod promesas vencidas.\n";

        // Obtener pólizas activas
        $polizaManager = new PolizaManager();
        $polizas = $polizaManager->obtenerPolizas();

        $enviados = 0;
        foreach ($polizas as $poliza) {
            // Regla: Excluir pólizas marcadas manualmente
            if (isset($poliza['bot_excluir']) && $poliza['bot_excluir'] == 1) {
                continue;
            }

            // Buscar cuotas pendientes de esta póliza
            $sql_cuotas = "SELECT * FROM pagos WHERE poliza_id = ? AND estado_pago = 'pendiente' ORDER BY fecha_pago ASC";
            $stmt = $this->db->prepare($sql_cuotas);
            $stmt->bind_param("i", $poliza['id']);
            $stmt->execute();
            $cuotas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            if (empty($cuotas)) {
                continue;
            }

            // Evaluamos la primera cuota vencida o por vencer
            $proximaCuota = $cuotas[0];
            $fechaPago = $proximaCuota['fecha_pago'];
            $hoy = date('Y-m-d');

            // Calcular diferencia en días (dias_mora > 0 es vencido, dias_mora < 0 es preventivo)
            $diff = (strtotime($hoy) - strtotime($fechaPago)) / (60 * 60 * 24);
            $diasDiferencia = intval($diff);

            // Validar política de envío (JIT Check de NOFTRAB)
            if (!$this->evaluarPoliticaEnvio($proximaCuota, $poliza)) {
                continue;
            }

            // Determinar secuencia según los días de diferencia
            $canal = null;
            $asunto = "";
            $plantilla = "";
            $identificadorSecuencia = "";

            if ($diasDiferencia === -3) {
                $canal = 'correo';
                $identificadorSecuencia = "Día -3 (Recordatorio Preventivo)";
                $asunto = "Recordatorio de pago: Tu cuota vence en 3 días - Póliza #" . $poliza['numero_poliza'];
                $plantilla = $this->obtenerPlantillaPreventiva($poliza, $proximaCuota, 3);
            } elseif ($diasDiferencia === 0) {
                $canal = 'correo_whatsapp';
                $identificadorSecuencia = "Día 0 (Recordatorio Vencimiento)";
                $asunto = "Tu cuota vence hoy: Pago Póliza #" . $poliza['numero_poliza'];
                $plantilla = $this->obtenerPlantillaVencimiento($poliza, $proximaCuota);
            } elseif ($diasDiferencia === 7) {
                $canal = 'correo';
                $identificadorSecuencia = "Día +7 (Aviso de Mora Leve)";
                $asunto = "Segundo Aviso: Pago pendiente de Póliza #" . $poliza['numero_poliza'];
                $plantilla = $this->obtenerPlantillaMora($poliza, $proximaCuota, 7);
            } elseif ($diasDiferencia === 15) {
                $canal = 'correo_whatsapp';
                $identificadorSecuencia = "Día +15 (Aviso Mora Media)";
                $asunto = "ADVERTENCIA DE COBERTURA: Pago pendiente de Póliza #" . $poliza['numero_poliza'];
                $plantilla = $this->obtenerPlantillaMora($poliza, $proximaCuota, 15);
            } elseif ($diasDiferencia >= 30 || $poliza['dias_cobertura_restante_prorrata'] < 0) {
                // Alerta crítica / Tiempo Temerario
                $canal = 'correo_whatsapp';
                $identificadorSecuencia = "Alerta Crítica (Tiempo Temerario / Mora 30+)";
                $asunto = "URGENTE: Suspensión de cobertura por falta de pago - Póliza #" . $poliza['numero_poliza'];
                $plantilla = $this->obtenerPlantillaCritica($poliza, $proximaCuota);
            }

            // Despachar si hay secuencia calificada
            if ($canal && !empty($poliza['cliente_email'])) {
                // Registrar que ya se envió esta notificación hoy para no duplicar en el mismo día
                if ($this->verificarNotificacionHoy($poliza['id'], $identificadorSecuencia)) {
                    continue;
                }

                $despachado = $this->despacharNotificacion($poliza, $proximaCuota, $canal, $asunto, $plantilla, $identificadorSecuencia);
                if ($despachado) {
                    $enviados++;
                }
            }
        }

        return $enviados;
    }

    /**
     * Regla JIT (Just-In-Time) de NOFTRAB:
     * Verifica en tiempo real si el cobro se puede procesar o si hay un pago pendiente de validar.
     */
    private function evaluarPoliticaEnvio($cuota, $poliza) {
        // 1. Exclusión explícita
        if ($poliza['bot_excluir'] == 1) {
            return false;
        }

        // 2. Si no hay balance real
        if (floatval($cuota['monto']) <= 0) {
            return false;
        }

        // 3. JIT CHECK (NOFTRAB): ¿Existe un pago en tránsito/pendiente con comprobante físico cargado?
        // Si hay una transferencia o depósito pendiente de auditoría, se bloquea la alerta automática.
        $sql_trancito = "SELECT COUNT(*) as cnt 
                         FROM pagos p
                         JOIN documentos_poliza d ON d.pago_id = p.id
                         WHERE p.poliza_id = ? AND p.estado_pago = 'pendiente' AND d.tipo_documento = 'soporte_pago'";
        
        $stmt = $this->db->prepare($sql_trancito);
        $stmt->bind_param("i", $poliza['id']);
        $stmt->execute();
        $cnt = $stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();

        if ($cnt > 0) {
            // Hay un pago en tránsito, omitimos la cobranza automatizada temporalmente
            return false;
        }

        return true;
    }

    /**
     * Verifica si el bot ya envió esta alerta específica a esta póliza hoy
     */
    private function verificarNotificacionHoy($polizaId, $secuencia) {
        $sql = "SELECT COUNT(*) as cnt FROM cf_gestiones_cobro 
                WHERE poliza_id = ? AND tipo_gestion = 'bot_notificacion' 
                  AND descripcion LIKE ? AND DATE(fecha_gestion) = DATE(NOW())";
        
        $match = "%" . $secuencia . "%";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("is", $polizaId, $match);
        $stmt->execute();
        $cnt = $stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();

        return ($cnt > 0);
    }

    /**
     * Despacha el mensaje e inserta de forma INMUTABLE el log en cf_gestiones_cobro
     */
    private function despacharNotificacion($poliza, $cuota, $canal, $asunto, $cuerpoHtml, $secuencia) {
        // Enviar correo real
        $correoEnviado = false;
        if (!empty($poliza['cliente_email'])) {
            try {
                $correoEnviado = $this->mailer->enviar($poliza['cliente_email'], $asunto, $cuerpoHtml);
            } catch (Exception $e) {
                error_log("Error al despachar correo del bot: " . $e->getMessage());
            }
        }

        // Si el canal incluye WhatsApp, simulamos el disparo de WhatsApp/SMS en consola
        $whatsappSimulado = "";
        if (strpos($canal, 'whatsapp') !== false) {
            $whatsappSimulado = " | WhatsApp automático enviado al celular " . ($poliza['cliente_telefono'] ?? 'S/N');
        }

        // Registrar registro de bitácora (NOFTRAB: Append-only)
        $descripcionLog = "🤖 Bot de Cobranza ejecutó secuencia: $secuencia vía " . strtoupper($canal) . "." . $whatsappSimulado;
        $usuarioBotId = 1; // Administrador del sistema
        
        $sql = "INSERT INTO cf_gestiones_cobro (
                    poliza_id, usuario_id, tipo_gestion, descripcion, 
                    fecha_promesa, monto_prometido, estado_promesa
                ) VALUES (?, ?, 'bot_notificacion', ?, null, null, 'n/a')";
        
        $stmt = $this->db->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("iis", $poliza['id'], $usuarioBotId, $descripcionLog);
            $stmt->execute();
            $stmt->close();
        }

        return $correoEnviado;
    }

    // =========================================================================
    // PLANTILLAS DE CORREO AUTOMÁTICAS
    // =========================================================================

    private function obtenerPlantillaPreventiva($poliza, $cuota, $dias) {
        $monto = number_format($cuota['monto'], 2);
        return "
            <div style='font-family:Arial,sans-serif;max-width:550px;margin:0 auto;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;'>
                <div style='background:#1e293b;padding:20px;text-align:center;color:#fff;'>
                    <h2 style='margin:0;font-size:18px;'>RECORDATORIO PREVENTIVO DE PAGO</h2>
                </div>
                <div style='padding:28px;'>
                    <p>Hola <strong>{$poliza['cliente_nombre']}</strong>,</p>
                    <p>Te recordamos que la cuota de tu póliza de seguro <strong>#{$poliza['numero_poliza']}</strong> está próxima a vencer en <strong>{$dias} días</strong>.</p>
                    <div style='background:#f8fafc;border:1px solid #cbd5e1;border-radius:8px;padding:16px;margin:20px 0;'>
                        <p style='margin:0 0 6px;color:#64748b;font-size:12px;text-transform:uppercase;'>Detalles de la Cuota</p>
                        <p style='margin:0 0 4px;'><strong>Póliza:</strong> #{$poliza['numero_poliza']} ({$poliza['aseguradora']})</p>
                        <p style='margin:0 0 4px;'><strong>Monto Cuota:</strong> RD$ {$monto}</p>
                        <p style='margin:0;'><strong>Vence el:</strong> {$cuota['fecha_pago']}</p>
                    </div>
                    <p>Evita retrasos e interrupciones en tus coberturas pagando cómodamente desde nuestro portal.</p>
                    <div style='text-align:center;margin-top:25px;'>
                        <a href='http://localhost/PLATAFORMA_INTEGRADA/frontend/' style='display:inline-block;padding:12px 28px;background:#4f46e5;color:#fff;border-radius:6px;text-decoration:none;font-weight:bold;'>Ir al Portal de Pagos</a>
                    </div>
                </div>
                <div style='background:#f1f5f9;padding:12px 28px;font-size:11px;color:#94a3b8;text-align:center;'>
                    Este es un aviso automático generado por el asistente virtual de MAS QUE FIANZAS.
                </div>
            </div>
        ";
    }

    private function obtenerPlantillaVencimiento($poliza, $cuota) {
        $monto = number_format($cuota['monto'], 2);
        return "
            <div style='font-family:Arial,sans-serif;max-width:550px;margin:0 auto;border:1px solid #fbcfe8;border-radius:8px;overflow:hidden;'>
                <div style='background:#db2777;padding:20px;text-align:center;color:#fff;'>
                    <h2 style='margin:0;font-size:18px;'>TU CUOTA VENCE HOY</h2>
                </div>
                <div style='padding:28px;'>
                    <p>Estimado(a) <strong>{$poliza['cliente_nombre']}</strong>,</p>
                    <p>Le notificamos que el día de hoy, <strong>{$cuota['fecha_pago']}</strong>, es la fecha límite para el pago de la cuota de su póliza <strong>#{$poliza['numero_poliza']}</strong>.</p>
                    <div style='background:#fdf2f8;border:1px solid #fbcfe8;border-radius:8px;padding:16px;margin:20px 0;'>
                        <p style='margin:0 0 6px;color:#db2777;font-weight:bold;font-size:12px;text-transform:uppercase;'>Desglose del Pago</p>
                        <p style='margin:0 0 4px;'><strong>Póliza:</strong> #{$poliza['numero_poliza']} ({$poliza['aseguradora']})</p>
                        <p style='margin:0;'><strong>Total a Pagar Hoy:</strong> RD$ {$monto}</p>
                    </div>
                    <p>Para asegurar la continuidad total de su póliza, le exhortamos a efectuar su pago de inmediato.</p>
                    <div style='text-align:center;margin-top:25px;'>
                        <a href='http://localhost/PLATAFORMA_INTEGRADA/frontend/' style='display:inline-block;padding:12px 28px;background:#db2777;color:#fff;border-radius:6px;text-decoration:none;font-weight:bold;'>Pagar Online Ahora</a>
                    </div>
                </div>
                <div style='background:#f1f5f9;padding:12px 28px;font-size:11px;color:#94a3b8;text-align:center;'>
                    Mensaje automatizado de cobros. Favor no responder a este correo.
                </div>
            </div>
        ";
    }

    private function obtenerPlantillaMora($poliza, $cuota, $diasMora) {
        $monto = number_format($cuota['monto'], 2);
        return "
            <div style='font-family:Arial,sans-serif;max-width:550px;margin:0 auto;border:1px solid #fed7aa;border-radius:8px;overflow:hidden;'>
                <div style='background:#ea580c;padding:20px;text-align:center;color:#fff;'>
                    <h2 style='margin:0;font-size:18px;'>AVISO DE PAGO COMPROMETIDO (MORA: {$diasMora} DÍAS)</h2>
                </div>
                <div style='padding:28px;'>
                    <p>Estimado(a) <strong>{$poliza['cliente_nombre']}</strong>,</p>
                    <p>Nuestros registros indican que su póliza <strong>#{$poliza['numero_poliza']}</strong> presenta un retraso de <strong>{$diasMora} días</strong> en la cuota vencida el pasado <strong>{$cuota['fecha_pago']}</strong>.</p>
                    <div style='background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:16px;margin:20px 0;'>
                        <p style='margin:0 0 6px;color:#ea580c;font-weight:bold;font-size:12px;text-transform:uppercase;'>Estado de Cuenta</p>
                        <p style='margin:0 0 4px;'><strong>Póliza:</strong> #{$poliza['numero_poliza']} ({$poliza['aseguradora']})</p>
                        <p style='margin:0 0 4px;'><strong>Monto Cuota:</strong> RD$ {$monto}</p>
                        <p style='margin:0;'><strong>Días de Retraso:</strong> {$diasMora} días</p>
                    </div>
                    <p>Le recordamos que circular sin sus coberturas de seguro vigentes representa un riesgo financiero y civil grave.</p>
                    <div style='text-align:center;margin-top:25px;'>
                        <a href='http://localhost/PLATAFORMA_INTEGRADA/frontend/' style='display:inline-block;padding:12px 28px;background:#ea580c;color:#fff;border-radius:6px;text-decoration:none;font-weight:bold;'>Realizar Pago</a>
                    </div>
                </div>
            </div>
        ";
    }

    private function obtenerPlantillaCritica($poliza, $cuota) {
        $monto = number_format($cuota['monto'], 2);
        $diasR = $poliza['dias_cobertura_restante_prorrata'];
        $diasR_abs = abs($diasR);
        return "
            <div style='font-family:Arial,sans-serif;max-width:550px;margin:0 auto;border:1px solid #fecaca;border-radius:8px;overflow:hidden;'>
                <div style='background:#dc2626;padding:20px;text-align:center;color:#fff;'>
                    <h2 style='margin:0;font-size:18px;'>⚠️ URGENTE: EXCESO DE COBERTURA / SUSPENSIÓN DE PÓLIZA</h2>
                </div>
                <div style='padding:28px;'>
                    <p>ATENCIÓN URGENTE <strong>{$poliza['cliente_nombre']}</strong>,</p>
                    <p>Le informamos que su póliza de seguro <strong>#{$poliza['numero_poliza']}</strong> ha excedido el tiempo de cobertura financiado por sus pagos en <strong>{$diasR_abs} días (Tiempo Temerario)</strong>.</p>
                    <div style='background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:16px;margin:20px 0;'>
                        <p style='margin:0 0 6px;color:#dc2626;font-weight:bold;font-size:12px;text-transform:uppercase;'>Alerta de Riesgo Técnico</p>
                        <p style='margin:0 0 4px;'><strong>Póliza:</strong> #{$poliza['numero_poliza']} ({$poliza['aseguradora']})</p>
                        <p style='margin:0 0 4px;'><strong>Monto Adeudado:</strong> RD$ {$monto}</p>
                        <p style='margin:0;color:#dc2626;'><strong>Estatus:</strong> Cobertura no financiada (Vencimiento de prorrata superado)</p>
                    </div>
                    <p style='color:#7f1d1d;font-weight:bold;'>⚠️ Su póliza se encuentra en proceso de cancelación y suspensión automática de coberturas. En caso de siniestro, la compañía aseguradora podría declinar formalmente la indemnización.</p>
                    <p>Regularice su cuenta hoy mismo cargando su comprobante o pagando en línea.</p>
                    <div style='text-align:center;margin-top:25px;'>
                        <a href='http://localhost/PLATAFORMA_INTEGRADA/frontend/' style='display:inline-block;padding:12px 28px;background:#dc2626;color:#fff;border-radius:6px;text-decoration:none;font-weight:bold;'>Regularizar Cuenta de Inmediato</a>
                    </div>
                </div>
            </div>
        ";
    }
}

// Permitir que el bot sea invocado directamente vía CLI por programador de tareas
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    try {
        $bot = new CobroBot();
        $notificaciones = $bot->ejecutarSecuenciaDiaria();
        echo "[" . date('Y-m-d H:i:s') . "] BOT EJECUTADO CON ÉXITO: Se enviaron $notificaciones notificaciones.\n";
        exit(0);
    } catch (Exception $e) {
        echo "Error ejecutando CobroBot: " . $e->getMessage() . "\n";
        exit(1);
    }
}
?>
