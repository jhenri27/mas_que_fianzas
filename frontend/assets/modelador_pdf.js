/**
 * CONTROLADOR MODELADOR PDF Y AUTOCOMPLETADO
 * MAS QUE FIANZAS - Frontend v1.0
 */

let currentPlantilla = null;
let mappedFields = []; // Campos mapeados actualmente en memoria
let currentPdf = null;
let pdfScale = 1.25;
let activeFieldId = null; // ID del campo seleccionado para editar propiedades
let dragVariable = null; // Variable que se está arrastrando
let mapeadorSimuladoDatos = null;
let mapeadorPreviewActivo = false;

// Manejo global de errores para diagnóstico
window.onerror = function (message, source, lineno, colno, error) {
    const errorMsg = `JS Error: ${message} en línea ${lineno}:${colno}`;
    console.error(errorMsg);
    if (window.MQF && MQF.toast) {
        MQF.toast(errorMsg, "error");
    } else {
        alert(errorMsg);
    }
    return false;
};

window.onunhandledrejection = function(event) {
    const errorMsg = `Rechazo de Promesa: ${event.reason}`;
    console.error(errorMsg);
    if (window.MQF && MQF.toast) {
        MQF.toast(errorMsg, "error");
    } else {
        alert(errorMsg);
    }
};

// Inicializar al cargar el módulo
document.addEventListener("DOMContentLoaded", () => {
    cargarAseguradoras();
    cargarPlantillas();
    cargarCotizacionesPrueba();
});

// Cambiar de Pestañas
function cambiarPestaña(tabName) {
    document.querySelectorAll(".tab-btn").forEach(btn => btn.classList.remove("active"));
    document.querySelectorAll(".tab-content").forEach(content => content.classList.remove("active"));
    
    // Activar botón correspondiente por ID
    let btnId = "";
    if (tabName === 'plantillas') btnId = "tabBtnPlantillas";
    else if (tabName === 'mapeador') btnId = "tabBtnMapeador";
    else if (tabName === 'pruebas') btnId = "tabBtnPruebas";
    
    const targetBtn = document.getElementById(btnId);
    if (targetBtn) {
        targetBtn.classList.add("active");
        // Forzar a habilitar en caso de cambio programático
        targetBtn.disabled = false;
    }
    
    const targetContent = document.getElementById("tab-" + tabName);
    if (targetContent) {
        targetContent.classList.add("active");
    }

    // Si el usuario cambia al mapeador, hay una plantilla activa pero no se ha renderizado el visor PDF
    if (tabName === 'mapeador' && currentPlantilla) {
        const container = document.getElementById("pdfPagesContainer");
        if (container) {
            // Verificar si hay páginas renderizadas (en vez de children.length que puede contener el spinner)
            const hasPages = container.querySelector(".pdf-page-container");
            if (!hasPages) {
                renderizarPDFWorkspace(currentPlantilla.archivo_base);
            }
        }
    }
}

// 1. Cargar catálogo de aseguradoras en el select
async function cargarAseguradoras() {
    try {
        const respuesta = await api.solicitud("/fianza_tarifarios.php?action=listar_aseguradoras");
        const lista = respuesta.datos || respuesta.data || [];
        if (respuesta.exito && Array.isArray(lista)) {
            const select = document.getElementById("plantillaAseguradora");
            select.innerHTML = '<option value="">Seleccione Aseguradora...</option>';
            lista.forEach(aseg => {
                select.innerHTML += `<option value="${aseg.id}">${aseg.nombre}</option>`;
            });
        }
    } catch(err) {
        console.error("Error cargando aseguradoras:", err);
    }
}

// 2. Cargar listado de plantillas
async function cargarPlantillas() {
    try {
        const respuesta = await api.solicitud("/pdf_modeler.php?action=listar_plantillas");
        const tbody = document.getElementById("listaPlantillasCargadas");
        
        if (respuesta.exito && Array.isArray(respuesta.plantillas)) {
            if (respuesta.plantillas.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: var(--mqf-text-muted);">No hay plantillas registradas. Carga una arriba para comenzar.</td></tr>';
                return;
            }
            
            tbody.innerHTML = respuesta.plantillas.map(p => {
                const interactivoBadge = p.es_interactivo == 1 
                    ? '<span class="mqf-badge mqf-badge--success"><i class="fa-solid fa-wand-magic-sparkles"></i> AcroForm</span>' 
                    : '<span class="mqf-badge mqf-badge--info"><i class="fa-solid fa-pencil"></i> Estático</span>';
                
                return `
                    <tr>
                        <td style="font-weight: 600;">${p.nombre}</td>
                        <td>${p.aseguradora_nombre || 'N/A'}</td>
                        <td>
                            <span class="mqf-badge mqf-badge--primary">${p.HTML_content || '0'} campos</span>
                        </td>
                        <td>${interactivoBadge}</td>
                        <td>${new Date(p.fecha_creacion).toLocaleDateString()}</td>
                        <td style="display: flex; gap: 8px;">
                            <button class="mqf-btn mqf-btn--sm mqf-btn--primary" onclick="abrirMapeador(${p.id})"><i class="fa-solid fa-pencil"></i> Mapear</button>
                            <button class="mqf-btn mqf-btn--sm mqf-btn--success" onclick="abrirPruebas(${p.id})"><i class="fa-solid fa-circle-check"></i> Probar</button>
                        </td>
                    </tr>
                `;
            }).join('');
        }
    } catch(err) {
        console.error("Error cargando plantillas:", err);
    }
}

