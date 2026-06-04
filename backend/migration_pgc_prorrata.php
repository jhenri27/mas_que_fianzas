<?php
require_once dirname(__FILE__) . '/config.php';
$db = Database::getInstance()->getConnection();

echo "Iniciando migración para PGC y Prorrata...\n";

// 1. Crear tabla cf_gestiones_cobro
$sql_table = "CREATE TABLE IF NOT EXISTS cf_gestiones_cobro (
    id INT AUTO_INCREMENT PRIMARY KEY,
    poliza_id INT NOT NULL,
    usuario_id INT NOT NULL,
    tipo_gestion ENUM('llamada', 'correo', 'whatsapp', 'visita', 'promesa_pago', 'bot_notificacion') NOT NULL,
    fecha_gestion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    descripcion TEXT NOT NULL,
    fecha_promesa DATE NULL,
    monto_prometido DECIMAL(15,2) NULL,
    estado_promesa ENUM('pendiente', 'cumplida', 'incumplida', 'n/a') DEFAULT 'n/a',
    FOREIGN KEY (poliza_id) REFERENCES polizas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($db->query($sql_table)) {
    echo "¡Tabla cf_gestiones_cobro validada/creada con éxito!\n";
} else {
    echo "Error creando tabla cf_gestiones_cobro: " . $db->error . "\n";
}

// 2. Agregar columna bot_excluir a polizas si no existe
$check_bot_excluir = $db->query("SHOW COLUMNS FROM polizas LIKE 'bot_excluir'");
if ($check_bot_excluir->num_rows == 0) {
    $sql_alter_poliza = "ALTER TABLE polizas ADD COLUMN bot_excluir TINYINT(1) DEFAULT 0 AFTER validada";
    if ($db->query($sql_alter_poliza)) {
        echo "¡Columna bot_excluir agregada a polizas!\n";
    } else {
        echo "Error agregando bot_excluir a polizas: " . $db->error . "\n";
    }
} else {
    echo "La columna bot_excluir ya existe en polizas.\n";
}

// 3. Registrar la función PAG_PGC_ACCEDER en funciones_modulo
$check_func = $db->query("SELECT id FROM funciones_modulo WHERE codigo_funcion = 'PAG_PGC_ACCEDER'");
$funcion_id = null;
if ($check_func->num_rows == 0) {
    // Usamos 'consultar' como tipo_permiso que es válido en el enum
    $sql_func = "INSERT INTO funciones_modulo (modulo_id, nombre_funcion, codigo_funcion, descripcion, tipo_permiso, estado) 
                 VALUES (5, 'Acceder al PGC', 'PAG_PGC_ACCEDER', 'Permite acceder al Portal de Gestión de Cobros y ver la prorrata interna', 'consultar', 'activo')";
    if ($db->query($sql_func)) {
        $funcion_id = $db->insert_id;
        echo "¡Función PAG_PGC_ACCEDER registrada con éxito (ID: $funcion_id)!\n";
    } else {
        echo "Error registrando función: " . $db->error . "\n";
    }
} else {
    $funcion_id = $check_func->fetch_assoc()['id'];
    echo "La función PAG_PGC_ACCEDER ya existe (ID: $funcion_id).\n";
}

// 4. Asignar permisos a perfiles superiores (Administrador = 1, Gerentes = 2, 3, 4)
if ($funcion_id) {
    $perfiles_superiores = [1, 2, 3, 4];
    
    // Obtener columnas de permisos_perfil para adaptarnos a su esquema
    $sql_cols = $db->query("SHOW COLUMNS FROM permisos_perfil");
    $cols = [];
    while ($c = $sql_cols->fetch_assoc()) {
        $cols[] = $c['Field'];
    }
    
    foreach ($perfiles_superiores as $perfil_id) {
        $check_perm = $db->prepare("SELECT id FROM permisos_perfil WHERE perfil_id = ? AND funcion_id = ?");
        $check_perm->bind_param("ii", $perfil_id, $funcion_id);
        $check_perm->execute();
        $res_perm = $check_perm->get_result();
        $check_perm->close();

        if ($res_perm->num_rows == 0) {
            // Intentar insertar de forma adaptativa según las columnas que existan en permisos_perfil
            $insert_cols = array_intersect(['perfil_id', 'modulo_id', 'funcion_id', 'puede_ejecutar', 'ver_datos', 'crear_datos', 'editar_datos', 'eliminar_datos', 'ver_reportes', 'exportar_datos', 'solo_propios', 'creado_por'], $cols);
            $placeholders = array_fill(0, count($insert_cols), '?');
            $sql_perm = "INSERT INTO permisos_perfil (" . implode(', ', $insert_cols) . ") VALUES (" . implode(', ', $placeholders) . ")";
            
            $stmt_p = $db->prepare($sql_perm);
            if (!$stmt_p) {
                echo "Error al preparar inserción adaptativa: " . $db->error . "\n";
                continue;
            }
            
            // Vincular parámetros dinámicamente
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
            
            $stmt_p->bind_param($types, ...$vals);
            if ($stmt_p->execute()) {
                echo "¡Permiso PAG_PGC_ACCEDER asignado al Perfil ID $perfil_id!\n";
            } else {
                echo "Error asignando permiso al Perfil ID $perfil_id: " . $stmt_p->error . "\n";
            }
            $stmt_p->close();
        } else {
            echo "El Perfil ID $perfil_id ya tiene asignado el permiso PAG_PGC_ACCEDER.\n";
        }
    }
}

echo "Migración completada.\n";
?>
