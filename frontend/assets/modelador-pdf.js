let currentPlantillaId = null;
let currentScale = 1;
let pdfOriginalWidth = 595.28; // Ancho típico A4 en puntos (pt)
let campos = [];
let activeCampo = null;
let isPreviewMode = false;

const API_URL = '/PLATAFORMA_INTEGRADA/backend/api/pdf_plantillas.php';

// Inicialización
document.addEventListener('DOMContentLoaded', () => {
    cargarListaPlantillas();
});

// Cargar Dropdown
async function cargarListaPlantillas() {
    try {
        const res = await fetch(API_URL);
        const json = await res.json();
        if(json.exito && json.data) {
            const select = document.getElementById('listaPlantillas');
            select.innerHTML = '<option value="">Selecciona una plantilla...</option>';
            json.data.forEach(p => {
                const nombreAseg = p.aseguradora_nombre ? `[${p.aseguradora_nombre}] ` : '';
                const opt = document.createElement('option');
                opt.value = p.id;
                opt.textContent = `${nombreAseg}${p.nombre} (${p.tipo_archivo.toUpperCase()})`;
                select.appendChild(opt);
            });
        }
    } catch(e) {
        console.error("Error cargando plantillas", e);
    }
}

// Subir Plantilla
async function subirPlantilla() {
    const fileInput = document.getElementById('filePlantilla');
    const nameInput = document.getElementById('nombrePlantilla');
    const asegInput = document.getElementById('aseguradoraPlantilla');
    
    if(!fileInput.files[0]) return alert("Selecciona un archivo");
    if(!nameInput.value) return alert("Ponle un nombre a la plantilla");

    const formData = new FormData();
    formData.append('action', 'upload');
    formData.append('file', fileInput.files[0]);
    formData.append('nombre', nameInput.value);
    if(asegInput.value) {
        formData.append('aseguradora_nombre', asegInput.value);
    }

    try {
        const res = await fetch(API_URL, {
            method: 'POST',
            body: formData
        });
        const json = await res.json();
        if(json.exito) {
            alert(json.mensaje);
            fileInput.value = '';
            nameInput.value = '';
            asegInput.value = '';
            cargarListaPlantillas();
            document.getElementById('listaPlantillas').value = json.id;
            cargarPlantilla();
        } else {
            alert(json.mensaje);
        }
    } catch(e) {
        alert("Error de red subiendo plantilla");
    }
}

// Cargar Plantilla al Lienzo
async function cargarPlantilla() {
    const id = document.getElementById('listaPlantillas').value;
    if(!id) return;
    
    // Limpiar workspace
    limpiarCampos();
    currentPlantillaId = id;

    try {
        const res = await fetch(`${API_URL}?id=${id}`);
        const json = await res.json();
        if(json.exito && json.data) {
            const fileUrl = `/PLATAFORMA_INTEGRADA/backend/uploads/plantillas_pdf/${json.data.archivo_base}`;
            
            if(json.data.tipo_archivo === 'pdf') {
                renderizarPDF(fileUrl);
            } else {
                renderizarImagen(fileUrl);
            }

            // Cargar campos guardados
            if(json.data.campos) {
                // Esperar a que el canvas se dibuje (1s hack o Promises)
                setTimeout(() => {
                    json.data.campos.forEach(c => {
                        crearElementoCampoUI(c.variable, parseFloat(c.pos_x), parseFloat(c.pos_y), c.font_size, c.font_weight);
                    });
                }, 800);
            }
        }
    } catch(e) {
        console.error(e);
    }
}

async function renderizarPDF(url) {
    const loadingTask = pdfjsLib.getDocument(url);
    const pdf = await loadingTask.promise;
    const page = await pdf.getPage(1);
    
    // Escalar para el preview (ancho fijo de ~600px para facilidad)
    const viewportOriginal = page.getViewport({scale: 1});
    pdfOriginalWidth = viewportOriginal.width; // pt
    
    const scale = 800 / viewportOriginal.width; // Visual preview scale
    currentScale = scale;
    const viewport = page.getViewport({scale: scale});
    
    const canvas = document.getElementById('pdfCanvas');
    const context = canvas.getContext('2d');
    canvas.height = viewport.height;
    canvas.width = viewport.width;
    
    document.getElementById('pdfContainer').style.width = viewport.width + 'px';
    document.getElementById('pdfContainer').style.height = viewport.height + 'px';

    await page.render({canvasContext: context, viewport: viewport}).promise;
}

