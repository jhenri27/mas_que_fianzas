/**
 * Lógica del Dashboard Principal
 * Sistema Integrado MAS QUE FIANZAS
 */

class Dashboard {
    constructor() {
        this.usuarioActual = api.obtenerUsuarioActual();
        this.paginaActualUsuarios = 1;
        this.perfilesCache = [];
        this.moduloActual = 'dashboard';
        
        this.init();
    }

    init() {
        // Verificar sesión
        if (!api.tieneSesion()) {
            window.location.href = '/PLATAFORMA_INTEGRADA/frontend/';
            return;
        }

        this.setupUI();
        this.setupEventListeners();
        this.cargarDatos();
    }

    setupUI() {
        // Actualizar información del usuario
        const userName = document.getElementById('userName');
        if (this.usuarioActual && this.usuarioActual.nombre_completo) {
            userName.textContent = this.usuarioActual.nombre_completo;
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
        }

        // Configurar menú lateral según el perfil
        if (this.usuarioActual && this.usuarioActual.perfil === 'Socio Comercial PDV') {
            const modulosPermitidos = ['dashboard', 'cotizaciones', 'clientes', 'polizas', 'reportes', 'mi-perfil'];
            
            document.querySelectorAll('.nav-item').forEach(item => {
                const moduleName = item.dataset.module;
                if (!modulosPermitidos.includes(moduleName)) {
                    item.style.display = 'none'; // Ocultar
                }
            });

            // Ocultar acciones rápidas del dashboard que no corresponden
            document.querySelectorAll('.action-btn').forEach(btn => {
                const action = btn.dataset.action;
                if (action === 'registrar-pago' || action === 'nuevo-cliente') {
                    // Solo permitimos nueva cotización y ver reportes en el inicio rápido del PDV
                    btn.style.display = 'none'; 
                }
            });
        }
    }

