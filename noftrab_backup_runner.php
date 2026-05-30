<?php
/**
 * NOFTRAB AUTOMATED BACKUP & SYNC RUNNER — v1.0
 * MAS QUE FIANZAS — Sistema Integrado
 * ===================================================================
 * Este script automatiza de forma atómica los tres pilares del respaldo NOFTRAB:
 * 1. Git Push: Agrega, commitea y empuja los cambios actualizados a GitHub.
 * 2. Correo de Respaldo: Envía el walkthrough HTML por SMTP a pastorandersonhenriquez@gmail.com.
 * 3. Copia en Google Drive: Comprime el código relevante del proyecto en ZIP y lo sube 
 *    directamente a la carpeta compartida en la nube del drive de Google.
 *
 * Puede ser ejecutado por CLI o por Navegador (con una interfaz terminal premium interactiva).
 *
 * URL: http://localhost/PLATAFORMA_INTEGRADA/noftrab_backup_runner.php?message=Mensaje+de+Commit
 */

require_once 'backend/config.php';
require_once 'backend/Mailer.php';

// Definir constante del folder de Google Drive destino
define('GOOGLE_DRIVE_FOLDER_ID', '1twjGFJZSYEdsWZDfxaNoHq7yc9bglr5A');
$config_path = dirname(__FILE__) . '/backend/config/google_drive.json';

// Detectar entorno (CLI vs Browser)
$is_cli = php_sapi_name() === 'cli';

// ─── ENDPOINT AUXILIAR: Guardar Configuración de Google Drive (Debe procesarse antes de cualquier validación) ────────────────
if (!$is_cli && isset($_GET['action']) && $_GET['action'] === 'save_google_config') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(["exito" => false, "mensaje" => "Método no permitido"]);
        exit;
    }
    
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $client_id = trim($input['client_id'] ?? '');
        $client_secret = trim($input['client_secret'] ?? '');
        $refresh_token = trim($input['refresh_token'] ?? '');
        
        if (empty($client_id) || empty($client_secret) || empty($refresh_token)) {
            throw new Exception("Todos los campos son obligatorios.");
        }
        
        // Crear carpeta de configuración si no existe
        $config_dir = dirname($config_path);
        if (!file_exists($config_dir)) {
            mkdir($config_dir, 0777, true);
        }
        
        // Guardar configuración en JSON
        $saved = file_put_contents($config_path, json_encode([
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'refresh_token' => $refresh_token
        ], JSON_PRETTY_PRINT));
        
        if ($saved === false) {
            throw new Exception("No se pudo escribir en el archivo de configuración.");
        }
        
        echo json_encode(["exito" => true, "mensaje" => "Credenciales vinculadas correctamente."]);
        
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(["exito" => false, "mensaje" => $e->getMessage()]);
    }
    exit;
}

$commit_msg = '';

if ($is_cli) {
    // Tomar mensaje de los argumentos de CLI (ej: php noftrab_backup_runner.php -m "mi commit")
    $args = getopt("m:");
    $commit_msg = $args['m'] ?? '';
    if (empty($commit_msg)) {
        echo "⚠️ Error: Debe especificar un mensaje de commit usando el parámetro -m.\n";
        echo "Ejemplo: php noftrab_backup_runner.php -m \"feat(auth): login de usuario\"\n";
        exit(1);
    }
} else {
    // Tomar mensaje de los parámetros GET/POST de la URL
    $commit_msg = $_GET['message'] ?? $_POST['message'] ?? '';
}

// Inicializar log array para la consola
$logs = [];
function addLog(&$logs, $msg, $type = 'info') {
    global $is_cli;
    $timestamp = date('H:i:s');
    $log_line = "[$timestamp] " . ($type === 'success' ? '✅ ' : ($type === 'error' ? '❌ ' : ($type === 'warn' ? '⚠️ ' : '⚡ '))) . $msg;
    $logs[] = ['time' => $timestamp, 'text' => $msg, 'type' => $type];
    if ($is_cli) {
        $color = $type === 'success' ? "\033[32m" : ($type === 'error' ? "\033[31m" : ($type === 'warn' ? "\033[33m" : "\033[36m"));
        echo $color . $log_line . "\033[0m\n";
    }
}

// Iniciar proceso
addLog($logs, "Iniciando proceso automatizado de respaldo y actualización NOFTRAB...");

