/**
 * Lógica de Importación y Exportación de Datos Multi-Formato
 * Requiere SheetJS (xlsx), jsPDF, jsPDF-AutoTable, JSZip en el HTML
 */

// ======== EXPORTACIÓN ========

function exportarListado(formato, modulo = 'clientes') {
    let rawData = [];
    let titulo = '';
    let nombreBase = '';
    let datosListos = [];
    
    const fechaStr = new Date().toISOString().split('T')[0];

    if (modulo === 'clientes') {
        if (!window.clientesData || window.clientesData.length === 0) { MQF.toast('No hay datos de clientes para exportar.', 'warning'); return; }
        rawData = window.clientesData;
        titulo = 'Directorio de Clientes';
        nombreBase = `Listado_Clientes_${fechaStr}`;
        datosListos = rawData.map(c => ({
            "ID": c.id,
            "Tipo": c.tipo_persona,
            "Nombre / Razón Social": c.nombre_razon_social,
            "RNC / Cédula": c.rnc,
            "Teléfono": c.telefono || 'N/A',
            "Estatus": c.estatus
        }));
    } else if (modulo === 'usuarios') {
        if (!window.usuariosData || window.usuariosData.length === 0) { MQF.toast('No hay datos de usuarios para exportar.', 'warning'); return; }
        rawData = window.usuariosData;
        titulo = 'Listado de Usuarios';
        nombreBase = `Listado_Usuarios_${fechaStr}`;
        datosListos = rawData.map(u => ({
            "ID": u.id,
            "Username": u.username,
            "Nombre": u.nombre + ' ' + u.apellido,
            "Email": u.email,
            "Perfil": u.nombre_perfil || 'N/A',
            "Estado": u.estado,
            "Creado": u.fecha_creacion ? new Date(u.fecha_creacion).toLocaleDateString() : 'N/A'
        }));
    } else if (modulo === 'cotizaciones') {
        if (!window.cotizacionesData || window.cotizacionesData.length === 0) { MQF.toast('No hay datos de cotizaciones para exportar.', 'warning'); return; }

        rawData = window.cotizacionesData;
        titulo = 'Historial de Cotizaciones';
        nombreBase = `Listado_Cotizaciones_${fechaStr}`;
        datosListos = rawData.map(c => ({
            "N° Cotizacion": c.numero || 'N/A',
            "Tipo": c.tipo || 'N/A',
            "Ramo / Subtipo": c.subtipo || 'N/A',
            "Cliente": c.cliente || 'N/A',
            "Cédula / RNC": c.cedula || 'N/A',
            "Monto Base": c.monto_afianzado || c.suma_asegurada || 'N/A',
            "Prima Total": c.total || c.prima_total || 'N/A',
            "Fecha Emisión": c.fecha ? new Date(c.fecha).toLocaleDateString() : 'N/A'
        }));
    }

    switch (formato) {
        case 'excel': exportarAExcel(datosListos, nombreBase + '.xlsx'); break;
        case 'csv': exportarAExcel(datosListos, nombreBase + '.csv', true); break;
        case 'json': exportarAJSON(rawData, nombreBase + '.json'); break;
        case 'pdf': exportarAPDF(datosListos, nombreBase + '.pdf', titulo); break;
        case 'zip': exportarAZIP(rawData, nombreBase + '.zip'); break;
        default: MQF.toast('Formato no soportado', 'error');
    }
    
    const exportMenu = document.getElementById('exportMenu');
    if (exportMenu) exportMenu.style.display = 'none';
}

function imprimirItem(id, modulo = 'clientes') {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    
    if (modulo === 'clientes') {
        if (!window.clientesData) return;
        const c = window.clientesData.find(x => x.id == id);
        if (!c) { MQF.toast('Cliente no encontrado', 'error'); return; }
        
        doc.setFontSize(20); doc.setTextColor(41, 128, 185); doc.text('Ficha de Cliente', 14, 22);
        doc.setFontSize(10); doc.setTextColor(100); doc.text(`Generado el: ${new Date().toLocaleDateString()}`, 14, 30);
        doc.setDrawColor(200); doc.line(14, 35, 196, 35);
        
        let y = 45;
        const linea = (label, value) => {
            doc.setFontSize(12); doc.setTextColor(40);
            doc.setFont(undefined, 'bold'); doc.text(label + ":", 14, y);
            doc.setFont(undefined, 'normal'); doc.text(value ? value.toString() : 'N/A', 60, y);
            y += 10;
        };
        linea('ID Cliente', c.id);
        linea('Tipo de Persona', c.tipo_persona);
        linea('Nombre / Razón', c.nombre_razon_social);
        linea('RNC / Cédula', c.rnc);
        linea('Teléfono', c.telefono);
        linea('Estatus', c.estatus);
        doc.autoPrint(); 
        
        try {
            const blob = doc.output('blob');
            const url = URL.createObjectURL(blob);
            const win = window.open(url, '_blank');
            if (!win || win.closed || typeof win.closed === 'undefined') {
                throw new Error('Popup blocked');
            }
        } catch (e) {
            console.warn('El navegador bloqueó la ventana emergente de impresión, descargando directamente...', e);
            doc.save(`Ficha_Cliente_${c.id}.pdf`);
        }
        
    } else if (modulo === 'cotizaciones') {
        if (!window.cotizacionesData) return;
        const c = window.cotizacionesData.find(x => x.numero == id);
        if (!c) { MQF.toast('Cotización no encontrada', 'error'); return; }
        
        dibujarCotizacionPDF(doc, c, window.LOGO_MQF_B64 || null, null);
    }
}

