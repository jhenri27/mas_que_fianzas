<?php
/**
 * API MODELADOR PDF Y AUTOCOMPLETADO v1.0
 * MAS QUE FIANZAS - Core Asegurador
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config.php';

// Validar autenticación
$bearer_token = null;
$auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? (function_exists('apache_request_headers') ? (apache_request_headers()['Authorization'] ?? '') : '');
if (preg_match('/Bearer\s+(.+)$/i', $auth_header, $matches)) {
    $bearer_token = trim($matches[1]);
}
if (empty($bearer_token)) {
    $bearer_token = $_GET['token_sesion'] ?? $_POST['token_sesion'] ?? $_REQUEST['token'] ?? null;
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

$action = $_GET['action'] ?? '';
$db = Database::getInstance()->getConnection();

// Helper para resolver variables de Póliza Emitida
function resolverVariablesPoliza($db, $poliza_id) {
    $stmt = $db->prepare("SELECT p.*, a.nombre as aseguradora_nombre 
                          FROM polizas p 
                          LEFT JOIN fianza_aseguradoras a ON p.aseguradora_id = a.id 
                          WHERE p.id = ?");
    if (!$stmt) return [];
    $stmt->bind_param("i", $poliza_id);
    $stmt->execute();
    $poliza = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$poliza) return [];
    
    $stmt = $db->prepare("SELECT * FROM clientes WHERE id = ?");
    $stmt->bind_param("i", $poliza['cliente_id']);
    $stmt->execute();
    $cliente = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$cliente) $cliente = [];
    
    return [
        'cliente.nombre' => $cliente['nombre'] ?? '',
        'cliente.cedula' => $cliente['cedula'] ?? '',
        'cliente.telefono' => $cliente['telefono'] ?? '',
        'cliente.email' => $cliente['email'] ?? '',
        'poliza.numero_poliza' => $poliza['numero_poliza'] ?? '',
        'poliza.monto_asegurado' => $poliza['monto_afianzado'] ?? $poliza['monto_asegurado'] ?? '',
        'poliza.fecha_inicio' => $poliza['fecha_inicio'] ?? '',
        'poliza.fecha_fin' => $poliza['fecha_vencimiento'] ?? $poliza['fecha_fin'] ?? '',
        'poliza.prima_neta' => $poliza['prima_base'] ?? '',
        'poliza.itbis' => $poliza['itbis'] ?? '',
        'poliza.total_pagar' => $poliza['monto_total'] ?? '',
        'poliza.beneficiario' => $poliza['beneficiario'] ?? '',
        'poliza.objeto_fianza' => $poliza['objeto_referencia'] ?? $poliza['observaciones'] ?? '',
        'poliza.aseguradora_nombre' => $poliza['aseguradora_nombre'] ?? ''
    ];
}

// Helper para resolver variables de Cotización / Solicitud
function resolverVariablesCotizacion($db, $cotizacion_id) {
    $stmt = $db->prepare("SELECT * FROM cotizaciones WHERE id = ?");
    if (!$stmt) return [];
    $stmt->bind_param("i", $cotizacion_id);
    $stmt->execute();
    $cot = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$cot) return [];
    
    return [
        'cliente.nombre' => $cot['cliente'] ?? '',
        'cliente.cedula' => $cot['cedula'] ?? '',
        'cliente.telefono' => $cot['telefono'] ?? '',
        'cliente.email' => $cot['email'] ?? '',
        'poliza.numero_poliza' => $cot['numero'] ?? $cot['id'] ?? '',
        'poliza.monto_asegurado' => $cot['monto_afianzado'] ?? $cot['total'] ?? '',
        'poliza.fecha_inicio' => $cot['fecha'] ? date('Y-m-d', strtotime($cot['fecha'])) : date('Y-m-d'),
        'poliza.fecha_fin' => isset($cot['plazo']) ? date('Y-m-d', strtotime($cot['fecha'] . " + " . intval($cot['plazo']) . " months")) : '',
        'poliza.prima_neta' => $cot['prima_base'] ?? $cot['total'] ?? '',
        'poliza.itbis' => $cot['impuesto'] ?? '0.00',
        'poliza.total_pagar' => $cot['total'] ?? '',
        'poliza.beneficiario' => $cot['beneficiario'] ?? '',
        'poliza.objeto_fianza' => $cot['subtipo'] ?? $cot['cobertura'] ?? '',
        'poliza.aseguradora_nombre' => $cot['aseguradora'] ?? ''
    ];
}

try {
    switch ($action) {
        case 'listar_plantillas':
            // Retornar las plantillas asociadas a aseguradoras
            $sql = "SELECT p.*, a.nombre as aseguradora_nombre 
                    FROM pdf_plantillas p 
                    LEFT JOIN fianza_aseguradoras a ON p.aseguradora_id = a.id 
                    ORDER BY p.id DESC";
            $res = $db->query($sql);
            $plantillas = [];
            while ($row = $res->fetch_assoc()) {
                $plantillas[] = $row;
            }
            echo json_encode(["exito" => true, "plantillas" => $plantillas]);
            break;

        case 'subir_plantilla':
            // Asegurar directorios de subida
            $dir_templates = dirname(__FILE__) . '/../../uploads/pdf_templates';
            if (!file_exists($dir_templates)) {
                mkdir($dir_templates, 0777, true);
            }

            if (!isset($_FILES['file'])) {
                throw new Exception("No se ha subido ningún archivo.");
            }

            $file = $_FILES['file'];
            $aseguradora_id = (int)($_POST['aseguradora_id'] ?? 0);
            $nombre = trim($_POST['nombre'] ?? '');
            $ancho_mm = floatval($_POST['ancho_mm'] ?? 215.9); // Letter por defecto
            $alto_mm = floatval($_POST['alto_mm'] ?? 279.4);

            if (empty($nombre)) {
                throw new Exception("El nombre de la plantilla es obligatorio.");
            }

            // Obtener el nombre de la aseguradora
            $aseg_nombre = '';
            if ($aseguradora_id > 0) {
                $stmt = $db->prepare("SELECT nombre FROM fianza_aseguradoras WHERE id = ?");
                $stmt->bind_param("i", $aseguradora_id);
                $stmt->execute();
                $r = $stmt->get_result()->fetch_assoc();
                $aseg_nombre = $r['nombre'] ?? '';
                $stmt->close();
            }

            // Generar ruta de destino
            $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            if (strtolower($file_ext) !== 'pdf') {
                throw new Exception("Solo se permiten archivos en formato PDF.");
            }

            $safe_name = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $nombre) . '_' . time() . '.pdf';
            $dest_path = $dir_templates . '/' . $safe_name;

            if (!move_uploaded_file($file['tmp_name'], $dest_path)) {
                throw new Exception("Error al mover el archivo subido.");
            }

            // Invocar script de Python para escanear y detectar AcroForm fields
            $python_exe = 'python';
            $script_path = dirname(__FILE__) . '/../python/pdf_extractor.py';
            $cmd = "$python_exe " . escapeshellarg($script_path) . " scan " . escapeshellarg($dest_path);
            
            $output = shell_exec($cmd);
            $analysis = json_decode($output, true);
            
            $es_interactivo = 0;
            if ($analysis && isset($analysis['exito']) && $analysis['exito']) {
                if (count($analysis['campos']) > 0) {
                    $es_interactivo = 1;
                }
            }

            // Insertar plantilla
            $stmt = $db->prepare("INSERT INTO pdf_plantillas (aseguradora_id, aseguradora_nombre, nombre, archivo_base, tipo_archivo, ancho_mm, alto_mm, estado) VALUES (?, ?, ?, ?, 'pdf', ?, ?, 1)");
            $archivo_rel = 'uploads/pdf_templates/' . $safe_name;
            $stmt->bind_param("isssdd", $aseguradora_id, $aseg_nombre, $nombre, $archivo_rel, $ancho_mm, $alto_mm);
            $stmt->execute();
            $plantilla_id = $db->insert_id;
            $stmt->close();

            // Si es interactivo, auto-cargar los campos AcroForm detectados en la BD
            if ($es_interactivo == 1 && $analysis && isset($analysis['campos'])) {
                foreach ($analysis['campos'] as $campo) {
                    $stmt_c = $db->prepare("INSERT INTO pdf_campos (plantilla_id, variable, nombre_campo_pdf, pagina, pos_x, pos_y, font_size, font_family, color, font_weight, alineacion, ancho) VALUES (?, '', ?, ?, ?, ?, 10, 'helvetica', '#000000', 'normal', 'left', 50.00)");
                    
                    // Convertir rect coords a pos_x/pos_y base
                    $x = $campo['rect'][0];
                    $y = $campo['rect'][1];
                    $pag = intval($campo['pagina']);
                    
                    $stmt_c->bind_param("isidd", $plantilla_id, $campo['nombre'], $pag, $x, $y);
                    $stmt_c->execute();
                    $stmt_c->close();
                }
            }

            echo json_encode([
                "exito" => true,
                "mensaje" => "Plantilla subida con éxito.",
                "plantilla_id" => $plantilla_id,
                "es_interactivo" => $es_interactivo,
                "campos_detectados" => $analysis['campos'] ?? []
            ]);
            break;

        case 'get_plantilla_detalle':
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) {
                throw new Exception("ID de plantilla no válido.");
            }

            // Obtener cabecera
            $stmt = $db->prepare("SELECT * FROM pdf_plantillas WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $plantilla = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$plantilla) {
                throw new Exception("Plantilla no encontrada.");
            }

            // Obtener campos mapeados
            $stmt = $db->prepare("SELECT * FROM pdf_campos WHERE plantilla_id = ? ORDER BY id ASC");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $campos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            echo json_encode([
                "exito" => true,
                "plantilla" => $plantilla,
                "campos" => $campos
            ]);
            break;

        case 'guardar_mapeo':
            $input = json_decode(file_get_contents('php://input'), true);
            $plantilla_id = (int)($input['plantilla_id'] ?? 0);
            $campos = $input['campos'] ?? [];

            if ($plantilla_id <= 0) {
                throw new Exception("ID de plantilla no válido.");
            }

            // Limpiar mapeos anteriores
            $stmt = $db->prepare("DELETE FROM pdf_campos WHERE plantilla_id = ?");
            $stmt->bind_param("i", $plantilla_id);
            $stmt->execute();
            $stmt->close();

            // Insertar nuevos mapeos
            foreach ($campos as $campo) {
                $variable = trim($campo['variable'] ?? '');
                $nombre_campo_pdf = trim($campo['nombre_campo_pdf'] ?? '');
                $pagina = intval($campo['pagina'] ?? 1);
                $pos_x = floatval($campo['pos_x'] ?? 0);
                $pos_y = floatval($campo['pos_y'] ?? 0);
                $font_size = intval($campo['font_size'] ?? 10);
                $font_family = trim($campo['font_family'] ?? 'helvetica');
                $color = trim($campo['color'] ?? '#000000');
                $font_weight = trim($campo['font_weight'] ?? 'normal');
                $alineacion = trim($campo['alineacion'] ?? 'left');
                $ancho = floatval($campo['ancho'] ?? 50.0);
                $fondo_opaco = isset($campo['fondo_opaco']) ? intval($campo['fondo_opaco']) : 0;

                $stmt = $db->prepare("INSERT INTO pdf_campos (plantilla_id, variable, nombre_campo_pdf, pagina, pos_x, pos_y, font_size, font_family, color, font_weight, alineacion, ancho, fondo_opaco) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("issiddsssssdi", $plantilla_id, $variable, $nombre_campo_pdf, $pagina, $pos_x, $pos_y, $font_size, $font_family, $color, $font_weight, $alineacion, $ancho, $fondo_opaco);
                $stmt->execute();
                $stmt->close();
            }

            echo json_encode(["exito" => true, "mensaje" => "Mapeo guardado correctamente."]);
            break;

        case 'generar_formulario':
            $plantilla_id = (int)($_GET['plantilla_id'] ?? 0);
            if ($plantilla_id <= 0) {
                throw new Exception("ID de plantilla no válido.");
            }

            // Obtener variables de sistema mapeadas
            $stmt = $db->prepare("SELECT DISTINCT variable FROM pdf_campos WHERE plantilla_id = ? AND variable != ''");
            $stmt->bind_param("i", $plantilla_id);
            $stmt->execute();
            $mapeados = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            // Identificar qué campos no tienen variable de sistema y son adicionales
            $stmt = $db->prepare("SELECT id, nombre_campo_pdf, variable FROM pdf_campos WHERE plantilla_id = ? AND variable = ''");
            $stmt->bind_param("i", $plantilla_id);
            $stmt->execute();
            $adicionales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            echo json_encode([
                "exito" => true,
                "mapeados" => array_column($mapeados, 'variable'),
                "adicionales" => $adicionales
            ]);
            break;

        case 'llenar_pdf':
            $input = json_decode(file_get_contents('php://input'), true);
            $plantilla_id = (int)($input['plantilla_id'] ?? 0);
            $poliza_id = (int)($input['poliza_id'] ?? 0);
            $cotizacion_id = (int)($input['cotizacion_id'] ?? 0);
            $datos_manuales = $input['datos_manuales'] ?? []; // Valores de campos adicionales

            if ($plantilla_id <= 0) {
                throw new Exception("ID de plantilla no válido.");
            }

            // 1. Obtener la plantilla
            $stmt = $db->prepare("SELECT * FROM pdf_plantillas WHERE id = ?");
            $stmt->bind_param("i", $plantilla_id);
            $stmt->execute();
            $plantilla = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$plantilla) {
                throw new Exception("Plantilla no encontrada.");
            }

            // 2. Cargar campos mapeados (usar los enviados en caliente por el cliente si existen, para pruebas en tiempo real)
            $campos_mapeo = $input['campos'] ?? null;
            if (empty($campos_mapeo) || !is_array($campos_mapeo)) {
                $stmt = $db->prepare("SELECT * FROM pdf_campos WHERE plantilla_id = ?");
                $stmt->bind_param("i", $plantilla_id);
                $stmt->execute();
                $campos_mapeo = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
            }

            // 3. Resolver variables de sistema
            $variables_sistema = [];
            if ($poliza_id > 0) {
                $variables_sistema = resolverVariablesPoliza($db, $poliza_id);
            } elseif ($cotizacion_id > 0) {
                $variables_sistema = resolverVariablesCotizacion($db, $cotizacion_id);
            }

            // 4. Compilar los datos finales a rellenar
            $final_fields_data = [];
            foreach ($campos_mapeo as $campo) {
                $val = '';
                
                if (!empty($campo['variable'])) {
                    // Cargar de las variables del sistema
                    $val = $variables_sistema[$campo['variable']] ?? '';
                } else {
                    // Cargar de las respuestas manuales del formulario
                    $val = $datos_manuales[$campo['nombre_campo_pdf']] ?? $datos_manuales[$campo['id']] ?? '';
                }

                $final_fields_data[] = [
                    'nombre_campo_pdf' => $campo['nombre_campo_pdf'],
                    'variable' => $campo['variable'],
                    'value' => $val,
                    'pagina' => $campo['pagina'],
                    'pos_x' => $campo['pos_x'],
                    'pos_y' => $campo['pos_y'],
                    'font_size' => $campo['font_size'],
                    'font_family' => $campo['font_family'],
                    'color' => $campo['color'],
                    'font_weight' => $campo['font_weight'],
                    'alineacion' => $campo['alineacion'],
                    'ancho' => $campo['ancho'],
                    'fondo_opaco' => isset($campo['fondo_opaco']) ? intval($campo['fondo_opaco']) : 0
                ];
            }

            // 5. Escribir JSON temporal
            $dir_temp = dirname(__FILE__) . '/../../uploads/temp';
            if (!file_exists($dir_temp)) {
                mkdir($dir_temp, 0777, true);
            }
            $temp_json_path = $dir_temp . '/fill_' . time() . '_' . rand(1000, 9999) . '.json';
            file_put_contents($temp_json_path, json_encode($final_fields_data, JSON_UNESCAPED_UNICODE));

            // 6. Preparar directorios de salida
            $dir_out = dirname(__FILE__) . '/../../uploads/pdfs_aseguradoras';
            if (!file_exists($dir_out)) {
                mkdir($dir_out, 0777, true);
            }
            $out_filename = 'aseg_filled_' . time() . '_' . rand(1000, 9999) . '.pdf';
            $output_path = $dir_out . '/' . $out_filename;

            // 7. Ejecutar Python Llenador
            $python_exe = 'python';
            $script_path = dirname(__FILE__) . '/../python/pdf_extractor.py';
            $template_full_path = dirname(__FILE__) . '/../../' . $plantilla['archivo_base'];

            $cmd = "$python_exe " . escapeshellarg($script_path) . " fill " 
                   . escapeshellarg($template_full_path) . " " 
                   . escapeshellarg($output_path) . " " 
                   . escapeshellarg($temp_json_path) . " " 
                   . escapeshellarg($plantilla['ancho_mm']) . " " 
                   . escapeshellarg($plantilla['alto_mm']);

            $output = shell_exec($cmd);
            
            // Limpiar JSON temporal
            if (file_exists($temp_json_path)) {
                unlink($temp_json_path);
            }

            $res_python = json_decode($output, true);

            if ($res_python && isset($res_python['exito']) && $res_python['exito']) {
                // Registrar log de auditoría usando logAudit del sistema
                $detalles_log = [
                    "plantilla_id" => $plantilla_id,
                    "poliza_id" => $poliza_id,
                    "cotizacion_id" => $cotizacion_id,
                    "archivo_generado" => $out_filename
                ];
                logAudit(
                    $usuario_id,
                    'llenar_pdf',
                    'pdf_modeler',
                    'autollenado_pdf',
                    "Autocompletado de PDF oficial para plantilla ID: $plantilla_id, archivo: $out_filename",
                    'exitoso',
                    null,
                    'pdf_plantillas',
                    $plantilla_id,
                    null,
                    $detalles_log
                );

                $download_url = 'uploads/pdfs_aseguradoras/' . $out_filename;
                echo json_encode([
                    "exito" => true,
                    "mensaje" => "PDF autocompletado con éxito.",
                    "pdf_url" => $download_url
                ]);
            } else {
                throw new Exception("Error en el procesador Python: " . ($res_python['mensaje'] ?? $output));
            }
            break;

        default:
            throw new Exception("Acción no definida.");
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(["exito" => false, "mensaje" => $e->getMessage()]);
}
?>
