<?php
/**
 * DOCUMENTACIÓN GENERAL DE LA PLATAFORMA INTEGRADA - PARTE 4
 * MAS QUE FIANZAS - Sistema Integrado de Gestión
 * 
 * Genera documentación completa de los módulos:
 * 1. Modelador PDF
 * 2. LABS-MASQF (Centro de Tecnología)
 */

$platform_name = "MAS QUE FIANZAS";
$platform_version = "9.0";
$generation_date = date('d/m/Y H:i');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentación General - <?php echo $platform_name; ?> - Parte 4</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            line-height: 1.6;
        }
        .menu-container {
            max-width: 1200px;
            margin: 0 auto 30px;
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .menu-container h1 {
            color: #1e293b;
            font-size: 32px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .menu-container .subtitle {
            color: #64748b;
            font-size: 16px;
            margin-bottom: 30px;
        }
        .doc-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .doc-btn {
            padding: 25px;
            border: 2px solid #e2e8f0;
            border-radius: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
            text-align: left;
        }
        .doc-btn:hover {
            border-color: #6366f1;
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(99,102,241,0.2);
        }
        .doc-btn i {
            font-size: 36px;
            color: #6366f1;
            margin-bottom: 15px;
            display: block;
        }
        .doc-btn h3 {
            color: #1e293b;
            font-size: 18px;
            margin-bottom: 8px;
        }
        .doc-btn p {
            color: #64748b;
            font-size: 13px;
            margin: 0;
        }
        .documento {
            display: none;
            max-width: 1200px;
            margin: 20px auto;
            background: white;
            padding: 60px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            border-radius: 10px;
        }
        .documento.activo { display: block; }
        .doc-header {
            border-bottom: 4px solid #6366f1;
            padding-bottom: 30px;
            margin-bottom: 40px;
        }
        .doc-header h1 {
            color: #1e293b;
            font-size: 36px;
            margin-bottom: 15px;
        }
        .doc-header .meta {
            color: #64748b;
            font-size: 14px;
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
        }
        .doc-header .meta span {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .doc-section {
            margin-bottom: 40px;
        }
        .doc-section h2 {
            color: #6366f1;
            font-size: 26px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .doc-section h3 {
            color: #1e293b;
            font-size: 20px;
            margin: 25px 0 15px;
        }
        .doc-section p {
            color: #475569;
            line-height: 1.8;
            margin-bottom: 15px;
            font-size: 15px;
        }
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin: 25px 0;
        }
        .status-card {
            padding: 20px;
            border-radius: 12px;
            border-left: 5px solid;
            background: #f8fafc;
        }
        .status-card.cumple { border-color: #10b981; }
        .status-card.parcial { border-color: #f59e0b; }
        .status-card.na { border-color: #94a3b8; }
        .status-card .label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #64748b;
            margin-bottom: 8px;
        }
        .status-card .value {
            font-size: 24px;
            font-weight: 700;
            color: #1e293b;
        }
        .status-card .desc {
            font-size: 13px;
            color: #64748b;
            margin-top: 8px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 14px;
        }
        th {
            background: #6366f1;
            color: white;
            padding: 14px;
            text-align: left;
            font-weight: 600;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            color: #475569;
        }
        tr:nth-child(even) td { background: #f8fafc; }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-ok { background: #dcfce7; color: #166534; }
        .badge-warn { background: #fef3c7; color: #92400e; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .feature-card {
            background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%);
            border: 2px solid #e2e8f0;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
        }
        .feature-card h4 {
            color: #6366f1;
            font-size: 17px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .feature-card ul {
            margin-left: 25px;
            color: #475569;
            font-size: 14px;
            line-height: 1.9;
        }
        .feature-card li { margin-bottom: 6px; }
        .action-bar {
            position: sticky;
            top: 20px;
            background: white;
            padding: 20px 30px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
            z-index: 100;
        }
        .action-bar h3 {
            color: #1e293b;
            font-size: 18px;
        }
        .action-buttons {
            display: flex;
            gap: 12px;
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.2s;
        }
        .btn-primary { background: #6366f1; color: white; }
        .btn-primary:hover { background: #4f46e5; }
        .btn-secondary { background: #e2e8f0; color: #475569; }
        .btn-secondary:hover { background: #cbd5e1; }
        .btn-success { background: #10b981; color: white; }
        .btn-success:hover { background: #059669; }
        .footer-doc {
            margin-top: 50px;
            padding-top: 25px;
            border-top: 3px solid #e2e8f0;
            text-align: center;
            color: #94a3b8;
            font-size: 13px;
        }
        .code-block {
            background: #1e293b;
            color: #e2e8f0;
            padding: 20px;
            border-radius: 10px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            overflow-x: auto;
            margin: 20px 0;
        }
        @media print {
            body { background: white; padding: 0; }
            .menu-container, .action-bar { display: none !important; }
            .documento { display: block !important; box-shadow: none; margin: 0; padding: 40px; }
        }
        @media (max-width: 768px) {
            .documento { padding: 30px; }
        }
    </style>
</head>
<body>

<!-- MENÚ PRINCIPAL -->
<div class="menu-container" id="menuPrincipal">
    <h1><i class="fa-solid fa-book" style="color:#6366f1;"></i> Documentación de la Plataforma - Parte 4</h1>
    <p class="subtitle"><?php echo $platform_name; ?> | v<?php echo $platform_version; ?> | Generado: <?php echo $generation_date; ?></p>
    
    <div class="doc-buttons">
        <div class="doc-btn" onclick="mostrarDocumento(1)">
            <i class="fa-solid fa-file-pdf"></i>
            <h3>1. Modelador PDF</h3>
            <p>Formularios inteligentes y plantillas oficiales</p>
        </div>
        <div class="doc-btn" onclick="mostrarDocumento(2)">
            <i class="fa-solid fa-flask-vial"></i>
            <h3>2. LABS-MASQF</h3>
            <p>Centro de Tecnología y Diagnóstico</p>
        </div>
        <div class="doc-btn" onclick="mostrarDocumento(3)">
            <i class="fa-solid fa-screwdriver-wrench"></i>
            <h3>3. Centro Técnico de Seguros</h3>
            <p>Ajustes excepcionales, simulación contable y reglas NOFTRAB/VAF</p>
        </div>
        <div class="doc-btn" onclick="mostrarDocumento(4)">
            <i class="fa-solid fa-sliders"></i>
            <h3>4. Configuración del Sistema</h3>
            <p>Parámetros globales, validador de documentos y motor SMTP/Nube</p>
        </div>
    </div>
</div>

<!-- BARRA DE ACCIONES -->
<div class="action-bar" id="actionBar" style="display:none;">
    <h3 id="actionTitle">Documento</h3>
    <div class="action-buttons">
        <button class="btn btn-secondary" onclick="volverMenu()">
            <i class="fa-solid fa-arrow-left"></i> Volver
        </button>
        <button class="btn btn-primary" onclick="window.print()">
            <i class="fa-solid fa-print"></i> Imprimir
        </button>
        <button class="btn btn-success" onclick="descargarPDF()">
            <i class="fa-solid fa-file-pdf"></i> Descargar PDF
        </button>
    </div>
</div>

<!-- DOCUMENTO 1: MODELADOR PDF -->
<div class="documento" id="documento1">
    <div class="doc-header">
        <h1><i class="fa-solid fa-file-pdf"></i> Modelador PDF - Formularios Inteligentes</h1>
        <div class="meta">
            <span><i class="fa-solid fa-building"></i> <?php echo $platform_name; ?></span>
            <span><i class="fa-solid fa-wand-magic-sparkles"></i> PDF Template Engine</span>
            <span><i class="fa-solid fa-calendar"></i> <?php echo date('d/m/Y'); ?></span>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-circle-info"></i> Descripción General</h2>
        <p>El Modelador PDF es un sistema avanzado de automatización de formularios oficiales que permite mapear variables del sistema contable y de pólizas a plantillas PDF de aseguradoras aliadas (Multiseguros, Seguros Reservas, Midas, APS). Utiliza un mapeador split-view con drag-and-drop para vincular campos, simulación en tiempo real, y generación de PDFs reales con autocompletado inteligente.</p>
        
        <div class="status-grid">
            <div class="status-card cumple">
                <div class="label">Aseguradoras</div>
                <div class="value">4+</div>
                <div class="desc">Multiseguros, Reservas, Midas, APS</div>
            </div>
            <div class="status-card cumple">
                <div class="label">Mapeo</div>
                <div class="value">Drag & Drop</div>
                <div class="desc">Variables arrastrables al PDF</div>
            </div>
            <div class="status-card cumple">
                <div class="label">Simulación</div>
                <div class="value">Real-time</div>
                <div class="desc">Vista previa instantánea</div>
            </div>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-gears"></i> Funcionalidades Principales</h2>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-upload"></i> Carga de Plantillas Oficiales</h4>
            <ul>
                <li><strong>Nombre de la Plantilla:</strong> Identificador único de la plantilla</li>
                <li><strong>Aseguradora Asociada:</strong> Selección de aseguradora (Multiseguros, Seguros Reservas, Midas, APS)</li>
                <li><strong>Archivo PDF Oficial:</strong> Subida del formulario oficial en formato PDF</li>
                <li><strong>Análisis automático:</strong> El sistema detecta campos AcroForm del PDF</li>
                <li><strong>Validación:</strong> Verifica estructura y campos mapeables</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-columns"></i> Mapeador Split-View</h4>
            <ul>
                <li><strong>Vista dividida:</strong> Panel izquierdo con variables, panel derecho con PDF</li>
                <li><strong>Drag & Drop:</strong> Arrastrar variables del sistema al PDF</li>
                <li><strong>Posicionamiento preciso:</strong> Coordenadas X/Y en milímetros</li>
                <li><strong>Propiedades de campo:</strong>
                    <ul>
                        <li>Variable MQF vinculada</li>
                        <li>Nombre de campo en el PDF (AcroForm)</li>
                        <li>Fuente: Helvetica, Times, Courier</li>
                        <li>Tamaño: 8pt, 9pt, 10pt, 11pt, 12pt, 14pt, 16pt</li>
                        <li>Estilo: Normal, Negrita</li>
                        <li>Color de texto</li>
                        <li>Alineación: Izquierda, Centro, Derecha</li>
                        <li>Ancho en milímetros</li>
                        <li>Fondo opaco (para tapar texto detrás)</li>
                    </ul>
                </li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-database"></i> Variables del Sistema</h4>
            <ul>
                <li><strong>Datos Asegurado / Cliente:</strong>
                    <ul>
                        <li>Nombre Completo</li>
                        <li>Cédula / RNC</li>
                        <li>Teléfono</li>
                        <li>Correo Electrónico</li>
                    </ul>
                </li>
                <li><strong>Datos de la Póliza / Fianza:</strong>
                    <ul>
                        <li>Nro de Póliza</li>
                        <li>Valor Afianzado</li>
                        <li>Inicio Cobertura</li>
                        <li>Fin Cobertura</li>
                        <li>Beneficiario</li>
                        <li>Objeto de la Fianza</li>
                    </ul>
                </li>
                <li><strong>Datos Primas y Costos:</strong>
                    <ul>
                        <li>Prima Neta</li>
                        <li>ITBIS (18%)</li>
                        <li>Total a Pagar</li>
                    </ul>
                </li>
                <li><strong>Campos personalizados:</strong> Creación de entradas manuales con valor fijo</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-vial"></i> Simulación en Tiempo Real</h4>
            <ul>
                <li><strong>Selección de cotización de prueba:</strong> Carga datos reales del sistema</li>
                <li><strong>Visualización instantánea:</strong> Los valores aparecen en el lienzo del PDF</li>
                <li><strong>Ajuste de posición:</strong> Mover campos y ver resultado inmediato</li>
                <li><strong>Validación de formato:</strong> Verifica que los datos se ajusten al campo</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-file-circle-check"></i> Generación de PDF Real</h4>
            <ul>
                <li><strong>Motor de renderizado:</strong> Usa librerías PDF para autocompletar formularios</li>
                <li><strong>Mapeo de campos:</strong> Vincula variables MQF con campos AcroForm del PDF</li>
                <li><strong>Preservación de formato:</strong> Mantiene estructura original del formulario oficial</li>
                <li><strong>Descarga automática:</strong> PDF generado se descarga inmediatamente</li>
                <li><strong>Archivado:</strong> Copia del PDF generado se guarda en el sistema</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-list-check"></i> Gestión de Plantillas</h4>
            <ul>
                <li><strong>Tabla de plantillas cargadas:</strong>
                    <ul>
                        <li>Nombre de la plantilla</li>
                        <li>Aseguradora asociada</li>
                        <li>Campos mapeados (contador)</li>
                        <li>Tipo de documento</li>
                        <li>Fecha de creación</li>
                        <li>Acciones: Editar, Eliminar, Duplicar</li>
                    </ul>
                </li>
                <li><strong>Filtros:</strong> Por aseguradora, tipo de documento</li>
                <li><strong>Búsqueda:</strong> Por nombre de plantilla</li>
            </ul>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-database"></i> Estructura de Datos - Tablas Principales</h2>
        
        <h3>Tabla: plantillas_pdf</h3>
        <table>
            <thead>
                <tr>
                    <th>Campo</th>
                    <th>Tipo</th>
                    <th>Descripción</th>
                </tr>
            </thead>
            <tbody>
                <tr><td><code>id</code></td><td>INT AUTO_INCREMENT</td><td>ID único de plantilla</td></tr>
                <tr><td><code>nombre</code></td><td>VARCHAR(200)</td><td>Nombre de la plantilla</td></tr>
                <tr><td><code>aseguradora_id</code></td><td>INT</td><td>Referencia a aseguradora</td></tr>
                <tr><td><code>archivo_ruta</code></td><td>VARCHAR(255)</td><td>Ruta del archivo PDF oficial</td></tr>
                <tr><td><code>tipo_documento</code></td><td>VARCHAR(100)</td><td>Tipo (Solicitud, Póliza, Recibo, etc.)</td></tr>
                <tr><td><code>campos_mapeados</code></td><td>JSON</td><td>Configuración de campos mapeados</td></tr>
                <tr><td><code>estado</code></td><td>ENUM('ACTIVA','INACTIVA')</td><td>Estado de la plantilla</td></tr>
                <tr><td><code>usuario_id</code></td><td>INT</td><td>Usuario que creó la plantilla</td></tr>
                <tr><td><code>fecha_creacion</code></td><td>DATETIME</td><td>Fecha de creación</td></tr>
                <tr><td><code>fecha_actualizacion</code></td><td>DATETIME</td><td>Última actualización</td></tr>
            </tbody>
        </table>

        <h3>Tabla: mapeo_campos_pdf</h3>
        <table>
            <thead>
                <tr>
                    <th>Campo</th>
                    <th>Tipo</th>
                    <th>Descripción</th>
                </tr>
            </thead>
            <tbody>
                <tr><td><code>id</code></td><td>INT AUTO_INCREMENT</td><td>ID único de mapeo</td></tr>
                <tr><td><code>plantilla_id</code></td><td>INT</td><td>Referencia a plantilla</td></tr>
                <tr><td><code>variable_mqf</code></td><td>VARCHAR(100)</td><td>Variable del sistema (ej: cliente_nombre)</td></tr>
                <tr><td><code>campo_pdf</code></td><td>VARCHAR(100)</td><td>Nombre del campo AcroForm en el PDF</td></tr>
                <tr><td><code>posicion_x</code></td><td>DECIMAL(8,2)</td><td>Posición X en milímetros</td></tr>
                <tr><td><code>posicion_y</code></td><td>DECIMAL(8,2)</td><td>Posición Y en milímetros</td></tr>
                <tr><td><code>fuentes</code></td><td>VARCHAR(50)</td><td>Fuente (Helvetica, Times, Courier)</td></tr>
                <tr><td><code>tamano</code></td><td>INT</td><td>Tamaño de fuente (8-16pt)</td></tr>
                <tr><td><code>estilo</code></td><td>ENUM('NORMAL','NEGRITA')</td><td>Estilo de texto</td></tr>
                <tr><td><code>color_texto</code></td><td>VARCHAR(7)</td><td>Color en formato HEX</td></tr>
                <tr><td><code>alineacion</code></td><td>ENUM('IZQUIERDA','CENTRO','DERECHA')</td><td>Alineación del texto</td></tr>
                <tr><td><code>ancho</code></td><td>DECIMAL(8,2)</td><td>Ancho del campo en mm</td></tr>
                <tr><td><code>fondo_opaco</code></td><td>BOOLEAN</td><td>Si debe tapar texto detrás</td></tr>
            </tbody>
        </table>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-code"></i> Implementación Técnica</h2>
        
        <div class="code-block">
// Estructura de mapeo de campos
const mapeoCampo = {
    variable_mqf: 'cliente_nombre',
    campo_pdf: 'txtNombreCompleto',
    posicion: { x: 25.5, y: 45.2 },
    fuente: 'Helvetica',
    tamano: 10,
    estilo: 'NORMAL',
    color: '#000000',
    alineacion: 'IZQUIERDA',
    ancho: 80.0,
    fondo_opaco: false
};

// Variables disponibles del sistema
const variablesSistema = {
    // Datos del cliente
    'cliente_nombre': { tipo: 'string', origen: 'clientes' },
    'cliente_cedula': { tipo: 'string', origen: 'clientes' },
    'cliente_telefono': { tipo: 'string', origen: 'clientes' },
    'cliente_email': { tipo: 'string', origen: 'clientes' },
    
    // Datos de póliza
    'poliza_numero': { tipo: 'string', origen: 'polizas' },
    'poliza_valor_afianzado': { tipo: 'decimal', origen: 'polizas' },
    'poliza_inicio': { tipo: 'date', origen: 'polizas' },
    'poliza_fin': { tipo: 'date', origen: 'polizas' },
    'poliza_beneficiario': { tipo: 'string', origen: 'polizas' },
    
    // Datos financieros
    'prima_neta': { tipo: 'decimal', origen: 'calculos' },
    'itbis': { tipo: 'decimal', origen: 'calculos' },
    'total_pagar': { tipo: 'decimal', origen: 'calculos' }
};

// Generación de PDF con autocompletado
async function generarPDFReal(plantillaId, datosCotizacion) {
    const plantilla = await obtenerPlantilla(plantillaId);
    const mapeos = await obtenerMapeosCampos(plantillaId);
    
    const pdfDoc = await PDFDocument.load(plantilla.archivo_ruta);
    const form = pdfDoc.getForm();
    
    mapeos.forEach(mapeo => {
        const campo = form.getTextField(mapeo.campo_pdf);
        const valor = datosCotizacion[mapeo.variable_mqf];
        
        campo.setText(valor);
        campo.setFontSize(mapeo.tamano);
        campo.setAlignment(mapeo.alineacion);
    });
    
    const pdfBytes = await pdfDoc.save();
    return pdfBytes;
}
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-plug"></i> APIs y Endpoints</h2>
        
        <table>
            <thead>
                <tr>
                    <th>Endpoint</th>
                    <th>Método</th>
                    <th>Descripción</th>
                </tr>
            </thead>
            <tbody>
                <tr><td><code>/modelador_pdf.php?action=listar</code></td><td>GET</td><td>Lista plantillas cargadas</td></tr>
                <tr><td><code>/modelador_pdf.php?action=crear</code></td><td>POST</td><td>Crea nueva plantilla</td></tr>
                <tr><td><code>/modelador_pdf.php?action=actualizar</code></td><td>POST</td><td>Actualiza plantilla existente</td></tr>
                <tr><td><code>/modelador_pdf.php?action=eliminar</code></td><td>DELETE</td><td>Elimina plantilla</td></tr>
                <tr><td><code>/modelador_pdf.php?action=mapear_campos</code></td><td>POST</td><td>Guarda mapeo de campos</td></tr>
                <tr><td><code>/modelador_pdf.php?action=generar</code></td><td>POST</td><td>Genera PDF real con datos</td></tr>
                <tr><td><code>/modelador_pdf.php?action=simular</code></td><td>POST</td><td>Simula autocompletado</td></tr>
            </tbody>
        </table>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-shield-halved"></i> Cumplimiento NOFTRAB</h2>
        
        <table>
            <thead>
                <tr>
                    <th>Regla</th>
                    <th>Cumplimiento</th>
                    <th>Implementación</th>
                </tr>
            </thead>
            <tbody>
                <tr><td><strong>R8 - Exportación</strong></td><td><span class="badge badge-ok">100%</span></td><td>Generación de PDFs oficiales con autocompletado</td></tr>
                <tr><td><strong>R4 - Auditoría</strong></td><td><span class="badge badge-ok">100%</span></td><td>Registro de plantillas creadas/modificadas</td></tr>
                <tr><td><strong>R5 - Datos sensibles</strong></td><td><span class="badge badge-ok">100%</span></td><td>Mapeo preciso de campos sin exposición de datos</td></tr>
            </tbody>
        </table>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-circle-check"></i> Flujo de Trabajo</h2>
        
        <div class="code-block">
1. Administrador accede a Modelador PDF
   ↓
2. Carga nueva plantilla oficial:
   - Nombre: "Solicitud Multiseguros 2026"
   - Aseguradora: MULTISEGUROS
   - Archivo: solicitud_multiseguros.pdf
   ↓
3. Sistema analiza el PDF:
   - Detecta campos AcroForm
   - Lista campos disponibles para mapeo
   ↓
4. Mapeador Split-View:
   - Panel izquierdo: Variables del sistema
   - Panel derecho: PDF con campos visibles
   ↓
5. Arrastrar y soltar:
   - "cliente_nombre" → campo "txtNombre" en PDF
   - "cliente_cedula" → campo "txtCedula" en PDF
   - "poliza_numero" → campo "txtPoliza" en PDF
   ↓
6. Configurar propiedades:
   - Fuente: Helvetica, 10pt, Normal
   - Alineación: Izquierda
   - Ancho: 80mm
   ↓
7. Simulación en tiempo real:
   - Selecciona cotización de prueba
   - Ve los valores aparecer en el PDF
   - Ajusta posición si es necesario
   ↓
8. Guardar mapeo:
   - Sistema guarda configuración en BD
   - Plantilla lista para uso
   ↓
9. Generación de PDF real:
   - Usuario selecciona póliza/cotización
   - Sistema carga plantilla mapeada
   - Autocompleta campos con datos reales
   - Genera PDF final
   - Descarga automática
   ↓
10. Archivado:
    - Copia del PDF generado se guarda
    - Registro en auditoría_lineal
        </div>
    </div>

    <div class="footer-doc">
        <p><strong><?php echo $platform_name; ?></strong> - Modelador PDF | Documentación Técnica</p>
        <p>Clasificación: <strong>INTERNO</strong> | Versión: 1.0</p>
    </div>
</div>

<!-- DOCUMENTO 2: LABS-MASQF -->
<div class="documento" id="documento2">
    <div class="doc-header">
        <h1><i class="fa-solid fa-flask-vial"></i> LABS-MASQF - Centro de Tecnología</h1>
        <div class="meta">
            <span><i class="fa-solid fa-building"></i> <?php echo $platform_name; ?></span>
            <span><i class="fa-solid fa-microscope"></i> Technology Lab</span>
            <span><i class="fa-solid fa-calendar"></i> <?php echo date('d/m/Y'); ?></span>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-circle-info"></i> Descripción General</h2>
        <p>LABS-MASQF es el centro de tecnología, diagnóstico y soporte avanzado de la plataforma. Proporciona herramientas de monitoreo del sistema, pruebas de integración contable, gestión de skins en tiempo real, visualización de logs de errores, control de versiones Plating, y un bot autónomo de testing (BOT-TESTING-DEV) que realiza diagnósticos exhaustivos y auto-correcciones según la norma NOFTRAB.</p>
        
        <div class="status-grid">
            <div class="status-card cumple">
                <div class="label">Versión</div>
                <div class="value">v3.0.1</div>
                <div class="desc">Stable Release</div>
            </div>
            <div class="status-card cumple">
                <div class="label">Módulos</div>
                <div class="value">6</div>
                <div class="desc">Centro de Mando, Skins, Logs, etc.</div>
            </div>
            <div class="status-card cumple">
                <div class="label">Bot Testing</div>
                <div class="value">Autónomo</div>
                <div class="desc">Diagnóstico automático</div>
            </div>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-gears"></i> Funcionalidades Principales</h2>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-gauge-high"></i> Centro de Mando - Estado Local</h4>
            <ul>
                <li><strong>Salud del Entorno:</strong>
                    <ul>
                        <li><strong>Base de Datos (MariaDB):</strong> Estado de conexión y rendimiento</li>
                        <li><strong>Motor Contable (Core):</strong> Estado del sistema contable</li>
                        <li><strong>Secuenciador NCF:</strong> Estado de generación de comprobantes fiscales</li>
                        <li><strong>API Integración:</strong> Estado de APIs externas</li>
                        <li><strong>Espacio en Disco:</strong> Porcentaje de almacenamiento utilizado</li>
                    </ul>
                </li>
                <li><strong>Sesión Activa:</strong> Usuario conectado y nivel de acceso</li>
                <li><strong>Forzar Check de Salud:</strong> Botón para re-evaluar todos los componentes</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-bolt"></i> Acciones de Emergencia y Diagnóstico</h4>
            <ul>
                <li><strong>Test de NCF:</strong>
                    <ul>
                        <li>Verifica última secuencia de NCF</li>
                        <li>Valida integridad del secuenciador</li>
                        <li>Reporta errores de secuencia</li>
                    </ul>
                </li>
                <li><strong>Test Contable:</strong>
                    <ul>
                        <li>Simula evento de partida doble</li>
                        <li>Verifica que débito = crédito</li>
                        <li>Valida cuentas contables</li>
                    </ul>
                </li>
                <li><strong>Limpieza Lab:</strong>
                    <ul>
                        <li>Borra registros temporales de pruebas</li>
                        <li>Limpia datos de laboratorio</li>
                        <li>Mantiene datos de producción intactos</li>
                    </ul>
                </li>
                <li><strong>Push Update:</strong>
                    <ul>
                        <li>Sincroniza módulos SaaS</li>
                        <li>Actualiza componentes</li>
                        <li>Verifica versiones</li>
                    </ul>
                </li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-terminal"></i> Terminal de Eventos LABS-MASQF</h4>
            <ul>
                <li><strong>Consola en tiempo real:</strong> Muestra eventos del sistema</li>
                <li><strong>Logs del sistema:</strong> Registro de operaciones técnicas</li>
                <li><strong>Comandos disponibles:</strong>
                    <ul>
                        <li><code>clear</code> - Limpiar terminal</li>
                        <li><code>status</code> - Estado del sistema</li>
                        <li><code>test ncf</code> - Probar NCF</li>
                        <li><code>test contable</code> - Probar motor contable</li>
                        <li><code>logs</code> - Ver logs recientes</li>
                    </ul>
                </li>
                <li><strong>Formato:</strong> [TIMESTAMP] [NIVEL] Mensaje</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-palette"></i> Control de Skin en Tiempo Real</h4>
            <ul>
                <li><strong>Skins disponibles:</strong>
                    <ul>
                        <li><strong>Indigo Light (Default):</strong> Tema corporativo estándar</li>
                        <li><strong>Obsidian Dark:</strong> Tema oscuro premium</li>
                        <li><strong>Coral Finance:</strong> Tema energético para ventas</li>
                    </ul>
                </li>
                <li><strong>Gradiente configurable:</strong>
                    <ul>
                        <li>Primary (color primario)</li>
                        <li>Surface (fondo de superficies)</li>
                        <li>Background (fondo general)</li>
                        <li>Success (verde éxito)</li>
                        <li>Danger (rojo error)</li>
                    </ul>
                </li>
                <li><strong>Botones:</strong>
                    <ul>
                        <li>Btn Primary</li>
                        <li>Btn Secondary</li>
                        <li>DangerBtn</li>
                        <li>SuccessBtn</li>
                    </ul>
                </li>
                <li><strong>Aplicación inmediata:</strong> Cambios se aplican en tiempo real</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-window-maximize"></i> Laboratorio de Modales Premium</h4>
            <ul>
                <li><strong>Previsualización de modales:</strong>
                    <ul>
                        <li>Modal Nuevo Usuario</li>
                        <li>Modal Editar Usuario</li>
                    </ul>
                </li>
                <li><strong>Aplicación automática de skin:</strong> Los modales usan el skin activo</li>
                <li><strong>Testing de UI:</strong> Permite probar diseño antes de implementar</li>
                <li><strong>CSS Design Tokens:</strong> Visualización de variables CSS computadas</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-shield-halved"></i> Visualizador Seguro de Logs de Errores</h4>
            <ul>
                <li><strong>Acceso restringido:</strong> Solo administradores (NOFTRAB)</li>
                <li><strong>Últimas 50 líneas:</strong> Del archivo de logs de error de PHP/WAMP</li>
                <li><strong>Visualización inmutable:</strong> Solo lectura, no se pueden modificar logs</li>
                <li><strong>Actualización manual:</strong> Botón para refrescar logs</li>
                <li><strong>Formato:</strong> Timestamp, nivel de error, mensaje, archivo, línea</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-code-branch"></i> Versiones Plating & Instalador Profesional</h4>
            <ul>
                <li><strong>Diagnóstico integral:</strong> Salud de la infraestructura</li>
                <li><strong>Gestor de actualizaciones:</strong> Migraciones de base de datos</li>
                <li><strong>Estándar NOFTRAB:</strong> Cumplimiento normativo en migraciones</li>
                <li><strong>Requisitos del sistema:</strong> Verificación de dependencias</li>
                <li><strong>Script de migraciones detectados:</strong> Lista de migraciones pendientes</li>
                <li><strong>Ejecutar todas las migraciones:</strong> Proceso automatizado</li>
                <li><strong>Log de ejecución:</strong> Registro detallado de migraciones aplicadas</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-robot"></i> BOT-TESTING-DEV: Diagnóstico Autónomo</h4>
            <ul>
                <li><strong>Funcionalidad:</strong> Bot autónomo que realiza pruebas exhaustivas</li>
                <li><strong>Alcance:</strong> Cada módulo de la plataforma</li>
                <li><strong>Detección de fallos:</strong> Identifica problemas operativos</li>
                <li><strong>Documentación:</strong> Registra fallos en mesa de ayuda (Helpdesk)</li>
                <li><strong>Auto-correcciones:</strong> Aplica correcciones inmutables según NOFTRAB</li>
                <li><strong>Modo de operación:</strong> Automática / Manual</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-list-check"></i> Diagnóstico de Módulos</h4>
            <ul>
                <li><strong>1. Integridad de Base de Datos:</strong>
                    <ul>
                        <li>Verifica tablas y relaciones</li>
                        <li>Valida integridad referencial</li>
                        <li>Detecta corrupción de datos</li>
                    </ul>
                </li>
                <li><strong>2. Permisos y Semillas:</strong>
                    <ul>
                        <li>Verifica tabla de permisos</li>
                        <li>Valida datos semilla</li>
                        <li>Detecta permisos faltantes</li>
                    </ul>
                </li>
                <li><strong>3. Chat CSR y Bot BHN:</strong>
                    <ul>
                        <li>Prueba integración de chat</li>
                        <li>Valida bot de atención</li>
                        <li>Verifica respuestas automáticas</li>
                    </ul>
                </li>
                <li><strong>4. Helpdesk e Incidencias:</strong>
                    <ul>
                        <li>Prueba creación de tickets</li>
                        <li>Valida asignación automática</li>
                        <li>Verifica notificaciones</li>
                    </ul>
                </li>
                <li><strong>5. Secuenciador de NCF:</strong>
                    <ul>
                        <li>Prueba generación de NCF</li>
                        <li>Valida secuencias correlativas</li>
                        <li>Detecta saltos en secuencia</li>
                    </ul>
                </li>
                <li><strong>6. Motor Contable Partida Doble:</strong>
                    <ul>
                        <li>Simula asiento contable</li>
                        <li>Valida que débito = crédito</li>
                        <li>Verifica cuentas contables</li>
                    </ul>
                </li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-terminal"></i> Consola del Bot (BOT-TESTING-DEV)</h4>
            <ul>
                <li><strong>Estado operativo:</strong> Inactivo/Activo/Evaluando</li>
                <li><strong>Última evaluación:</strong> Fecha y hora del último diagnóstico</li>
                <li><strong>Fallos detectados:</strong> Contador de problemas encontrados</li>
                <li><strong>Log en tiempo real:</strong> Muestra progreso del diagnóstico</li>
                <li><strong>Comandos:</strong>
                    <ul>
                        <li><code>iniciar</code> - Inicia diagnóstico completo</li>
                        <li><code>estado</code> - Muestra estado actual</li>
                        <li><code>detener</code> - Detiene el bot</li>
                    </ul>
                </li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-table-list"></i> Historial de Fallos y Acciones Correctivas</h4>
            <ul>
                <li><strong>Tabla de historial:</strong>
                    <ul>
                        <li>Fecha/Hora del fallo</li>
                        <li>Módulo afectado</li>
                        <li>Error detectado</li>
                        <li>Ticket Helpdesk creado</li>
                        <li>Estado de corrección (Pendiente/En Proceso/Corregido)</li>
                    </ul>
                </li>
                <li><strong>Filtros:</strong> Por módulo, estado de corrección</li>
                <li><strong>Exportación:</strong> CSV, PDF del historial</li>
            </ul>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-database"></i> Estructura de Datos - Tablas Principales</h2>
        
        <h3>Tabla: labs_diagnostico_log</h3>
        <table>
            <thead>
                <tr>
                    <th>Campo</th>
                    <th>Tipo</th>
                    <th>Descripción</th>
                </tr>
            </thead>
            <tbody>
                <tr><td><code>id</code></td><td>BIGINT AUTO_INCREMENT</td><td>ID único de log</td></tr>
                <tr><td><code>fecha_hora</code></td><td>DATETIME</td><td>Timestamp del evento</td></tr>
                <tr><td><code>nivel</code></td><td>ENUM('INFO','WARNING','ERROR','CRITICAL')</td><td>Nivel de severidad</td></tr>
                <tr><td><code>modulo</code></td><td>VARCHAR(100)</td><td>Módulo origen</td></tr>
                <tr><td><code>mensaje</code></td><td>TEXT</td><td>Mensaje del evento</td></tr>
                <tr><td><code>usuario_id</code></td><td>INT</td><td>Usuario que ejecutó la acción</td></tr>
                <tr><td><code>es_prueba</code></td><td>BOOLEAN</td><td>Si es registro de laboratorio</td></tr>
            </tbody>
        </table>

        <h3>Tabla: bot_testing_fallos</h3>
        <table>
            <thead>
                <tr>
                    <th>Campo</th>
                    <th>Tipo</th>
                    <th>Descripción</th>
                </tr>
            </thead>
            <tbody>
                <tr><td><code>id</code></td><td>BIGINT AUTO_INCREMENT</td><td>ID único de fallo</td></tr>
                <tr><td><code>fecha_hora</code></td><td>DATETIME</td><td>Fecha/hora de detección</td></tr>
                <tr><td><code>modulo_afectado</code></td><td>VARCHAR(100)</td><td>Módulo con el problema</td></tr>
                <tr><td><code>error_detectado</code></td><td>TEXT</td><td>Descripción del error</td></tr>
                <tr><td><code>ticket_helpdesk_id</code></td><td>BIGINT</td><td>Referencia a ticket creado</td></tr>
                <tr><td><code>estado_correccion</code></td><td>ENUM('PENDIENTE','EN_PROCESO','CORREGIDO')</td><td>Estado de la corrección</td></tr>
                <tr><td><code>accion_correctiva</code></td><td>TEXT</td><td>Descripción de la acción aplicada</td></tr>
                <tr><td><code>fecha_correccion</code></td><td>DATETIME</td><td>Fecha de corrección</td></tr>
            </tbody>
        </table>

        <h3>Tabla: migraciones_pendientes</h3>
        <table>
            <thead>
                <tr>
                    <th>Campo</th>
                    <th>Tipo</th>
                    <th>Descripción</th>
                </tr>
            </thead>
            <tbody>
                <tr><td><code>id</code></td><td>INT AUTO_INCREMENT</td><td>ID único de migración</td></tr>
                <tr><td><code>nombre</code></td><td>VARCHAR(200)</td><td>Nombre de la migración</td></tr>
                <tr><td><code>descripcion</code></td><td>TEXT</td><td>Descripción de cambios</td></tr>
                <tr><td><code>script_ruta</code></td><td>VARCHAR(255)</td><td>Ruta del script SQL</td></tr>
                <tr><td><code>estado</code></td><td>ENUM('PENDIENTE','EJECUTADA','ERROR')</td><td>Estado de ejecución</td></tr>
                <tr><td><code>fecha_ejecucion</code></td><td>DATETIME</td><td>Fecha de ejecución</td></tr>
                <tr><td><code>usuario_id</code></td><td>INT</td><td>Usuario que ejecutó</td></tr>
            </tbody>
        </table>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-code"></i> Implementación Técnica</h2>
        
        <div class="code-block">
// Estructura de diagnóstico de módulos
const modulosDiagnostico = [
    {
        id: 'integridad_bd',
        nombre: 'Integridad de Base de Datos',
        pruebas: [
            'Verificar tablas existentes',
            'Validar integridad referencial',
            'Detectar corrupción de datos'
        ]
    },
    {
        id: 'permisos_semillas',
        nombre: 'Permisos y Semillas',
        pruebas: [
            'Verificar tabla permisos',
            'Validar datos semilla',
            'Detectar permisos faltantes'
        ]
    },
    {
        id: 'chat_csr',
        nombre: 'Chat CSR y Bot BHN',
        pruebas: [
            'Probar integración chat',
            'Validar bot atención',
            'Verificar respuestas automáticas'
        ]
    },
    {
        id: 'helpdesk',
        nombre: 'Helpdesk e Incidencias',
        pruebas: [
            'Probar creación tickets',
            'Validar asignación automática',
            'Verificar notificaciones'
        ]
    },
    {
        id: 'secuenciador_ncf',
        nombre: 'Secuenciador de NCF',
        pruebas: [
            'Probar generación NCF',
            'Validar secuencias correlativas',
            'Detectar saltos en secuencia'
        ]
    },
    {
        id: 'motor_contable',
        nombre: 'Motor Contable Partida Doble',
        pruebas: [
            'Simular asiento contable',
            'Validar débito = crédito',
            'Verificar cuentas contables'
        ]
    }
];

// Bot autónomo de testing
class BOTTestingDEV {
    constructor() {
        this.estado = 'INACTIVO';
        this.fallosDetectados = 0;
        this.ultimaEvaluacion = null;
    }
    
    async iniciarDiagnostico() {
        this.estado = 'EVALUANDO';
        this.fallosDetectados = 0;
        
        for (const modulo of modulosDiagnostico) {
            await this.ejecutarPruebasModulo(modulo);
        }
        
        this.estado = 'INACTIVO';
        this.ultimaEvaluacion = new Date();
        
        return {
            fallos: this.fallosDetectados,
            ultimaEvaluacion: this.ultimaEvaluacion
        };
    }
    
    async ejecutarPruebasModulo(modulo) {
        for (const prueba of modulo.pruebas) {
            const resultado = await this.ejecutarPrueba(prueba);
            
            if (!resultado.exito) {
                this.fallosDetectados++;
                await this.registrarFallo(modulo, prueba, resultado.error);
                await this.crearTicketHelpdesk(modulo, prueba, resultado.error);
                
                // Auto-corrección si está disponible
                if (resultado.autoCorreccionDisponible) {
                    await this.aplicarAutoCorreccion(modulo, prueba);
                }
            }
        }
    }
}
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-plug"></i> APIs y Endpoints</h2>
        
        <table>
            <thead>
                <tr>
                    <th>Endpoint</th>
                    <th>Método</th>
                    <th>Descripción</th>
                </tr>
            </thead>
            <tbody>
                <tr><td><code>/labs.php?action=estado_sistema</code></td><td>GET</td><td>Obtiene estado de todos los componentes</td></tr>
                <tr><td><code>/labs.php?action=test_ncf</code></td><td>POST</td><td>Ejecuta prueba de NCF</td></tr>
                <tr><td><code>/labs.php?action=test_contable</code></td><td>POST</td><td>Ejecuta prueba contable</td></tr>
                <tr><td><code>/labs.php?action=limpiar_lab</code></td><td>POST</td><td>Limpia registros de laboratorio</td></tr>
                <tr><td><code>/labs.php?action=logs</code></td><td>GET</td><td>Obtiene logs de errores</td></tr>
                <tr><td><code>/labs.php?action=migraciones</code></td><td>GET</td><td>Lista migraciones pendientes</td></tr>
                <tr><td><code>/labs.php?action=ejecutar_migraciones</code></td><td>POST</td><td>Ejecuta todas las migraciones</td></tr>
                <tr><td><code>/labs.php?action=bot_iniciar</code></td><td>POST</td><td>Inicia bot de testing</td></tr>
                <tr><td><code>/labs.php?action=bot_estado</code></td><td>GET</td><td>Obtiene estado del bot</td></tr>
                <tr><td><code>/labs.php?action=bot_fallos</code></td><td>GET</td><td>Lista fallos detectados</td></tr>
            </tbody>
        </table>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-shield-halved"></i> Cumplimiento NOFTRAB</h2>
        
        <table>
            <thead>
                <tr>
                    <th>Regla</th>
                    <th>Cumplimiento</th>
                    <th>Implementación</th>
                </tr>
            </thead>
            <tbody>
                <tr><td><strong>R4 - Auditoría</strong></td><td><span class="badge badge-ok">100%</span></td><td>Logs inmutables de diagnóstico y correcciones</td></tr>
                <tr><td><strong>R6 - NCF</strong></td><td><span class="badge badge-ok">100%</span></td><td>Pruebas automatizadas de secuenciador</td></tr>
                <tr><td><strong>R7 - Contable</strong></td><td><span class="badge badge-ok">100%</span></td><td>Validación de motor contable partida doble</td></tr>
                <tr><td><strong>R9 - Accesibilidad</strong></td><td><span class="badge badge-ok">100%</span></td><td>Visualizador de logs con acceso restringido</td></tr>
            </tbody>
        </table>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-circle-check"></i> Flujo de Diagnóstico Autónomo</h2>
        
        <div class="code-block">
1. Administrador accede a LABS-MASQF
   ↓
2. Sección BOT-TESTING-DEV
   ↓
3. Click en "Iniciar Diagnóstico Completo"
   ↓
4. Bot cambia estado a "EVALUANDO"
   ↓
5. Ejecuta pruebas en cada módulo:
   
   MÓDULO 1: Integridad de Base de Datos
   - Verifica tablas existentes ✓
   - Valida integridad referencial ✓
   - Detecta corrupción de datos ✓
   
   MÓDULO 2: Permisos y Semillas
   - Verifica tabla permisos ✓
   - Valida datos semilla ️ (1 fallo)
   - Detecta permisos faltantes ✓
   
   MÓDULO 3: Chat CSR y Bot BHN
   - Prueba integración chat ✓
   - Valida bot atención ✓
   - Verifica respuestas automáticas ✓
   
   MÓDULO 4: Helpdesk e Incidencias
   - Prueba creación tickets ✓
   - Valida asignación automática ✓
   - Verifica notificaciones ✓
   
   MÓDULO 5: Secuenciador de NCF
   - Prueba generación NCF ✓
   - Valida secuencias correlativas ✓
   - Detecta saltos en secuencia ✓
   
   MÓDULO 6: Motor Contable
   - Simula asiento contable ✓
   - Valida débito = crédito ✓
   - Verifica cuentas contables ✓
   ↓
6. Fallo detectado en Módulo 2:
   - Error: "Permiso TAB_COT_FIANZAS faltante para perfil PDV"
   - Bot crea ticket en Helpdesk automáticamente
   - Bot aplica auto-corrección (agrega permiso faltante)
   - Registra acción en historial
   ↓
7. Bot completa diagnóstico:
   - Estado: INACTIVO
   - Fallos detectados: 1
   - Última evaluación: 2026-06-18 10:30:00
   ↓
8. Administrador revisa:
   - Historial de fallos y acciones correctivas
   - Ticket Helpdesk creado automáticamente
   - Corrección aplicada exitosamente
   ↓
9. Sistema queda operativo y validado
        </div>
    </div>

    <div class="footer-doc">
        <p><strong><?php echo $platform_name; ?></strong> - LABS-MASQF | Documentación Técnica</p>
        <p>Clasificación: <strong>INTERNO</strong> | Versión: 1.0</p>
    </div>
</div>

<!-- ========================================== -->
<!-- DOCUMENTO 3: CENTRO TÉCNICO DE SEGUROS -->
<!-- ========================================== -->
<div class="documento" id="documento3">
    <div class="doc-header">
        <h1><i class="fa-solid fa-screwdriver-wrench" style="color:#6366f1;"></i> Centro Técnico de Seguros</h1>
        <div class="meta">
            <span><i class="fa-solid fa-code-branch"></i> Versión: 4.0 NOFTRAB</span>
            <span><i class="fa-solid fa-calendar"></i> Actualizado: <?php echo $generation_date; ?></span>
            <span><i class="fa-solid fa-shield-halved"></i> Norma: Regla 4-VAF / NOFTRAB</span>
        </div>
    </div>

    <div class="status-grid">
        <div class="status-card cumple">
            <div class="label">Ajustes Financieros</div>
            <div class="value">Aprobación VAF</div>
            <div class="desc">Ajustes a primas, vigencias, vehículos y datos de clientes</div>
        </div>
        <div class="status-card cumple">
            <div class="label">Simulación Contable</div>
            <div class="value">Tiempo Real</div>
            <div class="desc">Asientos automáticos de débito/crédito pre-ejecución</div>
        </div>
        <div class="status-card cumple">
            <div class="label">Reglas Procesadas</div>
            <div class="value">Unicidad Strict</div>
            <div class="desc">Control global de Cédula, RNC, VIN, Placa y Fianzas</div>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-circle-info"></i> 1. Resumen Ejecutivo y Alcance</h2>
        <p>El <strong>Centro Técnico de Seguros</strong> es el módulo de control de alta jerarquía del sistema <strong>MÁS QUE FIANZAS</strong>. Ha sido diseñado e implementado rigurosamente bajo las especificaciones de las <strong>Normas NOFTRAB</strong> y la <strong>Regla 4-VAF</strong>. Permite autorizar ajustes excepcionales sobre documentos emitidos, simular el impacto contable en tiempo real, catalogar reglas de negocio operativas y controlar la unicidad de identificadores y documentos procesados en toda la plataforma.</p>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-list-check"></i> 2. Funcionalidades Principales</h2>
        
        <h3>2.1 Solicitud de Ajustes Excepcionales</h3>
        <p>Permite solicitar modificaciones sobre pólizas o cotizaciones con auditoría estricta:</p>
        <ul>
            <li><strong>Ajustes Financieros</strong>: Modificación justificada de prima neta, impuestos y aseguradora.</li>
            <li><strong>Ajustes de Vehículo</strong>: Corrección de Placa, Marca, Modelo, Año y Chasis (VIN).</li>
            <li><strong>Ajustes de Cliente</strong>: Actualización de Razón Social/Nombre, Cédula, RNC y Teléfono.</li>
            <li><strong>Soporte Documental Obligatorio</strong>: Carga requerida de archivos PDF/Imagen de soporte y justificación de al menos 15 caracteres.</li>
        </ul>

        <h3>2.2 Bandeja de Aprobaciones y Resoluciones</h3>
        <p>Los usuarios con perfil <strong>Administrador (ID 1)</strong> cuentan con la bandeja unificada de aprobación/rechazo de solicitudes:</p>
        <ul>
            <li>Verificación dual de solicitudes pendientes.</li>
            <li>Aprobación o rechazo con comentarios justificados e impacto inmediato en BD.</li>
            <li>Generación automática de asientos contables al aprobar ajustes financieros.</li>
        </ul>

        <h3>2.3 Catálogo de Reglas de Negocio Configurable</h3>
        <p>Gestor dinámico para activar/desactivar y ajustar valores de reglas contables y operativas (límites ACH, recargos de mora, comisiones máximas por producto y límites de antigüedad).</p>

        <h3>2.4 Reglas de Documentos Procesados e Identificadores</h3>
        <p>Pestaña especializada para el control de unicidad global:</p>
        <ul>
            <li><strong>Cédula Dominicana (Luhn Mod 10)</strong>: Verificación de algoritmo y prohibición de duplicados de clientes.</li>
            <li><strong>RNC Dominicana (Mod 11 DGII)</strong>: Validación formal de sociedades comerciales.</li>
            <li><strong>Vehículos (Chasis/VIN y Placa)</strong>: Impedimento estricto de registrar el mismo vehículo en múltiples cotizaciones/pólizas activas.</li>
            <li><strong>Fianzas</strong>: Control de unicidad estricta para números de fianza y certificados.</li>
        </ul>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-database"></i> 3. Arquitectura Backend y Endpoints</h2>
        <table>
            <thead>
                <tr>
                    <th>Acción API (`backend/api/centro_tecnico.php`)</th>
                    <th>Método</th>
                    <th>Descripción y Resultado</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>`action=buscar_poliza`</td>
                    <td>GET</td>
                    <td>Busca pólizas activas por número, cliente o placa.</td>
                </tr>
                <tr>
                    <td>`action=crear_solicitud`</td>
                    <td>POST</td>
                    <td>Registra una solicitud de ajuste con soporte adjunto.</td>
                </tr>
                <tr>
                    <td>`action=listar_solicitudes`</td>
                    <td>GET</td>
                    <td>Devuelve la lista de solicitudes pendientes/procesadas.</td>
                </tr>
                <tr>
                    <td>`action=procesar_solicitud`</td>
                    <td>POST</td>
                    <td>Aprueba o rechaza una solicitud ejecutando cambios en BD.</td>
                </tr>
                <tr>
                    <td>`action=listar_reglas`</td>
                    <td>GET</td>
                    <td>Retorna el catálogo completo de reglas de negocio y documentos.</td>
                </tr>
                <tr>
                    <td>`action=guardar_regla`</td>
                    <td>POST</td>
                    <td>Crea o actualiza una regla de negocio/documento con justificación VAF.</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="footer-doc">
        <p><strong><?php echo $platform_name; ?></strong> - Centro Técnico de Seguros | Documentación Técnica</p>
        <p>Clasificación: <strong>CONFIDENCIAL / NOFTRAB</strong> | Versión: 4.0</p>
    </div>
</div>

<!-- ========================================== -->
<!-- DOCUMENTO 4: CONFIGURACIÓN DEL SISTEMA -->
<!-- ========================================== -->
<div class="documento" id="documento4">
    <div class="doc-header">
        <h1><i class="fa-solid fa-sliders" style="color:#6366f1;"></i> Configuración del Sistema y Parámetros</h1>
        <div class="meta">
            <span><i class="fa-solid fa-code-branch"></i> Versión: 9.0</span>
            <span><i class="fa-solid fa-calendar"></i> Actualizado: <?php echo $generation_date; ?></span>
            <span><i class="fa-solid fa-lock"></i> ISO 27001 / NOFTRAB</span>
        </div>
    </div>

    <div class="status-grid">
        <div class="status-card cumple">
            <div class="label">Empresa y Logo</div>
            <div class="value">Parametrizado</div>
            <div class="desc">Nombre comercial, RNC, dirección y logo MQF Base64</div>
        </div>
        <div class="status-card cumple">
            <div class="label">Validador Documentos</div>
            <div class="value">NOFTRAB 4-VAF</div>
            <div class="desc">Validación modular de Cédula, RNC, Pasaporte y Licencia</div>
        </div>
        <div class="status-card cumple">
            <div class="label">Motor SMTP</div>
            <div class="value">Plantillas HTML</div>
            <div class="desc">Notificaciones parametrizables con variables dinámicas</div>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-circle-info"></i> 1. Resumen Ejecutivo</h2>
        <p>El módulo de <strong>Configuración del Sistema</strong> centraliza todos los parámetros globales de la plataforma <strong>MÁS QUE FIANZAS</strong>. Permite a los administradores del sistema gestionar la información corporativa, los interruptores master de validación de documentos (Norma 4-VAF), las políticas de seguridad y expiración de tokens JWT/Bearer, las plantillas dinámicas de notificaciones por e-mail SMTP y las credenciales de integraciones externas (WhatsApp, Pasarela de Pagos, Google Drive y OneDrive).</p>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-list-check"></i> 2. Módulos y Pestañas de Configuración</h2>
        
        <h3>2.1 Datos Institucionales de la Empresa</h3>
        <ul>
            <li>Razón social oficial: <strong>MÁS QUE FIANZAS, S.R.L.</strong></li>
            <li>RNC de la empresa, dirección fiscal, teléfono de contacto y correo oficial de soporte.</li>
            <li>Carga e inserción del logotipo oficial MQF en formato Base64 para reportes e impresiones PDF.</li>
        </ul>

        <h3>2.2 Validador de Documentos Dominicanos (4-VAF)</h3>
        <p>Gestión de interruptores de validación documental estricta:</p>
        <ul>
            <li>`VALIDADOR_DOCS_ACTIVO`: Interruptor general del motor de validación.</li>
            <li>`VALIDADOR_DOCS_COTIZACIONES`: Validación en emisión y edición de cotizaciones.</li>
            <li>`VALIDADOR_DOCS_CLIENTES`: Validación al registrar nuevos clientes o editar sus expedientes.</li>
            <li>`VALIDADOR_DOCS_USUARIOS`: Validación de cédulas de usuarios y colaboradores.</li>
            <li>`VALIDADOR_DOCS_POLIZAS` y `VALIDADOR_DOCS_FIANZAS`: Validación documental en suscripción de pólizas/fianzas.</li>
        </ul>

        <h3>2.3 Parámetros de Seguridad y Sesión</h3>
        <ul>
            <li>Política de contraseñas complejas (Mayúscula, minúscula, número, carácter especial).</li>
            <li>Tiempo de expiración de sesión (Tokens JWT/Bearer) y tiempo de inactividad de pantalla.</li>
            <li>Intentos máximos de inicio de sesión antes del bloqueo preventivo de cuenta.</li>
        </ul>

        <h3>2.4 Notificaciones Automáticas y Servidor SMTP</h3>
        <ul>
            <li>Configuración del servidor SMTP (`Host`, `Puerto 587/465`, `Usuario`, `Password`, `Seguridad SSL/TLS`).</li>
            <li>Plantillas HTML parametrizables con variables `{{NUMERO}}`, `{{CLIENTE}}`, `{{TOTAL_FMT}}`, `{{FECHA_LOCAL}}`.</li>
            <li>Envío automático de comprobantes de pago, cotizaciones en PDF y avisos de vencimiento.</li>
        </ul>

        <h3>2.5 Tasas Financieras e Impuestos</h3>
        <ul>
            <li>Tasa oficial de ITBIS (18.00%).</li>
            <li>Comisiones base y techos por ramos de seguro y fianzas de fiel cumplimiento/anticipo.</li>
            <li>Configuración de recargos por mora y días de gracia institucionales.</li>
        </ul>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-code"></i> 3. Gestión Backend de Parámetros</h2>
        <p>Las configuraciones son almacenadas en la tabla MySQL `configuracion` y consumidas dinámicamente mediante la API `backend/api/configuracion.php` con caché de sesión para optimizar el tiempo de respuesta en milisegundos.</p>
    </div>

    <div class="footer-doc">
        <p><strong><?php echo $platform_name; ?></strong> - Configuración del Sistema | Documentación Técnica</p>
        <p>Clasificación: <strong>CONFIDENCIAL</strong> | Versión: 9.0</p>
    </div>
</div>

<script>
let documentoActivo = null;

function mostrarDocumento(num) {
    document.getElementById('menuPrincipal').style.display = 'none';
    document.getElementById('actionBar').style.display = 'flex';
    
    document.querySelectorAll('.documento').forEach(d => d.classList.remove('activo'));
    document.getElementById('documento' + num).classList.add('activo');
    
    documentoActivo = num;
    const titulos = [
        '📄 Modelador PDF',
        '📄 LABS-MASQF',
        '🛠️ Centro Técnico de Seguros',
        '🎛️ Configuración del Sistema'
    ];
    document.getElementById('actionTitle').textContent = titulos[num - 1];
    
    window.scrollTo(0, 0);
}

function volverMenu() {
    document.getElementById('menuPrincipal').style.display = 'block';
    document.getElementById('actionBar').style.display = 'none';
    document.querySelectorAll('.documento').forEach(d => d.classList.remove('activo'));
    documentoActivo = null;
    window.scrollTo(0, 0);
}

async function descargarPDF() {
    if (!documentoActivo) return;
    
    const btn = event.target.closest('button');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Generando...';
    btn.disabled = true;
    
    try {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('p', 'mm', 'a4');
        const elemento = document.getElementById('documento' + documentoActivo);
        
        const canvas = await html2canvas(elemento, {
            scale: 2,
            useCORS: true,
            logging: false,
            backgroundColor: '#ffffff'
        });
        
        const imgData = canvas.toDataURL('image/png');
        const imgWidth = 210;
        const pageHeight = 297;
        const imgHeight = canvas.height * imgWidth / canvas.width;
        let heightLeft = imgHeight;
        let position = 0;
        
        doc.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
        heightLeft -= pageHeight;
        
        while (heightLeft > 0) {
            position = heightLeft - imgHeight;
            doc.addPage();
            doc.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
            heightLeft -= pageHeight;
        }
        
        const nombres = [
            'Modelador_PDF',
            'LABS_MASQF',
            'Centro_Tecnico',
            'Configuracion_Sistema'
        ];
        
        doc.save('Documentacion_Plataforma_Parte4_' + nombres[documentoActivo - 1] + '.pdf');
        
        btn.innerHTML = '<i class="fa-solid fa-check"></i> Descargado';
        setTimeout(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }, 2000);
        
    } catch (error) {
        console.error('Error al generar PDF:', error);
        alert('Error al generar PDF. Use Ctrl+P y seleccione "Guardar como PDF".');
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const docParam = urlParams.get('doc');
    if (docParam) {
        mostrarDocumento(parseInt(docParam));
        const btnVolver = document.querySelector('button[onclick="volverMenu()"]');
        if (btnVolver) btnVolver.style.display = 'none';
        document.body.style.background = '#ffffff';
        document.body.style.padding = '0';
        const docDiv = document.getElementById('documento' + docParam);
        if (docDiv) {
            docDiv.style.boxShadow = 'none';
            docDiv.style.margin = '0 auto';
            docDiv.style.padding = '20px';
        }
    }
});
</script>

</body>
</html>