/**
 * Cliente de API para la Plataforma
 * Maneja todas las llamadas al backend
 */

class APIClient {
    constructor() {
        // Detectar automáticamente si la app corre en subdirectorio (WAMP) o raíz (VPS)
        const isSubdir = window.location.pathname.startsWith('/PLATAFORMA_INTEGRADA');
        this.basePrefix = isSubdir ? '/PLATAFORMA_INTEGRADA' : '';
        this.baseURL = `${this.basePrefix}/backend/api`;
        this.tokenSesion = localStorage.getItem('token_sesion') || null;
        this.usuario = JSON.parse(localStorage.getItem('usuario_actual') || 'null');
    }

    /**
     * Método genérico para hacer solicitudes HTTP
     */
    async solicitud(endpoint, metodo = 'GET', datos = null) {
        const opciones = {
            method: metodo,
            headers: {
                'Content-Type': 'application/json',
            }
        };

        // Agregar token si existe
        if (this.tokenSesion) {
            opciones.headers['Authorization'] = `Bearer ${this.tokenSesion}`;
        }

        // Agregar datos al body si es POST, PUT, DELETE
        if (datos && ['POST', 'PUT', 'DELETE'].includes(metodo)) {
            opciones.body = JSON.stringify(datos);
        }

        try {
            const response = await fetch(`${this.baseURL}${endpoint}`, opciones);
            const data = await response.json();

            // Si la respuesta es 401, limpiar sesión y redirigir al login (manejando iframe breakout)
            if (!response.ok && response.status === 401) {
                this.limpiarSesion();
                const targetUrl = `${this.basePrefix}/frontend/`;
                if (window.top) {
                    window.top.location.href = targetUrl;
                } else {
                    window.location.href = targetUrl;
                }
            }

            return {
                ok: response.ok,
                status: response.status,
                ...data
            };
        } catch (error) {
            console.error('Error en solicitud API:', error);
            return {
                ok: false,
                status: 0,
                exito: false,
                mensaje: 'Error de conexión con el servidor'
            };
        }
    }

    /**
     * Login
     */
    async login(username, password) {
        const resultado = await this.solicitud('/auth/login', 'POST', {
            username,
            password
        });

        if (resultado.exito) {
            if (!resultado.nombre_completo && resultado.nombre && resultado.apellido) {
                resultado.nombre_completo = resultado.nombre + ' ' + resultado.apellido;
            }
            this.tokenSesion = resultado.token_sesion;
            this.usuario = resultado;
            localStorage.setItem('token_sesion', this.tokenSesion);
            localStorage.setItem('usuario_actual', JSON.stringify(resultado));
        }

        return resultado;
    }

    /**
     * Logout
     */
    async logout() {
        const resultado = await this.solicitud('/auth/logout', 'POST');
        this.limpiarSesion();
        return resultado;
    }

    /**
     * Cambiar contraseña
     */
    async cambiarPassword(passwordActual, passwordNueva, passwordConfirmacion) {
        return await this.solicitud('/auth/cambiar-password', 'POST', {
            password_actual: passwordActual,
            password_nueva: passwordNueva,
            password_confirmacion: passwordConfirmacion
        });
    }

    /**
     * Cambiar contraseña forzado (tras reset administrativo)
     */
    async cambiarPasswordForzado(passwordNueva, passwordConfirmacion) {
        return await this.solicitud('/auth/cambiar-password-forzado', 'POST', {
            password_nueva: passwordNueva,
            password_confirmacion: passwordConfirmacion
        });
    }

    /**
     * Validar sesión
     */
    async validarSesion() {
        if (!this.tokenSesion) {
            return { exito: false };
        }

        return await this.solicitud(`/auth/validar-sesion?token=${this.tokenSesion}`, 'GET');
    }

    // ==================== GESTIÓN DE USUARIOS ====================

    async crearUsuario(datos) {
        return await this.solicitud('/usuarios.php/crear', 'POST', datos);
    }

    async editarUsuario(usuarioId, datos) {
        return await this.solicitud(`/usuarios.php/editar/${usuarioId}`, 'PUT', datos);
    }

    async bloquearUsuario(usuarioId, razon) {
        return await this.solicitud(`/usuarios.php/bloquear/${usuarioId}`, 'POST', { razon });
    }

    async desbloquearUsuario(usuarioId) {
        return await this.solicitud(`/usuarios.php/desbloquear/${usuarioId}`, 'POST');
    }

    async restablecerPassword(usuarioId) {
        return await this.solicitud(`/usuarios.php/restablecer-password/${usuarioId}`, 'POST');
    }

    async obtenerUsuario(usuarioId) {
        return await this.solicitud(`/usuarios.php/obtener/${usuarioId}`, 'GET');
    }

