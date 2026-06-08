<?php
/**
 * Script de Migración: Motor de ETL de Tarifas
 * MAS QUE FIANZAS - Sistema Integrado
 */

require_once __DIR__ . '/../config.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Iniciar transacción
    $db->begin_transaction();
    
    echo "Iniciando migración para el Motor de ETL de Tarifas...\n";
    
    // 1. Alterar tabla tarifas_seguro para agregar columnas (idempotente)
    // Comprobar si existe compania_id
    $res = $db->query("SHOW COLUMNS FROM tarifas_seguro LIKE 'compania_id'");
    if ($res && $res->num_rows === 0) {
        $db->query("ALTER TABLE tarifas_seguro ADD COLUMN compania_id INT DEFAULT NULL AFTER id");
        $db->query("ALTER TABLE tarifas_seguro ADD CONSTRAINT fk_tarifas_compania FOREIGN KEY (compania_id) REFERENCES companias_registradas(id) ON DELETE CASCADE");
        echo "✓ Columna 'compania_id' y FK agregadas a tarifas_seguro.\n";
    } else {
        echo "- La columna 'compania_id' ya existe en tarifas_seguro.\n";
    }
    
    // Comprobar si existe cobertura
    $res = $db->query("SHOW COLUMNS FROM tarifas_seguro LIKE 'cobertura'");
    if ($res && $res->num_rows === 0) {
        $db->query("ALTER TABLE tarifas_seguro ADD COLUMN cobertura VARCHAR(100) DEFAULT NULL AFTER uso");
        echo "✓ Columna 'cobertura' agregada a tarifas_seguro.\n";
    } else {
        echo "- La columna 'cobertura' ya existe en tarifas_seguro.\n";
    }
    
    // 2. Crear tabla etl_mapeos
    $sql_etl_mapeos = "CREATE TABLE IF NOT EXISTS etl_mapeos (
        id INT PRIMARY KEY AUTO_INCREMENT,
        compania_id INT NOT NULL,
        columna_origen VARCHAR(100) NOT NULL,
        columna_destino VARCHAR(50) NOT NULL,
        valor_default VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (compania_id) REFERENCES companias_registradas(id) ON DELETE CASCADE,
        UNIQUE KEY uq_compania_destino (compania_id, columna_destino)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($db->query($sql_etl_mapeos)) {
        echo "✓ Tabla 'etl_mapeos' creada con éxito.\n";
    } else {
        throw new Exception("Error al crear tabla etl_mapeos: " . $db->error);
    }
    
    // 3. Obtener el ID del módulo 'configuracion' (por nombre_modulo)
    $res_mod = $db->query("SELECT id FROM modulos WHERE nombre_modulo = 'configuracion' LIMIT 1");
    $mod = $res_mod->fetch_assoc();
    if (!$mod) {
        throw new Exception("Módulo 'configuracion' no encontrado en la base de datos.");
    }
    $modulo_id = (int)$mod['id'];
    
    // 4. Agregar funciones en funciones_modulo (idempotente)
    $funciones = [
        [
            'nombre' => 'Ver Módulo ETL',
            'codigo' => 'CONF_ETL_VER',
            'desc' => 'Permite visualizar la pestaña del ETL de Tarifas en configuración.',
            'tipo' => 'consultar'
        ],
        [
            'nombre' => 'Ejecutar Procesamiento ETL',
            'codigo' => 'CONF_ETL_EJECUTAR',
            'desc' => 'Permite procesar cargas de tarifarios externos y alterar las tarifas de las aseguradoras.',
            'tipo' => 'editar'
        ]
    ];
    
    foreach ($funciones as $fun) {
        $stmt_check = $db->prepare("SELECT id FROM funciones_modulo WHERE codigo_funcion = ? LIMIT 1");
        $stmt_check->bind_param("s", $fun['codigo']);
        $stmt_check->execute();
        $res_fun = $stmt_check->get_result();
        $exists = $res_fun->fetch_assoc();
        $stmt_check->close();
        
        if (!$exists) {
            $stmt_ins = $db->prepare("INSERT INTO funciones_modulo (modulo_id, nombre_funcion, codigo_funcion, descripcion, tipo_permiso, estado) VALUES (?, ?, ?, ?, ?, 'activo')");
            $stmt_ins->bind_param("issss", $modulo_id, $fun['nombre'], $fun['codigo'], $fun['desc'], $fun['tipo']);
            if ($stmt_ins->execute()) {
                echo "✓ Función registrada: {$fun['codigo']}\n";
            } else {
                throw new Exception("Error al registrar función {$fun['codigo']}: " . $db->error);
            }
            $stmt_ins->close();
        } else {
            echo "- La función {$fun['codigo']} ya existe.\n";
        }
    }
    
    // 5. Asignar permisos al Administrador (ID 1) y Gerente Técnico (ID 2)
    // Obtener IDs de las funciones registradas
    $fun_ids = [];
    foreach (['CONF_ETL_VER', 'CONF_ETL_EJECUTAR'] as $cod) {
        $res = $db->query("SELECT id FROM funciones_modulo WHERE codigo_funcion = '$cod' LIMIT 1");
        $row = $res->fetch_assoc();
        if ($row) $fun_ids[$cod] = (int)$row['id'];
    }
    
    $perfiles_grant = [1, 2]; // Administrador, Gerente Técnico
    
    foreach ($perfiles_grant as $perfil_id) {
        foreach ($fun_ids as $cod => $fid) {
            // Verificar si el permiso ya existe
            $stmt_chk = $db->prepare("SELECT id FROM permisos_perfil WHERE perfil_id = ? AND funcion_id = ? LIMIT 1");
            $stmt_chk->bind_param("ii", $perfil_id, $fid);
            $stmt_chk->execute();
            $perm_exists = $stmt_chk->get_result()->fetch_assoc();
            $stmt_chk->close();
            
            if (!$perm_exists) {
                // En el perfil administrador/gerente técnico damos todos los booleanos a 1 excepto solo_propios que es 0
                $puede_ejecutar = 1;
                $ver_datos = 1;
                $crear_datos = ($cod === 'CONF_ETL_EJECUTAR') ? 1 : 0;
                $editar_datos = ($cod === 'CONF_ETL_EJECUTAR') ? 1 : 0;
                $eliminar_datos = ($cod === 'CONF_ETL_EJECUTAR') ? 1 : 0;
                $solo_propios = 0;
                
                $stmt_ins = $db->prepare("INSERT INTO permisos_perfil (perfil_id, funcion_id, modulo_id, puede_ejecutar, ver_datos, crear_datos, editar_datos, eliminar_datos, solo_propios, creado_por) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
                $stmt_ins->bind_param("iiiiiiiii", $perfil_id, $fid, $modulo_id, $puede_ejecutar, $ver_datos, $crear_datos, $editar_datos, $eliminar_datos, $solo_propios);
                if ($stmt_ins->execute()) {
                    echo "✓ Permiso {$cod} asignado al perfil {$perfil_id}\n";
                } else {
                    throw new Exception("Error al asignar permiso {$cod} al perfil {$perfil_id}: " . $db->error);
                }
                $stmt_ins->close();
            } else {
                echo "- El perfil {$perfil_id} ya posee el permiso {$cod}.\n";
            }
        }
    }
    
    // 6. Registrar log en auditoria_accesos
    $ip = '127.0.0.1';
    $user_agent = 'Migration Script';
    $tipo_evento = 'login'; // O un tipo genérico
    $desc = "Ejecutada migración de la base de datos para habilitar el Motor de ETL de Tarifas.";
    
    $stmt_audit = $db->prepare("INSERT INTO auditoria_accesos (usuario_id, tipo_evento, modulo_accedido, funcion_ejecutada, descripcion_evento, direccion_ip, navegador_user_agent, resultado, operacion_realizada) VALUES (1, 'intento_no_autorizado', 'configuracion', 'MIGRACION_ETL', ?, ?, ?, 'exitoso', 'update')");
    $stmt_audit->bind_param("sss", $desc, $ip, $user_agent);
    $stmt_audit->execute();
    $stmt_audit->close();
    
    $db->commit();
    echo "✓ Migración completada exitosamente.\n";
    
} catch (Exception $e) {
    if (isset($db)) $db->rollback();
    echo "✗ ERROR en la migración: " . $e->getMessage() . "\n";
    exit(1);
}
?>
