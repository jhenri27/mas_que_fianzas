<?php
/**
 * Diagnóstico SMTP completo - MAS QUE FIANZAS
 * Devuelve el log de la última prueba + verificaciones de DNS/SPF/DKIM.
 */
ob_start();
header('Content-Type: application/json; charset=utf-8');

set_error_handler(function($errno, $errstr) {
    ob_clean();
    http_response_code(500);
    echo json_encode(["exito" => false, "mensaje" => "Error PHP: $errstr"], JSON_UNESCAPED_UNICODE);
    exit;
});

require_once __DIR__ . '/../Mailer.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['to']) || !isset($data['config'])) {
    ob_clean();
    echo json_encode(["exito" => false, "mensaje" => "Datos incompletos (destino o config faltantes)."]);
    exit;
}

$to     = $data['to'];
$config = $data['config'];

// Resolver contraseña si viene con asteriscos (placeholder del UI)
if (isset($config['password']) && preg_match('/^\*+$/', $config['password'])) {
    $configFile = __DIR__ . '/../config/smtp.json';
    if (file_exists($configFile)) {
        $saved = json_decode(file_get_contents($configFile), true);
        if ($saved && isset($saved['password'])) {
            $config['password'] = $saved['password'];
        }
    }
}

if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
    ob_clean();
    echo json_encode(["exito" => false, "mensaje" => "Dirección de destino no válida: $to"]);
    exit;
}

// ── Diagnóstico previo: DNS del dominio remitente ──────────────────────────
$diagnostico = [];
$smtpServer  = $config['server'] ?? '';
$dominio     = substr(strrchr($config['username'] ?? '', '@'), 1);

// Verificar MX del dominio
$mxRecords = [];
$mxOk      = @getmxrr($dominio, $mxRecords);
$diagnostico['mx'] = $mxOk
    ? "✔ MX encontrado: " . implode(', ', array_slice($mxRecords, 0, 3))
    : "✖ No se encontraron registros MX para {$dominio}";

// Verificar SPF (registro TXT)
$txtRecords = @dns_get_record($dominio, DNS_TXT);
$spfRecord  = '';
if ($txtRecords) {
    foreach ($txtRecords as $r) {
        if (isset($r['txt']) && strpos($r['txt'], 'v=spf1') !== false) {
            $spfRecord = $r['txt'];
            break;
        }
    }
}
$diagnostico['spf'] = $spfRecord
    ? "✔ SPF: " . $spfRecord
    : "⚠ No se encontró registro SPF para {$dominio} — Los correos pueden llegar a SPAM";

// Verificar DKIM (selector común: 'default' o 'mail')
$dkimSelectors = ['default', 'mail', 'smtp', 'dkim'];
$dkimEncontrado = false;
foreach ($dkimSelectors as $sel) {
    $dkimHost = "{$sel}._domainkey.{$dominio}";
    $dkimRecs = @dns_get_record($dkimHost, DNS_TXT);
    if ($dkimRecs) {
        $diagnostico['dkim'] = "✔ DKIM encontrado ({$sel}._domainkey.{$dominio})";
        $dkimEncontrado = true;
        break;
    }
}
if (!$dkimEncontrado) {
    $diagnostico['dkim'] = "⚠ No se detectó DKIM para {$dominio} — Configura DKIM en cPanel para mejorar entregabilidad";
}

// Verificar DMARC
$dmarcRecs = @dns_get_record("_dmarc.{$dominio}", DNS_TXT);
if ($dmarcRecs) {
    $diagnostico['dmarc'] = "✔ DMARC: " . ($dmarcRecs[0]['txt'] ?? 'encontrado');
} else {
    $diagnostico['dmarc'] = "⚠ No se encontró registro DMARC para {$dominio}";
}

// Verificar resolución del servidor SMTP
$serverIp = @gethostbyname($smtpServer);
$diagnostico['servidor'] = ($serverIp && $serverIp !== $smtpServer)
    ? "✔ Servidor SMTP resuelve a IP: {$serverIp}"
    : "✖ No se pudo resolver el nombre del servidor: {$smtpServer}";

// Hostname local del servidor web
$localHost = gethostname() ?: php_uname('n');
$diagnostico['hostname_local'] = "ℹ Hostname local del servidor: {$localHost}";

// ── Limpiar log anterior para que el nuevo intento sea limpio ─────────────
$logFile = __DIR__ . '/../logs/smtp.log';
// Añadir separador de sesión al log
file_put_contents($logFile,
    "\n[" . date('Y-m-d H:i:s') . "] [SESSION] === INICIO PRUEBA DIAGNÓSTICO desde panel ===\n",
    FILE_APPEND | LOCK_EX
);

// ── Envío de prueba ───────────────────────────────────────────────────────
try {
    $mailer = new Mailer($config);

    $subject = "Prueba de Conexión SMTP - MAS QUE FIANZAS";
    $message = "
        <div style='font-family: Arial, sans-serif; color: #333; max-width:580px;'>
            <h2 style='color: #4f46e5; margin-bottom:8px;'>✅ Prueba de Conexión SMTP</h2>
            <p>Este correo confirma que el servidor SMTP de <strong>MAS QUE FIANZAS</strong> está configurado correctamente.</p>
            <div style='background:#f5f3ff;border-left:4px solid #4f46e5;padding:12px 16px;border-radius:4px;margin:20px 0;'>
                <strong>Servidor:</strong> {$smtpServer}<br>
                <strong>Dominio:</strong> {$dominio}<br>
                <strong>Fecha:</strong> " . date('Y-m-d H:i:s') . "
            </div>
            <p style='color:#6b7280;font-size:12px;'>
                Si recibiste este mensaje en tu carpeta principal (no spam), la configuración es correcta.<br>
                Si llegó a spam, revisa los registros SPF, DKIM y DMARC de tu dominio en tu proveedor de DNS.
            </p>
        </div>
    ";

    $resultado = $mailer->enviar($to, $subject, $message, true);

    // Leer las últimas líneas del log para devolver al frontend
    $logContent = '';
    if (file_exists($logFile)) {
        $lineas     = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $ultimas    = array_slice($lineas, -40); // últimas 40 líneas
        $logContent = implode("\n", $ultimas);
    }

    ob_clean();
    echo json_encode([
        "exito"       => $resultado,
        "mensaje"     => $resultado
            ? "✅ Envío exitoso a {$to}. Revisa tu bandeja de entrada Y la carpeta SPAM."
            : "❌ El servidor SMTP rechazó el envío. Revisa el log de conversación SMTP abajo.",
        "diagnostico" => $diagnostico,
        "smtp_log"    => $logContent
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        "exito"       => false,
        "mensaje"     => "Excepción: " . $e->getMessage(),
        "diagnostico" => $diagnostico,
        "smtp_log"    => ""
    ], JSON_UNESCAPED_UNICODE);
}
?>
