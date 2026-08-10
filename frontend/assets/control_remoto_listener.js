/**
 * PUENTE DE CONTROL REMOTO EN VIVO — MÁS QUE FIANZAS
 * Permite que la IA envíe comandos desde la consola y se ejecuten VISIBLEMENTE en tu navegador.
 */

(function() {
    let ultimoTimestamp = 0;
    const API_URL = '/PLATAFORMA_INTEGRADA/backend/api/control_remoto.php';

    // Banner flotante de IA Remota en pantalla
    function mostrarBannerIA(mensaje) {
        let banner = document.getElementById('aiRemoteBanner');
        if (!banner) {
            banner = document.createElement('div');
            banner.id = 'aiRemoteBanner';
            banner.style.cssText = `
                position: fixed;
                top: 15px;
                right: 15px;
                z-index: 999999;
                background: linear-gradient(135deg, #1e1b4b, #4338ca);
                color: #ffffff;
                padding: 12px 20px;
                border-radius: 12px;
                box-shadow: 0 10px 25px rgba(67, 56, 202, 0.5), 0 0 15px rgba(168, 85, 247, 0.4);
                border: 1px solid rgba(168, 85, 247, 0.5);
                font-family: 'Outfit', sans-serif;
                font-size: 14px;
                font-weight: 600;
                display: flex;
                align-items: center;
                gap: 10px;
                transition: all 0.3s ease;
                animation: pulseGlow 2s infinite;
            `;
            document.body.appendChild(banner);
        }
        banner.innerHTML = `
            <span style="font-size: 18px;">🤖</span>
            <span>${mensaje}</span>
        `;
    }

    async function escucharComandosRemotos() {
        try {
            const resp = await fetch(API_URL + '?t=' + Date.now());
            if (!resp.ok) return;
            const data = await resp.json();

            if (data.timestamp && data.timestamp > ultimoTimestamp) {
                if (ultimoTimestamp === 0) {
                    ultimoTimestamp = data.timestamp;
                    return; // Inicialización
                }
                ultimoTimestamp = data.timestamp;
                ejecutarComandoRemoto(data);
            }
        } catch (e) {
            // Ignorar errores de red
        }
    }

    function ejecutarComandoRemoto(data) {
        const comando = data.comando;
        console.log('🤖 Comando Remoto de IA Recibido:', comando);

        switch(comando) {
            case 'AUTOLOGIN_LIVE':
                mostrarBannerIA('IA Ejecutando Inicio de Sesión Visible...');
                const userInp = document.getElementById('username');
                const passInp = document.getElementById('password');
                const loginForm = document.getElementById('loginForm');
                if (userInp && passInp) {
                    userInp.value = 'pdv.prueba';
                    passInp.value = 'Demo@1234';
                    setTimeout(() => {
                        if (loginForm) loginForm.requestSubmit ? loginForm.requestSubmit() : loginForm.submit();
                    }, 1200);
                }
                break;

            case 'NAVIGATE_POLIZAS':
                mostrarBannerIA('IA Navegando al Módulo de Pólizas en Vivo...');
                setTimeout(() => {
                    const p = window.location.pathname;
                    let prefix = '/PLATAFORMA_INTEGRADA';
                    if (p.includes('/dev_plataforma/')) prefix = '/dev_plataforma';
                    else if (p.includes('/PLATAFORMA_INTEGRADA/dev/')) prefix = '/PLATAFORMA_INTEGRADA/dev';
                    else if (p.includes('/dev/')) prefix = '/dev';
                    window.location.href = `${prefix}/frontend/modulos/polizas.html`;
                }, 1500);
                break;

            case 'NAVIGATE_FIANZAS':
                mostrarBannerIA('IA Navegando al Módulo de Fianzas en Vivo...');
                setTimeout(() => {
                    const p = window.location.pathname;
                    let prefix = '/PLATAFORMA_INTEGRADA';
                    if (p.includes('/dev_plataforma/')) prefix = '/dev_plataforma';
                    else if (p.includes('/PLATAFORMA_INTEGRADA/dev/')) prefix = '/PLATAFORMA_INTEGRADA/dev';
                    else if (p.includes('/dev/')) prefix = '/dev';
                    window.location.href = `${prefix}/frontend/modulos/fianzas.html`;
                }, 1500);
                break;

            case 'NAVIGATE_PRODUCTOS':
                mostrarBannerIA('IA Navegando al Catálogo de Productos en Vivo...');
                setTimeout(() => {
                    const p = window.location.pathname;
                    let prefix = '/PLATAFORMA_INTEGRADA';
                    if (p.includes('/dev_plataforma/')) prefix = '/dev_plataforma';
                    else if (p.includes('/PLATAFORMA_INTEGRADA/dev/')) prefix = '/PLATAFORMA_INTEGRADA/dev';
                    else if (p.includes('/dev/')) prefix = '/dev';
                    window.location.href = `${prefix}/frontend/modulos/productos.html`;
                }, 1500);
                break;

            case 'NAVIGATE_DASHBOARD':
                mostrarBannerIA('IA Regresando al Dashboard Principal...');
                setTimeout(() => {
                    const p = window.location.pathname;
                    let prefix = '/PLATAFORMA_INTEGRADA';
                    if (p.includes('/dev_plataforma/')) prefix = '/dev_plataforma';
                    else if (p.includes('/PLATAFORMA_INTEGRADA/dev/')) prefix = '/PLATAFORMA_INTEGRADA/dev';
                    else if (p.includes('/dev/')) prefix = '/dev';
                    window.location.href = `${prefix}/frontend/dashboard.html`;
                }, 1500);
                break;
        }
    }

    // Polling cada 1 segundo
    setInterval(escucharComandosRemotos, 1000);
})();