    async listarUsuarios(pagina = 1, porPagina = 20, filtros = {}) {
        let queryString = `?pagina=${pagina}&por_pagina=${porPagina}`;

        if (filtros.estado) queryString += `&estado=${filtros.estado}`;
        if (filtros.perfil_id) queryString += `&perfil_id=${filtros.perfil_id}`;
        if (filtros.buscar) queryString += `&buscar=${encodeURIComponent(filtros.buscar)}`;

        return await this.solicitud(`/usuarios.php/listar${queryString}`, 'GET');
    }

    async eliminarUsuario(usuarioId) {
        return await this.solicitud(`/usuarios.php/eliminar/${usuarioId}`, 'DELETE');
    }

    // ==================== GESTIÓN DE PERFILES ====================

    async crearPerfil(datos) {
        return await this.solicitud('/perfiles.php/crear', 'POST', datos);
    }

    async editarPerfil(perfilId, datos) {
        return await this.solicitud(`/perfiles.php/editar/${perfilId}`, 'PUT', datos);
    }

    async obtenerPerfil(perfilId) {
        return await this.solicitud(`/perfiles.php/obtener/${perfilId}`, 'GET');
    }

    async listarPerfiles() {
        return await this.solicitud('/perfiles.php/listar', 'GET');
    }

    async eliminarPerfil(id) {
        return await this.solicitud(`/perfiles.php/eliminar/${id}`, 'DELETE');
    }

    // ==================== GESTIÓN DE CLIENTES ====================

    async crearCliente(datos) {
        return await this.solicitud('/clientes.php/crear', 'POST', datos);
    }

    async editarCliente(id, datos) {
        return await this.solicitud(`/clientes.php/editar/${id}`, 'PUT', datos);
    }

    async listarClientes() {
        return await this.solicitud('/clientes.php/listar', 'GET');
    }

    // ==================== GESTIÓN DE ACTIVIDAD ====================

    async listarActividadReciente() {
        return await this.solicitud('/actividad.php', 'GET');
    }

    async obtenerDetalleActividad(id) {
        return await this.solicitud(`/actividad.php?id=${id}`, 'GET');
    }

    async registrarActividad(modulo, descripcion, tipo = 'navegacion') {
        return await this.solicitud('/actividad.php', 'POST', {
            tipo,
            modulo,
            descripcion
        });
    }

    // ==================== SESIÓN Y UTILIDADES ====================

    limpiarSesion() {
        this.tokenSesion = null;
        this.usuario = null;
        localStorage.removeItem('token_sesion');
        localStorage.removeItem('usuario_actual');
    }

    tieneSesion() {
        return this.tokenSesion !== null && this.usuario !== null;
    }

    obtenerUsuarioActual() {
        return this.usuario;
    }
}

// Instancia global del cliente
const api = new APIClient();

