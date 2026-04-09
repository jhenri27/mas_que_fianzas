<?php
/**
 * Configuración de Base de Datos
 * MAS QUE FIANZAS - Sistema Integrado
 */

// ==================== CONFIGURACIÓN DE BASE DE DATOS ====================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASSWORD', ''); // Por defecto en WAMP
define('DB_NAME', 'masque_fianzas_integrada_01');
define('DB_PORT', 3306);

// ==================== CONFIGURACIÓN DE APLICACIÓN ====================
define('APP_NAME', 'MAS QUE FIANZAS');
define('APP_VERSION', '1.0.0');
define('APP_TIMEZONE', 'America/Santo_Domingo');

// ==================== CONFIGURACIÓN DE SEGURIDAD ====================
define('HASH_ALGORITHM', PASSWORD_BCRYPT);
define('HASH_COST', 10);
define('SESSION_TIMEOUT_MINUTES', 30);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME_MINUTES', 30);
define('PASSWORD_EXPIRATION_DAYS', 90);

// ==================== CONFIGURACIÓN DE API ====================
define('API_BASE_URL', 'http://localhost/PLATAFORMA_INTEGRADA/backend/api');
define('FRONTEND_BASE_URL', 'http://localhost/PLATAFORMA_INTEGRADA/frontend');
define('ENABLE_CORS', true);
define('ALLOWED_ORIGINS', ['http://localhost', 'http://localhost:3000', 'http://localhost:8080']);

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
define('TWO_FACTOR_ENABLED', false); // Opcional, cambiar a true para habilitar
define('TWO_FACTOR_PROVIDER', 'totp'); // 'email' o 'totp'

// ==================== MANEJO DE ERRORES ====================
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__FILE__) . '/logs/error.log');
date_default_timezone_set(APP_TIMEZONE);

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
            die("Error de conexión a base de datos");
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
            WHERE u.id = ? AND fm.codigo_funcion = ? AND pp.puede_ejecutar = 1
            LIMIT 1";
    
    $stmt = $db->prepare($sql);
    $stmt->bind_param("is", $usuario_id, $funcion_codigo);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    return $result->num_rows > 0;
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
