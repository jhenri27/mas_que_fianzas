
const getApiPrefix = () => {
    const p = window.location.pathname;
    if (p.indexOf('/dev_plataforma/') !== -1) return '/dev_plataforma/';
    if (p.indexOf('/PLATAFORMA_INTEGRADA/dev/') !== -1) return '/PLATAFORMA_INTEGRADA/dev/';
    if (p.indexOf('/dev/') !== -1) return '/dev/';
    if (p.indexOf('/PLATAFORMA_INTEGRADA/') !== -1) return '/PLATAFORMA_INTEGRADA/';
    return '/';
};

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
        console.log('[Dashboard] Inicializando...');
        
        // 1. Configurar Listeners primero (Prioridad para que la UI responda)
        try {
            this.setupEventListeners();
        } catch (e) {
            console.error('[Dashboard] Error en setupEventListeners:', e);
        }

        // 2. Aplicar Skin
        try {
            this.initSkin();
        } catch (e) {
            console.warn('[Dashboard] Falló initSkin, usando default.', e);
        }

        // 3. Verificar Sesión
        if (!api.tieneSesion()) {
            console.warn('[Dashboard] Sesión no detectada, redirigiendo a login...');
            window.location.href = 'index.html';
            return;
        }

        // 4. Cargar UI y Datos
        try {
            this.setupUI();
            this.cargarDatos();
        } catch (e) {
            console.error('[Dashboard] Error en carga de UI/Datos:', e);
        }
        
        console.log('[Dashboard] Listo.');
    }

    setupUI() {
        // Inyectar distintivo visual si estamos en ambiente DEV
        const esAmbienteDev = window.location.pathname.includes('/dev');
        if (esAmbienteDev) {
            const pageTitle = document.getElementById('pageTitle');
            if (pageTitle && !document.getElementById('devEnvHeaderBadge')) {
                const badge = document.createElement('span');
                badge.id = 'devEnvHeaderBadge';
                badge.style.cssText = 'background: linear-gradient(135deg, #d97706, #b45309); color: #ffffff; font-weight: 800; font-size: 11px; padding: 4px 12px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.8px; box-shadow: 0 0 12px rgba(217, 119, 6, 0.6); border: 1px solid rgba(255,255,255,0.4); margin-left: 12px; vertical-align: middle; display: inline-flex; align-items: center; gap: 6px;';
                badge.innerHTML = '🧪 AMBIENTE DE DESARROLLO (DEV)';
                pageTitle.appendChild(badge);
            }
            
            if (!document.getElementById('devTopBarAlert')) {
                const topAlert = document.createElement('div');
                topAlert.id = 'devTopBarAlert';
                topAlert.style.cssText = 'background: linear-gradient(90deg, #b45309, #d97706, #b45309); color: #ffffff; font-size: 12px; font-weight: 700; text-align: center; padding: 5px 10px; letter-spacing: 0.5px; box-shadow: 0 2px 8px rgba(0,0,0,0.3); z-index: 100000; position: relative; width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px;';
                topAlert.innerHTML = '🧪 <strong>MODO DESARROLLO (DEV)</strong> — Estás operando en el entorno de pruebas de MÁS QUE FIANZAS.';
                document.body.insertBefore(topAlert, document.body.firstChild);
            }
        }

        // Actualizar información del usuario
        const userName = document.getElementById('userName');
        if (this.usuarioActual && this.usuarioActual.nombre_completo) {
            userName.textContent = this.usuarioActual.nombre_completo;
        }

        // Actualizar avatar de usuario
        const userAvatar = document.querySelector('.user-avatar');
        if (userAvatar && this.usuarioActual && this.usuarioActual.foto_perfil) {
            userAvatar.src = this.usuarioActual.foto_perfil + '?t=' + Date.now();
        }

        // Sincronizar perfil con backend de manera asíncrona
        this.sincronizarPerfilConBackend();

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

        // Configurar menú lateral dinámico según permisos de base de datos (Doble Capa de Seguridad)
        if (this.usuarioActual) {
            let perfilId = parseInt(this.usuarioActual.perfil_id, 10);
            
            // Fallback robusto de perfilId por caché o localStorage antiguo
            if (isNaN(perfilId)) {
                const perfilLower = (this.usuarioActual.perfil || '').toLowerCase();
                if (perfilLower.includes('pdv') || perfilLower.includes('socio')) {
                    perfilId = 5;
                } else if (perfilLower.includes('admin') || perfilLower.includes('sistema')) {
                    perfilId = 1;
                }
            }

            // Bypass global para Administrador (ID 1)
            if (perfilId === 1) {
                document.querySelectorAll('.nav-item').forEach(item => {
                    item.style.display = 'flex';
                });
                
                // Fetch Admin Sys Info
                fetch(getApiPrefix() + 'backend/api/system_info.php', {
                    headers: { 'Authorization': 'Bearer ' + (localStorage.getItem('token_sesion') || '') }
                })
                .then(resp => resp.json())
                .then(result => {
                    if (result.exito) {
                        const sysInfoEl = document.getElementById('admin-sys-info');
                        if (sysInfoEl) {
                            sysInfoEl.style.display = 'block';
                            document.getElementById('sys-version').textContent = result.version;
                            document.getElementById('sys-db').textContent = result.db_name;
                            document.getElementById('sys-patch').textContent = result.last_update;
                        }
                    }
                })
                .catch(err => console.error('Error fetching sys info:', err));
            }
            
            // Carga dinámica de permisos en BD
            const token = localStorage.getItem('token_sesion') || '';
            fetch(`${getApiPrefix()}backend/api/perfiles.php/obtener/${perfilId}`, {
                headers: { 'Authorization': 'Bearer ' + token }
            })
            .then(resp => resp.json())
            .then(result => {
                if (result.exito && result.datos && Array.isArray(result.datos.permisos)) {
                    const permisos = result.datos.permisos;
                    window.MQF_PERMISOS = permisos; // Guardado global para iframes (Norma NOFTRAB)
                    window.MQF_PERMISOS_NOFTRAB = permisos.map(p => typeof p === 'string' ? p : (p.codigo_funcion || p.codigo || p.permiso || ''));
                    const modulosPermitidos = {'dashboard': true, 'mi-perfil': true};
                    
                    // Mapear los módulos que sí tienen al menos una función ejecutable de forma dinámica
                    permisos.forEach(p => {
                        const moduloName = p.nombre_modulo;
                        if (moduloName && parseInt(p.puede_ejecutar) === 1) {
                            modulosPermitidos[moduloName] = true;
                        }
                    });
                    
                    // Ocultar nav-items no autorizados
                    document.querySelectorAll('.nav-item').forEach(item => {
                        const moduleName = item.dataset.module;
                        if (moduleName === 'perfil_data') {
                            item.style.display = 'flex';
                        } else if (moduleName && !modulosPermitidos[moduleName]) {
                            item.style.display = 'none';
                        } else {
                            item.style.display = 'flex';
                        }
                    });
                    
                    // Ocultar acciones rápidas del dashboard
                    document.querySelectorAll('.action-btn').forEach(btn => {
                        const action = btn.dataset.action;
                        let requerido = '';
                        if (action === 'nueva-cotizacion') requerido = 'cotizaciones';
                        else if (action === 'nuevo-cliente') requerido = 'clientes';
                        else if (action === 'registrar-pago') requerido = 'pagos';
                        else if (action === 'ver-reportes') requerido = 'reportes';
                        
                        if (requerido && !modulosPermitidos[requerido]) {
                            btn.style.display = 'none';
                        } else {
                            btn.style.display = 'inline-flex';
                        }
                    });
                } else {
                    // Fallback preventivo si no devuelve permisos exitosos
                    console.warn('[Dashboard] API de perfiles no retornó permisos válidos. Aplicando fallback preventivo.');
                    if (this.usuarioActual.perfil === 'Socio Comercial PDV') {
                        const fallback = ['dashboard', 'cotizaciones', 'clientes', 'polizas', 'reportes', 'mi-perfil', 'perfil_data'];
                        document.querySelectorAll('.nav-item').forEach(item => {
                            const moduleName = item.dataset.module;
                            if (!fallback.includes(moduleName)) {
                                item.style.display = 'none';
                            } else {
                                item.style.display = 'flex';
                            }
                        });
                    }
                }
            })
            .catch(err => {
                console.error('[Dashboard] Falló la carga del menú dinámico por BD, aplicando fallback:', err);
                
                // Fallback por defecto si no hay conexión (Socio Comercial PDV limitado)
                if (this.usuarioActual.perfil === 'Socio Comercial PDV') {
                    const fallback = ['dashboard', 'cotizaciones', 'clientes', 'polizas', 'reportes', 'mi-perfil', 'perfil_data'];
                    document.querySelectorAll('.nav-item').forEach(item => {
                        const moduleName = item.dataset.module;
                        if (!fallback.includes(moduleName)) item.style.display = 'none';
                    });
                }
            });
        }
    }

    async sincronizarPerfilConBackend() {
        try {
            const token = localStorage.getItem('token_sesion') || '';
            if (!token) return;
            const resp = await fetch(getApiPrefix() + 'backend/api/mi_perfil.php', {
                credentials: 'include',
                headers: { 'Authorization': 'Bearer ' + token }
            });
            if (!resp.ok) return;
            const data = await resp.json();
            if (data.exito && data.datos) {
                const d = data.datos;
                
                // Actualizar localmente
                const userName = document.getElementById('userName');
                if (userName) userName.textContent = d.nombre + ' ' + d.apellido;
                
                const userGreeting = document.getElementById('userGreeting');
                if (userGreeting) {
                    const hora = new Date().getHours();
                    let saludo = '¡Hola! ';
                    if (hora < 12) saludo += 'Buenos días';
                    else if (hora < 18) saludo += 'Buenas tardes';
                    else saludo += 'Buenas noches';
                    userGreeting.textContent = saludo + ', ' + (d.nombre + ' ' + d.apellido);
                }

                const userAvatar = document.querySelector('.user-avatar');
                if (userAvatar && d.foto_perfil) {
                    userAvatar.src = d.foto_perfil + '?t=' + Date.now();
                }

                // Actualizar localStorage
                const usr = JSON.parse(localStorage.getItem('usuario_actual') || '{}');
                usr.nombre = d.nombre;
                usr.apellido = d.apellido;
                usr.nombre_completo = d.nombre + ' ' + d.apellido;
                usr.foto_perfil = d.foto_perfil;
                localStorage.setItem('usuario_actual', JSON.stringify(usr));
                this.usuarioActual = usr;
            }
        } catch(e) {
            console.warn('[Dashboard] No se pudo sincronizar perfil con el backend:', e);
        }
    }



    // ── SISTEMA DE SKINS (UX-SKINS) ───────────────────────────────────────────
    initSkin() {
        const savedSkin = localStorage.getItem('mqf-skin') || 'indigo';
        const autoTheme = localStorage.getItem('mqf-auto-theme') === 'true';
        
        let skinToApply = savedSkin;
        
        if (autoTheme) {
            const hora = new Date().getHours();
            if (hora >= 19 || hora < 7) {
                skinToApply = 'obsidian';
            } else {
                if (savedSkin !== 'custom' && savedSkin !== 'coral') {
                    skinToApply = 'indigo';
                }
            }
        }
        
        this.aplicarSkin(skinToApply);
    }

    aplicarSkin(skin) {
        document.body.setAttribute('data-skin', skin);
        document.documentElement.setAttribute('data-skin', skin);
        localStorage.setItem('mqf-skin', skin);

        // Broadcast a todos los iframes cargados
        document.querySelectorAll('iframe').forEach(iframe => {
            try {
                if (iframe.contentWindow) {
                    iframe.contentWindow.postMessage({ type: 'mqf-skin-set', skin: skin }, '*');
                }
            } catch (err) {
                console.warn('[Dashboard] No se pudo enviar skin a iframe:', err);
            }
        });
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

        // Menu toggle mobile (Off-Canvas Drawer & Backdrop)
        document.querySelectorAll('.menu-toggle').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const sidebar = document.querySelector('.sidebar');
                if (!sidebar) return;

                sidebar.classList.toggle('mobile-open');
                sidebar.classList.toggle('active');

                let backdrop = document.querySelector('.sidebar-backdrop');
                if (!backdrop) {
                    backdrop = document.createElement('div');
                    backdrop.className = 'sidebar-backdrop';
                    document.body.appendChild(backdrop);
                    backdrop.addEventListener('click', () => {
                        sidebar.classList.remove('mobile-open', 'active');
                        backdrop.classList.remove('active');
                    });
                }

                const isOpened = sidebar.classList.contains('mobile-open') || sidebar.classList.contains('active');
                if (isOpened) {
                    backdrop.classList.add('active');
                } else {
                    backdrop.classList.remove('active');
                }
            });
        });

        // Cierre automático del menú móvil al seleccionar cualquier módulo
        document.querySelectorAll('.sidebar-nav .nav-item').forEach(item => {
            item.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    const sidebar = document.querySelector('.sidebar');
                    const backdrop = document.querySelector('.sidebar-backdrop');
                    if (sidebar) sidebar.classList.remove('mobile-open', 'active');
                    if (backdrop) backdrop.classList.remove('active');
                }
            });
        });

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

        // Listener de mensajes para UX-SKINS
        window.addEventListener('message', (e) => {
            if (!e.data || !e.data.type) return;

            if (e.data.type === 'mqf-skin-apply') {
                this.aplicarSkin(e.data.skin);
            } else if (e.data.type === 'mqf-skin-preview') {
                document.body.setAttribute('data-skin', e.data.skin);
                document.documentElement.setAttribute('data-skin', e.data.skin);
                document.querySelectorAll('iframe').forEach(f => {
                    try { f.contentWindow.postMessage({ type: 'mqf-skin-set', skin: e.data.skin }, '*'); } catch(err) {}
                });
            }
        });


        // User menu dropdown
        const userMenuToggle = document.getElementById('userMenuToggle');
        const userDropdownMenu = document.getElementById('userDropdownMenu');
        if (userMenuToggle && userDropdownMenu) {
            userMenuToggle.addEventListener('click', (e) => {
                e.stopPropagation();
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

        // Evento para toda la tarjeta del widget de Pólizas Emitidas (OFTRAB Premium)
        const widgetPolizas = document.querySelector('.polizas-widget-card');
        if (widgetPolizas) {
            widgetPolizas.addEventListener('click', (e) => {
                // Evitar doble disparo si se hace clic en el botón de expandir (que ya tiene onclick inline)
                if (!e.target.closest('.expand-btn')) {
                    abrirDetallePolizas();
                }
            });
        }

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
            await this.cargarEstadisticasPolizas();
        } catch (error) {
            console.error('[Dashboard] Error en cargarEstadisticasPolizas:', error);
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
            const resp = await fetch(getApiPrefix() + 'backend/api/cotizaciones.php?action=listar&limite=500');
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

    async cargarEstadisticasPolizas() {
        try {
            const respuesta = await api.solicitud('/polizas_stats.php');
            if (respuesta.exito && respuesta.data) {
                const stats = respuesta.data;
                
                const elHoy = document.getElementById('polizasHoy');
                const elSemana = document.getElementById('polizasSemana');
                const elMes = document.getElementById('polizasMes');
                
                if (elHoy) elHoy.textContent = stats.diario;
                if (elSemana) elSemana.textContent = stats.semanal;
                if (elMes) elMes.textContent = stats.mensual;
                
                const topClientesList = document.getElementById('topClientesList');
                if (topClientesList) {
                    if (!stats.top_clientes || stats.top_clientes.length === 0) {
                        topClientesList.innerHTML = '<div style="opacity: 0.6; font-size: 13px; text-align: center; padding: 10px;">No hay emisiones registradas</div>';
                        return;
                    }
                    
                    const maxPolizas = Math.max(...stats.top_clientes.map(c => c.cantidad_polizas), 1);
                    
                    topClientesList.innerHTML = stats.top_clientes.map(c => {
                        const pct = (c.cantidad_polizas / maxPolizas) * 100;
                        const cleanCedula = (c.cliente_cedula || '').replace(/[^0-9]/g, '');
                        const isCompany = cleanCedula.length === 9 || 
                                          /\b(s\.?r\.?l\.?|s\.?a\.?|s\.?a\.?s\.?|inc|group|corp|ltda|inversiones|industrias|asociacion|empresa|cooperativa|s\.r\.l|s\.a)\b/i.test(c.cliente_nombre);
                        const iconClass = isCompany ? 'fa-solid fa-building' : 'fa-solid fa-robot';
                        const iconTypeClass = isCompany ? 'client-company' : 'client-natural';
                        const labelPoli = c.cantidad_polizas === 1 ? 'póliza' : 'pólizas';
                        
                        return `
                            <div class="top-cliente-card">
                                <div class="top-cliente-icon-wrapper ${iconTypeClass}">
                                    <i class="${iconClass}"></i>
                                </div>
                                <div class="top-cliente-details">
                                    <span class="top-cliente-name" title="${c.cliente_nombre}">${c.cliente_nombre}</span>
                                    <div class="top-cliente-progress-container">
                                        <div class="top-cliente-progress-bar" style="width: ${pct}%"></div>
                                    </div>
                                </div>
                                <div class="top-cliente-badge-pill">
                                    ${c.cantidad_polizas} ${labelPoli}
                                </div>
                            </div>
                        `;
                    }).join('');
                }
            } else {
                console.error('Error al obtener estadísticas de pólizas:', respuesta.mensaje);
                const topClientesList = document.getElementById('topClientesList');
                if (topClientesList) {
                    topClientesList.innerHTML = '<div style="opacity: 0.6; font-size: 13px; text-align: center; padding: 10px;">No hay pólizas emitidas o se generó un error.</div>';
                }
            }
        } catch (error) {
            console.error('Error en cargarEstadisticasPolizas:', error);
            const topClientesList = document.getElementById('topClientesList');
            if (topClientesList) {
                topClientesList.innerHTML = '<div style="opacity: 0.6; font-size: 13px; text-align: center; padding: 10px;">Error al cargar datos. Verifique conexión.</div>';
            }
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

        // Si es auditoria_lineal, forzar carga del iframe
        if (modulo === 'auditoria_lineal') {
            const iframe = document.getElementById('auditoria-lineal-iframe');
            if (iframe && !iframe.dataset.loaded) {
                iframe.src = getApiPrefix() + 'frontend/modulos/auditoria_lineal.html?v=1';
                iframe.dataset.loaded = 'true';
            }
        }

        // Si es helpdesk, forzar carga del iframe
        if (modulo === 'helpdesk') {
            const iframe = document.getElementById('helpdesk-iframe');
            if (iframe && !iframe.dataset.loaded) {
                iframe.src = getApiPrefix() + 'frontend/modulos/helpdesk.html?v=1';
                iframe.dataset.loaded = 'true';
            }
        }

        // Si es cotizaciones, forzar carga del iframe con versión para evitar caché
        if (modulo === 'cotizaciones') {
            console.log('Loading cotizaciones module...');
            const iframe = document.getElementById('cotizador-iframe');
            if (iframe) {
                // Always reload to avoid caching issues
                iframe.src = 'modulos/cotizaciones.html?t=' + Date.now();
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
                iframe.src = 'modulos/clientes.html?v=4';
                iframe.dataset.loaded = 'true';
            }
        }

        // Si es usuarios, forzar carga del iframe
        if (modulo === 'usuarios') {
            const iframe = document.getElementById('usuarios-iframe');
            if (iframe && !iframe.dataset.loaded) {
                iframe.src = 'modulos/usuarios.html?v=1';
                iframe.dataset.loaded = 'true';
            }
        }

        // Si es fianzas, forzar carga del iframe con timestamp (Norma NOFTRAB)
        if (modulo === 'fianzas') {
            const iframe = document.getElementById('fianzas-iframe');
            if (iframe) {
                iframe.src = 'modulos/fianzas.html?t=' + Date.now();
                iframe.dataset.loaded = 'true';
            }
        }

        // Si es pagos, forzar carga del iframe
        if (modulo === 'pagos') {
            const iframe = document.getElementById('pagos-iframe');
            if (iframe && !iframe.dataset.loaded) {
                iframe.src = 'modulos/pagos.html?v=1';
                iframe.dataset.loaded = 'true';
            }
        }

        // Si es siniestros, forzar carga del iframe
        if (modulo === 'siniestros') {
            const iframe = document.getElementById('siniestros-iframe');
            if (iframe && !iframe.dataset.loaded) {
                iframe.src = 'modulos/siniestros.html?v=1';
                iframe.dataset.loaded = 'true';
            }
        }

        // Si es productos, forzar carga del iframe
        if (modulo === 'productos') {
            const iframe = document.getElementById('productos-iframe');
            if (iframe && !iframe.dataset.loaded) {
                iframe.src = 'modulos/productos.html?v=1';
                iframe.dataset.loaded = 'true';
            }
        }

        // Si es centro_financiero, forzar carga del iframe
        if (modulo === 'centro_financiero') {
            const iframe = document.getElementById('finance-iframe');
            if (iframe && !iframe.dataset.loaded) {
                iframe.src = 'modulos/centro_financiero.html?v=1';
                iframe.dataset.loaded = 'true';
            }
        }

        // Si es labs_qa, forzar carga del iframe
        if (modulo === 'labs_qa') {
            const iframe = document.getElementById('labs-iframe');
            if (iframe && !iframe.dataset.loaded) {
                iframe.src = 'modulos/labs-qa.html?v=4';
                iframe.dataset.loaded = 'true';
            }
        }

        // Si es centro_tecnico, forzar carga del iframe
        if (modulo === 'centro_tecnico') {
            const iframe = document.getElementById('centro-tecnico-iframe');
            if (iframe && !iframe.dataset.loaded) {
                iframe.src = 'modulos/centro_tecnico.html?v=1';
                iframe.dataset.loaded = 'true';
            }
        }

        // Si es modelador_pdf, forzar carga del iframe
        if (modulo === 'modelador_pdf') {
            const iframe = document.getElementById('modelador-iframe');
            if (iframe) {
                iframe.src = 'modulos/modelador_pdf.html?t=' + Date.now();
                iframe.dataset.loaded = 'true';
            }
        }

        // Si es centro_negocios, forzar carga del iframe
        if (modulo === 'centro_negocios') {
            const iframe = document.getElementById('centro-negocios-iframe');
            if (iframe && !iframe.dataset.loaded) {
                iframe.src = 'modulos/centro_negocios.html?v=1';
                iframe.dataset.loaded = 'true';
            }
        }

        // Si es perfil_data, forzar carga del iframe
        if (modulo === 'perfil_data') {
            const iframe = document.getElementById('perfil-data-iframe');
            if (iframe && !iframe.dataset.loaded) {
                iframe.src = 'modulos/perfil_data.html?v=1';
                iframe.dataset.loaded = 'true';
            }
        }

        // Actualizar visibilidad de nav-items
        document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
        const navItem = document.querySelector(`[onclick="dashboard.cambiarModulo('${modulo}')"]`);
        if (navItem) navItem.classList.add('active');

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
            'usuarios': 'USUARIOS',
            'centro_financiero': 'CENTRO FINANCIERO',
            'centro_tecnico': 'CENTRO TÉCNICO DE SEGUROS',
            'centro_negocios': 'CENTRO DE NEGOCIOS',
            'labs_qa': 'LABS-QA (CALIDAD Y ACTUALIZACIONES)',
            'modelador_pdf': 'INTEGRADOR DE FORMULARIOS-PDF',
            'configuracion': 'CONFIGURACIÓN',
            'perfil_data': 'PERFIL DATA (MIS ACCESOS)',
            'auditoria_lineal': 'AUDITORÍA LINEAL',
            'helpdesk': 'HELPDESK E INCIDENCIAS'
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
                MQF.toast(resultado.mensaje, 'success');
                if (resultado.password_temporal) {
                    MQF.toast('NUEVO USUARIO CREADO\n\nContraseña Temporal: ' + resultado.password_temporal + '\n\nEnvíe estos accesos al usuario.', 'success');
                }
                cerrarModal('usuarioModal');
                this.cargarUsuarios();
            } else {
                MQF.toast('Error: ' + resultado.mensaje, 'error');
            }
        } catch (error) {
            console.error('Error guardando usuario:', error);
            MQF.toast('Error de conexión al guardar el usuario', 'error');
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
                MQF.toast('Usuario bloqueado exitosamente', 'success');
                this.cargarUsuarios();
            } else {
                MQF.toast('Error: ' + resultado.mensaje, 'error');
            }
        }
    }

    async desbloquearUsuario(usuarioId) {
        const resultado = await api.desbloquearUsuario(usuarioId);
        if (resultado.exito) {
            MQF.toast('Usuario desbloqueado exitosamente', 'success');
            this.cargarUsuarios();
        } else {
            MQF.toast('Error: ' + resultado.mensaje, 'error');
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
                MQF.toast(resultado.mensaje || 'Perfil guardado exitosamente', 'success');
                cerrarModal('perfilModal');
                this.cargarPerfiles();
            } else {
                MQF.toast('Error: ' + resultado.mensaje, 'error');
            }
        } catch (error) {
            console.error('Error guardando perfil:', error);
            MQF.toast('Error de conexión al guardar el perfil', 'error');
        }
    }

    editarPerfil(perfilId) {
        this.abrirModalPerfil(perfilId);
    }

    async logout() {
        const resultado = await api.logout();
        window.location.href = 'index.html';
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

    async cargarRejillaPerfiles() {
        const select = document.getElementById('selectPerfilPermisos');
        if (!select) return;
        
        select.innerHTML = '<option value="">Seleccione perfil...</option>';
        if (this.perfilesCache.length === 0) {
            await this.cargarPerfiles();
        }
        
        this.perfilesCache.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.id;
            opt.textContent = p.nombre_perfil;
            select.appendChild(opt);
        });
        
        document.getElementById('wrapperMallaPermisos').style.display = 'none';
    }

    async cargarPermisosPerfilSeleccionado(perfilId) {
        const wrapper = document.getElementById('wrapperMallaPermisos');
        const tbody = document.getElementById('mallaPermisosBody');
        const status = document.getElementById('perfilPermisosStatus');
        
        if (!perfilId) {
            wrapper.style.display = 'none';
            const resumenContainer = document.getElementById('perfilResumenInfoContainer');
            if (resumenContainer) resumenContainer.style.display = 'none';
            return;
        }
        
        status.textContent = 'Cargando malla de permisos...';
        status.style.color = '#3b82f6';
        tbody.innerHTML = '<tr><td colspan="9" style="text-align:center; padding: 20px; opacity:0.6;">Cargando módulos y permisos...</td></tr>';
        wrapper.style.display = 'block';
        
        try {
            const token = localStorage.getItem('token_sesion') || '';
            
            // 1. Obtener todos los módulos y funciones
            const respModulos = await fetch(getApiPrefix() + 'backend/api/perfiles_engine.php/listar', {
                headers: { 'Authorization': 'Bearer ' + token }
            });
            const dataModulos = await respModulos.json();
            
            // 2. Obtener los permisos guardados del perfil seleccionado
            const respPermisos = await fetch(`${getApiPrefix()}backend/api/perfiles_engine.php/obtener/${perfilId}`, {
                headers: { 'Authorization': 'Bearer ' + token }
            });
            const dataPermisos = await respPermisos.json();
            
            if (!dataModulos.exito || !dataPermisos.exito) {
                tbody.innerHTML = '<tr><td colspan="9" style="text-align:center; padding: 20px; color:#ef4444;">No se pudieron cargar los datos de permisos.</td></tr>';
                status.textContent = '❌ Error al cargar';
                status.style.color = '#ef4444';
                return;
            }
            
            const modulos = dataModulos.datos || [];
            const permisosExistentes = dataPermisos.datos || [];
            
            // Crear mapa de permisos por funcion_id para rápido acceso
            const permisosMap = {};
            permisosExistentes.forEach(p => {
                permisosMap[p.funcion_id] = p;
            });
            
            let html = '';
            
            modulos.forEach(m => {
                // Cabecera del Módulo clickable (Accordion-style)
                html += `
                    <tr class="permisos-modulo-header" onclick="dashboard.toggleModuloPermisos(${m.id})" style="cursor: pointer;">
                        <td colspan="11" class="permisos-modulo-header-label" style="user-select: none;">
                            <span id="chevron-modulo-${m.id}" style="display: inline-block; width: 15px; margin-right: 5px; font-size: 10px; transition: transform 0.2s;">▶</span> 📁 MÓDULO: ${m.nombre_modulo} <span style="font-size: 11px; opacity:0.6; font-family:monospace; margin-left: 10px;">[${m.codigo_modulo}]</span>
                        </td>
                    </tr>
                `;
                
                // Funciones del módulo
                if (m.funciones && m.funciones.length > 0) {
                    m.funciones.forEach(f => {
                        const perm = permisosMap[f.id] || {};
                        const pEjecutar = perm.puede_ejecutar == 1 || !perm.id; // Activado por defecto si no hay registro
                        const pVer = perm.ver_datos == 1;
                        const pCrear = perm.crear_datos == 1;
                        const pEditar = perm.editar_datos == 1;
                        const pEliminar = perm.eliminar_datos == 1;
                        const pReporte = perm.ver_reportes == 1;
                        const pExportar = perm.exportar_datos == 1;
                        const pImportar = perm.importar_datos == 1;
                        const pImprimir = perm.imprimir_datos == 1;
                        const pSoloPropio = perm.solo_propios == 1;
                        
                        html += `
                            <tr class="permiso-row" data-modulo-id="${m.id}" data-funcion-id="${f.id}" style="display: none;">
                                <td class="permiso-nombre-td">
                                    ⚙️ ${f.nombre_funcion} <span style="font-size: 11px; opacity:0.5; font-family:monospace;">[${f.codigo_funcion}]</span>
                                </td>
                                <td style="text-align:center; padding: 10px;"><label class="mqf-toggle-switch"><input type="checkbox" class="chk-ejecutar" ${pEjecutar ? 'checked' : ''}><span></span></label></td>
                                <td style="text-align:center; padding: 10px;"><label class="mqf-toggle-switch"><input type="checkbox" class="chk-ver" ${pVer ? 'checked' : ''}><span></span></label></td>
                                <td style="text-align:center; padding: 10px;"><label class="mqf-toggle-switch"><input type="checkbox" class="chk-crear" ${pCrear ? 'checked' : ''}><span></span></label></td>
                                <td style="text-align:center; padding: 10px;"><label class="mqf-toggle-switch"><input type="checkbox" class="chk-editar" ${pEditar ? 'checked' : ''}><span></span></label></td>
                                <td style="text-align:center; padding: 10px;"><label class="mqf-toggle-switch"><input type="checkbox" class="chk-eliminar" ${pEliminar ? 'checked' : ''}><span></span></label></td>
                                <td style="text-align:center; padding: 10px;"><label class="mqf-toggle-switch"><input type="checkbox" class="chk-reportes" ${pReporte ? 'checked' : ''}><span></span></label></td>
                                <td style="text-align:center; padding: 10px;"><label class="mqf-toggle-switch"><input type="checkbox" class="chk-exportar" ${pExportar ? 'checked' : ''}><span></span></label></td>
                                <td style="text-align:center; padding: 10px;"><label class="mqf-toggle-switch"><input type="checkbox" class="chk-importar" ${pImportar ? 'checked' : ''}><span></span></label></td>
                                <td style="text-align:center; padding: 10px;"><label class="mqf-toggle-switch"><input type="checkbox" class="chk-imprimir" ${pImprimir ? 'checked' : ''}><span></span></label></td>
                                <td style="text-align:center; padding: 10px;"><label class="mqf-toggle-switch"><input type="checkbox" class="chk-propios" ${pSoloPropio ? 'checked' : ''}><span></span></label></td>
                            </tr>
                        `;
                    });
                } else {
                    html += `
                        <tr class="permiso-row" data-modulo-id="${m.id}" style="display: none;">
                            <td colspan="11" style="padding: 8px 25px; opacity:0.5; font-size:12px; font-style:italic;">No hay funciones asociadas a este módulo.</td>
                        </tr>
                    `;
                }
            });
            
            tbody.innerHTML = html;
            status.textContent = 'Malla de permisos cargada.';
            status.style.color = '#10b981';
            setTimeout(() => status.textContent = '', 2000);

            // Cargar datos del perfil data para resumen superior
            const resumenContainer = document.getElementById('perfilResumenInfoContainer');
            if (resumenContainer) {
                fetch(`${getApiPrefix()}backend/api/perfil_data.php?perfil_id=${perfilId}`, {
                    headers: { 'Authorization': 'Bearer ' + token }
                })
                .then(r => r.json())
                .then(res => {
                    if (res.exito && res.datos && res.datos.perfil) {
                        const p = res.datos.perfil;
                        document.getElementById('perfilResumenNombre').textContent = p.nombre_perfil;
                        document.getElementById('perfilResumenNivel').textContent = p.nivel_jerarquico;
                        
                        const badge = document.getElementById('perfilResumenEstado');
                        badge.textContent = p.estado.toUpperCase();
                        if (p.estado.toLowerCase() === 'activo') {
                            badge.style.background = '#dcfce7';
                            badge.style.color = '#15803d';
                        } else {
                            badge.style.background = '#fee2e2';
                            badge.style.color = '#b91c1c';
                        }
                        
                        const initial = p.nombre_perfil.charAt(0).toUpperCase();
                        document.getElementById('perfilResumenAvatar').textContent = initial;
                        resumenContainer.style.display = 'flex';
                    } else {
                        resumenContainer.style.display = 'none';
                    }
                })
                .catch(() => {
                    resumenContainer.style.display = 'none';
                });
            }
            
        } catch (error) {
            console.error('Error cargando permisos de perfil:', error);
            tbody.innerHTML = '<tr><td colspan="9" style="text-align:center; padding: 20px; color:#ef4444;">Error de conexión con el servidor.</td></tr>';
            status.textContent = '❌ Error de red';
            status.style.color = '#ef4444';
        }
    }

    async guardarPermisosPerfil() {
        const select = document.getElementById('selectPerfilPermisos');
        const perfilId = select.value;
        const status = document.getElementById('perfilPermisosStatus');
        
        if (!perfilId) return;
        
        status.textContent = 'Guardando permisos granulares...';
        status.style.color = '#3b82f6';
        
        // Recopilar todos los permisos
        const permisos = [];
        document.querySelectorAll('.permiso-row').forEach(row => {
            const moduloId = parseInt(row.dataset.moduloId, 10);
            const funcionId = parseInt(row.dataset.funcionId, 10);
            
            permisos.push({
                modulo_id: moduloId,
                funcion_id: funcionId,
                puede_ejecutar: row.querySelector('.chk-ejecutar')?.checked || false,
                ver_datos: row.querySelector('.chk-ver')?.checked || false,
                crear_datos: row.querySelector('.chk-crear')?.checked || false,
                editar_datos: row.querySelector('.chk-editar')?.checked || false,
                eliminar_datos: row.querySelector('.chk-eliminar')?.checked || false,
                ver_reportes: row.querySelector('.chk-reportes')?.checked || false,
                exportar_datos: row.querySelector('.chk-exportar')?.checked || false,
                importar_datos: row.querySelector('.chk-importar')?.checked || false,
                imprimir_datos: row.querySelector('.chk-imprimir')?.checked || false,
                solo_propios: row.querySelector('.chk-propios')?.checked || false
            });
        });
        
        try {
            const token = localStorage.getItem('token_sesion') || '';
            const resp = await fetch(`${getApiPrefix()}backend/api/perfiles_engine.php/guardar/${perfilId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + token
                },
                body: JSON.stringify(permisos)
            });
            
            const result = await resp.json();
            if (result.exito) {
                status.textContent = '✅ Permisos guardados y auditados de forma inmutable con éxito.';
                status.style.color = '#10b981';
                MQF.toast('Permisos guardados y auditados de forma inmutable con éxito.', 'success');
                setTimeout(() => status.textContent = '', 4000);
            } else {
                status.textContent = '❌ Error: ' + result.mensaje;
                status.style.color = '#ef4444';
            }
        } catch (error) {
            console.error('Error al guardar permisos:', error);
            status.textContent = '❌ Error de conexión con el servidor.';
            status.style.color = '#ef4444';
        }
    }

    toggleModuloPermisos(moduloId) {
        const rows = document.querySelectorAll(`.permiso-row[data-modulo-id="${moduloId}"]`);
        const chevron = document.getElementById(`chevron-modulo-${moduloId}`);
        if (rows.length === 0) return;
        
        const targetsCollapsed = (rows[0].style.display === 'none' || rows[0].style.display === '');
        rows.forEach(row => {
            row.style.display = targetsCollapsed ? 'table-row' : 'none';
        });
        
        if (chevron) {
            chevron.innerHTML = targetsCollapsed ? '▼' : '▶';
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // CONTROLADORES: MANTENIMIENTO DE PERFILES (ETAPA 1)
    // ══════════════════════════════════════════════════════════════════════════
    
    cambiarSubTabPerfiles(subTab) {
        const tabMalla = document.getElementById('tab-perfiles-malla');
        const tabMantenimiento = document.getElementById('tab-perfiles-mantenimiento');
        const panelMalla = document.getElementById('panel-subtab-perfiles-malla');
        const panelMantenimiento = document.getElementById('panel-subtab-perfiles-mantenimiento');
        
        if (!panelMalla || !panelMantenimiento) return;
        
        if (subTab === 'malla') {
            tabMalla.classList.add('active');
            tabMantenimiento.classList.remove('active');
            panelMalla.style.display = 'block';
            panelMantenimiento.style.display = 'none';
            this.cargarRejillaPerfiles();
        } else {
            tabMantenimiento.classList.add('active');
            tabMalla.classList.remove('active');
            panelMantenimiento.style.display = 'block';
            panelMalla.style.display = 'none';
            this.cargarPerfilesMantenimiento();
        }
    }

    async cargarPerfilesMantenimiento() {
        try {
            const token = localStorage.getItem('token_sesion') || '';
            const resp = await fetch(getApiPrefix() + 'backend/api/perfiles_mantenimiento.php?action=listar', {
                headers: { 'Authorization': 'Bearer ' + token }
            });
            const result = await resp.json();
            if (result.exito) {
                this.mantenimientoPerfilesCache = result.datos || [];
                this.renderMantenimientoPerfilesGrid();
                
                // Llenar select de heredar del modal
                const selectHereda = document.getElementById('perfilMantenimientoHereda');
                if (selectHereda) {
                    selectHereda.innerHTML = '<option value="">No copiar (Vacío)</option>';
                    this.mantenimientoPerfilesCache.forEach(p => {
                        if (p.estado === 'activo') {
                            const opt = document.createElement('option');
                            opt.value = p.id;
                            opt.textContent = p.nombre_perfil;
                            selectHereda.appendChild(opt);
                        }
                    });
                }
            } else {
                MQF.toast('Error al cargar perfiles: ' + result.mensaje, 'error');
            }
        } catch (err) {
            console.error('Error cargando mantenimiento de perfiles:', err);
            MQF.toast('Error de conexión con el servidor', 'error');
        }
    }

    renderMantenimientoPerfilesGrid() {
        const tbody = document.getElementById('perfilesMantenimientoGridBody');
        if (!tbody) return;
        
        const searchVal = (document.getElementById('perfilesSearchInput')?.value || '').toLowerCase();
        const estadoVal = document.getElementById('perfilesFiltroEstado')?.value || '';
        
        let filtered = this.mantenimientoPerfilesCache || [];
        
        if (searchVal) {
            filtered = filtered.filter(p => 
                (p.nombre_perfil || '').toLowerCase().includes(searchVal) ||
                (p.descripcion || '').toLowerCase().includes(searchVal)
            );
        }
        
        if (estadoVal) {
            filtered = filtered.filter(p => p.estado === estadoVal);
        }
        
        if (filtered.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" style="text-align:center; padding: 24px; color: var(--mqf-text-secondary, #94a3b8);">
                        No se encontraron perfiles que coincidan con los filtros.
                    </td>
                </tr>
            `;
            return;
        }
        
        tbody.innerHTML = filtered.map(p => {
            const isActivo = p.estado === 'activo';
            const estadoLabel = isActivo ? 'Activo' : 'Inactivo';
            const estadoBg = isActivo ? '#dcfce7' : '#fee2e2';
            const estadoColor = isActivo ? '#15803d' : '#b91c1c';
            
            return `
                <tr>
                    <td style="text-align:center; font-weight:700; font-family:monospace;">#${p.id}</td>
                    <td><strong>${p.nombre_perfil}</strong></td>
                    <td style="font-size:12px; color: var(--mqf-text-secondary, #64748b); max-width:250px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="${p.descripcion || ''}">${p.descripcion || '-'}</td>
                    <td style="text-align:center; font-weight:600;">Nivel ${p.nivel_jerarquico}</td>
                    <td style="text-align:center;">
                        <span class="status-badge" style="background:${estadoBg}; color:${estadoColor}; border-radius:12px; padding:4px 10px; font-size:11px; font-weight:700; display:inline-block;">
                            ${estadoLabel}
                        </span>
                    </td>
                    <td style="text-align:center;">
                        <div style="display:flex; justify-content:center; gap:6px; flex-wrap:wrap;">
                            <button type="button" class="btn btn-sm" onclick="dashboard.abrirModalMantenimientoPerfil(${p.id})" style="font-size:11px; font-weight:600; padding:4px 8px; border-radius:6px; background:#e0f2fe; color:#0369a1;">✏️ Editar</button>
                            <button type="button" class="btn btn-sm" onclick="dashboard.actualizarEstadoPerfilMantenimiento(${p.id}, '${isActivo ? 'inactivo' : 'activo'}')" style="font-size:11px; font-weight:600; padding:4px 8px; border-radius:6px; background:${isActivo ? '#fee2e2' : '#dcfce7'}; color:${isActivo ? '#991b1b' : '#166534'};">
                                ${isActivo ? '🚫 Desactivar' : '✅ Activar'}
                            </button>
                            <button type="button" class="btn btn-sm" onclick="dashboard.exportarPerfilData(${p.id})" style="font-size:11px; font-weight:600; padding:4px 8px; border-radius:6px; background:#f1f5f9; color:#334155;">🖨️ Exportar</button>
                            <button type="button" class="btn btn-sm" onclick="dashboard.cargarAuditoriaPerfil(${p.id})" style="font-size:11px; font-weight:600; padding:4px 8px; border-radius:6px; background:#faf5ff; color:#6b21a8;">📜 Bitácora</button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    }

    filtrarPerfilesGrid() {
        this.renderMantenimientoPerfilesGrid();
    }

    abrirModalNuevoPerfil() {
        this.abrirModalMantenimientoPerfil(null);
    }

    async abrirModalMantenimientoPerfil(perfilId = null) {
        const form = document.getElementById('formPerfilMantenimiento');
        if (!form) return;
        
        form.reset();
        document.getElementById('perfilMantenimientoId').value = '';
        
        const title = document.getElementById('titleModalPerfilMantenimiento');
        const containerHereda = document.getElementById('containerHeredaPermisos');
        
        if (perfilId) {
            title.textContent = 'Modificar Perfil Existente';
            if (containerHereda) containerHereda.style.display = 'none'; // No heredar si ya existe
            
            try {
                const token = localStorage.getItem('token_sesion') || '';
                const resp = await fetch(`${getApiPrefix()}backend/api/perfiles_mantenimiento.php?action=obtener&id=${perfilId}`, {
                    headers: { 'Authorization': 'Bearer ' + token }
                });
                const result = await resp.json();
                if (result.exito && result.datos) {
                    const p = result.datos;
                    document.getElementById('perfilMantenimientoId').value = p.id;
                    document.getElementById('perfilMantenimientoNombre').value = p.nombre_perfil;
                    document.getElementById('perfilMantenimientoDescripcion').value = p.descripcion || '';
                    document.getElementById('perfilMantenimientoNivel').value = p.nivel_jerarquico;
                    document.getElementById('perfilMantenimientoEstado').checked = p.estado === 'activo';
                    
                    abrirModal('modalPerfilMantenimiento');
                } else {
                    MQF.toast('Error al obtener perfil: ' + result.mensaje, 'error');
                }
            } catch (err) {
                console.error(err);
                MQF.toast('Error de red al obtener detalles del perfil', 'error');
            }
        } else {
            title.textContent = 'Registrar Nuevo Perfil';
            if (containerHereda) containerHereda.style.display = 'block';
            abrirModal('modalPerfilMantenimiento');
        }
    }

    async guardarPerfilMantenimiento(e) {
        e.preventDefault();
        
        const id = document.getElementById('perfilMantenimientoId').value;
        const nombre = document.getElementById('perfilMantenimientoNombre').value.trim();
        const descripcion = document.getElementById('perfilMantenimientoDescripcion').value.trim();
        const nivel = parseInt(document.getElementById('perfilMantenimientoNivel').value, 10);
        const estado = document.getElementById('perfilMantenimientoEstado').checked ? 'activo' : 'inactivo';
        const heredarDe = document.getElementById('perfilMantenimientoHereda')?.value || '';
        
        if (!nombre || isNaN(nivel)) {
            MQF.toast('Por favor complete los campos obligatorios.', 'warning');
            return;
        }
        
        const datos = {
            id: id || null,
            nombre_perfil: nombre,
            descripcion: descripcion,
            nivel_jerarquico: nivel,
            estado: estado,
            heredar_de: heredarDe
        };
        
        try {
            const token = localStorage.getItem('token_sesion') || '';
            const resp = await fetch(getApiPrefix() + 'backend/api/perfiles_mantenimiento.php?action=guardar', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + token
                },
                body: JSON.stringify(datos)
            });
            const result = await resp.json();
            if (result.exito) {
                MQF.toast(result.mensaje || 'Perfil guardado con éxito transaccional.', 'success');
                cerrarModal('modalPerfilMantenimiento');
                this.cargarPerfilesMantenimiento();
                this.cargarRejillaPerfiles();
            } else {
                MQF.toast('Error: ' + result.mensaje, 'error');
            }
        } catch (err) {
            console.error(err);
            MQF.toast('Error de conexión al guardar el perfil', 'error');
        }
    }

    async actualizarEstadoPerfilMantenimiento(perfilId, nuevoEstado) {
        const accionText = nuevoEstado === 'activo' ? 'activar' : 'desactivar';
        if (!confirm(`¿Está seguro que desea ${accionText} este perfil?`)) return;
        
        try {
            const token = localStorage.getItem('token_sesion') || '';
            const resp = await fetch(getApiPrefix() + 'backend/api/perfiles_mantenimiento.php?action=actualizar_estado', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + token
                },
                body: JSON.stringify({ id: perfilId, estado: nuevoEstado })
            });
            const result = await resp.json();
            if (result.exito) {
                MQF.toast(result.mensaje || 'Estado del perfil actualizado.', 'success');
                this.cargarPerfilesMantenimiento();
                this.cargarRejillaPerfiles();
            } else {
                MQF.toast('Error: ' + result.mensaje, 'error');
            }
        } catch (err) {
            console.error(err);
            MQF.toast('Error de red al actualizar estado del perfil', 'error');
        }
    }

    async cargarAuditoriaPerfil(perfilId) {
        const container = document.getElementById('perfilAuditoriaTimeline');
        if (!container) return;
        
        container.innerHTML = '<div style="text-align:center; padding:20px; opacity:0.6;">Cargando historial de auditoría...</div>';
        
        try {
            const token = localStorage.getItem('token_sesion') || '';
            const resp = await fetch(`${getApiPrefix()}backend/api/perfiles_mantenimiento.php?action=auditoria&perfil_id=${perfilId}`, {
                headers: { 'Authorization': 'Bearer ' + token }
            });
            const result = await resp.json();
            if (result.exito && Array.isArray(result.datos)) {
                const logs = result.datos;
                if (logs.length === 0) {
                    container.innerHTML = `
                        <div style="text-align:center; padding: 20px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; color:#94a3b8; font-size:13px;">
                            No existen registros de auditoría para este perfil en la bitácora inmutable.
                        </div>
                    `;
                } else {
                    container.innerHTML = logs.map(log => {
                        const date = new Date(log.fecha_evento).toLocaleString('es-ES');
                        let badgeColor = '#6b7280';
                        if (log.tipo_evento === 'create') badgeColor = '#10b981';
                        if (log.tipo_evento === 'update') badgeColor = '#3b82f6';
                        if (log.tipo_evento === 'delete' || log.tipo_evento === 'disable') badgeColor = '#ef4444';
                        
                        return `
                            <div style="display:flex; gap:16px; border-left: 2px solid #e2e8f0; padding-left: 16px; margin-left: 8px; position:relative; padding-bottom: 8px;">
                                <div style="width:12px; height:12px; border-radius:50%; background:${badgeColor}; position:absolute; left:-7px; top:4px; box-shadow: 0 0 0 4px #fff;"></div>
                                <div style="flex:1;">
                                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                                        <strong style="font-size:13px; color:#1e293b;">${log.descripcion_evento}</strong>
                                        <span style="font-size:11px; color:#64748b; font-weight:600; font-family:monospace;">${date}</span>
                                    </div>
                                    <p style="font-size:12px; color:#475569; margin: 4px 0 8px 0; line-height:1.4;">
                                        ${log.valor_anterior ? `<strong>Anterior:</strong> <code style="font-size:11px; background:#f1f5f9; padding:2px 4px; border-radius:4px;">${log.valor_anterior}</code><br/>` : ''}
                                        ${log.valor_nuevo ? `<strong>Nuevo:</strong> <code style="font-size:11px; background:#f0fdf4; padding:2px 4px; border-radius:4px; color:#166534;">${log.valor_nuevo}</code>` : ''}
                                    </p>
                                    <div style="display:flex; gap:12px; font-size:11px; color:#94a3b8; font-family:monospace;">
                                        <span>IP: ${log.direccion_ip || 'N/D'}</span>
                                        <span>User-Agent: ${log.navegador_user_agent || 'N/D'}</span>
                                    </div>
                                </div>
                            </div>
                        `;
                    }).join('');
                }
                abrirModal('modalPerfilAuditoria');
            } else {
                MQF.toast('Error al obtener bitácora: ' + result.mensaje, 'error');
            }
        } catch (err) {
            console.error(err);
            MQF.toast('Error de red al consultar la bitácora', 'error');
        }
    }

    async exportarPerfilData(perfilId) {
        try {
            const token = localStorage.getItem('token_sesion') || '';
            MQF.toast('Obteniendo información del perfil para exportar...', 'info');
            
            // 1. Obtener detalles del perfil
            const respP = await fetch(`${getApiPrefix()}backend/api/perfiles_mantenimiento.php?action=obtener&id=${perfilId}`, {
                headers: { 'Authorization': 'Bearer ' + token }
            });
            const dataP = await respP.json();
            
            // 2. Obtener permisos de la malla
            const respM = await fetch(`${getApiPrefix()}backend/api/perfiles_engine.php/obtener/${perfilId}`, {
                headers: { 'Authorization': 'Bearer ' + token }
            });
            const dataM = await respM.json();
            
            if (!dataP.exito || !dataM.exito) {
                MQF.toast('Error al recuperar la data completa del perfil.', 'error');
                return;
            }
            
            const perfil = dataP.datos;
            const permisos = dataM.datos || [];
            
            // Crear vista de impresión premium
            const printWindow = window.open('', '_blank');
            if (!printWindow) {
                MQF.toast('Por favor permita las ventanas emergentes para imprimir.', 'warning');
                return;
            }
            
            let permisosHtml = permisos.map(p => {
                const getCheck = (val) => val == 1 ? '✔️ SI' : '❌ NO';
                return `
                    <tr>
                        <td style="padding:8px; border:1px solid #ddd;"><strong>${p.nombre_modulo}</strong></td>
                        <td style="padding:8px; border:1px solid #ddd;">${p.nombre_funcion}</td>
                        <td style="padding:8px; border:1px solid #ddd; text-align:center;">${getCheck(p.puede_ejecutar)}</td>
                        <td style="padding:8px; border:1px solid #ddd; text-align:center;">${getCheck(p.ver_datos)}</td>
                        <td style="padding:8px; border:1px solid #ddd; text-align:center;">${getCheck(p.crear_datos)}</td>
                        <td style="padding:8px; border:1px solid #ddd; text-align:center;">${getCheck(p.editar_datos)}</td>
                        <td style="padding:8px; border:1px solid #ddd; text-align:center;">${getCheck(p.eliminar_datos)}</td>
                        <td style="padding:8px; border:1px solid #ddd; text-align:center;">${getCheck(p.ver_reportes)}</td>
                        <td style="padding:8px; border:1px solid #ddd; text-align:center;">${getCheck(p.exportar_datos)}</td>
                        <td style="padding:8px; border:1px solid #ddd; text-align:center;">${getCheck(p.imprimir_datos)}</td>
                    </tr>
                `;
            }).join('');
            
            printWindow.document.write(`
                <html>
                <head>
                    <title>Reporte de Perfil - ${perfil.nombre_perfil}</title>
                    <style>
                        body { font-family: 'Segoe UI', Arial, sans-serif; padding:40px; color:#333; line-height:1.6; }
                        h1 { color:#1e1b4b; border-bottom:2px solid #312e81; padding-bottom:10px; margin-bottom:20px; }
                        h2 { color:#312e81; margin-top:30px; }
                        .info-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:30px; background:#f8fafc; padding:20px; border-radius:8px; border:1px solid #e2e8f0; }
                        .info-item { font-size:14px; }
                        .info-label { font-weight:700; color:#475569; }
                        table { width:100%; border-collapse:collapse; margin-top:15px; font-size:12px; }
                        th { background:#1e1b4b; color:#fff; text-align:left; padding:10px; }
                        .footer { margin-top:50px; font-size:11px; text-align:center; color:#94a3b8; border-top:1px solid #e2e8f0; padding-top:15px; }
                        @media print {
                            body { padding: 0; }
                            .no-print { display:none; }
                        }
                    </style>
                </head>
                <body>
                    <div style="display:flex; justify-content:space-between; align-items:center;" class="no-print">
                        <button onclick="window.print()" style="padding:10px 20px; background:#1e1b4b; color:#fff; border:none; border-radius:6px; cursor:pointer; font-weight:600;">🖨️ Imprimir Reporte</button>
                        <button onclick="window.close()" style="padding:10px 20px; background:#f1f5f9; color:#334155; border:none; border-radius:6px; cursor:pointer; font-weight:600;">Cerrar Ventana</button>
                    </div>
                    <h1>🛡️ Ficha Técnica de Perfil de Accesos</h1>
                    <div class="info-grid">
                        <div class="info-item"><span class="info-label">Nombre del Perfil:</span> ${perfil.nombre_perfil}</div>
                        <div class="info-item"><span class="info-label">Nivel Jerárquico:</span> Nivel ${perfil.nivel_jerarquico}</div>
                        <div class="info-item" style="grid-column:1/-1;"><span class="info-label">Descripción:</span> ${perfil.descripcion || 'Sin descripción.'}</div>
                        <div class="info-item"><span class="info-label">Estado actual:</span> ${perfil.estado.toUpperCase()}</div>
                        <div class="info-item"><span class="info-label">Fecha de Generación:</span> ${new Date().toLocaleString('es-ES')}</div>
                    </div>
                    
                    <h2>🔑 Malla Curricular de Permisos Granulares</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Módulo</th>
                                <th>Función</th>
                                <th>Ejecutar</th>
                                <th>Ver Datos</th>
                                <th>Crear</th>
                                <th>Editar</th>
                                <th>Eliminar</th>
                                <th>Reportes</th>
                                <th>Exportar</th>
                                <th>Imprimir</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${permisosHtml || '<tr><td colspan="10" style="text-align:center; padding:15px;">No hay permisos explícitos en la malla. Acceso predeterminado por rol.</td></tr>'}
                        </tbody>
                    </table>
                    
                    <div class="footer">
                        Plataforma Integrada MÁS QUE FIANZAS &copy; ${new Date().getFullYear()} - Sistema de Gestión de Permisos bajo Norma NOFTRAB. Bitácora de Auditoría Activa.
                    </div>
                    <script>
                        window.onload = function() {
                            // Opcional: auto disparar impresión
                        }
                    <\/script>
                </body>
                </html>
            `);
            printWindow.document.close();
            
        } catch (err) {
            console.error(err);
            MQF.toast('Error al exportar/imprimir el perfil.', 'error');
        }
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
window.dashboard = null;
document.addEventListener('DOMContentLoaded', function() {
    window.dashboard = new Dashboard();
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
        const resp = await fetch(getApiPrefix() + 'backend/api/mi_perfil.php', {
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
        const resp = await fetch(getApiPrefix() + 'backend/api/mi_perfil.php', {
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
                
                // Actualizar localStorage
                const usr = JSON.parse(localStorage.getItem('usuario_actual') || '{}');
                usr.foto_perfil = data.datos.foto_url;
                localStorage.setItem('usuario_actual', JSON.stringify(usr));
                
                if (window.dashboard) {
                    window.dashboard.usuarioActual = usr;
                }
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
        const resp = await fetch(getApiPrefix() + 'backend/api/mi_perfil.php', {
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
            
            if (window.dashboard) {
                window.dashboard.usuarioActual = usr;
            }
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


// =====================================================
// DETALLE DE PÓLIZAS EMITIDAS (NOFTRAB Premium)
// =====================================================
async function abrirDetallePolizas() {
    const modal = document.getElementById('modalPolizasDetalle');
    if (modal) {
        modal.classList.add('active');
    }
    
    // Inicializar el selector de mes si está vacío
    const selector = document.getElementById('modalPolizasMesSelector');
    if (selector && selector.options.length === 0) {
        const nombresMeses = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
        const now = new Date();
        for (let i = 0; i < 12; i++) {
            const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
            const y = d.getFullYear();
            const m = String(d.getMonth() + 1).padStart(2, '0');
            const value = `${y}-${m}`;
            const label = `${nombresMeses[d.getMonth()]} ${y}`;
            const opt = document.createElement('option');
            opt.value = value;
            opt.textContent = label;
            selector.appendChild(opt);
        }
        // Seleccionar el mes actual por defecto
        selector.value = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
    }
    
    // Cargar los datos para el mes seleccionado
    const mesSeleccionado = selector ? selector.value : `${new Date().getFullYear()}-${String(new Date().getMonth() + 1).padStart(2, '0')}`;
    cargarDetallePolizasPorMes(mesSeleccionado);
}

async function cargarDetallePolizasPorMes(mes) {
    const mHoy = document.getElementById('modalPolizasHoy');
    const mSemana = document.getElementById('modalPolizasSemana');
    const mMes = document.getElementById('modalPolizasMes');
    const lblHoy = document.getElementById('lblModalPolizasHoy');
    const lblSemana = document.getElementById('lblModalPolizasSemana');
    const lblMes = document.getElementById('lblModalPolizasMes');
    const modalTopClientesList = document.getElementById('modalTopClientesList');
    
    if (mHoy) mHoy.textContent = '...';
    if (mSemana) mSemana.textContent = '...';
    if (mMes) mMes.textContent = '...';
    if (modalTopClientesList) {
        modalTopClientesList.innerHTML = '<div style="text-align:center; padding: 20px; color:#64748b; font-style:italic;"><i class="fa-solid fa-spinner fa-spin"></i> Cargando estadísticas...</div>';
    }
    
    try {
        const respuesta = await api.solicitud('/polizas_stats.php?mes=' + encodeURIComponent(mes));
        if (respuesta.exito && respuesta.data) {
            const stats = respuesta.data;
            if (mHoy) mHoy.textContent = stats.diario;
            if (mSemana) mSemana.textContent = stats.semanal;
            if (mMes) mMes.textContent = stats.mensual;
            
            // Actualizar etiquetas dependiendo de si es el mes actual
            if (stats.es_mes_actual) {
                if (lblHoy) lblHoy.textContent = "Hoy";
                if (lblSemana) lblSemana.textContent = "Esta Semana";
                if (lblMes) lblMes.textContent = "Este Mes";
            } else {
                if (lblHoy) lblHoy.textContent = "Promedio Diario";
                if (lblSemana) lblSemana.textContent = "Promedio Semanal";
                if (lblMes) lblMes.textContent = "Total del Mes";
            }
            
            if (modalTopClientesList) {
                if (!stats.top_clientes || stats.top_clientes.length === 0) {
                    modalTopClientesList.innerHTML = '<div style="text-align:center; padding: 20px; color:#64748b; font-style:italic;">No hay pólizas emitidas en este mes</div>';
                } else {
                    const maxPol = stats.top_clientes.reduce((max, c) => Math.max(max, c.cantidad_polizas), 1);
                    
                    modalTopClientesList.innerHTML = stats.top_clientes.map((c, index) => {
                        const pct = Math.round((c.cantidad_polizas / maxPol) * 100);
                        const cleanCedula = (c.cliente_cedula || '').replace(/[^0-9]/g, '');
                        const isCompany = cleanCedula.length === 9 || 
                                          /\b(s\.?r\.?l\.?|s\.?a\.?|s\.?a\.?s\.?|inc|group|corp|ltda|inversiones|industrias|asociacion|empresa|cooperativa|s\.r\.l|s\.a)\b/i.test(c.cliente_nombre);
                        const iconClass = isCompany ? 'fa-solid fa-building' : 'fa-solid fa-robot';
                        const iconTypeClass = isCompany ? 'client-company' : 'client-natural';
                        const plural = c.cantidad_polizas === 1 ? 'póliza' : 'pólizas';
                        
                        return `
                            <div class="top-cliente-card" style="margin-bottom: 8px;">
                                <div class="top-cliente-icon-wrapper ${iconTypeClass}">
                                    <i class="${iconClass}"></i>
                                </div>
                                <div class="top-cliente-details">
                                    <span class="top-cliente-name" title="${c.cliente_nombre}">${c.cliente_nombre}</span>
                                    <div class="top-cliente-progress-container">
                                        <div class="top-cliente-progress-bar" style="width: ${pct}%"></div>
                                    </div>
                                </div>
                                <div class="top-cliente-badge-pill">
                                    ${c.cantidad_polizas} ${plural}
                                </div>
                            </div>
                        `;
                    }).join('');
                }
            }
        } else {
            if (modalTopClientesList) {
                modalTopClientesList.innerHTML = '<div style="text-align:center; padding: 20px; color:#64748b; font-style:italic;">No hay pólizas emitidas</div>';
            }
        }
    } catch (error) {
        console.error('Error cargando detalles en cargarDetallePolizasPorMes:', error);
        if (modalTopClientesList) {
            modalTopClientesList.innerHTML = '<div style="text-align:center; padding: 20px; color:#ef4444; font-style:italic;">Error al cargar las pólizas</div>';
        }
    }
}

window.cargarDetallePolizasPorMes = cargarDetallePolizasPorMes;
window.abrirDetallePolizas = abrirDetallePolizas;

// =====================================================
// AJUSTES Y AUDITORÍA EXPEDIENTES (Norma NOFTRAB v4.0)
// =====================================================
window._ajusteCallback = null;

window.solicitarAjusteAuditoria = function(tipo, registroId, campo, valorNuevo, callback) {
    const elTipo = document.getElementById('ajusteTipo');
    const elRegId = document.getElementById('ajusteRegistroId');
    const elCampo = document.getElementById('ajusteCampo');
    const elValNuevo = document.getElementById('ajusteValorNuevo');
    const elJust = document.getElementById('ajusteJustificacion');
    
    if (elTipo) elTipo.value = tipo;
    if (elRegId) elRegId.value = registroId;
    if (elCampo) elCampo.value = campo || '';
    if (elValNuevo) elValNuevo.value = valorNuevo;
    if (elJust) elJust.value = '';
    
    const errEl = document.getElementById('ajusteError');
    if (errEl) errEl.textContent = '';
    
    window._ajusteCallback = callback;
    
    const modal = document.getElementById('modalAjustesAuditoria');
    if (modal) {
        modal.classList.add('active');
    }
};

window.procesarAjusteAuditoria = async function() {
    const tipo = document.getElementById('ajusteTipo')?.value;
    const registroId = document.getElementById('ajusteRegistroId')?.value;
    const campo = document.getElementById('ajusteCampo')?.value;
    const valorNuevo = document.getElementById('ajusteValorNuevo')?.value;
    const justificacion = document.getElementById('ajusteJustificacion')?.value?.trim();
    const errEl = document.getElementById('ajusteError');
    const btn = document.getElementById('btnConfirmarAjuste');
    
    if (!justificacion || justificacion.length < 10) {
        if (errEl) errEl.textContent = '⚠️ La justificación debe tener al menos 10 caracteres.';
        return;
    }
    
    if (errEl) errEl.textContent = '';
    btn.disabled = true;
    const originalText = btn.textContent;
    btn.textContent = 'Procesando...';
    
    try {
        const payload = {
            tipo_ajuste: tipo,
            registro_id: parseInt(registroId, 10),
            campo: campo || undefined,
            valor_nuevo: valorNuevo,
            justificacion: justificacion
        };
        
        const respuesta = await api.solicitud('/ajustes.php', 'POST', payload);
        if (respuesta.exito) {
            cerrarModal('modalAjustesAuditoria');
            if (typeof window._ajusteCallback === 'function') {
                window._ajusteCallback(respuesta);
            }
            alert('✅ Ajuste aplicado y registrado en el historial inmutable con éxito.');
        } else {
            if (errEl) errEl.textContent = '❌ ' + (respuesta.mensaje || 'Error al procesar el ajuste.');
        }
    } catch (e) {
        console.error('Error al procesar ajuste:', e);
        if (errEl) errEl.textContent = '❌ Error de conexión con el servidor.';
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.textContent = originalText;
        }
    }
};

// --- SOPORTE GLOBAL DE CIERRE DE MODALES (TECLA ESCAPE Y CLIC FUERA DEL CONTENIDO) ---
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const activeModals = document.querySelectorAll('.modal.active, .global-modal.active');
        activeModals.forEach(function(modal) {
            cerrarModal(modal.id);
        });
    }
});

document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal') || e.target.classList.contains('global-modal')) {
        cerrarModal(e.target.id);
    }
});


