<?php
require_once 'c:/wamp64/www/PLATAFORMA_INTEGRADA/backend/config.php';
$db = Database::getInstance()->getConnection();

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "DEBUGGING MIGRATION PART 3 & 4\n";

$check_func = $db->query("SELECT id FROM funciones_modulo WHERE codigo_funcion = 'PAG_PGC_ACCEDER'");
if (!$check_func) {
    die("Error SELECT: " . $db->error . "\n");
}

$funcion_id = null;
if ($check_func->num_rows == 0) {
    // Let's inspect default columns
    $sql_func = "INSERT INTO funciones_modulo (modulo_id, nombre_funcion, codigo_funcion, descripcion, tipo_permiso, estado) 
                 VALUES (5, 'Acceder al PGC', 'PAG_PGC_ACCEDER', 'Permite acceder al Portal de Gestión de Cobros y ver la prorrata interna', 'ejecutar', 'activo')";
    if ($db->query($sql_func)) {
        $funcion_id = $db->insert_id;
        echo "¡Función PAG_PGC_ACCEDER registrada con éxito (ID: $funcion_id)!\n";
    } else {
        echo "Error INSERT funciones_modulo: " . $db->error . "\n";
    }
} else {
    $funcion_id = $check_func->fetch_assoc()['id'];
    echo "La función PAG_PGC_ACCEDER ya existe (ID: $funcion_id).\n";
}

if ($funcion_id) {
    $perfiles_superiores = [1, 2, 3, 4];
    foreach ($perfiles_superiores as $perfil_id) {
        $check_perm = $db->prepare("SELECT id FROM permisos_perfil WHERE perfil_id = ? AND funcion_id = ?");
        if (!$check_perm) {
            die("Error preparing SELECT permisos_perfil: " . $db->error . "\n");
        }
        $check_perm->bind_param("ii", $perfil_id, $funcion_id);
        $check_perm->execute();
        $res_perm = $check_perm->get_result();
        $check_perm->close();

        if ($res_perm->num_rows == 0) {
            // Let's check columns of permisos_perfil
            echo "Permiso no existe para perfil $perfil_id, intentando insertar...\n";
            $sql_cols = $db->query("SHOW COLUMNS FROM permisos_perfil");
            $cols = [];
            while ($c = $sql_cols->fetch_assoc()) {
                $cols[] = $c['Field'];
            }
            echo "Columnas de permisos_perfil: " . implode(', ', $cols) . "\n";
            
            $sql_perm = "INSERT INTO permisos_perfil (perfil_id, modulo_id, funcion_id, puede_ejecutar, ver_datos, crear_datos, editar_datos, ver_reportes, exportar_datos) 
                         VALUES (?, 5, ?, 1, 1, 1, 1, 1, 1)";
            $stmt_p = $db->prepare($sql_perm);
            if (!$stmt_p) {
                echo "Error preparing INSERT: " . $db->error . "\n";
                // Let's try inserting only existing columns
                $insert_cols = array_intersect(['perfil_id', 'modulo_id', 'funcion_id', 'puede_ejecutar', 'ver_datos', 'crear_datos', 'editar_datos', 'eliminar_datos', 'ver_reportes', 'exportar_datos', 'solo_propios', 'creado_por'], $cols);
                $placeholders = array_fill(0, count($insert_cols), '?');
                $sql_fallback = "INSERT INTO permisos_perfil (" . implode(', ', $insert_cols) . ") VALUES (" . implode(', ', $placeholders) . ")";
                echo "Fallback SQL: $sql_fallback\n";
                $stmt_fallback = $db->prepare($sql_fallback);
                if (!$stmt_fallback) {
                    die("Fallback prepare error: " . $db->error . "\n");
                }
                
                // Build bind params dynamically
                $types = '';
                $vals = [];
                foreach ($insert_cols as $c) {
                    if ($c === 'perfil_id') { $types .= 'i'; $vals[] = $perfil_id; }
                    elseif ($c === 'modulo_id') { $types .= 'i'; $vals[] = 5; }
                    elseif ($c === 'funcion_id') { $types .= 'i'; $vals[] = $funcion_id; }
                    elseif ($c === 'puede_ejecutar') { $types .= 'i'; $vals[] = 1; }
                    elseif ($c === 'ver_datos') { $types .= 'i'; $vals[] = 1; }
                    elseif ($c === 'crear_datos') { $types .= 'i'; $vals[] = 1; }
                    elseif ($c === 'editar_datos') { $types .= 'i'; $vals[] = 1; }
                    elseif ($c === 'eliminar_datos') { $types .= 'i'; $vals[] = 1; }
                    elseif ($c === 'ver_reportes') { $types .= 'i'; $vals[] = 1; }
                    elseif ($c === 'exportar_datos') { $types .= 'i'; $vals[] = 1; }
                    elseif ($c === 'solo_propios') { $types .= 'i'; $vals[] = 0; }
                    elseif ($c === 'creado_por') { $types .= 'i'; $vals[] = 1; }
                }
                
                $stmt_fallback->bind_param($types, ...$vals);
                if ($stmt_fallback->execute()) {
                    echo "¡Permiso asignado con éxito vía fallback al perfil $perfil_id!\n";
                } else {
                    echo "Error en ejecución del fallback: " . $stmt_fallback->error . "\n";
                }
                $stmt_fallback->close();
            } else {
                $stmt_p->bind_param("ii", $perfil_id, $funcion_id);
                if ($stmt_p->execute()) {
                    echo "¡Permiso asignado con éxito al perfil $perfil_id!\n";
                } else {
                    echo "Error executing INSERT: " . $stmt_p->error . "\n";
                }
                $stmt_p->close();
            }
        } else {
            echo "El Perfil ID $perfil_id ya tiene asignado el permiso PAG_PGC_ACCEDER.\n";
        }
    }
}
?>