// 3. Subir Nueva Plantilla PDF
async function subirNuevaPlantilla(e) {
    e.preventDefault();
    const nombre = document.getElementById("plantillaNombre").value.trim();
    const asegId = document.getElementById("plantillaAseguradora").value;
    const fileInput = document.getElementById("plantillaArchivo");
    
    if (!fileInput.files || fileInput.files.length === 0) {
        MQF.toast("Debe seleccionar un archivo PDF.", "error");
        return;
    }
    
    const file = fileInput.files[0];
    const formData = new FormData();
    formData.append("file", file);
    formData.append("aseguradora_id", asegId);
    formData.append("nombre", nombre);
    formData.append("ancho_mm", 215.9); // Carta por defecto
    formData.append("alto_mm", 279.4);
    
    MQF.toast("Subiendo y analizando estructura de PDF...", "info");
    
    try {
        // Enviar con fetch normal por FormData
        const resp = await fetch("/PLATAFORMA_INTEGRADA/backend/api/pdf_modeler.php?action=subir_plantilla", {
            method: "POST",
            headers: {
                "Authorization": "Bearer " + (localStorage.getItem("token_sesion") || "")
            },
            body: formData
        });
        const res = await resp.json();
        
        if (res.exito) {
            MQF.toast("¡Plantilla cargada y analizada correctamente!", "success");
            document.getElementById("formCargaPlantilla").reset();
            cargarPlantillas();
            abrirMapeador(res.plantilla_id);
        } else {
            MQF.toast("Error al subir plantilla: " + res.mensaje, "error");
        }
    } catch(err) {
        console.error("Error subiendo plantilla:", err);
        MQF.toast("Error de conexión al subir plantilla.", "error");
    }
}

// 4. Abrir Pestaña de Mapeador y Renderizar PDF
async function abrirMapeador(plantillaId) {
    try {
        MQF.toast("Cargando detalles de plantilla...", "info");
        const res = await api.solicitud(`/pdf_modeler.php?action=get_plantilla_detalle&id=${plantillaId}`);
        
        if (res.exito && res.plantilla) {
            currentPlantilla = res.plantilla;
            mappedFields = res.campos || [];
            
            document.getElementById("tabBtnMapeador").disabled = false;
            document.getElementById("tabBtnPruebas").disabled = false; // Habilitar también pestaña de pruebas
            
            // Limpiar previsualizaciones del mapeador antes de abrir
            resetLayoutMapeador();
            
            cambiarPestaña("mapeador");
            
            // Reset zoom
            pdfScale = 1.25;
            actualizarZoomLabel();
            
            // Renderizar PDF en Canvas
            renderizarPDFWorkspace(currentPlantilla.archivo_base);
        }
    } catch(err) {
        console.error("Error abriendo mapeador:", err);
        MQF.toast("Error de red al cargar plantilla.", "error");
    }
}

// Renderizado de PDF con PDF.js y capas overlay
async function renderizarPDFWorkspace(pdfRelativeUrl, isZooming = false) {
    const container = document.getElementById("pdfPagesContainer");
    if (!isZooming) {
        container.innerHTML = '<div style="text-align: center; color: white; padding: 40px;"><i class="fa-solid fa-spinner fa-spin fa-2xl"></i><p style="margin-top:15px; font-weight:600;">Cargando visor de páginas...</p></div>';
    }
    
    try {
        const pdfUrl = "/PLATAFORMA_INTEGRADA/" + pdfRelativeUrl;
        
        // Cargar documento PDF.js
        pdfjsLib.GlobalWorkerOptions.workerSrc = "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.worker.min.js";
        const loadingTask = pdfjsLib.getDocument(pdfUrl);
        currentPdf = await loadingTask.promise;
        
        container.innerHTML = ""; // Limpiar
        
        for (let pageNum = 1; pageNum <= currentPdf.numPages; pageNum++) {
            const page = await currentPdf.getPage(pageNum);
            const viewport = page.getViewport({ scale: pdfScale });
            
            // Crear el contenedor de página
            const pageDiv = document.createElement("div");
            pageDiv.className = "pdf-page-container";
            pageDiv.dataset.page = pageNum;
            pageDiv.style.width = viewport.width + "px";
            pageDiv.style.height = viewport.height + "px";
            
            // Canvas para dibujo
            const canvas = document.createElement("canvas");
            canvas.className = "pdf-canvas";
            canvas.width = viewport.width;
            canvas.height = viewport.height;
            pageDiv.appendChild(canvas);
            
            // Capa Overlay transparente para interactividad drag/drop y dibujo
            const overlay = document.createElement("div");
            overlay.className = "pdf-overlay";
            overlay.dataset.page = pageNum;
            
            // Configurar zonas de dibujo y drop
            configurarOverlayEventos(overlay);
            pageDiv.appendChild(overlay);
            
            container.appendChild(pageDiv);
            
            // Renderizar la página en el canvas
            const renderContext = {
                canvasContext: canvas.getContext("2d"),
                viewport: viewport
            };
            await page.render(renderContext).promise;
        }
        
        // Una vez renderizadas todas las páginas, dibujar los campos previamente mapeados
        dibujarCamposMapeados();
        
    } catch(err) {
        console.error("Error renderizando PDF:", err);
        container.innerHTML = '<div style="color: #f87171; text-align: center; padding: 20px;">❌ Error al renderizar PDF. Verifique que el archivo exista.</div>';
    }
}

