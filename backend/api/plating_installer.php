<?php
/**
 * API: VERSIONES PLATING - Diagnóstico e Instalador del Sistema (NOFTRAB)
 * MAS QUE FIANZAS
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

// Intentar conectar a la base de datos
$db = null;
$db_conn_ok = false;
$db_error_msg = "";
$db_need_creation = false;

try {
    $db = Database::getInstance()->getConnection();
    $db_conn_ok = ($db && $db->connect_errno === 0);
} catch (Exception $e) {
    $db_conn_ok = false;
    $db_error_msg = $e->getMessage();
    // Código de error 1049 indica base de datos no encontrada
    if ($e->getCode() === 1049 || stripos($db_error_msg, 'Unknown database') !== false || stripos($db_error_msg, 'base de datos no encontrada') !== false) {
        $db_need_creation = true;
    }
}

// Autorización de setup especial si la base de datos no existe y se provee la clave del VPS
$setup_token_req = $_GET['setup_token'] ?? $_POST['setup_token'] ?? $_REQUEST['setup_token'] ?? '';
$is_setup_authorized = (!empty($setup_token_req) && $setup_token_req === 'MasQF2026');

$usuario_id = null;
if (isset($_SESSION['usuario_id']) && $_SESSION['usuario_id']) {
    $usuario_id = (int)$_SESSION['usuario_id'];
} elseif ($is_setup_authorized) {
    $usuario_id = 1; // Autorizar temporalmente como administrador
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
        // Ignorar si falla la tabla de sesiones
    }
}

if (!$usuario_id) {
    http_response_code(401);
    echo json_encode(["exito" => false, "mensaje" => "Sesión no válida o expirada. Para instalaciones limpias, provea el parámetro setup_token."]);
    exit;
}

try {
    $es_admin = false;
    if ($usuario_id === 1 || $is_setup_authorized) {
        $es_admin = true;
    } elseif ($db_conn_ok && $db) {
        $stmt_u = $db->prepare("SELECT perfil_id FROM usuarios WHERE id = ? LIMIT 1");
        $stmt_u->bind_param("i", $usuario_id);
        $stmt_u->execute();
        $usr_data = $stmt_u->get_result()->fetch_assoc();
        $stmt_u->close();
        
        if ($usr_data) {
            $es_admin = (
                (int)$usr_data['perfil_id'] === 1 || 
                (function_exists('tienePermiso') && (tienePermiso($usuario_id, 'CONF_TOTAL') || tienePermiso($usuario_id, 'AUDITORIA_LINEAL_VER')))
            );
        }
    }

    if (!$es_admin) {
        http_response_code(403);
        echo json_encode(["exito" => false, "mensaje" => "Acceso denegado: Se requieren permisos administrativos para utilizar el instalador."]);
        exit;
    }

    $action = $_GET['action'] ?? '';

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'check_requirements') {
        // 1. PHP Version check
        $php_version = PHP_VERSION;
        $php_ok = version_compare($php_version, '8.2.0', '>=');

        // 2. MariaDB / MySQL Version check
        $db_version = 'Desconocida';
        $mariadb_ok = false;
        
        if ($db_conn_ok && $db) {
            $res_ver = $db->query("SELECT VERSION()");
            $db_version = $res_ver ? $res_ver->fetch_row()[0] : 'Desconocida';
            preg_match('/^[0-9\.]+/', $db_version, $ver_matches);
            $ver_num = $ver_matches[0] ?? '0';
            $is_mariadb = (stripos($db_version, 'mariadb') !== false);
            if ($is_mariadb) {
                $mariadb_ok = version_compare($ver_num, '10.4.0', '>=');
            } else {
                $mariadb_ok = version_compare($ver_num, '8.0.0', '>=');
            }
        } else {
            // Intentar conectar sin base de datos para ver versión y si el servidor está activo
            try {
                $test_conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, '', DB_PORT);
                if ($test_conn->connect_errno === 0) {
                    $db_version = $test_conn->server_info;
                    preg_match('/^[0-9\.]+/', $db_version, $ver_matches);
                    $ver_num = $ver_matches[0] ?? '0';
                    $mariadb_ok = version_compare($ver_num, '8.0.0', '>=') || version_compare($ver_num, '10.4.0', '>=');
                    $test_conn->close();
                }
            } catch (Exception $ex_test) {
                $db_version = 'MySQL Server no responde';
            }
        }

        // 3. Writable folders
        $logs_dir = dirname(__DIR__) . '/logs';
        $uploads_dir = dirname(__DIR__) . '/uploads';
        
        $logs_writable = is_dir($logs_dir) && is_writable($logs_dir);
        $uploads_writable = is_dir($uploads_dir) && is_writable($uploads_dir);

        // 4. Extensions check
        $required_extensions = ['mysqli', 'openssl', 'mbstring', 'curl', 'gd', 'pdo'];
        $extensions_status = [];
        $all_ext_ok = true;
        foreach ($required_extensions as $ext) {
            $loaded = extension_loaded($ext);
            $extensions_status[$ext] = $loaded;
            if (!$loaded) {
                $all_ext_ok = false;
            }
        }

        // 5. Gather migrations history
        $migrations = [];
        if ($db_conn_ok && $db) {
            $db->query("CREATE TABLE IF NOT EXISTS sistema_migraciones_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nombre_archivo VARCHAR(255) UNIQUE NOT NULL,
                fecha_ejecucion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            $migration_files = glob(dirname(__DIR__) . '/migration_*.php');
            $res_mig = $db->query("SELECT nombre_archivo FROM sistema_migraciones_log");
            $ejecutadas = [];
            if ($res_mig) {
                while ($row = $res_mig->fetch_assoc()) {
                    $ejecutadas[] = $row['nombre_archivo'];
                }
            }

            foreach ($migration_files as $file) {
                $base = basename($file);
                $migrations[] = [
                    "archivo" => $base,
                    "ejecutado" => in_array($base, $ejecutadas)
                ];
            }
        }

        $sys_status = ($php_ok && $logs_writable && $uploads_writable && $all_ext_ok && $db_conn_ok) ? "ready" : "warning";
        if ($db_need_creation) {
            $sys_status = "db_missing";
        }

        echo json_encode([
            "exito" => true,
            "status" => $sys_status,
            "db_need_creation" => $db_need_creation,
            "db_error" => $db_error_msg,
            "datos" => [
                "php" => [
                    "requerido" => ">= 8.2",
                    "actual" => $php_version,
                    "ok" => $php_ok
                ],
                "mariadb" => [
                    "requerido" => ">= 10.4",
                    "actual" => $db_version,
                    "ok" => $mariadb_ok
                ],
                "directorios" => [
                    "logs" => [
                        "ruta" => "backend/logs",
                        "ok" => $logs_writable
                    ],
                    "uploads" => [
                        "ruta" => "backend/uploads",
                        "ok" => $uploads_writable
                    ]
                ],
                "extensiones" => $extensions_status,
                "db_conexion" => $db_conn_ok,
                "migraciones" => $migrations
            ]
        ]);
        exit;

    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'crear_base_datos') {
        try {
            $conn_raw = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, '', DB_PORT);
            if ($conn_raw->connect_error) {
                throw new Exception("No se pudo conectar a MySQL para crear la base de datos: " . $conn_raw->connect_error);
            }
            
            $db_name_clean = $conn_raw->real_escape_string(DB_NAME);
            
            $sql_create = "CREATE DATABASE IF NOT EXISTS `$db_name_clean` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
            if ($conn_raw->query($sql_create)) {
                $conn_raw->close();
                
                // Conectar para inicializar tablas mínimas de auditoría
                try {
                    $db_new = Database::getInstance()->getConnection();
                    $db_new->query("CREATE TABLE IF NOT EXISTS sistema_migraciones_log (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        nombre_archivo VARCHAR(255) UNIQUE NOT NULL,
                        fecha_ejecucion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
                    
                    $db_new->query("CREATE TABLE IF NOT EXISTS auditoria_accesos (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        usuario_id INT NULL,
                        tipo_evento VARCHAR(100) NOT NULL,
                        modulo_accedido VARCHAR(100) NULL,
                        funcion_ejecutada VARCHAR(100) NULL,
                        descripcion_evento TEXT NOT NULL,
                        direccion_ip VARCHAR(45) NOT NULL,
                        navegador_user_agent VARCHAR(255) NOT NULL,
                        resultado VARCHAR(20) DEFAULT 'exitoso',
                        detalles_error TEXT DEFAULT NULL,
                        tabla_afectada VARCHAR(100) DEFAULT NULL,
                        registro_afectado_id INT DEFAULT NULL,
                        operacion_realizada VARCHAR(20) DEFAULT 'insert',
                        valor_anterior TEXT DEFAULT NULL,
                        valor_nuevo TEXT DEFAULT NULL,
                        fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

                    if (function_exists('logAudit')) {
                        logAudit($usuario_id, 'crear_base_datos', 'versiones_plating', 'crear', 
                            "Base de datos creada exitosamente: " . DB_NAME, 'exitoso', null, 'databases', 1);
                    }
                } catch (Exception $ex_audit) {
                    error_log("No se pudo registrar logAudit de creación de DB: " . $ex_audit->getMessage());
                }

                echo json_encode([
                    "exito" => true,
                    "mensaje" => "Base de datos '" . DB_NAME . "' creada con éxito. Proceda a ejecutar todas las migraciones para inicializar las tablas."
                ]);
            } else {
                throw new Exception("Error al ejecutar sentencia de creación: " . $conn_raw->error);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["exito" => false, "mensaje" => "Error al crear la base de datos: " . $e->getMessage()]);
        }
        exit;

    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'ejecutar_migracion') {
        if (!$db_conn_ok || !$db) {
            http_response_code(500);
            echo json_encode(["exito" => false, "mensaje" => "No hay conexión activa a la base de datos para correr migraciones."]);
            exit;
        }

        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true) ?: [];
        $target_migration = $data['archivo'] ?? $_POST['archivo'] ?? $_GET['archivo'] ?? 'todas';

        $db->query("CREATE TABLE IF NOT EXISTS sistema_migraciones_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre_archivo VARCHAR(255) UNIQUE NOT NULL,
            fecha_ejecucion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        $res_mig = $db->query("SELECT nombre_archivo FROM sistema_migraciones_log");
        $ejecutadas = [];
        if ($res_mig) {
            while ($row = $res_mig->fetch_assoc()) {
                $ejecutadas[] = $row['nombre_archivo'];
            }
        }

        $migration_files = glob(dirname(__DIR__) . '/migration_*.php');
        $output_logs = [];
        $ejecutados_ahora = 0;

        foreach ($migration_files as $file) {
            $base = basename($file);
            if ($target_migration !== 'todas' && $target_migration !== $base) {
                continue;
            }

            if (in_array($base, $ejecutadas)) {
                $output_logs[] = "[$base] Ya ejecutada anteriormente. Omitiendo.";
                continue;
            }

            ob_start();
            try {
                include $file;
                $output = ob_get_clean();
                
                $stmt_ins = $db->prepare("INSERT INTO sistema_migraciones_log (nombre_archivo) VALUES (?)");
                $stmt_ins->bind_param("s", $base);
                $stmt_ins->execute();
                $stmt_ins->close();

                $output_logs[] = "[$base] Ejecutada con éxito. Log:\n" . trim($output);
                $ejecutados_ahora++;

                if (function_exists('logAudit')) {
                    logAudit($usuario_id, 'ejecucion_migracion', 'versiones_plating', 'ejecutar', 
                        "Migración ejecutada: $base", 'exitoso', null, 'sistema_migraciones_log', $db->insert_id);
                }
            } catch (Exception $e) {
                ob_get_clean();
                $output_logs[] = "[$base] ERROR durante la ejecución: " . $e->getMessage();
                
                if (function_exists('logAudit')) {
                    logAudit($usuario_id, 'ejecucion_migracion', 'versiones_plating', 'ejecutar', 
                        "Error en migración: $base", 'fallido', $e->getMessage());
                }
            }
        }

        echo json_encode([
            "exito" => true,
            "mensaje" => "Proceso de migraciones finalizado.",
            "ejecutados" => $ejecutados_ahora,
            "logs" => $output_logs
        ]);
        exit;
    } else {
        http_response_code(400);
        echo json_encode(["exito" => false, "mensaje" => "Acción o método no válido"]);
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["exito" => false, "mensaje" => "Error interno del instalador: " . $e->getMessage()]);
    exit;
}
