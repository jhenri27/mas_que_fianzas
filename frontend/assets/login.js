/**
 * Lógica de Formulario de Login
 */

document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const usernameInput = document.getElementById('username');
    const passwordInput = document.getElementById('password');
    const loginBtn = document.querySelector('.btn-login');
    const alertaContainer = document.getElementById('alertaContainer');

    // Verificar si ya hay sesión activa
    if (api.tieneSesion()) {
        window.location.href = '/PLATAFORMA_INTEGRADA/frontend/dashboard.html';
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

                // Redirigir al dashboard
                setTimeout(() => {
                    if (resultado.requiere_cambio_password) {
                        window.location.href = '/PLATAFORMA_INTEGRADA/frontend/cambiar-password.html';
                    } else {
                        window.location.href = '/PLATAFORMA_INTEGRADA/frontend/dashboard.html';
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
                                    const req = await fetch('http://localhost/PLATAFORMA_INTEGRADA/backend/api/auth.php/solicitar-desbloqueo', {
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