// Funciones de control de Zoom para visualización de plantilla
async function zoomIn() {
    if (!currentPlantilla || !currentPdf) return;
    if (pdfScale >= 3.0) {
        MQF.toast("Zoom máximo alcanzado (300%).", "warning");
        return;
    }
    pdfScale = parseFloat((pdfScale + 0.15).toFixed(2));
    actualizarZoomLabel();
    await renderizarPDFWorkspace(currentPlantilla.archivo_base, true);
}

async function zoomOut() {
    if (!currentPlantilla || !currentPdf) return;
    if (pdfScale <= 0.6) {
        MQF.toast("Zoom mínimo alcanzado (60%).", "warning");
        return;
    }
    pdfScale = parseFloat((pdfScale - 0.15).toFixed(2));
    actualizarZoomLabel();
    await renderizarPDFWorkspace(currentPlantilla.archivo_base, true);
}

function actualizarZoomLabel() {
    const label = document.getElementById("zoomPercentLabel");
    if (label) {
        label.innerText = Math.round(pdfScale * 100) + "%";
    }
}

// Configurar eventos drag/drop y click-to-draw en la capa overlay
function configurarOverlayEventos(overlay) {
    const pageNum = parseInt(overlay.dataset.page);
    
    // Evitar dragover para permitir soltar (drop)
    overlay.addEventListener("dragover", (e) => {
        e.preventDefault();
        if (e.dataTransfer) {
            e.dataTransfer.dropEffect = "move";
        }
    });
    
    // Soltar variable de la lista
    overlay.addEventListener("drop", (e) => {
        e.preventDefault();
        
        const rect = overlay.getBoundingClientRect();
        const clickX = e.clientX - rect.left;
        const clickY = e.clientY - rect.top;
        
        // Convertir coordenadas a milímetros de plantilla
        const pctX = clickX / rect.width;
        const pctY = clickY / rect.height;
        
        const mmWidth = parseFloat(currentPlantilla.ancho_mm) || 215.9;
        const mmHeight = parseFloat(currentPlantilla.alto_mm) || 279.4;
        const mmX = pctX * mmWidth;
        const mmY = pctY * mmHeight;
        
        // Crear nuevo campo de mapeo en memoria
        const id_temp = "c_" + Date.now();
        const nuevoCampo = {
            id: id_temp,
            plantilla_id: currentPlantilla.id,
            pagina: pageNum,
            variable: dragVariable || "", // Si es vacío, es campo manual
            nombre_campo_pdf: dragVariable ? "" : "campo_" + Date.now(),
            pos_x: mmX,
            pos_y: mmY,
            font_size: 10,
            font_family: "helvetica",
            color: "#000000",
            font_weight: "normal",
            alineacion: "left",
            ancho: 50.0
        };
        
        mappedFields.push(nuevoCampo);
        dibujarCamposMapeados();
        abrirPopoverPropiedades(nuevoCampo, e.clientX + window.scrollX, e.clientY + window.scrollY);
    });

    // Permitir clic para crear campo en coordenadas
    overlay.addEventListener("click", (e) => {
        if (e.target !== overlay) return; // Evitar que dispare al hacer clic en un cuadro ya dibujado
        
        // Si el popover de propiedades está abierto, cerrarlo y consumir el clic
        const popover = document.getElementById("popoverPropiedadesCampo");
        if (popover && popover.style.display === "flex") {
            cerrarPopoverPropiedades();
            return;
        }
        
        const rect = overlay.getBoundingClientRect();
        const clickX = e.clientX - rect.left;
        const clickY = e.clientY - rect.top;
        
        const pctX = clickX / rect.width;
        const pctY = clickY / rect.height;
        
        const mmWidth = parseFloat(currentPlantilla.ancho_mm) || 215.9;
        const mmHeight = parseFloat(currentPlantilla.alto_mm) || 279.4;
        const mmX = pctX * mmWidth;
        const mmY = pctY * mmHeight;
        
        const id_temp = "c_" + Date.now();
        const nuevoCampo = {
            id: id_temp,
            plantilla_id: currentPlantilla.id,
            pagina: pageNum,
            variable: "", // Vacío para entrada manual
            nombre_campo_pdf: "campo_" + Date.now(),
            pos_x: mmX,
            pos_y: mmY,
            font_size: 10,
            font_family: "helvetica",
            color: "#000000",
            font_weight: "normal",
            alineacion: "left",
            ancho: 50.0
        };
        
        mappedFields.push(nuevoCampo);
        dibujarCamposMapeados();
        abrirPopoverPropiedades(nuevoCampo, e.clientX + window.scrollX, e.clientY + window.scrollY);
    });
}