// Verificar si se especificó el mensaje de commit
if (empty($commit_msg)) {
    if (!$is_cli) {
        renderBrowserUI($logs, false, "Debe especificar un mensaje de commit en la URL para iniciar.");
    }
    exit;
}

$error_general = false;
$zip_created = false;
$zip_file_path = '';

try {
    // ════ 1. AUTOMATIZACIÓN DE GIT ════
    addLog($logs, "Paso 1: Iniciando automatización de repositorios de Git...");
    
    // Verificar si git está instalado
    $git_ver = shell_exec('git --version');
    if (!$git_ver) {
        throw new Exception("Git no está instalado o no se encuentra en el PATH del sistema.");
    }
    
    addLog($logs, "Git detectado: " . trim($git_ver));
    
    // Git Add
    addLog($logs, "Staging de archivos modificados (git add -A)...");
    shell_exec('git add -A');
    
    // Git Commit
    addLog($logs, "Creando commit local con el mensaje: '$commit_msg'...");
    $commit_output = shell_exec('git commit -m ' . escapeshellarg($commit_msg));
    if (strpos($commit_output, 'nothing to commit') !== false || strpos($commit_output, 'sin cambios') !== false) {
        addLog($logs, "No hay cambios pendientes para commitear en el repositorio local.", "warn");
    } else {
        addLog($logs, "Commit creado de forma atómica.", "success");
    }
    
    // Git Push
    addLog($logs, "Empujando cambios a GitHub (git push origin master)...");
    $push_output = shell_exec('git push origin master 2>&1');
    if (strpos($push_output, 'Everything up-to-date') !== false) {
        addLog($logs, "Repositorio GitHub remoto ya está completamente actualizado.", "success");
    } elseif (strpos($push_output, 'master -> master') !== false || strpos($push_output, 'To ') !== false) {
        addLog($logs, "Cambios empujados y respaldados en GitHub con éxito.", "success");
    } else {
        addLog($logs, "Advertencia al empujar: " . trim($push_output), "warn");
    }

    // ════ 2. ENVÍO DE EMAIL DE RESPALDO (WALKTHROUGH) ════
    addLog($logs, "Paso 2: Iniciando despacho del Walkthrough por correo corporativo...");
    
    $walkthrough_path = 'C:/Users/jhenr/.gemini/antigravity/brain/7d2dc99f-87d6-40c6-86e5-a4a6d74f2cea/walkthrough.md';
    if (!file_exists($walkthrough_path)) {
        $walkthrough_path = dirname(__FILE__) . '/walkthrough.md'; // Fallback
    }

    if (!file_exists($walkthrough_path)) {
        addLog($logs, "No se encontró el archivo walkthrough.md. Omitiendo reporte por correo.", "warn");
    } else {
        addLog($logs, "Leyendo walkthrough.md desde " . basename($walkthrough_path) . "...");
        $content_md = file_get_contents($walkthrough_path);
        
        // Convertir MD a HTML básico
        $content_html = nl2br(htmlspecialchars($content_md));
        $content_html = preg_replace('/^#\s+(.+)$/m', '<h1 style="color:#4f46e5; border-bottom:2px solid #e2e8f0; padding-bottom:8px; margin-top:24px;">$1</h1>', $content_html);
        $content_html = preg_replace('/^##\s+(.+)$/m', '<h2 style="color:#1e293b; margin-top:20px;">$1</h2>', $content_html);
        $content_html = preg_replace('/^###\s+(.+)$/m', '<h3 style="color:#4f46e5; margin-top:16px;">$1</h3>', $content_html);
        $content_html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $content_html);
        $content_html = preg_replace('/\*\s+(.+)$/m', '<li style="margin-left:20px; margin-bottom:5px;">$1</li>', $content_html);
        
        $body_email = '
        <div style="font-family:\'Segoe UI\',Arial,sans-serif; max-width:800px; margin:0 auto; padding:30px; border:1px solid #e2e8f0; border-radius:16px; background:#ffffff; color:#1e293b;">
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:3px solid #4f46e5; padding-bottom:15px; margin-bottom:25px;">
                <div>
                    <h1 style="margin:0; font-size:20px; color:#0f172a;">Respaldo Oficial Automatizado</h1>
                    <p style="margin:5px 0 0 0; font-size:13px; color:#64748b; font-weight:500;">Control Técnico Integrado — MÁS QUE FIANZAS</p>
                </div>
                <div style="font-size:18px; font-weight:800; color:#4f46e5; text-transform:uppercase;">MÁS QUE FIANZAS</div>
            </div>
            <div style="line-height:1.6; font-size:14px;">' . $content_html . '</div>
            <div style="margin-top:40px; border-top:1px solid #e2e8f0; padding-top:20px; font-size:12px; color:#64748b; text-align:center;">
                <p style="margin:0;">Este correo de respaldo automatizado se generó al ejecutar el protocolo NOFTRAB.</p>
                <p style="margin:5px 0 0 0; font-weight:600; color:#4f46e5;">© 2026 MÁS QUE FIANZAS, S.R.L. Todos los derechos reservados.</p>
            </div>
        </div>';
        
        addLog($logs, "Despachando correo a pastorandersonhenriquez@gmail.com...");
        $mailer = new Mailer();
        $enviado = $mailer->enviar('pastorandersonhenriquez@gmail.com', 'RESPALDO AUTOMÁTICO: Walkthrough Técnico (Norma NOFTRAB)', $body_email, true);
        
        if ($enviado) {
            addLog($logs, "Correo enviado correctamente a pastorandersonhenriquez@gmail.com.", "success");
        } else {
            addLog($logs, "No se pudo despachar el correo. Revise el archivo backend/logs/smtp.log.", "warn");
        }
    }

    // ════ 3. COMPRESIÓN DEL PROYECTO (ZIP) ════
    addLog($logs, "Paso 3: Comprimiendo el código del proyecto para el Google Drive...");
    
    $zip_filename = 'masque_fianzas_backup.zip';
    $zip_file_path = sys_get_temp_dir() . '/' . $zip_filename;
    
    $zip = new ZipArchive();
    if ($zip->open($zip_file_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new Exception("No se pudo inicializar la librería ZipArchive de PHP.");
    }
    
    // Función para empaquetar directorios de forma selectiva (excluyendo carpetas pesadas)
    $source_dir = realpath(dirname(__FILE__));
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source_dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    
    $count = 0;
    foreach ($files as $name => $file) {
        if ($file->isDir()) continue;
        
        $filePath = $file->getRealPath();
        $relativePath = substr($filePath, strlen($source_dir) + 1);
        $relativePath = str_replace('\\', '/', $relativePath);
        
        // Excluir elementos no deseados para optimizar tamaño y privacidad
        if (
            strpos($relativePath, '.git/') === 0 || 
            strpos($relativePath, 'uploads/') === 0 || 
            strpos($relativePath, 'backend/uploads/') === 0 || 
            strpos($relativePath, 'backend/logs/') === 0 || 
            strpos($relativePath, 'node_modules/') === 0 || 
            substr($relativePath, -4) === '.zip' ||
            basename($relativePath) === '.gitignore' ||
            basename($relativePath) === 'google_drive.json'
        ) {
            continue;
        }
        
        $zip->addFile($filePath, $relativePath);
        $count++;
    }
    
    $zip->close();
    $zip_created = true;
    $size_mb = round(filesize($zip_file_path) / (1024 * 1024), 2);
    addLog($logs, "Archivo ZIP empaquetado con éxito: $zip_filename ($size_mb MB, $count archivos cargados).", "success");

    // ════ 4. SUBIDA AL GOOGLE DRIVE ════
    addLog($logs, "Paso 4: Subiendo respaldo comprimido al Google Drive...");
    
    // Verificar si existe la configuración
    if (!file_exists($config_path)) {
        addLog($logs, "El archivo de credenciales de Google Drive no existe.", "warn");
        addLog($logs, "Por favor, configure las credenciales en 'backend/config/google_drive.json' para activar la subida.", "warn");
        addLog($logs, "El backup ZIP local se ha guardado temporalmente en: " . $zip_file_path);
    } else {
        $config = json_decode(file_get_contents($config_path), true);
        $client_id = $config['client_id'] ?? '';
        $client_secret = $config['client_secret'] ?? '';
        $refresh_token = $config['refresh_token'] ?? '';
        
        if (empty($client_id) || empty($client_secret) || empty($refresh_token)) {
            throw new Exception("El archivo google_drive.json está configurado pero tiene campos vacíos.");
        }
        
        addLog($logs, "Solicitando token de acceso a la API de Google OAuth2...");
        
        // 1. Obtener access_token con el refresh_token
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'refresh_token' => $refresh_token,
            'grant_type' => 'refresh_token'
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $resp = curl_exec($ch);
        curl_close($ch);
        
        $token_data = json_decode($resp, true);
        $access_token = $token_data['access_token'] ?? null;
        
        if (!$access_token) {
            throw new Exception("Error de autorización en Google: " . ($token_data['error_description'] ?? $resp));
        }
        
        addLog($logs, "Token de acceso obtenido con éxito.", "success");
        addLog($logs, "Buscando si existe un respaldo previo 'masque_fianzas_backup.zip' en la carpeta destino de Google Drive...");
        
        $q = "name = 'masque_fianzas_backup.zip' and '" . GOOGLE_DRIVE_FOLDER_ID . "' in parents and trashed = false";
        $search_url = 'https://www.googleapis.com/drive/v3/files?q=' . urlencode($q) . '&fields=files(id)';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $search_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token
        ]);
        $search_resp = curl_exec($ch);
        $search_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $search_result = json_decode($search_resp, true);
        $existing_file_id = null;
        
        if ($search_code === 200 && !empty($search_result['files'])) {
            $existing_file_id = $search_result['files'][0]['id'];
            addLog($logs, "Respaldo existente encontrado en Drive (ID: $existing_file_id). Se actualizará mediante versionado nativo...");
        } else {
            addLog($logs, "No se encontró un respaldo previo. Se creará un nuevo archivo en la carpeta destino.");
        }
        
        if ($existing_file_id) {
            // Actualizar archivo existente (PATCH)
            addLog($logs, "Iniciando actualización (PATCH) de $zip_filename en Google Drive...");
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://www.googleapis.com/upload/drive/v3/files/' . $existing_file_id . '?uploadType=media');
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents($zip_file_path));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $access_token,
                'Content-Type: application/zip',
                'Content-Length: ' . filesize($zip_file_path)
            ]);
            
            $t_start = microtime(true);
            $upload_resp = curl_exec($ch);
            $t_end = microtime(true);
            $latency = round(($t_end - $t_start) * 1000);
            
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $upload_result = json_decode($upload_resp, true);
            
            if ($http_code === 200 && isset($upload_result['id'])) {
                addLog($logs, "¡Respaldo actualizado en Google Drive exitosamente! (ID: {$upload_result['id']}, Latencia: {$latency}ms).", "success");
                
                // Eliminar archivo temporal local para no acumular basura
                unlink($zip_file_path);
                addLog($logs, "Archivo temporal eliminado de la máquina local.", "success");
            } else {
                throw new Exception("Error al actualizar archivo en Google Drive (HTTP $http_code): " . ($upload_result['error']['message'] ?? $upload_resp));
            }
        } else {
            // Cargar archivo nuevo (POST)
            addLog($logs, "Iniciando carga inicial (POST multipart) de $zip_filename a Google Drive...");
            $metadata = [
                'name' => $zip_filename,
                'parents' => [GOOGLE_DRIVE_FOLDER_ID]
            ];
            
            $boundary = '-------314159265358979323846';
            $delimiter = "\r\n--" . $boundary . "\r\n";
            $closeBoundary = "\r\n--" . $boundary . "--\r\n";

            $metadataPart = "Content-Type: application/json; charset=UTF-8\r\n\r\n" . json_encode($metadata);
            $mediaPartHeader = "Content-Type: application/zip\r\n\r\n";
            
            $body = $delimiter . $metadataPart . $delimiter . $mediaPartHeader . file_get_contents($zip_file_path) . $closeBoundary;

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $access_token,
                'Content-Type: multipart/related; boundary=' . $boundary,
                'Content-Length: ' . strlen($body)
            ]);
            
            $t_start = microtime(true);
            $upload_resp = curl_exec($ch);
            $t_end = microtime(true);
            $latency = round(($t_end - $t_start) * 1000);
            
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $upload_result = json_decode($upload_resp, true);
            
            if ($http_code === 200 && isset($upload_result['id'])) {
                addLog($logs, "¡Respaldo subido a Google Drive exitosamente! (ID: {$upload_result['id']}, Latencia: {$latency}ms).", "success");
                
                // Eliminar archivo temporal local para no acumular basura
                unlink($zip_file_path);
                addLog($logs, "Archivo temporal eliminado de la máquina local.", "success");
            } else {
                throw new Exception("Error al subir a Google Drive (HTTP $http_code): " . ($upload_result['error']['message'] ?? $upload_resp));
            }
        }
    }

} catch (Exception $e) {
    $error_general = true;
    addLog($logs, "Error detectado en el flujo: " . $e->getMessage(), "error");
    if ($zip_created && file_exists($zip_file_path)) {
        addLog($logs, "Se conservará el archivo de respaldo local en: " . $zip_file_path, "warn");
    }
}