// =========================================================================
// SISTEMA DE PERMISOS GRANULARES NOFTRAB - EJECUCIÓN AUTOMÁTICA EN IFRAMES
// =========================================================================
document.addEventListener('DOMContentLoaded', () => {
    window.MQF_CAN_CREATE = true;
    window.MQF_CAN_EDIT = true;
    window.MQF_CAN_DELETE = true;
    window.MQF_CAN_IMPORT = true;
    window.MQF_CAN_PRINT = true;
    
    function aplicarPermisosYFiltros(tieneAcceso, modulePerms, fileBasename) {
        if (!tieneAcceso) {
            // BLOQUEAR ACCESO: Registrar intento fallido en auditoría (Norma NOFTRAB)
            api.registrarActividad(fileBasename, 'Intento de acceso no autorizado (bloqueado por iframe protection)', 'fallido');
            
            document.documentElement.innerHTML = `
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Acceso Denegado</title>
    <style>
        :root {
            --bg-color: #0f1117;
            --card-bg: #1e2433;
            --text-color: #e2e8f0;
            --text-muted: #64748b;
            --primary: #ef4444;
            --primary-glow: rgba(239, 68, 68, 0.15);
            --border: #2d3748;
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 1.5rem;
            overflow: hidden;
        }
        
        .card {
            background-color: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 2.5rem;
            max-width: 480px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            position: relative;
            animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }
        
        .card::before {
            content: '';
            position: absolute;
            top: 0; left: 50%;
            transform: translateX(-50%);
            width: 80px; height: 4px;
            background-color: var(--primary);
            border-radius: 0 0 4px 4px;
            box-shadow: 0 4px 12px var(--primary-glow);
        }
        
        .icon-wrapper {
            width: 80px;
            height: 80px;
            background-color: var(--primary-glow);
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            animation: pulse 2s infinite;
        }
        
        .icon-wrapper svg {
            width: 40px;
            height: 40px;
            color: var(--primary);
        }
        
        h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            letter-spacing: -0.025em;
        }
        
        p {
            color: var(--text-muted);
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 2rem;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background-color: #ef444415;
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.4);
            padding: 0.75rem 1.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        
        .btn:hover {
            background-color: var(--primary);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.25);
            transform: translateY(-1px);
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes pulse {
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.2); }
            70% { transform: scale(1.05); box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon-wrapper">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
        </div>
        <h1>Acceso Restringido</h1>
        <p>No tienes los privilegios de seguridad necesarios para acceder al módulo <strong>${fileBasename.replace(/_/g, ' ').toUpperCase()}</strong>. Este intento ha sido auditado de acuerdo a los protocolos NOFTRAB.</p>
        <button class="btn" onclick="if (window.parent && window.parent !== window) { window.parent.location.reload(); } else { window.location.href = '/PLATAFORMA_INTEGRADA/frontend/'; }">Regresar al Inicio</button>
    </div>
</body>
</html>
            `;
            window.stop();
            return;
        }
        
        window.MQF_CAN_CREATE = modulePerms.some(p => parseInt(p.crear_datos) === 1);
        window.MQF_CAN_EDIT = modulePerms.some(p => parseInt(p.editar_datos) === 1);
        window.MQF_CAN_DELETE = modulePerms.some(p => parseInt(p.eliminar_datos) === 1);
        window.MQF_CAN_IMPORT = modulePerms.some(p => parseInt(p.importar_datos) === 1);
        window.MQF_CAN_PRINT = modulePerms.some(p => parseInt(p.exportar_datos) === 1);
        
        let cssRules = '';
        if (!window.MQF_CAN_CREATE) {
            cssRules += '[onclick*="abrirModal"], [onclick*="abrirAsistente"], .btn-crear { display: none !important; }\n';
        }
        if (!window.MQF_CAN_EDIT) {
            cssRules += '[onclick*="editar"], [onclick*="Editar"], .btn-editar { display: none !important; }\n';
        }
        if (!window.MQF_CAN_DELETE) {
            cssRules += '[onclick*="eliminar"], [onclick*="Eliminar"], [onclick*="borrar"], .btn-danger { display: none !important; }\n';
        }
        if (!window.MQF_CAN_IMPORT) {
            cssRules += '[onclick*="importar"], [onclick*="Importar"], .btn-importar { display: none !important; }\n';
        }
        if (!window.MQF_CAN_PRINT) {
            cssRules += '[onclick*="imprimir"], [onclick*="Imprimir"], [onclick*="pdf"], [onclick*="PDF"], .btn-imprimir { display: none !important; }\n';
        }
        
        if (cssRules) {
            const style = document.createElement('style');
            style.textContent = cssRules;
            document.head.appendChild(style);
        }
    }

    try {
        const path = window.location.pathname.toLowerCase();
        
        // Solo aplicar la protección de acceso granular a archivos dentro de /modulos/
        if (!path.includes('/modulos/')) {
            return;
        }

        const fileBasename = path.split('/').pop().replace(/\.(html|php)$/, '').replace(/-/g, '_');
        const modulosExcluidos = ['dashboard', 'mi_perfil', 'index', 'login', 'perfil_data'];
        
        if (!modulosExcluidos.includes(fileBasename)) {
            const usuarioActual = (window.parent && window.parent.api) ? window.parent.api.obtenerUsuarioActual() : api.obtenerUsuarioActual();
            const perfilId = usuarioActual ? parseInt(usuarioActual.perfil_id, 10) : null;
            
            // Bypass para administrador
            if (perfilId === 1) {
                return;
            }
            
            let tieneAcceso = false;
            let modulePerms = [];
            
            if (window.parent && window.parent.MQF_PERMISOS) {
                const permisos = window.parent.MQF_PERMISOS;
                modulePerms = permisos.filter(p => p.nombre_modulo === fileBasename);
                tieneAcceso = modulePerms.some(p => parseInt(p.puede_ejecutar) === 1);
                aplicarPermisosYFiltros(tieneAcceso, modulePerms, fileBasename);
            } else if (perfilId) {
                // Si se carga directamente, hacer consulta a perfiles API
                document.documentElement.style.display = 'none';
                
                const token = localStorage.getItem('token_sesion') || '';
                fetch(`/PLATAFORMA_INTEGRADA/backend/api/perfiles.php/obtener/${perfilId}`, {
                    headers: { 'Authorization': 'Bearer ' + token }
                })
                .then(resp => resp.json())
                .then(result => {
                    document.documentElement.style.display = '';
                    if (result.exito && result.datos && Array.isArray(result.datos.permisos)) {
                        const permisos = result.datos.permisos;
                        modulePerms = permisos.filter(p => p.nombre_modulo === fileBasename);
                        tieneAcceso = modulePerms.some(p => parseInt(p.puede_ejecutar) === 1);
                        aplicarPermisosYFiltros(tieneAcceso, modulePerms, fileBasename);
                    } else {
                        aplicarPermisosYFiltros(false, [], fileBasename);
                    }
                })
                .catch(() => {
                    document.documentElement.style.display = '';
                    aplicarPermisosYFiltros(false, [], fileBasename);
                });
            } else {
                aplicarPermisosYFiltros(false, [], fileBasename);
            }
        }
    } catch (e) {
        console.warn('Error validando permisos granulares NOFTRAB:', e);
    }
});