// Dibujar campos sobre las capas overlay correspondientes
function dibujarCamposMapeados() {
    // Limpiar cajas anteriores del DOM
    document.querySelectorAll(".mapped-field-box").forEach(box => box.remove());
    
    // Obtener todas las capas overlay
    const overlays = document.querySelectorAll(".pdf-overlay");
    if (overlays.length === 0) return;
    
    mappedFields.forEach(c => {
        const overlay = Array.from(overlays).find(o => parseInt(o.dataset.page) === parseInt(c.pagina));
        if (!overlay) return;
        
        const rect = overlay.getBoundingClientRect();
        
        const mmWidth = parseFloat(currentPlantilla.ancho_mm) || 215.9;
        const mmHeight = parseFloat(currentPlantilla.alto_mm) || 279.4;
        
        // Convertir milímetros a píxeles en base al ancho real actual del canvas
        const posX = (parseFloat(c.pos_x) / mmWidth) * rect.width;
        const posY = (parseFloat(c.pos_y) / mmHeight) * rect.height;
        
        const box = document.createElement("div");
        box.className = "mapped-field-box";
        if (c.id === activeFieldId) {
            box.classList.add("active");
        }
        box.style.left = posX + "px";
        box.style.top = posY + "px";
        
        const customWidth = parseFloat(c.ancho);
        box.style.width = (!isNaN(customWidth) && customWidth > 0 ? (customWidth / mmWidth) * rect.width : 120) + "px";
        box.style.height = "22px";
        box.dataset.id = c.id;
        
        // Etiqueta visual
        const labelText = c.variable || c.nombre_campo_pdf || "campo manual";
        box.innerHTML = `<span class="box-label" title="${labelText}">${labelText}</span>`;
        
        // Aplicar estilos y textos en modo vista previa real
        if (mapeadorPreviewActivo && mapeadorSimuladoDatos) {
            box.classList.add("preview-mode");
            const val = c.variable ? (mapeadorSimuladoDatos[c.variable] || '') : `[${c.nombre_campo_pdf || 'campo manual'}]`;
            
            // Borde muy sutil y fondo translúcido para ubicar la caja en previsualización
            box.style.border = "1px dotted rgba(99, 102, 241, 0.4)";
            box.style.background = "rgba(255, 255, 255, 0.75)";
            
            // Buscar y aplicar estilos de fuente
            const label = box.querySelector(".box-label");
            if (label) {
                label.innerText = val;
                
                // Escalamiento dinámico del tamaño de fuente en píxeles basado en el zoom y mmWidth
                const page_width_pts = mmWidth * 2.83464;
                const fontSizePx = (c.font_size || 10) * (rect.width / page_width_pts);
                label.style.fontSize = fontSizePx + "px";
                
                label.style.fontFamily = c.font_family === 'times-roman' ? 'Georgia, serif' : (c.font_family === 'courier' ? 'Courier New, monospace' : 'Arial, sans-serif');
                label.style.color = c.color || '#000000';
                label.style.fontWeight = c.font_weight === 'bold' ? 'bold' : 'normal';
                label.style.textAlign = c.alineacion || 'left';
                label.style.width = "100%";
                label.style.display = "block";
            }
            
            // Alinear caja según c.alineacion
            if (c.alineacion === 'right') box.style.justifyContent = 'flex-end';
            else if (c.alineacion === 'center') box.style.justifyContent = 'center';
            else box.style.justifyContent = 'flex-start';
        }
        
        // Agregar botón de borrado rápido
        const delBtn = document.createElement("button");
        delBtn.className = "box-delete";
        delBtn.innerHTML = "&times;";
        delBtn.onclick = (e) => {
            e.stopPropagation();
            eliminarCampo(c.id);
        };
        box.appendChild(delBtn);
        
        // Agregar tirador de cambio de tamaño (resize handle)
        const resizeHandle = document.createElement("div");
        resizeHandle.className = "box-resize-handle";
        box.appendChild(resizeHandle);
        
        if (mapeadorPreviewActivo && mapeadorSimuladoDatos) {
            delBtn.style.display = "none";
            // keep resizeHandle visible/active even in preview mode so user can drag-to-resize
        }
        
        // Lógica de cambio de tamaño al arrastrar el tirador
        resizeHandle.addEventListener("mousedown", (e) => {
            e.stopPropagation();
            e.preventDefault();
            
            const startX = e.clientX;
            const startWidth = parseFloat(box.style.width) || 120;
            
            const onMouseMove = (moveEvent) => {
                const dx = moveEvent.clientX - startX;
                let newWidthPx = startWidth + dx;
                
                // Ancho mínimo de 15 píxeles
                newWidthPx = Math.max(15, newWidthPx);
                
                // Convertir a milímetros
                const overlayRect = overlay.getBoundingClientRect();
                const newWidthMm = (newWidthPx / overlayRect.width) * mmWidth;
                
                c.ancho = parseFloat(newWidthMm.toFixed(2));
                box.style.width = newWidthPx + "px";
            };
            
            const onMouseUp = () => {
                document.removeEventListener("mousemove", onMouseMove);
                document.removeEventListener("mouseup", onMouseUp);
                dibujarCamposMapeados();
            };
            
            document.addEventListener("mousemove", onMouseMove);
            document.addEventListener("mouseup", onMouseUp);
        });
        
        // Lógica de arrastrar (drag-to-move) para reposicionar la caja con click izquierdo
        box.addEventListener("mousedown", (e) => {
            if (e.button !== 0) return; // Solo click izquierdo
            e.stopPropagation();
            e.preventDefault();
            
            const startX = e.clientX;
            const startY = e.clientY;
            const startPosX = parseFloat(c.pos_x);
            const startPosY = parseFloat(c.pos_y);
            let hasMoved = false;
            
            box.style.cursor = "grabbing";
            
            const onMouseMove = (moveEvent) => {
                const dx = moveEvent.clientX - startX;
                const dy = moveEvent.clientY - startY;
                
                if (Math.abs(dx) > 3 || Math.abs(dy) > 3) {
                    hasMoved = true;
                }
                
                const overlayRect = overlay.getBoundingClientRect();
                const offsetXmm = (dx / overlayRect.width) * mmWidth;
                const offsetYmm = (dy / overlayRect.height) * mmHeight;
                
                let newPosX = startPosX + offsetXmm;
                let newPosY = startPosY + offsetYmm;
                
                // Limitar a los bordes de la página
                const fieldWidth = !isNaN(customWidth) && customWidth > 0 ? customWidth : 20;
                newPosX = Math.max(0, Math.min(mmWidth - fieldWidth, newPosX));
                newPosY = Math.max(0, Math.min(mmHeight - 5, newPosY));
                
                c.pos_x = newPosX;
                c.pos_y = newPosY;
                
                // Actualizar visualmente la posición del elemento en tiempo real
                const newLeft = (newPosX / mmWidth) * overlayRect.width;
                const newTop = (newPosY / mmHeight) * overlayRect.height;
                box.style.left = newLeft + "px";
                box.style.top = newTop + "px";
            };
            
            const onMouseUp = () => {
                box.style.cursor = "move";
                document.removeEventListener("mousemove", onMouseMove);
                document.removeEventListener("mouseup", onMouseUp);
                
                if (hasMoved) {
                    box.dataset.hasMoved = "true";
                    setTimeout(() => {
                        delete box.dataset.hasMoved;
                    }, 50);
                }
            };
            
            document.addEventListener("mousemove", onMouseMove);
            document.addEventListener("mouseup", onMouseUp);
        });
        
        // Clic para abrir popover de configuración (solo si no se arrastró)
        box.onclick = (e) => {
            e.stopPropagation();
            if (box.dataset.hasMoved === "true") return;
            const clientRect = box.getBoundingClientRect();
            abrirPopoverPropiedades(c, clientRect.left + window.scrollX, clientRect.bottom + window.scrollY + 5);
        };
        
        overlay.appendChild(box);
    });
}

