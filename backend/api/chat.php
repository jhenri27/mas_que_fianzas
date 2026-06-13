<?php
/**
 * API: Chat de Comunicación Interna (Chat-CSR) y Asistente BBS / BHN
 * MAS QUE FIANZAS - Core Asegurador v3.0
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once dirname(__DIR__) . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validar token de autorización si no hay sesión PHP activa
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

/**
 * Auto-crea e inicializa las tablas del Bot BBS (SSINDI)
 */
function crearBBSBotTablas($db) {
    $db->query("CREATE TABLE IF NOT EXISTS chat_bot_sesiones (
        usuario_id INT NOT NULL,
        bot_id INT NOT NULL,
        flujo VARCHAR(50) NOT NULL,
        paso VARCHAR(50) NOT NULL,
        datos_temporales TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (usuario_id, bot_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->query("INSERT IGNORE INTO companias_registradas (id, nombre, rnc, direccion, telefono, email, tipo, estado, creado_por)
        VALUES (2, 'Midas Seguros', '130000021', 'Santo Domingo, RD', '809-555-0299', 'contacto@midasseguros.com.do', 'aseguradora', 1, 1)");
}

/**
 * Renderiza el HTML premium para descargar la cotización
 */
function renderPremiumQuoteHTML($cot) {
    $numero = htmlspecialchars($cot['numero']);
    $cliente = htmlspecialchars($cot['cliente']);
    $email = htmlspecialchars($cot['email']);
    $telefono = htmlspecialchars($cot['telefono'] ?? '');
    $subtipo = htmlspecialchars($cot['subtipo'] ?? '');
    $capacidad = htmlspecialchars($cot['capacidad'] ?? '');
    $uso = htmlspecialchars($cot['uso'] ?? '');
    $cobertura = htmlspecialchars($cot['cobertura'] ?? '');
    $aseguradora = htmlspecialchars($cot['aseguradora'] ?? '');
    $prima_base = (float)$cot['prima_base'];
    $impuesto = (float)$cot['impuesto'];
    $total = (float)$cot['total'];
    $fecha = date('d/m/Y H:i', strtotime($cot['fecha']));
    $beneficiario = htmlspecialchars($cot['beneficiario'] ?? '');
    
    $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=" . urlencode(FRONTEND_BASE_URL . "/verificar-poliza.html?n=" . $numero);
    
    $aseguradora_upper = strtoupper($aseguradora);
    $brand_color = "#1d4ed8"; // default blue
    $brand_logo = "";
    
    if (strpos($aseguradora_upper, 'MIDAS') !== false) {
        $brand_color = "#b45309"; // gold
        $logo_path = dirname(dirname(__DIR__)) . '/uploads/logos/midas_seguros.png.txt';
        $logo_data = file_exists($logo_path) ? trim(file_get_contents($logo_path)) : '';
        if ($logo_data) {
            $brand_logo = '<img src="' . $logo_data . '" style="height:36px; object-fit: contain;" alt="Midas Seguros">';
        } else {
            $brand_logo = '<svg style="width:36px;height:36px;fill:#d97706;" viewBox="0 0 24 24"><path d="M5 16L3 5L8.5 10L12 4L15.5 10L21 5L19 16H5M19 19C19 19.6 18.6 20 18 20H6C5.4 20 5 19.6 5 19V18H19V19Z"/></svg>';
        }
    } elseif (strpos($aseguradora_upper, 'MULTI') !== false) {
        $brand_color = "#2563eb"; // blue
        $logo_path = dirname(dirname(__DIR__)) . '/uploads/logos/multiseguros.png.txt';
        $logo_data = file_exists($logo_path) ? trim(file_get_contents($logo_path)) : '';
        if ($logo_data) {
            $brand_logo = '<img src="' . $logo_data . '" style="height:36px; object-fit: contain;" alt="Multiseguros">';
        } else {
            $brand_logo = '<svg style="width:36px;height:36px;fill:#2563eb;" viewBox="0 0 24 24"><path d="M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M12,4A8,8 0 0,1 20,12A8,8 0 0,1 12,20A8,8 0 0,1 4,12A8,8 0 0,1 12,4M12,6A6,6 0 0,0 6,12A6,6 0 0,0 12,18A6,6 0 0,0 18,12A6,6 0 0,0 12,6M12,8A4,4 0 0,1 16,12A4,4 0 0,1 12,16A4,4 0 0,1 8,12A4,4 0 0,1 12,8Z"/></svg>';
        }
    } else {
        $brand_logo = '<svg style="width:36px;height:36px;fill:#4f46e5;" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>';
    }
    
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Cotización <?php echo $numero; ?> - Seguro de Ley</title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
        <style>
            :root {
                --primary: <?php echo $brand_color; ?>;
                --text-main: #0f172a;
                --text-muted: #475569;
                --bg-main: #f8fafc;
                --border-color: #e2e8f0;
            }
            body {
                margin: 0; padding: 0;
                font-family: 'Inter', sans-serif;
                background-color: var(--bg-main);
                color: var(--text-main);
                line-height: 1.5;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .no-print-bar {
                background: #ffffff;
                border-bottom: 1px solid var(--border-color);
                padding: 12px 24px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            }
            .btn-print {
                background-color: var(--primary);
                color: #ffffff;
                border: none;
                padding: 10px 20px;
                font-family: 'Outfit', sans-serif;
                font-size: 14px;
                font-weight: 600;
                border-radius: 8px;
                cursor: pointer;
                display: flex;
                align-items: center;
                gap: 8px;
                transition: opacity 0.2s;
            }
            .btn-print:hover { opacity: 0.9; }
            .quote-container {
                max-width: 800px;
                margin: 40px auto;
                background: #ffffff;
                border-radius: 16px;
                box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05);
                padding: 40px;
                border: 1px solid var(--border-color);
                position: relative;
            }
            .header-grid {
                display: grid;
                grid-template-columns: 1fr auto;
                border-bottom: 2px solid var(--border-color);
                padding-bottom: 30px;
                margin-bottom: 30px;
            }
            .brand-section h1 {
                margin: 0;
                font-family: 'Outfit', sans-serif;
                font-size: 24px;
                font-weight: 800;
                color: var(--primary);
                letter-spacing: -0.5px;
            }
            .brand-section p {
                margin: 4px 0 0 0;
                font-size: 12px;
                color: var(--text-muted);
                text-transform: uppercase;
                letter-spacing: 1px;
                font-weight: 600;
            }
            .insurer-badge {
                display: flex;
                align-items: center;
                gap: 10px;
                background: var(--bg-main);
                padding: 10px 18px;
                border-radius: 12px;
                border: 1px solid var(--border-color);
            }
            .insurer-name {
                font-family: 'Outfit', sans-serif;
                font-weight: 700;
                font-size: 16px;
                color: var(--text-main);
            }
            .details-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 30px;
                margin-bottom: 40px;
            }
            .section-title {
                font-family: 'Outfit', sans-serif;
                font-size: 14px;
                font-weight: 700;
                color: var(--primary);
                text-transform: uppercase;
                letter-spacing: 0.5px;
                margin-top: 0;
                margin-bottom: 12px;
                border-bottom: 1px solid var(--border-color);
                padding-bottom: 6px;
            }
            .info-item {
                margin-bottom: 10px;
                font-size: 14px;
            }
            .info-label {
                font-weight: 600;
                color: var(--text-muted);
                display: inline-block;
                width: 120px;
            }
            .info-value {
                color: var(--text-main);
                font-weight: 500;
            }
            .table-premium {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 40px;
            }
            .table-premium th {
                background-color: var(--bg-main);
                color: var(--text-muted);
                font-family: 'Outfit', sans-serif;
                font-weight: 700;
                font-size: 12px;
                text-transform: uppercase;
                text-align: left;
                padding: 12px 16px;
                border-bottom: 2px solid var(--border-color);
            }
            .table-premium td {
                padding: 16px;
                border-bottom: 1px solid var(--border-color);
                font-size: 14px;
            }
            .price-col {
                text-align: right;
                font-weight: 600;
            }
            .total-row {
                font-weight: 700;
                color: var(--primary);
                background-color: rgba(79, 70, 229, 0.03);
            }
            .total-row td {
                border-top: 2px solid var(--primary);
                font-size: 16px;
            }
            .coverage-grid {
                background-color: var(--bg-main);
                border-radius: 12px;
                padding: 24px;
                margin-bottom: 40px;
                border: 1px solid var(--border-color);
            }
            .coverage-list {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 12px 24px;
                font-size: 13px;
            }
            .coverage-item {
                display: flex;
                justify-content: space-between;
                border-bottom: 1px dashed var(--border-color);
                padding-bottom: 6px;
            }
            .coverage-label { color: var(--text-muted); }
            .coverage-value { font-weight: 600; }
            .footer-grid {
                display: grid;
                grid-template-columns: 1fr auto;
                gap: 30px;
                align-items: center;
                border-top: 2px solid var(--border-color);
                padding-top: 30px;
            }
            .disclaimer {
                font-size: 11px;
                color: var(--text-muted);
                line-height: 1.6;
            }
            .qr-code { text-align: center; }
            .qr-code img {
                border: 1px solid var(--border-color);
                border-radius: 8px;
                padding: 4px;
                background: white;
            }
            .qr-code p {
                margin: 4px 0 0 0;
                font-size: 9px;
                font-weight: 600;
                color: var(--text-muted);
                text-transform: uppercase;
            }
            @media print {
                body { background-color: #ffffff; }
                .no-print-bar { display: none; }
                .quote-container { margin: 0; padding: 0; border: none; box-shadow: none; }
            }
        </style>
    </head>
    <body>
        <div class="no-print-bar">
            <span style="font-family:'Outfit',sans-serif; font-weight:700; color:var(--text-muted);">Cotización Digital Seguro de Ley</span>
            <button class="btn-print" onclick="window.print()">Imprimir o Guardar PDF</button>
        </div>
        
        <div class="quote-container">
            <div class="header-grid">
                <div class="brand-section">
                    <h1>MÁS QUE FIANZAS</h1>
                    <p>Core Asegurador v3.0 • Cotización Digital</p>
                </div>
                <div class="insurer-badge">
                    <?php echo $brand_logo; ?>
                    <span class="insurer-name"><?php echo $aseguradora; ?></span>
                </div>
            </div>
            
            <div class="details-grid">
                <div>
                    <h3 class="section-title">Información del Asegurado</h3>
                    <div class="info-item">
                        <span class="info-label">Nombre:</span>
                        <span class="info-value"><?php echo $cliente; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Correo:</span>
                        <span class="info-value"><?php echo $email; ?></span>
                    </div>
                    <?php if ($telefono): ?>
                    <div class="info-item">
                        <span class="info-label">Teléfono:</span>
                        <span class="info-value"><?php echo $telefono; ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <div>
                    <h3 class="section-title">Datos del Vehículo</h3>
                    <div class="info-item">
                        <span class="info-label">Descripción:</span>
                        <span class="info-value"><?php echo $beneficiario ?: $subtipo; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Tipo:</span>
                        <span class="info-value"><?php echo $subtipo; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Uso:</span>
                        <span class="info-value"><?php echo $uso; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Capacidad:</span>
                        <span class="info-value"><?php echo $capacidad; ?></span>
                    </div>
                </div>
            </div>
            
            <table class="table-premium">
                <thead>
                    <tr>
                        <th>Descripción del Producto</th>
                        <th>Tipo Cobertura</th>
                        <th style="text-align: right;">Precio (RD$)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <strong>SEGURO DE LEY OBLIGATORIO</strong><br>
                            <span style="font-size:12px; color:var(--text-muted);">Seguro de daños a terceros según ley 146-02.</span>
                        </td>
                        <td><?php echo $cobertura; ?></td>
                        <td class="price-col">RD$ <?php echo number_format($prima_base, 2); ?></td>
                    </tr>
                    <tr>
                        <td>
                            <strong>IMPUESTO (ITBIS/Otros)</strong><br>
                            <span style="font-size:12px; color:var(--text-muted);">Tasas impositivas de seguros aplicadas.</span>
                        </td>
                        <td>Incluido</td>
                        <td class="price-col">RD$ <?php echo number_format($impuesto, 2); ?></td>
                    </tr>
                    <tr class="total-row">
                        <td colspan="2">TOTAL ANUAL ESTIMADO</td>
                        <td class="price-col">RD$ <?php echo number_format($total, 2); ?></td>
                    </tr>
                </tbody>
            </table>
            
            <h3 class="section-title">Límites y Coberturas Seguro de Ley (RD$)</h3>
            <div class="coverage-grid">
                <div class="coverage-list">
                    <div class="coverage-item">
                        <span class="coverage-label">Daños Propiedad Ajena</span>
                        <span class="coverage-value">RD$ 100,000.00</span>
                    </div>
                    <div class="coverage-item">
                        <span class="coverage-label">Lesiones Personales (1 pers)</span>
                        <span class="coverage-value">RD$ 100,000.00</span>
                    </div>
                    <div class="coverage-item">
                        <span class="coverage-label">Lesiones Personales (2+ pers)</span>
                        <span class="coverage-value">RD$ 200,000.00</span>
                    </div>
                    <div class="coverage-item">
                        <span class="coverage-label">Fianza Judicial</span>
                        <span class="coverage-value">RD$ 20,000.00</span>
                    </div>
                    <div class="coverage-item">
                        <span class="coverage-label">Daños al Conductor</span>
                        <span class="coverage-value">RD$ 20,000.00</span>
                    </div>
                    <div class="coverage-item">
                        <span class="coverage-label">Daños a Pasajeros</span>
                        <span class="coverage-value">RD$ 20,000.00</span>
                    </div>
                </div>
                <div style="margin-top: 15px; font-size: 12px; color: var(--text-muted);">
                    🤝 <strong>Servicios Gratuitos Incluidos:</strong> Asistencia Vial 24/7 (Servicio de grúa, cambio de neumático, combustible y carga de batería) y acceso al Centro del Automovilista en caso de siniestro.
                </div>
            </div>
            
            <div class="footer-grid">
                <div class="disclaimer">
                    <strong>N° Cotización: <?php echo $numero; ?></strong> • Fecha Generación: <?php echo $fecha; ?><br>
                    ⚠️ <strong>Validez:</strong> Esta cotización es válida por 30 días desde su fecha de emisión y está sujeta a los términos y condiciones de la póliza de la aseguradora. No constituye un contrato de seguro ni póliza emitida hasta tanto no sea formalizada y pagada. Cumple con la norma de auditoría inmutable <strong>NOFTRAB v4.0</strong>.
                </div>
                <div class="qr-code">
                    <img src="<?php echo $qr_url; ?>" alt="QR de Validación" width="100" height="100">
                    <p>Validación Oficial</p>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

/**
 * Renderiza el HTML premium para el marbete digital
 */
function renderMarbeteHTML($poliza) {
    $num_poliza = htmlspecialchars($poliza['numero_poliza']);
    $cliente = htmlspecialchars($poliza['cliente']);
    $subtipo = htmlspecialchars($poliza['subtipo']);
    $beneficiario = htmlspecialchars($poliza['beneficiario']);
    $aseguradora = htmlspecialchars($poliza['aseguradora']);
    $fecha_emision = date('d/m/Y', strtotime($poliza['fecha_emision']));
    $fecha_vencimiento = date('d/m/Y', strtotime($poliza['fecha_vencimiento']));
    
    $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=" . urlencode(FRONTEND_BASE_URL . "/verificar-poliza.html?n=" . $num_poliza);
    
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Marbete Digital <?php echo $num_poliza; ?></title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Outfit:wght@700;900&display=swap" rel="stylesheet">
        <style>
            body { font-family: 'Inter', sans-serif; background: #f1f5f9; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 20px; }
            .no-print { margin-bottom: 20px; }
            .btn-print { background: #10b981; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 14px; }
            .marbete-card {
                width: 380px;
                background: #ffffff;
                border: 4px solid #10b981;
                border-radius: 16px;
                box-shadow: 0 4px 15px rgba(0,0,0,0.1);
                overflow: hidden;
                padding: 20px;
                position: relative;
            }
            .header { text-align: center; border-bottom: 2px solid #10b981; padding-bottom: 10px; margin-bottom: 15px; }
            .header h1 { margin: 0; font-family: 'Outfit', sans-serif; font-size: 20px; color: #047857; }
            .header p { margin: 2px 0 0 0; font-size: 10px; color: #64748b; font-weight: 700; text-transform: uppercase; }
            .content-grid { display: grid; grid-template-columns: 1fr auto; gap: 15px; align-items: center; }
            .details { font-size: 12px; }
            .item { margin-bottom: 8px; }
            .lbl { font-weight: 700; color: #64748b; font-size: 10px; text-transform: uppercase; }
            .val { font-weight: 600; color: #1e293b; font-size: 13px; }
            .footer { margin-top: 15px; border-top: 1px solid #e2e8f0; padding-top: 10px; text-align: center; font-size: 10px; color: #64748b; font-weight: 600; }
            @media print {
                body { background: #fff; }
                .no-print { display: none; }
                .marbete-card { border: 4px solid #10b981; box-shadow: none; }
            }
        </style>
    </head>
    <body>
        <div class="no-print">
            <button class="btn-print" onclick="window.print()">Imprimir Marbete</button>
        </div>
        <div class="marbete-card">
            <div class="header">
                <h1>MÁS QUE FIANZAS</h1>
                <p>MARBETE DIGITAL OFICIAL — SEGURO DE LEY</p>
            </div>
            <div class="content-grid">
                <div class="details">
                    <div class="item">
                        <div class="lbl">Póliza N°:</div>
                        <div class="val" style="color:#047857; font-weight:700; font-size:15px;"><?php echo $num_poliza; ?></div>
                    </div>
                    <div class="item">
                        <div class="lbl">Asegurado:</div>
                        <div class="val"><?php echo $cliente; ?></div>
                    </div>
                    <div class="item">
                        <div class="lbl">Vehículo:</div>
                        <div class="val"><?php echo $beneficiario ?: $subtipo; ?></div>
                    </div>
                    <div class="item">
                        <div class="lbl">Aseguradora:</div>
                        <div class="val"><?php echo $aseguradora; ?></div>
                    </div>
                    <div class="item" style="display:flex; gap:15px; margin-bottom:0;">
                        <div>
                            <div class="lbl">Emisión:</div>
                            <div class="val" style="font-size:11px;"><?php echo $fecha_emision; ?></div>
                        </div>
                        <div>
                            <div class="lbl">Vence:</div>
                            <div class="val" style="font-size:11px; color:#dc2626;"><?php echo $fecha_vencimiento; ?></div>
                        </div>
                    </div>
                </div>
                <div style="text-align: center;">
                    <img src="<?php echo $qr_url; ?>" alt="QR" width="90" height="90">
                    <div style="font-size:8px; font-weight:700; color:#64748b; margin-top:2px;">VALIDEZ QR</div>
                </div>
            </div>
            <div class="footer">
                Válido en la República Dominicana conforme Ley 146-02.
            </div>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

/**
 * Renderiza el HTML premium para las condiciones generales
 */
function renderCondicionesHTML($poliza) {
    $num_poliza = htmlspecialchars($poliza['numero_poliza']);
    $cliente = htmlspecialchars($poliza['cliente']);
    
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Condiciones Generales - Póliza <?php echo $num_poliza; ?></title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
        <style>
            body { font-family: 'Inter', sans-serif; line-height: 1.6; color: #334155; padding: 40px; background: #f8fafc; }
            .container { max-width: 800px; margin: 0 auto; background: #fff; padding: 40px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
            h1 { font-size: 24px; color: #1e3a8a; margin-top: 0; }
            h2 { font-size: 18px; color: #1e40af; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px; margin-top: 25px; }
            p { font-size: 14px; text-align: justify; }
            ol { font-size: 14px; padding-left: 20px; }
            li { margin-bottom: 8px; text-align: justify; }
            .no-print { display: flex; justify-content: flex-end; margin-bottom: 20px; }
            .btn-print { background: #1e3a8a; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; }
            @media print {
                body { padding: 0; background: white; }
                .container { border: none; box-shadow: none; padding: 0; }
                .no-print { display: none; }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="no-print">
                <button class="btn-print" onclick="window.print()">Imprimir Condiciones</button>
            </div>
            <h1>CONDICIONES GENERALES DEL SEGURO DE LEY</h1>
            <p><strong>Póliza de Seguro N°: <?php echo $num_poliza; ?></strong><br><strong>Asegurado: <?php echo $cliente; ?></strong></p>
            
            <h2>1. OBJETO DEL CONTRATO</h2>
            <p>Conforme a la Ley N° 146-02 sobre Seguros y Fianzas de la República Dominicana, la compañía aseguradora se obliga a mantener indemne al asegurado por los daños causados a terceros derivados de accidentes de tránsito en los que participe el vehículo descrito en esta póliza.</p>
            
            <h2>2. COBERTURAS Y LÍMITES</h2>
            <p>Las coberturas incluidas en este Seguro Obligatorio de Ley corresponden a:</p>
            <ol>
                <li><strong>Daños a la Propiedad Ajena (Terceros):</strong> Límite de responsabilidad civil de hasta RD$ 100,000.00 por evento.</li>
                <li><strong>Lesiones Corporales o Muerte a una Persona (Terceros):</strong> Límite de hasta RD$ 100,000.00.</li>
                <li><strong>Lesiones Corporales o Muerte a dos o más Personas (Terceros):</strong> Límite de hasta RD$ 200,000.00 por evento.</li>
                <li><strong>Fianza Judicial:</strong> Garantía de fianza para obtener la libertad provisional en caso de accidentes con daños corporales, hasta un límite de RD$ 20,000.00.</li>
                <li><strong>Daños al Conductor:</strong> Cobertura de accidentes personales para el conductor autorizado del vehículo, hasta RD$ 20,000.00.</li>
                <li><strong>Daños a Pasajeros:</strong> Cobertura de accidentes personales para pasajeros transportados legalmente, hasta RD$ 20,000.00 por persona.</li>
            </ol>

            <h2>3. EXCLUSIONES PRINCIPALES</h2>
            <p>El seguro no cubre reclamaciones derivadas de:</p>
            <ul>
                <li>Accidentes ocurridos fuera del territorio de la República Dominicana.</li>
                <li>Conducción del vehículo por personas no autorizadas legalmente (sin licencia vigente).</li>
                <li>Conducción bajo efectos demostrados de bebidas alcohólicas, drogas recreativas o sustancias psicotrópicas.</li>
                <li>Uso del vehículo en competencias de velocidad oficiales o clandestinas.</li>
                <li>Daños materiales sufridos por el propio vehículo asegurado o por los bienes propiedad del asegurado o conductor.</li>
            </ul>

            <h2>4. PROCEDIMIENTO EN CASO DE SINIESTRO</h2>
            <p>En caso de accidente, el asegurado o conductor deberá:</p>
            <ol>
                <li>Prestar auxilio inmediato a cualquier lesionado si las circunstancias lo permiten.</li>
                <li>Obtener el Acta Policial correspondiente ante la Dirección General de Seguridad de Tránsito y Transporte Terrestre (DIGESETT).</li>
                <li>Notificar el siniestro a la aseguradora o a través de los canales de la plataforma MÁS QUE FIANZAS en un plazo no mayor a tres (3) días laborables.</li>
                <li>No aceptar responsabilidad civil, transigir o realizar acuerdos económicos con terceros sin la autorización expresa de la aseguradora.</li>
            </ol>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

try {
    $db = Database::getInstance()->getConnection();
    crearBBSBotTablas($db);

    $metodo = $_SERVER['REQUEST_METHOD'];

    if ($metodo === 'GET') {
        // GET: descargar_cotizacion
        if (isset($_GET['action']) && $_GET['action'] === 'descargar_cotizacion') {
            $cot_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($cot_id <= 0) {
                http_response_code(400);
                echo "ID de cotización requerido.";
                exit;
            }
            $stmt_c = $db->prepare("SELECT * FROM cotizaciones WHERE id = ? LIMIT 1");
            $stmt_c->bind_param("i", $cot_id);
            $stmt_c->execute();
            $cot = $stmt_c->get_result()->fetch_assoc();
            $stmt_c->close();
            if (!$cot) {
                http_response_code(404);
                echo "La cotización no existe.";
                exit;
            }
            header('Content-Type: text/html; charset=utf-8');
            echo renderPremiumQuoteHTML($cot);
            exit;
        }

        // GET: enviar_email_cotizacion
        if (isset($_GET['action']) && $_GET['action'] === 'enviar_email_cotizacion') {
            $cot_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($cot_id <= 0) {
                http_response_code(400);
                echo json_encode(["exito" => false, "mensaje" => "ID de cotización requerido"]);
                exit;
            }
            $stmt_c = $db->prepare("SELECT * FROM cotizaciones WHERE id = ? LIMIT 1");
            $stmt_c->bind_param("i", $cot_id);
            $stmt_c->execute();
            $cot = $stmt_c->get_result()->fetch_assoc();
            $stmt_c->close();
            if (!$cot) {
                http_response_code(404);
                echo json_encode(["exito" => false, "mensaje" => "La cotización no existe"]);
                exit;
            }
            if (empty($cot['email']) || !filter_var($cot['email'], FILTER_VALIDATE_EMAIL)) {
                echo json_encode(["exito" => false, "mensaje" => "El cliente no tiene un correo electrónico válido asignado"]);
                exit;
            }
            
            require_once dirname(__DIR__) . '/NotificacionesEngine.php';
            $ctx = [
                'NUMERO'       => $cot['numero'],
                'CLIENTE'      => $cot['cliente'],
                'EMAIL'        => $cot['email'],
                'TELEFONO'     => $cot['telefono'] ?? '',
                'TIPO'         => $cot['tipo'],
                'TIPO_LABEL'   => 'Seguro de Ley',
                'SUBTIPO'      => $cot['subtipo'],
                'ASEGURADORA'  => $cot['aseguradora'],
                'TOTAL_FMT'    => 'RD$ ' . number_format($cot['total'], 2),
                'PRIMA_FMT'    => 'RD$ ' . number_format($cot['prima_base'], 2),
                'MONTO_FMT'    => 'RD$ ' . number_format($cot['monto_afianzado'] ?? 0, 2),
                'PLAZO'        => (string)($cot['plazo'] ?? 0),
                'FECHA_LOCAL'  => date('d/m/Y H:i', strtotime($cot['fecha'])),
                'BENEFICIARIO' => $cot['beneficiario'] ?? '',
                'COBERTURA'    => $cot['cobertura'] ?? '',
                'USO'          => $cot['uso'] ?? '',
                'CAPACIDAD'    => $cot['capacidad'] ?? '',
                'email'        => $cot['email'],
                'creado_por'   => $cot['creado_por'],
            ];
            
            $res_disparar = notif_disparar($db, 'COTIZACION_NUEVA', $ctx, $cot['numero'], $usuario_id);
            if ($res_disparar['enviados'] > 0) {
                echo json_encode(["exito" => true, "mensaje" => "Cotización enviada exitosamente al correo " . $cot['email']]);
            } else {
                echo json_encode(["exito" => false, "mensaje" => "No se pudo enviar el correo. Revise smtp.log"]);
            }
            exit;
        }

        // GET: descargar_marbete
        if (isset($_GET['action']) && $_GET['action'] === 'descargar_marbete') {
            $pol_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($pol_id <= 0) {
                http_response_code(400);
                echo "ID de póliza requerido.";
                exit;
            }
            $stmt_p = $db->prepare("SELECT p.*, c.cliente, c.email, c.subtipo, c.capacidad, c.uso, c.beneficiario FROM polizas p LEFT JOIN cotizaciones c ON p.cotizacion_id = c.id WHERE p.id = ? LIMIT 1");
            $stmt_p->bind_param("i", $pol_id);
            $stmt_p->execute();
            $pol = $stmt_p->get_result()->fetch_assoc();
            $stmt_p->close();
            if (!$pol) {
                http_response_code(404);
                echo "La póliza no existe.";
                exit;
            }
            header('Content-Type: text/html; charset=utf-8');
            echo renderMarbeteHTML($pol);
            exit;
        }

        // GET: descargar_condiciones
        if (isset($_GET['action']) && $_GET['action'] === 'descargar_condiciones') {
            $pol_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($pol_id <= 0) {
                http_response_code(400);
                echo "ID de póliza requerido.";
                exit;
            }
            $stmt_p = $db->prepare("SELECT p.*, c.cliente, c.email, c.subtipo, c.capacidad, c.uso, c.beneficiario FROM polizas p LEFT JOIN cotizaciones c ON p.cotizacion_id = c.id WHERE p.id = ? LIMIT 1");
            $stmt_p->bind_param("i", $pol_id);
            $stmt_p->execute();
            $pol = $stmt_p->get_result()->fetch_assoc();
            $stmt_p->close();
            if (!$pol) {
                http_response_code(404);
                echo "La póliza no existe.";
                exit;
            }
            header('Content-Type: text/html; charset=utf-8');
            echo renderCondicionesHTML($pol);
            exit;
        }

        // GET: descargar_archivo (Opción C)
        if (isset($_GET['action']) && $_GET['action'] === 'descargar_archivo') {
            $msg_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($msg_id <= 0) {
                http_response_code(400);
                echo json_encode(["exito" => false, "mensaje" => "ID de mensaje requerido"]);
                exit;
            }
            $stmt_file = $db->prepare("SELECT emisor_id, receptor_id, archivo_nombre, archivo_ruta, archivo_tipo, archivo_size FROM mensajes_chat WHERE id = ? LIMIT 1");
            $stmt_file->bind_param("i", $msg_id);
            $stmt_file->execute();
            $file_data = $stmt_file->get_result()->fetch_assoc();
            $stmt_file->close();
            if (!$file_data || empty($file_data['archivo_ruta'])) {
                http_response_code(404);
                echo json_encode(["exito" => false, "mensaje" => "El archivo solicitado no existe"]);
                exit;
            }
            $puede_ver = (
                $usuario_id === 1 || 
                $usuario_id === (int)$file_data['emisor_id'] || 
                $usuario_id === (int)$file_data['receptor_id'] ||
                (int)$usr_data['perfil_id'] === 1 || 
                (function_exists('tienePermiso') && (tienePermiso($usuario_id, 'CONF_TOTAL') || tienePermiso($usuario_id, 'CHAT_CSR_SUPERVISAR')))
            );
            if (!$puede_ver) {
                http_response_code(403);
                echo json_encode(["exito" => false, "mensaje" => "Acceso denegado: no tiene permisos para descargar este archivo"]);
                exit;
            }
            $ruta_completa = dirname(__DIR__) . '/' . $file_data['archivo_ruta'];
            if (!file_exists($ruta_completa)) {
                http_response_code(404);
                echo json_encode(["exito" => false, "mensaje" => "El archivo físico no se encuentra en el servidor"]);
                exit;
            }
            if (function_exists('logAudit')) {
                logAudit($usuario_id, 'descargar_archivo_chat', 'mensajes_chat', 'consultar', 
                    "Archivo descargado: " . $file_data['archivo_nombre'] . " (Msg ID: $msg_id)", 'exitoso', null, 'mensajes_chat', $msg_id);
            }
            header('Content-Description: File Transfer');
            header('Content-Type: ' . $file_data['archivo_tipo']);
            header('Content-Disposition: attachment; filename="' . basename($file_data['archivo_nombre']) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . $file_data['archivo_size']);
            readfile($ruta_completa);
            exit;
        }

        // GET: Listar conversaciones activas
        if (!isset($_GET['chat_con_id'])) {
            $supervisor_id = $usr_data['referente_id'] ? (int)$usr_data['referente_id'] : 1;
            if ($supervisor_id === $usuario_id) {
                $supervisor_id = ($usuario_id == 1) ? null : 1;
            }
            $sql_interacciones = "
                SELECT DISTINCT u.id, u.username, u.nombre, u.apellido, p.nombre_perfil,
                       (SELECT MAX(fecha_envio) FROM mensajes_chat 
                        WHERE (emisor_id = u.id AND receptor_id = ?) 
                           OR (emisor_id = ? AND receptor_id = u.id)) as ultima_fecha,
                       (SELECT COUNT(*) FROM mensajes_chat 
                        WHERE emisor_id = u.id AND receptor_id = ? AND leido = 0) as no_leidos
                FROM usuarios u
                JOIN perfiles p ON u.perfil_id = p.id
                WHERE u.id IN (
                    SELECT emisor_id FROM mensajes_chat WHERE receptor_id = ?
                    UNION
                    SELECT receptor_id FROM mensajes_chat WHERE emisor_id = ?
                )
                ORDER BY ultima_fecha DESC
            ";
            $stmt_int = $db->prepare($sql_interacciones);
            $stmt_int->bind_param("iiiii", $usuario_id, $usuario_id, $usuario_id, $usuario_id, $usuario_id);
            $stmt_int->execute();
            $res_int = $stmt_int->get_result();
            $conversaciones = [];
            $interacted_ids = [];
            while ($row = $res_int->fetch_assoc()) {
                // Enriquecer con tipo de bot si corresponde
                $row['es_bot'] = 0;
                $row['bot_code'] = null;
                if ($row['username'] === 'bot.helpnow') {
                    $row['es_bot'] = 1;
                    $row['bot_code'] = 'BHN';
                } elseif ($row['username'] === 'bot.ssindi') {
                    $row['es_bot'] = 1;
                    $row['bot_code'] = 'BBS';
                }
                $conversaciones[] = $row;
                $interacted_ids[] = (int)$row['id'];
            }
            $stmt_int->close();

            // Asegurar que los bots del sistema siempre estén en la lista, incluso si no han interactuado
            $bots_res = $db->query("SELECT u.id, u.username, u.nombre, u.apellido, p.nombre_perfil FROM usuarios u JOIN perfiles p ON u.perfil_id = p.id WHERE u.username IN ('bot.helpnow', 'bot.ssindi')");
            if ($bots_res) {
                while ($b_row = $bots_res->fetch_assoc()) {
                    if (!in_array((int)$b_row['id'], $interacted_ids)) {
                        $b_row['ultima_fecha'] = null;
                        $b_row['no_leidos'] = 0;
                        $b_row['es_bot'] = 1;
                        $b_row['bot_code'] = ($b_row['username'] === 'bot.helpnow') ? 'BHN' : 'BBS';
                        $conversaciones[] = $b_row;
                        $interacted_ids[] = (int)$b_row['id'];
                    }
                }
            }

            if ($supervisor_id && !in_array($supervisor_id, $interacted_ids)) {
                $stmt_sup = $db->prepare("SELECT u.id, u.username, u.nombre, u.apellido, p.nombre_perfil FROM usuarios u JOIN perfiles p ON u.perfil_id = p.id WHERE u.id = ? LIMIT 1");
                $stmt_sup->bind_param("i", $supervisor_id);
                $stmt_sup->execute();
                $sup_info = $stmt_sup->get_result()->fetch_assoc();
                $stmt_sup->close();
                if ($sup_info) {
                    $sup_info['ultima_fecha'] = null;
                    $sup_info['no_leidos'] = 0;
                    $sup_info['es_supervisor'] = true;
                    $sup_info['es_bot'] = 0;
                    $sup_info['bot_code'] = null;
                    array_unshift($conversaciones, $sup_info);
                }
            }
            echo json_encode([
                "exito" => true,
                "conversaciones" => $conversaciones,
                "usuario_actual" => [
                    "id" => $usuario_id,
                    "nombre" => $usr_data['nombre'] . ' ' . $usr_data['apellido'],
                    "perfil_id" => (int)$usr_data['perfil_id']
                ]
            ]);
            exit;
        }

        // GET: Obtener mensajes con un usuario específico
        $chat_con_id = (int)$_GET['chat_con_id'];
        $stmt_read = $db->prepare("UPDATE mensajes_chat SET leido = 1 WHERE emisor_id = ? AND receptor_id = ? AND leido = 0");
        $stmt_read->bind_param("ii", $chat_con_id, $usuario_id);
        $stmt_read->execute();
        $stmt_read->close();

        $sql_msg = "
            SELECT m.*, 
                   e.username as emisor_username, e.nombre as emisor_nombre, e.apellido as emisor_apellido,
                   r.username as receptor_username, r.nombre as receptor_nombre, r.apellido as receptor_apellido
            FROM mensajes_chat m
            JOIN usuarios e ON m.emisor_id = e.id
            JOIN usuarios r ON m.receptor_id = r.id
            WHERE (m.emisor_id = ? AND m.receptor_id = ?) 
               OR (m.emisor_id = ? AND m.receptor_id = ?)
            ORDER BY m.fecha_envio ASC
        ";
        $stmt_msg = $db->prepare($sql_msg);
        $stmt_msg->bind_param("iiii", $usuario_id, $chat_con_id, $chat_con_id, $usuario_id);
        $stmt_msg->execute();
        $res_msg = $stmt_msg->get_result();
        $mensajes = [];
        while ($row = $res_msg->fetch_assoc()) {
            $mensajes[] = [
                "id" => (int)$row['id'],
                "emisor_id" => (int)$row['emisor_id'],
                "receptor_id" => (int)$row['receptor_id'],
                "mensaje" => $row['mensaje'],
                "fecha_envio" => $row['fecha_envio'],
                "leido" => (int)$row['leido'],
                "yo" => ((int)$row['emisor_id'] === $usuario_id),
                "archivo_nombre" => $row['archivo_nombre'] ?? null,
                "archivo_tipo" => $row['archivo_tipo'] ?? null,
                "archivo_size" => isset($row['archivo_size']) ? (int)$row['archivo_size'] : null,
                "archivo_hash" => $row['archivo_hash'] ?? null
            ];
        }
        $stmt_msg->close();

        echo json_encode(["exito" => true, "mensajes" => $mensajes]);
        exit;
    }

    if ($metodo === 'POST') {
        $content_type = $_SERVER['CONTENT_TYPE'] ?? '';
        $data = [];
        if (stripos($content_type, 'application/json') !== false) {
            $raw_data = file_get_contents('php://input');
            $data = json_decode($raw_data, true) ?? [];
        } else {
            $data = $_POST;
        }

        if (empty($data['mensaje']) && !isset($_FILES['archivo'])) {
            http_response_code(400);
            echo json_encode(["exito" => false, "mensaje" => "Mensaje o archivo adjunto requerido"]);
            exit;
        }

        $receptor_id = isset($data['receptor_id']) ? (int)$data['receptor_id'] : null;
        if (!$receptor_id) {
            $receptor_id = $usr_data['referente_id'] ? (int)$usr_data['referente_id'] : 1;
            if ($receptor_id === $usuario_id) {
                $receptor_id = ($usuario_id == 1) ? null : 1;
            }
        }
        if (!$receptor_id) {
            http_response_code(400);
            echo json_encode(["exito" => false, "mensaje" => "No se pudo determinar el destinatario del mensaje (Supervisor no configurado)"]);
            exit;
        }

        $mensaje = isset($data['mensaje']) ? trim($data['mensaje']) : '';
        $archivo_nombre = null;
        $archivo_ruta = null;
        $archivo_tipo = null;
        $archivo_size = null;
        $archivo_hash = null;

        if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['archivo'];
            $file_size = $file['size'];
            $file_name = $file['name'];
            $file_tmp = $file['tmp_name'];

            if ($file_size > 10 * 1024 * 1024) {
                http_response_code(400);
                echo json_encode(["exito" => false, "mensaje" => "El archivo excede el tamaño máximo permitido de 10 MB"]);
                exit;
            }

            $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed_exts = ['xls', 'xlsx', 'csv', 'xml', 'json', 'doc', 'docx', 'ppt', 'pptx', 'pdf', 'jpeg', 'jpg', 'png'];
            if (!in_array($ext, $allowed_exts)) {
                http_response_code(400);
                echo json_encode(["exito" => false, "mensaje" => "Formato de archivo no permitido. Solo se admiten documentos estándar e imágenes."]);
                exit;
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime_type = $finfo->file($file_tmp);
            
            $allowed_mimes = [
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'text/csv',
                'text/plain',
                'text/xml',
                'application/xml',
                'application/json',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'application/pdf',
                'image/png',
                'image/jpeg',
                'image/jpg'
            ];

            if (!in_array($mime_type, $allowed_mimes) && $mime_type !== 'text/plain') {
                http_response_code(400);
                echo json_encode(["exito" => false, "mensaje" => "El tipo de archivo no corresponde con la extensión declarada."]);
                exit;
            }

            $sub_dir = 'uploads/chat/' . date('Y/m') . '/';
            $upload_dir = dirname(__FILE__) . '/../' . $sub_dir;
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $new_filename = hash('sha256', time() . rand(1000, 9999)) . '.' . $ext;
            $dest_path = $upload_dir . $new_filename;

            if (move_uploaded_file($file_tmp, $dest_path)) {
                $archivo_nombre = $file_name;
                $archivo_ruta = $sub_dir . $new_filename;
                $archivo_tipo = $mime_type;
                $archivo_size = $file_size;
                $archivo_hash = hash_file('sha256', $dest_path);
                
                if (empty($mensaje)) {
                    $mensaje = "📎 Archivo adjunto: " . $file_name;
                }
            } else {
                http_response_code(500);
                echo json_encode(["exito" => false, "mensaje" => "Error al almacenar el archivo en el servidor."]);
                exit;
            }
        }

        $stmt_ins = $db->prepare("INSERT INTO mensajes_chat (emisor_id, receptor_id, mensaje, fecha_envio, leido, archivo_nombre, archivo_ruta, archivo_tipo, archivo_size, archivo_hash) VALUES (?, ?, ?, NOW(), 0, ?, ?, ?, ?, ?)");
        $stmt_ins->bind_param("iissssis", $usuario_id, $receptor_id, $mensaje, $archivo_nombre, $archivo_ruta, $archivo_tipo, $archivo_size, $archivo_hash);
        
        if ($stmt_ins->execute()) {
            $nuevo_id = $stmt_ins->insert_id;
            $stmt_ins->close();
            
            // 🤖 Enrutamiento Inteligente a Bots
            $res_bhn = $db->query("SELECT id FROM usuarios WHERE username = 'bot.helpnow' LIMIT 1");
            $row_bhn = $res_bhn->fetch_assoc();
            $bot_helpnow_id = $row_bhn ? (int)$row_bhn['id'] : 0;
            
            $res_bbs = $db->query("SELECT id FROM usuarios WHERE username = 'bot.ssindi' LIMIT 1");
            $row_bbs = $res_bbs->fetch_assoc();
            $bot_ssindi_id = $row_bbs ? (int)$row_bbs['id'] : 0;
            
            $es_bhn_trigger = false;
            $es_bbs_trigger = false;
            
            $mensaje_clean = trim($mensaje);
            $primer_palabra = strtolower(explode(' ', $mensaje_clean)[0]);
            
            if ($receptor_id === $bot_helpnow_id) {
                $es_bhn_trigger = true;
            } elseif ($receptor_id === $bot_ssindi_id) {
                $es_bbs_trigger = true;
            } else {
                $contiene_bbs_trigger = (
                    $primer_palabra === 'bbs' || 
                    $primer_palabra === 'ssindi' ||
                    preg_match('/^(bbs|ssindi)\b/i', $mensaje_clean) ||
                    preg_match('/(cotizar|cotizacion|cotización|seguro de ley|poliza|póliza|fianza|emitir|emision|emisión|renovar|renovacion|renovación)/i', $mensaje_clean) ||
                    preg_match('/\b(POL-\d{4}-\d+|\bFZ-\d{4}-\d+)\b/i', $mensaje_clean)
                );
                if ($contiene_bbs_trigger) {
                    $es_bbs_trigger = true;
                } else {
                    $bhn_keywords = ['bot', 'bhn', 'help', 'now'];
                    if (in_array($primer_palabra, $bhn_keywords) || preg_match('/^(bot|bhn|help|now)\b/i', $mensaje_clean)) {
                        $es_bhn_trigger = true;
                    }
                }
            }

            if ($es_bhn_trigger) {
                $bot_reply = "";
                if (function_exists('tienePermiso') && !tienePermiso($usuario_id, 'CHAT_BOT_BHN')) {
                    $bot_reply = "🤖 **BHN-Bot-HelpNow (Asistente de Soporte)**: Estimado usuario, su perfil actual no dispone de autorización para interactuar con este bot técnico (`CHAT_BOT_BHN`). Si considera que esto es un error, por favor contacte a su administrador. ¡Que tenga un excelente día!";
                } else {
                    $txtLower = strtolower($mensaje_clean);
                    if (strpos($txtLower, 'php') !== false) {
                        $bot_reply = "🤖 **BHN-Bot-HelpNow (Experto PHP 8.2)**: La plataforma corre sobre PHP 8.2. Usamos POO y patrón Singleton.";
                    } elseif (strpos($txtLower, 'mysql') !== false || strpos($txtLower, 'bd') !== false) {
                        $bot_reply = "🤖 **BHN-Bot-HelpNow (Experto MySQL)**: La base de datos es MariaDB. Toda transacción crítica requiere commit/rollback.";
                    } elseif (strpos($txtLower, 'javascript') !== false) {
                        $bot_reply = "🤖 **BHN-Bot-HelpNow (Experto JS/DOM)**: El frontend usa JS puro y fetch para llamadas asíncronas.";
                    } else {
                        $bot_reply = "🤖 **BHN-Bot-HelpNow (Soporte Técnico)**: Hola, ¿en qué área necesitas asistencia técnica?";
                    }
                }
                
                $stmt_bot = $db->prepare("INSERT INTO mensajes_chat (emisor_id, receptor_id, mensaje, fecha_envio, leido) VALUES (?, ?, ?, NOW(), 0)");
                $stmt_bot->bind_param("iis", $bot_helpnow_id, $usuario_id, $bot_reply);
                $stmt_bot->execute();
                $stmt_bot->close();
            }
            elseif ($es_bbs_trigger) {
                $bot_reply = "";
                if (function_exists('tienePermiso') && !tienePermiso($usuario_id, 'CHAT_BOT_BBS')) {
                    $bot_reply = "🤖 **BBS-BOT-BUSINES-SERVICE (SSINDI)**: Estimado usuario, su perfil actual no cuenta con los privilegios necesarios para utilizar los servicios comerciales automáticos (`CHAT_BOT_BBS`). Por favor, solicite acceso al administrador del sistema. ¡Estamos a su servicio!";
                } else {
                    $mensaje_sin_bbs = preg_replace('/^(bbs|ssindi)\s*/i', '', $mensaje_clean);
                    $tokens = explode(' ', preg_replace('/\s+/', ' ', trim($mensaje_sin_bbs)));
                    $cmd = isset($tokens[0]) ? strtolower($tokens[0]) : '';
                    
                    $msg_lower = strtolower($mensaje_clean);
                    $has_policy = preg_match('/(POL-\d{4}-\d+)/i', $mensaje_clean, $m_pol);
                    $has_fianza = preg_match('/(FZ-\d{4}-\d+)/i', $mensaje_clean, $m_fz);
                    $has_num = preg_match('/(?:emitir|emisión|emision|cotización|cotizacion|cotización|#)\s*(\d+)/i', $mensaje_clean, $m_num);
                    if (!$has_num) {
                        preg_match('/\b(\d+)\b/', $mensaje_clean, $m_num);
                    }
                    
                    $translated_cmd = false;
                    
                    if (preg_match('/(renovar|renovacion|renovación|extender)/i', $msg_lower) && $has_policy) {
                        $cmd = 'renovar';
                        $tokens = ['renovar', $m_pol[1]];
                        $translated_cmd = true;
                    } elseif (preg_match('/(emitir|emision|emisión)/i', $msg_lower) && $has_num) {
                        $cmd = 'emitir';
                        $tokens = ['emitir', $m_num[1]];
                        $translated_cmd = true;
                    } elseif (preg_match('/estado/i', $msg_lower) && preg_match('/(cuenta|cuentas|balance|deuda|pago|recibo|cuota)/i', $msg_lower) && $has_policy) {
                        $cmd = 'ver';
                        $tokens = ['ver', 'estado', $m_pol[1]];
                        $translated_cmd = true;
                    } elseif ((preg_match('/(poliza|póliza|cobertura|detalle|detalles|investiga|ver|estado)/i', $msg_lower) || preg_match('/(estado\s+tecnico|estado\s+técnico)/i', $msg_lower)) && $has_policy) {
                        $cmd = 'ver';
                        $tokens = ['ver', 'poliza', $m_pol[1]];
                        $translated_cmd = true;
                    } elseif (preg_match('/(fianza|judicial|comercial|detalle|detalles|investiga|ver)/i', $msg_lower) && $has_fianza) {
                        $cmd = 'ver';
                        $tokens = ['ver', 'fianza', $m_fz[1]];
                        $translated_cmd = true;
                    }
                    
                    // Verificar si está en el flujo de cotización
                    $stmt_s = $db->prepare("SELECT * FROM chat_bot_sesiones WHERE usuario_id = ? AND bot_id = ? LIMIT 1");
                    $stmt_s->bind_param("ii", $usuario_id, $bot_ssindi_id);
                    $stmt_s->execute();
                    $session = $stmt_s->get_result()->fetch_assoc();
                    $stmt_s->close();

                    $is_nlp_quote = false;
                    if (!$translated_cmd) {
                        if ($session !== null || preg_match('/(cotizar|cotizacion|cotización|seguro|ley|precio|costo|tarifa|coti|corregir|corrección|correccion|corrige|cambiar|cambia|cambio|modificar|modifica|modificacion|modificación|puse mal|mal escrito|incorrecto)/iu', $msg_lower)) {
                            $is_nlp_quote = true;
                        }
                    }
                    
                    if ($is_nlp_quote) {
                        if (preg_match('/^(cancelar|salir|reiniciar|limpiar|reset)\b/iu', $mensaje_clean)) {
                            $db->query("DELETE FROM chat_bot_sesiones WHERE usuario_id = $usuario_id AND bot_id = $bot_ssindi_id");
                            $bot_reply = "🤖 **BBS-BOT-BUSINES-SERVICE (SSINDI)**: Entendido. He cancelado el proceso de cotización activo y limpiado tu sesión. ¿Hay alguna otra consulta en la que te pueda asistir? 🤝";
                        } else {
                            $datos_acumulados = [];
                            if ($session) {
                                $datos_acumulados = json_decode($session['datos_temporales'], true) ?? [];
                            }

                            // 1. VALIDACIÓN EN TIEMPO REAL
                            $correo_invalido_detectado = null;
                            $año_invalido_detectado = null;

                            // A. Correo Electrónico
                            $palabras = explode(' ', $mensaje_clean);
                            foreach ($palabras as $p) {
                                $p_clean = trim($p, ".,:;()[]{}*`\"'");
                                if (strpos($p_clean, '@') !== false || preg_match('/\b[a-zA-Z0-9._%+-]+(?:\.|at)[a-zA-Z0-9.-]+\.(?:com|es|net|org|edu)\b/i', $p_clean)) {
                                    if (!filter_var($p_clean, FILTER_VALIDATE_EMAIL)) {
                                        $correo_invalido_detectado = $p_clean;
                                        break;
                                    }
                                }
                            }

                            // B. Año de Vehículo
                            if (preg_match('/\b(\d{4})\b/', $mensaje_clean, $m_yr)) {
                                $yr = (int)$m_yr[1];
                                if ($yr < 1920 || $yr > 2027) {
                                    if (!preg_match('/(?:cedula|cédula|rnc|telefono|teléfono|tel|cel|\b\d{1,3}-|\b\d{5,})\b/i', $mensaje_clean)) {
                                        $año_invalido_detectado = $yr;
                                    }
                                }
                            }

                            // C. Procesar Comando de Corrección
                            $es_correccion = preg_match('/(corregir|corrección|correccion|corrige|cambiar|cambia|cambio|modificar|modifica|modificacion|modificación|puse mal|mal escrito|incorrecto)/iu', $mensaje_clean);

                            if ($correo_invalido_detectado) {
                                $bot_reply = "🤖 **BBS-BOT-BUSINES-SERVICE (SSINDI)**: ⚠️ **Correo mal escrito**: He detectado que ingresaste un correo con formato incorrecto (`{$correo_invalido_detectado}`). Por favor, escríbelo correctamente (ejemplo: nombre@dominio.com).";
                            }
                            elseif ($año_invalido_detectado) {
                                $bot_reply = "🤖 **BBS-BOT-BUSINES-SERVICE (SSINDI)**: ⚠️ **Año de vehículo inválido**: He detectado que ingresaste un año de vehículo fuera de rango (`{$año_invalido_detectado}`). El año debe estar entre 1920 y 2027. Por favor, corrígelo.";
                            }
                            elseif ($es_correccion) {
                                $campos_corregidos = [];
                                $new_email_val = null;
                                $new_name_val = null;
                                $new_tipo_veh = null;
                                $new_yr_val = null;
                                $new_color_val = null;
                                $new_brand_val = null;
                                
                                if (preg_match('/\b([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})\b/u', $mensaje_clean, $m_em)) {
                                    if (filter_var($m_em[1], FILTER_VALIDATE_EMAIL)) {
                                        $new_email_val = strtolower(trim($m_em[1]));
                                        $campos_corregidos[] = "Correo electrónico";
                                    }
                                }
                                
                                if (preg_match('/\b(\d{4})\b/', $mensaje_clean, $m_y)) {
                                    $yr = (int)$m_y[1];
                                    if ($yr >= 1920 && $yr <= 2027) {
                                        if (!preg_match('/(?:cedula|cédula|rnc|telefono|teléfono|tel|cel|\b\d{1,3}-|\b\d{5,})\b/i', $mensaje_clean)) {
                                            $new_yr_val = $yr;
                                            $campos_corregidos[] = "Año del vehículo";
                                        }
                                    }
                                }
                                
                                if (preg_match('/\b(moto|motocicleta|motocicletas|pasola|trimotor|four\s*wheel|buggy|cuatrimoto)\b/iu', $mensaje_clean)) {
                                    $new_tipo_veh = 'MOTOCICLETAS';
                                    $campos_corregidos[] = "Tipo de vehículo";
                                } elseif (preg_match('/\b(jeep|jeepeta|suv|4x4)\b/iu', $mensaje_clean)) {
                                    $new_tipo_veh = 'JEEP';
                                    $campos_corregidos[] = "Tipo de vehículo";
                                } elseif (preg_match('/\b(camioneta|platanera|d-max|hilux)\b/iu', $mensaje_clean)) {
                                    $new_tipo_veh = 'CAMIONETAS';
                                    $campos_corregidos[] = "Tipo de vehículo";
                                } elseif (preg_match('/\b(minivan|vanette|van|furgoneta|autobus|microbus)\b/iu', $mensaje_clean)) {
                                    $new_tipo_veh = 'MINIVAN / VANETTES';
                                    $campos_corregidos[] = "Tipo de vehículo";
                                } elseif (preg_match('/\b(carro|automovil|automóvil|automoviles|automóviles|sedan|sedán|coupe|coupé)\b/iu', $mensaje_clean)) {
                                    $new_tipo_veh = 'AUTOMOVILES';
                                    $campos_corregidos[] = "Tipo de vehículo";
                                }
                                
                                if (preg_match('/(?:nombre|cliente|titular|asegurado)(?:\s+(?:a|es|de|seria|sería))?\s*:?\s*([a-zA-ZáéíóúÁÉÍÓÚñÑ\s]{3,40})/iu', $mensaje_clean, $m_nm)) {
                                    $new_name_val = trim(preg_replace('/^(de|del|cliente|nombre|titular|es|a)\s+/iu', '', trim($m_nm[1])));
                                    $campos_corregidos[] = "Nombre del cliente";
                                }
                                
                                if (preg_match('/\b(negro|blanco|gris|rojo|azul|plateado|plata|verde|amarillo)\b/iu', $mensaje_clean, $m_col)) {
                                    $new_color_val = ucfirst(mb_strtolower($m_col[1], 'UTF-8'));
                                }
                                
                                $brands_list = ['toyota', 'honda', 'kia', 'hyundai', 'nissan', 'chevrolet', 'ford', 'mazda', 'suzuki', 'lexus', 'jeep', 'mitsubishi', 'bmw', 'mercedes'];
                                foreach ($brands_list as $b) {
                                    if (preg_match('/\b' . $b . '\b/iu', $mensaje_clean)) {
                                        if (preg_match('/\b(' . $b . '\s+[a-z0-9]+(?:\s+[a-z0-9]+)?)\b/iu', $mensaje_clean, $m_br)) {
                                            $new_brand_val = ucwords(mb_strtolower($m_br[1], 'UTF-8'));
                                        } else {
                                            $new_brand_val = ucfirst($b);
                                        }
                                        $campos_corregidos[] = "Marca/Modelo";
                                        break;
                                    }
                                }

                                if (!empty($campos_corregidos)) {
                                    if ($session) {
                                        $datos = json_decode($session['datos_temporales'], true) ?? [];
                                        if ($new_email_val) $datos['cliente_email'] = $new_email_val;
                                        if ($new_name_val) $datos['cliente_nombre'] = $new_name_val;
                                        if ($new_tipo_veh) $datos['tipo_vehiculo'] = $new_tipo_veh;
                                        
                                        if ($new_yr_val || $new_color_val || $new_brand_val) {
                                            $curr_details = $datos['marca_modelo'] ?? '';
                                            $curr_yr = preg_match('/\b(\d{4})\b/', $curr_details, $my) ? $my[1] : '';
                                            $colors_list = ['negro', 'blanco', 'gris', 'rojo', 'azul', 'plateado', 'plata', 'verde', 'amarillo'];
                                            $curr_col = '';
                                            foreach ($colors_list as $col) {
                                                if (preg_match('/\b' . $col . '\b/i', $curr_details)) {
                                                    $curr_col = ucfirst($col);
                                                    break;
                                                }
                                            }
                                            $curr_brand = trim(str_ireplace([$curr_yr, $curr_col], '', $curr_details));
                                            
                                            if ($new_yr_val) $curr_yr = $new_yr_val;
                                            if ($new_color_val) $curr_col = $new_color_val;
                                            if ($new_brand_val) $curr_brand = $new_brand_val;
                                            
                                            $details = [];
                                            if ($curr_brand) $details[] = $curr_brand;
                                            if ($curr_col) $details[] = $curr_col;
                                            if ($curr_yr) $details[] = $curr_yr;
                                            
                                            $datos['marca_modelo'] = implode(' ', $details);
                                        }
                                        
                                        $datos_json = json_encode($datos, JSON_UNESCAPED_UNICODE);
                                        $stmt_up = $db->prepare("UPDATE chat_bot_sesiones SET datos_temporales = ? WHERE usuario_id = ? AND bot_id = ?");
                                        $stmt_up->bind_param("sii", $datos_json, $usuario_id, $bot_ssindi_id);
                                        $stmt_up->execute();
                                        $stmt_up->close();
                                        
                                        $fields_str = implode(', ', array_unique($campos_corregidos));
                                        $bot_reply = "🤖 **BBS-BOT-BUSINES-SERVICE (SSINDI)**: ¡Excelente! He corregido de forma positiva los siguientes datos en tu sesión: **{$fields_str}**.\n\n" .
                                                     "Tus datos actuales son:\n" .
                                                     "• **Nombre**: " . ($datos['cliente_nombre'] ?? '_Faltante_') . "\n" .
                                                     "• **Correo**: " . ($datos['cliente_email'] ?? '_Faltante_') . "\n" .
                                                     "• **Vehículo**: " . ($datos['marca_modelo'] ?? '_Faltante_') . " (" . ($datos['tipo_vehiculo'] ?? '_Faltante_') . ")\n\n" .
                                                     "¿Hay algún otro dato que desees modificar o procedemos con la cotización?";
                                    } else {
                                        $stmt_q = $db->prepare("SELECT * FROM cotizaciones WHERE creado_por = ? AND origen = 'bot' AND fecha >= DATE_SUB(NOW(), INTERVAL 15 MINUTE) ORDER BY id DESC");
                                        $stmt_q->bind_param("i", $usuario_id);
                                        $stmt_q->execute();
                                        $quotes = $stmt_q->get_result()->fetch_all(MYSQLI_ASSOC);
                                        $stmt_q->close();
                                        
                                        if (!empty($quotes)) {
                                            $opciones_reply = "";
                                            foreach ($quotes as $q) {
                                                $prev_q = $q;
                                                $new_cliente = $new_name_val ?: $q['cliente'];
                                                $new_email_val_col = $new_email_val ?: $q['email'];
                                                $new_subtipo = $new_tipo_veh ?: $q['subtipo'];
                                                
                                                $new_beneficiario = $q['beneficiario'];
                                                if ($new_yr_val || $new_color_val || $new_brand_val) {
                                                    $curr_details = $q['beneficiario'];
                                                    $curr_yr = preg_match('/\b(\d{4})\b/', $curr_details, $my) ? $my[1] : '';
                                                    $colors_list = ['negro', 'blanco', 'gris', 'rojo', 'azul', 'plateado', 'plata', 'verde', 'amarillo'];
                                                    $curr_col = '';
                                                    foreach ($colors_list as $col) {
                                                        if (preg_match('/\b' . $col . '\b/i', $curr_details)) {
                                                            $curr_col = ucfirst($col);
                                                            break;
                                                        }
                                                    }
                                                    $curr_brand = trim(str_ireplace([$curr_yr, $curr_col], '', $curr_details));
                                                    
                                                    if ($new_yr_val) $curr_yr = $new_yr_val;
                                                    if ($new_color_val) $curr_col = $new_color_val;
                                                    if ($new_brand_val) $curr_brand = $new_brand_val;
                                                    
                                                    $details = [];
                                                    if ($curr_brand) $details[] = $curr_brand;
                                                    if ($curr_col) $details[] = $curr_col;
                                                    if ($curr_yr) $details[] = $curr_yr;
                                                    
                                                    $new_beneficiario = implode(' ', $details);
                                                }
                                                
                                                $new_prima_base = $q['prima_base'];
                                                $new_impuesto = $q['impuesto'];
                                                $new_total = $q['total'];
                                                $new_capacidad = $q['capacidad'];
                                                $new_cobertura = $q['cobertura'];
                                                
                                                if ($new_tipo_veh && $new_tipo_veh !== $q['subtipo']) {
                                                    if ($new_tipo_veh === 'AUTOMOVILES') $new_capacidad = 'Hasta 4 Cilindros';
                                                    elseif ($new_tipo_veh === 'JEEP') $new_capacidad = 'Hasta 4 Cilindros';
                                                    elseif ($new_tipo_veh === 'MOTOCICLETAS') $new_capacidad = 'Hasta 250 cc';
                                                    
                                                    $new_cobertura = ($new_tipo_veh === 'MOTOCICLETAS') ? 'MOTOCICLETA BASICO' : 'LIVIANO BASICO';
                                                    
                                                    $stmt_comp = $db->prepare("SELECT id FROM companias_registradas WHERE nombre = ? AND tipo = 'aseguradora' LIMIT 1");
                                                    $stmt_comp->bind_param("s", $q['aseguradora']);
                                                    $stmt_comp->execute();
                                                    $comp_row = $stmt_comp->get_result()->fetch_assoc();
                                                    $stmt_comp->close();
                                                    $ins_id = $comp_row ? (int)$comp_row['id'] : 1;
                                                    
                                                    $stmt_t = $db->prepare("SELECT tarifa_base FROM tarifas_seguro WHERE tipo = ? AND capacidad = ? AND uso = ? AND compania_id = ? AND activo = 1 LIMIT 1");
                                                    $stmt_t->bind_param("sssi", $new_tipo_veh, $new_capacidad, $q['uso'], $ins_id);
                                                    $stmt_t->execute();
                                                    $rate_res = $stmt_t->get_result()->fetch_assoc();
                                                    $stmt_t->close();
                                                    
                                                    $tasa_aplicada = 4500.00;
                                                    if ($rate_res) {
                                                        $tasa_aplicada = (float)$rate_res['tarifa_base'];
                                                    } else {
                                                        $stmt_t1 = $db->prepare("SELECT tarifa_base FROM tarifas_seguro WHERE tipo = ? AND capacidad = ? AND uso = ? AND compania_id = 1 AND activo = 1 LIMIT 1");
                                                        $stmt_t1->bind_param("sss", $new_tipo_veh, $new_capacidad, $q['uso']);
                                                        $stmt_t1->execute();
                                                        $rate_t1 = $stmt_t1->get_result()->fetch_assoc();
                                                        $stmt_t1->close();
                                                        if ($rate_t1) {
                                                            $tasa_aplicada = (float)$rate_t1['tarifa_base'];
                                                        }
                                                        if ($ins_id == 2) {
                                                            $tasa_aplicada = round($tasa_aplicada * 1.05, 2);
                                                        }
                                                    }
                                                    
                                                    $new_total = $tasa_aplicada;
                                                    $new_prima_base = round($new_total / 1.16, 2);
                                                    $new_impuesto = round($new_total - $new_prima_base, 2);
                                                }
                                                
                                                $stmt_u_q = $db->prepare("UPDATE cotizaciones SET cliente = ?, email = ?, subtipo = ?, capacidad = ?, cobertura = ?, prima_base = ?, impuesto = ?, total = ?, beneficiario = ? WHERE id = ?");
                                                $stmt_u_q->bind_param("sssssdddsi", $new_cliente, $new_email_val_col, $new_subtipo, $new_capacidad, $new_cobertura, $new_prima_base, $new_impuesto, $new_total, $new_beneficiario, $q['id']);
                                                $stmt_u_q->execute();
                                                $stmt_u_q->close();
                                                
                                                $new_q = $q;
                                                $new_q['cliente'] = $new_cliente;
                                                $new_q['email'] = $new_email_val_col;
                                                $new_q['subtipo'] = $new_subtipo;
                                                $new_q['capacidad'] = $new_capacidad;
                                                $new_q['cobertura'] = $new_cobertura;
                                                $new_q['prima_base'] = $new_prima_base;
                                                $new_q['impuesto'] = $new_impuesto;
                                                $new_q['total'] = $new_total;
                                                $new_q['beneficiario'] = $new_beneficiario;
                                                if (function_exists('registrarAjuste')) {
                                                    $justificacion = "Corrección del cliente vía BOT BBS: " . implode(', ', $campos_corregidos);
                                                    registrarAjuste($usuario_id, 'COTIZACIONES', 'cotizaciones', $q['id'], $prev_q, $new_q, $justificacion);
                                                }
                                                
                                                $descargar_link = API_BASE_URL . "/chat.php?action=descargar_cotizacion&id=" . $q['id'] . "&token_sesion=" . $bearer_token;
                                                $initial = strtoupper(substr($q['aseguradora'], 0, 1));
                                                $opciones_reply .= "<div class='chat-quote-row'>" .
                                                                   "<div class='chat-quote-card'>" .
                                                                   "<div class='chat-quote-logo'>{$initial}</div>" .
                                                                   "<span class='chat-quote-company'>" . $q['aseguradora'] . "</span>" .
                                                                   "<span class='chat-quote-price'>RD$ " . number_format($new_total, 2) . "</span>" .
                                                                   "</div>" .
                                                                   "<div class='chat-quote-actions'>" .
                                                                   "<a href='" . $descargar_link . "' class='chat-quote-btn btn-download' title='Descargar PDF' target='_blank'>📥</a>" .
                                                                   "<button class='chat-quote-btn btn-email' title='Enviar por Correo' onclick='MQF.enviarEmailCotizacion(this, " . $q['id'] . ")'>📧</button>" .
                                                                   "</div>" .
                                                                   "</div>\n\n";
                                            }
                                            $fields_str = implode(', ', array_unique($campos_corregidos));
                                            $bot_reply = "🤖 **BBS-BOT-BUSINES-SERVICE (SSINDI)**: ¡Entendido! He corregido de forma positiva tus cotizaciones con: **{$fields_str}**.\n\n" .
                                                         "Se aplicaron las correcciones y las tarifas correspondientes bajo la norma de auditoría **NOFTRAB**. Aquí tienes los accesos a las cotizaciones actualizadas:\n\n" .
                                                         $opciones_reply;
                                        } else {
                                            $bot_reply = "🤖 **BBS-BOT-BUSINES-SERVICE (SSINDI)**: Entiendo que quieres corregir un dato, pero no encontré ninguna cotización reciente (últimos 15 minutos) ni sesión activa para modificar. Por favor, indícame los datos de tu vehículo para iniciar una cotización.";
                                        }
                                    }
                                } else {
                                    $bot_reply = "🤖 **BBS-BOT-BUSINES-SERVICE (SSINDI)**: Veo que quieres corregir un dato, pero no logré extraer los nuevos valores del mensaje. Por favor indícame algo claro como *'corrige el correo a nuevo@email.com'* o *'cambia el nombre a Juan Pérez'*.";
                                }
                            }
                            else {
                                // 2. ANÁLISIS DE DATOS NORMAL
                                if (preg_match('/\b(moto|motocicleta|motocicletas|pasola|trimotor|four\s*wheel|buggy|cuatrimoto)\b/iu', $msg_lower)) {
                                    $datos_acumulados['tipo_vehiculo'] = 'MOTOCICLETAS';
                                } elseif (preg_match('/\b(jeep|jeepeta|suv|4x4)\b/iu', $msg_lower)) {
                                    $datos_acumulados['tipo_vehiculo'] = 'JEEP';
                                } elseif (preg_match('/\b(camioneta|platanera|d-max|hilux)\b/iu', $msg_lower)) {
                                    $datos_acumulados['tipo_vehiculo'] = 'CAMIONETAS';
                                } elseif (preg_match('/\b(minivan|vanette|van|furgoneta|autobus|microbus)\b/iu', $msg_lower)) {
                                    $datos_acumulados['tipo_vehiculo'] = 'MINIVAN / VANETTES';
                                } elseif (preg_match('/\b(carro|automovil|automóvil|automoviles|automóviles|sedan|sedán|coupe|coupé)\b/iu', $msg_lower)) {
                                    $datos_acumulados['tipo_vehiculo'] = 'AUTOMOVILES';
                                }

                                $detected_capacidad = null;
                                if (!empty($datos_acumulados['tipo_vehiculo']) && $datos_acumulados['tipo_vehiculo'] === 'MOTOCICLETAS') {
                                    if (preg_match('/\b(electr|eléctr)\b/iu', $msg_lower)) $detected_capacidad = 'Bicicleta Eléctrica';
                                    elseif (preg_match('/\b(buggy)\b/iu', $msg_lower)) $detected_capacidad = 'Buggy';
                                    elseif (preg_match('/\b(four\s*wheel)\b/iu', $msg_lower)) $detected_capacidad = 'Four Wheel';
                                    elseif (preg_match('/\b(cuatrimoto)\b/iu', $msg_lower)) $detected_capacidad = 'Cuatrimoto';
                                    elseif (preg_match('/\b(350\s*(cc)?)\b/iu', $msg_lower)) $detected_capacidad = 'Mas de 350 cc';
                                    elseif (preg_match('/\b(250\s*(cc)?)\b/iu', $msg_lower)) {
                                        if (preg_match('/(mas de|más de|>)\s*250/iu', $msg_lower)) $detected_capacidad = 'Mas de 250 cc';
                                        else $detected_capacidad = 'Hasta 250 cc';
                                    }
                                } elseif (!empty($datos_acumulados['tipo_vehiculo']) && ($datos_acumulados['tipo_vehiculo'] === 'AUTOMOVILES' || $datos_acumulados['tipo_vehiculo'] === 'JEEP')) {
                                    if (preg_match('/\b(6\s*(cil|cyl|cilindros)|v6|seis\s*cil)\b/iu', $msg_lower)) $detected_capacidad = 'Hasta 6 Cilindros';
                                    elseif (preg_match('/\b(8\s*(cil|cyl|cilindros)|v8|mas de 6|más de 6)\b/iu', $msg_lower)) $detected_capacidad = 'Más de 6 Cilindros';
                                    elseif (preg_match('/\b(4\s*(cil|cyl|cilindros)|v4|cuatro\s*cil)\b/iu', $msg_lower)) $detected_capacidad = 'Hasta 4 Cilindros';
                                }
                                if ($detected_capacidad) {
                                    $datos_acumulados['capacidad'] = $detected_capacidad;
                                }

                                if (preg_match('/\b(publico|público|concho|taxi|transporte)\b/iu', $msg_lower)) {
                                    $datos_acumulados['uso'] = 'PUBLICO';
                                } elseif (preg_match('/\b(rent|alquiler|arrendar)\b/iu', $msg_lower)) {
                                    $datos_acumulados['uso'] = 'RENT CAR';
                                } elseif (preg_match('/\b(privado|particular|personal)\b/iu', $msg_lower)) {
                                    $datos_acumulados['uso'] = 'PRIVADO';
                                }

                                $detected_brand = null;
                                $brands = ['toyota', 'honda', 'kia', 'hyundai', 'nissan', 'chevrolet', 'ford', 'mazda', 'suzuki', 'lexus', 'jeep', 'mitsubishi', 'bmw', 'mercedes'];
                                foreach ($brands as $b) {
                                    if (preg_match('/\b' . $b . '\b/iu', $msg_lower)) {
                                        if (preg_match('/\b(' . $b . '\s+[a-z0-9]+(?:\s+[a-z0-9]+)?)\b/iu', $msg_lower, $m_brand)) {
                                            $detected_brand = ucwords(mb_strtolower($m_brand[1], 'UTF-8'));
                                        } else {
                                            $detected_brand = ucfirst($b);
                                        }
                                        break;
                                    }
                                }
                                $detected_color = null;
                                if (preg_match('/\b(negro|blanco|gris|rojo|azul|plateado|plata|verde|amarillo)\b/iu', $msg_lower, $m_col)) {
                                    $detected_color = ucfirst(mb_strtolower($m_col[1], 'UTF-8'));
                                }
                                $detected_year = null;
                                if (preg_match('/\b(19\d{2}|20\d{2})\b/', $mensaje_clean, $m_yr)) {
                                    $detected_year = $m_yr[1];
                                }

                                if ($detected_brand || $detected_color || $detected_year) {
                                    $details = [];
                                    if ($detected_brand) $details[] = $detected_brand;
                                    if ($detected_color) $details[] = $detected_color;
                                    if ($detected_year) $details[] = $detected_year;
                                    $datos_acumulados['marca_modelo'] = implode(' ', $details);
                                }

                                if (preg_match('/(?:nombre|a nombre de|cliente|titular)\s*:?\s*([a-zA-ZáéíóúÁÉÍÓÚñÑ\s]{3,30})/iu', $mensaje_clean, $m_name)) {
                                    $datos_acumulados['cliente_nombre'] = trim(preg_replace('/^(de|del|cliente|nombre|titular)\s+/iu', '', trim($m_name[1])));
                                }

                                if (preg_match('/\b([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})\b/u', $mensaje_clean, $m_email)) {
                                    $datos_acumulados['cliente_email'] = strtolower(trim($m_email[1]));
                                }

                                if (!empty($datos_acumulados['tipo_vehiculo']) && empty($datos_acumulados['capacidad'])) {
                                    if ($datos_acumulados['tipo_vehiculo'] === 'AUTOMOVILES') $datos_acumulados['capacidad'] = 'Hasta 4 Cilindros';
                                    elseif ($datos_acumulados['tipo_vehiculo'] === 'JEEP') $datos_acumulados['capacidad'] = 'Hasta 4 Cilindros';
                                    elseif ($datos_acumulados['tipo_vehiculo'] === 'MOTOCICLETAS') $datos_acumulados['capacidad'] = 'Hasta 250 cc';
                                }
                                if (empty($datos_acumulados['uso'])) {
                                    $datos_acumulados['uso'] = 'PRIVADO';
                                }

                                $missing = [];
                                if (empty($datos_acumulados['tipo_vehiculo'])) {
                                    $missing[] = "• **Tipo de vehículo** (ej: carro, motocicleta, jeepeta, camioneta).";
                                }
                                if (empty($datos_acumulados['marca_modelo'])) {
                                    $missing[] = "• **Datos del vehículo** (marca, modelo, color y año, ej: Toyota Corolla 2019 Negro).";
                                }
                                if (empty($datos_acumulados['cliente_nombre'])) {
                                    $missing[] = "• **Nombre del cliente** (nombre completo a registrar).";
                                }
                                if (empty($datos_acumulados['cliente_email'])) {
                                    $missing[] = "• **Correo electrónico** (para enviarle la cotización seleccionada).";
                                }

                                if (!empty($missing)) {
                                    $datos_json = json_encode($datos_acumulados, JSON_UNESCAPED_UNICODE);
                                    $db->query("INSERT INTO chat_bot_sesiones (usuario_id, bot_id, flujo, paso, datos_temporales) VALUES ($usuario_id, $bot_ssindi_id, 'cotizacion_seguro', 'esperando_datos', '" . $db->real_escape_string($datos_json) . "') ON DUPLICATE KEY UPDATE paso = 'esperando_datos', datos_temporales = '" . $db->real_escape_string($datos_json) . "'");

                                    $bot_reply = "🤖 **BBS-BOT-BUSINES-SERVICE (SSINDI)**:\n" .
                                                 "¡Hola! Con mucho gusto te asisto con la cotización de tu Seguro de Ley. Para poder generar las opciones reales de cada aseguradora en nuestro sistema, por favor indícame los siguientes datos faltantes:\n\n" .
                                                 implode("\n", $missing) . "\n\n" .
                                                 "Puedes escribir esta información en uno o varios mensajes, a tu propio ritmo. Si deseas cancelar este proceso en cualquier momento, solo escribe **cancelar**. ¡Quedo a la espera! 😊";
                                } else {
                                    $db->query("DELETE FROM chat_bot_sesiones WHERE usuario_id = $usuario_id AND bot_id = $bot_ssindi_id");

                                    $tipo_vehiculo = $datos_acumulados['tipo_vehiculo'];
                                    $capacidad = $datos_acumulados['capacidad'];
                                    $uso = $datos_acumulados['uso'];
                                    $subtipo_veh = $datos_acumulados['marca_modelo'];
                                    $nombre_completo = $datos_acumulados['cliente_nombre'];
                                    $email_usuario = $datos_acumulados['cliente_email'];

                                    $res_aseg = $db->query("SELECT id, nombre FROM companias_registradas WHERE tipo = 'aseguradora' AND estado = 1");
                                    $aseguradoras_list = [];
                                    while ($r_aseg = $res_aseg->fetch_assoc()) {
                                        $aseguradoras_list[] = $r_aseg;
                                    }
                                    if (empty($aseguradoras_list)) {
                                        $aseguradoras_list[] = ["id" => 1, "nombre" => "Multiseguros"];
                                    }

                                    $opciones_reply = "";
                                    foreach ($aseguradoras_list as $aseg) {
                                        $ins_id = (int)$aseg['id'];
                                        $ins_name = $aseg['nombre'];

                                        $stmt_t = $db->prepare("SELECT tarifa_base FROM tarifas_seguro WHERE tipo = ? AND capacidad = ? AND uso = ? AND compania_id = ? AND activo = 1 LIMIT 1");
                                        $stmt_t->bind_param("sssi", $tipo_vehiculo, $capacidad, $uso, $ins_id);
                                        $stmt_t->execute();
                                        $rate_res = $stmt_t->get_result()->fetch_assoc();
                                        $stmt_t->close();

                                        $tasa_aplicada = 4500.00;
                                        $cobertura_code = 'LIVIANO BASICO';
                                        if ($tipo_vehiculo === 'MOTOCICLETAS') $cobertura_code = 'MOTOCICLETA BASICO';

                                        if ($rate_res) {
                                            $tasa_aplicada = (float)$rate_res['tarifa_base'];
                                        } else {
                                            $stmt_t1 = $db->prepare("SELECT tarifa_base FROM tarifas_seguro WHERE tipo = ? AND capacidad = ? AND uso = ? AND compania_id = 1 AND activo = 1 LIMIT 1");
                                            $stmt_t1->bind_param("sss", $tipo_vehiculo, $capacidad, $uso);
                                            $stmt_t1->execute();
                                            $rate_t1 = $stmt_t1->get_result()->fetch_assoc();
                                            $stmt_t1->close();

                                            if ($rate_t1) {
                                                $tasa_aplicada = (float)$rate_t1['tarifa_base'];
                                            }
                                            if ($ins_id == 2) {
                                                $tasa_aplicada = round($tasa_aplicada * 1.05, 2);
                                            }
                                        }

                                        $total = $tasa_aplicada;
                                        $prima_base = round($total / 1.16, 2);
                                        $impuesto = round($total - $prima_base, 2);

                                        $numero_cot = 'COT-2026-' . rand(1000, 9999);

                                        $stmt_ins_cot = $db->prepare("
                                            INSERT INTO cotizaciones (
                                                numero, numero_cotizacion, cliente, email, tipo, subtipo, capacidad, uso, 
                                                cobertura, prima_base, impuesto, total, estado, creado_por, fecha_creacion, fecha, aseguradora, beneficiario, origen
                                            ) VALUES (?, ?, ?, ?, 'SEGURO DE LEY', ?, ?, ?, ?, ?, ?, ?, 'pendiente', ?, NOW(), NOW(), ?, ?, 'bot')
                                        ");
                                        $stmt_ins_cot->bind_param(
                                            "ssssssssdddiss",
                                            $numero_cot, $numero_cot, $nombre_completo, $email_usuario, $tipo_vehiculo, 
                                            $capacidad, $uso, $cobertura_code, $prima_base, $impuesto, $total, $usuario_id, $ins_name, $subtipo_veh
                                        );
                                        $stmt_ins_cot->execute();
                                        $cotizacion_id = $stmt_ins_cot->insert_id;
                                        $stmt_ins_cot->close();

                                        $descargar_link = API_BASE_URL . "/chat.php?action=descargar_cotizacion&id=" . $cotizacion_id . "&token_sesion=" . $bearer_token;
                                        $initial = strtoupper(substr($ins_name, 0, 1));
                                        $opciones_reply .= "<div class='chat-quote-row'>" .
                                                           "<div class='chat-quote-card'>" .
                                                           "<div class='chat-quote-logo'>{$initial}</div>" .
                                                           "<span class='chat-quote-company'>{$ins_name}</span>" .
                                                           "<span class='chat-quote-price'>RD$ " . number_format($total, 2) . "</span>" .
                                                           "</div>" .
                                                           "<div class='chat-quote-actions'>" .
                                                           "<a href='" . $descargar_link . "' class='chat-quote-btn btn-download' title='Descargar PDF' target='_blank'>📥</a>" .
                                                           "<button class='chat-quote-btn btn-email' title='Enviar por Correo' onclick='MQF.enviarEmailCotizacion(this, " . $cotizacion_id . ")'>📧</button>" .
                                                           "</div>" .
                                                           "</div>\n\n";
                                    }

                                    $bot_reply = "🤖 **BBS-BOT-BUSINES-SERVICE (SSINDI)**:\n" .
                                                 "¡Excelente! He procesado los datos de tu vehículo y generado una cotización formal en el sistema para cada aseguradora registrada con nuestros servicios:\n\n" .
                                                 "🚗 **Detalles del Vehículo**: " . $subtipo_veh . " (" . $tipo_vehiculo . ")\n" .
                                                 "👤 **Asegurado**: " . $nombre_completo . " (" . $email_usuario . ")\n\n" .
                                                 $opciones_reply .
                                                 "📄 **Servicios Incluidos en todas las opciones**:\n" .
                                                 "• **Asistencia Vial 24/7** (Servicio de grúa, cambio de neumático, combustible y carga de batería).\n" .
                                                 "• **Centro del Automovilista** (Asistencia legal especializada ante accidentes de tránsito).\n\n" .
                                                 "Por favor, elige la opción de tu preferencia para descargar su PDF o enviársela directamente a su correo.";
                                }
                            }
                        }
                    }
                    elseif ($cmd === 'emitir') {
                        $cot_param = $tokens[1] ?? '';
                        if ($archivo_ruta === null) {
                            $bot_reply = "🤖 **BBS-BOT-BUSINES-SERVICE (SSINDI)**: Para proceder con la emisión digital de la póliza basada en la cotización **#{$cot_param}**, se requieren los siguientes documentos:\n1. Matrícula, 2. Cédula, 3. Voucher de pago.\nPor favor adjunte su voucher en este chat y envíe el comando de nuevo.";
                        } else {
                            $stmt_c = $db->prepare("SELECT * FROM cotizaciones WHERE id = ? OR numero = ? LIMIT 1");
                            $stmt_c->bind_param("is", $cot_param, $cot_param);
                            $stmt_c->execute();
                            $cot = $stmt_c->get_result()->fetch_assoc();
                            $stmt_c->close();
                            
                            if (!$cot) {
                                $bot_reply = "🤖 **BBS-BOT-BUSINES-SERVICE (SSINDI)**: No se pudo encontrar una cotización con ID {$cot_param}";
                            } else {
                                $numero_pol = 'POL-2026-' . rand(1000, 9999);
                                $stmt_ins_pol = $db->prepare("
                                    INSERT INTO polizas (
                                        numero_poliza, cotizacion_id, cliente_id, tipo_seguro, tipo_poliza, ramo, aseguradora, 
                                        perfil_cobertura, prima_total, prima_neta, itbis, periodicidad_pago, cuota_total, 
                                        fecha_vencimiento, estado, emitida_por, fecha_emision
                                    ) VALUES (?, ?, 1, 'Seguro de Ley - Vehículo Liviano', 'Individual', 'Vehículos de Motor', 'MÁS QUE FIANZAS', 
                                    'Seguro Obligatorio de Ley', ?, ?, ?, 'anual', 1, DATE_ADD(NOW(), INTERVAL 1 YEAR), 'activa', ?, NOW())
                                ");
                                $stmt_ins_pol->bind_param("sidddi", $numero_pol, $cot['id'], $cot['total'], $cot['prima_base'], $cot['impuesto'], $usuario_id);
                                $stmt_ins_pol->execute();
                                $poliza_id = $stmt_ins_pol->insert_id;
                                $stmt_ins_pol->close();
                                
                                $num_ref = 'REF-' . rand(100000, 999999);
                                $num_rec = 'RC-' . rand(10000, 99999);
                                $num_ncf = 'B02' . sprintf('%08d', rand(1, 99999));
                                $stmt_ins_pag = $db->prepare("
                                    INSERT INTO pagos (
                                        numero_referencia, numero_recibo, numero_ncf, tipo_comprobante, poliza_id, cliente_id, 
                                        monto, fecha_pago, tipo_pago, estado_pago, registrado_por, fecha_registro
                                    ) VALUES (?, ?, ?, 'B02', ?, 1, ?, CURDATE(), 'transferencia', 'procesado', ?, NOW())
                                ");
                                $stmt_ins_pag->bind_param("sssidi", $num_ref, $num_rec, $num_ncf, $poliza_id, $cot['total'], $usuario_id);
                                $stmt_ins_pag->execute();
                                $stmt_ins_pag->close();
                                
                                $marbete_link = API_BASE_URL . "/chat.php?action=descargar_marbete&id=" . $poliza_id;
                                $condiciones_link = API_BASE_URL . "/chat.php?action=descargar_condiciones&id=" . $poliza_id;
                                
                                $bot_reply = "🤖 **BBS-BOT-BUSINES-SERVICE (SSINDI)**: ¡Enhorabuena! 🎉 La póliza **{$numero_pol}** ha sido emitida de forma exitosa y se encuentra en estado **ACTIVA**.\n\n" .
                                             "📄 **Documentos Generados**:\n" .
                                             "• **Marbete Digital**: [Descargar Marbete]({$marbete_link})\n" .
                                             "• **Condiciones Generales**: [Descargar Condiciones]({$condiciones_link})";
                            }
                        }
                    }
                    elseif ($cmd === 'ver' && isset($tokens[1]) && $tokens[1] === 'resumen') {
                        $bot_reply = "🤖 **BBS-BOT-BUSINES-SERVICE (SSINDI)**:\nResumen de Actividad de prueba.";
                    }
                    elseif ($cmd === 'ver' && isset($tokens[1]) && $tokens[1] === 'poliza') {
                        $pol_num = $tokens[2] ?? '';
                        $stmt_p = $db->prepare("SELECT * FROM polizas WHERE numero_poliza = ? OR id = ? LIMIT 1");
                        $stmt_p->bind_param("si", $pol_num, $pol_num);
                        $stmt_p->execute();
                        $pol = $stmt_p->get_result()->fetch_assoc();
                        $stmt_p->close();
                        
                        if (!$pol) {
                            $bot_reply = "🤖 Póliza no encontrada.";
                        } else {
                            $total_days = (strtotime($pol['fecha_vencimiento']) - strtotime($pol['fecha_emision'])) / 86400;
                            if ($total_days <= 0) $total_days = 365;
                            $elapsed = (time() - strtotime($pol['fecha_emision'])) / 86400;
                            if ($elapsed < 0) $elapsed = 0;
                            if ($elapsed > $total_days) $elapsed = $total_days;
                            
                            $consumed = ($elapsed / $total_days) * $pol['prima_total'];
                            $prorata = (($total_days - $elapsed) / $total_days) * $pol['prima_total'];
                            
                            $bot_reply = "🤖 **BBS-BOT-BUSINES-SERVICE (SSINDI)**:\n" .
                                         "Detalles Póliza: **" . $pol['numero_poliza'] . "**\n" .
                                         "• **Prima Total**: RD$ " . number_format($pol['prima_total'], 2) . "\n" .
                                         "• **Días transcurridos**: " . round($elapsed) . " de " . round($total_days) . "\n" .
                                         "• **Prima Consumida**: RD$ " . number_format($consumed, 2) . "\n" .
                                         "• **Prorata**: RD$ " . number_format($prorata, 2);
                        }
                    }
                    elseif ($cmd === 'renovar') {
                        $pol_num = $tokens[1] ?? '';
                        $stmt_p = $db->prepare("SELECT * FROM polizas WHERE numero_poliza = ? OR id = ? LIMIT 1");
                        $stmt_p->bind_param("si", $pol_num, $pol_num);
                        $stmt_p->execute();
                        $pol = $stmt_p->get_result()->fetch_assoc();
                        $stmt_p->close();
                        
                        if (!$pol) {
                            $bot_reply = "🤖 Póliza no encontrada.";
                        } else {
                            $stmt_d = $db->prepare("SELECT SUM(monto) as deuda FROM pagos WHERE poliza_id = ? AND estado_pago = 'pendiente'");
                            $stmt_d->bind_param("i", $pol['id']);
                            $stmt_d->execute();
                            $d_res = $stmt_d->get_result()->fetch_assoc();
                            $stmt_d->close();
                            
                            $deuda = $d_res ? (float)$d_res['deuda'] : 0;
                            
                            if ($deuda > 0) {
                                $bot_reply = "🤖 **BBS-BOT-BUSINES-SERVICE (SSINDI)**: ❌ **Renovación Bloqueada**: La póliza **" . $pol['numero_poliza'] . "** registra un balance vencido pendiente de pago de **RD$ " . number_format($deuda, 2) . "**. Debe estar al día.";
                            } else {
                                $nueva_venc = date('Y-m-d', strtotime('+1 year', strtotime($pol['fecha_vencimiento'])));
                                $stmt_up = $db->prepare("UPDATE polizas SET fecha_vencimiento = ? WHERE id = ?");
                                $stmt_up->bind_param("si", $nueva_venc, $pol['id']);
                                $stmt_up->execute();
                                $stmt_up->close();
                                
                                $bot_reply = "🤖 **BBS-BOT-BUSINES-SERVICE (SSINDI)**: ¡Renovación exitosa! Nueva fecha: " . $nueva_venc;
                            }
                        }
                    }
                }
                
                $stmt_bot = $db->prepare("INSERT INTO mensajes_chat (emisor_id, receptor_id, mensaje, fecha_envio, leido) VALUES (?, ?, ?, NOW(), 0)");
                $stmt_bot->bind_param("iis", $bot_ssindi_id, $usuario_id, $bot_reply);
                $stmt_bot->execute();
                $stmt_bot->close();
            }
            
            echo json_encode([
                "exito" => true,
                "mensaje" => "Mensaje enviado con éxito",
                "datos" => [
                    "id" => $nuevo_id,
                    "emisor_id" => $usuario_id,
                    "receptor_id" => $receptor_id,
                    "mensaje" => $mensaje,
                    "fecha_envio" => date('Y-m-d H:i:s'),
                    "leido" => 0,
                    "yo" => true,
                    "archivo_nombre" => $archivo_nombre,
                    "archivo_tipo" => $archivo_tipo,
                    "archivo_size" => $archivo_size
                ]
            ]);
        } else {
            throw new Exception("Error al guardar el mensaje: " . $db->error);
        }
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["exito" => false, "mensaje" => "Error interno: " . $e->getMessage()]);
}
