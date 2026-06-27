<?php
/**
 * API del Motor de ETL de Tarifas (Insertivo) — v1.0
 * MAS QUE FIANZAS — Sistema Integrado
 * ==========================================================
 * Permite subir planillas Excel/CSV, analizarlas, mapear columnas,
 * previsualizar registros e importarlos a la base de datos de manera atómica
 * cumpliendo con las normas de auditoría NOFTRAB v4.0.
 */

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200); exit;
}

require_once '../config.php';

// ─── VALIDACIÓN DE SESIÓN (PHP Session + Bearer Token) ──────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$bearer_token = null;
$auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? (function_exists('apache_request_headers') ? (apache_request_headers()['Authorization'] ?? '') : '');
if (preg_match('/Bearer\s+(.+)$/i', $auth_header, $matches)) {
    $bearer_token = trim($matches[1]);
}
if (empty($bearer_token)) {
    $bearer_token = $_GET['token_sesion'] ?? $_POST['token_sesion'] ?? $_REQUEST['token'] ?? $_REQUEST['token_sesion'] ?? null;
}

$usuario_id = null;
if (isset($_SESSION['usuario_id']) && $_SESSION['usuario_id']) {
    $usuario_id = (int)$_SESSION['usuario_id'];
} elseif (!empty($bearer_token)) {
    $db_temp = Database::getInstance()->getConnection();
    $stmt_tk = $db_temp->prepare("SELECT usuario_id FROM sesiones_usuario WHERE token_sesion = ? AND activa = 1 AND fecha_expiracion > NOW() LIMIT 1");
    if ($stmt_tk) {
        $stmt_tk->bind_param("s", $bearer_token);
        $stmt_tk->execute();
        $res_tk = $stmt_tk->get_result();
        if ($row_tk = $res_tk->fetch_assoc()) $usuario_id = (int)$row_tk['usuario_id'];
        $stmt_tk->close();
    }
}

if (!$usuario_id) {
    http_response_code(401);
    echo json_encode(["exito" => false, "mensaje" => "Sesión no válida o expirada"]);
    exit;
}

$usuario_actual = $usuario_id;
$db = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'listar_companias';

// Asegurar directorio temporal
$uploads_dir = dirname(__DIR__) . '/uploads/etl_temp';
if (!is_dir($uploads_dir)) {
    mkdir($uploads_dir, 0755, true);
}

// Helper para ejecutar el parser en Python
function ejecutarParserPython($cmd, $file_path, $extra_arg = null, $sheet_name = null) {
    $python_script = dirname(__DIR__) . '/scripts/etl_parser.py';
    $python_bin = 'python';
    
    // Si es comando parse, codificar el JSON en base64 para evitar escapes problemáticos en Windows
    if ($cmd === 'parse' && $extra_arg !== null) {
        $extra_arg = base64_encode($extra_arg);
    }
    
    $full_cmd = $python_bin . ' ' . escapeshellarg($python_script) . ' ' . escapeshellarg($cmd) . ' ' . escapeshellarg($file_path);
    if ($extra_arg !== null) {
        $full_cmd .= ' ' . escapeshellarg($extra_arg);
    }
    if ($sheet_name !== null) {
        $full_cmd .= ' ' . escapeshellarg($sheet_name);
    }
    
    $output = [];
    $return_var = 0;
    exec($full_cmd . ' 2>&1', $output, $return_var);
    
    $output_str = implode("\n", $output);
    $json = json_decode($output_str, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'exito' => false,
            'mensaje' => 'Error al invocar el parser de ETL en Python.',
            'error_detalle' => $output_str
        ];
    }
    
    return $json;
}