// Guardar variable que se está arrastrando
function drag(ev, variableName) {
    dragVariable = variableName;
    if (ev.dataTransfer) {
        ev.dataTransfer.setData("text", variableName);
    }
}

// 5. Popover de Configuración de Propiedades de Campo
function abrirPopoverPropiedades(campo, clientX, clientY) {
    activeFieldId = campo.id;
    // Highlight the selected field visual immediately by redrawing
    dibujarCamposMapeados();
    
    const popover = document.getElementById("popoverPropiedadesCampo");
    popover.style.display = "flex";
    popover.style.left = clientX + "px";
    popover.style.top = clientY + "px";
    
    // Poblar valores
    document.getElementById("popoverVariable").value = campo.variable || "Entrada Manual / Personalizada";
    document.getElementById("popoverCampoPDF").value = campo.nombre_campo_pdf || "";
    document.getElementById("popoverFontFamily").value = campo.font_family || "helvetica";
    document.getElementById("popoverFontSize").value = campo.font_size || 10;
    document.getElementById("popoverFontWeight").value = campo.font_weight || "normal";
    
    try {
        const colorInput = document.getElementById("popoverColor");
        let hexColor = campo.color || "#000000";
        if (hexColor.toLowerCase() === "black") hexColor = "#000000";
        if (hexColor.toLowerCase() === "white") hexColor = "#ffffff";
        if (hexColor.toLowerCase() === "blue") hexColor = "#0000ff";
        if (hexColor.toLowerCase() === "red") hexColor = "#ff0000";
        if (hexColor.toLowerCase() === "green") hexColor = "#00ff00";
        
        if (hexColor.startsWith("#") && hexColor.length === 7) {
            colorInput.value = hexColor;
        } else {
            colorInput.value = "#000000";
        }
    } catch(err) {
        try {
            document.getElementById("popoverColor").value = "#000000";
        } catch(e) {}
    }
    
    document.getElementById("popoverAlineacion").value = campo.alineacion || "left";
    document.getElementById("popoverAncho").value = Math.round(campo.ancho || 50);
    document.getElementById("popoverFondoOpaco").checked = campo.fondo_opaco == 1;
}

function cerrarPopoverPropiedades() {
    document.getElementById("popoverPropiedadesCampo").style.display = "none";
    activeFieldId = null;
    // Remove highlight from the field immediately by redrawing
    dibujarCamposMapeados();
}

