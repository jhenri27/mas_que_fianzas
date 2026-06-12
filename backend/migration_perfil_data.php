<?php
/**
 * Migración: Registro del módulo PERFIL DATA (Mis Accesos)
 * Asegura la existencia del módulo, su función y habilita de forma automática
 * la visualización y ejecución en todos los perfiles de usuario.
 * Cumple con normas NOFTRAB para sembrado idempotente y auditable.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';

try {
    $db = Database::getInstance()->getConnection();
    echo "=== Iniciando migración para el módulo PERFIL DATA ===\n";

    // 1. Insertar el módulo 'perfil_data' si no existe
    $nombre_modulo = 'perfil_data';
    $descripcion_modulo = 'Dashboard de permisos y condiciones de perfil (Mis Accesos)';
    $icono_modulo = '👤';
    $ruta_modulo = '/modulos/perfil_data.html';
    $orden_modulo = 15;

    $stmt = $db->prepare("SELECT id FROM modulos WHERE nombre_modulo = ?");
    $stmt->bind_param('s', $nombre_modulo);
    $stmt->execute();
    $res_mod = $stmt->get_result();
    $stmt->close();

    if ($res_mod->num_rows == 0) {
        $stmt_ins = $db->prepare("INSERT INTO modulos (nombre_modulo, descripcion, icono, nombre_ruta, orden_menu) VALUES (?, ?, ?, ?, ?)");
        $stmt_ins->bind_param('ssssi', $nombre_modulo, $descripcion_modulo, $icono_modulo, $ruta_modulo, $orden_modulo);
        if ($stmt_ins->execute()) {
            $modulo_id = $stmt_ins->insert_id;
            echo "[OK] Módulo 'perfil_data' creado con ID: $modulo_id\n";
        } else {
            throw new Exception("Error al crear módulo: " . $db->error);
        }
        $stmt_ins->close();
    } else {
        $row_mod = $res_mod->fetch_assoc();
        $modulo_id = $row_mod['id'];
        echo "[INFO] El módulo 'perfil_data' ya existe con ID: $modulo_id\n";
        
        // Actualizar icono/ruta por si acaso
        $stmt_upd = $db->prepare("UPDATE modulos SET icono = ?, nombre_ruta = ?, orden_menu = ? WHERE id = ?");
        $stmt_upd->bind_param('ssii', $icono_modulo, $ruta_modulo, $orden_modulo, $modulo_id);
        $stmt_upd->execute();
        $stmt_upd->close();
    }

    // 2. Insertar la función 'PERFIL_DATA_VER' si no existe
    $codigo_funcion = 'PERFIL_DATA_VER';
    $nombre_funcion = 'Ver Perfil Data';
    $desc_funcion = 'Permite consultar el dashboard informativo de permisos y políticas del perfil propio';
    $tipo_permiso = 'consultar';
    $estado_funcion = 'activo';

    $stmt = $db->prepare("SELECT id FROM funciones_modulo WHERE modulo_id = ? AND codigo_funcion = ?");
    $stmt->bind_param('is', $modulo_id, $codigo_funcion);
    $stmt->execute();
    $res_fun = $stmt->get_result();
    $stmt->close();

    if ($res_fun->num_rows == 0) {
        $stmt_ins = $db->prepare("INSERT INTO funciones_modulo (modulo_id, nombre_funcion, codigo_funcion, descripcion, tipo_permiso, estado) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt_ins->bind_param('isssss', $modulo_id, $nombre_funcion, $codigo_funcion, $desc_funcion, $tipo_permiso, $estado_funcion);
        if ($stmt_ins->execute()) {
            $funcion_id = $stmt_ins->insert_id;
            echo "[OK] Función 'PERFIL_DATA_VER' creada con ID: $funcion_id\n";
        } else {
            throw new Exception("Error al crear la función: " . $db->error);
        }
        $stmt_ins->close();
    } else {
        $row_fun = $res_fun->fetch_assoc();
        $funcion_id = $row_fun['id'];
        echo "[INFO] La función 'PERFIL_DATA_VER' ya existe con ID: $funcion_id\n";
    }

    // 3. Habilitar la función para todos los perfiles de usuario
    $res_perfiles = $db->query("SELECT id, nombre_perfil FROM perfiles");
    $habilitados = 0;
    while ($perfil = $res_perfiles->fetch_assoc()) {
        $perfil_id = $perfil['id'];
        
        // Verificar si ya tiene el permiso registrado
        $stmt_perm = $db->prepare("SELECT id FROM permisos_perfil WHERE perfil_id = ? AND funcion_id = ?");
        $stmt_perm->bind_param('ii', $perfil_id, $funcion_id);
        $stmt_perm->execute();
        $res_perm = $stmt_perm->get_result();
        $stmt_perm->close();

        if ($res_perm->num_rows == 0) {
            // Insertar permiso con acceso concedido (puede_ejecutar = 1, ver_datos = 1)
            $stmt_ins = $db->prepare("INSERT INTO permisos_perfil (perfil_id, funcion_id, modulo_id, puede_ejecutar, ver_datos, creado_por) VALUES (?, ?, ?, 1, 1, 1)");
            $stmt_ins->bind_param('iii', $perfil_id, $funcion_id, $modulo_id);
            if ($stmt_ins->execute()) {
                $habilitados++;
            } else {
                echo "[ERROR] No se pudo habilitar para el perfil '{$perfil['nombre_perfil']}': " . $db->error . "\n";
            }
            $stmt_ins->close();
        } else {
            // Asegurarse de que esté activo y con puede_ejecutar = 1
            $stmt_upd = $db->prepare("UPDATE permisos_perfil SET puede_ejecutar = 1, ver_datos = 1 WHERE perfil_id = ? AND funcion_id = ?");
            $stmt_upd->bind_param('ii', $perfil_id, $funcion_id);
            $stmt_upd->execute();
            $stmt_upd->close();
        }
    }
    echo "[OK] Módulo preactivado con éxito para $habilitados perfiles.\n";

    // 4. Registrar auditoría de la migración (NOFTRAB)
    $stmt_audit = $db->prepare("INSERT INTO auditoria_accesos (usuario_id, tipo_evento, modulo_accedido, funcion_ejecutada, descripcion_evento, direccion_ip, navegador_user_agent, resultado, tabla_afectada, registro_afectado_id, operacion_realizada) VALUES (1, 'configuracion', 'configuracion', 'MIGRACION', 'Ejecución exitosa de migración para sembrar el módulo PERFIL DATA y activar permisos globales.', '127.0.0.1', 'PHP CLI Migrator', 'exitoso', 'modulos', ?, 'insert')");
    $stmt_audit->bind_param('i', $modulo_id);
    $stmt_audit->execute();
    $stmt_audit->close();

    echo "=== Migración completada con éxito ===\n";

} catch (Exception $e) {
    echo "[ERROR CRÍTICO] " . $e->getMessage() . "\n";
}
?>
