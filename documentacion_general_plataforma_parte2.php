<?php
/**
 * DOCUMENTACIÓN GENERAL DE LA PLATAFORMA INTEGRADA - PARTE 2
 * MAS QUE FIANZAS - Sistema Integrado de Gestión
 * 
 * Genera documentación completa de los módulos:
 * 1. Pólizas
 * 2. Siniestros
 * 3. Productos
 * 4. Reportes y Modelador
 * 5. Perfil Data
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
    <title>Documentación General - <?php echo $platform_name; ?> - Parte 2</title>
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
    <h1><i class="fa-solid fa-book" style="color:#6366f1;"></i> Documentación de la Plataforma - Parte 2</h1>
    <p class="subtitle"><?php echo $platform_name; ?> | v<?php echo $platform_version; ?> | Generado: <?php echo $generation_date; ?></p>
    
    <div class="doc-buttons">
        <div class="doc-btn" onclick="mostrarDocumento(1)">
            <i class="fa-solid fa-file-shield"></i>
            <h3>1. Módulo de Pólizas</h3>
            <p>Emisión, gestión y distribución de comisiones</p>
        </div>
        <div class="doc-btn" onclick="mostrarDocumento(2)">
            <i class="fa-solid fa-car-burst"></i>
            <h3>2. Módulo de Siniestros</h3>
            <p>Declaración, liquidación y automatización contable</p>
        </div>
        <div class="doc-btn" onclick="mostrarDocumento(3)">
            <i class="fa-solid fa-box-open"></i>
            <h3>3. Catálogo de Productos</h3>
            <p>Tarifas, deducibles y carga masiva</p>
        </div>
        <div class="doc-btn" onclick="mostrarDocumento(4)">
            <i class="fa-solid fa-chart-pie"></i>
            <h3>4. Reportes y Modelador</h3>
            <p>Generación de informes y plantillas PDF</p>
        </div>
        <div class="doc-btn" onclick="mostrarDocumento(5)">
            <i class="fa-solid fa-user-shield"></i>
            <h3>5. Perfil Data</h3>
            <p>Accesos, permisos y políticas de seguridad</p>
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

<!-- DOCUMENTO 1: PÓLIZAS -->
<div class="documento" id="documento1">
    <div class="doc-header">
        <h1><i class="fa-solid fa-file-shield"></i> Módulo de Pólizas - Emisión y Gestión</h1>
        <div class="meta">
            <span><i class="fa-solid fa-building"></i> <?php echo $platform_name; ?></span>
            <span><i class="fa-solid fa-file-contract"></i> Policy Management</span>
            <span><i class="fa-solid fa-calendar"></i> <?php echo date('d/m/Y'); ?></span>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-circle-info"></i> Descripción General</h2>
        <p>El Módulo de Pólizas es el sistema central de emisión, consulta y gestión de pólizas de seguros de ley. Permite convertir cotizaciones en pólizas emitidas, gestionar condiciones financieras, distribuir comisiones automáticamente, generar documentos oficiales (marbetes, solicitudes, recibos), registrar pagos con NCF fiscal, y auditar técnicamente cada póliza conforme a la norma NOFTRAB.</p>
        
        <div class="status-grid">
            <div class="status-card cumple">
                <div class="label">Wizard</div>
                <div class="value">4 pasos</div>
                <div class="desc">Cotización, Vehículo, Condiciones, Resumen</div>
            </div>
            <div class="status-card cumple">
                <div class="label">Documentos</div>
                <div class="value">4 tipos</div>
                <div class="desc">Marbete, Solicitud, Recibo, Factura</div>
            </div>
            <div class="status-card cumple">
                <div class="label">Estados</div>
                <div class="value">4 estados</div>
                <div class="desc">Activa, Suspendida, Vencida, Cancelada</div>
            </div>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-gears"></i> Funcionalidades Principales</h2>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-wand-magic-sparkles"></i> Wizard de Emisión (4 Pasos)</h4>
            <ul>
                <li><strong>Paso 1 - Cotización:</strong>
                    <ul>
                        <li>Carga automática desde cotización de origen (número, tipo, cobertura, prima)</li>
                        <li>Selección de cliente (autocompletado si existe)</li>
                        <li>Tipo de seguro: Seguro de Ley (Motocicleta, Vehículo Liviano, Vehículo Pesado), Seguro Casco Todo Riesgo, Fianza de Fidelidad, Fianza Judicial, Otro Ramo, Vehículos de Motor, Fianzas, Incendio y Líneas Aliadas, Salud</li>
                        <li>Aseguradora: MULTISEGUROS, SEGUROS RESERVAS, MAPFRE, BANRESERVAS, SEGUROS, Otra</li>
                        <li>Perfil de Cobertura: Seguro de Ley Básico, Moto Básico, Liviano Básico, Pesado Plus, Todo Riesgo</li>
                    </ul>
                </li>
                <li><strong>Paso 2 - Vehículo:</strong>
                    <ul>
                        <li>Búsqueda por placa (si el vehículo ya existe)</li>
                        <li>Campos: Placa*, Marca, Modelo, Año, Color</li>
                        <li>Tipo de Vehículo: Automóvil, Jeepeta/SUV, Motocicleta, Camión, Bus/Autobús, Pick-Up</li>
                        <li>Uso: Privado, Público/Concho, Rent-a-Car</li>
                        <li>N° Chasis, N° Motor, Valor Comercial (RD$)</li>
                        <li>Capacidad del Motor: Hasta 4 Cilindros, Hasta 6 Cilindros, 8 o Más Cilindros, Moto Hasta 200cc, Moto Hasta 750cc</li>
                    </ul>
                </li>
                <li><strong>Paso 3 - Condiciones:</strong>
                    <ul>
                        <li>Prima Total (RD$)*</li>
                        <li>Periodicidad de Pago: Mensual (12 cuotas), Trimestral (4 cuotas), Semestral (2 cuotas), Anual (1 pago)</li>
                        <li>Fecha de Inicio de Vigencia</li>
                        <li>Fecha de Vencimiento (calculada automáticamente)</li>
                        <li>N° Póliza Aseguradora</li>
                        <li>Notas Internas</li>
                        <li>Desglose Calculado (solo lectura): Prima Neta, ITBIS (18%), Total, Cuota según período</li>
                    </ul>
                </li>
                <li><strong>Paso 4 - Resumen:</strong>
                    <ul>
                        <li>Resumen completo de emisión</li>
                        <li>Distribución de Comisiones (calculadas sobre Prima Neta)</li>
                        <li>Acción: Emitir Póliza (registra, calcula comisiones, genera asiento contable, descarga documentos)</li>
                    </ul>
                </li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-file-pdf"></i> Generación de Documentos</h4>
            <ul>
                <li><strong>Marbete Provisional:</strong> Documento oficial con código QR de verificación en línea</li>
                <li><strong>Solicitud de Seguro:</strong> Formulario legal de solicitud</li>
                <li><strong>Recibo de Pago:</strong> Comprobante de pago con NCF</li>
                <li><strong>Factura Interna:</strong> Factura corporativa interna</li>
                <li><strong>Formato:</strong> PDF profesional con branding corporativo</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-money-bill-wave"></i> Registro de Pagos</h4>
            <ul>
                <li><strong>Monto del Pago (RD$)*</strong></li>
                <li><strong>Fecha de Pago*</strong></li>
                <li><strong>Forma de Pago*:</strong> Efectivo, Transferencia Bancaria, Cheque, Tarjeta de Crédito, Tarjeta de Débito</li>
                <li><strong>Banco</strong> (opcional)</li>
                <li><strong>N° Comprobante / Referencia</strong></li>
                <li><strong>NCF:</strong> Dejar vacío para auto-generar (B02)</li>
                <li><strong>Cuota N°:</strong> De un total de cuotas</li>
                <li><strong>Descripción</strong></li>
                <li><strong>Comprobante de Pago:</strong> Adjuntar PDF, PNG, JPG, JPEG (Máx. 5MB) con drag & drop</li>
                <li><strong>Acción:</strong> Registrar y Emitir Recibo</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-chart-line"></i> Proyección y Metas Mensuales de Ventas</h4>
            <ul>
                <li><strong>Configuración de objetivos:</strong> Meta de venta física (cantidad de pólizas) por tipo de producto</li>
                <li><strong>Períodos:</strong> Enero a Diciembre</li>
                <li><strong>Tabla de metas:</strong> Producto | Meta (Cantidad de Pólizas)</li>
                <li><strong>Seguimiento:</strong> Comparación Meta vs Real en reportes</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-magnifying-glass"></i> Consulta y Filtros</h4>
            <ul>
                <li><strong>Tabla principal:</strong> N° Póliza, Tipo, Asegurado, Vehículo/Placa, Vigencia, Prima Total, Balance, Estado, Validada, Acciones</li>
                <li><strong>Filtros:</strong>
                    <ul>
                        <li>Estado: Todos, Activa, Suspendida, Vencida, Cancelada</li>
                        <li>Desde/Hasta (rango de fechas)</li>
                        <li>Búsqueda por texto</li>
                    </ul>
                </li>
                <li><strong>Paginación:</strong> Mostrar 10, 20, 50 por página</li>
                <li><strong>Exportar:</strong> PDF, Excel, CSV</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-clipboard-check"></i> Ficha de Auditoría Técnica (NOFTRAB)</h4>
            <ul>
                <li><strong>Estado General:</strong> Vigencia y estado actual</li>
                <li><strong>Validación Técnica:</strong> Integridad de datos</li>
                <li><strong>Vigencia Temporal:</strong> Fechas de validez</li>
                <li><strong>Compañía Aseguradora:</strong> Datos de la aseguradora</li>
                <li><strong>Vehículo Registrado:</strong> Datos del vehículo</li>
                <li><strong>Matrícula / Placa:</strong> Identificación vehicular</li>
                <li><strong>Prima Total Anual:</strong> Monto total</li>
                <li><strong>Fecha de Auditoría:</strong> Timestamp de consulta</li>
                <li><strong>Imprimir Ficha:</strong> Generación de informe técnico</li>
                <li><strong>Auditoría Técnica (QR):</strong> Código QR de verificación</li>
            </ul>
        </div>

        <div class="feature-card">
            <h4><i class="fa-solid fa-triangle-exclamation"></i> Motor de Cancelación a Prorrata y Control de Accesos Granulares</h4>
            <ul>
                <li><strong>Cálculo de Prorrata Proporcional (Flat Rate):</strong>
                    <ul>
                        <li>Fórmula matemática de prorrata: Días Transcurridos / Días de Vigencia Total * Prima Total.</li>
                        <li>Diferenciación de saldo: Reembolso a favor del cliente (si el pago supera la prima devengada) o saldo deudor pendiente (si los días consumidos no se han cubierto).</li>
                    </ul>
                </li>
                <li><strong>Acción e Integración de Cobros:</strong>
                    <ul>
                        <li>Paso inmediato a estado <strong>CANCELADA</strong> y desactivación de cobertura.</li>
                        <li>Configuración automática de exclusión del bot de cobranza (<code>bot_excluir = 1</code>) para impedir alertas de mora.</li>
                        <li>Generación de registro de auditoría en la tabla <code>cancelaciones_polizas</code> y en la bitácora del sistema (<code>logAudit</code>).</li>
                    </ul>
                </li>
                <li><strong>Control de Accesos Granulares (Seguridad de Perfiles):</strong>
                    <ul>
                        <li><code>POLIZAS_CANCELAR_INDIVIDUAL</code>: Permite acceder al panel de cancelación de la ficha técnica y cancelar una póliza unitaria.</li>
                        <li><code>POLIZAS_CANCELAR_GRUPO</code>: Permite seleccionar casillas de verificación y realizar cancelaciones en lote.</li>
                        <li><code>POLIZAS_CANCELAR_MASIVO</code>: Habilita el botón superior para anular masivamente por corredor, aseguradora o ramo de seguros.</li>
                    </ul>
                </li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-user-plus"></i> Nuevo Cliente (Modal Rápido)</h4>
            <ul>
                <li>Nombre Completo / Razón Social*</li>
                <li>Cédula / RNC*</li>
                <li>Teléfono</li>
                <li>Correo Electrónico</li>
                <li>Tipo de Persona: Física (Persona Natural), Jurídica (Empresa)</li>
                <li>Dirección</li>
                <li><strong>Acción:</strong> Guardar y Seleccionar (crea cliente y lo asigna a la póliza)</li>
            </ul>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-database"></i> Estructura de Datos - Tablas Principales</h2>
        
        <h3>Tabla: polizas</h3>
        <table>
            <thead>
                <tr>
                    <th>Campo</th>
                    <th>Tipo</th>
                    <th>Descripción</th>
                </tr>
            </thead>
            <tbody>
                <tr><td><code>id</code></td><td>BIGINT AUTO_INCREMENT</td><td>ID único de póliza</td></tr>
                <tr><td><code>numero_poliza</code></td><td>VARCHAR(40)</td><td>Número de póliza (único)</td></tr>
                <tr><td><code>numero_cotizacion_origen</code></td><td>VARCHAR(40)</td><td>Referencia a cotización origen</td></tr>
                <tr><td><code>cliente_id</code></td><td>INT</td><td>Referencia a cliente</td></tr>
                <tr><td><code>tipo_seguro</code></td><td>VARCHAR(100)</td><td>Tipo de seguro (Ley, Casco, Fianza, etc.)</td></tr>
                <tr><td><code>aseguradora</code></td><td>VARCHAR(100)</td><td>Compañía aseguradora</td></tr>
                <tr><td><code>perfil_cobertura</code></td><td>VARCHAR(100)</td><td>Perfil de cobertura aplicado</td></tr>
                <tr><td><code>placa</code></td><td>VARCHAR(20)</td><td>Placa del vehículo</td></tr>
                <tr><td><code>marca</code></td><td>VARCHAR(100)</td><td>Marca del vehículo</td></tr>
                <tr><td><code>modelo</code></td><td>VARCHAR(100)</td><td>Modelo del vehículo</td></tr>
                <tr><td><code>anio</code></td><td>INT</td><td>Año del vehículo</td></tr>
                <tr><td><code>color</code></td><td>VARCHAR(50)</td><td>Color del vehículo</td></tr>
                <tr><td><code>tipo_vehiculo</code></td><td>VARCHAR(50)</td><td>Tipo (Automóvil, Motocicleta, etc.)</td></tr>
                <tr><td><code>uso</code></td><td>VARCHAR(50)</td><td>Uso (Privado, Público, Rent-a-Car)</td></tr>
                <tr><td><code>chasis</code></td><td>VARCHAR(50)</td><td>Número de chasis</td></tr>
                <tr><td><code>motor</code></td><td>VARCHAR(50)</td><td>Número de motor</td></tr>
                <tr><td><code>valor_comercial</code></td><td>DECIMAL(15,2)</td><td>Valor comercial del vehículo</td></tr>
                <tr><td><code>capacidad_motor</code></td><td>VARCHAR(50)</td><td>Capacidad del motor</td></tr>
                <tr><td><code>prima_total</code></td><td>DECIMAL(15,2)</td><td>Prima total de la póliza</td></tr>
                <tr><td><code>prima_neta</code></td><td>DECIMAL(15,2)</td><td>Prima neta (sin impuestos)</td></tr>
                <tr><td><code>itbis</code></td><td>DECIMAL(15,2)</td><td>ITBIS (18%)</td></tr>
                <tr><td><code>periodicidad_pago</code></td><td>ENUM(...)</td><td>Mensual, Trimestral, Semestral, Anual</td></tr>
                <tr><td><code>cuota_monto</code></td><td>DECIMAL(15,2)</td><td>Monto de cuota según periodicidad</td></tr>
                <tr><td><code>total_cuotas</code></td><td>INT</td><td>Total de cuotas</td></tr>
                <tr><td><code>cuotas_pagadas</code></td><td>INT</td><td>Cuotas ya pagadas</td></tr>
                <tr><td><code>balance</code></td><td>DECIMAL(15,2)</td><td>Balance pendiente de pago</td></tr>
                <tr><td><code>fecha_inicio_vigencia</code></td><td>DATE</td><td>Inicio de vigencia</td></tr>
                <tr><td><code>fecha_vencimiento</code></td><td>DATE</td><td>Fin de vigencia</td></tr>
                <tr><td><code>numero_poliza_aseguradora</code></td><td>VARCHAR(100)</td><td>N° de póliza asignado por aseguradora</td></tr>
                <tr><td><code>estado</code></td><td>ENUM('ACTIVA','SUSPENDIDA','VENCIDA','CANCELADA')</td><td>Estado de la póliza</td></tr>
                <tr><td><code>validada</code></td><td>BOOLEAN</td><td>Póliza validada por aseguradora</td></tr>
                <tr><td><code>notas_internas</code></td><td>TEXT</td><td>Notas internas</td></tr>
                <tr><td><code>distribucion_comisiones</code></td><td>JSON</td><td>Distribución de comisiones calculadas</td></tr>
                <tr><td><code>usuario_id</code></td><td>INT</td><td>Usuario que emitió la póliza</td></tr>
                <tr><td><code>fecha_creacion</code></td><td>DATETIME</td><td>Fecha de emisión</td></tr>
            </tbody>
        </table>

        <h3>Tabla: pagos_polizas</h3>
        <table>
            <thead>
                <tr>
                    <th>Campo</th>
                    <th>Tipo</th>
                    <th>Descripción</th>
                </tr>
            </thead>
            <tbody>
                <tr><td><code>id</code></td><td>BIGINT AUTO_INCREMENT</td><td>ID único de pago</td></tr>
                <tr><td><code>poliza_id</code></td><td>BIGINT</td><td>Referencia a póliza</td></tr>
                <tr><td><code>cuota_numero</code></td><td>INT</td><td>Número de cuota pagada</td></tr>
                <tr><td><code>monto</code></td><td>DECIMAL(15,2)</td><td>Monto del pago</td></tr>
                <tr><td><code>fecha_pago</code></td><td>DATETIME</td><td>Fecha del pago</td></tr>
                <tr><td><code>forma_pago</code></td><td>ENUM('EFECTIVO','TRANSFERENCIA','CHEQUE','TARJETA_CREDITO','TARJETA_DEBITO')</td><td>Forma de pago</td></tr>
                <tr><td><code>banco</code></td><td>VARCHAR(100)</td><td>Banco (si aplica)</td></tr>
                <tr><td><code>numero_comprobante</code></td><td>VARCHAR(100)</td><td>N° de comprobante/referencia</td></tr>
                <tr><td><code>ncf</code></td><td>VARCHAR(13)</td><td>NCF generado (B02-XXXXXXXX)</td></tr>
                <tr><td><code>descripcion</code></td><td>TEXT</td><td>Descripción del pago</td></tr>
                <tr><td><code>comprobante_adjunto</code></td><td>VARCHAR(255)</td><td>Ruta del archivo adjunto (PDF/PNG/JPG)</td></tr>
                <tr><td><code>estado</code></td><td>ENUM('CONFIRMADO','PENDIENTE','RECHAZADO')</td><td>Estado del pago</td></tr>
                <tr><td><code>usuario_id</code></td><td>INT</td><td>Usuario que registró el pago</td></tr>
            </tbody>
        </table>

        <h3>Tabla: metas_ventas</h3>
        <table>
            <thead>
                <tr>
                    <th>Campo</th>
                    <th>Tipo</th>
                    <th>Descripción</th>
                </tr>
            </thead>
            <tbody>
                <tr><td><code>id</code></td><td>INT AUTO_INCREMENT</td><td>ID único</td></tr>
                <tr><td><code>producto_id</code></td><td>INT</td><td>Referencia a producto</td></tr>
                <tr><td><code>mes</code></td><td>INT (1-12)</td><td>Mes del objetivo</td></tr>
                <tr><td><code>anio</code></td><td>INT</td><td>Año del objetivo</td></tr>
                <tr><td><code>meta_cantidad</code></td><td>INT</td><td>Cantidad de pólizas objetivo</td></tr>
                <tr><td><code>usuario_id</code></td><td>INT</td><td>Usuario que configuró la meta</td></tr>
            </tbody>
        </table>
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
                <tr><td><code>/polizas.php?action=listar</code></td><td>GET</td><td>Lista pólizas con filtros</td></tr>
                <tr><td><code>/polizas.php?action=emitir</code></td><td>POST</td><td>Emite nueva póliza desde cotización</td></tr>
                <tr><td><code>/polizas.php?action=actualizar</code></td><td>POST</td><td>Actualiza póliza existente</td></tr>
                <tr><td><code>/polizas.php?action=registrar_pago</code></td><td>POST</td><td>Registra pago de cuota</td></tr>
                <tr><td><code>/polizas.php?action=generar_documento</code></td><td>POST</td><td>Genera PDF (marbete, solicitud, recibo, factura)</td></tr>
                <tr><td><code>/polizas.php?action=auditoria</code></td><td>GET</td><td>Obtiene ficha de auditoría técnica</td></tr>
                <tr><td><code>/metas_ventas.php?action=guardar</code></td><td>POST</td><td>Guarda metas mensuales</td></tr>
                <tr><td><code>/metas_ventas.php?action=obtener</code></td><td>GET</td><td>Obtiene metas configuradas</td></tr>
            </tbody>
        </table>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-calculator"></i> Fórmulas de Cálculo</h2>
        
        <div class="code-block">
// Cálculo de Prima Neta
prima_neta = prima_total / 1.18

// Cálculo de ITBIS
itbis = prima_neta * 0.18

// Cálculo de Cuota según Periodicidad
if (periodicidad == 'MENSUAL') cuota = prima_total / 12
if (periodicidad == 'TRIMESTRAL') cuota = prima_total / 4
if (periodicidad == 'SEMESTRAL') cuota = prima_total / 2
if (periodicidad == 'ANUAL') cuota = prima_total

// Cálculo de Balance
balance = prima_total - (cuota * cuotas_pagadas)

// Fecha de Vencimiento
fecha_vencimiento = fecha_inicio_vigencia + 1 año

// Distribución de Comisiones (sobre Prima Neta)
comision_agente = prima_neta * porcentaje_agente
comision_supervisor = prima_neta * porcentaje_supervisor
comision_empresa = prima_neta - comision_agente - comision_supervisor
        </div>
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
                <tr><td><strong>R1 - Notificación</strong></td><td><span class="badge badge-ok">100%</span></td><td>Envío automático de marbete y recibo al cliente</td></tr>
                <tr><td><strong>R2 - Evidencia</strong></td><td><span class="badge badge-ok">100%</span></td><td>Registro de pagos con comprobante adjunto y NCF</td></tr>
                <tr><td><strong>R6 - NCF</strong></td><td><span class="badge badge-ok">100%</span></td><td>Generación automática de NCF B02 en pagos</td></tr>
                <tr><td><strong>R7 - Contable</strong></td><td><span class="badge badge-ok">100%</span></td><td>Asiento contable automático al emitir póliza</td></tr>
                <tr><td><strong>R4 - Auditoría</strong></td><td><span class="badge badge-ok">100%</span></td><td>Ficha de auditoría técnica con QR</td></tr>
            </tbody>
        </table>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-circle-check"></i> Flujo Completo de Emisión</h2>
        
        <div class="code-block">
1. Usuario selecciona "Nueva Póliza" desde cotización
   ↓
2. Wizard Paso 1: Carga datos de cotización (tipo, cobertura, prima)
   - Selecciona/crea cliente
   - Selecciona aseguradora y perfil de cobertura
   ↓
3. Wizard Paso 2: Datos del vehículo
   - Busca por placa (si existe) o ingresa manualmente
   - Completa marca, modelo, año, color, tipo, uso, chasis, motor
   ↓
4. Wizard Paso 3: Condiciones financieras
   - Ingresa prima total
   - Selecciona periodicidad de pago
   - Sistema calcula: prima neta, ITBIS, cuota, fechas
   ↓
5. Wizard Paso 4: Resumen y distribución de comisiones
   - Revisa resumen completo
   - Confirma distribución de comisiones
   ↓
6. Click en "Emitir Póliza"
   - Guarda en tabla polizas
   - Calcula y guarda comisiones
   - Genera asiento contable automático
   - Registra en auditoría_lineal
   ↓
7. Generación de documentos
   - Marbete provisional (con QR)
   - Solicitud de seguro
   - Recibo de pago (si aplica pago inicial)
   ↓
8. Descarga automática de PDFs
        </div>
    </div>

    <div class="footer-doc">
        <p><strong><?php echo $platform_name; ?></strong> - Módulo de Pólizas | Documentación Técnica</p>
        <p>Clasificación: <strong>INTERNO</strong> | Versión: 1.0</p>
    </div>
</div>

<!-- DOCUMENTO 2: SINIESTROS -->
<div class="documento" id="documento2">
    <div class="doc-header">
        <h1><i class="fa-solid fa-car-burst"></i> Módulo de Siniestros - Gestión Contable</h1>
        <div class="meta">
            <span><i class="fa-solid fa-building"></i> <?php echo $platform_name; ?></span>
            <span><i class="fa-solid fa-clipboard-list"></i> Claims Management</span>
            <span><i class="fa-solid fa-calendar"></i> <?php echo date('d/m/Y'); ?></span>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-circle-info"></i> Descripción General</h2>
        <p>El Módulo de Siniestros es el sistema integral de declaración de incidentes, liquidación de reclamos y automatización contable de provisiones en tiempo real. Permite gestionar el ciclo completo de un siniestro desde la declaración inicial hasta el pago final, con integración contable automática que genera asientos de provisión y pago conforme a normas actuariales y fiscales.</p>
        
        <div class="status-grid">
            <div class="status-card cumple">
                <div class="label">Estados</div>
                <div class="value">5 estados</div>
                <div class="desc">Registrado, En Revisión, Aprobado, Rechazado, Pagado</div>
            </div>
            <div class="status-card cumple">
                <div class="label">KPIs</div>
                <div class="value">4 métricas</div>
                <div class="desc">Activos, Reclamado, Liquidado, Aprobación</div>
            </div>
            <div class="status-card cumple">
                <div class="label">Contable</div>
                <div class="value">Automático</div>
                <div class="desc">Asientos de provisión y pago</div>
            </div>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-gears"></i> Funcionalidades Principales</h2>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-chart-pie"></i> Dashboard de KPIs</h4>
            <ul>
                <li><strong>Siniestros Activos:</strong> Cantidad de siniestros en proceso (Registrado + En Revisión + Aprobado)</li>
                <li><strong>Total Reclamado:</strong> Suma de montos reclamados de todos los siniestros activos</li>
                <li><strong>Total Liquidado:</strong> Suma de montos aprobados y pagados</li>
                <li><strong>Tasa Aprobación:</strong> Porcentaje de siniestros aprobados vs total (Aprobados / (Aprobados + Rechazados) × 100)</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-filter"></i> Filtros por Estado</h4>
            <ul>
                <li><strong>Todos los Estados:</strong> Visualización completa</li>
                <li><strong>Registrado:</strong> Siniestro recién declarado, pendiente de revisión</li>
                <li><strong>En Revisión:</strong> En proceso de evaluación y dictamen</li>
                <li><strong>Aprobado:</strong> Dictamen favorable, pendiente de pago</li>
                <li><strong>Rechazado:</strong> Dictamen negativo, no procede pago</li>
                <li><strong>Pagado (Liquidado):</strong> Pago completado, caso cerrado</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-plus-circle"></i> Declarar Nuevo Siniestro</h4>
            <ul>
                <li><strong>Buscar Póliza Activa:</strong> Búsqueda por N° de Póliza, Cliente o Cédula</li>
                <li><strong>Datos automáticos:</strong> Cliente, Documento, Póliza Seleccionada, Aseguradora</li>
                <li><strong>Fecha del Siniestro*</strong></li>
                <li><strong>Monto Reclamado (DOP)*</strong></li>
                <li><strong>Lugar del Evento / Accidente</strong></li>
                <li><strong>Descripción Detallada del Evento*</strong></li>
                <li><strong>Adjuntar Evidencia:</strong> Foto del choque, acta policial, presupuesto del taller (PDF, PNG, JPG)</li>
                <li><strong>Acción:</strong> Declarar Incidente</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-file-circle-check"></i> Ficha y Dictamen de Siniestro</h4>
            <ul>
                <li><strong>Detalles del Reclamo:</strong>
                    <ul>
                        <li>Número de Caso</li>
                        <li>Póliza</li>
                        <li>Cliente</li>
                        <li>Fecha Evento</li>
                        <li>Lugar</li>
                        <li>Monto Reclamado</li>
                        <li>Monto Aprobado</li>
                        <li>Estatus Actual</li>
                        <li>Descripción del Suceso</li>
                        <li>Evidencia Cargada (visualización de archivos)</li>
                    </ul>
                </li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-gavel"></i> Dictaminar o Liquidar</h4>
            <ul>
                <li><strong>Poner En Revisión:</strong> Cambia estado de "Registrado" a "En Revisión"</li>
                <li><strong>Dictaminar Aprobación:</strong>
                    <ul>
                        <li>Monto Aprobado* (puede ser menor al reclamado)</li>
                        <li>Acción: Aprobar</li>
                    </ul>
                </li>
                <li><strong>Rechazar Reclamación:</strong> Cambia estado a "Rechazado" con justificación</li>
                <li><strong>Liquidar y Generar Pago Contable:</strong>
                    <ul>
                        <li>Genera asiento contable de pago</li>
                        <li>Registra pago en sistema financiero</li>
                        <li>Cambia estado a "Pagado (Liquidado)"</li>
                        <li>Actualiza KPIs automáticamente</li>
                    </ul>
                </li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-book"></i> Asientos Contables Vinculados</h4>
            <ul>
                <li><strong>Visualización:</strong> Tabla con asientos contables generados automáticamente</li>
                <li><strong>Columnas:</strong> Fecha, Asiento #, Descripción, Débito, Crédito, Estado</li>
                <li><strong>Tipos de asientos:</strong>
                    <ul>
                        <li><strong>Provisión:</strong> Al aprobar siniestro (Débito: Gastos de Siniestros, Crédito: Provisión de Siniestros)</li>
                        <li><strong>Pago:</strong> Al liquidar (Débito: Provisión de Siniestros, Crédito: Banco/Caja)</li>
                        <li><strong>Reverso:</strong> Al rechazar (reversa la provisión)</li>
                    </ul>
                </li>
                <li><strong>Integración:</strong> MotorContable automático</li>
            </ul>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-database"></i> Estructura de Datos - Tablas Principales</h2>
        
        <h3>Tabla: siniestros</h3>
        <table>
            <thead>
                <tr>
                    <th>Campo</th>
                    <th>Tipo</th>
                    <th>Descripción</th>
                </tr>
            </thead>
            <tbody>
                <tr><td><code>id</code></td><td>BIGINT AUTO_INCREMENT</td><td>ID único de siniestro</td></tr>
                <tr><td><code>numero_caso</code></td><td>VARCHAR(40)</td><td>Número de caso (SIN-2026-XXXX)</td></tr>
                <tr><td><code>poliza_id</code></td><td>BIGINT</td><td>Referencia a póliza</td></tr>
                <tr><td><code>cliente_id</code></td><td>INT</td><td>Referencia a cliente</td></tr>
                <tr><td><code>fecha_evento</code></td><td>DATETIME</td><td>Fecha y hora del siniestro</td></tr>
                <tr><td><code>lugar_evento</code></td><td>TEXT</td><td>Lugar del evento/accidente</td></tr>
                <tr><td><code>descripcion</code></td><td>TEXT</td><td>Descripción detallada del evento</td></tr>
                <tr><td><code>monto_reclamado</code></td><td>DECIMAL(15,2)</td><td>Monto reclamado por el asegurado</td></tr>
                <tr><td><code>monto_aprobado</code></td><td>DECIMAL(15,2)</td><td>Monto aprobado tras dictamen</td></tr>
                <tr><td><code>estado</code></td><td>ENUM('REGISTRADO','EN_REVISION','APROBADO','RECHAZADO','PAGADO')</td><td>Estado del siniestro</td></tr>
                <tr><td><code>evidencia_archivos</code></td><td>JSON</td><td>Array de rutas de archivos adjuntos</td></tr>
                <tr><td><code>justificacion_rechazo</code></td><td>TEXT</td><td>Justificación si fue rechazado</td></tr>
                <tr><td><code>fecha_aprobacion</code></td><td>DATETIME</td><td>Fecha de aprobación</td></tr>
                <tr><td><code>fecha_pago</code></td><td>DATETIME</td><td>Fecha de pago/liquidación</td></tr>
                <tr><td><code>asiento_provision_id</code></td><td>BIGINT</td><td>Referencia a asiento de provisión</td></tr>
                <tr><td><code>asiento_pago_id</code></td><td>BIGINT</td><td>Referencia a asiento de pago</td></tr>
                <tr><td><code>usuario_declarante_id</code></td><td>INT</td><td>Usuario que declaró el siniestro</td></tr>
                <tr><td><code>usuario_dictaminador_id</code></td><td>INT</td><td>Usuario que dictaminó</td></tr>
                <tr><td><code>fecha_creacion</code></td><td>DATETIME</td><td>Fecha de declaración</td></tr>
            </tbody>
        </table>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-calculator"></i> Fórmulas y KPIs</h2>
        
        <div class="code-block">
// KPIs del Dashboard
siniestros_activos = COUNT(*) WHERE estado IN ('REGISTRADO','EN_REVISION','APROBADO')
total_reclamado = SUM(monto_reclamado) WHERE estado != 'RECHAZADO'
total_liquidado = SUM(monto_aprobado) WHERE estado = 'PAGADO'
tasa_aprobacion = (COUNT(APROBADO) / (COUNT(APROBADO) + COUNT(RECHAZADO))) * 100

// Asiento Contable de Provisión (al aprobar)
Débito: Gastos de Siniestros (cuenta 5.x.x.xx)
Crédito: Provisión de Siniestros (cuenta 2.x.x.xx)
Monto: monto_aprobado

// Asiento Contable de Pago (al liquidar)
Débito: Provisión de Siniestros (cuenta 2.x.x.xx)
Crédito: Banco/Caja (cuenta 1.x.x.xx)
Monto: monto_aprobado

// Asiento de Reverso (al rechazar)
Débito: Provisión de Siniestros
Crédito: Gastos de Siniestros
Monto: monto_reclamado (reversa provisión inicial si existía)
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-circle-check"></i> Flujo Completo de Siniestro</h2>
        
        <div class="code-block">
1. Usuario accede a "Declarar Siniestro"
   ↓
2. Busca póliza activa (por número, cliente o cédula)
   - Sistema carga datos: cliente, documento, póliza, aseguradora
   ↓
3. Completa datos del siniestro:
   - Fecha del evento
   - Monto reclamado
   - Lugar del evento
   - Descripción detallada
   - Adjunta evidencia (fotos, acta, presupuesto)
   ↓
4. Click en "Declarar Incidente"
   - Guarda en tabla siniestros (estado: REGISTRADO)
   - Registra en auditoría_lineal
   - Actualiza KPI: Siniestros Activos +1, Total Reclamado +monto
   ↓
5. Revisor cambia estado a "En Revisión"
   ↓
6. Dictamen:
   Opción A: Aprobar
   - Ingresa monto aprobado (puede ser menor al reclamado)
   - Sistema genera asiento de provisión contable
   - Estado: APROBADO
   - KPI: Tasa Aprobación actualizada
   
   Opción B: Rechazar
   - Ingresa justificación
   - Sistema reversa provisión si existía
   - Estado: RECHAZADO
   - KPI: Tasa Aprobación actualizada
   ↓
7. Si fue aprobado: "Liquidar y Generar Pago Contable"
   - Sistema genera asiento de pago
   - Registra pago en sistema financiero
   - Estado: PAGADO (LIQUIDADO)
   - KPI: Total Liquidado +monto_aprobado, Siniestros Activos -1
   ↓
8. Caso cerrado
        </div>
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
                <tr><td><strong>R4 - Auditoría</strong></td><td><span class="badge badge-ok">100%</span></td><td>Registro inmutable de cada cambio de estado</td></tr>
                <tr><td><strong>R7 - Contable</strong></td><td><span class="badge badge-ok">100%</span></td><td>Asientos automáticos de provisión y pago</td></tr>
                <tr><td><strong>R2 - Evidencia</strong></td><td><span class="badge badge-ok">100%</span></td><td>Adjuntar archivos de evidencia (fotos, actas)</td></tr>
            </tbody>
        </table>
    </div>

    <div class="footer-doc">
        <p><strong><?php echo $platform_name; ?></strong> - Módulo de Siniestros | Documentación Técnica</p>
        <p>Clasificación: <strong>INTERNO</strong> | Versión: 1.0</p>
    </div>
</div>

<!-- DOCUMENTO 3: PRODUCTOS -->
<div class="documento" id="documento3">
    <div class="doc-header">
        <h1><i class="fa-solid fa-box-open"></i> Catálogo de Productos y Deducibles</h1>
        <div class="meta">
            <span><i class="fa-solid fa-building"></i> <?php echo $platform_name; ?></span>
            <span><i class="fa-solid fa-tags"></i> Product Catalog</span>
            <span><i class="fa-solid fa-calendar"></i> <?php echo date('d/m/Y'); ?></span>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-circle-info"></i> Descripción General</h2>
        <p>El Catálogo de Productos es un CRM inteligente de consulta, tarifas, deducibles y carga masiva para aseguradoras aliadas. Permite gestionar el portafolio completo de productos asegurables, configurar deducibles por aseguradora y concepto, importar catálogos masivos desde Excel, y mantener actualizadas las primas base, comisiones y vigencias de cada producto.</p>
        
        <div class="status-grid">
            <div class="status-card cumple">
                <div class="label">KPIs</div>
                <div class="value">4 métricas</div>
                <div class="desc">Activos, Comisión, Aseguradoras, Deducibles</div>
            </div>
            <div class="status-card cumple">
                <div class="label">Importación</div>
                <div class="value">Masiva</div>
                <div class="desc">Excel con previsualización</div>
            </div>
            <div class="status-card cumple">
                <div class="label">Deducibles</div>
                <div class="value">Por producto</div>
                <div class="desc">Configuración granular</div>
            </div>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-gears"></i> Funcionalidades Principales</h2>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-chart-pie"></i> Dashboard de KPIs</h4>
            <ul>
                <li><strong>Productos Activos:</strong> Cantidad de productos en estado "Activo"</li>
                <li><strong>Comisión Promedio:</strong> Promedio de comisiones de venta de todos los productos activos</li>
                <li><strong>Aseguradoras Aliadas:</strong> Cantidad de aseguradoras con productos configurados</li>
                <li><strong>Deducibles Registrados:</strong> Total de deducibles configurados en el sistema</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-filter"></i> Filtros Avanzados</h4>
            <ul>
                <li><strong>Tipo de Vehículo:</strong> Todos, Privado, Comercial, Motocicleta, Pesado</li>
                <li><strong>Uso:</strong> Todos, Privado, Público, Carga</li>
                <li><strong>Estado:</strong> Todos, Activo, Inactivo, Descontinuado</li>
                <li><strong>Búsqueda:</strong> Por código, nombre, aseguradora</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-table"></i> Tabla de Productos</h4>
            <ul>
                <li><strong>Columnas:</strong> Código, Nombre del Producto, Vehículo, Uso, Prima Base, Comisión, Deducibles, Estado, Acciones</li>
                <li><strong>Acciones:</strong> Editar, Configurar Deducibles, Activar/Desactivar, Eliminar</li>
                <li><strong>Paginación:</strong> 10, 20, 50 registros por página</li>
                <li><strong>Ordenamiento:</strong> Por columna (clic en encabezado)</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-plus-circle"></i> Nuevo Producto</h4>
            <ul>
                <li><strong>Código del Producto*</strong> (único)</li>
                <li><strong>Nombre del Producto*</strong></li>
                <li><strong>Descripción Comercial</strong></li>
                <li><strong>Tipo de Vehículo:</strong> Dropdown con tipos disponibles</li>
                <li><strong>Capacidad del Motor:</strong> Según tipo de vehículo</li>
                <li><strong>Uso del Vehículo:</strong> Privado, Público, Carga/Comercial</li>
                <li><strong>Prima Base (DOP)*</strong></li>
                <li><strong>Comisión de Venta (%)*</strong></li>
                <li><strong>Vigencia (Días)</strong></li>
                <li><strong>Estado del Producto:</strong> Activo, Inactivo, Descontinuado</li>
                <li><strong>Acción:</strong> Guardar Producto</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-sliders"></i> Configurar Deducibles</h4>
            <ul>
                <li><strong>Modal por producto:</strong> Configuración granular de deducibles</li>
                <li><strong>Tabla de deducibles:</strong>
                    <ul>
                        <li>Aseguradora*</li>
                        <li>Concepto Deducible* (Daños a Propiedad, Colisión, Robo Total, etc.)</li>
                        <li>Porcentaje (%)</li>
                        <li>Mínimo DOP</li>
                        <li>Estado (Activo/Inactivo)</li>
                        <li>Acciones (Editar, Eliminar)</li>
                    </ul>
                </li>
                <li><strong>Fórmula:</strong> Deducible = MAX(monto_siniestro × porcentaje, mínimo_dop)</li>
                <li><strong>Múltiples aseguradoras:</strong> Cada producto puede tener deducibles diferentes por aseguradora</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-file-import"></i> Importar Catálogo de Productos</h4>
            <ul>
                <li><strong>Formatos admitidos:</strong> .xlsx, .xls</li>
                <li><strong>Drag & Drop:</strong> Arrastrar archivo o hacer clic para buscar</li>
                <li><strong>Previsualización:</strong> Tabla con Código, Nombre, Prima, Comisión antes de importar</li>
                <li><strong>Validación:</strong> Detecta duplicados por código, valida campos obligatorios</li>
                <li><strong>Acción:</strong> Importar Registros</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-file-export"></i> Exportar Catálogo</h4>
            <ul>
                <li><strong>Formatos:</strong> PDF, Excel (.xlsx), CSV</li>
                <li><strong>Filtros aplicados:</strong> Exporta solo los registros filtrados</li>
                <li><strong>Columnas:</strong> Todas las columnas visibles en la tabla</li>
            </ul>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-database"></i> Estructura de Datos - Tablas Principales</h2>
        
        <h3>Tabla: productos</h3>
        <table>
            <thead>
                <tr>
                    <th>Campo</th>
                    <th>Tipo</th>
                    <th>Descripción</th>
                </tr>
            </thead>
            <tbody>
                <tr><td><code>id</code></td><td>INT AUTO_INCREMENT</td><td>ID único de producto</td></tr>
                <tr><td><code>codigo</code></td><td>VARCHAR(50)</td><td>Código único del producto</td></tr>
                <tr><td><code>nombre</code></td><td>VARCHAR(200)</td><td>Nombre del producto</td></tr>
                <tr><td><code>descripcion</code></td><td>TEXT</td><td>Descripción comercial</td></tr>
                <tr><td><code>tipo_vehiculo</code></td><td>VARCHAR(50)</td><td>Tipo de vehículo aplicable</td></tr>
                <tr><td><code>capacidad_motor</code></td><td>VARCHAR(50)</td><td>Capacidad del motor</td></tr>
                <tr><td><code>uso</code></td><td>VARCHAR(50)</td><td>Uso del vehículo</td></tr>
                <tr><td><code>prima_base</code></td><td>DECIMAL(15,2)</td><td>Prima base en DOP</td></tr>
                <tr><td><code>comision_venta</code></td><td>DECIMAL(5,2)</td><td>Comisión de venta (%)</td></tr>
                <tr><td><code>vigencia_dias</code></td><td>INT</td><td>Vigencia en días</td></tr>
                <tr><td><code>estado</code></td><td>ENUM('ACTIVO','INACTIVO','DESCONTINUADO')</td><td>Estado del producto</td></tr>
                <tr><td><code>aseguradora_id</code></td><td>INT</td><td>Referencia a aseguradora</td></tr>
                <tr><td><code>usuario_id</code></td><td>INT</td><td>Usuario que creó el producto</td></tr>
                <tr><td><code>fecha_creacion</code></td><td>DATETIME</td><td>Fecha de creación</td></tr>
                <tr><td><code>fecha_actualizacion</code></td><td>DATETIME</td><td>Última actualización</td></tr>
            </tbody>
        </table>

        <h3>Tabla: deducibles</h3>
        <table>
            <thead>
                <tr>
                    <th>Campo</th>
                    <th>Tipo</th>
                    <th>Descripción</th>
                </tr>
            </thead>
            <tbody>
                <tr><td><code>id</code></td><td>INT AUTO_INCREMENT</td><td>ID único de deducible</td></tr>
                <tr><td><code>producto_id</code></td><td>INT</td><td>Referencia a producto</td></tr>
                <tr><td><code>aseguradora_id</code></td><td>INT</td><td>Referencia a aseguradora</td></tr>
                <tr><td><code>concepto</code></td><td>VARCHAR(200)</td><td>Concepto del deducible (Daños, Colisión, Robo, etc.)</td></tr>
                <tr><td><code>porcentaje</code></td><td>DECIMAL(5,2)</td><td>Porcentaje del deducible</td></tr>
                <tr><td><code>minimo_dop</code></td><td>DECIMAL(15,2)</td><td>Monto mínimo en DOP</td></tr>
                <tr><td><code>estado</code></td><td>ENUM('ACTIVO','INACTIVO')</td><td>Estado del deducible</td></tr>
                <tr><td><code>fecha_creacion</code></td><td>DATETIME</td><td>Fecha de creación</td></tr>
            </tbody>
        </table>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-calculator"></i> Fórmulas de Cálculo</h2>
        
        <div class="code-block">
// Cálculo de Deducible
deducible = MAX(monto_siniestro * (porcentaje / 100), minimo_dop)

// Ejemplo:
// Siniestro: RD$ 100,000
// Deducible: 5% con mínimo RD$ 5,000
// Cálculo: MAX(100,000 * 0.05, 5,000) = MAX(5,000, 5,000) = RD$ 5,000

// Cálculo de Comisión Promedio
comision_promedio = AVG(comision_venta) WHERE estado = 'ACTIVO'

// Cálculo de Aseguradoras Aliadas
aseguradoras_aliadas = COUNT(DISTINCT aseguradora_id) WHERE estado = 'ACTIVO'
        </div>
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
                <tr><td><strong>R5 - Datos sensibles</strong></td><td><span class="badge badge-ok">100%</span></td><td>Tarifas y deducibles no visibles en UI pública</td></tr>
                <tr><td><strong>R8 - Exportación</strong></td><td><span class="badge badge-ok">100%</span></td><td>PDF, Excel, CSV con filtros aplicados</td></tr>
                <tr><td><strong>R4 - Auditoría</strong></td><td><span class="badge badge-ok">100%</span></td><td>Registro de cambios en productos y deducibles</td></tr>
            </tbody>
        </table>
    </div>

    <div class="footer-doc">
        <p><strong><?php echo $platform_name; ?></strong> - Catálogo de Productos | Documentación Técnica</p>
        <p>Clasificación: <strong>INTERNO</strong> | Versión: 1.0</p>
    </div>
</div>

<!-- DOCUMENTO 4: REPORTES -->
<div class="documento" id="documento4">
    <div class="doc-header">
        <h1><i class="fa-solid fa-chart-pie"></i> Reportes y Modelador PDF</h1>
        <div class="meta">
            <span><i class="fa-solid fa-building"></i> <?php echo $platform_name; ?></span>
            <span><i class="fa-solid fa-file-pdf"></i> Report Engine</span>
            <span><i class="fa-solid fa-calendar"></i> <?php echo date('d/m/Y'); ?></span>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-circle-info"></i> Descripción General</h2>
        <p>El Módulo de Reportes y Modelador es el sistema de generación de análisis, reportes corporativos y modelador de documentos PDF. Permite crear plantillas personalizables con variables dinámicas, generar reportes de ventas generales, ventas logradas (meta vs real) y margen comercial, enviar reportes por correo electrónico, y gestionar logos de aseguradoras para branding corporativo.</p>
        
        <div class="status-grid">
            <div class="status-card cumple">
                <div class="label">Modelador</div>
                <div class="value">Drag & Drop</div>
                <div class="desc">Variables arrastrables</div>
            </div>
            <div class="status-card cumple">
                <div class="label">Reportes</div>
                <div class="value">3 tipos</div>
                <div class="desc">Ventas, Metas, Margen</div>
            </div>
            <div class="status-card cumple">
                <div class="label">Envío</div>
                <div class="value">Email</div>
                <div class="desc">Reportes por correo</div>
            </div>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-gears"></i> Funcionalidades Principales</h2>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-wand-magic-sparkles"></i> Modelador PDF-DOCS</h4>
            <ul>
                <li><strong>1. Subir Plantilla (PDF/PNG/JPG):</strong>
                    <ul>
                        <li>Asignar Aseguradora: UNIVERSAL, RESERVAS, HUMANO, MAPFRE, SURA, COLONIAL, PATRIA, PEPÍN, APS, MULTISEGUROS</li>
                        <li>Subir y Cargar archivo de plantilla</li>
                        <li>Gestionar Logos de aseguradoras</li>
                    </ul>
                </li>
                <li><strong>2. Plantillas Existentes:</strong>
                    <ul>
                        <li>Seleccionar plantilla previamente cargada</li>
                        <li>Editar configuración de variables</li>
                        <li>Previsualizar resultado</li>
                    </ul>
                </li>
                <li><strong>3. Variables Disponibles (arrastrables):</strong>
                    <ul>
                        <li>Nombre Completo</li>
                        <li>Cédula/RNC</li>
                        <li>Teléfono</li>
                        <li>N° Póliza</li>
                        <li>Vigencia Desde</li>
                        <li>Vigencia Hasta</li>
                        <li>Prima Total</li>
                        <li>Fianza Judicial</li>
                        <li>Casa Contratada</li>
                        <li>Asistencia Vial</li>
                        <li>Deducible (Deduc. Min)</li>
                        <li>Marca Vehículo</li>
                        <li>Modelo Vehículo</li>
                        <li>Año Vehículo</li>
                        <li>Chasis</li>
                        <li>Placa</li>
                        <li>Uso</li>
                        <li>Tipo de Vehículo</li>
                        <li>Hora de Emisión</li>
                    </ul>
                </li>
                <li><strong>4. Editar Campo:</strong>
                    <ul>
                        <li>Tamaño (px)</li>
                        <li>Negrita (checkbox)</li>
                        <li>Eliminar Campo</li>
                        <li>Guardar Configuración</li>
                    </ul>
                </li>
                <li><strong>5. Vista Previa:</strong> Visualización del documento con variables aplicadas</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-chart-bar"></i> Reportes Generales</h4>
            <ul>
                <li><strong>📊 Reporte de Ventas Generales:</strong>
                    <ul>
                        <li>Total de pólizas emitidas por período</li>
                        <li>Prima total facturada</li>
                        <li>Desglose por tipo de seguro</li>
                        <li>Desglose por aseguradora</li>
                        <li>Comparativa vs período anterior</li>
                    </ul>
                </li>
                <li><strong>📈 Reporte de Ventas Logradas (Meta vs Real):</strong>
                    <ul>
                        <li>Metas configuradas por producto y mes</li>
                        <li>Ventas reales alcanzadas</li>
                        <li>Porcentaje de cumplimiento</li>
                        <li>Gráfico de barras comparativo</li>
                        <li>Alertas de bajo rendimiento (&lt;80%)</li>
                    </ul>
                </li>
                <li><strong>💰 Reporte de Margen Comercial:</strong>
                    <ul>
                        <li>Prima total facturada</li>
                        <li>Comisiones pagadas a agentes</li>
                        <li>Margen bruto (prima - comisiones)</li>
                        <li>Porcentaje de margen</li>
                        <li>Tendencia mensual</li>
                    </ul>
                </li>
                <li><strong>Período:</strong> Enero a Diciembre (selector de mes)</li>
                <li><strong>Acciones:</strong>
                    <ul>
                        <li>Actualizar (generar reporte en tiempo real)</li>
                        <li>Imprimir (PDF corporativo)</li>
                        <li>Enviar Correo (email con PDF adjunto)</li>
                    </ul>
                </li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-envelope"></i> Enviar Reporte por Correo</h4>
            <ul>
                <li><strong>Email del Destinatario:</strong> Campo de correo electrónico</li>
                <li><strong>Formato:</strong> PDF corporativo con branding</li>
                <li><strong>Adjuntos:</strong> Reporte PDF + Excel opcional</li>
                <li><strong>Mensaje:</strong> Texto personalizado con datos del período</li>
                <li><strong>Acción:</strong> Enviar</li>
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
                <tr><td><code>archivo_ruta</code></td><td>VARCHAR(255)</td><td>Ruta del archivo PDF/PNG/JPG</td></tr>
                <tr><td><code>tipo_archivo</code></td><td>ENUM('PDF','PNG','JPG')</td><td>Tipo de archivo</td></tr>
                <tr><td><code>variables_config</code></td><td>JSON</td><td>Configuración de variables (posición, tamaño, negrita)</td></tr>
                <tr><td><code>estado</code></td><td>ENUM('ACTIVA','INACTIVA')</td><td>Estado de la plantilla</td></tr>
                <tr><td><code>usuario_id</code></td><td>INT</td><td>Usuario que creó la plantilla</td></tr>
                <tr><td><code>fecha_creacion</code></td><td>DATETIME</td><td>Fecha de creación</td></tr>
            </tbody>
        </table>

        <h3>Tabla: reportes_generados</h3>
        <table>
            <thead>
                <tr>
                    <th>Campo</th>
                    <th>Tipo</th>
                    <th>Descripción</th>
                </tr>
            </thead>
            <tbody>
                <tr><td><code>id</code></td><td>BIGINT AUTO_INCREMENT</td><td>ID único de reporte</td></tr>
                <tr><td><code>tipo_reporte</code></td><td>ENUM('VENTAS_GENERALES','META_VS_REAL','MARGEN_COMERCIAL')</td><td>Tipo de reporte</td></tr>
                <tr><td><code>mes</code></td><td>INT (1-12)</td><td>Mes del reporte</td></tr>
                <tr><td><code>anio</code></td><td>INT</td><td>Año del reporte</td></tr>
                <tr><td><code>datos_json</code></td><td>JSON</td><td>Datos del reporte en formato JSON</td></tr>
                <tr><td><code>archivo_pdf</code></td><td>VARCHAR(255)</td><td>Ruta del PDF generado</td></tr>
                <tr><td><code>email_enviado</code></td><td>VARCHAR(255)</td><td>Email al que se envió (si aplica)</td></tr>
                <tr><td><code>fecha_envio</code></td><td>DATETIME</td><td>Fecha de envío por email</td></tr>
                <tr><td><code>usuario_id</code></td><td>INT</td><td>Usuario que generó el reporte</td></tr>
                <tr><td><code>fecha_creacion</code></td><td>DATETIME</td><td>Fecha de generación</td></tr>
            </tbody>
        </table>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-calculator"></i> Fórmulas de Reportes</h2>
        
        <div class="code-block">
// Reporte de Ventas Generales
total_polizas = COUNT(*) WHERE fecha_creacion BETWEEN inicio_mes AND fin_mes
prima_total = SUM(prima_total) WHERE fecha_creacion BETWEEN inicio_mes AND fin_mes
polizas_por_tipo = GROUP BY tipo_seguro
polizas_por_aseguradora = GROUP BY aseguradora
crecimiento = ((prima_total_mes_actual - prima_total_mes_anterior) / prima_total_mes_anterior) * 100

// Reporte de Ventas Logradas (Meta vs Real)
meta = SELECT meta_cantidad FROM metas_ventas WHERE producto_id = X AND mes = Y
real = COUNT(*) FROM polizas WHERE tipo_seguro = X AND MONTH(fecha_creacion) = Y
cumplimiento = (real / meta) * 100
alerta = IF cumplimiento < 80 THEN 'BAJO_RENDIMIENTO'

// Reporte de Margen Comercial
prima_total = SUM(prima_total) WHERE fecha_creacion BETWEEN inicio_mes AND fin_mes
comisiones_pagadas = SUM(comision_agente + comision_supervisor) WHERE fecha_pago BETWEEN inicio_mes AND fin_mes
margen_bruto = prima_total - comisiones_pagadas
porcentaje_margen = (margen_bruto / prima_total) * 100
        </div>
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
                <tr><td><strong>R8 - Exportación</strong></td><td><span class="badge badge-ok">100%</span></td><td>PDF corporativo con branding y variables dinámicas</td></tr>
                <tr><td><strong>R4 - Auditoría</strong></td><td><span class="badge badge-ok">100%</span></td><td>Registro de reportes generados y enviados</td></tr>
                <tr><td><strong>R1 - Notificación</strong></td><td><span class="badge badge-ok">100%</span></td><td>Envío de reportes por email con confirmación</td></tr>
            </tbody>
        </table>
    </div>

    <div class="footer-doc">
        <p><strong><?php echo $platform_name; ?></strong> - Reportes y Modelador | Documentación Técnica</p>
        <p>Clasificación: <strong>INTERNO</strong> | Versión: 1.0</p>
    </div>
</div>

<!-- DOCUMENTO 5: PERFIL DATA -->
<div class="documento" id="documento5">
    <div class="doc-header">
        <h1><i class="fa-solid fa-user-shield"></i> Perfil Data - Accesos y Seguridad</h1>
        <div class="meta">
            <span><i class="fa-solid fa-building"></i> <?php echo $platform_name; ?></span>
            <span><i class="fa-solid fa-shield-halved"></i> Security Profile</span>
            <span><i class="fa-solid fa-calendar"></i> <?php echo date('d/m/Y'); ?></span>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-circle-info"></i> Descripción General</h2>
        <p>Perfil Data es la ficha integral de accesos, permisos y políticas de seguridad del usuario actual. Proporciona un dashboard de consulta en tiempo real de la configuración de la cuenta, permisos por módulo, políticas de seguridad activas (expiración de sesión, bloqueo por intentos, 2FA), y permite compartir el estado del perfil vía correo, WhatsApp, Telegram o chat institucional. Es un módulo de solo lectura para el usuario final; únicamente el Administrador puede modificar permisos.</p>
        
        <div class="status-grid">
            <div class="status-card cumple">
                <div class="label">Modo</div>
                <div class="value">Informativo</div>
                <div class="desc">Solo lectura para usuario</div>
            </div>
            <div class="status-card cumple">
                <div class="label">Políticas</div>
                <div class="value">5 políticas</div>
                <div class="desc">Sesión, bloqueo, 2FA, clave</div>
            </div>
            <div class="status-card cumple">
                <div class="label">Compartir</div>
                <div class="value">4 canales</div>
                <div class="desc">Email, WhatsApp, Telegram, Chat</div>
            </div>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-gears"></i> Funcionalidades Principales</h2>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-circle-info"></i> Dashboard de Consulta de Perfil</h4>
            <ul>
                <li><strong>Información en tiempo real:</strong> Datos frescos desde la base de datos</li>
                <li><strong>Solo lectura:</strong> El usuario no puede modificar permisos</li>
                <li><strong>Actualización automática:</strong> Refleja cambios realizados por el Administrador</li>
                <li><strong>Indicador visual:</strong> "Obteniendo detalles del perfil..." durante carga</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-user"></i> Información del Usuario</h4>
            <ul>
                <li><strong>Nombre Completo:</strong> Nombre del usuario</li>
                <li><strong>Correo Electrónico:</strong> Email registrado</li>
                <li><strong>Perfil:</strong> Nombre del perfil asignado (Administrador, Agente, PDV, etc.)</li>
                <li><strong>Nivel:</strong> Nivel de acceso (1-5)</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-share-nodes"></i> Compartir Estado</h4>
            <ul>
                <li><strong>Correo Electrónico:</strong> Envío de ficha por email</li>
                <li><strong>WhatsApp:</strong> Compartir vía WhatsApp (link wa.me)</li>
                <li><strong>Telegram:</strong> Compartir vía Telegram (link t.me)</li>
                <li><strong>Chat Institucional:</strong> Compartir vía chat interno de la plataforma</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-shield-halved"></i> Cobertura del Perfil (Módulos Autorizados)</h4>
            <ul>
                <li><strong>Porcentaje de cobertura:</strong> X% Habilitado (ej: 75%)</li>
                <li><strong>Contador:</strong> X / Y Módulos (ej: 12 / 16 módulos)</li>
                <li><strong>Barra de progreso:</strong> Visualización gráfica del porcentaje</li>
                <li><strong>Lista de módulos:</strong> Detalle de módulos habilitados/deshabilitados</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-lock"></i> Políticas de Seguridad Activas</h4>
            <ul>
                <li><strong>Expiración de Sesión:</strong> X minutos (ej: 30 minutos)</li>
                <li><strong>Bloqueo por Intentos:</strong> X intentos fallidos (ej: 5 intentos)</li>
                <li><strong>Duración del Bloqueo:</strong> X minutos (ej: 15 minutos)</li>
                <li><strong>Expiración de Clave:</strong> X días (ej: 90 días)</li>
                <li><strong>Doble Factor (2FA):</strong> Habilitado/Deshabilitado</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-list-check"></i> Desglose Detallado de Permisos por Módulo</h4>
            <ul>
                <li><strong>Matriz de accesos:</strong> Tabla con todos los módulos y permisos</li>
                <li><strong>Columnas:</strong> Módulo, Crear, Editar, Eliminar, Importar, Exportar, Imprimir</li>
                <li><strong>Indicadores visuales:</strong> ✅ Habilitado / ❌ Deshabilitado</li>
                <li><strong>Carga dinámica:</strong> "Cargando matriz de accesos..." durante consulta</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-print"></i> Imprimir Ficha</h4>
            <ul>
                <li><strong>Formato:</strong> PDF corporativo con branding</li>
                <li><strong>Contenido:</strong> Toda la información del perfil, permisos y políticas</li>
                <li><strong>Uso:</strong> Documentación para auditoría o respaldo personal</li>
            </ul>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-database"></i> Estructura de Datos - Tablas Relacionadas</h2>
        
        <h3>Tabla: usuarios (consulta)</h3>
        <table>
            <thead>
                <tr>
                    <th>Campo</th>
                    <th>Tipo</th>
                    <th>Descripción</th>
                </tr>
            </thead>
            <tbody>
                <tr><td><code>id</code></td><td>INT</td><td>ID del usuario</td></tr>
                <tr><td><code>username</code></td><td>VARCHAR(100)</td><td>Nombre de usuario</td></tr>
                <tr><td><code>nombre_completo</code></td><td>VARCHAR(200)</td><td>Nombre completo</td></tr>
                <tr><td><code>email</code></td><td>VARCHAR(120)</td><td>Correo electrónico</td></tr>
                <tr><td><code>perfil_id</code></td><td>INT</td><td>Referencia a perfil</td></tr>
                <tr><td><code>nivel_acceso</code></td><td>INT (1-5)</td><td>Nivel de acceso</td></tr>
                <tr><td><code>activo</code></td><td>BOOLEAN</td><td>Usuario activo</td></tr>
            </tbody>
        </table>

        <h3>Tabla: perfiles (consulta)</h3>
        <table>
            <thead>
                <tr>
                    <th>Campo</th>
                    <th>Tipo</th>
                    <th>Descripción</th>
                </tr>
            </thead>
            <tbody>
                <tr><td><code>id</code></td><td>INT</td><td>ID del perfil</td></tr>
                <tr><td><code>nombre</code></td><td>VARCHAR(100)</td><td>Nombre del perfil</td></tr>
                <tr><td><code>descripcion</code></td><td>TEXT</td><td>Descripción del perfil</td></tr>
                <tr><td><code>permisos</code></td><td>JSON</td><td>Matriz de permisos por módulo</td></tr>
            </tbody>
        </table>

        <h3>Tabla: politicas_seguridad (consulta)</h3>
        <table>
            <thead>
                <tr>
                    <th>Campo</th>
                    <th>Tipo</th>
                    <th>Descripción</th>
                </tr>
            </thead>
            <tbody>
                <tr><td><code>id</code></td><td>INT</td><td>ID de política</td></tr>
                <tr><td><code>perfil_id</code></td><td>INT</td><td>Referencia a perfil</td></tr>
                <tr><td><code>expiracion_sesion_minutos</code></td><td>INT</td><td>Minutos para expiración de sesión</td></tr>
                <tr><td><code>bloqueo_intentos</code></td><td>INT</td><td>Intentos fallidos antes de bloqueo</td></tr>
                <tr><td><code>bloqueo_duracion_minutos</code></td><td>INT</td><td>Duración del bloqueo en minutos</td></tr>
                <tr><td><code>expiracion_clave_dias</code></td><td>INT</td><td>Días para expiración de contraseña</td></tr>
                <tr><td><code>doble_factor_activo</code></td><td>BOOLEAN</td><td>2FA habilitado</td></tr>
            </tbody>
        </table>
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
                <tr><td><code>/perfil_data.php?action=obtener</code></td><td>GET</td><td>Obtiene datos del perfil del usuario actual</td></tr>
                <tr><td><code>/perfil_data.php?action=permisos</code></td><td>GET</td><td>Obtiene matriz de permisos por módulo</td></tr>
                <tr><td><code>/perfil_data.php?action=politicas</code></td><td>GET</td><td>Obtiene políticas de seguridad activas</td></tr>
                <tr><td><code>/perfil_data.php?action=compartir</code></td><td>POST</td><td>Comparte ficha por email/WhatsApp/Telegram</td></tr>
                <tr><td><code>/perfil_data.php?action=imprimir</code></td><td>GET</td><td>Genera PDF de la ficha de perfil</td></tr>
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
                <tr><td><strong>R3 - Permisos granulares</strong></td><td><span class="badge badge-ok">100%</span></td><td>Visualización de permisos por módulo y operación</td></tr>
                <tr><td><strong>R4 - Auditoría</strong></td><td><span class="badge badge-ok">100%</span></td><td>Registro de consultas al perfil</td></tr>
                <tr><td><strong>R9 - Accesibilidad</strong></td><td><span class="badge badge-ok">100%</span></td><td>Labels ARIA, navegación por teclado</td></tr>
            </tbody>
        </table>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-circle-check"></i> Flujo de Consulta de Perfil</h2>
        
        <div class="code-block">
1. Usuario accede a "Perfil Data" desde menú lateral
   ↓
2. Sistema consulta datos del usuario actual (token de sesión)
   ↓
3. Carga información básica:
   - Nombre completo
   - Correo electrónico
   - Perfil asignado
   - Nivel de acceso
   ↓
4. Calcula cobertura de perfil:
   - Total de módulos disponibles
   - Módulos habilitados para el perfil
   - Porcentaje de cobertura
   ↓
5. Consulta políticas de seguridad:
   - Expiración de sesión
   - Bloqueo por intentos
   - Duración del bloqueo
   - Expiración de clave
   - Estado de 2FA
   ↓
6. Carga matriz de permisos:
   - Lista de todos los módulos
   - Permisos por operación (Crear, Editar, Eliminar, etc.)
   ↓
7. Usuario puede:
   - Imprimir ficha (PDF)
   - Compartir estado (Email, WhatsApp, Telegram, Chat)
   ↓
8. Datos se actualizan automáticamente si el Administrador modifica permisos
        </div>
    </div>

    <div class="footer-doc">
        <p><strong><?php echo $platform_name; ?></strong> - Perfil Data | Documentación Técnica</p>
        <p>Clasificación: <strong>INTERNO</strong> | Versión: 1.0</p>
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
        '📄 Módulo de Pólizas',
        '📄 Módulo de Siniestros',
        '📄 Catálogo de Productos',
        '📄 Reportes y Modelador',
        '📄 Perfil Data'
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
            'Modulo_Polizas',
            'Modulo_Siniestros',
            'Catalogo_Productos',
            'Reportes_Modelador',
            'Perfil_Data'
        ];
        
        doc.save('Documentacion_Plataforma_Parte2_' + nombres[documentoActivo - 1] + '.pdf');
        
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