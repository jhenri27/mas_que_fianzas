/**
 * MAS QUE FIANZAS - Manager de Detección de Ambiente (DEV vs PROD)
 * Garantiza la diferenciación visual inconfundible [DEV] en títulos, barra superior, sidebar y navegación.
 */
(function() {
    'use strict';

    function detectarAmbienteDev() {
        const path = window.location.pathname.toLowerCase();
        const host = window.location.hostname.toLowerCase();
        return path.includes('/dev') || path.includes('/dev_plataforma') || host.includes('dev.');
    }

    const ES_DEV = detectarAmbienteDev();

    // Exportar estado global
    window.MQF_IS_DEV = ES_DEV;

    function obtenerPrefijoBase() {
        const p = window.location.pathname;
        if (p.includes('/dev_plataforma/')) return '/dev_plataforma';
        if (p.includes('/PLATAFORMA_INTEGRADA/dev/')) return '/PLATAFORMA_INTEGRADA/dev';
        if (p.includes('/dev/')) return '/dev';
        if (p.includes('/PLATAFORMA_INTEGRADA/')) return '/PLATAFORMA_INTEGRADA';
        return '';
    }

    window.MQF_BASE_PREFIX = obtenerPrefijoBase();

    function aplicarMarcasDev() {
        if (!ES_DEV) return;

        // 1. Título de la Pestaña del Navegador
        if (document.title && !document.title.startsWith('[DEV]')) {
            document.title = '[DEV] ' + document.title;
        }

        // 2. Barra Superior Global DEV
        if (!document.getElementById('mqf-dev-global-bar')) {
            const devBar = document.createElement('div');
            devBar.id = 'mqf-dev-global-bar';
            devBar.style.cssText = 'background: linear-gradient(90deg, #b45309, #d97706, #b45309); color: #ffffff; font-size: 11px; font-weight: 800; text-align: center; padding: 4px 10px; letter-spacing: 0.8px; text-transform: uppercase; box-shadow: 0 2px 8px rgba(0,0,0,0.3); position: relative; z-index: 999999; display: flex; align-items: center; justify-content: center; gap: 8px; font-family: system-ui, -apple-system, sans-serif;';
            devBar.innerHTML = '🧪 <span style="background: rgba(0,0,0,0.25); padding: 2px 8px; border-radius: 4px;">[DEV] AMBIENTE DE DESARROLLO Y PRUEBAS</span> — MÁS QUE FIANZAS (Base de Datos e Infraestructura DEV)';
            
            if (document.body) {
                document.body.insertBefore(devBar, document.body.firstChild);
            } else {
                document.addEventListener('DOMContentLoaded', () => {
                    document.body.insertBefore(devBar, document.body.firstChild);
                });
            }
        }

        // 3. Insignia en Logo del Sidebar
        const sidebarLogoH3 = document.querySelector('.sidebar-header .logo h3') || document.querySelector('.logo h3');
        if (sidebarLogoH3 && !document.getElementById('mqf-dev-sidebar-tag')) {
            const tag = document.createElement('span');
            tag.id = 'mqf-dev-sidebar-tag';
            tag.style.cssText = 'background: #f59e0b; color: #111; font-size: 10px; font-weight: 900; padding: 2px 6px; border-radius: 4px; margin-left: 6px; vertical-align: middle; display: inline-block;';
            tag.textContent = 'DEV';
            sidebarLogoH3.appendChild(tag);
        }

        // 4. Insignia en Encabezado Principal
        const pageTitle = document.getElementById('pageTitle');
        if (pageTitle && !document.getElementById('mqf-dev-header-tag')) {
            const headerTag = document.createElement('span');
            headerTag.id = 'mqf-dev-header-tag';
            headerTag.style.cssText = 'background: linear-gradient(135deg, #d97706, #b45309); color: white; font-weight: 800; font-size: 11px; padding: 3px 10px; border-radius: 12px; margin-left: 10px; vertical-align: middle; display: inline-flex; align-items: center; gap: 4px; box-shadow: 0 0 8px rgba(217,119,6,0.5);';
            headerTag.innerHTML = '🧪 [DEV]';
            pageTitle.appendChild(headerTag);
        }
    }

    // Ejecución Inmediata
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', aplicarMarcasDev);
    } else {
        aplicarMarcasDev();
    }

    // Re-verificar dinámicamente si cambian los títulos o la UI
    window.addEventListener('load', aplicarMarcasDev);
})();