    setupEventListeners() {
        // Navegación del sidebar
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                const module = item.dataset.module;
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
                const sidebar = document.querySelector('.sidebar-nav');
                sidebar.classList.toggle('active');
            });
        }

        // Logout
        const logoutBtn = document.getElementById('logoutBtn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', () => this.logout());
        }

        const logoutBtnMenu = document.getElementById('logoutBtnMenu');
        if (logoutBtnMenu) {
            logoutBtnMenu.addEventListener('click', (e) => {
                e.preventDefault();
                this.logout();
            });
        }

        // User menu dropdown
        const userMenuToggle = document.getElementById('userMenuToggle');
        const userDropdownMenu = document.getElementById('userDropdownMenu');
        if (userMenuToggle && userDropdownMenu) {
            userMenuToggle.addEventListener('click', () => {
                userDropdownMenu.style.display = userDropdownMenu.style.display === 'none' ? 'block' : 'none';
            });

            document.addEventListener('click', (e) => {
                if (!userMenuToggle.contains(e.target) && !userDropdownMenu.contains(e.target)) {
                    userDropdownMenu.style.display = 'none';
                }
            });
        }

        // Mi Perfil
        const miPerfilBtn = document.getElementById('miPerfilBtn');
        if (miPerfilBtn) {
            miPerfilBtn.addEventListener('click', (e) => {
                e.preventDefault();
                if (userDropdownMenu) userDropdownMenu.style.display = 'none';
                abrirMiPerfil();
            });
        }

        // Cambiar Contraseña
        const cambiarPasswordBtn = document.getElementById('cambiarPasswordBtn');
        if (cambiarPasswordBtn) {
            cambiarPasswordBtn.addEventListener('click', (e) => {
                e.preventDefault();
                if (userDropdownMenu) userDropdownMenu.style.display = 'none';
                abrirCambiarPassword();
            });
        }


        // Acciones rápidas del dashboard → navegar al módulo correspondiente
        document.querySelectorAll('[data-action]').forEach(btn => {
            btn.addEventListener('click', () => {
                const action = btn.dataset.action;
                const actionModuleMap = {
                    'nueva-cotizacion': 'cotizaciones',
                    'nuevo-cliente':    'clientes',
                    'registrar-pago':   'pagos',
                    'ver-reportes':     'reportes'
                };
                const targetModule = actionModuleMap[action];
                if (targetModule) {
                    this.cambiarModulo(targetModule);
                    document.querySelectorAll('.nav-item').forEach(i => i.classList.remove('active'));
                    const navItem = document.querySelector(`.nav-item[data-module="${targetModule}"]`);
                    if (navItem) navItem.classList.add('active');
                }
            });
        });

        // Eventos para tarjetas de estadísticas del Dashboard
        document.querySelectorAll('.stat-card').forEach(card => {
            card.addEventListener('click', () => {
                const titulo = card.querySelector('h3').textContent;
                const valor = card.querySelector('.stat-number').textContent;
                const icono = card.querySelector('.stat-icon').textContent;
                this.abrirDetalleGlobal('estadistica', null, { titulo, valor, icono });
            });
        });

        // Eventos para actividad reciente (Delegación)
        const activityList = document.getElementById('recentActivityList');
        if (activityList) {
            activityList.addEventListener('click', (e) => {
                const item = e.target.closest('.activity-item');
                if (item && item.dataset.id) {
                    this.abrirDetalleGlobal('actividad', item.dataset.id);
                }
            });
        }


        // Tabs del módulo de usuarios
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const tabName = btn.dataset.tab;
                this.cambiarTab(tabName);
                
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
            });
        });

        // Crear usuario
        const crearUsuarioBtn = document.getElementById('crearUsuarioBtn');
        if (crearUsuarioBtn) {
            crearUsuarioBtn.addEventListener('click', () => this.abrirModalUsuario());
        }

        // Crear perfil
        const crearPerfilBtn = document.getElementById('crearPerfilBtn');
        if (crearPerfilBtn) {
            crearPerfilBtn.addEventListener('click', () => this.abrirModalPerfil());
        }

        // Formulario de perfil
        const perfilForm = document.getElementById('perfilForm');
        if (perfilForm) {
            perfilForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.guardarPerfil();
            });
        }

        // Formulario de usuario
        const usuarioForm = document.getElementById('usuarioForm');
        if (usuarioForm) {
            usuarioForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.guardarUsuario();
            });
        }

        // Toggle de comisión en usuario
        const esComisionanteCheckbox = document.getElementById('usuarioEsComisionante');
        if (esComisionanteCheckbox) {
            esComisionanteCheckbox.addEventListener('change', (e) => {
                const seccion = document.getElementById('seccionComision');
                seccion.style.display = e.target.checked ? 'block' : 'none';
            });
        }

        // Filtros de usuarios
        const buscarUsuarios = document.getElementById('buscarUsuarios');
        const filtroEstadoUsuarios = document.getElementById('filtroEstadoUsuarios');
        const filtroPerfilUsuarios = document.getElementById('filtroPerfilUsuarios');

        if (buscarUsuarios) {
            buscarUsuarios.addEventListener('input', () => {
                this.paginaActualUsuarios = 1;
                this.cargarUsuarios();
            });
        }

        if (filtroEstadoUsuarios) {
            filtroEstadoUsuarios.addEventListener('change', () => {
                this.paginaActualUsuarios = 1;
                this.cargarUsuarios();
            });
        }

        if (filtroPerfilUsuarios) {
            filtroPerfilUsuarios.addEventListener('change', () => {
                this.paginaActualUsuarios = 1;
                this.cargarUsuarios();
            });
        }
    }

    async cargarDatos() {
        console.log('[Dashboard] Iniciando cargarDatos...');
        
        try {
            await this.cargarEstadisticas();
        } catch (error) {
            console.error('[Dashboard] Error en cargarEstadisticas:', error);
        }

        try {
            await this.cargarPerfiles();
        } catch (error) {
            console.error('[Dashboard] Error en cargarPerfiles:', error);
        }
        
        try {
            await this.cargarActividadReciente();
        } catch (error) {
            console.error('[Dashboard] Error en cargarActividadReciente:', error);
        }
        
        console.log('[Dashboard] cargarDatos completado');
    }

    async cargarEstadisticas() {
        // 1. Total Clientes
        try {
            const respuesta = await api.listarClientes();
            if (respuesta.exito && respuesta.datos) {
                document.getElementById('totalClientes').textContent = respuesta.datos.length;
            }
        } catch (error) {
            console.error('Error cargando total de clientes:', error);
        }

        // 2. Cotizaciones (Fianzas & Seguros de Ley)
        try {
            const resp = await fetch('/PLATAFORMA_INTEGRADA/backend/api/cotizaciones.php?action=listar&limite=500');
            const data = await resp.json();
            if (data.exito && Array.isArray(data.datos)) {
                const hist = data.datos;
                
                // Conteo por lógica de negocio
                const total = hist.length;
                const fianzas = hist.filter(c => c.tipo === 'FIANZA').length;
                const seguros = hist.filter(c => c.tipo !== 'FIANZA').length;

                // Actualizar interfaz
                document.getElementById('totalCotizaciones').textContent = total;
                document.getElementById('cotizacionesFianzas').textContent = fianzas;
                document.getElementById('cotizacionesSeguros').textContent = seguros;

            } else {
                // Fallback localStorage (compatibilidad con versiones previas)
                const hist = JSON.parse(localStorage.getItem('cotHistorial') || '[]');
                document.getElementById('totalCotizaciones').textContent = hist.length;
                document.getElementById('cotizacionesFianzas').textContent = hist.filter(c => c.tipo === 'FIANZA' || c.subtipo).length;
                document.getElementById('cotizacionesSeguros').textContent = hist.filter(c => c.tipo !== 'FIANZA' && !c.subtipo).length;
            }
        } catch(error) {
            console.error('Error cargando historial de cotizaciones para dashboard:', error);
        }
    }

    async cargarActividadReciente() {
        const listContainer = document.getElementById('recentActivityList');
        if (!listContainer) return;

        try {
            const respuesta = await api.listarActividadReciente();
            if (respuesta.exito && Array.isArray(respuesta.datos)) {
                if (respuesta.datos.length === 0) {
                    listContainer.innerHTML = '<p class="empty-state">No hay actividades recientes</p>';
                    return;
                }

                listContainer.innerHTML = respuesta.datos.map(act => {
                    const fecha = new Date(act.fecha_evento);
                    const tiempo = this.formatearTiempoRelativo(fecha);
                    const icono = this.obtenerIconoActividad(act.tipo_evento, act.modulo_accedido);
                    
                    return `
                        <div class="activity-item" data-id="${act.id}">
                            <div class="activity-icon">${icono}</div>
                            <div class="activity-info">
                                <p class="activity-text">${act.descripcion_evento}</p>
                                <span class="activity-time">${tiempo}</span>
                            </div>
                        </div>
                    `;
                }).join('');
            }
        } catch (error) {
            console.error('Error cargando actividad reciente:', error);
        }
    }

    obtenerIconoActividad(tipo, modulo) {
        if (tipo === 'login') return '🔑';
        if (tipo === 'logout') return '🚪';
        
        const iconosModulo = {
            'dashboard':    '🏠',
            'clientes':     '👥',
            'cotizaciones': '📈',
            'usuarios':     '👤',
            'seguridad':    '🛡️',
            'configuracion':'⚙️',
            'reportes':     '📊'
        };
        
        return iconosModulo[modulo] || '📝';
    }

    formatearTiempoRelativo(fecha) {
        const ahora = new Date();
        const difSegundos = Math.floor((ahora - fecha) / 1000);
        
        if (difSegundos < 60) return 'Hace un momento';
        
        const difMinutos = Math.floor(difSegundos / 60);
        if (difMinutos < 60) return `Hace ${difMinutos} min`;
        
        const difHoras = Math.floor(difMinutos / 60);
        if (difHoras < 24) return `Hace ${difHoras} horas`;
        
        return fecha.toLocaleDateString();
    }

    cambiarModulo(modulo) {
        // Ocultar todos los módulos
        document.querySelectorAll('.module').forEach(m => {
            m.classList.remove('active');
        });

        // Mostrar módulo seleccionado
        const moduloElement = document.getElementById(`modulo-${modulo}`);
        if (moduloElement) {
            moduloElement.classList.add('active');
        }

        // Si es cotizaciones, forzar carga del iframe con versión para evitar caché
        if (modulo === 'cotizaciones') {
            console.log('Loading cotizaciones module...');
            const iframe = document.getElementById('cotizador-iframe');
            if (iframe) {
                // Always reload to avoid caching issues
                iframe.src = '/PLATAFORMA_INTEGRADA/frontend/modulos/cotizaciones.html?t=' + Date.now();
                iframe.dataset.loaded = 'true';
                console.log('Iframe src set to:', iframe.src);
            } else {
                console.error('Iframe cotizador-iframe not found!');
            }
        }

        // Si es clientes, forzar carga del iframe
        if (modulo === 'clientes') {
            const iframe = document.getElementById('clientes-iframe');
            if (iframe && !iframe.dataset.loaded) {
                // Forzar obtención sin caché del iframe durante el desarrollo
                iframe.src = '/PLATAFORMA_INTEGRADA/frontend/modulos/clientes.html?v=4';
                iframe.dataset.loaded = 'true';
            }
        }

        // Si es usuarios, forzar carga del iframe
        if (modulo === 'usuarios') {
            const iframe = document.getElementById('usuarios-iframe');
            if (iframe && !iframe.dataset.loaded) {
                iframe.src = '/PLATAFORMA_INTEGRADA/frontend/modulos/usuarios.html?v=1';
                iframe.dataset.loaded = 'true';
            }
        }

        // Actualizar título

        const titulo = document.getElementById('pageTitle');
        const titulos = {
            'dashboard': 'INICIO',
            'clientes': 'CLIENTES',
            'cotizaciones': 'COTIZACIONES',
            'polizas': 'PÓLIZAS',
            'fianzas': 'FIANZAS',
            'pagos': 'PAGOS',
            'siniestros': 'SINIESTROS',
            'productos': 'PRODUCTOS',
            'reportes': 'REPORTES',
            'usuarios': 'USUARIOS Y PERFILES',
            'configuracion': 'CONFIGURACIÓN'
        };

        if (titulo) {
            titulo.textContent = titulos[modulo] || 'MÓDULO';
        }

        // Registrar actividad si el módulo cambió
        if (this.moduloActual !== modulo) {
            api.registrarActividad(modulo, `Consultó el módulo ${titulos[modulo] || modulo}`);
            
            // Si volvemos al dashboard, refrescar la lista de actividad
            if (modulo === 'dashboard') {
                setTimeout(() => this.cargarActividadReciente(), 500);
            }
        }

        this.moduloActual = modulo;
    }

    cambiarTab(tabName) {
        // Ocultar todos los tab contents
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.classList.remove('active');
        });

        // Mostrar tab seleccionado
        const tabElement = document.getElementById(tabName);
        if (tabElement) {
            tabElement.classList.add('active');
        }
    }

    async cargarPerfiles() {
        try {
            const resultado = await api.listarPerfiles();
            if (resultado.exito) {
                this.perfilesCache = resultado.datos;
                this.llenarSelectPerfiles();
                this.llenarTablaPerfiles();
            }
        } catch (error) {
            console.error('Error cargando perfiles:', error);
        }
    }

    llenarSelectPerfiles() {
        const select = document.getElementById('usuarioPerfil');
        if (!select) return;

        select.innerHTML = '<option value="">Selecciona un perfil</option>';
        this.perfilesCache.forEach(perfil => {
            const option = document.createElement('option');
            option.value = perfil.id;
            option.textContent = perfil.nombre_perfil;
            select.appendChild(option);
        });
    }

    llenarTablaPerfiles() {
        const tbody = document.getElementById('perfilesList');
        if (!tbody) return;

        if (this.perfilesCache.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center">No hay perfiles registrados</td></tr>';
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
    }

    async cargarUsuarios() {
        try {
            const buscar = document.getElementById('buscarUsuarios')?.value || '';
            const estado = document.getElementById('filtroEstadoUsuarios')?.value || '';
            const perfilId = document.getElementById('filtroPerfilUsuarios')?.value || '';

            const resultado = await api.listarUsuarios(this.paginaActualUsuarios, 20, {
                buscar,
                estado,
                perfil_id: perfilId ? parseInt(perfilId) : 0
            });

            if (resultado.exito) {
                this.llenarTablaUsuarios(resultado.datos.usuarios);
                this.llenarPaginacionUsuarios(resultado.datos.paginacion);
            }
        } catch (error) {
            console.error('Error cargando usuarios:', error);
        }
    }

    llenarTablaUsuarios(usuarios) {
        const tbody = document.getElementById('usuariosList');
        if (!tbody) return;

        if (usuarios.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center">No hay usuarios</td></tr>';
            return;
        }

        tbody.innerHTML = usuarios.map(usuario => `
            <tr>
                <td><strong>${usuario.codigo_usuario || '-'}</strong></td>
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
                        ${usuario.estado === 'activo' ? 
                            `<button class="btn btn-sm btn-danger" onclick="dashboard.bloquearUsuario(${usuario.id})">Bloquear</button>` :
                            usuario.estado === 'bloqueado' ?
                            `<button class="btn btn-sm" onclick="dashboard.desbloquearUsuario(${usuario.id})">Desbloquear</button>` :
                            ''
                        }
                    </div>
                </td>
            </tr>
        `).join('');
    }

    llenarPaginacionUsuarios(paginacion) {
        const container = document.getElementById('usuariosPaginacion');
        if (!container) return;

        let html = '';
        
        // Botón anterior
        if (paginacion.pagina_actual > 1) {
            html += `<button onclick="dashboard.irPaginaUsuarios(${paginacion.pagina_actual - 1})">← Anterior</button>`;
        }

        // Números de página
        for (let i = 1; i <= paginacion.total_paginas; i++) {
            if (i === paginacion.pagina_actual) {
                html += `<button class="active">${i}</button>`;
            } else {
                html += `<button onclick="dashboard.irPaginaUsuarios(${i})">${i}</button>`;
            }
        }

        // Botón siguiente
        if (paginacion.pagina_actual < paginacion.total_paginas) {
            html += `<button onclick="dashboard.irPaginaUsuarios(${paginacion.pagina_actual + 1})">Siguiente →</button>`;
        }

        container.innerHTML = html;
    }

    irPaginaUsuarios(pagina) {
        this.paginaActualUsuarios = pagina;
        this.cargarUsuarios();
    }

    async abrirModalUsuario(usuarioId = null) {
        const modal = document.getElementById('usuarioModal');
        const form = document.getElementById('usuarioForm');
        const title = document.getElementById('usuarioModalTitle');
        const grupoCodigo = document.getElementById('grupoCodigo');
        const seccionComision = document.getElementById('seccionComision');
        
        // Resetear formulario
        form.reset();
        seccionComision.style.display = 'none';
        
        // Llenar select de referentes
        await this.llenarSelectReferentes(usuarioId);

        if (usuarioId) {
            title.textContent = 'Editar Usuario';
            grupoCodigo.style.display = 'block';
            
            try {
                const resultado = await api.obtenerUsuario(usuarioId);
                if (resultado.exito) {
                    const u = resultado.datos;
                    document.getElementById('usuarioId').value = u.id;
                    document.getElementById('usuarioCodigo').value = u.codigo_usuario || 'Pendiente';
                    document.getElementById('usuarioCedula').value = u.cedula;
                    document.getElementById('usuarioNombre').value = u.nombre;
                    document.getElementById('usuarioApellido').value = u.apellido;
                    document.getElementById('usuarioEmail').value = u.email;
                    document.getElementById('usuarioUsername').value = u.username;
                    document.getElementById('usuarioPerfil').value = u.perfil_id;
                    
                    document.getElementById('usuarioEsComisionante').checked = u.es_comisionante == 1;
                    if (u.es_comisionante == 1) {
                        seccionComision.style.display = 'block';
                        document.getElementById('usuarioComision').value = u.porcentaje_comision;
                        document.getElementById('usuarioComisionRed').value = u.porcentaje_comision_red;
                        document.getElementById('usuarioReferente').value = u.referente_id || '';
                    }
                }
            } catch (error) {
                console.error('Error cargando usuario:', error);
            }
        } else {
            title.textContent = 'Crear Usuario';
            grupoCodigo.style.display = 'none';
            document.getElementById('usuarioId').value = '';
        }

        modal.classList.add('active');
    }

    async llenarSelectReferentes(usuarioAExcluir = null) {
        const select = document.getElementById('usuarioReferente');
        if (!select) return;

        try {
            const resultado = await api.listarUsuarios(1, 1000);
            if (resultado.exito) {
                select.innerHTML = '<option value="">Ninguno (Raíz)</option>';
                resultado.datos.usuarios.forEach(u => {
                    if (u.id != usuarioAExcluir) {
                        const option = document.createElement('option');
                        option.value = u.id;
                        option.textContent = `${u.nombre} ${u.apellido} (${u.username})`;
                        select.appendChild(option);
                    }
                });
            }
        } catch (error) {
            console.error('Error cargando referentes:', error);
        }
    }

    async guardarUsuario() {
        const id = document.getElementById('usuarioId').value;
        const datos = {
            cedula: document.getElementById('usuarioCedula').value,
            nombre: document.getElementById('usuarioNombre').value,
            apellido: document.getElementById('usuarioApellido').value,
            email: document.getElementById('usuarioEmail').value,
            username: document.getElementById('usuarioUsername').value,
            perfil_id: document.getElementById('usuarioPerfil').value,
            es_comisionante: document.getElementById('usuarioEsComisionante').checked ? 1 : 0,
            porcentaje_comision: document.getElementById('usuarioComision').value || 0,
            porcentaje_comision_red: document.getElementById('usuarioComisionRed').value || 0,
            referente_id: document.getElementById('usuarioReferente').value || null
        };

        try {
            let resultado;
            if (id) {
                resultado = await api.editarUsuario(id, datos);
            } else {
                resultado = await api.crearUsuario(datos);
            }

            if (resultado.exito) {
                alert(resultado.mensaje);
                if (resultado.password_temporal) {
                    alert('NUEVO USUARIO CREADO\n\nContraseña Temporal: ' + resultado.password_temporal + '\n\nEnvíe estos accesos al usuario.');
                }
                cerrarModal('usuarioModal');
                this.cargarUsuarios();
            } else {
                alert('Error: ' + resultado.mensaje);
            }
        } catch (error) {
            console.error('Error guardando usuario:', error);
            alert('Error de conexión al guardar el usuario');
        }
    }

    editarUsuario(usuarioId) {
        this.abrirModalUsuario(usuarioId);
    }

    async bloquearUsuario(usuarioId) {
        const razon = prompt('Ingresa el motivo del bloqueo:');
        if (razon) {
            const resultado = await api.bloquearUsuario(usuarioId, razon);
            if (resultado.exito) {
                alert('Usuario bloqueado exitosamente');
                this.cargarUsuarios();
            } else {
                alert('Error: ' + resultado.mensaje);
            }
        }
    }

    async desbloquearUsuario(usuarioId) {
        const resultado = await api.desbloquearUsuario(usuarioId);
        if (resultado.exito) {
            alert('Usuario desbloqueado exitosamente');
            this.cargarUsuarios();
        } else {
            alert('Error: ' + resultado.mensaje);
        }
    }

    abrirModalPerfil(perfilId = null) {
        const modal = document.getElementById('perfilModal');
        const form = document.getElementById('perfilForm');
        const title = document.getElementById('perfilModalTitle');
        const idInput = document.getElementById('perfilId');

        if (perfilId) {
            title.textContent = 'Editar Perfil';
            const perfil = this.perfilesCache.find(p => p.id == perfilId);
            if (perfil) {
                idInput.value = perfil.id;
                document.getElementById('perfilNombre').value = perfil.nombre_perfil;
                document.getElementById('perfilNivel').value = perfil.nivel_jerarquico;
                document.getElementById('perfilDescripcion').value = perfil.descripcion || '';
            }
        } else {
            title.textContent = 'Crear Perfil';
            form.reset();
            idInput.value = '';
        }

        modal.classList.add('active');
    }

    async guardarPerfil() {
        const id = document.getElementById('perfilId').value;
        const datos = {
            nombre_perfil: document.getElementById('perfilNombre').value,
            nivel_jerarquico: parseInt(document.getElementById('perfilNivel').value),
            descripcion: document.getElementById('perfilDescripcion').value
        };

        try {
            let resultado;
            if (id) {
                resultado = await api.editarPerfil(id, datos);
            } else {
                resultado = await api.crearPerfil(datos);
            }

            if (resultado.exito) {
                alert(resultado.mensaje || 'Perfil guardado exitosamente');
                cerrarModal('perfilModal');
                this.cargarPerfiles();
            } else {
                alert('Error: ' + resultado.mensaje);
            }
        } catch (error) {
            console.error('Error guardando perfil:', error);
            alert('Error de conexión al guardar el perfil');
        }
    }

    editarPerfil(perfilId) {
        this.abrirModalPerfil(perfilId);
    }

    async logout() {
        const resultado = await api.logout();
        if (resultado.exito) {
            window.location.href = '/PLATAFORMA_INTEGRADA/frontend/';
        }
    }

    // --- SISTEMA DE CONSULTA GLOBAL ---

    async abrirDetalleGlobal(categoria, id, datosAdicionales = {}) {
        const modal = document.getElementById('globalQueryModal');
        const title = document.getElementById('globalModalTitle');
        const body = document.getElementById('globalModalBody');
        
        if (!modal || !body) return;

        // Reset y mostrar cargando
        body.innerHTML = '<div class="empty-state">Generando ficha de consulta...</div>';
        modal.classList.add('active');

        try {
            let contenidoHTML = '';
            
            if (categoria === 'actividad') {
                title.innerHTML = '🔍 Ficha de Auditoría de Actividad';
                const detalle = await api.obtenerDetalleActividad(id);
                if (detalle.exito) {
                    contenidoHTML = this.renderizarFichaActividad(detalle.datos);
                } else {
                    contenidoHTML = `<p class="error">No se pudo cargar el detalle: ${detalle.mensaje}</p>`;
                }
            } 
            else if (categoria === 'estadistica') {
                title.innerHTML = `📊 Desglose: ${datosAdicionales.titulo}`;
                contenidoHTML = this.renderizarFichaEstadistica(datosAdicionales);
            }

            body.innerHTML = contenidoHTML;

        } catch (error) {
            console.error('Error al abrir detalle global:', error);
            body.innerHTML = '<p class="error">Ocurrío un error al procesar la solicitud.</p>';
        }
    }

    renderizarFichaActividad(data) {
        return `
            <div class="detail-sheet">
                <div class="detail-header">
                    <div class="detail-logo">
                        <img src="assets/mqf-logo-sidebar.ico" alt="MQF">
                        <span style="font-weight:bold; color:var(--primary-color);">MAS QUE FIANZAS</span>
                    </div>
                    <div class="detail-title">
                        <h1>FICHA DE AUDITORÍA</h1>
                        <p>ID Transacción: #ACT-${data.id.toString().padStart(5, '0')}</p>
                    </div>
                </div>

                <div class="detail-grid">
                    <div class="detail-item">
                        <div class="detail-label">Fecha y Hora</div>
                        <div class="detail-value">${new Date(data.fecha_evento).toLocaleString()}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Usuario Responsable</div>
                        <div class="detail-value">${data.usuario_nombre || 'Desconocido'} (@${data.username || 'n/a'})</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Módulo Accedido</div>
                        <div class="detail-value" style="text-transform: uppercase;">${data.modulo_accedido}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Tipo de Evento</div>
                        <div class="detail-value">${data.tipo_evento}</div>
                    </div>
                    <div class="detail-item" style="grid-column: 1 / -1;">
                        <div class="detail-label">Descripción del Suceso</div>
                        <div class="detail-value" style="background:#f8fafc; padding:10px; border-radius:4px; border:1px solid #e2e8f0;">
                            ${data.descripcion_evento}
                        </div>
                    </div>
                </div>

                <div style="margin-top:20px; font-size:12px; color:#94a3b8; border-top:1px dashed #e2e8f0; padding-top:10px;">
                    <p>Documento generado dinámicamente para fines de auditoría interna y seguridad de la información.</p>
                </div>
            </div>
        `;
    }

    renderizarFichaEstadistica(data) {
        // En un caso real, aquí se llamaría a un endpoint para traer el desglose.
        // Simulamos un desglose corporativo premium.
        return `
            <div class="detail-sheet">
                <div class="detail-header">
                    <div class="detail-logo">
                        <img src="assets/mqf-logo-sidebar.ico" alt="MQF">
                        <span style="font-weight:bold; color:var(--primary-color);">MAS QUE FIANZAS</span>
                    </div>
                    <div class="detail-title">
                        <h1>DESGLOSE ESTADÍSTICO</h1>
                        <p>${new Date().toLocaleDateString()}</p>
                    </div>
                </div>

                <div style="text-align:center; margin-bottom:30px; padding:20px; background:#f0f9ff; border-radius:12px;">
                    <div style="font-size:48px; margin-bottom:10px;">${data.icono}</div>
                    <div style="font-size:32px; font-weight:bold; color:var(--primary-color);">${data.valor}</div>
                    <div style="font-size:14px; color:#64748b; text-transform:uppercase; letter-spacing:1px;">${data.titulo}</div>
                </div>

                <div class="detail-grid">
                    <div class="detail-item">
                        <div class="detail-label">Período Actual</div>
                        <div class="detail-value">${new Date().toLocaleString('es-ES', {month: 'long', year: 'numeric'})}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Estado de Sincronización</div>
                        <div class="detail-value text-success">● En Tiempo Real</div>
                    </div>
                </div>

                <p style="color:#64748b; font-size:13px; font-style:italic;">
                    * Este reporte muestra un resumen consolidado de la categoría seleccionada. Para un análisis más exhaustivo, diríjase al módulo de <strong>Reportes Avanzados</strong>.
                </p>
            </div>
        `;
    }
}