// Aplicar cambios del popover en el campo en memoria
function aplicarPropiedadesCampo() {
    if (!activeFieldId) return;
    
    const idx = mappedFields.findIndex(c => c.id === activeFieldId);
    if (idx !== -1) {
        mappedFields[idx].nombre_campo_pdf = document.getElementById("popoverCampoPDF").value.trim();
        mappedFields[idx].font_family = document.getElementById("popoverFontFamily").value;
        mappedFields[idx].font_size = parseInt(document.getElementById("popoverFontSize").value);
        mappedFields[idx].font_weight = document.getElementById("popoverFontWeight").value;
        mappedFields[idx].color = document.getElementById("popoverColor").value;
        mappedFields[idx].alineacion = document.getElementById("popoverAlineacion").value;
        mappedFields[idx].ancho = parseFloat(document.getElementById("popoverAncho").value) || 50.0;
        mappedFields[idx].fondo_opaco = document.getElementById("popoverFondoOpaco").checked ? 1 : 0;
        
        MQF.toast("Propiedades de campo actualizadas en la vista.", "info");
        dibujarCamposMapeados();
    }
    cerrarPopoverPropiedades();
}

// Eliminar campo de la memoria
function eliminarCampo(id) {
    mappedFields = mappedFields.filter(c => c.id !== id);
    dibujarCamposMapeados();
    cerrarPopoverPropiedades();
    MQF.toast("Campo removido de la plantilla.", "warning");
}

function eliminarCampoSeleccionado() {
    if (activeFieldId) {
        eliminarCampo(activeFieldId);
    }
}

function cancelarEdicion() {
    mappedFields = [];
    currentPlantilla = null;
    document.getElementById("tabBtnMapeador").disabled = true;
    document.getElementById("tabBtnPruebas").disabled = true;
    
    // Reset layout y previsualizaciones del mapeador
    resetLayoutMapeador();
    
    cambiarPestaña("plantillas");
}

// 6. Guardar Configuración en Base de Datos (API)
async function guardarMapeoConfig() {
    if (!currentPlantilla) return;
    
    try {
        MQF.toast("Guardando configuración de mapeo...", "info");
        const datos = {
            plantilla_id: currentPlantilla.id,
            campos: mappedFields
        };
        
        const res = await api.solicitud("/pdf_modeler.php?action=guardar_mapeo", "POST", datos);
        if (res.exito) {
            MQF.toast("¡Mapeo de plantilla guardado correctamente!", "success");
            cargarPlantillas();
            
            // Reset layout y previsualizaciones del mapeador
            resetLayoutMapeador();
            
            cambiarPestaña("plantillas");
        } else {
            MQF.toast("Error al guardar: " + res.mensaje, "error");
        }
    } catch(err) {
        console.error("Error guardando mapeo:", err);
        MQF.toast("Error de red al guardar mapeo.", "error");
    }
}

// 7. Cargar Cotizaciones para simulación de prueba
async function cargarCotizacionesPrueba() {
    try {
        // Obtenemos cotizaciones del sistema para pre-poblar datos de prueba
        const res = await api.solicitud("/cotizaciones.php?action=listar");
        const select = document.getElementById("simularCotizacionSelect");
        const selectMapeador = document.getElementById("mapeadorSimulacionSelect");
        
        if (res.exito && Array.isArray(res.datos)) {
            const options = ['<option value="">Seleccione cotización...</option>'];
            res.datos.forEach(cot => {
                const label = `${cot.numero || cot.id} - ${cot.cliente || 'Sin Nombre'} (${cot.aseguradora || 'Fianza'})`;
                options.push(`<option value="${cot.id}">${label}</option>`);
            });
            if (select) select.innerHTML = options.join('');
            if (selectMapeador) selectMapeador.innerHTML = options.join('');
        }
    } catch(err) {
        console.error("Error cargando cotizaciones de prueba:", err);
    }
}

// Cargar variables y construir el formulario dinámico en base a la plantilla
async function abrirPruebas(plantillaId) {
    try {
        MQF.toast("Abriendo simulador de autollenado...", "info");
        const res = await api.solicitud(`/pdf_modeler.php?action=get_plantilla_detalle&id=${plantillaId}`);
        
        if (res.exito && res.plantilla) {
            currentPlantilla = res.plantilla;
            mappedFields = res.campos || [];
            
            document.getElementById("tabBtnMapeador").disabled = false; // Habilitar también pestaña del mapeador
            document.getElementById("tabBtnPruebas").disabled = false;
            cambiarPestaña("pruebas");
            
            // Construir el formulario dinámico de campos adicionales (manuales)
            construirFormularioCamposAdicionales();
            
            // Reset preview iframe
            document.getElementById("iframePreviewPDFGenerado").src = "about:blank";
        }
    } catch(err) {
        console.error("Error abriendo simulador de pruebas:", err);
    }
}

