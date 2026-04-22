/**
 * cambiar-password.js
 * Lógica para el flujo de cambio de contraseña obligatorio
 * Se activa cuando un usuario inicia sesión con requiere_cambio_password = true
 */

document.addEventListener('DOMContentLoaded', function () {

    // ─── Verificar que haya sesión válida ──────────────────────────────────────
    if (!api.tieneSesion()) {
        window.location.href = 'index.html';
        return;
    }

    const form        = document.getElementById('cambiarPasswordForm');
    const inputNueva  = document.getElementById('password_nueva');
    const inputConfir = document.getElementById('password_confirmacion');
    const btnCambiar  = document.getElementById('btnCambiar');
    const alertaCont  = document.getElementById('alertaContainer');

    // ─── Indicador de fortaleza de contraseña ─────────────────────────────────
    inputNueva.addEventListener('input', function () {
        const val = this.value;
        const bar = document.getElementById('strengthBar');
        const lbl = document.getElementById('strengthLabel');

        const reqLen    = document.getElementById('req-length');
        const reqUpper  = document.getElementById('req-upper');
        const reqNumber = document.getElementById('req-number');

        const hasLen    = val.length >= 8;
        const hasUpper  = /[A-Z]/.test(val);
        const hasNumber = /\d/.test(val);

        reqLen.classList.toggle('ok', hasLen);
        reqUpper.classList.toggle('ok', hasUpper);
        reqNumber.classList.toggle('ok', hasNumber);

        const score = [hasLen, hasUpper, hasNumber].filter(Boolean).length;
        if (val.length === 0) {
            bar.className = 'password-strength';
            lbl.textContent = '';
        } else if (score === 1) {
            bar.className = 'password-strength weak';
            lbl.textContent = 'Contraseña débil';
        } else if (score === 2) {
            bar.className = 'password-strength medium';
            lbl.textContent = 'Contraseña regular';
        } else {
            bar.className = 'password-strength strong';
            lbl.textContent = 'Contraseña segura ✓';
        }
    });

    // ─── Envío del formulario ──────────────────────────────────────────────────
    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        const nueva        = inputNueva.value.trim();
        const confirmacion = inputConfir.value.trim();

        if (!nueva || !confirmacion) {
            mostrarAlerta('Completa ambos campos', 'warning');
            return;
        }
        if (nueva !== confirmacion) {
            mostrarAlerta('Las contraseñas no coinciden', 'warning');
            return;
        }
        if (nueva.length < 8) {
            mostrarAlerta('Mínimo 8 caracteres requeridos', 'warning');
            return;
        }

        // Deshabilitar botón mientras procesa
        btnCambiar.disabled = true;
        document.querySelector('.btn-text').style.display   = 'none';
        document.querySelector('.btn-loader').style.display = 'inline';

        try {
            const res = await api.cambiarPasswordForzado(nueva, confirmacion);

            if (res.exito) {
                mostrarAlerta('✓ Contraseña actualizada. Redirigiendo al dashboard...', 'success');
                setTimeout(() => {
                    window.location.href = 'dashboard.html';
                }, 2000);
            } else {
                mostrarAlerta(res.mensaje || 'Error al cambiar la contraseña', 'danger');
                btnCambiar.disabled = false;
                document.querySelector('.btn-text').style.display   = 'inline';
                document.querySelector('.btn-loader').style.display = 'none';
            }
        } catch (err) {
            console.error(err);
            mostrarAlerta('Error de conexión con el servidor', 'danger');
            btnCambiar.disabled = false;
            document.querySelector('.btn-text').style.display   = 'inline';
            document.querySelector('.btn-loader').style.display = 'none';
        }
    });

    // ─── Helper: mostrar alerta ────────────────────────────────────────────────
    function mostrarAlerta(mensaje, tipo = 'info') {
        alertaCont.innerHTML = '';
        const div = document.createElement('div');
        div.className = `alerta alerta-${tipo}`;
        div.innerHTML = `
            <span style="font-size:18px;">${tipo === 'success' ? '✓' : tipo === 'danger' ? '✕' : '⚠'}</span>
            <span>${mensaje}</span>
        `;
        alertaCont.appendChild(div);
        if (tipo !== 'success') {
            setTimeout(() => div.remove(), 6000);
        }
    }
});
