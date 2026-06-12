<?php
/**
 * NotificacionesEngine — v1.0
 * MAS QUE FIANZAS — Sistema Integrado
 * ============================================================
 * Librería pura (sin router HTTP) para disparo de notificaciones.
 * Puede ser incluida con require_once desde cualquier API.
 */

if (!defined('NOTIFICACIONES_ENGINE_LOADED')) {
    define('NOTIFICACIONES_ENGINE_LOADED', true);

    /**
     * Registra un intento de envío en el log inmutable (NOFTRAB).
     */
    function notif_logEnvio($db, $flujo_id, $evento, $referencia, $destinatario, $asunto, $resultado, $detalle, $usuario_id) {
        $st = $db->prepare(
            "INSERT INTO log_notificaciones
             (flujo_id, evento, referencia, destinatario, asunto, resultado, detalle, disparado_por)
             VALUES (?,?,?,?,?,?,?,?)"
        );
        if ($st) {
            $st->bind_param('issssssi', $flujo_id, $evento, $referencia, $destinatario, $asunto, $resultado, $detalle, $usuario_id);
            $st->execute();
            $st->close();
        }
    }

    /**
     * Reemplaza variables {{VAR}} con datos del contexto.
     */
    function notif_renderTemplate($template, $ctx) {
        foreach ($ctx as $k => $v) {
            if (is_scalar($v)) {
                $safe = htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
                $template = str_replace('{{' . strtoupper($k) . '}}', $safe, $template);
                $template = str_replace('{{' . $k . '}}', $safe, $template);
            }
        }
        return preg_replace('/\{\{[A-Z_a-z0-9]+\}\}/', '', $template);
    }

    /**
     * Resuelve la dirección de email según tipo de destinatario.
     */
    function notif_resolverEmail($destinatario, $ctx, $db) {
        $tipo = $destinatario['tipo'] ?? 'fijo';
        switch ($tipo) {
            case 'fijo':
                return $destinatario['email_fijo'] ?? null;
            case 'perfil_responsable':
                if (!empty($ctx['creado_por'])) {
                    $uid = (int)$ctx['creado_por'];
                    $r = $db->query("SELECT email FROM usuarios WHERE id=$uid LIMIT 1")->fetch_assoc();
                    return $r['email'] ?? null;
                }
                return null;
            case 'aseguradora':
                $nombre = $ctx['aseguradora'] ?? '';
                if ($nombre) {
                    $st = $db->prepare("SELECT email FROM companias_registradas WHERE nombre=? AND tipo='aseguradora' LIMIT 1");
                    $st->bind_param('s', $nombre);
                    $st->execute();
                    $r = $st->get_result()->fetch_assoc();
                    $st->close();
                    return $r['email'] ?? null;
                }
                return null;
            case 'plataforma':
                $r = $db->query("SELECT valor_config FROM configuracion_sistema WHERE clave_config='EMPRESA_CORREO' LIMIT 1")->fetch_assoc();
                if (!empty($r['valor_config'])) {
                    return $r['valor_config'];
                }
                $f = __DIR__ . '/../config/smtp.json';
                if (file_exists($f)) {
                    $cfg = json_decode(file_get_contents($f), true);
                    return $cfg['username'] ?? null;
                }
                return null;
            case 'cliente':
                return $ctx['email'] ?? null;
            case 'campo_ref':
                $campo = $destinatario['campo_ref'] ?? 'email';
                return $ctx[$campo] ?? null;
            default:
                return null;
        }
    }

    /**
     * Auto-crea las tablas necesarias (idempotente).
     */
    function notif_crearTablas($db) {
        $db->query("CREATE TABLE IF NOT EXISTS `flujos_notificacion` (
            `id`              INT AUTO_INCREMENT PRIMARY KEY,
            `nombre`          VARCHAR(120) NOT NULL,
            `evento`          VARCHAR(80)  NOT NULL,
            `descripcion`     TEXT         DEFAULT NULL,
            `destinatarios`   JSON         NOT NULL,
            `asunto_tpl`      VARCHAR(300) NOT NULL,
            `cuerpo_tpl`      LONGTEXT     NOT NULL,
            `activo`          TINYINT(1)   DEFAULT 1,
            `creado_por`      INT          DEFAULT NULL,
            `modificado_por`  INT          DEFAULT NULL,
            `created_at`      DATETIME     DEFAULT CURRENT_TIMESTAMP,
            `updated_at`      DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_evento` (`evento`),
            INDEX `idx_activo` (`activo`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->query("CREATE TABLE IF NOT EXISTS `log_notificaciones` (
            `id`              INT AUTO_INCREMENT PRIMARY KEY,
            `flujo_id`        INT          DEFAULT NULL,
            `evento`          VARCHAR(80)  NOT NULL,
            `referencia`      VARCHAR(80)  DEFAULT NULL,
            `destinatario`    VARCHAR(200) NOT NULL,
            `asunto`          VARCHAR(300) DEFAULT NULL,
            `resultado`       ENUM('enviado','fallido','omitido') DEFAULT 'omitido',
            `detalle`         TEXT         DEFAULT NULL,
            `disparado_por`   INT          DEFAULT NULL,
            `created_at`      DATETIME     DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_evento` (`evento`),
            INDEX `idx_referencia` (`referencia`),
            INDEX `idx_flujo` (`flujo_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    /**
     * Dispara todos los flujos activos para un evento dado.
     * @param  mysqli  $db
     * @param  string  $evento       Ej: 'COTIZACION_NUEVA'
     * @param  array   $ctx          Datos del documento (variables para la plantilla)
     * @param  string  $referencia   Número de documento
     * @param  int     $usuario_id
     * @return array   {enviados, fallidos, flujos}
     */
    function notif_disparar($db, $evento, $ctx, $referencia, $usuario_id) {
        // Asegurar tablas existen
        notif_crearTablas($db);

        $st = $db->prepare("SELECT * FROM flujos_notificacion WHERE evento=? AND activo=1");
        $st->bind_param('s', $evento);
        $st->execute();
        $flujos = $st->get_result()->fetch_all(MYSQLI_ASSOC);
        $st->close();

        if (empty($flujos)) return ['enviados' => 0, 'fallidos' => 0, 'flujos' => 0];

        require_once __DIR__ . '/Mailer.php';
        $mailer   = new Mailer();
        $enviados = 0;
        $fallidos = 0;

        foreach ($flujos as $flujo) {
            $destinatarios = json_decode($flujo['destinatarios'], true) ?: [];

            foreach ($destinatarios as $dest) {
                $email = notif_resolverEmail($dest, $ctx, $db);

                if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    notif_logEnvio($db, $flujo['id'], $evento, $referencia, $email ?? 'N/A',
                        null, 'omitido', 'Email no válido o no resuelto para tipo: ' . ($dest['tipo'] ?? '?'), $usuario_id);
                    continue;
                }

                $asunto = notif_renderTemplate($flujo['asunto_tpl'], $ctx);
                $cuerpo = notif_renderTemplate($flujo['cuerpo_tpl'], $ctx);

                $ok = @$mailer->enviar($email, $asunto, $cuerpo, true);

                notif_logEnvio($db, $flujo['id'], $evento, $referencia, $email, $asunto,
                    $ok ? 'enviado' : 'fallido',
                    $ok ? 'Correo entregado correctamente' : 'Error SMTP — ver smtp.log',
                    $usuario_id);

                if ($ok) $enviados++; else $fallidos++;
            }
        }

        return ['enviados' => $enviados, 'fallidos' => $fallidos, 'flujos' => count($flujos)];
    }
}
?>