// Construye campos manuales del formulario (campos de PDF sin variable de sistema MQF asociada)
function construirFormularioCamposAdicionales() {
    const form = document.getElementById("formCamposManualesPrueba");
    form.innerHTML = "";
    
    // Filtrar campos manuales (variable vacía)
    const manuales = mappedFields.filter(c => !c.variable);
    
    if (manuales.length === 0) {
        form.innerHTML = '<div style="font-size: 11px; color: var(--mqf-text-muted); font-style: italic;">Todos los campos del PDF están mapeados a variables del sistema.</div>';
        return;
    }
    
    manuales.forEach(c => {
        const label = c.nombre_campo_pdf || "Campo Manual";
        form.innerHTML += `
            <div class="form-group" style="margin: 0;">
                <label style="font-size: 11px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">${label}</label>
                <input type="text" name="${c.nombre_campo_pdf}" class="form-input" placeholder="Escriba valor de prueba...">
            </div>
        `;
    });
}

// Rellenar dinámicamente datos al elegir una cotización del sistema
async function cargarDatosSimulacionCotizacion(cotId) {
    if (!cotId) return;
    
    try {
        MQF.toast("Precargando datos de la cotización...", "info");
        // Simulamos la API para ver las variables
        const res = await api.solicitud(`/cotizaciones.php?action=obtener&id=${cotId}`);
        if (res.exito && res.dato) {
            MQF.toast("Variables de cotización listas. Pulse 'Llenar y Generar PDF' para testear.", "success");
        }
    } catch(err) {
        console.error("Error cargando cotización:", err);
    }
}

// Ejecutar llenador Python e inyectar en iframe
async function ejecutarAutollenadoSimulado() {
    if (!currentPlantilla) return;
    
    const cotId = document.getElementById("simularCotizacionSelect").value;
    
    // Obtener campos manuales del formulario
    const datosManuales = {};
    const formData = new FormData(document.getElementById("formCamposManualesPrueba"));
    formData.forEach((val, key) => {
        datosManuales[key] = val;
    });
    
    const body = {
        plantilla_id: currentPlantilla.id,
        cotizacion_id: cotId ? parseInt(cotId) : null,
        datos_manuales: datosManuales,
        campos: mappedFields
    };
    
    MQF.toast("Procesando formulario y autollenando PDF oficial...", "info");
    
    try {
        const res = await api.solicitud("/pdf_modeler.php?action=llenar_pdf", "POST", body);
        if (res.exito && res.pdf_url) {
            MQF.toast("¡PDF generado y validado con éxito!", "success");
            
            // Inyectar el PDF generado en el iframe para visualización
            document.getElementById("iframePreviewPDFGenerado").src = "/PLATAFORMA_INTEGRADA/" + res.pdf_url;
        } else {
            MQF.toast("Error al generar PDF: " + res.mensaje, "error");
        }
    } catch(err) {
        console.error("Error generando PDF simulado:", err);
        MQF.toast("Error en el procesador contable/llenador.", "error");
    }
}

// 8. Funciones de Simulación en Tiempo Real en el Mapeador
async function seleccionarSimulacionMapeador(cotId) {
    if (!cotId) {
        mapeadorSimuladoDatos = null;
        if (mapeadorPreviewActivo) {
            dibujarCamposMapeados();
        }
        return;
    }
    
    try {
        MQF.toast("Cargando variables de prueba...", "info");
        const res = await api.solicitud(`/cotizaciones.php?action=obtener&id=${cotId}`);
        if (res.exito && res.dato) {
            // Resolver variables en el formato que espera el modelador
            mapeadorSimuladoDatos = {
                'cliente.nombre': res.dato.cliente || '',
                'cliente.cedula': res.dato.cedula || '',
                'cliente.telefono': res.dato.telefono || '',
                'cliente.email': res.dato.email || '',
                'poliza.numero_poliza': res.dato.numero || res.dato.id || '',
                'poliza.monto_asegurado': res.dato.monto_afianzado || res.dato.total || '',
                'poliza.fecha_inicio': res.dato.fecha ? new Date(res.dato.fecha).toLocaleDateString() : new Date().toLocaleDateString(),
                'poliza.fecha_fin': res.dato.fecha && res.dato.plazo ? new Date(new Date(res.dato.fecha).setMonth(new Date(res.dato.fecha).getMonth() + parseInt(res.dato.plazo))).toLocaleDateString() : '',
                'poliza.prima_neta': res.dato.prima_base || res.dato.total || '',
                'poliza.itbis': res.dato.impuesto || '0.00',
                'poliza.total_pagar': res.dato.total || '',
                'poliza.beneficiario': res.dato.beneficiario || '',
                'poliza.objeto_fianza': res.dato.subtipo || res.dato.cobertura || '',
                'poliza.aseguradora_nombre': res.dato.aseguradora || ''
            };
            
            // Activar checkbox visual y estado
            document.getElementById("chkMapeadorPreview").checked = true;
            mapeadorPreviewActivo = true;
            dibujarCamposMapeados();
            MQF.toast("Valores de prueba activados en el lienzo.", "success");
        }
    } catch(err) {
        console.error("Error cargando cotización para simulador:", err);
        MQF.toast("Error al cargar la cotización de prueba.", "error");
    }
}

function toggleMapeadorPreview(activo) {
    mapeadorPreviewActivo = activo;
    if (activo && !mapeadorSimuladoDatos) {
        MQF.toast("Por favor, seleccione primero una cotización de prueba.", "warning");
        document.getElementById("chkMapeadorPreview").checked = false;
        mapeadorPreviewActivo = false;
        return;
    }
    dibujarCamposMapeados();
}

