<?php
/**
 * Seeder de Permisos para Centro de Negocios y Reglas de Negocio
 * MAS QUE FIANZAS - Core Asegurador v3.0
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once dirname(__DIR__) . '/config.php';

try {
    $db = Database::getInstance()->getConnection();

    echo "=== INICIANDO SEEDER DE PERMISOS ===\n";

    // Definir funciones a insertar
    $funciones = [
        [
            'codigo_funcion' => 'VER_ESTADISTICAS',
            'nombre_funcion' => 'Visualizar estadísticas de venta',
            'modulo_id' => 21,
            'descripcion' => 'Permite ver el dashboard y logros del Centro de Negocios.',
            'tipo_permiso' => 'consultar'
        ],
        [
            'codigo_funcion' => 'CONF_BONOS',
            'nombre_funcion' => 'Configurar bonos de venta',
            'modulo_id' => 21,
            'descripcion' => 'Permite crear, editar y pagar bonos configurados.',
            'tipo_permiso' => 'completo'
        ],
        [
            'codigo_funcion' => 'GENERAR_ENLACES',
            'nombre_funcion' => 'Generar enlaces de venta online',
            'modulo_id' => 21,
            'descripcion' => 'Permite crear enlaces de venta serverless.',
            'tipo_permiso' => 'registrar'
        ],
        [
            'codigo_funcion' => 'GESTIONAR_REGLAS',
            'nombre_funcion' => 'Configurar reglas de negocio',
            'modulo_id' => 20,
            'descripcion' => 'Permite configurar reglas de negocio de suscripción.',
            'tipo_permiso' => 'completo'
        ],
        [
            'codigo_funcion' => 'APROBAR_REGLAS',
            'nombre_funcion' => 'Aprobar cambios en reglas',
            'modulo_id' => 20,
            'descripcion' => 'Permite autorizar o rechazar solicitudes de cambios en reglas.',
            'tipo_permiso' => 'validar'
        ]
    ];

    foreach ($funciones as $f) {
        echo "Procesando función: " . $f['codigo_funcion'] . "\n";
        
        // Verificar si ya existe
        $stmt_check = $db->prepare("SELECT id FROM funciones_modulo WHERE codigo_funcion = ?");
        if (!$stmt_check) {
            throw new Exception("Error preparando select: " . $db->error);
        }
        $stmt_check->bind_param("s", $f['codigo_funcion']);
        $stmt_check->execute();
        $exist = $stmt_check->get_result()->fetch_assoc();
        $stmt_check->close();

        if ($exist) {
            echo "La función '{$f['codigo_funcion']}' ya se encuentra registrada.\n";
            $funcion_id = (int)$exist['id'];
        } else {
            // Armar insert según columnas
            $sql_ins = "INSERT INTO funciones_modulo (modulo_id, codigo_funcion, nombre_funcion, descripcion, tipo_permiso, estado) VALUES (?, ?, ?, ?, ?, 'activo')";
            $stmt = $db->prepare($sql_ins);
            if (!$stmt) {
                throw new Exception("Error preparando insert: " . $db->error);
            }
            $stmt->bind_param("issss", $f['modulo_id'], $f['codigo_funcion'], $f['nombre_funcion'], $f['descripcion'], $f['tipo_permiso']);
            
            if ($stmt->execute()) {
                $funcion_id = $db->insert_id;
                echo "Función '{$f['codigo_funcion']}' creada exitosamente con ID $funcion_id.\n";
            } else {
                echo "Error al insertar función '{$f['codigo_funcion']}': " . $stmt->error . "\n";
                continue;
            }
            $stmt->close();
        }

        // 3. Asignar por defecto a los perfiles correspondientes
        $perfiles = [1, 2, 3, 5];
        
        foreach ($perfiles as $perfil_id) {
            // Verificar si existe el perfil en la base de datos
            $res_perf = $db->query("SELECT id FROM perfiles WHERE id = $perfil_id");
            if ($res_perf->num_rows === 0) continue;

            // Determinar permisos según el rol
            $puede_ejecutar = 0;
            if ($perfil_id === 1 || $perfil_id === 2) {
                $puede_ejecutar = 1;
            } else {
                // Agentes/Socios
                if (in_array($f['codigo_funcion'], ['VER_ESTADISTICAS', 'GENERAR_ENLACES'])) {
                    $puede_ejecutar = 1;
                }
            }

            // Verificar si ya tiene el permiso asignado
            $stmt_p_chk = $db->prepare("SELECT id FROM permisos_perfil WHERE perfil_id = ? AND funcion_id = ?");
            if (!$stmt_p_chk) {
                throw new Exception("Error preparando select permisos: " . $db->error);
            }
            $stmt_p_chk->bind_param("ii", $perfil_id, $funcion_id);
            $stmt_p_chk->execute();
            $p_exist = $stmt_p_chk->get_result()->fetch_assoc();
            $stmt_p_chk->close();

            if ($p_exist) {
                // Actualizar
                $stmt_up = $db->prepare("UPDATE permisos_perfil SET puede_ejecutar = ?, ver_datos = ?, crear_datos = ?, editar_datos = ?, eliminar_datos = ?, ver_reportes = ? WHERE id = ?");
                if (!$stmt_up) {
                    throw new Exception("Error preparando update permisos: " . $db->error);
                }
                $stmt_up->bind_param("iiiiiii", $puede_ejecutar, $puede_ejecutar, $puede_ejecutar, $puede_ejecutar, $puede_ejecutar, $puede_ejecutar, $p_exist['id']);
                $stmt_up->execute();
                $stmt_up->close();
                echo "Permiso de perfil $perfil_id para función '{$f['codigo_funcion']}' actualizado.\n";
            } else {
                // Insertar
                $sql_ins_p = "INSERT INTO permisos_perfil (perfil_id, funcion_id, modulo_id, puede_ejecutar, ver_datos, crear_datos, editar_datos, eliminar_datos, ver_reportes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt_ins_p = $db->prepare($sql_ins_p);
                if (!$stmt_ins_p) {
                    throw new Exception("Error preparando insert permisos: " . $db->error);
                }
                $stmt_ins_p->bind_param("iiiiiiiii", $perfil_id, $funcion_id, $f['modulo_id'], $puede_ejecutar, $puede_ejecutar, $puede_ejecutar, $puede_ejecutar, $puede_ejecutar, $puede_ejecutar);
                $stmt_ins_p->execute();
                $stmt_ins_p->close();
                echo "Permiso de perfil $perfil_id para función '{$f['codigo_funcion']}' creado.\n";
            }
        }
    }

    echo "✅ Seeder finalizado con éxito.\n";

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
?>
