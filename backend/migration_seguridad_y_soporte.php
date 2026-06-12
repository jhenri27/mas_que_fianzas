<?php
/**
 * Migración: Ecosistema de Seguridad y Soporte (NOFTRAB)
 * Crea las tablas necesarias y registra los módulos y funciones de:
 *   - Módulo Auditoría Lineal
 *   - Módulo Helpdesk Inteligente
 *   - Módulo Chat-CSR / Widget FAB
 *   - Función de Políticas de Seguridad Setup (dentro de Configuración)
 *
 * IDEMPOTENTE: Ejecutable múltiples veces de forma segura.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';

try {
    $db = Database::getInstance()->getConnection();
    echo "=== Iniciando migración de Seguridad y Soporte ===\n";

    // ─── 1. CREACIÓN DE TABLAS ──────────────────────────────────────────────

    // A. Tabla mensajes_chat
    $sql_chat = "CREATE TABLE IF NOT EXISTS mensajes_chat (
        id INT AUTO_INCREMENT PRIMARY KEY,
        emisor_id INT NOT NULL,
        receptor_id INT NOT NULL,
        mensaje TEXT NOT NULL,
        fecha_envio DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        leido TINYINT(1) DEFAULT 0,
        archivo_nombre VARCHAR(255) DEFAULT NULL,
        archivo_ruta VARCHAR(255) DEFAULT NULL,
        archivo_tipo VARCHAR(100) DEFAULT NULL,
        archivo_size INT DEFAULT NULL,
        archivo_hash VARCHAR(64) DEFAULT NULL,
        KEY idx_chat_users (emisor_id, receptor_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    if ($db->query($sql_chat)) {
        echo "[OK] Tabla 'mensajes_chat' verificada/creada.\n";
        
        // Agregar columnas de archivo si la tabla ya existía
        $cols_chat = [];
        $r_chat = $db->query("SHOW COLUMNS FROM mensajes_chat");
        if ($r_chat) {
            while ($row = $r_chat->fetch_assoc()) $cols_chat[] = $row['Field'];
            if (!in_array('archivo_nombre', $cols_chat)) {
                $db->query("ALTER TABLE mensajes_chat 
                    ADD COLUMN archivo_nombre VARCHAR(255) DEFAULT NULL AFTER leido,
                    ADD COLUMN archivo_ruta VARCHAR(255) DEFAULT NULL AFTER archivo_nombre,
                    ADD COLUMN archivo_tipo VARCHAR(100) DEFAULT NULL AFTER archivo_ruta,
                    ADD COLUMN archivo_size INT DEFAULT NULL AFTER archivo_tipo,
                    ADD COLUMN archivo_hash VARCHAR(64) DEFAULT NULL AFTER archivo_size");
                echo "[OK] Columnas de archivo agregadas a 'mensajes_chat'.\n";
            }
        }
    } else {
        throw new Exception("Error al crear tabla mensajes_chat: " . $db->error);
    }

    // B. Tabla tickets_soporte
    $sql_tickets = "CREATE TABLE IF NOT EXISTS tickets_soporte (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        modulo_afectado VARCHAR(100) NOT NULL,
        titulo VARCHAR(255) NOT NULL,
        descripcion TEXT NOT NULL,
        prioridad ENUM('baja', 'media', 'alta') DEFAULT 'media',
        estado ENUM('abierto', 'en_proceso', 'resuelto') DEFAULT 'abierto',
        sla_limite DATETIME NOT NULL,
        fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        fecha_resolucion DATETIME DEFAULT NULL,
        KEY idx_ticket_usuario (usuario_id),
        KEY idx_ticket_estado (estado)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    if ($db->query($sql_tickets)) {
        echo "[OK] Tabla 'tickets_soporte' verificada/creada.\n";
    } else {
        throw new Exception("Error al crear tabla tickets_soporte: " . $db->error);
    }

    // C. Tabla mensajes_ticket
    $sql_msg_ticket = "CREATE TABLE IF NOT EXISTS mensajes_ticket (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ticket_id INT NOT NULL,
        usuario_id INT NOT NULL,
        mensaje TEXT NOT NULL,
        fecha_envio DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        origen ENUM('usuario', 'bot', 'agente') NOT NULL,
        KEY idx_msg_ticket (ticket_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    if ($db->query($sql_msg_ticket)) {
        echo "[OK] Tabla 'mensajes_ticket' verificada/creada.\n";
    } else {
        throw new Exception("Error al crear tabla mensajes_ticket: " . $db->error);
    }

    // ─── 2. REGISTRO DE MÓDULOS ─────────────────────────────────────────────
    
    // Módulo: auditoria_lineal
    $mod_auditoria_id = registrarModulo($db, 'auditoria_lineal', 'Historial de auditoría cronológico de documentos', '📑', '/modulos/auditoria_lineal.html', 16);
    // Módulo: helpdesk
    $mod_helpdesk_id = registrarModulo($db, 'helpdesk', 'Portal de Helpdesk y Soporte de Incidencias', '🛎️', '/modulos/helpdesk.html', 17);

    // Módulo dashboard (ID 1)
    $stmt_dash = $db->query("SELECT id FROM modulos WHERE nombre_modulo = 'dashboard' LIMIT 1");
    $mod_dashboard_id = $stmt_dash->fetch_assoc()['id'] ?? 1;

    // Módulo configuracion (ID 8)
    $stmt_conf = $db->query("SELECT id FROM modulos WHERE nombre_modulo = 'configuracion' LIMIT 1");
    $mod_configuracion_id = $stmt_conf->fetch_assoc()['id'] ?? 8;

    // ─── 3. REGISTRO DE FUNCIONES ───────────────────────────────────────────
    
    // A. Auditoría Lineal
    $fun_auditoria_ver = registrarFuncion($db, $mod_auditoria_id, 'Ver Auditoría Lineal', 'AUDITORIA_LINEAL_VER', 'Permite visualizar el historial de auditoría de documentos', 'consultar');
    
    // B. Helpdesk
    $fun_helpdesk_ver = registrarFuncion($db, $mod_helpdesk_id, 'Ver Tickets Propios', 'HELPDESK_VER', 'Permite consultar y crear tickets de soporte propios', 'consultar');
    $fun_helpdesk_admin = registrarFuncion($db, $mod_helpdesk_id, 'Administrar Helpdesk', 'HELPDESK_ADMINISTRAR', 'Permite responder y gestionar todos los tickets del sistema y SLAs', 'editar');

    // C. Seguridad Políticas (dentro de Configuración)
    $fun_seguridad_politicas = registrarFuncion($db, $mod_configuracion_id, 'Configurar Políticas de Seguridad', 'CONF_SEGURIDAD_POLITICAS_EDITAR', 'Permite modificar las políticas de expiración de sesión, bloqueo de cuenta y claves', 'editar');

    // D. Chat-CSR (dentro de Dashboard)
    $fun_chat_acceso = registrarFuncion($db, $mod_dashboard_id, 'Acceso a Chat-CSR', 'CHAT_CSR_ACCESO', 'Permite acceder al canal de chat interno (CSR) y al widget flotante', 'consultar');
    $fun_chat_supervisar = registrarFuncion($db, $mod_dashboard_id, 'Supervisar Chats', 'CHAT_CSR_SUPERVISAR', 'Permite supervisar y responder a los chats de comunicación internos', 'editar');

    // ─── 4. ASIGNACIÓN DE PERMISOS POR DEFECTO A PERFILES ─────────────────────
    
    // Perfiles en la BD:
    // 1=Admin, 2=GteTec, 3=GteCont, 4=GteCom, 5=Socio, 6=Cajero, 7=Auditor, 8=Usuario
    $perfiles_permisos = [
        1 => [$fun_auditoria_ver, $fun_helpdesk_ver, $fun_helpdesk_admin, $fun_seguridad_politicas, $fun_chat_acceso, $fun_chat_supervisar], // Admin
        2 => [$fun_helpdesk_ver, $fun_helpdesk_admin, $fun_chat_acceso, $fun_chat_supervisar], // GteTec
        3 => [$fun_helpdesk_ver, $fun_chat_acceso], // GteCont
        4 => [$fun_helpdesk_ver, $fun_chat_acceso, $fun_chat_supervisar], // GteCom
        5 => [$fun_helpdesk_ver, $fun_chat_acceso], // Socio
        6 => [$fun_helpdesk_ver, $fun_chat_acceso], // Cajero
        7 => [$fun_auditoria_ver, $fun_helpdesk_ver, $fun_chat_acceso], // Auditor
        8 => [$fun_helpdesk_ver, $fun_chat_acceso], // Usuario
    ];

    asignarPermisosMalla($db, $perfiles_permisos, $mod_auditoria_id, $mod_helpdesk_id, $mod_dashboard_id, $mod_configuracion_id);

    // ─── 5. REGISTRO DE AUDITORÍA ───────────────────────────────────────────
    $stmt_audit = $db->prepare("INSERT INTO auditoria_accesos 
        (usuario_id, tipo_evento, modulo_accedido, funcion_ejecutada, descripcion_evento, direccion_ip, navegador_user_agent, resultado, tabla_afectada, operacion_realizada) 
        VALUES (1, 'configuracion', 'configuracion', 'MIGRACION', 'Ejecución exitosa de migración de tablas y semillas de seguridad y soporte.', '127.0.0.1', 'PHP CLI Migrator', 'exitoso', 'modulos', 'insert')");
    $stmt_audit->execute();
    $stmt_audit->close();

    echo "=== Migración completada con éxito ===\n";

} catch (Exception $e) {
    echo "[ERROR CRÍTICO] " . $e->getMessage() . "\n";
    exit(1);
}

// ─── FUNCIONES AUXILIARES DE SEMBRADO ────────────────────────────────────────

function registrarModulo($db, $nombre, $descripcion, $icono, $ruta, $orden) {
    $stmt = $db->prepare("SELECT id FROM modulos WHERE nombre_modulo = ?");
    $stmt->bind_param('s', $nombre);
    $stmt->execute();
    $res = $stmt->get_result();
    $stmt->close();

    if ($res->num_rows == 0) {
        $stmt_ins = $db->prepare("INSERT INTO modulos (nombre_modulo, descripcion, icono, nombre_ruta, orden_menu) VALUES (?, ?, ?, ?, ?)");
        $stmt_ins->bind_param('ssssi', $nombre, $descripcion, $icono, $ruta, $orden);
        if ($stmt_ins->execute()) {
            $id = $stmt_ins->insert_id;
            echo "[OK] Módulo '$nombre' creado con ID: $id\n";
            $stmt_ins->close();
            return $id;
        } else {
            throw new Exception("Error al crear módulo $nombre: " . $db->error);
        }
    } else {
        $row = $res->fetch_assoc();
        $id = $row['id'];
        echo "[INFO] Módulo '$nombre' ya existe con ID: $id. Actualizando configuración.\n";
        
        $stmt_upd = $db->prepare("UPDATE modulos SET descripcion = ?, icono = ?, nombre_ruta = ?, orden_menu = ? WHERE id = ?");
        $stmt_upd->bind_param('sssii', $descripcion, $icono, $ruta, $orden, $id);
        $stmt_upd->execute();
        $stmt_upd->close();
        return $id;
    }
}

function registrarFuncion($db, $modulo_id, $nombre, $codigo, $descripcion, $tipo) {
    $stmt = $db->prepare("SELECT id FROM funciones_modulo WHERE modulo_id = ? AND codigo_funcion = ?");
    $stmt->bind_param('is', $modulo_id, $codigo);
    $stmt->execute();
    $res = $stmt->get_result();
    $stmt->close();

    if ($res->num_rows == 0) {
        $stmt_ins = $db->prepare("INSERT INTO funciones_modulo (modulo_id, nombre_funcion, codigo_funcion, descripcion, tipo_permiso, estado) VALUES (?, ?, ?, ?, ?, 'activo')");
        $stmt_ins->bind_param('issss', $modulo_id, $nombre, $codigo, $descripcion, $tipo);
        if ($stmt_ins->execute()) {
            $id = $stmt_ins->insert_id;
            echo "[OK] Función '$codigo' creada con ID: $id\n";
            $stmt_ins->close();
            return $id;
        } else {
            throw new Exception("Error al crear función $codigo: " . $db->error);
        }
    } else {
        $row = $res->fetch_assoc();
        $id = $row['id'];
        echo "[INFO] Función '$codigo' ya existe con ID: $id.\n";
        return $id;
    }
}

function asignarPermisosMalla($db, $perfiles_permisos, $mod_aud, $mod_help, $mod_dash, $mod_conf) {
    // Primero, obtener todos los perfiles que realmente existan en la BD
    $res_perfiles = $db->query("SELECT id, nombre_perfil FROM perfiles");
    $perfiles_reales = [];
    while ($p = $res_perfiles->fetch_assoc()) {
        $perfiles_reales[(int)$p['id']] = $p['nombre_perfil'];
    }

    $inserciones = 0;
    foreach ($perfiles_permisos as $perfil_id => $funciones) {
        if (!isset($perfiles_reales[$perfil_id])) {
            continue; // Evitar perfiles inexistentes
        }

        foreach ($funciones as $funcion_id) {
            // Determinar qué modulo_id corresponde a esta función
            $stmt_f = $db->prepare("SELECT modulo_id FROM funciones_modulo WHERE id = ?");
            $stmt_f->bind_param('i', $funcion_id);
            $stmt_f->execute();
            $mod_id = $stmt_f->get_result()->fetch_assoc()['modulo_id'] ?? null;
            $stmt_f->close();

            if (!$mod_id) continue;

            // Verificar si ya tiene el permiso
            $stmt_check = $db->prepare("SELECT id FROM permisos_perfil WHERE perfil_id = ? AND funcion_id = ?");
            $stmt_check->bind_param('ii', $perfil_id, $funcion_id);
            $stmt_check->execute();
            $has_perm = $stmt_check->get_result()->num_rows > 0;
            $stmt_check->close();

            if (!$has_perm) {
                // Insertar con acceso
                $stmt_ins = $db->prepare("INSERT INTO permisos_perfil 
                    (perfil_id, funcion_id, modulo_id, puede_ejecutar, ver_datos, crear_datos, editar_datos, eliminar_datos, ver_reportes, exportar_datos, solo_propios, creado_por) 
                    VALUES (?, ?, ?, 1, 1, 1, 1, 0, 1, 1, 0, 1)");
                $stmt_ins->bind_param('iii', $perfil_id, $funcion_id, $mod_id);
                $stmt_ins->execute();
                $stmt_ins->close();
                $inserciones++;
            } else {
                // Asegurar que esté activo
                $stmt_upd = $db->prepare("UPDATE permisos_perfil SET puede_ejecutar = 1, ver_datos = 1, modulo_id = ? WHERE perfil_id = ? AND funcion_id = ?");
                $stmt_upd->bind_param('iii', $mod_id, $perfil_id, $funcion_id);
                $stmt_upd->execute();
                $stmt_upd->close();
            }
        }
    }
    echo "[OK] Asignación de permisos granulares por defecto actualizada ($inserciones nuevos permisos creados).\n";
}
?>