function dibujarCotizacionPDF(doc, c, logoImg, printWindow) {
    const formatter = new Intl.NumberFormat('es-DO', { style: 'currency', currency: 'DOP' });
    const fmt = (n) => formatter.format(n || 0);

    const primaryColor = [25, 99, 163];   
    const lightColor = [220, 235, 248];  
    const textColor = [50, 50, 50];

    // Logo
    if (logoImg) {
        doc.addImage(logoImg, 'PNG', 14, 10, 50, 22);
    } else {
        doc.setFontSize(22); doc.setTextColor(...primaryColor); doc.setFont(undefined, 'bold');
        doc.text('MAS QUE FIANZAS', 14, 25);
    }
    
    // Header Right
    doc.setFontSize(9); doc.setTextColor(...textColor); doc.setFont(undefined, 'normal');
    doc.text('Usuario:', 150, 25, {align: 'right'}); doc.text('Generado Sistema', 196, 25, {align: 'right'});
    doc.text('Fecha:', 150, 29, {align: 'right'}); doc.text(new Date().toLocaleString('es-DO'), 196, 29, {align: 'right'});
    doc.text('Vigencia:', 150, 33, {align: 'right'}); doc.text('30 días', 196, 33, {align: 'right'});
    doc.text('Moneda:', 150, 37, {align: 'right'}); doc.text('RD$ Peso Dominicano', 196, 37, {align: 'right'});
    
    // Titulo COTIZACION
    doc.setFontSize(18); doc.setTextColor(...primaryColor); doc.setFont(undefined, 'bold');
    doc.text('COTIZACIÓN', 14, 45);
    doc.setFontSize(14); doc.setTextColor(...textColor); doc.text(c.numero || 'S/N', 14, 52);

    // Saludo
    doc.setFontSize(10); doc.setTextColor(...primaryColor); doc.setFont(undefined, 'bold');
    doc.text(`Estimado Sr(a). ${c.cliente || 'A QUIEN CORRESPONDA'}`, 14, 62);
    doc.setTextColor(...textColor); doc.setFont(undefined, 'normal');
    doc.text('Le agradecemos que haya contado con nosotros para su necesidad de fianza/seguro, y nos satisface', 14, 68);
    doc.text('presentarle estas propuestas para la cobertura de su solicitud basado en los siguientes detalles.', 14, 73);

    // Producto Line
    doc.setFillColor(...lightColor);
    doc.rect(14, 80, 182, 8, 'F');
    doc.setFont(undefined, 'bold'); doc.setTextColor(...primaryColor);
    doc.text(`Producto: ${c.subtipo || c.tipo || 'FIANZA'}`, 16, 85.5);
    // Para Seguro de Ley mostrar "Aseguradora" y "Prima Anual"; para Fianza mostrar "Monto a Afianzar"
    const esSeguroLey = c.tipo && c.tipo.toUpperCase().includes('SEGURO');
    const labelMonto = esSeguroLey ? 'Aseguradora' : 'Monto a Afianzar';
    const valorMonto = esSeguroLey ? (c.aseguradora || 'MULTISEGUROS') : fmt(c.monto_afianzado || c.suma_asegurada || 0);
    doc.text(`${labelMonto}: ${valorMonto}`, 95, 85.5);
    doc.text(`Prima: ${fmt(c.total || c.prima_total || 0)}`, 196, 85.5, {align: 'right'});

    // Coberturas Header
    doc.setFontSize(10); doc.text('Coberturas', 14, 98);
    doc.line(14, 100, 196, 100); 
    doc.line(14, 106, 196, 106); 

    doc.setFontSize(9); doc.setTextColor(...textColor);
    doc.setFont(undefined, 'bold');
    doc.text('Riesgos a Terceros / Detalles', 14, 104); 
    doc.text('Límite RD$', 160, 104, {align: 'right'});
    doc.text('Deducible', 196, 104, {align: 'right'});

    const COVERAGE_PROFILES = {
        'MOTOCICLETA BASICO': [{label:'Daños a la Propiedad Ajena',amount:50000},{label:'Lesiones Corporales o Muerte a 1 Persona',amount:50000},{label:'Lesiones Corporales o Muerte a Más de 1 Persona',amount:100000},{label:'Fianza Judicial',amount:50000}],
        'LIVIANO BASICO': [{label:'Daños a la Propiedad Ajena',amount:100000},{label:'Lesiones Corporales o Muerte a 1 Persona',amount:100000},{label:'Lesiones Corporales o Muerte a Más de 1 Persona',amount:200000},{label:'Lesiones Corporales o Muerte a 1 Pasajero',amount:100000},{label:'Lesiones Corporales o Muerte a Más de 1 Pasajero',amount:200000},{label:'Fianza Judicial',amount:200000},{label:'Riesgo Conductor',amount:50000}],
        'PESADO PLUS': [{label:'Daños a la Propiedad Ajena',amount:300000},{label:'Lesiones Corporales o Muerte a 1 Persona',amount:300000},{label:'Lesiones Corporales o Muerte a Más de 1 Persona',amount:600000},{label:'Lesiones Corporales o Muerte a 1 Pasajero',amount:300000},{label:'Lesiones Corporales o Muerte a Más de 1 Pasajero',amount:600000},{label:'Fianza Judicial',amount:500000},{label:'Riesgo Conductor',amount:50000}]
    };
    const OPTIONAL_LABELS = {
        'ASIST_VIAL_LIV': 'ASISTENCIA VIAL (LIVIANO)', 'ASIST_VIAL_PES': 'ASISTENCIA VIAL (PESADO)', 'CASA_CONDUCTOR': 'CASA DEL CONDUCTOR', 'CENTRO_AUTOMOVILISTA': 'CENTRO DE AUTOMOVILISTA'
    };

    // Filas Cobertura
    doc.setFont(undefined, 'normal');
    let yRow = 112;

    if (c.tipo === 'SEGURO DE LEY' && c.cobertura && COVERAGE_PROFILES[c.cobertura]) {
        COVERAGE_PROFILES[c.cobertura].forEach(p => {
            doc.text(`- ${p.label}`, 14, yRow);
            doc.text(`${fmt(p.amount)}`, 160, yRow, {align: 'right'});
            doc.text('0.00', 196, yRow, {align: 'right'});
            yRow += 6;
        });
        // ==== FIX: Parsear servicios_opcionales si es string (evita bug de +0, +1, +2) ====
        let serviciosOpc = c.servicios_opcionales;
        if (typeof serviciosOpc === 'string') {
            try { serviciosOpc = JSON.parse(serviciosOpc); } catch(e) { serviciosOpc = {}; }
        }
        // Si no es un objeto plano válido, ignorar
        if (!serviciosOpc || typeof serviciosOpc !== 'object' || Array.isArray(serviciosOpc)) {
            serviciosOpc = {};
        }
        if (Object.keys(serviciosOpc).length > 0) {
            Object.keys(serviciosOpc).forEach(k => {
                if (serviciosOpc[k]) {
                    doc.text(`+ ${OPTIONAL_LABELS[k] || k}`, 14, yRow);
                    doc.text('Incluido', 160, yRow, {align: 'right'});
                    doc.text('0.00', 196, yRow, {align: 'right'});
                    yRow += 6;
                }
            });
        }
    } else {
        doc.text(`Aval solidario / Póliza (${c.subtipo || c.tipo || 'General'})`, 14, yRow);
        doc.text(`${fmt(c.monto_afianzado || c.suma_asegurada || 0)}`, 160, yRow, {align: 'right'});
        doc.text('0.00', 196, yRow, {align: 'right'});
        yRow += 6;
        
        if (c.beneficiario) {
            doc.setFont(undefined, 'bold');
            doc.text(`Beneficiario: ${c.beneficiario}`, 14, yRow);
            doc.setFont(undefined, 'normal');
            yRow += 6;
        }
    }
    
    doc.setTextColor(200); doc.line(14, yRow - 2, 196, yRow - 2); doc.setTextColor(...textColor);
    
    // Totales Box
    yRow += 5;
    let yTotales = yRow > 135 ? yRow : 135;
    doc.setFillColor(...lightColor);
    doc.rect(110, yTotales, 86, 25, 'F');
    doc.setFont(undefined, 'bold'); doc.setTextColor(0,0,0);
    if (esSeguroLey) {
        // SEGURO DE LEY: mostrar Prima Base y Prima Total Anual
        doc.text('Cobertura', 115, yTotales + 6); doc.text(c.cobertura || 'N/A', 192, yTotales + 6, {align: 'right'});
        doc.setFont(undefined, 'normal');
        doc.text('Prima Base', 115, yTotales + 12); doc.text(`${fmt(c.prima_base || 0)}`, 192, yTotales + 12, {align: 'right'});
        doc.text('Servicios Opcionales', 115, yTotales + 17); 
        // Calcular suma de servicios opcionales
        const OPTIONAL_PRICES = { ASIST_VIAL_LIV: 2600, ASIST_VIAL_PES: 4600, CASA_CONDUCTOR: 1020, CENTRO_AUTOMOVILISTA: 1020 };
        let serviciosOpc2 = c.servicios_opcionales;
        if (typeof serviciosOpc2 === 'string') { try { serviciosOpc2 = JSON.parse(serviciosOpc2); } catch(e) { serviciosOpc2 = {}; } }
        if (!serviciosOpc2 || typeof serviciosOpc2 !== 'object' || Array.isArray(serviciosOpc2)) serviciosOpc2 = {};
        const sumOpc = Object.keys(serviciosOpc2).reduce((acc, k) => acc + (serviciosOpc2[k] ? (OPTIONAL_PRICES[k] || 0) : 0), 0);
        doc.text(`${fmt(sumOpc)}`, 192, yTotales + 17, {align: 'right'});
        doc.setFont(undefined, 'bold'); doc.setTextColor(0,0,0);
        doc.text('Prima Total Anual', 115, yTotales + 23); doc.text(`${fmt(c.total || c.prima_total || 0)}`, 192, yTotales + 23, {align: 'right'});
    } else {
        // FIANZA: mostrar Monto Afianzado, Prima Neta, Impuestos, Prima Bruta
        doc.text('Monto a Afianzar', 115, yTotales + 6); doc.text(`${fmt(c.monto_afianzado || c.suma_asegurada || 0)}`, 192, yTotales + 6, {align: 'right'});
        doc.setFont(undefined, 'normal');
        doc.text('Prima Neta', 115, yTotales + 12); doc.text(`${fmt(c.prima_base || c.total || c.prima_total || 0)}`, 192, yTotales + 12, {align: 'right'});
        doc.text('Impuestos (ISC)', 115, yTotales + 17); doc.text(`${fmt(c.impuesto || 0)}`, 192, yTotales + 17, {align: 'right'});
        doc.setFont(undefined, 'bold'); doc.setTextColor(0,0,0);
        doc.text('Prima Bruta', 115, yTotales + 23); doc.text(`${fmt(c.total || c.prima_total || 0)}`, 192, yTotales + 23, {align: 'right'});
    }

    // Observaciones
    doc.setFontSize(10); doc.setTextColor(...primaryColor); doc.setFont(undefined, 'bold');
    doc.text('Observaciones', 14, yTotales + 4);
    doc.setFontSize(9); doc.setTextColor(...textColor); doc.setFont(undefined, 'normal');
    doc.text('La aceptación de esta cotización para la Emisión', 14, yTotales + 9);
    doc.text('de la Póliza, dependerá de la inspección de', 14, yTotales + 14);
    doc.text('dicho riesgo, válida por 30 días.', 14, yTotales + 19);

    // Firma
    doc.text('Atentamente,', 14, yTotales + 35);
    doc.setLineWidth(0.5); doc.line(90, yTotales + 65, 140, yTotales + 65);
    doc.setFont(undefined, 'bold'); doc.setFontSize(9);
    doc.text('Firma autorizada', 115, yTotales + 70, {align: 'center'});

    // Footer Address dinámico
    doc.setFont(undefined, 'normal'); doc.setFontSize(8); doc.setTextColor(150, 150, 150);
    doc.text('Ave. 27 de febrero #234, Suite-304, La esperilla, Santo Domingo. DN. Código postal: 10107, República Dominicana', 105, 280, {align: 'center'});
    doc.text('Tel: +1 (829) 629-1952 | Email: info@masquefianzas.com', 105, 284, {align: 'center'});

    doc.autoPrint();
    try {
        const blob = doc.output('blob');
        const url = URL.createObjectURL(blob);
        const win = window.open(url, '_blank');
        if (!win || win.closed || typeof win.closed === 'undefined') {
            throw new Error('Popup blocked');
        }
    } catch (e) {
        console.warn('El navegador bloqueó la ventana emergente del PDF, descargando directamente...', e);
        doc.save(c.numero ? `${c.numero}.pdf` : 'cotizacion.pdf');
    }
}