// Renderizar UI del Navegador al finalizar
if (!$is_cli) {
    renderBrowserUI($logs, !$error_general);
}


/**
 * RENDERIZADOR DE INTERFAZ DEL NAVEGADOR
 */
function renderBrowserUI($logs, $exito, $mensaje_adicional = '') {
    $porcentaje = $exito ? 100 : 70;
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>NOFTRAB: Backup &amp; Sync Portal</title>
        <link rel="stylesheet" href="frontend/assets/design-system.css?v=5.0.0">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            body {
                background: #0f111a;
                color: #f8fafc;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                padding: 20px;
                font-family: 'Inter', sans-serif;
            }
            .portal-card {
                background: rgba(30, 41, 59, 0.4);
                backdrop-filter: blur(16px);
                -webkit-backdrop-filter: blur(16px);
                border: 1px solid rgba(255, 255, 255, 0.08);
                border-radius: 24px;
                width: 100%;
                max-width: 750px;
                padding: 30px;
                box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
                animation: mqf-fadeInScale 0.4s ease;
            }
            .portal-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                border-bottom: 1px solid rgba(255, 255, 255, 0.08);
                padding-bottom: 20px;
                margin-bottom: 25px;
            }
            .portal-header h1 {
                font-size: 1.5rem;
                font-weight: 800;
                margin: 0;
                background: linear-gradient(135deg, #818cf8 0%, #c084fc 100%);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
            }
            .progress-bar-wrap {
                background: rgba(255,255,255,0.05);
                height: 6px;
                border-radius: 999px;
                overflow: hidden;
                margin-bottom: 20px;
            }
            .progress-bar {
                height: 100%;
                background: linear-gradient(90deg, #6366f1, #a855f7);
                border-radius: 999px;
                transition: width 1s ease;
                width: <?= $porcentaje ?>%;
            }
            .terminal-console {
                background: #090d16;
                border: 1px solid rgba(255, 255, 255, 0.05);
                border-radius: 16px;
                padding: 16px 20px;
                font-family: 'JetBrains Mono', 'Courier New', monospace;
                font-size: 12px;
                color: #94a3b8;
                height: 280px;
                overflow-y: auto;
                box-shadow: inset 0 2px 8px rgba(0,0,0,0.8);
            }
            .terminal-line {
                margin-bottom: 8px;
                line-height: 1.5;
                animation: mqf-slideInRight 0.15s ease;
            }
            .t-success { color: #34d399; }
            .t-error { color: #f87171; font-weight: bold; }
            .t-warn { color: #fbbf24; }
            .t-info { color: #38bdf8; }
            
            /* Formulario de credenciales si no existe config */
            .setup-box {
                margin-top: 25px;
                background: rgba(99, 102, 241, 0.05);
                border: 1px solid rgba(99, 102, 241, 0.2);
                border-radius: 16px;
                padding: 20px;
            }
            .form-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 15px;
                margin-top: 15px;
            }
            .form-control {
                background: rgba(0,0,0,0.3);
                border: 1px solid rgba(255,255,255,0.08);
                color: #fff;
                font-size: 13px;
                padding: 8px 12px;
                border-radius: 8px;
                width: 100%;
            }
            .form-control:focus {
                border-color: #6366f1;
                outline: none;
            }
        </style>
    </head>
    <body>
        <div class="portal-card">
            <div class="portal-header">
                <div>
                    <h1><i class="fa-solid fa-cloud-arrow-up"></i> NOFTRAB: Backup &amp; Sync</h1>
                    <p style="font-size:12px; color:#64748b; margin:4px 0 0 0; font-weight:500;">Consola de Respaldo Atómico Masivo</p>
                </div>
                <span class="badge" style="background: <?= $exito ? 'rgba(16, 185, 129, 0.1)' : 'rgba(239, 68, 68, 0.1)' ?>; color: <?= $exito ? '#34d399' : '#f87171' ?>; border: 1px solid <?= $exito ? '#10b981' : '#ef4444' ?>; padding: 4px 12px; border-radius: 999px; font-size:11px; font-weight:700; text-transform:uppercase;">
                    <?= $exito ? '✅ Completado' : '❌ Fallido' ?>
                </span>
            </div>

            <div class="progress-bar-wrap">
                <div class="progress-bar"></div>
            </div>

            <div class="terminal-console" id="terminal">
                <?php foreach ($logs as $log): ?>
                    <?php
                    $class = 't-info';
                    $prefix = '⚡ ';
                    if ($log['type'] === 'success') { $class = 't-success'; $prefix = '✅ '; }
                    elseif ($log['type'] === 'error') { $class = 't-error'; $prefix = '❌ '; }
                    elseif ($log['type'] === 'warn') { $class = 't-warn'; $prefix = '⚠️ '; }
                    ?>
                    <div class="terminal-line <?= $class ?>">
                        [<?= $log['time'] ?>] <?= $prefix ?><?= htmlspecialchars($log['text']) ?>
                    </div>
                <?php endforeach; ?>
                
                <?php if (!empty($mensaje_adicional)): ?>
                    <div class="terminal-line t-error">[Error Central] <?= htmlspecialchars($mensaje_adicional) ?></div>
                <?php endif; ?>
            </div>

            <!-- Mostrar caja de configuración si no existe el archivo -->
            <?php if (!file_exists(dirname(__FILE__) . '/backend/config/google_drive.json')): ?>
                <div class="setup-box">
                    <h3 style="font-size: 14px; color:#c7d2fe; margin:0 0 8px 0; font-weight:700;"><i class="fa-solid fa-gears"></i> Vincular cuenta de Google Drive</h3>
                    <p style="font-size: 12px; color:#94a3b8; line-height:1.4;">
                        Para activar las copias automatizadas de seguridad en Google Drive, configure las credenciales de OAuth2. 
                        Puedes generar estos tokens en 2 minutos usando <a href="https://developers.google.com/oauthplayground/" target="_blank" style="color:#a855f7; font-weight:600;">Google OAuth Playground</a> seleccionando <code>Drive API v3</code>.
                    </p>
                    
                    <form onsubmit="guardarGoogleConfig(event)">
                        <div class="form-grid">
                            <div>
                                <label style="font-size:11px; color:#64748b; font-weight:600;">Client ID</label>
                                <input type="text" id="g_client_id" class="form-control" placeholder="Client ID provisto por Google Cloud" required>
                            </div>
                            <div>
                                <label style="font-size:11px; color:#64748b; font-weight:600;">Client Secret</label>
                                <input type="password" id="g_client_secret" class="form-control" placeholder="Client Secret de Google Cloud" required>
                            </div>
                            <div style="grid-column: 1 / -1;">
                                <label style="font-size:11px; color:#64748b; font-weight:600;">Refresh Token</label>
                                <input type="password" id="g_refresh_token" class="form-control" placeholder="Refresh Token generado" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary" style="margin-top: 15px; width: 100%; border-radius: 8px; font-size:12px; font-weight:700;">💾 Guardar y Vincular Google Drive</button>
                    </form>
                    <div id="setup-status" style="margin-top: 10px; font-size: 12px; font-weight: 700; text-align: center;"></div>
                </div>
            <?php endif; ?>
        </div>
        
        <script>
            // Scroll automático de la terminal
            const term = document.getElementById('terminal');
            term.scrollTop = term.scrollHeight;

            // Guardar configuración de Google Drive vía API de ajustes si no existe
            async function guardarGoogleConfig(e) {
                e.preventDefault();
                const status = document.getElementById('setup-status');
                status.style.color = '#818cf8';
                status.innerText = 'Guardando configuración...';

                const data = {
                    client_id: document.getElementById('g_client_id').value.trim(),
                    client_secret: document.getElementById('g_client_secret').value.trim(),
                    refresh_token: document.getElementById('g_refresh_token').value.trim()
                };

                try {
                    const resp = await fetch('noftrab_backup_runner.php?action=save_google_config', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify(data)
                    });
                    const res = await resp.json();
                    if (res.exito) {
                        status.style.color = '#34d399';
                        status.innerText = '✅ Configuración guardada. Recargando para ejecutar subida...';
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        status.style.color = '#f87171';
                        status.innerText = '❌ Error: ' + res.mensaje;
                    }
                } catch(err) {
                    status.style.color = '#f87171';
                    status.innerText = '❌ Error de conexión';
                }
            }
        </script>
    </body>
    </html>
    <?php
}


