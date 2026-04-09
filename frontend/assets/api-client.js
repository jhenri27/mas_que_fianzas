/**
 * Cliente de API para la Plataforma
 * Maneja todas las llamadas al backend
 */

class APIClient {
    constructor() {
        // Usar URL relativa para compatibilidad con cualquier dominio/IP
        this.baseURL = '/PLATAFORMA_INTEGRADA/backend/api';
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

            // Si la respuesta es 401 solo redirigir si es en un endpoint de autenticación
            if (!response.ok && response.status === 401 && endpoint.includes('/auth/')) {
                this.limpiarSesion();
                window.location.href = '/PLATAFORMA_INTEGRADA/frontend/';
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
