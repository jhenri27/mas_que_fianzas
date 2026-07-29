<?php
/**
 * Configuración de Base de Datos
 * MAS QUE FIANZAS - Sistema Integrado
 */

// ==================== CONFIGURACIÓN DE BASE DE DATOS ====================
if (file_exists(dirname(__FILE__) . '/config.local.php')) {
    require_once dirname(__FILE__) . '/config.local.php';
}
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASSWORD')) define('DB_PASSWORD', '');
if (!defined('DB_NAME')) define('DB_NAME', 'masque_fianzas_integrada_01');
if (!defined('DB_PORT')) define('DB_PORT', 3306);

// ==================== CONFIGURACIÓN DE APLICACIÓN ====================
define('APP_NAME', 'MAS QUE FIANZAS');
define('APP_VERSION', '1.0.0');
define('APP_TIMEZONE', 'America/Santo_Domingo');

// ==================== CONFIGURACIÓN DE SEGURIDAD ====================
define('HASH_ALGORITHM', PASSWORD_BCRYPT);
define('HASH_COST', 10);

// ==================== CONFIGURACIÓN DE API ====================
define('API_BASE_URL', 'http://localhost/PLATAFORMA_INTEGRADA/backend/api');
define('FRONTEND_BASE_URL', 'http://localhost/PLATAFORMA_INTEGRADA/frontend');
define('ENABLE_CORS', true);
define('ALLOWED_ORIGINS', ['http://localhost', 'http://localhost:3000', 'http://localhost:8080']);
define('GOOGLE_VISION_KEY_PATH', dirname(__FILE__) . '/google-key.json');
if (file_exists(GOOGLE_VISION_KEY_PATH)) {
    putenv('GOOGLE_APPLICATION_CREDENTIALS=' . GOOGLE_VISION_KEY_PATH);
}

// ==================== CONFIGURACIÓN DE AUDITORÍA ====================
define('AUDIT_ENABLED', true);
define('AUDIT_LOG_PATH', dirname(__FILE__) . '/logs/audit.log');
define('AUDIT_INCLUDE_PASSWORDS', false);
define('AUDIT_DEBUG_MODE', false);

// ==================== CONFIGURACIÓN DE CORREO ====================
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USER', 'contacto@masquefianzas.com');
define('MAIL_PASSWORD', ''); // Se debe configurar
define('MAIL_FROM', 'sistemas@masquefianzas.com');
define('MAIL_FROM_NAME', 'MAS QUE FIANZAS');

// ==================== CONFIGURACIÓN DE DOS FACTORES ====================
define('TWO_FACTOR_PROVIDER', 'totp'); // 'email' o 'totp'

// ==================== MANEJO DE ERRORES ====================
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__FILE__) . '/logs/error.log');
date_default_timezone_set(APP_TIMEZONE);

// Inicializar el Motor de Generación de Experiencia de BOTS (MOTGE-BOTS)
require_once __DIR__ . '/lib/MOTGE.php';
require_once __DIR__ . '/lib/ValidadorDocumentos.php';
MOTGE::init();

// ==================== CONEXIÓN A BASE DE DATOS ====================
class Database {
    private $conn;
    private static $instance = null;