try {
    // ─── VERIFICACIÓN DE PERMISOS GENERALES ──────────────────────────────────
    if (!tienePermiso($usuario_actual, 'CONF_ETL_VER') && $usuario_actual !== 1) {
        http_response_code(403);
        echo json_encode(["exito" => false, "mensaje" => "No tiene permisos para acceder al Motor de ETL de Tarifas."]);
        exit;
    }

    if ($method === 'GET') {
        if ($action === 'listar_companias') {
            $sql = "SELECT id, nombre, rnc FROM companias_registradas WHERE tipo = 'aseguradora' AND estado = 1 
                    ORDER BY 
                      CASE 
                        WHEN LOWER(nombre) LIKE '%multiseguros%' THEN 1
                        WHEN LOWER(nombre) LIKE '%midas%' THEN 2
                        WHEN LOWER(nombre) LIKE '%patria%' THEN 3
                        WHEN LOWER(nombre) LIKE '%pepin%' OR LOWER(nombre) LIKE '%pep%n%' THEN 4
                        ELSE 999
                      END ASC, nombre ASC";
            $res = $db->query($sql);
            $companias = [];
            while ($row = $res->fetch_assoc()) {
                $companias[] = [
                    'id' => (int)$row['id'],
                    'nombre' => $row['nombre'],
                    'rnc' => $row['rnc']
                ];
            }
            echo json_encode(["exito" => true, "data" => $companias]);
            exit;
            
        } elseif ($action === 'obtener_mapeo') {
            $compania_id = isset($_GET['compania_id']) ? (int)$_GET['compania_id'] : 0;
            if ($compania_id <= 0) throw new Exception("ID de compañía inválido.");
            
            $stmt = $db->prepare("SELECT columna_origen, columna_destino, valor_default FROM etl_mapeos WHERE compania_id = ?");
            $stmt->bind_param("i", $compania_id);
            $stmt->execute();
            $res = $stmt->get_result();
            
            $mappings = [];
            while ($row = $res->fetch_assoc()) {
                $mappings[$row['columna_destino']] = [
                    'type' => !empty($row['columna_origen']) ? 'column' : 'fixed',
                    'value' => !empty($row['columna_origen']) ? $row['columna_origen'] : $row['valor_default']
                ];
            }
            $stmt->close();
            
            echo json_encode(["exito" => true, "data" => $mappings]);
            exit;
        }
        
    } elseif ($method === 'POST') {
        // Operaciones de carga y procesamiento (requieren CONF_ETL_EJECUTAR)
        if (!tienePermiso($usuario_actual, 'CONF_ETL_EJECUTAR') && $usuario_actual !== 1) {
            http_response_code(403);
            echo json_encode(["exito" => false, "mensaje" => "No tiene autorización para realizar cargas de tarifas en el sistema."]);
            exit;
        }
        
        if ($action === 'upload') {
            if (!isset($_FILES['file'])) throw new Exception("No se ha subido ningún archivo.");
            
            $file = $_FILES['file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['xlsx', 'xls', 'csv'])) {
                throw new Exception("Formato de archivo no soportado. Debe ser Excel (.xlsx, .xls) o CSV.");
            }
            
            $temp_name = 'etl_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $dest_path = $uploads_dir . '/' . $temp_name;
            
            if (!move_uploaded_file($file['tmp_name'], $dest_path)) {
                throw new Exception("Error al mover el archivo subido al servidor.");
            }
            
            // Analizar archivo mediante Python
            $res_analysis = ejecutarParserPython('analyze', $dest_path);
            
            if (!$res_analysis['exito']) {
                if (file_exists($dest_path)) unlink($dest_path);
                throw new Exception($res_analysis['mensaje']);
            }
            
            // Cargar mapeo previo si existe para la aseguradora seleccionada
            $compania_id = isset($_POST['compania_id']) ? (int)$_POST['compania_id'] : 0;
            $prev_mapping = [];
            if ($compania_id > 0) {
                $stmt = $db->prepare("SELECT columna_origen, columna_destino, valor_default FROM etl_mapeos WHERE compania_id = ?");
                $stmt->bind_param("i", $compania_id);
                $stmt->execute();
                $res_m = $stmt->get_result();
                while ($row = $res_m->fetch_assoc()) {
                    $prev_mapping[$row['columna_destino']] = [
                        'type' => !empty($row['columna_origen']) ? 'column' : 'fixed',
                        'value' => !empty($row['columna_origen']) ? $row['columna_origen'] : $row['valor_default']
                    ];
                }
                $stmt->close();
            }
            
            echo json_encode([
                "exito" => true,
                "temp_filename" => $temp_name,
                "type" => $res_analysis['type'],
                "sheets" => $res_analysis['sheets'],
                "columns" => $res_analysis['columns'],
                "preview" => $res_analysis['preview'],
                "prev_mapping" => $prev_mapping
            ]);
            exit;
            
        } elseif ($action === 'preview') {
            $input = json_decode(file_get_contents('php://input'), true);
            $temp_filename = basename($input['temp_filename'] ?? '');
            $mapping = $input['mapping'] ?? null;
            $sheet_name = $input['sheet_name'] ?? null;
            
            if (empty($temp_filename) || !$mapping) {
                throw new Exception("Parámetros insuficientes para la vista previa.");
            }
            
            $file_path = $uploads_dir . '/' . $temp_filename;
            if (!file_exists($file_path)) {
                throw new Exception("El archivo temporal ha expirado o no existe.");
            }
            
            $mapping_str = json_encode($mapping, JSON_UNESCAPED_UNICODE);
            $res_parse = ejecutarParserPython('parse', $file_path, $mapping_str, $sheet_name);
            
            if (!$res_parse['exito']) {
                throw new Exception($res_parse['mensaje']);
            }
            
            // Retorna primeros 10 registros procesados como vista previa
            echo json_encode([
                "exito" => true,
                "data" => array_slice($res_parse['datos'], 0, 10),
                "total_validos" => $res_parse['total_validos'],
                "total_procesados" => $res_parse['total_procesados'],
                "errores" => $res_parse['errores']
            ]);
            exit;
            
        } elseif ($action === 'import') {
            $input = json_decode(file_get_contents('php://input'), true);
            $temp_filename = basename($input['temp_filename'] ?? '');
            $mapping = $input['mapping'] ?? null;
            $sheet_name = $input['sheet_name'] ?? null;
            $compania_id = (int)($input['compania_id'] ?? 0);
            $justificacion = trim($input['justificacion'] ?? '');
            $aplicar_descuento_livianos = isset($input['aplicar_descuento_livianos']) ? (bool)$input['aplicar_descuento_livianos'] : false;
            
            if (empty($temp_filename) || !$mapping || $compania_id <= 0) {
                throw new Exception("Parámetros requeridos incompletos.");
            }
            
            if (strlen($justificacion) < 10) {
                throw new Exception("Debe ingresar una justificación detallada de auditoría de al menos 10 caracteres.");
            }
            
            $file_path = $uploads_dir . '/' . $temp_filename;
            if (!file_exists($file_path)) {
                throw new Exception("El archivo temporal ha expirado o no existe.");
            }
            
            // 1. Obtener los datos mapeados del archivo completo
            $mapping_str = json_encode($mapping, JSON_UNESCAPED_UNICODE);
            $res_parse = ejecutarParserPython('parse', $file_path, $mapping_str, $sheet_name);
            
            if (!$res_parse['exito']) {
                throw new Exception($res_parse['mensaje']);
            }
            
            $nuevos_datos = $res_parse['datos'];
            if (empty($nuevos_datos)) {
                throw new Exception("El archivo no contiene registros válidos para importar.");
            }
            
            // 2. Iniciar Transacción e importar en Base de Datos
            $db->begin_transaction();
            
            // A) Copiar tarifas actuales para historial (auditoría forense NOFTRAB)
            $sql_ant = "SELECT id, tipo, capacidad, uso, cobertura, tarifa_base FROM tarifas_seguro WHERE compania_id = ?";
            $stmt_ant = $db->prepare($sql_ant);
            $stmt_ant->bind_param("i", $compania_id);
            $stmt_ant->execute();
            $res_ant = $stmt_ant->get_result();
            $tarifas_anteriores = [];
            while ($row = $res_ant->fetch_assoc()) {
                $tarifas_anteriores[] = $row;
            }
            $stmt_ant->close();
            
            // B) Eliminar tarifas anteriores de esta compañía
            $stmt_del = $db->prepare("DELETE FROM tarifas_seguro WHERE compania_id = ?");
            $stmt_del->bind_param("i", $compania_id);
            $stmt_del->execute();
            $stmt_del->close();
            
            // C) Insertar nuevas tarifas
            $sql_ins = "INSERT INTO tarifas_seguro (compania_id, tipo, capacidad, uso, cobertura, tarifa_base, porcentaje_adicional, activo) VALUES (?, ?, ?, ?, ?, ?, 0.00, 1)";
            $stmt_ins = $db->prepare($sql_ins);
            
            $tarifas_insertadas = [];
            $insert_count = 0;
            
            foreach ($nuevos_datos as $r) {
                $tipo = $r['tipo_vehiculo'];
                $capacidad = $r['capacidad'];
                $uso = $r['uso'];
                $cobertura = $r['cobertura'];
                $tarifa_base = (float)$r['tarifa_base'];
                
                // Aplicar la regla del 2% de descuento si corresponde
                if ($aplicar_descuento_livianos && in_array($tipo, ['AUTOMOVILES', 'JEEP']) && $uso === 'PRIVADO') {
                    $tarifa_base = round($tarifa_base * 0.98, 2);
                }
                
                $stmt_ins->bind_param("issssd", $compania_id, $tipo, $capacidad, $uso, $cobertura, $tarifa_base);
                if ($stmt_ins->execute()) {
                    $insert_count++;
                    $tarifas_insertadas[] = [
                        'tipo' => $tipo,
                        'capacidad' => $capacidad,
                        'uso' => $uso,
                        'cobertura' => $cobertura,
                        'tarifa_base' => $tarifa_base
                    ];
                }
            }
            $stmt_ins->close();
            
            // D) Guardar / Actualizar mapeo de columnas en etl_mapeos para esta aseguradora
            foreach ($mapping as $target_col => $map_cfg) {
                if ($target_col === 'mode') continue;
                $col_orig = ($map_cfg['type'] === 'column') ? $map_cfg['value'] : '';
                $val_def = ($map_cfg['type'] === 'fixed') ? $map_cfg['value'] : null;
                
                $stmt_map = $db->prepare("INSERT INTO etl_mapeos (compania_id, columna_origen, columna_destino, valor_default) 
                                          VALUES (?, ?, ?, ?)
                                          ON DUPLICATE KEY UPDATE columna_origen = VALUES(columna_origen), valor_default = VALUES(valor_default)");
                $stmt_map->bind_param("isss", $compania_id, $col_orig, $target_col, $val_def);
                $stmt_map->execute();
                $stmt_map->close();
            }
            
            // E) Obtener nombre de la compañía para la descripción de la auditoría
            $res_c = $db->query("SELECT nombre FROM companias_registradas WHERE id = $compania_id LIMIT 1");
            $comp_row = $res_c->fetch_assoc();
            $comp_name = $comp_row['nombre'] ?? "ID $compania_id";
            
            // F) Registrar Ajuste de Auditoría Inmutable (NOFTRAB)
            $ok_ajuste = registrarAjuste(
                $usuario_actual, 'Configuracion', 'tarifas_seguro', $compania_id,
                $tarifas_anteriores, $tarifas_insertadas, $justificacion
            );
            
            if (!$ok_ajuste) {
                throw new Exception("Error al escribir el registro inmutable en el historial de ajustes.");
            }
            
            // G) Registrar log de auditoría accesos
            logAudit(
                $usuario_actual, 'editar_usuario', 'Configuracion', 'CONF_ETL_EJECUTAR',
                "Importadas {$insert_count} tarifas para aseguradora '{$comp_name}' mediante ETL.",
                'exitoso', null, 'tarifas_seguro', $compania_id, $tarifas_anteriores, $tarifas_insertadas
            );
            
            $db->commit();
            
            // Eliminar archivo temporal tras el éxito de la importación
            if (file_exists($file_path)) unlink($file_path);
            
            echo json_encode([
                "exito" => true,
                "mensaje" => "Se importaron exitosamente {$insert_count} tarifas para {$comp_name} bajo normas NOFTRAB.",
                "total_importadas" => $insert_count
            ]);
            exit;
        }
    }
} catch (Exception $e) {
    if (isset($db) && $db->in_transaction) $db->rollback();
    // Limpiar archivo temporal en caso de error
    if (isset($file_path) && file_exists($file_path) && $action === 'upload') {
        unlink($file_path);
    }
    http_response_code(500);
    echo json_encode(["exito" => false, "mensaje" => $e->getMessage()]);
}
?>
