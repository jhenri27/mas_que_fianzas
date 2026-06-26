<?php
/**
 * DOCUMENTACIÓN GENERAL DE LA PLATAFORMA INTEGRADA - PARTE 3
 * MAS QUE FIANZAS - Sistema Integrado de Gestión
 * 
 * Genera documentación completa de los módulos:
 * 1. Usuarios y Red Comercial
 * 2. Verificar Pago
 * 3. UX Skins y Personalización
 * 4. Helpdesk e Incidencias
 * 5. Finance Lab
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
    <title>Documentación General - <?php echo $platform_name; ?> - Parte 3</title>
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
    <h1><i class="fa-solid fa-book" style="color:#6366f1;"></i> Documentación de la Plataforma - Parte 3</h1>
    <p class="subtitle"><?php echo $platform_name; ?> | v<?php echo $platform_version; ?> | Generado: <?php echo $generation_date; ?></p>
    
    <div class="doc-buttons">
        <div class="doc-btn" onclick="mostrarDocumento(1)">
            <i class="fa-solid fa-users-gear"></i>
            <h3>1. Gestión de Usuarios</h3>
            <p>Red comercial, comisiones y jerarquías</p>
        </div>
        <div class="doc-btn" onclick="mostrarDocumento(2)">
            <i class="fa-solid fa-circle-check"></i>
            <h3>2. Verificar Pago</h3>
            <p>Verificador de transacciones fiscales</p>
        </div>
        <div class="doc-btn" onclick="mostrarDocumento(3)">
            <i class="fa-solid fa-palette"></i>
            <h3>3. UX Skins</h3>
            <p>Personalización de apariencia y marca</p>
        </div>
        <div class="doc-btn" onclick="mostrarDocumento(4)">
            <i class="fa-solid fa-headset"></i>
            <h3>4. Helpdesk</h3>
            <p>Sistema de incidencias y soporte</p>
        </div>
        <div class="doc-btn" onclick="mostrarDocumento(5)">
            <i class="fa-solid fa-flask"></i>
            <h3>5. Finance Lab</h3>
            <p>Laboratorio de integración contable</p>
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

<!-- DOCUMENTO 1: USUARIOS -->
<div class="documento" id="documento1">
    <div class="doc-header">
        <h1><i class="fa-solid fa-users-gear"></i> Gestión de Usuarios y Red Comercial</h1>
        <div class="meta">
            <span><i class="fa-solid fa-building"></i> <?php echo $platform_name; ?></span>
            <span><i class="fa-solid fa-network-wired"></i> Network Management</span>
            <span><i class="fa-solid fa-calendar"></i> <?php echo date('d/m/Y'); ?></span>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-circle-info"></i> Descripción General</h2>
        <p>El módulo de Usuarios es el sistema integral de gestión de la red comercial de MAS QUE FIANZAS. Permite administrar usuarios, comisiones directas y por red, jerarquías de supervisores, información bancaria para pagos, y configuración de comisiones especiales por ramo. Soporta 6 perfiles comerciales con diferentes niveles de acceso y estructura de comisiones.</p>
        
        <div class="status-grid">
            <div class="status-card cumple">
                <div class="label">Perfiles</div>
                <div class="value">6 tipos</div>
                <div class="desc">Admin, Agente, Supervisor, PDV, etc.</div>
            </div>
            <div class="status-card cumple">
                <div class="label">Comisiones</div>
                <div class="value">Multi-ramo</div>
                <div class="desc">6 ramos configurables</div>
            </div>
            <div class="status-card cumple">
                <div class="label">Red</div>
                <div class="value">Jerárquica</div>
                <div class="desc">Supervisor → Agente → PDV</div>
            </div>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-gears"></i> Funcionalidades Principales</h2>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-user-plus"></i> Registro de Usuario</h4>
            <ul>
                <li><strong>Cédula / RNC*:</strong> Identificación fiscal dominicana</li>
                <li><strong>Usuario (login)*:</strong> Nombre de usuario único para acceso</li>
                <li><strong>Nombre* y Apellido*:</strong> Datos personales</li>
                <li><strong>Correo electrónico*:</strong> Email para notificaciones</li>
                <li><strong>Teléfono:</strong> Número de contacto</li>
                <li><strong>Perfil*:</strong>
                    <ul>
                        <li>Administrador (acceso total)</li>
                        <li>Agente Comercial (ventas directas)</li>
                        <li>Supervisor Comercial (supervisión de agentes)</li>
                        <li>Supervisor Zona (supervisión regional)</li>
                        <li>Socio Comercial (partner estratégico)</li>
                        <li>PDV (Punto de Venta - acceso limitado)</li>
                    </ul>
                </li>
                <li><strong>Estado:</strong> Activo, Inactivo, Bloqueado</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-coins"></i> Configuración de Comisiones</h4>
            <ul>
                <li><strong>¿Es Comisionante?:</strong> Sí/No (define si recibe comisiones)</li>
                <li><strong>% Comisión Directa:</strong> Porcentaje sobre pólizas propias</li>
                <li><strong>% Comisión por Red:</strong> Porcentaje sobre producción de su red</li>
                <li><strong>Comisiones Especiales por Ramo (%):</strong>
                    <ul>
                        <li>Autos Ley</li>
                        <li>Autos Full</li>
                        <li>Fianzas</li>
                        <li>Incendio</li>
                        <li>Responsabilidad Civil</li>
                        <li>Otros Ramos</li>
                    </ul>
                </li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-building-columns"></i> Información Bancaria</h4>
            <ul>
                <li><strong>Banco:</strong> Banreservas, Banco Popular, Banco BHD, Scotiabank, Asociación Popular, Banco Santa Cruz, Banco Promerica, Otro</li>
                <li><strong>Tipo Cuenta:</strong> Ahorros, Corriente</li>
                <li><strong>Número de Cuenta:</strong> Para depósitos de comisiones</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-location-dot"></i> Ubicación y Personal</h4>
            <ul>
                <li><strong>Enlace Google Maps:</strong> Ubicación física del usuario/PDV</li>
                <li><strong>Fecha de Cumpleaños:</strong> Para reconocimientos y beneficios</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-sitemap"></i> Jerarquía de Red</h4>
            <ul>
                <li><strong>Supervisor / Referente:</strong> Usuario superior bajo cuya red queda registrado</li>
                <li><strong>Red de Usuarios:</strong> Visualización de usuarios bajo su supervisión</li>
                <li><strong>Estructura:</strong> Supervisor Zona → Supervisor Comercial → Agente → PDV</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-file-export"></i> Exportación</h4>
            <ul>
                <li><strong>PDF:</strong> Listado profesional de usuarios</li>
                <li><strong>Excel (.xlsx):</strong> Hoja de cálculo editable</li>
                <li><strong>CSV:</strong> Texto plano para importación</li>
                <li><strong>JSON:</strong> Formato estructurado para APIs</li>
                <li><strong>ZIP:</strong> Paquete comprimido</li>
            </ul>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-database"></i> Estructura de Datos - Tablas Principales</h2>
        
        <h3>Tabla: usuarios</h3>
        <table>
            <thead>
                <tr>
                    <th>Campo</th>
                    <th>Tipo</th>
                    <th>Descripción</th>
                </tr>
            </thead>
            <tbody>
                <tr><td><code>id</code></td><td>INT AUTO_INCREMENT</td><td>ID único de usuario</td></tr>
                <tr><td><code>cedula_rnc</code></td><td>VARCHAR(30)</td><td>Cédula o RNC (único)</td></tr>
                <tr><td><code>username</code></td><td>VARCHAR(100)</td><td>Nombre de usuario (login)</td></tr>
                <tr><td><code>nombre</code></td><td>VARCHAR(100)</td><td>Nombre</td></tr>
                <tr><td><code>apellido</code></td><td>VARCHAR(100)</td><td>Apellido</td></tr>
                <tr><td><code>email</code></td><td>VARCHAR(120)</td><td>Correo electrónico</td></tr>
                <tr><td><code>telefono</code></td><td>VARCHAR(30)</td><td>Teléfono</td></tr>
                <tr><td><code>perfil_id</code></td><td>INT</td><td>Referencia a perfiles</td></tr>
                <tr><td><code>estado</code></td><td>ENUM('ACTIVO','INACTIVO','BLOQUEADO')</td><td>Estado del usuario</td></tr>
                <tr><td><code>es_comisionante</code></td><td>BOOLEAN</td><td>Si recibe comisiones</td></tr>
                <tr><td><code>comision_directa_pct</code></td><td>DECIMAL(5,2)</td><td>% comisión directa</td></tr>
                <tr><td><code>comision_red_pct</code></td><td>DECIMAL(5,2)</td><td>% comisión por red</td></tr>
                <tr><td><code>comision_autos_ley</code></td><td>DECIMAL(5,2)</td><td>% comisión Autos Ley</td></tr>
                <tr><td><code>comision_autos_full</code></td><td>DECIMAL(5,2)</td><td>% comisión Autos Full</td></tr>
                <tr><td><code>comision_fianzas</code></td><td>DECIMAL(5,2)</td><td>% comisión Fianzas</td></tr>
                <tr><td><code>comision_incendio</code></td><td>DECIMAL(5,2)</td><td>% comisión Incendio</td></tr>
                <tr><td><code>comision_rc</code></td><td>DECIMAL(5,2)</td><td>% comisión Responsabilidad Civil</td></tr>
                <tr><td><code>comision_otros</code></td><td>DECIMAL(5,2)</td><td>% comisión Otros Ramos</td></tr>
                <tr><td><code>banco</code></td><td>VARCHAR(100)</td><td>Banco para pagos</td></tr>
                <tr><td><code>tipo_cuenta</code></td><td>ENUM('AHORROS','CORRIENTE')</td><td>Tipo de cuenta bancaria</td></tr>
                <tr><td><code>numero_cuenta</code></td><td>VARCHAR(50)</td><td>Número de cuenta</td></tr>
                <tr><td><code>ubicacion_maps</code></td><td>VARCHAR(255)</td><td>Enlace Google Maps</td></tr>
                <tr><td><code>fecha_cumpleanos</code></td><td>DATE</td><td>Fecha de cumpleaños</td></tr>
                <tr><td><code>supervisor_id</code></td><td>INT</td><td>Referencia a supervisor (usuario padre)</td></tr>
                <tr><td><code>password_hash</code></td><td>VARCHAR(255)</td><td>Hash de contraseña (SHA-256)</td></tr>
                <tr><td><code>ultimo_acceso</code></td><td>DATETIME</td><td>Último acceso al sistema</td></tr>
                <tr><td><code>fecha_creacion</code></td><td>DATETIME</td><td>Fecha de registro</td></tr>
            </tbody>
        </table>

        <h3>Tabla: perfiles</h3>
        <table>
            <thead>
                <tr>
                    <th>Campo</th>
                    <th>Tipo</th>
                    <th>Descripción</th>
                </tr>
            </thead>
            <tbody>
                <tr><td><code>id</code></td><td>INT AUTO_INCREMENT</td><td>ID único de perfil</td></tr>
                <tr><td><code>nombre</code></td><td>VARCHAR(100)</td><td>Nombre del perfil</td></tr>
                <tr><td><code>descripcion</code></td><td>TEXT</td><td>Descripción del rol</td></tr>
                <tr><td><code>nivel_acceso</code></td><td>INT (1-5)</td><td>Nivel de acceso (1=Admin, 5=PDV)</td></tr>
                <tr><td><code>permisos</code></td><td>JSON</td><td>Matriz de permisos por módulo</td></tr>
            </tbody>
        </table>

        <h3>Tabla: comisiones_usuarios</h3>
        <table>
            <thead>
                <tr>
                    <th>Campo</th>
                    <th>Tipo</th>
                    <th>Descripción</th>
                </tr>
            </thead>
            <tbody>
                <tr><td><code>id</code></td><td>BIGINT AUTO_INCREMENT</td><td>ID único de comisión</td></tr>
                <tr><td><code>usuario_id</code></td><td>INT</td><td>Usuario que recibe la comisión</td></tr>
                <tr><td><code>poliza_id</code></td><td>BIGINT</td><td>Póliza que generó la comisión</td></tr>
                <tr><td><code>tipo_comision</code></td><td>ENUM('DIRECTA','RED')</td><td>Tipo de comisión</td></tr>
                <tr><td><code>ramo</code></td><td>VARCHAR(50)</td><td>Ramo (Autos Ley, Fianzas, etc.)</td></tr>
                <tr><td><code>porcentaje</code></td><td>DECIMAL(5,2)</td><td>Porcentaje aplicado</td></tr>
                <tr><td><code>monto</code></td><td>DECIMAL(15,2)</td><td>Monto de comisión</td></tr>
                <tr><td><code>estado_pago</code></td><td>ENUM('PENDIENTE','PAGADO')</td><td>Estado de pago</td></tr>
                <tr><td><code>fecha_generacion</code></td><td>DATETIME</td><td>Fecha de generación</td></tr>
                <tr><td><code>fecha_pago</code></td><td>DATETIME</td><td>Fecha de pago</td></tr>
            </tbody>
        </table>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-calculator"></i> Fórmulas de Cálculo de Comisiones</h2>
        
        <div class="code-block">
// Comisión Directa (sobre pólizas propias)
comision_directa = prima_neta * (comision_directa_pct / 100)

// Comisión por Red (sobre producción de agentes bajo supervisión)
comision_red = prima_neta_agente * (comision_red_pct / 100)

// Comisión Especial por Ramo (override de porcentaje estándar)
comision_ramo = prima_neta * (comision_autos_ley / 100)  // Ejemplo para Autos Ley

// Jerarquía de aplicación:
// 1. Si existe comisión especial por ramo → usar esa
// 2. Si no → usar comisión directa estándar
// 3. Supervisor recibe comisión por red de todos sus agentes

// Ejemplo práctico:
// Agente vende póliza Autos Ley: Prima Neta RD$ 10,000
// Comisión directa agente: 15% = RD$ 1,500
// Supervisor recibe: 5% sobre red = RD$ 500
// Total pagado: RD$ 2,000
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
                <tr><td><code>/usuarios.php?action=listar</code></td><td>GET</td><td>Lista usuarios con filtros</td></tr>
                <tr><td><code>/usuarios.php?action=crear</code></td><td>POST</td><td>Crea nuevo usuario</td></tr>
                <tr><td><code>/usuarios.php?action=editar/{id}</code></td><td>PUT</td><td>Actualiza usuario</td></tr>
                <tr><td><code>/usuarios.php?action=eliminar/{id}</code></td><td>DELETE</td><td>Elimina usuario</td></tr>
                <tr><td><code>/usuarios.php?action=bloquear/{id}</code></td><td>POST</td><td>Bloquea usuario</td></tr>
                <tr><td><code>/usuarios.php?action=desbloquear/{id}</code></td><td>POST</td><td>Desbloquea usuario</td></tr>
                <tr><td><code>/usuarios.php?action=restablecer-password/{id}</code></td><td>POST</td><td>Restablece contraseña</td></tr>
                <tr><td><code>/comisiones.php?action=calcular</code></td><td>POST</td><td>Calcula comisiones de usuario</td></tr>
                <tr><td><code>/comisiones.php?action=listar</code></td><td>GET</td><td>Lista comisiones pendientes</td></tr>
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
                <tr><td><strong>R3 - Permisos granulares</strong></td><td><span class="badge badge-ok">100%</span></td><td>6 perfiles con matriz de permisos configurable</td></tr>
                <tr><td><strong>R4 - Auditoría</strong></td><td><span class="badge badge-ok">100%</span></td><td>Registro de creación/modificación de usuarios</td></tr>
                <tr><td><strong>R9 - Accesibilidad</strong></td><td><span class="badge badge-ok">100%</span></td><td>Labels ARIA, navegación por teclado</td></tr>
            </tbody>
        </table>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-circle-check"></i> Flujo de Registro de Usuario</h2>
        
        <div class="code-block">
1. Administrador accede a "Nuevo Usuario"
   ↓
2. Completa datos básicos:
   - Cédula/RNC, Username, Nombre, Apellido, Email, Teléfono
   ↓
3. Selecciona Perfil (6 opciones disponibles)
   ↓
4. Configura estado: Activo/Inactivo/Bloqueado
   ↓
5. Si es comisionante:
   - Marca "Sí" en ¿Es Comisionante?
   - Ingresa % Comisión Directa
   - Ingresa % Comisión por Red
   - Configura comisiones especiales por ramo (opcional)
   ↓
6. Completa información bancaria:
   - Selecciona banco
   - Selecciona tipo de cuenta
   - Ingresa número de cuenta
   ↓
7. Agrega ubicación (Google Maps) y fecha de cumpleaños
   ↓
8. Selecciona Supervisor/Referente (usuario padre en la red)
   ↓
9. Guarda usuario
   - Sistema genera hash de contraseña temporal
   - Envía email de bienvenida con credenciales
   - Registra en auditoría_lineal
   ↓
10. Usuario aparece en la red bajo su supervisor
        </div>
    </div>

    <div class="footer-doc">
        <p><strong><?php echo $platform_name; ?></strong> - Gestión de Usuarios | Documentación Técnica</p>
        <p>Clasificación: <strong>INTERNO</strong> | Versión: 1.0</p>
    </div>
</div>

<!-- DOCUMENTO 2: VERIFICAR PAGO -->
<div class="documento" id="documento2">
    <div class="doc-header">
        <h1><i class="fa-solid fa-circle-check"></i> Verificar Pago - Verificador de Transacciones</h1>
        <div class="meta">
            <span><i class="fa-solid fa-building"></i> <?php echo $platform_name; ?></span>
            <span><i class="fa-solid fa-magnifying-glass-dollar"></i> Payment Verification</span>
            <span><i class="fa-solid fa-calendar"></i> <?php echo date('d/m/Y'); ?></span>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-circle-info"></i> Descripción General</h2>
        <p>El Verificador de Pago es un módulo de consulta que permite validar transacciones contra el Libro Mayor contable y la base de datos fiscal de la DGII. Proporciona trazabilidad completa de pagos registrados, verificando que cada transacción esté correctamente contabilizada y que los NCF emitidos sean válidos según registros fiscales.</p>
        
        <div class="status-grid">
            <div class="status-card cumple">
                <div class="label">Verificación</div>
                <div class="value">Doble</div>
                <div class="desc">Libro Mayor + DGII</div>
            </div>
            <div class="status-card cumple">
                <div class="label">Alcance</div>
                <div class="value">Fiscal</div>
                <div class="desc">NCF y comprobantes</div>
            </div>
            <div class="status-card cumple">
                <div class="label">Tiempo</div>
                <div class="value">Real</div>
                <div class="desc">Consulta instantánea</div>
            </div>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-gears"></i> Funcionalidades Principales</h2>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-magnifying-glass"></i> Búsqueda de Transacción</h4>
            <ul>
                <li><strong>Número de NCF:</strong> Búsqueda por comprobante fiscal (B02-XXXXXXXX)</li>
                <li><strong>Número de Recibo:</strong> Búsqueda por recibo de pago interno</li>
                <li><strong>Número de Póliza:</strong> Búsqueda por póliza asociada al pago</li>
                <li><strong>Fecha de Pago:</strong> Rango de fechas para filtrar</li>
                <li><strong>Monto:</strong> Búsqueda por monto exacto o aproximado</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-book"></i> Verificación con Libro Mayor</h4>
            <ul>
                <li><strong>Validación contable:</strong> Confirma que el pago está registrado en el asiento contable correspondiente</li>
                <li><strong>Cuenta de débito:</strong> Verifica cuenta bancaria o caja utilizada</li>
                <li><strong>Cuenta de crédito:</strong> Verifica cuenta de ingreso o cuenta por cobrar</li>
                <li><strong>Fecha de contabilización:</strong> Confirma fecha de registro contable</li>
                <li><strong>Usuario que registró:</strong> Identifica operador que procesó el pago</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-file-invoice"></i> Verificación con Base de Datos Fiscal (DGII)</h4>
            <ul>
                <li><strong>Validez de NCF:</strong> Confirma que el NCF está registrado en secuencias oficiales</li>
                <li><strong>Tipo de comprobante:</strong> Verifica tipo (B01, B02, B14, etc.)</li>
                <li><strong>Secuencia correlativa:</strong> Valida que no haya saltos en la secuencia</li>
                <li><strong>Fecha de emisión:</strong> Confirma fecha de emisión del NCF</li>
                <li><strong>Monto fiscal:</strong> Valida que el monto coincida con el declarado</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-circle-check"></i> Resultado de Verificación</h4>
            <ul>
                <li><strong>✅ Válido:</strong> Transacción verificada correctamente en ambos sistemas</li>
                <li><strong>⚠️ Parcial:</strong> Registrado en un sistema pero no en el otro (requiere revisión)</li>
                <li><strong>❌ Inválido:</strong> No encontrado o con inconsistencias críticas</li>
                <li><strong>Detalle completo:</strong> Muestra todos los datos de la transacción verificada</li>
            </ul>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-database"></i> Estructura de Datos - Tablas Consultadas</h2>
        
        <h3>Tabla: pagos_polizas (consulta)</h3>
        <table>
            <thead>
                <tr>
                    <th>Campo</th>
                    <th>Tipo</th>
                    <th>Descripción</th>
                </tr>
            </thead>
            <tbody>
                <tr><td><code>id</code></td><td>BIGINT</td><td>ID del pago</td></tr>
                <tr><td><code>poliza_id</code></td><td>BIGINT</td><td>Póliza asociada</td></tr>
                <tr><td><code>ncf</code></td><td>VARCHAR(13)</td><td>NCF generado</td></tr>
                <tr><td><code>monto</code></td><td>DECIMAL(15,2)</td><td>Monto del pago</td></tr>
                <tr><td><code>fecha_pago</code></td><td>DATETIME</td><td>Fecha del pago</td></tr>
                <tr><td><code>forma_pago</code></td><td>ENUM</td><td>Efectivo, Transferencia, etc.</td></tr>
                <tr><td><code>estado</code></td><td>ENUM</td><td>Confirmado, Pendiente, Rechazado</td></tr>
            </tbody>
        </table>

        <h3>Tabla: asientos_contables (consulta)</h3>
        <table>
            <thead>
                <tr>
                    <th>Campo</th>
                    <th>Tipo</th>
                    <th>Descripción</th>
                </tr>
            </thead>
            <tbody>
                <tr><td><code>id</code></td><td>BIGINT</td><td>ID del asiento</td></tr>
                <tr><td><code>numero_asiento</code></td><td>VARCHAR(20)</td><td>Número correlativo</td></tr>
                <tr><td><code>fecha</code></td><td>DATE</td><td>Fecha del asiento</td></tr>
                <tr><td><code>descripcion</code></td><td>TEXT</td><td>Descripción</td></tr>
                <tr><td><code>total_debito</code></td><td>DECIMAL(15,2)</td><td>Total débito</td></tr>
                <tr><td><code>total_credito</code></td><td>DECIMAL(15,2)</td><td>Total crédito</td></tr>
                <tr><td><code>estado</code></td><td>ENUM</td><td>Borrador, Contabilizado, Anulado</td></tr>
            </tbody>
        </table>

        <h3>Tabla: ncf_log_auditoria (consulta)</h3>
        <table>
            <thead>
                <tr>
                    <th>Campo</th>
                    <th>Tipo</th>
                    <th>Descripción</th>
                </tr>
            </thead>
            <tbody>
                <tr><td><code>id</code></td><td>BIGINT</td><td>ID del log</td></tr>
                <tr><td><code>ncf</code></td><td>VARCHAR(13)</td><td>NCF completo</td></tr>
                <tr><td><code>tipo</code></td><td>VARCHAR(3)</td><td>Tipo de comprobante</td></tr>
                <tr><td><code>fecha_hora</code></td><td>DATETIME</td><td>Fecha/hora de emisión</td></tr>
                <tr><td><code>modulo_origen</code></td><td>VARCHAR(50)</td><td>Módulo que generó</td></tr>
                <tr><td><code>registro_id</code></td><td>VARCHAR(100)</td><td>ID del registro asociado</td></tr>
            </tbody>
        </table>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-calculator"></i> Lógica de Verificación</h2>
        
        <div class="code-block">
// Proceso de verificación en 3 pasos:

PASO 1: Búsqueda en pagos_polizas
SELECT * FROM pagos_polizas 
WHERE ncf = ? OR numero_recibo = ? OR poliza_id = ?

PASO 2: Verificación con Libro Mayor
SELECT a.* FROM asientos_contables a
JOIN asientos_detalle ad ON a.id = ad.asiento_id
WHERE a.descripcion LIKE '%{numero_pago}%'
AND a.estado = 'CONTABILIZADO'

PASO 3: Verificación con NCF Log
SELECT * FROM ncf_log_auditoria
WHERE ncf = ?
AND tipo IN ('B01', 'B02', 'B14')

// Resultado:
// Si los 3 pasos retornan datos → ✅ VÁLIDO
// Si solo 1 o 2 pasos retornan → ⚠️ PARCIAL
// Si ningún paso retorna → ❌ INVÁLIDO
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
                <tr><td><strong>R6 - NCF</strong></td><td><span class="badge badge-ok">100%</span></td><td>Verificación de NCF contra log de auditoría</td></tr>
                <tr><td><strong>R7 - Contable</strong></td><td><span class="badge badge-ok">100%</span></td><td>Validación contra Libro Mayor</td></tr>
                <tr><td><strong>R4 - Auditoría</strong></td><td><span class="badge badge-ok">100%</span></td><td>Trazabilidad completa de transacciones</td></tr>
            </tbody>
        </table>
    </div>

    <div class="footer-doc">
        <p><strong><?php echo $platform_name; ?></strong> - Verificar Pago | Documentación Técnica</p>
        <p>Clasificación: <strong>INTERNO</strong> | Versión: 1.0</p>
    </div>
</div>

<!-- DOCUMENTO 3: UX SKINS -->
<div class="documento" id="documento3">
    <div class="doc-header">
        <h1><i class="fa-solid fa-palette"></i> UX Skins - Personalización de Apariencia</h1>
        <div class="meta">
            <span><i class="fa-solid fa-building"></i> <?php echo $platform_name; ?></span>
            <span><i class="fa-solid fa-wand-magic-sparkles"></i> Theme Engine</span>
            <span><i class="fa-solid fa-calendar"></i> <?php echo date('d/m/Y'); ?></span>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-circle-info"></i> Descripción General</h2>
        <p>El módulo UX Skins es el sistema de personalización de apariencia de la plataforma. Permite cambiar temas visuales, configurar modo día/noche automático, aplicar efectos glassmorphic premium, personalizar marca corporativa (logo, tipografía, colores), y aplicar cambios a nivel individual o corporativo. Los cambios se aplican en tiempo real y se persisten en la base de datos.</p>
        
        <div class="status-grid">
            <div class="status-card cumple">
                <div class="label">Skins</div>
                <div class="value">4+1</div>
                <div class="desc">4 predefinidos + custom</div>
            </div>
            <div class="status-card cumple">
                <div class="label">Modo</div>
                <div class="value">Auto</div>
                <div class="desc">Día/Noche automático</div>
            </div>
            <div class="status-card cumple">
                <div class="label">Marca</div>
                <div class="value">Custom</div>
                <div class="desc">Logo y colores B2B</div>
            </div>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-gears"></i> Funcionalidades Principales</h2>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-swatchbook"></i> Skins Predefinidos</h4>
            <ul>
                <li><strong>Indigo Corp (Activo por defecto):</strong>
                    <ul>
                        <li>Corporativo, profesional, confianza financiera</li>
                        <li>Colores: Indigo (#6366f1), Azul corporativo</li>
                        <li>Ideal para uso diario</li>
                    </ul>
                </li>
                <li><strong>Obsidian Dark:</strong>
                    <ul>
                        <li>Dark mode elegante, premium, visión nocturna</li>
                        <li>Colores: Negro obsidian, acentos luminosos</li>
                        <li>Reduce fatiga visual en ambientes oscuros</li>
                    </ul>
                </li>
                <li><strong>Coral Finance:</strong>
                    <ul>
                        <li>Energético, dinámico, ideal para equipos de ventas</li>
                        <li>Colores: Coral, naranja energético</li>
                        <li>Estimula productividad y acción</li>
                    </ul>
                </li>
                <li><strong>Custom Brand:</strong>
                    <ul>
                        <li>Personalización completa B2B con tus colores y logo</li>
                        <li>Configuración de colores primarios, secundarios, acentos</li>
                        <li>Subida de logo corporativo personalizado</li>
                    </ul>
                </li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-moon"></i> Modo Inteligente Día/Noche</h4>
            <ul>
                <li><strong>Activación automática:</strong> Cambia a Obsidian Dark después de las 19:00 hasta las 07:00</li>
                <li><strong>Detección de hora:</strong> Usa reloj del sistema del usuario</li>
                <li><strong>Transición suave:</strong> Animación de cambio de tema</li>
                <li><strong>Override manual:</strong> Usuario puede forzar modo específico</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-gem"></i> Diseño Moderno Glassmorphic (Premium)</h4>
            <ul>
                <li><strong>Efectos translúcidos:</strong> Ultra-premium con blur y transparencia</li>
                <li><strong>Brillos dinámicos:</strong> Efectos de luz en elementos interactivos</li>
                <li><strong>Tipografía Outfit:</strong> Fuente moderna y legible</li>
                <li><strong>Rollback:</strong> Opción de desactivar y usar estilos clásicos sólidos</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-pen-to-square"></i> Editor de Marca Personalizada</h4>
            <ul>
                <li><strong>Disponibilidad:</strong> Solo para Administradores</li>
                <li><strong>Alcance:</strong> Los cambios aplican a toda la empresa</li>
                <li><strong>Tipografía:</strong>
                    <ul>
                        <li>Inter (moderna, legible)</li>
                        <li>Roboto (clásica, profesional)</li>
                        <li>Poppins (amigable, redondeada)</li>
                        <li>Outfit (premium, glassmorphic)</li>
                    </ul>
                </li>
                <li><strong>Preview:</strong> Vista previa en tiempo real con ejemplo de póliza</li>
                <li><strong>Nombre de Empresa:</strong> Aparece en sidebar y PDFs</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-download"></i> Exportar/Importar Configuración</h4>
            <ul>
                <li><strong>Exportar JSON:</strong> Descarga configuración actual de skin</li>
                <li><strong>Importar JSON:</strong> Carga configuración previamente exportada</li>
                <li><strong>Backup:</strong> Permite respaldar configuraciones personalizadas</li>
                <li><strong>Migración:</strong> Facilita replicar configuración entre instancias</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-user-check"></i> Alcance de Aplicación</h4>
            <ul>
                <li><strong>Solo para mí:</strong> Cambios aplican solo al usuario actual (localStorage)</li>
                <li><strong>Aplicar a toda la empresa:</strong> Cambios aplican a todos los usuarios (base de datos)</li>
                <li><strong>Restablecer:</strong> Vuelve a configuración por defecto (Indigo Corp)</li>
            </ul>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-database"></i> Estructura de Datos - Tablas Principales</h2>
        
        <h3>Tabla: configuracion_skins</h3>
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
                <tr><td><code>usuario_id</code></td><td>INT</td><td>Usuario (NULL si es global)</td></tr>
                <tr><td><code>skin_activo</code></td><td>VARCHAR(50)</td><td>Skin activo (indigo, obsidian, coral, custom)</td></tr>
                <tr><td><code>modo_inteligente</code></td><td>BOOLEAN</td><td>Modo día/noche automático</td></tr>
                <tr><td><code>glassmorphic</code></td><td>BOOLEAN</td><td>Efectos glassmorphic activados</td></tr>
                <tr><td><code>tipografia</code></td><td>VARCHAR(50)</td><td>Tipografía seleccionada</td></tr>
                <tr><td><code>nombre_empresa</code></td><td>VARCHAR(200)</td><td>Nombre de empresa personalizado</td></tr>
                <tr><td><code>logo_ruta</code></td><td>VARCHAR(255)</td><td>Ruta del logo personalizado</td></tr>
                <tr><td><code>color_primario</code></td><td>VARCHAR(7)</td><td>Color primario (#6366f1)</td></tr>
                <tr><td><code>color_secundario</code></td><td>VARCHAR(7)</td><td>Color secundario</td></tr>
                <tr><td><code>color_acento</code></td><td>VARCHAR(7)</td><td>Color de acento</td></tr>
                <tr><td><code>configuracion_json</code></td><td>JSON</td><td>Configuración completa en JSON</td></tr>
                <tr><td><code>es_global</code></td><td>BOOLEAN</td><td>Aplica a toda la empresa</td></tr>
                <tr><td><code>fecha_actualizacion</code></td><td>DATETIME</td><td>Última actualización</td></tr>
            </tbody>
        </table>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-code"></i> Implementación Técnica</h2>
        
        <div class="code-block">
// Aplicación de skin al cargar la página
(function(){
    var s = localStorage.getItem('mqf-skin') || 'indigo';
    document.body.setAttribute('data-skin', s);
    document.documentElement.setAttribute('data-skin', s);
})();

// Escuchar mensajes del dashboard para cambios en tiempo real
window.addEventListener('message', function(e){
    if(e.data && e.data.type === 'mqf-skin-set'){
        document.body.setAttribute('data-skin', e.data.skin);
        document.documentElement.setAttribute('data-skin', e.data.skin);
    }
});

// Modo inteligente día/noche
function aplicarModoInteligente() {
    const hora = new Date().getHours();
    if (hora >= 19 || hora < 7) {
        document.body.setAttribute('data-skin', 'obsidian');
    } else {
        document.body.setAttribute('data-skin', 'indigo');
    }
}

// Variables CSS por skin
[data-skin="indigo"] {
    --mqf-primary: #6366f1;
    --mqf-bg: #f8fafc;
    --mqf-text: #1e293b;
}

[data-skin="obsidian"] {
    --mqf-primary: #667eea;
    --mqf-bg: #1a1a2e;
    --mqf-text: #e0e0e0;
}

[data-skin="coral"] {
    --mqf-primary: #f97316;
    --mqf-bg: #fff7ed;
    --mqf-text: #1e293b;
}
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
                <tr><td><strong>R9 - Accesibilidad</strong></td><td><span class="badge badge-ok">100%</span></td><td>Modo oscuro para reducción de fatiga visual</td></tr>
                <tr><td><strong>R5 - Datos sensibles</strong></td><td><span class="badge badge-ok">100%</span></td><td>Editor de marca solo para administradores</td></tr>
            </tbody>
        </table>
    </div>

    <div class="footer-doc">
        <p><strong><?php echo $platform_name; ?></strong> - UX Skins | Documentación Técnica</p>
        <p>Clasificación: <strong>INTERNO</strong> | Versión: 1.0</p>
    </div>
</div>

<!-- DOCUMENTO 4: HELPDESK -->
<div class="documento" id="documento4">
    <div class="doc-header">
        <h1><i class="fa-solid fa-headset"></i> Helpdesk e Incidencias - Sistema de Soporte</h1>
        <div class="meta">
            <span><i class="fa-solid fa-building"></i> <?php echo $platform_name; ?></span>
            <span><i class="fa-solid fa-ticket"></i> Ticket System</span>
            <span><i class="fa-solid fa-calendar"></i> <?php echo date('d/m/Y'); ?></span>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-circle-info"></i> Descripción General</h2>
        <p>El Helpdesk es el sistema integrado de gestión de incidencias y soporte técnico de la plataforma. Permite reportar problemas, asignar prioridades con SLA definido, hacer seguimiento de tickets, y mantener historial de conversaciones. Integra con todos los módulos del sistema para reportar problemas específicos con contexto automático.</p>
        
        <div class="status-grid">
            <div class="status-card cumple">
                <div class="label">Estados</div>
                <div class="value">4</div>
                <div class="desc">Abierto, En Proceso, Resuelto, Cerrado</div>
            </div>
            <div class="status-card cumple">
                <div class="label">SLA</div>
                <div class="value">3 niveles</div>
                <div class="desc">Baja 24h, Media 8h, Alta 2h</div>
            </div>
            <div class="status-card cumple">
                <div class="label">Módulos</div>
                <div class="value">7</div>
                <div class="desc">Cotizaciones, Pólizas, Fianzas, etc.</div>
            </div>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-gears"></i> Funcionalidades Principales</h2>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-list"></i> Panel de Incidencias</h4>
            <ul>
                <li><strong>Filtros por estado:</strong>
                    <ul>
                        <li>Todos: Visualización completa</li>
                        <li>Abiertos: Tickets recién creados</li>
                        <li>En Proceso: Tickets siendo atendidos</li>
                        <li>Resueltos: Tickets solucionados</li>
                    </ul>
                </li>
                <li><strong>Lista de tickets:</strong> Muestra título, estado, prioridad, fecha de creación</li>
                <li><strong>Ordenamiento:</strong> Por fecha, prioridad, estado</li>
                <li><strong>Búsqueda:</strong> Por número de ticket, asunto, módulo</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-plus-circle"></i> Reportar Nueva Incidencia</h4>
            <ul>
                <li><strong>Módulo del Sistema Afectado:</strong>
                    <ul>
                        <li>Cotizaciones</li>
                        <li>Emisión Pólizas</li>
                        <li>Fianzas</li>
                        <li>Pagos / Caja</li>
                        <li>Clientes</li>
                        <li>Seguridad / Accesos</li>
                        <li>General / Otro</li>
                    </ul>
                </li>
                <li><strong>Asunto / Título Resumido:</strong> Descripción breve del problema</li>
                <li><strong>Prioridad Inicial:</strong>
                    <ul>
                        <li><strong>Baja (24 horas SLA):</strong> Problemas menores, mejoras</li>
                        <li><strong>Media (8 horas SLA):</strong> Funcionalidad afectada parcialmente</li>
                        <li><strong>Alta (2 horas SLA):</strong> Sistema caído o funcionalidad crítica</li>
                    </ul>
                </li>
                <li><strong>Descripción Detallada del Problema:</strong> Texto libre con detalles</li>
                <li><strong>Adjuntar evidencia:</strong> Capturas de pantalla, logs (opcional)</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-comments"></i> Conversación de Soporte</h4>
            <ul>
                <li><strong>Panel derecho:</strong> Conversación del ticket seleccionado</li>
                <li><strong>Mensajes:</strong> Usuario y soporte técnico pueden comentar</li>
                <li><strong>Timestamp:</strong> Fecha y hora de cada mensaje</li>
                <li><strong>Adjuntos:</strong> Archivos adicionales en la conversación</li>
                <li><strong>Estado:</strong> Actualización de estado en tiempo real</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-clock"></i> Control de SLA</h4>
            <ul>
                <li><strong>Temporizador:</strong> Cuenta regresiva según prioridad</li>
                <li><strong>Alertas:</strong> Notificación cuando SLA está por vencer</li>
                <li><strong>Escalamiento:</strong> Alerta a supervisores si SLA se excede</li>
                <li><strong>Reportes:</strong> Estadísticas de cumplimiento de SLA</li>
            </ul>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-database"></i> Estructura de Datos - Tablas Principales</h2>
        
        <h3>Tabla: incidencias</h3>
        <table>
            <thead>
                <tr>
                    <th>Campo</th>
                    <th>Tipo</th>
                    <th>Descripción</th>
                </tr>
            </thead>
            <tbody>
                <tr><td><code>id</code></td><td>BIGINT AUTO_INCREMENT</td><td>ID único de incidencia</td></tr>
                <tr><td><code>numero_ticket</code></td><td>VARCHAR(20)</td><td>Número de ticket (TKT-2026-XXXX)</td></tr>
                <tr><td><code>usuario_id</code></td><td>INT</td><td>Usuario que reportó</td></tr>
                <tr><td><code>modulo_afectado</code></td><td>VARCHAR(50)</td><td>Módulo del sistema</td></tr>
                <tr><td><code>asunto</code></td><td>VARCHAR(200)</td><td>Título resumido</td></tr>
                <tr><td><code>descripcion</code></td><td>TEXT</td><td>Descripción detallada</td></tr>
                <tr><td><code>prioridad</code></td><td>ENUM('BAJA','MEDIA','ALTA')</td><td>Prioridad del ticket</td></tr>
                <tr><td><code>sla_horas</code></td><td>INT</td><td>Horas de SLA (2, 8, 24)</td></tr>
                <tr><td><code>estado</code></td><td>ENUM('ABIERTO','EN_PROCESO','RESUELTO','CERRADO')</td><td>Estado del ticket</td></tr>
                <tr><td><code>tecnico_asignado_id</code></td><td>INT</td><td>Técnico de soporte asignado</td></tr>
                <tr><td><code>fecha_creacion</code></td><td>DATETIME</td><td>Fecha de creación</td></tr>
                <tr><td><code>fecha_resolucion</code></td><td>DATETIME</td><td>Fecha de resolución</td></tr>
                <tr><td><code>fecha_cierre</code></td><td>DATETIME</td><td>Fecha de cierre</td></tr>
                <tr><td><code>sla_cumplido</code></td><td>BOOLEAN</td><td>Si se cumplió el SLA</td></tr>
            </tbody>
        </table>

        <h3>Tabla: incidencias_mensajes</h3>
        <table>
            <thead>
                <tr>
                    <th>Campo</th>
                    <th>Tipo</th>
                    <th>Descripción</th>
                </tr>
            </thead>
            <tbody>
                <tr><td><code>id</code></td><td>BIGINT AUTO_INCREMENT</td><td>ID único de mensaje</td></tr>
                <tr><td><code>incidencia_id</code></td><td>BIGINT</td><td>Referencia a incidencia</td></tr>
                <tr><td><code>usuario_id</code></td><td>INT</td><td>Usuario que envió el mensaje</td></tr>
                <tr><td><code>mensaje</code></td><td>TEXT</td><td>Contenido del mensaje</td></tr>
                <tr><td><code>es_tecnico</code></td><td>BOOLEAN</td><td>Si es del técnico de soporte</td></tr>
                <tr><td><code>adjunto_ruta</code></td><td>VARCHAR(255)</td><td>Ruta de archivo adjunto</td></tr>
                <tr><td><code>fecha_envio</code></td><td>DATETIME</td><td>Fecha de envío</td></tr>
            </tbody>
        </table>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-calculator"></i> Cálculo de SLA</h2>
        
        <div class="code-block">
// Definición de SLA por prioridad
SLA_ALTA = 2 horas
SLA_MEDIA = 8 horas
SLA_BAJA = 24 horas

// Cálculo de tiempo restante
tiempo_restante = fecha_creacion + sla_horas - NOW()

// Verificación de cumplimiento
sla_cumplido = fecha_resolucion <= (fecha_creacion + sla_horas)

// Ejemplo:
// Ticket creado: 2026-06-18 10:00:00
// Prioridad: ALTA (2 horas SLA)
// Deadline: 2026-06-18 12:00:00
// Resuelto: 2026-06-18 11:30:00
// Resultado: ✅ SLA Cumplido (30 minutos antes)
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
                <tr><td><strong>R4 - Auditoría</strong></td><td><span class="badge badge-ok">100%</span></td><td>Registro completo de incidencias y resoluciones</td></tr>
                <tr><td><strong>R9 - Accesibilidad</strong></td><td><span class="badge badge-ok">100%</span></td><td>Interfaz accesible para reportar problemas</td></tr>
            </tbody>
        </table>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-circle-check"></i> Flujo de Incidencia</h2>
        
        <div class="code-block">
1. Usuario detecta problema en módulo
   ↓
2. Accede a Helpdesk → "Nueva Incidencia"
   ↓
3. Completa formulario:
   - Selecciona módulo afectado
   - Escribe asunto resumido
   - Selecciona prioridad (Baja/Media/Alta)
   - Describe problema en detalle
   ↓
4. Sistema genera ticket (TKT-2026-XXXX)
   - Calcula deadline según SLA
   - Asigna técnico automáticamente (round-robin)
   - Envía notificación al técnico
   ↓
5. Técnico recibe notificación
   - Revisa ticket
   - Cambia estado a "En Proceso"
   - Inicia conversación con usuario
   ↓
6. Resolución:
   - Técnico resuelve problema
   - Cambia estado a "Resuelto"
   - Usuario confirma solución
   - Estado cambia a "Cerrado"
   ↓
7. Sistema registra:
   - Tiempo de resolución
   - Si SLA fue cumplido
   - Estadísticas para reportes
        </div>
    </div>

    <div class="footer-doc">
        <p><strong><?php echo $platform_name; ?></strong> - Helpdesk | Documentación Técnica</p>
        <p>Clasificación: <strong>INTERNO</strong> | Versión: 1.0</p>
    </div>
</div>

<!-- DOCUMENTO 5: FINANCE LAB -->
<div class="documento" id="documento5">
    <div class="doc-header">
        <h1><i class="fa-solid fa-flask"></i> Finance Lab - Laboratorio de Integración</h1>
        <div class="meta">
            <span><i class="fa-solid fa-building"></i> <?php echo $platform_name; ?></span>
            <span><i class="fa-solid fa-vial"></i> Testing Environment</span>
            <span><i class="fa-solid fa-calendar"></i> <?php echo date('d/m/Y'); ?></span>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-circle-info"></i> Descripción General</h2>
        <p>Finance Lab es el laboratorio de pruebas e integración del sistema contable y fiscal. Permite simular generación de NCF, disparar eventos contables para verificar asientos automáticos, y probar la integración entre módulos sin afectar datos de producción. Es una herramienta de desarrollo y QA para validar el correcto funcionamiento del Motor Contable y el sistema de NCF antes de despliegues.</p>
        
        <div class="status-grid">
            <div class="status-card cumple">
                <div class="label">NCF</div>
                <div class="value">3 tipos</div>
                <div class="desc">B01, B02, B14</div>
            </div>
            <div class="status-card cumple">
                <div class="label">Eventos</div>
                <div class="value">3 tipos</div>
                <div class="desc">Póliza, Cobro, Pago Agente</div>
            </div>
            <div class="status-card cumple">
                <div class="label">Ambiente</div>
                <div class="value">Aislado</div>
                <div class="desc">Sin afectar producción</div>
            </div>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-gears"></i> Funcionalidades Principales</h2>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-receipt"></i> Generador de NCF</h4>
            <ul>
                <li><strong>Simulación de solicitud:</strong> Simula la solicitud de un nuevo comprobante fiscal al sistema</li>
                <li><strong>Tipos de Comprobante:</strong>
                    <ul>
                        <li><strong>B01:</strong> Factura de Crédito Fiscal</li>
                        <li><strong>B02:</strong> Factura de Consumo</li>
                        <li><strong>B14:</strong> Regímenes Especiales</li>
                    </ul>
                </li>
                <li><strong>Toggle de Emergencia:</strong> Habilitar/deshabilitar uso de NCF (para pruebas sin generar NCF reales)</li>
                <li><strong>Botón "Generar Siguiente NCF":</strong> Ejecuta la lógica de generación y muestra el resultado</li>
                <li><strong>Resultado:</strong> Muestra el NCF generado o mensaje de error</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-robot"></i> Motor de Asientos (Auto-Asientos)</h4>
            <ul>
                <li><strong>Disparo de eventos:</strong> Simula eventos de negocio para generar representación contable</li>
                <li><strong>Eventos de Negocio disponibles:</strong>
                    <ul>
                        <li><strong>Emisión de Póliza (Seguros/Fianzas):</strong>
                            <ul>
                                <li>Débito: Cuentas por Cobrar</li>
                                <li>Crédito: Ingresos por Primas</li>
                                <li>Crédito: ITBIS por Pagar</li>
                                <li>Crédito: ISC por Pagar</li>
                            </ul>
                        </li>
                        <li><strong>Cobro de Prima (Caja/Bancos):</strong>
                            <ul>
                                <li>Débito: Banco/Caja</li>
                                <li>Crédito: Cuentas por Cobrar</li>
                            </ul>
                        </li>
                        <li><strong>Pago a Agente (Con Retención 10%):</strong>
                            <ul>
                                <li>Débito: Gastos de Comisiones</li>
                                <li>Crédito: Comisiones por Pagar (90%)</li>
                                <li>Crédito: Retención por Pagar (10%)</li>
                            </ul>
                        </li>
                    </ul>
                </li>
                <li><strong>Monto de la Operación (DOP):</strong> Campo para ingresar monto de prueba</li>
                <li><strong>Botón "Disparar Evento Contable":</strong> Ejecuta la generación de asientos</li>
                <li><strong>Resultado:</strong> Muestra los asientos generados con cuentas y montos</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-broom"></i> Control del Entorno</h4>
            <ul>
                <li><strong>Resetear Datos del Lab:</strong> Elimina todos los asientos generados por el laboratorio</li>
                <li><strong>Limpieza de BD:</strong> Borra registros de prueba sin afectar datos de producción</li>
                <li><strong>Aislamiento:</strong> Los datos del lab están marcados con flag "es_prueba = 1"</li>
                <li><strong>Seguridad:</strong> Solo accesible para administradores y desarrolladores</li>
            </ul>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-database"></i> Estructura de Datos - Tablas Utilizadas</h2>
        
        <h3>Tabla: ncf_secuencias (lectura/escritura controlada)</h3>
        <table>
            <thead>
                <tr>
                    <th>Campo</th>
                    <th>Tipo</th>
                    <th>Descripción</th>
                </tr>
            </thead>
            <tbody>
                <tr><td><code>id</code></td><td>INT</td><td>ID único</td></tr>
                <tr><td><code>tipo_comprobante</code></td><td>VARCHAR(3)</td><td>B01, B02, B14</td></tr>
                <tr><td><code>secuencia_actual</code></td><td>VARCHAR(8)</td><td>Último número utilizado</td></tr>
                <tr><td><code>proximo_numero</code></td><td>INT</td><td>Próximo número a utilizar</td></tr>
            </tbody>
        </table>

        <h3>Tabla: asientos_contables (escritura con flag es_prueba)</h3>
        <table>
            <thead>
                <tr>
                    <th>Campo</th>
                    <th>Tipo</th>
                    <th>Descripción</th>
                </tr>
            </thead>
            <tbody>
                <tr><td><code>id</code></td><td>BIGINT</td><td>ID único</td></tr>
                <tr><td><code>numero_asiento</code></td><td>VARCHAR(20)</td><td>Número correlativo</td></tr>
                <tr><td><code>descripcion</code></td><td>TEXT</td><td>Descripción del asiento</td></tr>
                <tr><td><code>es_prueba</code></td><td>BOOLEAN</td><td>Flag de laboratorio (1) o producción (0)</td></tr>
                <tr><td><code>total_debito</code></td><td>DECIMAL(15,2)</td><td>Total débito</td></tr>
                <tr><td><code>total_credito</code></td><td>DECIMAL(15,2)</td><td>Total crédito</td></tr>
            </tbody>
        </table>

        <h3>Tabla: ncf_log_auditoria (escritura con flag es_prueba)</h3>
        <table>
            <thead>
                <tr>
                    <th>Campo</th>
                    <th>Tipo</th>
                    <th>Descripción</th>
                </tr>
            </thead>
            <tbody>
                <tr><td><code>id</code></td><td>BIGINT</td><td>ID único</td></tr>
                <tr><td><code>ncf</code></td><td>VARCHAR(13)</td><td>NCF generado</td></tr>
                <tr><td><code>es_prueba</code></td><td>BOOLEAN</td><td>Flag de laboratorio</td></tr>
                <tr><td><code>fecha_hora</code></td><td>DATETIME</td><td>Fecha/hora de generación</td></tr>
            </tbody>
        </table>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-code"></i> Lógica de Generación de NCF</h2>
        
        <div class="code-block">
// Generación de NCF en Finance Lab
function generarNCF(tipo, habilitar_nsf) {
    if (!habilitar_nsf) {
        return "NCF_DESHABILITADO";
    }
    
    // Obtener secuencia actual
    const secuencia = obtenerSecuencia(tipo);
    
    // Generar siguiente número
    const siguiente = secuencia.proximo_numero + 1;
    
    // Formatear NCF: B02 + 00000001
    const ncf = tipo + siguiente.toString().padStart(8, '0');
    
    // Actualizar secuencia
    actualizarSecuencia(tipo, siguiente);
    
    // Registrar en log (con flag es_prueba = 1)
    registrarLog(ncf, tipo, true);
    
    return ncf;
}

// Ejemplo:
// Tipo: B02
// Secuencia actual: 00000001
// Resultado: B0200000002
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-code"></i> Lógica de Asientos Automáticos</h2>
        
        <div class="code-block">
// Disparo de evento contable
function dispararEvento(evento, monto) {
    let asientos = [];
    
    switch(evento) {
        case 'EMISION_POLIZA':
            asientos = [
                { cuenta: '1.1.2.01.001.000', descripcion: 'Cuentas por Cobrar', debito: monto, credito: 0 },
                { cuenta: '4.1.1.01.001.000', descripcion: 'Ingresos por Primas', debito: 0, credito: monto / 1.16 },
                { cuenta: '2.1.1.01.001.000', descripcion: 'ITBIS por Pagar', debito: 0, credito: monto * 0.16 / 1.16 }
            ];
            break;
            
        case 'COBRO_PRIMA':
            asientos = [
                { cuenta: '1.1.1.01.001.000', descripcion: 'Banco', debito: monto, credito: 0 },
                { cuenta: '1.1.2.01.001.000', descripcion: 'Cuentas por Cobrar', debito: 0, credito: monto }
            ];
            break;
            
        case 'PAGO_AGENTE':
            const comision = monto;
            const retencion = comision * 0.10;
            const neto = comision - retencion;
            
            asientos = [
                { cuenta: '5.1.1.01.001.000', descripcion: 'Gastos de Comisiones', debito: comision, credito: 0 },
                { cuenta: '2.1.2.01.001.000', descripcion: 'Comisiones por Pagar', debito: 0, credito: neto },
                { cuenta: '2.1.3.01.001.000', descripcion: 'Retención por Pagar', debito: 0, credito: retencion }
            ];
            break;
    }
    
    // Guardar asientos con flag es_prueba = 1
    asientos.forEach(a => {
        guardarAsiento(a, true);
    });
    
    return asientos;
}
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
                <tr><td><strong>R6 - NCF</strong></td><td><span class="badge badge-ok">100%</span></td><td>Pruebas de generación de NCF con flag de aislamiento</td></tr>
                <tr><td><strong>R7 - Contable</strong></td><td><span class="badge badge-ok">100%</span></td><td>Validación de asientos automáticos antes de producción</td></tr>
                <tr><td><strong>R4 - Auditoría</strong></td><td><span class="badge badge-ok">100%</span></td><td>Logs de laboratorio separados de producción</td></tr>
            </tbody>
        </table>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-circle-check"></i> Flujo de Prueba en Finance Lab</h2>
        
        <div class="code-block">
1. Administrador/Desarrollador accede a Finance Lab
   ↓
2. Sección "Generador de NCF":
   - Selecciona tipo de comprobante (B01, B02, B14)
   - Activa toggle "Habilitar uso de NCF"
   - Click en "Generar Siguiente NCF"
   - Sistema retorna NCF generado (ej: B0200000002)
   - Registra en ncf_log_auditoria con es_prueba = 1
   ↓
3. Sección "Motor de Asientos":
   - Selecciona evento de negocio
   - Ingresa monto de operación
   - Click en "Disparar Evento Contable"
   - Sistema genera asientos contables
   - Registra en asientos_contables con es_prueba = 1
   ↓
4. Validación:
   - Revisa asientos generados
   - Verifica cuentas débito/crédito
   - Confirma que cuadre (débito = crédito)
   ↓
5. Limpieza:
   - Click en "Resetear Datos del Lab"
   - Sistema elimina todos los registros con es_prueba = 1
   - Base de datos queda limpia para siguiente prueba
        </div>
    </div>

    <div class="footer-doc">
        <p><strong><?php echo $platform_name; ?></strong> - Finance Lab | Documentación Técnica</p>
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
        '📄 Gestión de Usuarios',
        '📄 Verificar Pago',
        '📄 UX Skins',
        '📄 Helpdesk',
        ' Finance Lab'
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
            'Gestion_Usuarios',
            'Verificar_Pago',
            'UX_Skins',
            'Helpdesk',
            'Finance_Lab'
        ];
        
        doc.save('Documentacion_Plataforma_Parte3_' + nombres[documentoActivo - 1] + '.pdf');
        
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