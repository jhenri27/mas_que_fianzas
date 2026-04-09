/**
 * Enhanced Dashboard Debug Version
 * Includes additional logging to help diagnose issues
 */

class DashboardDebug {
    constructor() {
        console.log('[DEBUG] Iniciando Dashboard...');
        
        this.usuarioActual = api.obtenerUsuarioActual();
        console.log('[DEBUG] Usuario actual:', this.usuarioActual);
        
        this.paginaActualUsuarios = 1;
        this.perfilesCache = [];
        this.moduloActual = 'dashboard';
        
        this.init();
    }

    init() {
        console.log('[DEBUG] Ejecutando init()...');
        
        // Verificar sesión
        console.log('[DEBUG] Verificando sesión...');
        console.log('[DEBUG] token_sesion:', localStorage.getItem('token_sesion'));
        console.log('[DEBUG] usuario_actual:', localStorage.getItem('usuario_actual'));
        
        if (!api.tieneSesion()) {
            console.error('[DEBUG] No hay sesión válida, redirigiendo...');
            window.location.href = '/PLATAFORMA_INTEGRADA/frontend/';
            return;
        }

        console.log('[DEBUG] Sesión válida, continuando...');
        this.setupUI();
        this.setupEventListeners();
        this.cargarDatos();
    }

    setupUI() {
        console.log('[DEBUG] Ejecutando setupUI()...');
        
        // Actualizar información del usuario
        const userName = document.getElementById('userName');
        console.log('[DEBUG] userName element:', userName);
        
        if (this.usuarioActual && this.usuarioActual.nombre_completo) {
            userName.textContent = this.usuarioActual.nombre_completo;
            console.log('[DEBUG] Nombre de usuario actualizado:', this.usuarioActual.nombre_completo);
        }

        // Actualizar saludo
        const hora = new Date().getHours();
        let saludo = '¡Hola! ';
        if (hora < 12) saludo += 'Buenos días';
        else if (hora < 18) saludo += 'Buenas tardes';
        else saludo += 'Buenas noches';

        const userGreeting = document.getElementById('userGreeting');
        if (userGreeting) {
            userGreeting.textContent = saludo + ', ' + (this.usuarioActual?.nombre_completo || 'Usuario');
            console.log('[DEBUG] Saludo actualizado:', userGreeting.textContent);
        }
    }