function renderizarImagen(url) {
    const canvas = document.getElementById('pdfCanvas');
    const ctx = canvas.getContext('2d');
    const img = new Image();
    img.onload = () => {
        // Asumimos un A4 base = 595.28 x 841.89 pt
        pdfOriginalWidth = 595.28;
        currentScale = 800 / pdfOriginalWidth;
        
        canvas.width = 800;
        canvas.height = img.height * (800 / img.width);
        
        document.getElementById('pdfContainer').style.width = canvas.width + 'px';
        document.getElementById('pdfContainer').style.height = canvas.height + 'px';
        
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
    };
    img.src = url;
}

// ---------------------------------
// LÓGICA DE CAMPOS ARRASTRABLES
// ---------------------------------
function agregarCampo(variable) {
    if(!currentPlantillaId) return alert("Carga una plantilla primero");
    // Coordenadas default en PT (centro)
    const defX = 100; 
    const defY = 100;
    crearElementoCampoUI(variable, defX, defY, 10, 'normal');
}

function crearElementoCampoUI(variable, xPt, yPt, size, weight) {
    const container = document.getElementById('pdfContainer');
    const el = document.createElement('div');
    el.className = 'draggable-field';
    
    // Set text based on preview mode
    el.innerText = isPreviewMode ? getDummyData(variable) : `[${variable}]`;
    
    el.dataset.var = variable;
    el.dataset.size = size || 10;
    el.dataset.weight = weight || 'normal';
    el.style.fontSize = (el.dataset.size * currentScale) + 'px';
    el.style.fontWeight = el.dataset.weight;
    
    // Convertir de PT a Pixeles del Canvas
    el.style.left = (xPt * currentScale) + 'px';
    el.style.top = (yPt * currentScale) + 'px'; 

    el.onmousedown = iniciarArrastre;
    el.onclick = seleccionarCampo;

    // Remove border and background if in preview mode
    if (isPreviewMode) {
        el.style.background = 'transparent';
        el.style.border = 'none';
        el.style.color = '#000';
    }

    container.appendChild(el);
    campos.push(el);
}

function togglePreview() {
    isPreviewMode = !isPreviewMode;
    const btn = document.getElementById('btnPreview');
    
    if (isPreviewMode) {
        btn.innerHTML = '<i class="fa-solid fa-eye"></i> Vista Previa (Activa)';
        btn.style.background = '#eef2ff';
        btn.style.borderColor = '#4f46e5';
        btn.style.color = '#4f46e5';
    } else {
        btn.innerHTML = '<i class="fa-solid fa-eye-slash"></i> Vista Previa (Inactiva)';
        btn.style.background = 'white';
        btn.style.borderColor = '#667eea';
        btn.style.color = '#667eea';
    }

    // Refresh all existing fields
    campos.forEach(el => {
        const variable = el.dataset.var;
        if (isPreviewMode) {
            el.innerText = getDummyData(variable);
            el.style.background = 'transparent';
            el.style.outline = 'none';
            el.style.color = '#000';
        } else {
            el.innerText = `[${variable}]`;
            el.style.background = 'rgba(102, 126, 234, 0.15)';
            el.style.outline = '1px dashed var(--primary)';
            el.style.color = 'var(--text-dark)';
        }
    });
}

function getDummyData(variable) {
    const data = {
        'cliente.nombre': 'JUAN PEREZ GONZALEZ',
        'cliente.cedula': '001-1234567-8',
        'cliente.telefono': '809-555-1234',
        'poliza.numero_poliza': 'AUTO-Tramite',
        'poliza.fecha_emision': '18-04-2026',
        'poliza.fecha_vencimiento': '19-04-2026',
        'poliza.prima_total': '15,000.00',
        'poliza.fianza_judicial': '50,000.00',
        'poliza.casa_contratada': 'CENTRO DEL AUTOMOVILISTA',
        'poliza.asistencia_vial': 'SI',
        'poliza.deduccion': 'Deduc. Min',
        'vehiculo.marca': 'Honda',
        'vehiculo.modelo': 'Civic',
        'vehiculo.anio': '2018',
        'vehiculo.chasis': '4RRTFDGCVRE67HJDRFFCS',
        'vehiculo.placa': 'A457848',
        'vehiculo.uso': 'PRIVADO',
        'vehiculo.tipo_vehiculo': 'AUTOMOVIL',
        'general.hora_emision': '01:12 pm'
    };
    return data[variable] || 'Dato Ficticio';
}

