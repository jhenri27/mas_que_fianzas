<?php
/**
 * Clase de Gestión de Usuarios
 * MAS QUE FIANZAS
 */

require_once dirname(__FILE__) . '/config.php';

class UsuarioManager {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Genera un código de usuario basado en la nomenclatura [JERARQUIA][SIGLAS][CORRELATIVO]
     */
    private function generarCodigoUsuario($perfil_id) {
        // Consultar jerarquía y siglas del perfil
        $sql = "SELECT nivel_jerarquico, siglas FROM perfiles WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $perfil_id);
        $stmt->execute();
        $perfil = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$perfil) return null;

        $jerarquia = str_pad($perfil['nivel_jerarquico'], 2, '0', STR_PAD_LEFT);
        $siglas = !empty($perfil['siglas']) ? $perfil['siglas'] : 'USU';

        // Contar usuarios con el mismo perfil para el correlativo
        $sql_count = "SELECT COUNT(*) as total FROM usuarios WHERE perfil_id = ?";
        $stmt = $this->db->prepare($sql_count);
        $stmt->bind_param("i", $perfil_id);
        $stmt->execute();
        $total = $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();

        $correlativo = str_pad($total + 1, 3, '0', STR_PAD_LEFT);

        return "{$jerarquia}{$siglas}{$correlativo}";
    }

    /**
     * Crear nuevo usuario
     */
    public function crearUsuario($datos, $usuario_creador) {
        try {
            // Validar que el usuario creador tenga permisos
            if (!tienePermiso($usuario_creador, 'USU_CREAR') && !tienePermiso($usuario_creador, 'USU_TOTAL')) {
                return ['exito' => false, 'mensaje' => 'No tiene permisos para crear usuarios'];
            }

            // Validar datos requeridos
            $requeridos = ['cedula', 'nombre', 'apellido', 'email', 'username', 'perfil_id'];
            foreach ($requeridos as $campo) {
                if (empty($datos[$campo])) {
                    return ['exito' => false, 'mensaje' => "El campo $campo es requerido"];
                }
            }

            // Validar email único
            if ($this->emailExiste($datos['email'])) {
                return ['exito' => false, 'mensaje' => 'El email ya está registrado'];
            }

            // Validar username único
            if ($this->usernameExiste($datos['username'])) {
                return ['exito' => false, 'mensaje' => 'El nombre de usuario ya existe'];
            }

            // Generar código de usuario
            $codigo_usuario = $this->generarCodigoUsuario($datos['perfil_id']);

            // Generar contraseña temporal
            $password_temporal = $this->generarPasswordTemporal();
            $password_hash = password_hash($password_temporal, HASH_ALGORITHM, ['cost' => HASH_COST]);

            // Preparar SQL de inserción
            $sql = "INSERT INTO usuarios 
                    (codigo_usuario, cedula, nombre, apellido, email, telefono, username, password_hash, 
                     perfil_id, estado, es_comisionante, porcentaje_comision, porcentaje_comision_red, 
                     referente_id, requiere_cambio_password, creado_por) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)";

            $stmt = $this->db->prepare($sql);
            if (!$stmt) {
                return ['exito' => false, 'mensaje' => 'Error en la preparación de la consulta'];
            }

            $estado = 'inactivo';
            $cedula = $datos['cedula'];
            $nombre = $datos['nombre'];
            $apellido = $datos['apellido'];
            $email = $datos['email'];
            $telefono = $datos['telefono'] ?? null;
            $username = $datos['username'];
            $perfil_id = (int)$datos['perfil_id'];
            $es_comisionante = (int)($datos['es_comisionante'] ?? 0);
            $porcentaje_comision = (float)($datos['porcentaje_comision'] ?? 0);
            $porcentaje_comision_red = (float)($datos['porcentaje_comision_red'] ?? 0);
            $referente_id = !empty($datos['referente_id']) ? (int)$datos['referente_id'] : null;

            $stmt->bind_param(
                "ssssssssisiiddi",
                $codigo_usuario,
                $cedula,
                $nombre,
                $apellido,
                $email,
                $telefono,
                $username,
                $password_hash,
                $perfil_id,
                $estado,
                $es_comisionante,
                $porcentaje_comision,
                $porcentaje_comision_red,
                $referente_id,
                $usuario_creador
            );

            if (!$stmt->execute()) {
                return ['exito' => false, 'mensaje' => 'Error al crear el usuario: ' . $stmt->error];
            }

            $usuario_id = $stmt->insert_id;
            $stmt->close();

            // Registrar en auditoría
            logAudit(
                $usuario_creador,
                'crear_usuario',
                'usuarios',
                'USU_CREAR',
                "Se creó nuevo usuario: {$datos['username']}",
                'exitoso',
                null,
                'usuarios',
                $usuario_id,
                null,
                $datos
            );

            return [
                'exito' => true,
                'mensaje' => 'Usuario creado exitosamente',
                'usuario_id' => $usuario_id,
                'password_temporal' => $password_temporal,
                'debe_enviar_email' => true
            ];

        } catch (Exception $e) {
            logAudit($usuario_creador, 'crear_usuario', 'usuarios', 'USU_CREAR', 'Error al crear usuario', 'fallido', $e->getMessage());
            return ['exito' => false, 'mensaje' => 'Error: ' . $e->getMessage()];
        }
    }

    /**
     * Editar usuario existente
     */
    public function editarUsuario($usuario_id, $datos, $usuario_editor) {
        try {
            if (!tienePermiso($usuario_editor, 'USU_EDITAR') && !tienePermiso($usuario_editor, 'USU_TOTAL')) {
                return ['exito' => false, 'mensaje' => 'No tiene permisos para editar usuarios'];
            }

            // Obtener usuario actual para auditoría
            $usuario_actual = $this->obtenerUsuarioCompleto($usuario_id);
            if (!$usuario_actual) {
                return ['exito' => false, 'mensaje' => 'Usuario no encontrado'];
            }

            // Preparar campos para actualizar
            $campos_actualizables = [
                'nombre', 'apellido', 'email', 'telefono', 'perfil_id', 'estado',
                'es_comisionante', 'porcentaje_comision', 'porcentaje_comision_red', 'referente_id'
            ];
            $campos_actualizar = [];
            $tipos = '';
            $valores = [];

            foreach ($campos_actualizables as $campo) {
                if (isset($datos[$campo])) {
                    // Validar email único si se está actualizando
                    if ($campo === 'email' && $datos['email'] !== $usuario_actual['email']) {
                        if ($this->emailExiste($datos['email'])) {
                            return ['exito' => false, 'mensaje' => 'El email ya está registrado'];
                        }
                    }

                    $campos_actualizar[] = "$campo = ?";
                    
                    // Determinar tipo para bind_param
                    if (in_array($campo, ['perfil_id', 'es_comisionante', 'referente_id'])) {
                        $tipos .= 'i';
                        $valores[] = $datos[$campo] !== '' ? (int)$datos[$campo] : null;
                    } elseif (in_array($campo, ['porcentaje_comision', 'porcentaje_comision_red'])) {
                        $tipos .= 'd';
                        $valores[] = (float)$datos[$campo];
                    } else {
                        $tipos .= 's';
                        $valores[] = $datos[$campo];
                    }
                }
            }

            if (empty($campos_actualizar)) {
                return ['exito' => false, 'mensaje' => 'No hay campos para actualizar'];
            }

            $campos_actualizar[] = 'fecha_modificacion = NOW()';
            $campos_actualizar[] = 'modificado_por = ?';
            $tipos .= 'ii';
            $valores[] = $usuario_editor;
            $valores[] = $usuario_id;

            $sql = "UPDATE usuarios SET " . implode(', ', $campos_actualizar) . " WHERE id = ?";

            $stmt = $this->db->prepare($sql);
            if (!$stmt) {
                return ['exito' => false, 'mensaje' => 'Error en la preparación de la consulta'];
            }

            $stmt->bind_param($tipos, ...$valores);

            if (!$stmt->execute()) {
                return ['exito' => false, 'mensaje' => 'Error al actualizar usuario: ' . $stmt->error];
            }

            $stmt->close();

            // Registrar en auditoría
            $usuario_nuevo = $this->obtenerUsuarioCompleto($usuario_id);
            logAudit(
                $usuario_editor,
                'editar_usuario',
                'usuarios',
                'USU_EDITAR',
                "Se editó usuario: {$usuario_nuevo['username']}",
                'exitoso',
                null,
                'usuarios',
                $usuario_id,
                $usuario_actual,
                $usuario_nuevo
            );

            return ['exito' => true, 'mensaje' => 'Usuario actualizado exitosamente'];

        } catch (Exception $e) {
            return ['exito' => false, 'mensaje' => 'Error: ' . $e->getMessage()];
        }
    }

    /**
     * Bloquear usuario
     */
    public function bloquearUsuario($usuario_id, $razon, $usuario_bloqueador) {
        try {
            if (!tienePermiso($usuario_bloqueador, 'USU_BLOQUEAR') && !tienePermiso($usuario_bloqueador, 'USU_TOTAL')) {
                return ['exito' => false, 'mensaje' => 'No tiene permisos para bloquear usuarios'];
            }

            $sql = "UPDATE usuarios 
                    SET estado = 'bloqueado', 
                        descripcion_bloqueo = ?, 
                        modificado_por = ?,
                        fecha_modificacion = NOW() 
                    WHERE id = ?";

            $stmt = $this->db->prepare($sql);
            $stmt->bind_param("sii", $razon, $usuario_bloqueador, $usuario_id);

            if (!$stmt->execute()) {
                return ['exito' => false, 'mensaje' => 'Error al bloquear usuario'];
            }

            $stmt->close();

            logAudit($usuario_bloqueador, 'bloquear_usuario', 'usuarios', 'USU_BLOQUEAR', "Usuario bloqueado: $razon", 'exitoso', null, 'usuarios', $usuario_id);

            return ['exito' => true, 'mensaje' => 'Usuario bloqueado exitosamente'];

        } catch (Exception $e) {
            return ['exito' => false, 'mensaje' => 'Error: ' . $e->getMessage()];
        }
    }

    /**
     * Desbloquear usuario
     */
    public function desbloquearUsuario($usuario_id, $usuario_desbloqueador) {
        try {
            if (!tienePermiso($usuario_desbloqueador, 'USU_EDITAR') && !tienePermiso($usuario_desbloqueador, 'USU_TOTAL')) {
                return ['exito' => false, 'mensaje' => 'No tiene permisos para desbloquear usuarios'];
            }

            $sql = "UPDATE usuarios 
                    SET estado = 'activo', 
                        descripcion_bloqueo = NULL,
                        intentos_fallidos = 0,
                        bloqueado_hasta = NULL,
                        modificado_por = ?,
                        fecha_modificacion = NOW() 
                    WHERE id = ?";

            $stmt = $this->db->prepare($sql);
            $stmt->bind_param("ii", $usuario_desbloqueador, $usuario_id);

            if (!$stmt->execute()) {
                return ['exito' => false, 'mensaje' => 'Error al desbloquear usuario'];
            }

            $stmt->close();

            logAudit($usuario_desbloqueador, 'desbloquear_usuario', 'usuarios', 'USU_EDITAR', "Usuario desbloqueado", 'exitoso', null, 'usuarios', $usuario_id);

            return ['exito' => true, 'mensaje' => 'Usuario desbloqueado exitosamente'];

        } catch (Exception $e) {
            return ['exito' => false, 'mensaje' => 'Error: ' . $e->getMessage()];
        }
    }

    /**
     * Restablecer contraseña de usuario
     */
    public function restablecerPassword($usuario_id, $usuario_resetea) {
        try {
            if (!tienePermiso($usuario_resetea, 'USU_PASS_RESET') && !tienePermiso($usuario_resetea, 'USU_TOTAL')) {
                return ['exito' => false, 'mensaje' => 'No tiene permisos para restablecer contraseñas'];
            }

            $usuario = $this->obtenerUsuarioCompleto($usuario_id);
            if (!$usuario) {
                return ['exito' => false, 'mensaje' => 'Usuario no encontrado'];
            }

            // Generar nueva contraseña temporal
            $password_temporal = $this->generarPasswordTemporal();
            $password_hash = password_hash($password_temporal, HASH_ALGORITHM, ['cost' => HASH_COST]);

            // Guardar contraseña anterior en historial
            $sql_historial = "INSERT INTO historial_password (usuario_id, password_anterior_hash, motivo, cambiado_por) 
                             VALUES (?, ?, 'Restablecimiento por administrador', ?)";
            $stmt = $this->db->prepare($sql_historial);
            $stmt->bind_param("isi", $usuario_id, $usuario['password_hash'], $usuario_resetea);
            $stmt->execute();
            $stmt->close();

            // Actualizar contraseña y desbloquear cuenta
            $sql = "UPDATE usuarios 
                    SET password_hash = ?, 
                        requiere_cambio_password = 1,
                        intentos_fallidos = 0,
                        bloqueado_hasta = NULL,
                        estado = 'activo',
                        ultimo_cambio_password = NOW(),
                        modificado_por = ?,
                        fecha_modificacion = NOW() 
                    WHERE id = ?";

            $stmt = $this->db->prepare($sql);
            $stmt->bind_param("sii", $password_hash, $usuario_resetea, $usuario_id);

            if (!$stmt->execute()) {
                return ['exito' => false, 'mensaje' => 'Error al restablecer contraseña'];
            }
            $stmt->close();

            // Enviar correo al usuario con su nueva contraseña temporal
            $correo_enviado = false;
            if (!empty($usuario['email'])) {
                try {
                    require_once dirname(__FILE__) . '/Mailer.php';
                    $mailer = new Mailer();
                    $link_login = "http://localhost/PLATAFORMA_INTEGRADA/frontend/";
                    $cuerpo = "
                        <div style='font-family:Arial,sans-serif;max-width:500px;margin:0 auto;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;'>
                            <div style='background:#1e293b;padding:20px;text-align:center;'>
                                <h2 style='color:#fff;margin:0;font-size:18px;'>MAS QUE FIANZAS</h2>
                            </div>
                            <div style='padding:28px;'>
                                <h3 style='color:#1e293b;margin-top:0;'>Contraseña Restablecida</h3>
                                <p>Hola <strong>{$usuario['nombre']} {$usuario['apellido']}</strong>,</p>
                                <p>Un administrador ha restablecido tu contraseña de acceso al sistema.</p>
                                <div style='background:#f0f4ff;border:1px solid #c7d2fe;border-radius:8px;padding:16px;margin:20px 0;text-align:center;'>
                                    <p style='margin:0 0 6px;color:#475569;font-size:13px;'>Tu nueva contraseña temporal es:</p>
                                    <p style='margin:0;font-size:22px;font-weight:700;color:#4f46e5;letter-spacing:2px;font-family:monospace;'>{$password_temporal}</p>
                                </div>
                                <p style='color:#ef4444;font-size:13px;'>⚠️ Por seguridad, deberás cambiar esta contraseña la próxima vez que inicies sesión.</p>
                                <a href='{$link_login}' style='display:inline-block;margin-top:10px;padding:10px 24px;background:#4f46e5;color:#fff;border-radius:6px;text-decoration:none;font-weight:700;'>Iniciar Sesión</a>
                            </div>
                            <div style='background:#f8fafc;padding:12px 28px;font-size:12px;color:#94a3b8;'>
                                Si no solicitaste este cambio, contacta al administrador del sistema.
                            </div>
                        </div>
                    ";
                    $correo_enviado = $mailer->enviar($usuario['email'], "Tu contraseña ha sido restablecida - MAS QUE FIANZAS", $cuerpo);
                } catch (Exception $e) {
                    // Si falla el correo, el reset igual fue exitoso — loguear pero no fallar
                    error_log("Error enviando correo de reset: " . $e->getMessage());
                }
            }

            logAudit($usuario_resetea, 'cambio_password', 'usuarios', 'USU_PASS_RESET', "Contraseña restablecida para: {$usuario['username']} | Correo enviado: " . ($correo_enviado ? 'Sí' : 'No'), 'exitoso', null, 'usuarios', $usuario_id);

            return [
                'exito'             => true,
                'mensaje'           => 'Contraseña restablecida exitosamente' . ($correo_enviado ? '. Se envió un correo al usuario con la nueva clave.' : '. No se pudo enviar correo (verifique configuración SMTP).'),
                'password_temporal' => $password_temporal,
                'email_destino'     => $usuario['email'],
                'correo_enviado'    => $correo_enviado,
                'nombre_usuario'    => $usuario['nombre'] . ' ' . $usuario['apellido'],
            ];

        } catch (Exception $e) {
            return ['exito' => false, 'mensaje' => 'Error: ' . $e->getMessage()];
        }
    }


    /**
     * Eliminar usuario
     */
    public function eliminarUsuario($usuario_id, $usuario_eliminador) {
        try {
            $perfil_ejecutor = obtenerPerfilUsuario($usuario_eliminador);
            $es_admin = ($perfil_ejecutor && $perfil_ejecutor['id'] == 1);

            if (!$es_admin && !tienePermiso($usuario_eliminador, 'USU_ELIMINAR') && !tienePermiso($usuario_eliminador, 'USU_TOTAL')) {
                return ['exito' => false, 'mensaje' => 'Acción restringida: Solo el Administrador puede eliminar usuarios.'];
            }


            $usuario = $this->obtenerUsuarioCompleto($usuario_id);
            if (!$usuario) {
                return ['exito' => false, 'mensaje' => 'Usuario no encontrado'];
            }

            // No permitir eliminar al administrador
            if ($usuario['perfil_id'] === 1 && $usuario_eliminador !== $usuario_id) {
                return ['exito' => false, 'mensaje' => 'No se puede eliminar al administrador del sistema'];
            }

            // Soft delete: cambiar estado a inactivo en lugar de eliminar físicamente
            $sql = "UPDATE usuarios SET estado = 'inactivo', modificado_por = ?, fecha_modificacion = NOW() WHERE id = ?";

            $stmt = $this->db->prepare($sql);
            $stmt->bind_param("ii", $usuario_eliminador, $usuario_id);

            if (!$stmt->execute()) {
                return ['exito' => false, 'mensaje' => 'Error al eliminar usuario'];
            }

            $stmt->close();

            logAudit($usuario_eliminador, 'eliminar_usuario', 'usuarios', 'USU_ELIMINAR', "Usuario eliminado: {$usuario['username']}", 'exitoso', null, 'usuarios', $usuario_id);

            return ['exito' => true, 'mensaje' => 'Usuario eliminado exitosamente'];

        } catch (Exception $e) {
            return ['exito' => false, 'mensaje' => 'Error: ' . $e->getMessage()];
        }
    }

    /**
     * Obtener lista de usuarios con paginación
     */
    public function listarUsuarios($pagina = 1, $por_pagina = 20, $filtros = []) {
        $offset = ($pagina - 1) * $por_pagina;

        $sql = "SELECT u.id, u.codigo_usuario, u.cedula, u.nombre, u.apellido, u.email, u.username, u.estado,
                       p.nombre_perfil, u.fecha_creacion, u.fecha_ultimo_acceso,
                       u.es_comisionante, u.referente_id, r.username as username_referente
                FROM usuarios u
                LEFT JOIN perfiles p ON u.perfil_id = p.id
                LEFT JOIN usuarios r ON u.referente_id = r.id
                WHERE 1=1";


        $params = [];
        $tipos = '';

        // Filtros opcionales
        if (!empty($filtros['estado'])) {
            $sql .= " AND u.estado = ?";
            $tipos .= 's';
            $params[] = $filtros['estado'];
        }

        if (!empty($filtros['perfil_id'])) {
            $sql .= " AND u.perfil_id = ?";
            $tipos .= 'i';
            $params[] = $filtros['perfil_id'];
        }

        if (!empty($filtros['buscar'])) {
            $sql .= " AND (u.nombre LIKE ? OR u.apellido LIKE ? OR u.email LIKE ? OR u.username LIKE ? OR u.codigo_usuario LIKE ?)";
            $buscar = '%' . $filtros['buscar'] . '%';
            $tipos .= 'sssss';
            $params[] = $buscar;
            $params[] = $buscar;
            $params[] = $buscar;
            $params[] = $buscar;
            $params[] = $buscar;
        }

        // Total de registros
        $sql_count = "SELECT COUNT(*) as total FROM usuarios u WHERE 1=1";
        if (!empty($filtros['estado'])) $sql_count .= " AND u.estado = '{$filtros['estado']}'";
        if (!empty($filtros['perfil_id'])) $sql_count .= " AND u.perfil_id = {$filtros['perfil_id']}";

        $result_count = $this->db->query($sql_count);
        $total_registros = $result_count->fetch_assoc()['total'];
        $total_paginas = ceil($total_registros / $por_pagina);

        // Agregar ordenamiento y limitación
        $sql .= " ORDER BY u.fecha_creacion DESC LIMIT ?, ?";
        $tipos .= 'ii';
        $params[] = $offset;
        $params[] = $por_pagina;

        $stmt = $this->db->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($tipos, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        $usuarios = [];
        while ($row = $result->fetch_assoc()) {
            $usuarios[] = $row;
        }
        $stmt->close();

        return [
            'usuarios' => $usuarios,
            'paginacion' => [
                'pagina_actual' => $pagina,
                'por_pagina' => $por_pagina,
                'total_registros' => $total_registros,
                'total_paginas' => $total_paginas
            ]
        ];
    }

    /**
     * Obtener usuario completo por ID
     */
    public function obtenerUsuarioCompleto($usuario_id) {
        $sql = "SELECT u.*, p.nombre_perfil, r.nombre as nombre_referente, r.apellido as apellido_referente 
                FROM usuarios u 
                LEFT JOIN perfiles p ON u.perfil_id = p.id 
                LEFT JOIN usuarios r ON u.referente_id = r.id
                WHERE u.id = ?";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $usuario_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $result;
    }

    /**
     * Verificar si email existe
     */
    private function emailExiste($email, $usuario_id = null) {
        $sql = "SELECT id FROM usuarios WHERE email = ?";
        if ($usuario_id) {
            $sql .= " AND id != ?";
        }

        $stmt = $this->db->prepare($sql);
        if ($usuario_id) {
            $stmt->bind_param("si", $email, $usuario_id);
        } else {
            $stmt->bind_param("s", $email);
        }

        $stmt->execute();
        $existe = $stmt->num_rows > 0;
        $stmt->close();

        return $existe;
    }

    /**
     * Verificar si username existe
     */
    private function usernameExiste($username, $usuario_id = null) {
        $sql = "SELECT id FROM usuarios WHERE username = ?";
        if ($usuario_id) {
            $sql .= " AND id != ?";
        }

        $stmt = $this->db->prepare($sql);
        if ($usuario_id) {
            $stmt->bind_param("si", $username, $usuario_id);
        } else {
            $stmt->bind_param("s", $username);
        }

        $stmt->execute();
        $existe = $stmt->num_rows > 0;
        $stmt->close();

        return $existe;
    }

    /**
     * Generar contraseña temporal segura
     */
    private function generarPasswordTemporal() {
        $caracteres = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%';
        $password = '';
        for ($i = 0; $i < 12; $i++) {
            $password .= $caracteres[random_int(0, strlen($caracteres) - 1)];
        }
        return $password;
    }

    /**
     * Importación masiva de usuarios
     */
    public function importarUsuarios($usuarios, $usuario_creador) {

        $insertados = 0;
        $errores = 0;
        $mensajes = [];

        foreach ($usuarios as $u) {
            // Preparar datos para crearUsuario
            $datos = [
                'cedula'    => $u['cedula'] ?? $u['rnc'] ?? '',
                'nombre'    => $u['nombre'] ?? '',
                'apellido'  => $u['apellido'] ?? '',
                'email'     => $u['email'] ?? $u['correo'] ?? '',
                'username'  => $u['username'] ?? '',
                'perfil_id' => $u['perfil_id'] ?? 2, // Por defecto Agente
                'telefono'  => $u['telefono'] ?? null
            ];

            $res = $this->crearUsuario($datos, $usuario_creador);
            if ($res['exito']) {
                $insertados++;
            } else {
                $errores++;
                $mensajes[] = "Error en {$datos['username']}: {$res['mensaje']}";
            }
        }

        return [
            'exito' => $insertados > 0,
            'mensaje' => "Proceso finalizado. Insertados: $insertados, Errores: $errores",
            'insertados' => $insertados,
            'errores' => $errores,
            'detalles' => $mensajes
        ];
    }
}


?>