    setupEventListeners() {
        console.log('[DEBUG] Ejecutando setupEventListeners()...');
        
        // Navegación del sidebar
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                const module = item.dataset.module;
                console.log('[DEBUG] Cambiar a módulo:', module);
                this.cambiarModulo(module);
                
                // Actualizar active item
                document.querySelectorAll('.nav-item').forEach(i => i.classList.remove('active'));
                item.classList.add('active');
            });
        });

        // Menu toggle mobile
        const menuToggle = document.getElementById('menuToggle');
        if (menuToggle) {
            menuToggle.addEventListener('click', () => {
                console.log('[DEBUG] Menu toggle clicked');
                const sidebar = document.querySelector('.sidebar-nav');
                sidebar.classList.toggle('active');
            });
        }

        // Logout
        const logoutBtn = document.getElementById('logoutBtn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', () => {
                console.log('[DEBUG] Logout clicked');
                this.logout();
            });
        }

        // Tabs del módulo de usuarios
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const tabName = btn.dataset.tab;
                console.log('[DEBUG] Cambiar a tab:', tabName);
                this.cambiarTab(tabName);
                
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
            });
        });
    }

    async cargarDatos() {
        console.log('[DEBUG] Ejecutando cargarDatos()...');
        
        console.log('[DEBUG] Llamando cargarPerfiles()...');
        await this.cargarPerfiles();
        
        console.log('[DEBUG] Llamando cargarUsuarios()...');
        await this.cargarUsuarios();
        
        console.log('[DEBUG] cargarDatos() completado');
    }

    cambiarModulo(modulo) {
        console.log('[DEBUG] cambiarModulo:', modulo);
        
        // Ocultar todos los módulos
        document.querySelectorAll('.module').forEach(m => {
            m.classList.remove('active');
        });

        // Mostrar módulo seleccionado
        const moduloElement = document.getElementById(`modulo-${modulo}`);
        console.log('[DEBUG] moduloElement:', moduloElement);
        
        if (moduloElement) {
            moduloElement.classList.add('active');
            console.log('[DEBUG] Módulo activado:', modulo);
        }

        this.moduloActual = modulo;
    }

    cambiarTab(tabName) {
        console.log('[DEBUG] cambiarTab:', tabName);
        
        // Ocultar todos los tab contents
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.classList.remove('active');
        });

        // Mostrar tab seleccionado
        const tabElement = document.getElementById(tabName);
        console.log('[DEBUG] tabElement:', tabElement);
        
        if (tabElement) {
            tabElement.classList.add('active');
            console.log('[DEBUG] Tab activado:', tabName);
        }
    }

    async cargarPerfiles() {
        console.log('[DEBUG] Ejecutando cargarPerfiles()...');
        
        try {
            console.log('[DEBUG] Llamando api.listarPerfiles()...');
            const resultado = await api.listarPerfiles();
            
            console.log('[DEBUG] Resultado listarPerfiles:', resultado);
            
            if (resultado.exito) {
                this.perfilesCache = resultado.datos;
                console.log('[DEBUG] Perfiles cargados:', this.perfilesCache);
                this.llenarSelectPerfiles();
                this.llenarTablaPerfiles();
            } else {
                console.error('[DEBUG] Error en listarPerfiles:', resultado.mensaje);
            }
        } catch (error) {
            console.error('[DEBUG] Error en cargarPerfiles:', error);
        }
    }

    llenarSelectPerfiles() {
        console.log('[DEBUG] Ejecutando llenarSelectPerfiles()...');
        
        const select = document.getElementById('usuarioPerfil');
        if (!select) {
            console.warn('[DEBUG] Elemento usuarioPerfil no encontrado');
            return;
        }

        select.innerHTML = '<option value="">Selecciona un perfil</option>';
        this.perfilesCache.forEach(perfil => {
            const option = document.createElement('option');
            option.value = perfil.id;
            option.textContent = perfil.nombre_perfil;
            select.appendChild(option);
        });
        
        console.log('[DEBUG] Select de perfiles rellenado');
    }

    llenarTablaPerfiles() {
        console.log('[DEBUG] Ejecutando llenarTablaPerfiles()...');
        
        const tbody = document.getElementById('perfilesList');
        if (!tbody) {
            console.warn('[DEBUG] Elemento perfilesList no encontrado');
            return;
        }

        if (this.perfilesCache.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center">No hay perfiles registrados</td></tr>';
            console.log('[DEBUG] No hay perfiles');
            return;
        }

        tbody.innerHTML = this.perfilesCache.map(perfil => `
            <tr>
                <td><strong>${perfil.nombre_perfil}</strong></td>
                <td>${perfil.descripcion || '-'}</td>
                <td>${perfil.nivel_jerarquico}</td>
                <td>
                    <span class="status-badge status-${perfil.estado}">
                        ${perfil.estado.charAt(0).toUpperCase() + perfil.estado.slice(1)}
                    </span>
                </td>
                <td>
                    <button class="btn btn-sm" onclick="dashboard.editarPerfil(${perfil.id})">Editar</button>
                </td>
            </tr>
        `).join('');
        
        console.log('[DEBUG] Tabla de perfiles rellenada');
    }

    async cargarUsuarios() {
        console.log('[DEBUG] Ejecutando cargarUsuarios()...');
        
        try {
            const buscar = document.getElementById('buscarUsuarios')?.value || '';
            const estado = document.getElementById('filtroEstadoUsuarios')?.value || '';
            const perfilId = document.getElementById('filtroPerfilUsuarios')?.value || '';

            console.log('[DEBUG] Parámetros de filtro:', { buscar, estado, perfilId });

            const resultado = await api.listarUsuarios(this.paginaActualUsuarios, 20, {
                buscar,
                estado,
                perfil_id: perfilId ? parseInt(perfilId) : 0
            });

            console.log('[DEBUG] Resultado listarUsuarios:', resultado);

            if (resultado.exito) {
                this.llenarTablaUsuarios(resultado.datos.usuarios);
                this.llenarPaginacionUsuarios(resultado.datos.paginacion);
                console.log('[DEBUG] Usuarios cargados:', resultado.datos.usuarios.length);
            } else {
                console.error('[DEBUG] Error en listarUsuarios:', resultado.mensaje);
            }
        } catch (error) {
            console.error('[DEBUG] Error en cargarUsuarios:', error);
        }
    }

    llenarTablaUsuarios(usuarios) {
        console.log('[DEBUG] Ejecutando llenarTablaUsuarios()...');
        
        const tbody = document.getElementById('usuariosList');
        if (!tbody) {
            console.warn('[DEBUG] Elemento usuariosList no encontrado');
            return;
        }

        if (usuarios.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center">No hay usuarios</td></tr>';
            console.log('[DEBUG] No hay usuarios');
            return;
        }

        tbody.innerHTML = usuarios.map(usuario => `
            <tr>
                <td>${usuario.nombre} ${usuario.apellido}</td>
                <td>${usuario.email}</td>
                <td>${usuario.nombre_perfil || '-'}</td>
                <td>
                    <span class="status-badge status-${usuario.estado}">
                        ${usuario.estado.charAt(0).toUpperCase() + usuario.estado.slice(1)}
                    </span>
                </td>
                <td>${usuario.fecha_ultimo_acceso ? new Date(usuario.fecha_ultimo_acceso).toLocaleString() : 'Nunca'}</td>
                <td>
                    <div style="display: flex; gap: 5px;">
                        <button class="btn btn-sm" onclick="dashboard.editarUsuario(${usuario.id})">Editar</button>
                    </div>
                </td>
            </tr>
        `).join('');
        
        console.log('[DEBUG] Tabla de usuarios rellenada');
    }

    llenarPaginacionUsuarios(paginacion) {
        console.log('[DEBUG] Paginación:', paginacion);
        const container = document.getElementById('usuariosPaginacion');
        if (!container) return;

        let html = '';
        if (paginacion.pagina_actual > 1) {
            html += `<button onclick="dashboard.irPaginaUsuarios(${paginacion.pagina_actual - 1})">← Anterior</button>`;
        }

        for (let i = 1; i <= paginacion.total_paginas; i++) {
            if (i === paginacion.pagina_actual) {
                html += `<button class="active">${i}</button>`;
            } else {
                html += `<button onclick="dashboard.irPaginaUsuarios(${i})">${i}</button>`;
            }
        }

        if (paginacion.pagina_actual < paginacion.total_paginas) {
            html += `<button onclick="dashboard.irPaginaUsuarios(${paginacion.pagina_actual + 1})">Siguiente →</button>`;
        }

        container.innerHTML = html;
    }

    irPaginaUsuarios(pagina) {
        console.log('[DEBUG] Ir a página:', pagina);
        this.paginaActualUsuarios = pagina;
        this.cargarUsuarios();
    }

    editarUsuario(usuarioId) {
        console.log('[DEBUG] Editar usuario:', usuarioId);
        alert('Función de edición en desarrollo');
    }

    editarPerfil(perfilId) {
        console.log('[DEBUG] Editar perfil:', perfilId);
        alert('Función de edición de perfiles en desarrollo');
    }

    async logout() {
        console.log('[DEBUG] Ejecutando logout()...');
        const resultado = await api.logout();
        console.log('[DEBUG] Resultado logout:', resultado);
        
        if (resultado.exito) {
            window.location.href = '/PLATAFORMA_INTEGRADA/frontend/';
        }
    }
}

// Inicializar dashboard cuando carga la página
let dashboard;
document.addEventListener('DOMContentLoaded', function() {
    console.log('[DEBUG] DOMContentLoaded ejecutado');
    dashboard = new DashboardDebug();
});
