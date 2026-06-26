<?php
/**
 * API de Gestión de Usuarios
 * Endpoints para crear, editar, eliminar, bloquear usuarios
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
require_once '../UsuarioManager.php';

$metodo = $_SERVER['REQUEST_METHOD'];
$ruta = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$partes = explode('/', $ruta);

// Validar sesión: aceptar PHP session O Bearer token del header Authorization
session_start();
$bearer_token = null;
$auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? apache_request_headers()['Authorization'] ?? '';
if (preg_match('/Bearer\s+(.+)$/i', $auth_header, $matches)) {
    $bearer_token = $matches[1];
}

if (!isset($_SESSION['usuario_id']) && empty($bearer_token)) {
    respuestaJSON(false, 'Sesión no válida', null, 401);
}

// En modo demo: usuario_id = 1 si viene por Bearer token
$usuario_actual = $_SESSION['usuario_id'] ?? 1;

// Crear manager (puede fallar si la BD no está disponible)
try {
    $manager = new UsuarioManager();
} catch (Exception $e) {
    $manager = null;
}

try {
    if (strpos($ruta, '/crear') !== false && $metodo === 'POST') {
        // CREAR USUARIO
        $datos = json_decode(file_get_contents('php://input'), true);
        
        // Validación técnica Dominicana (NOFTRAB)
        if (ValidadorDocumentos::isValidatorActive('usuarios')) {
            $tipoDoc = $datos['tipo_documento'] ?? null;
            if (!empty($datos['cedula']) && !ValidadorDocumentos::validarDocumento($datos['cedula'], $tipoDoc)) {
                $msg = ($tipoDoc === 'pasaporte') ? 'El pasaporte especificado no es válido (formato incorrecto).' : 'La cédula o RNC especificada no es válida (dígito verificador incorrecto).';
                respuestaJSON(false, $msg, null, 400);
                exit;
            }
            if (!empty($datos['telefono']) && !ValidadorDocumentos::validarTelefono($datos['telefono'])) {
                respuestaJSON(false, 'El teléfono especificado no es válido (debe tener 10 dígitos y código de área 809, 829 o 849).', null, 400);
                exit;
            }
        }
        
        $resultado = $manager->crearUsuario($datos, $usuario_actual);
        respuestaJSON($resultado['exito'], $resultado['mensaje'], $resultado, $resultado['exito'] ? 201 : 400);

    } elseif (strpos($ruta, '/editar/') !== false && $metodo === 'PUT') {
        // EDITAR USUARIO
        $usuario_id = intval(end($partes));
        $datos = json_decode(file_get_contents('php://input'), true);
        
        // Validación técnica Dominicana (NOFTRAB)
        if (ValidadorDocumentos::isValidatorActive('usuarios')) {
            $tipoDoc = $datos['tipo_documento'] ?? null;
            if (!empty($datos['cedula']) && !ValidadorDocumentos::validarDocumento($datos['cedula'], $tipoDoc)) {
                $msg = ($tipoDoc === 'pasaporte') ? 'El pasaporte especificado no es válido (formato incorrecto).' : 'La cédula o RNC especificada no es válida (dígito verificador incorrecto).';
                respuestaJSON(false, $msg, null, 400);
                exit;
            }
            if (!empty($datos['telefono']) && !ValidadorDocumentos::validarTelefono($datos['telefono'])) {
                respuestaJSON(false, 'El teléfono especificado no es válido (debe tener 10 dígitos y código de área 809, 829 o 849).', null, 400);
                exit;
            }
        }
        
        $resultado = $manager->editarUsuario($usuario_id, $datos, $usuario_actual);
        respuestaJSON($resultado['exito'], $resultado['mensaje'], null, $resultado['exito'] ? 200 : 400);

    } elseif (strpos($ruta, '/bloquear/') !== false && $metodo === 'POST') {
        // BLOQUEAR USUARIO
        $usuario_id = intval(end($partes));
        $datos = json_decode(file_get_contents('php://input'), true);
        $resultado = $manager->bloquearUsuario($usuario_id, $datos['razon'] ?? 'Bloqueo administrativo', $usuario_actual);
        respuestaJSON($resultado['exito'], $resultado['mensaje'], null, $resultado['exito'] ? 200 : 400);

    } elseif (strpos($ruta, '/desbloquear/') !== false && $metodo === 'POST') {
        // DESBLOQUEAR USUARIO
        $usuario_id = intval(end($partes));
        $resultado = $manager->desbloquearUsuario($usuario_id, $usuario_actual);
        respuestaJSON($resultado['exito'], $resultado['mensaje'], null, $resultado['exito'] ? 200 : 400);

    } elseif (strpos($ruta, '/restablecer-password/') !== false && $metodo === 'POST') {
        // RESTABLECER CONTRASEÑA
        $usuario_id = intval(end($partes));
        $resultado = $manager->restablecerPassword($usuario_id, $usuario_actual);
        respuestaJSON($resultado['exito'], $resultado['mensaje'], $resultado, $resultado['exito'] ? 200 : 400);

    } elseif (strpos($ruta, '/obtener/') !== false && $metodo === 'GET') {
        // OBTENER USUARIO ESPECÍFICO
        $usuario_id = intval(end($partes));
        $usuario = $manager->obtenerUsuarioCompleto($usuario_id);

        if (!$usuario) {
            respuestaJSON(false, 'Usuario no encontrado', null, 404);
        }

        respuestaJSON(true, 'Usuario obtenido', $usuario, 200);

    } elseif (strpos($ruta, '/listar') !== false && $metodo === 'GET') {
        // LISTAR USUARIOS CON PAGINACIÓN
        $pagina = intval($_GET['pagina'] ?? 1);
        $por_pagina = intval($_GET['por_pagina'] ?? 20);
        $filtros = [
            'estado'    => $_GET['estado'] ?? null,
            'perfil_id' => intval($_GET['perfil_id'] ?? 0),
            'buscar'    => $_GET['buscar'] ?? null
        ];

        try {
            $resultado = $manager
                ? $manager->listarUsuarios($pagina, $por_pagina, array_filter($filtros))
                : ['usuarios' => [], 'paginacion' => ['pagina_actual' => 1, 'total_paginas' => 1, 'total_registros' => 0]];
        } catch (Exception $e) {
            $resultado = ['usuarios' => [], 'paginacion' => ['pagina_actual' => 1, 'total_paginas' => 1, 'total_registros' => 0]];
        }
        respuestaJSON(true, 'Usuarios obtenidos', $resultado, 200);

    } elseif (strpos($ruta, '/importar-excel') !== false && $metodo === 'POST') {
        // IMPORTACIÓN DE EXCEL/CSV VÍA ETL PYTHON 3.14
        if (empty($_FILES['file']) && empty($_FILES['archivo'])) {
            respuestaJSON(false, 'Debe proporcionar un archivo válido', null, 400);
        }

        $archivo = $_FILES['file'] ?? $_FILES['archivo'];
        
        // Crear carpeta temporal si no existe
        $upload_dir = dirname(__DIR__) . '/uploads/temp_import';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        // Generar nombre de archivo temporal seguro
        $ext = pathinfo($archivo['name'], PATHINFO_EXTENSION);
        $temp_name = 'import_' . time() . '_' . uniqid() . '.' . $ext;
        $temp_file_path = $upload_dir . '/' . $temp_name;

        if (!move_uploaded_file($archivo['tmp_name'], $temp_file_path)) {
            respuestaJSON(false, 'No se pudo mover el archivo subido', null, 500);
        }

        // Ejecutar ETL en Python 3.14
        $python_cmd = "python";
        $script_path = escapeshellarg(dirname(__DIR__) . '/etl_usuarios_import.py');
        $file_path_arg = escapeshellarg($temp_file_path);
        
        $command = "$python_cmd $script_path $file_path_arg";
        
        $output = [];
        $return_var = 0;
        exec($command, $output, $return_var);
        
        // Eliminar archivo temporal de inmediato
        if (file_exists($temp_file_path)) {
            unlink($temp_file_path);
        }
        
        $json_output = implode("\n", $output);
        $etl_data = json_decode($json_output, true);
        
        if ($return_var !== 0 || empty($etl_data) || !$etl_data['exito']) {
            $err_msg = $etl_data['mensaje'] ?? 'Error desconocido al procesar el archivo con el motor Python ETL.';
            respuestaJSON(false, $err_msg, ['output' => $json_output], 500);
        }
        
        // Ejecutar la importación masiva en la BD a través de UsuarioManager
        $resultado = $manager->importarUsuarios($etl_data['registros'], $usuario_actual);
        
        // Agregar logs y warnings del motor ETL en la respuesta
        $resultado['warnings'] = $etl_data['warnings'] ?? [];
        $resultado['registros_leidos'] = count($etl_data['registros']);
        
        respuestaJSON(true, 'Archivo importado y procesado exitosamente por el motor ETL', $resultado, 200);

    } elseif (strpos($ruta, '/importar') !== false && $metodo === 'POST') {
        // IMPORTACIÓN MASIVA
        $datos = json_decode(file_get_contents('php://input'), true);
        if (!isset($datos['usuarios']) || !is_array($datos['usuarios'])) {
            respuestaJSON(false, 'Datos de importación inválidos', null, 400);
        }
        $resultado = $manager->importarUsuarios($datos['usuarios'], $usuario_actual);
        respuestaJSON($resultado['exito'], $resultado['mensaje'], $resultado, $resultado['exito'] ? 200 : 400);

    } elseif (strpos($ruta, '/eliminar/') !== false && $metodo === 'DELETE') {

        // ELIMINAR USUARIO
        $usuario_id = intval(end($partes));
        $resultado = $manager->eliminarUsuario($usuario_id, $usuario_actual);
        respuestaJSON($resultado['exito'], $resultado['mensaje'], null, $resultado['exito'] ? 200 : 400);

    } else {
        respuestaJSON(false, 'Endpoint no encontrado', null, 404);
    }

} catch (Exception $e) {
    // Si es listar, devolver estructura vacía en lugar de error 500
    if (strpos($ruta, '/listar') !== false) {
        $data_vacia = ['usuarios' => [], 'paginacion' => ['pagina_actual' => 1, 'total_paginas' => 1, 'total_registros' => 0]];
        respuestaJSON(true, 'Sin datos (BD no disponible)', $data_vacia, 200);
    }
    respuestaJSON(false, 'Error: ' . $e->getMessage(), null, 500);
}

?>