// Funciones globales de ayuda (si no están en la clase)
function imprimirDetalleModal() {
    window.print();
}

// Funciones globales para modales
function cerrarModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
    }
}

// Inicializar dashboard cuando carga la página
let dashboard;
document.addEventListener('DOMContentLoaded', function() {
    dashboard = new Dashboard();
});

// =====================================================
// MI PERFIL — funciones globales
// =====================================================
async function abrirMiPerfil() {
    const status = document.getElementById('perfilGuardarStatus');
    if (status) { status.textContent = ''; status.style.color = ''; }

    // Pre-llenar inmediatamente con datos del localStorage
    const usuarioLocal = JSON.parse(localStorage.getItem('usuario_actual') || '{}');
    // Separar nombre/apellido si vienen como nombre_completo
    let nomLocal = usuarioLocal.nombre || '';
    let apLocal  = usuarioLocal.apellido || '';
    if (!nomLocal && usuarioLocal.nombre_completo) {
        const parts = usuarioLocal.nombre_completo.trim().split(' ');
        nomLocal = parts[0] || '';
        apLocal  = parts.slice(1).join(' ') || '';
    }
    document.getElementById('perfilNombreEdit').value   = nomLocal;
    document.getElementById('perfilApellidoEdit').value = apLocal;
    document.getElementById('perfilTelefonoEdit').value = usuarioLocal.telefono || '';
    document.getElementById('perfilEmailEdit').value    = usuarioLocal.email || '';
    document.getElementById('perfilUsernameEdit').value = usuarioLocal.username || '';
    document.getElementById('perfilRolEdit').value      = usuarioLocal.perfil || usuarioLocal.nombre_perfil || '';
    document.getElementById('perfilFotoStatus').textContent = '';

    // Abrir modal ya con datos previos
    document.getElementById('modalMiPerfil').classList.add('active');

    // Luego buscar datos completos desde el backend (foto_perfil, teléfono, etc.)
    try {
        const token = localStorage.getItem('token_sesion') || '';
        const resp = await fetch('/PLATAFORMA_INTEGRADA/backend/api/mi_perfil.php', {
            credentials: 'include',
            headers: { 'Authorization': 'Bearer ' + token }
        });
        if (!resp.ok) return;
        const data = await resp.json();
        if (data.exito && data.datos) {
            const d = data.datos;
            document.getElementById('perfilNombreEdit').value   = d.nombre || '';
            document.getElementById('perfilApellidoEdit').value = d.apellido || '';
            document.getElementById('perfilTelefonoEdit').value = d.telefono || '';
            document.getElementById('perfilEmailEdit').value    = d.email || '';
            document.getElementById('perfilUsernameEdit').value = d.username || '';
            document.getElementById('perfilRolEdit').value      = d.nombre_perfil || '';
            const foto = document.getElementById('perfilFotoPreview');
            foto.src = d.foto_perfil ? d.foto_perfil + '?t=' + Date.now() : '';
        }
    } catch(e) { console.warn('No se pudo cargar perfil del backend:', e); }
}


