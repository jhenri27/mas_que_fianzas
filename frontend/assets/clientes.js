/**
 * Lógica del Módulo de Clientes interactuando con api-client.js
 */

document.addEventListener('DOMContentLoaded', () => {
    cargarClientes();

    const form = document.getElementById('clienteForm');
    if (form) {
        form.addEventListener('submit', guardarCliente);
    }
    
    const buscador = document.getElementById('buscadorClientes');
    if (buscador) {
        buscador.addEventListener('input', (e) => {
            renderizarTablaClientes(e.target.value);
        });
    }
});

async function cargarClientes() {
    const tbody = document.getElementById('clientesList');
    if (!tbody) return;

    try {
        const resultado = await api.listarClientes();
        if (resultado.exito) {
            window.clientesData = resultado.datos; // Guardar para edición y búsqueda
            renderizarTablaClientes();
        } else {
            tbody.innerHTML = `<tr><td colspan="7" class="text-center" style="padding:20px; color:red;">Error: ${resultado.mensaje}</td></tr>`;
        }
    } catch (error) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center" style="padding:20px; color:red;">Error de conexión con el base de datos local</td></tr>`;
    }
}

function renderizarTablaClientes(filtro = '') {
    const tbody = document.getElementById('clientesList');
    if (!tbody || !window.clientesData) return;
    
    const filterText = filtro.toLowerCase();
    const filtrados = window.clientesData.filter(c => {
        return (c.id && c.id.toString().includes(filterText)) ||
               (c.nombre_razon_social && c.nombre_razon_social.toLowerCase().includes(filterText)) ||
               (c.rnc && c.rnc.toLowerCase().includes(filterText)) ||
               (c.telefono && c.telefono.toLowerCase().includes(filterText)) ||
               (c.estatus && c.estatus.toLowerCase().includes(filterText));
    });

    if (filtrados.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center" style="padding:20px;">No se encontraron clientes</td></tr>';
        return;
    }

    tbody.innerHTML = filtrados.map(cliente => `
        <tr>
            <td>${cliente.id}</td>
            <td><strong>${cliente.nombre_razon_social}</strong></td>
            <td>${cliente.rnc}</td>
            <td>${cliente.tipo_persona}</td>
            <td>${cliente.telefono || '-'}</td>
            <td>
                <span class="status-badge status-${cliente.estatus.toLowerCase() === 'activo' ? 'activo' : 'inactivo'}">
                    ${cliente.estatus}
                </span>
            </td>
            <td>
                <button class="btn btn-sm btn-secondary" style="padding:5px 10px; border-radius:4px; font-size:12px;" onclick="window.editarClienteUI(${cliente.id})" title="Editar"><i class="fa-solid fa-pen"></i></button>
                <button class="btn btn-sm btn-secondary" style="padding:5px 10px; border-radius:4px; font-size:12px; margin-left:5px;" onclick="window.imprimirCliente(${cliente.id})" title="Imprimir"><i class="fa-solid fa-print"></i></button>
            </td>
        </tr>
    `).join('');
}

function abrirModalCliente() {
    document.getElementById('clienteForm').reset();
    document.getElementById('cliente_id').value = '';
    document.getElementById('clienteModalTitle').textContent = 'Nuevo Cliente';
    document.getElementById('clienteModal').style.display = 'flex';
}

function editarClienteUI(id) {
    try {
        if (!window.clientesData) {
            alert('Aún no se han cargado los datos de los clientes.');
            return;
        }
        const cliente = window.clientesData.find(c => c.id == id);
        if (!cliente) {
            alert('No se encontró el cliente con ID ' + id);
            return;
        }
        
        document.getElementById('clienteForm').reset();
        
        // Llenar campos
        document.getElementById('cliente_id').value = cliente.id;
        
        const selectTipo = document.getElementById('tipo_persona');
        if(selectTipo && cliente.tipo_persona) {
            selectTipo.value = cliente.tipo_persona;
        }
        
        document.getElementById('nombre_razon_social').value = cliente.nombre_razon_social || '';
        document.getElementById('rfc').value = cliente.rnc || '';
        document.getElementById('telefono').value = cliente.telefono || '';
        
        // El correo no viene en /listar, se deja en blanco pero el input no falla
        const elCorreo = document.getElementById('correo');
        if (elCorreo) elCorreo.value = cliente.correo || cliente.email || '';
        
        const selectComisionante = document.getElementById('comisionante');
        if (selectComisionante) selectComisionante.value = cliente.comisionante || '';
        const inputCodigo = document.getElementById('codigo_comisionante');
        if (inputCodigo) inputCodigo.value = cliente.codigo_comisionante || '';
        const inputNombre = document.getElementById('nombre_comisionante');
        if (inputNombre) inputNombre.value = cliente.nombre_comisionante || '';
        const lookupDiv = document.getElementById('comisionante_lookup');
        if (lookupDiv && cliente.nombre_comisionante) {
            lookupDiv.innerHTML = '<span style="color:#166534;background:#dcfce7;padding:2px 8px;border-radius:4px;"><i class="fa-solid fa-circle-check" style="margin-right:4px;"></i>' + cliente.nombre_comisionante + '</span>';
        }
        
        document.getElementById('clienteModalTitle').textContent = 'Editar Cliente';
        document.getElementById('clienteModal').style.display = 'flex';
    } catch(e) {
        alert('Ocurrió un error abriendo la edición: ' + e.message);
        console.error(e);
    }
}

