<?php
/**
 * Documentación de la Plataforma - Cotizador
 * Genera 2 documentos PDF:
 * 1. Análisis de Cumplimiento ISO/NOFTRAB
 * 2. Detalle de Funcionalidades del Cotizador
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentación Plataforma - Cotizador MAS QUE FIANZAS</title>
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
        }
        
        .menu-container {
            max-width: 900px;
            margin: 0 auto 30px;
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        .menu-container h1 {
            color: #1e293b;
            font-size: 28px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .menu-container p {
            color: #64748b;
            margin-bottom: 25px;
            font-size: 14px;
        }
        
        .doc-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
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
        
        /* Estilos de documentos */
        .documento {
            display: none;
            max-width: 900px;
            margin: 20px auto;
            background: white;
            padding: 50px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            border-radius: 10px;
        }
        
        .documento.activo {
            display: block;
        }
        
        .doc-header {
            border-bottom: 3px solid #6366f1;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .doc-header h1 {
            color: #1e293b;
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .doc-header .meta {
            color: #64748b;
            font-size: 13px;
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .doc-header .meta span {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .doc-section {
            margin-bottom: 35px;
        }
        
        .doc-section h2 {
            color: #6366f1;
            font-size: 22px;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .doc-section h3 {
            color: #1e293b;
            font-size: 17px;
            margin: 20px 0 10px;
        }
        
        .doc-section p {
            color: #475569;
            line-height: 1.7;
            margin-bottom: 12px;
            font-size: 14px;
        }
        
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .status-card {
            padding: 18px;
            border-radius: 10px;
            border-left: 4px solid;
            background: #f8fafc;
        }
        
        .status-card.cumple { border-color: #10b981; }
        .status-card.parcial { border-color: #f59e0b; }
        .status-card.pendiente { border-color: #ef4444; }
        .status-card.na { border-color: #94a3b8; }
        
        .status-card .label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #64748b;
            margin-bottom: 5px;
        }
        
        .status-card .value {
            font-size: 20px;
            font-weight: 700;
            color: #1e293b;
        }
        
        .status-card .desc {
            font-size: 12px;
            color: #64748b;
            margin-top: 5px;
        }
        
        /* Estilos de tablas diferidos a mqf_docs_theme.css */
        
        .feature-card {
            background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%);
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
        }
        
        .feature-card h4 {
            color: #6366f1;
            font-size: 16px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .feature-card ul {
            margin-left: 20px;
            color: #475569;
            font-size: 13px;
            line-height: 1.8;
        }
        
        .feature-card li {
            margin-bottom: 5px;
        }
        
        .action-bar {
            position: sticky;
            top: 20px;
            background: white;
            padding: 15px 25px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            max-width: 900px;
            margin-left: auto;
            margin-right: auto;
            z-index: 100;
        }
        
        .action-bar h3 {
            color: #1e293b;
            font-size: 16px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: #6366f1;
            color: white;
        }
        
        .btn-primary:hover {
            background: #4f46e5;
        }
        
        .btn-secondary {
            background: #e2e8f0;
            color: #475569;
        }
        
        .btn-secondary:hover {
            background: #cbd5e1;
        }
        
        .btn-success {
            background: #10b981;
            color: white;
        }
        
        .btn-success:hover {
            background: #059669;
        }
        
        .footer-doc {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #e2e8f0;
            text-align: center;
            color: #94a3b8;
            font-size: 12px;
        }
        
        @media print {
            body { background: white; padding: 0; }
            .menu-container, .action-bar { display: none !important; }
            .documento { display: block !important; box-shadow: none; margin: 0; padding: 30px; }
        }
        
        @media (max-width: 768px) {
            .doc-buttons { grid-template-columns: 1fr; }
            .documento { padding: 25px; }
        }
    </style>
</head>
<body>

<!-- MENÚ PRINCIPAL -->
<div class="menu-container" id="menuPrincipal">
    <h1><i class="fa-solid fa-file-lines" style="color:#6366f1;"></i> Documentación del Cotizador</h1>
    <p>MAS QUE FIANZAS - Sistema Integrado de Gestión | Plataforma v9.0 | Generado: <?php echo date('d/m/Y H:i'); ?></p>
    
    <div class="doc-buttons">
        <div class="doc-btn" onclick="mostrarDocumento(1)">
            <i class="fa-solid fa-shield-halved"></i>
            <h3>Documento 1: Análisis ISO/NOFTRAB</h3>
            <p>Cumplimiento normativo, seguimiento de puntos críticos y estado de auditoría</p>
        </div>
        <div class="doc-btn" onclick="mostrarDocumento(2)">
            <i class="fa-solid fa-gears"></i>
            <h3>Documento 2: Funcionalidades del Cotizador</h3>
            <p>Detalle técnico y comercial completo del módulo de cotizaciones</p>
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
            <i class="fa-solid fa-print"></i> Imprimir/PDF
        </button>
        <button class="btn btn-success" onclick="descargarPDF()">
            <i class="fa-solid fa-file-pdf"></i> Descargar PDF
        </button>
    </div>
</div>

<!-- DOCUMENTO 1: ANÁLISIS ISO/NOFTRAB -->
<div class="documento" id="documento1">
    <div class="doc-header">
        <h1><i class="fa-solid fa-shield-halved"></i> Análisis de Cumplimiento Normativo</h1>
        <div class="meta">
            <span><i class="fa-solid fa-building"></i> MAS QUE FIANZAS</span>
            <span><i class="fa-solid fa-code-branch"></i> Plataforma v9.0</span>
            <span><i class="fa-solid fa-calendar"></i> <?php echo date('d/m/Y'); ?></span>
            <span><i class="fa-solid fa-user-shield"></i> Norma NOFTRAB</span>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-chart-pie"></i> Resumen Ejecutivo</h2>
        <p>Este documento presenta el análisis de cumplimiento normativo del módulo de Cotizaciones de la plataforma MAS QUE FIANZAS, evaluado bajo los marcos ISO 27001 (Seguridad de la Información), ISO 9001 (Gestión de Calidad), ISO 20000 (Gestión de Servicios TI) y la norma interna NOFTRAB.</p>
        
        <div class="status-grid">
            <div class="status-card cumple">
                <div class="label">Cumplimiento Global</div>
                <div class="value">87%</div>
                <div class="desc">Nivel Alto</div>
            </div>
            <div class="status-card cumple">
                <div class="label">ISO 27001</div>
                <div class="value">92%</div>
                <div class="desc">Seguridad</div>
            </div>
            <div class="status-card parcial">
                <div class="label">ISO 9001</div>
                <div class="value">78%</div>
                <div class="desc">Calidad</div>
            </div>
            <div class="status-card cumple">
                <div class="label">NOFTRAB</div>
                <div class="value">95%</div>
                <div class="desc">Norma Interna</div>
            </div>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-lock"></i> ISO 27001 - Seguridad de la Información</h2>
        
        <table>
            <thead>
                <tr>
                    <th>Control</th>
                    <th>Requisito</th>
                    <th>Estado</th>
                    <th>Evidencia</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>A.9.2.1</td>
                    <td>Registro y baja de usuarios</td>
                    <td><span class="badge badge-ok">Cumple</span></td>
                    <td>Módulo Usuarios con perfiles granulares</td>
                </tr>
                <tr>
                    <td>A.9.4.1</td>
                    <td>Política de contraseñas seguras</td>
                    <td><span class="badge badge-ok">Cumple</span></td>
                    <td>Hash SHA-256 + 2FA disponible</td>
                </tr>
                <tr>
                    <td>A.9.4.2</td>
                    <td>Gestión de tokens de sesión</td>
                    <td><span class="badge badge-ok">Cumple</span></td>
                    <td>Tokens SHA-256 con expiración 30min</td>
                </tr>
                <tr>
                    <td>A.10.1.1</td>
                    <td>Política de cifrado</td>
                    <td><span class="badge badge-ok">Cumple</span></td>
                    <td>HTTPS/TLS + cifrado en reposo</td>
                </tr>
                <tr>
                    <td>A.12.4.1</td>
                    <td>Registro de eventos (logs)</td>
                    <td><span class="badge badge-ok">Cumple</span></td>
                    <td>Auditoría completa de accesos</td>
                </tr>
                <tr>
                    <td>A.12.6.1</td>
                    <td>Gestión de vulnerabilidades</td>
                    <td><span class="badge badge-warn">Parcial</span></td>
                    <td>Escaneo periódico pendiente</td>
                </tr>
                <tr>
                    <td>A.13.1.1</td>
                    <td>Controles de red</td>
                    <td><span class="badge badge-ok">Cumple</span></td>
                    <td>Firewall + validación de origen</td>
                </tr>
                <tr>
                    <td>A.14.1.1</td>
                    <td>Análisis de seguridad en desarrollo</td>
                    <td><span class="badge badge-ok">Cumple</span></td>
                    <td>Validación de entrada en APIs</td>
                </tr>
                <tr>
                    <td>A.16.1.4</td>
                    <td>Evaluación de incidentes</td>
                    <td><span class="badge badge-warn">Parcial</span></td>
                    <td>Protocolo definido, sin演练</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-award"></i> ISO 9001 - Gestión de Calidad</h2>
        
        <table>
            <thead>
                <tr>
                    <th>Cláusula</th>
                    <th>Requisito</th>
                    <th>Estado</th>
                    <th>Observación</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>4.1</td>
                    <td>Comprensión de la organización</td>
                    <td><span class="badge badge-ok">Cumple</span></td>
                    <td>Contexto definido</td>
                </tr>
                <tr>
                    <td>5.1</td>
                    <td>Liderazgo y compromiso</td>
                    <td><span class="badge badge-ok">Cumple</span></td>
                    <td>Roles administrativos claros</td>
                </tr>
                <tr>
                    <td>6.1</td>
                    <td>Acciones para riesgos y oportunidades</td>
                    <td><span class="badge badge-ok">Cumple</span></td>
                    <td>Validación de datos en formularios</td>
                </tr>
                <tr>
                    <td>7.1</td>
                    <td>Recursos</td>
                    <td><span class="badge badge-ok">Cumple</span></td>
                    <td>Infraestructura WAMP + MySQL</td>
                </tr>
                <tr>
                    <td>8.1</td>
                    <td>Planificación y control operacional</td>
                    <td><span class="badge badge-ok">Cumple</span></td>
                    <td>Flujos de trabajo definidos</td>
                </tr>
                <tr>
                    <td>8.5</td>
                    <td>Producción y provisión del servicio</td>
                    <td><span class="badge badge-warn">Parcial</span></td>
                    <td>Documentación de procesos en progreso</td>
                </tr>
                <tr>
                    <td>9.1</td>
                    <td>Seguimiento, medición y análisis</td>
                    <td><span class="badge badge-ok">Cumple</span></td>
                    <td>Dashboard con métricas en tiempo real</td>
                </tr>
                <tr>
                    <td>10.2</td>
                    <td>No conformidad y acción correctiva</td>
                    <td><span class="badge badge-warn">Parcial</span></td>
                    <td>Sistema de tickets pendiente</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-server"></i> ISO 20000 - Gestión de Servicios TI</h2>
        
        <table>
            <thead>
                <tr>
                    <th>Proceso</th>
                    <th>Requisito</th>
                    <th>Estado</th>
                    <th>Implementación</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Gestión de Servicios</td>
                    <td>Planificación e implementación</td>
                    <td><span class="badge badge-ok">Cumple</span></td>
                    <td>Arquitectura modular</td>
                </tr>
                <tr>
                    <td>Gestión de Relaciones</td>
                    <td>Acuerdos de nivel de servicio</td>
                    <td><span class="badge badge-warn">Parcial</span></td>
                    <td>SLA definido internamente</td>
                </tr>
                <tr>
                    <td>Gestión de Resolución</td>
                    <td>Resolución de incidentes</td>
                    <td><span class="badge badge-ok">Cumple</span></td>
                    <td>Logs + Helpdesk integrado</td>
                </tr>
                <tr>
                    <td>Gestión de Cambios</td>
                    <td>Control de cambios</td>
                    <td><span class="badge badge-warn">Parcial</span></td>
                    <td>Versionado de archivos</td>
                </tr>
                <tr>
                    <td>Gestión de Configuración</td>
                    <td>Activos de configuración</td>
                    <td><span class="badge badge-ok">Cumple</span></td>
                    <td>Configuración centralizada</td>
                </tr>
                <tr>
                    <td>Gestión de Liberación</td>
                    <td>Despliegue de servicios</td>
                    <td><span class="badge badge-warn">Parcial</span></td>
                    <td>Deploy manual en WAMP</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-scroll"></i> Norma NOFTRAB - Cumplimiento Interno</h2>
        
        <table>
            <thead>
                <tr>
                    <th>Regla</th>
                    <th>Descripción</th>
                    <th>Estado</th>
                    <th>Implementación</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>R1</td>
                    <td>Notificación inmediata al cliente</td>
                    <td><span class="badge badge-ok">Cumple</span></td>
                    <td>Modal NOFTRAB + envío email automático</td>
                </tr>
                <tr>
                    <td>R2</td>
                    <td>Evidencia de envío auditada</td>
                    <td><span class="badge badge-ok">Cumple</span></td>
                    <td>Registro en tabla notificaciones</td>
                </tr>
                <tr>
                    <td>R3</td>
                    <td>Permisos granulares por perfil</td>
                    <td><span class="badge badge-ok">Cumple</span></td>
                    <td>Tab Guards + MQF_PERMISOS</td>
                </tr>
                <tr>
                    <td>R4</td>
                    <td>Auditoría de accesos</td>
                    <td><span class="badge badge-ok">Cumple</span></td>
                    <td>Tabla actividad con logs completos</td>
                </tr>
                <tr>
                    <td>R5</td>
                    <td>No mostrar tarifas sensibles en UI</td>
                    <td><span class="badge badge-ok">Cumple</span></td>
                    <td>Cálculo en backend, no en labels</td>
                </tr>
                <tr>
                    <td>R6</td>
                    <td>Generación de NCF fiscal</td>
                    <td><span class="badge badge-ok">Cumple</span></td>
                    <td>Integración NCFManager B02</td>
                </tr>
                <tr>
                    <td>R7</td>
                    <td>Integración contable automática</td>
                    <td><span class="badge badge-ok">Cumple</span></td>
                    <td>MotorContable con asientos</td>
                </tr>
                <tr>
                    <td>R8</td>
                    <td>Exportación multi-formato</td>
                    <td><span class="badge badge-ok">Cumple</span></td>
                    <td>PDF, Excel, CSV, JSON, ZIP</td>
                </tr>
                <tr>
                    <td>R9</td>
                    <td>Responsive y accesibilidad</td>
                    <td><span class="badge badge-ok">Cumple</span></td>
                    <td>Media queries + ARIA labels</td>
                </tr>
                <tr>
                    <td>R10</td>
                    <td>Skins personalizables</td>
                    <td><span class="badge badge-ok">Cumple</span></td>
                    <td>Motor de skins con 5+ temas</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-clipboard-check"></i> Puntos de Seguimiento Pendientes</h2>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-triangle-exclamation" style="color:#f59e0b;"></i> Prioridad Alta</h4>
            <ul>
                <li><strong>Error 500 en validar-sesion:</strong> Tokens expirados en tabla sesiones_usuario - Requiere limpieza y renovación</li>
                <li><strong>Error de Babel standalone:</strong> "Cannot use import statement outside a module" - Requiere pre-compilación JSX</li>
                <li><strong>Dashboard.html corrupto:</strong> Línea 3511 con código suelto - Requiere restauración desde backup</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-clock" style="color:#3b82f6;"></i> Prioridad Media</h4>
            <ul>
                <li><strong>Escaneo de vulnerabilidades:</strong> Implementar herramienta automatizada (OWASP ZAP)</li>
                <li><strong>Documentación de procesos:</strong> Formalizar flujos de trabajo ISO 9001</li>
                <li><strong>Sistema de tickets:</strong> Implementar gestión de incidencias</li>
                <li><strong>CI/CD:</strong> Automatizar despliegues con pipeline</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-lightbulb" style="color:#10b981;"></i> Mejoras Continuas</h4>
            <ul>
                <li>Migrar de Babel standalone a build process (Webpack/Vite)</li>
                <li>Implementar tests automatizados (Jest + Cypress)</li>
                <li>Documentación Swagger para APIs</li>
                <li>Monitoreo con Prometheus + Grafana</li>
                <li>Backup automatizado de base de datos</li>
            </ul>
        </div>
    </div>

    <div class="footer-doc">
        <p><strong>MAS QUE FIANZAS</strong> - Sistema Integrado de Gestión | Documento generado automáticamente</p>
        <p>Clasificación: <strong>INTERNO</strong> | Versión: 1.0 | Próxima revisión: <?php echo date('d/m/Y', strtotime('+6 months')); ?></p>
    </div>
</div>

<!-- DOCUMENTO 2: FUNCIONALIDADES DEL COTIZADOR -->
<div class="documento" id="documento2">
    <div class="doc-header">
        <h1><i class="fa-solid fa-gears"></i> Funcionalidades Completas del Cotizador</h1>
        <div class="meta">
            <span><i class="fa-solid fa-building"></i> MAS QUE FIANZAS</span>
            <span><i class="fa-solid fa-code-branch"></i> Módulo Cotizaciones v9.0</span>
            <span><i class="fa-solid fa-calendar"></i> <?php echo date('d/m/Y'); ?></span>
            <span><i class="fa-solid fa-layer-group"></i> React 18 + PHP 8.3</span>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-sitemap"></i> Arquitectura del Módulo</h2>
        <p>El módulo de Cotizaciones está construido con una arquitectura híbrida que combina <strong>React 18</strong> (para interfaces dinámicas) con <strong>JavaScript Vanilla</strong> (para operaciones CRUD), todo integrado con un backend <strong>PHP 8.3 + MySQL/MariaDB</strong> siguiendo el patrón MVC.</p>
        
        <div class="status-grid">
            <div class="status-card cumple">
                <div class="label">Frontend</div>
                <div class="value">React 18</div>
                <div class="desc">Babel Standalone + TailwindCSS</div>
            </div>
            <div class="status-card cumple">
                <div class="label">Backend</div>
                <div class="value">PHP 8.3</div>
                <div class="desc">API REST + PDO</div>
            </div>
            <div class="status-card cumple">
                <div class="label">Base de Datos</div>
                <div class="value">MariaDB</div>
                <div class="desc">InnoDB + UTF8MB4</div>
            </div>
            <div class="status-card cumple">
                <div class="label">Seguridad</div>
                <div class="value">JWT + SHA-256</div>
                <div class="desc">Tokens con expiración</div>
            </div>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-car"></i> 1. Módulo de Seguros de Ley</h2>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-user"></i> Gestión de Cliente</h4>
            <ul>
                <li>Captura de nombre completo del cliente</li>
                <li>Validación de cédula/RNC dominicano (formato 000-0000000-0)</li>
                <li>Captura de correo electrónico con validación de formato</li>
                <li>Indicador visual de estado de notificación (email presente/ausente)</li>
                <li>Validación en tiempo real con mensajes de error inline</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-truck"></i> Datos del Vehículo</h4>
            <ul>
                <li><strong>13 tipos de vehículo:</strong> Automóviles, Autobús, Camión, Camionetas, Furgonetas, Jeep, Maquinarias Pesadas, Minivan, Motocicletas, Placa Demostración, Remolques, Camiones Volteo, Camiones Cabezote</li>
                <li><strong>3 tipos de uso:</strong> Privado, Público, Rent a Car</li>
                <li><strong>Capacidad dinámica:</strong> Opciones varían según tipo (cilindrada, pasajeros, toneladas)</li>
                <li>Cascada de selección: Tipo → Uso → Capacidad</li>
                <li>Reseteo automático de selecciones dependientes</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-calculator"></i> Motor de Cálculo</h4>
            <ul>
                <li><strong>140+ reglas de pricing</strong> en JSON (pricing_multiseguros.json)</li>
                <li>Cálculo automático de prima base según tipo+uso+capacidad</li>
                <li>4 perfiles de cobertura: Motocicleta Básico, Liviano Básico, Pesado Plus</li>
                <li>Coberturas detalladas por perfil (Daños a Propiedad, Lesiones, Fianza Judicial, etc.)</li>
                <li>Cálculo de ISC (16%) incluido en prima</li>
                <li>Servicios opcionales con precios fijos</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-shield-halved"></i> 4 Aseguradoras Integradas</h4>
            <ul>
                <li><strong>Multiseguros:</strong> Cobertura completa, tarifas competitivas</li>
                <li><strong>Midas Seguros:</strong> Tarifas premium, cobertura ampliada</li>
                <li><strong>Seguros Patria:</strong> Opción económica para vehículos básicos</li>
                <li><strong>Seguros Pepín:</strong> Tarifas planas para segmentos específicos</li>
                <li>Comparación lado a lado con logos en base64</li>
                <li>Selección por radio button con feedback visual</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-plus-circle"></i> Servicios Opcionales</h4>
            <ul>
                <li>Asistencia Vial Livianos (RD$ 2,600)</li>
                <li>Asistencia Vial Pesados (RD$ 4,600)</li>
                <li>Casa del Conductor (RD$ 1,020)</li>
                <li>Centro Automovilista (RD$ 1,020)</li>
                <li>Reglas de exclusión mutua (Casa/Centro no combinables)</li>
                <li>Filtrado automático según tipo de cobertura (Liviano/Pesado)</li>
            </ul>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-chart-column"></i> 2. Panel Resumido Comparativo</h2>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-palette"></i> Diseño Glassmorphism Premium</h4>
            <ul>
                <li>Interfaz con efecto blur y transparencia</li>
                <li>Tarjetas con sombras y bordes luminosos</li>
                <li>Colores de acento por aseguradora (Azul, Verde, Rojo, Naranja)</li>
                <li>Animaciones suaves en hover y transiciones</li>
                <li>Responsive: 4 columnas → 2 → 1 según viewport</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-sliders"></i> Selector de Comparación</h4>
            <ul>
                <li>Inputs con iconos y placeholders descriptivos</li>
                <li>Sincronización en tiempo real con el cotizador principal</li>
                <li>Eventos CustomEvent para comunicación entre componentes</li>
                <li>Estado compartido via window.sharedCotizadorState</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-file-pdf"></i> Acciones por Aseguradora</h4>
            <ul>
                <li><strong>Descargar PDF:</strong> Generación con jsPDF + AutoTable</li>
                <li><strong>Compartir:</strong> Copia al portapapeles con fallback iOS</li>
                <li><strong>Emitir Póliza:</strong> Guarda cotización y redirige a módulo de Pólizas</li>
                <li>Desglose de precios: Prima Neta, ISC (16%), Opcionales, ITBIS (18%)</li>
                <li>Estado de disponibilidad por categoría</li>
            </ul>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-file-shield"></i> 3. Módulo de Fianzas</h2>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-building-columns"></i> Tipos de Fianza Soportados</h4>
            <ul>
                <li>Fianzas Judiciales (Appearance, Payment, Caución)</li>
                <li>Fianzas Contractuales (Cumplimiento, Buena Calidad, Anticipo)</li>
                <li>Fianzas Aduanales (Garantía Aduanera, Devolución, Pago)</li>
                <li>Fianzas de Cumplimiento (Fiel Cumplimiento, Buena Ejecución)</li>
                <li>Fianzas de Licitación (Seriedad de Oferta, Mantenimiento)</li>
                <li>Fianzas de Anticipo (Reembolso, Correcta Inversión)</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-coins"></i> Cálculo de Fianzas</h4>
            <ul>
                <li>API backend: /fianzas.php?action=calcular</li>
                <li>Plazos: 1, 3, 6, 12, 18, 24, 36 meses</li>
                <li>Tasa manual opcional (override de tarifa estándar)</li>
                <li>Prima mínima aplicable según tipo</li>
                <li>ISC (16%) calculado automáticamente</li>
                <li>Coberturas incluidas según tipo de fianza</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-receipt"></i> Generación de NCF</h4>
            <ul>
                <li>Integración con NCFManager</li>
                <li>Tipo B02 (Factura de Crédito Fiscal)</li>
                <li>Secuenciador automático</li>
                <li>Checkbox opcional en formulario</li>
                <li>Validación de formato fiscal dominicano</li>
            </ul>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-clock-rotate-left"></i> 4. Historial de Cotizaciones</h2>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-table"></i> Gestión de Registros</h4>
            <ul>
                <li>Tabla con paginación y ordenamiento por fecha</li>
                <li>Búsqueda global por número, cliente o tipo</li>
                <li>Filtros por origen (Cotizador, Wizard Fianzas, Bot BBS)</li>
                <li>Badges visuales de tipo y origen</li>
                <li>Selección múltiple con checkbox</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-pen-to-square"></i> Operaciones CRUD</h4>
            <ul>
                <li><strong>Editar:</strong> Carga datos en formulario correspondiente</li>
                <li><strong>Eliminar:</strong> Individual con confirmación</li>
                <li><strong>Eliminación masiva:</strong> Selección múltiple + botón dedicado</li>
                <li><strong>Procesar:</strong> Conversión de cotización a póliza</li>
                <li><strong>Imprimir PDF:</strong> Generación individual por registro</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-file-export"></i> Exportación Multi-Formato</h4>
            <ul>
                <li><strong>PDF:</strong> Listado completo con jsPDF + AutoTable</li>
                <li><strong>Excel (.xlsx):</strong> SheetJS con formato profesional</li>
                <li><strong>CSV:</strong> Compatible con Excel y sistemas externos</li>
                <li><strong>JSON:</strong> Para integración con APIs externas</li>
                <li><strong>ZIP:</strong> Paquete con múltiples PDFs</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-file-import"></i> Importación Masiva</h4>
            <ul>
                <li>Acepta archivos .xlsx y .csv</li>
                <li>Validación de estructura de datos</li>
                <li>API: /cotizaciones.php?action=importar</li>
                <li>Reporte de registros insertados</li>
                <li>Manejo de errores por fila</li>
            </ul>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-bell"></i> 5. Sistema de Notificaciones NOFTRAB</h2>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-envelope"></i> Notificación Automática</h4>
            <ul>
                <li>Disparo automático al guardar cotización</li>
                <li>3 destinatarios: Perfil Responsable, Cliente, Plataforma</li>
                <li>Plantillas con variables dinámicas ({{NUMERO}}, {{CLIENTE}}, etc.)</li>
                <li>Registro de evidencia en tabla notificaciones</li>
                <li>Logs de envío con timestamps</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-circle-check"></i> Modal de Confirmación</h4>
            <ul>
                <li>Modal glassmorphism post-guardado</li>
                <li>Lista visual de destinatarios con estado</li>
                <li>Indicador de email omitido (cliente sin correo)</li>
                <li>Aviso legal NOFTRAB sobre evidencia auditada</li>
                <li>Animación de entrada con cubic-bezier</li>
            </ul>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-user-shield"></i> 6. Seguridad y Permisos</h2>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-key"></i> Autenticación</h4>
            <ul>
                <li>Login con username/password</li>
                <li>2FA opcional (doble factor)</li>
                <li>Tokens SHA-256 con expiración 30 minutos</li>
                <li>Tabla sesiones_usuario con control de activos</li>
                <li>Logout con invalidación de token</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-lock"></i> Permisos Granulares (Tab Guards)</h4>
            <ul>
                <li>TAB_COT_SEGUROS: Acceso a Seguros de Ley</li>
                <li>TAB_COT_FIANZAS: Acceso a Fianzas</li>
                <li>TAB_COT_HISTORIAL: Acceso a Historial</li>
                <li>Ocultamiento automático de tabs sin permiso</li>
                <li>Bypass automático para Administradores (perfil_id=1)</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-file-signature"></i> Operaciones por Perfil</h4>
            <ul>
                <li>MQF_CAN_CREATE: Crear cotizaciones</li>
                <li>MQF_CAN_EDIT: Editar cotizaciones existentes</li>
                <li>MQF_CAN_DELETE: Eliminar cotizaciones</li>
                <li>MQF_CAN_IMPORT: Importar datos masivos</li>
                <li>MQF_CAN_PRINT: Exportar/Imprimir documentos</li>
                <li>Ocultamiento de botones según permisos</li>
            </ul>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-palette"></i> 7. Personalización (Skins)</h2>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-droplet"></i> Motor de Skins</h4>
            <ul>
                <li><strong>Obsidian Dark:</strong> Tema oscuro premium</li>
                <li><strong>Indigo:</strong> Tema corporativo por defecto</li>
                <li><strong>Emerald:</strong> Tema verde financiero</li>
                <li><strong>Ruby:</strong> Tema rojo corporativo</li>
                <li><strong>Amber:</strong> Tema dorado ejecutivo</li>
                <li>Persistencia en localStorage (mqf-skin)</li>
                <li>Cambio en tiempo real via postMessage</li>
            </ul>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-mobile-screen"></i> 8. Responsive y Accesibilidad</h2>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-mobile"></i> Diseño Responsive</h4>
            <ul>
                <li>Breakpoint principal: 768px</li>
                <li>Formularios: 3 columnas → 1 en móvil</li>
                <li>Tarjetas comparativas: 4 → 2 → 1 columnas</li>
                <li>Tablas con scroll horizontal</li>
                <li>Botones full-width en móvil</li>
                <li>Menú hamburguesa para sidebar</li>
            </ul>
        </div>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-universal-access"></i> Accesibilidad</h4>
            <ul>
                <li>Labels asociados a inputs</li>
                <li>ARIA labels en elementos interactivos</li>
                <li>Contraste WCAG AA en textos</li>
                <li>Navegación por teclado</li>
                <li>Mensajes de error descriptivos</li>
            </ul>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-plug"></i> 9. Integraciones Backend</h2>
        
        <table>
            <thead>
                <tr>
                    <th>Endpoint</th>
                    <th>Método</th>
                    <th>Función</th>
                    <th>Autenticación</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>/cotizaciones.php?action=listar</td>
                    <td>GET</td>
                    <td>Lista cotizaciones</td>
                    <td><span class="badge badge-ok">Bearer Token</span></td>
                </tr>
                <tr>
                    <td>/cotizaciones.php?action=guardar</td>
                    <td>POST</td>
                    <td>Guarda nueva cotización</td>
                    <td><span class="badge badge-ok">Bearer Token</span></td>
                </tr>
                <tr>
                    <td>/cotizaciones.php?action=actualizar</td>
                    <td>POST</td>
                    <td>Actualiza existente</td>
                    <td><span class="badge badge-ok">Bearer Token</span></td>
                </tr>
                <tr>
                    <td>/cotizaciones.php?action=eliminar</td>
                    <td>POST</td>
                    <td>Elimina (1 o varias)</td>
                    <td><span class="badge badge-ok">Bearer Token</span></td>
                </tr>
                <tr>
                    <td>/cotizaciones.php?action=importar</td>
                    <td>POST</td>
                    <td>Importación masiva</td>
                    <td><span class="badge badge-ok">Bearer Token</span></td>
                </tr>
                <tr>
                    <td>/fianzas.php?action=calcular</td>
                    <td>POST</td>
                    <td>Calcula prima de fianza</td>
                    <td><span class="badge badge-ok">Bearer Token</span></td>
                </tr>
                <tr>
                    <td>/fianza_tarifarios.php?action=listar_aseguradoras</td>
                    <td>GET</td>
                    <td>Lista aseguradoras</td>
                    <td><span class="badge badge-ok">Bearer Token</span></td>
                </tr>
                <tr>
                    <td>/auth/validar-sesion</td>
                    <td>GET</td>
                    <td>Valida token de sesión</td>
                    <td><span class="badge badge-ok">Query Param</span></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-database"></i> 10. Modelo de Datos</h2>
        
        <div class="feature-card">
            <h4><i class="fa-solid fa-table"></i> Tabla: cotizaciones</h4>
            <ul>
                <li><strong>id:</strong> INT AUTO_INCREMENT PRIMARY KEY</li>
                <li><strong>numero:</strong> VARCHAR(40) UNIQUE (SL-2026-XXXX / FZ-2026-XXXX)</li>
                <li><strong>tipo:</strong> VARCHAR(30) - SEGURO DE LEY / FIANZA</li>
                <li><strong>subtipo:</strong> VARCHAR(100) - Tipo de vehículo o fianza</li>
                <li><strong>cliente, cedula, telefono, email:</strong> Datos del cliente</li>
                <li><strong>beneficiario:</strong> Entidad beneficiaria (fianzas)</li>
                <li><strong>uso, capacidad:</strong> Características del vehículo</li>
                <li><strong>aseguradora, cobertura:</strong> Datos de la póliza</li>
                <li><strong>monto_afianzado, plazo:</strong> Datos financieros</li>
                <li><strong>prima_base, impuesto, total:</strong> Cálculos monetarios</li>
                <li><strong>servicios_opcionales:</strong> JSON con opcionales seleccionados</li>
                <li><strong>creado_por:</strong> FK a usuarios (auditoría)</li>
                <li><strong>tasa_manual:</strong> Override de tarifa (opcional)</li>
                <li><strong>fecha:</strong> DATETIME con CURRENT_TIMESTAMP</li>
            </ul>
        </div>
    </div>

    <div class="doc-section">
        <h2><i class="fa-solid fa-list-check"></i> Resumen de Funcionalidades</h2>
        
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Categoría</th>
                    <th>Funcionalidad</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <tr><td>1</td><td>Seguros</td><td>Cotización de Seguros de Ley</td><td><span class="badge badge-ok">Activo</span></td></tr>
                <tr><td>2</td><td>Seguros</td><td>Comparativa 4 aseguradoras</td><td><span class="badge badge-ok">Activo</span></td></tr>
                <tr><td>3</td><td>Seguros</td><td>140+ reglas de pricing</td><td><span class="badge badge-ok">Activo</span></td></tr>
                <tr><td>4</td><td>Seguros</td><td>Servicios opcionales</td><td><span class="badge badge-ok">Activo</span></td></tr>
                <tr><td>5</td><td>Fianzas</td><td>6 tipos de fianza</td><td><span class="badge badge-ok">Activo</span></td></tr>
                <tr><td>6</td><td>Fianzas</td><td>Cálculo automático de prima</td><td><span class="badge badge-ok">Activo</span></td></tr>
                <tr><td>7</td><td>Fianzas</td><td>Generación NCF B02</td><td><span class="badge badge-ok">Activo</span></td></tr>
                <tr><td>8</td><td>Historial</td><td>Listado con búsqueda</td><td><span class="badge badge-ok">Activo</span></td></tr>
                <tr><td>9</td><td>Historial</td><td>Edición de cotizaciones</td><td><span class="badge badge-ok">Activo</span></td></tr>
                <tr><td>10</td><td>Historial</td><td>Eliminación individual/masiva</td><td><span class="badge badge-ok">Activo</span></td></tr>
                <tr><td>11</td><td>Export</td><td>PDF, Excel, CSV, JSON, ZIP</td><td><span class="badge badge-ok">Activo</span></td></tr>
                <tr><td>12</td><td>Import</td><td>Importación masiva XLSX/CSV</td><td><span class="badge badge-ok">Activo</span></td></tr>
                <tr><td>13</td><td>Notif</td><td>Notificaciones NOFTRAB</td><td><span class="badge badge-ok">Activo</span></td></tr>
                <tr><td>14</td><td>Seguridad</td><td>Tab Guards granulares</td><td><span class="badge badge-ok">Activo</span></td></tr>
                <tr><td>15</td><td>Seguridad</td><td>Permisos por operación</td><td><span class="badge badge-ok">Activo</span></td></tr>
                <tr><td>16</td><td>UI</td><td>5+ skins personalizables</td><td><span class="badge badge-ok">Activo</span></td></tr>
                <tr><td>17</td><td>UI</td><td>Responsive mobile-first</td><td><span class="badge badge-ok">Activo</span></td></tr>
                <tr><td>18</td><td>Integración</td><td>Motor Contable</td><td><span class="badge badge-ok">Activo</span></td></tr>
                <tr><td>19</td><td>Integración</td><td>Procesamiento a Pólizas</td><td><span class="badge badge-ok">Activo</span></td></tr>
                <tr><td>20</td><td>Auditoría</td><td>Logs de actividad completos</td><td><span class="badge badge-ok">Activo</span></td></tr>
            </tbody>
        </table>
    </div>

    <div class="footer-doc">
        <p><strong>MAS QUE FIANZAS</strong> - Sistema Integrado de Gestión | Módulo Cotizaciones v9.0</p>
        <p>Clasificación: <strong>INTERNO</strong> | Versión: 1.0 | Próxima revisión: <?php echo date('d/m/Y', strtotime('+6 months')); ?></p>
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
    document.getElementById('actionTitle').textContent = 
        num === 1 ? '📄 Análisis ISO/NOFTRAB' : '📄 Funcionalidades del Cotizador';
    
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
        
        // Ocultar elementos no imprimibles
        const canvas = await html2canvas(elemento, {
            scale: 2,
            useCORS: true,
            logging: false,
            backgroundColor: '#ffffff'
        });
        
        const imgData = canvas.toDataURL('image/png');
        const imgWidth = 210; // A4 width in mm
        const pageHeight = 297; // A4 height in mm
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
        
        const filename = documentoActivo === 1 
            ? 'Analisis_ISO_NOFTRAB_Cotizador.pdf' 
            : 'Funcionalidades_Cotizador.pdf';
        
        doc.save(filename);
        
        btn.innerHTML = '<i class="fa-solid fa-check"></i> Descargado';
        setTimeout(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }, 2000);
        
    } catch (error) {
        console.error('Error al generar PDF:', error);
        alert('Error al generar PDF. Use la opción Imprimir (Ctrl+P) y seleccione "Guardar como PDF".');
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
