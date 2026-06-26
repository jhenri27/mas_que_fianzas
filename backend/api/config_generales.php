<?php
/**
 * API de Configuración General e Institucional (Norma NOFTRAB v4.0)
 * MAS QUE FIANZAS - Core Asegurador
 */

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config.php';

// Validar sesión: aceptar PHP session O Bearer token
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

$db = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        // Obtener configuraciones institucionales y del validador
        $keys = [
            'EMPRESA_NOMBRE', 'EMPRESA_RNC', 'EMPRESA_CORREO', 
            'EMPRESA_DIRECCION', 'EMPRESA_TELEFONO', 'EMPRESA_WEB', 'EMPRESA_REDES',
            'VALIDADOR_DOCS_ACTIVO', 'VALIDADOR_DOCS_CLIENTES', 'VALIDADOR_DOCS_COTIZACIONES',
            'VALIDADOR_DOCS_USUARIOS', 'VALIDADOR_DOCS_POLIZAS', 'VALIDADOR_DOCS_FIANZAS'
        ];
        
        $inClause = implode(',', array_fill(0, count($keys), '?'));
        $stmt = $db->prepare("SELECT clave_config, valor_config, tipo_valor FROM configuracion_sistema WHERE clave_config IN ($inClause)");
        
        // Dynamic bind params
        $stmt->bind_param(str_repeat('s', count($keys)), ...$keys);
        $stmt->execute();
        $res = $stmt->get_result();
        
        $configs = [];
        // Valores por defecto en memoria por si acaso
        $configs['empresa_nombre'] = 'MAS QUE FIANZAS';
        $configs['empresa_rnc'] = '133-53573-4';
        $configs['empresa_correo'] = 'info@masquefianzas.com';
        $configs['empresa_direccion'] = 'Av. 27 de Febrero, Santo Domingo, República Dominicana';
        $configs['empresa_telefono'] = '809-555-0123';
        $configs['empresa_web'] = 'https://www.masquefianzas.com.do';
        $configs['empresa_redes'] = ['instagram' => '', 'facebook' => '', 'twitter' => '', 'linkedin' => ''];
        $configs['validador_docs_activo'] = '0';
        $configs['validador_docs_clientes'] = '0';
        $configs['validador_docs_cotizaciones'] = '0';
        $configs['validador_docs_usuarios'] = '0';
        $configs['validador_docs_polizas'] = '0';
        $configs['validador_docs_fianzas'] = '0';
        
        while ($row = $res->fetch_assoc()) {
            $keyLower = strtolower($row['clave_config']);
            $val = $row['valor_config'];
            if ($row['tipo_valor'] === 'json') {
                $val = json_decode($val, true) ?: [];
            }
            $configs[$keyLower] = $val;
        }
        $stmt->close();
        
        echo json_encode(["exito" => true, "datos" => $configs]);
        exit;
        
    } elseif ($method === 'POST') {
        // Validar permisos de edición (Admin ID=1 o permiso de editar conf)
        if ($usuario_id !== 1 && !tienePermiso($usuario_id, 'CONF_GENERALES_EDITAR') && !tienePermiso($usuario_id, 'CONF_NOTIF_EDITAR')) {
            http_response_code(403);
            echo json_encode(["exito" => false, "mensaje" => "No tiene permisos para modificar la configuración de la plataforma."]);
            exit;
        }
        
        $datos = json_decode(file_get_contents('php://input'), true);
        if (!$datos) {
            http_response_code(400);
            echo json_encode(["exito" => false, "mensaje" => "Cuerpo de solicitud inválido."]);
            exit;
        }
        
        $is_validador_only = isset($datos['validador_only']) && $datos['validador_only'];
        $updates = [];
        
        if (!$is_validador_only) {
            // Validar campos obligatorios
            $required = ['empresa_nombre', 'empresa_rnc', 'empresa_correo', 'empresa_direccion', 'empresa_telefono'];
            foreach ($required as $req) {
                if (empty($datos[$req])) {
                    http_response_code(400);
                    echo json_encode(["exito" => false, "mensaje" => "El campo " . str_replace('empresa_', '', $req) . " es obligatorio."]);
                    exit;
                }
            }
            
            // Validar formato de email
            if (!filter_var($datos['empresa_correo'], FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                echo json_encode(["exito" => false, "mensaje" => "El correo institucional especificado no tiene un formato válido."]);
                exit;
            }
            
            // Preparar redes sociales
            $redes = [
                'instagram' => trim($datos['empresa_redes']['instagram'] ?? ''),
                'facebook' => trim($datos['empresa_redes']['facebook'] ?? ''),
                'twitter' => trim($datos['empresa_redes']['twitter'] ?? ''),
                'linkedin' => trim($datos['empresa_redes']['linkedin'] ?? '')
            ];
            
            // Mapeo de campos a actualizar
            $updates = [
                'EMPRESA_NOMBRE' => trim($datos['empresa_nombre']),
                'EMPRESA_RNC' => trim($datos['empresa_rnc']),
                'EMPRESA_CORREO' => trim($datos['empresa_correo']),
                'EMPRESA_DIRECCION' => trim($datos['empresa_direccion']),
                'EMPRESA_TELEFONO' => trim($datos['empresa_telefono']),
                'EMPRESA_WEB' => trim($datos['empresa_web'] ?? ''),
                'EMPRESA_REDES' => json_encode($redes)
            ];
        }
        
        // Agregar llaves del validador si están presentes en la petición
        $validador_keys = [
            'VALIDADOR_DOCS_ACTIVO', 'VALIDADOR_DOCS_CLIENTES', 'VALIDADOR_DOCS_COTIZACIONES',
            'VALIDADOR_DOCS_USUARIOS', 'VALIDADOR_DOCS_POLIZAS', 'VALIDADOR_DOCS_FIANZAS'
        ];
        foreach ($validador_keys as $vk) {
            $keyLower = strtolower($vk);
            if (isset($datos[$keyLower])) {
                $updates[$vk] = ($datos[$keyLower] === '1' || $datos[$keyLower] === 1 || $datos[$keyLower] === true) ? '1' : '0';
            }
        }
        
        // 1. Obtener valores anteriores para auditoría (NOFTRAB)
        $valor_anterior = [];
        if (!empty($updates)) {
            $inClause = implode(',', array_fill(0, count($updates), '?'));
            $stmt_before = $db->prepare("SELECT clave_config, valor_config FROM configuracion_sistema WHERE clave_config IN ($inClause)");
            $stmt_before->bind_param(str_repeat('s', count($updates)), ...array_keys($updates));
            $stmt_before->execute();
            $res_before = $stmt_before->get_result();
            while ($row = $res_before->fetch_assoc()) {
                $valor_anterior[$row['clave_config']] = $row['valor_config'];
            }
            $stmt_before->close();
        }
        
        // Iniciar transacción
        $db->begin_transaction();
        
        // 2. Ejecutar actualizaciones
        $st_upd = $db->prepare("UPDATE configuracion_sistema SET valor_config = ?, modificado_por = ? WHERE clave_config = ?");
        foreach ($updates as $clave => $valor) {
            $st_upd->bind_param('sis', $valor, $usuario_id, $clave);
            if (!$st_upd->execute()) {
                throw new Exception("Error al actualizar la configuración {$clave}: " . $db->error);
            }
        }
        $st_upd->close();
        
        // 3. Confirmar cambios
        $db->commit();
        
        // 4. Registrar en bitácora de auditoría global
        logAudit(
            $usuario_id,
            'editar_configuracion',
            'Configuración',
            'guardar_datos_institucionales',
            'Actualización de datos institucionales de la plataforma y correo institucional',
            'exitoso',
            null,
            'configuracion_sistema',
            null,
            $valor_anterior,
            $updates
        );
        
        echo json_encode(["exito" => true, "mensaje" => "Datos institucionales guardados correctamente."]);
        exit;
    }
} catch (Exception $e) {
    if (isset($db)) {
        $db->rollback();
    }
    http_response_code(500);
    echo json_encode(["exito" => false, "mensaje" => "Error interno del servidor: " . $e->getMessage()]);
    exit;
}
?>
