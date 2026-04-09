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
                mostrarAlerta(resultado.mensaje || 'Error en el login', 'danger');
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

        // Auto-remover después de 5 segundos si no es éxito
        if (tipo !== 'success') {
            setTimeout(() => {
                alerta.remove();
            }, 5000);
        }
    }
});