    private function __construct() {
        try {
            $this->conn = new mysqli(
                DB_HOST,
                DB_USER,
                DB_PASSWORD,
                DB_NAME,
                DB_PORT
            );

            if ($this->conn->connect_error) {
                throw new Exception("Error de conexión: " . $this->conn->connect_error);
            }

            $this->conn->set_charset("utf8mb4");
            $this->conn->query("SET SESSION sql_mode='STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        } catch (Exception $e) {
            error_log("Error de base de datos: " . $e->getMessage());
            throw new Exception("Error de conexión a base de datos: " . $e->getMessage(), $e->getCode());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->conn;
    }

    public function __clone() {}
    public function __wakeup() {}
}

// Resolviendo dinámicamente constantes de seguridad desde la Base de Datos (Norma NOFTRAB)
try {
    $db_conf = Database::getInstance()->getConnection();
    $res_conf = $db_conf->query("SELECT clave_config, valor_config FROM configuracion_sistema WHERE clave_config IN ('INTENTOS_LOGIN_MAX', 'MINUTOS_BLOQUEO', 'DIAS_EXPIRATION_PASSWORD', 'SESION_TIMEOUT_MINUTES', 'DOS_FACTOR_OPCIONAL')");
    
    $db_values = [];
    if ($res_conf) {
        while ($row = $res_conf->fetch_assoc()) {
            $db_values[$row['clave_config']] = $row['valor_config'];
        }
    }
    
    define('SESSION_TIMEOUT_MINUTES', isset($db_values['SESION_TIMEOUT_MINUTES']) ? (int)$db_values['SESION_TIMEOUT_MINUTES'] : 30);
    define('MAX_LOGIN_ATTEMPTS', isset($db_values['INTENTOS_LOGIN_MAX']) ? (int)$db_values['INTENTOS_LOGIN_MAX'] : 5);
    define('LOCKOUT_TIME_MINUTES', isset($db_values['MINUTOS_BLOQUEO']) ? (int)$db_values['MINUTOS_BLOQUEO'] : 30);
    define('PASSWORD_EXPIRATION_DAYS', isset($db_values['DIAS_EXPIRATION_PASSWORD']) ? (int)$db_values['DIAS_EXPIRATION_PASSWORD'] : 90);
    define('TWO_FACTOR_ENABLED', isset($db_values['DOS_FACTOR_OPCIONAL']) ? ((int)$db_values['DOS_FACTOR_OPCIONAL'] === 1) : false);
} catch (Exception $e) {
    // Fallback estático preventivo
    define('SESSION_TIMEOUT_MINUTES', 30);
    define('MAX_LOGIN_ATTEMPTS', 5);
    define('LOCKOUT_TIME_MINUTES', 30);
    define('PASSWORD_EXPIRATION_DAYS', 90);
    define('TWO_FACTOR_ENABLED', false);
}

// ==================== FUNCIONES GLOBALES DE UTILIDAD ====================

/**
 * Registra auditoría de acciones
 */
function logAudit($usuario_id, $tipo_evento, $modulo, $funcion, $descripcion, $resultado = 'exitoso', $detalles_error = null, $tabla_afectada = null, $registro_id = null, $valor_anterior = null, $valor_nuevo = null) {
    if (!AUDIT_ENABLED) return;

    $db = Database::getInstance()->getConnection();
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'DESCONOCIDA';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'DESCONOCIDA';
    $operacion = determinarOperacion($tipo_evento);

    $valor_anterior_json = $valor_anterior ? json_encode($valor_anterior) : NULL;
    $valor_nuevo_json = $valor_nuevo ? json_encode($valor_nuevo) : NULL;

    $sql = "INSERT INTO auditoria_accesos 
            (usuario_id, tipo_evento, modulo_accedido, funcion_ejecutada, descripcion_evento, 
             direccion_ip, navegador_user_agent, resultado, detalles_error, tabla_afectada, 
             registro_afectado_id, operacion_realizada, valor_anterior, valor_nuevo) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $db->prepare($sql);
    $stmt->bind_param(
        "isssssssssssss",
        $usuario_id, $tipo_evento, $modulo, $funcion, $descripcion,
        $ip, $user_agent, $resultado, $detalles_error, $tabla_afectada,
        $registro_id, $operacion, $valor_anterior_json, $valor_nuevo_json
    );

    $stmt->execute();
    $stmt->close();
}

function determinarOperacion($tipo_evento) {
    $operaciones = [
        'crear_usuario' => 'insert',
        'editar_usuario' => 'update',
        'eliminar_usuario' => 'delete',
        'crear_perfil' => 'insert',
        'editar_perfil' => 'update',
        'eliminar_perfil' => 'delete',
        'login' => 'login',
        'logout' => 'logout',
        'cambio_permiso' => 'cambio_permiso',
        'cambio_dato_usuario' => 'update'
    ];
    return $operaciones[$tipo_evento] ?? 'select';
}

/**
 * Valida si un usuario tiene permiso para ejecutar una acción
 */
function tienePermiso($usuario_id, $funcion_codigo) {
    // Bypass para el administrador principal (ID 1)
    if ($usuario_id == 1) {
        return true;
    }

    $db = Database::getInstance()->getConnection();
    
    $sql = "SELECT pp.puede_ejecutar 
            FROM usuarios u
            INNER JOIN perfiles p ON u.perfil_id = p.id
            INNER JOIN permisos_perfil pp ON p.id = pp.perfil_id
            INNER JOIN funciones_modulo fm ON pp.funcion_id = fm.id
            WHERE u.id = ? AND fm.codigo_funcion = ? AND (pp.puede_ejecutar = 1 OR pp.ver_datos = 1 OR pp.ver_reportes = 1)
            LIMIT 1";
    
    $stmt = $db->prepare($sql);
    $stmt->bind_param("is", $usuario_id, $funcion_codigo);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    return $result->num_rows > 0;
}

/**
 * Verifica si el perfil del usuario tiene restringido ver solo datos propios en un módulo específico.
 */
function restringirSoloPropios($usuario_id, $modulo_codigo_o_id) {
    // Bypass para el administrador principal (ID 1)
    if ($usuario_id == 1) {
        return false;
    }

    $db = Database::getInstance()->getConnection();

    // Podemos recibir el código del módulo (string) o el ID del módulo (int)
    if (is_numeric($modulo_codigo_o_id)) {
        $sql = "SELECT pp.solo_propios 
                FROM usuarios u
                INNER JOIN perfiles p ON u.perfil_id = p.id
                INNER JOIN permisos_perfil pp ON p.id = pp.perfil_id
                WHERE u.id = ? AND pp.modulo_id = ? AND pp.solo_propios = 1
                LIMIT 1";
    } else {
        $sql = "SELECT pp.solo_propios 
                FROM usuarios u
                INNER JOIN perfiles p ON u.perfil_id = p.id
                INNER JOIN permisos_perfil pp ON p.id = pp.perfil_id
                INNER JOIN modulos m ON pp.modulo_id = m.id
                WHERE u.id = ? AND m.nombre_modulo = ? AND pp.solo_propios = 1
                LIMIT 1";
    }

    $stmt = $db->prepare($sql);
    if (is_numeric($modulo_codigo_o_id)) {
        $stmt->bind_param("ii", $usuario_id, $modulo_codigo_o_id);
    } else {
        $stmt->bind_param("is", $usuario_id, $modulo_codigo_o_id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $restringido = ($result->num_rows > 0);
    $stmt->close();

    return $restringido;
}


/**
 * Registra un ajuste de auditoría inmutable bajo la norma NOFTRAB
 */
function registrarAjuste($usuario_id, $modulo_afectado, $tabla_afectada, $registro_id, $valor_anterior, $valor_nuevo, $justificacion) {
    $db = Database::getInstance()->getConnection();
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'DESCONOCIDA';
    
    $valor_anterior_json = json_encode($valor_anterior, JSON_UNESCAPED_UNICODE);
    $valor_nuevo_json = json_encode($valor_nuevo, JSON_UNESCAPED_UNICODE);
    
    $sql = "INSERT INTO historial_ajustes 
            (usuario_id, modulo_afectado, tabla_afectada, registro_id, valor_anterior, valor_nuevo, justificacion, direccion_ip) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        error_log("Error al preparar insertar_ajuste: " . $db->error);
        return false;
    }
    
    $stmt->bind_param("ississss", $usuario_id, $modulo_afectado, $tabla_afectada, $registro_id, $valor_anterior_json, $valor_nuevo_json, $justificacion, $ip);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}


/**
 * Obtiene el perfil del usuario
 */
function obtenerPerfilUsuario($usuario_id) {
    $db = Database::getInstance()->getConnection();
    
    $sql = "SELECT p.* FROM usuarios u 
            INNER JOIN perfiles p ON u.perfil_id = p.id 
            WHERE u.id = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    return $result;
}

/**
 * Valida token JWT (si se implementa)
 */
function validarToken($token) {
    // Aquí se implementaría validación de JWT
    // Por ahora es un placeholder
    return !empty($token);
}

/**
 * Respuesta JSON estándar
 */
function respuestaJSON($exito, $mensaje, $datos = null, $codigo_http = 200) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($codigo_http);
    
    $respuesta = [
        'exito' => $exito,
        'mensaje' => $mensaje,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    if ($datos !== null) {
        $respuesta['datos'] = $datos;
    }
    
    echo json_encode($respuesta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Obtiene la ruta del log
 */
function asegurarDirectorioLogs() {
    $directorio = dirname(AUDIT_LOG_PATH);
    if (!is_dir($directorio)) {
        mkdir($directorio, 0755, true);
    }
}

asegurarDirectorioLogs();

?>