// Helpers
function exportarAExcel(datos, filename, isCsv = false) {
    if (typeof XLSX === 'undefined') { MQF.toast('Librería SheetJS no encontrada', 'error'); return; }
    const ws = XLSX.utils.json_to_sheet(datos);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, "Datos");
    if (isCsv) XLSX.writeFile(wb, filename, { bookType: "csv" });
    else XLSX.writeFile(wb, filename);
}

function exportarAJSON(datos, filename) {
    const jsonStr = JSON.stringify(datos, null, 2);
    const blob = new Blob([jsonStr], { type: "application/json" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = filename;
    document.body.appendChild(a); a.click(); document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

function exportarAPDF(datos, filename, titulo) {
    if (typeof window.jspdf === 'undefined') { MQF.toast('Librería jsPDF no encontrada', 'error'); return; }
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    
    doc.text(titulo, 14, 15);
    const headers = Object.keys(datos[0]);
    const body = datos.map(row => Object.values(row));
    
    doc.autoTable({
        startY: 20,
        head: [headers],
        body: body,
        theme: 'striped',
        headStyles: { fillColor: [41, 128, 185] }
    });
    doc.save(filename);
}

function exportarAZIP(datos, filename) {
    if (typeof JSZip === 'undefined') { MQF.toast('Librería JSZip no encontrada', 'error'); return; }
    const zip = new JSZip();
    zip.file("backup_datos.json", JSON.stringify(datos, null, 2));
    
    if (typeof XLSX !== 'undefined') {
        const ws = XLSX.utils.json_to_sheet(datos);
        const csvText = XLSX.utils.sheet_to_csv(ws);
        zip.file("backup_datos.csv", csvText);
    }
    
    zip.generateAsync({ type: "blob" }).then(function(content) {
        const url = URL.createObjectURL(content);
        const a = document.createElement('a');
        a.href = url; a.download = filename;
        document.body.appendChild(a); a.click(); document.body.removeChild(a);
        URL.revokeObjectURL(url);
    });
}


// ======== MOTOR DE IMPORTACIÓN Y ASISTENTE PREMIUM EN 3 PASOS ========

// Estilos dinámicos para el asistente premium de importación
function inyectarEstilosAsistente() {
    if (document.getElementById('import-wizard-styles')) return;
    
    const style = document.createElement('style');
    style.id = 'import-wizard-styles';
    style.textContent = `
        .import-wizard-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(8px);
            z-index: 99999; display: flex; align-items: center; justify-content: center;
            font-family: 'Segoe UI', system-ui, sans-serif; color: #1e293b;
            opacity: 0; transition: opacity 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .import-wizard-overlay.show { opacity: 1; }
        .import-wizard-container {
            background: #ffffff; width: 95%; max-width: 750px; border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); overflow: hidden;
            transform: scale(0.95); transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex; flex-direction: column; max-height: 90vh;
        }
        .import-wizard-overlay.show .import-wizard-container { transform: scale(1); }
        .import-wizard-header {
            background: linear-gradient(135deg, #1e293b, #0f172a); padding: 18px 24px;
            color: #ffffff; display: flex; justify-content: space-between; align-items: center;
        }
        .import-wizard-header h3 { margin: 0; font-size: 1.15rem; font-weight: 700; display: flex; align-items: center; gap: 10px; }
        .import-wizard-close {
            background: rgba(255, 255, 255, 0.1); border: none; color: #e2e8f0;
            font-size: 1.25rem; width: 32px; height: 32px; border-radius: 50%;
            cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s;
        }
        .import-wizard-close:hover { background: rgba(255, 255, 255, 0.25); color: #ffffff; }
        .import-wizard-steps-indicator { display: flex; background: #f8fafc; padding: 12px 24px; border-bottom: 1px solid #e2e8f0; gap: 12px; }
        .import-wizard-step-pill {
            flex: 1; display: flex; align-items: center; justify-content: center; gap: 8px;
            padding: 8px 12px; border-radius: 8px; font-size: 0.8rem; font-weight: 700;
            color: #64748b; background: #f1f5f9; transition: all 0.25s ease;
        }
        .import-wizard-step-pill.active { color: #ffffff; background: #4f46e5; }
        .import-wizard-step-pill.completed { color: #15803d; background: #dcfce7; }
        .import-wizard-body { padding: 24px; overflow-y: auto; flex: 1; }
        .import-wizard-view { display: none; }
        .import-wizard-view.active { display: block; }
        .import-wizard-dropzone {
            border: 2px dashed #cbd5e1; border-radius: 12px; padding: 32px 20px;
            text-align: center; cursor: pointer; transition: all 0.2s ease;
            background: #f8fafc; display: flex; flex-direction: column; align-items: center; gap: 10px;
        }
        .import-wizard-dropzone:hover, .import-wizard-dropzone.dragover { border-color: #4f46e5; background: #eef2ff; }
        .import-wizard-dropzone-icon { font-size: 2.25rem; color: #4f46e5; }
        .import-wizard-instructions {
            background: #eff6ff; border-left: 4px solid #3b82f6; padding: 14px 18px;
            border-radius: 0 8px 8px 0; margin-bottom: 20px; font-size: 0.88rem; line-height: 1.45;
        }
        .import-wizard-instructions h4 { margin: 0 0 6px 0; color: #1e3a8a; font-weight: 700; }
        .import-wizard-instructions ul { margin: 0; padding-left: 18px; }
        .import-wizard-preview-table-container { margin-top: 18px; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; }
        .import-wizard-preview-table { width: 100%; border-collapse: collapse; font-size: 0.78rem; }
        .import-wizard-preview-table th { background: #f1f5f9; padding: 8px 12px; text-align: left; font-weight: 600; color: #475569; border-bottom: 1px solid #e2e8f0; }
        .import-wizard-preview-table td { padding: 8px 12px; border-bottom: 1px solid #e2e8f0; color: #64748b; }
        .import-wizard-spinner-container { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 36px 20px; gap: 14px; }
        .import-wizard-spinner {
            width: 44px; height: 44px; border: 4px solid #f1f5f9; border-top-color: #4f46e5;
            border-radius: 50%; animation: import-spin 1s linear infinite;
        }
        @keyframes import-spin { to { transform: rotate(360deg); } }
        .import-wizard-alert {
            background: #fffbeb; border: 1.5px solid #fef3c7; border-left: 5px solid #d97706;
            padding: 16px; border-radius: 8px; margin-bottom: 20px; color: #78350f; font-size: 0.88rem; line-height: 1.5;
        }
        .import-wizard-metrics-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 20px; }
        .import-wizard-metric-card { padding: 14px; border-radius: 10px; text-align: center; background: #f8fafc; border: 1px solid #e2e8f0; }
        .import-wizard-metric-card.success { background: #f0fdf4; border-color: #bbf7d0; }
        .import-wizard-metric-card.danger { background: #fef2f2; border-color: #fecaca; }
        .import-wizard-metric-value { font-size: 1.6rem; font-weight: 700; margin-bottom: 2px; color: #334155; }
        .import-wizard-metric-value.success { color: #16a34a; }
        .import-wizard-metric-value.danger { color: #dc2626; }
        .import-wizard-metric-label { font-size: 0.75rem; color: #64748b; font-weight: 700; }
        .import-wizard-footer { padding: 14px 24px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 10px; }
    `;
    document.head.appendChild(style);
}

// Sinónimos para normalización de columnas
const CANONICAL_CLIENTES = {
    tipo_persona: ['tipo persona', 'tipo', 'persona', 'person type', 'type'],
    nombre_razon_social: ['nombre razon social', 'nombre', 'nombre completo', 'razon social', 'cliente', 'fullname', 'name', 'nombre razon social', 'nombre_razon_social'],
    rnc: ['rnc cedula', 'rnc', 'cedula', 'documento', 'identificacion', 'id', 'rnc / cedula', 'rnc / cedula', 'rnc_cedula'],
    telefono: ['telefono', 'tel', 'phone', 'celular'],
    correo: ['correo', 'email', 'e mail', 'correo electronico', 'mail', 'correo_electronico'],
    direccion: ['direccion', 'direccion fisica', 'address', 'direccion_fisica'],
    estatus: ['estatus', 'estado', 'status', 'active']
};

const CANONICAL_USUARIOS = {
    cedula: ['cedula', 'rnc', 'documento', 'identificacion', 'id', 'rnc / cedula'],
    username: ['usuario', 'username', 'login', 'user'],
    nombre: ['nombre', 'first name', 'name', 'nombre completo'],
    apellido: ['apellido', 'last name', 'surname'],
    email: ['correo', 'email', 'e mail', 'correo electronico', 'mail'],
    telefono: ['telefono', 'tel', 'phone', 'celular'],
    perfil_id: ['perfil', 'rol', 'role', 'profile', 'perfil_id'],
    estado: ['estado', 'estatus', 'status', 'active'],
    es_comisionante: ['es comisionante', 'comisionante'],
    porcentaje_comision: ['porcentaje comision', 'comision', 'comision directa', 'porcentaje_comision'],
    porcentaje_comision_red: ['porcentaje comision red', 'comision red', 'porcentaje_comision_red'],
    referente_id: ['referente', 'supervisor', 'parent', 'referente_id']
};

function normalizarTexto(txt) {
    if (!txt) return '';
    return txt.toString().toLowerCase().trim()
        .normalize("NFD").replace(/[\u0300-\u036f]/g, "") // remover acentos
        .replace(/[^a-z0-9]/g, ' ') // caracteres no alfanuméricos
        .replace(/\s+/g, ' ').trim();
}

function encontrarColumna(headers, canonicalMap, key) {
    const synonyms = canonicalMap[key];
    for (let h of headers) {
        const normH = normalizarTexto(h);
        if (normH === key || synonyms.includes(normH)) {
            return h;
        }
    }
    // Substring fallback
    for (let h of headers) {
        const normH = normalizarTexto(h);
        for (let syn of synonyms) {
            if (normH.includes(syn) || syn.includes(normH)) {
                return h;
            }
        }
    }
    return null;
}

// Ventana principal del Wizard
function abrirAsistenteImportacion(modulo) {
    inyectarEstilosAsistente();

    // Eliminar modal previo si existe
    const prevModal = document.getElementById('import-wizard-modal');
    if (prevModal) prevModal.remove();

    const labelModulo = modulo === 'usuarios' ? 'Usuarios' : 'Clientes';
    
    // Crear el contenedor principal
    const modal = document.createElement('div');
    modal.id = 'import-wizard-modal';
    modal.className = 'import-wizard-overlay';
    
    // Contenido del modal
    modal.innerHTML = `
        <div class="import-wizard-container">
            <div class="import-wizard-header">
                <h3><i class="fa-solid fa-file-import"></i> Asistente de Importación: ${labelModulo}</h3>
                <button class="import-wizard-close" id="wizard-close-btn">&times;</button>
            </div>
            
            <div class="import-wizard-steps-indicator">
                <div class="import-wizard-step-pill active" id="pill-step-1">
                    <span class="step-num">1</span> Preparar & Seleccionar
                </div>
                <div class="import-wizard-step-pill" id="pill-step-2">
                    <span class="step-num">2</span> Cargar & Procesar
                </div>
                <div class="import-wizard-step-pill" id="pill-step-3">
                    <span class="step-num">3</span> Resultado & Logs
                </div>
            </div>
            
            <div class="import-wizard-body">
                <!-- PASO 1: SELECCIONAR Y PREVISUALIZAR -->
                <div class="import-wizard-view active" id="view-step-1">
                    <div class="import-wizard-instructions">
                        <h4><i class="fa-solid fa-circle-info" style="color:#2563eb;"></i> Instrucciones de formato:</h4>
                        <ul>
                            <li>El archivo debe ser de tipo <strong>Excel (.xlsx)</strong> o <strong>CSV</strong>.</li>
                            <li>Las columnas se mapearán automáticamente buscando sinónimos inteligentes.</li>
                            ${modulo === 'clientes' 
                              ? `<li><strong>Campos sugeridos:</strong> Nombre / Razón Social, RNC / Cédula, Teléfono, Correo, Dirección.</li>`
                              : `<li><strong>Campos sugeridos:</strong> Cédula, Nombre, Apellido, Usuario (login), Correo electrónico, Teléfono.</li>`}
                            <li>Cualquier campo obligatorio vacío será **autocompletado** de manera segura para evitar errores en la base de datos.</li>
                        </ul>
                    </div>
                    
                    <div class="import-wizard-dropzone" id="dropzone">
                        <i class="fa-solid fa-file-excel import-wizard-dropzone-icon"></i>
                        <p style="margin: 0; font-weight:600;">Arrastra tu archivo aquí o haz clic para buscar</p>
                        <span style="font-size: 0.78rem; color:#64748b;">Soporta archivos .xlsx y .csv</span>
                        <input type="file" id="wizard-file-input" accept=".xlsx, .csv" style="display:none;">
                    </div>
                    
                    <div id="preview-container" style="display:none; margin-top:20px;">
                        <h4 style="margin: 0 0 10px 0; font-size:0.9rem; font-weight:700;"><i class="fa-solid fa-table"></i> Vista Previa (Primeras 3 filas normalizadas):</h4>
                        <div class="import-wizard-preview-table-container">
                            <table class="import-wizard-preview-table" id="preview-table">
                                <thead><tr id="preview-headers"></tr></thead>
                                <tbody id="preview-rows"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- PASO 2: CARGANDO -->
                <div class="import-wizard-view" id="view-step-2">
                    <div class="import-wizard-spinner-container">
                        <div class="import-wizard-spinner"></div>
                        <h4 style="margin: 10px 0 0 0; font-size:1rem; font-weight:700;" id="spinner-text">Cargando el archivo...</h4>
                        <p style="color:#64748b; font-size:0.85rem; margin:0;" id="spinner-subtext">procesando y normalizando la información de los registros...</p>
                    </div>
                </div>
                
                <!-- PASO 3: RESULTADOS -->
                <div class="import-wizard-view" id="view-step-3">
                    <div class="import-wizard-alert">
                        <strong>⚠️ IMPORTANTE:</strong> Los campos que no existían o no tenían información en el archivo de origen fueron autocompletados. Se recomienda revisar y actualizar estos registros mediante la acción de 'Modificación' (Editar) en el listado.
                    </div>
                    
                    <div class="import-wizard-metrics-grid">
                        <div class="import-wizard-metric-card">
                            <div class="import-wizard-metric-value" id="metric-total">0</div>
                            <div class="import-wizard-metric-label">Registros Procesados</div>
                        </div>
                        <div class="import-wizard-metric-card success">
                            <div class="import-wizard-metric-value success" id="metric-success">0</div>
                            <div class="import-wizard-metric-label">Cargados con éxito</div>
                        </div>
                        <div class="import-wizard-metric-card danger">
                            <div class="import-wizard-metric-value danger" id="metric-failed">0</div>
                            <div class="import-wizard-metric-label">Fallas / Omitidos</div>
                        </div>
                    </div>
                    
                    <div style="display:flex; justify-content:center; gap:12px; margin-top:20px;">
                        <button class="mqf-btn mqf-btn--secondary" id="download-log-btn" style="padding:10px 20px;">
                            <i class="fa-solid fa-file-lines"></i> Descargar Log Detallado (.txt)
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="import-wizard-footer">
                <button class="mqf-btn mqf-btn--secondary" id="wizard-cancel-btn">Cancelar</button>
                <button class="mqf-btn mqf-btn--primary" id="wizard-next-btn" disabled>Siguiente <i class="fa-solid fa-arrow-right"></i></button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Forzar reflow para animación
    setTimeout(() => modal.classList.add('show'), 10);
    
    // Variables de control de estado del Wizard
    let fileLoaded = null;
    let parsedData = [];
    let normalizedPayloads = [];
    let logsText = "";
    let metrics = { total: 0, success: 0, failed: 0 };
    
    // Elementos de la interfaz
    const dropzone = modal.querySelector('#dropzone');
    const fileInput = modal.querySelector('#wizard-file-input');
    const cancelBtn = modal.querySelector('#wizard-cancel-btn');
    const nextBtn = modal.querySelector('#wizard-next-btn');
    const closeBtn = modal.querySelector('#wizard-close-btn');
    const downloadLogBtn = modal.querySelector('#download-log-btn');
    
    const pill1 = modal.querySelector('#pill-step-1');
    const pill2 = modal.querySelector('#pill-step-2');
    const pill3 = modal.querySelector('#pill-step-3');
    
    const view1 = modal.querySelector('#view-step-1');
    const view2 = modal.querySelector('#view-step-2');
    const view3 = modal.querySelector('#view-step-3');

    // Cerrar el modal
    const cerrarModal = () => {
        modal.classList.remove('show');
        setTimeout(() => modal.remove(), 300);
    };
    
    closeBtn.addEventListener('click', cerrarModal);
    cancelBtn.addEventListener('click', cerrarModal);
    
    // Manejo de eventos de Drag & Drop
    dropzone.addEventListener('click', () => fileInput.click());
    dropzone.addEventListener('dragover', (e) => { e.preventDefault(); dropzone.classList.add('dragover'); });
    dropzone.addEventListener('dragleave', () => dropzone.classList.remove('dragover'));
    dropzone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropzone.classList.remove('dragover');
        if (e.dataTransfer.files.length > 0) {
            cargarYProcesarArchivo(e.dataTransfer.files[0]);
        }
    });
    
    fileInput.addEventListener('change', (e) => {
        if (e.target.files.length > 0) {
            cargarYProcesarArchivo(e.target.files[0]);
        }
    });

    // Motor de lectura y normalización
    function cargarYProcesarArchivo(file) {
        if (typeof XLSX === 'undefined') {
            MQF.toast('La librería SheetJS no está cargada.', 'error');
            return;
        }
        
        fileLoaded = file;
        dropzone.innerHTML = `
            <i class="fa-solid fa-file-circle-check" style="font-size:2.25rem; color:#16a34a;"></i>
            <p style="margin: 0; font-weight:600; color:#16a34a;">¡Archivo seleccionado!</p>
            <span style="font-size: 0.82rem; color:#334155;">${file.name} (${(file.size/1024).toFixed(1)} KB)</span>
            <button class="mqf-btn mqf-btn--secondary mqf-btn--sm" style="margin-top:10px;" id="change-file-btn">Cambiar Archivo</button>
        `;
        
        modal.querySelector('#change-file-btn').addEventListener('click', (e) => {
            e.stopPropagation();
            fileInput.click();
        });
        
        const reader = new FileReader();
        reader.onload = function(e) {
            try {
                const data = new Uint8Array(e.target.result);
                const workbook = XLSX.read(data, { type: 'array' });
                const firstSheetName = workbook.SheetNames[0];
                const worksheet = workbook.Sheets[firstSheetName];
                parsedData = XLSX.utils.sheet_to_json(worksheet, { defval: "" });
                
                if (parsedData.length === 0) {
                    MQF.toast('El archivo está completamente vacío.', 'warning');
                    resetearPaso1();
                    return;
                }
                
                normalizarDatos();
            } catch (err) {
                console.error(err);
                MQF.toast('Error al leer el archivo. Asegúrese de que es un Excel o CSV válido.', 'error');
                resetearPaso1();
            }
        };
        reader.readAsArrayBuffer(file);
    }

    function resetearPaso1() {
        fileLoaded = null;
        parsedData = [];
        normalizedPayloads = [];
        dropzone.innerHTML = `
            <i class="fa-solid fa-file-excel import-wizard-dropzone-icon"></i>
            <p style="margin: 0; font-weight:600;">Arrastra tu archivo aquí o haz clic para buscar</p>
            <span style="font-size: 0.78rem; color:#64748b;">Soporta archivos .xlsx y .csv</span>
            <input type="file" id="wizard-file-input" accept=".xlsx, .csv" style="display:none;">
        `;
        modal.querySelector('#preview-container').style.display = 'none';
        nextBtn.setAttribute('disabled', 'true');
    }

    function normalizarDatos() {
        // Encontrar cabeceras originales del excel
        const firstRow = parsedData[0];
        const headers = Object.keys(firstRow);
        
        normalizedPayloads = [];
        logsText = `=== LOG DE IMPORTACION MASIVA: ${labelModulo.toUpperCase()} ===\r\n`;
        logsText += `Fecha de Importación: ${new Date().toLocaleString()}\r\n`;
        logsText += `Archivo: ${fileLoaded.name}\r\n`;
        logsText += `--------------------------------------------------------\r\n\r\n`;
        
        const map = modulo === 'usuarios' ? CANONICAL_USUARIOS : CANONICAL_CLIENTES;
        
        parsedData.forEach((row, idx) => {
            const rowNum = idx + 1;
            const logPre = `Fila #${rowNum}: `;
            let logsFila = [];
            
            if (modulo === 'clientes') {
                // CLIENTES
                const colTipo = encontrarColumna(headers, map, 'tipo_persona');
                const colNombre = encontrarColumna(headers, map, 'nombre_razon_social');
                const colRnc = encontrarColumna(headers, map, 'rnc');
                const colTel = encontrarColumna(headers, map, 'telefono');
                const colCorreo = encontrarColumna(headers, map, 'correo');
                const colDir = encontrarColumna(headers, map, 'direccion');
                const colEstatus = encontrarColumna(headers, map, 'estatus');
                
                let rawNombre = colNombre ? row[colNombre] : '';
                let rawRnc = colRnc ? row[colRnc] : '';
                let rawTipo = colTipo ? row[colTipo] : '';
                
                // Mapear Tipo Persona
                let tipo_persona = 'Fisica';
                if (rawTipo) {
                    const cleanTipo = normalizarTexto(rawTipo);
                    if (cleanTipo.includes('jurid') || cleanTipo.includes('empresa') || cleanTipo.includes('company')) {
                        tipo_persona = 'Juridica';
                    }
                } else {
                    logsFila.push("El tipo de persona estaba vacío, se asignó 'Fisica' por defecto.");
                }
                
                // Mapear Nombre
                let nombre_razon_social = rawNombre ? rawNombre.toString().trim() : '';
                if (!nombre_razon_social) {
                    nombre_razon_social = `Importado - ${new Date().toLocaleDateString('es-DO')}`;
                    logsFila.push(`El nombre del cliente estaba vacío, se autocompletó como '${nombre_razon_social}'.`);
                }
                
                // Mapear RNC
                let rnc = rawRnc ? rawRnc.toString().replace(/[^a-zA-Z0-9]/g, '').trim() : '';
                if (!rnc) {
                    rnc = 'GEN-' + Math.floor(10000000 + Math.random() * 90000000);
                    logsFila.push(`RNC/Cédula no especificado, se autocompletó con el ID aleatorio '${rnc}'.`);
                }
                
                const normRow = {
                    tipo_persona: tipo_persona,
                    nombre_razon_social: nombre_razon_social,
                    rnc: rnc,
                    telefono: colTel && row[colTel] ? row[colTel].toString().trim() : '',
                    correo: colCorreo && row[colCorreo] ? colCorreo.toString().trim() : '',
                    direccion: colDir && row[colDir] ? row[colDir].toString().trim() : '',
                    estatus: colEstatus && row[colEstatus] ? row[colEstatus].toString().trim() : 'Activo'
                };
                
                normalizedPayloads.push(normRow);
                
                if (logsFila.length > 0) {
                    logsText += `${logPre}Cliente '${nombre_razon_social}' procesado con advertencias:\r\n` + logsFila.map(l => `   - ${l}`).join('\r\n') + '\r\n';
                } else {
                    logsText += `${logPre}Cliente '${nombre_razon_social}' procesado y normalizado con éxito sin requerir cambios.\r\n`;
                }
                
            } else {
                // USUARIOS
                const colCedula = encontrarColumna(headers, map, 'cedula');
                const colNombre = encontrarColumna(headers, map, 'nombre');
                const colApellido = encontrarColumna(headers, map, 'apellido');
                const colUsername = encontrarColumna(headers, map, 'username');
                const colEmail = encontrarColumna(headers, map, 'email');
                const colTel = encontrarColumna(headers, map, 'telefono');
                const colPerfil = encontrarColumna(headers, map, 'perfil_id');
                const colEstado = encontrarColumna(headers, map, 'estado');
                
                let rawNombre = colNombre ? row[colNombre] : '';
                let rawApellido = colApellido ? row[colApellido] : '';
                let rawCedula = colCedula ? row[colCedula] : '';
                let rawUsername = colUsername ? row[colUsername] : '';
                let rawEmail = colEmail ? row[colEmail] : '';
                
                let nombre = rawNombre ? rawNombre.toString().trim() : 'Usuario';
                if (!rawNombre) logsFila.push("Nombre vacío, se asignó 'Usuario' por defecto.");
                
                let apellido = rawApellido ? rawApellido.toString().trim() : 'Importado';
                if (!rawApellido) logsFila.push("Apellido vacío, se asignó 'Importado' por defecto.");
                
                let cedula = rawCedula ? rawCedula.toString().replace(/[^a-zA-Z0-9]/g, '').trim() : '';
                if (!cedula) {
                    cedula = 'GEN-' + Math.floor(10000000 + Math.random() * 90000000);
                    logsFila.push(`Cédula vacía, se autocompletó como '${cedula}'.`);
                }
                
                // Generar Username seguro: nombre.apellido + sufijo aleatorio
                let username = rawUsername ? normalizarTexto(rawUsername).replace(/\s+/g, '') : '';
                if (!username) {
                    const normNom = normalizarTexto(nombre).replace(/\s+/g, '');
                    const normApe = normalizarTexto(apellido).replace(/\s+/g, '');
                    const randSuffix = Math.floor(100 + Math.random() * 900);
                    username = `${normNom}.${normApe}${randSuffix}`;
                    logsFila.push(`Nombre de usuario (login) autogenerado como '${username}' para cumplir unicidad.`);
                }
                
                // Email corporativo fallback
                let email = rawEmail ? rawEmail.toString().trim() : '';
                if (!email) {
                    email = `${username}@masquefianzas.com`;
                    logsFila.push(`Correo vacío, se asignó la cuenta corporativa '${email}'.`);
                }
                
                // Mapear Perfil ID
                let perfil_id = 2; // Agente Comercial
                if (colPerfil && row[colPerfil]) {
                    const cleanP = normalizarTexto(row[colPerfil]);
                    if (cleanP.includes('admin')) perfil_id = 1;
                    else if (cleanP.includes('agente') || cleanP.includes('comercial')) perfil_id = 2;
                    else if (cleanP.includes('sup') && cleanP.includes('com')) perfil_id = 3;
                    else if (cleanP.includes('zona')) perfil_id = 4;
                    else if (cleanP.includes('socio') || cleanP.includes('pdv')) perfil_id = 5;
                } else {
                    logsFila.push("Perfil de acceso no definido, se asignó 'Agente Comercial' por defecto.");
                }
                
                const normRow = {
                    cedula: cedula,
                    nombre: nombre,
                    apellido: apellido,
                    username: username,
                    email: email,
                    telefono: colTel && row[colTel] ? row[colTel].toString().trim() : '',
                    perfil_id: perfil_id,
                    estado: colEstado && row[colEstado] ? row[colEstado].toString().trim().toLowerCase() : 'activo'
                };
                
                normalizedPayloads.push(normRow);
                
                if (logsFila.length > 0) {
                    logsText += `${logPre}Usuario @${username} procesado con advertencias:\r\n` + logsFila.map(l => `   - ${l}`).join('\r\n') + '\r\n';
                } else {
                    logsText += `${logPre}Usuario @${username} procesado y normalizado con éxito sin requerir cambios.\r\n`;
                }
            }
        });
        
        // Renderizar la previsualización de las primeras 3 filas
        const headTr = modal.querySelector('#preview-headers');
        const rowsTbody = modal.querySelector('#preview-rows');
        
        headTr.innerHTML = '';
        rowsTbody.innerHTML = '';
        
        const previewKeys = modulo === 'clientes' 
            ? ['tipo_persona', 'nombre_razon_social', 'rnc', 'telefono', 'correo'] 
            : ['cedula', 'nombre', 'apellido', 'username', 'email'];
            
        const previewLabels = modulo === 'clientes'
            ? ['Tipo Persona', 'Nombre / Razón Social', 'RNC / Cédula', 'Teléfono', 'Correo']
            : ['Cédula / RNC', 'Nombre', 'Apellido', 'Usuario (login)', 'Email'];
            
        previewLabels.forEach(lbl => {
            const th = document.createElement('th');
            th.textContent = lbl;
            headTr.appendChild(th);
        });
        
        const rowsToPreview = normalizedPayloads.slice(0, 3);
        rowsToPreview.forEach(row => {
            const tr = document.createElement('tr');
            previewKeys.forEach(k => {
                const td = document.createElement('td');
                td.textContent = row[k] || '-';
                tr.appendChild(td);
            });
            rowsTbody.appendChild(tr);
        });
        
        modal.querySelector('#preview-container').style.display = 'block';
        nextBtn.removeAttribute('disabled');
    }
    
    // Ir al Paso 2
    nextBtn.addEventListener('click', () => {
        mostrarPaso(2);
        ejecutarImportacionAPI();
    });
    
    function mostrarPaso(num) {
        pill1.className = 'import-wizard-step-pill' + (num === 1 ? ' active' : (num > 1 ? ' completed' : ''));
        pill2.className = 'import-wizard-step-pill' + (num === 2 ? ' active' : (num > 2 ? ' completed' : ''));
        pill3.className = 'import-wizard-step-pill' + (num === 3 ? ' active' : '');
        
        view1.className = 'import-wizard-view' + (num === 1 ? ' active' : '');
        view2.className = 'import-wizard-view' + (num === 2 ? ' active' : '');
        view3.className = 'import-wizard-view' + (num === 3 ? ' active' : '');
        
        if (num === 1) {
            cancelBtn.style.display = 'block';
            cancelBtn.textContent = 'Cancelar';
            nextBtn.style.display = 'block';
            nextBtn.textContent = 'Siguiente';
        } else if (num === 2) {
            cancelBtn.style.display = 'none';
            nextBtn.style.display = 'none';
        } else if (num === 3) {
            cancelBtn.style.display = 'none';
            nextBtn.style.display = 'block';
            nextBtn.textContent = 'Finalizar & Cerrar';
            nextBtn.removeAttribute('disabled');
            
            // Re-vincular botón finalizar para recargar tabla y cerrar
            nextBtn.removeEventListener('click', null);
            nextBtn.addEventListener('click', () => {
                cerrarModal();
                if (modulo === 'usuarios' && typeof cargarUsuarios === 'function') cargarUsuarios();
                if (modulo === 'clientes' && typeof cargarClientes === 'function') cargarClientes();
            });
        }
    }
    
    // Paso 2: Ejecución de la importación a través de api.solicitud()
    async function ejecutarImportacionAPI() {
        const endpoint = modulo === 'usuarios' ? '/usuarios.php/importar' : '/clientes.php/importar';
        const payloadKey = modulo === 'usuarios' ? 'usuarios' : 'clientes';
        
        const dataToSend = {};
        dataToSend[payloadKey] = normalizedPayloads;
        
        const spinnerTxt = modal.querySelector('#spinner-text');
        const spinnerSub = modal.querySelector('#spinner-subtext');
        
        spinnerTxt.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Cargando data...`;
        spinnerSub.textContent = `procederemos a cargar la data esto tomara unos minutos...`;
        
        try {
            // Llamar usando api-client.js centralizado que inyecta el Bearer token
            const respuesta = await api.solicitud(endpoint, 'POST', dataToSend);
            
            if (respuesta.exito) {
                metrics.total = normalizedPayloads.length;
                metrics.success = parseInt(respuesta.insertados) || normalizedPayloads.length;
                metrics.failed = parseInt(respuesta.errores) || 0;
                
                logsText += `\r\n\r\n=== RESULTADO FINAL ===\r\n`;
                logsText += `Total procesados: ${metrics.total}\r\n`;
                logsText += `Insertados con éxito: ${metrics.success}\r\n`;
                logsText += `Errores / Omitidos: ${metrics.failed}\r\n`;
                if (respuesta.detalles && Array.isArray(respuesta.detalles)) {
                    logsText += `\r\nDetalles de fallas reportadas por el backend:\r\n`;
                    respuesta.detalles.forEach(d => {
                        logsText += `   - ${d}\r\n`;
                    });
                }
                
                // Sincronizar UI métricas del paso 3
                modal.querySelector('#metric-total').textContent = metrics.total;
                modal.querySelector('#metric-success').textContent = metrics.success;
                modal.querySelector('#metric-failed').textContent = metrics.failed;
                
                MQF.toast(`Importación completada: ${metrics.success} exitosos.`, 'success');
                mostrarPaso(3);
            } else {
                MQF.toast('Error en la carga: ' + (respuesta.mensaje || 'Error desconocido'), 'error');
                mostrarPaso(1);
            }
        } catch (error) {
            console.error(error);
            MQF.toast('Error de red al intentar importar los datos.', 'error');
            mostrarPaso(1);
        }
    }
    
    // Descarga de Log de Operaciones (.txt)
    downloadLogBtn.addEventListener('click', () => {
        const blob = new Blob([logsText], { type: 'text/plain;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `Log_Importacion_${labelModulo}_${new Date().toISOString().split('T')[0]}.txt`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    });
}


function importarDatos(event) {
    // Mantener compatibilidad por si alguna llamada inline lo requiere, redirigiendo a la interfaz premium
    const modulo = window.location.pathname.includes('usuarios') ? 'usuarios' : 'clientes';
    abrirAsistenteImportacion(modulo);
}

document.addEventListener('click', function(event) {
    const exportMenu = document.getElementById('exportMenu');
    if (exportMenu && event.target.closest('.dropdown-export') === null) {
        exportMenu.style.display = 'none';
    }
});

function importarCotizaciones(event) {
    const file = event.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = async function(e) {
        try {
            const data = new Uint8Array(e.target.result);
            const workbook = XLSX.read(data, { type: 'array' });
            const json = XLSX.utils.sheet_to_json(workbook.Sheets[workbook.SheetNames[0]]);
            
            if (json.length === 0) { MQF.toast('Archivo vacío.', 'warning'); return; }
            
            const payload = json.map(row => ({
                numero: row['N° Cotizacion'] || row['numero'] || ('F-IMP-' + Math.floor(Math.random()*9000)),
                tipo: row['Tipo'] || row['tipo'] || 'SEGURO',
                subtipo: row['Ramo / Subtipo'] || row['subtipo'] || '',
                cliente: row['Cliente'] || row['cliente'] || 'Importado',
                cedula: row['Cédula / RNC'] || row['cedula'] || '',
                beneficiario: row['Beneficiario'] || row['beneficiario'] || '',
                monto_afianzado: parseFloat(row['Monto Base'] || row['monto_afianzado'] || row['monto'] || 0),
                total: parseFloat(row['Prima Total'] || row['total'] || row['prima_total'] || 0),
                fecha: row['Fecha Emisión'] ? new Date(row['Fecha Emisión']).toISOString() : new Date().toISOString()
            }));
            
            if (await MQF.confirm(`¿Importar ${payload.length} cotizaciones a la base de datos central?`, {type: 'primary'})) {
                MQF.toast('Procesando importación central...', 'info');
                const res = await api.solicitud('/cotizaciones.php?action=importar', 'POST', payload);
                if (res.exito) {
                    MQF.toast(`¡${payload.length} cotizaciones importadas con éxito!`, 'success');
                    if (typeof cargarHistorial === 'function') cargarHistorial();
                    if (typeof loadHistorial === 'function') loadHistorial();
                } else {
                    MQF.toast('Error al importar cotizaciones: ' + (res.mensaje || 'Error del servidor'), 'error');
                }
            }
        } catch (error) {
            console.error("Import error:", error);
            MQF.toast('Error procesando el archivo Excel/CSV.', 'error');
        } finally {
            event.target.value = null;
        }
    };
    reader.readAsArrayBuffer(file);
}

window.exportarListado = exportarListado;
window.exportarAExcel = exportarAExcel;
window.exportarAJSON = exportarAJSON;
window.exportarAPDF = exportarAPDF;
window.exportarAZIP = exportarAZIP;
window.imprimirItem = imprimirItem;
window.importarDatos = importarDatos;
window.importarCotizaciones = importarCotizaciones;
window.abrirAsistenteImportacion = abrirAsistenteImportacion;
