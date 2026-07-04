<?php
/**
 * API Pública: Procesamiento de Venta Rápida (Serverless Checkout)
 * MAS QUE FIANZAS - Core Asegurador v3.0
 */

if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
}

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/ClienteManager.php';
require_once dirname(__DIR__) . '/PolizaManager.php';
require_once dirname(__DIR__) . '/NCFManager.php';

$db = Database::getInstance()->getConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? $_REQUEST['action'] ?? 'cargar_checkout';

try {
    switch ($action) {
        case 'cargar_checkout':
            $ref = trim($_GET['ref'] ?? '');
            if (empty($ref)) {
                throw new Exception("Referencia de enlace de venta requerida");
            }

            // 1. Obtener enlace
            $stmt = $db->prepare("SELECT * FROM enlaces_venta_online WHERE codigo_enlace = ? AND estado = 'activo' LIMIT 1");
            $stmt->bind_param("s", $ref);
            $stmt->execute();
            $enlace = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$enlace) {
                throw new Exception("El enlace de venta online no es válido o ha sido desactivado.");
            }

            // Validar expiración si tiene (vence al final del día indicado, 23:59:59)
            if (!empty($enlace['fecha_expiracion'])) {
                $fecha_limite = $enlace['fecha_expiracion'];
                if (strlen($fecha_limite) === 10) {
                    $fecha_limite .= ' 23:59:59';
                } elseif (str_contains($fecha_limite, '00:00:00')) {
                    $fecha_limite = str_replace('00:00:00', '23:59:59', $fecha_limite);
                }
                if (strtotime($fecha_limite) < time()) {
                    // Actualizar estado a inactivo en background
                    $db->query("UPDATE enlaces_venta_online SET estado = 'inactivo' WHERE id = " . $enlace['id']);
                    throw new Exception("El enlace de venta online ha expirado.");
                }
            }

            // 2. Incrementar contador de vistas
            $db->query("UPDATE enlaces_venta_online SET vistas = vistas + 1 WHERE id = " . $enlace['id']);

            // 3. Obtener vendedor creador
            $stmt_u = $db->prepare("SELECT id, nombre, apellido, email FROM usuarios WHERE id = ? LIMIT 1");
            $stmt_u->bind_param("i", $enlace['usuario_id']);
            $stmt_u->execute();
            $vendedor = $stmt_u->get_result()->fetch_assoc();
            $stmt_u->close();

            echo json_encode([
                "exito" => true,
                "enlace" => [
                    "id" => (int)$enlace['id'],
                    "aseguradora" => $enlace['aseguradora'],
                    "ramo" => $enlace['ramo'],
                    "descripcion" => $enlace['descripcion'],
                    "descuento_aplicado" => (float)$enlace['descuento_aplicado']
                ],
                "vendedor" => $vendedor
            ]);
            break;

        case 'procesar_pago':
            $ref = trim($_POST['ref'] ?? '');
            
            // Datos del asegurado
            $nombre = trim($_POST['nombre_razon_social'] ?? '');
            $rnc = trim($_POST['rnc'] ?? '');
            $correo = trim($_POST['correo'] ?? '');
            $telefono = trim($_POST['telefono'] ?? '');
            $direccion = trim($_POST['direccion'] ?? 'Dirección Venta Online');

            // Datos del vehículo
            $placa = trim($_POST['placa'] ?? '');
            $matricula = trim($_POST['matricula'] ?? '');
            $chasis = trim($_POST['chasis'] ?? '');
            $motor = trim($_POST['motor'] ?? '');
            $marca = trim($_POST['marca'] ?? '');
            $modelo = trim($_POST['modelo'] ?? '');
            $anio = trim($_POST['anio'] ?? '');
            $color = trim($_POST['color'] ?? '');
            $tipo_vehiculo = trim($_POST['tipo_vehiculo'] ?? 'Sedan');
            
            // Método de pago
            $metodo_pago = trim($_POST['metodo_pago'] ?? 'tarjeta'); // 'tarjeta' o 'transferencia'

            if (empty($ref) || empty($nombre) || empty($rnc) || empty($correo) || empty($placa) || empty($chasis) || empty($marca) || empty($modelo) || empty($matricula)) {
                throw new Exception("Por favor complete todos los datos requeridos del asegurado y del vehículo (incluyendo Placa y Matrícula).");
            }

            if ($metodo_pago === 'transferencia' && (empty($_FILES['comprobante']) || $_FILES['comprobante']['error'] !== UPLOAD_ERR_OK)) {
                throw new Exception("Es obligatorio subir el comprobante de transferencia para esta opción de pago.");
            }

            // 1. Obtener Enlace
            $stmt = $db->prepare("SELECT * FROM enlaces_venta_online WHERE codigo_enlace = ? AND estado = 'activo' LIMIT 1");
            $stmt->bind_param("s", $ref);
            $stmt->execute();
            $enlace = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$enlace) {
                throw new Exception("Enlace de venta inválido o inactivo.");
            }

            $vendedor_id = (int)$enlace['usuario_id'];

            $db->begin_transaction();

            // 2. Gestionar Cliente (buscar por cédula/RNC)
            $stmt_cli = $db->prepare("SELECT id FROM clientes WHERE cedula = ? LIMIT 1");
            $stmt_cli->bind_param("s", $rnc);
            $stmt_cli->execute();
            $cli_res = $stmt_cli->get_result()->fetch_assoc();
            $stmt_cli->close();

            $cliente_id = null;
            if ($cli_res) {
                $cliente_id = (int)$cli_res['id'];
            } else {
                // Crear cliente
                $cliManager = new ClienteManager();
                $res_c = $cliManager->crearCliente([
                    'rnc' => $rnc,
                    'nombre_razon_social' => $nombre,
                    'tipo_persona' => 'Fisica',
                    'correo' => $correo,
                    'telefono' => $telefono,
                    'direccion' => $direccion,
                    'estatus' => 'activo',
                    'creado_por' => $vendedor_id
                ]);
                if (!$res_c['exito']) {
                    throw new Exception("Error al dar de alta al cliente: " . $res_c['mensaje']);
                }
                $cliente_id = (int)$res_c['id'];
            }

            // ==================================================================
            // VALIDACIÓN ANTI-DUPLICIDAD (Políticas NOFTRAB y VAF)
            // ==================================================================
            $stmt_dup = $db->prepare("
                SELECT p.numero_poliza, p.estado 
                FROM polizas p 
                JOIN vehiculos v ON p.vehiculo_id = v.id 
                WHERE p.cliente_id = ? AND (v.chasis = ? OR v.placa = ?) 
                AND p.estado IN ('activa', 'pendiente_pago') 
                LIMIT 1
            ");
            $stmt_dup->bind_param("iss", $cliente_id, $chasis, $placa);
            $stmt_dup->execute();
            $dup_res = $stmt_dup->get_result()->fetch_assoc();
            $stmt_dup->close();

            if ($dup_res) {
                $estado_txt = strtoupper($dup_res['estado']);
                throw new Exception("ALERTA ANTI-DUPLICIDAD (NOFTRAB/VAF): El cliente actual ya tiene el vehículo con placa $placa y chasis $chasis asegurado bajo la póliza {$dup_res['numero_poliza']} (Estado: $estado_txt). Por reglas de negocio, no se permite que un mismo cliente tenga más de una póliza activa/pendiente para el mismo vehículo.");
            }

            // 3. Emitir Póliza usando el PolizaManager
            $polizaManager = new PolizaManager();

            // ------------------------------------------------------------------
            // Calcular prima REAL desde el tarifario oficial (tarifas_seguro)
            // ------------------------------------------------------------------
            // Detectar tipo de vehículo por palabras clave en modelo/marca
            $tipo_tarifa = 'AUTOMOVILES';
            $modelo_lower = strtolower($modelo . ' ' . $marca);
            $jeep_keywords = ['jeep', 'sportage', 'tucson', 'rav4', 'suv', 'crv', 'cr-v', 'santa fe',
                              'santa', 'pilot', 'highlander', 'blazer', 'territory', 'escape',
                              'explorer', 'renegade', 'compass', 'cherokee', 'forester', 'outback'];
            foreach ($jeep_keywords as $kw) {
                if (str_contains($modelo_lower, $kw)) {
                    $tipo_tarifa = 'JEEP';
                    break;
                }
            }

            // Resolver compania_id desde fianza_aseguradoras
            $stmt_ins = $db->prepare("SELECT id FROM fianza_aseguradoras WHERE LOWER(nombre) LIKE ? OR LOWER(codigo) LIKE ? LIMIT 1");
            $ins_like = '%' . strtolower($enlace['aseguradora']) . '%';
            $stmt_ins->bind_param("ss", $ins_like, $ins_like);
            $stmt_ins->execute();
            $ins_res = $stmt_ins->get_result()->fetch_assoc();
            $stmt_ins->close();
            $compania_id = $ins_res ? (int)$ins_res['id'] : null;

            // Consultar tarifa base real (primero por compañía, luego global)
            $base_prima = 0;
            if ($compania_id) {
                $stmt_tar = $db->prepare(
                    "SELECT tarifa_base FROM tarifas_seguro 
                     WHERE compania_id = ? AND tipo = ? AND uso = 'PRIVADO' 
                     ORDER BY id DESC LIMIT 1"
                );
                $stmt_tar->bind_param("is", $compania_id, $tipo_tarifa);
                $stmt_tar->execute();
                $tar_res = $stmt_tar->get_result()->fetch_assoc();
                $stmt_tar->close();
                if ($tar_res) {
                    $base_prima = floatval($tar_res['tarifa_base']);
                }
            }
            // Fallback: tarifa global (compania_id IS NULL)
            if ($base_prima <= 0) {
                $stmt_tar2 = $db->prepare(
                    "SELECT tarifa_base FROM tarifas_seguro 
                     WHERE compania_id IS NULL AND tipo = ? AND uso = 'PRIVADO' 
                     ORDER BY id LIMIT 1"
                );
                $stmt_tar2->bind_param("s", $tipo_tarifa);
                $stmt_tar2->execute();
                $tar_res2 = $stmt_tar2->get_result()->fetch_assoc();
                $stmt_tar2->close();
                $base_prima = $tar_res2 ? floatval($tar_res2['tarifa_base']) : 15000.00;
            }

            // En tarifas_seguro el tarifa_base ya incluye impuestos (es el total)
            // Aplicar descuento del enlace sobre ese precio total
            $descuento = (float)$enlace['descuento_aplicado'];
            $prima_total = $base_prima * (1 - ($descuento / 100));

            $datos_poliza = [
                'cliente_id' => $cliente_id,
                'emitida_por' => $vendedor_id,
                'tipo_seguro' => $enlace['ramo'],
                'tipo_poliza' => 'Individual',
                'ramo' => $enlace['ramo'],
                'aseguradora' => $enlace['aseguradora'],
                'perfil_cobertura' => 'Seguro de Ley',
                'prima_total' => $prima_total,
                'cuota_total' => 1,
                'fecha_vencimiento' => date('Y-m-d', strtotime('+1 year')),
                'estado' => ($metodo_pago === 'transferencia' ? 'pendiente_pago' : 'activa'),
                'vehiculo' => [
                    'placa' => $placa,
                    'matricula' => $matricula,
                    'chasis' => $chasis,
                    'motor' => $motor,
                    'marca' => $marca,
                    'modelo' => $modelo,
                    'anio' => $anio,
                    'color' => $color,
                    'tipo_vehiculo' => $tipo_vehiculo,
                    'uso' => 'PRIVADO',
                    'capacidad' => '5 pasajeros',
                    'valor_comercial' => 500000.00
                ]
            ];

            $res_em = $polizaManager->emitirPoliza($datos_poliza);
            if (!$res_em['exito']) {
                throw new Exception("Error al emitir la póliza: " . $res_em['mensaje']);
            }

            $poliza_id = (int)$res_em['id'];
            $num_poliza = $res_em['numero'];

            // 4. Generar y asignar NCF de consumidor final (B02)
            $stmt_cfg = $db->query("SELECT valor_config FROM configuracion_sistema WHERE clave_config = 'GENERAR_NCF_AUTOMATICO'");
            $cfg_res = $stmt_cfg ? $stmt_cfg->fetch_assoc() : null;
            $generar_ncf_auto = ($cfg_res && $cfg_res['valor_config'] == '1');

            $ncf = null;
            if ($generar_ncf_auto) {
                $ncfManager = new \MQF\Finance\NCFManager($db);
                $ncf = $ncfManager->generarSiguiente('B02');
            }
            
            if ($ncf) {
                $stmt_up_ncf = $db->prepare("UPDATE polizas SET numero_poliza_aseguradora = ? WHERE id = ?");
                $stmt_up_ncf->bind_param("si", $ncf, $poliza_id);
                $stmt_up_ncf->execute();
                $stmt_up_ncf->close();
            }

            // 5. Procesar comprobante físico si es transferencia
            $comprobante_url = null;
            if ($metodo_pago === 'transferencia') {
                if (!empty($_FILES['comprobante']) && $_FILES['comprobante']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['comprobante'];
                    // Directorio del comprobante
                    $upload_dir = dirname(__DIR__, 2) . '/frontend/assets/uploads/comprobantes';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $filename = 'comp_' . $poliza_id . '_' . time() . '.' . $ext;
                    $dest = $upload_dir . '/' . $filename;
                    if (move_uploaded_file($file['tmp_name'], $dest)) {
                        $comprobante_url = '/PLATAFORMA_INTEGRADA/frontend/assets/uploads/comprobantes/' . $filename;
                    }
                }
            }

            // Actualizar método de pago y comprobante en la tabla polizas
            $stmt_up_p = $db->prepare("UPDATE polizas SET metodo_pago = ?, comprobante_url = ? WHERE id = ?");
            $stmt_up_p->bind_param("ssi", $metodo_pago, $comprobante_url, $poliza_id);
            $stmt_up_p->execute();
            $stmt_up_p->close();

            // 6. Incrementar conversiones del enlace
            $db->query("UPDATE enlaces_venta_online SET conversiones = conversiones + 1 WHERE id = " . $enlace['id']);

            $db->commit();

            echo json_encode([
                "exito" => true,
                "mensaje" => ($metodo_pago === 'transferencia') 
                    ? "Tu póliza se ha emitido y se encuentra PENDIENTE DE VALIDACIÓN tras verificar la transferencia."
                    : "¡Emisión exitosa! Tu póliza ha sido pagada y emitida.",
                "poliza_id" => $poliza_id,
                "numero_poliza" => $num_poliza,
                "ncf" => $ncf ?: 'N/D',
                "pendiente" => ($metodo_pago === 'transferencia')
            ]);
            break;

        case 'descargar_pdf':
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) throw new Exception("ID de póliza requerido");

            $stmt = $db->prepare("SELECT p.*, 
                                         c.nombre as cliente_nombre, c.cedula as cliente_cedula, c.email as cliente_email, c.telefono as cliente_telefono,
                                         v.placa as vehiculo_placa, v.matricula as vehiculo_matricula, v.marca as vehiculo_marca, v.modelo as vehiculo_modelo, v.chasis as vehiculo_chasis, v.anio as vehiculo_anio, v.color as vehiculo_color, v.tipo_vehiculo as vehiculo_tipo,
                                         fa.logo_url as aseguradora_logo
                                  FROM polizas p 
                                  LEFT JOIN clientes c ON p.cliente_id = c.id 
                                  LEFT JOIN vehiculos v ON p.vehiculo_id = v.id
                                  LEFT JOIN fianza_aseguradoras fa ON LOWER(fa.nombre) LIKE CONCAT('%', LOWER(p.aseguradora), '%')
                                  WHERE p.id = ? LIMIT 1");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $poliza = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$poliza) throw new Exception("Póliza no encontrada");

            // Cargar FPDF
            if (!class_exists('FPDF')) {
                @require_once dirname(__DIR__) . '/libs/fpdf/fpdf.php';
            }

            if (!class_exists('FPDF')) {
                throw new Exception("Librería PDF no disponible");
            }

            // Fetch dynamic validation QR code (encoded URL pointing to verify-poliza.html)
            $port = isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] !== '80' && $_SERVER['SERVER_PORT'] !== '443' ? ':' . $_SERVER['SERVER_PORT'] : '';
            $verificationUrl = "http://" . ($_SERVER['SERVER_NAME'] ?? 'localhost') . $port . "/PLATAFORMA_INTEGRADA/frontend/verificar-poliza.html?n=" . urlencode($poliza['numero_poliza']);
            
            $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($verificationUrl);
            $tempQr = tempnam(sys_get_temp_dir(), 'qr_') . '.png';
            
            try {
                $ch = curl_init($qrUrl);
                $fp = fopen($tempQr, 'wb');
                curl_setopt($ch, CURLOPT_FILE, $fp);
                curl_setopt($ch, CURLOPT_HEADER, 0);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 3);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                $qrLoaded = curl_exec($ch);
                curl_close($ch);
                fclose($fp);
            } catch (Exception $e) {
                $qrLoaded = false;
            }

            $pdf = new FPDF();
            $pdf->AddPage();
            
            $s = function($str) { return iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', (string)$str); };

            // Color palette definition
            $insurerName = $poliza['aseguradora'];
            $r = 0; $g = 68; $b = 136; // Multiseguros (Azul)
            if (stripos($insurerName, 'Patria') !== false) {
                $r = 180; $g = 0; $b = 0; // Seguros Patria (Rojo)
            } elseif (stripos($insurerName, 'Pep') !== false) {
                $r = 218; $g = 165; $b = 32; // Seguros Pepín (Dorado)
            } elseif (stripos($insurerName, 'Midas') !== false) {
                $r = 0; $g = 120; $b = 60; // Midas Seguros (Verde)
            }

            // Top Header Banner
            $pdf->SetFillColor($r, $g, $b);
            $pdf->Rect(10, 10, 190, 22, 'F');
            $pdf->SetTextColor(255, 255, 255);

            // Logo de aseguradora (si existe y es accesible)
            $logoLoaded = false;
            $tempLogo = null;
            $logoUrl = $poliza['aseguradora_logo'] ?? null;
            if ($logoUrl) {
                // Si es ruta relativa, convertir a ruta absoluta del servidor
                if (!str_starts_with($logoUrl, 'http')) {
                    $absLogo = realpath(dirname(__DIR__, 2) . '/' . ltrim($logoUrl, '/'));
                } else {
                    $absLogo = null;
                }
                if ($absLogo && file_exists($absLogo)) {
                    // Logo local
                    try {
                        $pdf->Image($absLogo, 12, 12, 30, 18);
                        $logoLoaded = true;
                    } catch (Exception $eL) { $logoLoaded = false; }
                } elseif (str_starts_with($logoUrl, 'http')) {
                    // Logo remoto: descargar temporal
                    $tempLogo = tempnam(sys_get_temp_dir(), 'logo_') . '.png';
                    try {
                        $chL = curl_init($logoUrl);
                        $fpL = fopen($tempLogo, 'wb');
                        curl_setopt($chL, CURLOPT_FILE, $fpL);
                        curl_setopt($chL, CURLOPT_HEADER, 0);
                        curl_setopt($chL, CURLOPT_FOLLOWLOCATION, true);
                        curl_setopt($chL, CURLOPT_TIMEOUT, 3);
                        curl_exec($chL);
                        curl_close($chL);
                        fclose($fpL);
                        $pdf->Image($tempLogo, 12, 12, 30, 18);
                        $logoLoaded = true;
                    } catch (Exception $eL) { $logoLoaded = false; }
                }
            }

            // Nombre de aseguradora en header (ajustar X si hay logo)
            $headerTextX = $logoLoaded ? 46 : 15;
            $pdf->SetFont('Arial', 'B', 13);
            $pdf->SetXY($headerTextX, 13);
            $pdf->Cell(100, 8, $s(strtoupper($insurerName)), 0, 0, 'L');
            $pdf->SetFont('Arial', 'B', 10);
            $tituloPdf = ($poliza['estado'] === 'pendiente_pago') ? 'PROPUESTA DE SEGURO (PENDIENTE DE PAGO)' : 'CERTIFICADO DE POLIZA DE VEHICULOS';
            $pdf->Cell(190 - $headerTextX + 10, 8, $s($tituloPdf), 0, 1, 'R');
            $pdf->SetXY(15, 22);
            $pdf->SetFont('Arial', '', 8);
            $pdf->Cell(180, 4, $s('Core Asegurador v3.0 - Emision de Venta Rapida'), 0, 1, 'R');

            // Limpiar logo temporal
            if ($tempLogo && file_exists($tempLogo)) { @unlink($tempLogo); }

            // Marca de agua si está pendiente
            if ($poliza['estado'] === 'pendiente_pago') {
                $pdf->SetFont('Arial', 'B', 46);
                $pdf->SetTextColor(230, 230, 230); // Muy claro
                $pdf->Text(22, 110, $s('PENDIENTE DE VALIDACION'));
                $pdf->SetTextColor(30, 41, 59); // Restaurar color texto
            }

            // Spacer
            $pdf->SetTextColor(30, 41, 59);
            $pdf->Ln(14);

            // Row 1: General Info Block (Side-by-Side: Policy info / Client info)
            // Left Column: Policy Info
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->SetFillColor(245, 247, 250);
            $pdf->Rect(10, 35, 92, 48, 'F'); // Draw left gray block background
            $pdf->SetXY(12, 37);
            $pdf->Cell(88, 5, $s('1. DETALLE DEL SEGURO'), 0, 1, 'L');
            $pdf->SetFont('Arial', '', 8);
            $pdf->SetX(12);
            $pdf->Cell(35, 5, $s('No. Póliza Interna:'), 0, 0);
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Cell(0, 5, $s($poliza['numero_poliza']), 0, 1);
            $pdf->SetFont('Arial', '', 8);
            $pdf->SetX(12);
            $pdf->Cell(35, 5, $s('No. Comprobante (NCF):'), 0, 0);
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Cell(0, 5, $s($poliza['numero_poliza_aseguradora'] ?: 'N/D'), 0, 1);
            $pdf->SetFont('Arial', '', 8);
            $pdf->SetX(12);
            $pdf->Cell(35, 5, $s('Fecha Emisión:'), 0, 0);
            $pdf->Cell(0, 5, $s(date('d/m/Y H:i:s', strtotime($poliza['fecha_emision']))), 0, 1);
            $pdf->SetX(12);
            $pdf->Cell(35, 5, $s('Fecha Vencimiento:'), 0, 0);
            $pdf->Cell(0, 5, $s(date('d/m/Y', strtotime($poliza['fecha_vencimiento']))), 0, 1);
            $pdf->SetX(12);
            $pdf->Cell(35, 5, $s('Vigencia:'), 0, 0);
            $pdf->Cell(0, 5, $s('12 Meses'), 0, 1);
            $pdf->SetX(12);
            $pdf->Cell(35, 5, $s('Estado:'), 0, 0);
            if ($poliza['estado'] === 'pendiente_pago') {
                $pdf->SetTextColor(180, 83, 9); // Amarillo/Marrón oscuro
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(0, 5, $s('PENDIENTE DE PAGO'), 0, 1);
            } else {
                $pdf->SetTextColor(22, 163, 74); // Verde
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(0, 5, $s(strtoupper($poliza['estado'])), 0, 1);
            }
            $pdf->SetTextColor(30, 41, 59);

            // Right Column: Client Info
            $pdf->Rect(108, 35, 92, 48, 'F'); // Draw right gray block background
            $pdf->SetXY(110, 37);
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(88, 5, $s('2. DATOS DEL ASEGURADO'), 0, 1, 'L');
            $pdf->SetFont('Arial', '', 8);
            $pdf->SetX(110);
            $pdf->Cell(30, 5, $s('Nombre / Razón:'), 0, 0);
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Cell(0, 5, $s($poliza['cliente_nombre']), 0, 1);
            $pdf->SetFont('Arial', '', 8);
            $pdf->SetX(110);
            $pdf->Cell(30, 5, $s('Cédula / RNC:'), 0, 0);
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Cell(0, 5, $s($poliza['cliente_cedula']), 0, 1);
            $pdf->SetFont('Arial', '', 8);
            $pdf->SetX(110);
            $pdf->Cell(30, 5, $s('Teléfono:'), 0, 0);
            $pdf->Cell(0, 5, $s($poliza['cliente_telefono']), 0, 1);
            $pdf->SetX(110);
            $pdf->Cell(30, 5, $s('Correo Electrónico:'), 0, 0);
            $pdf->Cell(0, 5, $s($poliza['cliente_email']), 0, 1);
            $pdf->SetX(110);
            $pdf->Cell(30, 5, $s('Intermediario:'), 0, 0);
            $pdf->Cell(0, 5, $s('MÁS QUE FIANZAS SRL'), 0, 1);

            // Spacer
            $pdf->Ln(8);

            // Row 2: Vehicle Details Block
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->SetFillColor(245, 247, 250);
            $pdf->Rect(10, 88, 190, 24, 'F');
            $pdf->SetXY(12, 90);
            $pdf->Cell(186, 5, $s('3. DETALLES DEL VEHÍCULO ASEGURADO'), 0, 1, 'L');
            $pdf->SetFont('Arial', '', 8);
            $pdf->SetX(12);
            $pdf->Cell(25, 4, $s('Marca / Modelo:'), 0, 0);
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Cell(70, 4, $s($poliza['vehiculo_marca'] . ' ' . $poliza['vehiculo_modelo']), 0, 0);
            $pdf->SetFont('Arial', '', 8);
            $pdf->Cell(20, 4, $s('Año:'), 0, 0);
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Cell(25, 4, $s($poliza['vehiculo_anio']), 0, 0);
            $pdf->SetFont('Arial', '', 8);
            $pdf->Cell(20, 4, $s('Placa:'), 0, 0);
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Cell(0, 4, $s($poliza['vehiculo_placa']), 0, 1);
            
            $pdf->SetX(12);
            $pdf->SetFont('Arial', '', 8);
            $pdf->Cell(25, 4, $s('Chasis:'), 0, 0);
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Cell(70, 4, $s($poliza['vehiculo_chasis']), 0, 0);
            $pdf->SetFont('Arial', '', 8);
            $pdf->Cell(20, 4, $s('Color:'), 0, 0);
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Cell(25, 4, $s($poliza['vehiculo_color']), 0, 0);
            $pdf->SetFont('Arial', '', 8);
            $pdf->Cell(20, 4, $s('Matrícula:'), 0, 0);
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Cell(0, 4, $s($poliza['vehiculo_matricula'] ?: 'N/D'), 0, 1);

            // Spacer
            $pdf->Ln(6);

            // Row 3: Coberturas y Límites Table
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(0, 6, $s('4. COBERTURAS Y LÍMITES (SEGURO DE LEY OBLIGATORIO)'), 'B', 1);
            $pdf->Ln(2);

            $pdf->SetFont('Arial', 'B', 8);
            $pdf->SetFillColor(230, 235, 245);
            $pdf->Cell(130, 5, $s(' Cobertura'), 1, 0, 'L', true);
            $pdf->Cell(60, 5, $s(' Suma Asegurada (RD$)'), 1, 1, 'R', true);

            $coberturas = [
                ['Daños a la Propiedad Ajena', '100,000.00'],
                ['Lesiones Corporales o Muerte a una Persona', '100,000.00'],
                ['Lesiones Corporales o Muerte a dos o más Personas', '200,000.00'],
                ['Lesiones Corporales o Muerte a un Pasajero', '100,000.00'],
                ['Lesiones Corporales o Muerte a dos o más Pasajeros', '200,000.00'],
                ['Riesgos del Conductor', '20,000.00'],
                ['Fianza Judicial', '20,000.00'],
                ['Su Asistencia Vial', 'INCLUIDO'],
                ['Casa del Conductor / Centro del Automovilista', 'INCLUIDO']
            ];

            $pdf->SetFont('Arial', '', 8);
            foreach ($coberturas as $c) {
                $pdf->Cell(130, 5, $s(' ' . $c[0]), 1, 0, 'L');
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell(60, 5, $s($c[1] === 'INCLUIDO' ? 'INCLUIDO' : 'RD$ ' . $c[1]), 1, 1, 'R');
                $pdf->SetFont('Arial', '', 8);
            }

            // Spacer
            $pdf->Ln(4);

            // Row 4: Totales & Breakdown (Bottom Left: Verification QR / Bottom Right: Invoice details)
            // Draw QR box on left
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(92, 5, $s('5. VALIDACIÓN DIGITAL DE LA PÓLIZA'), 'B', 0);
            $pdf->Cell(6, 5, '', 0, 0); // spacer
            $pdf->Cell(92, 5, $s('6. DETALLE DE PRIMAS Y FACTURACIÓN'), 'B', 1);

            $pdf->Ln(2);

            // Render QR Code image
            $qrX = 15;
            $qrY = $pdf->GetY();
            
            if ($qrLoaded && !empty($tempQr)) {
                $pdf->Image($tempQr, $qrX, $qrY, 28, 28, 'PNG');
            } else {
                $pdf->Rect($qrX, $qrY, 28, 28);
                $pdf->SetFont('Arial', '', 6);
                $pdf->SetXY($qrX, $qrY + 12);
                $pdf->Cell(28, 4, $s('[QR CODE]'), 0, 1, 'C');
            }

            $pdf->SetXY(47, $qrY);
            $pdf->SetFont('Arial', '', 7);
            $pdf->SetTextColor(70, 80, 95);
            $pdf->MultiCell(55, 3.5, $s("Escanee este codigo QR con su dispositivo movil para validar el estado de vigencia de esta poliza en tiempo real en nuestra plataforma. \n\nNo. Validacion: " . substr($poliza['numero_poliza'], 4)), 0, 'L');
            
            // Render Invoice right side
            $pdf->SetXY(108, $qrY);
            $pdf->SetTextColor(30, 41, 59);
            $pdf->SetFont('Arial', '', 8);
            $pdf->Cell(45, 5, $s('Prima Neta Certificado:'), 0, 0);
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Cell(47, 5, $s('RD$ ' . number_format($poliza['prima_neta'], 2)), 0, 1, 'R');
            
            $pdf->SetX(108);
            $pdf->SetFont('Arial', '', 8);
            $pdf->Cell(45, 5, $s('Impuestos (16% ISC):'), 0, 0);
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Cell(47, 5, $s('RD$ ' . number_format($poliza['itbis'], 2)), 0, 1, 'R');
            
            $pdf->SetX(108);
            $pdf->SetFont('Arial', '', 8);
            $pdf->Cell(45, 5, $s('Servicios de Emergencia:'), 0, 0);
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Cell(47, 5, $s('RD$ 0.00 (INCLUIDO)'), 0, 1, 'R');

            $pdf->SetX(108);
            $pdf->Line(108, $pdf->GetY(), 200, $pdf->GetY());
            
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetTextColor($r, $g, $b);
            $pdf->Cell(45, 8, $s('PRIMA TOTAL FACTURA:'), 0, 0);
            $pdf->Cell(47, 8, $s('RD$ ' . number_format($poliza['prima_total'], 2)), 0, 1, 'R');

            // Footer of page 1
            $pdf->SetXY(10, 275);
            $pdf->SetTextColor(100, 116, 139);
            $pdf->SetFont('Arial', 'I', 7);
            $pdf->Cell(0, 4, $s('Este certificado de cobertura ha sido emitido de manera automatica bajo las regulaciones de la Superintendencia de Seguros.'), 0, 1, 'C');

            // PAGE 2: MARBETE DE SEGURO
            $pdf->AddPage();

            // Section 1: Conditions info
            $pdf->SetTextColor(30, 41, 59);
            $pdf->SetFont('Arial', 'B', 11);
            $pdf->Cell(0, 7, $s('CONDICIONES GENERALES Y FORMATO DE MARBETE'), 'B', 1);
            $pdf->Ln(3);

            $pdf->SetFont('Arial', '', 8);
            $pdf->MultiCell(0, 3.8, $s("La cobertura del presente certificado se rige por las Condiciones Generales de la Poliza de Automoviles aprobadas por la Superintendencia de Seguros de la Republica Dominicana. El Asegurado se compromete al cumplimiento de los terminos estipulados, incluyendo la notificacion inmediata del siniestro en un plazo no mayor de setenta y dos (72) horas. \n\nEn testimonio de lo cual, la Compañia de Seguros firma la presente poliza en Santo Domingo, Distrito Nacional."), 0, 'L');
            
            $pdf->Ln(6);
            
            // Signature lines
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Cell(95, 4, $s('Firma Cliente / Asegurado'), 0, 0, 'C');
            $pdf->Cell(95, 4, $s('Compañía Aseguradora Emisora'), 0, 1, 'C');
            $pdf->Ln(12);
            $pdf->SetFont('Arial', '', 7);
            $pdf->Cell(95, 4, $s('__________________________________'), 0, 0, 'C');
            $pdf->Cell(95, 4, $s('__________________________________'), 0, 1, 'C');
            $pdf->Cell(95, 4, $s('Firma del Asegurado'), 0, 0, 'C');
            $pdf->Cell(95, 4, $s('Firma Autorizada'), 0, 1, 'C');

            $pdf->Ln(15);

            // ==========================================
            // MARBETE DE SEGURO (Front Frame)
            // ==========================================
            $mX = 15;
            $mY = $pdf->GetY();
            $mW = 180;
            $mH = 75;

            // Draw outer frame box with dynamic colors
            $pdf->SetDrawColor($r, $g, $b);
            $pdf->SetLineWidth(0.8);
            $pdf->SetFillColor(255, 255, 255);
            $pdf->Rect($mX, $mY, $mW, $mH, 'DF');

            // Draw colored header inside marbete
            $pdf->SetFillColor($r, $g, $b);
            $pdf->Rect($mX, $mY, $mW, 12, 'F');
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('Arial', 'B', 11);
            $pdf->SetXY($mX + 4, $mY + 2);
            $pdf->Cell(100, 8, $s(strtoupper($insurerName)), 0, 0, 'L');
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Cell(72, 8, $s('MARBETE DE SEGURO - VEHICULOS'), 0, 1, 'R');

            // Reset text colors
            $pdf->SetTextColor(30, 41, 59);
            
            // Left block (Vehicle & Policy details)
            $pdf->SetXY($mX + 4, $mY + 15);
            $pdf->SetFont('Arial', '', 7);
            $pdf->Cell(20, 4.5, $s('Póliza:'), 0, 0);
            $pdf->SetFont('Arial', 'B', 7);
            $pdf->Cell(60, 4.5, $s($poliza['numero_poliza']), 0, 1);
            
            $pdf->SetX($mX + 4);
            $pdf->SetFont('Arial', '', 7);
            $pdf->Cell(20, 4.5, $s('Vigencia:'), 0, 0);
            $pdf->SetFont('Arial', 'B', 7);
            $pdf->Cell(60, 4.5, $s(date('d/m/Y', strtotime($poliza['fecha_emision'])) . ' al ' . date('d/m/Y', strtotime($poliza['fecha_vencimiento']))), 0, 1);

            $pdf->SetX($mX + 4);
            $pdf->SetFont('Arial', '', 7);
            $pdf->Cell(20, 4.5, $s('Asegurado:'), 0, 0);
            $pdf->SetFont('Arial', 'B', 7);
            $pdf->Cell(60, 4.5, $s($poliza['cliente_nombre']), 0, 1);

            $pdf->SetX($mX + 4);
            $pdf->SetFont('Arial', '', 7);
            $pdf->Cell(20, 4.5, $s('Vehículo:'), 0, 0);
            $pdf->SetFont('Arial', 'B', 7);
            $pdf->Cell(60, 4.5, $s($poliza['vehiculo_marca'] . ' ' . $poliza['vehiculo_modelo'] . ' (' . $poliza['vehiculo_anio'] . ')'), 0, 1);

            $pdf->SetX($mX + 4);
            $pdf->SetFont('Arial', '', 7);
            $pdf->Cell(20, 4.5, $s('Chasis:'), 0, 0);
            $pdf->SetFont('Arial', 'B', 7);
            $pdf->Cell(60, 4.5, $s($poliza['vehiculo_chasis']), 0, 1);

            $pdf->SetX($mX + 4);
            $pdf->SetFont('Arial', '', 7);
            $pdf->Cell(20, 4.5, $s('Placa / Mat:'), 0, 0);
            $pdf->SetFont('Arial', 'B', 7);
            $pdf->Cell(60, 4.5, $s($poliza['vehiculo_placa'] . ' / ' . ($poliza['vehiculo_matricula'] ?: 'N/D')), 0, 1);

            // Right block (Coverages info & Mini QR)
            $pdf->SetXY($mX + 90, $mY + 15);
            $pdf->SetFont('Arial', '', 7);
            $pdf->Cell(25, 4.5, $s('Fianza Judicial:'), 0, 0);
            $pdf->SetFont('Arial', 'B', 7);
            $pdf->Cell(35, 4.5, $s('RD$ 20,000.00'), 0, 1);

            $pdf->SetXY($mX + 90, $mY + 19.5);
            $pdf->SetFont('Arial', '', 7);
            $pdf->Cell(25, 4.5, $s('Asistencia Vial:'), 0, 0);
            $pdf->SetFont('Arial', 'B', 7);
            $pdf->Cell(35, 4.5, $s('SU ASISTENCIA VIAL'), 0, 1);

            $pdf->SetXY($mX + 90, $mY + 24);
            $pdf->SetFont('Arial', '', 7);
            $pdf->Cell(25, 4.5, $s('Casa Contratada:'), 0, 0);
            $pdf->SetFont('Arial', 'B', 7);
            $pdf->Cell(35, 4.5, $s('CMA / CENTRO AUTO'), 0, 1);

            $pdf->SetXY($mX + 90, $mY + 28.5);
            $pdf->SetFont('Arial', '', 7);
            $pdf->Cell(25, 4.5, $s('Plan:'), 0, 0);
            $pdf->SetFont('Arial', 'B', 7);
            // Plan name dinamico por aseguradora
            if (stripos($insurerName, 'Pep') !== false) {
                $planName = 'PEPIN BASICO PLAN DE LEY';
            } elseif (stripos($insurerName, 'Midas') !== false) {
                $planName = 'MIDAS BASIC PLAN DE LEY';
            } elseif (stripos($insurerName, 'Patria') !== false) {
                $planName = 'PATRIA BASIC PLAN DE LEY';
            } else {
                $planName = 'MULTI BASIC PLAN DE LEY';
            }
            $pdf->Cell(35, 4.5, $s($planName), 0, 1);

            $pdf->SetXY($mX + 90, $mY + 33);
            $pdf->SetFont('Arial', '', 7);
            $pdf->Cell(25, 4.5, $s('Uso / Deduc:'), 0, 0);
            $pdf->SetFont('Arial', 'B', 7);
            $pdf->Cell(35, 4.5, $s('PRIVADO / SIN DEDUC.'), 0, 1);

            // Render mini validation QR inside Marbete box
            $mqrX = $mX + 152;
            $mqrY = $mY + 15;
            if ($qrLoaded && !empty($tempQr)) {
                $pdf->Image($tempQr, $mqrX, $mqrY, 22, 22, 'PNG');
            } else {
                $pdf->Rect($mqrX, $mqrY, 22, 22);
            }
            $pdf->SetXY($mqrX, $mqrY + 23);
            $pdf->SetFont('Arial', '', 5);
            $pdf->Cell(22, 4, $s('Verificar'), 0, 1, 'C');

            // Text on back instructions (drawn under the marbete box)
            $pdf->SetXY($mX + 2, $mY + 45);
            $pdf->SetFont('Arial', 'B', 6);
            $pdf->Cell(0, 4.5, $s('INSTRUCCIONES EN CASO DE SINIESTROS / CENTRO DE ASISTENCIA:'), 0, 1, 'L');
            
            $pdf->SetX($mX + 2);
            $pdf->SetFont('Arial', '', 5.5);
            $pdf->MultiCell($mW - 4, 3, $s("1. En caso de accidente para levantar el Acta Policial y asistencia, favor comunicarse de inmediato con: LA CASA DEL CONDUCTOR o el CENTRO DEL AUTOMOVILISTA contratado. \n2. Centro de atencion telefonica de emergencia vial: Santo Domingo (809) 565-3121 | Santiago (809) 583-3121 (Soporte de grua, cambio de neumaticos, gasolina y paso de corriente 24/7). \n3. No admita responsabilidad ante terceros y de aviso de inmediato a MÁS QUE FIANZAS SRL o directamente a la Aseguradora."));

            // Delete temp QR file if created
            if (!empty($tempQr) && file_exists($tempQr)) {
                @unlink($tempQr);
            }

            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="Poliza_' . $poliza['numero_poliza'] . '.pdf"');
            $pdf->Output('I', 'Poliza_' . $poliza['numero_poliza'] . '.pdf');
            exit;

        case 'verificar_publico':
            $n = trim($_GET['n'] ?? '');
            if (empty($n)) {
                throw new Exception("Número de póliza requerido");
            }
            
            $stmt = $db->prepare("SELECT p.numero_poliza, p.estado, p.fecha_emision, p.fecha_vencimiento, p.aseguradora, p.ramo,
                                         c.nombre as cliente_nombre,
                                         v.placa as vehiculo_placa, v.matricula as vehiculo_matricula, v.marca as vehiculo_marca, v.modelo as vehiculo_modelo, v.anio as vehiculo_anio, v.color as vehiculo_color
                                  FROM polizas p
                                  LEFT JOIN clientes c ON p.cliente_id = c.id
                                  LEFT JOIN vehiculos v ON p.vehiculo_id = v.id
                                  WHERE p.numero_poliza = ? LIMIT 1");
            $stmt->bind_param("s", $n);
            $stmt->execute();
            $poliza = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if (!$poliza) {
                throw new Exception("Póliza no encontrada o inválida");
            }
            
            echo json_encode([
                "exito" => true,
                "data" => [
                    "numero_poliza" => $poliza['numero_poliza'],
                    "estado" => $poliza['estado'],
                    "aseguradora" => $poliza['aseguradora'],
                    "ramo" => $poliza['ramo'],
                    "fecha_emision" => $poliza['fecha_emision'],
                    "fecha_vencimiento" => $poliza['fecha_vencimiento'],
                    "cliente_nombre" => $poliza['cliente_nombre'],
                    "vehiculo" => [
                        "placa" => $poliza['vehiculo_placa'],
                        "matricula" => $poliza['vehiculo_matricula'],
                        "marca" => $poliza['vehiculo_marca'],
                        "modelo" => $poliza['vehiculo_modelo'],
                        "anio" => $poliza['vehiculo_anio'],
                        "color" => $poliza['vehiculo_color']
                    ]
                ]
            ]);
            exit;

        case 'procesar_ocr':
            if (empty($_FILES['imagen']) || $_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("No se recibió ninguna imagen válida.");
            }
            $tipo_doc = $_POST['tipo'] ?? 'cedula'; // 'cedula' o 'matricula'
            $file = $_FILES['imagen'];
            
            // Validar formato
            $allowed = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp'];
            $mime = mime_content_type($file['tmp_name']);
            if (!in_array($mime, $allowed)) {
                throw new Exception("Formato no permitido. Use PNG, JPG o WEBP.");
            }
            
            // Codificar imagen en Base64
            $base64 = base64_encode(file_get_contents($file['tmp_name']));
            
            // Consumir Google Vision API
            $apiKey = 'AIzaSyAkWDsH18r5JmUuSyTTBz3cnpwP6pu4k1s';
            $url = "https://vision.googleapis.com/v1/images:annotate?key=" . $apiKey;
            
            $payload = json_encode([
                "requests" => [
                    [
                        "image" => [
                            "content" => $base64
                        ],
                        "features" => [
                            [
                                "type" => "TEXT_DETECTION"
                            ]
                        ]
                    ]
                ]
            ]);
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            $response = curl_exec($ch);
            
            if (curl_errno($ch)) {
                $err = curl_error($ch);
                curl_close($ch);
                throw new Exception("Error al conectar con Google Vision API: " . $err);
            }
            curl_close($ch);
            
            $resData = json_decode($response, true);
            
            // Check for root level API errors
            if (isset($resData['error']['message'])) {
                throw new Exception("Error de Google Vision API: " . $resData['error']['message']);
            }
            // Check for image-specific processing errors
            if (isset($resData['responses'][0]['error']['message'])) {
                throw new Exception("Error de Google Vision API (imagen): " . $resData['responses'][0]['error']['message']);
            }
            
            $fullText = $resData['responses'][0]['fullTextAnnotation']['text'] ?? '';
            @file_put_contents(__DIR__ . '/ocr_debug.txt', "--- OCR TYPE: " . $tipo_doc . " ---\n" . $fullText);
            
            if (empty($fullText)) {
                throw new Exception("No se pudo detectar ningún texto en la imagen. Intente con una foto más clara.");
            }
            
            // Procesamiento del texto extraído
            $resultado = [
                "full_text" => $fullText
            ];
            
            if ($tipo_doc === 'cedula') {
                // Buscar Cédula Dominicana: \d{3}-?\d{7}-?\d{1}
                if (preg_match('/\b(\d{3})-?(\d{7})-?(\d{1})\b/', $fullText, $matches)) {
                    $resultado['cedula'] = $matches[1] . '-' . $matches[2] . '-' . $matches[3];
                }
                
                // Extraer Nombre (simple parser heurístico)
                $lines = explode("\n", $fullText);
                $nombres = '';
                $apellidos = '';
                for ($i = 0; $i < count($lines); $i++) {
                    $line = trim($lines[$i]);
                    if (preg_match('/\bNOMBRE(S)?\b/i', $line)) {
                        $nombres = trim($lines[$i+1] ?? '');
                        $nombres = preg_replace('/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/', '', $nombres);
                    }
                    if (preg_match('/\bAPELLIDO(S)?\b/i', $line)) {
                        $apellidos = trim($lines[$i+1] ?? '');
                        $apellidos = preg_replace('/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/', '', $apellidos);
                    }
                }
                if (!empty($nombres) || !empty($apellidos)) {
                    $resultado['nombre'] = trim($nombres . ' ' . $apellidos);
                } else {
                    // Fallback: tratar de adivinar el nombre usando líneas de arriba
                    $nombre_fallback = [];
                    foreach (array_slice($lines, 0, 5) as $l) {
                        $l = trim($l);
                        if (strlen($l) > 6 && !preg_match('/\d/', $l) && !str_contains($l, 'REPUBLICA') && !str_contains($l, 'JUNTA') && !str_contains($l, 'ELECTORAL') && !str_contains($l, 'CEDULA')) {
                            $nombre_fallback[] = preg_replace('/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/', '', $l);
                        }
                    }
                    if (count($nombre_fallback) > 0) {
                        $resultado['nombre'] = implode(' ', $nombre_fallback);
                    }
                }
            } else {
                // Matrícula de Vehículo (DGII Dominicana)
                $lines = explode("\n", $fullText);
                
                // Buscar Placa: Suele empezar por letra seguido de 6-7 dígitos
                if (preg_match('/\b([A-Z]\d{6,7})\b/i', $fullText, $matches)) {
                    $resultado['placa'] = strtoupper($matches[1]);
                }
                
                // Buscar Matrícula / Registro: "No. 8482514"
                if (preg_match('/No\.\s*(\d{7,10})\b/i', $fullText, $matches)) {
                    $resultado['matricula'] = $matches[1];
                } elseif (preg_match('/\b(REGISTRO|MATRICULA)\b.*?\b(\d{7,10})\b/is', $fullText, $matches)) {
                    $resultado['matricula'] = $matches[2];
                }

                // 1. Extraer Marca por diccionario (bulletproof)
                $marcas_comunes = ['TOYOTA', 'HONDA', 'HYUNDAI', 'KIA', 'NISSAN', 'CHEVROLET', 'FORD', 'MAZDA', 'MITSUBISHI', 'SUZUKI', 'LEXUS', 'BMW', 'MERCEDES', 'AUDI', 'VOLKSWAGEN', 'SUBARU', 'JEEP', 'PORSCHE', 'LAND ROVER', 'CHRYSLER', 'DODGE', 'FIAT', 'PEUGEOT', 'RENAULT', 'ISUZU', 'DAIHATSU', 'VOLVO'];
                foreach ($marcas_comunes as $m) {
                    if (stripos($fullText, $m) !== false) {
                        $resultado['marca'] = $m;
                        break;
                    }
                }

                // 2. Extraer Color por diccionario (bulletproof)
                $colores_comunes = ['GRIS', 'BLANCO', 'NEGRO', 'ROJO', 'AZUL', 'PLATEADO', 'PLATA', 'VERDE', 'AMARILLO', 'DORADO', 'MARRON', 'CREMA', 'VINO'];
                foreach ($colores_comunes as $c) {
                    if (preg_match('/\b' . $c . '\b/i', $fullText)) {
                        $resultado['color'] = $c;
                        break;
                    }
                }

                // 3. Extraer Chasis Alfanumérico (Debe tener letras y números mezclados, longitud 9 a 17)
                if (preg_match_all('/\b([A-Z0-9]{9,17})\b/i', $fullText, $matches)) {
                    foreach ($matches[1] as $candidate) {
                        $candidate = strtoupper($candidate);
                        // Comprobar que tiene al menos una letra y al menos un número (para evitar palabras de control como REPUBLICA)
                        if (preg_match('/[A-Z]/', $candidate) && preg_match('/\d/', $candidate)) {
                            if (isset($resultado['placa']) && $candidate === $resultado['placa']) {
                                continue;
                            }
                            $resultado['chasis'] = $candidate;
                            break;
                        }
                    }
                }
                
                // Si no se detectó chasis alfanumérico mixto, hacemos fallback clásico por etiqueta
                if (empty($resultado['chasis'])) {
                    for ($i = 0; $i < count($lines); $i++) {
                        $line = trim($lines[$i]);
                        if (preg_match('/CHASIS/i', $line)) {
                            $val = '';
                            if (preg_match('/(Registro|Placa|Status|Vehiculo)/i', $line)) {
                                if (isset($lines[$i+1])) $val = trim($lines[$i+1]);
                            } else {
                                $val = trim(preg_replace('/.*?\bCHASIS\b\s*:?/i', '', $line));
                            }
                            $words = preg_split('/\s+/', $val);
                            foreach ($words as $w) {
                                $w_clean = strtoupper(preg_replace('/[^A-Z0-9]/', '', $w));
                                if (preg_match('/^[A-Z0-9]{9,17}$/', $w_clean)) {
                                    $resultado['chasis'] = $w_clean;
                                    break;
                                }
                            }
                        }
                    }
                }
                
                // Buscar Modelo y Año en el recorrido por líneas
                for ($i = 0; $i < count($lines); $i++) {
                    $line = trim($lines[$i]);

                    // Modelo
                    if (preg_match('/\bMODELO\b/i', $line)) {
                        $val = trim(preg_replace('/.*?\bMODELO\b\s*:?/i', '', $line));
                        if (empty($val) || preg_match('/(Marca|Año|Fabricacion|Tipo)/i', $line)) {
                            if (isset($lines[$i+1])) $val = trim($lines[$i+1]);
                        }
                        
                        $words = preg_split('/\s+/', $val);
                        $modelo_detectado = '';
                        foreach ($words as $w) {
                            $w_clean = strtoupper(preg_replace('/[^A-Z0-9]/', '', $w));
                            if (empty($w_clean)) continue;
                            if (isset($resultado['marca']) && stripos($w_clean, $resultado['marca']) !== false) continue;
                            if (preg_match('/^(19\d{2}|20\d{2})$/', $w_clean)) continue;
                            if (in_array($w_clean, ['AUTOMOVIL', 'PRIVADO', 'JEEP', 'CAMIONETA', 'DE', 'FABRICACION', 'TIPO', 'VEHICULO'])) continue;
                            
                            $modelo_detectado = $w_clean;
                            break;
                        }
                        if ($modelo_detectado) {
                            $resultado['modelo'] = $modelo_detectado;
                        }
                    }

                    // Año de Fabricación (puede decir Ado de Fabricación en OCR de mala calidad)
                    if (preg_match('/(AÑO|ANO|FABRICACION|FABRICAD|Ado\s+de)/i', $line)) {
                        $val = trim(preg_replace('/.*?(FABRICACION|FABRICAD|AÑO|ANO|Ado\s+de)\s*:?/i', '', $line));
                        if (empty($val) || preg_match('/(Modelo|Marca|Tipo)/i', $line)) {
                            if (isset($lines[$i+1])) $val = trim($lines[$i+1]);
                        }
                        
                        // Buscar el año de 4 dígitos en la misma línea
                        if (preg_match('/\b(19\d{2}|20\d{2})\b/', $val, $yr)) {
                            $resultado['anio'] = $yr[1];
                        } else {
                            // Fallback: Si no hay un año de 4 dígitos, lo buscamos en la línea de abajo
                            if (isset($lines[$i+1]) && preg_match('/\b(19\d{2}|20\d{2})\b/', $lines[$i+1], $yr)) {
                                $resultado['anio'] = $yr[1];
                            }
                        }
                    }
                }
            }
            
            echo json_encode([
                "exito" => true,
                "datos" => $resultado
            ]);
            exit;

        default:
            throw new Exception("Acción de checkout no válida");
    }
} catch (Exception $e) {
    if (isset($db) && $db->ping()) {
        $db->rollback();
    }
    http_response_code(400);
    echo json_encode(["exito" => false, "mensaje" => $e->getMessage()]);
}
?>