function previewFotoPerfil(input) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById('perfilFotoPreview').src = e.target.result;
    };
    reader.readAsDataURL(input.files[0]);
    document.getElementById('perfilFotoStatus').textContent = 'Foto seleccionada. Guarda para subirla.';
    // Subir inmediatamente
    subirFotoPerfil(input.files[0]);
}

async function subirFotoPerfil(file) {
    const statusEl = document.getElementById('perfilFotoStatus');
    statusEl.textContent = 'Subiendo foto...';
    statusEl.style.color = '#6366f1';
    const formData = new FormData();
    formData.append('foto', file);
    try {
        const token = localStorage.getItem('token_sesion') || '';
        const resp = await fetch('/PLATAFORMA_INTEGRADA/backend/api/mi_perfil.php', {
            method: 'POST',
            credentials: 'include',
            headers: { 'Authorization': 'Bearer ' + token },
            body: formData
        });
        const data = await resp.json();
        if (data.exito) {
            statusEl.textContent = '✅ Foto actualizada.';
            statusEl.style.color = '#16a34a';
            const avatarHeader = document.querySelector('.user-avatar');
            if (avatarHeader && data.datos && data.datos.foto_url) {
                avatarHeader.src = data.datos.foto_url + '?t=' + Date.now();
            }
        } else {
            statusEl.textContent = '❌ ' + (data.mensaje || 'Error al subir foto.');
            statusEl.style.color = '#ef4444';
        }
    } catch(e) {
        statusEl.textContent = '❌ Error de conexión.';
        statusEl.style.color = '#ef4444';
    }
}

