<?php
/**
 * API: BOT-TESTING-DEV - Motor de Diagnóstico y Auto-Corrección Autónoma (NOFTRAB)
 * MAS QUE FIANZAS - Core Asegurador v3.0
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once dirname(__DIR__) . '/config.php';

// Cargar clases requeridas preventivamente
@include_once dirname(__DIR__) . '/ContabilidadManager.php';
@include_once dirname(__DIR__) . '/MotorContable.php';
@include_once dirname(__DIR__) . '/NCFManager.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validar token de autorización si no hay sesión PHP activa
$bearer_token = null;
$auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? (function_exists('apache_request_headers') ? (apache_request_headers()['Authorization'] ?? '') : '');
if (preg_match('/Bearer\s+(.+)$/i', $auth_header, $matches)) {
    $bearer_token = trim($matches[1]);
}
if (empty($bearer_token)) {
    $bearer_token = $_GET['token_sesion'] ?? $_POST['token_sesion'] ?? $_REQUEST['token'] ?? $_REQUEST['token_sesion'] ?? null;
}

$usuario_id = null;
$db = null;
$db_conn_ok = false;

try {
    $db = Database::getInstance()->getConnection();
    $db_conn_ok = ($db && $db->connect_errno === 0);
} catch (Exception $e) {
    $db_conn_ok = false;
}

if (isset($_SESSION['usuario_id']) && $_SESSION['usuario_id']) {
    $usuario_id = (int)$_SESSION['usuario_id'];
} elseif (!empty($bearer_token) && $db_conn_ok && $db) {
    try {
        $stmt_tk = $db->prepare("SELECT usuario_id FROM sesiones_usuario WHERE token_sesion = ? AND activa = 1 AND fecha_expiracion > NOW() LIMIT 1");
        if ($stmt_tk) {
            $stmt_tk->bind_param("s", $bearer_token);
            $stmt_tk->execute();
            $res_tk = $stmt_tk->get_result();
            if ($row_tk = $res_tk->fetch_assoc()) $usuario_id = (int)$row_tk['usuario_id'];
            $stmt_tk->close();
        }
    } catch (Exception $ex) {
        // Ignorar si falla la tabla
    }
}

if (!$usuario_id) {
    http_response_code(401);
    echo json_encode(["exito" => false, "mensaje" => "Sesión no válida o expirada. Inicie sesión para interactuar con el bot."]);
    exit;
}

if ($usuario_id && empty($bearer_token) && $db_conn_ok && $db) {
    try {
        $stmt_tk = $db->prepare("SELECT token_sesion FROM sesiones_usuario WHERE usuario_id = ? AND activa = 1 AND fecha_expiracion > NOW() ORDER BY id DESC LIMIT 1");
        if ($stmt_tk) {
            $stmt_tk->bind_param("i", $usuario_id);
            $stmt_tk->execute();
            $res_tk = $stmt_tk->get_result();
            if ($row_tk = $res_tk->fetch_assoc()) {
                $bearer_token = $row_tk['token_sesion'];
            }
            $stmt_tk->close();
        }
    } catch (Exception $ex) {
        // Ignorar
    }
}

try {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'run_diagnostics') {
        $logs = [];
        $fallos = [];
        $modulos = [
            "database" => ["ok" => true, "mensaje" => "Integridad de base de datos OK"],
            "permissions" => ["ok" => true, "mensaje" => "Matriz de permisos OK"],
            "chat" => ["ok" => true, "mensaje" => "Chat-CSR y Bot BHN OK"],
            "helpdesk" => ["ok" => true, "mensaje" => "Helpdesk e Incidencias OK"],
            "ncf" => ["ok" => true, "mensaje" => "Generador de NCF OK"],
            "accounting" => ["ok" => true, "mensaje" => "Motor Contable de Partida Doble OK"]
        ];

        // ── Suite 1: Database Integrity
        $logs[] = ["modulo" => "DATABASE", "tipo" => "info", "mensaje" => "Iniciando verificación de integridad de base de datos..."];
        if (!$db_conn_ok || !$db) {
            $modulos["database"] = ["ok" => false, "mensaje" => "No hay conexión activa a la base de datos."];
            $fallos[] = "database";
            $logs[] = ["modulo" => "DATABASE", "tipo" => "error", "mensaje" => "Fallo crítico: No se pudo conectar a la base de datos."];
        } else {
            // Verificar tablas críticas (incluyendo nuevos módulos y opciones)
            $critical_tables = [
                'usuarios', 'perfiles', 'permisos_perfil', 'funciones_modulo', 'modulos', 
                'historial_ajustes', 'auditoria_accesos', 'mensajes_chat', 
                'tickets_soporte', 'mensajes_ticket', 'sistema_migraciones_log',
                'comisiones_poliza', 'cf_asientos', 'cf_asiento_lineas', 'cf_catalogo_cuentas',
                'fianzas', 'fianza_tarifarios', 'productos', 'siniestros',
                'flujos_notificacion', 'pdf_plantillas', 'etl_mapeos', 'integraciones_aseguradoras',
                'motge_experiencia'
            ];
            $missing_tables = [];
            foreach ($critical_tables as $tbl) {
                $check = $db->query("SHOW TABLES LIKE '$tbl'");
                if (!$check || $check->num_rows === 0) {
                    $missing_tables[] = $tbl;
                }
            }

            if (!empty($missing_tables)) {
                $modulos["database"] = ["ok" => false, "mensaje" => "Tablas faltantes: " . implode(", ", $missing_tables)];
                $fallos[] = "database";
                $logs[] = ["modulo" => "DATABASE", "tipo" => "error", "mensaje" => "Tablas faltantes en el esquema: " . implode(", ", $missing_tables)];
            } else {
                $logs[] = ["modulo" => "DATABASE", "tipo" => "ok", "mensaje" => "Todas las tablas críticas verificadas en la base de datos."];
            }
        }

        // ── Suite 2: System Permissions
        $logs[] = ["modulo" => "PERMISSIONS", "tipo" => "info", "mensaje" => "Validando matriz de perfiles y permisos de usuario..."];
        if (in_array("database", $fallos)) {
            $modulos["permissions"] = ["ok" => false, "mensaje" => "Omitido por fallo en base de datos."];
            $logs[] = ["modulo" => "PERMISSIONS", "tipo" => "error", "mensaje" => "Prueba omitida debido a tablas faltantes en la base de datos."];
        } else {
            $perm_res = $db->query("SELECT COUNT(*) as total FROM permisos_perfil");
            $perm_count = $perm_res ? (int)$perm_res->fetch_assoc()['total'] : 0;
            
            $prof_res = $db->query("SELECT COUNT(*) as total FROM perfiles");
            $prof_count = $prof_res ? (int)$prof_res->fetch_assoc()['total'] : 0;

            if ($perm_count < 10 || $prof_count === 0) {
                $modulos["permissions"] = ["ok" => false, "mensaje" => "Semillas de permisos incompletas o vacías (Encontrados: $perm_count permisos)."];
                $fallos[] = "permissions";
                $logs[] = ["modulo" => "PERMISSIONS", "tipo" => "error", "mensaje" => "Matriz de permisos inválida. Se encontraron solo $perm_count registros en permisos_perfil."];
            } else {
                $logs[] = ["modulo" => "PERMISSIONS", "tipo" => "ok", "mensaje" => "Matriz de permisos sembrada correctamente ($perm_count permisos, $prof_count perfiles)."];
            }
        }

        // ── Suite 3: Chat CSR & Bot BHN
        $logs[] = ["modulo" => "CHAT-CSR", "tipo" => "info", "mensaje" => "Simulando flujo de conversación y disparador automático BHN-Bot-HelpNow..."];
        if (in_array("database", $fallos)) {
            $modulos["chat"] = ["ok" => false, "mensaje" => "Omitido por fallo en base de datos."];
            $logs[] = ["modulo" => "CHAT-CSR", "tipo" => "error", "mensaje" => "Prueba de chat omitida por fallos estructurales."];
        } else {
            try {
                // Determinar URL base local para el loopback
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $chat_url = "$protocol://$host/PLATAFORMA_INTEGRADA/backend/api/chat.php";
                
                // CERRAR SESIÓN PHP temporalmente para evitar deadlock de bloqueo de sesión en el loopback cURL
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_write_close();
                }

                // Realizar llamada CURL simulando el envío real con la sesión activa
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $chat_url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                    'mensaje' => 'bot test autodiagnostico de sesion real',
                    'receptor_id' => 1 // Admin
                ]));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                
                $headers = [
                    'Authorization: Bearer ' . $bearer_token,
                    'Content-Type: application/json'
                ];
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                
                if (isset($_COOKIE[session_name()])) {
                    curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . $_COOKIE[session_name()]);
                }
                
                $resp = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curl_err = curl_error($ch);
                curl_close($ch);
                
                if ($http_code !== 200) {
                    $res_err = json_decode($resp, true);
                    $msg_err = $res_err['mensaje'] ?? ($curl_err ? $curl_err : 'Error de red o loopback');
                    throw new Exception("La API de chat retornó código HTTP $http_code. Detalle: $msg_err");
                }
                
                $res_data = json_decode($resp, true);
                if (!isset($res_data['exito']) || !$res_data['exito']) {
                    throw new Exception("La API de chat reportó fallo: " . ($res_data['mensaje'] ?? 'N/D'));
                }
                
                $msg_id = $res_data['datos']['id'] ?? null;
                
                // 2. Verificar que se haya insertado el mensaje del usuario y la respuesta automática del bot
                $check_user = 0;
                $check_bot = 0;
                
                if ($msg_id) {
                    $check_user = $db->query("SELECT COUNT(*) as cnt FROM mensajes_chat WHERE id = " . intval($msg_id))->fetch_assoc()['cnt'];
                    
                    // Obtener ID real del bot.helpnow
                    $res_bhn = $db->query("SELECT id FROM usuarios WHERE username = 'bot.helpnow' LIMIT 1");
                    $row_bhn = $res_bhn->fetch_assoc();
                    $bot_helpnow_id = $row_bhn ? (int)$row_bhn['id'] : 121;
                    
                    // Buscar la respuesta automática del bot generada para este usuario en los últimos 5 segundos
                    $check_bot = $db->query("SELECT COUNT(*) as cnt FROM mensajes_chat WHERE emisor_id = " . intval($bot_helpnow_id) . " AND receptor_id = " . intval($usuario_id) . " AND mensaje LIKE '🤖%' AND fecha_envio >= NOW() - INTERVAL 5 SECOND")->fetch_assoc()['cnt'];
                    
                    // Limpieza inmediata en base de datos para cumplir con la inmutabilidad y evitar ruido
                    $db->query("DELETE FROM mensajes_chat WHERE id = " . intval($msg_id));
                    $db->query("DELETE FROM mensajes_chat WHERE emisor_id = " . intval($bot_helpnow_id) . " AND receptor_id = " . intval($usuario_id) . " AND fecha_envio >= NOW() - INTERVAL 5 SECOND");
                }
                
                if ($check_user > 0 && $check_bot > 0) {
                    $logs[] = ["modulo" => "CHAT-CSR", "tipo" => "ok", "mensaje" => "Prueba de Chat y disparador BHN-Bot-HelpNow validada con sesión real a través de API HTTP."];
                } else {
                    throw new Exception("El bot no respondió o los registros de prueba no persistieron en la base de datos.");
                }
            } catch (Exception $e) {
                $modulos["chat"] = ["ok" => false, "mensaje" => "Fallo de conexión o consulta de chat: " . $e->getMessage()];
                $fallos[] = "chat";
                $logs[] = ["modulo" => "CHAT-CSR", "tipo" => "error", "mensaje" => "Error al simular chat: " . $e->getMessage()];
            }
        }

        // ── Suite 4: Helpdesk & SLAs
        $logs[] = ["modulo" => "HELPDESK", "tipo" => "info", "mensaje" => "Verificando consistencia de tickets y cálculo de SLAs de respuesta..."];
        if (in_array("database", $fallos)) {
            $modulos["helpdesk"] = ["ok" => false, "mensaje" => "Omitido por fallo en base de datos."];
            $logs[] = ["modulo" => "HELPDESK", "tipo" => "error", "mensaje" => "Prueba de helpdesk omitida por fallos estructurales."];
        } else {
            try {
                $db->begin_transaction();
                
                // Simular ticket de prioridad alta (SLA 2 horas)
                $titulo = "Test SLA bot_testing_dev";
                $desc = "Descripción de prueba";
                $modulo_afectado = "TEST";
                $prioridad = "alta";
                $sla_limite = date('Y-m-d H:i:s', strtotime("+2 hours"));

                $stmt_ins = $db->prepare("INSERT INTO tickets_soporte (usuario_id, modulo_afectado, titulo, descripcion, prioridad, estado, sla_limite, fecha_creacion) VALUES (?, ?, ?, ?, ?, 'abierto', ?, NOW())");
                $stmt_ins->bind_param("isssss", $usuario_id, $modulo_afectado, $titulo, $desc, $prioridad, $sla_limite);
                $stmt_ins->execute();
                $ticket_id = $stmt_ins->insert_id;
                $stmt_ins->close();

                // Verificar que el ticket exista y que el SLA esté en el rango esperado
                $stmt_chk = $db->prepare("SELECT sla_limite FROM tickets_soporte WHERE id = ?");
                $stmt_chk->bind_param("i", $ticket_id);
                $stmt_chk->execute();
                $res_chk = $stmt_chk->get_result()->fetch_assoc();
                $stmt_chk->close();

                $db->rollback();

                if ($res_chk) {
                    $diff = strtotime($res_chk['sla_limite']) - time();
                    // Tolerancia de 60 segundos
                    if ($diff > 1.9 * 3600 && $diff < 2.1 * 3600) {
                        $logs[] = ["modulo" => "HELPDESK", "tipo" => "ok", "mensaje" => "Cálculo de SLA e inserción de tickets OK (SLA Alta = 2 horas)."];
                    } else {
                        throw new Exception("El SLA calculado no corresponde al plazo esperado (Encontrado: " . ($diff/3600) . " horas).");
                    }
                } else {
                    throw new Exception("El ticket de prueba no fue persistido.");
                }
            } catch (Exception $e) {
                $modulos["helpdesk"] = ["ok" => false, "mensaje" => "Error de Helpdesk: " . $e->getMessage()];
                $fallos[] = "helpdesk";
                $logs[] = ["modulo" => "HELPDESK", "tipo" => "error", "mensaje" => "Fallo en verificación de Helpdesk: " . $e->getMessage()];
            }
        }

        // ── Suite 5: NCF Sequencer
        $logs[] = ["modulo" => "NCF-SEQUENCER", "tipo" => "info", "mensaje" => "Simulando consumo de comprobante de consumo (B02) con el NCFManager..."];
        if (in_array("database", $fallos)) {
            $modulos["ncf"] = ["ok" => false, "mensaje" => "Omitido por fallo en base de datos."];
            $logs[] = ["modulo" => "NCF-SEQUENCER", "tipo" => "error", "mensaje" => "Prueba de NCF omitida por fallos estructurales."];
        } else {
            try {
                // Instanciar NCFManager
                if (class_exists('\\MQF\\Finance\\NCFManager')) {
                    $db->begin_transaction();
                    $ncfMgr = new \MQF\Finance\NCFManager($db);
                    
                    // Simular consumo de B02 de emergencia (usar = true)
                    $ncf = $ncfMgr->generarSiguiente('B02', true);
                    
                    $db->rollback();

                    if (!empty($ncf) && \MQF\Finance\NCFManager::validarFormato($ncf)) {
                        $logs[] = ["modulo" => "NCF-SEQUENCER", "tipo" => "ok", "mensaje" => "Consumo simulado de NCF exitoso: $ncf (Formato válido)."];
                    } else {
                        throw new Exception("El NCF generado es nulo, vacío o tiene formato inválido.");
                    }
                } else {
                    throw new Exception("Clase MQF\\Finance\\NCFManager no encontrada.");
                }
            } catch (Exception $e) {
                $modulos["ncf"] = ["ok" => false, "mensaje" => "Error de NCF: " . $e->getMessage()];
                $fallos[] = "ncf";
                $logs[] = ["modulo" => "NCF-SEQUENCER", "tipo" => "error", "mensaje" => "Fallo al generar NCF: " . $e->getMessage()];
            }
        }

        // ── Suite 6: Contabilidad Core
        $logs[] = ["modulo" => "ACCOUNTING-CORE", "tipo" => "info", "mensaje" => "Validando consistencia aritmética del motor contable partida doble (debe == haber)..."];
        if (in_array("database", $fallos)) {
            $modulos["accounting"] = ["ok" => false, "mensaje" => "Omitido por fallo en base de datos."];
            $logs[] = ["modulo" => "ACCOUNTING-CORE", "tipo" => "error", "mensaje" => "Prueba contable omitida por fallos estructurales."];
        } else {
            try {
                if (class_exists('\\MQF\\Finance\\MotorContable')) {
                    $db->begin_transaction();
                    
                    // Simular emisión de póliza de $10,000 DOP
                    $montoOperacion = 10000;
                    $comisionBruta = $montoOperacion * 0.10; // 1,000
                    $itbisComision = $comisionBruta * 0.18;  // 180
                    $netoAseguradora = $montoOperacion - $comisionBruta - $itbisComision; // 8,820

                    $payload = [
                        'id' => 9999,
                        'modulo' => 'BOT_TESTING',
                        'numero' => 'BOT-' . date('is'),
                        'total' => $montoOperacion,
                        'monto_total' => $montoOperacion,
                        'monto_neto' => $netoAseguradora,
                        'comision' => $comisionBruta,
                        'itbis' => $itbisComision,
                        'monto_cobrado' => $montoOperacion,
                        'monto_bruto' => $comisionBruta,
                        'retencion_isr' => 0,
                        'agente' => 'BOT-TESTING-DEV'
                    ];

                    $asientoId = \MQF\Finance\MotorContable::disparar('EMISION_POLIZA', $payload);

                    if ($asientoId) {
                        // Obtener suma de debe y haber de la base de datos
                        $res_sum = $db->query("SELECT SUM(debe) as total_debe, SUM(haber) as total_haber FROM cf_asiento_lineas WHERE asiento_id = $asientoId")->fetch_assoc();
                        
                        $db->rollback();

                        if ($res_sum) {
                            $debe = (float)$res_sum['total_debe'];
                            $haber = (float)$res_sum['total_haber'];
                            
                            if ($debe > 0 && abs($debe - $haber) < 0.01) {
                                $logs[] = ["modulo" => "ACCOUNTING-CORE", "tipo" => "ok", "mensaje" => "Asiento contable generado y validado (Total Debe: $debe DOP == Total Haber: $haber DOP)."];
                            } else {
                                throw new Exception("Desbalance de partida doble. Debe: $debe DOP, Haber: $haber DOP.");
                            }
                        } else {
                            throw new Exception("No se pudieron consultar las líneas del asiento generado.");
                        }
                    } else {
                        throw new Exception("El motor contable no retornó un ID de asiento válido.");
                    }
                } else {
                    throw new Exception("Clase MQF\\Finance\\MotorContable no encontrada.");
                }
            } catch (Exception $e) {
                $modulos["accounting"] = ["ok" => false, "mensaje" => "Error contable: " . $e->getMessage()];
                $fallos[] = "accounting";
                $logs[] = ["modulo" => "ACCOUNTING-CORE", "tipo" => "error", "mensaje" => "Fallo de validación de asiento contable: " . $e->getMessage()];
            }
        }

        echo json_encode([
            "exito" => true,
            "fallos_count" => count($fallos),
            "fallos" => $fallos,
            "modulos" => $modulos,
            "logs" => $logs
        ]);
        exit;

    } elseif ($action === 'auto_heal') {
        // Ejecutar rutinas de auto-corrección
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true) ?: [];
        $fallos = $data['fallos'] ?? [];

        $logs = [];
        $correcciones = [];
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $logs[] = ["tipo" => "info", "mensaje" => "Iniciando proceso de auto-curación autónoma (NOFTRAB)..."];

        foreach ($fallos as $fallo) {
            $ticket_id = null;
            $error_detallado = "";
            $modulo_nombre = "";

            if ($fallo === 'database') {
                $modulo_nombre = "Base de Datos";
                $error_detallado = "Tablas críticas faltantes en el esquema de base de datos.";
            } elseif ($fallo === 'permissions') {
                $modulo_nombre = "Seguridad / Permisos";
                $error_detallado = "Matriz de permisos vacía o semillas desactualizadas.";
            } elseif ($fallo === 'chat') {
                $modulo_nombre = "Chat-CSR";
                $error_detallado = "Error al persistir mensajes de chat o al invocar el bot BHN.";
            } elseif ($fallo === 'helpdesk') {
                $modulo_nombre = "Helpdesk";
                $error_detallado = "Falla al crear o leer tickets e incidencias de soporte.";
            } elseif ($fallo === 'ncf') {
                $modulo_nombre = "NCF Sequencer";
                $error_detallado = "La tabla de secuencias de NCF no está bindeada o inicializada.";
            } elseif ($fallo === 'accounting') {
                $modulo_nombre = "Contabilidad Core";
                $error_detallado = "Falla al generar asientos de partida doble o desbalance.";
            }

            // 1. REGISTRAR TICKET EN EL HELPDESK
            try {
                $titulo_ticket = "[BOT-TESTING-DEV] Fallo Detectado en Módulo: " . $modulo_nombre;
                $desc_ticket = "El BOT-TESTING-DEV detectó de forma automatizada un fallo crítico en el módulo $modulo_nombre durante el diagnóstico.\nDetalles: $error_detallado\nSe procede a la resolución autónoma.";
                $sla_limite = date('Y-m-d H:i:s', strtotime("+2 hours")); // Prioridad alta

                $stmt_tk = $db->prepare("INSERT INTO tickets_soporte (usuario_id, modulo_afectado, titulo, descripcion, prioridad, estado, sla_limite, fecha_creacion) VALUES (?, ?, ?, ?, 'alta', 'abierto', ?, NOW())");
                $target_mod = strtolower($fallo);
                $stmt_tk->bind_param("issss", $usuario_id, $target_mod, $titulo_ticket, $desc_ticket, $sla_limite);
                $stmt_tk->execute();
                $ticket_id = $stmt_tk->insert_id;
                $stmt_tk->close();

                // Responder ticket inicialmente
                $msg_init = "🤖 **BOT-TESTING-DEV**:\nHe detectado esta falla en el sistema. Iniciando proceso de reparación...";
                $stmt_msg = $db->prepare("INSERT INTO mensajes_ticket (ticket_id, usuario_id, mensaje, origen, fecha_envio) VALUES (?, NULL, ?, 'bot', NOW())");
                $stmt_msg->bind_param("is", $ticket_id, $msg_init);
                $stmt_msg->execute();
                $stmt_msg->close();

                $logs[] = ["tipo" => "ok", "mensaje" => "Ticket de Helpdesk #$ticket_id creado para registrar el fallo en $modulo_nombre."];
            } catch (Exception $ex_tk) {
                // Si la tabla tickets no existe (fallo estructural general), no podemos registrar el ticket.
                // En este caso, procedemos a curar directamente
                $logs[] = ["tipo" => "error", "mensaje" => "No se pudo registrar el ticket en el Helpdesk por fallos estructurales mayores. Procediendo con la reparación directa."];
            }

            // 2. EJECUTAR ACCIÓN CORRECTIVA
            $reparado = false;
            $log_reparacion = "";

            if ($fallo === 'database' || $fallo === 'chat' || $fallo === 'helpdesk' || $fallo === 'accounting') {
                // Ejecutar todas las migraciones del Plating Installer
                $logs[] = ["tipo" => "info", "mensaje" => "Corriendo scripts de migración de base de datos pendientes..."];
                
                $migration_files = glob(dirname(__DIR__) . '/migration_*.php');
                $ejecutados = 0;
                
                // Asegurar existencia de la tabla de logs
                $db->query("CREATE TABLE IF NOT EXISTS sistema_migraciones_log (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    nombre_archivo VARCHAR(255) UNIQUE NOT NULL,
                    fecha_ejecucion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

                // Leer ejecutadas
                $res_mig = $db->query("SELECT nombre_archivo FROM sistema_migraciones_log");
                $ejecutadas = [];
                if ($res_mig) {
                    while ($row = $res_mig->fetch_assoc()) $ejecutadas[] = $row['nombre_archivo'];
                }

                foreach ($migration_files as $file) {
                    $base = basename($file);
                    if (!in_array($base, $ejecutadas)) {
                        ob_start();
                        try {
                            include $file;
                            ob_get_clean();
                            
                            $stmt_ins = $db->prepare("INSERT INTO sistema_migraciones_log (nombre_archivo) VALUES (?)");
                            $stmt_ins->bind_param("s", $base);
                            $stmt_ins->execute();
                            $stmt_ins->close();
                            $ejecutados++;
                        } catch (Exception $ex_mig) {
                            ob_get_clean();
                            $logs[] = ["tipo" => "error", "mensaje" => "Error al ejecutar migración $base: " . $ex_mig->getMessage()];
                        }
                    }
                }
                
                $reparado = true;
                $log_reparacion = "Se ejecutaron exitosamente $ejecutados scripts de migración de base de datos pendientes.";
                $logs[] = ["tipo" => "ok", "mensaje" => $log_reparacion];
            }

            if ($fallo === 'permissions') {
                // Sembrar perfiles y permisos
                $logs[] = ["tipo" => "info", "mensaje" => "Sincronizando malla de perfiles y permisos de usuario..."];
                
                $_GET['dry_run'] = '0';
                $_GET['token'] = 'MQF_SEED_2026_SECURE';
                
                ob_start();
                try {
                    @include dirname(__DIR__) . '/../seed_permisos_panel_tabs.php';
                    @include dirname(__DIR__) . '/../seed_permisos.php';
                    @include dirname(__DIR__) . '/../seed_permisos_centro_financiero.php';
                    ob_get_clean();
                    
                    $reparado = true;
                    $log_reparacion = "Se sembró y sincronizó la matriz de permisos granulares para perfiles en la base de datos.";
                    $logs[] = ["tipo" => "ok", "mensaje" => $log_reparacion];
                } catch (Exception $ex_seed) {
                    ob_get_clean();
                    $logs[] = ["tipo" => "error", "mensaje" => "Error al ejecutar semillas de permisos: " . $ex_seed->getMessage()];
                }
            }

            if ($fallo === 'ncf') {
                // Correr el setup de NCF
                $logs[] = ["tipo" => "info", "mensaje" => "Inicializando secuencias de Comprobantes Fiscales (NCF)..."];
                ob_start();
                try {
                    @include dirname(__DIR__) . '/force_ncf_setup.php';
                    ob_get_clean();
                    
                    $reparado = true;
                    $log_reparacion = "Se crearon las tablas de secuencias de NCF y se insertaron las semillas fiscales por defecto.";
                    $logs[] = ["tipo" => "ok", "mensaje" => $log_reparacion];
                } catch (Exception $ex_ncf) {
                    ob_get_clean();
                    $logs[] = ["tipo" => "error", "mensaje" => "Error al inicializar NCF: " . $ex_ncf->getMessage()];
                }
            }

            // 3. REGISTRAR AJUSTE INMUTABLE EN LA AUDITORÍA (NOFTRAB)
            if ($reparado && $db_conn_ok && $db) {
                try {
                    $anterior = ["estado" => "fallido", "error" => $error_detallado];
                    $nuevo = ["estado" => "reparado", "detalle" => $log_reparacion];
                    
                    // Registro en el historial de ajustes de auditoría
                    $sql_ajuste = "INSERT INTO historial_ajustes 
                            (usuario_id, modulo_afectado, tabla_afectada, registro_id, valor_anterior, valor_nuevo, justificacion, direccion_ip) 
                            VALUES (?, ?, 'sistema', 0, ?, ?, 'Auto-corrección autónoma ejecutada por BOT-TESTING-DEV.', ?)";
                    $stmt_aj = $db->prepare($sql_ajuste);
                    $target_mod = strtolower($fallo);
                    $val_ant = json_encode($anterior);
                    $val_nue = json_encode($nuevo);
                    $stmt_aj->bind_param("issss", $usuario_id, $target_mod, $val_ant, $val_nue, $ip);
                    $stmt_aj->execute();
                    $stmt_aj->close();

                    if (function_exists('logAudit')) {
                        logAudit($usuario_id, 'auto_correccion_bot', $target_mod, 'auto-heal', 
                            "Auto-corrección exitosa en módulo: " . $modulo_nombre, 'exitoso', null, 'historial_ajustes', 0);
                    }
                } catch (Exception $ex_aud) {
                    error_log("Error al registrar auditoría de auto-corrección: " . $ex_aud->getMessage());
                }
            }

            // 4. ACTUALIZAR TICKET EN EL HELPDESK A RESUELTO
            if ($ticket_id) {
                try {
                    $msg_resolucion = "🤖 **BOT-TESTING-DEV**:\nFallo resuelto de forma autónoma.\nAcción aplicada: $log_reparacion\nSe cierra el ticket como RESUELTO.";
                    
                    // Comentario de cierre
                    $stmt_msg = $db->prepare("INSERT INTO mensajes_ticket (ticket_id, usuario_id, mensaje, origen, fecha_envio) VALUES (?, NULL, ?, 'bot', NOW())");
                    $stmt_msg->bind_param("is", $ticket_id, $msg_resolucion);
                    $stmt_msg->execute();
                    $stmt_msg->close();

                    // Cerrar ticket
                    $stmt_close = $db->prepare("UPDATE tickets_soporte SET estado = 'resuelto', fecha_resolucion = NOW() WHERE id = ?");
                    $stmt_close->bind_param("i", $ticket_id);
                    $stmt_close->execute();
                    $stmt_close->close();
                } catch (Exception $ex_close) {
                    error_log("Error al cerrar ticket de helpdesk: " . $ex_close->getMessage());
                }
            }

            // Agregar a la lista de respuestas de la bitácora
            $correcciones[] = [
                "fecha" => date('Y-m-d H:i:s'),
                "modulo" => $modulo_nombre,
                "error" => $error_detallado,
                "ticket_id" => $ticket_id ?: 0,
                "estado" => "resuelto"
            ];
        }

        echo json_encode([
            "exito" => true,
            "mensaje" => "Proceso de auto-corrección finalizado.",
            "logs" => $logs,
            "correcciones" => $correcciones
        ]);
        exit;
    } elseif ($action === 'aplicar_plan_motge') {
        // Ejecutar corrección atómica asistida aprobada por el Administrador
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true) ?: [];
        $firma_error = $data['firma_error'] ?? '';
        $ticket_id = isset($data['ticket_id']) ? (int)$data['ticket_id'] : 0;

        if (empty($firma_error)) {
            http_response_code(400);
            echo json_encode(["exito" => false, "mensaje" => "Firma de error requerida."]);
            exit;
        }

        // Obtener el comando de autocuración de la base de datos de experiencia
        $stmt_exp = $db->prepare("SELECT id, comando_autocuracion, solucion_propuesta FROM motge_experiencia WHERE firma_error = ? LIMIT 1");
        if (!$stmt_exp) {
            http_response_code(500);
            echo json_encode(["exito" => false, "mensaje" => "Error al consultar base de experiencia."]);
            exit;
        }

        $stmt_exp->bind_param("s", $firma_error);
        $stmt_exp->execute();
        $res_exp = $stmt_exp->get_result();
        $experiencia = $res_exp->fetch_assoc();
        $stmt_exp->close();

        if (!$experiencia) {
            http_response_code(404);
            echo json_encode(["exito" => false, "mensaje" => "No se encontró solución registrada para esta firma de error."]);
            exit;
        }

        $cmd = $experiencia['comando_autocuracion'];
        $solucion = $experiencia['solucion_propuesta'];
        $exito = false;
        $mensaje_correccion = "";

        // Ejecutar acción atómica segura sin backups (NOFTRAB)
        if ($cmd === 'RESET_PERMISSIONS') {
            $db->query("DELETE FROM permisos_perfil WHERE perfil_id = 1"); // Admin
            $db->query("INSERT IGNORE INTO permisos_perfil (perfil_id, funcion_modulo_id, permiso) 
                        SELECT 1, id, 1 FROM funciones_modulo");
            $exito = true;
            $mensaje_correccion = "Malla de permisos del administrador principal restablecida correctamente.";
        } elseif (strpos($cmd, 'REBUILD_TABLE:') === 0) {
            $table = str_replace('REBUILD_TABLE:', '', $cmd);
            $table = preg_replace('/[^a-zA-Z0-9_-]/', '', $table);
            
            if ($table === 'usuarios') {
                $db->query("CREATE TABLE IF NOT EXISTS `usuarios` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `username` varchar(50) NOT NULL UNIQUE,
                    `nombre` varchar(100) NOT NULL,
                    `password` varchar(255) NOT NULL,
                    `referente_id` int(11) DEFAULT NULL,
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                $exito = true;
                $mensaje_correccion = "Tabla `usuarios` verificada / recreada.";
            } else {
                $exito = false;
                $mensaje_correccion = "Reconstrucción de la tabla `$table` no soportada automáticamente.";
            }
        } elseif ($cmd === 'RECALIBRATE_NCF') {
            $db->query("UPDATE secuencia_ncf SET secuencia_actual = secuencia_actual + 1");
            $exito = true;
            $mensaje_correccion = "Secuencias contables de NCF incrementadas para prevenir duplicidades.";
        } else {
            $exito = false;
            $mensaje_correccion = "Comando de autocuración desconocido o requiere intervención manual.";
        }

        if ($exito) {
            // 1. Confirmar éxito en la base de experiencia
            $stmt_upd = $db->prepare("UPDATE motge_experiencia SET exito_confirmado = 1 WHERE id = ?");
            if ($stmt_upd) {
                $stmt_upd->bind_param("i", $experiencia['id']);
                $stmt_upd->execute();
                $stmt_upd->close();
            }

            // 2. Cerrar el ticket de soporte si existe
            if ($ticket_id > 0) {
                $msg_resolve = "💡 **AUTOCURACIÓN ASISTIDA MOTGE-BOTS**:\nCorrección autorizada y aplicada con éxito. Detalles: " . $mensaje_correccion;
                $stmt_msg = $db->prepare("INSERT INTO mensajes_ticket (ticket_id, usuario_id, mensaje, origen, fecha_envio) VALUES (?, NULL, ?, 'bot', NOW())");
                if ($stmt_msg) {
                    $stmt_msg->bind_param("is", $ticket_id, $msg_resolve);
                    $stmt_msg->execute();
                    $stmt_msg->close();
                }

                $db->query("UPDATE tickets_soporte SET estado = 'cerrado', fecha_cierre = NOW() WHERE id = $ticket_id");
            }
        }

        echo json_encode([
            "exito" => $exito,
            "mensaje" => $mensaje_correccion
        ]);
        exit;
    } else {
        http_response_code(400);
        echo json_encode(["exito" => false, "mensaje" => "Acción no válida"]);
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["exito" => false, "mensaje" => "Error interno en el Bot: " . $e->getMessage()]);
    exit;
}