function cerrarModalCliente() {
    document.getElementById('clienteModal').style.display = 'none';
}

async function guardarCliente(e) {
    e.preventDefault();
    
    const datos = {
        tipo_persona: document.getElementById('tipo_persona').value,
        nombre_razon_social: document.getElementById('nombre_razon_social').value,
        rnc: document.getElementById('rfc').value, // El input HTML tiene id='rfc' y name='rnc'
        telefono: document.getElementById('telefono').value,
        correo: document.getElementById('correo').value,
        direccion: document.getElementById('direccion').value,
        estatus: document.getElementById('estatus').value,
        comisionante: document.getElementById('comisionante').value,
        codigo_comisionante: document.getElementById('codigo_comisionante') ? document.getElementById('codigo_comisionante').value : '',
        nombre_comisionante: document.getElementById('nombre_comisionante') ? document.getElementById('nombre_comisionante').value : ''
    };

    const btn = e.target.querySelector('button[type="submit"]');
    const txtOriginal = btn.innerHTML;
    btn.innerHTML = 'Guardando...';
    btn.disabled = true;

    try {
        const id = document.getElementById('cliente_id').value;
        let resultado;
        
        if (id) {
            resultado = await api.editarCliente(id, datos);
        } else {
            resultado = await api.crearCliente(datos);
        }

        if (resultado.exito) {
            alert('Cliente guardado exitosamente en Base de Datos');
            cerrarModalCliente();
            cargarClientes(); // Recargar tabla
        } else {
            alert('Error: ' + resultado.mensaje);
        }
    } catch (error) {
        alert('Error conectando con el servidor MySQL.');
    } finally {
        btn.innerHTML = txtOriginal;
        btn.disabled = false;
    }
}

// Exponer globalmente para onclick inline
window.abrirModalCliente = abrirModalCliente;
window.cerrarModalCliente = cerrarModalCliente;
window.editarClienteUI = editarClienteUI;