function limpiarCampos() {
    campos.forEach(c => c.remove());
    campos = [];
    deseleccionarTodo();
}

// Drag & Drop
let dragEl = null, offsetX = 0, offsetY = 0;
function iniciarArrastre(e) {
    seleccionarCampo({target: this});
    dragEl = this;
    const rect = dragEl.getBoundingClientRect();
    const contRect = document.getElementById('pdfContainer').getBoundingClientRect();
    
    offsetX = e.clientX - rect.left;
    offsetY = e.clientY - rect.top;
    
    document.addEventListener('mousemove', arrastrar);
    document.addEventListener('mouseup', soltar);
    e.preventDefault();
}

function arrastrar(e) {
    if(!dragEl) return;
    const container = document.getElementById('pdfContainer');
    const contRect = container.getBoundingClientRect();
    
    let left = e.clientX - contRect.left - offsetX;
    let top = e.clientY - contRect.top - offsetY;
    
    // Boundaries
    if(left < 0) left = 0;
    if(top < 0) top = 0;
    if(left > contRect.width - dragEl.offsetWidth) left = contRect.width - dragEl.offsetWidth;
    if(top > contRect.height - dragEl.offsetHeight) top = contRect.height - dragEl.offsetHeight;
    
    dragEl.style.left = left + 'px';
    dragEl.style.top = top + 'px';
}

function soltar() {
    dragEl = null;
    document.removeEventListener('mousemove', arrastrar);
    document.removeEventListener('mouseup', soltar);
}

// Selección y Edición
function seleccionarCampo(e) {
    deseleccionarTodo();
    activeCampo = e.target || e;
    activeCampo.classList.add('selected');
    
    document.getElementById('opcionesCampo').style.display = 'block';
    document.getElementById('campoSize').value = activeCampo.dataset.size;
    document.getElementById('campoBold').checked = activeCampo.dataset.weight === 'bold';
}

function deseleccionarTodo() {
    campos.forEach(c => c.classList.remove('selected'));
    activeCampo = null;
    document.getElementById('opcionesCampo').style.display = 'none';
}

function actualizarCampoActivo() {
    if(!activeCampo) return;
    const newSize = document.getElementById('campoSize').value;
    const isBold = document.getElementById('campoBold').checked;
    
    activeCampo.dataset.size = newSize;
    activeCampo.dataset.weight = isBold ? 'bold' : 'normal';
    
    activeCampo.style.fontSize = (newSize * currentScale) + 'px';
    activeCampo.style.fontWeight = activeCampo.dataset.weight;
}

function eliminarCampoActivo() {
    if(!activeCampo) return;
    activeCampo.remove();
    campos = campos.filter(c => c !== activeCampo);
    deseleccionarTodo();
}

// Guardar Mapeo
async function guardarMapeo() {
    if(!currentPlantillaId) return alert("No hay plantilla cargada.");
    
    const payload = {
        action: 'save_fields',
        plantilla_id: currentPlantillaId,
        campos: []
    };

    // Extraer coordenadas
    campos.forEach(el => {
        // Convertir pixeles HTML a PT de PDF
        const pxLeft = parseFloat(el.style.left);
        const pxTop = parseFloat(el.style.top);
        
        const ptX = pxLeft / currentScale;
        const ptY = pxTop / currentScale; 
        
        payload.campos.push({
            variable: el.dataset.var,
            x: ptX,
            y: ptY,
            size: el.dataset.size || 10,
            weight: el.dataset.weight || 'normal'
        });
    });

    try {
        const res = await fetch(API_URL, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        const json = await res.json();
        if(json.exito) {
            alert(json.mensaje);
        } else {
            alert(json.mensaje);
        }
    } catch(e) {
        alert("Error guardando configuración");
    }
}
