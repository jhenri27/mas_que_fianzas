<?php
/**
 * DOCUMENTACIÓN GENERAL DE LA PLATAFORMA INTEGRADA - PARTE 1
 * MAS QUE FIANZAS - Sistema Integrado de Gestión
 * 
 * Genera documentación completa de los módulos:
 * 1. Clientes
 * 2. Auditoría Lineal
 * 3. Centro Financiero
 * 4. Comisiones
 * 5. Fianzas
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
    <title>Documentación General - <?php echo $platform_name; ?> - Parte 1</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="docs/mqf_docs_theme.css">
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
        /* Estilos de tablas diferidos a mqf_docs_theme.css */
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
        .module-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin: 25px 0;
        }
        .module-card {
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s ease;
        }
        .module-card:hover {
            border-color: #6366f1;
            box-shadow: 0 8px 20px rgba(99,102,241,0.15);
            transform: translateY(-2px);
        }
        .module-card h4 {
            color: #1e293b;
            font-size: 16px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .module-card p {
            color: #64748b;
            font-size: 13px;
            margin: 0;
        }
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
            .module-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<!-- MENÚ PRINCIPAL -->
<div class="menu-container" id="menuPrincipal">
    <h1><i class="fa-solid fa-book" style="color:#6366f1;"></i> Documentación de la Plataforma - Parte 1</h1>
    <p class="subtitle"><?php echo $platform_name; ?> | v<?php echo $platform_version; ?> | Generado: <?php echo $generation_date; ?></p>
    
    <div class="doc-buttons">
        <div class="doc-btn" onclick="mostrarDocumento(1)">
            <i class="fa-solid fa-users"></i>
            <h3>1. Módulo de Clientes</h3>
            <p>Gestión completa del directorio de clientes</p>
        </div>
        <div class="doc-btn" onclick="mostrarDocumento(2)">
            <i class="fa-solid fa-clipboard-list"></i>
            <h3>2. Auditoría Lineal NOFTRAB</h3>
            <p>Trazabilidad inmutable de operaciones</p>
        </div>
        <div class="doc-btn" onclick="mostrarDocumento(3)">
            <i class="fa-solid fa-building-columns"></i>
            <h3>3. Centro Financiero</h3>
            <p>Core contable y gestión NCF/DGII</p>
        </div>
        <div class="doc-btn" onclick="mostrarDocumento(4)">
            <i class="fa-solid fa-coins"></i>
            <h3>4. Panel de Comisiones</h3>
            <p>Gestión de comisiones y cuentas por cobrar</p>
        </div>
        <div class="doc-btn" onclick="mostrarDocumento(5)">
            <i class="fa-solid fa-shield-halved"></i>
            <h3>5. Módulo de Fianzas</h3>
            <p>Wizard de cotización y gestión de fianzas</p>
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

<!-- DOCUMENTO 1: MÓDULO DE CLIENTES -->
<div class="documento" id="documento1">
    <div class="doc-header">
        <h1><i class="fa-solid fa-users"></i> Módulo de Clientes - Gestión de Directorio</h1>
        <div class="meta">
            <span><i class="fa-solid fa-building"></i> <?php echo $platform_name; ?></span>
            <span><i class="fa-solid fa-users"></i> CRM Integrado</span>
            <span><i class="fa-solid fa-calendar"></i> <?php echo date('d/m/Y'); ?></span>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-circle-info"></i> Descripción General</h2>
        <p>El módulo de Clientes es el sistema centralizado de gestión del directorio de clientes de MAS QUE FIANZAS. Permite registrar, organizar y administrar toda la información de clientes y prospectos, con capacidades avanzadas de exportación, importación y gestión de relaciones comerciales.</p>
        
        <div class="status-grid">
            <div class="status-card cumple">
                <div class="label">Tipo de Módulo</div>
                <div class="value">CRM</div>
                <div class="desc">Customer Relationship Management</div>
            </div>
            <div class="status-card cumple">
                <div class="label">Base de Datos</div>
                <div class="value">MySQL</div>
                <div class="desc">Tabla: clientes</div>
            </div>
            <div class="status-card cumple">
                <div class="label">Exportación</div>
                <div class="value">5 formatos</div>
                <div class="desc">PDF, Excel, CSV, JSON, ZIP</div>
            </div>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-gears"></i> Funcionalidades Principales</h2>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-plus-circle"></i> Registro de Clientes</h4>
            <ul>
                <li><strong>Tipos de Persona:</strong> Física (individual) y Jurídica (empresas)</li>
                <li><strong>Campos obligatorios:</strong> Nombre/Razón Social, RNC/Cédula</li>
                <li><strong>Campos adicionales:</strong> Teléfono, Correo Electrónico, Dirección Física</li>
                <li><strong>Validación de datos:</strong> Formato de cédula/RNC dominicano (000-0000000-0)</li>
                <li><strong>Estatus:</strong> Activo / Inactivo</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-user-tag"></i> Clasificación de Roles</h4>
            <ul>
                <li><strong>Sin comisionante:</strong> Cliente sin asignación de comisión</li>
                <li><strong>Supervisor Comercial:</strong> Supervisor de Zona con comisiones</li>
                <li><strong>Agente Comercial:</strong> Agente vendedor con cartera</li>
                <li><strong>Socio Comercial:</strong> Partner estratégico</li>
                <li><strong>PDV:</strong> Punto de Venta con código de usuario</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-file-export"></i> Exportación Multi-Formato</h4>
            <ul>
                <li><strong>PDF:</strong> Listado profesional con formato de informe</li>
                <li><strong>Excel (.xlsx):</strong> Hoja de cálculo con formato editable</li>
                <li><strong>CSV:</strong> Texto plano compatible con cualquier sistema</li>
                <li><strong>JSON:</strong> Formato estructurado para integración API</li>
                <li><strong>ZIP:</strong> Paquete comprimido con múltiples archivos</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-file-import"></i> Importación Masiva</h4>
            <ul>
                <li>Carga masiva de clientes desde archivos Excel/CSV</li>
                <li>Validación automática de estructura de datos</li>
                <li>Detección de duplicados por RNC/Cédula</li>
                <li>Reporte de registros importados/errores</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-table"></i> Visualización en Tabla</h4>
            <ul>
                <li>Columnas: ID, Nombre/Razón Social, RNC/Cédula, Tipo, Teléfono, Estatus, Acciones</li>
                <li>Ordenamiento por columnas</li>
                <li>Paginación de resultados</li>
                <li>Búsqueda y filtrado en tiempo real</li>
                <li>Acciones rápidas: Editar, Eliminar, Ver detalle</li>
            </ul>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-database"></i> Estructura de Datos</h2>
        
        <table>
            <thead>
                <tr>
                    <th>Campo</th>
                    <th>Tipo</th>
                    <th>Requerido</th>
                    <th>Descripción</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>id</code></td>
                    <td>INT AUTO_INCREMENT</td>
                    <td><span class="badge badge-ok">PK</span></td>
                    <td>Identificador único del cliente</td>
                </tr>
                <tr>
                    <td><code>nombre_razon_social</code></td>
                    <td>VARCHAR(200)</td>
                    <td><span class="badge badge-ok">Sí</span></td>
                    <td>Nombre completo o razón social</td>
                </tr>
                <tr>
                    <td><code>rnc_cedula</code></td>
                    <td>VARCHAR(30)</td>
                    <td><span class="badge badge-ok">Sí</span></td>
                    <td>RNC (empresa) o Cédula (persona)</td>
                </tr>
                <tr>
                    <td><code>tipo_persona</code></td>
                    <td>ENUM('FISICA','JURIDICA')</td>
                    <td><span class="badge badge-ok">Sí</span></td>
                    <td>Tipo de persona</td>
                </tr>
                <tr>
                    <td><code>telefono</code></td>
                    <td>VARCHAR(30)</td>
                    <td><span class="badge badge-info">No</span></td>
                    <td>Número de teléfono de contacto</td>
                </tr>
                <tr>
                    <td><code>email</code></td>
                    <td>VARCHAR(120)</td>
                    <td><span class="badge badge-info">No</span></td>
                    <td>Correo electrónico</td>
                </tr>
                <tr>
                    <td><code>direccion</code></td>
                    <td>TEXT</td>
                    <td><span class="badge badge-info">No</span></td>
                    <td>Dirección física completa</td>
                </tr>
                <tr>
                    <td><code>estatus</code></td>
                    <td>ENUM('ACTIVO','INACTIVO')</td>
                    <td><span class="badge badge-ok">Sí</span></td>
                    <td>Estado del cliente</td>
                </tr>
                <tr>
                    <td><code>rol</code></td>
                    <td>ENUM(...)</td>
                    <td><span class="badge badge-info">No</span></td>
                    <td>Rol comercial (Agente, Supervisor, etc.)</td>
                </tr>
                <tr>
                    <td><code>codigo_usuario</code></td>
                    <td>VARCHAR(50)</td>
                    <td><span class="badge badge-info">No</span></td>
                    <td>Código de usuario PDV</td>
                </tr>
                <tr>
                    <td><code>fecha_creacion</code></td>
                    <td>DATETIME</td>
                    <td><span class="badge badge-ok">Auto</span></td>
                    <td>Fecha de registro</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-plug"></i> Integraciones</h2>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-link"></i> Módulos Relacionados</h4>
            <ul>
                <li><strong>Cotizaciones:</strong> Vinculación cliente-cotización</li>
                <li><strong>Pólizas:</strong> Asegurado en pólizas emitidas</li>
                <li><strong>Fianzas:</strong> Cliente de fianzas</li>
                <li><strong>Pagos:</strong> Historial de pagos del cliente</li>
                <li><strong>Comisiones:</strong> Agentes y supervisores comerciales</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-code"></i> APIs Utilizadas</h4>
            <ul>
                <li><code>GET /clientes.php?action=listar</code> - Listar clientes</li>
                <li><code>POST /clientes.php?action=crear</code> - Crear cliente</li>
                <li><code>PUT /clientes.php?action=editar/{id}</code> - Actualizar cliente</li>
                <li><code>DELETE /clientes.php?action=eliminar/{id}</code> - Eliminar cliente</li>
                <li><code>POST /clientes.php?action=importar</code> - Importación masiva</li>
            </ul>
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
                <tr>
                    <td><strong>R4 - Auditoría</strong></td>
                    <td><span class="badge badge-ok">Cumple</span></td>
                    <td>Registro de actividad en tabla auditoria_lineal</td>
                </tr>
                <tr>
                    <td><strong>R9 - Accesibilidad</strong></td>
                    <td><span class="badge badge-ok">Cumple</span></td>
                    <td>Labels ARIA, navegación por teclado</td>
                </tr>
                <tr>
                    <td><strong>R5 - Datos sensibles</strong></td>
                    <td><span class="badge badge-ok">Cumple</span></td>
                    <td>Validación y sanitización de inputs</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="footer-doc">
        <p><strong><?php echo $platform_name; ?></strong> - Módulo de Clientes | Documentación Técnica</p>
        <p>Clasificación: <strong>INTERNO</strong> | Versión: 1.0</p>
    </div>
</div>

<!-- DOCUMENTO 2: AUDITORÍA LINEAL -->
<div class="documento" id="documento2">
    <div class="doc-header">
        <h1><i class="fa-solid fa-clipboard-list"></i> Auditoría Lineal NOFTRAB v4.0 - Trazabilidad Inmutable</h1>
        <div class="meta">
            <span><i class="fa-solid fa-building"></i> <?php echo $platform_name; ?></span>
            <span><i class="fa-solid fa-shield-halved"></i> Compliance</span>
            <span><i class="fa-solid fa-calendar"></i> <?php echo date('d/m/Y'); ?></span>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-circle-info"></i> Descripción General</h2>
        <p>La Bitácora de Auditoría Lineal es un sistema inmutable de trazabilidad que registra cronológicamente todas las operaciones realizadas en la plataforma. Cumple con la norma NOFTRAB v4.0 y proporciona un historial completo e inalterable de eventos para fines de auditoría, cumplimiento regulatorio y trazabilidad forense.</p>
        
        <div class="status-grid">
            <div class="status-card cumple">
                <div class="label">Norma</div>
                <div class="value">NOFTRAB v4.0</div>
                <div class="desc">Trazabilidad Lineal Inmutable</div>
            </div>
            <div class="status-card cumple">
                <div class="label">Alcance</div>
                <div class="value">6 módulos</div>
                <div class="desc">Cotizaciones, Pólizas, Fianzas, Pagos, Clientes, Usuarios</div>
            </div>
            <div class="status-card cumple">
                <div class="label">Búsqueda</div>
                <div class="value">Universal</div>
                <div class="desc">Búsqueda inteligente multi-campo</div>
            </div>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-gears"></i> Funcionalidades Principales</h2>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-magnifying-glass"></i> Búsqueda Inteligente Universal</h4>
            <ul>
                <li><strong>Búsqueda universal:</strong> Un solo campo de búsqueda que rastrea todos los identificadores</li>
                <li><strong>Identificadores soportados:</strong>
                    <ul>
                        <li>Número de Póliza (ej: POL-2026-1234)</li>
                        <li>Número de Cotización (ej: SL-2026-5678, FZ-2026-9012)</li>
                        <li>Cédula/RNC del cliente (ej: 000-0000000-0)</li>
                        <li>Número de Recibo/Pago</li>
                        <li>NCF (Comprobante Fiscal: B02-00000001)</li>
                        <li>ID de Usuario o username</li>
                    </ul>
                </li>
                <li><strong>Búsqueda difusa:</strong> Coincidencias parciales y tolerancia a errores tipográficos</li>
                <li><strong>Resultados en tiempo real:</strong> Filtrado instantáneo mientras escribe</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-filter"></i> Filtrado por Módulo</h4>
            <ul>
                <li><strong>Cotizaciones:</strong> Todas las operaciones de generación, edición y eliminación de cotizaciones</li>
                <li><strong>Pólizas:</strong> Emisión, renovación, cancelación y modificaciones de pólizas</li>
                <li><strong>Fianzas:</strong> Cotización y emisión de fianzas</li>
                <li><strong>Pagos / Recibos:</strong> Registro de pagos, generación de recibos</li>
                <li><strong>Clientes:</strong> Creación, actualización y eliminación de clientes</li>
                <li><strong>Usuarios / Operadores:</strong> Gestión de usuarios y permisos</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-clock-rotate-left"></i> Trazabilidad Cronológica</h4>
            <ul>
                <li><strong>Orden cronológico estricto:</strong> Eventos ordenados por fecha/hora de ocurrencia</li>
                <li><strong>Timestamp preciso:</strong> Registro con precisión de segundos (YYYY-MM-DD HH:MM:SS)</li>
                <li><strong>Historial completo:</strong> Sin límite de tiempo - todos los eventos se conservan</li>
                <li><strong>Inmutabilidad:</strong> Los registros no pueden ser modificados ni eliminados</li>
                <li><strong>Forense:</strong> Estado anterior y posterior almacenados en formato forense</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-file-pdf"></i> Reporte e Impresión</h4>
            <ul>
                <li><strong>Imprimir Reporte:</strong> Generación de informe físico de auditoría</li>
                <li><strong>Formato profesional:</strong> Encabezado corporativo, numeración de páginas</li>
                <li><strong>Filtros aplicados:</strong> El reporte incluye los criterios de búsqueda/filtrado</li>
                <li><strong>Firma digital:</strong> Hash de verificación de integridad del reporte</li>
            </ul>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-database"></i> Estructura de Datos - Tabla: auditoria_lineal</h2>
        
        <table>
            <thead>
                <tr>
                    <th>Campo</th>
                    <th>Tipo</th>
                    <th>Descripción</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>id</code></td>
                    <td>BIGINT AUTO_INCREMENT</td>
                    <td>ID único incremental (inmutable)</td>
                </tr>
                <tr>
                    <td><code>fecha_hora</code></td>
                    <td>DATETIME</td>
                    <td>Timestamp del evento (precisión segundos)</td>
                </tr>
                <tr>
                    <td><code>usuario_id</code></td>
                    <td>INT</td>
                    <td>ID del usuario que realizó la acción</td>
                </tr>
                <tr>
                    <td><code>modulo</code></td>
                    <td>VARCHAR(50)</td>
                    <td>Módulo origen (Cotizaciones, Pólizas, etc.)</td>
                </tr>
                <tr>
                    <td><code>accion</code></td>
                    <td>VARCHAR(100)</td>
                    <td>Tipo de acción (CREAR, EDITAR, ELIMINAR, CONSULTAR)</td>
                </tr>
                <tr>
                    <td><code>registro_afectado</code></td>
                    <td>VARCHAR(100)</td>
                    <td>ID o número del registro afectado</td>
                </tr>
                <tr>
                    <td><code>estado_anterior</code></td>
                    <td>JSON</td>
                    <td>Datos antes de la modificación (forense)</td>
                </tr>
                <tr>
                    <td><code>estado_nuevo</code></td>
                    <td>JSON</td>
                    <td>Datos después de la modificación (forense)</td>
                </tr>
                <tr>
                    <td><code>descripcion</code></td>
                    <td>TEXT</td>
                    <td>Descripción detallada de la operación</td>
                </tr>
                <tr>
                    <td><code>ip_origen</code></td>
                    <td>VARCHAR(45)</td>
                    <td>Dirección IP del usuario</td>
                </tr>
                <tr>
                    <td><code>user_agent</code></td>
                    <td>TEXT</td>
                    <td>Navegador/dispositivo utilizado</td>
                </tr>
                <tr>
                    <td><code>hash_integridad</code></td>
                    <td>VARCHAR(64)</td>
                    <td>SHA-256 del registro (verificación inmutabilidad)</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-link"></i> Flujo de Trazabilidad</h2>
        
        <div class="code-block">
1. Usuario realiza acción en la plataforma
   ↓
2. Sistema captura evento (pre-operación)
   ↓
3. Registra estado anterior (si aplica)
   ↓
4. Ejecuta operación en base de datos
   ↓
5. Registra estado posterior (si aplica)
   ↓
6. Genera hash SHA-256 del registro completo
   ↓
7. Inserta registro inmutable en auditoria_lineal
   ↓
8. Retorna confirmación al usuario
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-shield-halved"></i> Cumplimiento Normativo</h2>
        
        <table>
            <thead>
                <tr>
                    <th>Norma</th>
                    <th>Requisito</th>
                    <th>Cumplimiento</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>NOFTRAB R4</strong></td>
                    <td>Auditoría de accesos y operaciones</td>
                    <td><span class="badge badge-ok">100%</span></td>
                </tr>
                <tr>
                    <td><strong>ISO 27001 A.12.4.1</strong></td>
                    <td>Registro de eventos (logs)</td>
                    <td><span class="badge badge-ok">100%</span></td>
                </tr>
                <tr>
                    <td><strong>DGII</strong></td>
                    <td>Trazabilidad de documentos fiscales</td>
                    <td><span class="badge badge-ok">100%</span></td>
                </tr>
                <tr>
                    <td><strong>ISO 9001 8.5.1</strong></td>
                    <td>Control de cambios</td>
                    <td><span class="badge badge-ok">100%</span></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-circle-check"></i> Casos de Uso</h2>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-search"></i> Auditoría de Póliza</h4>
            <p><strong>Escenario:</strong> Un cliente pregunta sobre modificaciones realizadas a su póliza POL-2026-1234.</p>
            <p><strong>Proceso:</strong></p>
            <ol>
                <li>Ingresa "POL-2026-1234" en el campo de búsqueda universal</li>
                <li>Selecciona filtro "Pólizas" (opcional)</li>
                <li>El sistema muestra cronológicamente:
                    <ul>
                        <li>Creación inicial de la póliza</li>
                        <li>Modificaciones de endoso</li>
                        <li>Renovaciones</li>
                        <li>Pagos registrados</li>
                    </ul>
                </li>
                <li>Cada registro muestra: fecha/hora, usuario, acción, datos anteriores y posteriores</li>
            </ol>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-user-check"></i> Investigación de Usuario</h4>
            <p><strong>Escenario:</strong> Auditoría de actividades del usuario "jdoe" en el último mes.</p>
            <p><strong>Proceso:</strong></p>
            <ol>
                <li>Busca "jdoe" en el campo universal</li>
                <li>Filtra por fecha (últimos 30 días)</li>
                <li>Obtiene todas las operaciones realizadas por ese usuario</li>
                <li>Exporta a PDF para informe de auditoría</li>
            </ol>
        </div>
    </div>

    <div class="footer-doc">
        <p><strong><?php echo $platform_name; ?></strong> - Auditoría Lineal NOFTRAB v4.0 | Documentación Técnica</p>
        <p>Clasificación: <strong>INTERNO</strong> | Versión: 1.0</p>
    </div>
</div>

<!-- DOCUMENTO 3: CENTRO FINANCIERO -->
<div class="documento" id="documento3">
    <div class="doc-header">
        <h1><i class="fa-solid fa-building-columns"></i> Centro Financiero - Core Contable y Fiscal</h1>
        <div class="meta">
            <span><i class="fa-solid fa-building"></i> <?php echo $platform_name; ?></span>
            <span><i class="fa-solid fa-calculator"></i> Motor Contable</span>
            <span><i class="fa-solid fa-calendar"></i> <?php echo date('d/m/Y'); ?></span>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-circle-info"></i> Descripción General</h2>
        <p>El Centro Financiero es el núcleo contable y fiscal de la plataforma. Integra el core contable SIS (Sistema Integrado de Gestión Contable), la gestión de NCF (Números de Comprobante Fiscal) para cumplimiento DGII, y la generación de reportes financieros oficiales. Automatiza asientos contables y proporciona trazabilidad completa de operaciones financieras.</p>
        
        <div class="status-grid">
            <div class="status-card cumple">
                <div class="label">Motor Contable</div>
                <div class="value">SIS</div>
                <div class="desc">Sistema Integrado SIS</div>
            </div>
            <div class="status-card cumple">
                <div class="label">NCF</div>
                <div class="value">DGII</div>
                <div class="desc">Comprobantes Fiscales</div>
            </div>
            <div class="status-card cumple">
                <div class="label">Catálogo</div>
                <div class="value">6 niveles</div>
                <div class="desc">Estructura jerárquica</div>
            </div>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-gears"></i> Funcionalidades Principales</h2>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-chart-pie"></i> Dashboard Financiero</h4>
            <ul>
                <li><strong>Disponibilidad (Bancos):</strong> Saldo disponible en cuentas bancarias con indicador de progreso mensual</li>
                <li><strong>Primas por Cobrar:</strong> Total de primas pendientes de cobro con estado de auditoría</li>
                <li><strong>Comisiones Ganadas:</strong> Comisiones acumuladas del ciclo actual</li>
                <li><strong>Obligaciones DGII:</strong> Monto pendiente de pago a DGII con próximo vencimiento (día 20)</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-book"></i> Catálogo de Cuentas SIS</h4>
            <ul>
                <li><strong>Estructura de 6 niveles:</strong> Jerarquía contable completa (ej: 1.1.1.01.001.000)</li>
                <li><strong>Tipos de cuenta:</strong> ACTIVO, PASIVO, PATRIMONIO, INGRESO, EGRESO, ORDEN</li>
                <li><strong>Naturaleza:</strong> DEUDORA o ACREEDORA</li>
                <li><strong>Nivel de detalle:</strong> Cuentas de detalle (permiten asientos) vs cuentas de agrupación</li>
                <li><strong>Estado:</strong> Activa / Inactiva</li>
                <li><strong>Justificación NOFTRAB:</strong> Cada creación/modificación requiere justificación mínima de 15 caracteres</li>
                <li><strong>Auditoría inmutable:</strong> Estado anterior y posterior almacenados en formato forense</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-clock-rotate-left"></i> Libro Diario</h4>
            <ul>
                <li><strong>Registro cronológico:</strong> Todos los asientos contables ordenados por fecha</li>
                <li><strong>Columnas:</strong> Fecha, Asiento #, Descripción, Débito, Crédito, Estado, Acción</li>
                <li><strong>Exportación PDF:</strong> Generación de libro diario formal</li>
                <li><strong>Filtros:</strong> Por fecha, tipo de asiento, módulo origen</li>
                <li><strong>Asientos automáticos:</strong> Generados por MotorContable desde operaciones (pólizas, pagos, comisiones)</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-book-open"></i> Libro Mayor</h4>
            <ul>
                <li><strong>Historial por cuenta:</strong> Movimientos detallados de cada cuenta del catálogo</li>
                <li><strong>Saldo actual:</strong> Saldo debit/credit por cuenta</li>
                <li><strong>Consultas:</strong> Búsqueda por período, número de asiento, descripción</li>
                <li><strong>Conciliación:</strong> Cruce con libro diario</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-scale-balanced"></i> Balances</h4>
            <ul>
                <li><strong>Balance de Comprobación:</strong> Sumas iguales (débito = crédito)</li>
                <li><strong>Balance de Situación:</strong> Activos = Pasivos + Patrimonio</li>
                <li><strong>Formatos SIS/DGII:</strong> Cumplimiento de formatos oficiales</li>
                <li><strong>Exportación:</strong> PDF, Excel</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-receipt"></i> Monitor de Secuencias NCF (DGII)</h4>
            <ul>
                <li><strong>Tipos de comprobante:</strong>
                    <ul>
                        <li><strong>B01:</strong> Factura de Crédito Fiscal</li>
                        <li><strong>B02:</strong> Factura de Consumo</li>
                        <li><strong>B12:</strong> Comprobante de Ingresos</li>
                        <li><strong>B14:</strong> Comprobante de Egresos</li>
                        <li><strong>B15:</strong> Comprobante Gastos Menores</li>
                    </ul>
                </li>
                <li><strong>Secuenciador automático:</strong> Generación correlativa de NCF</li>
                <li><strong>Monitor de secuencias:</strong> Visualización del próximo número disponible por tipo</li>
                <li><strong>Ajuste manual:</strong> Capacidad de ajustar secuencia con justificación NOFTRAB</li>
                <li><strong>Log de auditoría NCF:</strong> Registro inmutable de últimos 20 NCF emitidos</li>
                <li><strong>Reporte NCF:</strong> Impresión de secuencias activas y log completo</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-robot"></i> Últimos Asientos Automáticos</h4>
            <ul>
                <li><strong>Visualización:</strong> Tabla con últimos asientos generados automáticamente</li>
                <li><strong>Columnas:</strong> Fecha, Asiento, Descripción, Monto</li>
                <li><strong>Origen:</strong> Indica módulo que generó el asiento (Cotizaciones, Pólizas, Pagos, etc.)</li>
                <li><strong>Detalle:</strong> Click para ver desglose completo del asiento</li>
            </ul>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-database"></i> Estructura de Datos Principal</h2>
        
        <h3>Tabla: catalogo_cuentas</h3>
        <table>
            <thead>
                <tr>
                    <th>Campo</th>
                    <th>Tipo</th>
                    <th>Descripción</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>id</code></td>
                    <td>INT AUTO_INCREMENT</td>
                    <td>ID único</td>
                </tr>
                <tr>
                    <td><code>codigo</code></td>
                    <td>VARCHAR(20)</td>
                    <td>Código de cuenta (ej: 1.1.1.01.001.000)</td>
                </tr>
                <tr>
                    <td><code>nombre</code></td>
                    <td>VARCHAR(200)</td>
                    <td>Nombre de la cuenta</td>
                </tr>
                <tr>
                    <td><code>tipo</code></td>
                    <td>ENUM(...)</td>
                    <td>ACTIVO, PASIVO, PATRIMONIO, INGRESO, EGRESO, ORDEN</td>
                </tr>
                <tr>
                    <td><code>naturaleza</code></td>
                    <td>ENUM('DEUDORA','ACREEDORA')</td>
                    <td>Naturaleza contable</td>
                </tr>
                <tr>
                    <td><code>es_detalle</code></td>
                    <td>BOOLEAN</td>
                    <td>Permite registrar asientos (1) o es agrupadora (0)</td>
                </tr>
                <tr>
                    <td><code>estado</code></td>
                    <td>ENUM('ACTIVA','INACTIVA')</td>
                    <td>Estado de la cuenta</td>
                </tr>
                <tr>
                    <td><code>nivel</code></td>
                    <td>INT (1-6)</td>
                    <td>Nivel jerárquico en el catálogo</td>
                </tr>
                <tr>
                    <td><code>cuenta_padre_id</code></td>
                    <td>INT</td>
                    <td>Referencia a cuenta padre (NULL si es nivel 1)</td>
                </tr>
            </tbody>
        </table>

        <h3>Tabla: asientos_contables</h3>
        <table>
            <thead>
                <tr>
                    <th>Campo</th>
                    <th>Tipo</th>
                    <th>Descripción</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>id</code></td>
                    <td>BIGINT AUTO_INCREMENT</td>
                    <td>ID único del asiento</td>
                </tr>
                <tr>
                    <td><code>numero_asiento</code></td>
                    <td>VARCHAR(20)</td>
                    <td>Número correlativo (ej: AS-2026-00001)</td>
                </tr>
                <tr>
                    <td><code>fecha</code></td>
                    <td>DATE</td>
                    <td>Fecha del asiento</td>
                </tr>
                <tr>
                    <td><code>descripcion</code></td>
                    <td>TEXT</td>
                    <td>Descripción del asiento</td>
                </tr>
                <tr>
                    <td><code>modulo_origen</code></td>
                    <td>VARCHAR(50)</td>
                    <td>Módulo que generó el asiento (Cotizaciones, Pólizas, etc.)</td>
                </tr>
                <tr>
                    <td><code>registro_referencia</code></td>
                    <td>VARCHAR(100)</td>
                    <td>ID del registro que originó el asiento</td>
                </tr>
                <tr>
                    <td><code>estado</code></td>
                    <td>ENUM('BORRADOR','CONTABILIZADO','ANULADO')</td>
                    <td>Estado del asiento</td>
                </tr>
                <tr>
                    <td><code>total_debito</code></td>
                    <td>DECIMAL(15,2)</td>
                    <td>Total débito del asiento</td>
                </tr>
                <tr>
                    <td><code>total_credito</code></td>
                    <td>DECIMAL(15,2)</td>
                    <td>Total crédito del asiento</td>
                </tr>
            </tbody>
        </table>

        <h3>Tabla: asientos_detalle</h3>
        <table>
            <thead>
                <tr>
                    <th>Campo</th>
                    <th>Tipo</th>
                    <th>Descripción</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>id</code></td>
                    <td>BIGINT AUTO_INCREMENT</td>
                    <td>ID único</td>
                </tr>
                <tr>
                    <td><code>asiento_id</code></td>
                    <td>BIGINT</td>
                    <td>Referencia a asientos_contables</td>
                </tr>
                <tr>
                    <td><code>cuenta_id</code></td>
                    <td>INT</td>
                    <td>Referencia a catalogo_cuentas</td>
                </tr>
                <tr>
                    <td><code>debito</code></td>
                    <td>DECIMAL(15,2)</td>
                    <td>Monto al débito</td>
                </tr>
                <tr>
                    <td><code>credito</code></td>
                    <td>DECIMAL(15,2)</td>
                    <td>Monto al crédito</td>
                </tr>
                <tr>
                    <td><code>descripcion</code></td>
                    <td>VARCHAR(255)</td>
                    <td>Descripción del movimiento</td>
                </tr>
            </tbody>
        </table>

        <h3>Tabla: ncf_secuencias</h3>
        <table>
            <thead>
                <tr>
                    <th>Campo</th>
                    <th>Tipo</th>
                    <th>Descripción</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>id</code></td>
                    <td>INT AUTO_INCREMENT</td>
                    <td>ID único</td>
                </tr>
                <tr>
                    <td><code>tipo_comprobante</code></td>
                    <td>VARCHAR(3)</td>
                    <td>B01, B02, B12, B14, B15</td>
                </tr>
                <tr>
                    <td><code>secuencia_actual</code></td>
                    <td>VARCHAR(8)</td>
                    <td>Último número utilizado (ej: 00000001)</td>
                </tr>
                <tr>
                    <td><code>proximo_numero</code></td>
                    <td>INT</td>
                    <td>Próximo número a utilizar</td>
                </tr>
                <tr>
                    <td><code>fecha_ultimo_uso</code></td>
                    <td>DATETIME</td>
                    <td>Fecha del último NCF emitido</td>
                </tr>
            </tbody>
        </table>

        <h3>Tabla: ncf_log_auditoria</h3>
        <table>
            <thead>
                <tr>
                    <th>Campo</th>
                    <th>Tipo</th>
                    <th>Descripción</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>id</code></td>
                    <td>BIGINT AUTO_INCREMENT</td>
                    <td>ID único</td>
                </tr>
                <tr>
                    <td><code>fecha_hora</code></td>
                    <td>DATETIME</td>
                    <td>Fecha/hora de emisión</td>
                </tr>
                <tr>
                    <td><code>tipo</code></td>
                    <td>VARCHAR(3)</td>
                    <td>Tipo de comprobante</td>
                </tr>
                <tr>
                    <td><code>ncf</code></td>
                    <td>VARCHAR(13)</td>
                    <td>NCF completo (ej: B02000000001)</td>
                </tr>
                <tr>
                    <td><code>modulo_origen</code></td>
                    <td>VARCHAR(50)</td>
                    <td>Módulo que solicitó el NCF</td>
                </tr>
                <tr>
                    <td><code>registro_id</code></td>
                    <td>VARCHAR(100)</td>
                    <td>ID del registro asociado</td>
                </tr>
                <tr>
                    <td><code>usuario_id</code></td>
                    <td>INT</td>
                    <td>Usuario que generó el NCF</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-plug"></i> Integración Motor Contable</h2>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-robot"></i> Asientos Automáticos</h4>
            <p>El MotorContable genera automáticamente asientos contables desde operaciones de negocio:</p>
            <ul>
                <li><strong>Emisión de Póliza:</strong>
                    <ul>
                        <li>Débito: Cuentas por Cobrar (Primas)</li>
                        <li>Crédito: Ingresos por Primas</li>
                        <li>Crédito: ITBIS por Pagar</li>
                        <li>Crédito: ISC por Pagar</li>
                    </ul>
                </li>
                <li><strong>Registro de Pago:</strong>
                    <ul>
                        <li>Débito: Banco / Caja</li>
                        <li>Crédito: Cuentas por Cobrar</li>
                    </ul>
                </li>
                <li><strong>Generación de Comisión:</strong>
                    <ul>
                        <li>Débito: Gastos de Comisiones</li>
                        <li>Crédito: Comisiones por Pagar (Agente)</li>
                    </ul>
                </li>
                <li><strong>Cotización con NCF:</strong>
                    <ul>
                        <li>Generación de NCF B02</li>
                        <li>Registro en log de auditoría</li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-shield-halved"></i> Cumplimiento NOFTRAB y DGII</h2>
        
        <table>
            <thead>
                <tr>
                    <th>Requisito</th>
                    <th>Cumplimiento</th>
                    <th>Implementación</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>DGII - NCF</strong></td>
                    <td><span class="badge badge-ok">100%</span></td>
                    <td>Secuenciador automático, log de auditoría, formatos oficiales</td>
                </tr>
                <tr>
                    <td><strong>NOFTRAB R6</strong></td>
                    <td><span class="badge badge-ok">100%</span></td>
                    <td>Generación de NCF fiscal integrado</td>
                </tr>
                <tr>
                    <td><strong>NOFTRAB R7</strong></td>
                    <td><span class="badge badge-ok">100%</span></td>
                    <td>Integración contable automática con MotorContable</td>
                </tr>
                <tr>
                    <td><strong>ISO 27001 A.12.4.1</strong></td>
                    <td><span class="badge badge-ok">100%</span></td>
                    <td>Logs de auditoría NCF inmutables</td>
                </tr>
                <tr>
                    <td><strong>SIS - Estructura 6 niveles</strong></td>
                    <td><span class="badge badge-ok">100%</span></td>
                    <td>Catálogo de cuentas jerárquico completo</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="footer-doc">
        <p><strong><?php echo $platform_name; ?></strong> - Centro Financiero | Documentación Técnica</p>
        <p>Clasificación: <strong>INTERNO</strong> | Versión: 1.0</p>
    </div>
</div>

<!-- DOCUMENTO 4: COMISIONES -->
<div class="documento" id="documento4">
    <div class="doc-header">
        <h1><i class="fa-solid fa-coins"></i> Panel de Comisiones - Gestión y Proyección</h1>
        <div class="meta">
            <span><i class="fa-solid fa-building"></i> <?php echo $platform_name; ?></span>
            <span><i class="fa-solid fa-chart-line"></i> Revenue Management</span>
            <span><i class="fa-solid fa-calendar"></i> <?php echo date('d/m/Y'); ?></span>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-circle-info"></i> Descripción General</h2>
        <p>El Panel de Comisiones es el sistema integral de gestión de comisiones para agentes y supervisores comerciales. Proporciona visualización en tiempo real de comisiones cobradas, cuentas por cobrar (CxC), proyección mensual, y trazabilidad completa de pólizas emitidas con su respectiva comisión. Incluye auditoría técnica NOFTRAB con código QR de verificación.</p>
        
        <div class="status-grid">
            <div class="status-card cumple">
                <div class="label">Módulo</div>
                <div class="value">Comisiones</div>
                <div class="desc">Gestión Revenue</div>
            </div>
            <div class="status-card cumple">
                <div class="label">Métricas</div>
                <div class="value">4 KPIs</div>
                <div class="desc">Cobradas, CxC, Proyección, Pólizas</div>
            </div>
            <div class="status-card cumple">
                <div class="label">Auditoría</div>
                <div class="value">QR + NOFTRAB</div>
                <div class="desc">Verificación inmutable</div>
            </div>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-gears"></i> Funcionalidades Principales</h2>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-circle-notch"></i> Dashboard de Métricas</h4>
            <ul>
                <li><strong>💰 Comisiones Cobradas:</strong> Total de comisiones ya pagadas con número de pólizas incluidas</li>
                <li><strong>⏳ En Tránsito (CxC):</strong> Comisiones pendientes de cobro con conteo de pólizas</li>
                <li><strong>📈 Proyección Mensual:</strong> Proyección de comisiones del mes con porcentaje de progreso del período</li>
                <li><strong>📋 Pólizas Emitidas:</strong> Total de pólizas generadas en el período seleccionado</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-filter"></i> Filtros y Períodos</h4>
            <ul>
                <li><strong>Período:</strong> Selector de meses (Enero - Diciembre)</li>
                <li><strong>Año:</strong> Selector de año fiscal</li>
                <li><strong>Agente:</strong> Filtro por agente específico o "Todos los Agentes"</li>
                <li><strong>Actualización:</strong> Botón "Actualizar" para refrescar datos en tiempo real</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-file-invoice-dollar"></i> Pólizas Emitidas — Prima Neta y Comisión</h4>
            <ul>
                <li><strong>Tabla principal:</strong> Listado completo de pólizas con detalle de comisión</li>
                <li><strong>Columnas:</strong>
                    <ul>
                        <li>N° Póliza (con link a detalle)</li>
                        <li>Tipo de seguro/fianza</li>
                        <li>Asegurado (nombre del cliente)</li>
                        <li>Prima Neta (monto base)</li>
                        <li>% Comisión (porcentaje aplicado)</li>
                        <li>Comisión (monto calculado)</li>
                        <li>Estado de Pago (Cobrado / Pendiente / En Tránsito)</li>
                        <li>Agente (responsable de la comisión)</li>
                        <li>Acciones (Ver detalle, Imprimir)</li>
                    </ul>
                </li>
                <li><strong>Filtros de estado:</strong> Todos los estados / Cobrado / Pendiente / En Tránsito</li>
                <li><strong>Exportación:</strong> PDF, Excel, CSV</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-hourglass-half"></i> Comisiones en Tránsito — Cuentas por Cobrar</h4>
            <ul>
                <li><strong>Tabla CxC:</strong> Comisiones pendientes de cobro con antigüedad</li>
                <li><strong>Columnas:</strong>
                    <ul>
                        <li>N° Póliza</li>
                        <li>Asegurado</li>
                        <li>Monto Pendiente (total de la póliza)</li>
                        <li>Comisión en Tránsito (monto de comisión pendiente)</li>
                        <li>Días Pendiente (antigüedad de la deuda)</li>
                        <li>Vencimiento (fecha límite de pago)</li>
                        <li>Agente</li>
                        <li>Acción (Gestionar cobro, Recordatorio)</li>
                    </ul>
                </li>
                <li><strong>Alertas:</strong> Colores por antigüedad (verde: &lt;30 días, amarillo: 30-60 días, rojo: &gt;60 días)</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-chart-line"></i> Proyección del Mes Actual</h4>
            <ul>
                <li><strong>Progreso del período:</strong> Barra de progreso con porcentaje completado del mes</li>
                <li><strong>✅ Cobrado:</strong> Monto ya cobrado en el mes</li>
                <li><strong>⏳ Pendiente CxC:</strong> Monto pendiente de cobro</li>
                <li><strong>📈 Total Proyectado:</strong> Proyección total si se cobran todas las CxC</li>
                <li><strong>Insight:</strong> "Si se cobran todas las CxC del mes, recibirías RD$ X.XX en comisiones"</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-file-circle-check"></i> Detalle de Póliza — Comisión</h4>
            <ul>
                <li><strong>Modal de detalle:</strong> Información completa de la póliza y su comisión</li>
                <li><strong>Datos incluidos:</strong>
                    <ul>
                        <li>N° Póliza, Tipo de Seguro, Asegurado</li>
                        <li>Agente responsable</li>
                        <li>Prima Neta, % Comisión, Monto Comisión</li>
                        <li>Estado de Pago</li>
                        <li>Vigencia (Inicio - Fin)</li>
                    </ul>
                </li>
                <li><strong>Historial de Pagos:</strong> Tabla con todos los pagos realizados
                    <ul>
                        <li>Fecha de Pago</li>
                        <li>Método (Efectivo, Transferencia, Cheque)</li>
                        <li>Monto</li>
                        <li>Estado del Pago</li>
                    </ul>
                </li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-qrcode"></i> Código QR de Verificación</h4>
            <ul>
                <li><strong>QR único:</strong> Código QR generado para cada comisión/póliza</li>
                <li><strong>Verificación:</strong> Escaneo para validar autenticidad del documento</li>
                <li><strong>Información codificada:</strong> Hash de verificación + ID de póliza + timestamp</li>
                <li><strong>Integridad:</strong> Garantiza que el documento no ha sido alterado</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-clipboard-check"></i> Ficha de Auditoría Técnica (NOFTRAB)</h4>
            <ul>
                <li><strong>Auditoría en tiempo real:</strong> Consulta a base de datos con validación forense</li>
                <li><strong>Campos auditados:</strong>
                    <ul>
                        <li>Estado General de la póliza/comisión</li>
                        <li>Validación Técnica (integridad de datos)</li>
                        <li>Vigencia Temporal (fechas de validez)</li>
                        <li>Compañía Aseguradora</li>
                        <li>Vehículo Registrado (si aplica)</li>
                        <li>Matrícula / Placa</li>
                        <li>Prima Total Anual</li>
                        <li>Fecha de Auditoría (timestamp)</li>
                    </ul>
                </li>
                <li><strong>Imprimir Ficha:</strong> Generación de informe técnico de auditoría</li>
                <li><strong>Formato forense:</strong> Estado anterior y posterior almacenados</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-envelope"></i> Enviar Reporte por Email</h4>
            <ul>
                <li><strong>Email de destino:</strong> Campo para ingresar correo del destinatario</li>
                <li><strong>Secciones seleccionables:</strong>
                    <ul>
                        <li>📋 Sección 1: Pólizas Emitidas y Comisiones</li>
                        <li>⏳ Sección 2: Cuentas por Cobrar</li>
                        <li>📈 Sección 3: Proyección Mensual</li>
                    </ul>
                </li>
                <li><strong>Preview:</strong> Vista previa del período seleccionado</li>
                <li><strong>Formato:</strong> PDF profesional con branding corporativo</li>
                <li><strong>Adjuntos:</strong> Opción de incluir Excel/CSV adicionales</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-file-export"></i> Exportación Multi-Formato</h4>
            <ul>
                <li><strong>Exportar PDF:</strong> Informe profesional con gráficos y tablas</li>
                <li><strong>Enviar por Email:</strong> Envío directo del PDF generado</li>
                <li><strong>Imprimir:</strong> Impresión directa desde navegador</li>
                <li><strong>Excel (.xlsx):</strong> Hoja de cálculo con fórmulas y formato</li>
                <li><strong>CSV:</strong> Texto plano para importación a otros sistemas</li>
            </ul>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-database"></i> Estructura de Datos - Tablas Principales</h2>
        
        <h3>Tabla: comisiones</h3>
        <table>
            <thead>
                <tr>
                    <th>Campo</th>
                    <th>Tipo</th>
                    <th>Descripción</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>id</code></td>
                    <td>BIGINT AUTO_INCREMENT</td>
                    <td>ID único de comisión</td>
                </tr>
                <tr>
                    <td><code>poliza_id</code></td>
                    <td>BIGINT</td>
                    <td>Referencia a póliza</td>
                </tr>
                <tr>
                    <td><code>agente_id</code></td>
                    <td>INT</td>
                    <td>Referencia a usuario (agente)</td>
                </tr>
                <tr>
                    <td><code>porcentaje_comision</code></td>
                    <td>DECIMAL(5,2)</td>
                    <td>Porcentaje de comisión aplicado</td>
                </tr>
                <tr>
                    <td><code>monto_comision</code></td>
                    <td>DECIMAL(15,2)</td>
                    <td>Monto total de comisión</td>
                </tr>
                <tr>
                    <td><code>estado_pago</code></td>
                    <td>ENUM('COBRADO','PENDIENTE','EN_TRANSITO')</td>
                    <td>Estado del pago de comisión</td>
                </tr>
                <tr>
                    <td><code>fecha_emision</code></td>
                    <td>DATETIME</td>
                    <td>Fecha de generación de comisión</td>
                </tr>
                <tr>
                    <td><code>fecha_pago</code></td>
                    <td>DATETIME</td>
                    <td>Fecha de pago (NULL si pendiente)</td>
                </tr>
                <tr>
                    <td><code>periodo</code></td>
                    <td>DATE</td>
                    <td>Período fiscal (primer día del mes)</td>
                </tr>
            </tbody>
        </table>

        <h3>Tabla: cuentas_por_cobrar</h3>
        <table>
            <thead>
                <tr>
                    <th>Campo</th>
                    <th>Tipo</th>
                    <th>Descripción</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>id</code></td>
                    <td>BIGINT AUTO_INCREMENT</td>
                    <td>ID único</td>
                </tr>
                <tr>
                    <td><code>comision_id</code></td>
                    <td>BIGINT</td>
                    <td>Referencia a comisiones</td>
                </tr>
                <tr>
                    <td><code>monto_pendiente</code></td>
                    <td>DECIMAL(15,2)</td>
                    <td>Monto total pendiente</td>
                </tr>
                <tr>
                    <td><code>fecha_vencimiento</code></td>
                    <td>DATE</td>
                    <td>Fecha límite de pago</td>
                </tr>
                <tr>
                    <td><code>dias_pendiente</code></td>
                    <td>INT</td>
                    <td>Días transcurridos desde emisión</td>
                </tr>
                <tr>
                    <td><code>estado</code></td>
                    <td>ENUM('ACTIVA','COBRADA','VENCIDA')</td>
                    <td>Estado de la cuenta</td>
                </tr>
            </tbody>
        </table>

        <h3>Tabla: pagos_comisiones</h3>
        <table>
            <thead>
                <tr>
                    <th>Campo</th>
                    <th>Tipo</th>
                    <th>Descripción</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>id</code></td>
                    <td>BIGINT AUTO_INCREMENT</td>
                    <td>ID único de pago</td>
                </tr>
                <tr>
                    <td><code>comision_id</code></td>
                    <td>BIGINT</td>
                    <td>Referencia a comisión</td>
                </tr>
                <tr>
                    <td><code>fecha_pago</code></td>
                    <td>DATETIME</td>
                    <td>Fecha y hora del pago</td>
                </tr>
                <tr>
                    <td><code>metodo_pago</code></td>
                    <td>ENUM('EFECTIVO','TRANSFERENCIA','CHEQUE')</td>
                    <td>Método de pago</td>
                </tr>
                <tr>
                    <td><code>monto</code></td>
                    <td>DECIMAL(15,2)</td>
                    <td>Monto pagado</td>
                </tr>
                <tr>
                    <td><code>estado_pago</code></td>
                    <td>ENUM('CONFIRMADO','PENDIENTE','RECHAZADO')</td>
                    <td>Estado del pago</td>
                </tr>
                <tr>
                    <td><code>referencia</code></td>
                    <td>VARCHAR(100)</td>
                    <td>Número de cheque, transferencia, etc.</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-calculator"></i> Fórmulas de Cálculo</h2>
        
        <div class="code-block">
// Cálculo de Comisión
monto_comision = prima_neta * (porcentaje_comision / 100)

// Cálculo de Proyección Mensual
proyeccion_total = comisiones_cobradas + comisiones_pendientes_cxc

// Progreso del Período
dias_transcurridos = fecha_actual - primer_dia_del_mes
dias_del_mes = days_in_month(fecha_actual)
progreso_porcentaje = (dias_transcurridos / dias_del_mes) * 100

// Antigüedad de CxC
dias_pendiente = fecha_actual - fecha_emision
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
                <tr>
                    <td><strong>R4 - Auditoría</strong></td>
                    <td><span class="badge badge-ok">100%</span></td>
                    <td>Ficha de auditoría técnica con QR + logs inmutables</td>
                </tr>
                <tr>
                    <td><strong>R8 - Exportación</strong></td>
                    <td><span class="badge badge-ok">100%</span></td>
                    <td>PDF, Excel, CSV, Email con preview</td>
                </tr>
                <tr>
                    <td><strong>R9 - Accesibilidad</strong></td>
                    <td><span class="badge badge-ok">100%</span></td>
                    <td>Labels ARIA, navegación por teclado, contraste WCAG</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="footer-doc">
        <p><strong><?php echo $platform_name; ?></strong> - Panel de Comisiones | Documentación Técnica</p>
        <p>Clasificación: <strong>INTERNO</strong> | Versión: 1.0</p>
    </div>
</div>

<!-- DOCUMENTO 5: FIANZAS -->
<div class="documento" id="documento5">
    <div class="doc-header">
        <h1><i class="fa-solid fa-shield-halved"></i> Módulo de Fianzas - Wizard de Cotización y Gestión</h1>
        <div class="meta">
            <span><i class="fa-solid fa-building"></i> <?php echo $platform_name; ?></span>
            <span><i class="fa-solid fa-file-contract"></i> Surety Bonds</span>
            <span><i class="fa-solid fa-calendar"></i> <?php echo date('d/m/Y'); ?></span>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-circle-info"></i> Descripción General</h2>
        <p>El Módulo de Fianzas es un sistema completo de cotización y gestión de fianzas y garantías. Incluye un wizard interactivo de 4 pasos para cotización, registro de empresas, historial completo de fianzas, gestión de tarifarios por aseguradora, y generación de NCF fiscal. Soporta múltiples tipos de fianzas (judiciales, contractuales, aduanales, de cumplimiento, licitación, anticipo) con cálculo automático de primas según monto afianzado, plazo y tasa de la aseguradora.</p>
        
        <div class="status-grid">
            <div class="status-card cumple">
                <div class="label">Wizard</div>
                <div class="value">4 pasos</div>
                <div class="desc">Declaraciones, Cliente, Fianza, Confirmar</div>
            </div>
            <div class="status-card cumple">
                <div class="label">Tipos de Fianza</div>
                <div class="value">6+ categorías</div>
                <div class="desc">Judicial, Contractual, Aduanal, etc.</div>
            </div>
            <div class="status-card cumple">
                <div class="label">Plazos</div>
                <div class="value">7 opciones</div>
                <div class="desc">1, 3, 6, 12, 18, 24, 36 meses</div>
            </div>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-gears"></i> Funcionalidades Principales</h2>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-building"></i> Mis Empresas Registradas</h4>
            <ul>
                <li><strong>Registro de empresas:</strong> Base de datos de empresas cliente con RNC, teléfono, email</li>
                <li><strong>Nueva Empresa:</strong> Formulario rápido con campos: Razón Social*, RNC*, Teléfono, Email, Estado (Activo/Inactivo)</li>
                <li><strong>Listado:</strong> Visualización de empresas registradas con opción de editar/eliminar</li>
                <li><strong>Búsqueda:</strong> Filtrado por nombre o RNC</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-clipboard-list"></i> Mis Cotizaciones de Fianza</h4>
            <ul>
                <li><strong>Dashboard:</strong> Métricas principales:
                    <ul>
                        <li>Total Fianzas (cantidad)</li>
                        <li>Vigentes (cantidad)</li>
                        <li>Vencidas (cantidad)</li>
                        <li>Ingresos del Mes (monto)</li>
                    </ul>
                </li>
                <li><strong>Fianzas Vigentes:</strong> Listado de fianzas activas con estado actual</li>
                <li><strong>Nueva Fianza:</strong> Botón de acceso rápido al wizard</li>
                <li><strong>Exportar:</strong> PDF, Excel, CSV del listado</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-wand-magic-sparkles"></i> Wizard de Nueva Cotización (4 Pasos)</h4>
            
            <h5>Paso 1: Declaraciones</h5>
            <ul>
                <li><strong>Declaración de Veracidad:</strong> Checkbox obligatorio con texto legal completo
                    <blockquote>"Yo, el abajo firmante, declaro bajo fe de juramento que toda la información suministrada en este formulario de solicitud es verdadera, correcta y completa. Autorizo expresamente a MAS QUE FIANZAS +QF, SRL a verificar, consultar y corroborar cualquier dato..."</blockquote>
                </li>
                <li><strong>Autorización de Administración:</strong> Checkbox obligatorio
                    <blockquote>"Autorizo a MAS QUE FIANZAS +QF, SRL a administrar, procesar y ceder la información suministrada a las compañías aseguradoras..."</blockquote>
                </li>
                <li><strong>Validación:</strong> Ambos checkboxes deben estar marcados para continuar</li>
            </ul>
            
            <h5>Paso 2: Cliente</h5>
            <ul>
                <li><strong>Nombre o Razón Social:</strong> Campo obligatorio</li>
                <li><strong>Número de Teléfono:</strong> Campo obligatorio con validación de formato</li>
                <li><strong>Correo Electrónico:</strong> Campo obligatorio con validación de email</li>
                <li><strong>Autocompletado:</strong> Si la empresa ya está registrada, se autocompletan los datos</li>
            </ul>
            
            <h5>Paso 3: Fianza (Datos Técnicos)</h5>
            <ul>
                <li><strong>Nombre, Objeto o Referencia del Proyecto:</strong> Descripción de la obligación a garantizar</li>
                <li><strong>Beneficiario:</strong> Entidad beneficiaria de la fianza (obligatorio)</li>
                <li><strong>¿Es a Primer Requerimiento?:</strong> Radio buttons (Sí/No)</li>
                <li><strong>Compañía Aseguradora:</strong> Dropdown cargado dinámicamente desde API</li>
                <li><strong>Tipo de Fianza:</strong> Dropdown que se carga según aseguradora seleccionada
                    <ul>
                        <li>Judiciales (Appearance, Payment, Caución)</li>
                        <li>Contractuales (Cumplimiento, Buena Calidad, Anticipo)</li>
                        <li>Aduanales (Garantía Aduanera, Devolución, Pago)</li>
                        <li>Cumplimiento (Fiel Cumplimiento, Buena Ejecución)</li>
                        <li>Licitación (Seriedad de Oferta, Mantenimiento)</li>
                        <li>Anticipo (Reembolso, Correcta Inversión)</li>
                    </ul>
                </li>
                <li><strong>Monto del Contrato (DOP):</strong> Campo numérico obligatorio</li>
                <li><strong>% a Afianzar:</strong> Porcentaje del monto a garantizar</li>
                <li><strong>Valor a Afianzar (calculado):</strong> Campo automático = Monto × % Afianzar</li>
                <li><strong>Valor a Afianzar (DOP):</strong> Campo manual (override del calculado)</li>
                <li><strong>Cédula / RNC del Asegurado:</strong> Identificación fiscal</li>
                <li><strong>Tasa Especial Manual (%):</strong> Campo opcional para override de tarifa</li>
                <li><strong>Plazo de la Fianza:</strong> Dropdown con opciones: 1, 3, 6, 12, 18, 24, 36 meses</li>
                <li><strong>Fecha de Inicio:</strong> Date picker obligatorio</li>
                <li><strong>N° de Contrato / Licitación:</strong> Campo opcional de referencia</li>
                <li><strong>Generar NCF (B02):</strong> Checkbox para generación de comprobante fiscal</li>
                <li><strong>Observaciones:</strong> Campo de texto libre opcional</li>
            </ul>
            
            <h5>Paso 4: Confirmar</h5>
            <ul>
                <li><strong>Resumen completo:</strong> Visualización de todos los datos ingresados</li>
                <li><strong>Desglose de Prima:</strong>
                    <ul>
                        <li>Valor a Afianzar</li>
                        <li>Prima Base (calculada según tarifario)</li>
                        <li>ISC (16%)</li>
                        <li>TOTAL A PAGAR</li>
                    </ul>
                </li>
                <li><strong>Prima mínima:</strong> Alerta si se aplica prima mínima del tarifario</li>
                <li><strong>Botones de acción:</strong>
                    <ul>
                        <li>← Atrás (volver al paso 3)</li>
                        <li>Guardar Cotización (guarda en BD)</li>
                        <li>Procesar y Descargar PDF (genera PDF profesional)</li>
                    </ul>
                </li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-calculator"></i> Cálculo Automático de Prima</h4>
            <ul>
                <li><strong>API de cálculo:</strong> <code>POST /fianzas.php?action=calcular</code></li>
                <li><strong>Payload:</strong>
                    <ul>
                        <li><code>tarifario_id:</code> ID del tipo de fianza</li>
                        <li><code>monto_afianzado:</code> Valor a garantizar</li>
                        <li><code>plazo_meses:</code> Duración de la fianza</li>
                        <li><code>tasa_manual:</code> (opcional) Tasa override</li>
                    </ul>
                </li>
                <li><strong>Respuesta:</strong>
                    <ul>
                        <li><code>prima_base:</code> Prima neta calculada</li>
                        <li><code>itbis:</code> ISC (16%) calculado</li>
                        <li><code>total:</code> Total a pagar</li>
                        <li><code>prima_minima_aplicada:</code> Boolean si aplica mínima</li>
                    </ul>
                </li>
                <li><strong>Coberturas incluidas:</strong> Lista de coberturas según tipo de fianza (generada automáticamente)</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-gear"></i> Editar Tarifa (Tarifarios)</h4>
            <ul>
                <li><strong>Formulario de edición:</strong>
                    <ul>
                        <li>Tipo de Fianza (readonly)</li>
                        <li>Aseguradora (readonly)</li>
                        <li>Nueva Tasa (%): Campo numérico obligatorio</li>
                        <li>Prima Mínima (DOP): Campo numérico obligatorio</li>
                        <li>Justificación del cambio: Texto mínimo 15 caracteres (NOFTRAB)</li>
                    </ul>
                </li>
                <li><strong>Validación:</strong> Justificación obligatoria para auditoría</li>
                <li><strong>Auditoría:</strong> Registro inmutable del cambio con timestamp y usuario</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-clock-rotate-left"></i> Historial Completo de Fianzas</h4>
            <ul>
                <li><strong>Tabla de historial:</strong> Listado completo de todas las fianzas
                    <ul>
                        <li>N° Fianza</li>
                        <li>Cliente</li>
                        <li>Tipo de Fianza</li>
                        <li>Aseguradora</li>
                        <li>Monto Afianzado</li>
                        <li>Prima Total</li>
                        <li>Fecha Inicio</li>
                        <li>Fecha Fin</li>
                        <li>Estado (Cotización, Pendiente, Vigente, Vencida, Cancelada, Renovada)</li>
                        <li>Acciones (Ver, Editar, PDF, Eliminar)</li>
                    </ul>
                </li>
                <li><strong>Filtros:</strong>
                    <ul>
                        <li>Por estado: Todos / Cotización / Pendiente / Vigente / Vencida / Cancelada / Renovada</li>
                        <li>Por aseguradora: Todas / Multiseguros / Midas / Patria / Pepín</li>
                        <li>Búsqueda por texto: N°, cliente, tipo</li>
                    </ul>
                </li>
                <li><strong>Exportar:</strong> PDF, Excel (.xlsx), CSV</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-file-pdf"></i> Generación de PDF Profesional</h4>
            <ul>
                <li><strong>Formato corporativo:</strong> Logo MAS QUE FIANZAS, branding completo</li>
                <li><strong>Información incluida:</strong>
                    <ul>
                        <li>Datos de la empresa (RNC: 133-53573-4, Licencia CMS-1561-A)</li>
                        <li>Datos del cliente</li>
                        <li>Detalle de la fianza (tipo, beneficiario, monto, plazo)</li>
                        <li>Desglose de prima (base, ISC, total)</li>
                        <li>Coberturas incluidas</li>
                        <li>Declaraciones legales firmadas</li>
                        <li>NCF (si fue generado)</li>
                        <li>Código QR de verificación</li>
                    </ul>
                </li>
                <li><strong>Descarga automática:</strong> El PDF se descarga inmediatamente después de guardar</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-receipt"></i> Generación de NCF (B02)</h4>
            <ul>
                <li><strong>Checkbox opcional:</strong> "Generar NCF (Comprobante Fiscal B02)"</li>
                <li><strong>Secuenciador automático:</strong> Obtiene próximo NCF disponible de la tabla ncf_secuencias</li>
                <li><strong>Registro en log:</strong> Inserta registro en ncf_log_auditoria con:
                    <ul>
                        <li>Fecha/hora</li>
                        <li>Tipo (B02)</li>
                        <li>NCF completo</li>
                        <li>Módulo origen (Fianzas)</li>
                        <li>ID de la fianza</li>
                        <li>Usuario que generó</li>
                    </ul>
                </li>
                <li><strong>Inclusión en PDF:</strong> El NCF se imprime en el documento profesional</li>
            </ul>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-database"></i> Estructura de Datos - Tablas Principales</h2>
        
        <h3>Tabla: fianzas_empresas</h3>
        <table>
            <thead>
                <tr>
                    <th>Campo</th>
                    <th>Tipo</th>
                    <th>Descripción</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>id</code></td>
                    <td>INT AUTO_INCREMENT</td>
                    <td>ID único de empresa</td>
                </tr>
                <tr>
                    <td><code>razon_social</code></td>
                    <td>VARCHAR(200)</td>
                    <td>Nombre o razón social</td>
                </tr>
                <tr>
                    <td><code>rnc</code></td>
                    <td>VARCHAR(30)</td>
                    <td>RNC de la empresa</td>
                </tr>
                <tr>
                    <td><code>telefono</code></td>
                    <td>VARCHAR(30)</td>
                    <td>Teléfono de contacto</td>
                </tr>
                <tr>
                    <td><code>email</code></td>
                    <td>VARCHAR(120)</td>
                    <td>Correo electrónico</td>
                </tr>
                <tr>
                    <td><code>estado</code></td>
                    <td>ENUM('ACTIVO','INACTIVO')</td>
                    <td>Estado del registro</td>
                </tr>
                <tr>
                    <td><code>fecha_creacion</code></td>
                    <td>DATETIME</td>
                    <td>Fecha de registro</td>
                </tr>
            </tbody>
        </table>

        <h3>Tabla: fianzas</h3>
        <table>
            <thead>
                <tr>
                    <th>Campo</th>
                    <th>Tipo</th>
                    <th>Descripción</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>id</code></td>
                    <td>BIGINT AUTO_INCREMENT</td>
                    <td>ID único de fianza</td>
                </tr>
                <tr>
                    <td><code>numero_fianza</code></td>
                    <td>VARCHAR(40)</td>
                    <td>Número correlativo (FZ-2026-XXXX)</td>
                </tr>
                <tr>
                    <td><code>empresa_id</code></td>
                    <td>INT</td>
                    <td>Referencia a fianzas_empresas</td>
                </tr>
                <tr>
                    <td><code>cliente_nombre</code></td>
                    <td>VARCHAR(200)</td>
                    <td>Nombre del cliente (si no es empresa registrada)</td>
                </tr>
                <tr>
                    <td><code>cliente_telefono</code></td>
                    <td>VARCHAR(30)</td>
                    <td>Teléfono del cliente</td>
                </tr>
                <tr>
                    <td><code>cliente_email</code></td>
                    <td>VARCHAR(120)</td>
                    <td>Email del cliente</td>
                </tr>
                <tr>
                    <td><code>proyecto_referencia</code></td>
                    <td>VARCHAR(255)</td>
                    <td>Nombre/objeto del proyecto</td>
                </tr>
                <tr>
                    <td><code>beneficiario</code></td>
                    <td>VARCHAR(200)</td>
                    <td>Entidad beneficiaria</td>
                </tr>
                <tr>
                    <td><code>primer_requerimiento</code></td>
                    <td>BOOLEAN</td>
                    <td>Si es a primer requerimiento</td>
                </tr>
                <tr>
                    <td><code>aseguradora_id</code></td>
                    <td>INT</td>
                    <td>Referencia a aseguradora</td>
                </tr>
                <tr>
                    <td><code>aseguradora_nombre</code></td>
                    <td>VARCHAR(100)</td>
                    <td>Nombre de la aseguradora</td>
                </tr>
                <tr>
                    <td><code>tipo_fianza_id</code></td>
                    <td>INT</td>
                    <td>Referencia a tipo de fianza</td>
                </tr>
                <tr>
                    <td><code>tipo_fianza</code></td>
                    <td>VARCHAR(100)</td>
                    <td>Nombre del tipo de fianza</td>
                </tr>
                <tr>
                    <td><code>monto_contrato</code></td>
                    <td>DECIMAL(15,2)</td>
                    <td>Monto total del contrato</td>
                </tr>
                <tr>
                    <td><code>porcentaje_afianzar</code></td>
                    <td>DECIMAL(5,2)</td>
                    <td>Porcentaje a afianzar</td>
                </tr>
                <tr>
                    <td><code>valor_afianzado</code></td>
                    <td>DECIMAL(15,2)</td>
                    <td>Valor garantizado</td>
                </tr>
                <tr>
                    <td><code>cedula_rnc_asegurado</code></td>
                    <td>VARCHAR(30)</td>
                    <td>Identificación del asegurado</td>
                </tr>
                <tr>
                    <td><code>tasa_aplicada</code></td>
                    <td>DECIMAL(5,2)</td>
                    <td>Tasa de prima aplicada</td>
                </tr>
                <tr>
                    <td><code>tasa_manual</code></td>
                    <td>DECIMAL(5,2)</td>
                    <td>Tasa manual (si aplica override)</td>
                </tr>
                <tr>
                    <td><code>plazo_meses</code></td>
                    <td>INT</td>
                    <td>Duración en meses</td>
                </tr>
                <tr>
                    <td><code>fecha_inicio</code></td>
                    <td>DATE</td>
                    <td>Fecha de inicio de vigencia</td>
                </tr>
                <tr>
                    <td><code>fecha_fin</code></td>
                    <td>DATE</td>
                    <td>Fecha de vencimiento (calculada)</td>
                </tr>
                <tr>
                    <td><code>numero_contrato_licitacion</code></td>
                    <td>VARCHAR(100)</td>
                    <td>N° de contrato o licitación</td>
                </tr>
                <tr>
                    <td><code>ncf</code></td>
                    <td>VARCHAR(13)</td>
                    <td>NCF generado (B02-XXXXXXXX)</td>
                </tr>
                <tr>
                    <td><code>observaciones</code></td>
                    <td>TEXT</td>
                    <td>Observaciones adicionales</td>
                </tr>
                <tr>
                    <td><code>prima_base</code></td>
                    <td>DECIMAL(15,2)</td>
                    <td>Prima neta base</td>
                </tr>
                <tr>
                    <td><code>isc</code></td>
                    <td>DECIMAL(15,2)</td>
                    <td>ISC (16%)</td>
                </tr>
                <tr>
                    <td><code>total</code></td>
                    <td>DECIMAL(15,2)</td>
                    <td>Total a pagar</td>
                </tr>
                <tr>
                    <td><code>prima_minima_aplicada</code></td>
                    <td>BOOLEAN</td>
                    <td>Si se aplicó prima mínima</td>
                </tr>
                <tr>
                    <td><code>coberturas</code></td>
                    <td>JSON</td>
                    <td>Array de coberturas incluidas</td>
                </tr>
                <tr>
                    <td><code>estado</code></td>
                    <td>ENUM(...)</td>
                    <td>Cotización, Pendiente, Vigente, Vencida, Cancelada, Renovada</td>
                </tr>
                <tr>
                    <td><code>declaracion_veracidad</code></td>
                    <td>BOOLEAN</td>
                    <td>Aceptación de declaración (paso 1)</td>
                </tr>
                <tr>
                    <td><code>declaracion_administracion</code></td>
                    <td>BOOLEAN</td>
                    <td>Aceptación de autorización (paso 1)</td>
                </tr>
                <tr>
                    <td><code>usuario_id</code></td>
                    <td>INT</td>
                    <td>Usuario que creó la fianza</td>
                </tr>
                <tr>
                    <td><code>fecha_creacion</code></td>
                    <td>DATETIME</td>
                    <td>Timestamp de creación</td>
                </tr>
            </tbody>
        </table>

        <h3>Tabla: fianzas_tarifarios</h3>
        <table>
            <thead>
                <tr>
                    <th>Campo</th>
                    <th>Tipo</th>
                    <th>Descripción</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>id</code></td>
                    <td>INT AUTO_INCREMENT</td>
                    <td>ID único de tarifario</td>
                </tr>
                <tr>
                    <td><code>aseguradora_id</code></td>
                    <td>INT</td>
                    <td>Referencia a aseguradora</td>
                </tr>
                <tr>
                    <td><code>tipo_fianza</code></td>
                    <td>VARCHAR(100)</td>
                    <td>Tipo de fianza</td>
                </tr>
                <tr>
                    <td><code>tasa_porcentaje</code></td>
                    <td>DECIMAL(5,2)</td>
                    <td>Tasa de prima (%)</td>
                </tr>
                <tr>
                    <td><code>prima_minima</code></td>
                    <td>DECIMAL(15,2)</td>
                    <td>Prima mínima aplicable</td>
                </tr>
                <tr>
                    <td><code>fecha_vigencia_desde</code></td>
                    <td>DATE</td>
                    <td>Inicio de vigencia de la tarifa</td>
                </tr>
                <tr>
                    <td><code>fecha_vigencia_hasta</code></td>
                    <td>DATE</td>
                    <td>Fin de vigencia (NULL si indefinida)</td>
                </tr>
                <tr>
                    <td><code>activo</code></td>
                    <td>BOOLEAN</td>
                    <td>Tarifa activa (1) o inactiva (0)</td>
                </tr>
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
                <tr>
                    <td><code>/fianza_tarifarios.php?action=listar_aseguradoras</code></td>
                    <td>GET</td>
                    <td>Lista aseguradoras disponibles</td>
                </tr>
                <tr>
                    <td><code>/fianza_tarifarios.php?action=listar_tipos&aseguradora_id=X</code></td>
                    <td>GET</td>
                    <td>Lista tipos de fianza por aseguradora</td>
                </tr>
                <tr>
                    <td><code>/fianzas.php?action=calcular</code></td>
                    <td>POST</td>
                    <td>Calcula prima de fianza</td>
                </tr>
                <tr>
                    <td><code>/fianzas.php?action=listar</code></td>
                    <td>GET</td>
                    <td>Lista fianzas con filtros</td>
                </tr>
                <tr>
                    <td><code>/fianzas.php?action=guardar</code></td>
                    <td>POST</td>
                    <td>Guarda nueva fianza</td>
                </tr>
                <tr>
                    <td><code>/fianzas.php?action=actualizar</code></td>
                    <td>POST</td>
                    <td>Actualiza fianza existente</td>
                </tr>
                <tr>
                    <td><code>/fianzas_empresas.php?action=listar</code></td>
                    <td>GET</td>
                    <td>Lista empresas registradas</td>
                </tr>
                <tr>
                    <td><code>/fianzas_empresas.php?action=crear</code></td>
                    <td>POST</td>
                    <td>Registra nueva empresa</td>
                </tr>
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
                <tr>
                    <td><strong>R1 - Notificación</strong></td>
                    <td><span class="badge badge-ok">100%</span></td>
                    <td>Declaraciones legales firmadas en paso 1 del wizard</td>
                </tr>
                <tr>
                    <td><strong>R2 - Evidencia</strong></td>
                    <td><span class="badge badge-ok">100%</span></td>
                    <td>Registro inmutable de declaraciones (boolean flags)</td>
                </tr>
                <tr>
                    <td><strong>R6 - NCF</strong></td>
                    <td><span class="badge badge-ok">100%</span></td>
                    <td>Generación opcional de NCF B02 con log de auditoría</td>
                </tr>
                <tr>
                    <td><strong>R7 - Contable</strong></td>
                    <td><span class="badge badge-ok">100%</span></td>
                    <td>Integración con MotorContable para asientos automáticos</td>
                </tr>
                <tr>
                    <td><strong>R8 - Exportación</strong></td>
                    <td><span class="badge badge-ok">100%</span></td>
                    <td>PDF profesional, Excel, CSV con branding corporativo</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-circle-check"></i> Flujo Completo de Cotización</h2>
        
        <div class="code-block">
1. Usuario accede a "Nueva Fianza"
   ↓
2. Wizard - Paso 1: Acepta declaraciones legales
   ↓
3. Wizard - Paso 2: Ingresa datos del cliente
   ↓
4. Wizard - Paso 3: Completa datos técnicos de la fianza
   - Selecciona aseguradora → Carga tipos disponibles
   - Selecciona tipo de fianza
   - Ingresa monto contrato y % afianzar
   - Sistema calcula valor afianzado automáticamente
   - Ingresa plazo y fecha inicio
   - Opcional: Marca "Generar NCF"
   ↓
5. Click en "Calcular Prima"
   - API calcula prima base según tarifario
   - Aplica ISC (16%)
   - Verifica prima mínima
   - Genera coberturas incluidas
   ↓
6. Wizard - Paso 4: Confirmación
   - Muestra resumen completo
   - Muestra desglose de prima
   ↓
7. Click en "Guardar Cotización"
   - Guarda en tabla fianzas
   - Si NCF marcado: genera NCF y registra en log
   - Dispara MotorContable para asiento automático
   ↓
8. Descarga automática de PDF profesional
   - PDF incluye logo, datos, coberturas, NCF, QR
   ↓
9. Registro en auditoría_lineal
   - Timestamp, usuario, acción, datos forenses
        </div>
    </div>

    <div class="footer-doc">
        <p><strong><?php echo $platform_name; ?></strong> - Módulo de Fianzas | Documentación Técnica</p>
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
        '📄 Módulo de Clientes',
        '📄 Auditoría Lineal NOFTRAB',
        '📄 Centro Financiero',
        '📄 Panel de Comisiones',
        '📄 Módulo de Fianzas'
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
            'Modulo_Clientes',
            'Auditoria_Lineal_NOFTRAB',
            'Centro_Financiero',
            'Panel_Comisiones',
            'Modulo_Fianzas'
        ];
        
        doc.save('Documentacion_Plataforma_Parte1_' + nombres[documentoActivo - 1] + '.pdf');
        
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
        // Heredar skin del dashboard padre
        try {
            if (window.parent && window.parent.document) {
                const parentSkin = window.parent.document.documentElement.getAttribute('data-skin');
                if (parentSkin) document.documentElement.setAttribute('data-skin', parentSkin);
            }
        } catch(e) {}

        const urlParams = new URLSearchParams(window.location.search);
        const docParam = urlParams.get('doc');
        if (docParam) {
            mostrarDocumento(parseInt(docParam));
            const btnVolver = document.querySelector('button[onclick="volverMenu()"]');
            if (btnVolver) btnVolver.style.display = 'none';
            // Premium dark theme — no override to white
        }
    });
</script>

</body>
</html>