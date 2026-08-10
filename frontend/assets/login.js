/**
 * Lógica de Formulario de Login
 */

document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const usernameInput = document.getElementById('username');
    const passwordInput = document.getElementById('password');
    const loginBtn = document.querySelector('.btn-login');
    const alertaContainer = document.getElementById('alertaContainer');

    function obtenerRutaBaseFrontend() {
        const p = window.location.pathname;
        if (p.includes('/dev_plataforma/')) return '/dev_plataforma/frontend';
        if (p.includes('/PLATAFORMA_INTEGRADA/dev/')) return '/PLATAFORMA_INTEGRADA/dev/frontend';
        if (p.includes('/dev/')) return '/dev/frontend';
        if (p.includes('/PLATAFORMA_INTEGRADA/')) return '/PLATAFORMA_INTEGRADA/frontend';
        return '/frontend';
    }

    const baseFrontendPath = obtenerRutaBaseFrontend();
    const esAmbienteDev = window.location.pathname.includes('/dev');

    // Inyectar distintivo visual si estamos en ambiente DEV
    if (esAmbienteDev) {
        const loginHeader = document.querySelector('.login-header');
        if (loginHeader && !document.getElementById('devEnvBanner')) {
            const devBanner = document.createElement('div');
            devBanner.id = 'devEnvBanner';
            devBanner.style.cssText = 'background: linear-gradient(135deg, #d97706, #b45309); color: white; padding: 8px 16px; border-radius: 8px; text-align: center; font-weight: bold; font-size: 13px; margin: 12px 0 15px; box-shadow: 0 4px 12px rgba(217, 119, 6, 0.4); border: 1px solid rgba(255,255,255,0.3); display: flex; align-items: center; justify-content: center; gap: 8px;';
            devBanner.innerHTML = '🧪 AMBIENTE DE DESARROLLO (DEV)';
            loginHeader.appendChild(devBanner);
        }
    }

    // Verificar si ya hay sesión activa
    if (api.tieneSesion()) {
        window.location.href = `${baseFrontendPath}/dashboard.html`;
    }

    /**
     * Manejar envío del formulario
     */
    loginForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        const username = usernameInput.value.trim();
        const password = passwordInput.value;

        // Validar campos
        if (!username || !password) {
            mostrarAlerta('Por favor completa todos los campos', 'warning');
            return;
        }

        // Deshabilitar botón y mostrar cargando
        loginBtn.disabled = true;
        document.querySelector('.btn-text').style.display = 'none';
        document.querySelector('.btn-loader').style.display = 'flex';

        try {
            const resultado = await api.login(username, password);

            if (resultado.exito) {
                mostrarAlerta('¡Bienvenido! Redirigiendo...', 'success');

                // Redirigir al dashboard preservando el ambiente DEV o PROD
                setTimeout(() => {
                    if (resultado.requiere_cambio_password) {
                        window.location.href = `${baseFrontendPath}/cambiar-password.html`;
                    } else {
                        window.location.href = `${baseFrontendPath}/dashboard.html`;
                    }
                }, 1500);
            } else {
                let mensajeError = resultado.mensaje || 'Error en el login';
                let isBlockError = mensajeError.toLowerCase().includes('bloqueada') || mensajeError.toLowerCase().includes('bloqueado');
                
                if (isBlockError) {
                    mensajeError += `<div style="margin-top: 15px;"><button type="button" id="btnDesbloqueo" class="btn" style="background-color: #f39c12; color: white; padding: 8px 15px; border-radius: 4px; font-size: 14px; width: 100%; border: none; cursor: pointer;">📬 Solicitar Desbloqueo a Soporte</button></div>`;
                }

                mostrarAlerta(mensajeError, 'danger');

                if (isBlockError) {
                    setTimeout(() => {
                        const btnD = document.getElementById('btnDesbloqueo');
                        if (btnD) {
                            btnD.addEventListener('click', async function() {
                                btnD.innerHTML = 'Enviando alerta... <i class="fa-solid fa-spinner fa-spin"></i>';
                                btnD.disabled = true;
                                btnD.style.opacity = '0.7';
                                try {
                                    const req = await fetch(`${basePrefix}/backend/api/auth.php/solicitar-desbloqueo`, {
                                        method: 'POST',
                                        headers: { 'Content-Type': 'application/json' },
                                        body: JSON.stringify({ username: usernameInput.value.trim() })
                                    });
                                    const res = await req.json();
                                    if(res.exito) {
                                        mostrarAlerta(res.mensaje, 'success');
                                    } else {
                                        mostrarAlerta('Error: ' + res.mensaje, 'danger');
                                    }
                                } catch(err) {
                                    mostrarAlerta('Error de red al solicitar desbloqueo.', 'danger');
                                }
                            });
                        }
                    }, 100);
                }

                loginBtn.disabled = false;
                document.querySelector('.btn-text').style.display = 'inline';
                document.querySelector('.btn-loader').style.display = 'none';
            }
        } catch (error) {
            console.error('Error:', error);
            mostrarAlerta('Error de conexión con el servidor', 'danger');
            loginBtn.disabled = false;
            document.querySelector('.btn-text').style.display = 'inline';
            document.querySelector('.btn-loader').style.display = 'none';
        }
    });

    /**
     * Permitir envío con Enter
     */
    passwordInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            loginForm.dispatchEvent(new Event('submit'));
        }
    });

    /**
     * Mostrar alerta
     */
    function mostrarAlerta(mensaje, tipo = 'info') {
        // Limpiar alertas previas
        alertaContainer.innerHTML = '';

        const alerta = document.createElement('div');
        alerta.className = `alerta alerta-${tipo}`;
        alerta.innerHTML = `
            <span style="font-size: 18px;">
                ${tipo === 'success' ? '✓' : tipo === 'danger' ? '✕' : tipo === 'warning' ? '⚠' : 'ℹ'}
            </span>
            <span>${mensaje}</span>
        `;

        alertaContainer.appendChild(alerta);

        // Auto-remover después de 5 segundos si no es éxito o si no contiene el botón
        if (tipo !== 'success' && !mensaje.includes('btnDesbloqueo')) {
            setTimeout(() => {
                alerta.remove();
            }, 6000);
        }
    }
});