// 9. Funciones para Previsualización Real en Tiempo Real
async function generarPDFPreviewMapeador() {
    if (!currentPlantilla) return;
    
    const cotId = document.getElementById("mapeadorSimulacionSelect").value;
    if (!cotId) {
        MQF.toast("Por favor, seleccione primero una cotización de prueba.", "warning");
        return;
    }
    
    // Obtener campos manuales si existen en el formulario de pruebas (por si el usuario los llenó)
    const datosManuales = {};
    const formPruebas = document.getElementById("formCamposManualesPrueba");
    if (formPruebas) {
        const formData = new FormData(formPruebas);
        formData.forEach((val, key) => {
            datosManuales[key] = val;
        });
    }
    
    // Si no existen en el form, los inicializamos vacíos en el payload
    mappedFields.forEach(c => {
        if (!c.variable && !datosManuales[c.nombre_campo_pdf]) {
            datosManuales[c.nombre_campo_pdf] = "";
        }
    });
    
    const body = {
        plantilla_id: currentPlantilla.id,
        cotizacion_id: parseInt(cotId),
        datos_manuales: datosManuales,
        campos: mappedFields
    };
    
    MQF.toast("Generando previsualización del PDF real...", "info");
    
    try {
        const res = await api.solicitud("/pdf_modeler.php?action=llenar_pdf", "POST", body);
        if (res.exito && res.pdf_url) {
            MQF.toast("¡Previsualización del PDF real generada!", "success");
            
            // Inyectar el PDF generado en el iframe del mapeador
            const iframe = document.getElementById("iframeMapeadorPreview");
            if (iframe) {
                iframe.src = "/PLATAFORMA_INTEGRADA/" + res.pdf_url;
            }
            
            // Mostrar el contenedor del iframe y el selector de distribución
            const iframeContainer = document.getElementById("iframeMapeadorPreviewContainer");
            if (iframeContainer) {
                iframeContainer.style.display = "block";
            }
            
            const layoutSelector = document.getElementById("mapeadorLayoutSelector");
            if (layoutSelector) {
                layoutSelector.style.display = "flex";
            }
            
            // Activar Vista Dividida por defecto al generar
            cambiarLayoutMapeador('split');
        } else {
            MQF.toast("Error al generar previsualización: " + res.mensaje, "error");
        }
    } catch(err) {
        console.error("Error al generar PDF de prueba para mapeador:", err);
        MQF.toast("Error de conexión al generar previsualización.", "error");
    }
}

function cambiarLayoutMapeador(layout) {
    const wrapper = document.getElementById("editorWorkspaceWrapper");
    const editor = document.getElementById("editorWorkspace");
    const pdfContainer = document.getElementById("iframeMapeadorPreviewContainer");
    
    if (!wrapper || !editor || !pdfContainer) return;
    
    // Quitar active de todos los botones de layout
    document.getElementById("btnLayoutEditor").classList.remove("active");
    document.getElementById("btnLayoutSplit").classList.remove("active");
    document.getElementById("btnLayoutPdf").classList.remove("active");
    
    if (layout === 'editor') {
        wrapper.style.gridTemplateColumns = "1fr";
        editor.style.display = "flex";
        pdfContainer.style.display = "none";
        document.getElementById("btnLayoutEditor").classList.add("active");
    } else if (layout === 'split') {
        wrapper.style.gridTemplateColumns = "1fr 1fr";
        editor.style.display = "flex";
        pdfContainer.style.display = "block";
        document.getElementById("btnLayoutSplit").classList.add("active");
    } else if (layout === 'pdf') {
        wrapper.style.gridTemplateColumns = "1fr";
        editor.style.display = "none";
        pdfContainer.style.display = "block";
        document.getElementById("btnLayoutPdf").classList.add("active");
    }
    
    // Forzar redibujado de campos con un retraso para que coincida con el nuevo tamaño del lienzo
    setTimeout(() => {
        dibujarCamposMapeados();
    }, 150);
}

function resetLayoutMapeador() {
    const wrapper = document.getElementById("editorWorkspaceWrapper");
    const editor = document.getElementById("editorWorkspace");
    const pdfContainer = document.getElementById("iframeMapeadorPreviewContainer");
    const iframe = document.getElementById("iframeMapeadorPreview");
    
    if (wrapper) wrapper.style.gridTemplateColumns = "1fr";
    if (editor) editor.style.display = "flex";
    if (pdfContainer) pdfContainer.style.display = "none";
    if (iframe) iframe.src = "about:blank";
    
    const layoutSelector = document.getElementById("mapeadorLayoutSelector");
    if (layoutSelector) layoutSelector.style.display = "none";
    
    const previewCheckbox = document.getElementById("chkMapeadorPreview");
    if (previewCheckbox) previewCheckbox.checked = false;
    
    const simulationSelect = document.getElementById("mapeadorSimulacionSelect");
    if (simulationSelect) simulationSelect.value = "";
    
    mapeadorSimuladoDatos = null;
    mapeadorPreviewActivo = false;
}
