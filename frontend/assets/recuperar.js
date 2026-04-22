document.addEventListener('DOMContentLoaded', function() {
    const requestForm = document.getElementById('requestForm');
    const resetForm = document.getElementById('resetForm');
    const alertaContainer = document.getElementById('alertaContainer');
    
    // Check if URL has token
    const urlParams = new URLSearchParams(window.location.search);
    const token = urlParams.get('token');
    
    if (token) {
        // Mode: Restablecer
        requestForm.style.display = 'none';
        resetForm.style.display = 'block';
        document.getElementById('subtituloUi').innerText = 'Crea una nueva contraseña segura';
    }
    
    function mostrarAlerta(mensaje, tipo = 'info') {
        alertaContainer.innerHTML = '';
        const alerta = document.createElement('div');
        alerta.className = `alerta alerta-${tipo}`;
        alerta.innerHTML = `<span>${mensaje}</span>`;
        alertaContainer.appendChild(alerta);
        if (tipo !== 'success') {
            setTimeout(() => alerta.remove(), 6000);
        }
    }

    if (requestForm) {
        requestForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const identificador = document.getElementById('identificador').value.trim();
            if (!identificador) returnmostrarAlerta('Ingresa tu usuario o correo', 'warning');
            
            const btn = document.getElementById('btnRequest');
            btn.disabled = true;
            btn.innerHTML = 'Enviando...';
            
            try {
                const req = await fetch('http://localhost/PLATAFORMA_INTEGRADA/backend/api/auth.php/recuperar-password', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ identificador })
                });
                const res = await req.json();
                if(res.exito) {
                    mostrarAlerta(res.mensaje, 'success');
                } else {
                    mostrarAlerta(res.mensaje, 'danger');
                }
            } catch (err) {
                mostrarAlerta('Error de red de comunicación.', 'danger');
            }
            btn.innerHTML = 'Enviar Enlace de Recuperación';
            btn.disabled = false;
        });
    }

    if (resetForm) {
        resetForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const p1 = document.getElementById('new_password').value;
            const p2 = document.getElementById('confirm_password').value;
            
            if (p1 !== p2) {
                mostrarAlerta('Las contraseñas no coinciden', 'warning');
                return;
            }
            if (p1.length < 8) {
                mostrarAlerta('Mínimo 8 caracteres', 'warning');
                return;
            }

            const btn = document.getElementById('btnReset');
            btn.disabled = true;
            btn.innerHTML = 'Actualizando...';
            
            try {
                const req = await fetch('http://localhost/PLATAFORMA_INTEGRADA/backend/api/auth.php/reset-password', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ token: token, password_nueva: p1, password_confirmacion: p2 })
                });
                const res = await req.json();
                if(res.exito) {
                    mostrarAlerta(res.mensaje, 'success');
                    setTimeout(() => window.location.href = 'index.html', 3000);
                } else {
                    mostrarAlerta(res.mensaje, 'danger');
                    btn.innerHTML = 'Guardar y Restaurar';
                    btn.disabled = false;
                }
            } catch (err) {
                mostrarAlerta('Error de conexión', 'danger');
                btn.innerHTML = 'Guardar y Restaurar';
                btn.disabled = false;
            }
        });
    }
});
