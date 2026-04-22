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
                $this->logSmtp("[AUTH] Login BLOQUEADO - cuenta bloqueada | username: {$usuario['username']} | ID: {$usuario['id']} | intentos acumulados: {$usuario['intentos_fallidos']}", 'WARNING');
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

                    $this->logSmtp("[AUTH] Cuenta BLOQUEADA automáticamente | username: {$usuario['username']} | ID: {$usuario['id']} | {$intentos_nuevos} intentos fallidos superaron el máximo permitido (".MAX_LOGIN_ATTEMPTS.")", 'WARNING');
                    logAudit($usuario['id'], 'login', 'autenticacion', 'LOGIN', "Cuenta bloqueada por múltiples intentos fallidos", 'fallido', 'Demasiados intentos', null, null, null, null);
                    return ['exito' => false, 'mensaje' => "Demasiados intentos fallidos. Tu cuenta ha sido bloqueada temporalmente."];
                } else {
                    $sql_intento = "UPDATE usuarios SET intentos_fallidos = ? WHERE id = ?";
                    $stmt_intento = $this->db->prepare($sql_intento);
                    $stmt_intento->bind_param("ii", $intentos_nuevos, $usuario['id']);
                    $stmt_intento->execute();
                    $stmt_intento->close();

                    $this->logSmtp("[AUTH] Contraseña incorrecta | username: {$usuario['username']} | ID: {$usuario['id']} | intento {$intentos_nuevos} de ".MAX_LOGIN_ATTEMPTS, 'WARNING');
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

    /**
     * Escribe un evento de autenticación/recuperación en smtp.log
     * para que aparezca en el log viewer del módulo de seguridad.
     */
    private function logSmtp($mensaje, $nivel = 'INFO') {
        $logFile = dirname(__FILE__) . '/logs/smtp.log';
        if (!is_dir(dirname($logFile))) {
            mkdir(dirname($logFile), 0777, true);
        }
        $fecha = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[{$fecha}] [{$nivel}] {$mensaje}\n", FILE_APPEND);
    }

    /**
     * Solicitar recuperación de contraseña (envía token por correo)
     */
    public function solicitarRecuperacion($identificador) {
        $sql = "SELECT id, email, username FROM usuarios WHERE (email = ? OR username = ?) AND estado != 'inactivo'";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("ss", $identificador, $identificador);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($res->num_rows === 0) {
            $this->logSmtp("[RECOVERY] Solicitud de recuperación para identificador NO ENCONTRADO: '{$identificador}'", 'WARNING');
            return ['exito' => true, 'mensaje' => 'Si el correo o usuario existe, se ha enviado un enlace de recuperación.'];
        }
        
        $usuario = $res->fetch_assoc();
        $stmt->close();
        
        $token = bin2hex(random_bytes(32));
        $hashToken = hash('sha256', $token);
        $expiracion = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $sql = "UPDATE usuarios SET reset_token = ?, reset_token_expires = ? WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("ssi", $hashToken, $expiracion, $usuario['id']);
        $stmt->execute();
        $stmt->close();

        $this->logSmtp("[RECOVERY] Token de recuperación generado | usuario ID: {$usuario['id']} | username: {$usuario['username']} | email destino: {$usuario['email']} | expira: {$expiracion}", 'INFO');
        
        require_once dirname(__FILE__) . '/Mailer.php';
        $mailer = new Mailer();
        $link = "http://localhost/PLATAFORMA_INTEGRADA/frontend/recuperar.html?token=" . $token;
        $mensaje = "<h3>Recuperación de Contraseña</h3><p>Hola {$usuario['username']},</p><p>Has solicitado restablecer tu contraseña. Haz clic en el siguiente enlace para crear una nueva (válido por 1 hora):</p><p><a href='{$link}'>{$link}</a></p><p>Si no fuiste tú, ignora este correo.</p>";
        
        $mailer->enviar($usuario['email'], "Recuperacion de Contrasena - MAS QUE FIANZAS", $mensaje);
        
        return ['exito' => true, 'mensaje' => 'Si el correo o usuario existe, se ha enviado un enlace de recuperación.'];
    }

    /**
     * Restablecer la contraseña a través de un token de recuperación válido
     */
    public function restablecerConToken($token, $nuevaPassword, $confirmacion) {
        if ($nuevaPassword !== $confirmacion) {
            return ['exito' => false, 'mensaje' => 'Las contraseñas no coinciden'];
        }
        if (strlen($nuevaPassword) < 8) {
            return ['exito' => false, 'mensaje' => 'La contraseña debe tener al menos 8 caracteres'];
        }
        
        $hashToken = hash('sha256', $token);

        // Buscar token (válido o no) para diagnóstico y para el flujo principal
        $sql = "SELECT id, username, reset_token_expires, (reset_token_expires > NOW()) as vigente 
                FROM usuarios WHERE reset_token = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("s", $hashToken);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows === 0) {
            $this->logSmtp("[RECOVERY] Token recibido INVÁLIDO — no existe en base de datos", 'ERROR');
            $stmt->close();
            return ['exito' => false, 'mensaje' => 'El enlace de recuperación es inválido o ha expirado'];
        }

        $usuario = $res->fetch_assoc();
        $stmt->close();

        if (!$usuario['vigente']) {
            $this->logSmtp("[RECOVERY] Token EXPIRADO | usuario ID: {$usuario['id']} | username: {$usuario['username']} | expiró: {$usuario['reset_token_expires']}", 'ERROR');
            return ['exito' => false, 'mensaje' => 'El enlace de recuperación es inválido o ha expirado'];
        }
        
        // Token válido — continuar con el reset
        $this->logSmtp("[RECOVERY] Token válido recibido | usuario ID: {$usuario['id']} | username: {$usuario['username']} — procesando reset de contraseña", 'INFO');

        
        $passwordHash = password_hash($nuevaPassword, HASH_ALGORITHM, ['cost' => HASH_COST]);
        
        // Al restablecer por token: limpiar token, resetear intentos, desbloquear y registrar fecha de cambio
        $sql = "UPDATE usuarios 
                SET password_hash = ?, 
                    reset_token = NULL, 
                    reset_token_expires = NULL,
                    intentos_fallidos = 0,
                    bloqueado_hasta = NULL,
                    estado = 'activo',
                    requiere_cambio_password = 0,
                    ultimo_cambio_password = NOW()
                WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("si", $passwordHash, $usuario['id']);
        $stmt->execute();
        $stmt->close();
        
        // Log auditing
        $sql_historial = "INSERT INTO historial_password (usuario_id, password_anterior_hash, motivo, cambiado_por) VALUES (?, 'reset_vía_token', 'Recuperación token', ?)";
        $stmt = $this->db->prepare($sql_historial);
        $stmt->bind_param("ii", $usuario['id'], $usuario['id']);
        $stmt->execute();
        $stmt->close();
        
        $this->logSmtp("[RECOVERY] Contraseña restablecida EXITOSAMENTE vía token | usuario ID: {$usuario['id']} | username: {$usuario['username']} | cuenta desbloqueada, intentos reseteados", 'SUCCESS');
        logAudit($usuario['id'], 'cambio_password', 'usuarios', 'CAMBIO_PASSWORD', "Contraseña recuperada vía token", 'exitoso', null, null, null, null, null);
        return ['exito' => true, 'mensaje' => 'Tu contraseña ha sido restablecida exitosamente. Ya puedes iniciar sesión.'];
    }

    /**
     * Solicitar el desbloqueo de cuenta enviando una alerta al admin
     */
    public function solicitarDesbloqueo($username) {
        $sql = "UPDATE usuarios SET solicita_desbloqueo = 1 WHERE username = ? AND estado = 'bloqueado'";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $afectados = $stmt->affected_rows;
        $stmt->close();
        
        if ($afectados > 0 || true) { // Permitir fallback de correo mas no exponer info
            require_once dirname(__FILE__) . '/Mailer.php';
            $mailer = new Mailer();
            $mensaje = "<h3>Solicitud de Desbloqueo</h3><p>El usuario <b>{$username}</b> ha solicitado el desbloqueo de su cuenta porque superó los intentos permitidos o está inhabilitada.</p><p>Revisa el portal de usuarios para rehabilitarlo.</p>";
            $mailer->enviar('ventas@masquefianzas.com', "Alerta Administrativa: Desbloqueo Solicitado", $mensaje);
            return ['exito' => true, 'mensaje' => 'Solicitud enviada. Un administrador revisará tu cuenta pronto.'];
        }
        return ['exito' => false, 'mensaje' => 'No se pudo procesar la solicitud (usuario no encontrado o no está bloqueado).'];
    }

    /**
     * Cambiar contraseña obligatoria (cuando el admin la resetea)
     * No requiere la actual ya que el usuario entra con una temporal o flag de reset
     */
    public function cambiarPasswordForzado($usuario_id, $password_nueva, $password_confirmacion) {
        try {
            if ($password_nueva !== $password_confirmacion) {
                return ['exito' => false, 'mensaje' => 'Las contraseñas no coinciden'];
            }
            if (strlen($password_nueva) < 8) {
                return ['exito' => false, 'mensaje' => 'La contraseña debe tener al menos 8 caracteres'];
            }

            // Actualizar contraseña y limpiar flag
            $password_hash = password_hash($password_nueva, HASH_ALGORITHM, ['cost' => HASH_COST]);
            $sql = "UPDATE usuarios 
                    SET password_hash = ?, 
                        requiere_cambio_password = 0,
                        ultimo_cambio_password = NOW(),
                        intentos_fallidos = 0,
                        bloqueado_hasta = NULL,
                        estado = 'activo'
                    WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param("si", $password_hash, $usuario_id);

            if (!$stmt->execute()) {
                return ['exito' => false, 'mensaje' => 'Error al actualizar contraseña'];
            }
            $stmt->close();

            logAudit($usuario_id, 'cambio_password_forzado', 'usuarios', 'CAMBIO_PASSWORD', "Contraseña actualizada tras reset administrativo", 'exitoso');

            return ['exito' => true, 'mensaje' => 'Contraseña actualizada correctamente'];

        } catch (Exception $e) {
            return ['exito' => false, 'mensaje' => 'Error: ' . $e->getMessage()];
        }
    }
}

?>
