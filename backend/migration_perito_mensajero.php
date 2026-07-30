<?php
/**
 * Migración NOFTRAB 4.0: Registro e Integración del Perfil Perito-Mensajero
 * Absorbe la App Jotform "MENSAJERÍA +QF" (261593518544868)
 * Crea el perfil, le asigna nivel jerárquico 6 y siembra sus permisos de módulo.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';

try {
    $db = Database::getInstance()->getConnection();
    echo "=========================================================\n";
    echo "=== [NOFTRAB] Migración: Perfil Perito-Mensajero (DEV) ===\n";
    echo "=========================================================\n\n";

    // 1. Verificar/Crear el Perfil Perito-Mensajero
    $nombre_perfil = 'Perito-Mensajero';
    $siglas = 'MEN';
    $descripcion = 'Perito-Mensajero: inspecciones de riesgo en campo, capturas de firma digital y entregas de pólizas en ruta (Absorbe App MENSAJERÍA +QF 261593518544868)';
    $nivel_jerarquico = 6;
    $estado = 'activo';

    $stmt_check = $db->prepare("SELECT id FROM perfiles WHERE nombre_perfil = ?");
    $stmt_check->bind_param('s', $nombre_perfil);
    $stmt_check->execute();
    $res_check = $stmt_check->get_result();
    $stmt_check->close();

    $perfil_id = null;

    if ($res_check->num_rows == 0) {
        $stmt_ins = $db->prepare("INSERT INTO perfiles (nombre_perfil, siglas, descripcion, nivel_jerarquico, estado, es_predeterminado, creado_por) VALUES (?, ?, ?, ?, ?, 0, 1)");
        $stmt_ins->bind_param('sssis', $nombre_perfil, $siglas, $descripcion, $nivel_jerarquico, $estado);
        if ($stmt_ins->execute()) {
            $perfil_id = $stmt_ins->insert_id;
            echo "[OK] Perfil '$nombre_perfil' creado exitosamente con ID: $perfil_id\n";
        } else {
            throw new Exception("Error al insertar perfil: " . $db->error);
        }
        $stmt_ins->close();
    } else {
        $row_p = $res_check->fetch_assoc();
        $perfil_id = $row_p['id'];
        echo "[INFO] El perfil '$nombre_perfil' ya existía con ID: $perfil_id\n";

        // Actualizar descripción y nivel por seguridad
        $stmt_upd = $db->prepare("UPDATE perfiles SET siglas = ?, descripcion = ?, nivel_jerarquico = ?, estado = 'activo' WHERE id = ?");
        $stmt_upd->bind_param('ssii', $siglas, $descripcion, $nivel_jerarquico, $perfil_id);
        $stmt_upd->execute();
        $stmt_upd->close();
    }

    // 2. Asignar Permisos del Sistema
    $modulos_permitidos = ['dashboard', 'clientes', 'polizas', 'siniestros', 'perfil_data', 'notificaciones', 'helpdesk'];
    $permisos_creados = 0;

    foreach ($modulos_permitidos as $mod) {
        $stmt_mod = $db->prepare("SELECT id FROM modulos WHERE nombre_modulo = ?");
        $stmt_mod->bind_param('s', $mod);
        $stmt_mod->execute();
        $res_mod = $stmt_mod->get_result();
        $stmt_mod->close();

        if ($res_mod->num_rows > 0) {
            $mod_data = $res_mod->fetch_assoc();
            $modulo_id = $mod_data['id'];

            // Buscar funciones del módulo
            $stmt_fn = $db->prepare("SELECT id FROM funciones WHERE modulo_id = ?");
            $stmt_fn->bind_param('i', $modulo_id);
            $stmt_fn->execute();
            $res_fn = $stmt_fn->get_result();
            $stmt_fn->close();

            $funciones_ids = [];
            while ($f_row = $res_fn->fetch_assoc()) {
                $funciones_ids[] = $f_row['id'];
            }

            if (empty($funciones_ids)) {
                $funciones_ids = [0];
            }

            foreach ($funciones_ids as $fn_id) {
                $stmt_p = $db->prepare("INSERT INTO permisos_perfil (perfil_id, modulo_id, funcion_id, puede_ejecutar, ver_datos, crear_datos, editar_datos, eliminar_datos) VALUES (?, ?, ?, 1, 1, 1, 1, 0) ON DUPLICATE KEY UPDATE puede_ejecutar = 1, ver_datos = 1, crear_datos = 1, editar_datos = 1");
                $stmt_p->bind_param('iii', $perfil_id, $modulo_id, $fn_id);
                $stmt_p->execute();
                $stmt_p->close();
                $permisos_creados++;
            }
        }
    }

    echo "[OK] Permisos asignados/actualizados para el perfil con $permisos_creados registros en 'permisos_perfil'.\n";
    echo "\n=== MIGRACIÓN COMPLETADA EXITOSAMENTE DE ACUERDO A NORMA NOFTRAB 4.0 ===\n";

} catch (Exception $e) {
    echo "[ERROR] Error durante la migración: " . $e->getMessage() . "\n";
    exit(1);
}
