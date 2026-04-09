<?php
/**
 * Clase de Autenticación y Control de Sesiones
 * MAS QUE FIANZAS
 */

require_once dirname(__FILE__) . '/config.php';

class Autenticacion {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        session_start();
    }

    /**
     * Autenticar usuario con usuario y contraseña
     */
    public function login($username, $password, $ip_cliente = null, $user_agent = null) {
        try {
            $ip_cliente = $ip_cliente ?? $_SERVER['REMOTE_ADDR'] ?? 'DESCONOCIDA';
            $user_agent = $user_agent ?? $_SERVER['HTTP_USER_AGENT'] ?? 'DESCONOCIDA';

            // 1. Buscar usuario por username
            $sql = "SELECT u.id, u.username, u.password_hash, u.estado, u.email, u.nombre, u.apellido,
                           u.perfil_id, u.intentos_fallidos, u.bloqueado_hasta, u.requiere_cambio_password,
                           p.nombre_perfil
                    FROM usuarios u
                    LEFT JOIN perfiles p ON u.perfil_id = p.id
                    WHERE u.username = ?";

            $stmt = $this->db->prepare($sql);
            if (!$stmt) {
                logAudit(null, 'login', 'autenticacion', 'LOGIN', "Error en consulta de login", 'fallido', $this->db->error, null, null, null, null);
                return ['exito' => false, 'mensaje' => 'Error en el sistema'];
            }

            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                // Usuario no existe
                logAudit(null, 'login', 'autenticacion', 'LOGIN', "Intento de login con usuario inexistente: $username", 'fallido', 'Usuario no encontrado', null, null, null, null);
                return ['exito' => false, 'mensaje' => 'Usuario o contraseña incorrectos'];
            }

            $usuario = $result->fetch_assoc();
            $stmt->close();

            // 2. Validar que el usuario no esté bloqueado
            if ($usuario['estado'] === 'bloqueado') {
                logAudit($usuario['id'], 'login', 'autenticacion', 'LOGIN', "Intento de acceso a cuenta bloqueada", 'fallido', 'Usuario bloqueado', null, null, null, null);
                return ['exito' => false, 'mensaje' => 'Tu cuenta ha sido bloqueada. Contacta al administrador.'];
            }

            // 3. Validar que el usuario esté activo
            if ($usuario['estado'] !== 'activo' && $usuario['estado'] !== 'inactivo') {
                logAudit($usuario['id'], 'login', 'autenticacion', 'LOGIN', "Intento de acceso a cuenta inactiva", 'fallido', "Estado: {$usuario['estado']}", null, null, null, null);
                return ['exito' => false, 'mensaje' => 'Tu cuenta no está activa'];
            }

            // 4. Validar contraseña
            if (!password_verify($password, $usuario['password_hash'])) {
                // Incrementar intentos fallidos
                $intentos_nuevos = $usuario['intentos_fallidos'] + 1;

                if ($intentos_nuevos >= MAX_LOGIN_ATTEMPTS) {
                    $bloqueado_hasta = date('Y-m-d H:i:s', strtotime('+' . LOCKOUT_TIME_MINUTES . ' minutes'));
                    $sql_bloqueo = "UPDATE usuarios 
                                   SET intentos_fallidos = ?, bloqueado_hasta = ?, estado = 'bloqueado' 
                                   WHERE id = ?";
                    $stmt_bloqueo = $this->db->prepare($sql_bloqueo);
                    $stmt_bloqueo->bind_param("isi", $intentos_nuevos, $bloqueado_hasta, $usuario['id']);
                    $stmt_bloqueo->execute();
                    $stmt_bloqueo->close();

                    logAudit($usuario['id'], 'login', 'autenticacion', 'LOGIN', "Cuenta bloqueada por múltiples intentos fallidos", 'fallido', 'Demasiados intentos', null, null, null, null);
                    return ['exito' => false, 'mensaje' => "Demasiados intentos fallidos. Tu cuenta ha sido bloqueada temporalmente."];
                } else {
                    $sql_intento = "UPDATE usuarios SET intentos_fallidos = ? WHERE id = ?";
                    $stmt_intento = $this->db->prepare($sql_intento);
                    $stmt_intento->bind_param("ii", $intentos_nuevos, $usuario['id']);
                    $stmt_intento->execute();
                    $stmt_intento->close();

                    logAudit($usuario['id'], 'login', 'autenticacion', 'LOGIN', "Intento de login fallido (contraseña incorrecta)", 'fallido', "Intento {$intentos_nuevos} de " . MAX_LOGIN_ATTEMPTS, null, null, null, null);
                    return ['exito' => false, 'mensaje' => 'Usuario o contraseña incorrectos'];
                }
            }

            // 5. Login exitoso - Resets
            $sql_reset = "UPDATE usuarios 
                         SET intentos_fallidos = 0, 
                             bloqueado_hasta = NULL, 
                             estado = 'activo',
                             fecha_ultimo_acceso = NOW()
                         WHERE id = ?";
            $stmt_reset = $this->db->prepare($sql_reset);
            $stmt_reset->bind_param("i", $usuario['id']);
            $stmt_reset->execute();
            $stmt_reset->close();

            // 6. Crear sesión en base de datos
            $token_sesion = $this->generarTokenSesion();
            $fecha_expiracion = date('Y-m-d H:i:s', strtotime('+' . SESSION_TIMEOUT_MINUTES . ' minutes'));

            $sql_sesion = "INSERT INTO sesiones_usuario 
                          (usuario_id, token_sesion, direccion_ip, navegador_user_agent, fecha_expiracion, activa) 
                          VALUES (?, ?, ?, ?, ?, 1)";
            $stmt_sesion = $this->db->prepare($sql_sesion);
            $stmt_sesion->bind_param("issss", $usuario['id'], $token_sesion, $ip_cliente, $user_agent, $fecha_expiracion);
            $stmt_sesion->execute();
            $stmt_sesion->close();

            // 7. Crear sesión PHP
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['username'] = $usuario['username'];
            $_SESSION['nombre_completo'] = $usuario['nombre'] . ' ' . $usuario['apellido'];
            $_SESSION['perfil'] = $usuario['nombre_perfil'];
            $_SESSION['perfil_id'] = $usuario['perfil_id'];
            $_SESSION['token_sesion'] = $token_sesion;
            $_SESSION['fecha_login'] = date('Y-m-d H:i:s');
            $_SESSION['requiere_cambio_password'] = $usuario['requiere_cambio_password'];

            // 8. Registrar en auditoría
            logAudit($usuario['id'], 'login', 'autenticacion', 'LOGIN', "Login exitoso", 'exitoso', null, null, null, null, null);

            // Si requiere cambio de password, notificar al cliente
            if ($usuario['requiere_cambio_password']) {
                return [
                    'exito' => true,
                    'mensaje' => 'Login exitoso. Es necesario cambiar la contraseña.',
                    'requiere_cambio_password' => true,
                    'usuario_id' => $usuario['id'],
                    'token_sesion' => $token_sesion
                ];
            }

            return [
                'exito' => true,
                'mensaje' => 'Login exitoso',
                'usuario_id' => $usuario['id'],
                'nombre_completo' => $_SESSION['nombre_completo'],
                'perfil' => $usuario['nombre_perfil'],
                'token_sesion' => $token_sesion
            ];

        } catch (Exception $e) {
            logAudit(null, 'login', 'autenticacion', 'LOGIN', "Error durante login", 'fallido', $e->getMessage(), null, null, null, null);
            return ['exito' => false, 'mensaje' => 'Error en el sistema: ' . $e->getMessage()];
        }
    }

    /**
     * Cerrar sesión
     */
    public function logout($usuario_id = null) {
        try {
            $usuario_id = $usuario_id ?? ($_SESSION['usuario_id'] ?? null);
            $token_sesion = $_SESSION['token_sesion'] ?? null;

            if ($usuario_id && $token_sesion) {
                // Marcar sesión como cerrada
                $sql = "UPDATE sesiones_usuario 
                       SET activa = 0, motivo_cierre = 'Logout manual', fecha_cierre = NOW() 
                       WHERE usuario_id = ? AND token_sesion = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->bind_param("is", $usuario_id, $token_sesion);
                $stmt->execute();
                $stmt->close();

                logAudit($usuario_id, 'logout', 'autenticacion', 'LOGOUT', "Logout exitoso", 'exitoso');
            }

            // Destruir sesión PHP
            session_destroy();

            return ['exito' => true, 'mensaje' => 'Sesión cerrada exitosamente'];

        } catch (Exception $e) {
            return ['exito' => false, 'mensaje' => 'Error al cerrar sesión: ' . $e->getMessage()];
        }
    }

    /**
     * Validar sesión activa
     */
    public function validarSesion($token_sesion) {
        try {
            $sql = "SELECT su.*, u.estado, u.id as usuario_id
                   FROM sesiones_usuario su
                   INNER JOIN usuarios u ON su.usuario_id = u.id
                   WHERE su.token_sesion = ? AND su.activa = 1 AND su.fecha_expiracion > NOW()";

            $stmt = $this->db->prepare($sql);
            $stmt->bind_param("s", $token_sesion);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                return ['exito' => false, 'mensaje' => 'Sesión inválida o expirada'];
            }

            $sesion = $result->fetch_assoc();
            $stmt->close();

            // Validar que el usuario siga activo
            if ($sesion['estado'] !== 'activo') {
                return ['exito' => false, 'mensaje' => 'Usuario inactivo o bloqueado'];
            }

            return ['exito' => true, 'sesion' => $sesion];

        } catch (Exception $e) {
            return ['exito' => false, 'mensaje' => 'Error validando sesión'];
        }
    }

    /**
     * Cambiar contraseña del usuario
     */
    public function cambiarPassword($usuario_id, $password_actual, $password_nueva, $password_confirmacion) {
        try {
            // Validar que las contraseñas coincidan
            if ($password_nueva !== $password_confirmacion) {
                return ['exito' => false, 'mensaje' => 'Las contraseñas no coinciden'];
            }

            // Validar longitud mínima
            if (strlen($password_nueva) < 8) {
                return ['exito' => false, 'mensaje' => 'La contraseña debe tener al menos 8 caracteres'];
            }

            // Obtener usuario
            $sql = "SELECT id, password_hash FROM usuarios WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param("i", $usuario_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                return ['exito' => false, 'mensaje' => 'Usuario no encontrado'];
            }

            $usuario = $result->fetch_assoc();
            $stmt->close();

            // Validar contraseña actual
            if (!password_verify($password_actual, $usuario['password_hash'])) {
                logAudit($usuario_id, 'cambio_password', 'usuarios', 'CAMBIO_PASSWORD', "Intento de cambio de contraseña fallido", 'fallido', 'Contraseña actual incorrecta');
                return ['exito' => false, 'mensaje' => 'Contraseña actual incorrecta'];
            }

            // Guardar contraseña anterior en historial
            $sql_historial = "INSERT INTO historial_password (usuario_id, password_anterior_hash, motivo, cambiado_por) 
                             VALUES (?, ?, 'Cambio por usuario', ?)";
            $stmt = $this->db->prepare($sql_historial);
            $stmt->bind_param("isi", $usuario_id, $usuario['password_hash'], $usuario_id);
            $stmt->execute();
            $stmt->close();

            // Actualizar contraseña
            $password_hash = password_hash($password_nueva, HASH_ALGORITHM, ['cost' => HASH_COST]);
            $sql_update = "UPDATE usuarios 
                          SET password_hash = ?, 
                              requiere_cambio_password = 0,
                              ultimo_cambio_password = NOW()
                          WHERE id = ?";
            $stmt = $this->db->prepare($sql_update);
            $stmt->bind_param("si", $password_hash, $usuario_id);

            if (!$stmt->execute()) {
                return ['exito' => false, 'mensaje' => 'Error al actualizar contraseña'];
            }

            $stmt->close();

            logAudit($usuario_id, 'cambio_password', 'usuarios', 'CAMBIO_PASSWORD', "Contraseña cambiada exitosamente", 'exitoso');

            return ['exito' => true, 'mensaje' => 'Contraseña actualizada exitosamente'];

        } catch (Exception $e) {
            return ['exito' => false, 'mensaje' => 'Error: ' . $e->getMessage()];
        }
    }

    /**
     * Validar que la sesión actual sea válida
     */
    public static function sesionValida() {
        return isset($_SESSION['usuario_id']) && isset($_SESSION['token_sesion']);
    }

    /**
     * Obtener datos del usuario actual
     */
    public static function usuarioActual() {
        return $_SESSION['usuario_id'] ?? null;
    }

    /**
     * Generar token de sesión único
     */
    private function generarTokenSesion() {
        return hash('sha256', uniqid() . time() . random_bytes(16));
    }
}

?>