async function guardarMiPerfil() {
    const nombre   = document.getElementById('perfilNombreEdit').value.trim();
    const apellido = document.getElementById('perfilApellidoEdit').value.trim();
    const telefono = document.getElementById('perfilTelefonoEdit').value.trim();
    const statusEl = document.getElementById('perfilGuardarStatus');

    if (!nombre || !apellido) {
        statusEl.textContent = '⚠️ Nombre y apellido son requeridos.';
        statusEl.style.color = '#f59e0b';
        return;
    }
    statusEl.textContent = 'Guardando...';
    statusEl.style.color = '#6366f1';
    try {
        const token = localStorage.getItem('token_sesion') || '';
        const resp = await fetch('/PLATAFORMA_INTEGRADA/backend/api/mi_perfil.php', {
            method: 'PUT',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + token
            },
            body: JSON.stringify({ nombre, apellido, telefono })
        });
        const data = await resp.json();
        if (data.exito) {
            statusEl.textContent = '✅ Perfil actualizado exitosamente.';
            statusEl.style.color = '#16a34a';
            const userName = document.getElementById('userName');
            if (userName) userName.textContent = nombre + ' ' + apellido;
            // Actualizar localStorage
            const usr = JSON.parse(localStorage.getItem('usuario_actual') || '{}');
            usr.nombre = nombre; usr.apellido = apellido; usr.nombre_completo = nombre + ' ' + apellido;
            localStorage.setItem('usuario_actual', JSON.stringify(usr));
            setTimeout(() => cerrarModal('modalMiPerfil'), 1500);
        } else {
            statusEl.textContent = '❌ ' + (data.mensaje || 'Error al guardar.');
            statusEl.style.color = '#ef4444';
        }
    } catch(e) {
        statusEl.textContent = '❌ Error de conexión.';
        statusEl.style.color = '#ef4444';
    }
}


