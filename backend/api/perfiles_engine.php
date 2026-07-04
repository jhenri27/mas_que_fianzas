<?php
/**
 * Wrapper de API para el Motor transaccional en Python de Perfiles y Permisos (NOFTRAB)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config.php';

session_start();

// Validar token de autorización si no hay sesión PHP activa
$bearer_token = null;
$auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (empty($auth_header) && function_exists('apache_request_headers')) {
    $headers = apache_request_headers();
    $auth_header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
}
if (preg_match('/Bearer\s+(.+)$/i', $auth_header, $matches)) {
    $bearer_token = $matches[1];
}

if (!isset($_SESSION['usuario_id']) && empty($bearer_token)) {
    respuestaJSON(false, 'Sesión no válida', null, 401);
}

$usuario_actual = $_SESSION['usuario_id'] ?? 1;

// Solo el Administrador (perfil_id = 1) o alguien con permisos especiales puede gestionar esto
$perfil_usuario = obtenerPerfilUsuario($usuario_actual);
if ($usuario_actual != 1 && $perfil_usuario['id'] != 1 && !tienePermiso($usuario_actual, 'CONF_TOTAL') && !tienePermiso($usuario_actual, 'PER_GESTIONAR')) {
    respuestaJSON(false, 'Acceso denegado: Se requiere perfil de Administrador.', null, 403);
}

$metodo = $_SERVER['REQUEST_METHOD'];
$request_uri = $_SERVER['REQUEST_URI'];
$script_name = $_SERVER['SCRIPT_NAME'];

// Obtener la ruta relativa al script
$ruta = $_SERVER['PATH_INFO'] ?? '';
if (empty($ruta)) {
    $pos = strpos($request_uri, $script_name);
    if ($pos !== false) {
        $ruta = substr($request_uri, $pos + strlen($script_name));
    }
}

$partes = explode('/', trim($ruta, '/'));
$endpoint = $partes[0] ?? '';

$python_script = dirname(__DIR__) . '/perfiles_engine.py';

// Función para ejecutar comando python
function runPythonCommand($cmd, $args = []) {
    $python_bin = 'python'; // Por defecto
    
    // Si conocemos la ruta de Python de WAMP podemos usarla o simplemente 'python'
    $full_cmd = $python_bin . ' ' . escapeshellarg($cmd);
    foreach ($args as $arg) {
        $full_cmd .= ' ' . escapeshellarg($arg);
    }
    
    $output = [];
    $return_var = 0;
    exec($full_cmd . ' 2>&1', $output, $return_var);
    
    $output_str = implode("\n", $output);
    $json = json_decode($output_str, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'exito' => false,
            'mensaje' => 'Error al invocar el motor en Python',
            'error_detalle' => $output_str
        ];
    }
    
    return $json;
}

try {
    if ($endpoint === 'listar' && $metodo === 'GET') {
        // Listar módulos y funciones
        $res = runPythonCommand($python_script, ['list_modules_functions']);
        respuestaJSON($res['exito'], $res['mensaje'], $res['datos'] ?? null);
        
    } elseif ($endpoint === 'obtener' && $metodo === 'GET') {
        // Obtener permisos de un perfil específico
        $perfil_id = intval($partes[1] ?? 0);
        if ($perfil_id === 0) {
            respuestaJSON(false, 'ID de perfil no proporcionado', null, 400);
        }
        
        $res = runPythonCommand($python_script, ['get_profile_permissions', $perfil_id]);
        respuestaJSON($res['exito'], $res['mensaje'], $res['datos'] ?? null);
        
    } elseif ($endpoint === 'guardar' && $metodo === 'POST') {
        // Guardar permisos granulares para un perfil (PHP nativo para evitar cuelgues de Apache en Windows)
        $perfil_id = intval($partes[1] ?? 0);
        if ($perfil_id === 0) {
            respuestaJSON(false, 'ID de perfil no proporcionado', null, 400);
        }
        
        $json_input = file_get_contents('php://input');
        $permisos = json_decode($json_input, true);
        
        if (!is_array($permisos)) {
            respuestaJSON(false, 'Formato de permisos inválido', null, 400);
        }
        
        $db = Database::getInstance()->getConnection();
        
        // 1. Obtener el perfil
        $stmt_perfil = $db->prepare("SELECT nombre_perfil FROM perfiles WHERE id = ?");
        $stmt_perfil->bind_param("i", $perfil_id);
        $stmt_perfil->execute();
        $res_perfil = $stmt_perfil->get_result()->fetch_assoc();
        $stmt_perfil->close();
        
        if (!$res_perfil) {
            respuestaJSON(false, 'Perfil no encontrado', null, 404);
        }
        
        // 2. Obtener permisos antiguos para la auditoría (NOFTRAB)
        $permisos_antiguos = [];
        $stmt_ant = $db->prepare("SELECT * FROM permisos_perfil WHERE perfil_id = ?");
        $stmt_ant->bind_param("i", $perfil_id);
        $stmt_ant->execute();
        $res_ant = $stmt_ant->get_result();
        while ($row = $res_ant->fetch_assoc()) {
            $permisos_antiguos[] = $row;
        }
        $stmt_ant->close();
        
        // Iniciar transacción
        $db->begin_transaction();
        
        try {
            // 3. Eliminar permisos antiguos
            $stmt_del = $db->prepare("DELETE FROM permisos_perfil WHERE perfil_id = ?");
            $stmt_del->bind_param("i", $perfil_id);
            $stmt_del->execute();
            $stmt_del->close();
            
            // 4. Insertar nuevos permisos granulares
            $sql_insert = "INSERT INTO permisos_perfil (
                perfil_id, funcion_id, modulo_id, puede_ejecutar, ver_datos,
                crear_datos, editar_datos, eliminar_datos, ver_reportes,
                exportar_datos, importar_datos, imprimir_datos, solo_propios, creado_por
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt_ins = $db->prepare($sql_insert);
            if (!$stmt_ins) {
                throw new Exception("Error al preparar la inserción de permisos.");
            }
            
            foreach ($permisos as $p) {
                $f_id = intval($p['funcion_id']);
                $m_id = intval($p['modulo_id']);
                $p_ejec = isset($p['puede_ejecutar']) && $p['puede_ejecutar'] ? 1 : 0;
                $v_dat = isset($p['ver_datos']) && $p['ver_datos'] ? 1 : 0;
                $c_dat = isset($p['crear_datos']) && $p['crear_datos'] ? 1 : 0;
                $e_dat = isset($p['editar_datos']) && $p['editar_datos'] ? 1 : 0;
                $el_dat = isset($p['eliminar_datos']) && $p['eliminar_datos'] ? 1 : 0;
                $v_rep = isset($p['ver_reportes']) && $p['ver_reportes'] ? 1 : 0;
                $ex_dat = isset($p['exportar_datos']) && $p['exportar_datos'] ? 1 : 0;
                $im_dat = isset($p['importar_datos']) && $p['importar_datos'] ? 1 : 0;
                $imp_dat = isset($p['imprimir_datos']) && $p['imprimir_datos'] ? 1 : 0;
                $s_prop = isset($p['solo_propios']) && $p['solo_propios'] ? 1 : 0;
                
                $stmt_ins->bind_param(
                    "iiiiiiiiiiiiii",
                    $perfil_id, $f_id, $m_id, $p_ejec, $v_dat,
                    $c_dat, $e_dat, $el_dat, $v_rep,
                    $ex_dat, $im_dat, $imp_dat, $s_prop, $usuario_actual
                );
                $stmt_ins->execute();
            }
            $stmt_ins->close();
            
            // 5. Escribir auditoría de accesos (NOFTRAB)
            $sql_audit = "INSERT INTO auditoria_accesos (
                usuario_id, tipo_evento, modulo_accedido, funcion_ejecutada,
                descripcion_evento, direccion_ip, navegador_user_agent,
                resultado, tabla_afectada, registro_afectado_id,
                operacion_realizada, valor_anterior, valor_nuevo
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt_aud = $db->prepare($sql_audit);
            if ($stmt_aud) {
                $tipo_ev = 'cambio_permiso';
                $mod_acc = 'configuracion';
                $func_ej = 'PER_ASIGNAR';
                $descr = "Actualización granular de permisos para el perfil: " . $res_perfil['nombre_perfil'];
                $ip_addr = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                $u_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'PHP Engine API';
                $res_ok = 'exitoso';
                $t_afect = 'permisos_perfil';
                $op_real = 'update';
                $val_ant = json_encode($permisos_antiguos);
                $val_nuev = json_encode($permisos);
                
                $stmt_aud->bind_param(
                    "issssssssisss",
                    $usuario_actual, $tipo_ev, $mod_acc, $func_ej,
                    $descr, $ip_addr, $u_agent, $res_ok, $t_afect,
                    $perfil_id, $op_real, $val_ant, $val_nuev
                );
                $stmt_aud->execute();
                $stmt_aud->close();
            }
            
            // Confirmar transacción
            $db->commit();
            respuestaJSON(true, 'Permisos guardados y auditados exitosamente', null);
            
        } catch (Exception $e) {
            $db->rollback();
            respuestaJSON(false, 'Error al guardar permisos: ' . $e->getMessage(), null, 500);
        }
        
    } elseif ($endpoint === 'crear' && $metodo === 'POST') {
        // Crear un nuevo perfil
        $json_input = file_get_contents('php://input');
        
        $temp_file = dirname(__DIR__) . '/logs/temp_crear_perfil_' . time() . '.json';
        file_put_contents($temp_file, $json_input);
        
        $res = runPythonCommand($python_script, ['create_profile', $temp_file, $usuario_actual]);
        
        if (file_exists($temp_file)) {
            unlink($temp_file);
        }
        
        respuestaJSON($res['exito'], $res['mensaje'], $res['perfil_id'] ?? null, $res['exito'] ? 201 : 400);
        
    } elseif ($endpoint === 'editar' && ($metodo === 'PUT' || $metodo === 'POST')) {
        // Editar un perfil existente
        $perfil_id = intval($partes[1] ?? 0);
        if ($perfil_id === 0) {
            respuestaJSON(false, 'ID de perfil no proporcionado', null, 400);
        }
        
        $json_input = file_get_contents('php://input');
        
        $temp_file = dirname(__DIR__) . '/logs/temp_editar_perfil_' . $perfil_id . '_' . time() . '.json';
        file_put_contents($temp_file, $json_input);
        
        $res = runPythonCommand($python_script, ['update_profile', $perfil_id, $temp_file, $usuario_actual]);
        
        if (file_exists($temp_file)) {
            unlink($temp_file);
        }
        
        respuestaJSON($res['exito'], $res['mensaje'], null);
        
    } elseif ($endpoint === 'eliminar' && ($metodo === 'DELETE' || $metodo === 'POST')) {
        // Eliminar perfil
        $perfil_id = intval($partes[1] ?? 0);
        if ($perfil_id === 0 && isset($_GET['id'])) {
            $perfil_id = intval($_GET['id']);
        }
        
        if ($perfil_id === 0) {
            respuestaJSON(false, 'ID de perfil no proporcionado', null, 400);
        }
        
        $res = runPythonCommand($python_script, ['delete_profile', $perfil_id, $usuario_actual]);
        respuestaJSON($res['exito'], $res['mensaje'], null);
        
    } elseif ($endpoint === 'copiar' && $metodo === 'POST') {
        // Copiar permisos de un perfil a otro
        $json_input = file_get_contents('php://input');
        $data = json_decode($json_input, true);
        $origen_id = intval($data['origen_id'] ?? 0);
        $destino_id = intval($data['destino_id'] ?? 0);
        
        if ($origen_id === 0 || $destino_id === 0) {
            respuestaJSON(false, 'Se requiere perfil de origen y destino', null, 400);
        }
        
        $res = runPythonCommand($python_script, ['copy_profile_permissions', $origen_id, $destino_id, $usuario_actual]);
        respuestaJSON($res['exito'], $res['mensaje'], null);
        
    } else {
        respuestaJSON(false, "Endpoint '$endpoint' no encontrado", null, 404);
    }
} catch (Exception $e) {
    respuestaJSON(false, 'Error en Wrapper API: ' . $e->getMessage(), null, 500);
}
?>