// ======================================================
// IMPRIMIR FICHA DE CLIENTE EN PDF CORPORATIVO
// ======================================================
window.imprimirCliente = function(id) {
    if (!window.clientesData) { alert('Los datos de clientes aún no están cargados.'); return; }
    const c = window.clientesData.find(x => x.id == id);
    if (!c) { alert('Cliente no encontrado (ID: ' + id + ')'); return; }

    if (typeof window.jspdf === 'undefined') { alert('Librería jsPDF no cargada'); return; }
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();

    const primaryColor = [25, 99, 163];
    const lightColor   = [220, 235, 248];
    const textColor    = [50, 50, 50];
    const fmt = (v) => v ? v.toString() : 'N/A';

    // --- Logo ---
    if (window.LOGO_MQF_B64) {
        doc.addImage(window.LOGO_MQF_B64, 'PNG', 14, 10, 48, 21);
    } else {
        doc.setFontSize(18); doc.setTextColor(...primaryColor); doc.setFont(undefined, 'bold');
        doc.text('MAS QUE FIANZAS', 14, 25);
    }

    // Header derecho
    doc.setFontSize(9); doc.setTextColor(...textColor); doc.setFont(undefined, 'normal');
    doc.text('Generado:', 148, 16); doc.text(new Date().toLocaleString('es-DO'), 196, 16, {align:'right'});
    doc.text('Módulo:', 148, 21);   doc.text('Directorio de Clientes', 196, 21, {align:'right'});

    // Barra azul de título
    doc.setFillColor(...primaryColor);
    doc.rect(14, 36, 182, 10, 'F');
    doc.setFontSize(13); doc.setTextColor(255, 255, 255); doc.setFont(undefined, 'bold');
    doc.text('FICHA DE CLIENTE', 105, 43, {align: 'center'});

    // N° de Cliente
    doc.setFillColor(...lightColor);
    doc.rect(14, 48, 182, 8, 'F');
    doc.setFontSize(10); doc.setTextColor(...primaryColor); doc.setFont(undefined, 'bold');
    doc.text(`Código de Cliente: ${fmt(c.id)}`, 16, 54);
    doc.setFont(undefined, 'normal'); doc.setTextColor(...textColor);

    // Tabla de datos
    let y = 65;
    const campo = (label, value, isHighlight = false) => {
        if (isHighlight) {
            doc.setFillColor(...lightColor);
            doc.rect(14, y - 5, 182, 9, 'F');
        }
        doc.setFont(undefined, 'bold'); doc.setFontSize(10); doc.setTextColor(...primaryColor);
        doc.text(label + ':', 16, y);
        doc.setFont(undefined, 'normal'); doc.setTextColor(...textColor);
        doc.text(fmt(value), 75, y);
        doc.setDrawColor(230); doc.line(14, y + 3, 196, y + 3);
        y += 12;
    };

    campo('Tipo de Persona',    c.tipo_persona, true);
    campo('Nombre / Razón Social', c.nombre_razon_social);
    campo('RNC / Cédula',       c.rnc, true);
    campo('Teléfono',           c.telefono);
    campo('Correo Electrónico', c.correo || c.email, true);
    campo('Dirección Física',   c.direccion);
    campo('Estatus',            c.estatus, true);
    if (c.created_at) campo('Fecha de Registro', new Date(c.created_at).toLocaleDateString('es-DO'));

    // Línea de firma
    y += 20;
    doc.setLineWidth(0.5); doc.line(90, y, 140, y);
    doc.setFont(undefined, 'bold'); doc.setFontSize(9); doc.setTextColor(...textColor);
    doc.text('Firma autorizada', 115, y + 6, {align: 'center'});

    // Footer corporativo
    doc.setFont(undefined, 'normal'); doc.setFontSize(8); doc.setTextColor(150);
    doc.text('Ave. 27 de febrero #234, Suite-304, La esperilla, Santo Domingo. DN. Código postal: 10107, República Dominicana', 105, 278, {align:'center'});
    doc.text('Tel: +1 (829) 629-1952 | Email: info@masquefianzas.com', 105, 283, {align:'center'});

    doc.autoPrint();
    const blob = doc.output('blob');
    const url  = URL.createObjectURL(blob);
    window.open(url, '_blank');
};



// ======================================================
// LOOKUP CODIGO COMISIONANTE -> NOMBRE USUARIO
// ======================================================
var lookupTimeout;
document.addEventListener('DOMContentLoaded', function() {
    var codigoInput = document.getElementById('codigo_comisionante');
    if (codigoInput) {
        codigoInput.addEventListener('input', function() {
            clearTimeout(lookupTimeout);
            var codigo = this.value.trim();
            var lookupDiv = document.getElementById('comisionante_lookup');
            var nombreInput = document.getElementById('nombre_comisionante');
            if (!codigo) {
                lookupDiv.innerHTML = '';
                if (nombreInput) nombreInput.value = '';
                return;
            }
            lookupDiv.innerHTML = '<span style="color:#64748b;"><i class="fa-solid fa-spinner fa-spin"></i> Buscando...</span>';
            lookupTimeout = setTimeout(async function() {
                try {
                    var res = await api.solicitud('/usuarios.php/listar?buscar=' + encodeURIComponent(codigo) + '&por_pagina=50');
                    if (res.exito && res.datos && res.datos.usuarios) {
                        var match = res.datos.usuarios.find(function(u) {
                            return u.codigo_usuario && u.codigo_usuario.toUpperCase() === codigo.toUpperCase();
                        });
                        if (match) {
                            var fullName = match.nombre + ' ' + match.apellido;
                            if (nombreInput) nombreInput.value = fullName;
                            lookupDiv.innerHTML = '<span style="color:#166534;background:#dcfce7;padding:2px 8px;border-radius:4px;display:inline-flex;align-items:center;gap:5px;"><i class="fa-solid fa-circle-check"></i><strong>' + fullName + '</strong><span style="opacity:.7;font-size:11px;">@' + match.username + '</span></span>';
                        } else {
                            if (nombreInput) nombreInput.value = '';
                            lookupDiv.innerHTML = '<span style="color:#991b1b;background:#fee2e2;padding:2px 8px;border-radius:4px;"><i class="fa-solid fa-circle-xmark"></i> Codigo no encontrado</span>';
                        }
                    }
                } catch(e) {
                    lookupDiv.innerHTML = '<span style="color:#b45309;">Error de conexion</span>';
                }
            }, 600);
        });
    }
});