// =====================================================
// CAMBIAR CONTRASEÑA — funciones globales
// =====================================================
function abrirCambiarPassword() {
    ['passActual','passNueva','passConfirmar'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    const status = document.getElementById('passStatus');
    if (status) { status.textContent = ''; }
    document.getElementById('modalCambiarPassword').classList.add('active');
}

async function confirmarCambioPassword() {
    const actual    = document.getElementById('passActual').value;
    const nueva     = document.getElementById('passNueva').value;
    const confirmar = document.getElementById('passConfirmar').value;
    const statusEl  = document.getElementById('passStatus');
    const btn       = document.getElementById('btnGuardarPass');

    if (!actual || !nueva || !confirmar) {
        statusEl.textContent = '⚠️ Todos los campos son requeridos.';
        statusEl.style.color = '#f59e0b'; return;
    }
    if (nueva !== confirmar) {
        statusEl.textContent = '⚠️ Las contraseñas nuevas no coinciden.';
        statusEl.style.color = '#f59e0b'; return;
    }
    if (nueva.length < 8) {
        statusEl.textContent = '⚠️ La nueva contraseña debe tener al menos 8 caracteres.';
        statusEl.style.color = '#f59e0b'; return;
    }
    btn.disabled = true;
    statusEl.textContent = 'Actualizando contraseña...';
    statusEl.style.color = '#6366f1';
    try {
        // Usar el método del api-client que ya maneja el Bearer token
        const resp = await api.cambiarPassword(actual, nueva, confirmar);
        if (resp.exito) {
            statusEl.textContent = '✅ Contraseña actualizada exitosamente.';
            statusEl.style.color = '#16a34a';
            setTimeout(() => cerrarModal('modalCambiarPassword'), 1800);
        } else {
            statusEl.textContent = '❌ ' + (resp.mensaje || 'Error al actualizar.');
            statusEl.style.color = '#ef4444';
        }
    } catch(e) {
        statusEl.textContent = '❌ Error de conexión con el servidor.';
        statusEl.style.color = '#ef4444';
    } finally {
        btn.disabled = false;
    }
}

